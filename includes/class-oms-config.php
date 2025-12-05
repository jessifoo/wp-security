<?php
/**
 * Configuration class for malware scanner.
 *
 * This class defines all the configuration constants used by the malware scanner,
 * including file patterns, severity levels, and scan settings.
 *
 * @package ObfuscatedMalwareScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * Configuration class for malware scanner.
 */
class OMS_Config {
	/**
	 * Malware pattern severity levels.
	 */
	const SEVERITY_LEVELS = array(
		'CRITICAL' => 5,  // Immediate action required.
		'HIGH'     => 4,  // Likely malicious.
		'MEDIUM'   => 3,  // Suspicious, needs context.
		'LOW'      => 2,  // Potentially suspicious.
		'INFO'     => 1,  // Informational only.
	);

	/**
	 * Advanced obfuscation detection patterns.
	 */
	const OBFUSCATION_PATTERNS = array(
		// Variable function calls.
		array(
			'pattern'     => '\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\s*=\s*[\'"](?:eval|assert|base64_decode|gzinflate|str_rot13|gzuncompress|strrev|substr|chr|ord|include|require|file|curl|wget|system)[\'"];\s*\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\s*\(',
			'severity'    => 'CRITICAL',
			'description' => 'Variable function call obfuscation detected.',
		),

		// String splitting/concatenation.
		array(
			'pattern'     => '(?:chr\(\d+\)\.){3,}|(?:[\'"][^\'"]{1,2}[\'"]\s*\.){3,}',
			'severity'    => 'HIGH',
			'description' => 'String splitting obfuscation detected.',
		),

		// Multi-layer encoding.
		array(
			'pattern'     => 'base64_decode\s*\(\s*(?:str_rot13|gzinflate|strrev|convert_uudecode)\s*\([^\)]+\)\s*\)',
			'severity'    => 'CRITICAL',
			'description' => 'Multi-layer encoding detected.',
		),

		// WordPress hook abuse.
		array(
			'pattern'     => 'add_action\s*\(\s*[\'"](?:admin_init|wp_head|wp_footer|init|admin_notices)[\'"]\s*,\s*(?:\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*|\s*create_function\s*\([^\)]*\)|\s*(?:base64_decode|eval)\s*\([^\)]*\))',
			'severity'    => 'HIGH',
			'description' => 'WordPress hook abuse detected.',
		),

		// Common Hostinger backdoors.
		array(
			'pattern'     => '(?:FilesMan|WSO|C99|r57|shell_exec|passthru|backdoor|rootkit|hacktools|hacktool|phpshell|php_shell|shell\.php)',
			'severity'    => 'CRITICAL',
			'description' => 'Known backdoor signature detected.',
		),

		// Encoded PHP tags.
		array(
			'pattern'     => '\\\\x3c\\\\x3f(?:php)?|\\\\x3c\\\\x25|\\\\x25\\\\x3e|\\\\x3f\\\\x3e',
			'severity'    => 'HIGH',
			'description' => 'Encoded PHP tags detected.',
		),

		// Hidden code in images.
		array(
			'pattern'     => '\xFF\xD8\xFF\xE0(?:.*)<\?php',
			'severity'    => 'CRITICAL',
			'description' => 'PHP code hidden in image detected.',
		),

		// Dynamic function creation.
		array(
			'pattern'     => 'create_function\s*\(\s*[\'"][^\'"]*[\'"]\s*,\s*(?:\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*|base64_decode|eval)',
			'severity'    => 'CRITICAL',
			'description' => 'Dynamic function creation detected.',
		),

		// Malicious WordPress options.
		array(
			'pattern'     => 'update_option\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*(?:\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*|base64_decode|eval)',
			'severity'    => 'HIGH',
			'description' => 'Malicious WordPress option detected.',
		),

		// Common upload directory exploits.
		array(
			'pattern'     => 'wp-content\/uploads\/[^\'"]+\.php',
			'severity'    => 'HIGH',
			'description' => 'PHP file in uploads directory detected.',
		),
	);

