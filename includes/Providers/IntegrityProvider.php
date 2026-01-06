<?php

declare(strict_types=1);

namespace OMS\Providers;

use OMS\Core\ServiceProvider;
use OMS\Core\Container;
use OMS\Services\IntegrityCheckerService;
use OMS\Services\LoggerService;

/**
 * Register Integrity Services.
 *
 * @package OMS\Providers
 */
class IntegrityProvider implements ServiceProvider
{

    /**
     * Register services.
     *
     * @param Container $container The DI Container.
     */
    public function register(Container $container): void
    {
        $container->singleton(IntegrityCheckerService::class, function (Container $c) {
            return new IntegrityCheckerService($c->get(LoggerService::class));
        });
    }

    /**
     * Boot services.
     *
     * @param Container $container The DI Container.
     */
    public function boot(Container $container): void
    {
        // No boot actions required for Integrity Checker (it's called on demand).
    }
}
