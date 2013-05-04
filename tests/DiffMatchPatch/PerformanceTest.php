<?php

namespace DiffMatchPatch;

class PerformanceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Diff
     */
    protected $d;
    /**
     * @var Match
     */
    protected $m;

    protected  function setUp() {
        $this->d = new Diff();
        $this->m = new Match();
    }

    public function testDiffPerformance(){
        $this->d->setTimeout(0.1);
        $a = "`Twas brillig, and the slithy toves\nDid gyre and gimble in the wabe:\nAll mimsy were the borogoves,\nAnd the mome raths outgrabe.\n";
        $b = "I am the very model of a modern major general,\nI've information vegetable, animal, and mineral,\nI know the kings of England, and I quote the fights historical,\nFrom Marathon to Waterloo, in order categorical.\n";
        // Increase the text lengths by 1024 times to ensure a timeout.
        for ($i = 0;  $i < 10; $i++) {
            $a .= $a;
            $b .= $b;
        }
        $startTime = microtime(1);
        $this->d->main($a, $b);
        $endTime = microtime(1);

        echo round($endTime - $startTime, 3) . PHP_EOL;
        echo round(memory_get_peak_usage()/1024/1024, 3) . PHP_EOL;

        $this->assertLessThan(1, $endTime - $startTime, 'Too slow');
        $this->assertLessThan(55*1024*1024, memory_get_peak_usage(), 'Too much memory usage');

        $this->d->setTimeout(20);
        $a = "`Twas brillig, and the slithy toves\nDid gyre and gimble in the wabe:\nAll mimsy were the borogoves,\nAnd the mome raths outgrabe.\n";
        $b = "I am the very model of a modern major general,\nI've information vegetable, animal, and mineral,\nI know the kings of England, and I quote the fights historical,\nFrom Marathon to Waterloo, in order categorical.\n";
        // Increase the text lengths by 1024 times to ensure a timeout.
        for ($i = 0;  $i < 2; $i++) {
            $a .= $a;
            $b .= $b;
        }
        $startTime = microtime(1);
        $this->d->main($a, $b);
        $endTime = microtime(1);

        echo round($endTime - $startTime, 3) . PHP_EOL;
        echo round(memory_get_peak_usage()/1024/1024, 3) . PHP_EOL;

        $this->assertLessThan(11, $endTime - $startTime, 'Too slow');
        $this->assertLessThan(55*1024*1024, memory_get_peak_usage(), 'Too much memory usage');
    }
}
