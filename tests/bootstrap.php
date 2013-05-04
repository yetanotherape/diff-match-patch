<?php
ini_set('error_reporting', E_ALL | E_STRICT);

//ini_set('xdebug.remote_autostart', 1);
//ini_set('xdebug.remote_host', '10.0.2.2');
//ini_set('xdebug.remote_port', '9000');
//ini_set('xdebug.remote_log', '/var/log/php5-xdebug-remote.log');

$loader = require __DIR__ . "/../vendor/autoload.php";
$loader->add('DiffMatchPatch\\', __DIR__);
