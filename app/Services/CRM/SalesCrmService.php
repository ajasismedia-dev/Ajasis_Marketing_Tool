<?php

namespace App\Services\CRM;

use App\Core\Database;
use App\Models\Communication;
use App\Models\FollowUp;
use App\Models\Company;
use PDO;
use Exception;

class SalesCrmService
{
    public static $validTypes = [
        'whatsapp', 'phone', 'email', 'instagram', 'linkedin', 'meeting', 'note', 'other'
    ];

    public static $validDirections = [
        'outbound', 'inbound', 'internal'
    ];

    public static $validOutcomes = [
        'sent', 'no_answer', 'replied', 'interested', 'not_interested',
        'callback', 'proposal_requested', 'proposal_sent', 'meeting_scheduled',
        'customer', 'other'
    ];

    public static $validCompanyStatuses = [
        'new', 'contacted', 'replied', 'proposal', 'customer', 'negative'
    ];

    /**
     * Determine next company status based on current status, direction, and outcome
     */
    public static function calculateNextStatus($currentStatus, $direction, $outcome)
    {
        // Internal events (notes, follow-up updates, system updates) NEVER change pipeline status
        if ($direction === 'internal') {
            return $currentStatus;
        }

        $statusRanks = [
            'new' => 1,
            'contacted' => 2,
            'replied' => 3,
            'proposal' => 4,
            'customer' => 5,
            'negative' => 0
        ];

        $currentRank = $statusRanks[$currentStatus] ?? 1;

        // Customer is the final successful tier: never downgrade
        if ($currentStatus === 'customer') {
            return 'customer';
        }

        // Negative outcome: mark negative if not customer
        if ($outcome === 'not_interested') {
            return 'negative';
        }

        // Customer outcome
        if ($outcome === 'customer') {
            return 'customer';
        }

        // Proposal outcome
        if ($outcome === 'proposal_sent') {
            return ($currentRank < 4) ? 'proposal' : $currentStatus;
        }

        // Replied / Interested / Proposal requested
        if (in_array($outcome, ['replied', 'interested', 'proposal_requested'])) {
            return ($currentRank < 3) ? 'replied' : $currentStatus;
        }

        // Initial outbound contact on 'new'
        if ($direction === 'outbound' && $currentStatus === 'new') {
            return 'contacted';
        }

        // Any inbound contact on 'new'
        if ($direction === 'inbound' && $currentStatus === 'new') {
            return 'contacted';
        }

        return $currentStatus;
    }

