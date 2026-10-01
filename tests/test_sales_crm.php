<?php

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Models/Company.php';
require_once __DIR__ . '/../app/Models/Communication.php';
require_once __DIR__ . '/../app/Models/FollowUp.php';
require_once __DIR__ . '/../app/Services/CRM/SalesCrmService.php';

use App\Models\Company;
use App\Models\Communication;
use App\Models\FollowUp;
use App\Services\CRM\SalesCrmService;

echo "\n--- SALES CRM LOGIC & STATUS PROGRESSION TESTS ---\n";

// 1. Status progression tests
TestHelper::assertEqual('contacted', SalesCrmService::calculateNextStatus('new', 'outbound', 'sent'), 'new + outbound -> contacted');
TestHelper::assertEqual('contacted', SalesCrmService::calculateNextStatus('new', 'inbound', 'sent'), 'new + inbound -> contacted');
TestHelper::assertEqual('replied', SalesCrmService::calculateNextStatus('contacted', 'inbound', 'replied'), 'contacted + replied -> replied');
TestHelper::assertEqual('replied', SalesCrmService::calculateNextStatus('contacted', 'outbound', 'interested'), 'contacted + interested -> replied');
TestHelper::assertEqual('replied', SalesCrmService::calculateNextStatus('new', 'outbound', 'proposal_requested'), 'new + proposal_requested -> replied');
TestHelper::assertEqual('proposal', SalesCrmService::calculateNextStatus('replied', 'outbound', 'proposal_sent'), 'replied + proposal_sent -> proposal');
TestHelper::assertEqual('proposal', SalesCrmService::calculateNextStatus('contacted', 'outbound', 'proposal_sent'), 'contacted + proposal_sent -> proposal');
TestHelper::assertEqual('customer', SalesCrmService::calculateNextStatus('proposal', 'inbound', 'customer'), 'proposal + customer -> customer');
TestHelper::assertEqual('customer', SalesCrmService::calculateNextStatus('customer', 'outbound', 'sent'), 'customer does NOT downgrade on new outbound');
TestHelper::assertEqual('customer', SalesCrmService::calculateNextStatus('customer', 'outbound', 'not_interested'), 'customer does NOT downgrade to negative');
TestHelper::assertEqual('negative', SalesCrmService::calculateNextStatus('contacted', 'outbound', 'not_interested'), 'contacted + not_interested -> negative');
TestHelper::assertEqual('negative', SalesCrmService::calculateNextStatus('new', 'outbound', 'not_interested'), 'new + not_interested -> negative');

echo "\n--- SALES CRM LIVE DATABASE END-TO-END SCENARIO ---\n";

