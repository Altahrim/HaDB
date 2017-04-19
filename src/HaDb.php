<?php
declare(strict_types=1);

/**
 * Class HaDb
 */
class HaDb
{
    /**
     * Max allowed connections in pool
     * @var int
     */
    private $maxConn;

    /**
     * Current number of connections in pool
     * @var int
     */
    private $nbConn;

    /**
     * Autocommit state
     * @var bool
     */
    private $autocommit = true;

    /**
     * Server pool
     * @var \HaDb\ServerPool
     */
    private $serverPool;

    /**
     * Idle connections
     * @var \mysqli[]
     */
    private $idleConnections = [];

    /**
     * Active connections
     * @var \mysqli[]
     */
    private $activeConnections = [];

    private $connectionsLastQuery = [];

    /**
     * Queued queries.
     *
     * Queries waiting for an available connection.
     *
     * @var string[]
     */
    private $queuedQueries = [];

    /**
     * Query results
     * @var array
     */
    private $readyResults = [];

    /**
     * Connection used for last synced query
     * @var \mysqli
     */
    private $lastUsedConnection = null;

    /**
     * Connection used for transaction
     * @var \mysqli|null
     */
    private $transactionConnection = null;

    private $currentQueryId = 0;

    /**
     * PSR-3 logger
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Maximum number of connections established by this object
     */
    const NB_DEFAULT_MAX_CONN = 8;

    /**
     * Constructor.
     * @param \HaDb\ServerPool $serverPool
     * @param null|\Psr\Log\LoggerInterface $logger
     */
    public function __construct(\HaDb\ServerPool $serverPool, ?\Psr\Log\LoggerInterface $logger = null)
    {
        $this->serverPool = $serverPool;
        $this->logger = null === $logger ? new \Psr\Log\NullLogger() : $logger;
        $this->nbConn = 0;
        $this->maxConn = self::NB_DEFAULT_MAX_CONN;
    }

    /**
     * Shortcut to access mysqli properties on last request
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        switch ($name) {
            case 'affected_rows':
            case 'connect_errno':
            case 'connect_error':
            case 'errno':
            case 'error_list':
            case 'error':
            case 'field_count':
            case 'info':
            case 'insert_id':
            case 'sqlstate':
            case 'warning_count':
                if ($this->lastUsedConnection instanceof \mysqli) {
                    return $this->lastUsedConnection->$name;
                }
                return null;
        }

        throw new RuntimeException('Undefined property ', $name);
    }

    /**
     * Returns the current number of established connections
     * @return int
     */
    public function getNbConn(): int
    {
        return $this->nbConn;
    }

    /**
     * Returns the maximum number of established connections
     * @return int
     */
    public function getMaxConn(): int
    {
        return $this->maxConn;
    }

    /**
     * Adjusts the maximum number of established connections
     * @param int $maxConn
     */
    public function setMaxConn(int $maxConn)
    {
        if ($maxConn < 1) {
            throw new \BadMethodCallException('$maxConn can’t be smaller than 1.');
        }
        $this->maxConn = $maxConn;
    }

    /**
     * Performs a query
     * @param string $query
     * @param bool $buffered
     * @param float $maxWait Maximum time to wait if there is no connection available. 0 waits forever.
     * @return bool|\mysqli_result as in mysqli::query()
     * @throws RuntimeException if there is no connection available and $maxWait is reached
     */
    public function query(string $query, bool $buffered = true, float $maxWait = 0.0)
    {
        ++$this->currentQueryId;
        if ($this->lastUsedConnection instanceof \mysqli) {
            $conn = $this->lastUsedConnection;
        } else {
            $conn = $this->getAvailableConnection();
            if (false === $conn) {
                $res = $this->poll($maxWait, false);
                if ($res > 1) {
                    return $this->query($query, $buffered);
                }

                throw new RuntimeException('No database connection available');
            }
        }

        $result = $this->execute($conn, $query, $buffered);
        $this->idleConnections[$conn->getUid()] = $conn;

        return $result;
    }

    /**
     * Performs an asynchronous query
     * If there is no connection available at this moment, query will be queued.
     * If a transaction is running, your query will NOT be included in it.
     * @param string $query
     * @param int $qid
     * @return bool
     */
    public function asyncQuery(string $query, int &$qid = null): bool
    {
        // TODO Check if it is possible to do async unbuffered query
        $qid = $this->getNextQueryId();
        $conn = $this->getAvailableConnection();
        if ($conn instanceof \mysqli) {
            $result = $this->execute($conn, $query, false, true);
        } else {
            $this->queuedQueries[$qid] = $query;
            $result = true;
        }

        return $result;
    }

