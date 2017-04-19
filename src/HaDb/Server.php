<?php
declare(strict_types=1);
namespace HaDb;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * MySQL Server.
 */
class Server
{
    /**
     * Server description.
     *
     * @var \HaDb\ServerDescription
     */
    private $data;

    /**
     * Server status.
     *
     * @var bool
     */
    private $isAlive;

    /**
     * Server unique ID
     * @var int
     */
    private $uid;

    /**
     * Logger
     * @var \Psr\Log\LoggerInterface
     */
    private $log;

    /**
     * Number of declared servers.
     * Used for generating UIDs.
     * @var int
     */
    private static $count = 0;

    /**
     * Constructor.
     *
     * @param \HaDb\ServerDescription $description
     * @param \Psr\Log\LoggerInterface|null $logger
     */
    public function __construct(ServerDescription $description, ?LoggerInterface $logger = null)
    {
        $this->uid = ++self::$count;
        $this->data = $description;
        $this->log = null === $logger
            ? new NullLogger()
            : $logger;
        $this->isAlive = true;
    }

    /**
     * Returns server unique ID
     * @return int
     */
    public function getUid(): int
    {
        return $this->uid;
    }

    /**
     * Returns true if the server is alive.
     * This function does’nt do any check by itself.
     *
     * @return bool
     */
    public function isAlive(): bool
    {
        return $this->isAlive;
    }

    /**
     * Mark the server as up.
     * @return bool
     */
    public function markServerUp()
    {
        return $this->isAlive = true;
    }

    /**
     * Mark the server as down.
     * It will not be used for new connections.
     *
     * @return bool
     */
    public function markServerDown()
    {
        return $this->isAlive = false;
    }

    /**
     * Establish a new MySQL connection on server.
     *
     * @return \HaDb\Connection
     * @throws \mysqli_sql_exception
     */
    public function getNewConnection(): Connection
    {
        $hostname = $this->data->getHostname();

        $this->log->info('Establishing a new connection to server {server}.', ['server' => $this->data]);
        $conn = new Connection($this->data);
        var_dump($conn); exit;
        $conn = mysqli_init();
        $initCmd = 'SET AUTOCOMMIT = ' . ($this->autocommit ? '1' : '0');
        $options = $this->data->getOptions();
        if (!empty($options[\MYSQLI_INIT_COMMAND])) {
            $options[\MYSQLI_INIT_COMMAND] .= ',' . $initCmd;
        } else {
            $options[\MYSQLI_INIT_COMMAND] = $initCmd;
        }
        foreach ($options as $key => $value) {
            if (true !== $conn->options($key, $value)) {
                throw new \RuntimeException('Fail to set connection option');
            }
        }

        $conn->real_connect(
            $hostname,
            $this->data->getUsername(),
            $this->data->getPassword(),
            $this->data->getDatabase(),
            $this->data->getPort(),
            $this->data->getSocket()
        );

        if (0 !== $conn->connect_errno) {
            throw new \mysqli_sql_exception($conn->connect_error, $conn->connect_errno);
        }


        return $conn;
    }
}
