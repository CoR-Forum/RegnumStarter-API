<?php

class Admin {

    private $pdo;
    private $isAdmin;

    public function __construct($pdo, $isAdmin) {
        $this->pdo = $pdo;
        $this->isAdmin = $isAdmin;
    }

    private function checkAdmin() {
        if (!$this->isAdmin) {
            throw new Exception('Access denied. User is not an admin.');
        }
    }

    public function getAllUsers() {
        $this->checkAdmin();
        $stmt = $this->pdo->prepare('SELECT id, username, email, is_active, is_admin, shoutbox_banned, created_at, updated_at, is_banned FROM users');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}