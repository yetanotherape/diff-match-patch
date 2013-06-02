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
class DiffTmpTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Diff
     */
    protected $d;

    protected  function setUp() {
        mb_internal_encoding('UTF-8');

        $this->d = new Diff();
    }

    public function testTmp()
    {
//        function rebuildtexts($diffs) {
//            // Construct the two texts which made up the diff originally.
//            $text1 = "";
//            $text2 = "";
//            foreach ($diffs as $change) {
//                if ($change[0] != Diff::INSERT) {
//                    $text1 .= $change[1];
//                }
//                if ($change[0] != Diff::DELETE) {
//                    $text2 .= $change[1];
//                }
//            }
//            return array($text1, $text2);
//        }
//        // Overlap line-mode.
//        $a = str_repeat("1234567890\n", 13);
//        $b = "abcdefghij\n1234567890\n1234567890\n1234567890\nabcdefghij\n1234567890\n1234567890\n1234567890\nabcdefghij\n1234567890\n1234567890\n1234567890\nabcdefghij\n";
//        $this->assertEquals(
//            rebuildtexts($this->d->main($a, $b, false)->getChanges()),
//            rebuildtexts($this->d->main($a, $b, true)->getChanges())
//        );
    }
}
