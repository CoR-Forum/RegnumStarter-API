<?php
class GlobalFunctions {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getCurrentStatus() {
        try {
            $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE name = 'status'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['value'] : 'unknown';
        } catch (\PDOException $e) {
            return 'error';
        }
    }

    public static function sendJsonResponse($status, $message, $data = []) {
        echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
        exit;
    }
}