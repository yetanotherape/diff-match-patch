<?php


namespace DiffMatchPatch;


class Utils {
    /**
     * Multibyte replacement for standard chr()
     *
     * @param int $code Character code.
     *
     * @return string Char with given code in UTF-8.
     */
    public static function unicodeChr($code) {
        // TODO this works by order of magnitude slower then chr()
        $code = str_pad(dechex($code), 4, 0, STR_PAD_LEFT);
        return json_decode('"\u'.$code.'"');
    }

    /**
     * Multibyte replacement for standard ord()
     *
     * @param string $char Char in UTF-8
     *
     * @return int Code of given char.
     */
    public static function unicodeOrd($char) {
        $twoByteChar = mb_convert_encoding($char, 'UCS-2LE', 'UTF-8');
        $code = ord($twoByteChar[0]) + 256 * ord($twoByteChar[1]);
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
