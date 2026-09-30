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
        $query = $_GET['q'] ?? '';
        $city = $_GET['city'] ?? 'Konya';
        $district = $_GET['district'] ?? '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
        $sourceKey = $_GET['source'] ?? 'all';

        $leads = [];

        if (!empty($query)) {
            $finder = new LeadFinderService($sourceKey);
            $leads = $finder->search($query, $city, $district, $limit);
        }

        $this->view('leads/index', [
            'page_title' => 'Firma Bul',
            'leads' => $leads,
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

    private function error404()
    {
        http_response_code(404);
        $this->view('errors/404', ['page_title' => 'Sayfa Bulunamadı'], 'main');
        exit;
    }
}
