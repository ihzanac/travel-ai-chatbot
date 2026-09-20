<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/includes/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST is allowed.']);
    exit;
}
if (!$dbAvailable || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database is not available.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = trim((string) ($input['action'] ?? ''));

function verifyAndUpgradePassword(mysqli $conn, array $user, string $password): bool
{
    $storedHash = (string) ($user['passwd_hash'] ?? '');
    if ($storedHash === '') {
        return false;
    }

    if (password_verify($password, $storedHash)) {
        return true;
    }

    // Backward compatibility: support old plaintext-stored passwords,
    // then immediately migrate to a secure hash.
    if (!password_get_info($storedHash)['algo'] && hash_equals($storedHash, $password)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $upgradeStmt = $conn->prepare('UPDATE app_users SET passwd_hash = ? WHERE user_id = ?');
        if ($upgradeStmt) {
            $userId = (int) $user['user_id'];
            $upgradeStmt->bind_param('si', $newHash, $userId);
            $upgradeStmt->execute();
            $upgradeStmt->close();
        }
        return true;
    }

    // Auto-fix known bad seeded admin hashes used by older SQL snapshots.
    $isDefaultAdmin = strtolower((string) ($user['email_address'] ?? '')) === 'admin@travelai.local';
    if ($isDefaultAdmin && hash_equals($password, 'admin123')) {
        $knownBadSeedHashes = [
            '$2y$10$7fqaTP71.dK3sQl0SpTjCOgA0h7IWq./HBqs./USU0QkkiXKxyWtS',
            '$2y$10$WmNJRT86Yh.JPVU82wGXK./8IWpEjitOrfrmzVPruaKbXwAdq.jYi',
            '$2y$10$IIuK3Y1HPedNzexZEAaBfO6O3bbZxz4AabapTdEAO4ApwhmhBMsMW',
        ];
        if (in_array($storedHash, $knownBadSeedHashes, true)) {
            $newHash = password_hash('admin123', PASSWORD_DEFAULT);
            $upgradeStmt = $conn->prepare('UPDATE app_users SET passwd_hash = ? WHERE user_id = ?');
            if ($upgradeStmt) {
                $userId = (int) $user['user_id'];
                $upgradeStmt->bind_param('si', $newHash, $userId);
                $upgradeStmt->execute();
                $upgradeStmt->close();
            }
            return true;
        }
    }

    return false;
}

if ($action === 'register') {
    $name = trim((string) ($input['name'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $password = (string) ($input['password'] ?? '');
    if ($name === '' || $email === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'All fields are required.']);
        exit;
    }
    $checkStmt = $conn->prepare('SELECT user_id FROM app_users WHERE email_address = ? LIMIT 1');
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    if ($checkStmt->get_result()->fetch_assoc()) {
        $checkStmt->close();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Email already exists.']);
        exit;
    }
    $checkStmt->close();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO app_users (display_name, email_address, passwd_hash, admin_flag) VALUES (?, ?, ?, 0)');
    $stmt->bind_param('sss', $name, $email, $hash);
    $stmt->execute();
    $_SESSION['user_id'] = (int) $conn->insert_id;
    $_SESSION['user_name'] = $name;
    $stmt->close();
    echo json_encode(['success' => true, 'user' => ['id' => $_SESSION['user_id'], 'name' => $name]]);
    exit;
}

if ($action === 'login') {
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $password = (string) ($input['password'] ?? '');
    if ($email === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email and password are required.']);
        exit;
    }
    $stmt = $conn->prepare('SELECT user_id, display_name, email_address, passwd_hash FROM app_users WHERE email_address = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Account not found. Please register first.']);
        exit;
    }
    $isValidPassword = verifyAndUpgradePassword($conn, $user, $password);
    if (!$isValidPassword) {
        $trimmedPassword = trim($password);
        if ($trimmedPassword !== $password && $trimmedPassword !== '') {
            $isValidPassword = verifyAndUpgradePassword($conn, $user, $trimmedPassword);
        }
    }
    if (!$isValidPassword) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid email or password.']);
        exit;
    }
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['user_name'] = (string) $user['display_name'];
    echo json_encode(['success' => true, 'user' => ['id' => $_SESSION['user_id'], 'name' => $_SESSION['user_name']]]);
    exit;
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'status') {
    $isLoggedIn = isset($_SESSION['user_id']);
    echo json_encode(['success' => true, 'authenticated' => $isLoggedIn, 'user' => $isLoggedIn ? ['id' => (int) $_SESSION['user_id'], 'name' => (string) $_SESSION['user_name']] : null]);
    exit;
}

if ($action === 'profile_get') {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Please login first.']);
        exit;
    }
    $userId = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare('SELECT user_id, display_name, email_address FROM app_users WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo json_encode(['success' => true, 'user' => ['id' => (int) $u['user_id'], 'full_name' => $u['display_name'], 'email' => $u['email_address']]]);
    exit;
}

if ($action === 'profile_update') {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Please login first.']);
        exit;
    }
    $userId = (int) $_SESSION['user_id'];
    $name = trim((string) ($input['name'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $password = (string) ($input['password'] ?? '');
    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE app_users SET display_name = ?, email_address = ?, passwd_hash = ? WHERE user_id = ?');
        $stmt->bind_param('sssi', $name, $email, $hash, $userId);
    } else {
        $stmt = $conn->prepare('UPDATE app_users SET display_name = ?, email_address = ? WHERE user_id = ?');
        $stmt->bind_param('ssi', $name, $email, $userId);
    }
    $stmt->execute();
    $stmt->close();
    $_SESSION['user_name'] = $name;
    echo json_encode(['success' => true, 'user' => ['id' => $userId, 'name' => $name, 'email' => $email]]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action.']);
