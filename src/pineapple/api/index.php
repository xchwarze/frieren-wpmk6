<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */

header('Content-Type: application/json');

require_once('pineapple.php');
require_once('API.php');
$api = new API();
echo $api->handleRequest();
