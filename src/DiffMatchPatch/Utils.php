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
 * Common utilities.
 *
 * @package DiffMatchPatch
 * @author Daniil Skrobov <yetanotherape@gmail.com>
 */
class Utils {
    /**
     * Multibyte replacement for standard chr()
     *
     * @param int $code Character code.
     *
     * @return string Char with given code
     */
    public static function unicodeChr($code) {
        // TODO this works by order of magnitude slower then chr()
        $code = sprintf("%04x", $code);
        $char = json_decode('"\u'.$code.'"');
        $char = iconv('UTF-8', mb_internal_encoding(), $char);

        return $char;
    }

    /**
     * Multibyte replacement for standard ord()
     *
     * @param string $char Char.
     *
     * @return int Code of given char.
     */
    public static function unicodeOrd($char) {
        if (mb_internal_encoding() != 'UCS-2LE') {
            $char = iconv(mb_internal_encoding(), 'UCS-2LE', $char);
        }
        $code = ord($char[0]) + 256 * ord($char[1]);

        return $code;
    }

    /**
     * Special string encoding function like urlencode(),
     * corresponding to Python's urllib.parse.quote(string, "!~*'();/?:@&=+$,# ")
     *
     * @param string $string String for encoding.
     *
     * @return string Encoded string.
     */
    public static function escapeString($string)
    {
        $string = rawurlencode($string);
        $string = strtr($string, array (
            '%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')', '%3B' => ';', '%2F' => '/', '%3F' => '?',
            '%3A' => ':', '%40' => '@', '%26' => '&', '%3D' => '=', '%2B' => '+', '%24' => '$', '%2C' => ',', '%23' => '#', '%20' => ' '
        ));
        return $string;
    }

    /**
     * Special string decoding function like urldecode(),
     * corresponding to Python's urllib.parse.unquote(string)
     *
     * @param string $string String for decoding.
     *
     * @return string Decoded string.
     */
    public static function unescapeString($string)
    {
        $string = strtr($string, array (
            '%21' => '%2521', '%2A' => '%252A', '%27' => "%2527", '%28' => '%2528', '%29' => '%2529', '%3B' => '%253B', '%2F' => '%252F', '%3F' => '%253F',
            '%3A' => '%253A', '%40' => '%2540', '%26' => '%2526', '%3D' => '%253D', '%2B' => '%252B', '%24' => '%2524', '%2C' => '%252C', '%23' => '%2523', '%20' => '%2520'
        ));
        $string = rawurldecode($string);
        return $string;
    }
}
