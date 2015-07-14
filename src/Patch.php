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
 * Patch offers methods for create a patch. Applies the patch onto another text, allowing for errors.
 *
 * @package DiffMatchPatch
 * @author Neil Fraser <fraser@google.com>
 * @author Daniil Skrobov <yetanotherape@gmail.com>
 */
class Patch
{
    /**
     * @var float When deleting a large block of text (over ~64 characters), how close do
     * the contents have to be to match the expected contents. (0.0 = perfection,
     * 1.0 = very loose).  Note that Match->threshold controls how closely the
     * end points of a delete need to match.
     */
    protected $deleteTreshold = 0.5;
    /**
     * @var int Chunk size for context length.
     */
    protected $margin = 4;
    /**
     * @var Diff
     */
    protected $diff;
    /**
     * @var Match
     */
    protected $match;

    /**
     * @param Diff|null $diff
     * @param Match|null $match
     */
    public function __construct(Diff $diff = null, Match $match = null)
    {
        if (!isset($match)) {
            $match = new Match();
        }
        if (!isset($diff)) {
            $diff = new Diff();
        }

        $this->diff = $diff;
        $this->match = $match;
    }

    /**
     * @return float
     */
    public function getDeleteTreshold()
    {
        return $this->deleteTreshold;
    }

    /**
     * @param float $deleteTreshold
     */
    public function setDeleteTreshold($deleteTreshold)
    {
        $this->deleteTreshold = $deleteTreshold;
    }

    /**
     * @return int
     */
    public function getMargin()
    {
        return $this->margin;
    }

    /**
     * @param int $margin
     */
    public function setMargin($margin)
    {
        $this->margin = $margin;
    }

    /**
     * @return Match
     */
    protected function getMatch()
    {
        return $this->match;
    }

    /**
     * @return Diff
     */
    protected function getDiff()
    {
        return $this->diff;
    }



    /**
     * Parse a textual representation of patches and return a list of patch objects.
     *
     * @param string $patchText Text representation of patches.
     *
     * @throws \InvalidArgumentException If invalid input.
     * @throws \UnexpectedValueException If text has bad syntax.
     * @return PatchObject[] Array of PatchObjects.
     */
    public function fromText($patchText){
        $patches = array();
        if (!$patchText) {
            return $patches;
        }

        $lines = explode("\n", $patchText);
        while (count($lines)) {
            $line = $lines[0];
            if (!preg_match("/^@@ -(\d+),?(\d*) \+(\d+),?(\d*) @@$/", $line, $m)) {
                throw new \InvalidArgumentException("Invalid patch string: " . $line);
            }
            $patch = new PatchObject();
            $patch->setStart1($m[1]);
            if ($m[2] == '') {
                $patch->setStart1($patch->getStart1() - 1);
                $patch->setLength1(1);
            } elseif ($m[2] == '0') {
                $patch->setLength1(0);
            } else {
                $patch->setStart1($patch->getStart1() - 1);
                $patch->setLength1($m[2]);
            }
            $patch->setStart2($m[3]);
            if ($m[4] == '') {
                $patch->setStart2($patch->getStart2() - 1);
                $patch->setLength2(1);
            } elseif ($m[4] == '0') {
                $patch->setLength2(0);
            } else {
                $patch->setStart2($patch->getStart2() - 1);
                $patch->setLength2($m[4]);
            }
            $patches[] = $patch;
            array_shift($lines);

            while (count($lines)) {
                $line = $lines[0];
                if ($line) {
                    $sign = mb_substr($line, 0, 1);
                } else {
                    $sign = '';
                }
                $text = Utils::unescapeString(mb_substr($line, 1));
                switch ($sign) {
                    case '+':
                        // Insertion.
                        $patch->appendChanges(array(Diff::INSERT, $text));
                        break;
                    case '-':
                        // Deletion.
                        $patch->appendChanges(array(Diff::DELETE, $text));
                        break;
                    case ' ':
                        // Minor equality.
                        $patch->appendChanges(array(Diff::EQUAL, $text));
                        break;
                    case '@':
                        // Start of next patch.
                        break 2;
                    case '':
                        // Blank line?  Whatever.
                        break;
                    default:
                        // WTF?
                        throw new \UnexpectedValueException("Invalid patch mode: " .  $sign . PHP_EOL . $text);
                }
                array_shift($lines);
            }
        }

        return $patches;
    }

