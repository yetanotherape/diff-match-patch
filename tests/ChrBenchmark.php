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


function unicodeChr1($code)
{
    return mb_convert_encoding('&#' . $code . ';', 'UTF-8', 'HTML-ENTITIES');
}

function unicodeChr2($code)
{
    if ($code < 0xFFFF) {
        return json_decode('"' . sprintf('\u%04X', $code) . '"');
    } else {
        $first = (($code - 0x10000) >> 10) + 0xD800;
        $second = (($code - 0x10000) % 0x400) + 0xDC00;
        return json_decode('"' . sprintf('\u%04X\u%04X', $first, $second) . '"');
    }
}

function unicodeChr3($code)
{
    if ($code < 0xFF) {
        return chr($code);
    } elseif ($code < 0xFFFF) {
        return json_decode('"' . sprintf('\u%04X', $code) . '"');
    } else {
        $first = (($code - 0x10000) >> 10) + 0xD800;
        $second = (($code - 0x10000) % 0x400) + 0xDC00;
        return json_decode('"' . sprintf('\u%04X\u%04X', $first, $second) . '"');
    }
}

function unicodeChr4($code)
{
    if ($code < 0xFF) {
        return chr($code);
    } elseif(PHP_MAJOR_VERSION >= 7) {
        $str =  (sprintf('\u{%X}', $code));
        eval("\$str = \"$str\";");
        return $str;
    } else {
        if ($code < 0xFFFF) {
            return json_decode('"' . sprintf('\u%04X', $code) . '"');
        } else {
            $first = (($code - 0x10000) >> 10) + 0xD800;
            $second = (($code - 0x10000) % 0x400) + 0xDC00;
            return json_decode('"' . sprintf('\u%04X\u%04X', $first, $second) . '"');
        }
    }
}

function unicodeChr5($code)
{
    return mb_chr($code, 'UCS-2LE');
}

function unicodeOrd1($char)
{
    if (mb_internal_encoding() !== 'UCS-4LE') {
        $char = iconv(mb_internal_encoding(), 'UCS-4LE', $char);
    }
    $code = 0;
    for ($i = 0; $i < strlen($char); $i++) {
        $code += ord($char[$i]) * pow(256, $i);
    }

    return $code;
}

//mb_internal_encoding('UTF-32');

//mb_internal_encoding('UTF-8');
//var_dump(unicodeChr2(97) === unicodeChr3(97));
//var_dump(strlen(unicodeChr2(97)) === strlen(unicodeChr3(97)));
//var_dump(mb_strlen(unicodeChr2(97)) === mb_strlen(unicodeChr3(97)));
//exit;

$codeList = [
    97 => 'a',
    255 => 'ÿ',
    256 => 'Ā',
    260 => 'Ą',
    65535 => '?',
    65536 => '𐀀',
    128570 => '😺',
];
foreach ($codeList as $code => $char) {
    echo 'code = ' . $code . PHP_EOL;
    echo 'char = ' . $char . PHP_EOL;
    echo 'chr() = ' . chr($code) . PHP_EOL;
    echo 'unicodeChr1() = ' . unicodeChr1($code) . PHP_EOL;
    echo 'unicodeChr2() = ' . unicodeChr2($code) . PHP_EOL;
    echo 'unicodeChr3() = ' . unicodeChr3($code) . PHP_EOL;
    echo 'unicodeChr4() = ' . unicodeChr4($code) . PHP_EOL;
    echo 'unicodeChr5() = ' . unicodeChr5($code) . PHP_EOL;
    echo PHP_EOL;
}

$char = 'a';
echo 'char = ' . $char . PHP_EOL;
echo 'ord() = ' . ord($char) . PHP_EOL;
echo 'unicodeOrd1() = ' . unicodeOrd1($char) . PHP_EOL;
echo PHP_EOL;


$char = 'Ą';
echo 'char = ' . $char . PHP_EOL;
echo 'ord() = ' . ord($char) . PHP_EOL;
echo 'unicodeOrd1() = ' . unicodeOrd1($char) . PHP_EOL;
echo PHP_EOL;


$char = '😺';
echo 'char = ' . $char . PHP_EOL;
echo 'ord() = ' . ord($char) . PHP_EOL;
echo 'unicodeOrd1() = ' . unicodeOrd1($char) . PHP_EOL;
echo PHP_EOL;

//exit;

sleep(1);

$N = 1000000;

// chr(), M = 255
$timeStart = microtime(1);
$M = 255;
for ($i = 0; $i < $N; $i++) {
    $char = chr($i % $M);
}
$timeElapsed = microtime(1) - $timeStart;
echo 'chr(), M = ' . $M . PHP_EOL;
echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 3) . PHP_EOL . PHP_EOL;


// unicodeChr1(), M = 255
$timeStart = microtime(1);
$M = 255;
for ($i = 0; $i < $N; $i++) {
    $char = unicodeChr1($i % $M);
}
$timeElapsed = microtime(1) - $timeStart;
echo 'unicodeChr1(), M = ' . $M . PHP_EOL;
echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 3) . PHP_EOL . PHP_EOL;


