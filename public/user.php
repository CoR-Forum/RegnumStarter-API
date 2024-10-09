<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/License.php';

$action = $_GET['action'] ?? null;
$username = $_GET['username'] ?? null;
$password = $_GET['password'] ?? null;
$email = $_GET['email'] ?? null;
$token = $_GET['token'] ?? null;

$user = new User($pdo);

switch ($action) {
    case 'login':
        if ($user = $user->login($username, $password)) {
            $licenseObj = new License($pdo);
            $license = $licenseObj->getLastActivatedLicense($user['id']);
            
            if ($license) {
                $licensed_features = json_decode($license['licensed_features'], true);
                if (is_null($licensed_features)) {
                    $licensed_features = ["zoom"];
                }
            } else {
                $licensed_features = ["zoom"];
            }
            
            $response = [
                'status' => 'success',
                'message' => 'Login successful',
                'username' => $user['username'],
                'id' => $user['id'],
                'created_at' => $user['created_at'],
                'is_active' => $user['is_active'],
                'email' => $user['email'],
                'license_key' => $license['license_key'] ?? null,
                'licensed_features' => $licensed_features,
                'expires_at' => $license['expires_at'] ?? null
            ];
            if ($user['is_admin'] == 1) {
                $response['role'] = "admin";
            } else {
                $response['role'] = "user";
            }
            echo json_encode($response);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Login failed']);
        }
        break;

    case 'register':
        if (!$username || !$password || !$email) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required ' . (!$username ? 'username, ' : '') . (!$password ? 'password, ' : '') . (!$email ? 'email' : '')]);
            exit;
        }
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid username. It should be 3-20 characters long and can contain letters, numbers, and underscores.']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email address']);
            exit;
        }
        if (strlen($password) < 8) {
            echo json_encode(['status' => 'error', 'message' => 'Password should be at least 8 characters long']);
            exit;
        }
        if ($user->userExists($username, $email)) {
            echo json_encode(['status' => 'error', 'message' => 'Username or email already exists']);
            exit;
        }
        if ($user->register($username, $password, $email)) {
            echo json_encode(['status' => 'success', 'message' => 'User registered successfully. Please check your email to activate your account.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'User registration failed']);
        }
        break;

    case 'reset':
        $resetAction = $_GET['resetAction'] ?? null;
        if ($resetAction === 'init') {
            if (!$email) {
                echo json_encode(['status' => 'error', 'message' => 'Missing required email']);
                exit;
            }
            if ($user->initiatePasswordReset($email)) {
                echo json_encode(['status' => 'success', 'message' => 'Password reset initiated successfully. Check your email for the reset code']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Password reset initiation failed']);
            }
        } elseif ($resetAction === 'reset') {
            if (!$token || !$password) {
                echo json_encode(['status' => 'error', 'message' => 'Missing required ' . (!$token ? 'token, ' : '') . (!$password ? 'password' : '')]);
                exit;
            }
            if (strlen($password) < 8) {
                echo json_encode(['status' => 'error', 'message' => 'Password should be at least 8 characters long']);
                exit;
            }
            if ($user->resetPassword($token, $password)) {
                echo json_encode(['status' => 'success', 'message' => 'Password reset successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Password reset failed']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid reset action']);
        }
        break;

    case 'activate':
        if (!$token) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid activation token']);
            exit;
        }
        if ($user->activate($token)) {
            echo json_encode(['status' => 'success', 'message' => 'Account activated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Account activation failed']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}
?>