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
        $allResults = [];
        $this->sourceStatuses = [];

        foreach ($this->sources as $name => $source) {
            $startTime = microtime(true);
            try {
                $results = $source->search($query, $city, $district, $limit);
                $duration = round(microtime(true) - $startTime, 2);
                
                if (is_array($results) && count($results) > 0) {
                    $allResults = array_merge($allResults, $results);
                    $this->sourceStatuses[$name] = [
                        'status' => 'success',
                        'count' => count($results),
                        'duration' => $duration . 's'
                    ];
                } else {
                    $this->sourceStatuses[$name] = [
                        'status' => method_exists($source, 'getLastStatus') ? $source->getLastStatus() : 'empty',
                        'count' => 0,
                        'duration' => $duration . 's'
                    ];
                }
                $this->logRequest($name, $query, $this->sourceStatuses[$name]['status'], $duration);
            } catch (\Exception $e) {
                $duration = round(microtime(true) - $startTime, 2);
                $this->sourceStatuses[$name] = [
                    'status' => 'error',
                    'count' => 0,
                    'duration' => $duration . 's'
                ];
                $this->logRequest($name, $query, 'error: ' . $e->getMessage(), $duration);
            }
        }

        $mergedResults = LeadNormalizer::deduplicate($allResults);
        $mergedResults = array_slice($mergedResults, 0, $limit);

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
