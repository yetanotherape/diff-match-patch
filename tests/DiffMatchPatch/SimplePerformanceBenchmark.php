<?php
require __DIR__ . "/../../src/DiffMatchPatch/Diff.php";

use DiffMatchPatch\Diff;

$size = 'M';
$text1 = file_get_contents(__DIR__ . "/fixtures/{$size}_performance1.txt");
$text2 = file_get_contents(__DIR__ .  "/fixtures/{$size}_performance2.txt");

//$text1 = "The quick brown fox jumps over the lazy dog.";
//$text2 = "That quick brown fox jumped over a lazy dog.";

$timeStart = microtime(1);
$diff = new Diff();
$diff->setTimeout(0);
$diffs = $diff->main($text1, $text2, false);
$timeElapsed = microtime(1) - $timeStart;

echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 3) . PHP_EOL;
echo 'Texts length: ' . mb_strlen($text1) . ', ' . mb_strlen($text2) . PHP_EOL;
echo 'Diffs count: ' . count($diffs) . PHP_EOL;