    /**
     * Take a list of patches and return a textual representation.
     *
     * @param PatchObject[] $patches Array of PatchObjects.
     *
     * @return string Text representation of patches.
     */
    public function toText($patches)
    {
        $text = '';
        foreach ($patches as $patch) {
            $text .= (string)$patch;
        }

        return $text;
    }

    /**
     * Increase the context until it is unique, but don't let the pattern expand beyond Match->maxBits.
     *
     * @param PatchObject $patch The patch to grow.
     * @param string      $text Source text.
     */
    public function addContext(PatchObject $patch, $text)
    {
        if (!mb_strlen($text)) {
            return;
        }
        $padding = 0;
        $pattern = mb_substr($text, $patch->getStart1(), $patch->getLength1());

        // Look for the first and last matches of pattern in text.
        // If two different matches are found, increase the pattern length.
        $match = $this->getMatch();
        while (
            (!$pattern || mb_strpos($text, $pattern) !== mb_strrpos($text, $pattern)) &&
            ($match->getMaxBits() == 0 || mb_strlen($pattern) < $match->getMaxBits() - 2 * $this->getMargin())
        ) {
            $padding += $this->getMargin();
            $pattern = mb_substr(
                $text,
                max(0, $patch->getStart2() - $padding),
                $patch->getStart2() + $patch->getLength1() + $padding - max(0, $patch->getStart2() - $padding)
            );
        }
        // Add one chunk for good luck.
        $padding += $this->getMargin();

        // Add the prefix.
        $prefix = mb_substr($text, max(0, $patch->getStart2() - $padding), min($patch->getStart2(), $padding));
        if ($prefix != '') {
            $patch->prependChanges(array(Diff::EQUAL, $prefix));
        }
        // Add the suffix.
        $suffix = mb_substr($text, $patch->getStart2() + $patch->getLength1(), $padding);
        if ($suffix != '') {
            $patch->appendChanges(array(Diff::EQUAL, $suffix));
        }

        // Roll back the start points.
        $prefixLen = mb_strlen($prefix);
        $patch->setStart1($patch->getStart1() - $prefixLen);
        $patch->setStart2($patch->getStart2() - $prefixLen);
        // Extend lengths.
        $suffixLen = mb_strlen($suffix);
        $patch->setLength1($patch->getLength1() + $prefixLen + $suffixLen);
        $patch->setLength2($patch->getLength2() + $prefixLen + $suffixLen);
    }

