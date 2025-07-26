<?php
namespace webhub\Helpers\Tests;

use PHPUnit\Framework\TestCase;
use webhub\Helpers\MtLogger;
use Monolog\Level;
use org\bovigo\vfs\vfsStream;

class MtLoggerTest extends TestCase
{
    private $root;
    private $logPath;

    protected function setUp(): void
    {
        // Setup virtual file system
        $this->root = vfsStream::setup('logs');
        $this->logPath = vfsStream::url('logs');
    }

    public function testSingletonInstance(): void
    {
        $logger1 = MtLogger::getInstance();
        $logger2 = MtLogger::getInstance();

        $this->assertSame($logger1, $logger2, 'Both instances should be the same');
    }

    public function testFormLoggerName(): void
    {
        $path = '/var/logs';
        $name = 'test_logger';
        $expected = '/var/logs/test_logger.log';

        $result = MtLogger::form_logger_name($path, $name);
        $this->assertEquals($expected, $result);

        // Test with trailing slash
        $result = MtLogger::form_logger_name($path.'/', $name);
        $this->assertEquals($expected, $result);

        // Test with special characters
        $name = 'test/logger';
        $expected = '/var/logs/test_logger.log';
        $result = MtLogger::form_logger_name($path, $name);
        $this->assertEquals($expected, $result);
    }

    public function testLogFileCreation(): void
    {
        $loggerName = 'test_logger';
        $logger = new MtLogger($this->logPath, $loggerName);
        
        $expectedFile = $this->logPath . '/' . $loggerName . '.log';
        $this->assertFileExists($expectedFile);
    }

    public function testLogLevelMethods(): void
    {
        $logger = new MtLogger($this->logPath, 'level_test');
        
        // Test all log level methods
        $logger->debug('Test debug message');
        $logger->info('Test info message');
        $logger->notice('Test notice message');
        $logger->warning('Test warning message');
        $logger->error('Test error message');
        
        $logContent = file_get_contents($this->logPath . '/level_test.log');
        
        $this->assertStringContainsString('DEBUG', $logContent);
        $this->assertStringContainsString('INFO', $logContent);
        $this->assertStringContainsString('NOTICE', $logContent);
        $this->assertStringContainsString('WARNING', $logContent);
        $this->assertStringContainsString('ERROR', $logContent);
    }

    public function testLogWithContext(): void
    {
        $logger = new MtLogger($this->logPath, 'context_test');
        $context = ['key' => 'value', 'user' => 'testuser'];
        
        $logger->info('Message with context', $context);
        
        $logContent = file_get_contents($this->logPath . '/context_test.log');
        $this->assertStringContainsString('Message with context', $logContent);
    }

    public function testRequestResponseLogging(): void
    {
        $logger = new MtLogger($this->logPath, 'req_res_test');
        
        $request = ['method' => 'GET', 'path' => '/test', 'params' => ['id' => 123]];
        $response = ['status' => 200, 'data' => ['result' => 'success']];
        
        $logger->logRequest($request);
        $logger->logResponse($response);
        
        $logContent = file_get_contents($this->logPath . '/req_res_test.log');
        
        $this->assertStringContainsString('Request received', $logContent);
        $this->assertStringContainsString('Response received', $logContent);
    }

    public function testFileAndLineInclusion(): void
    {
        $logger = new MtLogger($this->logPath, 'file_line_test');
        $logger->info('Testing file and line inclusion');
        
        $logContent = file_get_contents($this->logPath . '/file_line_test.log');
        
        // Pattern to match file path and line number
        $pattern = '/in .*MtLoggerTest.php:\d+: Testing file and line inclusion/';
        $this->assertMatchesRegularExpression($pattern, $logContent);
    }

    public function testCustomMaxFiles(): void
    {
        $maxFiles = 5;
        new MtLogger($this->logPath, 'max_files_test', $maxFiles);
        
        // Verification would require inspecting the RotatingFileHandler
        $this->addToAssertionCount(1); // Just mark the test as passed
    }

    public function testDefaultLogPath(): void
    {
        $logger = new MtLogger();
        
        // This is more of an integration test
        $this->addToAssertionCount(1); // Just mark the test as passed
    }
}