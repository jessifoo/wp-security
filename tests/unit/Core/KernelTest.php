<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use OMS\Core\Kernel;
use OMS\Core\ServiceProvider;
use OMS\Core\Container;

class KernelTest extends TestCase {

	public function test_kernel_runs_providers_lifecycle(): void {
		// We track execution order in a static array just for this test
		// typically strict unit tests might mock this differently, but this is simple and effective.
		MockProvider::$called = array();

		$kernel = new Kernel( array( MockProvider::class ) );
		$kernel->run();

		$this->assertEquals(
			array(
				'register',
				'boot',
			),
			MockProvider::$called,
			'Kernel should call register then boot'
		);
	}

	public function test_kernel_exposes_container(): void {
		$kernel = new Kernel( array() );
		$this->assertInstanceOf( Container::class, $kernel->get_container() );
	}
}

class MockProvider implements ServiceProvider {
	public static array $called = array();

	public function register( Container $container ): void {
		self::$called[] = 'register';
	}

	public function boot( Container $container ): void {
		self::$called[] = 'boot';
	}
}
