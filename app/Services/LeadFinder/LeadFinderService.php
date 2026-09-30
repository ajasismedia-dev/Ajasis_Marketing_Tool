<?php

namespace App\Services\LeadFinder;

use App\Services\LeadFinder\Sources\KsoSource;
use App\Services\LeadFinder\Sources\ListOfCompanySource;
use App\Services\LeadFinder\Sources\OpenStreetMapSource;
use App\Services\LeadFinder\Helpers\LeadNormalizer;
use App\Models\Company;

class LeadFinderService
{
    private $sources = [];

    public function __construct($sourceKey = 'all')
    {
        if ($sourceKey === 'all' || $sourceKey === 'kso') {
            $this->sources[] = new KsoSource();
        }
        if ($sourceKey === 'all' || $sourceKey === 'listofcompany') {
            $this->sources[] = new ListOfCompanySource();
        }
        if ($sourceKey === 'all' || $sourceKey === 'osm') {
            $this->sources[] = new OpenStreetMapSource();
        }
    }

    public function search($query, $city = 'Konya', $district = '', $limit = 25)
    {
        $allResults = [];
        $perSourceLimit = ceil($limit / max(1, count($this->sources)));

        foreach ($this->sources as $source) {
            try {
                $results = $source->search($query, $city, $district, $perSourceLimit);
                if (is_array($results)) {
                    $allResults = array_merge($allResults, $results);
                }
            } catch (\Exception $e) {
                // Log and continue
                error_log("LeadFinderService Error: " . $e->getMessage());
            }
        }

        // Deduplicate and merge results
        $mergedResults = LeadNormalizer::deduplicate($allResults);

        // Limit final results
        $mergedResults = array_slice($mergedResults, 0, $limit);

        // Check against DB
        try {
            $companyModel = new Company();
            foreach ($mergedResults as &$lead) {
                $dbMatch = $companyModel->findPotentialDuplicate($lead['name'], $lead['phone'] ?? '', $lead['website'] ?? '');
                if ($dbMatch) {
                    $lead['db_id'] = $dbMatch['id'];
                    $fullDb = $companyModel->findById($dbMatch['id']);
                    $lead['db_status'] = $fullDb['status'];
                } else {
                    $lead['db_id'] = null;
                }
            }
        } catch (\Exception $e) {
            foreach ($mergedResults as &$lead) {
                $lead['db_id'] = null;
            }
        }

        return $mergedResults;
    }
}
