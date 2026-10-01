<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Communication
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getByCompany($companyId, $limit = 100)
    {
        $sql = "SELECT c.*, u.name AS created_by_name 
                FROM communications c
                LEFT JOIN users u ON c.created_by = u.id
                WHERE c.company_id = :company_id
                ORDER BY c.contacted_at DESC, c.id DESC
                LIMIT " . (int)$limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['company_id' => (int)$companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll($filters = [], $limit = 25, $offset = 0)
    {
        $sql = "SELECT c.*, comp.name AS company_name, comp.phone AS company_phone, comp.status AS company_status, u.name AS created_by_name
                FROM communications c
                INNER JOIN companies comp ON c.company_id = comp.id
                LEFT JOIN users u ON c.created_by = u.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['company_id'])) {
            $sql .= " AND c.company_id = :company_id";
            $params['company_id'] = (int)$filters['company_id'];
        }

        if (!empty($filters['type'])) {
            $sql .= " AND c.type = :type";
            $params['type'] = $filters['type'];
        }

        if (!empty($filters['direction'])) {
            $sql .= " AND c.direction = :direction";
            $params['direction'] = $filters['direction'];
        }

        if (!empty($filters['outcome'])) {
            $sql .= " AND c.outcome = :outcome";
            $params['outcome'] = $filters['outcome'];
        }

        if (!empty($filters['date_start'])) {
            $sql .= " AND DATE(c.contacted_at) >= :date_start";
            $params['date_start'] = $filters['date_start'];
        }

        if (!empty($filters['date_end'])) {
            $sql .= " AND DATE(c.contacted_at) <= :date_end";
            $params['date_end'] = $filters['date_end'];
        }

        if (!empty($filters['q'])) {
            $sql .= " AND (comp.name LIKE :q OR c.subject LIKE :q OR c.message LIKE :q)";
            $params['q'] = "%" . $filters['q'] . "%";
        }

        $sql .= " ORDER BY c.contacted_at DESC, c.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countFiltered($filters = [])
    {
        $sql = "SELECT COUNT(*) 
                FROM communications c
                INNER JOIN companies comp ON c.company_id = comp.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['company_id'])) {
            $sql .= " AND c.company_id = :company_id";
            $params['company_id'] = (int)$filters['company_id'];
        }

        if (!empty($filters['type'])) {
            $sql .= " AND c.type = :type";
            $params['type'] = $filters['type'];
        }

        if (!empty($filters['direction'])) {
            $sql .= " AND c.direction = :direction";
            $params['direction'] = $filters['direction'];
        }

        if (!empty($filters['outcome'])) {
            $sql .= " AND c.outcome = :outcome";
            $params['outcome'] = $filters['outcome'];
        }

        if (!empty($filters['date_start'])) {
            $sql .= " AND DATE(c.contacted_at) >= :date_start";
            $params['date_start'] = $filters['date_start'];
        }

        if (!empty($filters['date_end'])) {
            $sql .= " AND DATE(c.contacted_at) <= :date_end";
            $params['date_end'] = $filters['date_end'];
        }

        if (!empty($filters['q'])) {
            $sql .= " AND (comp.name LIKE :q OR c.subject LIKE :q OR c.message LIKE :q)";
            $params['q'] = "%" . $filters['q'] . "%";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findById($id)
    {
        $sql = "SELECT c.*, comp.name AS company_name, u.name AS created_by_name
                FROM communications c
                INNER JOIN companies comp ON c.company_id = comp.id
                LEFT JOIN users u ON c.created_by = u.id
                WHERE c.id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => (int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByClientMessageId($clientMessageId)
    {
        if (empty($clientMessageId)) return null;
        $sql = "SELECT c.*, comp.name AS company_name
                FROM communications c
                INNER JOIN companies comp ON c.company_id = comp.id
                WHERE c.client_message_id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $clientMessageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }


    public function create($data)
    {
        $fields = array_keys($data);
        $placeholders = array_map(function($field) { return ":" . $field; }, $fields);

        $sql = "INSERT INTO communications (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
        }
        $data['id'] = (int)$id;

        $sql = "UPDATE communications SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM communications WHERE id = :id");
        return $stmt->execute(['id' => (int)$id]);
    }

    public function getLatest($limit = 10)
    {
        $sql = "SELECT c.*, comp.name AS company_name
                FROM communications c
                INNER JOIN companies comp ON c.company_id = comp.id
                ORDER BY c.contacted_at DESC, c.id DESC
                LIMIT " . (int)$limit;
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentActivity($limit = 8)
    {
        $sql = "SELECT c.*, comp.name AS company_name, comp.status AS company_status
                FROM communications c
                INNER JOIN companies comp ON c.company_id = comp.id
                ORDER BY c.contacted_at DESC, c.id DESC
                LIMIT " . (int)$limit;
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTodayCount()
    {
        $sql = "SELECT COUNT(*) FROM communications 
                WHERE direction IN ('outbound', 'inbound') 
                AND DATE(contacted_at) = CURDATE()";
        return (int)$this->db->query($sql)->fetchColumn();
    }

    public function getThisWeekCount()
    {
        $sql = "SELECT COUNT(*) FROM communications 
                WHERE direction IN ('outbound', 'inbound') 
                AND YEARWEEK(contacted_at, 1) = YEARWEEK(CURDATE(), 1)";
        return (int)$this->db->query($sql)->fetchColumn();
    }

    public function getRepliedCount()
    {
        $sql = "SELECT COUNT(DISTINCT company_id) FROM communications 
                WHERE outcome IN ('replied', 'interested', 'proposal_requested')
                AND direction IN ('outbound', 'inbound')";
        return (int)$this->db->query($sql)->fetchColumn();
    }

    public function getProposalsCount()
    {
        $sql = "SELECT COUNT(DISTINCT company_id) FROM communications 
                WHERE outcome = 'proposal_sent'
                AND direction IN ('outbound', 'inbound')";
        return (int)$this->db->query($sql)->fetchColumn();
    }

}
