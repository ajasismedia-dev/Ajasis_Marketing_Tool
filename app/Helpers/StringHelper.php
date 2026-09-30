<?php

namespace App\Helpers;

class StringHelper
{
    public static function normalizeTurkish($string)
    {
        $search = ['ç', 'ğ', 'ı', 'i', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'I', 'İ', 'Ö', 'Ş', 'Ü'];
        $replace = ['c', 'g', 'i', 'i', 'o', 's', 'u', 'c', 'g', 'i', 'i', 'o', 's', 'u'];
        return str_replace($search, $replace, $string);
    }
}
