<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Company
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll($filters = [], $limit = 20, $offset = 0)
    {
        $sql = "SELECT * FROM companies WHERE 1=1";
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= " AND (name LIKE :q OR phone LIKE :q OR sector LIKE :q OR website LIKE :q)";
            $params['q'] = "%" . $filters['q'] . "%";
        }

        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['sector'])) {
            $sql .= " AND sector = :sector";
            $params['sector'] = $filters['sector'];
        }

        if (!empty($filters['district'])) {
            $sql .= " AND district = :district";
            $params['district'] = $filters['district'];
        }

        $sql .= " ORDER BY id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countAllFiltered($filters = [])
    {
        $sql = "SELECT COUNT(*) FROM companies WHERE 1=1";
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= " AND (name LIKE :q OR phone LIKE :q OR sector LIKE :q OR website LIKE :q)";
            $params['q'] = "%" . $filters['q'] . "%";
        }
        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['sector'])) {
            $sql .= " AND sector = :sector";
            $params['sector'] = $filters['sector'];
        }
        if (!empty($filters['district'])) {
            $sql .= " AND district = :district";
            $params['district'] = $filters['district'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM companies WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function create($data)
    {
        $fields = array_keys($data);
        $placeholders = array_map(function($field) { return ":" . $field; }, $fields);
        
        $sql = "INSERT INTO companies (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
        }
        $data['id'] = $id;

        $sql = "UPDATE companies SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM companies WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function countAll()
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM companies");
        return (int) $stmt->fetchColumn();
    }

    public function countByStatus($status)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM companies WHERE status = :status");
        $stmt->execute(['status' => $status]);
        return (int) $stmt->fetchColumn();
    }

    public function findPotentialDuplicate($name, $phone, $website)
    {
        $sql = "SELECT id, name FROM companies WHERE name = :name OR (phone != '' AND phone = :phone) OR (website != '' AND website = :website) LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'name' => $name,
            'phone' => $phone ?? '',
            'website' => $website ?? ''
        ]);
        return $stmt->fetch();
    }

    public function getLatest($limit = 5)
    {
        $stmt = $this->db->query("SELECT * FROM companies ORDER BY id DESC LIMIT " . (int)$limit);
        return $stmt->fetchAll();
    }
}
