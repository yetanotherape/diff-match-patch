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
 * Functions for diff, match and patch.
 *
 * Computes the difference between two texts to create a patch. Applies the patch onto another text,
 * allowing for errors.
 * This class implements the same API as all other google-diff-match-patch libs. It was created for
 * compatibility reason only.
 *
 * @property float Diff_Timeout          Number of seconds to map a diff before giving up (0 for infinity).
 * @property int   Diff_EditCost         Cost of an empty edit operation in terms of edit characters.
 * @property float Match_Threshold       At what point is no match declared (0.0 = perfection, 1.0 = very loose).
 * @property int   Match_Distance        How far to search for a match (0 = exact location, 1000+ = broad match).
 *                                       A match this many characters away from the expected location will add
 *                                       1.0 to the score (0.0 is a perfect match).
 * @property int   Match_MaxBits         The number of bits in an int.
 * @property float Patch_DeleteThreshold When deleting a large block of text (over ~64 characters), how close do
 *                                       the contents have to be to match the expected contents. (0.0 = perfection,
 *                                       1.0 = very loose).  Note that Match_Threshold controls how closely the
 *                                       end points of a delete need to match.
 * @property int   Patch_Margin          Chunk size for context length.
 *
 * @package DiffMatchPatch
 * @author Neil Fraser <fraser@google.com>
 * @author Daniil Skrobov <yetanotherape@gmail.com>
 */
class DiffMatchPatch
{
    /**
     * The data structure representing a diff is an array of arrays:
     * array(
     *      array(DiffMatchPatch::DIFF_DELETE, "Hello"),
     *      array(DiffMatchPatch::DIFF_INSERT, "Goodbye"),
     *      array(DiffMatchPatch::DIFF_EQUAL, " world."),
     * )
     * which means: delete "Hello", add "Goodbye" and keep " world."
     */
    const DIFF_DELETE = Diff::DELETE;
    const DIFF_INSERT = Diff::INSERT;
    const DIFF_EQUAL  = Diff::EQUAL;

    /**
     * @var Diff
     */
    protected $diff;
    /**
     * @var Match
     */
    protected $match;
    /**
     * @var Patch
     */
    protected $patch;

    /**
     * Proxy getting properties to real objects.
     *
     * @param string $name Property name.
     *
     * @throws \UnexpectedValueException If property unknown.
     * @return float
     */
    public function __get($name)
    {
        switch ($name){
            case 'Diff_Timeout':
                $result = $this->diff->getTimeout();
                break;
            case 'Diff_EditCost':
                $result = $this->diff->getEditCost();
                break;
            case 'Match_Threshold':
                $result = $this->match->getThreshold();
                break;
            case 'Match_Distance':
                $result = $this->match->getDistance();
                break;
            case 'Match_MaxBits':
                $result = $this->match->getMaxBits();
                break;
            case 'Patch_DeleteThreshold':
                $result = $this->patch->getDeleteTreshold();
                break;
            case 'Patch_Margin':
                $result = $this->patch->getMargin();
                break;
            default:
                throw new \UnexpectedValueException('Unknown property: ' . $name);
        }

        return $result;
    }

    /**
     * Proxy setting properties to real objects.
     *
     * @param string $name  Property name.
     * @param mixed  $value Property value.
     *
     * @throws \UnexpectedValueException If property unknown.
     * @return float
     */
    public function __set($name, $value)
    {
        switch ($name){
            case 'Diff_Timeout':
                $this->diff->setTimeout($value);
                break;
            case 'Diff_EditCost':
                $this->diff->setEditCost($value);
                break;
            case 'Match_Threshold':
                $this->match->setThreshold($value);
                break;
            case 'Match_Distance':
                $this->match->setDistance($value);
                break;
            case 'Match_MaxBits':
                $this->match->setMaxBits($value);
                break;
            case 'Patch_DeleteThreshold':
                $this->patch->setDeleteTreshold($value);
                break;
            case 'Patch_Margin':
                $this->patch->setMargin($value);
                break;
            default:
                throw new \UnexpectedValueException('Unknown property: ' . $name);
        }
    }

    public function __construct()
    {
        $this->diff = new Diff();
        $this->match = new Match();
        $this->patch = new Patch($this->diff, $this->match);
    }

    /**
     * Find the differences between two texts.  Simplifies the problem by
     * stripping any common prefix or suffix off the texts before diffing.
     *
     * @param string $text1      Old string to be diffed.
     * @param string $text2      New string to be diffed.
     * @param bool   $checklines Optional speedup flag.  If present and false, then don't run
     *                           a line-level diff first to identify the changed areas.
     *                           Defaults to true, which does a faster, slightly less optimal diff.
     *
     * @throws \InvalidArgumentException If texts is null.
     * @return array Array of changes.
     */
    public function diff_main($text1, $text2, $checklines = true)
    {
        return $this->diff->main($text1, $text2, $checklines)->getChanges();
    }

    /**
     * Reduce the number of edits by eliminating semantically trivial equalities.
     * Modifies $diffs.
     *
     * @param array $diffs Array of diff arrays.
     */
    public function diff_cleanupSemantic(&$diffs)
    {
        $this->diff->setChanges($diffs);
        $this->diff->cleanupSemantic();
        $diffs = $this->diff->getChanges();
    }

    /**
     * Reduce the number of edits by eliminating operationally trivial equalities.
     * Modifies $diffs.
     *
     * @param array $diffs Array of diff arrays.
     */
    public function diff_cleanupEfficiency(&$diffs)
    {
        $this->diff->setChanges($diffs);
        $this->diff->cleanupEfficiency();
        $diffs = $this->diff->getChanges();
    }

    /**
     * Compute the Levenshtein distance; the number of inserted, deleted or substituted characters.
     *
     * @param array $diffs Array of diff arrays.
     *
     * @return int Number of changes.
     */
    public function diff_levenshtein($diffs)
    {
        $this->diff->setChanges($diffs);
        return $this->diff->levenshtein();
    }

    /**
     * Convert a diff array into a pretty HTML report.
     *
     * @param array $diffs Array of diff arrays.
     *
     * @return string HTML representation.
     */
    public function diff_prettyHtml($diffs)
    {
        $this->diff->setChanges($diffs);
        return $this->diff->prettyHtml();
    }

    /**
     * Locate the best instance of 'pattern' in 'text' near 'loc'.
     *
     * @param string $text    The text to search.
     * @param string $pattern The pattern to search for.
     * @param int    $loc     The location to search around.
     *
     * @throws \InvalidArgumentException If null inout.
     * @return int Best match index or -1.
     */
    public function match_main($text, $pattern, $loc = 0)
    {
        return $this->match->main($text, $pattern, $loc);
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
    public function patch_make($a, $b = null, $c = null)
    {
        return $this->patch->make($a, $b, $c);
    }

    /**
     * Take a list of patches and return a textual representation.
     *
     * @param PatchObject[] $patches Array of PatchObjects.
     *
     * @return string Text representation of patches.
     */
    public function patch_toText($patches)
    {
        return $this->patch->toText($patches);
    }

    /**
     * Parse a textual representation of patches and return a list of patch objects.
     *
     * @param string $text Text representation of patches.
     *
     * @throws \InvalidArgumentException If invalid input.
     * @throws \UnexpectedValueException If text has bad syntax.
     * @return PatchObject[] Array of PatchObjects.
     */
    public function patch_fromText($text)
    {
       return $this->patch->fromText($text);
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
    public function patch_apply($patches, $text)
    {
        return $this->patch->apply($patches, $text);
    }
}