	/**
	 * File categories and how to handle them.
	 */
	const FILE_CATEGORIES = array(
		'core'    => array(
			'path'     => ABSPATH,
			'patterns' => array(
				'wp-admin/**/*.php',
				'wp-includes/**/*.php',
				'*.php',
			),
			'exclude'  => array(
				'wp-content',
			),
		),
		'plugins' => array(
			'path'     => WP_PLUGIN_DIR,
			'patterns' => array(
				'**/*.php',
			),
		),
		'themes'  => array(
			'path'     => WP_CONTENT_DIR . '/themes',
			'patterns' => array(
				'**/*.php',
			),
		),
		'uploads' => array(
			'path'     => WP_CONTENT_DIR . '/uploads',
			'patterns' => array(
				'**/*',
			),
		),
	);

	/**
	 * Files that can be zero bytes.
	 */
	const ALLOWED_EMPTY_FILES = array(
		'index.php',
		'.htaccess',
		'robots.txt',
	);

	/**
	 * Files that must never be zero bytes.
	 */
	const CRITICAL_FILES = array(
		'wp-config.php',
		'wp-settings.php',
		'wp-load.php',
		'wp-blog-header.php',
		'wp-cron.php',
	);

	/**
	 * Suspicious file modification times.
	 */
	const SUSPICIOUS_TIMES = array(
		'night_hours'  => array( 0, 5 ),  // Suspicious if modified between midnight and 5am.
		'max_age_days' => 7,           // Flag files modified in last week.
	);

	/**
	 * Definitely malicious patterns that should always be removed.
	 */
	const MALICIOUS_PATTERNS = array(
		// Base64 encoded eval.
		'eval\s*\(\s*base64_decode\s*\([^\)]+\)\s*\)',
		// Encoded strings with eval.
		'eval\s*\(\s*[\'"][a-zA-Z0-9+\/]+={0,2}[\'"]\s*\)',
		// Shell command execution.
		'(shell_exec|system|passthru|exec|popen)\s*\([^\)]*\)',
		// Remote file inclusion.
		'include(_once)?\s*\(\s*[\'"]https?:\/\/ ',
		// Malicious WordPress hooks.
		'add_(action|filter)\s*\(\s*[\'"]wp_head[\'"]\s*,.*base64',
		// Common malware functions.
		'(assert|create_function)\s*\([^\)]+\)',
		// Malicious iframe injection.
		'<iframe\s+src=[\'"]https?:\/\/[^\'"]+[\'"][^>]*style=[\'"]display:\s*none;?[\'"]',
		// SEO spam injection.
		'<div\s+style=[\'"]display:\s*none;?[\'"]\s*>[^<]*<a\s+href=',
		// Malicious redirects.
		'header\s*\(\s*[\'"]Location:\s*https?:\/\/ ',
		// WordPress database manipulation.
		'\$wpdb->query\s*\(\s*[\'"](?:INSERT|UPDATE|DELETE)',
		// Malicious file operations.
		'(copy|rename|unlink|rmdir|mkdir)\s*\(\s*\$_(GET|POST|REQUEST)',
		// Remote POST data.
		'file_(get|put)_contents\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*\$_(GET|POST|REQUEST)',
	);

