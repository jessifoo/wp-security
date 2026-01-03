<?php
declare(strict_types=1);

namespace Tests\Unit\Providers;

use PHPUnit\Framework\TestCase;
use OMS\Core\Container;
use OMS\Providers\CoreProvider;
use OMS\Services\LoggerService;
use OMS\Services\CacheService;

class CoreProviderTest extends TestCase {

    public function test_registers_core_services(): void {
        $container = new Container();
        $provider = new CoreProvider();

        $provider->register($container);

        $this->assertInstanceOf(LoggerService::class, $container->get(LoggerService::class));
        $this->assertInstanceOf(CacheService::class, $container->get(CacheService::class));
    }
}
