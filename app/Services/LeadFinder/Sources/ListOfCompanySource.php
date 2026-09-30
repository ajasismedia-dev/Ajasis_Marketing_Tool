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
        curl_setopt($ch, CURLOPT_USERAGENT, 'AjasisMarketingTool/1.0');
        $html = curl_exec($ch);
        

        if (!$html) return [];

        // Basic generic parsing if possible, structure might vary.
        // Assuming a standard list item for companies
        preg_match_all('/<div class="company-item".*?<h2[^>]*><a href="([^"]+)".*?>([^<]+)<\/a><\/h2>.*?<div class="sector"[^>]*>([^<]+)<\/div>.*?<div class="phone"[^>]*>([^<]+)<\/div>/is', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $index => $match) {
            if ($index >= $limit) break;
            
            $results[] = [
                'name' => trim($match[2]),
                'sector' => trim(strip_tags($match[3])),
                'phone' => trim(strip_tags($match[4])),
                'email' => '',
                'website' => '',
                'instagram' => '',
                'facebook' => '',
                'linkedin' => '',
                'address' => '',
                'district' => '',
                'city' => 'Konya',
                'source' => 'List of Company',
                'source_url' => 'https://listofcompany.com' . $match[1]
            ];
        }

        return $results;
    }
}
