<?php
declare(strict_types=1);

namespace OMS\Providers;

use OMS\Core\ServiceProvider;
use OMS\Core\Container;
use OMS\Services\FilesystemService;
use OMS\Services\FileScannerService;
use OMS\Services\UploadMonitorService;
use OMS\Services\LoggerService;

class SecurityProvider implements ServiceProvider {

    public function register(Container $container): void {
        // Register Low Level Infrastructure
        $container->singleton(FilesystemService::class, function(Container $c) {
            return new FilesystemService();
        });

        // Register Business Logic
        $container->singleton(FileScannerService::class, function(Container $c) {
            return new FileScannerService(
                $c->get(FilesystemService::class),
                $c->get(LoggerService::class)
            );
        });

        $container->singleton(UploadMonitorService::class, function(Container $c) {
            return new UploadMonitorService(
                $c->get(FileScannerService::class),
                $c->get(LoggerService::class)
            );
        });
    }

    public function boot(Container $container): void {
        // Hook into upload validation
        $monitor = $container->get(UploadMonitorService::class);

        // Using a closure to keep the method public API clean if desired, or direct callback
        add_action('added_post_meta', [$monitor, 'check_uploaded_file'], 10, 4);
    }
}
