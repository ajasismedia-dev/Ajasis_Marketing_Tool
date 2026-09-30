<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class User
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findByUsername($username)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        return $stmt->fetch();
    }

    public function countUsers()
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM users");
        return (int) $stmt->fetchColumn();
    }

    public function create($name, $username, $passwordHash)
    {
        $stmt = $this->db->prepare("INSERT INTO users (name, username, password_hash) VALUES (:name, :username, :password)");
        return $stmt->execute([
            'name' => $name,
            'username' => $username,
            'password' => $passwordHash
        ]);
    }
}
