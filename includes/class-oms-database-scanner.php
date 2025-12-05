<?php
/**
 * Database Scanner class for malware detection and integrity checks
 *
 * Handles database content scanning, integrity verification, and malicious content detection.
 *
 * @package ObfuscatedMalwareScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * Database Scanner class responsible for database security checks
 */
class OMS_Database_Scanner {
	/**
	 * Logger instance
	 *
	 * @var OMS_Logger
	 */
	private $logger;

	/**
	 * Cache instance
	 *
	 * @var OMS_Cache
	 */
	private $cache;

	/**
	 * Database backup instance
	 *
	 * @var OMS_Database_Backup
	 */
	private $backup;

	/**
	 * Critical WordPress tables that must be checked
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
	 * @param OMS_Logger          $logger Logger instance.
	 * @param OMS_Cache           $cache  Cache instance.
	 * @param OMS_Database_Backup $backup Backup instance.
	 */
	public function __construct( OMS_Logger $logger, OMS_Cache $cache, OMS_Database_Backup $backup = null ) {
		$this->logger = $logger;
		$this->cache  = $cache;
		$this->backup = $backup ? $backup : new OMS_Database_Backup( $this->logger );
	}

	/**
	 * Get database backup instance
	 *
	 * @return OMS_Database_Backup Backup instance.
	 */
	public function get_backup() {
		return $this->backup;
	}

	/**
	 * Validate and sanitize database table/column name
	 *
	 * @param string $identifier Table or column name to validate.
	 * @return string|false Sanitized identifier or false if invalid.
	 */
	private function validate_db_identifier( $identifier ) {
		if ( ! is_string( $identifier ) || empty( $identifier ) ) {
			return false;
		}

		// Allow only alphanumeric, underscore, and backtick characters.
		// Remove backticks if present (we'll add them ourselves).
		$identifier = str_replace( '`', '', $identifier );

		// Check against whitelist for table names (without prefix).
		global $wpdb;
		$table_base = str_replace( $wpdb->prefix, '', $identifier );
		if ( in_array( $table_base, $this->critical_tables, true ) ) {
			return $identifier;
		}

		// For column names, validate format (alphanumeric and underscore only).
		if ( preg_match( '/^[a-zA-Z0-9_]+$/', $identifier ) ) {
			return $identifier;
		}

		return false;
	}

	/**
	 * Scan database for malicious content
	 *
	 * @return array Scan results with issues found.
	 */
	public function scan_database() {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			$this->logger->error( 'WordPress database object not available for database scan' );
			return array(
				'success' => false,
				'issues'  => array(),
				'message' => 'Database object not available',
			);
		}

		$this->logger->info( 'Starting database security scan' );

		$issues = array();

		try {
			// Check database integrity.
			$integrity_issues = $this->check_database_integrity();
			if ( ! empty( $integrity_issues ) ) {
				$issues['integrity'] = $integrity_issues;
			}

			// Scan database content for malicious patterns.
			$content_issues = $this->scan_database_content();
			if ( ! empty( $content_issues ) ) {
				$issues['content'] = $content_issues;
			}

			// Check for suspicious database modifications.
			$modification_issues = $this->check_suspicious_modifications();
			if ( ! empty( $modification_issues ) ) {
				$issues['modifications'] = $modification_issues;
			}

			$total_issues = count( $issues['integrity'] ?? array() ) + count( $issues['content'] ?? array() ) + count( $issues['modifications'] ?? array() );

			if ( $total_issues > 0 ) {
				$this->logger->warning( sprintf( 'Database scan found %d issue(s)', $total_issues ) );
			} else {
				$this->logger->info( 'Database scan completed - no issues found' );
			}

			return array(
				'success' => true,
				'issues'  => $issues,
				'total'   => $total_issues,
			);
		} catch ( Exception $e ) {
			$this->logger->error( sprintf( 'Database scan failed: %s', esc_html( $e->getMessage() ) ) );
			return array(
				'success' => false,
				'issues'  => array(),
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Check database integrity (table structure, indexes)
	 *
	 * @return array Integrity issues found.
	 */
	private function check_database_integrity() {
		global $wpdb;

		$issues = array();

		try {
			// Get table prefix.
			$prefix = $wpdb->prefix;

			// Check each critical table.
			foreach ( $this->critical_tables as $table_name ) {
				$full_table_name = $prefix . $table_name;

				// Check if table exists.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database integrity check requires direct query, information_schema queries don't benefit from caching.
				$table_exists = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s',
						defined( 'DB_NAME' ) ? DB_NAME : '',
						$full_table_name
					)
				);

				if ( ! $table_exists ) {
					$issues[] = array(
						'type'     => 'missing_table',
						'table'    => $full_table_name,
						'severity' => 'CRITICAL',
						'message'  => sprintf( 'Critical table missing: %s', esc_html( $full_table_name ) ),
					);
					continue;
				}

				// Check table structure integrity.
				$structure_issues = $this->check_table_structure( $full_table_name );
				if ( ! empty( $structure_issues ) ) {
					$issues = array_merge( $issues, $structure_issues );
				}

				// Check indexes.
				$index_issues = $this->check_table_indexes( $full_table_name );
				if ( ! empty( $index_issues ) ) {
					$issues = array_merge( $issues, $index_issues );
				}
			}
		} catch ( Exception $e ) {
			$this->logger->error( sprintf( 'Database integrity check failed: %s', esc_html( $e->getMessage() ) ) );
			$issues[] = array(
				'type'     => 'check_error',
				'severity' => 'HIGH',
				'message'  => sprintf( 'Integrity check error: %s', esc_html( $e->getMessage() ) ),
			);
		}

		return $issues;
	}

