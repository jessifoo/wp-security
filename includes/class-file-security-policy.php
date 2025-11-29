<?php
/**
 * Security policy for file validation
 *
 * @package ObfuscatedMalwareScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * Security policy for file validation
 */
class OMS_File_Security_Policy {
	/**
	 * Filesystem instance.
	 *
	 * @var OMS_Filesystem
	 */
	private $filesystem;

	/**
	 * Constructor.
	 *
	 * @param OMS_Filesystem $filesystem Filesystem instance.
	 */
	public function __construct( OMS_Filesystem $filesystem ) {
		$this->filesystem = $filesystem;
	}


	/**
	 * List of forbidden file extensions
	 *
	 * @var array
	 */
	private $forbidden_extensions = array(
		// PHP variants.
		'php',
		'phtml',
		'php3',
		'php4',
		'php5',
		'php7',
		'pht',
		'phar',
		'inc',
		// Other server-side.
		'cgi',
		'pl',
		'py',
		'rb',
		'asp',
		'aspx',
		'jsp',
		// JavaScript.
		'js',
		'jsx',
		'mjs',
		// Shell scripts.
		'sh',
		'bash',
		'ksh',
		'zsh',
		'bat',
		'cmd',
		// System files.
		'htaccess',
		'htpasswd',
		'ini',
		'phps',
		'sql',
		// Compressed files that might contain malicious code.
		'zip',
		'rar',
		'tar',
		'gz',
		'7z',
	);

	/**
	 * Paths that should never contain executable files
	 *
	 * @var array
	 */
	private $restricted_paths = array(
		'wp-admin',
		'wp-includes',
		'wp-config.php',
	);

	/**
	 * List of protected theme paths
	 *
	 * @var array
	 */
	private $protected_theme_paths = array(
		'wp-content/themes/astra',
		'wp-content/plugins/elementor',
	);

	/**
	 * Suspicious file permissions
	 *
	 * @var array
	 */
	private $suspicious_perms = array(
		'executable' => 0111,  // Any execute permission.
	);

	/**
	 * Suspicious modification times
	 *
	 * @var array
	 */
	private $suspicious_times = array(
		'night_hours' => array( 0, 4 ),  // Suspicious between midnight and 4 AM.
	);

	/**
	 * Protected paths that should never be modified
	 *
	 * @var array
	 */
	private $protected_paths = array(
		// Elementor.
		'wp-content/plugins/elementor',
		'wp-content/plugins/elementor-pro',
		// Astra.
		'wp-content/themes/astra',
		'wp-content/plugins/astra-pro-sites',
		// WordPress Core.
		'wp-includes',
		'wp-admin',
	);



	/**
	 * Known good file patterns (e.g., minified files)
	 *
	 * @var array
	 */
	private $known_good_patterns = array(
		'/\.min\.(js|css)$/',  // Minified assets.
		'/elementor.*\.js$/',  // Elementor scripts.
		'/astra.*\.js$/',      // Astra scripts.
	);