    /**
     * Returns next Query ID.
     *
     * If ID > PHP_INT_MAX, returns to 1.
     *
     * @return int
     */
    private function getNextQueryId(): int
    {
        if ($this->currentQueryId === PHP_INT_MAX) {
            return $this->currentQueryId = 1;
        }
        return ++$this->currentQueryId;
    }

    /**
     * Executes a query.
     *
     * @param \HaDb\Connection $connection
     * @param string $query
     * @param bool $buffered
     * @param bool $async
     * @return bool|\mysqli_result
     */
    private function execute(\HaDb\Connection $connection, string $query, $buffered = true, $async = false)
    {
        $mode = $buffered ? \MYSQLI_STORE_RESULT : \MYSQLI_USE_RESULT;
        if ($async) {
            $mode = $mode | \MYSQLI_ASYNC;
        } else {
            $this->lastUsedConnection = $connection;
        }

        $result = $connection->query($query, $mode);
        $this->connectionsLastQuery[$connection->getUid()] = [
            'qid' => $this->currentQueryId,
            'connUid' => $connection->getUid(),
            'result' => null
        ];

        echo 'Execute request on thread ', $connection->getUid(), '. Mode ', $mode, PHP_EOL;

        return $result;
    }

    /**
     * Start queued queries
     *
     * @return int Number of started queries
     */
    private function launchQueuedQueries()
    {
        $this->logger->debug('Check for queued queries…');
        $i = 0;

        foreach ($this->queuedQueries as $qid => $query) {
            $conn = $this->getAvailableConnection();
            if ($conn instanceof \mysqli) {
                $this->logger->debug('Launch queued query number {qid}…', ['qid' => $qid]);
                $this->execute($conn, $query, false, true);
                unset($this->queuedQueries[$qid]);
                ++$i;
            } else {
                break;
            }
        }

        return $i;
    }

    /**
     * Checks if any connection is available.
     *
     * @return bool
     */
    public function hasAvailableConnections(): bool
    {
        if (!empty($this->idleConnections)) {
            return true;
        }

        if ($this->allowNewConnection()) {
            return true;
        }

        $this->logger->debug('Reached max connection number.');
        return false;
    }

    /**
     * Returns an available connection
     *
     * @param bool $markAsActive
     * @return \HaDb\Connection
     */
    private function getAvailableConnection(bool $markAsActive = true)
    {
        if (empty($this->idleConnections)) {
            if ($this->allowNewConnection()) {
                $this->getNewConnection();
            } else {
                throw new \RuntimeException('Impossible to establish more connections.');
            }
        }

        if ($markAsActive) {
            $conn = array_shift($this->idleConnections);
            $this->activeConnections[$conn->getUid()] = $conn;
        } else {
            $conn = reset($this->idleConnections);
        }

        return $conn;
    }

    /**
     * Check if more connections can be established
     * @return bool
     */
    private function allowNewConnection(): bool
    {
        return count($this->activeConnections) + count($this->idleConnections) < $this->maxConn;
    }
    /**
     * Establish a new connection to next database server
     *
     * return \mysqli
     */
    private function getNewConnection(): \mysqli
    {
        do {
            $server = $this->serverPool->getServer();
            // TODO Handle exceptions
            $connection = $server->getNewConnection();
        } while (!$connection instanceof \HaDb\Connection);

        return $this->idleConnections[$connection->getUid()] = $connection;
    }

