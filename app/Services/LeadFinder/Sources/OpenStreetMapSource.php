<?php

namespace App\Services\LeadFinder\Sources;

use App\Services\LeadFinder\LeadSourceInterface;

class OpenStreetMapSource implements LeadSourceInterface
{
    public function search($query, $city, $district, $limit)
    {
        $results = [];
        
        // Simple Overpass query to find nodes/ways matching the name/type in the area
        // Note: Using a general search for the word in name tag within Konya
        
        $searchQuery = mb_strtolower($query, 'UTF-8');
        
        // Building Overpass QL
        $overpassQuery = "[out:json][timeout:10];
        area[name=\"{$city}\"]->.searchArea;
        (
          node[\"name\"~\"{$searchQuery}\",i](area.searchArea);
          way[\"name\"~\"{$searchQuery}\",i](area.searchArea);
        );
        out center {$limit};";

        $url = "https://overpass-api.de/api/interpreter";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "data=" . urlencode($overpassQuery));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AjasisMarketingTool/1.0');
        $response = curl_exec($ch);
        

        if (!$response) return [];

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
