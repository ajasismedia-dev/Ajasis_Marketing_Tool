<?php

namespace App\Services\LeadFinder\Enrichment;

class WebsiteEnricher
{
    private static $timeout = 5;
    private static $maxRedirects = 3;

    public static function enrich($url)
    {
        if (empty($url)) return null;

        $baseUrl = self::getSafeRedirectUrl($url);
        if (!$baseUrl) return null;

        $html = self::fetchHtml($baseUrl);
        if (!$html) return null;

        $data = self::extractData($html, $baseUrl);
        
        // Find internal contact links
        $internalLinks = self::extractInternalContactLinks($html, $baseUrl);
        $pagesFetched = 1;
        
        foreach ($internalLinks as $link) {
            if ($pagesFetched >= 4) break;
            $safeLink = self::getSafeRedirectUrl($link);
            if (!$safeLink) continue;
            
            $subHtml = self::fetchHtml($safeLink);
            if ($subHtml) {
                $subData = self::extractData($subHtml, $safeLink);
                $data = self::mergeData($data, $subData);
                $pagesFetched++;
            }
        }

        return array_filter($data);
    }

    private static function mergeData($existing, $new)
    {
        foreach ($new as $key => $value) {
            if (empty($existing[$key]) && !empty($value)) {
                $existing[$key] = $value;
            }
        }
        return $existing;
    }

    private static function extractData($html, $url)
    {
        return [
            'phone' => self::extractPhone($html) ?? self::extractJsonLd($html, 'telephone'),
            'email' => self::extractEmail($html) ?? self::extractJsonLd($html, 'email'),
            'instagram' => self::extractSocial($html, 'instagram.com'),
            'facebook' => self::extractSocial($html, 'facebook.com'),
            'linkedin' => self::extractSocial($html, 'linkedin.com'),
            'youtube' => self::extractSocial($html, 'youtube.com')
        ];
    }

    private static function getSafeRedirectUrl($url, $depth = 0)
    {
        if ($depth > self::$maxRedirects) return null;
        if (!self::isSafeUrl($url)) return null;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AjasisMarketingTool/1.0');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        

        if ($httpCode >= 300 && $httpCode < 400 && $redirectUrl) {
            return self::getSafeRedirectUrl($redirectUrl, $depth + 1);
        }

        return $url;
    }

    private static function isSafeUrl($url)
    {
        $parsed = parse_url($url);
        if (empty($parsed['host'])) return false;
        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) return false;

        $host = $parsed['host'];
        
        // Prevent simple DNS rebinding attacks by resolving once here and checking
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if (empty($records)) return false;

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (!$ip) continue;
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        
        $blacklistedHosts = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];
        if (in_array($host, $blacklistedHosts)) return false;

        return true;
    }

    private static function fetchHtml($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Manually handled
        curl_setopt($ch, CURLOPT_TIMEOUT, self::$timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AjasisMarketingTool/1.0');
        
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($ch, $downloadSize, $downloaded, $uploadSize, $uploaded) {
            return ($downloaded > (1024 * 1024)) ? 1 : 0;
        });

        $html = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        
        
        if ($error || $httpCode >= 400 || stripos($contentType, 'text/html') === false) {
            return null;
        }

        return $html;
    }
    
    private static function extractInternalContactLinks($html, $baseUrl)
    {
        $links = [];
        $parsedBase = parse_url($baseUrl);
        $baseHost = $parsedBase['host'] ?? '';
        
        if (preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $href) {
                if (preg_match('/(iletisim|contact|about|hakkimizda)/i', $href)) {
                    // Normalize URL
                    if (strpos($href, 'http') === 0) {
                        $parsed = parse_url($href);
                        if (($parsed['host'] ?? '') === $baseHost) {
                            $links[] = $href;
                        }
                    } else if (strpos($href, '/') === 0) {
                        $links[] = ($parsedBase['scheme'] ?? 'https') . '://' . $baseHost . $href;
                    } else {
                        $links[] = rtrim($baseUrl, '/') . '/' . $href;
                    }
                }
            }
        }
        return array_unique($links);
    }

    private static function extractJsonLd($html, $key)
    {
        if (preg_match_all('/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $json) {
                $data = json_decode($json, true);
                if (is_array($data)) {
                    if (isset($data['@type']) && in_array($data['@type'], ['Organization', 'LocalBusiness'])) {
                        if (!empty($data[$key])) {
                            return is_array($data[$key]) ? $data[$key][0] : $data[$key];
                        }
                    }
                }
            }
        }
        return null;
    }

    private static function extractPhone($html)
    {
        if (preg_match('/href=["\']tel:([^"\']+)["\']/i', $html, $matches)) {
            return trim(urldecode($matches[1]));
        }
        return null;
    }

    private static function extractEmail($html)
    {
        if (preg_match('/href=["\']mailto:([^"\']+)["\']/i', $html, $matches)) {
            $email = trim(urldecode($matches[1]));
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        }
        return null;
    }

    private static function extractSocial($html, $domain)
    {
        if (preg_match('/href=["\'](https?:\/\/(?:www\.)?' . preg_quote($domain) . '\/[^"\']+)["\']/i', $html, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
