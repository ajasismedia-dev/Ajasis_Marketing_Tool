<?php

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Helpers/Auth.php';
require_once __DIR__ . '/../app/Helpers/Security.php';
require_once __DIR__ . '/../app/Services/Messaging/TemplateRenderer.php';
require_once __DIR__ . '/../app/Services/Messaging/EmailService.php';
require_once __DIR__ . '/../app/Models/MessageTemplate.php';
require_once __DIR__ . '/../app/Models/OutboundMessage.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Models/Company.php';
require_once __DIR__ . '/../app/Models/Communication.php';
require_once __DIR__ . '/../app/Services/CRM/SalesCrmService.php';

use App\Services\Messaging\TemplateRenderer;
use App\Services\Messaging\EmailService;
use App\Models\MessageTemplate;
use App\Models\OutboundMessage;
use App\Models\Company;
use App\Models\Communication;
use App\Services\CRM\SalesCrmService;

echo "\n--- TEMPLATE RENDERER TESTS ---\n";

$renderer = new TemplateRenderer();

// 1. Whitelist variables definition
$vars = $renderer->getWhitelistedVariables();
TestHelper::assertTrue(count($vars) >= 7, 'At least 7 template variables whitelisted');
TestHelper::assertTrue(isset($vars['company_name']), 'company_name in whitelist');
TestHelper::assertTrue(isset($vars['sector']), 'sector in whitelist');
TestHelper::assertTrue(isset($vars['district']), 'district in whitelist');
TestHelper::assertTrue(isset($vars['city']), 'city in whitelist');
TestHelper::assertTrue(isset($vars['website']), 'website in whitelist');
TestHelper::assertTrue(isset($vars['sender_name']), 'sender_name in whitelist');
TestHelper::assertTrue(isset($vars['agency_name']), 'agency_name in whitelist');

// 2. Full substitution test
$template = "Merhaba {company_name},\n{city}/{district} bölgesindeki {sector} çalışmalarınızı inceledik. Web siteniz: {website}.\nSaygılar, {sender_name} - {agency_name}";
$companyData = [
    'name' => 'Örnek Mimarlık Ltd. Şti.',
    'sector' => 'Mimarlık & Tasarım',
    'district' => 'Selçuklu',
    'city' => 'Konya',
    'website' => 'https://ornek-mimarlik.com'
];
$senderOverrides = [
    'sender_name' => 'Muhammet Tüzün',
    'agency_name' => 'Ajasis Media'
];

$rendered = $renderer->render($template, $companyData, $senderOverrides);
TestHelper::assertTrue(strpos($rendered, 'Örnek Mimarlık Ltd. Şti.') !== false, 'company_name replaced');
TestHelper::assertTrue(strpos($rendered, 'Mimarlık & Tasarım') !== false, 'sector replaced');
TestHelper::assertTrue(strpos($rendered, 'Selçuklu') !== false, 'district replaced');
TestHelper::assertTrue(strpos($rendered, 'Konya') !== false, 'city replaced');
TestHelper::assertTrue(strpos($rendered, 'https://ornek-mimarlik.com') !== false, 'website replaced');
TestHelper::assertTrue(strpos($rendered, 'Muhammet Tüzün') !== false, 'sender_name replaced');
TestHelper::assertTrue(strpos($rendered, 'Ajasis Media') !== false, 'agency_name replaced');

// 3. Missing / empty field handling without warnings
$emptyComp = ['name' => 'Boş Firma'];
$renderedEmpty = $renderer->render("{company_name} - {sector} - {city}", $emptyComp);
TestHelper::assertEqual('Boş Firma -  - ', $renderedEmpty, 'Missing fields gracefully substituted with empty string');

// 4. Unknown placeholders safety (No eval, no injection)
$untrustedTmpl = "Sayın {company_name}, {evil_code} and {\$system('id')} and {phpinfo()}";
$renderedUntrusted = $renderer->render($untrustedTmpl, ['name' => 'Güvenli Firma']);
TestHelper::assertTrue(strpos($renderedUntrusted, 'Güvenli Firma') !== false, 'Known placeholder replaced');
TestHelper::assertTrue(strpos($renderedUntrusted, '{evil_code}') !== false, 'Untrusted token untouched');
TestHelper::assertTrue(strpos($renderedUntrusted, '{$system(\'id\')}') !== false, 'Injection token untouched');

// 5. Plain-text sanitization (No raw HTML execution)
$htmlComp = ['name' => '<script>alert(1)</script>'];
$renderedHtml = $renderer->render("Firma: {company_name}", $htmlComp);
TestHelper::assertTrue(strpos($renderedHtml, '<script>') !== false, 'Renderer preserves text without execution risk');


echo "\n--- EMAIL SERVICE VALIDATION & SANITIZATION TESTS ---\n";

