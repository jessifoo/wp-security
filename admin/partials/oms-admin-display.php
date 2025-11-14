<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @package    ObfuscatedMalwareScanner
 * @subpackage ObfuscatedMalwareScanner/admin/partials
 */

?>
<div class="wrap">
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
	
	<div class="oms-admin-content">
		<div class="oms-status-panel">
			<h3><?php esc_html_e( 'Scanner Status', 'obfuscated-malware-scanner' ); ?></h3>
			<div class="oms-status">
				<?php
				$scanner        = new Obfuscated_Malware_Scanner();
				$scanner_status = $scanner->get_status();
				echo '<p><strong>' . esc_html__( 'Last Scan:', 'obfuscated-malware-scanner' ) . '</strong> ' .
					esc_html( $scanner_status['last_scan'] ) . '</p>';
				echo '<p><strong>' . esc_html__( 'Files Scanned:', 'obfuscated-malware-scanner' ) . '</strong> ' .
					esc_html( $scanner_status['files_scanned'] ) . '</p>';
				echo '<p><strong>' . esc_html__( 'Issues Found:', 'obfuscated-malware-scanner' ) . '</strong> ' .
					esc_html( $scanner_status['issues_found'] ) . '</p>';
				?>
			</div>
		</div>

		<div class="oms-actions">
			<form method="post" action="options.php">
				<?php
				settings_fields( 'oms_options' );
				do_settings_sections( 'oms_options' );
				submit_button( esc_html__( 'Save Settings', 'obfuscated-malware-scanner' ) );
				?>
			</form>

			<form method="post" action="">
				<?php wp_nonce_field( 'oms_manual_scan' ); ?>
				<input type="hidden" name="oms_manual_scan" value="1" />
				<?php submit_button( esc_html__( 'Start Manual Scan', 'obfuscated-malware-scanner' ), 'secondary' ); ?>
			</form>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameter used for display only, not form processing.
			$scan_param = isset( $_GET['scan'] ) && is_string( $_GET['scan'] ) ? sanitize_text_field( wp_unslash( $_GET['scan'] ) ) : '';
			if ( 'complete' === $scan_param ) :
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Scan completed successfully!', 'obfuscated-malware-scanner' ); ?></p>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $scanner_status['issues'] ) ) : ?>
		<div class="oms-issues">
			<h3><?php esc_html_e( 'Detected Issues', 'obfuscated-malware-scanner' ); ?></h3>
			<table class="widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'File', 'obfuscated-malware-scanner' ); ?></th>
						<th><?php esc_html_e( 'Issue', 'obfuscated-malware-scanner' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'obfuscated-malware-scanner' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $scanner_status['issues'] as $issue ) : ?>
					<tr>
						<td><?php echo esc_html( $issue['file'] ); ?></td>
						<td><?php echo esc_html( $issue['description'] ); ?></td>
						<td>
							<button class="button" data-file="<?php echo esc_attr( $issue['file'] ); ?>">
								<?php esc_html_e( 'Review', 'obfuscated-malware-scanner' ); ?>
							</button>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
	</div>
</div>
