<?php
declare(strict_types=1);
namespace HaDb;

/**
 * MySQL server description
 * @todo Rely on PHP.ini configuration
 */
class ServerDescription
{
    /**
     * @var string|null
     */
    private $username;

    /**
     * @var string|null
     */
    private $password;

    /**
     * @var string|null
     */
    private $hostname;

    /**
     * @var string
     */
    private $database = '';

    /**
     * @var int|null
     */
    private $port;

    /**
     * @var string|null
     */
    private $socket;

    /**
     * @var array
     */
    private $options = [];

    /**
     * Charset used by MySQL connection.
     * null means charset will use default configuration.
     *
     * @var string|null
     */
    private $charset = null;

    /**
     * Autocommit value
     * null means charset will use default state.
     *
     * @var bool|null
     */
    private $autocommit = null;

    /**
     * Persistent connection to MySQL server.
     *
     * @var bool
     */
    private $persistent = false;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->setHostname(ini_get('mysqli.default_host'));
        $this->setPort((int) ini_get('mysqli.default_port'));
        $this->setSocket(ini_get('mysqli.default_socket'));
        $this->setUsername(ini_get('mysqli.default_user'));
        $this->setPassword(ini_get('mysqli.default_pw'));
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param string $username
     */
    public function setUsername(string $username)
    {
        $this->username = $username;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword(string $password)
    {
        $this->password = $password;
    }

    /**
     * @return string
     */
    public function getHostname(): string
    {
        return $this->hostname;
    }

    /**
     * @param string $hostname
     */
    public function setHostname(string $hostname)
    {
        $this->hostname = $hostname;
        if (strpos($this->hostname, 'p:') === 0) {
            $this->hostname = substr($this->hostname, 2);
            $this->setPersistent(true);
        }
    }

    /**
     * @return string
     */
    public function getDatabase(): string
    {
        return $this->database;
    }

    /**
     * @param string $database
     */
    public function setDatabase(string $database)
    {
        $this->database = $database;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @param int $port
     */
    public function setPort(int $port)
    {
        $this->port = $port;
    }

    /**
     * @return string
     */
    public function getSocket(): string
    {
        return $this->socket;
    }

    /**
     * @param string $socket
     */
    public function setSocket(string $socket)
    {
        $this->socket = $socket;
    }

    /**
     * @return ?string
     */
    public function getCharset(): ?string
    {
        return $this->charset;
    }

    /**
     * @param string $charset
     */
    public function setCharset(string $charset)
    {
        $this->charset = $charset;
    }

    /**
     * @return bool|null
     */
    public function getAutocommit(): ?bool
    {
        return $this->autocommit;
    }

    /**
     * @param bool $autocommit
     */
    public function setAutocommit(bool $autocommit = true)
    {
        $this->autocommit = $autocommit;
    }

    /**
     * @return bool
     */
    public function getPersistent(): bool
    {
        // TODO Gérer hostname particulier p:
        return $this->persistent;
    }

    /**
     * @param bool $persistent
     */
    public function setPersistent(bool $persistent = true)
    {
        $this->persistent = $persistent;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param int $option
     * @param mixed $value
     */
    public function setOption($option, $value)
    {
        $this->options[$option] = $value;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        $str = '['. $this->getUid() . '] ' . $this->hostname;
        if (self::DEFAULT_PORT !== $this->port) {
            $str .= ':' . $this->port;
        }

        return  $str;
    }
}
