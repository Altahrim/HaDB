<?php
declare(strict_types=1);
namespace HaDb;

/**
 * Database servers pool
 */
class ServerPool
{
    /**
     * List of servers.
     * @var \HaDb\Server[];
     */
    private $servers = [];

    /**
     * Server selection mode.
     *
     * @var int
     */
    private $selection;

    /**
     * Server selection: none.
     * The first server of the pool is returned until dead.
     */
    const OPT_SERVERS_FALLBACK = 0;

    /**
     * Server selection: round robin.
     * Each time you need a server, the next server of the pool is returned.
     */
    const OPT_SERVERS_RR = 1;

    /**
     * Server selection: random.
     * When you ask for a server, you got a randomly selected server from the pool.
     */
    const OPT_SERVERS_RANDOM = 2;

    /**
     * Constructor.
     *
     * @param int $serverSelection
     */
    public function __construct(int $serverSelection = self::OPT_SERVERS_RR)
    {
        $this->selection = $serverSelection;
    }

    /**
     * Add a new server to pool.
     * @param \HaDb\Server $server
     * @return bool
     */
    public function addServer(Server $server): bool
    {
        $serverUid = $server->getUid();
        if (isset($this->servers[$serverUid])) {
            throw new \RuntimeException('This server is already in the current pool.');
        }

        $this->servers[$serverUid] = $server;

        return true;
    }

    /**
     * Returns the next server.
     * @return \HaDb\Server
     * @see self::$selection for allowed modes
     */
    public function getServer()
    {
        if (empty($this->servers)) {
            throw new \RuntimeException('You must set at least one server.', 10);
        }

        switch ($this->selection) {
            case self::OPT_SERVERS_RANDOM:
                return $this->getServerRandom();
            case self::OPT_SERVERS_RR:
                return $this->getServerRoundRobin();
            case self::OPT_SERVERS_FALLBACK:
                return $this->getServerFallback();

            default:
        }

        throw new \LogicException('Invalid server selection mode.');
    }

    /**
     * Picks a random servers among alive servers
     * @return \HaDb\Server
     */
    private function getServerRandom()
    {
        $aliveServers = array_filter($this->servers, function ($server) {
            return $server->isAlive();
        });

        if (empty($aliveServers)) {
            throw new \RuntimeException('All servers are down..', 11);
        }

        $randomKey = array_rand($aliveServers);
        return $aliveServers[$randomKey];
    }

    /**
     * Picks a servers among alive servers according to round-robin method.
     * @return \HaDb\Server
     * @todo Handle alive status
     */
    private function getServerRoundRobin()
    {
        $server = current($this->servers);
        if (!next($this->servers)) {
            reset($this->servers);
        }

        return $server;
    }

    /**
     * Picks the first alive server..
     * @return \HaDb\Server
     */
    private function getServerFallback()
    {
        foreach ($this->servers as $server) {
            if ($server->isAlive()) {
                return $server;
            }
        }

        throw new \RuntimeException('All servers are down..', 11);
    }

    /**
     * Mark a server as down.
     * @param int $serverUid
     * @return bool
     */
    public function markServerDown(int $serverUid): bool
    {
        if (!isset($this->servers[$serverUid])) {
            throw new \RuntimeException('The specified server does’nt belong to this pool.', 5);
        }

        $server = $this->servers[$serverUid];

        // Mark server down
        if ($server->isAlive()) {
            $server->markServerDown();
            return true;
        }

        return false;
    }

    /**
     * Mark a server as up and running.
     * @param int $serverUid
     * @return bool
     */
    public function markServerUp(int $serverUid): bool
    {
        if (!isset($this->servers[$serverUid])) {
            throw new \RuntimeException('The specified server does’nt belong to this pool.', 6);
        }

        $server = $this->servers[$serverUid];

        // Mark server up
        if (!$server->isAlive()) {
            $server->markServerUp();
            return true;
        }

        return false;
    }

    /**
     * Shuffle the pool.
     *
     * Useful when using Round-Robin selection, so the first server of the pool is not always used first.
     */
    public function shufflePool()
    {
        shuffle($this->servers);
    }
}
