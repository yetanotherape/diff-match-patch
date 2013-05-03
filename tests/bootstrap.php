<?php
ini_set('error_reporting', E_ALL | E_STRICT);
ini_set('xdebug.profiler_enable', 'On');

$loader = require __DIR__ . "/../vendor/autoload.php";
$loader->add('DiffMatchPatch\\', __DIR__);