    /**
     * Compute a list of patches to turn text1 into text2.
     * Use diffs if provided, otherwise compute it ourselves.
     * There are four ways to call this function, depending on what data is
     * available to the caller:
     * Method 1:
     *     a = text1, b = text2
     * Method 2:
     *     a = diffs
     * Method 3 (optimal):
     *     a = text1, b = diffs
     * Method 4 (deprecated, use method 3):
     *     a = text1, b = text2, c = diffs
     *
     * @param string|array      $a text1 (methods 1,3,4) or Array of diff arrays for text1 to text2 (method 2).
     * @param string|array|null $b text2 (methods 1,4) or Array of diff arrays for text1 to text2 (method 3)
     *                             or null (method 2).
     * @param array|null        $c Array of diff arrays for text1 to text2 (method 4) or null (methods 1,2,3).
     *
     * @throws \InvalidArgumentException If unknown call format.
     * @return PatchObject[] Array of PatchObjects.
     */
    public function make($a, $b = null, $c = null)
    {
        $diff = $this->getDiff();
        if (is_string($a) && is_string($b) && is_null($c)) {
            // Method 1: text1, text2
            // Compute diffs from text1 and text2.
            $text1 = $a;
            $diff->main($text1, $b);
            if (count($diff->getChanges()) > 2) {
                $diff->cleanupSemantic();
                $diff->cleanupEfficiency();
            }
            $diffs = $diff->getChanges();
        } elseif (is_array($a) && is_null($b)) {
            // Method 2: diffs
            // Compute text1 from diffs.
            $diffs = $a;
            $diff->setChanges($diffs);
            $text1 = $diff->text1();
        } elseif (is_string($a) && is_array($b) && is_null($c)) {
            // Method 3: text1, diffs
            $text1 = $a;
            $diffs = $b;
        } elseif (is_string($a) && is_string($b) && is_array($c)) {
            // Method 4: text1, text2, diffs
            $text1 = $a;
            $diffs = $c;
        } else {
            throw new \InvalidArgumentException("Unknown call format");
        }

        $patches = array();
        if (!isset($diffs)) {
            // Get rid of the null case.
            return $patches;
        }
        $patch = new PatchObject();
        // Number of characters into the text1 string.
        $charCount1 = 0;
        // Number of characters into the text2 string.
        $charCount2 = 0;
        // Recreate the patches to determine context info.
        $prePatchText = $text1;
        $postPatchText = $text1;
        for ($i = 0; $i < count($diffs); $i++) {
            list($diffType, $diffText) = $diffs[$i];
            $diffTextLen = mb_strlen($diffText);

            if (count($patch->getChanges()) == 0 && $diffType != Diff::EQUAL) {
                // A new patch starts here.
                $patch->setStart1($charCount1);
                $patch->setStart2($charCount2);
            }
            if ($diffType == Diff::INSERT) {
                // Insertion.
                $patch->appendChanges($diffs[$i]);
                $patch->setLength2($patch->getLength2() + $diffTextLen);
                $postPatchText = mb_substr($postPatchText, 0, $charCount2) .
                    $diffText . mb_substr($postPatchText, $charCount2);
            } elseif ($diffType == Diff::DELETE) {
                // Deletion.
                $patch->appendChanges($diffs[$i]);
                $patch->setLength1($patch->getLength1() + $diffTextLen);
                $postPatchText = mb_substr($postPatchText, 0, $charCount2) .
                    mb_substr($postPatchText, $charCount2 + $diffTextLen);
            } elseif (
                $diffType == Diff::EQUAL && $diffTextLen <= 2 * $this->getMargin() &&
                count($patch->getChanges()) && $i + 1 != count($diffs)
            ) {
                // Small equality inside a patch.
                $patch->appendChanges($diffs[$i]);
                $patch->setLength1($patch->getLength1() + $diffTextLen);
                $patch->setLength2($patch->getLength2() + $diffTextLen);
            }

            if ($diffType == Diff::EQUAL && $diffTextLen >= 2 * $this->getMargin()) {
                // Time for a new patch.
                if (count($patch->getChanges())) {
                    $this->addContext($patch, $prePatchText);
                    $patches[] = $patch;
                    $patch = new PatchObject();
                    // Unlike Unidiff, our patch lists have a rolling context.
                    // http://code.google.com/p/google-diff-match-patch/wiki/Unidiff
                    // Update prepatch text & pos to reflect the application of the
                    // just completed patch.
                    $prePatchText = $postPatchText;
                    $charCount1 = $charCount2;
                }
            }

            // Update the current character count.
            if ($diffType != Diff::INSERT) {
                $charCount1 += $diffTextLen;
            }
            if ($diffType != Diff::DELETE) {
                $charCount2 += $diffTextLen;
            }
        }

        // Pick up the leftover patch if not empty.
        if (count($patch->getChanges())) {
            $this->addContext($patch, $prePatchText);
            $patches[] = $patch;
        }

        return $patches;
    }

