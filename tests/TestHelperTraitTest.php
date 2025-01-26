<?php

use PHPUnit\Framework\TestCase;

class TestHelperTraitTest extends TestCase
{
    use TestHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestEnvironment();
        parent::tearDown();
    }

    public function testCreateTestFile()
    {
        $filePath = $this->createTestFile('example.txt', 'test content');
        $this->assertFileExists($filePath);
        $this->assertEquals('test content', file_get_contents($filePath));
    }

    public function testCreateTestFileWithPermissions()
    {
        $filePath = $this->createTestFile('readonly.txt', 'content', 0444);
        $this->assertFileExists($filePath);
        $this->assertEquals(0444, fileperms($filePath) & 0777);
    }

    public function testCreateTestSymlink()
    {
        $targetPath = $this->createTestFile('target.txt', 'target content');
        $symlinkPath = $this->createTestSymlink('target.txt', 'link.txt');
        
        $this->assertTrue(is_link($symlinkPath));
        $this->assertEquals('target content', file_get_contents($symlinkPath));
    }

    public function testCreateBrokenSymlink()
    {
        $symlinkPath = $this->createTestSymlink('nonexistent.txt', 'broken.txt', true);
        $this->assertTrue(is_link($symlinkPath));
        $this->assertFalse(file_exists($symlinkPath));
    }

    public function testCreateMockLogger()
    {
        $logger = $this->createMockLogger(['warning' => $this->once()]);
        $this->assertInstanceOf(OMS_Logger::class, $logger);
        
        // Verify logger expectations
        $logger->warning('Test warning');
    }

    public function testCreateMockSecurityPolicy()
    {
        $policy = $this->createMockSecurityPolicy(true, 'Test reason');
        $this->assertInstanceOf(OMS_File_Security_Policy::class, $policy);
        
        $result = $policy->validate_file('test.txt');
        $this->assertTrue($result['valid']);
        $this->assertEquals('Test reason', $result['reason']);
    }

    public function testCleanupTestEnvironment()
    {
        // Create some test files and directories
        $testDir = $this->tempDir . '/nested/dir';
        mkdir($testDir, 0777, true);
        $this->createTestFile('nested/dir/test.txt', 'content');
        
        $this->cleanupTestEnvironment();
        
        $this->assertDirectoryDoesNotExist($this->tempDir);
    }

    public function testRemoveDirectoryWithReadOnlyFiles()
    {
        $testDir = $this->tempDir . '/readonly';
        mkdir($testDir);
        $filePath = $testDir . '/readonly.txt';
        file_put_contents($filePath, 'content');
        chmod($filePath, 0444);
        
        $this->removeDirectory($testDir);
        $this->assertDirectoryDoesNotExist($testDir);
    }
}
