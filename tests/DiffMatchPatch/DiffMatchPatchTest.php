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
 * DiffMatchPatchTest tests that all methods successfully proxies.
 * DiffTest, MatchTest and PatchTest contains all other unit tests.
 *
 * @package DiffMatchPatch
 * @author Neil Fraser <fraser@google.com>
 * @author Daniil Skrobov <yetanotherape@gmail.com>
 */
class DiffMatchPatchTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var DiffMatchPatch
     */
    protected $dmp;

    protected  function setUp() {
        mb_internal_encoding('UTF-8');

        $this->dmp = new DiffMatchPatch();
    }

    public function testProperties()
    {
        $this->dmp->Diff_Timeout = 13.1;
        $this->assertEquals(13.1, $this->dmp->Diff_Timeout);

        $this->dmp->Diff_EditCost = 13;
        $this->assertEquals(13, $this->dmp->Diff_EditCost);

        $this->dmp->Match_Threshold = 13.1;
        $this->assertEquals(13.1, $this->dmp->Match_Threshold);

        $this->dmp->Match_Distance = 13;
        $this->assertEquals(13, $this->dmp->Match_Distance);

        $this->dmp->Match_MaxBits = 13;
        $this->assertEquals(13, $this->dmp->Match_MaxBits);

        $this->dmp->Patch_DeleteThreshold = 13.1;
        $this->assertEquals(13.1, $this->dmp->Patch_DeleteThreshold);

        $this->dmp->Patch_Margin = 13;
        $this->assertEquals(13, $this->dmp->Patch_Margin);
    }

    public function testDiffMain()
    {
        $this->assertEquals(
            array(
                array(DiffMatchPatch::DIFF_DELETE, "Apple"),
                array(DiffMatchPatch::DIFF_INSERT, "Banana"),
                array(DiffMatchPatch::DIFF_EQUAL, "s are a"),
                array(DiffMatchPatch::DIFF_INSERT, "lso"),
                array(DiffMatchPatch::DIFF_EQUAL, " fruit."),
            ),
            $this->dmp->diff_main("Apples are a fruit.", "Bananas are also fruit.", false)
        );
    }

    public function testDiffCleanupSemantic()
    {
        $diffs = array(
            array(DiffMatchPatch::DIFF_INSERT, "1"),
            array(DiffMatchPatch::DIFF_EQUAL, "A"),
            array(DiffMatchPatch::DIFF_DELETE, "B"),
            array(DiffMatchPatch::DIFF_INSERT, "2"),
            array(DiffMatchPatch::DIFF_EQUAL, "_"),
            array(DiffMatchPatch::DIFF_INSERT, "1"),
            array(DiffMatchPatch::DIFF_EQUAL, "A"),
            array(DiffMatchPatch::DIFF_DELETE, "B"),
            array(DiffMatchPatch::DIFF_INSERT, "2"),
        );
        $this->dmp->diff_cleanupSemantic($diffs);
        $this->assertEquals(array(
            array(DiffMatchPatch::DIFF_DELETE, "AB_AB"),
            array(DiffMatchPatch::DIFF_INSERT, "1A2_1A2"),
        ), $diffs);
    }

    public function testDiffCleanupEfficiency()
    {
        $diffs = array(
            array(DiffMatchPatch::DIFF_DELETE, "ab"),
            array(DiffMatchPatch::DIFF_INSERT, "12"),
            array(DiffMatchPatch::DIFF_EQUAL, "xyz"),
            array(DiffMatchPatch::DIFF_DELETE, "cd"),
            array(DiffMatchPatch::DIFF_INSERT, "34"),
        );
        $this->dmp->diff_cleanupEfficiency($diffs);
        $this->assertEquals(array(
            array(DiffMatchPatch::DIFF_DELETE, "abxyzcd"),
            array(DiffMatchPatch::DIFF_INSERT, "12xyz34"),
        ), $diffs);
    }

    public function testDiffLevenshtein()
    {
        $this->assertEquals(4, $this->dmp->diff_levenshtein(array(
            array(DiffMatchPatch::DIFF_EQUAL, "xyz"),
            array(DiffMatchPatch::DIFF_DELETE, "abc"),
            array(DiffMatchPatch::DIFF_INSERT, "1234"),
        )));
    }

    public function testDiffPrettyHtml()
    {
        $diffs = array(
            array(DiffMatchPatch::DIFF_EQUAL, "a\n"),
            array(DiffMatchPatch::DIFF_DELETE, "<B>b</B>"),
            array(DiffMatchPatch::DIFF_INSERT, "c&d"),
        );
        $this->assertEquals(
            '<span>a&para;<br></span><del style="background:#ffe6e6;">&lt;B&gt;b&lt;/B&gt;</del><ins style="background:#e6ffe6;">c&amp;d</ins>',
            $this->dmp->diff_prettyHtml($diffs)
        );
    }

    public function testMatchMain()
    {
        $this->assertEquals(3, $this->dmp->match_main("abcdef", "defy", 4));
    }

    public function testPatchFromText()
    {
        $text = "@@ -21,18 +22,17 @@\n jump\n-s\n+ed\n  over \n-the\n+a\n %0Alaz\n";
        $patches = $this->dmp->patch_fromText($text);
        $this->assertEquals($text, (string)$patches[0]);
    }

    public function testPatchToText()
    {
        $text = "@@ -21,18 +22,17 @@\n jump\n-s\n+ed\n  over \n-the\n+a\n  laz\n";
        $this->assertEquals($text, $this->dmp->patch_toText($this->dmp->patch_fromText($text)));
    }

    public function testPatchMake()
    {
        $text1 = "The quick brown fox jumps over the lazy dog.";
        $text2 = "That quick brown fox jumped over a lazy dog.";
        $expected = "@@ -1,8 +1,7 @@\n Th\n-at\n+e\n  qui\n@@ -21,17 +21,18 @@\n jump\n-ed\n+s\n  over \n-a\n+the\n  laz\n";
        $patches = $this->dmp->patch_make($text2, $text1);
        $this->assertEquals($expected, $this->dmp->patch_toText($patches));
    }

    public function testPatchApply()
    {
        $patches = $this->dmp->patch_make("The quick brown fox jumps over the lazy dog.", "That quick brown fox jumped over a lazy dog.");
        $this->assertEquals(
            array("That quick red rabbit jumped over a tired tiger.", array(true, true,)),
            $this->dmp->patch_apply($patches, "The quick red rabbit jumps over the tired tiger.")
        );
    }

}
