<?php
/**
 * PHPUnit bootstrap file
 *
 * @package ObfuscatedMalwareScanner\Tests
 */

// Increase memory limit for tests.
// phpcs:ignore WordPress.PHP.IniSet.memory_limit_Disallowed -- Required for large test suites.
ini_set( 'memory_limit', '512M' );

define( 'OMS_TEST_MODE', true );

// Composer autoloader must be loaded before WP_PHPUNIT__DIR will be available
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define WordPress constants
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/wordpress/' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}

if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}

if ( ! defined( 'WP_CONTENT_URL' ) ) {
	define( 'WP_CONTENT_URL', 'http://example.com/wp-content' );
}

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

// Create WordPress directories if they don't exist
$dirs = array(
	ABSPATH,
	WP_CONTENT_DIR,
	WP_PLUGIN_DIR,
	WP_CONTENT_DIR . '/oms-logs',
);

foreach ( $dirs as $dir ) {
	if ( ! file_exists( $dir ) ) {
		mkdir( $dir, 0777, true );
	}
}

// Include WordPress function mocks
require_once __DIR__ . '/wp-functions-mock.php';

// Include plugin classes
require_once __DIR__ . '/../includes/class-oms-exception.php';
require_once __DIR__ . '/../includes/class-oms-error-handler.php';
require_once __DIR__ . '/../includes/class-oms-utils.php';
require_once __DIR__ . '/../includes/class-oms-config.php';
require_once __DIR__ . '/../includes/class-oms-logger.php';
require_once __DIR__ . '/../includes/class-oms-cache.php';
require_once __DIR__ . '/../includes/class-oms-rate-limiter.php';
require_once __DIR__ . '/../includes/class-oms-filesystem.php';
require_once __DIR__ . '/../includes/class-oms-database-cleaner.php';
require_once __DIR__ . '/../includes/class-oms-database-scanner.php';
require_once __DIR__ . '/../includes/class-oms-core-integrity-checker.php';
require_once __DIR__ . '/../includes/class-oms-quarantine-manager.php';
require_once __DIR__ . '/../includes/class-oms-api.php';
require_once __DIR__ . '/../includes/class-oms-plugin.php';
require_once __DIR__ . '/../includes/class-oms-file-security-policy.php';
require_once __DIR__ . '/../admin/class-oms-admin.php';
require_once __DIR__ . '/../includes/class-obfuscated-malware-scanner.php';
