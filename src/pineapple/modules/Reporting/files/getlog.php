#!/usr/bin/php-cgi
<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */

'';

$probesOnly = false;

if (count($argv) > 1) {
    if ($argv[1] === "--probes") {
        $probesOnly = true;
    }
}

$logDBPath = exec("uci get pineap.@config[0].hostapd_db_path");
if (!file_exists($logDBPath)) {
	exit("File ${logDBPath} does not exist\n");
}
$dbConnection = new \frieren\orm\SQLite($logDBPath, false);
if ($dbConnection === NULL) {
	exit("Unable to create database connection\n");
}

$sql = "SELECT * FROM log ORDER BY updated_at DESC;";
if ($probesOnly) {
    $sql = "SELECT * FROM log WHERE log_type=0 ORDER BY updated_at DESC;";
}
$log = $dbConnection->queryLegacy($sql);

$clearlog = exec('uci get reporting.@settings[0].clear_log');
if ($clearlog == '1') {
	$dbConnection->execLegacy('DELETE FROM log;');
}
echo json_encode($log, JSON_PRETTY_PRINT);
