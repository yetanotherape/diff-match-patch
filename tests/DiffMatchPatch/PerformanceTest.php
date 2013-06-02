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

namespace DiffMatchPatch;

/**
 * @package DiffMatchPatch
 * @author Daniil Skrobov <yetanotherape@gmail.com>
 * @runTestsInSeparateProcesses
 */
class PerformanceTest extends \PHPUnit_Framework_TestCase
{
    protected  function setUp() {
        mb_internal_encoding('UTF-8');
    }

    public function testDiffMainPerformance()
    {
        $text1 = file_get_contents(__DIR__ . '/fixtures/S_performance1.txt');
        $text2 = file_get_contents(__DIR__ . '/fixtures/S_performance2.txt');

        // Warm up
        $diff = new Diff();
        $diff->setTimeout(0);
        $diff->main($text1, $text2);

        $timeStart = microtime(1);
        $memoryStart = memory_get_usage();

        $diff = new Diff();
        $diff->setTimeout(0);
        $diff->main($text1, $text2);

        $timeElapsed = microtime(1) - $timeStart;
        $memoryUsage = (memory_get_peak_usage() - $memoryStart) / 1024 / 1024;

        $this->assertLessThan(0.6, $timeElapsed);
        $this->assertLessThan(1, $memoryUsage);

        echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
        echo 'Memory usage: ' . round($memoryUsage, 3) . PHP_EOL;
    }

    public function testDiffMainMemoryLeaks()
    {
        $text1 = file_get_contents(__DIR__ . '/fixtures/S_performance1.txt');
        $text2 = file_get_contents(__DIR__ . '/fixtures/S_performance2.txt');
        $n = 20;

        // Warm up
        $diff = new Diff();
        $diff->setTimeout(0);
        $diff->main($text1, $text2);
        unset($diff);

        $timeStart = microtime(1);
        $memoryStart = memory_get_usage();

        for ($i = 0; $i < $n; $i++) {
            $diff = new Diff();
            $diff->setTimeout(0);
            $diff->main($text1, $text2);
            unset($diff);
        }

        $timeElapsed = microtime(1) - $timeStart;
        $memoryUsage = (memory_get_usage() - $memoryStart) / 1024 / 1024;

        $this->assertLessThan(0.001, $memoryUsage);

        echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
        echo 'Memory usage: ' . round($memoryUsage, 10) . PHP_EOL;
    }


}
