<?php

namespace App\Services\LeadFinder\Sources;

use App\Services\LeadFinder\LeadSourceInterface;

class KsoSource implements LeadSourceInterface
{
    public function search($query, $city, $district, $limit)
    {
        $results = [];
        // Only fetch page 1 for speed
        $url = "https://kso.org.tr/tr-TR/Company/Search/1?q=" . urlencode($query);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AjasisMarketingTool/1.0');
        $html = curl_exec($ch);
        

        if (!$html) return [];

        preg_match_all('/<a href="([^"]+)".*?<strong class="l-company__list-item-title">([^<]+)<\/strong>.*?<span class="l-company__list-item-category">Sektör:\s*([^<]+)<\/span>/is', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $index => $match) {
            if ($index >= $limit) break;
            
            $results[] = [
                'name' => trim($match[2]),
                'sector' => trim(strip_tags($match[3])),
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
                'source_url' => 'https://kso.org.tr' . $match[1]
            ];
        }

        return $results;
    }
}
