<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/Global.php';

// Read the input from the request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$type = $data['type'] ?? null;
$username = $data['username'] ?? null;
$password = $data['password'] ?? null;
$feedback = $data['feedback'] ?? null;
$log = $data['log'] ?? null; // Optional log parameter

$missingParams = [];

if (!$type) {
    $missingParams[] = 'type';
}
if (!$username) {
    $missingParams[] = 'username';
}
if (!$password) {
    $missingParams[] = 'password';
}
if (!$feedback) {
    $missingParams[] = 'feedback';
}

if (!empty($missingParams)) {
    GlobalFunctions::sendJsonResponse('error', 'Missing required parameter(s): ' . implode(', ', $missingParams));
}

$user = new User($pdo);
$loggedInUser = $user->login($username, $password);

if (!$loggedInUser) {
    GlobalFunctions::sendJsonResponse('error', 'Login failed');
}

// Store feedback in the database
$stmt = $pdo->prepare('INSERT INTO feedback (type, user_id, feedback, log) VALUES (?, ?, ?, ?)');
$stmt->execute([$type, $loggedInUser['id'], $feedback, $log]);

$fields = [
    [
        'name' => 'Type',
        'value' => $type,
        'inline' => true
    ],
    [
        'name' => 'From',
        'value' => $username,
        'inline' => true
    ]
];

if ($log) {
    $fields[] = [
        'name' => 'Log Included',
        'value' => 'Yes',
        'inline' => true
    ];
}

$payload = json_encode([
    'embeds' => [
        [
            'title' => 'Feedback',
            'description' => $feedback,
            'color' => 16711680,
            'fields' => $fields
        ]
    ]
]);

$options = [
    'http' => [
        'header'  => "Content-Type: application/json\r\n",
        'method'  => 'POST',
        'content' => $payload,
    ],
];

$context  = stream_context_create($options);
$result = file_get_contents($discordFeedbackWebhookUrl, false, $context);

if ($result === FALSE) {
    GlobalFunctions::sendJsonResponse('error', 'Failed to send feedback');
} else {
    GlobalFunctions::sendJsonResponse('success', 'Feedback submitted successfully');
}
?>