    /**
     * Waits for results from an async request.
     *
     * @param float $maxWait Number of seconds to wait, must be positive. Use zero if you want to wait indefinitely
     * @param bool $launchQueued
     * @return bool|int
     */
    private function poll(float $maxWait = 3.0, bool $launchQueued = true): int
    {
        if ($maxWait) {
            $waitSec = (int) $maxWait;
            $waitUSec = (int) (($maxWait - $waitSec) * 10 ** 6);
        } else {
            $waitSec = 10;
            $waitUSec = 0;
        }

        if (empty($this->activeConnections)) {
            return 0;
        }

        $read = $error = $reject = $this->activeConnections;
        $res = mysqli_poll($read, $error, $reject, $waitSec, $waitUSec);

        if ($res === false) {
            echo "Pool is false !"; exit(5);
        }

        // FIXME If no answer and $maxWait = 0, relaunch !

        foreach ($error as $connection) {
            echo 'Errrrroooooooooooooor', "\n";
            var_dump($connection);
            exit;
        }

        foreach ($read as $connection) {
            $uid = $connection->getUid();
            unset($this->activeConnections[$uid]);

            $qid = $this->connectionsLastQuery[$uid]['qid'];
            $this->connectionsLastQuery[$uid]['result'] = $connection->reap_async_query();
            $this->readyResults[$qid] = $this->connectionsLastQuery[$uid];

            $this->idleConnections[$uid] = $connection;
        }

        if (!empty($reject)) {
            foreach ($reject as $c) {
                var_dump($c, $c->error);
            }
            throw new LogicException('MySQLi::poll rejects ' . count($reject) . ' connection(s)');
        }

        echo "$res read. " . count($this->queuedQueries) . " queries waiting\n";
        if ($res >= 1 && $launchQueued) {
            $this->launchQueuedQueries();
        }

        return $res;
    }

    /**
     * Get next
     * @param float $maxWait
     * @return bool|mixed
     */
    public function getNextAsyncResult(float $maxWait = 3.0)
    {
        if (empty($this->readyResults)) {
            $this->poll($maxWait);
        }

        return empty($this->readyResults) ? false : array_shift($this->readyResults);
    }

    /**
     * Waits for a specific asynchronous query
     *
     * @param int $qid
     * @param float $maxWait
     * @return bool|mixed
     */
    public function waitForQuery(int $qid, float $maxWait = 3.0)
    {
        // TODO Check if QID exists somewhere so we don't wait for nothing
        $start = microtime(true);
        do {
            if (isset($this->readyResults[$qid])) {
                $query = $this->readyResults[$qid];
                unset($this->readyResults[$qid]);
                return $query;
            }
            $this->poll($maxWait);
            /*TODO
            if ($maxWait === 0) {

            }*/
            $now = microtime(true);
            $maxWait -= $now - $start;
            $start = $now;
        }
        while ($maxWait >= 0);

        return false;
    }

    /**
     * Escape strings for MySQL
     * @param string $string
     * @param bool $fullEscape
     * @return string
     */
    public function escapeString(string $string, bool $fullEscape = false)
    {
        $link = $this->getAvailableConnection(false);
        $string = $link->real_escape_string($string);
        if (true === $fullEscape) {
            $string = addcslashes($string, '%_');
        }

        return $string;
    }

    /**
     * Automagically escape variables for MySQL
     * @param mixed $mixed
     * @param bool $fullEscape
     * @return string
     */
    public function escape($mixed, bool $fullEscape = false)
    {
        switch (gettype($mixed)) {
            case 'NULL':
                return 'NULL';
            case 'boolean':
                return $mixed ? '1' : '0';
            case 'integer':
            case 'double':
                return $mixed;
        }

        $mixed = (string) $mixed;
        return '\'' . $this->escapeString($mixed, $fullEscape) . '\'';
    }

    /**
     * Start a transaction
     *
     * When you start a transaction, all subsequent requests will be part on the transaction and will use the SAME
     * MySQL connection.
     * Asynchronous queries ARE NOT included in transactions.
     */
    public function beginTransaction()
    {
        if ($this->isInTransaction()) {
            $this->transactionConnection = $this->getAvailableConnection();
            $this->transactionConnection->begin_transaction();
        }
    }

    /**
     * Check if a transaction is open
     * @return bool
     */
    public function isInTransaction(): bool
    {
        return $this->transactionConnection instanceof \mysqli;
    }

    /**
     * Commit a transaction.
     * If several transactions are started, you will have to commit each one.
     * @return bool
     */
    public function commit(): bool
    {
        if ($this->isInTransaction()) {
            $link = $this->transactionConnection;
            $link->commit();
            $this->transactionConnection = null;
            unset($this->activeConnections[$link->getUid()]);
            $this->idleConnections[$link->getUid()] = $link;
            return true;
        }

        return false;
    }

    /**
     * Rollback current transaction
     * No matter how many transactions are started
     *
     * @return bool
     */
    public function rollback(): bool
    {
        if ($this->isInTransaction()) {
            $link = $this->transactionConnection;
            $link->rollback();
            $this->transactionConnection = null;
            unset($this->activeConnections[$link->getUid()]);
            $this->idleConnections[$link->getUid()] = $link;
            return true;
        }

        return false;
    }
}
