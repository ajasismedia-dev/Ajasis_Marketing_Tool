<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Security;
use App\Models\Communication;
use App\Models\Company;
use App\Services\CRM\SalesCrmService;

class HistoryController extends Controller
{
    private $commModel;

    public function __construct()
    {
        Auth::requireLogin();
        $this->commModel = new Communication();
    }

    public function index()
    {
        $dateStart = '';
        if (!empty($_GET['date_start'])) {
            $d = \DateTime::createFromFormat('Y-m-d', $_GET['date_start']);
            if ($d && $d->format('Y-m-d') === $_GET['date_start']) {
                $dateStart = $_GET['date_start'];
            }
        }

        $dateEnd = '';
        if (!empty($_GET['date_end'])) {
            $d = \DateTime::createFromFormat('Y-m-d', $_GET['date_end']);
            if ($d && $d->format('Y-m-d') === $_GET['date_end']) {
                $dateEnd = $_GET['date_end'];
            }
        }

        $filters = [
            'company_id' => isset($_GET['company_id']) && is_numeric($_GET['company_id']) ? (int)$_GET['company_id'] : '',
            'type' => !empty($_GET['type']) && in_array($_GET['type'], SalesCrmService::$validTypes) ? $_GET['type'] : '',
            'direction' => !empty($_GET['direction']) && in_array($_GET['direction'], SalesCrmService::$validDirections) ? $_GET['direction'] : '',
            'outcome' => !empty($_GET['outcome']) && in_array($_GET['outcome'], SalesCrmService::$validOutcomes) ? $_GET['outcome'] : '',
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'q' => trim($_GET['q'] ?? '')
        ];

        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $communications = $this->commModel->getAll($filters, $limit, $offset);
        $total = $this->commModel->countFiltered($filters);
        $totalPages = ceil($total / $limit);

        // Stats
        $stats = [
            'today' => $this->commModel->getTodayCount(),
            'week' => $this->commModel->getThisWeekCount(),
            'replied' => $this->commModel->getRepliedCount(),
            'proposals' => $this->commModel->getProposalsCount()
        ];

        // All companies for filter dropdown
        $companyModel = new Company();
        $allCompanies = $companyModel->getAll([], 500);

        $this->view('history/index', [
            'page_title' => 'İletişim Geçmişi',
            'communications' => $communications,
            'filters' => $filters,
            'stats' => $stats,
            'companies' => $allCompanies,
            'page' => $page,
            'total' => $total,
            'totalPages' => $totalPages
        ], 'main');
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

        $type = in_array($_POST['type'] ?? '', SalesCrmService::$validTypes) ? $_POST['type'] : 'other';
        $direction = in_array($_POST['direction'] ?? '', SalesCrmService::$validDirections) ? $_POST['direction'] : 'outbound';

        // Internal note integrity: note channel forces internal direction
        if ($type === 'note') {
            $direction = 'internal';
        }

        // Direction internal forces outcome to null
        if ($direction === 'internal') {
            $outcome = null;
        } else {
            $outcome = (!empty($_POST['outcome']) && in_array($_POST['outcome'], SalesCrmService::$validOutcomes)) ? $_POST['outcome'] : null;
        }

        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        // Strict date and time validation
        $cDate = trim($_POST['contacted_date'] ?? date('Y-m-d'));
        $cTime = trim($_POST['contacted_time'] ?? date('H:i'));
        if (strlen($cTime) === 8) {
            $cTime = substr($cTime, 0, 5);
        }
        $cDateTimeStr = $cDate . ' ' . $cTime;
        $dt = \DateTime::createFromFormat('Y-m-d H:i', $cDateTimeStr);
        if (!$dt || $dt->format('Y-m-d H:i') !== $cDateTimeStr) {
            $errMsg = 'Geçersiz iletişim tarihi veya saati.';
            if (!empty($_POST['is_ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $errMsg]);
                exit;
            }
            die($errMsg);
        }

        // Future date check (+300s grace period for minor server clock drift)
        if ($dt->getTimestamp() > time() + 300) {
            $errMsg = 'İletişim tarihi gelecekte olamaz.';
            if (!empty($_POST['is_ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $errMsg]);
                exit;
            }
            die($errMsg);
        }

        $contactedAt = $dt->format('Y-m-d H:i:s');

        $commData = [
            'type' => $type,
            'direction' => $direction,
            'outcome' => $outcome,
            'subject' => $subject,
            'message' => $message,
            'contacted_at' => $contactedAt
        ];

        // Optional follow-up
        $followUpData = null;
        if (!empty($_POST['create_followup']) && !empty($_POST['follow_up_title'])) {
            $fuDate = trim($_POST['follow_up_due_date'] ?? date('Y-m-d', strtotime('+2 days')));
            $fuTime = trim($_POST['follow_up_due_time'] ?? '10:00');
            if (strlen($fuTime) === 8) {
                $fuTime = substr($fuTime, 0, 5);
            }
            $fuDateTimeStr = $fuDate . ' ' . $fuTime;
            $fuDt = \DateTime::createFromFormat('Y-m-d H:i', $fuDateTimeStr);
            if (!$fuDt || $fuDt->format('Y-m-d H:i') !== $fuDateTimeStr) {
                $errMsg = 'Geçersiz takip tarihi veya saati.';
                if (!empty($_POST['is_ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $errMsg]);
                    exit;
                }
                die($errMsg);
            }
            $fuDueAt = $fuDt->format('Y-m-d H:i:s');

            $followUpData = [
                'title' => trim($_POST['follow_up_title']),
                'due_at' => $fuDueAt,
                'priority' => in_array($_POST['follow_up_priority'] ?? '', ['low', 'normal', 'high']) ? $_POST['follow_up_priority'] : 'normal',
                'notes' => trim($_POST['follow_up_notes'] ?? '')
            ];
        }


        $userId = Auth::user()['id'] ?? null;
        $res = SalesCrmService::logCommunication($companyId, $commData, $followUpData, $userId);

        if (!empty($_POST['is_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode($res);
            exit;
        }

        if ($res['success']) {
            $this->redirect('/companies/show/' . $companyId . '?msg=comm_logged');
        } else {
            die('Hata: ' . ($res['error'] ?? 'İletişim kaydedilemedi.'));
        }
    }

    public function delete($id)
    {
        if (!is_numeric($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->error404();
        }

        Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        $comm = $this->commModel->findById((int)$id);
        if (!$comm) return $this->error404();

        $this->commModel->delete((int)$id);

        if (!empty($_POST['is_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : '/companies/show/' . $comm['company_id'];
        $this->redirect($redirect);
    }

    private function error404()
    {
        http_response_code(404);
        $this->view('errors/404', ['page_title' => 'Sayfa Bulunamadı'], 'main');
        exit;
    }
}
