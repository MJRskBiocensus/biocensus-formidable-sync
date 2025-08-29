<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Libs\Snap;

use Exception;

class SnapString
{
    /**
     * Return true or false in string
     *
     * @param mixed $b input value
     *
     * @return string
     */
    public static function boolToString($b): string
    {
        return ($b ? 'true' : 'false');
    }

    /**
     * Truncate string and add ellipsis
     *
     * @param string $s        string to truncate
     * @param int    $maxWidth max length
     *
     * @return string
     */
    public static function truncateString($s, $maxWidth)
    {
        if (strlen($s) > $maxWidth) {
            $s = substr($s, 0, $maxWidth - 3) . '...';
        }

        return $s;
    }

    /**
     * Returns true if the $haystack string starts with the $needle
     *
     * @param string $haystack The full string to search in
     * @param string $needle   The string to for
     *
     * @return bool Returns true if the $haystack string starts with the $needle
     */
    public static function startsWith($haystack, $needle): bool
    {
        return (strpos($haystack, $needle) === 0);
    }

    /**
     * Returns true if the $haystack string end with the $needle
     *
     * @param string $haystack The full string to search in
     * @param string $needle   The string to for
     *
     * @return bool Returns true if the $haystack string starts with the $needle
     */
    public static function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }
        return (substr($haystack, -$length) === $needle);
    }

    /**
     * Returns true if the $needle is found in the $haystack
     *
     * @param string $haystack The full string to search in
     * @param string $needle   The string to for
     *
     * @return bool
     */
    public static function contains($haystack, $needle): bool
    {
        $pos = strpos($haystack, $needle);
        return ($pos !== false);
    }

    /**
     * Implode array key values to a string
     *
     * @param string  $glue   separator
     * @param mixed[] $pieces array fo implode
     * @param string  $format format
     *
     * @return string
     */
    public static function implodeKeyVals($glue, $pieces, $format = '%s="%s"'): string
    {
        $strList = [];
        foreach ($pieces as $key => $value) {
            $strList[] = is_scalar($value) ? sprintf($format, $key, $value) : sprintf($format, $key, print_r($value, true));
        }
        return implode($glue, $strList);
    }

    /**
     * Replace last occurrence
     *
     * @param string  $search        The value being searched for
     * @param string  $replace       The replacement value that replaces found search values
     * @param string  $str           The string or array being searched and replaced on, otherwise known as the haystack
     * @param boolean $caseSensitive Whether the replacement should be case sensitive or not
     *
     * @return string
     */
    public static function strLastReplace($search, $replace, $str, $caseSensitive = true)
    {
        $pos = $caseSensitive ? strrpos($str, $search) : strripos($str, $search);
        if (false !== $pos) {
            $str = substr_replace($str, $replace, $pos, strlen($search));
        }
        return $str;
    }

    /**
     * Check if passed string have html tags
     *
     * @param string $string input string
     *
     * @return boolean
     */
    public static function isHTML($string): bool
    {
        return ($string != strip_tags($string));
    }

    /**
     * Safe way to get number of characters
     *
     * @param ?string $string input string
     *
     * @return int
     */
    public static function stringLength($string): int
    {
        if (!isset($string) || $string == "") { // null == "" is also true
            return 0;
        }
        return strlen($string);
    }

    /**
     * Returns case insensitive duplicates
     *
     * @param string[] $strings The array of strings to check for duplicates
     *
     * @return array<string[]>
     */
    public static function getCaseInsesitiveDuplicates($strings): array
    {
        $duplicates = [];
        for ($i = 0; $i < count($strings) - 1; $i++) {
            $key = strtolower($strings[$i]);

            //already found all instances so don't check again
            if (isset($duplicates[$key])) {
                continue;
            }

            for ($j = $i + 1; $j < count($strings); $j++) {
                if ($strings[$i] !== $strings[$j] && $key === strtolower($strings[$j])) {
                    $duplicates[$key][] = $strings[$j];
                }
            }

            //duplicates were found, add the comparing string to list
            if (isset($duplicates[$key])) {
                $duplicates[$key][] = $strings[$i];
            }
        }

        return $duplicates;
    }

    /**
     * Display human readable byte sizes
     *
     * @param int $size The size in bytes
     *
     * @return string The size of bytes readable such as 100KB, 20MB, 1GB etc.
     */
    public static function byteSize(int $size): string
    {
        try {
            $units = [
                'B',
                'KB',
                'MB',
                'GB',
                'TB',
            ];
            for ($i = 0; $size >= 1024 && $i < 4; $i++) {
                $size /= 1024;
            }
            return round($size, 2) . $units[$i];
        } catch (Exception $e) {
            return "n/a";
        }
    }

    /**
     * If input value is string, try to get typed value from it or return input value, if input value is array, return array with typed values
     *
     * @param mixed $value Generic value to get typed value from
     *
     * @return mixed value with it's natural string type
     */
    public static function getTypedVal($value)
    {
        if (is_string($value)) {
            if (is_numeric($value)) {
                if ((int) $value == $value) {
                    return (int) $value;
                } elseif ((float) $value == $value) {
                    return (float) $value;
                }
            } elseif (in_array(strtolower($value), ['true', 'false'], true)) {
                return ($value == 'true');
            }
        } elseif (is_array($value)) {
            foreach ($value as $key => $subVal) {
                $value[$key] = self::getTypedVal($subVal);
            }
        } else {
            return $value;
        }
    }

    /**
     * Return a string with the elapsed time in seconds
     *
     * @see getMicrotime()
     *
     * @param float $end   The final time in the sequence to measure
     * @param float $start The start time in the sequence to measure
     *
     * @return string   The time elapsed from $start to $end as 5.89 sec.
     */
    public static function formattedElapsedTime(float $end, float $start): string
    {

        return sprintf(
            esc_html_x(
                '%.3f sec.',
                'sec. stands for seconds',
                'duplicator-pro'
            ),
            abs($end - $start)
        );
    }
}
