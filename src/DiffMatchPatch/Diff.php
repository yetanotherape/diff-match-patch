<?php


namespace DiffMatchPatch;


class Diff
{
    /**
     * @var float Number of seconds to map a diff before giving up (0 for infinity).
     */
    protected $timeout = 1.0;
    /**
     * @var int Cost of an empty edit operation in terms of edit characters.
     */
    protected $editCost = 4;

    public function __construct($charset = 'UTF-8')
    {
        // XXX this may do some side effects
        mb_internal_encoding($charset);
    }

    /**
     * @return float
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * @return int
     */
    public function getEditCost()
    {
        return $this->editCost;
    }

    /**
     * @param int $editCost
     */
    public function setEditCost($editCost)
    {
        $this->editCost = $editCost;
    }

    /**
     * Determine the common prefix of two strings.
     *
     * @param string $text1 First string.
     * @param string $text2 Second string.
     *
     * @return int The number of characters common to the start of each string.
     */
    public function commonPrefix($text1, $text2)
    {
        // Quick check for common null cases.
        if (!$text1 || !$text2 || mb_substr($text1, 0, 1) != mb_substr($text2, 0, 1)) {
            return 0;
        }
        // Binary search.
        // Performance analysis: http://neil.fraser.name/news/2007/10/09/
        $pointermin = 0;
        $pointermax = min(mb_strlen($text1), mb_strlen($text2));
        $pointermid = $pointermax;
        $pointerstart = 0;
        while ($pointermin < $pointermid) {
            if (mb_substr($text1, $pointerstart, $pointermid - $pointerstart) == mb_substr($text2, $pointerstart,
                $pointermid - $pointerstart)
            ) {
                $pointermin = $pointermid;
                $pointerstart = $pointermin;
            } else {
                $pointermax = $pointermid;
            }
            $pointermid = (int)floor(($pointermax - $pointermin) / 2) + $pointermin;
        }

        return $pointermid;
    }

    /**
     * Determine the common suffix of two strings.
     *
     * @param string $text1 First string.
     * @param string $text2 Second string.
     *
     * @return int The number of characters common to the end of each string.
     */
    public function commonSuffix($text1, $text2)
    {
        // Quick check for common null cases.
        if (!$text1 || !$text2 || mb_substr($text1, -1, 1) != mb_substr($text2, -1, 1)) {
            return 0;
        }
        // Binary search.
        // Performance analysis: http://neil.fraser.name/news/2007/10/09/
        $pointermin = 0;
        $pointermax = min(mb_strlen($text1), mb_strlen($text2));
        $pointermid = $pointermax;
        $pointerend = 0;
        while ($pointermin < $pointermid) {
            if (mb_substr($text1, -$pointermid, $pointermid - $pointerend) == mb_substr($text2, -$pointermid,
                $pointermid - $pointerend)
            ) {
                $pointermin = $pointermid;
                $pointerend = $pointermin;
            } else {
                $pointermax = $pointermid;
            }
            $pointermid = (int)floor(($pointermax - $pointermin) / 2) + $pointermin;
        }

        return $pointermid;
    }

    /**
     * Determine if the suffix of one string is the prefix of another.
     *
     * @param string $text1 First string.
     * @param string $text2 Second string.
     *
     * @return int The number of characters common to the end of the first string and the start of the second string.
     */
    public function commontOverlap($text1, $text2)
    {
        // Cache the text lengths to prevent multiple calls.
        $text1_length = mb_strlen($text1);
        $text2_length = mb_strlen($text2);

        // Eliminate the null case.
        if (!$text1_length || !$text2_length) {
            return 0;
        }

        // Truncate the longer string.
        if ($text1_length > $text2_length) {
            $text1 = mb_substr($text1, -$text2_length);
        } elseif ($text1_length < $text2_length) {
            $text2 = mb_substr($text2, 0, $text1_length);
        }
        $text_length = min($text1_length, $text2_length);

        // Quick check for the worst case.
        if ($text1 == $text2) {
            return $text_length;
        }

        // Start by looking for a single character match
        // and increase length until no match is found.
        // Performance analysis: http://neil.fraser.name/news/2010/11/04/
        $best = 0;
        $length = 1;
        while (true) {
            $pattern = mb_substr($text1, -$length);
            $found = strpos($text2, $pattern);
            if ($found === false) {
                break;
            }
            $length += $found;
            if ($found == 0 || mb_substr($text1, -$length) == mb_substr($text2, 0, $length)) {
                $best = $length;
                $length += 1;
            }
        }

        return $best;
    }

