<?php

namespace tests;

use dnj\Filesystem\Local;
use dnj\Log\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class LoggerTest extends TestCase
{
    public function test(): void
    {
        $file = new Local\File('/tmp/dnj-log-test-'.rand(1000, 2000).'.log');
        $logger = new Logger();
        $logger->setFile($file);
        $logger->setLevel(LogLevel::DEBUG);
        $logger->info('hello');
        $child = $logger->getInstance();
        $child->info("it's child");
    }
}
