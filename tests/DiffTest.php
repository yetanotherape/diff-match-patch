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
class DiffTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Diff
     */
    protected $d;

    protected  function setUp() {
        mb_internal_encoding('UTF-8');

        $this->d = new Diff();
    }

    public function testCleanupMerge()
    {
        // Cleanup a messy diff.

        // Null case.
        $this->d->setChanges(array());
        $this->d->cleanupMerge();
        $this->assertEquals(array(), $this->d->getChanges());

        // No change case.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "a"),
            array(Diff::DELETE, "b"),
            array(Diff::INSERT, "c"),
        ));
        $this->d->cleanupMerge();
        $this->assertEquals(array(
            array(Diff::EQUAL, "a"),
            array(Diff::DELETE, "b"),
            array(Diff::INSERT, "c"),
        ), $this->d->getChanges());

        // Merge equalities.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "a"),
            array(Diff::EQUAL, "b"),
            array(Diff::EQUAL, "c"),
        ));
        $this->d->cleanupMerge();
        $this->assertEquals(array(
            array(Diff::EQUAL, "abc"),
        ), $this->d->getChanges());

        // Merge deletions.
        $this->d->setChanges(array(
            array(Diff::DELETE, "a"),
            array(Diff::DELETE, "b"),
            array(Diff::DELETE, "c"),
        ));
        $this->d->cleanupMerge();
        $this->assertEquals(array(
            array(Diff::DELETE, "abc"),
        ), $this->d->getChanges());

        // Merge insertions.
        $this->d->setChanges(array(
            array(Diff::INSERT, "a"),
            array(Diff::INSERT, "b"),
            array(Diff::INSERT, "c"),
        ));
        $this->d->cleanupMerge();
        $this->assertEquals(array(
            array(Diff::INSERT, "abc"),
        ), $this->d->getChanges());

        // Merge interweave.
        $this->d->setChanges(array(
            array(Diff::DELETE, "a"),
            array(Diff::INSERT, "b"),
            array(Diff::DELETE, "c"),
            array(Diff::INSERT, "d"),
            array(Diff::EQUAL, "e"),
            array(Diff::EQUAL, "f"),
        ));
        $this->d->cleanupMerge();
        $this->assertEquals(array(
            array(Diff::DELETE, "ac"),
            array(Diff::INSERT, "bd"),
            array(Diff::EQUAL, "ef"),
        ), $this->d->getChanges());

        // Prefix and suffix detection.
        $this->d->setChanges(array(
            array(Diff::DELETE, "a"),
            array(Diff::INSERT, "abc"),
            array(Diff::DELETE, "dc"),
        ));
        $this->d->cleanupMerge();
        $this->assertEquals(array(
            array(Diff::EQUAL, "a"),
            array(Diff::DELETE, "d"),
            array(Diff::INSERT, "b"),
            array(Diff::EQUAL, "c"),
        ), $this->d->getChanges());

        // Prefix and suffix detection with equalities.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "x"),
            array(Diff::DELETE, "a"),
            array(Diff::INSERT, "abc"),
            array(Diff::DELETE, "dc"),
            array(Diff::EQUAL, "y"),
        ));
        $this->d->cleanupMerge();
        $this->assertEquals(array(
            array(Diff::EQUAL, "xa"),
            array(Diff::DELETE, "d"),
            array(Diff::INSERT, "b"),
            array(Diff::EQUAL, "cy"),
        ), $this->d->getChanges());

        // Slide edit left.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "a"),
            array(Diff::INSERT, "ba"),
            array(Diff::EQUAL, "c"),
        ));
        $this->d->cleanupMerge();
        $this->assertEquals(array(
            array(Diff::INSERT, "ab"),
            array(Diff::EQUAL, "ac"),
        ), $this->d->getChanges());

        // Slide edit right.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "c"),
            array(Diff::INSERT, "ab"),
            array(Diff::EQUAL, "a"),
        ));
        $this->d->cleanupMerge();
        $this->assertEquals(array(
            array(Diff::EQUAL, "ca"),
            array(Diff::INSERT, "ba"),
        ), $this->d->getChanges());

        // Slide edit left recursive.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "a"),
            array(Diff::DELETE, "b"),
            array(Diff::EQUAL, "c"),
            array(Diff::DELETE, "ac"),
            array(Diff::EQUAL, "x"),
        ));
        $this->d->cleanupMerge();
        $this->assertEquals(array(
            array(Diff::DELETE, "abc"),
            array(Diff::EQUAL, "acx"),
        ), $this->d->getChanges());

        // Slide edit right recursive.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "x"),
            array(Diff::DELETE, "ca"),
            array(Diff::EQUAL, "c"),
            array(Diff::DELETE, "b"),
            array(Diff::EQUAL, "a"),
        ));
        $this->d->cleanupMerge();
        $this->assertEquals(array(
            array(Diff::EQUAL, "xca"),
            array(Diff::DELETE, "cba"),
        ), $this->d->getChanges());
    }

    public function testCleanupSemanticLossless()
    {
        // Slide diffs to match logical boundaries.
        // Null case.
        $this->d->setChanges(array());
        $this->d->cleanupSemanticLossless();
        $this->assertEquals(array(), $this->d->getChanges());

        // Blank lines.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "AAA\r\n\r\nBBB"),
            array(Diff::INSERT, "\r\nDDD\r\n\r\nBBB"),
            array(Diff::EQUAL, "\r\nEEE"),
        ));
        $this->d->cleanupSemanticLossless();
        $this->assertEquals(array(
            array(Diff::EQUAL, "AAA\r\n\r\n"),
            array(Diff::INSERT, "BBB\r\nDDD\r\n\r\n"),
            array(Diff::EQUAL, "BBB\r\nEEE"),
        ), $this->d->getChanges());

        // Line boundaries.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "AAA\r\nBBB"),
            array(Diff::INSERT, " DDD\r\nBBB"),
            array(Diff::EQUAL, " EEE"),
        ));
        $this->d->cleanupSemanticLossless();
        $this->assertEquals(array(
            array(Diff::EQUAL, "AAA\r\n"),
            array(Diff::INSERT, "BBB DDD\r\n"),
            array(Diff::EQUAL, "BBB EEE"),
        ), $this->d->getChanges());

        // Word boundaries.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "The c"),
            array(Diff::INSERT, "ow and the c"),
            array(Diff::EQUAL, "at."),
        ));
        $this->d->cleanupSemanticLossless();
        $this->assertEquals(array(
            array(Diff::EQUAL, "The "),
            array(Diff::INSERT, "cow and the "),
            array(Diff::EQUAL, "cat."),
        ), $this->d->getChanges());

        // Alphanumeric boundaries.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "The-c"),
            array(Diff::INSERT, "ow-and-the-c"),
            array(Diff::EQUAL, "at."),
        ));
        $this->d->cleanupSemanticLossless();
        $this->assertEquals(array(
            array(Diff::EQUAL, "The-"),
            array(Diff::INSERT, "cow-and-the-"),
            array(Diff::EQUAL, "cat."),
        ), $this->d->getChanges());

        // Hitting the start.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "a"),
            array(Diff::DELETE, "a"),
            array(Diff::EQUAL, "ax"),
        ));
        $this->d->cleanupSemanticLossless();
        $this->assertEquals(array(
            array(Diff::DELETE, "a"),
            array(Diff::EQUAL, "aax"),
        ), $this->d->getChanges());

        // Hitting the end.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "xa"),
            array(Diff::DELETE, "a"),
            array(Diff::EQUAL, "a"),
        ));
        $this->d->cleanupSemanticLossless();
        $this->assertEquals(array(
            array(Diff::EQUAL, "xaa"),
            array(Diff::DELETE, "a"),
        ), $this->d->getChanges());

        // Sentence boundaries.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "The xxx. The "),
            array(Diff::INSERT, "zzz. The "),
            array(Diff::EQUAL, "yyy."),
        ));
        $this->d->cleanupSemanticLossless();
        $this->assertEquals(array(
            array(Diff::EQUAL, "The xxx."),
            array(Diff::INSERT, " The zzz."),
            array(Diff::EQUAL, " The yyy."),
        ), $this->d->getChanges());
    }

    public function testCleanupSemantic()
    {
        // Cleanup semantically trivial equalities.
        // Null case.
        $this->d->setChanges(array());
        $this->d->cleanupSemantic();
        $this->assertEquals(array(), $this->d->getChanges());

        // No elimination #1.
        $this->d->setChanges(array(
            array(Diff::DELETE, "ab"),
            array(Diff::INSERT, "cd"),
            array(Diff::EQUAL, "12"),
            array(Diff::DELETE, "e"),
        ));
        $this->d->cleanupSemantic();
        $this->assertEquals(array(
            array(Diff::DELETE, "ab"),
            array(Diff::INSERT, "cd"),
            array(Diff::EQUAL, "12"),
            array(Diff::DELETE, "e"),
        ), $this->d->getChanges());

        // No elimination #2.
        $this->d->setChanges(array(
            array(Diff::DELETE, "abc"),
            array(Diff::INSERT, "ABC"),
            array(Diff::EQUAL, "1234"),
            array(Diff::DELETE, "wxyz"),
        ));
        $this->d->cleanupSemantic();
        $this->assertEquals(array(
            array(Diff::DELETE, "abc"),
            array(Diff::INSERT, "ABC"),
            array(Diff::EQUAL, "1234"),
            array(Diff::DELETE, "wxyz"),
        ), $this->d->getChanges());

        // Simple elimination.
        $this->d->setChanges(array(
            array(Diff::DELETE, "a"),
            array(Diff::EQUAL, "b"),
            array(Diff::DELETE, "c"),
        ));
        $this->d->cleanupSemantic();
        $this->assertEquals(array(
            array(Diff::DELETE, "abc"),
            array(Diff::INSERT, "b"),
        ), $this->d->getChanges());

        // Backpass elimination.
        $this->d->setChanges(array(
            array(Diff::DELETE, "ab"),
            array(Diff::EQUAL, "cd"),
            array(Diff::DELETE, "e"),
            array(Diff::EQUAL, "f"),
            array(Diff::INSERT, "g"),
        ));
        $this->d->cleanupSemantic();
        $this->assertEquals(array(
            array(Diff::DELETE, "abcdef"),
            array(Diff::INSERT, "cdfg"),
        ), $this->d->getChanges());

        // Multiple eliminations.
        $this->d->setChanges(array(
            array(Diff::INSERT, "1"),
            array(Diff::EQUAL, "A"),
            array(Diff::DELETE, "B"),
            array(Diff::INSERT, "2"),
            array(Diff::EQUAL, "_"),
            array(Diff::INSERT, "1"),
            array(Diff::EQUAL, "A"),
            array(Diff::DELETE, "B"),
            array(Diff::INSERT, "2"),
        ));
        $this->d->cleanupSemantic();
        $this->assertEquals(array(
            array(Diff::DELETE, "AB_AB"),
            array(Diff::INSERT, "1A2_1A2"),
        ), $this->d->getChanges());

        // Word boundaries.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "The c"),
            array(Diff::DELETE, "ow and the c"),
            array(Diff::EQUAL, "at."),
        ));
        $this->d->cleanupSemantic();
        $this->assertEquals(array(
            array(Diff::EQUAL, "The "),
            array(Diff::DELETE, "cow and the "),
            array(Diff::EQUAL, "cat."),
        ), $this->d->getChanges());

        // No overlap elimination.
        $this->d->setChanges(array(
            array(Diff::DELETE, "abcxx"),
            array(Diff::INSERT, "xxdef"),
        ));
        $this->d->cleanupSemantic();
        $this->assertEquals(array(
            array(Diff::DELETE, "abcxx"),
            array(Diff::INSERT, "xxdef"),
        ), $this->d->getChanges());

        // Overlap elimination.
        $this->d->setChanges(array(
            array(Diff::DELETE, "abcxxx"),
            array(Diff::INSERT, "xxxdef"),
        ));
        $this->d->cleanupSemantic();
        $this->assertEquals(array(
            array(Diff::DELETE, "abc"),
            array(Diff::EQUAL, "xxx"),
            array(Diff::INSERT, "def"),
        ), $this->d->getChanges());

        // Reverse overlap elimination.
        $this->d->setChanges(array(
            array(Diff::DELETE, "xxxabc"),
            array(Diff::INSERT, "defxxx"),
        ));
        $this->d->cleanupSemantic();
        $this->assertEquals(array(
            array(Diff::INSERT, "def"),
            array(Diff::EQUAL, "xxx"),
            array(Diff::DELETE, "abc"),
        ), $this->d->getChanges());

        // Two overlap eliminations.
        $this->d->setChanges(array(
            array(Diff::DELETE, "abcd1212"),
            array(Diff::INSERT, "1212efghi"),
            array(Diff::EQUAL, "----"),
            array(Diff::DELETE, "A3"),
            array(Diff::INSERT, "3BC"),
        ));
        $this->d->cleanupSemantic();
        $this->assertEquals(array(
            array(Diff::DELETE, "abcd"),
            array(Diff::EQUAL, "1212"),
            array(Diff::INSERT, "efghi"),
            array(Diff::EQUAL, "----"),
            array(Diff::DELETE, "A"),
            array(Diff::EQUAL, "3"),
            array(Diff::INSERT, "BC"),
        ), $this->d->getChanges());
    }

    public function testCleanupEfficiency()
    {
        // Cleanup operationally trivial equalities.
        $this->d->setEditCost(4);

        // Null case.
        $this->d->setChanges(array());
        $this->d->cleanupEfficiency();
        $this->assertEquals(array(), $this->d->getChanges());

        // No elimination.
        $this->d->setChanges(array(
            array(Diff::DELETE, "ab"),
            array(Diff::INSERT, "12"),
            array(Diff::EQUAL, "wxyz"),
            array(Diff::DELETE, "cd"),
            array(Diff::INSERT, "34"),
        ));
        $this->d->cleanupEfficiency();
        $this->assertEquals(array(
            array(Diff::DELETE, "ab"),
            array(Diff::INSERT, "12"),
            array(Diff::EQUAL, "wxyz"),
            array(Diff::DELETE, "cd"),
            array(Diff::INSERT, "34"),
        ), $this->d->getChanges());

        // Four-edit elimination.
        $this->d->setChanges(array(
            array(Diff::DELETE, "ab"),
            array(Diff::INSERT, "12"),
            array(Diff::EQUAL, "xyz"),
            array(Diff::DELETE, "cd"),
            array(Diff::INSERT, "34"),
        ));
        $this->d->cleanupEfficiency();
        $this->assertEquals(array(
            array(Diff::DELETE, "abxyzcd"),
            array(Diff::INSERT, "12xyz34"),
        ), $this->d->getChanges());

        // Three-edit elimination.
        $this->d->setChanges(array(
            array(Diff::INSERT, "12"),
            array(Diff::EQUAL, "x"),
            array(Diff::DELETE, "cd"),
            array(Diff::INSERT, "34"),
        ));
        $this->d->cleanupEfficiency();
        $this->assertEquals(array(
            array(Diff::DELETE, "xcd"),
            array(Diff::INSERT, "12x34"),
        ), $this->d->getChanges());

        // Backpass elimination.
        $this->d->setChanges(array(
            array(Diff::DELETE, "ab"),
            array(Diff::INSERT, "12"),
            array(Diff::EQUAL, "xy"),
            array(Diff::INSERT, "34"),
            array(Diff::EQUAL, "z"),
            array(Diff::DELETE, "cd"),
            array(Diff::INSERT, "56"),
        ));
        $this->d->cleanupEfficiency();
        $this->assertEquals(array(
            array(Diff::DELETE, "abxyzcd"),
            array(Diff::INSERT, "12xy34z56"),
        ), $this->d->getChanges());

        // High cost elimination.
        $this->d->setEditCost(5);
        $this->d->setChanges(array(
            array(Diff::DELETE, "ab"),
            array(Diff::INSERT, "12"),
            array(Diff::EQUAL, "wxyz"),
            array(Diff::DELETE, "cd"),
            array(Diff::INSERT, "34"),
        ));
        $this->d->cleanupEfficiency();
        $this->assertEquals(array(
            array(Diff::DELETE, "abwxyzcd"),
            array(Diff::INSERT, "12wxyz34"),
        ), $this->d->getChanges());
        $this->d->setEditCost(4);
    }

    public function testPrettyHtml(){
        // Pretty print.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "a\n"),
            array(Diff::DELETE, "<B>b</B>"),
            array(Diff::INSERT, "c&d"),
        ));
        $this->assertEquals(
            '<span>a&para;<br></span><del style="background:#ffe6e6;">&lt;B&gt;b&lt;/B&gt;</del><ins style="background:#e6ffe6;">c&amp;d</ins>',
            $this->d->prettyHtml()
        );
    }

    public function testText(){
        // Compute the source and destination texts.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "jump"),
            array(Diff::DELETE, "s"),
            array(Diff::INSERT, "ed"),
            array(Diff::EQUAL, " over "),
            array(Diff::DELETE, "the"),
            array(Diff::INSERT, "a"),
            array(Diff::EQUAL, " lazy"),
        ));
        $this->assertEquals(
            "jumps over the lazy",
            $this->d->text1()
        );
        $this->assertEquals(
            "jumped over a lazy",
            $this->d->text2()
        );
    }

    public function testDelta()
    {
        // Convert a diff into delta string.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "jump"),
            array(Diff::DELETE, "s"),
            array(Diff::INSERT, "ed"),
            array(Diff::EQUAL, " over "),
            array(Diff::DELETE, "the"),
            array(Diff::INSERT, "a"),
            array(Diff::EQUAL, " lazy"),
            array(Diff::INSERT, "old dog"),
        ));
        $text1 = $this->d->text1();
        $this->assertEquals("jumps over the lazy", $text1);

        $delta = $this->d->toDelta();
        $this->assertEquals("=4\t-1\t+ed\t=6\t-3\t+a\t=5\t+old dog", $delta);

        // Convert delta string into a diff.
        $this->assertEquals($this->d->getChanges(), $this->d->fromDelta($text1, $delta)->getChanges());

        // Generates error (19 != 20).
        try {
            $this->d->fromDelta($text1 . 'x', $delta);
            $this->fail();
        } catch (\InvalidArgumentException $e) {
        }

        // Generates error (19 != 18).
        try {
            $this->d->fromDelta(mb_substr($text1, 1), $delta);
            $this->fail();
        } catch (\InvalidArgumentException $e) {
        }

        // Test deltas with special characters.
        $this->d->setChanges(array(
            array(Diff::EQUAL, Utils::unicodeChr(0x0680) . " \x00 \t %"),
            array(Diff::DELETE, Utils::unicodeChr(0x0681) . " \x01 \n ^"),
            array(Diff::INSERT, Utils::unicodeChr(0x0682) . " \x02 \\ |"),
        ));

        $text1 = $this->d->text1();
        $this->assertEquals(Utils::unicodeChr(0x0680) . " \x00 \t %" . Utils::unicodeChr(0x0681) . " \x01 \n ^", $text1);

        $delta = $this->d->toDelta();
        $this->assertEquals("=7\t-7\t+%DA%82 %02 %5C %7C", $delta);

        // Convert delta string into a diff.
        $this->assertEquals($this->d->getChanges(), $this->d->fromDelta($text1, $delta)->getChanges());

        // Verify pool of unchanged characters.
        $this->d->setChanges(array(
            array(Diff::INSERT, "A-Z a-z 0-9 - _ . ! ~ * ' ( ) ; / ? : @ & = + $ , # "),
        ));

        $text2 = $this->d->text2();
        $this->assertEquals("A-Z a-z 0-9 - _ . ! ~ * ' ( ) ; / ? : @ & = + $ , # ", $text2);

        $delta = $this->d->toDelta();
        $this->assertEquals("+A-Z a-z 0-9 - _ . ! ~ * ' ( ) ; / ? : @ & = + $ , # ", $delta);

        // Convert delta string into a diff.
        $this->assertEquals($this->d->getChanges(), $this->d->fromDelta("", $delta)->getChanges());
    }

    public function testXIndex()
    {
        // Translate a location in text1 to text2.
        $this->d->setChanges(array(
            array(Diff::DELETE, "a"),
            array(Diff::INSERT, "1234"),
            array(Diff::EQUAL, "xyz"),
        ));
        $this->assertEquals(5, $this->d->xIndex(2));

        // Translation on deletion.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "a"),
            array(Diff::DELETE, "1234"),
            array(Diff::EQUAL, "xyz"),
        ));
        $this->assertEquals(1, $this->d->xIndex(3));
    }

    public function testLevenshtein()
    {
        // Levenshtein with trailing equality.
        $this->d->setChanges(array(
            array(Diff::DELETE, "abc"),
            array(Diff::INSERT, "1234"),
            array(Diff::EQUAL, "xyz"),
        ));
        $this->assertEquals(4, $this->d->levenshtein());

        // Levenshtein with leading equality.
        $this->d->setChanges(array(
            array(Diff::EQUAL, "xyz"),
            array(Diff::DELETE, "abc"),
            array(Diff::INSERT, "1234"),
        ));
        $this->assertEquals(4, $this->d->levenshtein());

        // Levenshtein with middle equality.
        $this->d->setChanges(array(
            array(Diff::DELETE, "abc"),
            array(Diff::EQUAL, "xyz"),
            array(Diff::INSERT, "1234"),
        ));
        $this->assertEquals(7, $this->d->levenshtein());
    }

    public function testBisect()
    {
        $method = new \ReflectionMethod('DiffMatchPatch\Diff', 'bisect');
        $method->setAccessible(true);

        // Since the resulting diff hasn't been normalized, it would be ok if
        // the insertion and deletion pairs are swapped.
        // If the order changes, tweak this test as required.
        $this->assertEquals(array(
            array(Diff::DELETE, "c"),
            array(Diff::INSERT, "m"),
            array(Diff::EQUAL, "a"),
            array(Diff::DELETE, "t"),
            array(Diff::INSERT, "p"),
        ), $method->invoke($this->d, 'cat', 'map', PHP_INT_MAX));

        // Timeout.
        $this->assertEquals(array(
            array(Diff::DELETE, "cat"),
            array(Diff::INSERT, "map"),
        ), $method->invoke($this->d, 'cat', 'map', 0));

    }

    public function testMain()
    {
        // Perform a trivial diff.
        // Null case.
        $this->assertEquals(array(), $this->d->main("", "", false)->getChanges());

        // Equality.
        $this->assertEquals(
            array(
                array(Diff::EQUAL, "abc"),
            ),
            $this->d->main("abc", "abc", false)->getChanges()
        );

        // Check '0' strings
        $this->assertEquals(
            array(
                array(Diff::EQUAL, "0"),
                array(Diff::INSERT, "X"),
                array(Diff::EQUAL, "12"),
                array(Diff::INSERT, "X"),
                array(Diff::EQUAL, "0"),
                array(Diff::INSERT, "X"),
                array(Diff::EQUAL, "34"),
                array(Diff::INSERT, "X"),
                array(Diff::EQUAL, "0"),
            ),
            $this->d->main("0120340", "0X12X0X34X0", false)->getChanges()
        );

        $this->assertEquals(
            array(
                array(Diff::EQUAL, "0"),
                array(Diff::DELETE, "X"),
                array(Diff::EQUAL, "12"),
                array(Diff::DELETE, "X"),
                array(Diff::EQUAL, "0"),
                array(Diff::DELETE, "X"),
                array(Diff::EQUAL, "34"),
                array(Diff::DELETE, "X"),
                array(Diff::EQUAL, "0"),
            ),
            $this->d->main("0X12X0X34X0", "0120340", false)->getChanges()
        );


        $this->assertEquals(
            array(
                array(Diff::DELETE, "Apple"),
                array(Diff::INSERT, "Banana"),
                array(Diff::EQUAL, "s are a"),
                array(Diff::INSERT, "lso"),
                array(Diff::EQUAL, " fruit."),
            ),
            $this->d->main("Apples are a fruit.", "Bananas are also fruit.", false)->getChanges()
        );

        $this->assertEquals(
            array(
                array(Diff::DELETE, "a"),
                array(Diff::INSERT, Utils::unicodeChr(0x0680)),
                array(Diff::EQUAL, "x"),
                array(Diff::DELETE, "\t"),
                array(Diff::INSERT, "\x00"),
            ),
            $this->d->main("ax\t", Utils::unicodeChr(0x0680) . "x\x00", false)->getChanges()
        );

        // Overlaps.
        $this->assertEquals(
            array(
                array(Diff::DELETE, "1"),
                array(Diff::EQUAL, "a"),
                array(Diff::DELETE, "y"),
                array(Diff::EQUAL, "b"),
                array(Diff::DELETE, "2"),
                array(Diff::INSERT, "xab"),
            ),
            $this->d->main("1ayb2", "abxab", false)->getChanges()
        );

        $this->assertEquals(
            array(
                array(Diff::INSERT, "xaxcx"),
                array(Diff::EQUAL, "abc"),
                array(Diff::DELETE, "y"),
            ),
            $this->d->main("abcy", "xaxcxabc", false)->getChanges()
        );

        $this->assertEquals(
            array(
                array(Diff::DELETE, "ABCD"),
                array(Diff::EQUAL, "a"),
                array(Diff::DELETE, "="),
                array(Diff::INSERT, "-"),
                array(Diff::EQUAL, "bcd"),
                array(Diff::DELETE, "="),
                array(Diff::INSERT, "-"),
                array(Diff::EQUAL, "efghijklmnopqrs"),
                array(Diff::DELETE, "EFGHIJKLMNOefg"),
            ),
            $this->d->main("ABCDa=bcd=efghijklmnopqrsEFGHIJKLMNOefg", "a-bcd-efghijklmnopqrs", false)->getChanges()
        );

        // Large equality.
        $this->assertEquals(
            array(
                array(Diff::INSERT, " "),
                array(Diff::EQUAL, "a"),
                array(Diff::INSERT, "nd"),
                array(Diff::EQUAL, " [[Pennsylvania]]"),
                array(Diff::DELETE, " and [[New"),
            ),
            $this->d->main("a [[Pennsylvania]] and [[New", " and [[Pennsylvania]]", false)->getChanges()
        );

        // Timeout.
        // 100ms
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

        // Test that we took at least the timeout period.
        $this->assertGreaterThanOrEqual($this->d->getTimeout(), $endTime - $startTime);

        // Test that we didn't take forever (be forgiving).
        // Theoretically this test could fail very occasionally if the
        // OS task swaps or locks up for a second at the wrong moment.
        // TODO must be $this->d->getTimeout() * 2, but it need some optimization of linesToCharsMunge()
        $this->assertLessThan($this->d->getTimeout() * 15, $endTime - $startTime);
        $this->d->setTimeout(0);

        // Test the linemode speedup.
        // Must be long to pass the 100 char cutoff.
        // Simple line-mode.
        $a = str_repeat("1234567890\n", 13);
        $b = str_repeat("abcdefghij\n", 13);
        $this->assertEquals(
            $this->d->main($a, $b, false)->getChanges(),
            $this->d->main($a, $b, true)->getChanges()
        );

        // Single line-mode.
        $a = str_repeat("1234567890", 13);
        $b = str_repeat("abcdefghij", 13);
        $this->assertEquals(
            $this->d->main($a, $b, false)->getChanges(),
            $this->d->main($a, $b, true)->getChanges()
        );

        function rebuildtexts($diffs) {
            // Construct the two texts which made up the diff originally.
            $text1 = "";
            $text2 = "";
            foreach ($diffs as $change) {
                if ($change[0] != Diff::INSERT) {
                    $text1 .= $change[1];
                }
                if ($change[0] != Diff::DELETE) {
                    $text2 .= $change[1];
                }
            }
            return array($text1, $text2);
        }
        // Overlap line-mode.
        $a = str_repeat("1234567890\n", 13);
        $b = "abcdefghij\n1234567890\n1234567890\n1234567890\nabcdefghij\n1234567890\n1234567890\n1234567890\nabcdefghij\n1234567890\n1234567890\n1234567890\nabcdefghij\n";
        $this->assertEquals(
            rebuildtexts($this->d->main($a, $b, false)->getChanges()),
            rebuildtexts($this->d->main($a, $b, true)->getChanges())
        );

        // Test null inputs.
        try {
            $this->d->main(null, null);
            $this->fail();
        } catch (\InvalidArgumentException $e) {
        }
    }

}
