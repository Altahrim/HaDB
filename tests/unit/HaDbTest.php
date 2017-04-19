<?php
declare (strict_types=1);

use Codeception\Test\Unit;
use \Mockery as m;

final class HaDbTest extends Unit
{
    /**
     * @var \HaDb\Server[]|\Mockery\MockInterface[]
     */
    private $servers;

    /**
     * @var \HaDb\ServerPool|\Mockery\MockInterface
     */
    private $pool;

    public function setUp()
    {
        $this->servers = [
            'Up1' => m::mock(\HaDb\Server::class),
            'Down1' => m::mock(\HaDb\Server::class),
            'Up2' => m::mock(\HaDb\Server::class),
        ];

        $this->pool = m::mock(\HaDb\ServerPool::class);
    }

    public function testInit()
    {
        $pool = m::mock(\HaDb\ServerPool::class);

        $db = new \HaDb($pool);

        $this->assertInstanceOf(\HaDb::class, $db);
    }

    public function testExecAsyncQuery()
    {
        $server = $this->servers['Up1'];
        $this->pool->shouldReceive('getServer')->andReturn($server);
        $this->pool->shouldReceive('getServer')->andReturn($server);
        $connection = m::mock(\HaDb\Connection::class);
        $connection->shouldReceive('getUid')->andReturn(1);
        $connection->shouldReceive('query')->once()->andReturn(true);
        $server->shouldReceive('getNewConnection')->andReturn($connection);

        $db = new \HaDb($this->pool);
        $db->asyncQuery('Good SQL Query');
    }
}
