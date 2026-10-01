<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Security;
use App\Models\Company;
use App\Models\MessageTemplate;
use App\Models\Communication;
use App\Models\OutboundMessage;
use App\Services\Messaging\TemplateRenderer;
use App\Services\Messaging\EmailService;
use App\Services\CRM\SalesCrmService;
use App\Services\LeadFinder\Helpers\LeadNormalizer;

class MessagesController extends Controller
{
    private $templateModel;
    private $companyModel;
    private $commModel;
    private $outboundModel;

    public function __construct()
    {
        Auth::requireLogin();
        $this->templateModel = new MessageTemplate();
        $this->companyModel = new Company();
        $this->commModel = new Communication();
        $this->outboundModel = new OutboundMessage();

        // Ensure default templates exist
        $this->templateModel->seedDefaults();
    }

    /**
     * Main Message Center Page (/messages)
     */
    public function index()
    {
        $selectedCompanyId = isset($_GET['company_id']) && is_numeric($_GET['company_id']) ? (int)$_GET['company_id'] : null;
        $selectedChannel = isset($_GET['channel']) && in_array($_GET['channel'], ['whatsapp', 'email'], true) ? $_GET['channel'] : 'whatsapp';
        $selectedTemplateId = isset($_GET['template_id']) && is_numeric($_GET['template_id']) ? (int)$_GET['template_id'] : null;

        $selectedCompany = null;
        if ($selectedCompanyId) {
            $selectedCompany = $this->companyModel->findById($selectedCompanyId);
        }

        // Active templates for channel
        $channelTemplates = $this->templateModel->getActiveByChannel($selectedChannel);

        // Determine current template
        $currentTemplate = null;
        if ($selectedTemplateId) {
            $currentTemplate = $this->templateModel->findById($selectedTemplateId);
        }
        if (!$currentTemplate) {
            $currentTemplate = $this->templateModel->getDefaultByChannel($selectedChannel);
        }

        // Render initial message if company and template exist
        $renderedSubject = '';
        $renderedBody = '';
        if ($currentTemplate) {
            $renderedSubject = TemplateRenderer::render($currentTemplate['subject'] ?? '', $selectedCompany ?: []);
            $renderedBody = TemplateRenderer::render($currentTemplate['body'] ?? '', $selectedCompany ?: []);
        }

        // Resolve targets and warnings
        $targetInfo = $this->resolveTarget($selectedCompany, $selectedChannel);

        // All templates for management section
        $allTemplates = $this->templateModel->getAll();

        // Recent outbound messages (last 25)
        $recentOutbound = $this->getRecentOutbound(25);

        // SMTP Status
        $smtpStatus = EmailService::getStatus();

        $this->view('messages/index', [
            'page_title'        => 'Mesaj Merkezi',
            'selectedCompany'   => $selectedCompany,
            'selectedChannel'   => $selectedChannel,
            'channelTemplates'  => $channelTemplates,
            'currentTemplate'   => $currentTemplate,
            'renderedSubject'   => $renderedSubject,
            'renderedBody'      => $renderedBody,
            'targetInfo'        => $targetInfo,
            'allTemplates'      => $allTemplates,
            'recentOutbound'    => $recentOutbound,
            'smtpStatus'        => $smtpStatus,
            'businessName'      => defined('BUSINESS_NAME') ? BUSINESS_NAME : 'Ajasis Media'
        ], 'main');
    }

    /**
     * Search companies for composer autocomplete (AJAX: GET /messages/company-search?q=)
     */
    public function companySearch()
    {
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? '');

        if (mb_strlen($q) < 2) {
            echo json_encode(['success' => true, 'companies' => []]);
            exit;
        }