	/**
	 * Check table structure integrity
	 *
	 * @param string $table_name Full table name.
	 * @return array Structure issues found.
	 */
	private function check_table_structure( $table_name ) {
		global $wpdb;

		$issues = array();

		try {
			// Get expected structure from WordPress core.
			$expected_structure = $this->get_expected_table_structure( $table_name );
			if ( empty( $expected_structure ) ) {
				return $issues; // Skip if we don't have expected structure.
			}

			// Get actual table structure.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database integrity check requires direct query, information_schema queries don't benefit from caching.
			$actual_columns = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
					FROM information_schema.COLUMNS
					WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
					defined( 'DB_NAME' ) ? DB_NAME : '',
					$table_name
				),
				ARRAY_A
			);

			$actual_column_names = array_column( $actual_columns, 'COLUMN_NAME' );

			// Check for missing columns.
			foreach ( $expected_structure['columns'] as $expected_column => $expected_def ) {
				if ( ! in_array( $expected_column, $actual_column_names, true ) ) {
					$issues[] = array(
						'type'     => 'missing_column',
						'table'    => $table_name,
						'column'   => $expected_column,
						'severity' => 'HIGH',
						'message'  => sprintf( 'Missing column %s in table %s', esc_html( $expected_column ), esc_html( $table_name ) ),
					);
				}
			}

