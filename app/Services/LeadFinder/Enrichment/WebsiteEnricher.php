<?php

namespace App\Services\LeadFinder\Enrichment;

class WebsiteEnricher
{
    private static $timeout = 5;
    private static $maxRedirects = 2;

    public static function enrich($url)
    {
        if (empty($url)) return null;

        if (!self::isSafeUrl($url)) {
            return null; // SSRF protection
        }

        $html = self::fetchHtml($url);
        if (!$html) return null;

        $data = [
            'phone' => self::extractPhone($html),
            'email' => self::extractEmail($html),
            'instagram' => self::extractSocial($html, 'instagram.com'),
            'facebook' => self::extractSocial($html, 'facebook.com'),
            'linkedin' => self::extractSocial($html, 'linkedin.com')
        ];

        return array_filter($data);
    }

    private static function isSafeUrl($url)
    {
        $parsed = parse_url($url);
        if (empty($parsed['host'])) return false;
        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) return false;

        $host = $parsed['host'];
        $ip = gethostbyname($host);
        
        // Block private/local IPs
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        // Block explicit localhost/127.0.0.1 (though covered above, just extra safe)
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'])) {
            return false;
        }

        return true;
    }

    private static function fetchHtml($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, self::$maxRedirects);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::$timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AjasisMarketingTool/1.0');
        
        // Limit body size to 1MB to prevent memory issues
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($ch, $downloadSize, $downloaded, $uploadSize, $uploaded) {
            return ($downloaded > (1024 * 1024)) ? 1 : 0; // Abort if > 1MB
        });

        $html = curl_exec($ch);
        
        
        return $html;
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
