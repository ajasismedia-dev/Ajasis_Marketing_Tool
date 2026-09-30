<?php

namespace App\Services\LeadFinder;

interface LeadSourceInterface
{
    /**
     * @param string $query
     * @param string $city
     * @param string $district
     * @param int $limit
     * @return array
     */
    public function search($query, $city, $district, $limit);
}
