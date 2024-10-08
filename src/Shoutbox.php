<?php
class Shoutbox {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function addMessage($userId, $message) {
        if ($this->isUserBanned($userId)) {
            return ['status' => 'error', 'message' => 'User is banned from the shoutbox'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO shoutbox_messages (user_id, message, created_at) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $message, (new DateTime())->format('Y-m-d H:i:s')]);

        // Fetch the last 50 messages
        $messages = $this->getMessages(50);

        return ['status' => 'success', 'message' => 'Message added successfully', 'messages' => $messages];
    }

    public function getMessages($limit = null) {
        $sql = 'SELECT s.message, s.created_at, u.username FROM shoutbox_messages s JOIN users u ON s.user_id = u.id WHERE s.deleted_at IS NULL ORDER BY s.created_at ASC';
        if ($limit !== null) {
            $sql .= ' LIMIT ?';
        }
        $stmt = $this->pdo->prepare($sql);
        if ($limit !== null) {
            $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function isUserBanned($userId) {
        $stmt = $this->pdo->prepare('SELECT shoutbox_banned FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['shoutbox_banned'];
    }

    // admin function to delete a message by id
    // check if user is admin with isAdmin function from User class
    // then set the deleted_at and deleted_by columns of the shoutbox_messages table entry
    public function deleteMessage($userId, $messageId) {
        if (!$this->isUserBanned($userId) && (new User($this->pdo))->isAdmin($userId)) {
            $stmt = $this->pdo->prepare('UPDATE shoutbox_messages SET deleted_at = ?, deleted_by = ? WHERE id = ?');
            $stmt->execute([(new DateTime())->format('Y-m-d H:i:s'), $userId, $messageId]);
            return ['status' => 'success', 'message' => 'Message marked as deleted successfully'];
        }
        return ['status' => 'error', 'message' => 'User is banned or not an admin'];
    }

}
?>