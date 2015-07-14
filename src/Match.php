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
 * Match offers methods for fuzzy search string in a block of plain text.
 *
 * @package DiffMatchPatch
 * @author Neil Fraser <fraser@google.com>
 * @author Daniil Skrobov <yetanotherape@gmail.com>
 */
class Match {

    /**
     * @var float At what point is no match declared (0.0 = perfection, 1.0 = very loose).
     */
    protected $threshold = 0.5;
    /**
     * @var int How far to search for a match (0 = exact location, 1000+ = broad match).
     * A match this many characters away from the expected location will add
     * 1.0 to the score (0.0 is a perfect match).
     */
    protected $distance = 1000;
    /**
     * @var int The number of bits in an int.
     */
    protected $maxBits;

    public function __construct()
    {
        // PHP_INT_SIZE == 4 for 32bit platform, and 8 — for 64bit
        $this->maxBits = PHP_INT_SIZE * 8;
    }


    /**
     * @return float
     */
    public function getThreshold()
    {
        return $this->threshold;
    }

    /**
     * @param float $threshold
     */
    public function setThreshold($threshold)
    {
        $this->threshold = $threshold;
    }

    /**
     * @return int
     */
    public function getDistance()
    {
        return $this->distance;
    }

    /**
     * @param int $distance
     */
    public function setDistance($distance)
    {
        $this->distance = $distance;
    }

    /**
     * @return int
     */
    public function getMaxBits()
    {
        return $this->maxBits;
    }

