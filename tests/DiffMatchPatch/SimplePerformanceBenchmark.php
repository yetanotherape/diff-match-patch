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

require __DIR__ . "/../../src/DiffMatchPatch/Diff.php";
require __DIR__ . "/../../src/DiffMatchPatch/DiffToolkit.php";
require __DIR__ . "/../../src/DiffMatchPatch/Utils.php";

use DiffMatchPatch\Diff;

$size = 'M';
$text1 = file_get_contents(__DIR__ . "/fixtures/{$size}_performance1.txt");
$text2 = file_get_contents(__DIR__ .  "/fixtures/{$size}_performance2.txt");

//$text1 = "The quick brown fox jumps over the lazy dog.";
//$text2 = "That quick brown fox jumped over a lazy dog.";

$timeStart = microtime(1);

$diff = new Diff();
$diff->setTimeout(0);
$diff->main($text1, $text2, false)->cleanupSemantic();

$timeElapsed = microtime(1) - $timeStart;

echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 3) . PHP_EOL;
echo 'Texts length: ' . mb_strlen($text1) . ', ' . mb_strlen($text2) . PHP_EOL;
echo 'Diffs count: ' . count($diff->getChanges()) . PHP_EOL . PHP_EOL;

$timeStart = microtime(1);

$diff = new Diff();
$diff->setTimeout(0);
$diff->main($text1, $text2)->cleanupEfficiency();

$timeElapsed = microtime(1) - $timeStart;

echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 3) . PHP_EOL;
echo 'Texts length: ' . mb_strlen($text1) . ', ' . mb_strlen($text2) . PHP_EOL;
echo 'Diffs count: ' . count($diff->getChanges()) . PHP_EOL . PHP_EOL;
