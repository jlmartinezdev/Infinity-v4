<?php

namespace App\Support;

final class SearchHighlight
{
    public static function html(?string $text, string $query): string
    {
        $text = (string) $text;
        $query = trim($query);
        if ($text === '') {
            return '';
        }
        if ($query === '') {
            return e($text);
        }

        $needle = mb_strtolower($query);
        $needleLen = mb_strlen($query);
        if ($needle === '' || $needleLen < 1) {
            return e($text);
        }

        $haystackLower = mb_strtolower($text);
        $offset = 0;
        $textLen = mb_strlen($text);
        $out = '';

        while ($offset < $textLen) {
            $pos = mb_strpos($haystackLower, $needle, $offset);
            if ($pos === false) {
                $out .= e(mb_substr($text, $offset));
                break;
            }
            if ($pos > $offset) {
                $out .= e(mb_substr($text, $offset, $pos - $offset));
            }
            $out .= '<mark class="search-mark">'.e(mb_substr($text, $pos, $needleLen)).'</mark>';
            $offset = $pos + $needleLen;
        }

        return $out;
    }
}
