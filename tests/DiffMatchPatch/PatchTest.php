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
class PatchTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Diff
     */
    protected $d;
    /**
     * @var Patch
     */
    protected $p;
    /**
     * @var Match
     */
    protected $m;

    protected  function setUp() {
        mb_internal_encoding('UTF-8');

        $this->d = new Diff();
        $this->m = new Match();
        // Assumes that Match->maxBits is 32.
        $this->m->setMaxBits(32);
        $this->p = new Patch($this->d, $this->m);
    }

    public function testPatchObj()
    {
        $p = new PatchObject();
        $p->setStart1(20);
        $p->setStart2(21);
        $p->setLength1(18);
        $p->setLength2(17);
        $p->setChanges(array(
           array(Diff::EQUAL, "jump"),
           array(Diff::DELETE, "s"),
           array(Diff::INSERT, "ed"),
           array(Diff::EQUAL, " over "),
           array(Diff::DELETE, "the"),
           array(Diff::INSERT, "a"),
           array(Diff::EQUAL, "\nlaz"),
        ));
        $this->assertEquals("@@ -21,18 +22,17 @@\n jump\n-s\n+ed\n  over \n-the\n+a\n %0Alaz\n", (string)$p);
    }

    public function testFromText()
    {
        $this->assertEquals(array(), $this->p->fromText(""));

        $text = "@@ -21,18 +22,17 @@\n jump\n-s\n+ed\n  over \n-the\n+a\n %0Alaz\n";
        $patches = $this->p->fromText($text);
        $this->assertEquals($text, (string)$patches[0]);

        $text = "@@ -1 +1 @@\n-a\n+b\n";
        $patches = $this->p->fromText($text);
        $this->assertEquals($text, (string)$patches[0]);

        $text = "@@ -1,3 +0,0 @@\n-abc\n";
        $patches = $this->p->fromText($text);
        $this->assertEquals($text, (string)$patches[0]);

        $text = "@@ -0,0 +1,3 @@\n+abc\n";
        $patches = $this->p->fromText($text);
        $this->assertEquals($text, (string)$patches[0]);

        try {
            $this->p->fromText("Bad\nPatch\n");
            $this->fail();
        } catch (\InvalidArgumentException $e) {

        }
    }

    public function testToText()
    {
        $text = "@@ -21,18 +22,17 @@\n jump\n-s\n+ed\n  over \n-the\n+a\n  laz\n";
        $this->assertEquals($text, $this->p->toText($this->p->fromText($text)));

        $text = "@@ -1,9 +1,9 @@\n-f\n+F\n oo+fooba\n@@ -7,9 +7,9 @@\n obar\n-,\n+.\n tes\n";
        $this->assertEquals($text, $this->p->toText($this->p->fromText($text)));
    }

    public function testAddContext()
    {
        $this->p->setMargin(4);
        $patches = $this->p->fromText("@@ -21,4 +21,10 @@\n-jump\n+somersault\n");
        $this->p->addContext($patches[0], "The quick brown fox jumps over the lazy dog.");
        $this->assertEquals(
            "@@ -17,12 +17,18 @@\n fox \n-jump\n+somersault\n s ov\n",
            (string)$patches[0]
        );

        // Same, but not enough trailing context.
        $patches = $this->p->fromText("@@ -21,4 +21,10 @@\n-jump\n+somersault\n");
        $this->p->addContext($patches[0], "The quick brown fox jumps.");
        $this->assertEquals(
            "@@ -17,10 +17,16 @@\n fox \n-jump\n+somersault\n s.\n",
            (string)$patches[0]
        );

        // Same, but not enough leading context.
        $patches = $this->p->fromText("@@ -3 +3,2 @@\n-e\n+at\n");
        $this->p->addContext($patches[0], "The quick brown fox jumps.");
        $this->assertEquals(
            "@@ -1,7 +1,8 @@\n Th\n-e\n+at\n  qui\n",
            (string)$patches[0]
        );

        // Same, but with ambiguity.
        $patches = $this->p->fromText("@@ -3 +3,2 @@\n-e\n+at\n");
        $this->p->addContext($patches[0], "The quick brown fox jumps.  The quick brown fox crashes.");
        $this->assertEquals(
            "@@ -1,27 +1,28 @@\n Th\n-e\n+at\n  quick brown fox jumps. \n",
            (string)$patches[0]
        );
    }

    public function testMake()
    {
        // Null case.
        $patches = $this->p->make("", "");
        $this->assertEquals("", $this->p->toText($patches));

        $text1 = "The quick brown fox jumps over the lazy dog.";
        $text2 = "That quick brown fox jumped over a lazy dog.";

        // Text2 + Text1 inputs.
        // The second patch must be "-21,17 +21,18", not "-22,17 +21,18" due to rolling context.
        $expected = "@@ -1,8 +1,7 @@\n Th\n-at\n+e\n  qui\n@@ -21,17 +21,18 @@\n jump\n-ed\n+s\n  over \n-a\n+the\n  laz\n";
        $patches = $this->p->make($text2, $text1);
        $this->assertEquals($expected, $this->p->toText($patches));

        // Text1 + Text2 inputs.
        $expected = "@@ -1,11 +1,12 @@\n Th\n-e\n+at\n  quick b\n@@ -22,18 +22,17 @@\n jump\n-s\n+ed\n  over \n-the\n+a\n  laz\n";
        $patches = $this->p->make($text1, $text2);
        $this->assertEquals($expected, $this->p->toText($patches));

        // Diff input.
        $diffs = $this->d->main($text1, $text2, false)->getChanges();
        $patches = $this->p->make($diffs);
        $this->assertEquals($expected, $this->p->toText($patches));

        // Text1+Diff inputs.
        $patches = $this->p->make($text1, $diffs);
        $this->assertEquals($expected, $this->p->toText($patches));

        // Text1+Text2+Diff inputs (deprecated).
        $patches = $this->p->make($text1, $text2, $diffs);
        $this->assertEquals($expected, $this->p->toText($patches));

        // Character encoding.
        $patches = $this->p->make("`1234567890-=[]\\;',./", "~!@#$%^&*()_+{}|:\"<>?");
        $this->assertEquals(
            "@@ -1,21 +1,21 @@\n-%601234567890-=%5B%5D%5C;',./\n+~!@#$%25%5E&*()_+%7B%7D%7C:%22%3C%3E?\n",
            $this->p->toText($patches)
        );

        // Character decoding.
        $diffs = array(
            array(Diff::DELETE, "`1234567890-=[]\\;',./"),
            array(Diff::INSERT, "~!@#$%^&*()_+{}|:\"<>?"),
        );
        $patches = $this->p->fromText("@@ -1,21 +1,21 @@\n-%601234567890-=%5B%5D%5C;',./\n+~!@#$%25%5E&*()_+%7B%7D%7C:%22%3C%3E?\n");
        $this->assertEquals($diffs, $patches[0]->getChanges());

        // Long string with repeats.
        $text1 = "";
        for($i = 0; $i < 100; $i++) {
            $text1 .= "abcdef";
        }
        $text2 = $text1 . "123";
        $expected = "@@ -573,28 +573,31 @@\n cdefabcdefabcdefabcdefabcdef\n+123\n";
        $patches = $this->p->make($text1, $text2);
        $this->assertEquals($expected, $this->p->toText($patches));

        // Test null inputs.
        try {
            $this->p->make(null, null);
            $this->fail();
        } catch (\InvalidArgumentException $e) {

        }
    }

    public function testSplitMax()
    {
        $patches = $this->p->make("abcdefghijklmnopqrstuvwxyz01234567890", "XabXcdXefXghXijXklXmnXopXqrXstXuvXwxXyzX01X23X45X67X89X0");
        $this->p->splitMax($patches);
        $this->assertEquals(
            "@@ -1,32 +1,46 @@\n+X\n ab\n+X\n cd\n+X\n ef\n+X\n gh\n+X\n ij\n+X\n kl\n+X\n mn\n+X\n op\n+X\n qr\n+X\n st\n+X\n uv\n+X\n wx\n+X\n yz\n+X\n 012345\n@@ -25,13 +39,18 @@\n zX01\n+X\n 23\n+X\n 45\n+X\n 67\n+X\n 89\n+X\n 0\n",
            $this->p->toText($patches)
        );

        $patches = $this->p->make("abcdef1234567890123456789012345678901234567890123456789012345678901234567890uvwxyz", "abcdefuvwxyz");
        $oldPathesText = $this->p->toText($patches);
        $this->p->splitMax($patches);
        $this->assertEquals(
            $oldPathesText,
            $this->p->toText($patches)
        );

        $patches = $this->p->make("1234567890123456789012345678901234567890123456789012345678901234567890", "abc");
        $this->p->splitMax($patches);
        $this->assertEquals(
            "@@ -1,32 +1,4 @@\n-1234567890123456789012345678\n 9012\n@@ -29,32 +1,4 @@\n-9012345678901234567890123456\n 7890\n@@ -57,14 +1,3 @@\n-78901234567890\n+abc\n",
            $this->p->toText($patches)
        );

        $patches = $this->p->make("abcdefghij , h : 0 , t : 1 abcdefghij , h : 0 , t : 1 abcdefghij , h : 0 , t : 1", "abcdefghij , h : 1 , t : 1 abcdefghij , h : 1 , t : 1 abcdefghij , h : 0 , t : 1");
        $this->p->splitMax($patches);
        $this->assertEquals(
            "@@ -2,32 +2,32 @@\n bcdefghij , h : \n-0\n+1\n  , t : 1 abcdef\n@@ -29,32 +29,32 @@\n bcdefghij , h : \n-0\n+1\n  , t : 1 abcdef\n",
            $this->p->toText($patches)
        );
    }

    public function testAddPadding()
    {
        // Both edges full.
        $patches = $this->p->make("", "test");
        $this->assertEquals("@@ -0,0 +1,4 @@\n+test\n", $this->p->toText($patches));
        $this->p->addPadding($patches);
        $this->assertEquals(
            "@@ -1,8 +1,12 @@\n %01%02%03%04\n+test\n %01%02%03%04\n",
            $this->p->toText($patches)
        );

        // Both edges partial.
        $patches = $this->p->make("XY", "XtestY");
        $this->assertEquals("@@ -1,2 +1,6 @@\n X\n+test\n Y\n", $this->p->toText($patches));
        $this->p->addPadding($patches);
        $this->assertEquals(
            "@@ -2,8 +2,12 @@\n %02%03%04X\n+test\n Y%01%02%03\n",
            $this->p->toText($patches)
        );

        // Both edges none.
        $patches = $this->p->make("XXXXYYYY", "XXXXtestYYYY");
        $this->assertEquals("@@ -1,8 +1,12 @@\n XXXX\n+test\n YYYY\n", $this->p->toText($patches));
        $this->p->addPadding($patches);
        $this->assertEquals(
            "@@ -5,8 +5,12 @@\n XXXX\n+test\n YYYY\n",
            $this->p->toText($patches)
        );
    }

    public function testApply()
    {
        $this->m->setDistance(1000);
        $this->m->setThreshold(0.5);
        $this->p->setDeleteTreshold(0.5);

        // Null case.
        $patches = $this->p->make("", "");
        $this->assertEquals(
            array("Hello world.", array()),
            $this->p->apply($patches, "Hello world.")
        );

        // Exact match.
        $patches = $this->p->make("The quick brown fox jumps over the lazy dog.", "That quick brown fox jumped over a lazy dog.");
        $this->assertEquals(
            array("That quick brown fox jumped over a lazy dog.", array(true, true,)),
            $this->p->apply($patches, "The quick brown fox jumps over the lazy dog.")
        );

        // Partial match.
        $this->assertEquals(
            array("That quick red rabbit jumped over a tired tiger.", array(true, true,)),
            $this->p->apply($patches, "The quick red rabbit jumps over the tired tiger.")
        );

        // Failed match.
        $this->assertEquals(
            array("I am the very model of a modern major general.", array(false, false,)),
            $this->p->apply($patches, "I am the very model of a modern major general.")
        );

        // Big delete, small change.
        $patches = $this->p->make("x1234567890123456789012345678901234567890123456789012345678901234567890y", "xabcy");
        $this->assertEquals(
            array("xabcy", array(true, true,)),
            $this->p->apply($patches, "x123456789012345678901234567890-----++++++++++-----123456789012345678901234567890y")
        );

        // Big delete, big change 1.
        $patches = $this->p->make("x1234567890123456789012345678901234567890123456789012345678901234567890y", "xabcy");
        $this->assertEquals(
            array("xabc12345678901234567890---------------++++++++++---------------12345678901234567890y", array(false, true,)),
            $this->p->apply($patches, "x12345678901234567890---------------++++++++++---------------12345678901234567890y")
        );

        // Big delete, big change 2.
        $this->p->setDeleteTreshold(0.6);
        $patches = $this->p->make("x1234567890123456789012345678901234567890123456789012345678901234567890y", "xabcy");
        $this->assertEquals(
            array("xabcy", array(true, true,)),
            $this->p->apply($patches, "x12345678901234567890---------------++++++++++---------------12345678901234567890y")
        );
        $this->p->setDeleteTreshold(0.5);

        // Compensate for failed patch.
        $this->m->setDistance(0);
        $this->m->setThreshold(0.0);
        $patches = $this->p->make("abcdefghijklmnopqrstuvwxyz--------------------1234567890", "abcXXXXXXXXXXdefghijklmnopqrstuvwxyz--------------------1234567YYYYYYYYYY890");
        $this->assertEquals(
            array("ABCDEFGHIJKLMNOPQRSTUVWXYZ--------------------1234567YYYYYYYYYY890", array(false, true,)),
            $this->p->apply($patches, "ABCDEFGHIJKLMNOPQRSTUVWXYZ--------------------1234567890")
        );
        $this->m->setDistance(1000);
        $this->m->setThreshold(0.5);

        // No side effects.
        $patches = $this->p->make("", "test");
        $patchesText = $this->p->toText($patches);
        $this->p->apply($patches, "");
        $this->assertEquals(
            $patchesText,
            $this->p->toText($patches)
        );

        // No side effects with major delete.
        $patches = $this->p->make("The quick brown fox jumps over the lazy dog.", "Woof");
        $patchesText = $this->p->toText($patches);
        $this->p->apply($patches, "The quick brown fox jumps over the lazy dog.");
        $this->assertEquals(
            $patchesText,
            $this->p->toText($patches)
        );

        // Edge exact match.
        $patches = $this->p->make("", "test");
        $this->assertEquals(
            array("test", array(true,)),
            $this->p->apply($patches, "")
        );

        // Near edge exact match.
        $patches = $this->p->make("XY", "XtestY");
        $this->assertEquals(
            array("XtestY", array(true,)),
            $this->p->apply($patches, "XY")
        );

        // Edge partial match.
        $patches = $this->p->make("y", "y123");
        $this->assertEquals(
            array("x123", array(true,)),
            $this->p->apply($patches, "x")
        );
    }
}
