<?php
declare (strict_types=1);
namespace HaDb;

use Codeception\Test\Unit;

final class ServerDescriptionTest extends Unit
{
    public function testNew()
    {
        $server = new ServerDescription();

        $this->assertInstanceOf(ServerDescription::class, $server);
    }

    public function testUsernameAccessorsAndDefaultValue()
    {
        $defaultUsername = 'random_user';
        $username = 'mysql_user';
        ini_set('mysqli.default_user', $defaultUsername);
        $server = new ServerDescription();

        $default = $server->getUsername();
        $server->setUsername($username);
        $custom = $server->getUsername();

        $this->assertEquals($defaultUsername, $default, 'Default MySQL username must be rely on mysqli.default_user.');
        $this->assertEquals($username, $custom);
    }

    public function testPasswordAccessorsAndDefaultValue()
    {
        $defaultPassword = 's3cr3t';
        ini_set('mysqli.default_pw', $defaultPassword);
        $password = 'secret';
        $server = new ServerDescription();

        $default = $server->getPassword();
        $server->setPassword($password);
        $custom = $server->getPassword();

        $this->assertEquals($defaultPassword, $default, 'Default MySQL password must rely on mysqli.default_pw.');
        $this->assertEquals($password, $custom);
    }

    public function testHostnameAccessorsAndDefaultValue()
    {
        $defaultHost = 'my_hostname';
        ini_set('mysqli.default_host', $defaultHost);
        $hostname = 'example.org';
        $server = new ServerDescription();

        $default = $server->getHostname();
        $server->setHostname($hostname);
        $custom = $server->getHostname();

        $this->assertEquals($defaultHost, $default, 'Default MySQL password must rely on mysqli.default_host.');
        $this->assertEquals($hostname, $custom);
    }

    public function testDatabaseAccessorsAndDefaultValue()
    {
        $database = 'mydb';
        $server = new ServerDescription();

        $default = $server->getDatabase();
        $server->setDatabase($database);
        $custom = $server->getDatabase();

        $this->assertEquals('', $default, 'Default MySQL database must be empty.');
        $this->assertEquals($database, $custom);
    }

    public function testPortAccessorsAndDefaultValue()
    {
        $defaultPort = 12345;
        ini_set('mysqli.default_port', (string) $defaultPort);
        $port = 3307;
        $server = new ServerDescription();

        $default = $server->getPort();
        $server->setPort($port);
        $custom = $server->getPort();

        $this->assertEquals($defaultPort, $default, 'Default MySQL port must must rely on mysqli.default_port.');
        $this->assertEquals($port, $custom);
    }

    public function testSocketAccessorsAndDefaultValue()
    {
        $defaultSocket = '/run/mysql.sock';
        ini_set('mysqli.default_socket', $defaultSocket);
        $socket = '/var/run/mysql.sock';
        $server = new ServerDescription();

        $default = $server->getSocket();
        $server->setSocket($socket);
        $custom = $server->getSocket();

        $this->assertEquals($defaultSocket, $default, 'Default MySQL socket must rely on mysqli.default_socket.');
        $this->assertEquals($socket, $custom);
    }

    public function testCharsetAccessorsAndDefaultValue()
    {
        $charset = 'UTF-8';
        $server = new ServerDescription();

        $default = $server->getCharset();
        $server->setCharset($charset);
        $custom = $server->getCharset();

        $this->assertNull($default, 'Default connection charset must be NULL.');
        $this->assertEquals($charset, $custom);
    }

    public function testAutocommitAccessorsAndDefaultValue()
    {
        $server = new ServerDescription();

        $default = $server->getAutocommit();
        $server->setAutocommit(false);
        $disabled = $server->getAutocommit();
        $server->setAutocommit();
        $enabledByDefault = $server->getAutocommit();

        $this->assertNull($default, 'Default autocommit must be NULL.');
        $this->assertFalse($disabled);
        $this->assertTrue($enabledByDefault);
    }

    public function testPersistentConnectionAccessorsAndDefaultValue()
    {
        ini_set('mysqli.default_host', 'localhost');
        $server = new ServerDescription();

        $default = $server->getPersistent();
        $server->setPersistent(false);
        $enabled = $server->getPersistent();
        $server->setPersistent(false);
        $server->setPersistent();
        $enabledByDefault = $server->getPersistent();

        $this->assertFalse($default, 'MySQL persistent connection must be disabled by default.');
        $this->assertFalse($enabled);
        $this->assertTrue($enabledByDefault);
    }

    public function testPersistentConnectionDetection()
    {
        $host = 'example.org';
        ini_set('mysqli.default_host', 'p:' . $host);
        $server = new ServerDescription();
        $isPersistent = $server->getPersistent();
        $this->assertTrue($isPersistent, 'Connection must be persistent if hostname is set with p: prefix.');
        $this->assertEquals($host, $server->getHostname(), 'Hostname must be returned without p:.');

        ini_set('mysqli.default_host', $host);
        $server = new ServerDescription();
        $isPersistent = $server->getPersistent();
        $this->assertFalse($isPersistent);
        $this->assertEquals($host, $server->getHostname(), 'Hostname must be returned without p:.');

        $server->setHostname('p:' . $host);
        $isPersistent = $server->getPersistent();
        $this->assertTrue($isPersistent);
        $this->assertEquals($host, $server->getHostname(), 'Hostname must be returned without p:.');
    }

    public function testOptionsAccessorsAndDefaultValue()
    {
        $server = new ServerDescription();

        $default = $server->getOptions();
        $server->setOption(3,4);
        $server->setOption(5,6);
        $custom = $server->getOptions();

        $this->assertInternalType('array', $default);
        $this->assertEmpty($default);
        $this->assertEquals($custom, [3 => 4, 5 => 6]);
    }
}
