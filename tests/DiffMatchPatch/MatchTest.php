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
 * @author Neil Fraser <fraser@google.com>
 * @author Daniil Skrobov <yetanotherape@gmail.com>
 */
class MatchTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Match
     */
    protected $m;

    protected  function setUp() {
        mb_internal_encoding('UTF-8');

        $this->m = new Match();
    }

    public function testAlphabet()
    {
        // Initialise the bitmasks for Bitap.
        $this->assertEquals(array(
            "a" => 4,
            "b" => 2,
            "c" => 1,
        ), $this->m->alphabet("abc"));

        $this->assertEquals(array(
            "a" => 37,
            "b" => 18,
            "c" => 8,
        ), $this->m->alphabet("abcaba"));
    }

    public function testBitap(){
        $this->m->setDistance(100);
        $this->m->setThreshold(0.5);

        // Exact matches.
        $this->assertEquals(5, $this->m->bitap("abcdefghijk", "fgh", 5));

        $this->assertEquals(5, $this->m->bitap("abcdefghijk", "fgh", 0));

        // Fuzzy matches.
        $this->assertEquals(4, $this->m->bitap("abcdefghijk", "efxhi", 0));

        $this->assertEquals(2, $this->m->bitap("abcdefghijk", "cdefxyhijk", 5));

        $this->assertEquals(-1, $this->m->bitap("abcdefghijk", "bxy", 1));

        // Overflow.
        $this->assertEquals(2, $this->m->bitap("123456789xx0", "3456789x0", 2));

        $this->assertEquals(0, $this->m->bitap("abcdef", "xxabc", 4));

        $this->assertEquals(3, $this->m->bitap("abcdef", "defyy", 4));

        $this->assertEquals(0, $this->m->bitap("abcdef", "xabcdefy", 0));

        // Threshold test.
        $this->m->setThreshold(0.4);
        $this->assertEquals(4, $this->m->bitap("abcdefghijk", "efxyhi", 1));

        $this->m->setThreshold(0.3);
        $this->assertEquals(-1, $this->m->bitap("abcdefghijk", "efxyhi", 1));

        $this->m->setThreshold(0.0);
        $this->assertEquals(1, $this->m->bitap("abcdefghijk", "bcdef", 1));

        $this->m->setThreshold(0.5);

        // Multiple select.
        $this->assertEquals(0, $this->m->bitap("abcdexyzabcde", "abccde", 3));

        $this->assertEquals(8, $this->m->bitap("abcdexyzabcde", "abccde", 5));

        // Distance test.
        // Strict location.
        $this->m->setDistance(10);
        $this->assertEquals(-1, $this->m->bitap("abcdefghijklmnopqrstuvwxyz", "abcdefg", 24));

        $this->assertEquals(0, $this->m->bitap("abcdefghijklmnopqrstuvwxyz", "abcdxxefg", 1));

        //Loose location.
        $this->m->setDistance(1000);
        $this->assertEquals(0, $this->m->bitap("abcdefghijklmnopqrstuvwxyz", "abcdefg", 24));
    }

    public function testMain(){
        // Full match.
        // Shortcut matches.
        $this->assertEquals(0, $this->m->main("abcdef", "abcdef", 1000));

        $this->assertEquals(-1, $this->m->main("", "abcdef", 1));

        $this->assertEquals(3, $this->m->main("abcdef", "", 3));

        $this->assertEquals(3, $this->m->main("abcdef", "de", 3));

        $this->assertEquals(3, $this->m->main("abcdef", "defy", 4));

        $this->assertEquals(0, $this->m->main("abcdef", "abcdefy", 0));

        // Complex match.
        $this->m->setThreshold(0.7);
        $this->assertEquals(4, $this->m->main("I am the very model of a modern major general.", " that berry ", 5));
        $this->m->setThreshold(0.5);

        // Test null inputs.
        try {
            $this->m->main(null, null, 0);
            $this->fail();
        } catch (\InvalidArgumentException $e) {

        }
    }
}
