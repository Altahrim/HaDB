<?php
declare (strict_types=1);
namespace HaDb;

use Codeception\Test\Unit;

final class ConnectionTest extends Unit
{
    public function testInit()
    {
        $connection = new \HaDb\Connection();

        $this->assertInstanceOf(\HaDb\Connection::class, $connection);
    }
}