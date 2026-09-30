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

    private static function isSame($a, $b)
    {
        // 1. Exact Website Domain Match
        $domainA = self::getDomain($a['website'] ?? '');
        $domainB = self::getDomain($b['website'] ?? '');
        if ($domainA && $domainA === $domainB) return true;

        // 2. Exact Phone Match
        $phoneA = preg_replace('/[^0-9]/', '', $a['phone'] ?? '');
        $phoneB = preg_replace('/[^0-9]/', '', $b['phone'] ?? '');
        if ($phoneA && $phoneB && $phoneA === $phoneB) return true;

        // 3. Name Match
        $nameA = trim(mb_strtolower($a['name'] ?? '', 'UTF-8'));
        $nameB = trim(mb_strtolower($b['name'] ?? '', 'UTF-8'));
        if ($nameA && $nameB && $nameA === $nameB) return true;

        return false;
    }

    private static function merge($existing, $new)
    {
        foreach ($new as $key => $value) {
            if (empty($existing[$key]) && !empty($value)) {
                $existing[$key] = $value;
            }
        }
        if ($existing['source'] !== $new['source'] && !empty($new['source'])) {
             // Combine sources if different
             if (strpos($existing['source'], $new['source']) === false) {
                 $existing['source'] .= ', ' . $new['source'];
             }
        }
        return $existing;
    }

    private static function normalizeLead($lead)
    {
        $lead['phone'] = self::formatPhone($lead['phone'] ?? '');
        $lead['website'] = self::formatUrl($lead['website'] ?? '');
        return $lead;
    }

    private static function getDomain($url)
    {
        if (empty($url)) return '';
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = "http://" . $url;
        }
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        return preg_replace('/^www\./', '', $host);
    }

    private static function formatPhone($phone)
    {
        if (empty($phone)) return '';
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($clean) === 10) {
            return '90' . $clean;
        } elseif (strlen($clean) === 11 && strpos($clean, '0') === 0) {
            return '9' . $clean;
        }
        return $clean; // Default fallback
    }

    private static function formatUrl($url)
    {
        if (empty($url)) return '';
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            return "http://" . $url;
        }
        return $url;
    }
}
