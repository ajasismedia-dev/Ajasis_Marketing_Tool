<?php

namespace App\Services\LeadFinder\Sources;

use App\Services\LeadFinder\LeadSourceInterface;

class ListOfCompanySource implements LeadSourceInterface
{
    public function search($query, $city, $district, $limit)
    {
        $results = [];
        $url = "https://listofcompany.com/tr/search?q=" . urlencode($query);
        
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
        
        $nodes = $xpath->query('//div[contains(@class, "company")] | //div[contains(@class, "item")]');
        
        if ($nodes->length === 0) {
            // Fallback for generic links if classes don't match
            $nodes = $xpath->query('//a[contains(@href, "company/") or contains(@href, "firma/")]');
            $count = 0;
            foreach ($nodes as $node) {
                if ($count >= $limit) break;
                
                $name = trim($node->textContent);
                if (empty($name) || strlen($name) < 3) continue;
                
                $href = $node->getAttribute('href');
                $sourceUrl = strpos($href, 'http') === 0 ? $href : 'https://listofcompany.com' . (strpos($href, '/') === 0 ? '' : '/') . $href;
                
                $results[] = [
                    'name' => $name,
                    'sector' => '',
                    'phone' => '',
                    'email' => '',
                    'website' => '',
                    'instagram' => '',
                    'facebook' => '',
                    'linkedin' => '',
                    'address' => '',
                    'district' => '',
                    'city' => 'Konya',
                    'source' => 'List of Company',
                    'source_url' => $sourceUrl
                ];
                $count++;
            }
        } else {
            $count = 0;
            foreach ($nodes as $node) {
                if ($count >= $limit) break;
                
                $nameNode = $xpath->query('.//h2 | .//h3 | .//a[@class="title"]', $node)->item(0);
                if (!$nameNode) continue;
                
                $name = trim($nameNode->textContent);
                $aNode = $xpath->query('.//a', $nameNode)->item(0) ?: ($nameNode->tagName === 'a' ? $nameNode : null);
                $href = $aNode ? $aNode->getAttribute('href') : '';
                $sourceUrl = $href ? (strpos($href, 'http') === 0 ? $href : 'https://listofcompany.com' . (strpos($href, '/') === 0 ? '' : '/') . $href) : '';
                
                $sectorNode = $xpath->query('.//div[contains(@class, "sector")]', $node)->item(0);
                $sector = $sectorNode ? trim($sectorNode->textContent) : '';
                
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
                    'source' => 'List of Company',
                    'source_url' => $sourceUrl
                ];
                $count++;
            }
        }

        return $results;
    }
}
