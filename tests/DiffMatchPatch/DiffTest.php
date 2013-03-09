<?php

namespace DiffMatchPatch;

use DiffMatchPatch\Diff;

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
}
