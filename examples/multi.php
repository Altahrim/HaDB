#!/usr/bin/env php
<?php
error_reporting(E_ALL);

// Log errors in local file error.log
ini_set('display_errors', 1);
ini_set('html_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'error.log');

// Default parameters
$nbQueries = 10;
$nbConn = 5;
$useExceptions = false;

// Parse options
$opts = getopt('q:c:e');
if (isset($opts['c'])) {
    $nbConn = (int) $opts['c'];
}
if (isset($opts['q'])) {
    $nbQueries = (int) $opts['q'];
}
if (array_key_exists('e', $opts)) {
    $useExceptions = true;
}

echo "### Sending $nbQueries queries on $nbConn connections ###\n";

if ($useExceptions) {
    echo "### Use exceptions instead of errors\n";
    $dri = new mysqli_driver();
    $dri->report_mode = MYSQLI_REPORT_STRICT;
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

// Define servers
$serverDescNOK = new \HaDb\ServerDescription();
$serverDescNOK->setHostname('non_existing_hostname');
$serverDescNOK->setUsername('1nv4lid User');
$serverDescNOK->setPassword('wrong password');
$serverDescOK = new \HaDb\ServerDescription();
$serverDescOK->setUsername('root');
$serverDescOK->setOption(\MYSQLI_OPT_CONNECT_TIMEOUT, 2);

$server1 = new \HaDb\Server($serverDescOK);
$server2 = new \HaDb\Server($serverDescNOK);
$server3 = new \HaDb\Server($serverDescOK);

$pool = new \HaDb\ServerPool();
$pool->addServer($server1);
$pool->addServer($server2);
$pool->addServer($server3);

$logger = new \HaDb\Logger();

$db = new HaDb($pool, $logger);
$db->setMaxConn($nbConn);

for ($i = 1; $i <= $nbQueries; ++$i) {
    $j = rand(1, 30);
    $sql = 'SELECT \'' . $i . '\', \'Sleep for ' . $j . 's\', SLEEP(' . $j . ')';
    if (rand(0, 5) === 0) {
        $sql = "Invalid SQL command";
    }
    if (0 === ($i % 3)) {
        $res = $db->query($sql);
    } else {
        $res = $db->asyncQuery($sql);
    }
}

echo "\n## All requests sent. Waiting for answers…\n\n";

$qid = rand(1, $nbQueries);
$res = $db->waitForQuery($qid, 0);
echo "### Result for query $qid ###\n", print_r($res, true), "\n";

for ($i = 1; $i <= $nbQueries; ++$i) {
    $res = $db->getNextAsyncResult(5);
    echo 'Result ', implode(
        ', ',
        ($res['result'] ? $res['result']->fetch_assoc() : ["Nope"])
    ), " on connection " . $res['connUid'] . "\n";
}
