<?php
declare (strict_types=1);
namespace HaDb;

use Codeception\Test\Unit;

final class LoggerTest extends Unit
{
    public function testInit()
    {
        $logger = new Logger();

        $this->assertInstanceOf(Logger::class, $logger);
    }
}

/**
 * Stubs
 * @todo There is probably a better way to mock native functions
 */
function openlog() {
    return true;
}

function closelog() {
    return true;
}

function syslog()
{
    return true;
}