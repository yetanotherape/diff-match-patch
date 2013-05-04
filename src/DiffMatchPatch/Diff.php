<?php


namespace DiffMatchPatch;

class Diff
{
    // The data structure representing a diff is an array of arrays:
    // [[Diff::DELETE, "Hello"], [Diff::INSERT, "Goodbye"], [Diff::EQUAL, " world."]]
    // which means: delete "Hello", add "Goodbye" and keep " world."
    const DELETE = -1;
    const INSERT = 1;
    const EQUAL  = 0;

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
            $pointermid = (int)(($pointermax - $pointermin) / 2) + $pointermin;
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
            $pointermid = (int)(($pointermax - $pointermin) / 2) + $pointermin;
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
        $hm1 = $this->halfMatchI($longtext, $shorttext, (int)((mb_strlen($longtext) + 3) / 4));
        // Check again based on the third quarter.
        $hm2 = $this->halfMatchI($longtext, $shorttext, (int)((mb_strlen($longtext) + 1) / 2));

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
        $seed = mb_substr($longtext, $i, (int)(mb_strlen($longtext) / 4));
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

    /**
     * Split two texts into an array of strings.  Reduce the texts to a string of hashes where each
     * Unicode character represents one line.
     *
     * @param string $text1 First string.
     * @param string $text2 Second string.
     *
     * @return array Three element array, containing the encoded text1, the encoded text2 and the array
     * of unique strings.  The zeroth element of the array of unique strings is intentionally blank.
     */
    public function linesToChars($text1, $text2)
    {
        // e.g. $lineArray[4] == "Hello\n"
        $lineArray = array();
        // e.g. $lineHash["Hello\n"] == 4
        $lineHash = array();

        // "\x00" is a valid character, but various debuggers don't like it.
        // So we'll insert a junk entry to avoid generating a null character.
        $lineArray[] = '';

        $chars1 = $this->linesToCharsMunge($text1, $lineArray, $lineHash);
        $chars2 = $this->linesToCharsMunge($text2, $lineArray, $lineHash);

        return array($chars1, $chars2, $lineArray);
    }

    /**
     * Split a text into an array of strings.  Reduce the texts to a string of hashes where each
     * Unicode character represents one line.
     * Modifies $lineArray and $lineHash. TODO try to fix it!
     *
     * @param string $text String to encode.
     * @param array  $lineArray
     * @param array  $lineHash
     *
     * @return string Encoded string.
     */
    protected function linesToCharsMunge($text, array &$lineArray, array &$lineHash)
    {
        // Simple string concat is even faster then implode() in PHP.
        $chars = '';

        // TODO optimize code
        // explode('\n', $text) would temporarily double our memory footprint,
        // but mb_strpos() and mb_substr() work slow
        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            if (mb_strlen($line)) {
                if (isset($lines[$i + 1])) {
                    $line .= "\n";
                }
                if (isset($lineHash[$line])) {
                    $chars .= $this->unicodeChr($lineHash[$line]);
                } else {
                    $lineArray[] = $line;
                    $lineHash[$line] = count($lineArray) - 1;
                    $chars .= $this->unicodeChr(count($lineArray) - 1);
                }
            }
        }

