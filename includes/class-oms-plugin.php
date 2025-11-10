<?php
/**
 * Plugin initialization class
 *
 * @package ObfuscatedMalwareScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * Plugin initialization class.
 */
class OMS_Plugin {
	/**
	 * Plugin instance.
	 *
	 * @var OMS_Plugin
	 */
	private static $instance = null;

	/**
	 * Scanner instance.
	 *
	 * @var Obfuscated_Malware_Scanner
	 */
	private $scanner = null;

	/**
	 * Cache instance.
	 *
	 * @var OMS_Cache
	 */
	private $cache = null;

	/**
	 * Get plugin instance.
	 *
	 * @return OMS_Plugin Plugin instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {
		$this->cache   = new OMS_Cache();
		$this->scanner = new Obfuscated_Malware_Scanner();

		// Initialize scanner.
		$this->scanner->init();

		// Add admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Malware Scanner', 'obfuscated-malware-scanner' ),
			__( 'Malware Scanner', 'obfuscated-malware-scanner' ),
			'manage_options',
			'obfuscated-malware-scanner',
			array( $this, 'render_admin_page' ),
			'dashicons-shield'
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
               include_once OMS_PLUGIN_DIR . 'admin/partials/oms-admin-display.php';
	}
}
