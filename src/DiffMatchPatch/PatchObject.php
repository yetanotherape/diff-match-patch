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
 * Class representing one patch operation.
 *
 * @package DiffMatchPatch
 * @author Neil Fraser <fraser@google.com>
 * @author Daniil Skrobov <yetanotherape@gmail.com>
 */
class PatchObject
{
    /**
     * @var array
     * TODO replace to diff object
     */
    protected $changes = array();
    /**
     * @var int
     */
    protected $start1;
    /**
     * @var int
     */
    protected $start2;
    /**
     * @var int
     */
    protected $length1 = 0;
    /**
     * @var int
     */
    protected $length2 = 0;

    /**
     * Emmulate GNU diff's format.
     * Header: @@ -382,8 +481,9 @@
     * Indicies are printed as 1-based, not 0-based.
     *
     * @return string The GNU diff string.
     */
    public function __toString()
    {
        if ($this->getLength1() == 0) {
            $coords1 = $this->getStart1() . ',0';
        } elseif ($this->getLength1() == 1) {
            $coords1 = $this->getStart1() + 1;
        } else {
            $coords1 = ($this->getStart1() + 1) . ',' . $this->getLength1();
        }

        if ($this->getLength2() == 0) {
            $coords2 = $this->getStart2() . ',0';
        } elseif ($this->getLength2() == 1) {
            $coords2 = $this->getStart2() + 1;
        } else {
            $coords2 = ($this->getStart2() + 1) . ',' . $this->getLength2();
        }
        $patchText = "@@ -" . $coords1 . " +" . $coords2 . " @@\n";

        // Escape the body of the patch with %xx notation.
        foreach ($this->getChanges() as $change) {
            $op = $change[0];
            $text = $change[1];

            switch ($op) {
                case Diff::INSERT:
                    $patchText .= '+';
                    break;
                case Diff::DELETE:
                    $patchText .= '-';
                    break;
                case Diff::EQUAL:
                    $patchText .= ' ';
                    break;
            }
            $patchText .= Utils::escapeString($text) . "\n";
        }

        return $patchText;
    }

    /**
     * @return int
     */
    public function getLength1()
    {
        return $this->length1;
    }

    /**
     * @param $length1
     */
    public function setLength1($length1)
    {
        $this->length1 = (int)$length1;
    }

    /**
     * @return int
     */
    public function getStart1()
    {
        return $this->start1;
    }

    /**
     * @param $start1
     */
    public function setStart1($start1)
    {
        $this->start1 = (int)$start1;
    }

    /**
     * @return int
     */
    public function getLength2()
    {
        return $this->length2;
    }

    /**
     * @param $length2
     */
    public function setLength2($length2)
    {
        $this->length2 = (int)$length2;
    }

    /**
     * @return int
     */
    public function getStart2()
    {
        return $this->start2;
    }

    /**
     * @param $start2
     */
    public function setStart2($start2)
    {
        $this->start2 = (int)$start2;
    }


    /**
     * @return array
     */
    public function getChanges()
    {
        return $this->changes;
    }

    /**
     * @param $changes
     */
    public function setChanges($changes)
    {
        $this->changes = $changes;
    }

    /**
     * @param array $change
     */
    public function appendChanges(array $change)
    {
        $this->changes[] = $change;
    }

    /**
     * @param array $change
     */
    public function prependChanges(array $change)
    {
        array_unshift($this->changes, $change);
    }

}
