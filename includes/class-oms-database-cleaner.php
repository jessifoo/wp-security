<?php
/**
 * Database Cleaner class for malicious content removal
 *
 * Handles cleaning malicious database content with row-level backups
 * and transaction support for atomic operations.
 *
 * @package ObfuscatedMalwareScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * Database Cleaner class responsible for removing malicious content
 *
 * Uses MySQL transactions for atomic operations and stores row-level
 * backups for rollback capability without backing up entire tables.
 */
class OMS_Database_Cleaner {

	/**
	 * Logger instance
	 *
	 * @var OMS_Logger
	 */
	private $logger;

	/**
	 * Pending row backups for current operation
	 *
	 * @var array
	 */
	private $pending_backups = array();

	/**
	 * Whether we're in a transaction
	 *
	 * @var bool
	 */
	private $in_transaction = false;

	/**
	 * Critical tables that are allowed to be cleaned
	 *
	 * @var array
	 */
	private $allowed_tables = array(
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
	 * @param OMS_Logger $logger Logger instance.
	 */
	public function __construct( OMS_Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Clean malicious database content with transaction support
	 *
	 * Uses MySQL transactions for atomic operations. If any delete fails,
	 * the entire operation is rolled back automatically.
	 *
	 * @param array $issues Array of malicious content issues to clean.
	 * @return array Cleanup results.
	 */
	public function clean_issues( array $issues ) {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			$this->logger->error( 'WordPress database object not available for cleanup' );
			return array(
				'success' => false,
				'message' => 'Database object not available',
			);
		}

		// Filter to only malicious content issues.
		$cleanable_issues = array_filter(
			$issues,
			static function ( $issue ) {
				return isset( $issue['type'] ) && 'malicious_content' === $issue['type'];
			}
		);

		if ( empty( $cleanable_issues ) ) {
			return array(
				'success' => true,
				'cleaned' => 0,
				'message' => 'No cleanable issues found',
			);
		}

		$this->logger->info( sprintf( 'Starting database cleanup for %d issues', count( $cleanable_issues ) ) );

		try {
			// Start transaction for atomic operation.
			$this->begin_transaction();

			$cleaned = 0;
			$errors  = array();

			foreach ( $cleanable_issues as $issue ) {
				$result = $this->delete_row_with_backup( $issue );

				if ( $result['success'] ) {
					++$cleaned;
				} else {
					$errors[] = $result['message'];
					// On first error, rollback everything.
					$this->rollback_transaction();
					$this->restore_pending_backups();

					return array(
						'success'  => false,
						'cleaned'  => 0,
						'message'  => 'Cleanup failed, all changes rolled back',
						'errors'   => $errors,
						'rollback' => true,
					);
				}
			}

			// All deletes successful, commit transaction.
			$this->commit_transaction();

			// Store backups temporarily in case manual restore is needed.
			$this->store_session_backups();

			$this->logger->info( sprintf( 'Database cleanup completed: %d rows cleaned', $cleaned ) );

			return array(
				'success'   => true,
				'cleaned'   => $cleaned,
				'backup_id' => $this->get_current_backup_id(),
			);
		} catch ( Exception $e ) {
			$this->rollback_transaction();
			$this->restore_pending_backups();

			$this->logger->error( sprintf( 'Database cleanup failed: %s', esc_html( $e->getMessage() ) ) );

			return array(
				'success'  => false,
				'message'  => $e->getMessage(),
				'rollback' => true,
			);
		}
	}

	/**
	 * Delete a single row with backup for potential restore
	 *
	 * @param array $issue Issue details containing table, column, and row_id.
	 * @return array Result with success status.
	 */
	private function delete_row_with_backup( array $issue ) {
		global $wpdb;

		$table_name = isset( $issue['table'] ) ? $issue['table'] : '';
		$row_id     = isset( $issue['row_id'] ) ? $issue['row_id'] : null;

		if ( empty( $table_name ) || null === $row_id ) {
			return array(
				'success' => false,
				'message' => 'Missing table name or row ID',
			);
		}

		// Validate table is in allowed list.
		if ( ! $this->is_allowed_table( $table_name ) ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Table %s is not in the allowed cleanup list', $table_name ),
			);
		}

		$id_column = $this->get_id_column( $table_name );
		if ( false === $id_column ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Could not determine ID column for table %s', $table_name ),
			);
		}

