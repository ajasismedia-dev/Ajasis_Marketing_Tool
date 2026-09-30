<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Security;
use App\Services\LeadFinder\LeadFinderService;
use App\Services\LeadFinder\Enrichment\WebsiteEnricher;

class LeadsController extends Controller
{
    public function __construct()
    {
        Auth::requireLogin();
    }

    public function index()
    {
        $query = trim($_GET['q'] ?? '');
        $city = trim($_GET['city'] ?? 'Konya');
        $district = trim($_GET['district'] ?? '');
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
        $sourceKey = $_GET['source'] ?? 'all';

        // Validation
        $allowedSources = ['all', 'kso', 'listofcompany', 'osm'];
        if (!in_array($sourceKey, $allowedSources)) {
            $sourceKey = 'all';
        }
        $allowedLimits = [10, 25, 50];
        if (!in_array($limit, $allowedLimits)) {
            $limit = 25;
        }
        if (mb_strlen($query) > 100) $query = mb_substr($query, 0, 100);
        if (mb_strlen($city) > 100) $city = mb_substr($city, 0, 100);
        if (mb_strlen($district) > 100) $district = mb_substr($district, 0, 100);

        $leads = [];
        $statuses = [];

        if (!empty($query)) {
            $finder = new LeadFinderService($sourceKey);
            $result = $finder->search($query, $city, $district, $limit);
            $leads = $result['leads'];
            $statuses = $result['statuses'];
        }

        $this->view('leads/index', [
            'page_title' => 'Firma Bul',
            'leads' => $leads,
            'statuses' => $statuses,
            'filters' => [
                'q' => $query,
                'city' => $city,
                'district' => $district,
                'limit' => $limit,
                'source' => $sourceKey
            ]
        ], 'main');
    }

    public function enrich()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return $this->error404();
        Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        $url = $_POST['url'] ?? '';
        if (empty($url)) {
            die(json_encode(['success' => false, 'message' => 'URL eksik']));
        }

        $data = WebsiteEnricher::enrich($url);

        if ($data) {
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Yeni bilgi bulunamadı veya erişim engellendi.']);
        }
    }

    public function save()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return $this->error404();
        Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        // Whitelist fields
        $fields = [
            'name', 'sector', 'phone', 'website', 'instagram', 
            'facebook', 'linkedin', 'address', 'district', 'city', 'source'
        ];
        
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $_POST[$field] ?? '';
        }

        if (empty($data['name'])) {
            // Handle error, though name is required in form
            die(json_encode(['success' => false, 'message' => 'Firma adı zorunludur.']));
        }

        $companyModel = new \App\Models\Company();
        
        // Duplicate check
        $duplicate = $companyModel->findPotentialDuplicate($data['name'], $data['phone'], $data['website']);
        if ($duplicate) {
            // Redirect to existing
            header("Location: " . BASE_PATH . "/companies/show/" . $duplicate['id'] . "?msg=duplicate");
            exit;
        }

        // Save
        $data['status'] = 'new';
        $newId = $companyModel->create($data);

        header("Location: " . BASE_PATH . "/companies/show/" . $newId);
        exit;
    }

    private function error404()
    {
        http_response_code(404);
        $this->view('errors/404', ['page_title' => 'Sayfa Bulunamadı'], 'main');
        exit;
    }
}