	const MALWARE_PATTERNS = array(
		array(
			'pattern'     => '/eval\\s*\\(.*\\$.*\\)/i',
			'severity'    => 'CRITICAL',
			'description' => 'Dynamic code execution (eval)',
		),
		array(
			'pattern'     => '/base64_decode\\s*\\([^)]*\\)/i',
			'severity'    => 'HIGH',
			'description' => 'Base64 decoded execution',
		),
		array(
			'pattern'     => '/\\$[a-z0-9_]+\\s*\\(\\s*\\$[^)]+\\)/i',
			'severity'    => 'MEDIUM',
			'description' => 'Variable function call',
		),
		array(
			'pattern'     => '/preg_replace\\s*\\([^)]*\\/e/i',
			'severity'    => 'CRITICAL',
			'description' => 'Executable regex replacement',
		),
		array(
			'pattern'     => '/assert\\s*\\([^)]+\\)/i',
			'severity'    => 'HIGH',
			'description' => 'Assert execution',
		),
		array(
			'pattern'     => '/\\b(?:exec|shell_exec|system|passthru|popen)\\s*\\([^)]*\\)/i',
			'severity'    => 'CRITICAL',
			'description' => 'System command execution',
		),
		array(
			'pattern'     => '/\\b(?:file_get_contents|fopen|file)\\s*\\([^)]*(?:https?:|ftp:)[^)]+\\)/i',
			'severity'    => 'MEDIUM',
			'description' => 'Remote file operations',
		),
		array(
			'pattern'     => '/\$_(?:GET|POST|REQUEST|COOKIE)\s*\[[\'\"](?:\w+)[\'\"]\]\s*\([^)]*\)/i',
			'severity'    => 'HIGH',
			'description' => 'User input execution',
		),
		array(
			'pattern'     => '/(?:chr|ord|gzinflate|str_rot13|convert_uudecode|base64_decode|urldecode)\\s*\\(\\s*(?:chr|ord|gzinflate|str_rot13|convert_uudecode|base64_decode|urldecode)\\s*\\(/i',
			'severity'    => 'HIGH',
			'description' => 'Nested encoding',
		),
	);

	/**
	 * Database malware patterns for content scanning.
	 */
	const DATABASE_MALWARE_PATTERNS = array(
		array(
			'pattern'     => '/eval\s*\(/i',
			'severity'    => 'CRITICAL',
			'description' => 'Eval() function detected in database',
		),
		array(
			'pattern'     => '/base64_decode\s*\(/i',
			'severity'    => 'HIGH',
			'description' => 'Base64 decode detected in database',
		),
		array(
			'pattern'     => '/exec\s*\(|shell_exec\s*\(|system\s*\(|passthru\s*\(/i',
			'severity'    => 'CRITICAL',
			'description' => 'System command execution detected in database',
		),
		array(
			'pattern'     => '/<script[^>]*>.*eval/i',
			'severity'    => 'HIGH',
			'description' => 'JavaScript eval detected in database',
		),
		array(
			'pattern'     => '/<iframe[^>]*src=["\'](?:javascript|data):/i',
			'severity'    => 'HIGH',
			'description' => 'Malicious iframe detected in database',
		),
		array(
			'pattern'     => '/\$_GET\[|\$_POST\[|\$_REQUEST\[|\$_COOKIE\[.*\)\s*\(/i',
			'severity'    => 'HIGH',
			'description' => 'User input execution detected in database',
		),
		array(
			'pattern'     => '/gzinflate\s*\(|gzuncompress\s*\(|str_rot13\s*\(/i',
			'severity'    => 'MEDIUM',
			'description' => 'Obfuscation function detected in database',
		),
		array(
			'pattern'     => '/wp_remote_get\s*\(.*\$_(?:GET|POST|REQUEST)/i',
			'severity'    => 'HIGH',
			'description' => 'Remote request with user input detected in database',
		),
		array(
			'pattern'     => '/file_get_contents\s*\(.*\$_(?:GET|POST|REQUEST)/i',
			'severity'    => 'HIGH',
			'description' => 'File operation with user input detected in database',
		),
		array(
			'pattern'     => '/\?php.*eval|\?php.*base64_decode|\?php.*exec/i',
			'severity'    => 'CRITICAL',
			'description' => 'PHP code execution detected in database',
		),
	);

