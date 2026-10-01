<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class FollowUp
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getByCompany($companyId)
    {
        $sql = "SELECT f.*, u.name AS created_by_name
                FROM follow_ups f
                LEFT JOIN users u ON f.created_by = u.id
                WHERE f.company_id = :company_id
                ORDER BY f.status ASC, f.due_at ASC, f.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['company_id' => (int)$companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingByCompany($companyId)
    {
        $sql = "SELECT f.*, u.name AS created_by_name
                FROM follow_ups f
                LEFT JOIN users u ON f.created_by = u.id
                WHERE f.company_id = :company_id AND f.status = 'pending'
                ORDER BY f.due_at ASC, f.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['company_id' => (int)$companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDueToday()
    {
        $sql = "SELECT f.*, comp.name AS company_name, comp.phone AS company_phone, comp.status AS company_status
                FROM follow_ups f
                INNER JOIN companies comp ON f.company_id = comp.id
                WHERE f.status = 'pending' AND DATE(f.due_at) = CURDATE()
                ORDER BY f.due_at ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOverdue()
    {
        $sql = "SELECT f.*, comp.name AS company_name, comp.phone AS company_phone, comp.status AS company_status
                FROM follow_ups f
                INNER JOIN companies comp ON f.company_id = comp.id
                WHERE f.status = 'pending' AND f.due_at < CURDATE()
                ORDER BY f.due_at ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUpcoming($limit = 10)
    {
        $sql = "SELECT f.*, comp.name AS company_name, comp.phone AS company_phone, comp.status AS company_status
                FROM follow_ups f
                INNER JOIN companies comp ON f.company_id = comp.id
                WHERE f.status = 'pending' AND f.due_at >= CURDATE() + INTERVAL 1 DAY
                ORDER BY f.due_at ASC
                LIMIT " . (int)$limit;
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function findById($id)
    {
        $sql = "SELECT f.*, comp.name AS company_name
                FROM follow_ups f
                INNER JOIN companies comp ON f.company_id = comp.id
                WHERE f.id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => (int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        $fields = array_keys($data);
        $placeholders = array_map(function($field) { return ":" . $field; }, $fields);

        $sql = "INSERT INTO follow_ups (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
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

        $sql = "UPDATE follow_ups SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function complete($id)
    {
        $sql = "UPDATE follow_ups SET status = 'completed', completed_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => (int)$id]);
    }

    public function cancel($id)
    {
        $sql = "UPDATE follow_ups SET status = 'cancelled' WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => (int)$id]);
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM follow_ups WHERE id = :id");
        return $stmt->execute(['id' => (int)$id]);
    }

    public function countDueToday()
    {
        $sql = "SELECT COUNT(*) FROM follow_ups WHERE status = 'pending' AND DATE(due_at) = CURDATE()";
        return (int)$this->db->query($sql)->fetchColumn();
    }

    public function countOverdue()
    {
        $sql = "SELECT COUNT(*) FROM follow_ups WHERE status = 'pending' AND due_at < CURDATE()";
        return (int)$this->db->query($sql)->fetchColumn();
    }

}
