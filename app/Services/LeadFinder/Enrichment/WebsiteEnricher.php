<?php

namespace App\Services\LeadFinder\Enrichment;

class WebsiteEnricher
{
    private static $timeout = 5;
    private static $maxRedirects = 3;

    public static function enrich($url)
    {
        if (empty($url)) return null;

        $safeRequest = self::getSafeRequestData($url);
        if (!$safeRequest) return null;

        $html = self::fetchHtml($safeRequest);
        if (!$html) return null;

        $data = self::extractData($html, $safeRequest['url']);
        
        $internalLinks = self::extractInternalContactLinks($html, $safeRequest['url']);
        $pagesFetched = 1;
        
        foreach ($internalLinks as $link) {
            if ($pagesFetched >= 4) break;
            $safeLinkRequest = self::getSafeRequestData($link);
            if (!$safeLinkRequest) continue;
            
            $subHtml = self::fetchHtml($safeLinkRequest);
            if ($subHtml) {
                $subData = self::extractData($subHtml, $safeLinkRequest['url']);
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

    private static function getSafeRequestData($url, $depth = 0)
    {
        if ($depth > self::$maxRedirects) return null;
        
        $parsed = parse_url($url);
        if (empty($parsed['host']) || !in_array($parsed['scheme'] ?? '', ['http', 'https'])) return null;
        $host = $parsed['host'];
        $port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? 443 : 80);

        $safeIp = self::resolveAndVerifySafeIp($host);
        if (!$safeIp) return null;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AjasisMarketingTool/1.0');
        curl_setopt($ch, CURLOPT_RESOLVE, ["{$host}:{$port}:{$safeIp}"]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        if ($httpCode >= 300 && $httpCode < 400 && $redirectUrl) {
            // Check if relative redirect
            if (strpos($redirectUrl, '/') === 0) {
                $redirectUrl = ($parsed['scheme'] ?? 'https') . '://' . $host . $redirectUrl;
            }
            return self::getSafeRequestData($redirectUrl, $depth + 1);
        }

        return ['url' => $url, 'ip' => $safeIp, 'host' => $host, 'port' => $port];
    }

    private static function resolveAndVerifySafeIp($host)
    {
        $blacklistedHosts = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];
        if (in_array(strtolower($host), $blacklistedHosts)) return null;

        // Note: DNS_A | DNS_AAAA is supported but dns_get_record with multiple constants can be tricky in older PHP.
        // We will do DNS_A first, then DNS_AAAA if empty.
        $records = dns_get_record($host, DNS_A);
        if (empty($records)) {
            $records = dns_get_record($host, DNS_AAAA);
        }
        
        if (empty($records)) return null;

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (!$ip) continue;
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip; // Return the first verified public IP
            }
        }
        
        return null;
    }

    private static function fetchHtml($requestData)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $requestData['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Manually handled by getSafeRequestData
        curl_setopt($ch, CURLOPT_TIMEOUT, self::$timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AjasisMarketingTool/1.0');
        curl_setopt($ch, CURLOPT_RESOLVE, ["{$requestData['host']}:{$requestData['port']}:{$requestData['ip']}"]);
        
        // Max size 1MB
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($ch, $downloadSize, $downloaded, $uploadSize, $uploaded) {
            return ($downloaded > (1024 * 1024)) ? 1 : 0;
        });

        $html = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        if ($error || $httpCode >= 400 || empty($contentType)) {
            return null;
        }

        if (stripos($contentType, 'text/html') === false && stripos($contentType, 'application/xhtml+xml') === false) {
            return null;
        }

        return $html;
    }
    
    private static function extractInternalContactLinks($html, $baseUrl)
    {
        $links = [];
        $parsedBase = parse_url($baseUrl);
        $baseHost = $parsedBase['host'] ?? '';
        $baseScheme = $parsedBase['scheme'] ?? 'https';
        
        if (preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $href) {
                if (preg_match('/(iletisim|contact|about|hakkimizda)/i', $href)) {
                    $resolved = self::resolveRelativeUrl($baseUrl, $href);
                    if ($resolved) {
                        $parsedRes = parse_url($resolved);
                        if (($parsedRes['host'] ?? '') === $baseHost) {
                            $links[] = $resolved;
                        }
                    }
                }
            }
        }
        return array_unique($links);
    }
    
    private static function resolveRelativeUrl($base, $rel)
    {
        if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;
        if ($rel[0] == '#' || $rel[0] == '?') return $base . $rel;
        extract(parse_url($base));
        $path = preg_replace('#/[^/]*$#', '', $path ?? '');
        if ($rel[0] == '/') $path = '';
        $abs = "$host$path/$rel";
        $re = ['#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#'];
        for ($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n)) {}
        $abs = str_replace("../", "", $abs);
        return $scheme . '://' . $abs;
    }

    private static function extractJsonLd($html, $key)
    {
        if (preg_match_all('/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $json) {
                $data = json_decode($json, true);
                if (is_array($data)) {
                    if (isset($data['@graph'])) {
                        foreach ($data['@graph'] as $item) {
                            $res = self::parseJsonLdItem($item, $key);
                            if ($res) return $res;
                        }
                    } else if (isset($data[0])) {
                        foreach ($data as $item) {
                            $res = self::parseJsonLdItem($item, $key);
                            if ($res) return $res;
                        }
                    } else {
                        $res = self::parseJsonLdItem($data, $key);
                        if ($res) return $res;
                    }
                }
            }
        }
        return null;
    }
    
    private static function parseJsonLdItem($item, $key)
    {
        if (is_array($item) && isset($item['@type'])) {
            $types = is_array($item['@type']) ? $item['@type'] : [$item['@type']];
            $validTypes = ['Organization', 'LocalBusiness', 'Store', 'Restaurant'];
            if (count(array_intersect($types, $validTypes)) > 0) {
                // If they requested social link via sameAs
                if (strpos($key, 'social:') === 0) {
                    $domain = str_replace('social:', '', $key);
                    if (!empty($item['sameAs'])) {
                        $sameAs = is_array($item['sameAs']) ? $item['sameAs'] : [$item['sameAs']];
                        foreach ($sameAs as $link) {
                            if (stripos($link, $domain) !== false) return $link;
                        }
                    }
                    return null;
                }
                
                if (!empty($item[$key])) {
                    return is_array($item[$key]) ? $item[$key][0] : $item[$key];
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
        // Try json-ld first
        $jsonLdRes = self::extractJsonLd($html, 'social:' . $domain);
        if ($jsonLdRes) return $jsonLdRes;
        
        if (preg_match('/href=["\'](https?:\/\/(?:www\.)?' . preg_quote($domain) . '\/[^"\']+)["\']/i', $html, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
