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

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        
        $data = [];
        if (in_array(strtolower($host), ['kso.org.tr', 'www.kso.org.tr'])) {
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
            echo "<script>alert('Firma adı zorunludur.'); history.back();</script>";
            exit;
        }

        // Email validation
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            echo "<script>alert('Firma kaydedilemedi: geçersiz e-posta adresi.'); history.back();</script>";
            exit;
        }

        // Normalizations
        $normalizer = new \App\Services\LeadFinder\Helpers\LeadNormalizer();
        $data['phone'] = $normalizer->normalizePhone($data['phone']);
        $data['whatsapp'] = $normalizer->normalizePhone($data['whatsapp']);
        $data['website'] = $normalizer->formatUrl($data['website']);
        $data['instagram'] = $normalizer->normalizeSocialUrl($data['instagram']);
        $data['facebook'] = $normalizer->normalizeSocialUrl($data['facebook']);
        $data['linkedin'] = $normalizer->normalizeSocialUrl($data['linkedin']);

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