        $db = \App\Core\Database::getInstance()->getConnection();
        $sql = "SELECT id, name, sector, phone, whatsapp, email, district, city, website 
                FROM companies 
                WHERE name LIKE :q OR phone LIKE :q OR whatsapp LIKE :q OR email LIKE :q 
                ORDER BY name ASC LIMIT 20";
        $stmt = $db->prepare($sql);
        $stmt->execute(['q' => '%' . $q . '%']);
        $companies = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'companies' => $companies]);
        exit;
    }

    /**
     * Server-side render template with company data (AJAX: POST /messages/render)
     */
    public function render()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Geçersiz istek türü.']);
            exit;
        }

        Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        $companyId = isset($_POST['company_id']) && is_numeric($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
        $templateId = isset($_POST['template_id']) && is_numeric($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
        $channel = isset($_POST['channel']) && in_array($_POST['channel'], ['whatsapp', 'email'], true) ? $_POST['channel'] : 'whatsapp';

        $company = $companyId ? $this->companyModel->findById($companyId) : [];
        if ($companyId && !$company) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Firma bulunamadı.']);
            exit;
        }

        $template = null;
        if ($templateId) {
            $template = $this->templateModel->findById($templateId);
            if (!$template || !$template['is_active']) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Geçersiz veya pasif şablon.']);
                exit;
            }
            $channel = $template['channel'];
        } else {
            $template = $this->templateModel->getDefaultByChannel($channel);
        }

        $subjectRaw = $template['subject'] ?? '';
        $bodyRaw = $template['body'] ?? '';

        $renderedSubject = TemplateRenderer::render($subjectRaw, $company ?: []);
        $renderedBody = TemplateRenderer::render($bodyRaw, $company ?: []);

        $targetInfo = $this->resolveTarget($company, $channel);

        echo json_encode([
            'success'        => true,
            'template_id'    => $template['id'] ?? null,
            'template_name'  => $template['name'] ?? null,
            'channel'        => $channel,
            'subject'        => $renderedSubject,
            'body'           => $renderedBody,
            'target'         => $targetInfo['target'],
            'target_valid'   => $targetInfo['valid'],
            'target_warning' => $targetInfo['warning']
        ]);
        exit;
    }

    /**
     * Dispatcher for template subroutes (/messages/templates/{action}/{id})
     */
    public function templates($action = 'store', $id = null)
    {
        if ($action === 'store') return $this->storeTemplate();
        if ($action === 'update') return $this->updateTemplate($id);
        if ($action === 'delete') return $this->deleteTemplate($id);
        if ($action === 'default') return $this->setDefaultTemplate($id);
        return $this->error404();
    }

    /**
     * Create new template (POST /messages/templates/store)
     */
    public function storeTemplate()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return $this->error404();
        Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        $name = trim($_POST['name'] ?? '');
        $channel = in_array($_POST['channel'] ?? '', ['whatsapp', 'email'], true) ? $_POST['channel'] : 'whatsapp';
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $isDefault = !empty($_POST['is_default']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        if (empty($name)) {
            $this->respond(['success' => false, 'error' => 'Şablon adı zorunludur.'], 400);
            return;
        }
        if (mb_strlen($name) > 150) {
            $this->respond(['success' => false, 'error' => 'Şablon adı maksimum 150 karakter olabilir.'], 400);
            return;
        }
        if (empty($body)) {
            $this->respond(['success' => false, 'error' => 'Mesaj içeriği boş olamaz.'], 400);
            return;
        }
        if (mb_strlen($body) > 10000) {
            $this->respond(['success' => false, 'error' => 'Mesaj içeriği maksimum 10.000 karakter olabilir.'], 400);
            return;
        }
        if ($channel === 'email' && mb_strlen($subject) > 255) {
            $this->respond(['success' => false, 'error' => 'Konu maksimum 255 karakter olabilir.'], 400);
            return;
        }

        $userId = Auth::user()['id'] ?? null;

        $newId = $this->templateModel->create([
            'name'       => $name,
            'channel'    => $channel,
            'subject'    => $channel === 'email' ? $subject : null,
            'body'       => $body,
            'is_default' => $isDefault,
            'is_active'  => $isActive,
            'created_by' => $userId
        ]);

        $this->respond([
            'success' => true,
            'id'      => $newId,
            'message' => 'Şablon başarıyla oluşturuldu.'
        ], 200, '/messages#templates');
    }

    /**
     * Update template (POST /messages/templates/update/{id})
     */
    public function updateTemplate($id)
    {
        if (!is_numeric($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') return $this->error404();
        Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        $template = $this->templateModel->findById((int)$id);
        if (!$template) {
            $this->respond(['success' => false, 'error' => 'Şablon bulunamadı.'], 404);
            return;
        }

        $name = trim($_POST['name'] ?? $template['name']);
        $channel = in_array($_POST['channel'] ?? $template['channel'], ['whatsapp', 'email'], true) ? $_POST['channel'] : $template['channel'];
        $subject = trim($_POST['subject'] ?? ($template['subject'] ?? ''));
        $body = trim($_POST['body'] ?? $template['body']);
        $isDefault = isset($_POST['is_default']) ? (!empty($_POST['is_default']) ? 1 : 0) : $template['is_default'];
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : $template['is_active'];

        if (empty($name)) {
            $this->respond(['success' => false, 'error' => 'Şablon adı zorunludur.'], 400);
            return;
        }
        if (empty($body)) {
            $this->respond(['success' => false, 'error' => 'Mesaj içeriği boş olamaz.'], 400);
            return;
        }
        if (mb_strlen($body) > 10000) {
            $this->respond(['success' => false, 'error' => 'Mesaj içeriği maksimum 10.000 karakter olabilir.'], 400);
            return;
        }

        $this->templateModel->update((int)$id, [
            'name'       => $name,
            'channel'    => $channel,
            'subject'    => $channel === 'email' ? $subject : null,
            'body'       => $body,
            'is_default' => $isDefault,
            'is_active'  => $isActive
        ]);

        if ($isDefault && !$template['is_default']) {
            $this->templateModel->setDefault((int)$id, $channel);
        }

        $this->respond([
            'success' => true,
            'message' => 'Şablon başarıyla güncellendi.'
        ], 200, '/messages#templates');
    }

    /**
     * Delete template (POST /messages/templates/delete/{id})
     */
    public function deleteTemplate($id)
    {
        if (!is_numeric($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') return $this->error404();
        Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        $template = $this->templateModel->findById((int)$id);
        if (!$template) {
            $this->respond(['success' => false, 'error' => 'Şablon bulunamadı.'], 404);
            return;
        }

        $this->templateModel->delete((int)$id);

        $this->respond([
            'success' => true,
            'message' => 'Şablon silindi.'
        ], 200, '/messages#templates');
    }

    /**
     * Set template as default (POST /messages/templates/default/{id})
     */
    public function setDefaultTemplate($id)
    {
        if (!is_numeric($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') return $this->error404();
        Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        $template = $this->templateModel->findById((int)$id);
        if (!$template) {
            $this->respond(['success' => false, 'error' => 'Şablon bulunamadı.'], 404);
            return;
        }

        $this->templateModel->setDefault((int)$id, $template['channel']);

        $this->respond([
            'success' => true,
            'message' => 'Varsayılan şablon güncellendi.'
        ], 200, '/messages#templates');
    }

    /**
     * Log WhatsApp manual send to CRM timeline (POST /messages/log-whatsapp)
     */
    public function logWhatsapp()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Geçersiz istek türü.']);
            exit;
        }

        Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        $companyId = isset($_POST['company_id']) && is_numeric($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
        $company = $this->companyModel->findById($companyId);
        if (!$company) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Firma bulunamadı.']);
            exit;
        }

        $message = trim($_POST['message'] ?? '');
        if (empty($message)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Mesaj içeriği boş olamaz.']);
            exit;
        }
        if (mb_strlen($message) > 10000) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Mesaj maksimum 10.000 karakter olabilir.']);
            exit;
        }

        $subject = trim($_POST['subject'] ?? 'WhatsApp Mesajı');
        if (mb_strlen($subject) > 255) {
            $subject = mb_substr($subject, 0, 255);
        }

        $clientMessageId = trim($_POST['client_message_id'] ?? '');
        if (empty($clientMessageId)) {
            $clientMessageId = 'wa_' . bin2hex(random_bytes(16));
        }

        $userId = Auth::user()['id'] ?? null;

        // Idempotency check with OutboundMessage
        $existingOutbound = $this->outboundModel->findByClientMessageId($clientMessageId);
        if ($existingOutbound && in_array($existingOutbound['status'], ['logged', 'sent'], true)) {
            echo json_encode([
                'success'          => true,
                'idempotent'       => true,
                'communication_id' => $existingOutbound['communication_id'],
                'message'          => 'Bu WhatsApp iletişimi daha önce kaydedilmiş.'
            ]);
            exit;
        }

        if (!$existingOutbound) {
            $this->outboundModel->createPending($companyId, 'whatsapp', $clientMessageId, $subject, $message, $userId);
        }

        $commData = [
            'type'              => 'whatsapp',
            'direction'         => 'outbound',
            'outcome'           => 'sent',
            'subject'           => $subject,
            'message'           => $message,
            'client_message_id' => $clientMessageId,
            'contacted_at'      => date('Y-m-d H:i:s')
        ];

        $res = SalesCrmService::logCommunication($companyId, $commData, null, $userId);

        if ($res['success']) {
            $commId = $res['communication_id'] ?? null;
            $this->outboundModel->markLogged($clientMessageId, $commId);
            echo json_encode([
                'success'          => true,
                'communication_id' => $commId,
                'message'          => 'WhatsApp iletişimi başarıyla zaman tüneline kaydedildi.'
            ]);
        } else {
            $this->outboundModel->markFailed($clientMessageId, $res['error'] ?? 'Bilinmeyen hata');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $res['error'] ?? 'İletişim kaydedilemedi.']);
        }
        exit;
    }

    /**
     * Send single email via SMTP and log to CRM timeline (POST /messages/send-email)
     */
    public function sendEmail()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Geçersiz istek türü.']);
            exit;
        }

        Security::checkCsrfToken($_POST['csrf_token'] ?? '');

        $userId = Auth::user()['id'] ?? null;

        // Rate guard: max 10 attempts in 5 minutes per user
        if ($userId && $this->outboundModel->countAttemptsInWindow($userId, 300) >= 10) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'error'   => 'Çok fazla e-posta gönderme denemesi yapıldı. Lütfen birkaç dakika bekleyin.'
            ]);
            exit;
        }

        $companyId = isset($_POST['company_id']) && is_numeric($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
        $company = $this->companyModel->findById($companyId);
        if (!$company) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Firma bulunamadı.']);
            exit;
        }

        // Validate recipient email from DB
        $toEmail = trim($company['email'] ?? '');
        if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Bu firmaya ait geçerli bir e-posta adresi bulunmuyor.']);
            exit;
        }

        $subject = trim($_POST['subject'] ?? '');
        if (empty($subject)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'E-posta konusu zorunludur.']);
            exit;
        }
        $cleanSubject = trim(preg_replace('/[\r\n]+/', ' ', $subject));
        if (mb_strlen($cleanSubject) > 255) {
            $cleanSubject = mb_substr($cleanSubject, 0, 255);
        }

        $body = trim($_POST['body'] ?? '');
        if (empty($body)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'E-posta içeriği boş olamaz.']);
            exit;
        }
        if (mb_strlen($body) > 10000) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'E-posta içeriği maksimum 10.000 karakter olabilir.']);
            exit;
        }

        // Verify SMTP configuration
        if (!EmailService::isConfigured()) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'SMTP yapılandırması etkin değil veya eksik. Lütfen config ayarlarını kontrol edin.'
            ]);
            exit;
        }

        $clientMessageId = trim($_POST['client_message_id'] ?? '');
        if (empty($clientMessageId)) {
            $clientMessageId = 'em_' . bin2hex(random_bytes(16));
        }

        // Idempotency check with OutboundMessage
        $existing = $this->outboundModel->findByClientMessageId($clientMessageId);
        if ($existing) {
            if ($existing['status'] === 'sent') {
                echo json_encode([
                    'success'          => true,
                    'idempotent'       => true,
                    'communication_id' => $existing['communication_id'],
                    'message'          => 'Bu e-posta daha önce başarıyla gönderilmiş.'
                ]);
                exit;
            }
            if ($existing['status'] === 'pending') {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'error'   => 'Bu e-posta için gönderim işlemi devam ediyor. Lütfen bekleyin.'
                ]);
                exit;
            }
        }

        // Create pending record
        if (!$existing) {
            $this->outboundModel->createPending($companyId, 'email', $clientMessageId, $cleanSubject, $body, $userId);
        }

        // Send email via PHPMailer
        $sendResult = EmailService::send($toEmail, $company['name'], $cleanSubject, $body);

        if (!$sendResult['success']) {
            $this->outboundModel->markFailed($clientMessageId, $sendResult['error'] ?? 'SMTP hatası');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => $sendResult['error'] ?? 'E-posta gönderilemedi.'
            ]);
            exit;
        }

        // Email successfully sent: record communication in timeline
        $commData = [
            'type'              => 'email',
            'direction'         => 'outbound',
            'outcome'           => 'sent',
            'subject'           => $cleanSubject,
            'message'           => $body,
            'client_message_id' => $clientMessageId,
            'contacted_at'      => date('Y-m-d H:i:s')
        ];

        $crmRes = SalesCrmService::logCommunication($companyId, $commData, null, $userId);
        $commId = $crmRes['communication_id'] ?? null;

        $this->outboundModel->markSent($clientMessageId, $commId);

        echo json_encode([
            'success'          => true,
            'communication_id' => $commId,
            'message'          => 'E-posta başarıyla gönderildi ve zaman tüneline kaydedildi.'
        ]);
        exit;
    }

    /**
     * Resolve target phone or email and validation state for a company and channel
     */
    private function resolveTarget(?array $company, string $channel): array
    {
        if (!$company) {
            return [
                'target'  => '',
                'valid'   => false,
                'warning' => 'Firma seçilmedi.'
            ];
        }

        if ($channel === 'whatsapp') {
            // First check company.whatsapp
            $waRaw = trim($company['whatsapp'] ?? '');
            if (!empty($waRaw)) {
                $normWa = LeadNormalizer::normalizePhone($waRaw);
                if (strlen($normWa) >= 10 && strpos($normWa, '905') === 0) {
                    return [
                        'target'  => $normWa,
                        'valid'   => true,
                        'warning' => null
                    ];
                }
            }

            // Fallback to mobile-looking company.phone
            $phoneRaw = trim($company['phone'] ?? '');
            if (!empty($phoneRaw)) {
                $normPhone = LeadNormalizer::normalizePhone($phoneRaw);
                if (strlen($normPhone) >= 10 && strpos($normPhone, '905') === 0) {
                    return [
                        'target'  => $normPhone,
                        'valid'   => true,
                        'warning' => null
                    ];
                }
            }

            // Fixed line or no phone
            return [
                'target'  => '',
                'valid'   => false,
                'warning' => 'Bu firmada WhatsApp kullanılabilecek bir numara bulunmuyor.'
            ];
        }

        if ($channel === 'email') {
            $email = trim($company['email'] ?? '');
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'target'  => $email,
                    'valid'   => true,
                    'warning' => null
                ];
            }

            return [
                'target'  => '',
                'valid'   => false,
                'warning' => 'Bu firmada kayıtlı geçerli bir e-posta adresi bulunmuyor.'
            ];
        }

        return ['target' => '', 'valid' => false, 'warning' => 'Geçersiz kanal.'];
    }

    /**
     * Fetch recent 25 outbound communications of type whatsapp / email
     */
    private function getRecentOutbound(int $limit = 25): array
    {
        $db = \App\Core\Database::getInstance()->getConnection();
        $sql = "SELECT c.*, comp.name AS company_name, comp.phone AS company_phone, comp.email AS company_email, u.name AS user_name 
                FROM communications c 
                INNER JOIN companies comp ON c.company_id = comp.id 
                LEFT JOIN users u ON c.created_by = u.id 
                WHERE c.direction = 'outbound' AND c.type IN ('whatsapp', 'email') 
                ORDER BY c.contacted_at DESC, c.id DESC 
                LIMIT " . (int)$limit;
        $stmt = $db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Helper to respond as JSON or redirect
     */
    private function respond(array $data, int $statusCode = 200, ?string $redirectUrl = null)
    {
        if (!empty($_POST['is_ajax']) || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        }

        if ($redirectUrl) {
            $this->redirect($redirectUrl);
            return;
        }

        if ($statusCode !== 200) {
            die($data['error'] ?? 'Hata oluştu.');
        }
    }

    private function error404()
    {
        http_response_code(404);
        $this->view('errors/404', ['page_title' => 'Sayfa Bulunamadı'], 'main');
        exit;
    }
}
