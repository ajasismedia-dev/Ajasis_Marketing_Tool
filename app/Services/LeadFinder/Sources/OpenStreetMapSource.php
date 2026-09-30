<?php

namespace App\Services\LeadFinder\Sources;

use App\Services\LeadFinder\LeadSourceInterface;

class OpenStreetMapSource implements LeadSourceInterface
{
    private $sectorMapping = [
        'mimarlık' => 'office=architect',
        'mimar' => 'office=architect',
        'emlak' => 'office=estate_agent',
        'mobilya' => 'shop=furniture',
        'restoran' => 'amenity=restaurant',
        'kafe' => 'amenity=cafe',
        'kuaför' => 'shop=hairdresser',
        'otomotiv' => 'shop=car',
        'oto tamir' => 'shop=car_repair',
        'market' => 'shop=supermarket',
        'eczane' => 'amenity=pharmacy'
    ];

    public function search($query, $city, $district, $limit)
    {
        $results = [];
        $searchQuery = mb_strtolower(trim($query), 'UTF-8');
        $safeCity = addslashes($city ?: 'Konya');
        
        $tagFilter = '["name"~"' . preg_quote($searchQuery) . '",i]';
        
        foreach ($this->sectorMapping as $keyword => $tag) {
            if (strpos($searchQuery, $keyword) !== false) {
                list($k, $v) = explode('=', $tag);
                $tagFilter = '["' . $k . '"="' . $v . '"]';
                break;
            }
        }

        $areaQuery = "area[\"name\"=\"{$safeCity}\"]->.searchArea;";
        if (!empty($district)) {
            $safeDistrict = addslashes($district);
            $areaQuery = "area[\"name\"=\"{$safeCity}\"]->.city; area[\"name\"=\"{$safeDistrict}\"](area.city)->.searchArea;";
        }
        
        $overpassQuery = "[out:json][timeout:10];
        {$areaQuery}
        (
          node{$tagFilter}(area.searchArea);
          way{$tagFilter}(area.searchArea);
        );
        out center {$limit};";

        $url = "https://overpass-api.de/api/interpreter";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "data=" . urlencode($overpassQuery));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AjasisMarketingTool/1.0');
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        

        if ($error || $httpCode >= 400 || !$response) return [];

        $data = json_decode($response, true);
        if (empty($data['elements'])) return [];

        foreach ($data['elements'] as $element) {
            $tags = $element['tags'] ?? [];
            if (empty($tags['name'])) continue;
            
            $results[] = [
                'name' => $tags['name'],
                'sector' => $tags['shop'] ?? $tags['office'] ?? $tags['craft'] ?? $tags['amenity'] ?? '',
                'phone' => $tags['phone'] ?? $tags['contact:phone'] ?? '',
                'email' => $tags['email'] ?? $tags['contact:email'] ?? '',
                'website' => $tags['website'] ?? $tags['contact:website'] ?? '',
                'instagram' => $tags['contact:instagram'] ?? '',
                'facebook' => $tags['contact:facebook'] ?? '',
                'linkedin' => $tags['contact:linkedin'] ?? '',
                'address' => trim(($tags['addr:street'] ?? '') . ' ' . ($tags['addr:housenumber'] ?? '')),
                'district' => $tags['addr:suburb'] ?? $tags['addr:district'] ?? '',
                'city' => $tags['addr:city'] ?? $city,
                'source' => 'OpenStreetMap',
                'source_url' => 'https://www.openstreetmap.org/' . $element['type'] . '/' . $element['id']
            ];
        }

        return $results;
    }
}