// unicodeChr2(), M = 255
$timeStart = microtime(1);
$M = 255;
for ($i = 0; $i < $N; $i++) {
    $char = unicodeChr2($i % $M);
}
$timeElapsed = microtime(1) - $timeStart;
echo 'unicodeChr2(), M = ' . $M . PHP_EOL;
echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 3) . PHP_EOL . PHP_EOL;

// unicodeChr3(), M = 255
$timeStart = microtime(1);
$M = 255;
for ($i = 0; $i < $N; $i++) {
    $char = unicodeChr3($i % $M);
}
$timeElapsed = microtime(1) - $timeStart;
echo 'unicodeChr3(), M = ' . $M . PHP_EOL;
echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 3) . PHP_EOL . PHP_EOL;

// unicodeChr5(), M = 255
$timeStart = microtime(1);
$M = 255;
for ($i = 0; $i < $N; $i++) {
    $char = unicodeChr5($i % $M);
}
$timeElapsed = microtime(1) - $timeStart;
echo 'unicodeChr5(), M = ' . $M . PHP_EOL;
echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 3) . PHP_EOL . PHP_EOL;


// unicodeChr1(), M = 65535
$timeStart = microtime(1);
$M = 65535;
for ($i = 0; $i < $N; $i++) {
    $char = unicodeChr1($i % $M);
}
$timeElapsed = microtime(1) - $timeStart;
echo 'unicodeChr1(), M = ' . $M . PHP_EOL;
echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 3) . PHP_EOL . PHP_EOL;


// unicodeChr2(), M = 65535
$timeStart = microtime(1);
$M = 65535;
for ($i = 0; $i < $N; $i++) {
    $char = unicodeChr2($i % $M);
}
$timeElapsed = microtime(1) - $timeStart;
echo 'unicodeChr2(), M = ' . $M . PHP_EOL;
echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 3) . PHP_EOL . PHP_EOL;

// unicodeChr3(), M = 65535
$timeStart = microtime(1);
$M = 65535;
for ($i = 0; $i < $N; $i++) {
    $char = unicodeChr3($i % $M);
}
$timeElapsed = microtime(1) - $timeStart;
echo 'unicodeChr3(), M = ' . $M . PHP_EOL;
echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 3) . PHP_EOL . PHP_EOL;

// unicodeChr4(), M = 65535
$timeStart = microtime(1);
$M = 65535;
for ($i = 0; $i < $N; $i++) {
    $char = unicodeChr4($i % $M);
}
$timeElapsed = microtime(1) - $timeStart;
echo 'unicodeChr4(), M = ' . $M . PHP_EOL;
echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 3) . PHP_EOL . PHP_EOL;

// unicodeChr5(), M = 65535
$timeStart = microtime(1);
$M = 65535;
for ($i = 0; $i < $N; $i++) {
    $char = unicodeChr5($i % $M);
}
$timeElapsed = microtime(1) - $timeStart;
echo 'unicodeChr5(), M = ' . $M . PHP_EOL;
echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 3) . PHP_EOL . PHP_EOL;


// unicodeChr1(), M = 16777215
$timeStart = microtime(1);
$M = 16777215;
for ($i = 0; $i < $N; $i++) {
    $char = unicodeChr1($i % $M);
}
$timeElapsed = microtime(1) - $timeStart;
echo 'unicodeChr1(), M = ' . $M . PHP_EOL;
echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 3) . PHP_EOL . PHP_EOL;


// unicodeChr2(), M = 16777215
$timeStart = microtime(1);
$M = 16777215;
for ($i = 0; $i < $N; $i++) {
    $char = unicodeChr2($i % $M);
}
$timeElapsed = microtime(1) - $timeStart;
echo 'unicodeChr2(), M = ' . $M . PHP_EOL;
echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 3) . PHP_EOL . PHP_EOL;

// unicodeChr3(), M = 16777215
$timeStart = microtime(1);
$M = 16777215;
for ($i = 0; $i < $N; $i++) {
    $char = unicodeChr3($i % $M);
}
$timeElapsed = microtime(1) - $timeStart;
echo 'unicodeChr3(), M = ' . $M . PHP_EOL;
echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 3) . PHP_EOL . PHP_EOL;

// unicodeChr4(), M = 16777215
$timeStart = microtime(1);
$M = 16777215;
for ($i = 0; $i < $N; $i++) {
    $char = unicodeChr4($i % $M);
}
$timeElapsed = microtime(1) - $timeStart;
echo 'unicodeChr4(), M = ' . $M . PHP_EOL;
echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 3) . PHP_EOL . PHP_EOL;

// unicodeChr5(), M = 16777215
$timeStart = microtime(1);
$M = 16777215;
for ($i = 0; $i < $N; $i++) {
    $char = unicodeChr5($i % $M);
}
$timeElapsed = microtime(1) - $timeStart;
echo 'unicodeChr5(), M = ' . $M . PHP_EOL;
echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 3) . PHP_EOL . PHP_EOL;
