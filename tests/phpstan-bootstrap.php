<?php
// Define constants to prevent side-effects during analysis
if ( ! defined( 'OMS_TEST_MODE' ) ) {
	define( 'OMS_TEST_MODE', true );
}
if ( ! defined( 'OMS_NO_INIT' ) ) {
	define( 'OMS_NO_INIT', true );
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}

// Include WordPress stubs if not already loaded (handled by extension usually)
// require_once __DIR__ . '/../vendor/php-stubs/wordpress-stubs/wordpress-stubs.php';

// Include the main plugin file to load classes.
require_once dirname( __DIR__ ) . '/obfuscated-malware-scanner.php';
