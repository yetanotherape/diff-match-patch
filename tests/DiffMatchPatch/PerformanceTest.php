<?php

namespace DiffMatchPatch;

class PerformanceTest extends \PHPUnit_Framework_TestCase
{
    protected  function setUp() {

    }

    public function testDiffMainPerformance()
    {
        $text1 = file_get_contents(__DIR__ . '/fixtures/S_performance1.txt');
        $text2 = file_get_contents(__DIR__ . '/fixtures/S_performance2.txt');

        $timeStart = microtime(1);
        $memoryStart = memory_get_usage();

        $diff = new Diff();
        $diff->setTimeout(0);
        $diff->main($text1, $text2);

        $timeElapsed = microtime(1) - $timeStart;
        $memoryUsage = (memory_get_peak_usage() - $memoryStart) / 1024 / 1024;

        $this->assertLessThan(0.8, $timeElapsed);
        $this->assertLessThan(2, $memoryUsage);

        echo 'Elapsed time: ' . round($timeElapsed, 3) . PHP_EOL;
        echo 'Memory usage: ' . round($memoryUsage, 3) . PHP_EOL;
    }

    public function testDiffMainMemoryLeaks()
    {
        $text1 = file_get_contents(__DIR__ . '/fixtures/S_performance1.txt');
        $text2 = file_get_contents(__DIR__ . '/fixtures/S_performance2.txt');
        $n = 20;

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
