<?php
declare(strict_types=1);
namespace HaDb;

/**
 * Connection to a MySQL server.
 */
class Connection
{
    /**
     * MySQLi connection
     * @var \mysqli
     */
    private $mysqli;

    /**
     * Informations about MySQL targeted server.
     * @var \HaDb\ServerDescription
     */
    private $info;

    public function __construct(ServerDescription $serverDescription)
    {
        $this->info = $serverDescription;
        $this->connect();
    }

    /**
     * Establish connection.
     * @throws \mysqli_sql_exception
     */
    private function connect()
    {
        $this->mysqli = mysqli_init();
        $initCmd = 'SET AUTOCOMMIT = ' . ($this->autocommit ? '1' : '0');
        $options = $this->info->getOptions();
        if (!empty($options[\MYSQLI_INIT_COMMAND])) {
            $options[\MYSQLI_INIT_COMMAND] .= ',' . $initCmd;
        } else {
            $options[\MYSQLI_INIT_COMMAND] = $initCmd;
        }
        foreach ($options as $key => $value) {
            if (true !== $this->mysqli->options($key, $value)) {
                throw new \RuntimeException('Fail to set connection option');
            }
        }

        $this->mysqli->real_connect(
            $this->info->getHostname(),
            $this->info->getUsername(),
            $this->info->getPassword(),
            $this->info->getDatabase(),
            $this->info->getPort(),
            $this->info->getSocket()
        );

        if (0 !== $this->mysqli->connect_errno) {
            throw new \mysqli_sql_exception($this->mysqli->connect_error, $this->mysqli->connect_errno);
        }
    }

    /**
     * Returns MySQL thread ID
     * @return int
     */
    public function getUid(): int
    {
        return $this->mysqli->thread_id;
    }
}