	/**
	 * Suspicious patterns that need context checking.
	 */
	const SUSPICIOUS_PATTERNS = array(
		// File operations.
		'file_(get|put)_contents',
		'fopen',
		'readfile',
		'unlink',
		'rmdir',
		'mkdir',
		'chmod',
		'chown',
		'touch',

		// System commands.
		'system',
		'exec',
		'shell_exec',
		'passthru',
		'proc_open',
		'popen',

		// Code execution.
		'eval',
		'assert',
		'create_function',
		'call_user_func',
		'call_user_func_array',
		'preg_replace_callback',

		// Database.
		'mysql_query',
		'mysqli_query',
		'\$wpdb->query',
		'INSERT INTO',
		'UPDATE.*SET',
		'DELETE FROM',

		// Network.
		'curl_exec',
		'file_get_contents',
		'fsockopen',
		'pfsockopen',
		'stream_socket_client',
		'socket_create',

		// Potentially dangerous.
		'base64_decode',
		'gzinflate',
		'gzuncompress',
		'strrev',
		'str_rot13',
		'convert_uudecode',
		'hebrev',
	);
	const QUARANTINE_CONFIG   = array(
		'path'               => WP_CONTENT_DIR . '/uploads/oms-scanner/quarantine',
		'retention_days'     => 30,
		'max_size'           => 500 * 1024 * 1024,  // 500MB max quarantine size.
		'cleanup_batch_size' => 50,                // Files to process per cleanup batch.
	);

	const RATE_LIMIT_CONFIG = array(
		'max_cpu_load'       => 80,
		'max_memory_percent' => 80,
		'requests_per_hour'  => 100,
		'peak_hour_start'    => 9,
		'peak_hour_end'      => 17,
	);

	const SCAN_CONFIG = array(
		'chunk_size'          => 1024 * 1024,     // 1MB default chunk size.
		'min_chunk_size'      => 1024 * 1024,     // 1MB min chunk size.
		'max_chunk_size'      => 10 * 1024 * 1024, // 10MB max chunk size.
		'overlap_size'        => 1024,            // 1KB overlap between chunks.
		'batch_size'          => 100,             // Files per batch.
		'batch_pause'         => 100,             // Milliseconds between batches.
		'max_file_size'       => 100 * 1024 * 1024, // 100MB max file size.
		'memory_limit'        => '80%',           // Max memory usage percent.
		'allowed_permissions' => array(
			'file' => 0644,
			'dir'  => 0755,
		),
		'excluded_dirs'       => array( '.git', 'node_modules', 'vendor' ),
		'excluded_files'      => array( '.DS_Store', 'Thumbs.db' ),
	);

	const SECURITY_CONFIG = array(
		'max_execution_time'  => 300,     // 5 minutes.
		'memory_limit'        => '256M',
		'max_input_time'      => 60,
		'max_input_vars'      => 1000,
		'post_max_size'       => '64M',
		'upload_max_filesize' => '32M',
		'allowed_permissions' => array(
			'file'      => 0644,
			'directory' => 0755,
		),
	);

	const LOG_CONFIG = array(
		'path'   => WP_CONTENT_DIR . '/uploads/oms-scanner/logs',
		'levels' => array( 'debug', 'info', 'warning', 'error', 'critical' ),
	);

	const CACHE_CONFIG = array(
		'ttl'      => 3600, // 1 hour default TTL.
		'max_size' => 1000, // Max number of items in cache.
	);

	const NOTIFICATION_THRESHOLD = 'MEDIUM';

	/**
	 * Common WordPress terms for filename validation.
	 */
	const FILENAME_DICTIONARY = array(
		'wp',
		'wordpress',
		'admin',
		'content',
		'includes',
		'class',
		'style',
		'script',
		'index',
		'login',
		'user',
		'post',
		'ajax',
		'comment',
		'feed',
		'mail',
		'cron',
		'settings',
		'signup',
		'trackback',
		'xmlrpc',
		'theme',
		'plugin',
		'widget',
		'nav',
		'menu',
		'header',
		'footer',
		'sidebar',
		'page',
		'template',
		'functions',
		'config',
		'load',
		'activate',
		'links',
		'opml',
		'error',
		'tinymce',
		'jquery',
		'json',
		'rest',
		'api',
		'block',
		'editor',
		'media',
		'image',
		'upload',
	);

	/**
	 * Linking key for centralized management handshake.
	 *
	 * @var string
	 */
	const OMS_LINKING_KEY = 'oms_secret_key_change_me';
}
