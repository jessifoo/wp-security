<?php

use PHPUnit\Framework\TestCase;

/**
 * TestHelperTrait provides common setup and teardown functionality for OMS tests.
 *
 * This trait encapsulates shared test infrastructure including:
 * - Temporary directory management
 * - Mock creation for common dependencies
 * - File operation helpers
 * - WordPress environment simulation
 */
trait TestHelperTrait
{
    /** @var string Temporary directory path for test files */
    protected $tempDir;

    /** @var array List of created test files for cleanup */
    protected $testFiles = [];

    /**
     * Creates a temporary directory for test files
     *
     * @throws RuntimeException If directory creation fails
     */
    protected function createTemporaryDirectory(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/oms_test_' . uniqid();

        if (!mkdir($this->tempDir) && !is_dir($this->tempDir)) {
            throw new RuntimeException(
                sprintf('Failed to create temporary directory: %s', $this->tempDir)
            );
        }

        if (!is_writable($this->tempDir)) {
            throw new RuntimeException(
                sprintf('Temporary directory is not writable: %s', $this->tempDir)
            );
        }
    }

    /**
     * Creates a test file with specified content and permissions
     *
     * @param string $filename Relative path to the file
     * @param string $content File content
     * @param int $permissions File permissions (octal)
     * @return string Absolute path to the created file
     * @throws InvalidArgumentException If filename is empty
     * @throws RuntimeException If file creation fails
     */
    protected function createTestFile(string $filename, string $content, int $permissions = 0644): string
    {
        if (empty($filename)) {
            throw new InvalidArgumentException('Filename cannot be empty');
        }

        $filepath = $this->tempDir . '/' . ltrim($filename, '/');
        $directory = dirname($filepath);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException(
                    sprintf('Failed to create directory: %s', $directory)
                );
            }
        }

        if (file_put_contents($filepath, $content) === false) {
            throw new RuntimeException(
                sprintf('Failed to write content to file: %s', $filepath)
            );
        }

        if (!chmod($filepath, $permissions)) {
            throw new RuntimeException(
                sprintf('Failed to set permissions on file: %s', $filepath)
            );
        }

        $this->testFiles[] = $filepath;
        return $filepath;
    }

    /**
     * Creates a symbolic link
     *
     * @param string $targetName Name of the target file (relative to temp dir)
     * @param string $linkName Name of the symlink (relative to temp dir)
     * @param bool $broken Whether to create a broken symlink
     * @return string Absolute path to the created symlink
     * @throws RuntimeException If symlink creation fails
     */
    protected function createTestSymlink(string $targetName, string $linkName, bool $broken = false): string
    {
        $targetPath = $this->tempDir . '/' . ltrim($targetName, '/');
        $linkPath = $this->tempDir . '/' . ltrim($linkName, '/');

        if ($broken) {
            $targetPath = $this->tempDir . '/nonexistent_' . uniqid();
        }

        if (file_exists($linkPath)) {
            unlink($linkPath);
        }

        if (!symlink($targetPath, $linkPath)) {
            throw new RuntimeException(
                sprintf('Failed to create symlink from %s to %s', $linkPath, $targetPath)
            );
        }

        $this->testFiles[] = $linkPath;
        return $linkPath;
    }

    /**
     * Creates a mock logger with specified expectations
     *
     * @param array $expectations Array of method names and their expected call counts
     * @return OMS_Logger Mock logger instance
     */
    protected function createMockLogger(array $expectations = []): OMS_Logger
    {
        $logger = $this->getMockBuilder(OMS_Logger::class)
            ->onlyMethods(['debug', 'info', 'warning', 'error'])
            ->getMock();

        foreach ($expectations as $method => $expectation) {
            $logger->expects($expectation)
                ->method($method)
                ->willReturn(null);
        }

        return $logger;
    }

    /**
     * Creates a mock security policy
     *
     * @param bool $isValid Whether the policy should validate files as valid
     * @param string $reason Reason for the validation result
     * @return OMS_File_Security_Policy Mock policy instance
     */
    protected function createMockSecurityPolicy(bool $isValid, string $reason): OMS_File_Security_Policy
    {
        $policy = $this->getMockBuilder(OMS_File_Security_Policy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['validate_file'])
            ->getMock();

        $policy->method('validate_file')
            ->willReturn([
                'valid' => $isValid,
                'reason' => $reason
            ]);

        return $policy;
    }

    /**
     * Sets up WordPress environment mocks
     */
    protected function mockWordPressEnvironment(): void
    {
        $this->setup_wordpress_mocks();
        $this->mockWPUploadDir($this->tempDir);
    }

    /**
     * Removes a directory and its contents recursively
     *
     * @param string $dir Directory path
     * @throws RuntimeException If directory removal fails
     */
    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = "$dir/$file";

            try {
                if (is_link($path)) {
                    unlink($path);
                } elseif (is_dir($path)) {
                    $this->removeDirectory($path);
                } else {
                    // Try to make the file writable before deletion
                    if (!is_writable($path)) {
                        chmod($path, 0666);
                    }
                    if (!unlink($path)) {
                        throw new RuntimeException(
                            sprintf('Failed to delete file: %s', $path)
                        );
                    }
                }
            } catch (\Exception $e) {
                error_log(sprintf(
                    'Error while removing %s: %s',
                    $path,
                    $e->getMessage()
                ));
            }
        }

        if (!rmdir($dir)) {
            throw new RuntimeException(
                sprintf('Failed to remove directory: %s', $dir)
            );
        }
    }

    /**
     * Cleans up the test environment
     */
    protected function cleanupTestEnvironment(): void
    {
        if (!empty($this->tempDir) && is_dir($this->tempDir)) {
            try {
                $this->removeDirectory($this->tempDir);
            } catch (\Exception $e) {
                error_log(sprintf(
                    'Failed to clean up test environment: %s',
                    $e->getMessage()
                ));
            }
        }

        $this->testFiles = [];
        $this->tempDir = null;
    }

    /**
     * Invokes a private or protected method on an object.
     *
     * @param object $object Object instance
     * @param string $methodName Method to call
     * @param array $parameters Parameters to pass
     * @return mixed Method result
     */
    protected function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}
