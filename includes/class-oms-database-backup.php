<?php
/**
 * Database Backup class for critical table backups
 *
 * Handles backing up critical WordPress tables before cleanup operations.
 *
 * @package ObfuscatedMalwareScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * Database Backup class responsible for backing up critical tables
 */
class OMS_Database_Backup {
	/**
	 * Logger instance
	 *
	 * @var OMS_Logger
	 */
	private $logger;

	/**
	 * Backup directory
	 *
	 * @var string
	 */
	private $backup_dir;

	/**
	 * Critical tables to backup
	 *
	 * @var array
	 */
	private $critical_tables = array(
		'options',
		'posts',
		'postmeta',
		'users',
		'usermeta',
		'comments',
		'commentmeta',
	);

	/**
	 * Constructor
	 *
	 * @param OMS_Logger|null $logger Logger instance. Optional for backward compatibility.
	 */
	public function __construct( $logger = null ) {
		$this->logger     = $logger ? $logger : new OMS_Logger();
		$this->backup_dir = WP_CONTENT_DIR . '/oms-db-backups';

		// Ensure backup directory exists.
		$this->ensure_backup_directory();
	}

	/**
	 * Ensure backup directory exists and is secure
	 */
	private function ensure_backup_directory() {
		if ( ! is_dir( $this->backup_dir ) ) {
			wp_mkdir_p( $this->backup_dir );
		}

		// Create .htaccess to protect backups.
		$htaccess_file = $this->backup_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$result = file_put_contents( $htaccess_file, "deny from all\n" );
			if ( false === $result ) {
				$this->logger->error( sprintf( 'Failed to create .htaccess file for database backup directory: %s', esc_html( $htaccess_file ) ) );
			}
		}
	}

	/**
	 * Validate and sanitize database table name
	 *
	 * @param string $table_name Table name to validate.
	 * @return string|false Sanitized table name or false if invalid.
	 */
	private function validate_db_table_name( $table_name ) {
		if ( ! is_string( $table_name ) || empty( $table_name ) ) {
			return false;
		}

		// Remove backticks if present.
		$table_name = str_replace( '`', '', $table_name );

		// Check against whitelist (without prefix).
		global $wpdb;
		$table_base = str_replace( $wpdb->prefix, '', $table_name );
		if ( in_array( $table_base, $this->critical_tables, true ) ) {
			return $table_name;
		}

		return false;
	}

	/**
	 * Backup critical tables
	 *
	 * @return array Backup result.
	 */
	public function backup_critical_tables() {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			$this->logger->error( 'WordPress database object not available for backup' );
			return array(
				'success' => false,
				'message' => 'Database object not available',
			);
		}

		$this->logger->info( 'Starting critical table backup' );

		$backup_files = array();
		$timestamp    = gmdate( 'Y-m-d-H-i-s' );
		$backup_id    = 'backup_' . $timestamp;

		try {
			$prefix = $wpdb->prefix;

			foreach ( $this->critical_tables as $table_name ) {
				$full_table_name = $prefix . $table_name;

				// Check if table exists.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database backup requires direct query, information_schema queries don't benefit from caching.
				$table_exists = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s',
						defined( 'DB_NAME' ) ? DB_NAME : '',
						$full_table_name
					)
				);

				if ( ! $table_exists ) {
					$this->logger->warning( sprintf( 'Table %s does not exist, skipping backup', esc_html( $full_table_name ) ) );
					continue;
				}

				// Backup table data.
				$backup_file = $this->backup_table( $full_table_name, $backup_id );
				if ( $backup_file ) {
					$backup_files[ $table_name ] = $backup_file;
				}
			}

			// Save backup manifest.
			$manifest = array(
				'backup_id'  => $backup_id,
				'timestamp'  => $timestamp,
				'tables'     => $backup_files,
				'db_name'    => defined( 'DB_NAME' ) ? DB_NAME : '',
				'wp_version' => get_bloginfo( 'version' ),
			);

			$manifest_file = $this->backup_dir . '/' . $backup_id . '_manifest.json';
			$manifest_json = wp_json_encode( $manifest, JSON_PRETTY_PRINT );
			$result        = file_put_contents( $manifest_file, $manifest_json );
			if ( false === $result ) {
				$this->logger->error( sprintf( 'Failed to save backup manifest: %s', esc_html( $manifest_file ) ) );
			}

			// Store backup ID for potential rollback.
			update_option( 'oms_last_db_backup_id', $backup_id, false );

			$this->logger->info( sprintf( 'Critical table backup completed: %d tables backed up', count( $backup_files ) ) );

			return array(
				'success'      => true,
				'backup_id'    => $backup_id,
				'backup_files' => $backup_files,
				'message'      => sprintf( 'Backed up %d tables', count( $backup_files ) ),
			);
		} catch ( Exception $e ) {
			$this->logger->error( sprintf( 'Database backup failed: %s', esc_html( $e->getMessage() ) ) );
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Backup a single table
	 *
	 * @param string $table_name Full table name.
	 * @param string $backup_id Backup identifier.
	 * @return string|false Backup file path or false on failure.
	 */
	private function backup_table( $table_name, $backup_id ) {
		global $wpdb;

		try {
			// Validate table name.
			$validated_table = $this->validate_db_table_name( $table_name );
			if ( false === $validated_table ) {
				$this->logger->error( sprintf( 'Invalid table name for backup: %s', esc_html( $table_name ) ) );
				return false;
			}

			// Get table data.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			// Database backup requires direct query, table name is validated via validate_db_table_name(). Backup needs current data not cached. Table name is validated and sanitized via validate_db_table_name() which ensures it is safe for use in SQL queries.
			$rows = $wpdb->get_results(
				"SELECT * FROM `{$validated_table}`",
				ARRAY_A
			);
			// phpcs:enable

			if ( false === $rows ) {
				$this->logger->error( sprintf( 'Failed to retrieve data from table %s', esc_html( $table_name ) ) );
				return false;
			}

			// Create backup file.
			$backup_file = $this->backup_dir . '/' . $backup_id . '_' . $table_name . '.json';
			$backup_data = wp_json_encode( $rows, JSON_PRETTY_PRINT );

			$result = file_put_contents( $backup_file, $backup_data );
			if ( false === $result ) {
				$this->logger->error( sprintf( 'Failed to write backup file: %s', esc_html( $backup_file ) ) );
				return false;
			}

			$this->logger->info( sprintf( 'Backed up table %s (%d rows)', esc_html( $table_name ), count( $rows ) ) );

			return $backup_file;
		} catch ( Exception $e ) {
			$this->logger->error( sprintf( 'Table backup failed for %s: %s', esc_html( $table_name ), esc_html( $e->getMessage() ) ) );
			return false;
		}
	}

	/**
	 * Rollback from backup
	 *
	 * @param string|null $backup_id Backup ID to rollback from. If null, uses last backup.
	 * @return array Rollback result.
	 */
	public function rollback_from_backup( $backup_id = null ) {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			$this->logger->error( 'WordPress database object not available for rollback' );
			return array(
				'success' => false,
				'message' => 'Database object not available',
			);
		}

		if ( null === $backup_id ) {
			$backup_id = get_option( 'oms_last_db_backup_id' );
			if ( ! $backup_id ) {
				return array(
					'success' => false,
					'message' => 'No backup ID found',
				);
			}
		}

		$this->logger->info( sprintf( 'Starting database rollback from backup: %s', esc_html( $backup_id ) ) );

		try {
			// Load manifest.
			$manifest_file = $this->backup_dir . '/' . $backup_id . '_manifest.json';
			if ( ! file_exists( $manifest_file ) ) {
				return array(
					'success' => false,
					'message' => 'Backup manifest not found',
				);
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local backup manifest file, not remote URL.
			$manifest_json = file_get_contents( $manifest_file );
			$manifest      = json_decode( $manifest_json, true );

			if ( ! $manifest || ! isset( $manifest['tables'] ) ) {
				return array(
					'success' => false,
					'message' => 'Invalid backup manifest',
				);
			}

			$restored = 0;
			$errors   = 0;

			foreach ( $manifest['tables'] as $table_name => $backup_file ) {
				if ( ! file_exists( $backup_file ) ) {
					$this->logger->error( sprintf( 'Backup file not found: %s', esc_html( $backup_file ) ) );
					++$errors;
					continue;
				}

				$restore_result = $this->restore_table( $table_name, $backup_file );
				if ( $restore_result['success'] ) {
					++$restored;
				} else {
					++$errors;
					$this->logger->error( sprintf( 'Failed to restore table %s: %s', esc_html( $table_name ), esc_html( $restore_result['message'] ) ) );
				}
			}

			$this->logger->info( sprintf( 'Database rollback completed: %d restored, %d errors', $restored, $errors ) );

			return array(
				'success'  => 0 === $errors,
				'restored' => $restored,
				'errors'   => $errors,
			);
		} catch ( Exception $e ) {
			$this->logger->error( sprintf( 'Database rollback failed: %s', esc_html( $e->getMessage() ) ) );
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Restore a table from backup
	 *
	 * @param string $table_name Full table name.
	 * @param string $backup_file Backup file path.
	 * @return array Restore result.
	 */
	private function restore_table( $table_name, $backup_file ) {
		global $wpdb;

		try {
			// Validate table name.
			$validated_table = $this->validate_db_table_name( $table_name );
			if ( false === $validated_table ) {
				return array(
					'success' => false,
					'message' => 'Invalid table name',
				);
			}

			// Load backup data.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local backup file, not remote URL.
			$backup_json = file_get_contents( $backup_file );
			$backup_data = json_decode( $backup_json, true );

			if ( ! $backup_data || ! is_array( $backup_data ) ) {
				return array(
					'success' => false,
					'message' => 'Invalid backup data',
				);
			}

			// Truncate table first.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Database restore requires direct query, table name is validated via validate_db_table_name(). TRUNCATE TABLE does not support placeholders for table names.
			$wpdb->query( "TRUNCATE TABLE `{$validated_table}`" );

			// Restore data in batches.
			$batch_size = 100;
			$batches    = array_chunk( $backup_data, $batch_size );

			foreach ( $batches as $batch ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database restore requires direct query.
				foreach ( $batch as $row ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database restore requires direct query.
					$wpdb->insert( $validated_table, $row );
				}
			}

			return array(
				'success' => true,
				'message' => 'Table restored successfully',
			);
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Clean up old backups
	 *
	 * @param int $keep_days Number of days to keep backups.
	 * @return array Cleanup result.
	 */
	public function cleanup_old_backups( $keep_days = 7 ) {
		$this->logger->info( sprintf( 'Cleaning up database backups older than %d days', $keep_days ) );

		$cutoff_time = time() - ( $keep_days * DAY_IN_SECONDS );
		$deleted     = 0;
		$errors      = 0;

		try {
			$files = glob( $this->backup_dir . '/backup_*' );
			foreach ( $files as $file ) {
				$basename = basename( $file );

				// Only allow deletion of files this component creates.
				$is_manifest = (bool) preg_match( '/^backup_[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{2}_manifest\.json$/', $basename );
				$is_table    = (bool) preg_match( '/^backup_[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{2}_[a-zA-Z0-9_]+\.[jJ][sS][oO][nN]$/', $basename );

				if ( ! $is_manifest && ! $is_table ) {
					continue; // Skip unknown files.
				}

				if ( filemtime( $file ) < $cutoff_time ) {
					if ( @unlink( $file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
						++$deleted;
					} else {
						++$errors;
						$this->logger->error( sprintf( 'Failed to delete old backup: %s', esc_html( $file ) ) );
					}
				}
			}

			$this->logger->info( sprintf( 'Backup cleanup completed: %d deleted, %d errors', $deleted, $errors ) );

			return array(
				'success' => true,
				'deleted' => $deleted,
				'errors'  => $errors,
			);
		} catch ( Exception $e ) {
			$this->logger->error( sprintf( 'Backup cleanup failed: %s', esc_html( $e->getMessage() ) ) );
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}
}
