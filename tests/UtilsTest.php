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

use PHPUnit\Framework\TestCase;

/**
 * @package DiffMatchPatch
 * @author Neil Fraser <fraser@google.com>
 * @author Daniil Skrobov <yetanotherape@gmail.com>
 */
class UtilsTest extends TestCase
{
    protected  function setUp():void {
        mb_internal_encoding('UTF-8');
    }

    public function testUnicodeChr()
    {
        $this->assertEquals('a', Utils::unicodeChr(97));
        $this->assertEquals('ÿ', Utils::unicodeChr(255));
        $this->assertEquals('Ā', Utils::unicodeChr(256));
        $this->assertEquals('Ą', Utils::unicodeChr(260));
//        $this->assertEquals('𐀀', Utils::unicodeChr(65536));
//        $this->assertEquals('😺', Utils::unicodeChr(128570));
    }

    public function testUnicodeOrd()
    {
        $this->assertEquals(97, Utils::unicodeOrd('a'));
        $this->assertEquals(255, Utils::unicodeOrd('ÿ'));
        $this->assertEquals(256, Utils::unicodeOrd('Ā'));
        $this->assertEquals(260, Utils::unicodeOrd('Ą'));
//        $this->assertEquals(65536, Utils::unicodeOrd('𐀀'));
//        $this->assertEquals(128570, Utils::unicodeOrd('😺'));
    }


}
