<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use OMS\Services\IntegrityCheckerService;
use OMS\Services\LoggerService;
use WordPressMocksTrait;

require_once dirname(__DIR__, 2) . '/WordPressMocksTrait.php';

class IntegrityCheckerServiceTest extends TestCase
{
    use \WordPressMocksTrait;

    private $logger;
    private $service;
    private $test_dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setup_wordpress_mocks();

        $this->logger = $this->createMock(LoggerService::class);
        $this->service = new IntegrityCheckerService($this->logger);

        // Ensure test directory exists
        $this->test_dir = sys_get_temp_dir() . '/wordpress';
        if (! file_exists($this->test_dir)) {
            mkdir($this->test_dir, 0777, true);
        }

        // Mock ABSPATH
        if (! defined('ABSPATH')) {
            define('ABSPATH', $this->test_dir . '/');
        }
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->test_dir);
        $this->teardown_wordpress_mocks();
        parent::tearDown();
    }

    private function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ('.' !== $object && '..' !== $object) {
                    if (is_dir($dir . '/' . $object)) {
                        $this->rrmdir($dir . '/' . $object);
                    } else {
                        unlink($dir . '/' . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    public function test_verify_core_files_success()
    {
        // Create dummy files
        $sample_content = 'sample content';
        $index_content  = 'index content';

        file_put_contents($this->test_dir . '/wp-config-sample.php', $sample_content);
        file_put_contents($this->test_dir . '/index.php', $index_content);

        $checksums = [
            'checksums' => [
                'wp-config-sample.php' => md5($sample_content),
                'index.php'            => md5($index_content),
            ],
        ];

        // Mock wp_remote_get via global available in mock file or just assume helper does it.
        // Since we use WordPressMocksTrait, we need to ensure it supports response mocking.
        // We'll rely on the global mock variable mechanism used in existing test.
        global $wp_remote_get_mock;
        $wp_remote_get_mock = [
            'response' => ['code' => 200],
            'body'     => json_encode($checksums),
        ];

        $results = $this->service->verify_core_files();

        $this->assertIsArray($results);
        $this->assertCount(2, $results['safe']);
        $this->assertEmpty($results['modified']);
    }

    public function test_verify_core_files_mismatch()
    {
        $sample_content = 'modified content';
        file_put_contents($this->test_dir . '/wp-config-sample.php', $sample_content);

        $checksums = [
            'checksums' => [
                'wp-config-sample.php' => md5('original'),
            ],
        ];

        global $wp_remote_get_mock;
        $wp_remote_get_mock = [
            'response' => ['code' => 200],
            'body'     => json_encode($checksums),
        ];

        $results = $this->service->verify_core_files();

        $this->assertContains('wp-config-sample.php', $results['modified']);
    }
}
