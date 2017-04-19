<?php
declare (strict_types=1);
namespace HaDb;

use Codeception\Test\Unit;
use \Mockery as m;

final class ServerPoolTest extends Unit
{
    private function getServerMock()
    {
        $server = m::mock(Server::class);
        $server->shouldReceive('getUid')->atLeast()->once()->andReturn('1');

        /** @var \HaDb\ServerDescription $server */
        return $server;
    }

    public function testInitNewPool()
    {
        $pool = new ServerPool();

        $this->assertInstanceOf(ServerPool::class, $pool);
    }

    public function testAddServer()
    {
        $pool = new ServerPool();
        $server = $this->getServerMock();
        $res = $pool->addServer($server);

        $this->assertTrue($res);
    }

    public function testServerAddAndGet()
    {
        $pool = new ServerPool();
        $server = $this->getServerMock();
        $pool->addServer($server);

        $server = $pool->getServer();

        $this->assertInstanceOf(Server::class, $server);
    }

    public function testGetServerWhenPoolEmpty()
    {
        $pool = new ServerPool();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(10);
        $this->expectExceptionMessage('You must set at least one server.');

        $pool->getServer();
    }

    public function testMarkServerAsDown()
    {
        $pool = new ServerPool();
        $server = $this->getServerMock();
        $server->shouldReceive('isAlive')->once()->andReturn(true);
        $server->shouldReceive('markServerDown')->once();

        $serverUid = $server->getUid();
        $pool->addServer($server);

        $res = $pool->markServerDown($serverUid);
        $this->assertTrue($res);
    }

    public function testMarkAsDownServerAlreadyDown()
    {
        $pool = new ServerPool();
        $server = $this->getServerMock();
        $server->shouldReceive('isAlive')->once()->andReturn(false);

        $serverUid = $server->getUid();
        $pool->addServer($server);

        $res = $pool->markServerDown($serverUid);
        $this->assertFalse($res);
    }

    public function testMarkNonExistingServerAsDown()
    {
        $pool = new ServerPool();
        $server = $this->getServerMock();
        $serverUid = $server->getUid();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(5);
        $this->expectExceptionMessage('The specified server does’nt belong to this pool.');
        $pool->markServerDown($serverUid);
    }

    public function testMarkServerAsUp()
    {
        $pool = new ServerPool();
        $server = $this->getServerMock();
        $server->shouldReceive('isAlive')->once()->andReturn(false);
        $server->shouldReceive('markServerUp')->once();

        $serverUid = $server->getUid();
        $pool->addServer($server);

        $res = $pool->markServerUp($serverUid);
        $this->assertTrue($res);
    }

    public function testMarkAsDownServerAlreadyUp()
    {
        $pool = new ServerPool();
        $server = $this->getServerMock();
        $server->shouldReceive('isAlive')->once()->andReturn(true);

        $serverUid = $server->getUid();
        $pool->addServer($server);

        $res = $pool->markServerUp($serverUid);
        $this->assertFalse($res);
    }

    public function testMarkNonExistingServerAsUp()
    {
        $pool = new ServerPool();
        $server = $this->getServerMock();
        $serverUid = $server->getUid();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(6);
        $this->expectExceptionMessage('The specified server does’nt belong to this pool.');
        $pool->markServerUp($serverUid);
    }
}
