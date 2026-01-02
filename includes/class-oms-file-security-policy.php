<?php
declare(strict_types=1);

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
	 * List of forbidden file extensions
	 *
	 * @var string[]
	 */
	private array $forbidden_extensions = array(
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
	 * @var string[]
	 */
	private array $restricted_paths = array(
		'wp-admin',
		'wp-includes',
		'wp-config.php',
	);

	/**
	 * List of protected theme paths
	 *
	 * @var string[]
	 */
	private array $protected_theme_paths = array(
		'wp-content/themes/astra',
		'wp-content/plugins/elementor',
	);

	/**
	 * Suspicious file permissions
	 *
	 * @var array<string, int>
	 */
	private array $suspicious_perms = array(
		'executable' => 0111,  // Any execute permission.
	);

	/**
	 * Suspicious modification times
	 *
	 * @var array<string, int[]>
	 */
	private array $suspicious_times = array(
		'night_hours' => array( 0, 4 ),  // Suspicious between midnight and 4 AM.
	);

	/**
	 * Protected paths that should never be modified
	 *
	 * @var string[]
	 */
	private array $protected_paths = array(
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
	 * @var string[]
	 */
	private array $known_good_patterns = array(
		'/\.min\.(js|css)$/',  // Minified assets.
		'/elementor.*\.js$/',  // Elementor scripts.
		'/astra.*\.js$/',      // Astra scripts.
	);

	/**
	 * Constructor.
	 *
	 * @param OMS_Filesystem $filesystem Filesystem instance.
	 */
	public function __construct( private readonly OMS_Filesystem $filesystem ) {}

	/**
	 * Validate a file against security policy
	 *
	 * @param string $path Full path to file.
	 * @param array  $options Validation options.
	 * @return array{valid: bool, reason?: string} Validation result.
	 * @throws OMS_Security_Exception If validation fails critically.
	 */
	public function validate_file( string $path, array $options = array() ): array {
		try {
			// Verify nonce if provided.
			if ( isset( $options['nonce'] ) && ! wp_verify_nonce( (string) $options['nonce'], 'oms_file_validation' ) ) {
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
	 * @return array{valid: bool, reason?: string} Validation result.
	 * @throws OMS_Security_Exception If file does not exist.
	 */
	private function validate_file_basics( string $path ): array {
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
	 * @return array{valid: bool, reason?: string} Validation result.
	 */
	private function validate_file_metadata( string $path ): array {
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
		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
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
	 * @return array{valid: bool, reason?: string} Validation result.
	 */
	private function validate_file_path_security( string $path ): array {
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
	 * @return array{valid: bool, reason?: string} Validation result.
	 */
	private function validate_file_content_security( string $path ): array {
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

		if ( ! $content_check['safe'] ) {
			// If it's a theme file, we need to be more careful.
			if ( $is_theme_file ) {
				return $this->handle_theme_file_with_suspicious_content( $path, $content_check, $relative_path );
			}
			return array(
				'valid'  => false,
				'reason' => $content_check['reason'],
			);
		}

		return array( 'valid' => true );
	}

	/**
	 * Validate file system security (permissions, time).
	 *
	 * @param string $path File path.
	 * @return array{valid: bool, reason?: string} Validation result.
	 */
	private function validate_file_system_security( string $path ): array {
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
			error_log( 'Theme file modified during suspicious hours: ' . esc_html( $path ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return array( 'valid' => true );
	}

	/**
	 * Check if a file is in a protected path
	 *
	 * @param string $file_path File path to check.
	 * @return bool True if file is in protected path.
	 */
	public function is_protected_path( string $file_path ): bool {
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
	private function is_random_filename( string $filename ): bool {
		// Remove extension.
		$name = pathinfo( $filename, PATHINFO_FILENAME );

		// Skip short filenames.
		if ( strlen( $name ) < 5 ) {
			return false;
		}

		// Calculate entropy.
		$entropy = 0.0;
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
			$match_count = preg_match_all( '/[0-9]/', $name );
			$digits      = ( false !== $match_count ) ? $match_count : 0;
			$ratio       = $digits / $size;

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
	public function is_known_good_file( string $file_path ): bool {
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
	private function get_relative_path( string $file_path ): string {
		return str_replace( ABSPATH, '', $file_path );
	}

	/**
	 * Add a restricted path
	 *
	 * @param string $path Path to restrict.
	 * @return void
	 */
	public function add_restricted_path( string $path ): void {
		$this->restricted_paths[] = $path;
	}

	/**
	 * Add a forbidden extension
	 *
	 * @param string $ext Extension to forbid.
	 * @return void
	 */
	public function add_forbidden_extension( string $ext ): void {
		$this->forbidden_extensions[] = $ext;
	}

	/**
	 * Handle theme file with suspicious content using content preservation logic.
	 *
	 * @param string $path Full path to the theme file.
	 * @param array  $content_check Content check result from OMS_Utils::check_file_content().
	 * @param string $relative_path Relative path from WordPress root.
	 * @return array{valid: bool, reason: string} Validation result with 'valid' boolean and 'reason' string.
	 */
	private function handle_theme_file_with_suspicious_content( string $path, array $content_check, string $relative_path ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Check if file matches known-good patterns.
		if ( $this->is_known_good_file( $path ) ) {
			return array(
				'valid'  => true,
				'reason' => 'Theme file matches known-good pattern - safe',
			);
		}

		// Read file content for detailed analysis.
		$file_content = file_get_contents( $path );
		if ( false === $file_content ) {
			return array(
				'valid'  => false,
				'reason' => 'Cannot read theme file for analysis',
			);
		}

		// Create backup and check severity.
		$backup_path       = $this->create_theme_file_backup( $path );
		$backup_created    = ( false !== $backup_path );
		$has_high_severity = $this->has_high_severity_patterns( $file_content );

		// Handle based on severity.
		if ( $has_high_severity ) {
			return $this->handle_high_severity_theme_file( $path, $backup_path, $backup_created, $content_check );
		}

		// Low-severity - monitor and allow.
		$this->log_suspicious_theme_file( $path, $backup_path, $backup_created, $content_check, 'low' );

		return array(
			'valid'  => true,
			'reason' => 'Theme file with suspicious content - monitoring (backup created)',
		);
	}

	/**
	 * Check if file content contains high-severity malware patterns.
	 *
	 * @param string $file_content File content to check.
	 * @return bool True if high-severity patterns found.
	 */
	private function has_high_severity_patterns( string $file_content ): bool {
		$high_severity_patterns = array(
			'eval\s*\(',
			'base64_decode\s*\(',
			'exec\s*\(',
			'system\s*\(',
			'shell_exec\s*\(',
			'passthru\s*\(',
		);

		foreach ( $high_severity_patterns as $pattern ) {
			if ( preg_match( '/' . $pattern . '/i', $file_content ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Create backup of theme file.
	 *
	 * @param string $path Path to the file to backup.
	 * @return string|false Backup path on success, false on failure.
	 */
	private function create_theme_file_backup( string $path ): string|false {
		$backup_dir = WP_CONTENT_DIR . '/oms-theme-backups';

		// Ensure backup directory exists.
		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
			$this->create_htaccess_protection( $backup_dir );
		}

		// Create backup.
		$backup_filename = sanitize_file_name( basename( $path ) . '.' . gmdate( 'Y-m-d-H-i-s' ) . '.backup' );
		$backup_path     = $backup_dir . '/' . $backup_filename;

		if ( copy( $path, $backup_path ) ) {
			return $backup_path;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
		error_log( 'OMS: Failed to create backup of theme file: ' . esc_html( $path ) );
		return false;
	}

	/**
	 * Create .htaccess protection for a directory.
	 *
	 * @param string $dir_path Directory path.
	 * @return void
	 */
	private function create_htaccess_protection( string $dir_path ): void {
		$htaccess_file = $dir_path . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$result = file_put_contents( $htaccess_file, "Order deny,allow\nDeny from all\nRequire all denied\n" );
			if ( false === $result ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
				error_log( 'OMS: Failed to create .htaccess file: ' . esc_html( $htaccess_file ) );
			}
		}
	}

	/**
	 * Handle high-severity malware in theme file.
	 *
	 * @param string       $path Path to the file.
	 * @param string|false $backup_path Backup path or false.
	 * @param bool         $backup_created Whether backup was created.
	 * @param array        $content_check Content check result.
	 * @return array{valid: bool, reason: string} Validation result.
	 */
	private function handle_high_severity_theme_file( string $path, string|false $backup_path, bool $backup_created, array $content_check ): array {
		$this->log_suspicious_theme_file( $path, $backup_path, $backup_created, $content_check, 'high' );

		return array(
			'valid'  => false,
			'reason' => 'High-severity malware detected in theme file',
		);
	}

	/**
	 * Log suspicious theme file detection.
	 *
	 * @param string       $path Path to the file.
	 * @param string|false $backup_path Backup path or false.
	 * @param bool         $backup_created Whether backup was created.
	 * @param array        $content_check Content check result.
	 * @param string       $severity 'high' or 'low'.
	 * @return void
	 */
	private function log_suspicious_theme_file( string $path, string|false $backup_path, bool $backup_created, array $content_check, string $severity ): void {
		$reason      = isset( $content_check['reason'] ) ? (string) $content_check['reason'] : 'Suspicious content detected';
		$backup_info = ( $backup_created && is_string( $backup_path ) ) ? $backup_path : 'none';

		if ( 'high' === $severity ) {
			$message = sprintf(
				'High-severity malware detected in theme file - Path: %s, Backup: %s, Reason: %s',
				esc_html( $path ),
				esc_html( $backup_info ),
				esc_html( $reason )
			);
		} else {
			$message = sprintf(
				'Low-severity suspicious content in theme file - monitoring - Path: %s, Backup: %s, Reason: %s',
				esc_html( $path ),
				esc_html( $backup_info ),
				esc_html( $reason )
			);
		}

		if ( class_exists( 'OMS_Logger' ) ) {
			$logger = new OMS_Logger();
			$logger->warning( $message );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
			error_log( 'OMS: ' . $message );
		}
	}
}
