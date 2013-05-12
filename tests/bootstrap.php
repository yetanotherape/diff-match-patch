<?php
/*
 * DiffMatchPatch is a port of the google-diff-match-patch (http://code.google.com/p/google-diff-match-patch/)
 * lib to PHP.
 *
 * (c) 2006 Google Inc.
 * (c) 2013 Daniil Skrobov <yetanotherape@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

ini_set('error_reporting', E_ALL | E_STRICT);

//ini_set('xdebug.remote_autostart', 1);
//ini_set('xdebug.remote_host', '10.0.2.2');
//ini_set('xdebug.remote_port', '9000');
//ini_set('xdebug.remote_log', '/var/log/php5-xdebug-remote.log');

$loader = require __DIR__ . "/../vendor/autoload.php";
$loader->add('DiffMatchPatch\\', __DIR__);