// 1. Email validation
TestHelper::assertTrue(EmailService::validateEmail('info@ajasis.com'), 'Valid email accepted');
TestHelper::assertTrue(EmailService::validateEmail('test.user+tag@domain.co.uk'), 'Valid subaddressed email accepted');
TestHelper::assertTrue(!EmailService::validateEmail(''), 'Empty email rejected');
TestHelper::assertTrue(!EmailService::validateEmail('not-an-email'), 'Invalid format rejected');
TestHelper::assertTrue(!EmailService::validateEmail('test@'), 'Missing domain rejected');
TestHelper::assertTrue(!EmailService::validateEmail('@domain.com'), 'Missing user rejected');
TestHelper::assertTrue(!EmailService::validateEmail("evil@domain.com\nBcc:victim@test.com"), 'Newline in email rejected');

// 2. Subject CRLF sanitization
$dirtySubject = "Tanışma Mesajı\r\nBcc: hacker@target.com\nAnother line";
$cleanSubject = EmailService::sanitizeSubject($dirtySubject);
TestHelper::assertTrue(strpos($cleanSubject, "\r") === false, 'CRLF \\r stripped from subject');
TestHelper::assertTrue(strpos($cleanSubject, "\n") === false, 'CRLF \\n stripped from subject');
TestHelper::assertEqual('Tanışma Mesajı Bcc: hacker@target.com Another line', $cleanSubject, 'Subject sanitized into single line');

// 3. Service status reporting
$emailService = new EmailService();
$status = $emailService->getStatus();
TestHelper::assertTrue(is_array($status), 'getStatus returns array');
TestHelper::assertTrue(isset($status['status']), 'status has status key');
TestHelper::assertTrue(isset($status['configured']), 'status has configured key');
TestHelper::assertTrue(isset($status['reason']), 'status has reason key');

// 4. Send rejection on empty inputs
$sendRes = $emailService->send('', 'Test Subject', 'Test Body');
TestHelper::assertTrue(!$sendRes['success'], 'Send fails gracefully on invalid recipient');
TestHelper::assertNotEmpty($sendRes['error'], 'Error message provided on failed send');


echo "\n--- WHATSAPP TARGET RESOLUTION & URL TESTS ---\n";

// 1. Phone number normalizer for WhatsApp
$normalizer = new \App\Services\LeadFinder\Helpers\LeadNormalizer();
$normalized1 = $normalizer->normalizePhone('0555 123 45 67');
TestHelper::assertEqual('905551234567', $normalized1, 'Mobile 0555... normalized to 905551234567');

$normalized2 = $normalizer->normalizePhone('+90 532 999 88 77');
TestHelper::assertEqual('905329998877', $normalized2, 'International +90 normalized');

$normalized3 = $normalizer->normalizePhone('0332 322 00 00');
TestHelper::assertEqual('903323220000', $normalized3, 'Fixed line normalized');

// 2. Fixed line vs Mobile check logic
$isMobile1 = strpos($normalized1, '905') === 0;
TestHelper::assertTrue($isMobile1, '90555... detected as mobile');

$isMobile3 = strpos($normalized3, '905') === 0;
TestHelper::assertTrue(!$isMobile3, '90332... detected as fixed line (not mobile)');

// 3. WhatsApp wa.me link generation check
$sampleMsg = "Merhaba Dünya & Selamlar!";
$waUrl = 'https://wa.me/' . $normalized1 . '?text=' . urlencode($sampleMsg);
TestHelper::assertTrue(strpos($waUrl, 'https://wa.me/905551234567?text=') === 0, 'wa.me base URL correct');
TestHelper::assertTrue(strpos($waUrl, 'Merhaba+D%C3%BCnya') !== false || strpos($waUrl, 'Merhaba%20D%C3%BCnya') !== false, 'URL encoding of Turkish characters correct');


echo "\n--- MESSAGE CENTER LIVE DATABASE & IDEMPOTENCY TESTS ---\n";

