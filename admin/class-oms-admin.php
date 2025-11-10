<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    ObfuscatedMalwareScanner
 * @subpackage ObfuscatedMalwareScanner/admin
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for the admin area.
 *
 * @package    ObfuscatedMalwareScanner
 * @subpackage ObfuscatedMalwareScanner/admin
 */
class OMS_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/oms-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/oms-admin.js',
			array( 'jquery' ),
			$this->version,
			false
		);
	}

	/**
	 * Add an options page under the Settings submenu
	 */
	public function add_options_page() {
		add_options_page(
			__( 'Obfuscated Malware Scanner Settings', 'obfuscated-malware-scanner' ),
			__( 'Malware Scanner', 'obfuscated-malware-scanner' ),
			'manage_options',
			$this->plugin_name,
			array( $this, 'display_options_page' )
		);
	}

	/**
	 * Render the options page for plugin
	 */
	public function display_options_page() {
		include_once 'partials/oms-admin-display.php';
	}

	/**
	 * Register plugin settings using WordPress Settings API.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		// Register settings.
		register_setting(
			'oms_options',
			'oms_scan_schedule',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'daily',
			)
		);

		register_setting(
			'oms_options',
			'oms_auto_quarantine',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		register_setting(
			'oms_options',
			'oms_email_notifications',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		// Add settings section.
		add_settings_section(
			'oms_main_section',
			__( 'Scanner Settings', 'obfuscated-malware-scanner' ),
			array( $this, 'render_main_section' ),
			'oms_options'
		);

		// Add settings fields.
		add_settings_field(
			'oms_scan_schedule',
			__( 'Scan Schedule', 'obfuscated-malware-scanner' ),
			array( $this, 'render_scan_schedule_field' ),
			'oms_options',
			'oms_main_section'
		);

		add_settings_field(
			'oms_auto_quarantine',
			__( 'Auto Quarantine', 'obfuscated-malware-scanner' ),
			array( $this, 'render_auto_quarantine_field' ),
			'oms_options',
			'oms_main_section'
		);

		add_settings_field(
			'oms_email_notifications',
			__( 'Email Notifications', 'obfuscated-malware-scanner' ),
			array( $this, 'render_email_notifications_field' ),
			'oms_options',
			'oms_main_section'
		);
	}

	/**
	 * Render main settings section description.
	 *
	 * @since 1.0.0
	 */
	public function render_main_section() {
		echo '<p>' . esc_html__( 'Configure automatic malware scanning and cleanup.', 'obfuscated-malware-scanner' ) . '</p>';
	}

	/**
	 * Render scan schedule field.
	 *
	 * @since 1.0.0
	 */
	public function render_scan_schedule_field() {
		$value = get_option( 'oms_scan_schedule', 'daily' );
		?>
		<select name="oms_scan_schedule" id="oms_scan_schedule">
			<option value="hourly" <?php selected( $value, 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'obfuscated-malware-scanner' ); ?></option>
			<option value="daily" <?php selected( $value, 'daily' ); ?>><?php esc_html_e( 'Daily', 'obfuscated-malware-scanner' ); ?></option>
			<option value="weekly" <?php selected( $value, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'obfuscated-malware-scanner' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'How often to run automatic scans.', 'obfuscated-malware-scanner' ); ?></p>
		<?php
	}

	/**
	 * Render auto quarantine field.
	 *
	 * @since 1.0.0
	 */
	public function render_auto_quarantine_field() {
		$value = get_option( 'oms_auto_quarantine', true );
		?>
		<label>
			<input type="checkbox" name="oms_auto_quarantine" value="1" <?php checked( $value, true ); ?> />
			<?php esc_html_e( 'Automatically quarantine detected malware files', 'obfuscated-malware-scanner' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'When enabled, malicious files will be automatically moved to quarantine.', 'obfuscated-malware-scanner' ); ?></p>
		<?php
	}

	/**
	 * Render email notifications field.
	 *
	 * @since 1.0.0
	 */
	public function render_email_notifications_field() {
		$value = get_option( 'oms_email_notifications', true );
		?>
		<label>
			<input type="checkbox" name="oms_email_notifications" value="1" <?php checked( $value, true ); ?> />
			<?php esc_html_e( 'Send email notifications when malware is detected', 'obfuscated-malware-scanner' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Receive email alerts when the scanner detects malicious files.', 'obfuscated-malware-scanner' ); ?></p>
		<?php
	}

	/**
	 * Handle manual scan action.
	 *
	 * @since 1.0.0
	 */
	public function handle_manual_scan() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'obfuscated-malware-scanner' ) );
		}

		check_admin_referer( 'oms_manual_scan' );

		$scanner = new Obfuscated_Malware_Scanner();
		$scanner->run_full_cleanup();

		update_option( 'oms_last_scan', current_time( 'mysql' ) );

		wp_redirect( admin_url( 'options-general.php?page=' . $this->plugin_name . '&scan=complete' ) );
		exit;
	}
}