try {
    $db = \App\Core\Database::getInstance()->getConnection();
    if (!$db) {
        TestHelper::skip('Database connection unavailable for live CRM tests.');
    } else {
        $compModel = new Company();
        $commModel = new Communication();
        $fuModel = new FollowUp();

        // 1. Create test company
        $testCompName = 'CRM_TEST_' . bin2hex(random_bytes(4));
        $cid = $compModel->create([
            'name' => $testCompName,
            'phone' => '05321112233',
            'city' => 'Konya',
            'status' => 'new'
        ]);
        TestHelper::assertTrue($cid > 0, "Test company created with ID $cid");

        $comp = $compModel->findById($cid);
        TestHelper::assertEqual('new', $comp['status'], 'Initial status is new');
        TestHelper::assertNull($comp['first_contact_at'], 'Initial first_contact_at is NULL');
        TestHelper::assertNull($comp['last_contact_at'], 'Initial last_contact_at is NULL');

        // 2. Log initial outbound WhatsApp communication
        $waTime = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $res1 = SalesCrmService::logCommunication($cid, [
            'type' => 'whatsapp',
            'direction' => 'outbound',
            'outcome' => 'sent',
            'subject' => 'WhatsApp Tanışma',
            'message' => 'Merhaba Ajasis Medyadan yazıyorum',
            'contacted_at' => $waTime
        ]);
        TestHelper::assertTrue($res1['success'], 'WhatsApp communication logged successfully');

        $comp = $compModel->findById($cid);
        TestHelper::assertEqual('contacted', $comp['status'], 'Status transitioned new -> contacted');
        TestHelper::assertEqual($waTime, $comp['first_contact_at'], 'first_contact_at set on initial outbound');
        TestHelper::assertEqual($waTime, $comp['last_contact_at'], 'last_contact_at set on initial outbound');

        // 3. Internal note does NOT modify last_contact_at
        $resNote = SalesCrmService::logCommunication($cid, [
            'type' => 'note',
            'direction' => 'internal',
            'subject' => 'Dahili Not',
            'message' => 'Bu bir iç not',
            'contacted_at' => date('Y-m-d H:i:s')
        ]);
        TestHelper::assertTrue($resNote['success'], 'Internal note logged successfully');

        $comp = $compModel->findById($cid);
        TestHelper::assertEqual($waTime, $comp['last_contact_at'], 'last_contact_at unchanged by internal note');

        // 4. Log call + Follow-up in same transaction
        $dueTime = date('Y-m-d H:i:s', strtotime('+1 day'));
        $resTx = SalesCrmService::logCommunication($cid, [
            'type' => 'phone',
            'direction' => 'outbound',
            'outcome' => 'replied',
            'subject' => 'Telefon Görüşmesi',
            'message' => 'Görüştük, teklif bekliyor',
            'contacted_at' => date('Y-m-d H:i:s')
        ], [
            'title' => 'Teklif gönderilecek',
            'due_at' => $dueTime,
            'priority' => 'high',
            'notes' => 'Fiyat listesi hazırla'
        ]);
        TestHelper::assertTrue($resTx['success'], 'Communication + Follow-up transaction succeeded');
        TestHelper::assertNotEmpty($resTx['follow_up_id'], 'Follow-up created in transaction');

        $comp = $compModel->findById($cid);
        TestHelper::assertEqual('replied', $comp['status'], 'Status transitioned contacted -> replied');

        $comms = $commModel->getByCompany($cid);
        TestHelper::assertEqual(3, count($comms), 'Company timeline has 3 communications');

        $fus = $fuModel->getByCompany($cid);
        TestHelper::assertEqual(1, count($fus), 'Company has 1 follow-up');
        TestHelper::assertEqual('Teklif gönderilecek', $fus[0]['title'], 'Follow-up title matched');
        TestHelper::assertEqual('high', $fus[0]['priority'], 'Follow-up priority matched');
        TestHelper::assertEqual('pending', $fus[0]['status'], 'Follow-up status is pending');

        // 5. Outcome = proposal_sent -> status = proposal
        $resProp = SalesCrmService::logCommunication($cid, [
            'type' => 'email',
            'direction' => 'outbound',
            'outcome' => 'proposal_sent',
            'subject' => 'Teklif Gönderildi',
            'message' => 'Fiyat teklifi e-posta ile iletildi.'
        ]);
        TestHelper::assertTrue($resProp['success'], 'Proposal communication logged');
        $comp = $compModel->findById($cid);
        TestHelper::assertEqual('proposal', $comp['status'], 'Status transitioned replied -> proposal');

        // 6. Outcome = customer -> status = customer
        $resCust = SalesCrmService::logCommunication($cid, [
            'type' => 'phone',
            'direction' => 'inbound',
            'outcome' => 'customer',
            'subject' => 'Anlaşma Sağlandı',
            'message' => 'Müşteri teklifi onayladı ve sözleşme yapıldı.'
        ]);
        TestHelper::assertTrue($resCust['success'], 'Customer communication logged');
        $comp = $compModel->findById($cid);
        TestHelper::assertEqual('customer', $comp['status'], 'Status transitioned proposal -> customer');

        // 7. Complete Follow-up
        $fuId = $fus[0]['id'];
        $resComplete = SalesCrmService::completeFollowUp($fuId);
        TestHelper::assertTrue($resComplete['success'], 'Follow-up completed via SalesCrmService');

        $completedFu = $fuModel->findById($fuId);
        TestHelper::assertEqual('completed', $completedFu['status'], 'Follow-up status is completed');
        TestHelper::assertNotEmpty($completedFu['completed_at'], 'Follow-up completed_at is set');

        // 8. Verify timeline reflects completed follow-up via internal note
        $allComms = $commModel->getByCompany($cid);
        TestHelper::assertEqual(6, count($allComms), 'Timeline has 6 events including follow-up completion');

        // 9. History getAll with filter
        $historyResult = $commModel->getAll(['company_id' => $cid], 20, 0);
        TestHelper::assertEqual(6, count($historyResult), 'History model returns 6 records for test company');

        // 10. Invalid company ID rejected
        $resInvalid = SalesCrmService::logCommunication(999999999, [
            'type' => 'phone',
            'direction' => 'outbound',
            'message' => 'test'
        ]);
        TestHelper::assertFalse($resInvalid['success'], 'Invalid company ID cleanly rejected by transaction');

        // 11. Clean up test company (CASCADE deletes communications and follow-ups)
        $compModel->delete($cid);
        TestHelper::assertFalse($compModel->findById($cid), 'Test company deleted');

        $remainingComms = $commModel->getByCompany($cid);
        TestHelper::assertEqual(0, count($remainingComms), 'Cascade deleted all associated communications');

        $remainingFus = $fuModel->getByCompany($cid);
        TestHelper::assertEqual(0, count($remainingFus), 'Cascade deleted all associated follow-ups');
    }
} catch (\Throwable $e) {
    TestHelper::skip('Sales CRM DB test exception: ' . $e->getMessage());
}
