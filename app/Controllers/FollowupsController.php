<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Security;
use App\Models\Company;
use App\Models\FollowUp;
use App\Services\CRM\SalesCrmService;

class FollowupsController extends Controller
{
    private $followUpModel;

    public function __construct()
    {
        Auth::requireLogin();
        $this->followUpModel = new FollowUp();
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->error404();
        }

        Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        $companyId = isset($_POST['company_id']) && is_numeric($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
        $companyModel = new Company();
        $company = $companyModel->findById($companyId);
        if (!$company) {
            if (!empty($_POST['is_ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Geçersiz firma.']);
                exit;
            }
            die('Geçersiz firma.');
        }

        $title = trim($_POST['title'] ?? '');
        if (empty($title)) {
            if (!empty($_POST['is_ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Takip başlığı zorunludur.']);
                exit;
            }
            die('Takip başlığı zorunludur.');
        }

        $dueDate = trim($_POST['due_date'] ?? date('Y-m-d'));
        $dueTime = trim($_POST['due_time'] ?? '12:00');
        $dueAt = $dueDate . ' ' . (strlen($dueTime) === 5 ? $dueTime . ':00' : $dueTime);
        if (!strtotime($dueAt)) {
            $dueAt = date('Y-m-d 12:00:00');
        }

        $priority = in_array($_POST['priority'] ?? '', ['low', 'normal', 'high']) ? $_POST['priority'] : 'normal';
        $notes = trim($_POST['notes'] ?? '');
        $userId = Auth::user()['id'] ?? null;

        $newId = $this->followUpModel->create([
            'company_id' => $companyId,
            'title' => $title,
            'notes' => $notes ?: null,
            'due_at' => $dueAt,
            'status' => 'pending',
            'priority' => $priority,
            'created_by' => $userId
        ]);

        if (!empty($_POST['is_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'id' => $newId]);
            exit;
        }

        $this->redirect('/companies/show/' . $companyId . '?msg=followup_created');
    }

    public function complete($id)
    {
        if (!is_numeric($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->error404();
        }

        Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        $userId = Auth::user()['id'] ?? null;
        $res = SalesCrmService::completeFollowUp((int)$id, $userId);

        if (!empty($_POST['is_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode($res);
            exit;
        }

        $companyId = $res['company_id'] ?? 0;
        if ($companyId) {
            $this->redirect('/companies/show/' . $companyId . '?msg=followup_completed');
        } else {
            $this->redirect('/');
        }
    }

    public function cancel($id)
    {
        if (!is_numeric($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->error404();
        }

        Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        $fu = $this->followUpModel->findById((int)$id);
        if (!$fu) return $this->error404();

        $this->followUpModel->cancel((int)$id);

        if (!empty($_POST['is_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        $this->redirect('/companies/show/' . $fu['company_id'] . '?msg=followup_cancelled');
    }

    public function delete($id)
    {
        if (!is_numeric($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->error404();
        }

        Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        $fu = $this->followUpModel->findById((int)$id);
        if (!$fu) return $this->error404();

        $this->followUpModel->delete((int)$id);

        if (!empty($_POST['is_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        $this->redirect('/companies/show/' . $fu['company_id'] . '?msg=followup_deleted');
    }

    private function error404()
    {
        http_response_code(404);
        $this->view('errors/404', ['page_title' => 'Sayfa Bulunamadı'], 'main');
        exit;
    }
}