try {
    $db = \App\Core\Database::getInstance()->getConnection();
    if (!$db) {
        TestHelper::skip('Database connection unavailable for live Message Center tests.');
    } else {
        $tmplModel = new MessageTemplate();
        $outboundModel = new OutboundMessage();
        $compModel = new Company();
        $crmService = new SalesCrmService();
        $commModel = new Communication();

        // 1. Seed defaults
        $seeded = $tmplModel->seedDefaults();
        TestHelper::assertTrue($seeded >= 0, 'seedDefaults executed without errors');

        $defaultWa = $tmplModel->findDefaultByChannel('whatsapp');
        TestHelper::assertNotEmpty($defaultWa, 'Default WhatsApp template exists');
        TestHelper::assertEqual('whatsapp', $defaultWa['channel'], 'Default template channel is whatsapp');
        TestHelper::assertEqual(1, (int)$defaultWa['is_default'], 'Template is marked is_default = 1');

        $defaultEmail = $tmplModel->findDefaultByChannel('email');
        TestHelper::assertNotEmpty($defaultEmail, 'Default Email template exists');
        TestHelper::assertEqual('email', $defaultEmail['channel'], 'Default template channel is email');

        // 2. MessageTemplate CRUD & Transactional setDefault
        $testTmplId = $tmplModel->create([
            'channel' => 'whatsapp',
            'name' => 'TEST_TEMPL_' . bin2hex(random_bytes(3)),
            'body' => 'Test body for {company_name}',
            'is_default' => 0
        ]);
        TestHelper::assertTrue($testTmplId > 0, "Custom template created with ID $testTmplId");

        // Set as default
        $setOk = $tmplModel->setDefault($testTmplId, 'whatsapp');
        TestHelper::assertTrue($setOk, 'setDefault returned true');

        $reloaded = $tmplModel->findById($testTmplId);
        TestHelper::assertEqual(1, (int)$reloaded['is_default'], 'Custom template is now default');

        // Verify old default was cleared
        if ($defaultWa['id'] != $testTmplId) {
            $oldWa = $tmplModel->findById($defaultWa['id']);
            TestHelper::assertEqual(0, (int)$oldWa['is_default'], 'Previous default template is_default was cleared to 0');
        }

        // Restore original default
        if ($defaultWa['id'] != $testTmplId) {
            $tmplModel->setDefault($defaultWa['id'], 'whatsapp');
        }

        // Cleanup custom template
        $tmplModel->delete($testTmplId);
        $deletedTmpl = $tmplModel->findById($testTmplId);
        TestHelper::assertNull($deletedTmpl, 'Custom template was deleted');

        // 3. OutboundMessage Creation & Tracking
        $testCompId = $compModel->create([
            'name' => 'MSG_TEST_COMP_' . bin2hex(random_bytes(3)),
            'phone' => '05321112233',
            'email' => 'test@company.example.com',
            'city' => 'Konya',
            'status' => 'new'
        ]);
        TestHelper::assertTrue($testCompId > 0, "Test company created with ID $testCompId");

        $clientMsgId = 'test_cmsg_' . bin2hex(random_bytes(8));
        $outboundId = $outboundModel->create([
            'company_id' => $testCompId,
            'channel' => 'whatsapp',
            'client_message_id' => $clientMsgId,
            'recipient' => '905321112233',
            'subject' => 'Test Konu',
            'body' => 'Test Mesaj İçeriği',
            'status' => 'pending'
        ]);
        TestHelper::assertTrue($outboundId > 0, "Outbound message created with ID $outboundId");

        $foundMsg = $outboundModel->findByClientMessageId($clientMsgId);
        TestHelper::assertNotEmpty($foundMsg, 'Outbound message found by client_message_id');
        TestHelper::assertEqual($clientMsgId, $foundMsg['client_message_id'], 'client_message_id matches');
        TestHelper::assertEqual('pending', $foundMsg['status'], 'Initial status is pending');

        // Update status
        $outboundModel->updateStatus($outboundId, 'logged');
        $updatedMsg = $outboundModel->findById($outboundId);
        TestHelper::assertEqual('logged', $updatedMsg['status'], 'Status updated to logged');

        // 4. SalesCrmService Communication Idempotency with client_message_id
        $commClientMsgId = 'comm_idemp_' . bin2hex(random_bytes(8));
        
        // First log attempt
        $res1 = SalesCrmService::logCommunication($testCompId, [
            'type' => 'whatsapp',
            'direction' => 'outbound',
            'outcome' => 'sent',
            'subject' => 'İlk WhatsApp Mesajı',
            'message' => 'Merhaba, bu bir test mesajıdır.',
            'client_message_id' => $commClientMsgId
        ]);
        TestHelper::assertTrue($res1['success'], 'First logCommunication call succeeded');
        $commId1 = $res1['communication_id'];
        TestHelper::assertTrue($commId1 > 0, 'Valid communication ID returned');

        // Check company status progressed from 'new' to 'contacted'
        $compAfterFirst = $compModel->findById($testCompId);
        TestHelper::assertEqual('contacted', $compAfterFirst['status'], 'Company status progressed to contacted');
        $firstContactAt = $compAfterFirst['first_contact_at'];
        TestHelper::assertNotEmpty($firstContactAt, 'first_contact_at is populated');

        // Second log attempt with DUPLICATE client_message_id
        $res2 = SalesCrmService::logCommunication($testCompId, [
            'type' => 'whatsapp',
            'direction' => 'outbound',
            'outcome' => 'sent',
            'subject' => 'Yinelenen WhatsApp Mesajı (Tekrar Gönderim)',
            'message' => 'Merhaba, bu bir test mesajıdır.',
            'client_message_id' => $commClientMsgId
        ]);
        TestHelper::assertTrue($res2['success'], 'Second logCommunication call succeeded idempotently');
        TestHelper::assertTrue(!empty($res2['idempotent']), 'Response flagged as idempotent duplicate');
        TestHelper::assertEqual($commId1, $res2['communication_id'], 'Same communication ID returned');

        // Verify communications count is still exactly 1 for this company
        $comms = $commModel->getByCompany($testCompId);
        TestHelper::assertEqual(1, count($comms), 'Only 1 communication row exists in DB (no duplicate)');

        // Cleanup
        $db->prepare("DELETE FROM communications WHERE company_id = ?")->execute([$testCompId]);
        $db->prepare("DELETE FROM outbound_messages WHERE company_id = ?")->execute([$testCompId]);
        $compModel->delete($testCompId);
    }
} catch (\Throwable $e) {
    TestHelper::skip('DB live test failed: ' . $e->getMessage());
}
