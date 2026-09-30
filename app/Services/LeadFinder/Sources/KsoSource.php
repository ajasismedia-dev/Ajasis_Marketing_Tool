<?php

namespace App\Services\LeadFinder\Sources;

use App\Services\LeadFinder\LeadSourceInterface;

class KsoSource implements LeadSourceInterface
{
    private $lastStatus = 'success';

    public function search($query, $city, $district, $limit)
    {
        $this->lastStatus = 'success';
        $results = [];
        $queryLower = \App\Helpers\StringHelper::normalizeTurkish(mb_strtolower($query));
        
        $page = 1;
        $maxPages = 10;
        $fetchedCount = 0;
        
        while ($page <= $maxPages && count($results) < $limit) {
            $url = "https://kso.org.tr/tr-TR/Company/Search/" . $page;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_USERAGENT, 'AjasisMarketingTool/1.0');
            $html = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($error) {
                $this->lastStatus = 'timeout';
                break;
            }
            if ($httpCode >= 400 || !$html) {
                $this->lastStatus = 'unavailable';
                break;
            }

            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $dom->loadHTML($html);
            libxml_clear_errors();
            
            $xpath = new \DOMXPath($dom);
            $nodes = $xpath->query('//a[contains(@class, "l-company__list-item")]');
            
            if ($nodes->length === 0) {
                if ($page === 1) $this->lastStatus = 'empty';
                break;
            }
            
            $pageHasMatches = false;
            foreach ($nodes as $node) {
                if (count($results) >= $limit) break;
                
                $nameNode = $xpath->query('.//strong[contains(@class, "l-company__list-item-title")]', $node)->item(0);
                $sectorNode = $xpath->query('.//span[contains(@class, "l-company__list-item-category")]', $node)->item(0);
                
                if (!$nameNode) continue;
                
                $name = trim($nameNode->textContent);
                $sectorText = $sectorNode ? trim($sectorNode->textContent) : '';
                $sector = preg_replace('/^Sektör:\s*/i', '', $sectorText);
                
                $nameLower = \App\Helpers\StringHelper::normalizeTurkish(mb_strtolower($name));
                $sectorLower = \App\Helpers\StringHelper::normalizeTurkish(mb_strtolower($sector));
                
                if (empty($query) || strpos($nameLower, $queryLower) !== false || strpos($sectorLower, $queryLower) !== false) {
                    $pageHasMatches = true;
                    $href = $node->getAttribute('href');
                    $sourceUrl = strpos($href, 'http') === 0 ? $href : 'https://kso.org.tr' . (strpos($href, '/') === 0 ? '' : '/') . $href;

                    $results[] = [
                        'name' => $name,
                        'sector' => $sector,
                        'phone' => '',
                        'email' => '',
                        'website' => '',
                        'instagram' => '',
                        'facebook' => '',
                        'linkedin' => '',
                        'address' => '',
                        'district' => '',
                        'city' => 'Konya',
                        'source' => 'Konya Sanayi Odası',
                        'source_url' => $sourceUrl
                    ];
                }
            }
            $page++;
            usleep(200000); // 200ms rate limit
        }
        
        if (empty($results) && $this->lastStatus === 'success') {
            $this->lastStatus = 'filtered_zero';
        }

        return $results;
    }

    public function getLastStatus()
    {
        return $this->lastStatus;
    }
    
    public function enrichResult(array $lead)
    {
        if (empty($lead['source_url'])) return $lead;
        
        $parsed = parse_url($lead['source_url']);
        $host = strtolower($parsed['host'] ?? '');
        if (!in_array($host, ['kso.org.tr', 'www.kso.org.tr'])) {
            return $lead; // Block arbitrary requests
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $lead['source_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AjasisMarketingTool/1.0');
        $html = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($error || $httpCode >= 400 || !$html) {
            return $lead; // Fail gracefully
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        // KSO Detail extraction heuristics
        $phoneNode = $xpath->query('//a[starts-with(@href, "tel:")]')->item(0);
        if ($phoneNode) $lead['phone'] = trim(str_replace('tel:', '', $phoneNode->getAttribute('href')));
        
        $emailNode = $xpath->query('//a[starts-with(@href, "mailto:")]')->item(0);
        if ($emailNode) $lead['email'] = trim(str_replace('mailto:', '', $emailNode->getAttribute('href')));

        $blacklistDomains = [
            'kso.org.tr', 'facebook.com', 'instagram.com', 'linkedin.com',
            'youtube.com', 'twitter.com', 'x.com', 'wa.me', 'whatsapp.com'
        ];
        
        $websiteCandidates = [];
        foreach ($xpath->query('//a[starts-with(@href, "http")]') as $node) {
            $href = $node->getAttribute('href');
            $host = strtolower(parse_url($href, PHP_URL_HOST) ?? '');
            
            $isBlacklisted = false;
            foreach ($blacklistDomains as $bDomain) {
                if (strpos($host, $bDomain) !== false) {
                    $isBlacklisted = true;
                    break;
                }
            }
            if (!$isBlacklisted) {
                $websiteCandidates[] = $href;
            }
        }
        
        if (count($websiteCandidates) === 1) {
            $lead['website'] = $websiteCandidates[0];
        } else if (count($websiteCandidates) > 1) {
            // Check if one of them matches a 'web' or 'internet' label near it, or just leave it empty if ambiguous
            // We'll leave it empty to avoid false positives as requested.
        }
        
        // Address is usually within an address tag or a specific paragraph
        $addressNode = $xpath->query('//address | //p[contains(@class, "address")]')->item(0);
        if ($addressNode) {
            $lead['address'] = trim($addressNode->textContent);
        }

        return $lead;
    }
}