    /**
     * Look through the patches and break up any which are longer than the
     * maximum limit of the match algorithm.
     * Intended to be called only from within apply().
     * Modifies $patches. TODO try to fix it!
     *
     * @param PatchObject[] $patches Array of PatchObjects.
     */
    public function splitMax(&$patches)
    {
        $patchSize = $this->getMatch()->getMaxBits();
        if ($patchSize == 0) {
            // TODO PHP has fixed size int, so this case isn't relevant.
            return;
        }

        for ($i = 0; $i < count($patches); $i++) {
            if ($patches[$i]->getLength1() <= $patchSize) {
                continue;
            }

            $bigPatch = $patches[$i];
            // Remove the big old patch.
            array_splice($patches, $i, 1);
            $i--;

            $start1 = $bigPatch->getStart1();
            $start2 = $bigPatch->getStart2();
            $preContext = '';
            $bigPatchDiffs = $bigPatch->getChanges();
            while (count($bigPatchDiffs)) {
                // Create one of several smaller patches.
                $empty = true;
                $patch = new PatchObject();
                $preContextLen = mb_strlen($preContext);
                $patch->setStart1($start1 - $preContextLen);
                $patch->setStart2($start2 - $preContextLen);

                if ($preContext != '') {
                    $patch->setLength1($preContextLen);
                    $patch->setLength2($preContextLen);
                    $patch->appendChanges(array(Diff::EQUAL, $preContext));
                }

                while (count($bigPatchDiffs) && $patch->getLength1() < $patchSize - $this->getMargin()) {
                    list($diffType, $diffText) = $bigPatchDiffs[0];
                    $diffTextLen = mb_strlen($diffText);

                    if ($diffType == Diff::INSERT) {
                        // Insertions are harmless.
                        $patch->setLength2($patch->getLength2() + $diffTextLen);
                        $start2 += $diffTextLen;
                        $patch->appendChanges(array_shift($bigPatchDiffs));
                        $empty = false;
                    } elseif (
                        $diffType == Diff::DELETE && ($patchDiffs = $patch->getChanges()) &&
                        count($patchDiffs) == 1 && $patchDiffs[0][0] == Diff::EQUAL &&
                        2 * $patchSize < $diffTextLen
                    ) {
                        // This is a large deletion.  Let it pass in one chunk.
                        $patch->setLength1($patch->getLength1() + $diffTextLen);
                        $start1 += $diffTextLen;
                        array_shift($bigPatchDiffs);
                        $patch->appendChanges(array($diffType, $diffText));
                        $empty = false;
                    } else {
                        // Deletion or equality.  Only take as much as we can stomach.
                        $diffText = mb_substr($diffText, 0, $patchSize - $patch->getLength1() - $this->getMargin());
                        $diffTextLen = mb_strlen($diffText);
                        $patch->setLength1($patch->getLength1() + $diffTextLen);
                        $start1 += $diffTextLen;

                        if ($diffType == Diff::EQUAL) {
                            $patch->setLength2($patch->getLength2() + $diffTextLen);
                            $start2 += $diffTextLen;
                        } else {
                            $empty = false;
                        }

                        if ($diffText == $bigPatchDiffs[0][1]) {
                            array_shift($bigPatchDiffs);
                        } else {
                            $bigPatchDiffs[0][1] = mb_substr($bigPatchDiffs[0][1], $diffTextLen);
                        }
                        $patch->appendChanges(array($diffType, $diffText));
                    }
                }

                // Compute the head context for the next patch.
                $diff = $this->getDiff();
                $diff->setChanges($patch->getChanges());
                $preContext = $diff->text2();
                $preContext = mb_substr($preContext, -$this->getMargin());

                // Append the end context for this patch.
                $diff->setChanges($bigPatchDiffs);
                $postContext = $diff->text1();
                $postContext = mb_substr($postContext, 0, $this->getMargin());
                if ($postContext != '') {
                    $patch->setLength1($patch->getLength1() + mb_strlen($postContext));
                    $patch->setLength2($patch->getLength2() + mb_strlen($postContext));
                    if (
                        ($patchDiffs = $patch->getChanges()) && count($patchDiffs) &&
                        $patchDiffs[count($patchDiffs) - 1][0] == Diff::EQUAL
                    ) {
                        $patchDiffs[count($patchDiffs) - 1][1] .= $postContext;
                        $patch->setChanges($patchDiffs);
                    } else {
                        $patch->appendChanges(array(Diff::EQUAL, $postContext));
                    }
                }

                if (!$empty) {
                    $i++;
                    array_splice($patches, $i, 0, array($patch));
                }
            }
        }
    }

