<?php
namespace Webhubx\Helpers;

use Monolog\Logger;
use Monolog\Level;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;  

/**
 * MtLogger is a singleton logger class that uses Monolog to log messages to a rotating file.
 * It provides methods for logging at different levels and includes file and line information in the logs.
 * 
 *   Author: WebHub
 *   Date: 2018-07-20
 * 
 */

class MtLogger
{
    private static $instance = null;
    private $logger;

    //create the logger instance with default parameters
    private function __construct($log_path, $logger_name, $max_files)
    {
        $this->logger = new Logger($logger_name);

        $loggerFile = self::form_logger_name($log_path, $logger_name);

        $rotating_handler = new RotatingFileHandler($loggerFile, $max_files, Level::Debug, true);
        $rotating_handler->pushProcessor(function ($record) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
            foreach ($backtrace as $trace) {
                if (isset($trace['file']) && strpos($trace['file'], 'Monolog') === false) {
                    $record['extra']['file'] = $trace['file'];
                    $record['extra']['line'] = $trace['line'] ?? '?';
                    break;
                }
            }
            return $record;
        });

        $format = "[%datetime%] %level_name% in %extra.file%:%extra.line%: %message%\n";
        $formatter = new LineFormatter($format, null, true, true);
        $rotating_handler->setFormatter($formatter);

        $this->logger->pushHandler($rotating_handler);
    }

    public static function form_logger_name($path, $name) {
        $path = rtrim($path, '/') . '/';
        $name = str_replace(['/', '\\'], '_', $name);
    
        return $path . $name . '.log';
    }

    public static function getInstance($log_path='/tmp', $logger_name='mt_logger', $max_files = 10)
    {
        if (self::$instance === null) {
            self::$instance = new self($log_path, $logger_name, $max_files);
        }
        return self::$instance;
    }

    public function log($level, $message, array $context = [])
    {
        $this->logger->log($level, $message, $context);
    }

    public function debug($message, array $context = [])
    {
        $this->log(Level::Debug, $message, $context);
    }

    public function info($message, array $context = [])
    {
        $this->log(Level::Info, $message, $context);
    }

    public function notice($message, array $context = [])
    {
        $this->log(Level::Notice, $message, $context);
    }

    public function warning($message, array $context = [])
    {
        $this->log(Level::Warning, $message, $context);
    }

    public function error($message, array $context = [])
    {
        $this->log(Level::Error, $message, $context);
    }

    public function logRequest($req) {
        $this->info('Request received', $req);
    }

    public function logResponse($res){
        $this->info('Response received', $res);
    }
}


