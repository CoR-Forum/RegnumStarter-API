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
        $stmt = $this->pdo->prepare('SELECT id, username, email, is_active, is_admin, shoutbox_banned, created_at, updated_at, is_banned , last_login, last_activity FROM users');
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
        $stmt = $this->pdo->prepare('
            SELECT 
                licenses.id, 
                licenses.license_key, 
                licenses.licensed_features, 
                licenses.activated_by, 
                users.username AS activated_by_username, 
                licenses.activated_at, 
                licenses.runtime_end, 
                licenses.runtime, 
                licenses.expires_at 
            FROM licenses
            LEFT JOIN users ON licenses.activated_by = users.id
        ');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function expireLicense($licenseId) {
        $this->checkAdmin();
        $stmt = $this->pdo->prepare('UPDATE licenses SET runtime_end = NOW() WHERE id = ?');
        $stmt->execute([$licenseId]);
        return ['status' => 'success', 'message' => 'License disabled successfully.'];
    }

}