    /**
     * Add some padding on text start and end so that edges can match something.
     * Intended to be called only from within patch_apply.
     * Modifies $patches. TODO try to fix it!
     *
     * @param PatchObject[] $patches Array of PatchObjects.
     *
     * @return string The padding string added to each side.
     */
    public function addPadding(&$patches)
    {
        $paddingLength = $this->getMargin();
        $nullPadding = '';
        for ($i = 1; $i <= $paddingLength; $i++) {
            $nullPadding .= chr($i);
        }

        // Bump all the patches forward.
        foreach ($patches as &$patch) {
            $patch->setStart1($patch->getStart1() + $paddingLength);
            $patch->setStart2($patch->getStart2() + $paddingLength);
        }
        unset($patch);

        // Add some padding on start of first diff.
        $patch = &$patches[0];
        $diffs = $patch->getChanges();
        $firstChange = &$diffs[0];
        if (!$diffs || $firstChange[0] != Diff::EQUAL) {
            // Add nullPadding equality.
            array_unshift($diffs, array(Diff::EQUAL, $nullPadding));
            // Should be 0.
            $patch->setStart1($patch->getStart1() - $paddingLength);
            // Should be 0.
            $patch->setStart2($patch->getStart2() - $paddingLength);
            $patch->setLength1($patch->getLength1() + $paddingLength);
            $patch->setLength2($patch->getLength2() + $paddingLength);
        } elseif($paddingLength > mb_strlen($firstChange[1])) {
            // Grow first equality.
            $extraLength = $paddingLength - mb_strlen($firstChange[1]);
            $firstChange[1] = mb_substr($nullPadding, mb_strlen($firstChange[1])) . $firstChange[1];
            $patch->setStart1($patch->getStart1() - $extraLength);
            $patch->setStart2($patch->getStart2() - $extraLength);
            $patch->setLength1($patch->getLength1() + $extraLength);
            $patch->setLength2($patch->getLength2() + $extraLength);
        }
        $patch->setChanges($diffs);
        unset($patch, $firstChange);

        // Add some padding on end of last diff.
        $patch = &$patches[count($patches) - 1];
        $diffs = $patch->getChanges();
        $lastChange = &$diffs[count($diffs) - 1];
        if (!$diffs || $lastChange[0] != Diff::EQUAL) {
            // Add nullPadding equality.
            array_push($diffs, array(Diff::EQUAL, $nullPadding));
            $patch->setLength1($patch->getLength1() + $paddingLength);
            $patch->setLength2($patch->getLength2() + $paddingLength);
        } elseif($paddingLength > mb_strlen($lastChange[1])) {
            // Grow last equality.
            $extraLength = $paddingLength - mb_strlen($lastChange[1]);
            $lastChange[1] .= mb_substr($nullPadding, 0, $extraLength);
            $patch->setLength1($patch->getLength1() + $extraLength);
            $patch->setLength2($patch->getLength2() + $extraLength);
        }
        $patch->setChanges($diffs);
        unset($patch, $lastChange);

        return $nullPadding;
    }