    /**
     * Do the two texts share a substring which is at least half the length of the longer text?
     * This speedup can produce non-minimal diffs.
     *
     * @param string $text1 First string.
     * @param string $text2 Second string.
     *
     * @return null|array Five element array, containing the prefix of text1, the suffix of text1,
     * the prefix of text2, the suffix of text2 and the common middle.  Or null if there was no match.
     */
    public function halfMatch($text1, $text2)
    {
        if ($this->timeout <= 0) {
            // Don't risk returning a non-optimal diff if we have unlimited time.
            return null;
        }

        if (mb_strlen($text1) > mb_strlen($text2)) {
            $longtext = $text1;
            $shorttext = $text2;
        } else {
            $shorttext = $text1;
            $longtext = $text2;
        }

        if (mb_strlen($longtext) < 4 || mb_strlen($shorttext) * 2 < mb_strlen(mb_strlen($longtext))) {
            // Pointless
            return null;
        }

        // First check if the second quarter is the seed for a half-match.
        $hm1 = $this->halfMatchI($longtext, $shorttext, floor(mb_strlen($longtext) + 3) / 4);
        // Check again based on the third quarter.
        $hm2 = $this->halfMatchI($longtext, $shorttext, floor(mb_strlen($longtext) + 1) / 2);

        if (empty($hm1) && empty($hm2)) {
            return null;
        } elseif (empty($hm2)) {
            $hm = $hm1;
        } elseif (empty($hm1)) {
            $hm = $hm2;
        } else {
            // Both matched.  Select the longest.
            if (mb_strlen($hm1[4] > $hm2[4])) {
                $hm = $hm1;
            } else {
                $hm = $hm2;
            }
        }

        // A half-match was found, sort out the return data.
        if (mb_strlen($text1) > mb_strlen($text2)) {
            return array($hm[0], $hm[1], $hm[2], $hm[3], $hm[4]);
        } else {
            return array($hm[2], $hm[3], $hm[0], $hm[1], $hm[4]);
        }
    }

    /**
     * Does a substring of shorttext exist within longtext such that the substring
     * is at least half the length of longtext?
     *
     * @param string $longtext  Longer string.
     * @param string $shorttext Shorter string.
     * @param int    $i         Start index of quarter length substring within longtext.
     *
     * @return null|array Five element array, containing the prefix of longtext, the suffix of longtext,
     * the prefix of shorttext, the suffix of shorttext and the common middle.  Or null if there was no match.
     */
    protected function halfMatchI($longtext, $shorttext, $i)
    {
        $seed = mb_substr($longtext, $i, floor(mb_strlen($longtext) / 4));
        $best_common = $best_longtext_a = $best_longtext_b = $best_shorttext_a = $best_shorttext_b = '';

        $j = mb_strpos($shorttext, $seed);
        while ($j !== false) {
            $prefixLegth = $this->commonPrefix(mb_substr($longtext, $i), mb_substr($shorttext, $j));
            $suffixLegth = $this->commonSuffix(mb_substr($longtext, 0, $i), mb_substr($shorttext, 0, $j));
            if (mb_strlen($best_common) < $suffixLegth + $prefixLegth) {
                $best_common = mb_substr($shorttext, $j - $suffixLegth, $suffixLegth) . mb_substr($shorttext, $j,
                    $prefixLegth);
                $best_longtext_a = mb_substr($longtext, 0, $i - $suffixLegth);
                $best_longtext_b = mb_substr($longtext, $i + $prefixLegth);
                $best_shorttext_a = mb_substr($shorttext, 0, $j - $suffixLegth);
                $best_shorttext_b = mb_substr($shorttext, $j + $prefixLegth);
            }
            $j = mb_strpos($shorttext, $seed, $j + 1);
        }


        if (mb_strlen($best_common) * 2 >= mb_strlen($longtext)) {
            return array($best_longtext_a, $best_longtext_b, $best_shorttext_a, $best_shorttext_b, $best_common);
        } else {
            return null;
        }
    }
}