		// Backup the row before deletion.
		$backup_result = $this->backup_row( $table_name, $id_column, $row_id );
		if ( ! $backup_result['success'] ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Failed to backup row before deletion: %s', $backup_result['message'] ),
			);
		}

		// Perform the delete.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete(
			$table_name,
			array( $id_column => $row_id ),
			array( '%s' )
		);

		if ( false === $deleted ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Database delete failed: %s', $wpdb->last_error ),
			);
		}

		if ( 0 === $deleted ) {
			// Row didn't exist, remove from pending backups.
			array_pop( $this->pending_backups );
			return array(
				'success' => true,
				'message' => 'Row not found (may have already been deleted)',
			);
		}

		return array(
			'success' => true,
			'message' => 'Row deleted successfully',
		);
	}

	/**
	 * Backup a single row before deletion
	 *
	 * @param string $table_name Full table name.
	 * @param string $id_column  ID column name.
	 * @param mixed  $row_id     Row ID value.
	 * @return array Result with success status.
	 */
	private function backup_row( $table_name, $id_column, $row_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM `{$table_name}` WHERE `{$id_column}` = %s",
				$row_id
			),
			ARRAY_A
		);

		if ( null === $row ) {
			// Row doesn't exist, nothing to backup.
			return array(
				'success' => true,
				'message' => 'Row not found, nothing to backup',
			);
		}

		$this->pending_backups[] = array(
			'table'     => $table_name,
			'id_column' => $id_column,
			'row_id'    => $row_id,
			'data'      => $row,
			'timestamp' => time(),
		);

		return array(
			'success' => true,
			'message' => 'Row backed up',
		);
	}

	/**
	 * Restore all pending backups (re-insert deleted rows)
	 *
	 * Called when a transaction fails and we need to manually restore
	 * rows that were deleted before the failure.
	 *
	 * @return array Result with restore count.
	 */
	private function restore_pending_backups() {
		global $wpdb;

		$restored = 0;
		$errors   = 0;

		foreach ( $this->pending_backups as $backup ) {
			if ( empty( $backup['data'] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->insert( $backup['table'], $backup['data'] );

			if ( false !== $result ) {
				++$restored;
				$this->logger->info(
					sprintf(
						'Restored row %s in table %s',
						esc_html( (string) $backup['row_id'] ),
						esc_html( $backup['table'] )
					)
				);
			} else {
				++$errors;
				$this->logger->error(
					sprintf(
						'Failed to restore row %s in table %s: %s',
						esc_html( (string) $backup['row_id'] ),
						esc_html( $backup['table'] ),
						esc_html( $wpdb->last_error )
					)
				);
			}
		}

		$this->pending_backups = array();

		return array(
			'restored' => $restored,
			'errors'   => $errors,
		);
	}

	/**
	 * Begin a database transaction
	 *
	 * @return bool True if transaction started.
	 */
	private function begin_transaction() {
		global $wpdb;

		if ( $this->in_transaction ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query( 'START TRANSACTION' );

		if ( false !== $result ) {
			$this->in_transaction = true;
			$this->logger->debug( 'Database transaction started' );
			return true;
		}

		$this->logger->warning( 'Failed to start database transaction, proceeding without transaction support' );
		return false;
	}

	/**
	 * Commit the current transaction
	 *
	 * @return bool True if committed successfully.
	 */
	private function commit_transaction() {
		global $wpdb;

		if ( ! $this->in_transaction ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query( 'COMMIT' );

		$this->in_transaction = false;

		if ( false !== $result ) {
			$this->logger->debug( 'Database transaction committed' );
			return true;
		}

		$this->logger->error( 'Failed to commit database transaction' );
		return false;
	}

	/**
	 * Rollback the current transaction
	 *
	 * @return bool True if rolled back successfully.
	 */
	private function rollback_transaction() {
		global $wpdb;

		if ( ! $this->in_transaction ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query( 'ROLLBACK' );

		$this->in_transaction = false;

		if ( false !== $result ) {
			$this->logger->info( 'Database transaction rolled back' );
			return true;
		}

		$this->logger->error( 'Failed to rollback database transaction' );
		return false;
	}

	/**
	 * Store session backups in a transient for manual restore capability
	 *
	 * Keeps backups available for a short period after successful cleanup
	 * in case the user wants to manually undo the operation.
	 */
	private function store_session_backups() {
		if ( empty( $this->pending_backups ) ) {
			return;
		}

		$backup_id = $this->get_current_backup_id();

		// Store for 1 hour (can be restored manually if needed).
		set_transient(
			'oms_cleanup_backup_' . $backup_id,
			$this->pending_backups,
			HOUR_IN_SECONDS
		);

		// Track backup IDs for listing.
		$backup_ids   = get_option( 'oms_cleanup_backup_ids', array() );
		$backup_ids[] = array(
			'id'        => $backup_id,
			'timestamp' => time(),
			'count'     => count( $this->pending_backups ),
		);

		// Keep only last 10 backup references.
		$backup_ids = array_slice( $backup_ids, -10 );
		update_option( 'oms_cleanup_backup_ids', $backup_ids, false );

		$this->logger->debug( sprintf( 'Stored %d row backups with ID: %s', count( $this->pending_backups ), $backup_id ) );
	}

	/**
	 * Restore rows from a stored backup
	 *
	 * @param string $backup_id Backup ID to restore from.
	 * @return array Result with restore count.
	 */
	public function restore_from_backup( $backup_id ) {
		$backups = get_transient( 'oms_cleanup_backup_' . $backup_id );

		if ( false === $backups || ! is_array( $backups ) ) {
			return array(
				'success' => false,
				'message' => 'Backup not found or expired',
			);
		}

		$this->pending_backups = $backups;
		$result                = $this->restore_pending_backups();

		// Remove the used backup.
		delete_transient( 'oms_cleanup_backup_' . $backup_id );

		// Update backup IDs list.
		$backup_ids = get_option( 'oms_cleanup_backup_ids', array() );
		$backup_ids = array_filter(
			$backup_ids,
			static function ( $item ) use ( $backup_id ) {
				return $item['id'] !== $backup_id;
			}
		);
		update_option( 'oms_cleanup_backup_ids', $backup_ids, false );

		return array(
			'success'  => 0 === $result['errors'],
			'restored' => $result['restored'],
			'errors'   => $result['errors'],
		);
	}

	/**
	 * List available cleanup backups
	 *
	 * @return array List of available backups.
	 */
	public function list_backups() {
		$backup_ids = get_option( 'oms_cleanup_backup_ids', array() );
		$available  = array();

		foreach ( $backup_ids as $backup_info ) {
			$transient = get_transient( 'oms_cleanup_backup_' . $backup_info['id'] );
			if ( false !== $transient ) {
				$available[] = $backup_info;
			}
		}

		return $available;
	}

	/**
	 * Get current backup ID
	 *
	 * @return string Unique backup identifier.
	 */
	private function get_current_backup_id() {
		return 'cleanup_' . gmdate( 'Y-m-d-H-i-s' ) . '_' . wp_generate_password( 6, false );
	}

	/**
	 * Check if a table is in the allowed cleanup list
	 *
	 * @param string $table_name Full table name.
	 * @return bool True if allowed.
	 */
	private function is_allowed_table( $table_name ) {
		global $wpdb;

		$table_base = str_replace( $wpdb->prefix, '', $table_name );

		/**
		 * Filter the list of tables allowed for cleanup operations.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $allowed_tables List of allowed table base names.
		 * @param string $table_name     The full table name being checked.
		 */
		$allowed = apply_filters( 'oms_allowed_cleanup_tables', $this->allowed_tables, $table_name );

		return in_array( $table_base, $allowed, true );
	}

	/**
	 * Get the ID column name for a table
	 *
	 * @param string $table_name Full table name.
	 * @return string|false ID column name or false.
	 */
	private function get_id_column( $table_name ) {
		global $wpdb;

		$table_base = str_replace( $wpdb->prefix, '', $table_name );

		$id_columns = array(
			'posts'       => 'ID',
			'users'       => 'ID',
			'comments'    => 'comment_ID',
			'options'     => 'option_id',
			'postmeta'    => 'meta_id',
			'usermeta'    => 'umeta_id',
			'commentmeta' => 'meta_id',
			'terms'       => 'term_id',
			'links'       => 'link_id',
		);

		return isset( $id_columns[ $table_base ] ) ? $id_columns[ $table_base ] : false;
	}
}
