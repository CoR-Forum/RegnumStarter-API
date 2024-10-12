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

    public function toggleUserBan($userId) {
        $this->checkAdmin();
        $stmt = $this->pdo->prepare('UPDATE users SET is_banned = NOT is_banned WHERE id = ?');
        $stmt->execute([$userId]);
        return ['status' => 'success', 'message' => 'User ' . ($stmt->rowCount() ? 'banned' : 'unbanned ') . 'successfully.'];
    }

    public function toggleUserAdmin($userId) {
        $this->checkAdmin();
        $stmt = $this->pdo->prepare('UPDATE users SET is_admin = NOT is_admin WHERE id = ?');
        $stmt->execute([$userId]);
        return ['status' => 'success', 'message' => 'User ' . ($stmt->rowCount() ? 'promoted' : 'demoted ') . 'successfully.'];
    }

    public function toggleUserActivation($userId) {
        $this->checkAdmin();
        $stmt = $this->pdo->prepare('UPDATE users SET is_active = NOT is_active WHERE id = ?');
        $stmt->execute([$userId]);
        return ['status' => 'success', 'message' => 'User ' . ($stmt->rowCount() ? 'activated' : 'deactivated ') . 'successfully.'];
    }

    public function getAllLicenses() {
        $this->checkAdmin();
        $stmt = $this->pdo->prepare('SELECT id, license_key, licensed_features, activated_by, activated_at, runtime_end, runtime, expires_at FROM licenses');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}