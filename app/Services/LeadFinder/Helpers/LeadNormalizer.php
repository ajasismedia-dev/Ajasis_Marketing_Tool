<?php

namespace App\Services\LeadFinder\Helpers;

class LeadNormalizer
{
    public static function deduplicate(array $leads)
    {
        $merged = [];
        foreach ($leads as $lead) {
            $foundIndex = -1;
            foreach ($merged as $index => $existing) {
                if (self::isSame($existing, $lead)) {
                    $foundIndex = $index;
                    break;
                }
            }

            if ($foundIndex !== -1) {
                $merged[$foundIndex] = self::merge($merged[$foundIndex], $lead);
            } else {
                $merged[] = self::normalizeLead($lead);
            }
        }
        return $merged;
    }

    public static function isSame($a, $b)
    {
        // 1. Exact Website Domain Match
        $domainA = self::normalizeDomain($a['website'] ?? '');
        $domainB = self::normalizeDomain($b['website'] ?? '');
        if ($domainA && $domainA === $domainB) return true;

        // 2. Exact Phone Match
        $phoneA = self::normalizePhone($a['phone'] ?? '');
        $phoneB = self::normalizePhone($b['phone'] ?? '');
        if ($phoneA && $phoneB && $phoneA === $phoneB) return true;

        // 3. Exact Name Match
        $nameA = self::normalizeCompanyName($a['name'] ?? '');
        $nameB = self::normalizeCompanyName($b['name'] ?? '');
        if ($nameA && $nameB && $nameA === $nameB) return true;

        // 4. Core Name Match (Stripping company legal suffixes for HIGH confidence deduplication)
        $coreA = self::normalizeCoreCompanyName($a['name'] ?? '');
        $coreB = self::normalizeCoreCompanyName($b['name'] ?? '');
        if ($coreA && $coreB && mb_strlen($coreA) >= 4 && mb_strlen($coreB) >= 4 && $coreA === $coreB) {
            return true;
        }

        return false;
    }

    public static function normalizeCoreCompanyName($name)
    {
        if (empty($name)) return '';
        $name = self::normalizeCompanyName($name);
        $name = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $name);
        
        $suffixes = [
            '\\banonim sirketi\\b', '\\blimited sirketi\\b', '\\bltd sti\\b', '\\bltd\\b', '\\bsti\\b',
            '\\ba s\\b', '\\bas\\b', '\\bsanayi ve ticaret\\b', '\\bsan ve tic\\b', '\\bsanayi ve tic\\b',
            '\\bsanayi\\b', '\\bticaret\\b', '\\bsan\\b', '\\btic\\b', '\\bve\\b', '\\bholding\\b',
            '\\bsirketi\\b', '\\bkollektif sirketi\\b', '\\bkomandit sirketi\\b', '\\bsubesi\\b',
            '\\bmerkezi\\b'
        ];
        foreach ($suffixes as $s) {
            $name = preg_replace('/' . $s . '/u', ' ', $name);
        }
        return preg_replace('/\s+/', ' ', trim($name));
    }

    public static function merge($existing, $new)
    {
        foreach ($new as $key => $value) {
            if (empty($existing[$key]) && !empty($value)) {
                $existing[$key] = $value;
            }
        }
        $existing['source'] = self::mergeSources($existing['source'] ?? '', $new['source'] ?? '');
        return $existing;
    }

    public static function mergeSources($sourceA, $sourceB)
    {
        $partsA = !empty($sourceA) ? array_map('trim', preg_split('/[,+]/', (string)$sourceA)) : [];
        $partsB = !empty($sourceB) ? array_map('trim', preg_split('/[,+]/', (string)$sourceB)) : [];
        $all = [];
        foreach (array_merge($partsA, $partsB) as $part) {
            if ($part !== '' && !in_array($part, $all)) {
                $all[] = $part;
            }
        }
        return implode(' + ', $all);
    }

    public static function normalizeLead($lead)
    {
        $lead['phone'] = self::normalizePhone($lead['phone'] ?? '');
        $lead['website'] = self::formatUrl($lead['website'] ?? '');
        $lead['instagram'] = self::normalizeSocialUrl($lead['instagram'] ?? '');
        $lead['facebook'] = self::normalizeSocialUrl($lead['facebook'] ?? '');
        $lead['linkedin'] = self::normalizeSocialUrl($lead['linkedin'] ?? '');
        return $lead;
    }

    public static function normalizeDomain($url)
    {
        if (empty($url)) return '';
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = "http://" . $url;
        }
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $host = mb_strtolower($host, 'UTF-8');
        return preg_replace('/^www\./', '', $host);
    }

    public static function normalizePhone($phone)
    {
        if (empty($phone)) return '';
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($clean) === 10) {
            return '90' . $clean;
        } elseif (strlen($clean) === 11 && strpos($clean, '0') === 0) {
            return '9' . $clean;
        } elseif (strlen($clean) === 12 && strpos($clean, '90') === 0) {
            return $clean;
        }
        return $clean;
    }
    
    public static function normalizeCompanyName($name)
    {
        if (empty($name)) return '';
        
        $search = ['Ç','Ğ','İ','I','Ö','Ş','Ü','ç','ğ','ı','i','ö','ş','ü'];
        $replace = ['c','g','i','i','o','s','u','c','g','i','i','o','s','u'];
        $name = str_replace($search, $replace, $name);
        
        $name = mb_strtolower(trim($name), 'UTF-8');
        return $name;
    }

    public static function normalizeSocialUrl($url)
    {
        if (empty($url)) return '';
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            return "https://" . ltrim($url, '/');
        }
        return $url;
    }

    public static function formatUrl($url)
    {
        if (empty($url)) return '';
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            return "http://" . ltrim($url, '/');
        }
        return $url;
    }
}
