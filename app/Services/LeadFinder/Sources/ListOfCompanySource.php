<?php

namespace App\Services\LeadFinder\Sources;

use App\Services\LeadFinder\LeadSourceInterface;

class ListOfCompanySource implements LeadSourceInterface
{
    public function search($query, $city, $district, $limit)
    {
        // Site returns 404 or blocks via Cloudflare. Marking as unavailable.
        return [];
    }
}
