<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class OutboundMessage
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Find outbound record by ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM outbound_messages WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Find outbound record by client_message_id
     */
    public function findByClientMessageId(string $clientMessageId): ?array
    {
        $sql = "SELECT * FROM outbound_messages WHERE client_message_id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $clientMessageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Generic create method
     */
    public function create(array $data): int
    {
        $companyId = (int)($data['company_id'] ?? 0);
        $channel = $data['channel'] ?? 'whatsapp';
        $clientMessageId = $data['client_message_id'] ?? ('msg_' . bin2hex(random_bytes(8)));
        $subject = $data['subject'] ?? null;
        $body = $data['body'] ?? '';
        $userId = $data['created_by'] ?? null;
        $status = $data['status'] ?? 'pending';

        $sql = "INSERT INTO outbound_messages 
                (company_id, channel, client_message_id, status, subject, body_hash, created_by)
                VALUES (:company_id, :channel, :client_message_id, :status, :subject, :body_hash, :created_by)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'company_id'        => $companyId,
            'channel'           => $channel,
            'client_message_id' => $clientMessageId,
            'status'            => $status,
            'subject'           => $subject,
            'body_hash'         => hash('sha256', $body),
            'created_by'        => $userId
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Update status by ID
     */
    public function updateStatus(int $id, string $status, ?string $error = null): bool
    {
        $sql = "UPDATE outbound_messages SET status = :status";
        $params = ['id' => $id, 'status' => $status];
        if ($error !== null) {
            $sql .= ", error_message = :error";
            $params['error'] = mb_substr($error, 0, 500);
        }
        if ($status === 'sent' || $status === 'logged') {
            $sql .= ", sent_at = NOW()";
        }
        $sql .= " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Create initial pending record for atomic dispatch
     */
    public function createPending(int $companyId, string $channel, string $clientMessageId, ?string $subject, string $body, ?int $userId = null): int
    {
        $sql = "INSERT INTO outbound_messages 
                (company_id, channel, client_message_id, status, subject, body_hash, created_by)
                VALUES (:company_id, :channel, :client_message_id, 'pending', :subject, :body_hash, :created_by)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'company_id'        => $companyId,
            'channel'           => $channel,
            'client_message_id' => $clientMessageId,
            'subject'           => $subject,
            'body_hash'         => hash('sha256', $body),
            'created_by'        => $userId
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Mark record as successfully sent
     */
    public function markSent(string $clientMessageId, ?int $communicationId = null): bool
    {
        $sql = "UPDATE outbound_messages 
                SET status = 'sent', communication_id = :communication_id, sent_at = NOW() 
                WHERE client_message_id = :client_message_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'communication_id'  => $communicationId,
            'client_message_id' => $clientMessageId
        ]);
    }

    /**
     * Mark record as failed
     */
    public function markFailed(string $clientMessageId, string $errorMessage): bool
    {
        $sql = "UPDATE outbound_messages 
                SET status = 'failed', error_message = :error_message 
                WHERE client_message_id = :client_message_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'error_message'     => mb_substr($errorMessage, 0, 500),
            'client_message_id' => $clientMessageId
        ]);
    }

    /**
     * Mark record as logged (e.g. for WhatsApp manual send)
     */
    public function markLogged(string $clientMessageId, ?int $communicationId = null): bool
    {
        $sql = "UPDATE outbound_messages 
                SET status = 'logged', communication_id = :communication_id, sent_at = NOW() 
                WHERE client_message_id = :client_message_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'communication_id'  => $communicationId,
            'client_message_id' => $clientMessageId
        ]);
    }

    /**
     * Count attempts by user or company within a time window (for basic rate guard)
     */
    public function countAttemptsInWindow(?int $userId, int $seconds = 300): int
    {
        if (!$userId) return 0;
        $sql = "SELECT COUNT(*) FROM outbound_messages 
                WHERE created_by = :user_id 
                AND created_at >= NOW() - INTERVAL :seconds SECOND";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'seconds' => $seconds
        ]);
        return (int)$stmt->fetchColumn();
    }
}