    /**
     * Merge a set of patches onto the text.  Return a patched text, as well
     * as a list of true/false values indicating which patches were applied.
     *
     * @param PatchObject[] $patches Array of PatchObjects.
     * @param string        $text    Old text.
     *
     * @return array Two element Array, containing the new text and an array of boolean values.
     */
    public function apply($patches, $text)
    {
        if (empty($patches)) {
            return array($text, array());
        }
        // Deep copy the patches so that no changes are made to originals.
        // FIXME don't need in PHP
        $patches = $this->deepCopy($patches);

        $nullPadding = $this->addPadding($patches);
        $text = $nullPadding . $text . $nullPadding;
        $this->splitMax($patches);

        // Delta keeps track of the offset between the expected and actual location
        // of the previous patch.  If there are patches expected at positions 10 and
        // 20, but the first patch was found at 12, delta is 2 and the second patch
        // has an effective expected position of 22.
        $delta = 0;
        $results = array();
        $diff = $this->getDiff();
        $match = $this->getMatch();
        $maxBits = $match->getMaxBits();

        foreach ($patches as $patch) {
            $expectedLoc = $patch->getStart2() + $delta;
            $diff->setChanges($patch->getChanges());
            $text1 = $diff->text1();
            $text1Len = mb_strlen($text1);
            $endLoc = -1;

            if ($text1Len > $maxBits) {
                // self::splitMax() will only provide an oversized pattern in the case of
                // a monster delete.
                $startLoc = $match->main($text, mb_substr($text1, 0, $maxBits), $expectedLoc);

                if ($startLoc != -1) {
                    $endLoc = $match->main($text, mb_substr($text1, -$maxBits),
                        $expectedLoc + $text1Len - $maxBits);
                    if ($endLoc == -1 || $startLoc >= $endLoc) {
                        // Can't find valid trailing context.  Drop this patch.
                        $startLoc = -1;
                    }
                }
            } else {
                $startLoc = $match->main($text, $text1, $expectedLoc);
            }

            if ($startLoc == -1) {
                // No match found.  :(
                $results[] = false;
                // Subtract the delta for this failed patch from subsequent patches.
                $delta -= $patch->getLength2() - $patch->getLength1();
            } else {
                // Found a match.  :)
                $results[] = true;
                $delta = $startLoc - $expectedLoc;
                if ($endLoc == -1) {
                    $text2 = mb_substr($text, $startLoc, $text1Len);
                } else {
                    $text2 = mb_substr($text, $startLoc, $endLoc + $maxBits - $startLoc);
                }
                if ($text1 == $text2) {
                    // Perfect match, just shove the replacement text in.
                    $text = mb_substr($text, 0, $startLoc) . $diff->text2() .
                        mb_substr($text, $startLoc + $text1Len);
                } else {
                    // Imperfect match.
                    // Run a diff to get a framework of equivalent indices.
                    $diff->main($text1, $text2, false);
                    if (
                        $text1Len > $maxBits &&
                        $diff->levenshtein() / $text1Len > $this->getDeleteTreshold()
                    ) {
                        // The end points match, but the content is unacceptably bad.
                        $results[count($results) - 1] = false;
                    } else {
                        $diff->cleanupSemanticLossless();
                        $index1 = 0;
                        foreach ($patch->getChanges() as $change) {
                            list ($op, $data) = $change;
                            if ($op != Diff::EQUAL) {
                                $index2 = $diff->xIndex($index1);
                                if ($op == Diff::INSERT) {
                                    $text = mb_substr($text, 0, $startLoc + $index2) . $data .
                                        mb_substr($text, $startLoc + $index2);
                                } elseif ($op == Diff::DELETE) {
                                    $text = mb_substr($text, 0, $startLoc + $index2) .
                                        mb_substr($text, $startLoc + $diff->xIndex($index1 + mb_strlen($data)));
                                }
                            }
                            if ($op != Diff::DELETE) {
                                $index1 += mb_strlen($data);
                            }
                        }
                    }
                }
            }
        }

        // Strip the padding off.
        $text = mb_substr($text, mb_strlen($nullPadding), -mb_strlen($nullPadding));

        return array($text, $results);
    }
//          else:
//            self.diff_cleanupSemanticLossless(diffs)
//            index1 = 0
//            for (op, data) in patch.diffs:
//              if op != self.DIFF_EQUAL:
//                index2 = self.diff_xIndex(diffs, index1)
//              if op == self.DIFF_INSERT:  # Insertion
//                text = text[:start_loc + index2] + data + text[start_loc +
//                                                               index2:]
//              elif op == self.DIFF_DELETE:  # Deletion
//                text = text[:start_loc + index2] + text[start_loc +
//                    self.diff_xIndex(diffs, index1 + len(data)):]
//              if op != self.DIFF_DELETE:
//                index1 += len(data)
//    # Strip the padding off.
//    text = text[len(nullPadding):-len(nullPadding)]
//    return (text, results)
    /**
     * Given an array of patches, return another array that is identical.
     *
     * @param PatchObject[] $patches Array of PatchObjects.
     *
     * @return PatchObject[] Array of PatchObjects.
     */
    protected  function deepCopy($patches)
    {
        $patchesCopy = array();
        foreach ($patches as $patch) {
            $patchCopy = clone $patch;
            $patchesCopy[] = $patchCopy;
        }
        return $patchesCopy;
    }
}
