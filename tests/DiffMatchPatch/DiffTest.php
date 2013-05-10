<?php

namespace DiffMatchPatch;

class DiffTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Diff
     */
    protected $d;

    protected  function setUp() {
        $this->d = new Diff();
    }

    public function testCommonPrefix()
    {
        // Detect any common prefix.
        // Null case.
        $this->assertEquals(0, $this->d->commonPrefix("abc", "xyz"));

        // Non-null case.
        $this->assertEquals(4, $this->d->commonPrefix("1234abcdef", "1234xyz"));

        // Whole case.
        $this->assertEquals(4, $this->d->commonPrefix("1234", "1234xyz"));
    }

    public function testCommonSuffix()
    {
        // Detect any common suffix.
        // Null case.
        $this->assertEquals(0, $this->d->commonSuffix("abc", "xyz"));

        // Non-null case.
        $this->assertEquals(4, $this->d->commonSuffix("abcdef1234", "xyz1234"));

        // Whole case.
        $this->assertEquals(4, $this->d->commonSuffix("1234", "xyz1234"));
    }

    public function testCommonOverlap()
    {
        # Null case.
        $this->assertEquals(0, $this->d->commontOverlap("", "abcd"));

        // Whole case.
        $this->assertEquals(3, $this->d->commontOverlap("abc", "abcd"));

        // No overlap.
        $this->assertEquals(0, $this->d->commontOverlap("123456", "abcd"));

        // Overlap.
        $this->assertEquals(3, $this->d->commontOverlap("123456xxx", "xxxabcd"));

        // Unicode.
        // Some overly clever languages (C#) may treat ligatures as equal to their
        // component letters.  E.g. U+FB01 == 'fi'
        $this->assertEquals(0, $this->d->commontOverlap("fi", json_decode('"\ufb01"')));
    }

    public function testHalfMatch()
    {
        // Detect a halfmatch.
        $this->d->setTimeout(1);

        // No match.
        $this->assertNull($this->d->halfMatch("1234567890", "abcdef"));
        $this->assertNull($this->d->halfMatch("12345", "23"));

        // Single Match.
        $this->assertEquals(array("12", "90", "a", "z", "345678"), $this->d->halfMatch("1234567890", "a345678z"));
        $this->assertEquals(array("a", "z", "12", "90", "345678"), $this->d->halfMatch("a345678z", "1234567890"));
        $this->assertEquals(array("abc", "z", "1234", "0", "56789"), $this->d->halfMatch("abc56789z", "1234567890"));
        $this->assertEquals(array("a", "xyz", "1", "7890", "23456"), $this->d->halfMatch("a23456xyz", "1234567890"));

        // Multiple Matches.
        $this->assertEquals(array("12123", "123121", "a", "z", "1234123451234"), $this->d->halfMatch("121231234123451234123121", "a1234123451234z"));
        $this->assertEquals(array("", "-=-=-=-=-=", "x", "", "x-=-=-=-=-=-=-="), $this->d->halfMatch("x-=-=-=-=-=-=-=-=-=-=-=-=", "xx-=-=-=-=-=-=-="));
        $this->assertEquals(array("-=-=-=-=-=", "", "", "y", "-=-=-=-=-=-=-=y"), $this->d->halfMatch("-=-=-=-=-=-=-=-=-=-=-=-=y", "-=-=-=-=-=-=-=yy"));


        // Non-optimal halfmatch.
        // Optimal diff would be -q+x=H-i+e=lloHe+Hu=llo-Hew+y not -qHillo+x=HelloHe-w+Hulloy
        $this->assertEquals(array("qHillo", "w", "x", "Hulloy", "HelloHe"), $this->d->halfMatch("qHilloHelloHew", "xHelloHeHulloy"));

        // Optimal no halfmatch.
        $this->d->setTimeout(0);
        $this->assertNull($this->d->halfMatch("qHilloHelloHew", "xHelloHeHulloy"));
    }

    public function testLinesToChars()
    {
        // Convert lines down to characters.
        $this->assertEquals(
            array("\x01\x02\x01", "\x02\x01\x02", array("", "alpha\n", "beta\n")),
            $this->d->linesToChars("alpha\nbeta\nalpha\n", "beta\nalpha\nbeta\n")
        );
        $this->assertEquals(
            array("", "\x01\x02\x03\x03", array("", "alpha\r\n", "beta\r\n", "\r\n")),
            $this->d->linesToChars("", "alpha\r\nbeta\r\n\r\n\r\n")
        );
        $this->assertEquals(
            array("\x01", "\x02", array("", "a", "b")),
            $this->d->linesToChars("a", "b")
        );

        // More than 256 to reveal any 8-bit limitations.
        $n = 300;
        $lineList = array();
        $charList = array();

        for ($x = 1; $x <= $n; $x++) {
            $lineList[] = $x . "\n";
            $charList[] = Utils::unicodeChr($x);
        }
        $this->assertCount($n, $lineList);

        $lines = implode('', $lineList);
        $chars = implode('', $charList);
        $this->assertEquals($n, mb_strlen($chars));

        array_unshift($lineList, "");
        $this->assertEquals(
            array($chars, "", $lineList),
            $this->d->linesToChars($lines, "")
        );
    }

    public function testCharsToLines()
    {
        // Convert chars up to lines.
        $diffs = array(
            array(Diff::EQUAL, "\x01\x02\x01"),
            array(Diff::INSERT, "\x02\x01\x02")
        );
        $this->d->charsToLines($diffs, array("", "alpha\n", "beta\n"));
        $this->assertEquals(array(
            array(Diff::EQUAL, "alpha\nbeta\nalpha\n"),
            array(Diff::INSERT, "beta\nalpha\nbeta\n")
        ), $diffs);

        // More than 256 to reveal any 8-bit limitations.
        $n = 300;
        $lineList = array();
        $charList = array();

        for ($x = 1; $x <= $n; $x++) {
            $lineList[] = $x . "\n";
            $charList[] = Utils::unicodeChr($x);
        }
        $this->assertCount($n, $lineList);

        $lines = implode('', $lineList);
        $chars = implode('', $charList);
        $this->assertEquals($n, mb_strlen($chars));

        array_unshift($lineList, "");
        $diffs = array(
            array(Diff::DELETE, $chars)
        );
        $this->d->charsToLines($diffs, $lineList);
        $this->assertEquals(array(
            array(Diff::DELETE, $lines),
        ), $diffs);
    }

    public function testCleanupMerge()
    {
        // Cleanup a messy diff.

        // Null case.
        $diffs = array();
        $this->d->cleanupMerge($diffs);
        $this->assertEquals(array(), $diffs);

        // No change case.
        $diffs = array(
            array(Diff::EQUAL, "a"),
            array(Diff::DELETE, "b"),
            array(Diff::INSERT, "c"),
        );
        $this->d->cleanupMerge($diffs);
        $this->assertEquals(array(
            array(Diff::EQUAL, "a"),
            array(Diff::DELETE, "b"),
            array(Diff::INSERT, "c"),
        ), $diffs);

        // Merge equalities.
        $diffs = array(
            array(Diff::EQUAL, "a"),
            array(Diff::EQUAL, "b"),
            array(Diff::EQUAL, "c"),
        );
        $this->d->cleanupMerge($diffs);
        $this->assertEquals(array(
            array(Diff::EQUAL, "abc"),
        ), $diffs);

        // Merge deletions.
        $diffs = array(
            array(Diff::DELETE, "a"),
            array(Diff::DELETE, "b"),
            array(Diff::DELETE, "c"),
        );
        $this->d->cleanupMerge($diffs);
        $this->assertEquals(array(
            array(Diff::DELETE, "abc"),
        ), $diffs);

        // Merge insertions.
        $diffs = array(
            array(Diff::INSERT, "a"),
            array(Diff::INSERT, "b"),
            array(Diff::INSERT, "c"),
        );
        $this->d->cleanupMerge($diffs);
        $this->assertEquals(array(
            array(Diff::INSERT, "abc"),
        ), $diffs);

        // Merge interweave.
        $diffs = array(
            array(Diff::DELETE, "a"),
            array(Diff::INSERT, "b"),
            array(Diff::DELETE, "c"),
            array(Diff::INSERT, "d"),
            array(Diff::EQUAL, "e"),
            array(Diff::EQUAL, "f"),
        );
        $this->d->cleanupMerge($diffs);
        $this->assertEquals(array(
            array(Diff::DELETE, "ac"),
            array(Diff::INSERT, "bd"),
            array(Diff::EQUAL, "ef"),
        ), $diffs);

        // Prefix and suffix detection.
        $diffs = array(
            array(Diff::DELETE, "a"),
            array(Diff::INSERT, "abc"),
            array(Diff::DELETE, "dc"),
        );
        $this->d->cleanupMerge($diffs);
        $this->assertEquals(array(
            array(Diff::EQUAL, "a"),
            array(Diff::DELETE, "d"),
            array(Diff::INSERT, "b"),
            array(Diff::EQUAL, "c"),
        ), $diffs);

        // Prefix and suffix detection with equalities.
        $diffs = array(
            array(Diff::EQUAL, "x"),
            array(Diff::DELETE, "a"),
            array(Diff::INSERT, "abc"),
            array(Diff::DELETE, "dc"),
            array(Diff::EQUAL, "y"),
        );
        $this->d->cleanupMerge($diffs);
        $this->assertEquals(array(
            array(Diff::EQUAL, "xa"),
            array(Diff::DELETE, "d"),
            array(Diff::INSERT, "b"),
            array(Diff::EQUAL, "cy"),
        ), $diffs);

        // Slide edit left.
        $diffs = array(
            array(Diff::EQUAL, "a"),
            array(Diff::INSERT, "ba"),
            array(Diff::EQUAL, "c"),
        );
        $this->d->cleanupMerge($diffs);
        $this->assertEquals(array(
            array(Diff::INSERT, "ab"),
            array(Diff::EQUAL, "ac"),
        ), $diffs);

        // Slide edit right.
        $diffs = array(
            array(Diff::EQUAL, "c"),
            array(Diff::INSERT, "ab"),
            array(Diff::EQUAL, "a"),
        );
        $this->d->cleanupMerge($diffs);
        $this->assertEquals(array(
            array(Diff::EQUAL, "ca"),
            array(Diff::INSERT, "ba"),
        ), $diffs);

        // Slide edit left recursive.
        $diffs = array(
            array(Diff::EQUAL, "a"),
            array(Diff::DELETE, "b"),
            array(Diff::EQUAL, "c"),
            array(Diff::DELETE, "ac"),
            array(Diff::EQUAL, "x"),
        );
        $this->d->cleanupMerge($diffs);
        $this->assertEquals(array(
            array(Diff::DELETE, "abc"),
            array(Diff::EQUAL, "acx"),
        ), $diffs);

        // Slide edit right recursive.
        $diffs = array(
            array(Diff::EQUAL, "x"),
            array(Diff::DELETE, "ca"),
            array(Diff::EQUAL, "c"),
            array(Diff::DELETE, "b"),
            array(Diff::EQUAL, "a"),
        );
        $this->d->cleanupMerge($diffs);
        $this->assertEquals(array(
            array(Diff::EQUAL, "xca"),
            array(Diff::DELETE, "cba"),
        ), $diffs);
    }

    public function testCleanupSemanticLossless()
    {
        // Slide diffs to match logical boundaries.
        // Null case.
        $diffs = array();
        $this->d->cleanupSemanticLossless($diffs);
        $this->assertEquals(array(), $diffs);

        // Blank lines.
        $diffs = array(
            array(Diff::EQUAL, "AAA\r\n\r\nBBB"),
            array(Diff::INSERT, "\r\nDDD\r\n\r\nBBB"),
            array(Diff::EQUAL, "\r\nEEE"),
        );
        $this->d->cleanupSemanticLossless($diffs);
        $this->assertEquals(array(
            array(Diff::EQUAL, "AAA\r\n\r\n"),
            array(Diff::INSERT, "BBB\r\nDDD\r\n\r\n"),
            array(Diff::EQUAL, "BBB\r\nEEE"),
        ), $diffs);

        // Line boundaries.
        $diffs = array(
            array(Diff::EQUAL, "AAA\r\nBBB"),
            array(Diff::INSERT, " DDD\r\nBBB"),
            array(Diff::EQUAL, " EEE"),
        );
        $this->d->cleanupSemanticLossless($diffs);
        $this->assertEquals(array(
            array(Diff::EQUAL, "AAA\r\n"),
            array(Diff::INSERT, "BBB DDD\r\n"),
            array(Diff::EQUAL, "BBB EEE"),
        ), $diffs);

        // Word boundaries.
        $diffs = array(
            array(Diff::EQUAL, "The c"),
            array(Diff::INSERT, "ow and the c"),
            array(Diff::EQUAL, "at."),
        );
        $this->d->cleanupSemanticLossless($diffs);
        $this->assertEquals(array(
            array(Diff::EQUAL, "The "),
            array(Diff::INSERT, "cow and the "),
            array(Diff::EQUAL, "cat."),
        ), $diffs);

        // Alphanumeric boundaries.
        $diffs = array(
            array(Diff::EQUAL, "The-c"),
            array(Diff::INSERT, "ow-and-the-c"),
            array(Diff::EQUAL, "at."),
        );
        $this->d->cleanupSemanticLossless($diffs);
        $this->assertEquals(array(
            array(Diff::EQUAL, "The-"),
            array(Diff::INSERT, "cow-and-the-"),
            array(Diff::EQUAL, "cat."),
        ), $diffs);

        // Hitting the start.
        $diffs = array(
            array(Diff::EQUAL, "a"),
            array(Diff::DELETE, "a"),
            array(Diff::EQUAL, "ax"),
        );
        $this->d->cleanupSemanticLossless($diffs);
        $this->assertEquals(array(
            array(Diff::DELETE, "a"),
            array(Diff::EQUAL, "aax"),
        ), $diffs);

        // Hitting the end.
        $diffs = array(
            array(Diff::EQUAL, "xa"),
            array(Diff::DELETE, "a"),
            array(Diff::EQUAL, "a"),
        );
        $this->d->cleanupSemanticLossless($diffs);
        $this->assertEquals(array(
            array(Diff::EQUAL, "xaa"),
            array(Diff::DELETE, "a"),
        ), $diffs);

        // Sentence boundaries.
        $diffs = array(
            array(Diff::EQUAL, "The xxx. The "),
            array(Diff::INSERT, "zzz. The "),
            array(Diff::EQUAL, "yyy."),
        );
        $this->d->cleanupSemanticLossless($diffs);
        $this->assertEquals(array(
            array(Diff::EQUAL, "The xxx."),
            array(Diff::INSERT, " The zzz."),
            array(Diff::EQUAL, " The yyy."),
        ), $diffs);
    }

    public function testCleanupSemantic()
    {
        // Cleanup semantically trivial equalities.
        // Null case.
        $diffs = array();
        $this->d->cleanupSemantic($diffs);
        $this->assertEquals(array(), $diffs);

        // No elimination #1.
        $diffs = array(
            array(Diff::DELETE, "ab"),
            array(Diff::INSERT, "cd"),
            array(Diff::EQUAL, "12"),
            array(Diff::DELETE, "e"),
        );
        $this->d->cleanupSemantic($diffs);
        $this->assertEquals(array(
            array(Diff::DELETE, "ab"),
            array(Diff::INSERT, "cd"),
            array(Diff::EQUAL, "12"),
            array(Diff::DELETE, "e"),
        ), $diffs);

        // No elimination #2.
        $diffs = array(
            array(Diff::DELETE, "abc"),
            array(Diff::INSERT, "ABC"),
            array(Diff::EQUAL, "1234"),
            array(Diff::DELETE, "wxyz"),
        );
        $this->d->cleanupSemantic($diffs);
        $this->assertEquals(array(
            array(Diff::DELETE, "abc"),
            array(Diff::INSERT, "ABC"),
            array(Diff::EQUAL, "1234"),
            array(Diff::DELETE, "wxyz"),
        ), $diffs);

        // Simple elimination.
        $diffs = array(
            array(Diff::DELETE, "a"),
            array(Diff::EQUAL, "b"),
            array(Diff::DELETE, "c"),
        );
        $this->d->cleanupSemantic($diffs);
        $this->assertEquals(array(
            array(Diff::DELETE, "abc"),
            array(Diff::INSERT, "b"),
        ), $diffs);

        // Backpass elimination.
        $diffs = array(
            array(Diff::DELETE, "ab"),
            array(Diff::EQUAL, "cd"),
            array(Diff::DELETE, "e"),
            array(Diff::EQUAL, "f"),
            array(Diff::INSERT, "g"),
        );
        $this->d->cleanupSemantic($diffs);
        $this->assertEquals(array(
            array(Diff::DELETE, "abcdef"),
            array(Diff::INSERT, "cdfg"),
        ), $diffs);

        // Multiple eliminations.
        $diffs = array(
            array(Diff::INSERT, "1"),
            array(Diff::EQUAL, "A"),
            array(Diff::DELETE, "B"),
            array(Diff::INSERT, "2"),
            array(Diff::EQUAL, "_"),
            array(Diff::INSERT, "1"),
            array(Diff::EQUAL, "A"),
            array(Diff::DELETE, "B"),
            array(Diff::INSERT, "2"),
        );
        $this->d->cleanupSemantic($diffs);
        $this->assertEquals(array(
            array(Diff::DELETE, "AB_AB"),
            array(Diff::INSERT, "1A2_1A2"),
        ), $diffs);

        // Word boundaries.
        $diffs = array(
            array(Diff::EQUAL, "The c"),
            array(Diff::DELETE, "ow and the c"),
            array(Diff::EQUAL, "at."),
        );
        $this->d->cleanupSemantic($diffs);
        $this->assertEquals(array(
            array(Diff::EQUAL, "The "),
            array(Diff::DELETE, "cow and the "),
            array(Diff::EQUAL, "cat."),
        ), $diffs);

        // No overlap elimination.
        $diffs = array(
            array(Diff::DELETE, "abcxx"),
            array(Diff::INSERT, "xxdef"),
        );
        $this->d->cleanupSemantic($diffs);
        $this->assertEquals(array(
            array(Diff::DELETE, "abcxx"),
            array(Diff::INSERT, "xxdef"),
        ), $diffs);

        // Overlap elimination.
        $diffs = array(
            array(Diff::DELETE, "abcxxx"),
            array(Diff::INSERT, "xxxdef"),
        );
        $this->d->cleanupSemantic($diffs);
        $this->assertEquals(array(
            array(Diff::DELETE, "abc"),
            array(Diff::EQUAL, "xxx"),
            array(Diff::INSERT, "def"),
        ), $diffs);

        // Reverse overlap elimination.
        $diffs = array(
            array(Diff::DELETE, "xxxabc"),
            array(Diff::INSERT, "defxxx"),
        );
        $this->d->cleanupSemantic($diffs);
        $this->assertEquals(array(
            array(Diff::INSERT, "def"),
            array(Diff::EQUAL, "xxx"),
            array(Diff::DELETE, "abc"),
        ), $diffs);

        // Two overlap eliminations.
        $diffs = array(
            array(Diff::DELETE, "abcd1212"),
            array(Diff::INSERT, "1212efghi"),
            array(Diff::EQUAL, "----"),
            array(Diff::DELETE, "A3"),
            array(Diff::INSERT, "3BC"),
        );
        $this->d->cleanupSemantic($diffs);
        $this->assertEquals(array(
            array(Diff::DELETE, "abcd"),
            array(Diff::EQUAL, "1212"),
            array(Diff::INSERT, "efghi"),
            array(Diff::EQUAL, "----"),
            array(Diff::DELETE, "A"),
            array(Diff::EQUAL, "3"),
            array(Diff::INSERT, "BC"),
        ), $diffs);
    }

    public function testCleanupEfficiency()
    {
        // Cleanup operationally trivial equalities.
        $this->d->setEditCost(4);

        // Null case.
        $diffs = array();
        $this->d->cleanupEfficiency($diffs);
        $this->assertEquals(array(), $diffs);

        // No elimination.
        $diffs = array(
            array(Diff::DELETE, "ab"),
            array(Diff::INSERT, "12"),
            array(Diff::EQUAL, "wxyz"),
            array(Diff::DELETE, "cd"),
            array(Diff::INSERT, "34"),
        );
        $this->d->cleanupEfficiency($diffs);
        $this->assertEquals(array(
            array(Diff::DELETE, "ab"),
            array(Diff::INSERT, "12"),
            array(Diff::EQUAL, "wxyz"),
            array(Diff::DELETE, "cd"),
            array(Diff::INSERT, "34"),
        ), $diffs);

        // Four-edit elimination.
        $diffs = array(
            array(Diff::DELETE, "ab"),
            array(Diff::INSERT, "12"),
            array(Diff::EQUAL, "xyz"),
            array(Diff::DELETE, "cd"),
            array(Diff::INSERT, "34"),
        );
        $this->d->cleanupEfficiency($diffs);
        $this->assertEquals(array(
            array(Diff::DELETE, "abxyzcd"),
            array(Diff::INSERT, "12xyz34"),
        ), $diffs);

        // Three-edit elimination.
        $diffs = array(
            array(Diff::INSERT, "12"),
            array(Diff::EQUAL, "x"),
            array(Diff::DELETE, "cd"),
            array(Diff::INSERT, "34"),
        );
        $this->d->cleanupEfficiency($diffs);
        $this->assertEquals(array(
            array(Diff::DELETE, "xcd"),
            array(Diff::INSERT, "12x34"),
        ), $diffs);

        // Backpass elimination.
        $diffs = array(
            array(Diff::DELETE, "ab"),
            array(Diff::INSERT, "12"),
            array(Diff::EQUAL, "xy"),
            array(Diff::INSERT, "34"),
            array(Diff::EQUAL, "z"),
            array(Diff::DELETE, "cd"),
            array(Diff::INSERT, "56"),
        );
        $this->d->cleanupEfficiency($diffs);
        $this->assertEquals(array(
            array(Diff::DELETE, "abxyzcd"),
            array(Diff::INSERT, "12xy34z56"),
        ), $diffs);

        // High cost elimination.
        $this->d->setEditCost(5);
        $diffs = array(
            array(Diff::DELETE, "ab"),
            array(Diff::INSERT, "12"),
            array(Diff::EQUAL, "wxyz"),
            array(Diff::DELETE, "cd"),
            array(Diff::INSERT, "34"),
        );
        $this->d->cleanupEfficiency($diffs);
        $this->assertEquals(array(
            array(Diff::DELETE, "abwxyzcd"),
            array(Diff::INSERT, "12wxyz34"),
        ), $diffs);
        $this->d->setEditCost(4);
    }

    public function testPrettyHtml(){
        // Pretty print.
        $diffs = array(
            array(Diff::EQUAL, "a\n"),
            array(Diff::DELETE, "<B>b</B>"),
            array(Diff::INSERT, "c&d"),
        );
        $this->assertEquals(
            '<span>a&para;<br></span><del style="background:#ffe6e6;">&lt;B&gt;b&lt;/B&gt;</del><ins style="background:#e6ffe6;">c&amp;d</ins>',
            $this->d->prettyHtml($diffs)
        );
    }

    public function testText(){
        // Compute the source and destination texts.
        $diffs = array(
            array(Diff::EQUAL, "jump"),
            array(Diff::DELETE, "s"),
            array(Diff::INSERT, "ed"),
            array(Diff::EQUAL, " over "),
            array(Diff::DELETE, "the"),
            array(Diff::INSERT, "a"),
            array(Diff::EQUAL, " lazy"),
        );
        $this->assertEquals(
            "jumps over the lazy",
            $this->d->text1($diffs)
        );
        $this->assertEquals(
            "jumped over a lazy",
            $this->d->text2($diffs)
        );
    }

    public function testDelta()
    {
        // Convert a diff into delta string.
        $diffs = array(
            array(Diff::EQUAL, "jump"),
            array(Diff::DELETE, "s"),
            array(Diff::INSERT, "ed"),
            array(Diff::EQUAL, " over "),
            array(Diff::DELETE, "the"),
            array(Diff::INSERT, "a"),
            array(Diff::EQUAL, " lazy"),
            array(Diff::INSERT, "old dog"),
        );
        $text1 = $this->d->text1($diffs);
        $this->assertEquals("jumps over the lazy", $text1);

        $delta = $this->d->toDelta($diffs);
        $this->assertEquals("=4\t-1\t+ed\t=6\t-3\t+a\t=5\t+old dog", $delta);

        // Convert delta string into a diff.
        $this->assertEquals($diffs, $this->d->fromDelta($text1, $delta));

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
        $diffs = array(
            array(Diff::EQUAL, Utils::unicodeChr(0x0680) . " \x00 \t %"),
            array(Diff::DELETE, Utils::unicodeChr(0x0681) . " \x01 \n ^"),
            array(Diff::INSERT, Utils::unicodeChr(0x0682) . " \x02 \\ |"),
        );

        $text1 = $this->d->text1($diffs);
        $this->assertEquals(Utils::unicodeChr(0x0680) . " \x00 \t %" . Utils::unicodeChr(0x0681) . " \x01 \n ^", $text1);

        $delta = $this->d->toDelta($diffs);
        $this->assertEquals("=7\t-7\t+%DA%82 %02 %5C %7C", $delta);

        // Convert delta string into a diff.
        $this->assertEquals($diffs, $this->d->fromDelta($text1, $delta));

        // Verify pool of unchanged characters.
        $diffs = array(
            array(Diff::INSERT, "A-Z a-z 0-9 - _ . ! ~ * ' ( ) ; / ? : @ & = + $ , # "),
        );

        $text2 = $this->d->text2($diffs);
        $this->assertEquals("A-Z a-z 0-9 - _ . ! ~ * ' ( ) ; / ? : @ & = + $ , # ", $text2);

        $delta = $this->d->toDelta($diffs);
        $this->assertEquals("+A-Z a-z 0-9 - _ . ! ~ * ' ( ) ; / ? : @ & = + $ , # ", $delta);

        // Convert delta string into a diff.
        $this->assertEquals($diffs, $this->d->fromDelta("", $delta));
    }

    public function testXIndex()
    {
        // Translate a location in text1 to text2.
        $this->assertEquals(5, $this->d->xIndex(array(
            array(Diff::DELETE, "a"),
            array(Diff::INSERT, "1234"),
            array(Diff::EQUAL, "xyz"),
        ), 2));

        // Translation on deletion.
        $this->assertEquals(1, $this->d->xIndex(array(
            array(Diff::EQUAL, "a"),
            array(Diff::DELETE, "1234"),
            array(Diff::EQUAL, "xyz"),
        ), 3));
    }

    public function testLevenshtein()
    {
        // Levenshtein with trailing equality.
        $this->assertEquals(4, $this->d->levenshtein(array(
            array(Diff::DELETE, "abc"),
            array(Diff::INSERT, "1234"),
            array(Diff::EQUAL, "xyz"),
        )));

        // Levenshtein with leading equality.
        $this->assertEquals(4, $this->d->levenshtein(array(
            array(Diff::EQUAL, "xyz"),
            array(Diff::DELETE, "abc"),
            array(Diff::INSERT, "1234"),
        )));

        // Levenshtein with middle equality.
        $this->assertEquals(7, $this->d->levenshtein(array(
            array(Diff::DELETE, "abc"),
            array(Diff::EQUAL, "xyz"),
            array(Diff::INSERT, "1234"),
        )));
    }

    public function testBisect()
    {
        // Since the resulting diff hasn't been normalized, it would be ok if
        // the insertion and deletion pairs are swapped.
        // If the order changes, tweak this test as required.
        $this->assertEquals(array(
            array(Diff::DELETE, "c"),
            array(Diff::INSERT, "m"),
            array(Diff::EQUAL, "a"),
            array(Diff::DELETE, "t"),
            array(Diff::INSERT, "p"),
        ), $this->d->bisect('cat', 'map', PHP_INT_MAX));

        // Timeout.
        $this->assertEquals(array(
            array(Diff::DELETE, "cat"),
            array(Diff::INSERT, "map"),
        ), $this->d->bisect('cat', 'map', 0));

    }

    public function testMain()
    {
        // Perform a trivial diff.
        // Null case.
        $this->assertEquals(array(), $this->d->main("", "", false));

        // Equality.
        $this->assertEquals(
            array(
                array(Diff::EQUAL, "abc"),
            ),
            $this->d->main("abc", "abc", false)
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
            $this->d->main("0120340", "0X12X0X34X0", false)
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
            $this->d->main("0X12X0X34X0", "0120340", false)
        );


        $this->assertEquals(
            array(
                array(Diff::DELETE, "Apple"),
                array(Diff::INSERT, "Banana"),
                array(Diff::EQUAL, "s are a"),
                array(Diff::INSERT, "lso"),
                array(Diff::EQUAL, " fruit."),
            ),
            $this->d->main("Apples are a fruit.", "Bananas are also fruit.", false)
        );

        $this->assertEquals(
            array(
                array(Diff::DELETE, "a"),
                array(Diff::INSERT, Utils::unicodeChr(0x0680)),
                array(Diff::EQUAL, "x"),
                array(Diff::DELETE, "\t"),
                array(Diff::INSERT, "\x00"),
            ),
            $this->d->main("ax\t", Utils::unicodeChr(0x0680) . "x\x00", false)
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
            $this->d->main("1ayb2", "abxab", false)
        );

        $this->assertEquals(
            array(
                array(Diff::INSERT, "xaxcx"),
                array(Diff::EQUAL, "abc"),
                array(Diff::DELETE, "y"),
            ),
            $this->d->main("abcy", "xaxcxabc", false)
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
            $this->d->main("ABCDa=bcd=efghijklmnopqrsEFGHIJKLMNOefg", "a-bcd-efghijklmnopqrs", false)
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
            $this->d->main("a [[Pennsylvania]] and [[New", " and [[Pennsylvania]]", false)
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
        $this->assertLessThan($this->d->getTimeout() * 10, $endTime - $startTime);
        $this->d->setTimeout(0);

        // Test the linemode speedup.
        // Must be long to pass the 100 char cutoff.
        // Simple line-mode.
        $a = str_repeat("1234567890\n", 13);
        $b = str_repeat("abcdefghij\n", 13);
        $this->assertEquals(
            $this->d->main($a, $b, false),
            $this->d->main($a, $b, true)
        );

        // Single line-mode.
        $a = str_repeat("1234567890", 13);
        $b = str_repeat("abcdefghij", 13);
        $this->assertEquals(
            $this->d->main($a, $b, false),
            $this->d->main($a, $b, true)
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
            rebuildtexts($this->d->main($a, $b, false)),
            rebuildtexts($this->d->main($a, $b, true))
        );

        // Test null inputs.
        try {
            $this->d->main(null, null);
            $this->fail();
        } catch (\InvalidArgumentException $e) {
        }
    }

}