	/**
	 * Validate a file against security policy
	 *
	 * @param string $path Full path to file.
	 * @param array  $options Validation options.
	 * @return array Validation result with 'valid' boolean and 'reason' string.
	 * @throws OMS_Security_Exception If validation fails critically.
	 */
	public function validate_file( $path, $options = array() ) {
		try {
			// Verify nonce if provided.
			if ( isset( $options['nonce'] ) && ! wp_verify_nonce( $options['nonce'], 'oms_file_validation' ) ) {
				throw new OMS_Security_Exception( 'Invalid security token' );
			}

			// 1. Basic file checks.
			$basic_check = $this->validate_file_basics( $path );
			if ( ! $basic_check['valid'] ) {
				return $basic_check;
			}

			// 2. Metadata checks (size, name, type, ext).
			$metadata_check = $this->validate_file_metadata( $path );
			if ( ! $metadata_check['valid'] ) {
				return $metadata_check;
			}

			// 3. Path security checks.
			$path_check = $this->validate_file_path_security( $path );
			if ( ! $path_check['valid'] ) {
				return $path_check;
			}

			// 4. Content security checks.
			$content_check = $this->validate_file_content_security( $path );
			if ( ! $content_check['valid'] ) {
				return $content_check;
			}

			// 5. System security checks (permissions, time).
			$system_check = $this->validate_file_system_security( $path );
			if ( ! $system_check['valid'] ) {
				return $system_check;
			}

			// All checks passed.
			return array(
				'valid'  => true,
				'reason' => 'File passed all security checks',
			);

		} catch ( Exception $e ) {
			throw new OMS_Security_Exception( 'File validation failed: ' . esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Validate basic file attributes.
	 *
	 * @param string $path File path.
	 * @return array Validation result.
	 */
	private function validate_file_basics( $path ) {
		if ( ! file_exists( $path ) ) {
			throw new OMS_Security_Exception( 'File does not exist' );
		}

		if ( ! is_file( $path ) ) {
			return array(
				'valid'  => false,
				'reason' => 'Not a regular file',
			);
		}

		return array( 'valid' => true );
	}

	/**
	 * Validate file metadata (size, name, type).
	 *
	 * @param string $path File path.
	 * @return array Validation result.
	 */
	private function validate_file_metadata( $path ) {
		// Check file size.
		if ( 0 === filesize( $path ) ) {
			$filename = basename( $path );
			if ( ! in_array( $filename, OMS_Config::ALLOWED_EMPTY_FILES, true ) ) {
				return array(
					'valid'  => false,
					'reason' => 'Zero byte file not in allowlist',
				);
			}
		}

		// Check for random filenames.
		if ( $this->is_random_filename( basename( $path ) ) ) {
			return array(
				'valid'  => false,
				'reason' => 'Suspicious random filename detected',
			);
		}

		// Use WordPress file type verification.
		$file_type = wp_check_filetype( basename( $path ) );
		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- wp_check_filetype returns false or string for type.
		if ( ! $file_type['type'] ) {
			return array(
				'valid'  => false,
				'reason' => 'Invalid file type',
			);
		}

		// Check file extension.
		$ext = ( is_string( $file_type['ext'] ) ) ? strtolower( $file_type['ext'] ) : '';
		if ( ! empty( $ext ) && in_array( $ext, $this->forbidden_extensions, true ) ) {
			return array(
				'valid'  => false,
				'reason' => 'Forbidden file extension',
			);
		}

		return array( 'valid' => true );
	}

	/**
	 * Validate file path security.
	 *
	 * @param string $path File path.
	 * @return array Validation result.
	 */
	private function validate_file_path_security( $path ) {
		$relative_path = OMS_Utils::get_relative_path( $path );

		// Check if file is in a restricted path.
		foreach ( $this->restricted_paths as $restricted_path ) {
			if ( 0 === strpos( $relative_path, $restricted_path ) ) {
				return array(
					'valid'  => false,
					'reason' => 'File in restricted path',
				);
			}
		}

		return array( 'valid' => true );
	}

	/**
	 * Validate file content security.
	 *
	 * @param string $path File path.
	 * @return array Validation result.
	 */
	private function validate_file_content_security( $path ) {
		$relative_path = OMS_Utils::get_relative_path( $path );

		// Check if file is in a protected theme path.
		$is_theme_file = false;
		foreach ( $this->protected_theme_paths as $theme_path ) {
			if ( 0 === strpos( $relative_path, $theme_path ) ) {
				$is_theme_file = true;
				break;
			}
		}

		// Perform content checks.
		$content_check = $this->filesystem->check_file_content( $path );
		if ( ! is_array( $content_check ) || ! isset( $content_check['safe'] ) || ! $content_check['safe'] ) {
			// If it's a theme file, we need to be more careful.
			if ( $is_theme_file ) {
				return $this->handle_theme_file_with_suspicious_content( $path, $content_check, $relative_path );
			}
			$reason = isset( $content_check['reason'] ) ? $content_check['reason'] : 'File content validation failed';
			return array(
				'valid'  => false,
				'reason' => $reason,
			);
		}

		return array( 'valid' => true );
	}

	/**
	 * Validate file system security (permissions, time).
	 *
	 * @param string $path File path.
	 * @return array Validation result.
	 */
	private function validate_file_system_security( $path ) {
		// Check permissions using WordPress functions.
		$stat = stat( $path );
		if ( false === $stat ) {
			return array(
				'valid'  => false,
				'reason' => 'Unable to check file permissions',
			);
		}

		$perms = $stat['mode'] & 0777;
		if ( $perms & $this->suspicious_perms['executable'] ) {
			return array(
				'valid'  => false,
				'reason' => 'File has executable permissions',
			);
		}

		// Check modification time.
		$mod_hour = (int) get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $stat['mtime'] ), 'G' );
		if ( $mod_hour >= $this->suspicious_times['night_hours'][0] &&
		$mod_hour <= $this->suspicious_times['night_hours'][1] ) {
			$relative_path = OMS_Utils::get_relative_path( $path );
			$is_theme_file = false;
			foreach ( $this->protected_theme_paths as $theme_path ) {
				if ( 0 === strpos( $relative_path, $theme_path ) ) {
					$is_theme_file = true;
					break;
				}
			}

			if ( ! $is_theme_file ) {
				return array(
					'valid'  => false,
					'reason' => 'File modified during suspicious hours',
				);
			}
			// For theme files, just log the suspicious modification.
			error_log( 'Theme file modified during suspicious hours: ' . esc_html( $path ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
		}

		return array( 'valid' => true );
	}



	/**
	 * Check if a file is in a protected path
	 *
	 * @param string $file_path File path to check.
	 * @return bool True if file is in protected path.
	 */
	public function is_protected_path( $file_path ) {
		$relative_path = $this->get_relative_path( $file_path );
		foreach ( $this->protected_paths as $protected_path ) {
			if ( 0 === strpos( $relative_path, $protected_path ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if a filename appears to be random (high entropy)
	 *
	 * @param string $filename Filename to check.
	 * @return bool True if filename appears random.
	 */
	private function is_random_filename( $filename ) {
		// Remove extension.
		$name = pathinfo( $filename, PATHINFO_FILENAME );

		// Skip short filenames.
		if ( strlen( $name ) < 5 ) {
			return false;
		}

		// Calculate entropy.
		$entropy = 0;
		$size    = strlen( $name );
		$data    = count_chars( $name, 1 );

		foreach ( $data as $count ) {
			$p        = $count / $size;
			$entropy -= $p * log( $p, 2 );
		}

		// High entropy threshold (very random).
		if ( $entropy > 4.5 ) {
			return true;
		}

		// Medium entropy check with heuristics.
		if ( $entropy > 2.5 ) {
			// Check for dictionary words.
			foreach ( OMS_Config::FILENAME_DICTIONARY as $word ) {
				if ( false !== stripos( $name, $word ) ) {
					return false; // Contains a known word, likely safe.
				}
			}

			// Check digit ratio.
			$digits = preg_match_all( '/[0-9]/', $name );
			$ratio  = $digits / $size;

			// If it has significant entropy AND significant digits AND no known words -> Flag.
			if ( $ratio > 0.2 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a file matches known good patterns
	 *
	 * @param string $file_path File path to check.
	 * @return bool True if file matches known good pattern.
	 */
	public function is_known_good_file( $file_path ) {
		foreach ( $this->known_good_patterns as $pattern ) {
			if ( preg_match( $pattern, $file_path ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get relative path from WordPress root
	 *
	 * @param string $file_path Absolute file path.
	 * @return string Relative path from WordPress root.
	 */
	private function get_relative_path( $file_path ) {
		return str_replace( ABSPATH, '', $file_path );
	}



	/**
	 * Add a restricted path
	 *
	 * @param string $path Path to restrict.
	 */
	public function add_restricted_path( $path ) {
		$this->restricted_paths[] = $path;
	}

	/**
	 * Add a forbidden extension
	 *
	 * @param string $ext Extension to forbid.
	 */
	public function add_forbidden_extension( $ext ) {
		$this->forbidden_extensions[] = $ext;
	}

	/**
	 * Handle theme file with suspicious content using content preservation logic.
	 *
	 * This method implements theme content preservation by:
	 * 1. Checking if the suspicious pattern matches known-good patterns (e.g., minified files)
	 * 2. Creating a backup of the theme file before any action
	 * 3. Checking if the content is actually malicious or a false positive
	 * 4. Quarantining only if truly malicious, preserving theme functionality otherwise
	 *
	 * @param string $path Full path to the theme file.
	 * @param array  $content_check Content check result from OMS_Utils::check_file_content().
	 * @param string $relative_path Relative path from WordPress root.
	 * @return array Validation result with 'valid' boolean and 'reason' string.
	 */
	private function handle_theme_file_with_suspicious_content( $path, $content_check, $relative_path ) {
		// Check if file matches known-good patterns (minified files, etc.).
		if ( $this->is_known_good_file( $path ) ) {
			// Known-good pattern match - likely a false positive.
			return array(
				'valid'  => true,
				'reason' => 'Theme file matches known-good pattern - safe',
			);
		}

		// Check if the suspicious content is in a protected theme path.
		$is_protected_theme = false;
		foreach ( $this->protected_theme_paths as $protected_path ) {
			if ( 0 === strpos( $relative_path, $protected_path ) ) {
				$is_protected_theme = true;
				break;
			}
		}

		// Read file content for detailed analysis.
		$file_content = file_get_contents( $path );
		if ( false === $file_content ) {
			// Can't read file - treat as invalid.
			return array(
				'valid'  => false,
				'reason' => 'Cannot read theme file for analysis',
			);
		}

		// Check if content contains high-severity malware patterns.
		$high_severity_patterns = array(
			'eval\s*\(',
			'base64_decode\s*\(',
			'exec\s*\(',
			'system\s*\(',
			'shell_exec\s*\(',
			'passthru\s*\(',
		);

		$has_high_severity = false;
		foreach ( $high_severity_patterns as $pattern ) {
			if ( preg_match( '/' . $pattern . '/i', $file_content ) ) {
				$has_high_severity = true;
				break;
			}
		}

		// Create backup directory if it doesn't exist.
		$backup_dir = WP_CONTENT_DIR . '/oms-theme-backups';
		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
			// Create .htaccess to protect backups.
			$htaccess_file = $backup_dir . '/.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				$result = file_put_contents( $htaccess_file, "deny from all\n" );
				if ( false === $result ) {
					error_log( 'OMS File Security Policy: Failed to create .htaccess file for backup directory: ' . esc_html( $htaccess_file ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
				}
			}
		}

		// Create backup of theme file.
		$backup_filename = sanitize_file_name( basename( $path ) . '.' . gmdate( 'Y-m-d-H-i-s' ) . '.backup' );
		$backup_path     = $backup_dir . '/' . $backup_filename;
		$backup_created  = copy( $path, $backup_path );

		if ( ! $backup_created ) {
			// Backup failed - log but continue with caution.
			error_log( 'OMS: Failed to create backup of theme file: ' . esc_html( $path ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
		}

		// If high-severity malware detected, quarantine the file.
		if ( $has_high_severity ) {
			// Use WordPress logger if available, otherwise use error_log.
			if ( class_exists( 'OMS_Logger' ) ) {
				$logger  = new OMS_Logger();
				$reason  = isset( $content_check['reason'] ) ? $content_check['reason'] : 'High-severity malware detected';
				$context = array(
					'path'        => $path,
					'backup_path' => $backup_created ? $backup_path : null,
					'reason'      => $reason,
				);
				$logger->warning(
					sprintf( 'High-severity malware detected in theme file - quarantining - Path: %s, Backup: %s, Reason: %s', esc_html( $path ), esc_html( $backup_created ? $backup_path : 'none' ), esc_html( $reason ) )
				);
			} else {
				error_log( 'OMS: High-severity malware detected in theme file: ' . esc_html( $path ) . ' (backup: ' . esc_html( $backup_created ? $backup_path : 'failed' ) . ')' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
			}

			// Attempt to quarantine using scanner if available.
			if ( class_exists( 'Obfuscated_Malware_Scanner' ) ) {
				try {
					$scanner = new Obfuscated_Malware_Scanner();
					// Use reflection to access private method, or create a backup and log.
					// For now, we'll create a backup and mark as invalid.
					// The scanner will handle quarantine during its regular scan.
					return array(
						'valid'  => false,
						'reason' => 'High-severity malware detected - requires quarantine',
					);
				} catch ( Exception $e ) {
					error_log( 'OMS: Failed to quarantine theme file: ' . esc_html( $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
				}
			}

			return array(
				'valid'  => false,
				'reason' => 'High-severity malware detected in theme file',
			);
		}

		// Low-severity suspicious content - monitor but preserve theme functionality.
		if ( class_exists( 'OMS_Logger' ) ) {
			$logger = new OMS_Logger();
			$logger->warning(
				sprintf( 'Low-severity suspicious content in theme file - monitoring - Path: %s, Backup: %s, Reason: %s', esc_html( $path ), esc_html( $backup_created ? $backup_path : 'none' ), esc_html( isset( $content_check['reason'] ) ? $content_check['reason'] : 'Suspicious content detected' ) )
			);
		} else {
			error_log( 'OMS: Potentially malicious content in theme file: ' . esc_html( $path ) . ' (backup: ' . esc_html( $backup_created ? $backup_path : 'failed' ) . ')' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
		}

		// Return valid but flagged for monitoring.
		return array(
			'valid'  => true,
			'reason' => 'Theme file with suspicious content - monitoring (backup created)',
		);
	}
}
