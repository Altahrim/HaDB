<?php
declare (strict_types=1);
namespace HaDb;

use Codeception\Test\Unit;

final class ServerTest extends Unit
{
    public function testInit()
    {
        $desc = \Mockery::mock(ServerDescription::class);
        $server = new Server($desc);

        $this->assertInstanceOf(Server::class, $server);
    }

    public function testUidGeneration()
    {
        $description = \Mockery::mock(ServerDescription::class);
        $server1 = new Server($description);
        $server2 = new Server($description);


        $uid1 = $server1->getUid();
        $uid2 = $server2->getUid();

        $this->assertNotEmpty($uid1);
        $this->assertNotEmpty($uid2);
        $this->assertNotEquals($uid1, $uid2, 'Different servers must have different UID.');
    }

    public function testIsAliveByDefault()
    {
        $desc = \Mockery::mock(ServerDescription::class);
        $server = new Server($desc);
        $isAlive = $server->isAlive();

        $this->assertTrue($isAlive);
    }

    public function testStatusChange()
    {
        $desc = \Mockery::mock(ServerDescription::class);
        $server = new Server($desc);

        $server->markServerUp();
        $isAlive = $server->isAlive();
        $server->markServerDown();
        $isDead = $server->isAlive();

        $this->assertTrue($isAlive);
        $this->assertFalse($isDead);
    }

    public function testGetNewConnection()
    {

    }
}
