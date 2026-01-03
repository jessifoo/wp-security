<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use OMS\Core\Container;
use Exception;

class ContainerTest extends TestCase {

    private Container $container;

    protected function setUp(): void {
        $this->container = new Container();
    }

    public function test_can_instantiate(): void {
        $this->assertInstanceOf(Container::class, $this->container);
    }

    public function test_bind_and_get(): void {
        $this->container->bind('test_service', function() {
            return new \stdClass();
        });

        $instance1 = $this->container->get('test_service');
        $instance2 = $this->container->get('test_service');

        $this->assertInstanceOf(\stdClass::class, $instance1);
        $this->assertNotSame($instance1, $instance2, 'Standard bind should return new instances');
    }

    public function test_singleton(): void {
        $this->container->singleton('test_singleton', function() {
            return new \stdClass();
        });

        $instance1 = $this->container->get('test_singleton');
        $instance2 = $this->container->get('test_singleton');

        $this->assertSame($instance1, $instance2, 'Singleton should return the same instance');
    }

    public function test_resolve_concrete_class(): void {
        $instance = $this->container->get(ConcreteTestClass::class);
        $this->assertInstanceOf(ConcreteTestClass::class, $instance);
    }

    public function test_autowire_dependencies(): void {
        $dependent = $this->container->get(DependentTestClass::class);
        $this->assertInstanceOf(DependentTestClass::class, $dependent);
        $this->assertInstanceOf(ConcreteTestClass::class, $dependent->dependency);
    }

    public function test_throws_exception_if_not_found(): void {
        $this->expectException(Exception::class);
        $this->container->get('non_existent_service');
    }
}

// Helpers for testing
class ConcreteTestClass {}
class DependentTestClass {
    public function __construct(public ConcreteTestClass $dependency) {}
}
