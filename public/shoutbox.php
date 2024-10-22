<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/Shoutbox.php';
require_once __DIR__ . '/../src/Global.php';

$action = $_GET['action'] ?? null;
$username = $_GET['username'] ?? null;
$password = $_GET['password'] ?? null;
$message = $_GET['message'] ?? null;

if (!$username || !$password) {
    GlobalFunctions::sendJsonResponse('error', 'Missing required username or password');
}

$user = new User($pdo);
if ($user = $user->login($username, $password)) {
    $shoutbox = new Shoutbox($pdo);
    if ($action === 'add' && $message) {
        $result = $shoutbox->addMessage($user['id'], $message);
        GlobalFunctions::sendJsonResponse('success', 'Message added successfully', $result);
    } elseif ($action === 'get') {
        $messages = $shoutbox->getMessages();
        GlobalFunctions::sendJsonResponse('success', 'Messages fetched successfully', ['messages' => $messages]);
    } elseif ($action === 'delete' && $message) {
        $result = $shoutbox->deleteMessage($user['id'], $message);
        GlobalFunctions::sendJsonResponse('success', 'Message deleted successfully', $result);
    } elseif ($action === 'private' && $message && $_GET['recipient']) {
        $recipient = $_GET['recipient'];
        $result = $shoutbox->addPrivateMessage($user['id'], $message, $recipient);
        GlobalFunctions::sendJsonResponse('success', 'Private message added successfully', $result);
    } elseif ($action === 'getprivate' && $_GET['recipient']) {
        $recipient = $_GET['recipient'];
        $messages = $shoutbox->getPrivateMessages($user['id'], $recipient);
        GlobalFunctions::sendJsonResponse('success', 'Private messages fetched successfully', ['messages' => $messages]);
    } else {
        GlobalFunctions::sendJsonResponse('error', 'Invalid action or missing message');
    }
} else {
    GlobalFunctions::sendJsonResponse('error', 'Login failed');
}
?>