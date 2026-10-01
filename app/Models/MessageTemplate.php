<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use Exception;

class MessageTemplate
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all templates optionally filtered by channel and active status
     */
    public function getAll(?string $channel = null, bool $activeOnly = false): array
    {
        $sql = "SELECT t.*, u.name AS created_by_name 
                FROM message_templates t
                LEFT JOIN users u ON t.created_by = u.id
                WHERE 1=1";
        $params = [];

        if ($channel !== null && in_array($channel, ['whatsapp', 'email'], true)) {
            $sql .= " AND t.channel = :channel";
            $params['channel'] = $channel;
        }

        if ($activeOnly) {
            $sql .= " AND t.is_active = 1";
        }

        $sql .= " ORDER BY t.is_default DESC, t.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all active templates for a given channel
     */
    public function getActiveByChannel(string $channel): array
    {
        return $this->getAll($channel, true);
    }

    /**
     * Get the default template for a channel (or null if none)
     */
    public function getDefaultByChannel(string $channel): ?array
    {
        $sql = "SELECT * FROM message_templates 
                WHERE channel = :channel AND is_default = 1 AND is_active = 1 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['channel' => $channel]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // Fallback: any active template for the channel
            $sqlFallback = "SELECT * FROM message_templates 
                            WHERE channel = :channel AND is_active = 1 
                            ORDER BY id ASC LIMIT 1";
            $stmtF = $this->db->prepare($sqlFallback);
            $stmtF->execute(['channel' => $channel]);
            $row = $stmtF->fetch(PDO::FETCH_ASSOC);
        }

        return $row ?: null;
    }

    /**
     * Alias for getDefaultByChannel
     */
    public function findDefaultByChannel(string $channel): ?array
    {
        return $this->getDefaultByChannel($channel);
    }

    /**
     * Find template by ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT t.*, u.name AS created_by_name 
                FROM message_templates t
                LEFT JOIN users u ON t.created_by = u.id
                WHERE t.id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Create a new template
     */
    public function create(array $data): int
    {
        $channel = in_array($data['channel'] ?? '', ['whatsapp', 'email'], true) ? $data['channel'] : 'whatsapp';
        $isDefault = !empty($data['is_default']) ? 1 : 0;

        if ($isDefault) {
            // Clear other defaults in transaction
            $this->db->beginTransaction();
            try {
                $clearStmt = $this->db->prepare("UPDATE message_templates SET is_default = 0 WHERE channel = :channel");
                $clearStmt->execute(['channel' => $channel]);

                $sql = "INSERT INTO message_templates (name, channel, subject, body, is_default, is_active, created_by)
                        VALUES (:name, :channel, :subject, :body, :is_default, :is_active, :created_by)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'name' => trim($data['name'] ?? ''),
                    'channel' => $channel,
                    'subject' => !empty($data['subject']) ? trim($data['subject']) : null,
                    'body' => trim($data['body'] ?? ''),
                    'is_default' => 1,
                    'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
                    'created_by' => $data['created_by'] ?? null
                ]);
                $id = (int)$this->db->lastInsertId();
                $this->db->commit();
                return $id;
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        }

        $sql = "INSERT INTO message_templates (name, channel, subject, body, is_default, is_active, created_by)
                VALUES (:name, :channel, :subject, :body, :is_default, :is_active, :created_by)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'name' => trim($data['name'] ?? ''),
            'channel' => $channel,
            'subject' => !empty($data['subject']) ? trim($data['subject']) : null,
            'body' => trim($data['body'] ?? ''),
            'is_default' => 0,
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'created_by' => $data['created_by'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Update an existing template
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        if (isset($data['name'])) {
            $fields[] = "name = :name";
            $params['name'] = trim($data['name']);
        }

        if (isset($data['channel']) && in_array($data['channel'], ['whatsapp', 'email'], true)) {
            $fields[] = "channel = :channel";
            $params['channel'] = $data['channel'];
        }

        if (array_key_exists('subject', $data)) {
            $fields[] = "subject = :subject";
            $params['subject'] = !empty($data['subject']) ? trim($data['subject']) : null;
        }

        if (isset($data['body'])) {
            $fields[] = "body = :body";
            $params['body'] = trim($data['body']);
        }

        if (isset($data['is_active'])) {
            $fields[] = "is_active = :is_active";
            $params['is_active'] = (int)$data['is_active'];
        }

        if (isset($data['is_default'])) {
            $fields[] = "is_default = :is_default";
            $params['is_default'] = (int)$data['is_default'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE message_templates SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Set a template as the default for its channel (transactional)
     */
    public function setDefault(int $id, string $channel): bool
    {
        $this->db->beginTransaction();
        try {
            // Set all other templates of same channel to non-default
            $clearStmt = $this->db->prepare("UPDATE message_templates SET is_default = 0 WHERE channel = :channel");
            $clearStmt->execute(['channel' => $channel]);

            // Set target template as default and active
            $setStmt = $this->db->prepare("UPDATE message_templates SET is_default = 1, is_active = 1 WHERE id = :id AND channel = :channel");
            $setStmt->execute(['id' => $id, 'channel' => $channel]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Delete a template
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM message_templates WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Seed default initial templates if none exist
     */
    public function seedDefaults(): void
    {
        $waDefault = $this->getDefaultByChannel('whatsapp');
        if (!$waDefault) {
            $this->create([
                'name' => 'İlk Tanışma',
                'channel' => 'whatsapp',
                'subject' => null,
                'body' => "Merhaba,\n\n{{company_name}} markasını incelerken sosyal medya ve dijital içerik tarafında geliştirebileceğimiz birkaç fikir dikkatimi çekti.\n\nUygunsanız markanıza özel hazırladığımız 1-2 fikri ücretsiz paylaşmak isterim.\n\n{{sender_name}}\n{{agency_name}}",
                'is_default' => 1,
                'is_active' => 1,
                'created_by' => null
            ]);
        }

        $emailDefault = $this->getDefaultByChannel('email');
        if (!$emailDefault) {
            $this->create([
                'name' => 'İlk Tanışma E-postası',
                'channel' => 'email',
                'subject' => '{{company_name}} için birkaç dijital içerik fikri',
                'body' => "Merhaba,\n\n{{company_name}} markasını incelerken dijital iletişim ve içerik tarafında geliştirebileceğimiz birkaç nokta dikkatimi çekti.\n\nUygunsanız markanıza özel hazırladığımız kısa fikirleri paylaşmak isterim.\n\nİyi çalışmalar,\n{{sender_name}}\n{{agency_name}}",
                'is_default' => 1,
                'is_active' => 1,
                'created_by' => null
            ]);
        }
    }
}
