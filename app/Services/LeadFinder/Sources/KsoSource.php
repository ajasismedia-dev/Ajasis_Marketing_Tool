<?php

namespace App\Services\LeadFinder\Sources;

use App\Services\LeadFinder\LeadSourceInterface;

class KsoSource implements LeadSourceInterface
{
    public function search($query, $city, $district, $limit)
    {
        $results = [];
        $url = "https://kso.org.tr/tr-TR/Company/Search/1?q=" . urlencode($query);
        
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
        

        if ($error || $httpCode >= 400 || !$html) {
            return []; // Unavailable or timeout
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//a[contains(@class, "l-company__list-item")]');
        
        $count = 0;
        foreach ($nodes as $node) {
            if ($count >= $limit) break;
            
            $nameNode = $xpath->query('.//strong[contains(@class, "l-company__list-item-title")]', $node)->item(0);
            $sectorNode = $xpath->query('.//span[contains(@class, "l-company__list-item-category")]', $node)->item(0);
            
            if (!$nameNode) continue;
            
            $name = trim($nameNode->textContent);
            $sectorText = $sectorNode ? trim($sectorNode->textContent) : '';
            $sector = preg_replace('/^Sektör:\s*/i', '', $sectorText);
            
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
            $count++;
        }

        return $results;
    }
    
    public function enrichResult(array $lead)
    {
        if (empty($lead['source_url'])) return $lead;
        
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

        $webNode = $xpath->query('//a[starts-with(@href, "http")]')->item(0); // This could be risky if there are social links, but as a fallback it's okay for KSO which often links to the company directly under a specific class. Let's look for a class if possible, or just the first http link that isn't kso.org.tr
        foreach ($xpath->query('//a[starts-with(@href, "http")]') as $node) {
            $href = $node->getAttribute('href');
            if (strpos($href, 'kso.org.tr') === false) {
                $lead['website'] = $href;
                break;
            }
        }
        
        // Address is usually within an address tag or a specific paragraph
        $addressNode = $xpath->query('//address | //p[contains(@class, "address")]')->item(0);
        if ($addressNode) {
            $lead['address'] = trim($addressNode->textContent);
        }

        return $lead;
    }
}
