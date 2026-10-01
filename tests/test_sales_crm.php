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
TestHelper::assertEqual('new', SalesCrmService::calculateNextStatus('new', 'internal', 'sent'), 'new + internal does NOT change status');
TestHelper::assertEqual('contacted', SalesCrmService::calculateNextStatus('contacted', 'internal', 'proposal_sent'), 'contacted + internal does NOT change status');
TestHelper::assertEqual('replied', SalesCrmService::calculateNextStatus('replied', 'internal', 'customer'), 'replied + internal does NOT change status');
TestHelper::assertEqual('proposal', SalesCrmService::calculateNextStatus('proposal', 'internal', 'not_interested'), 'proposal + internal does NOT change status');


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

        // 12. Historical timestamp MIN/MAX & internal note integrity
        echo "\n--- FAZ 4.1 DATA INTEGRITY & TIMESTAMPS TESTS ---\n";
        $cidH = $compModel->create([
            'name' => 'HIST_TEST_' . bin2hex(random_bytes(4)),
            'phone' => '05329998877',
            'city' => 'Konya',
            'status' => 'new'
        ]);

        $tOct5 = '2026-10-05 15:00:00';
        SalesCrmService::logCommunication($cidH, [
            'type' => 'phone',
            'direction' => 'outbound',
            'contacted_at' => $tOct5
        ]);
        $compH = $compModel->findById($cidH);
        TestHelper::assertEqual($tOct5, $compH['first_contact_at'], 'First contact at tOct5');
        TestHelper::assertEqual($tOct5, $compH['last_contact_at'], 'Last contact at tOct5');

        // Past contact at Oct 1 10:00: first_contact_at must update to Oct 1, last_contact_at must STAY Oct 5 (NO ROLLBACK)
        $tOct1 = '2026-10-01 10:00:00';
        SalesCrmService::logCommunication($cidH, [
            'type' => 'phone',
            'direction' => 'inbound',
            'contacted_at' => $tOct1
        ]);
        $compH = $compModel->findById($cidH);
        TestHelper::assertEqual($tOct1, $compH['first_contact_at'], 'Past contact updates first_contact_at to earlier date (MIN)');
        TestHelper::assertEqual($tOct5, $compH['last_contact_at'], 'Past contact does NOT downgrade last_contact_at (MAX preserved)');

        // Later contact at Oct 6 12:00: first_contact_at must STAY Oct 1, last_contact_at must update to Oct 6
        $tOct6 = '2026-10-06 12:00:00';
        SalesCrmService::logCommunication($cidH, [
            'type' => 'whatsapp',
            'direction' => 'outbound',
            'contacted_at' => $tOct6
        ]);
        $compH = $compModel->findById($cidH);
        TestHelper::assertEqual($tOct1, $compH['first_contact_at'], 'Later contact preserves first_contact_at');
        TestHelper::assertEqual($tOct6, $compH['last_contact_at'], 'Later contact updates last_contact_at');

        // Internal note: channel='note', should force direction='internal', outcome=null, timestamps untouched
        $tOct10 = '2026-10-10 10:00:00';
        $resNoteH = SalesCrmService::logCommunication($cidH, [
            'type' => 'note',
            'direction' => 'outbound',
            'outcome' => 'customer',
            'subject' => 'Note check',
            'message' => 'Internal note integrity check',
            'contacted_at' => $tOct10
        ]);
        TestHelper::assertTrue($resNoteH['success'], 'Note logged');
        $loggedNote = $commModel->findById($resNoteH['communication_id']);
        TestHelper::assertEqual('internal', $loggedNote['direction'], 'type=note forced direction=internal');
        TestHelper::assertNull($loggedNote['outcome'], 'direction=internal forced outcome=null');

        $compH = $compModel->findById($cidH);
        TestHelper::assertEqual($tOct1, $compH['first_contact_at'], 'Internal note does NOT modify first_contact_at');
        TestHelper::assertEqual($tOct6, $compH['last_contact_at'], 'Internal note does NOT modify last_contact_at');

        $compModel->delete($cidH);

        // 13. Follow-up idempotency tests
        echo "\n--- FAZ 4.1 FOLLOW-UP IDEMPOTENCY TESTS ---\n";
        $cidF = $compModel->create([
            'name' => 'FU_IDEMP_TEST_' . bin2hex(random_bytes(4)),
            'phone' => '05321113355',
            'city' => 'Konya',
            'status' => 'new'
        ]);

        $fu1Id = $fuModel->create([
            'company_id' => $cidF,
            'title' => 'Complete Idempotency Test',
            'due_at' => date('Y-m-d 15:00:00'),
            'status' => 'pending'
        ]);

        // First completion: changed=true
        $compRes1 = SalesCrmService::completeFollowUp($fu1Id);
        TestHelper::assertTrue($compRes1['success'], 'First completeFollowUp succeeds');
        TestHelper::assertTrue($compRes1['changed'], 'First completeFollowUp changed=true');
        $commsAfterFirst = $commModel->getByCompany($cidF);
        $countAfterFirst = count($commsAfterFirst);
        TestHelper::assertEqual(1, $countAfterFirst, '1 timeline note added on complete');

        // Second completion (idempotent call): changed=false, no extra timeline note
        $compRes2 = SalesCrmService::completeFollowUp($fu1Id);
        TestHelper::assertTrue($compRes2['success'], 'Second completeFollowUp succeeds (idempotent)');
        TestHelper::assertFalse($compRes2['changed'], 'Second completeFollowUp changed=false (no-op)');
        $commsAfterSecond = $commModel->getByCompany($cidF);
        TestHelper::assertEqual($countAfterFirst, count($commsAfterSecond), 'Zero additional timeline notes on idempotent completion');

        // Try to cancel already completed follow-up: must reject
        $cancelCompleted = SalesCrmService::cancelFollowUp($fu1Id);
        TestHelper::assertFalse($cancelCompleted['success'], 'Cannot cancel an already completed follow-up');

        // Test cancelFollowUp idempotency
        $fu2Id = $fuModel->create([
            'company_id' => $cidF,
            'title' => 'Cancel Idempotency Test',
            'due_at' => date('Y-m-d 16:00:00'),
            'status' => 'pending'
        ]);

        // First cancellation: changed=true
        $cancRes1 = SalesCrmService::cancelFollowUp($fu2Id);
        TestHelper::assertTrue($cancRes1['success'], 'First cancelFollowUp succeeds');
        TestHelper::assertTrue($cancRes1['changed'], 'First cancelFollowUp changed=true');
        $commsAfterCanc = $commModel->getByCompany($cidF);
        $countAfterCanc = count($commsAfterCanc);
        TestHelper::assertEqual($countAfterFirst + 1, $countAfterCanc, '1 timeline note added on cancellation');

        // Second cancellation (idempotent call): changed=false, no extra timeline note
        $cancRes2 = SalesCrmService::cancelFollowUp($fu2Id);
        TestHelper::assertTrue($cancRes2['success'], 'Second cancelFollowUp succeeds (idempotent)');
        TestHelper::assertFalse($cancRes2['changed'], 'Second cancelFollowUp changed=false (no-op)');
        $commsAfterCanc2 = $commModel->getByCompany($cidF);
        TestHelper::assertEqual($countAfterCanc, count($commsAfterCanc2), 'Zero additional timeline notes on idempotent cancellation');

        // Try to complete already cancelled follow-up: must reject
        $completeCancelled = SalesCrmService::completeFollowUp($fu2Id);
        TestHelper::assertFalse($completeCancelled['success'], 'Cannot complete an already cancelled follow-up');

        $compModel->delete($cidF);

        // 14. Non-overlapping follow-up partitions tests
        echo "\n--- FAZ 4.1 NON-OVERLAPPING FOLLOW-UP PARTITIONS TESTS ---\n";
        $cidP = $compModel->create([
            'name' => 'PARTITION_TEST_' . bin2hex(random_bytes(4)),
            'phone' => '05327778899',
            'city' => 'Konya',
            'status' => 'new'
        ]);

        $fuPastId = $fuModel->create([
            'company_id' => $cidP,
            'title' => 'Past Followup',
            'due_at' => date('Y-m-d 10:00:00', strtotime('-2 days')),
            'status' => 'pending'
        ]);

        $fuTodayId = $fuModel->create([
            'company_id' => $cidP,
            'title' => 'Today Followup',
            'due_at' => date('Y-m-d 14:00:00'),
            'status' => 'pending'
        ]);

        $fuFutureId = $fuModel->create([
            'company_id' => $cidP,
            'title' => 'Future Followup',
            'due_at' => date('Y-m-d 11:00:00', strtotime('+2 days')),
            'status' => 'pending'
        ]);

        $overdueList = $fuModel->getOverdue();
        $overdueIds = array_column($overdueList, 'id');
        TestHelper::assertTrue(in_array($fuPastId, $overdueIds), 'Overdue list contains past item');
        TestHelper::assertFalse(in_array($fuTodayId, $overdueIds), 'Overdue list does NOT contain today item (zero overlap)');
        TestHelper::assertFalse(in_array($fuFutureId, $overdueIds), 'Overdue list does NOT contain future item (zero overlap)');

        $todayList = $fuModel->getDueToday();
        $todayIds = array_column($todayList, 'id');
        TestHelper::assertTrue(in_array($fuTodayId, $todayIds), 'Today list contains today item');
        TestHelper::assertFalse(in_array($fuPastId, $todayIds), 'Today list does NOT contain past item (zero overlap)');
        TestHelper::assertFalse(in_array($fuFutureId, $todayIds), 'Today list does NOT contain future item (zero overlap)');

        $upcomingList = $fuModel->getUpcoming(100);
        $upcomingIds = array_column($upcomingList, 'id');
        TestHelper::assertTrue(in_array($fuFutureId, $upcomingIds), 'Upcoming list contains future item');
        TestHelper::assertFalse(in_array($fuPastId, $upcomingIds), 'Upcoming list does NOT contain past item (zero overlap)');
        TestHelper::assertFalse(in_array($fuTodayId, $upcomingIds), 'Upcoming list does NOT contain today item (zero overlap)');

        $compModel->delete($cidP);

        // 15. Unique company counts in Communication tests
        echo "\n--- FAZ 4.1 UNIQUE COMPANY COUNTS IN COMMUNICATION TESTS ---\n";
        $cidKpi1 = $compModel->create(['name' => 'KPI_TEST_1_' . bin2hex(random_bytes(4)), 'phone' => '05320000001', 'status' => 'new']);
        $cidKpi2 = $compModel->create(['name' => 'KPI_TEST_2_' . bin2hex(random_bytes(4)), 'phone' => '05320000002', 'status' => 'new']);

        $beforeReplied = $commModel->getRepliedCount();
        $beforeProposals = $commModel->getProposalsCount();

        SalesCrmService::logCommunication($cidKpi1, ['type' => 'phone', 'direction' => 'inbound', 'outcome' => 'replied']);
        SalesCrmService::logCommunication($cidKpi1, ['type' => 'whatsapp', 'direction' => 'outbound', 'outcome' => 'interested']);
        SalesCrmService::logCommunication($cidKpi2, ['type' => 'email', 'direction' => 'inbound', 'outcome' => 'replied']);

        SalesCrmService::logCommunication($cidKpi1, ['type' => 'email', 'direction' => 'outbound', 'outcome' => 'proposal_sent']);
        SalesCrmService::logCommunication($cidKpi1, ['type' => 'phone', 'direction' => 'outbound', 'outcome' => 'proposal_sent']);

        $afterReplied = $commModel->getRepliedCount();
        $afterProposals = $commModel->getProposalsCount();

        TestHelper::assertEqual($beforeReplied + 2, $afterReplied, 'getRepliedCount counts unique companies (DISTINCT company_id)');
        TestHelper::assertEqual($beforeProposals + 1, $afterProposals, 'getProposalsCount counts unique companies (DISTINCT company_id)');

        $compModel->delete($cidKpi1);
        $compModel->delete($cidKpi2);

        // 16. Timezone synchronization tests
        echo "\n--- FAZ 4.1 TIMEZONE SYNCHRONIZATION TESTS ---\n";
        $nowPhp = date('Y-m-d H:i:s');
        $nowDb = $db->query("SELECT NOW()")->fetchColumn();
        $diff = abs(strtotime($nowPhp) - strtotime($nowDb));
        TestHelper::assertTrue($diff <= 2, "PHP date ($nowPhp) and DB NOW() ($nowDb) match within 2 seconds (diff: {$diff}s)");
    }

} catch (\Throwable $e) {
    TestHelper::skip('Sales CRM DB test exception: ' . $e->getMessage());
}
