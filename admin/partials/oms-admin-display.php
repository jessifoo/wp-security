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
            <h3><?php _e( 'Scanner Status', 'obfuscated-malware-scanner' ); ?></h3>
            <div class="oms-status">
                <?php
                $scanner = new Obfuscated_Malware_Scanner();
                $status = $scanner->get_status();
                echo '<p><strong>' . __( 'Last Scan:', 'obfuscated-malware-scanner' ) . '</strong> ' . 
                     esc_html( $status['last_scan'] ) . '</p>';
                echo '<p><strong>' . __( 'Files Scanned:', 'obfuscated-malware-scanner' ) . '</strong> ' . 
                     esc_html( $status['files_scanned'] ) . '</p>';
                echo '<p><strong>' . __( 'Issues Found:', 'obfuscated-malware-scanner' ) . '</strong> ' . 
                     esc_html( $status['issues_found'] ) . '</p>';
                ?>
            </div>
        </div>

        <div class="oms-actions">
            <form method="post" action="options.php">
                <?php
                settings_fields( 'oms_options' );
                do_settings_sections( 'oms_options' );
                submit_button( __( 'Start Scan', 'obfuscated-malware-scanner' ) );
                ?>
            </form>
        </div>

        <?php if ( ! empty( $status['issues'] ) ) : ?>
        <div class="oms-issues">
            <h3><?php _e( 'Detected Issues', 'obfuscated-malware-scanner' ); ?></h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e( 'File', 'obfuscated-malware-scanner' ); ?></th>
                        <th><?php _e( 'Issue', 'obfuscated-malware-scanner' ); ?></th>
                        <th><?php _e( 'Actions', 'obfuscated-malware-scanner' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $status['issues'] as $issue ) : ?>
                    <tr>
                        <td><?php echo esc_html( $issue['file'] ); ?></td>
                        <td><?php echo esc_html( $issue['description'] ); ?></td>
                        <td>
                            <button class="button" data-file="<?php echo esc_attr( $issue['file'] ); ?>">
                                <?php _e( 'Review', 'obfuscated-malware-scanner' ); ?>
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