    /**
     * Record communication, update company status and timestamps, and optionally create follow-up
     * in a single atomic database transaction.
     */
    public static function logCommunication($companyId, array $commData, ?array $followUpData = null, ?int $userId = null)
    {
        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();

        try {
            // Lock and fetch company
            $stmt = $db->prepare("SELECT * FROM companies WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => (int)$companyId]);
            $company = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$company) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Firma bulunamadı.'];
            }

            // Sanitize & validate communication fields
            $type = in_array($commData['type'] ?? '', self::$validTypes) ? $commData['type'] : 'other';
            $direction = in_array($commData['direction'] ?? '', self::$validDirections) ? $commData['direction'] : 'outbound';

            // Internal note integrity: note type forces direction to internal
            if ($type === 'note') {
                $direction = 'internal';
            }

            // Internal direction forces outcome to null
            if ($direction === 'internal') {
                $outcome = null;
            } else {
                $outcome = (!empty($commData['outcome']) && in_array($commData['outcome'], self::$validOutcomes)) ? $commData['outcome'] : null;
            }

            $subject = trim($commData['subject'] ?? '');
            $message = trim($commData['message'] ?? '');
            $contactedAt = !empty($commData['contacted_at']) ? $commData['contacted_at'] : date('Y-m-d H:i:s');
            $clientMessageId = !empty($commData['client_message_id']) ? trim($commData['client_message_id']) : null;

            $commModel = new Communication();

            // Idempotency guard: prevent duplicate communication inserts
            if (!empty($clientMessageId)) {
                $existing = $commModel->findByClientMessageId($clientMessageId);
                if ($existing) {
                    $db->rollBack();
                    return [
                        'success' => true,
                        'idempotent' => true,
                        'communication_id' => (int)$existing['id'],
                        'message' => 'Bu mesaj daha önce kaydedilmiş.',
                        'old_status' => $company['status'] ?? 'new',
                        'new_status' => $company['status'] ?? 'new'
                    ];
                }
            }

            $insertCommData = [
                'company_id' => (int)$companyId,
                'type' => $type,
                'direction' => $direction,
                'subject' => $subject ?: null,
                'message' => $message ?: null,
                'outcome' => $outcome,
                'contacted_at' => $contactedAt,
                'client_message_id' => $clientMessageId,
                'created_by' => $userId
            ];

            // 1. Insert communication
            $commId = $commModel->create($insertCommData);

            // 2. Optionally insert follow-up
            $followUpId = null;
            if (!empty($followUpData) && !empty($followUpData['title']) && !empty($followUpData['due_at'])) {
                $fuPriority = in_array($followUpData['priority'] ?? '', ['low', 'normal', 'high']) ? $followUpData['priority'] : 'normal';
                
                $insertFuData = [
                    'company_id' => (int)$companyId,
                    'title' => trim($followUpData['title']),
                    'notes' => trim($followUpData['notes'] ?? ''),
                    'due_at' => $followUpData['due_at'],
                    'status' => 'pending',
                    'priority' => $fuPriority,
                    'communication_id' => $commId,
                    'created_by' => $userId
                ];

                $fuModel = new FollowUp();
                $followUpId = $fuModel->create($insertFuData);
            }

            // 3. Update company status and timestamps
            $currentStatus = $company['status'] ?? 'new';
            $newStatus = self::calculateNextStatus($currentStatus, $direction, $outcome);

            $updateCompanyData = [
                'status' => $newStatus
            ];

            // Only outbound and inbound communications update contact timestamps
            // Internal direction never touches contact timestamps
            if (in_array($direction, ['outbound', 'inbound'])) {
                $cTime = strtotime($contactedAt);
                $currFirst = !empty($company['first_contact_at']) ? strtotime($company['first_contact_at']) : null;
                $currLast = !empty($company['last_contact_at']) ? strtotime($company['last_contact_at']) : null;

                if ($currFirst === null || $cTime < $currFirst) {
                    $updateCompanyData['first_contact_at'] = date('Y-m-d H:i:s', $cTime);
                }

                if ($currLast === null || $cTime > $currLast) {
                    $updateCompanyData['last_contact_at'] = date('Y-m-d H:i:s', $cTime);
                }
            }

            $compModel = new Company();
            $compModel->update($companyId, $updateCompanyData);

            $db->commit();

            return [
                'success' => true,
                'communication_id' => $commId,
                'follow_up_id' => $followUpId,
                'old_status' => $currentStatus,
                'new_status' => $newStatus
            ];
        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Manually change company status and log an internal note in the timeline
     */
    public static function updateCompanyStatus($companyId, $newStatus, ?int $userId = null)
    {
        if (!in_array($newStatus, self::$validCompanyStatuses)) {
            return ['success' => false, 'error' => 'Geçersiz durum.'];
        }

        $compModel = new Company();
        $company = $compModel->findById($companyId);
        if (!$company) {
            return ['success' => false, 'error' => 'Firma bulunamadı.'];
        }

        $oldStatus = $company['status'];
        if ($oldStatus === $newStatus) {
            return ['success' => true, 'changed' => false];
        }

        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();

        try {
            $compModel->update($companyId, ['status' => $newStatus]);

            // Add internal note to timeline
            $statusLabels = [
                'new' => 'Yeni',
                'contacted' => 'İletişimde',
                'replied' => 'Cevap Alındı',
                'proposal' => 'Teklif Aşaması',
                'customer' => 'Müşteri',
                'negative' => 'Olumsuz'
            ];
            $oldLabel = $statusLabels[$oldStatus] ?? $oldStatus;
            $newLabel = $statusLabels[$newStatus] ?? $newStatus;

            $commModel = new Communication();
            $commModel->create([
                'company_id' => (int)$companyId,
                'type' => 'note',
                'direction' => 'internal',
                'subject' => 'Durum Güncellendi',
                'message' => "Firma durumu '{$oldLabel}' → '{$newLabel}' olarak değiştirildi.",
                'outcome' => null,
                'contacted_at' => date('Y-m-d H:i:s'),
                'created_by' => $userId
            ]);

            $db->commit();
            return ['success' => true, 'changed' => true, 'old_status' => $oldStatus, 'new_status' => $newStatus];
        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Complete a follow-up and log internal note to timeline (idempotent with row locking)
     */
    public static function completeFollowUp($followUpId, ?int $userId = null)
    {
        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("SELECT * FROM follow_ups WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => (int)$followUpId]);
            $fu = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$fu) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Takip bulunamadı.'];
            }

            // Idempotent: already completed
            if ($fu['status'] === 'completed') {
                $db->rollBack();
                return [
                    'success' => true,
                    'changed' => false,
                    'message' => 'Zaten tamamlanmış.',
                    'company_id' => (int)$fu['company_id']
                ];
            }

            // Cannot complete a cancelled follow-up
            if ($fu['status'] === 'cancelled') {
                $db->rollBack();
                return [
                    'success' => false,
                    'error' => 'İptal edilmiş bir takip tamamlanamaz.',
                    'company_id' => (int)$fu['company_id']
                ];
            }

            // Status is pending: complete it
            $updateStmt = $db->prepare("UPDATE follow_ups SET status = 'completed', completed_at = NOW() WHERE id = :id");
            $updateStmt->execute(['id' => (int)$followUpId]);

            // Log internal note
            $commModel = new Communication();
            $commModel->create([
                'company_id' => (int)$fu['company_id'],
                'type' => 'note',
                'direction' => 'internal',
                'subject' => 'Takip Tamamlandı',
                'message' => "Tamamlanan Takip: " . $fu['title'] . (!empty($fu['notes']) ? " (Not: {$fu['notes']})" : ""),
                'outcome' => null,
                'contacted_at' => date('Y-m-d H:i:s'),
                'created_by' => $userId
            ]);

            $db->commit();
            return [
                'success' => true,
                'changed' => true,
                'company_id' => (int)$fu['company_id']
            ];
        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cancel a follow-up and log internal note to timeline (idempotent with row locking)
     */
    public static function cancelFollowUp($followUpId, ?int $userId = null)
    {
        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("SELECT * FROM follow_ups WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => (int)$followUpId]);
            $fu = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$fu) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Takip bulunamadı.'];
            }

            // Idempotent: already cancelled
            if ($fu['status'] === 'cancelled') {
                $db->rollBack();
                return [
                    'success' => true,
                    'changed' => false,
                    'message' => 'Zaten iptal edilmiş.',
                    'company_id' => (int)$fu['company_id']
                ];
            }

            // Cannot cancel a completed follow-up
            if ($fu['status'] === 'completed') {
                $db->rollBack();
                return [
                    'success' => false,
                    'error' => 'Tamamlanmış bir takip iptal edilemez.',
                    'company_id' => (int)$fu['company_id']
                ];
            }

            // Status is pending: cancel it
            $updateStmt = $db->prepare("UPDATE follow_ups SET status = 'cancelled' WHERE id = :id");
            $updateStmt->execute(['id' => (int)$followUpId]);

            // Log internal note
            $commModel = new Communication();
            $commModel->create([
                'company_id' => (int)$fu['company_id'],
                'type' => 'note',
                'direction' => 'internal',
                'subject' => 'Takip İptal Edildi',
                'message' => "İptal Edilen Takip: " . $fu['title'] . (!empty($fu['notes']) ? " (Not: {$fu['notes']})" : ""),
                'outcome' => null,
                'contacted_at' => date('Y-m-d H:i:s'),
                'created_by' => $userId
            ]);

            $db->commit();
            return [
                'success' => true,
                'changed' => true,
                'company_id' => (int)$fu['company_id']
            ];
        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

