<?php
/**
 * Architecture Check Script.
 * run with: php check_architecture.php
 */

require_once 'vendor/autoload.php';

use OMS\Core\Kernel;
use OMS\Providers\CoreProvider;
use OMS\Services\LoggerService;
use OMS\Services\CacheService;
use OMS\Services\DatabaseScannerService;
use OMS\Services\FileScannerService;
use OMS\Admin\AdminService;

// Mocks for WP
if (!class_exists('wpdb')) {
    require_once 'tests/mocks/class-wpdb-mock.php';
}
if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new wpdb('user', 'pass', 'db', 'host');
}

// Mock WordPress functions
if (!function_exists('is_admin')) {
    function is_admin() { return true; }
}
if (!function_exists('add_action')) {
    function add_action($tag, $callback, $priority = 10, $args = 1) {
        if ($callback instanceof Closure) {
            $name = 'Closure';
        } elseif (is_array($callback)) {
             $name = get_class($callback[0]) . '::' . $callback[1];
        } else {
             $name = (string)$callback;
        }
        echo "[WP-MOCK] Added action: $tag -> $name\n";
    }
}
if (!function_exists('add_options_page')) {
    function add_options_page($t1, $t2, $c, $s, $cb) {}
}

echo "Starting Kernel (Simulation of OMS_Plugin::init)...\n";

try {
    // Manually require files to cover non-composer environments in test
    require_once 'includes/Services/DatabaseScannerService.php';
    require_once 'includes/Interfaces/DatabaseScannerInterface.php';
    require_once 'includes/Providers/DatabaseProvider.php';

    require_once 'includes/Services/FilesystemService.php';
    require_once 'includes/Services/FileScannerService.php';
    require_once 'includes/Services/UploadMonitorService.php';
    require_once 'includes/Interfaces/FileScannerInterface.php';
    require_once 'includes/Providers/SecurityProvider.php';

    require_once 'includes/Admin/AdminService.php';
    require_once 'includes/Providers/AdminProvider.php';

    // Verify the exact stack used in OMS_Plugin
    $providers = [
        OMS\Providers\CoreProvider::class,
        OMS\Providers\DatabaseProvider::class,
        OMS\Providers\SecurityProvider::class,
        OMS\Providers\AdminProvider::class,
    ];

    $kernel = new Kernel($providers);
    $kernel->run();

    $container = $kernel->get_container();

    echo "[SUCCESS] Kernel booted successfully with standard provider stack.\n";
    echo "[SUCCESS] Services Resolved:\n";
    echo " - Logger: " . get_class($container->get(LoggerService::class)) . "\n";
    echo " - DB Scanner: " . get_class($container->get(DatabaseScannerService::class)) . "\n";
    echo " - File Scanner: " . get_class($container->get(FileScannerService::class)) . "\n";
    echo " - Admin Service: " . get_class($container->get(AdminService::class)) . "\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    exit(1);
}