    /**
     * @param int $maxBits
     *
     * @throws \RangeException If param greater than number of bits in int.
     */
    public function setMaxBits($maxBits)
    {
        if ($maxBits <= PHP_INT_SIZE * 8) {
            $this->maxBits = $maxBits;
        } else {
            throw new \RangeException('Param greater than number of bits in int');
        }
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
    public function main($text, $pattern, $loc = 0){
        // Check for null inputs.
        if (!isset($text, $pattern)) {
            throw new \InvalidArgumentException("Null inputs.");
        }

        $loc = max(0, min($loc, mb_strlen($text)));
        if ($text == $pattern) {
            // Shortcut (potentially not guaranteed by the algorithm)
            return 0;
        } elseif ($text == '') {
            // Nothing to match.
            return -1;
        } elseif (mb_substr($text, $loc, mb_strlen($pattern)) == $pattern) {
            // Perfect match at the perfect spot!  (Includes case of null pattern)
            return $loc;
        } else {
            // Do a fuzzy compare.
            return  $this->bitap($text, $pattern, $loc);
        }
    }

    /**
     * Locate the best instance of 'pattern' in 'text' near 'loc' using the
     * Bitap algorithm.
     *
     * @param string $text    The text to search.
     * @param string $pattern The pattern to search for.
     * @param int    $loc     The location to search around.
     *
     * @throws \RangeException If pattern longer than number of bits in int.
     * @return int Best match index or -1.
     */
    public function bitap($text, $pattern, $loc)
    {
        if ($this->getMaxBits() != 0 && $this->getMaxBits() < mb_strlen($pattern)) {
            throw new \RangeException('Pattern too long for this application.');
        }

        // Initialise the alphabet.
        $s = $this->alphabet($pattern);

        $patternLen = mb_strlen($pattern);
        $textLen = mb_strlen($text);

        // Highest score beyond which we give up.
        $scoreThreshold = $this->getThreshold();
        // Is there a nearby exact match? (speedup)
        $bestLoc = mb_strpos($text, $pattern, $loc);

        if ($bestLoc !== false) {
            $scoreThreshold = min($this->bitapScore(0, $bestLoc, $patternLen, $loc), $scoreThreshold);
            // What about in the other direction? (speedup)
            $bestLoc = mb_strrpos($text,$pattern, $loc + $patternLen);
            if ($bestLoc !== false) {
                $scoreThreshold = min($this->bitapScore(0, $bestLoc, $patternLen, $loc), $scoreThreshold);
            }
        }

        // Initialise the bit arrays.
        $matchMask = 1 << ($patternLen - 1);
        $bestLoc = -1;

        $binMax = $patternLen + $textLen;
        $lastRd = null;
        for ($d = 0; $d < $patternLen; $d++) {
            // Scan for the best match each iteration allows for one more error.
            // Run a binary search to determine how far from 'loc' we can stray at
            // this error level.
            $binMin = 0;
            $binMid = $binMax;
            while ($binMin < $binMid) {
                if ($this->bitapScore($d, $loc + $binMid, $patternLen, $loc) <= $scoreThreshold) {
                    $binMin = $binMid;
                } else {
                    $binMax = $binMid;
                }
                $binMid = (int)(($binMax - $binMin) / 2) + $binMin;
            }
            // Use the result from this iteration as the maximum for the next.
            $binMax = $binMid;
            $start = max(1, $loc - $binMid + 1);
            $finish = min($loc + $binMid, $textLen) + $patternLen;

            $rd = array_fill(0, $finish + 2, 0);
            $rd[$finish + 1] = (1 << $d) - 1;
            for ($j = $finish; $j > $start - 1; $j--) {
                if ($textLen <= $j - 1) {
                    // Out of range.
                    $charMatch = 0;
                } else {
                    $charMatch = isset($s[$text[$j - 1]]) ? $s[$text[$j - 1]] : 0;
                }
                if ($d == 0) {
                    // First pass: exact match.
                    $rd[$j] = (($rd[$j + 1] << 1) | 1) & $charMatch;
                } else {
                    // Subsequent passes: fuzzy match.
                    $rd[$j] = ((($rd[$j + 1] << 1) | 1) & $charMatch) |
                        ((($lastRd[$j + 1] | $lastRd[$j]) << 1) | 1) |
                        $lastRd[$j + 1];
                }
                if ($rd[$j] & $matchMask) {
                    $score = $this->bitapScore($d, $j - 1, $patternLen, $loc);
                    // This match will almost certainly be better than any existing match.
                    // But check anyway.
                    if ($score <= $scoreThreshold) {
                        // Told you so.
                        $scoreThreshold = $score;
                        $bestLoc = $j - 1;
                        if ($bestLoc > $loc) {
                            // When passing loc, don't exceed our current distance from loc.
                            $start = max(1, 2 * $loc - $bestLoc);
                        } else {
                            // Already passed loc, downhill from here on in.
                            break;
                        }
                    }
                }
            }
            // No hope for a (better) match at greater error levels.
            if ($this->bitapScore($d + 1, $loc, $patternLen, $loc) > $scoreThreshold) {
                break;
            }
            $lastRd = $rd;
        }
        return $bestLoc;
    }

    /**
     * Compute and return the score for a match with e errors and x location.
     * Accesses loc and pattern through being a closure.
     *
     * @param int    $errors            Number of errors in match.
     * @param int    $matchLoc          Location of match.
     * @param int    $patternLen        Length of pattern to search.
     * @param int    $searchLoc         The location to search around.
     * TODO refactor param usage.
     *
     * @return float Overall score for match (0.0 = good, 1.0 = bad).
     */
    protected function bitapScore($errors, $matchLoc, $patternLen, $searchLoc)
    {
        $accuracy = $errors / $patternLen;
        $proximity = abs($searchLoc - $matchLoc);
        if (!$this->getDistance()) {
            // Dodge divide by zero error.
            return $proximity ? 1.0 : $accuracy;
        }
        return $accuracy + ($proximity/$this->getDistance());
    }

    /**
     * Initialise the alphabet for the Bitap algorithm.
     *
     * @param string $pattern The text to encode.
     *
     * @return array Hash of character locations.
     */
    public function alphabet($pattern)
    {
        $s = array();
        foreach (preg_split("//u", $pattern, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            $s[$char] = 0;
        }
        for ($i = 0; $i < mb_strlen($pattern); $i++) {
            $s[mb_substr($pattern, $i, 1)] |= 1 << (mb_strlen($pattern) - $i - 1);
        }
        return $s;
    }

}