			// Check for unexpected columns (potential injection).
			foreach ( $actual_column_names as $actual_column ) {
				if ( ! isset( $expected_structure['columns'][ $actual_column ] ) ) {
					$issues[] = array(
						'type'     => 'unexpected_column',
						'table'    => $table_name,
						'column'   => $actual_column,
						'severity' => 'MEDIUM',
						'message'  => sprintf( 'Unexpected column %s in table %s', esc_html( $actual_column ), esc_html( $table_name ) ),
					);
				}
			}
		} catch ( Exception $e ) {
			$this->logger->error( sprintf( 'Table structure check failed for %s: %s', esc_html( $table_name ), esc_html( $e->getMessage() ) ) );
		}

		return $issues;
	}

	/**
	 * Check table indexes
	 *
	 * @param string $table_name Full table name.
	 * @return array Index issues found.
	 */
	private function check_table_indexes( $table_name ) {
		global $wpdb;

		$issues = array();

		try {
			// Get expected indexes from WordPress core.
			$expected_indexes = $this->get_expected_indexes( $table_name );
			if ( empty( $expected_indexes ) ) {
				return $issues;
			}

			// Get actual indexes.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database integrity check requires direct query, information_schema queries don't benefit from caching.
			$actual_indexes = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT INDEX_NAME FROM information_schema.STATISTICS
					WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
					GROUP BY INDEX_NAME',
					defined( 'DB_NAME' ) ? DB_NAME : '',
					$table_name
				),
				ARRAY_A
			);

			$actual_index_names = array_column( $actual_indexes, 'INDEX_NAME' );

			// Check for missing critical indexes.
			foreach ( $expected_indexes as $expected_index ) {
				if ( ! in_array( $expected_index, $actual_index_names, true ) ) {
					$issues[] = array(
						'type'     => 'missing_index',
						'table'    => $table_name,
						'index'    => $expected_index,
						'severity' => 'MEDIUM',
						'message'  => sprintf( 'Missing index %s in table %s', esc_html( $expected_index ), esc_html( $table_name ) ),
					);
				}
			}
		} catch ( Exception $e ) {
			$this->logger->error( sprintf( 'Index check failed for %s: %s', esc_html( $table_name ), esc_html( $e->getMessage() ) ) );
		}

		return $issues;
	}

	/**
	 * Scan database content for malicious patterns
	 *
	 * @return array Content issues found.
	 */
	private function scan_database_content() {
		global $wpdb;

		$issues = array();

		try {
			// Get database patterns from config.
			$patterns = OMS_Config::DATABASE_MALWARE_PATTERNS;

			// Scan critical tables.
			foreach ( $this->critical_tables as $table_name ) {
				$full_table_name = $wpdb->prefix . $table_name;

				// Get table content to scan.
				$table_issues = $this->scan_table_content( $full_table_name, $patterns );
				if ( ! empty( $table_issues ) ) {
					$issues = array_merge( $issues, $table_issues );
				}
			}
		} catch ( Exception $e ) {
			$this->logger->error( sprintf( 'Database content scan failed: %s', esc_html( $e->getMessage() ) ) );
			$issues[] = array(
				'type'     => 'scan_error',
				'severity' => 'HIGH',
				'message'  => sprintf( 'Content scan error: %s', esc_html( $e->getMessage() ) ),
			);
		}

		return $issues;
	}

	/**
	 * Scan table content for malicious patterns
	 *
	 * @param string $table_name Full table name.
	 * @param array  $patterns Malware patterns to check.
	 * @return array Content issues found.
	 */
	private function scan_table_content( $table_name, $patterns ) {
		global $wpdb;

		$issues = array();

		try {
			// Get all text columns from the table.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database security scan requires direct query, information_schema queries don't benefit from caching.
			$columns = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT COLUMN_NAME FROM information_schema.COLUMNS
					WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
					AND DATA_TYPE IN ("varchar", "text", "longtext", "mediumtext", "tinytext", "char")',
					defined( 'DB_NAME' ) ? DB_NAME : '',
					$table_name
				)
			);

			if ( empty( $columns ) ) {
				return $issues;
			}

			// Scan each column.
			foreach ( $columns as $column ) {
				$column_issues = $this->scan_column_content( $table_name, $column, $patterns );
				if ( ! empty( $column_issues ) ) {
					$issues = array_merge( $issues, $column_issues );
				}
			}
		} catch ( Exception $e ) {
			$this->logger->error( sprintf( 'Table content scan failed for %s: %s', esc_html( $table_name ), esc_html( $e->getMessage() ) ) );
		}

		return $issues;
	}

	/**
	 * Scan column content for malicious patterns
	 *
	 * @param string $table_name Full table name.
	 * @param string $column Column name.
	 * @param array  $patterns Malware patterns to check.
	 * @return array Content issues found.
	 */
	private function scan_column_content( $table_name, $column, $patterns ) {
		global $wpdb;

		$issues = array();

		try {
			// Validate table and column names.
			$validated_table  = $this->validate_db_identifier( $table_name );
			$validated_column = $this->validate_db_identifier( $column );
			if ( false === $validated_table || false === $validated_column ) {
				$this->logger->error( sprintf( 'Invalid database identifier: table=%s, column=%s', esc_html( $table_name ), esc_html( $column ) ) );
				return $issues;
			}

			// Get the correct primary key column name for this table.
			$id_column           = $this->get_id_column_name( $validated_table );
			$validated_id_column = $this->validate_db_identifier( $id_column );
			if ( false === $validated_id_column ) {
				$this->logger->error( sprintf( 'Invalid ID column name for table %s: %s', esc_html( $table_name ), esc_html( $id_column ) ) );
				return $issues;
			}

			// Process in batches to avoid memory issues.
			$batch_size = 100;
			$offset     = 0;

			while ( true ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Database security scan requires direct query, content scanning needs current data not cached. Table and column names are validated and sanitized via validate_db_identifier().
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and column names are validated via validate_db_identifier().
						"SELECT `{$validated_id_column}`, `{$validated_column}` FROM `{$validated_table}` WHERE `{$validated_column}` IS NOT NULL AND `{$validated_column}` != '' LIMIT %d OFFSET %d",
						$batch_size,
						$offset
					),
					ARRAY_A
				);

				foreach ( $rows as $row ) {
					$content = isset( $row[ $validated_column ] ) ? $row[ $validated_column ] : '';
					if ( '' === $content ) {
						continue;
					}

					$row_id = isset( $row[ $validated_id_column ] ) ? $row[ $validated_id_column ] : null;

					// Check against patterns.
					foreach ( $patterns as $pattern_data ) {
						$pattern  = is_array( $pattern_data ) && isset( $pattern_data['pattern'] ) ? $pattern_data['pattern'] : $pattern_data;
						$severity = is_array( $pattern_data ) && isset( $pattern_data['severity'] ) ? $pattern_data['severity'] : 'MEDIUM';

						if ( preg_match( $pattern, $content, $matches ) ) {
							$issues[] = array(
								'type'     => 'malicious_content',
								'table'    => $table_name,
								'column'   => $column,
								'row_id'   => $row_id,
								'pattern'  => $pattern,
								'severity' => $severity,
								'message'  => sprintf(
									'Malicious content detected in %s.%s (row: %s)',
									esc_html( $table_name ),
									esc_html( $column ),
									null !== $row_id ? esc_html( (string) $row_id ) : 'unknown'
								),
								'match'    => isset( $matches[0] ) ? substr( $matches[0], 0, 100 ) : '',
							);
						}
					}
				}

				$offset += $batch_size;

				// Prevent infinite loops.
				if ( $offset > 10000 ) {
					break;
				}
			}
		} catch ( Exception $e ) {
			$this->logger->error( sprintf( 'Column content scan failed for %s.%s: %s', esc_html( $table_name ), esc_html( $column ), esc_html( $e->getMessage() ) ) );
		}

		return $issues;
	}

	/**
	 * Check for suspicious database modifications
	 *
	 * @return array Modification issues found.
	 */
	private function check_suspicious_modifications() {
		global $wpdb;

		$issues = array();

		try {
			$prefix = $wpdb->prefix;

			// Check for suspicious options.
			$suspicious_options = $this->check_suspicious_options( $prefix . 'options' );
			if ( ! empty( $suspicious_options ) ) {
				$issues = array_merge( $issues, $suspicious_options );
			}

			// Check for suspicious user meta.
			$suspicious_usermeta = $this->check_suspicious_usermeta( $prefix . 'usermeta' );
			if ( ! empty( $suspicious_usermeta ) ) {
				$issues = array_merge( $issues, $suspicious_usermeta );
			}
		} catch ( Exception $e ) {
			$this->logger->error( sprintf( 'Suspicious modifications check failed: %s', esc_html( $e->getMessage() ) ) );
		}

		return $issues;
	}

	/**
	 * Check for suspicious options
	 *
	 * @param string $options_table Options table name.
	 * @return array Suspicious options found.
	 */
	private function check_suspicious_options( $options_table ) {
		global $wpdb;

		$issues                  = array();
		$suspicious_option_names = array(
			'%eval%',
			'%base64%',
			'%shell%',
			'%backdoor%',
			'%hack%',
			'%malware%',
		);

		try {
			// Validate table name.
			$validated_table = $this->validate_db_identifier( $options_table );
			if ( false === $validated_table ) {
				$this->logger->error( sprintf( 'Invalid options table name: %s', esc_html( $options_table ) ) );
				return $issues;
			}

			foreach ( $suspicious_option_names as $pattern ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Database security scan requires direct query, needs current data for security checks. Table name is validated and sanitized via validate_db_identifier().
				$options = $wpdb->get_results(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated via validate_db_identifier().
						"SELECT option_id, option_name, option_value FROM `{$validated_table}` WHERE option_name LIKE %s",
						$pattern
					),
					ARRAY_A
				);

				foreach ( $options as $option ) {
					$issues[] = array(
						'type'        => 'suspicious_option',
						'table'       => $options_table,
						'option_id'   => $option['option_id'],
						'option_name' => $option['option_name'],
						'severity'    => 'HIGH',
						'message'     => sprintf( 'Suspicious option name detected: %s', esc_html( $option['option_name'] ) ),
					);
				}
			}
		} catch ( Exception $e ) {
			$this->logger->error( sprintf( 'Suspicious options check failed: %s', esc_html( $e->getMessage() ) ) );
		}

		return $issues;
	}

	/**
	 * Check for suspicious user meta
	 *
	 * @param string $usermeta_table User meta table name.
	 * @return array Suspicious user meta found.
	 */
	private function check_suspicious_usermeta( $usermeta_table ) {
		global $wpdb;

		$issues = array();

		try {
			// Check for suspicious meta keys.
			$suspicious_keys = array(
				'%eval%',
				'%base64%',
				'%shell%',
				'%backdoor%',
			);

			// Validate table name.
			$validated_table = $this->validate_db_identifier( $usermeta_table );
			if ( false === $validated_table ) {
				$this->logger->error( sprintf( 'Invalid usermeta table name: %s', esc_html( $usermeta_table ) ) );
				return $issues;
			}

			foreach ( $suspicious_keys as $pattern ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Database security scan requires direct query, needs current data for security checks. Table name is validated and sanitized via validate_db_identifier().
				$meta = $wpdb->get_results(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated via validate_db_identifier().
						"SELECT umeta_id, user_id, meta_key FROM `{$validated_table}` WHERE meta_key LIKE %s",
						$pattern
					),
					ARRAY_A
				);

				foreach ( $meta as $meta_row ) {
					$issues[] = array(
						'type'     => 'suspicious_usermeta',
						'table'    => $usermeta_table,
						'umeta_id' => $meta_row['umeta_id'],
						'user_id'  => $meta_row['user_id'],
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Security scan requires checking meta_key for suspicious patterns.
						'meta_key' => $meta_row['meta_key'],
						'severity' => 'HIGH',
						'message'  => sprintf( 'Suspicious user meta key detected: %s (user: %d)', esc_html( $meta_row['meta_key'] ), $meta_row['user_id'] ),
					);
				}
			}
		} catch ( Exception $e ) {
			$this->logger->error( sprintf( 'Suspicious usermeta check failed: %s', esc_html( $e->getMessage() ) ) );
		}

		return $issues;
	}

	/**
	 * Get expected table structure for a WordPress table
	 *
	 * @param string $table_name Full table name.
	 * @return array Expected structure or empty array.
	 */
	private function get_expected_table_structure( $table_name ) {
		// Cache key for structure.
		$cache_key = 'oms_table_structure_' . $table_name;
		$cached    = $this->cache->get( $cache_key );
		if ( $cached ) {
			return $cached;
		}

		// For now, return basic structure for common tables.
		// In a full implementation, this would fetch from WordPress core schema.
		$structures = array(
			'options' => array(
				'columns' => array(
					'option_id'    => array( 'type' => 'bigint' ),
					'option_name'  => array( 'type' => 'varchar' ),
					'option_value' => array( 'type' => 'longtext' ),
					'autoload'     => array( 'type' => 'varchar' ),
				),
			),
			'posts'   => array(
				'columns' => array(
					'ID'           => array( 'type' => 'bigint' ),
					'post_author'  => array( 'type' => 'bigint' ),
					'post_content' => array( 'type' => 'longtext' ),
					'post_title'   => array( 'type' => 'text' ),
					'post_status'  => array( 'type' => 'varchar' ),
				),
			),
		);

		// Extract table name without prefix.
		global $wpdb;
		$table_base = str_replace( $wpdb->prefix, '', $table_name );

		$structure = isset( $structures[ $table_base ] ) ? $structures[ $table_base ] : array();

		// Cache for 1 hour.
		if ( ! empty( $structure ) ) {
			$this->cache->set( $cache_key, $structure, 3600 );
		}

		return $structure;
	}

	/**
	 * Get expected indexes for a WordPress table
	 *
	 * @param string $table_name Full table name.
	 * @return array Expected indexes or empty array.
	 */
	private function get_expected_indexes( $table_name ) {
		// Cache key for indexes.
		$cache_key = 'oms_table_indexes_' . $table_name;
		$cached    = $this->cache->get( $cache_key );
		if ( $cached ) {
			return $cached;
		}

		// Basic expected indexes for common tables.
		global $wpdb;
		$table_base = str_replace( $wpdb->prefix, '', $table_name );

		$indexes = array(
			'options' => array( 'PRIMARY', 'option_name' ),
			'posts'   => array( 'PRIMARY', 'post_name', 'type_status_date', 'post_author', 'post_parent' ),
			'users'   => array( 'PRIMARY', 'user_login', 'user_nicename', 'user_email' ),
		);

		$expected = isset( $indexes[ $table_base ] ) ? $indexes[ $table_base ] : array();

		// Cache for 1 hour.
		if ( ! empty( $expected ) ) {
			$this->cache->set( $cache_key, $expected, 3600 );
		}

		return $expected;
	}

	/**
	 * Clean malicious database content
	 *
	 * @param array $issues Issues to clean.
	 * @return array Cleanup results.
	 */
	public function clean_database_content( $issues ) {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			$this->logger->error( 'WordPress database object not available for database cleanup' );
			return array(
				'success' => false,
				'message' => 'Database object not available',
			);
		}

		$this->logger->info( 'Starting database content cleanup' );

		// Backup critical tables before cleanup.
		$backup_result = $this->backup->backup_critical_tables();
		if ( ! $backup_result['success'] ) {
			$this->logger->error( 'Failed to backup critical tables before cleanup' );
			return array(
				'success' => false,
				'message' => 'Backup failed, cleanup aborted',
			);
		}

		$cleaned = 0;
		$errors  = 0;

		try {
			foreach ( $issues as $issue ) {
				if ( 'malicious_content' !== $issue['type'] ) {
					continue;
				}

				$clean_result = $this->clean_malicious_row( $issue );
				if ( $clean_result['success'] ) {
					++$cleaned;
				} else {
					++$errors;
					$this->logger->error( sprintf( 'Failed to clean row: %s', esc_html( $clean_result['message'] ) ) );
				}
			}

			$this->logger->info( sprintf( 'Database cleanup completed: %d cleaned, %d errors', $cleaned, $errors ) );

			return array(
				'success' => true,
				'cleaned' => $cleaned,
				'errors'  => $errors,
			);
		} catch ( Exception $e ) {
			$this->logger->error( sprintf( 'Database cleanup failed: %s', esc_html( $e->getMessage() ) ) );

			// Attempt rollback.
			$rollback_result = $this->backup->rollback_from_backup();
			if ( $rollback_result['success'] ) {
				$this->logger->info( 'Rolled back database changes after cleanup failure' );
			}

			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Clean a malicious database row
	 *
	 * @param array $issue Issue details.
	 * @return array Cleanup result.
	 */
	private function clean_malicious_row( $issue ) {
		global $wpdb;

		try {
			$table_name = isset( $issue['table'] ) ? $issue['table'] : '';
			$column     = isset( $issue['column'] ) ? $issue['column'] : '';
			$row_id     = isset( $issue['row_id'] ) ? $issue['row_id'] : null;

			if ( empty( $row_id ) ) {
				return array(
					'success' => false,
					'message' => 'Row ID not available',
				);
			}

			// Validate table and column names.
			$validated_table  = $this->validate_db_identifier( $table_name );
			$validated_column = $this->validate_db_identifier( $column );
			if ( false === $validated_table || false === $validated_column ) {
				return array(
					'success' => false,
					'message' => 'Invalid table or column name',
				);
			}

			// Determine ID column name.
			$id_column           = $this->get_id_column_name( $validated_table );
			$validated_id_column = $this->validate_db_identifier( $id_column );
			if ( false === $validated_id_column ) {
				return array(
					'success' => false,
					'message' => 'Invalid ID column name',
				);
			}

			// Delete the row.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database cleanup requires direct query. Table and column names are validated via validate_db_identifier().
			$result = $wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and column names are validated via validate_db_identifier().
					"DELETE FROM `{$validated_table}` WHERE `{$validated_id_column}` = %s",
					$row_id
				)
			);

			if ( false === $result ) {
				return array(
					'success' => false,
					'message' => 'Database error during deletion',
				);
			}

			return array(
				'success' => true,
				'message' => 'Row deleted successfully',
			);
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Get the primary key column name for a table
	 *
	 * @param string $table_name Full table name.
	 * @return string ID column name.
	 */
	private function get_id_column_name( $table_name ) {
		global $wpdb;

		// Strip prefix to identify table type.
		$table_base = str_replace( $wpdb->prefix, '', $table_name );

		switch ( $table_base ) {
			case 'posts':
				return 'ID';
			case 'users':
				return 'ID';
			case 'comments':
				return 'comment_ID';
			case 'options':
				return 'option_id';
			case 'postmeta':
				return 'meta_id';
			case 'usermeta':
				return 'umeta_id';
			case 'commentmeta':
				return 'meta_id';
			case 'terms':
				return 'term_id';
			case 'term_taxonomy':
				return 'term_taxonomy_id';
			case 'links':
				return 'link_id';
			default:
				// Fallback: try to guess or query schema.
				// For now, default to 'ID' or 'id'.
				return 'ID';
		}
	}
}
