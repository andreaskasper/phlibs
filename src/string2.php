<?php
/**
 * string2 — String utility helpers.
 *
 * Provides static methods for number parsing (supporting European/German decimal
 * formats), phone number normalization, and text truncation at word boundaries.
 *
 * @author  Andreas Kasper <andreas.kasper@goo1.de>
 * @package phlibs
 * @version 1.0.0
 * @license FreeFoodLicense
 */

namespace phlibs;

class string2 {

    /**
     * Parse a numeric string supporting both US and European (German) formats.
     *
     * Handles comma as decimal separator and dot as thousands separator:
     *   - "123.45"     → 123.45  (US format, dot = decimal)
     *   - "123,45"     → 123.45  (European, comma = decimal)
     *   - "12.345,67"  → 12345.67 (European with thousands separator)
     *
     * @param string $value Numeric string to parse.
     * @return float|int|string Parsed number, or empty string if not numeric.
     */
    public static function vall($value) {
        if (strpos($value, ",") !== false) {
            $value = str_replace(".", "", $value);
            $value = str_replace(",", ".", $value);
        }
        if ($value + 0 != $value) return "";
        return $value + 0;
    }

    /**
     * Normalize a phone number to digits-only format.
     *
     * Converts international prefix '+' to '00' and strips all non-digit characters.
     *
     * @param string $txt Phone number string (e.g. '+49 170 1234567').
     * @return string Normalized digits-only string (e.g. '004917012345667').
     */
    public static function parse_phonenr($txt) {
        if (substr($txt, 0, 1) == "+") {
            $txt = "00" . substr($txt, 1, 999);
        }
        $txt = preg_replace("@[^0-9]@", "", $txt);
        return $txt;
    }

    /**
     * Truncate text to a maximum length at the nearest word boundary.
     *
     * Appends an ellipsis character (…) if the text was truncated.
     *
     * @param string $txt    Text to truncate.
     * @param int    $length Maximum length in characters (default: 160).
     * @return string Truncated text with ellipsis, or original text if short enough.
     */
    public static function Abkuerzen($txt, $length = 160) {
        if (strlen($txt) <= $length) return $txt;
        $p = strrpos(substr($txt, 0, $length + 1), " ");
        if ($p === false) $p = $length;
        return substr($txt, 0, $p) . "…";
    }
}
