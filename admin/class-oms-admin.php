<?php
declare(strict_types=1);

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
 * All dependencies are injected via constructor.
 *
 * @package    ObfuscatedMalwareScanner
 * @subpackage ObfuscatedMalwareScanner/admin
 */
class OMS_Admin {

	/**
	 * Initialize the class with dependencies.
	 *
	 * @param string                     $plugin_name The name of this plugin.
	 * @param string                     $version     The version of this plugin.
	 * @param Obfuscated_Malware_Scanner $scanner     The scanner instance.
	 */
	public function __construct(
		private readonly string $plugin_name,
		private readonly string $version,
		private readonly Obfuscated_Malware_Scanner $scanner,
	) {}

	/**
	 * Initialize admin hooks.
	 *
	 * Called from the main plugin bootstrap.
	 *
	 * @return void
	 */
	public function init(): void {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_options_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Manual scan handler.
		add_action( 'admin_init', array( $this, 'handle_manual_scan' ) );
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles(): void {
		// Only load on plugin admin pages.
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, $this->plugin_name ) ) {
			return;
		}

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
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts(): void {
		// Only load on plugin admin pages.
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, $this->plugin_name ) ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/oms-admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);
	}

	/**
	 * Add an options page under the Settings submenu.
	 *
	 * @return void
	 */
	public function add_options_page(): void {
		add_options_page(
			__( 'Obfuscated Malware Scanner Settings', 'obfuscated-malware-scanner' ),
			__( 'Malware Scanner', 'obfuscated-malware-scanner' ),
			'manage_options',
			$this->plugin_name,
			array( $this, 'display_options_page' )
		);
	}

	/**
	 * Render the options page for plugin.
	 *
	 * @return void
	 */
	public function display_options_page(): void {
		include_once 'partials/oms-admin-display.php';
	}

	/**
	 * Register plugin settings using WordPress Settings API.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings(): void {
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
				'sanitize_callback' => array( $this, 'sanitize_boolean' ),
				'default'           => true,
			)
		);

		register_setting(
			'oms_options',
			'oms_email_notifications',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_boolean' ),
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
	 * @return void
	 */
	public function render_main_section(): void {
		echo '<p>' . esc_html__( 'Configure automatic malware scanning and cleanup.', 'obfuscated-malware-scanner' ) . '</p>';
	}

	/**
	 * Render scan schedule field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_scan_schedule_field(): void {
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
	 * @return void
	 */
	public function render_auto_quarantine_field(): void {
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
	 * @return void
	 */
	public function render_email_notifications_field(): void {
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
	 * @SuppressWarnings("PHPMD.ExitExpression")
	 * Exit required after wp_safe_redirect per WordPress standards.
	 * @return void
	 */
	public function handle_manual_scan(): void {
		// Check if manual scan was requested.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below.
		if ( ! isset( $_POST['oms_manual_scan'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'obfuscated-malware-scanner' ) );
		}

		check_admin_referer( 'oms_manual_scan' );

		// Use injected scanner instance - no instantiation needed.
		$this->scanner->run_full_cleanup();

		update_option( 'oms_last_scan', current_time( 'mysql' ) );

		wp_safe_redirect( admin_url( 'options-general.php?page=' . esc_attr( $this->plugin_name ) . '&scan=complete' ) );
		exit;
	}

	/**
	 * Sanitize boolean values for settings.
	 *
	 * @since 1.0.0
	 * @param mixed $value Value to sanitize.
	 * @return bool Sanitized boolean value.
	 */
	public function sanitize_boolean( mixed $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}
}