        return $chars;

//        // Walk the text, pulling out a substring for each line.
//        // explode('\n', $text) would temporarily double our memory footprint.
//        // Modifying text would create many large strings to garbage collect.
//        $lineStart = 0;
//        $lineEnd = -1;
//        $textLen = mb_strlen($text);
//        while ($lineEnd < $textLen - 1) {
//            $lineEnd = mb_strpos($text, "\n", $lineStart);
//            if ($lineEnd === false) {
//                $lineEnd = $textLen - 1;
//            }
//            $line = mb_substr($text, $lineStart, $lineEnd + 1 - $lineStart);
//            $lineStart = $lineEnd + 1;
//
//            if (isset($lineHash[$line])) {
//                $chars .= $this->unicodeChr($lineHash[$line]);
//            } else {
//                $lineArray[] = $line;
//                $lineHash[$line] = count($lineArray) - 1;
//                $chars .= $this->unicodeChr(count($lineArray) - 1);
//            }
//        }
//
//        return $chars;
    }

    /**
     * Rehydrate the text in a diff from a string of line hashes to real lines of text.
     * Modifies $diffs. TODO try to fix it!
     *
     * @param array $diffs Array of diff arrays.
     * @param array $lineArray Array of unique strings.
     */
    public function charsToLines(&$diffs, $lineArray)
    {
        foreach ($diffs as &$diff) {
            $text = '';
            foreach (preg_split("//u", $diff[1], -1, PREG_SPLIT_NO_EMPTY) as $char) {
                $text .= $lineArray[$this->unicodeOrd($char)];
            }
            $diff[1] = $text;
        }
        unset($diff);
    }

    /**
     * Reorder and merge like edit sections.  Merge equalities.
     * Any edit section can move as long as it doesn't cross an equality.
     * Modifies $diffs. TODO try to fix it!
     *
     * @param array $diffs Array of diff arrays.
     */
    public function cleanupMerge(&$diffs)
    {
        $diffs[] = array(
            self::EQUAL,
            '',
        );

        $pointer = 0;
        $count_delete = 0;
        $count_insert = 0;
        $text_delete = '';
        $text_insert = '';
        while ($pointer < count($diffs)) {
            if ($diffs[$pointer][0] == self::INSERT) {
                $count_insert++;
                $text_insert .= $diffs[$pointer][1];
                $pointer++;
            } elseif ($diffs[$pointer][0] == self::DELETE) {
                $count_delete++;
                $text_delete .= $diffs[$pointer][1];
                $pointer++;
            } elseif ($diffs[$pointer][0] == self::EQUAL) {
                // Upon reaching an equality, check for prior redundancies.
                if ($count_delete + $count_insert > 1) {
                    if ($count_delete != 0 && $count_insert != 0) {
                        // Factor out any common prefixies.
                        $commonlength = $this->commonPrefix($text_insert, $text_delete);
                        if ($commonlength != 0) {
                            $x = $pointer - $count_delete - $count_insert - 1;
                            if ($x >= 0 && $diffs[$x][0] == self::EQUAL) {
                                $diffs[$x][1] .= mb_substr($text_insert, 0, $commonlength);
                            } else {
                                array_unshift($diffs, array(
                                    self::EQUAL,
                                    mb_substr($text_insert, 0, $commonlength),
                                ));
                                $pointer++;
                            }
                            $text_insert = mb_substr($text_insert, $commonlength);
                            $text_delete = mb_substr($text_delete, $commonlength);
                        }
                        // Factor out any common suffixies.
                        $commonlength = $this->commonSuffix($text_insert, $text_delete);
                        if ($commonlength != 0) {
                            $diffs[$pointer][1] = mb_substr($text_insert, -$commonlength) . $diffs[$pointer][1];
                            $text_insert = mb_substr($text_insert, 0, -$commonlength);
                            $text_delete = mb_substr($text_delete, 0, -$commonlength);
                        }
                    }
                    // Delete the offending records and add the merged ones.
                    if ($count_delete == 0) {
                        array_splice($diffs, $pointer - $count_insert, $count_insert, array(array(
                            self::INSERT,
                            $text_insert,
                        )));
                    } elseif ($count_insert == 0) {
                        array_splice($diffs, $pointer - $count_delete, $count_delete, array(array(
                            self::DELETE,
                            $text_delete,
                        )));
                    } else {
                        array_splice($diffs, $pointer - $count_delete - $count_insert, $count_delete + $count_insert, array(
                            array(
                                self::DELETE,
                                $text_delete,
                            ),
                            array(
                                self::INSERT,
                                $text_insert,
                            ),
                        ));
                    }
                    $pointer = $pointer - $count_delete - $count_insert + 1;
                    if ($count_delete != 0) {
                        $pointer += 1;
                    }
                    if ($count_insert != 0) {
                        $pointer += 1;
                    }
                } elseif ($pointer != 0 && $diffs[$pointer - 1][0] == self::EQUAL) {
                    // Merge this equality with the previous one.
                    $diffs[$pointer - 1][1] .= $diffs[$pointer][1];
                    array_splice($diffs, $pointer, 1);
                } else {
                    $pointer++;
                }
                $count_delete = 0;
                $count_insert = 0;
                $text_delete = '';
                $text_insert = '';
            }
        }

        if ($diffs[count($diffs) - 1][1] == '') {
            array_pop($diffs);
        }

        // Second pass: look for single edits surrounded on both sides by equalities
        // which can be shifted sideways to eliminate an equality.
        // e.g: A<ins>BA</ins>C -> <ins>AB</ins>AC
        $changes = false;
        $pointer = 1;
        // Intentionally ignore the first and last element (don't need checking).
        while ($pointer < count($diffs) - 1) {
            if ($diffs[$pointer - 1][0] == self::EQUAL && $diffs[$pointer + 1][0] == self::EQUAL) {
                // This is a single edit surrounded by equalities.
                if (mb_substr($diffs[$pointer][1], -mb_strlen($diffs[$pointer - 1][1])) == $diffs[$pointer - 1][1]) {
                    // Shift the edit over the previous equality.
                    $diffs[$pointer][1] = $diffs[$pointer - 1][1] . mb_substr($diffs[$pointer][1], 0, -mb_strlen($diffs[$pointer - 1][1]));
                    $diffs[$pointer + 1][1] = $diffs[$pointer - 1][1] . $diffs[$pointer + 1][1];
                    array_splice($diffs, $pointer - 1, 1);
                    $changes = true;
                } elseif (mb_substr($diffs[$pointer][1], 0, mb_strlen($diffs[$pointer + 1][1])) == $diffs[$pointer + 1][1]) {
                    // Shift the edit over the next equality.
                    $diffs[$pointer - 1][1] = $diffs[$pointer - 1][1] . $diffs[$pointer + 1][1];
                    $diffs[$pointer][1] = mb_substr($diffs[$pointer][1], mb_strlen($diffs[$pointer + 1][1])) . $diffs[$pointer + 1][1];
                    array_splice($diffs, $pointer + 1, 1);
                    $changes = true;
                }
            }
            $pointer++;
        }

        // If shifts were made, the diff needs reordering and another shift sweep.
        if ($changes) {
            $this->cleanupMerge($diffs);
        }
    }

    /**
     * Look for single edits surrounded on both sides by equalities
     * which can be shifted sideways to align the edit to a word boundary.
     * e.g: The c<ins>at c</ins>ame. -> The <ins>cat </ins>came.
     * Modifies $diffs. TODO try to fix it!
     *
     * @param array $diffs Array of diff arrays.
     */
    public function cleanupSemanticLossless(&$diffs)
    {
        $pointer = 1;
        // Intentionally ignore the first and last element (don't need checking).
        while ($pointer < count($diffs) - 1) {
            if ($diffs[$pointer - 1][0] == self::EQUAL && $diffs[$pointer + 1][0] == self::EQUAL) {
                // This is a single edit surrounded by equalities.
                $equality1 = $diffs[$pointer - 1][1];
                $edit = $diffs[$pointer][1];
                $equality2 = $diffs[$pointer + 1][1];

                // First, shift the edit as far left as possible.
                $commonOffset = $this->commonSuffix($equality1, $edit);
                if ($commonOffset) {
                    $commonString = mb_substr($edit, -$commonOffset);
                    $equality1 = mb_substr($equality1, 0, -$commonOffset);
                    $edit = $commonString . mb_substr($edit, 0, -$commonOffset);
                    $equality2 = $commonString . $equality2;
                }

                // Second, step character by character right, looking for the best fit.
                $bestEquality1 = $equality1;
                $bestEdit = $edit;
                $bestEquality2 = $equality2;
                $bestScore = $this->cleanupSemanticScore($equality1, $edit) + $this->cleanupSemanticScore($edit, $equality2);
                while ($edit && $equality2 && mb_substr($edit, 0, 1) == mb_substr($equality2, 0, 1)) {
                    $equality1 .= mb_substr($edit, 0, 1);
                    $edit = mb_substr($edit, 1) . mb_substr($equality2, 0, 1);
                    $equality2 = mb_substr($equality2, 1);
                    $score = $this->cleanupSemanticScore($equality1, $edit) + $this->cleanupSemanticScore($edit, $equality2);
                    // The >= encourages trailing rather than leading whitespace on edits.
                    if ($score >= $bestScore) {
                        $bestScore = $score;
                        $bestEquality1 = $equality1;
                        $bestEdit = $edit;
                        $bestEquality2 = $equality2;
                    }
                }
                if ($diffs[$pointer - 1][1] != $bestEquality1) {
                    // We have an improvement, save it back to the diff.
                    if ($bestEquality1) {
                        $diffs[$pointer - 1][1] = $bestEquality1;
                    } else {
                        array_splice($diffs, $pointer - 1, 1);
                        $pointer -= 1;
                    }
                    $diffs[$pointer][1] = $bestEdit;
                    if ($bestEquality2) {
                        $diffs[$pointer + 1][1] = $bestEquality2;
                    } else {
                        array_splice($diffs, $pointer + 1, 1);
                        $pointer -= 1;
                    }
                }
            }
            $pointer++;
        }
    }

    /**
     * Given two strings, compute a score representing whether the internal boundary falls on logical boundaries.
     * Scores range from 6 (best) to 0 (worst).
     *
     * @param string $one First string.
     * @param string $two Second string.
     *
     * @return int The score.
     */
    protected function cleanupSemanticScore($one, $two)
    {
        if (!$one || !$two) {
            // Edges are the best.
            return 6;
        }

        // Each port of this function behaves slightly differently due to
        // subtle differences in each language's definition of things like
        // 'whitespace'.  Since this function's purpose is largely cosmetic,
        // the choice has been made to use each language's native features
        // rather than force total conformity.
        $char1 = mb_substr($one, -1, 1);
        $char2 = mb_substr($two, 0, 1);
        $nonAlphaNumeric1 = preg_match('/[^[:alnum:]]/u', $char1);
        $nonAlphaNumeric2 = preg_match('/[^[:alnum:]]/u', $char2);
        $whitespace1 = $nonAlphaNumeric1 && preg_match('/\s/', $char1);
        $whitespace2 = $nonAlphaNumeric2 && preg_match('/\s/', $char2);
        $lineBreak1 = $whitespace1 && preg_match('/[\r\n]/', $char1);
        $lineBreak2 = $whitespace2 && preg_match('/[\r\n]/', $char2);
        $blankLine1 = $lineBreak1 && preg_match('/\n\r?\n$/', $one);
        $blankLine2 = $lineBreak2 && preg_match('/^\r?\n\r?\n/', $two);

        if ($blankLine1 || $blankLine2) {
            // Five points for blank lines.
            return 5;
        } elseif ($lineBreak1 || $lineBreak2) {
            // Four points for line breaks.
            return 4;
        } elseif ($nonAlphaNumeric1 && !$whitespace1 && $whitespace2) {
            // Three points for end of sentences.
            return 3;
        } elseif ($whitespace1 || $whitespace2) {
            // Two points for whitespace.
            return 2;
        } elseif ($nonAlphaNumeric1 || $nonAlphaNumeric2) {
            // One point for non-alphanumeric.
            return 1;
        }

        return 0;
    }

    /**
     * Reduce the number of edits by eliminating semantically trivial equalities.
     * Modifies $diffs. TODO try to fix it!
     * TODO refactor this cap's code
     *
     * @param array $diffs Array of diff arrays.
     */
    public function cleanupSemantic(&$diffs)
    {
        $changes = false;
        // Stack of indices where equalities are found.
        $equalities = array();
        // Always equal to diffs[equalities[-1]][1]
        $lastequality = null;
        // Index of current position.
        $pointer = 0;
        // Number of chars that changed prior to the equality.
        $length_insertions1 = 0;
        $length_deletions1 = 0;
        // Number of chars that changed after the equality.
        $length_insertions2 = 0;
        $length_deletions2 = 0;

        while ($pointer < count($diffs)) {
            if ($diffs[$pointer][0] == self::EQUAL) {
                $equalities[] = $pointer;
                $length_insertions1 = $length_insertions2;
                $length_insertions2 = 0;
                $length_deletions1 = $length_deletions2;
                $length_deletions2 = 0;
                $lastequality = $diffs[$pointer][1];
            } else {
                if ($diffs[$pointer][0] == self::INSERT) {
                    $length_insertions2 += mb_strlen($diffs[$pointer][1]);
                } else {
                    $length_deletions2 += mb_strlen($diffs[$pointer][1]);
                }
                // Eliminate an equality that is smaller or equal to the edits on both sides of it.
                if (
                    $lastequality
                    && mb_strlen($lastequality) <= max($length_insertions1, $length_deletions1)
                    && mb_strlen($lastequality) <= max($length_insertions2, $length_deletions2)
                ) {
                    $insertPointer = array_pop($equalities);
                    // Duplicate record.
                    array_splice($diffs, $insertPointer, 0, array(array(
                       self::DELETE,
                       $lastequality,
                    )));
                    // Change second copy to insert.
                    $diffs[$insertPointer + 1][0] = self::INSERT;
                    // Throw away the previous equality (it needs to be reevaluated).
                    if (count($equalities)) {
                        array_pop($equalities);
                    }
                    if (count($equalities)) {
                        $pointer = end($equalities);
                    } else {
                        $pointer = -1;
                    }
                    // Reset the counters.
                    $length_insertions1 = 0;
                    $length_deletions1 = 0;
                    $length_insertions2 = 0;
                    $length_deletions2 = 0;
                    $lastequality = null;
                    $changes = true;
                }
            }
            $pointer++;
        }
        // Normalize the diff.
        if ($changes) {
            $this->cleanupMerge($diffs);
        }
        $this->cleanupSemanticLossless($diffs);

        // Find any overlaps between deletions and insertions.
        // e.g: <del>abcxxx</del><ins>xxxdef</ins>
        //   -> <del>abc</del>xxx<ins>def</ins>
        // e.g: <del>xxxabc</del><ins>defxxx</ins>
        //   -> <ins>def</ins>xxx<del>abc</del>
        // Only extract an overlap if it is as big as the edit ahead or behind it.

        $pointer = 1;
        while ($pointer < count($diffs)) {
            if ($diffs[$pointer - 1][0] == self::DELETE && $diffs[$pointer][0] == self::INSERT) {
                $deletion = $diffs[$pointer - 1][1];
                $insertion = $diffs[$pointer][1];
                $overlap_length1 = $this->commontOverlap($deletion, $insertion);
                $overlap_length2 = $this->commontOverlap($insertion, $deletion);

                if ($overlap_length1 >= $overlap_length2) {
                    if ($overlap_length1 >= mb_strlen($deletion) / 2 || $overlap_length1 >= mb_strlen($insertion) / 2) {
                        // Overlap found. Insert an equality and trim the surrounding edits.
                        array_splice($diffs, $pointer, 0, array(array(
                            self::EQUAL,
                            mb_substr($insertion, 0, $overlap_length1),
                        )));
                        $diffs[$pointer - 1][1] = mb_substr($deletion, 0, -$overlap_length1);
                        $diffs[$pointer + 1][1] = mb_substr($insertion, $overlap_length1);
                        $pointer++;
                    }
                } else {
                    if ($overlap_length2 >= mb_strlen($deletion) / 2 || $overlap_length2 >= mb_strlen($insertion) / 2) {
                        // Reverse overlap found.
                        // Insert an equality and swap and trim the surrounding edits.
                        array_splice($diffs, $pointer, 0, array(array(
                            self::EQUAL,
                            mb_substr($deletion, 0, $overlap_length2),
                        )));
                        $diffs[$pointer - 1] = array(
                            self::INSERT,
                            mb_substr($insertion, 0, $overlap_length2),
                        );
                        $diffs[$pointer + 1] = array(
                            self::DELETE,
                            mb_substr($deletion, $overlap_length2),
                        );
                        $pointer++;
                    }
                }
                $pointer++;
            }
            $pointer++;
        }
    }

    /**
     * Reduce the number of edits by eliminating operationally trivial equalities.
     * Modifies $diffs. TODO try to fix it!
     * TODO refactor this Cap's code
     *
     * @param array $diffs Array of diff arrays.
     */
    public function cleanupEfficiency(&$diffs) {
        $changes = false;
        // Stack of indices where equalities are found.
        $equalities = array();
        // Always equal to diffs[equalities[-1]][1]
        $lastequality = null;
        // Index of current position.
        $pointer = 0;
        // Is there an insertion operation before the last equality.
        $pre_ins = false;
        // Is there a deletion operation before the last equality.
        $pre_del = false;
        // Is there an insertion operation after the last equality.
        $post_ins = false;
        // Is there a deletion operation after the last equality.
        $post_del = false;

        while ($pointer < count($diffs)) {
            if ($diffs[$pointer][0] == self::EQUAL) {
                if (mb_strlen($diffs[$pointer][1]) < $this->getEditCost() && ($post_ins || $post_del)) {
                    // Candidate found.
                    $equalities[] = $pointer;
                    $pre_ins = $post_ins;
                    $pre_del = $post_del;
                    $lastequality = $diffs[$pointer][1];
                } else {
                    // Not a candidate, and can never become one.
                    $equalities = array();
                    $lastequality = null;
                }
                $post_ins = false;
                $post_del = false;
            } else {
                if ($diffs[$pointer][0] == self::DELETE) {
                    $post_del = true;
                } else {
                    $post_ins = true;
                }

                // Five types to be split:
                // <ins>A</ins><del>B</del>XY<ins>C</ins><del>D</del>
                // <ins>A</ins>X<ins>C</ins><del>D</del>
                // <ins>A</ins><del>B</del>X<ins>C</ins>
                // <ins>A</del>X<ins>C</ins><del>D</del>
                // <ins>A</ins><del>B</del>X<del>C</del>
                // TODO refactor condition
                if (
                    $lastequality
                    && (
                        ($pre_ins && $pre_del && $post_ins && $post_del)
                        || (
                            mb_strlen($lastequality) < $this->getEditCost() / 2
                            && ($pre_ins + $pre_del + $post_del + $post_ins == 3)
                        )
                    )
                ) {
                    $insertPointer = array_pop($equalities);
                    // Duplicate record.
                    array_splice($diffs, $insertPointer, 0, array(array(
                        self::DELETE,
                        $lastequality,
                    )));
                    // Change second copy to insert.
                    $diffs[$insertPointer + 1][0] = self::INSERT;
                    // Throw away the previous equality (it needs to be reevaluated).
                    if (count($equalities)) {
                        array_pop($equalities);
                    }
                    $lastequality = null;
                    if ($pre_ins && $pre_del) {
                        // No changes made which could affect previous entry, keep going.
                        $post_ins = true;
                        $post_del = true;
                        $equalities = array();
                    } else {
                        if (count($equalities)) {
                            // Throw away the previous equality.
                            array_pop($equalities);
                        }
                        if (count($equalities)) {
                            $pointer = end($equalities);
                        } else {
                            $pointer = -1;
                        }
                        $post_ins = false;
                        $post_del = false;
                    }
                    $changes = true;
                }
            }
            $pointer++;
        }
        if ($changes) {
            $this->cleanupMerge($diffs);
        }
    }

    /**
     * Convert a diff array into a pretty HTML report.
     *
     * @param array $diffs Array of diff arrays.
     *
     * @return string HTML representation.
     */
    public function prettyHtml($diffs)
    {
        $html = '';
        foreach ($diffs as $change) {
            $op = $change[0];
            $data = $change[1];
            $text = str_replace(array(
                '&', '<', '>', "\n",
            ), array(
                '&amp;', '&lt;', '&gt;', '&para;<br>',
            ), $data);

            if ($op == self::INSERT) {
                $html .= '<ins style="background:#e6ffe6;">' . $text . '</ins>';
            } elseif ($op == self::DELETE) {
                $html .= '<del style="background:#ffe6e6;">' . $text . '</del>';
            } else {
                $html .= '<span>' . $text . '</span>';
            }
        }

        return $html;
    }

    /**
     * Compute and return the source text (all equalities and deletions).
     *
     * @param array $diffs Array of diff arrays.
     *
     * @return string Source text.
     */
    public function text1($diffs){
        $text = '';
        foreach ($diffs as $change) {
            $op = $change[0];
            $data = $change[1];

            if ($op != self::INSERT) {
                $text .= $data;
            }
        }

        return $text;
    }

    /**
     * Compute and return the destination text (all equalities and insertions).
     *
     * @param array $diffs Array of diff arrays.
     *
     * @return string Destination text.
     */
    public function text2($diffs){
        $text = '';
        foreach ($diffs as $change) {
            $op = $change[0];
            $data = $change[1];

            if ($op != self::DELETE) {
                $text .= $data;
            }
        }

        return $text;
    }

    /**
     * Crush the diff into an encoded string which describes the operations
     * required to transform text1 into text2.
     * E.g. =3\t-2\t+ing  -> Keep 3 chars, delete 2 chars, insert 'ing'.
     * Operations are tab-separated.  Inserted text is escaped using %xx notation.
     *
     * @param array $diffs Array of diff arrays.
     *
     * @return string Delta text.
     */
    public function toDelta($diffs) {
        $text = array();
        foreach ($diffs as $change) {
            $op = $change[0];
            $data = $change[1];

            if ($op == self::INSERT) {
                $text[] = '+'. $this->encodeString($data);
            } elseif ($op == self::DELETE) {
                $text[] = '-' . mb_strlen($data);
            } else {
                $text[] = '=' . mb_strlen($data);
            }
        }

        return implode("\t", $text);
    }

    /**
     * Given the original text1, and an encoded string which describes the
     * operations required to transform text1 into text2, compute the full diff.
     *
     * @param string $text1 Source string for the diff.
     * @param string $delta Delta text.
     *
     * @throws \InvalidArgumentException If invalid input. TODO create exception class
     * @return array Array of diff arrays.
     */
    public function fromDelta($text1, $delta)
    {
        $diffs = array();
        // Cursor in text1
        $pointer = 0;
        $tokens = explode("\t", $delta);
        foreach ($tokens as $token) {
            if ($token == '') {
                // Blank tokens are ok (from a trailing \t).
                continue;
            }
            // Each token begins with a one character parameter which specifies the
            // operation of this token (delete, insert, equality).
            $op = mb_substr($token, 0, 1);
            $param = mb_substr($token, 1);
            switch ($op) {
                case '+':
                    $diffs[] = array(
                        self::INSERT,
                        $this->decodeString($param),
                    );
                    break;
                case '-':
                case '=':
                    if (!is_numeric($param)) {
                        throw new \InvalidArgumentException('Invalid number in delta: ' . $param);
                    } elseif ($param < 0) {
                        throw new \InvalidArgumentException('Negative number in delta: ' . $param);
                    } else {
                        $n = (int) $param;
                    }
                    $text = mb_substr($text1, $pointer, $n);
                    $pointer += $n;
                    $diffs[] = array(
                        $op == '=' ? self::EQUAL : self::DELETE,
                        $text,
                    );
                    break;
                default:
                    // Anything else is an error.
                    throw new \InvalidArgumentException('Invalid diff operation in delta: ' . $op);
            }
        }
        if ($pointer != mb_strlen($text1)) {
            throw new \InvalidArgumentException('Delta length (' . $pointer . ') does not equal source text length (' . mb_strlen($text1) . ').');
        }

        return $diffs;
    }

    /**
     * Compute and return location in text2 equivalent to the $loc in text1.
     * e.g. "The cat" vs "The big cat", 1->1, 5->8
     *
     * @param array $diffs Array of diff arrays.
     * @param int $loc Location within text1.
     *
     * @return int Location within text2.
     */
    public function xIndex($diffs, $loc)
    {
        $chars1 = 0;
        $chars2 = 0;
        $last_chars1 = 0;
        $last_chars2 = 0;

        $i = 0;
        foreach ($diffs as $change) {
            $op = $change[0];
            $text = $change[1];
            // Equality or deletion.
            if ($op != self::INSERT) {
                $chars1 += mb_strlen($text);
            }
            // Equality or insertion.
            if ($op != self::DELETE) {
                $chars2 += mb_strlen($text);
            }
            // Overshot the location.
            if ($chars1 > $loc) {
                break;
            }
            $last_chars1 = $chars1;
            $last_chars2 = $chars2;
            $i++;
        }

        // The location was deleted.
        if (count($diffs) != $i && $diffs[$i][0] == self::DELETE) {
            return $last_chars2;
        }
        return $loc + $last_chars2 - $last_chars1;
    }

    /**
     * Compute the Levenshtein distance; the number of inserted, deleted or substituted characters.
     *
     * @param array $diffs Array of diff arrays.
     *
     * @return int Number of changes.
     */
    public function levenshtein($diffs){
        $levenshtein = 0;
        $insertions = 0;
        $deletions = 0;

        foreach ($diffs as $change) {
            $op = $change[0];
            $text = $change[1];

            switch ($op) {
                case self::INSERT:
                    $insertions += mb_strlen($text);
                    break;
                case self::DELETE:
                    $deletions += mb_strlen($text);
                    break;
                case self::EQUAL:
                    // A deletion and an insertion is one substitution.
                    $levenshtein += max($insertions, $deletions);
                    $insertions = 0;
                    $deletions = 0;
                    break;
            }
        }
        $levenshtein += max($insertions, $deletions);

        return $levenshtein;
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
     * @param int    $deadline   Optional time when the diff should be complete by.  Used internally for recursive calls.
     *                           Users should set $this->timeout instead.
     *
     * @throws \InvalidArgumentException If texts is null. TODO create exception class
     * @return array Array of changes.
     */
    public function main($text1, $text2, $checklines = true, $deadline = null)
    {
        // Set a deadline by which time the diff must be complete.
        if (!isset($deadline)) {
            if ($this->getTimeout() <= 0) {
                $deadline  = PHP_INT_MAX;
            } else {
                $deadline = microtime(1) + $this->getTimeout();
            }
        }

        if (!isset($text1, $text2)) {
            throw new \InvalidArgumentException();
        }

        // Check for equality (speedup).
        if ($text1 == $text2) {
            if ($text1) {
                return array(
                    array(self::EQUAL, $text1),
                );
            }
            return array();
        }

        // Trim off common prefix (speedup).
        $commonLength = $this->commonPrefix($text1, $text2);
        if ($commonLength == 0) {
            $commonPrefix = '';
        } else {
            $commonPrefix = mb_substr($text1, 0, $commonLength);
            $text1 = mb_substr($text1, $commonLength);
            $text2 = mb_substr($text2, $commonLength);
        }

        // Trim off common suffix (speedup).
        $commonLength = $this->commonSuffix($text1, $text2);
        if ($commonLength == 0) {
            $commonSuffix = '';
        } else {
            $commonSuffix = mb_substr($text1, -$commonLength);
            $text1 = mb_substr($text1, 0, -$commonLength);
            $text2 = mb_substr($text2, 0, -$commonLength);
        }

        // Compute the diff on the middle block.
        $diffs = $this->compute($text1, $text2, $checklines, $deadline);

        // Restore the prefix and suffix.
        if ($commonPrefix) {
            array_unshift($diffs, array(self::EQUAL, $commonPrefix));
        }
        if ($commonSuffix) {
            array_push($diffs, array(self::EQUAL, $commonSuffix));
        }

        $this->cleanupMerge($diffs);

        return $diffs;
    }

    /**
     * Find the differences between two texts.  Assumes that the texts do not
     * have any common prefix or suffix.
     *
     * @param string $text1         Old string to be diffed.
     * @param string $text2         New string to be diffed.
     * @param bool   $checklines    Speedup flag.  If false, then don't run a line-level diff
     *                              first to identify the changed areas.
     *                              If true, then run a faster, slightly less optimal diff.
     * @param int    $deadline      Time when the diff should be complete by.
     *
     * @return array Array of changes.
     */
    protected function compute($text1, $text2, $checklines, $deadline)
    {
        if (!$text1) {
            // Just add some text (speedup).
            return array(
                array(self::INSERT, $text2),
            );
        }

        if (!$text2) {
            // Just delete some text (speedup).
            return array(
                array(self::DELETE, $text1),
            );
        }

        if (mb_strlen($text1) < mb_strlen($text2)) {
            $shortText = $text1;
            $longText = $text2;
        } else {
            $shortText = $text2;
            $longText = $text1;
        }

        $i = mb_strpos($longText, $shortText);
        if ($i !== false) {
            // Shorter text is inside the longer text (speedup).
            $diffs = array(
                array(self::INSERT, mb_substr($longText, 0, $i)),
                array(self::EQUAL, $shortText),
                array(self::INSERT, mb_substr($longText, $i + mb_strlen($shortText))),
            );
            // Swap insertions for deletions if diff is reversed.
            if (mb_strlen($text2) < mb_strlen($text1)) {
                $diffs[0][0] = self::DELETE;
                $diffs[2][0] = self::DELETE;
            }
            return $diffs;
        }

        if (mb_strlen($shortText) == 1) {
            // Single character string.
            // After the previous speedup, the character can't be an equality.
            $diffs = array(
                array(self::DELETE, $text1),
                array(self::INSERT, $text2),
            );
            return $diffs;
        }

        // Check to see if the problem can be split in two.
        $hm = $this->halfMatch($text1, $text2);
        if ($hm) {
            // A half-match was found, sort out the return data.
            list($text1_a, $text1_b, $text2_a, $text2_b, $mid_common) = $hm;
            // Send both pairs off for separate processing.
            $diffs_a = $this->main($text1_a, $text2_a, $checklines, $deadline);
            $diffs_b = $this->main($text1_b, $text2_b, $checklines, $deadline);
            // Merge the results.
            $diffs = array_merge(
                $diffs_a,
                array(
                    array(self::EQUAL, $mid_common),
                ),
                $diffs_b
            );
            return $diffs;
        }

        if ($checklines && mb_strlen($text1) > 100 && mb_strlen($text2) > 100) {
            return $this->lineMode($text1, $text2, $deadline);
        }

        return $this->bisect($text1, $text2, $deadline);
    }

    /**
     * Do a quick line-level diff on both strings, then rediff the parts for greater accuracy.
     * This speedup can produce non-minimal diffs.
     *
     * @param string $text1    Old string to be diffed.
     * @param string $text2    New string to be diffed.
     * @param int    $deadline Time when the diff should be complete by.
     *
     * @return array Array of changes.
     */
    protected function lineMode($text1, $text2, $deadline)
    {
        // Scan the text on a line-by-line basis first.
        list($text1, $text2, $lineArray) = $this->linesToChars($text1, $text2);

        $diffs = $this->main($text1, $text2, false, $deadline);

        // Convert the diff back to original text.
        $this->charsToLines($diffs, $lineArray);

        // Eliminate freak matches (e.g. blank lines)
        $this->cleanupSemantic($diffs);

        // Rediff any replacement blocks, this time character-by-character.
        // Add a dummy entry at the end.
        array_push($diffs, array(self::EQUAL, ''));
        $pointer = 0;
        $countDelete = 0;
        $countInsert = 0;
        $textDelete = '';
        $textInsert = '';

        while ($pointer < count($diffs)) {
            switch ($diffs[$pointer][0]) {
                case self::DELETE:
                    $countDelete++;
                    $textDelete .= $diffs[$pointer][1];
                    break;
                case self::INSERT:
                $countInsert++;
                    $textInsert .= $diffs[$pointer][1];
                    break;
                case self::EQUAL:
                    // Upon reaching an equality, check for prior redundancies.
                    if ($countDelete > 0 && $countInsert > 0) {
                        // Delete the offending records and add the merged ones.
                        $a = $this->main($textDelete, $textInsert, false, $deadline);
                        array_splice($diffs, $pointer - $countDelete - $countInsert, $countDelete + $countInsert, $a);
                        $pointer = $pointer - $countDelete - $countInsert + count($a);
                    }
                    $countDelete = 0;
                    $countInsert = 0;
                    $textDelete = '';
                    $textInsert = '';
                    break;
            }
            $pointer++;
        }

        // Remove the dummy entry at the end.
        array_pop($diffs);

        return $diffs;
    }

    /**
     * Find the 'middle snake' of a diff, split the problem in two
     * and return the recursively constructed diff.
     * See Myers 1986 paper: An O(ND) Difference Algorithm and Its Variations.
     *
     * @param string $text1    Old string to be diffed.
     * @param string $text2    New string to be diffed.
     * @param int    $deadline Time at which to bail if not yet complete.
     *
     * @return array Array of diff arrays.
     */
    public function bisect($text1, $text2, $deadline)
    {
        // Cache the text lengths to prevent multiple calls.
        $text1Length = mb_strlen($text1);
        $text2Length = mb_strlen($text2);
        $maxD = (int)(($text1Length + $text2Length + 1) / 2);
        $vOffset = $maxD;
        $vLength = 2 * $maxD;
        $v1 = array_fill(0, $vLength, -1);
        $v1[$vOffset + 1] = 0;
        $v2 = $v1;
        $delta = $text1Length - $text2Length;

        // If the total number of characters is odd, then the front path will collide with the reverse path.
        $front = $delta % 2 != 0;

        // Offsets for start and end of k loop.
        // Prevents mapping of space beyond the grid.
        $k1Start = 0;
        $k1End = 0;
        $k2Start = 0;
        $k2End = 0;

        for ($d = 0; $d < $maxD; $d++) {
            // Bail out if deadline is reached.
            if (microtime(1) > $deadline) {
                break;
            }

            // Walk the front path one step.
            for ($k1 = -$d + $k1Start; $k1 < $d + 1 - $k1End; $k1 += 2) {
                $k1Offset = $vOffset + $k1;
                if ($k1 == -$d || ($k1 != $d && $v1[$k1Offset - 1] < $v1[$k1Offset + 1])) {
                    $x1 = $v1[$k1Offset + 1];
                } else {
                    $x1 = $v1[$k1Offset - 1] + 1;
                }
                $y1 = $x1 - $k1;
                while ($x1 < $text1Length && $y1 < $text2Length && mb_substr($text1, $x1, 1) == mb_substr($text2, $y1, 1)) {
                    $x1++;
                    $y1++;
                }
                $v1[$k1Offset] = $x1;
                if ($x1 > $text1Length) {
                    // Ran off the right of the graph.
                    $k1End += 2;
                } elseif ($y1 > $text2Length) {
                    // Ran off the bottom of the graph.
                    $k1Start += 2;
                } elseif ($front) {
                    $k2Offset = $vOffset + $delta - $k1;
                    if ($k2Offset >= 0 && $k2Offset < $vLength && $v2[$k2Offset] != -1) {
                        // Mirror x2 onto top-left coordinate system.
                        $x2 = $text1Length - $v2[$k2Offset];
                        if ($x1 >= $x2) {
                            // Overlap detected.
                            return $this->bisectSplit($text1, $text2, $x1, $y1, $deadline);
                        }
                    }
                }
            }

            // Walk the reverse path one step.
            for ($k2 = -$d + $k2Start; $k2 < $d + 1 - $k2End; $k2 += 2) {
                $k2Offset = $vOffset + $k2;
                if ($k2 == -$d || ($k2 != $d && $v2[$k2Offset - 1] < $v2[$k2Offset + 1])) {
                    $x2 = $v2[$k2Offset + 1];
                } else {
                    $x2 = $v2[$k2Offset - 1] + 1;
                }
                $y2 = $x2 - $k2;
                while ($x2 < $text1Length && $y2 < $text2Length && mb_substr($text1, -$x2 - 1, 1) == mb_substr($text2, -$y2 - 1, 1)) {
                    $x2++;
                    $y2++;
                }
                $v2[$k2Offset] = $x2;
                if ($x2 > $text1Length) {
                    // Ran off the right of the graph.
                    $k2End += 2;
                } elseif ($y2 > $text2Length) {
                    // Ran off the bottom of the graph.
                    $k2Start += 2;
                } elseif (!$front) {
                    $k1Offset = $vOffset + $delta - $k2;
                    if ($k1Offset >= 0 && $k1Offset < $vLength && $v1[$k1Offset] != -1) {
                        $x1 = $v1[$k1Offset];
                        $y1 = $vOffset + $x1 - $k1Offset;
                        // Mirror x2 onto top-left coordinate system.
                        $x2 = $text1Length - $x2;
                        if ($x1 >= $x2) {
                            // Overlap detected.
                            return $this->bisectSplit($text1, $text2, $x1, $y1, $deadline);
                        }
                    }
                }
            }
        }
        // Diff took too long and hit the deadline or
        // number of diffs equals number of characters, no commonality at all.
        return array(
          array(self::DELETE, $text1),
          array(self::INSERT, $text2),
        );
    }

    /**
     * Given the location of the 'middle snake', split the diff in two parts and recurse.
     *
     * @param string $text1    Old string to be diffed.
     * @param string $text2    New string to be diffed.
     * @param int    $x        Index of split point in text1.
     * @param int    $y        Index of split point in text2.
     * @param int    $deadline Time at which to bail if not yet complete.
     *
     * @return array Array of diff arrays.
     */
    protected function bisectSplit($text1, $text2, $x, $y, $deadline)
    {
        $text1A = mb_substr($text1, 0, $x);
        $text2A = mb_substr($text2, 0, $y);
        $text1B = mb_substr($text1, $x);
        $text2B = mb_substr($text2, $y);

        // Compute both diffs serially.
        $diffsA = $this->main($text1A, $text2A, false, $deadline);
        $diffsB = $this->main($text1B, $text2B, false, $deadline);

        return array_merge($diffsA, $diffsB);
    }

    /**
     * Multibyte replacement for standard chr()
     *
     * @param int $code Character code.
     *
     * @return string Char with given code in UTF-8.
     */
    public function unicodeChr($code) {
        // TODO this works by order of magnitude slower then chr() and limit
        return mb_convert_encoding("&#{$code};", 'UTF-8', 'HTML-ENTITIES');
    }

    /**
     * Multibyte replacement for standard ord()
     *
     * @param string $char Char in UTF-8
     *
     * @return int Code of given char.
     */
    public function unicodeOrd($char) {
        $twoByteChar = mb_convert_encoding($char, 'UCS-2LE', 'UTF-8');
        return ord($twoByteChar[0]) + 256 * ord($twoByteChar[1]);
    }

    public function encodeString($string)
    {
        $string = rawurlencode($string);
        $string = strtr($string, array (
            '%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')', '%3B' => ';', '%2F' => '/', '%3F' => '?',
            '%3A' => ':', '%40' => '@', '%26' => '&', '%3D' => '=', '%2B' => '+', '%24' => '$', '%2C' => ',', '%23' => '#', '%20' => ' '
        ));
        return $string;
    }

    public function decodeString($string)
    {
        $string = strtr($string, array (
            '%21' => '%2521', '%2A' => '%252A', '%27' => "%2527", '%28' => '%2528', '%29' => '%2529', '%3B' => '%253B', '%2F' => '%252F', '%3F' => '%253F',
            '%3A' => '%253A', '%40' => '%2540', '%26' => '%2526', '%3D' => '%253D', '%2B' => '%252B', '%24' => '%2524', '%2C' => '%252C', '%23' => '%2523', '%20' => '%2520'
        ));
        $string = rawurldecode($string);
        return $string;
    }

//    function charCodeAt($str, $pos) {
//        return mb_ord(mb_substr($str, $pos, 1));
//    }
}
