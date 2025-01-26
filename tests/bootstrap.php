<?php
/**
 * PHPUnit bootstrap file
 */

// Increase memory limit for tests
ini_set('memory_limit', '512M');

// Composer autoloader must be loaded before WP_PHPUNIT__DIR will be available
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WordPress constants
if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/wordpress/');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
}

if (!defined('WP_CONTENT_URL')) {
    define('WP_CONTENT_URL', 'http://example.com/wp-content');
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

// Create WordPress directories if they don't exist
$dirs = array(
    ABSPATH,
    WP_CONTENT_DIR,
    WP_PLUGIN_DIR,
    WP_CONTENT_DIR . '/oms-logs'
);

foreach ($dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Include WordPress function mocks
require_once __DIR__ . '/wp-functions-mock.php';

// Include plugin classes
require_once __DIR__ . '/../class-oms-exception.php';
require_once __DIR__ . '/../class-oms-utils.php';
require_once __DIR__ . '/../class-oms-config.php';
require_once __DIR__ . '/../class-oms-logger.php';
require_once __DIR__ . '/../class-obfuscated-malware-scanner.php';
