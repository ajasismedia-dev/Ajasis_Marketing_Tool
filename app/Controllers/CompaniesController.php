<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Security;
use App\Models\Company;

class CompaniesController extends Controller
{
    private $companyModel;

    public function __construct()
    {
        Auth::requireLogin();
        $this->companyModel = new Company();
    }

    public function index()
    {
        $filters = [
            'q' => $_GET['q'] ?? '',
            'status' => $_GET['status'] ?? '',
            'sector' => $_GET['sector'] ?? '',
            'district' => $_GET['district'] ?? ''
        ];

        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $companies = $this->companyModel->getAll($filters, $limit, $offset);
        $total = $this->companyModel->countAllFiltered($filters);
        $totalPages = ceil($total / $limit);

        $this->view('companies/index', [
            'page_title' => 'Firmalar',
            'companies' => $companies,
            'filters' => $filters,
            'page' => $page,
            'totalPages' => $totalPages
        ], 'main');
    }

    public function create()
    {
        $this->view('companies/create', ['page_title' => 'Yeni Firma Ekle', 'duplicateWarning' => null, 'formData' => []], 'main');
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/companies');
        }

        Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        $data = $this->extractFormData($_POST);
        
        if (empty($data['name'])) {
            die('Firma adı zorunludur.');
        }

        if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->view('companies/create', ['page_title' => 'Yeni Firma Ekle', 'error' => 'Geçerli bir e-posta adresi girin.', 'formData' => $data], 'main');
        }

        $validStatuses = ['new', 'contacted', 'replied', 'proposal', 'customer', 'negative'];
        if (!in_array($data['status'], $validStatuses)) {
            $data['status'] = 'new';
        }

        // Duplicate check unless confirmed
        if (empty($_POST['confirm_duplicate'])) {
            $duplicate = $this->companyModel->findPotentialDuplicate($data['name'], $data['phone'], $data['website']);
            if ($duplicate) {
                return $this->view('companies/create', [
                    'page_title' => 'Yeni Firma Ekle',
                    'duplicateWarning' => $duplicate,
                    'formData' => $data
                ], 'main');
            }
        }

        $this->companyModel->create($data);
        $this->redirect('/companies');
    }

    public function edit($id)
    {
        if (!is_numeric($id)) return $this->error404();

        $company = $this->companyModel->findById($id);
        if (!$company) return $this->error404();

        $this->view('companies/edit', ['page_title' => 'Firma Düzenle', 'company' => $company], 'main');
    }

    public function update($id)
    {
        if (!is_numeric($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') return $this->error404();

        Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        $data = $this->extractFormData($_POST);
        if (empty($data['name'])) {
            die('Firma adı zorunludur.');
        }

        if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $company = $this->companyModel->findById($id);
            $company = array_merge($company, $data);
            return $this->view('companies/edit', ['page_title' => 'Firma Düzenle', 'error' => 'Geçerli bir e-posta adresi girin.', 'company' => $company], 'main');
        }

        $validStatuses = ['new', 'contacted', 'replied', 'proposal', 'customer', 'negative'];
        if (!in_array($data['status'], $validStatuses)) {
            $data['status'] = 'new';
        }

        $this->companyModel->update($id, $data);
        $this->redirect('/companies/show/' . $id);
    }

    public function show($id)
    {
        if (!is_numeric($id)) return $this->error404();

        $company = $this->companyModel->findById($id);
        if (!$company) return $this->error404();

        $this->view('companies/show', ['page_title' => 'Firma Detayı', 'company' => $company], 'main');
    }

    public function delete($id)
    {
        if (!is_numeric($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') return $this->error404();
        Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        $this->companyModel->delete($id);
        $this->redirect('/companies');
    }

    public function markContacted($id)
    {
        if (!is_numeric($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') return $this->error404();
        Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        $company = $this->companyModel->findById($id);
        if (!$company) return $this->error404();

        $updateData = [
            'status' => 'contacted',
            'last_contact_at' => date('Y-m-d H:i:s')
        ];

        if (empty($company['first_contact_at'])) {
            $updateData['first_contact_at'] = date('Y-m-d H:i:s');
        }

        $this->companyModel->update($id, $updateData);
        $this->redirect('/companies/show/' . $id);
    }

    private function extractFormData($postData)
    {
        return [
            'name' => trim($postData['name'] ?? ''),
            'sector' => trim($postData['sector'] ?? ''),
            'phone' => trim(preg_replace('/[^0-9+]/', '', $postData['phone'] ?? '')),
            'whatsapp' => trim(preg_replace('/[^0-9+]/', '', $postData['whatsapp'] ?? '')),
            'email' => filter_var(trim($postData['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '',
            'website' => $this->normalizeUrl(trim($postData['website'] ?? '')),
            'instagram' => $this->normalizeUrl(trim($postData['instagram'] ?? '')),
            'facebook' => $this->normalizeUrl(trim($postData['facebook'] ?? '')),
            'linkedin' => $this->normalizeUrl(trim($postData['linkedin'] ?? '')),
            'address' => trim($postData['address'] ?? ''),
            'district' => trim($postData['district'] ?? ''),
            'city' => trim($postData['city'] ?? 'Konya'),
            'source' => trim($postData['source'] ?? ''),
            'status' => trim($postData['status'] ?? 'new'),
            'notes' => trim($postData['notes'] ?? '')
        ];
    }

    private function normalizeUrl($url)
    {
        if (empty($url)) return '';
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = "http://" . $url;
        }
        return $url;
    }

    private function error404()
    {
        http_response_code(404);
        $this->view('errors/404', ['page_title' => 'Sayfa Bulunamadı'], 'main');
        exit;
    }
}
