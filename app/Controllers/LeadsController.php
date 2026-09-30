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
        \App\Helpers\Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        $url = trim($_POST['url'] ?? '');
        if (empty($url)) {
            die(json_encode(['success' => false, 'message' => 'URL eksik']));
        }

        $data = [];
        if (strpos($url, 'kso.org.tr') !== false) {
            $kso = new \App\Services\LeadFinder\Sources\KsoSource();
            $lead = ['source_url' => $url];
            $enriched = $kso->enrichResult($lead);
            $data = $enriched;
            unset($data['source_url']); // remove internal key
        } else {
            $data = \App\Services\LeadFinder\Enrichment\WebsiteEnricher::enrich($url);
        }

        if ($data) {
            $normalizer = new \App\Services\LeadFinder\Helpers\LeadNormalizer();
            $data['phone'] = $normalizer->normalizePhone($data['phone'] ?? '');
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Yeni bilgi bulunamadı veya erişim engellendi.']);
        }
        exit;
    }

    public function save()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return $this->error404();
        \App\Helpers\Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        // Whitelist fields
        $fields = [
            'name', 'sector', 'phone', 'whatsapp', 'email', 'website', 'instagram', 
            'facebook', 'linkedin', 'address', 'district', 'city', 'source'
        ];
        
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = trim($_POST[$field] ?? '');
        }

        if (empty($data['name'])) {
            die(json_encode(['success' => false, 'message' => 'Firma adı zorunludur.']));
        }

        // Email validation
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $data['email'] = '';
        }

        // Normalizations
        $normalizer = new \App\Services\LeadFinder\Helpers\LeadNormalizer();
        $data['phone'] = $normalizer->normalizePhone($data['phone']);
        $data['website'] = $normalizer->formatUrl($data['website']);
        $data['instagram'] = $normalizer->normalizeSocialUrl($data['instagram']);
        $data['facebook'] = $normalizer->normalizeSocialUrl($data['facebook']);
        $data['linkedin'] = $normalizer->normalizeSocialUrl($data['linkedin']);

        // Auto-assign whatsapp if phone is mobile and whatsapp is empty
        if (empty($data['whatsapp']) && !empty($data['phone'])) {
            if (strlen($data['phone']) === 12 && strpos($data['phone'], '905') === 0) {
                $data['whatsapp'] = $data['phone'];
            }
        }

        $companyModel = new \App\Models\Company();
        
        // Duplicate check
        $duplicate = $companyModel->findPotentialDuplicate($data['name'], $data['phone'], $data['website']);
        if ($duplicate) {
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
