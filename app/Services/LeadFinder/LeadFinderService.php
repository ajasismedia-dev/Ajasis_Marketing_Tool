<?php

namespace App\Services\LeadFinder;

use App\Services\LeadFinder\Sources\KtoSource;
use App\Services\LeadFinder\Sources\KsoSource;
use App\Services\LeadFinder\Sources\ListOfCompanySource;
use App\Services\LeadFinder\Sources\OpenStreetMapSource;
use App\Services\LeadFinder\Helpers\LeadNormalizer;
use App\Models\Company;

class LeadFinderService
{
    private $sources = [];
    private $sourceStatuses = [];

    public function __construct($sourceKey = 'all')
    {
        // Source Order: 1. KTO, 2. KSO, 3. OSM
        if ($sourceKey === 'all' || $sourceKey === 'kto') {
            $this->sources['KTO'] = new KtoSource();
        }
        if ($sourceKey === 'all' || $sourceKey === 'kso') {
            $this->sources['KSO'] = new KsoSource();
        }
        if ($sourceKey === 'all' || $sourceKey === 'osm') {
            $this->sources['OSM'] = new OpenStreetMapSource();
        }
        if ($sourceKey === 'listofcompany') {
            $this->sources['ListOfCompany'] = new ListOfCompanySource();
        }
    }

    public function search($query, $city = 'Konya', $district = '', $limit = 25)
    {
        $sourceResults = [];
        $this->sourceStatuses = [];

        foreach ($this->sources as $name => $source) {
            $startTime = microtime(true);
            try {
                $results = $source->search($query, $city, $district, $limit);
                $duration = round(microtime(true) - $startTime, 2);
                
                if (is_array($results) && count($results) > 0) {
                    $sourceResults[$name] = $results;
                    $this->sourceStatuses[$name] = [
                        'status' => 'success',
                        'count' => count($results),
                        'duration' => $duration . 's'
                    ];
                } else {
                    $sourceResults[$name] = [];
                    $this->sourceStatuses[$name] = [
                        'status' => method_exists($source, 'getLastStatus') ? $source->getLastStatus() : 'empty',
                        'count' => 0,
                        'duration' => $duration . 's'
                    ];
                }
                $this->logRequest($name, $query, $this->sourceStatuses[$name]['status'], $duration);
            } catch (\Exception $e) {
                $duration = round(microtime(true) - $startTime, 2);
                $sourceResults[$name] = [];
                $this->sourceStatuses[$name] = [
                    'status' => 'error',
                    'count' => 0,
                    'duration' => $duration . 's'
                ];
                $this->logRequest($name, $query, 'error: ' . $e->getMessage(), $duration);
            }
        }

        $mergedResults = self::balanceSources($sourceResults, $limit);

        try {
            $companyModel = new Company();
            foreach ($mergedResults as &$lead) {
                $dbMatch = $companyModel->findPotentialDuplicate($lead['name'], $lead['phone'] ?? '', $lead['website'] ?? '');
                if ($dbMatch) {
                    $lead['db_id'] = $dbMatch['id'];
                    $fullDb = $companyModel->findById($dbMatch['id']);
                    $lead['db_status'] = $fullDb['status'] ?? 'new';
                } else {
                    $lead['db_id'] = null;
                }
            }
        } catch (\Exception $e) {
            foreach ($mergedResults as &$lead) {
                $lead['db_id'] = null;
            }
            error_log("DB check failed: " . $e->getMessage());
        }

        return [
            'leads' => $mergedResults,
            'statuses' => $this->sourceStatuses
        ];
    }

    public static function balanceSources(array $sourceResults, int $limit, array $preferredRatios = ['KTO' => 0.60, 'KSO' => 0.20, 'OSM' => 0.20])
    {
        $cleanedSources = [];
        foreach ($sourceResults as $src => $items) {
            $cleanedSources[$src] = LeadNormalizer::deduplicate($items);
        }

        if (count($cleanedSources) === 1) {
            $first = reset($cleanedSources);
            return array_slice($first, 0, $limit);
        }

        $quotas = [];
        $totalAllocated = 0;
        foreach ($preferredRatios as $src => $ratio) {
            if (isset($cleanedSources[$src])) {
                $quotas[$src] = (int)round($limit * $ratio);
                $totalAllocated += $quotas[$src];
            }
        }
        if ($totalAllocated < $limit && isset($quotas['KTO'])) {
            $quotas['KTO'] += ($limit - $totalAllocated);
        }

        $finalLeads = [];
        $pointers = array_fill_keys(array_keys($cleanedSources), 0);
        $takenCounts = array_fill_keys(array_keys($cleanedSources), 0);

        $addOrMerge = function($lead) use (&$finalLeads) {
            foreach ($finalLeads as $idx => $existing) {
                if (LeadNormalizer::isSame($existing, $lead)) {
                    $finalLeads[$idx] = LeadNormalizer::merge($existing, $lead);
                    return false;
                }
            }
            $finalLeads[] = LeadNormalizer::normalizeLead($lead);
            return true;
        };

        // Pass 1: Quota-based allocation
        foreach ($quotas as $src => $quota) {
            while ($takenCounts[$src] < $quota && $pointers[$src] < count($cleanedSources[$src])) {
                $item = $cleanedSources[$src][$pointers[$src]++];
                $isNew = $addOrMerge($item);
                if ($isNew) {
                    $takenCounts[$src]++;
                }
                if (count($finalLeads) >= $limit) {
                    break 2;
                }
            }
        }

        // Pass 2: Spillover round-robin for unused slots
        $hasMore = true;
        while (count($finalLeads) < $limit && $hasMore) {
            $hasMore = false;
            foreach ($cleanedSources as $src => $items) {
                if ($pointers[$src] < count($items)) {
                    $item = $items[$pointers[$src]++];
                    $hasMore = true;
                    $addOrMerge($item);
                    if (count($finalLeads) >= $limit) {
                        break 2;
                    }
                }
            }
        }

        return array_slice($finalLeads, 0, $limit);
    }
    
    private function logRequest($source, $query, $status, $duration)
    {
        $logFile = __DIR__ . '/../../../../storage/logs/leadfinder.log';
        if (!file_exists(dirname($logFile))) {
            @mkdir(dirname($logFile), 0777, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        $msg = "[$timestamp] SOURCE: $source | QUERY: $query | STATUS: $status | TIME: {$duration}s\n";
        @file_put_contents($logFile, $msg, FILE_APPEND);
    }
}
