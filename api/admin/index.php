<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/includes/database.php';

if (!isset($_SESSION['admin_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!$dbAvailable || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database unavailable']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = trim((string) ($input['action'] ?? ''));

if ($action === 'dashboard_stats') {
    $usersCount = (int) ($conn->query('SELECT COUNT(*) AS c FROM app_users')->fetch_assoc()['c'] ?? 0);
    $placesCount = (int) ($conn->query('SELECT COUNT(*) AS c FROM tour_places')->fetch_assoc()['c'] ?? 0);
    $chatsCount = (int) ($conn->query('SELECT COUNT(*) AS c FROM chat_entries')->fetch_assoc()['c'] ?? 0);
    $todayUsers = (int) ($conn->query('SELECT COUNT(*) AS c FROM app_users WHERE DATE(created_on) = CURDATE()')->fetch_assoc()['c'] ?? 0);
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_users' => $usersCount,
            'total_places' => $placesCount,
            'total_chats' => $chatsCount,
            'today_users' => $todayUsers
        ]
    ]);
    exit;
}

if ($action === 'district_list') {
    $rows = $conn->query('SELECT district_id AS id, district_title AS district_name, created_on AS created_at FROM tour_districts ORDER BY district_title ASC')->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'districts' => $rows ?: []]);
    exit;
}

if ($action === 'district_save') {
    $id = (int) ($input['id'] ?? 0);
    $name = trim((string) ($input['district_name'] ?? ''));
    if ($name === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'District name required']);
        exit;
    }
    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE tour_districts SET district_title = ? WHERE district_id = ?');
        $stmt->bind_param('si', $name, $id);
    } else {
        $stmt = $conn->prepare('INSERT INTO tour_districts (district_title) VALUES (?)');
        $stmt->bind_param('s', $name);
    }
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'district_delete') {
    $id = (int) ($input['id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM tour_districts WHERE district_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'place_list') {
    $rows = $conn->query('SELECT p.place_id AS id, d.district_title AS district, p.place_title AS place_name, p.place_type AS category, p.place_description AS description, p.best_season AS best_time, p.average_budget_lkr AS avg_budget_lkr FROM tour_places p INNER JOIN tour_districts d ON d.district_id = p.district_id ORDER BY p.place_id DESC')->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'places' => $rows]);
    exit;
}

if ($action === 'place_save') {
    $id = (int) ($input['id'] ?? 0);
    $district = trim((string) ($input['district'] ?? ''));
    $name = trim((string) ($input['place_name'] ?? ''));
    $category = trim((string) ($input['category'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $bestTime = trim((string) ($input['best_time'] ?? ''));
    $budget = (int) ($input['avg_budget_lkr'] ?? 0);
    $districtIdStmt = $conn->prepare('SELECT district_id FROM tour_districts WHERE district_title = ? LIMIT 1');
    $districtIdStmt->bind_param('s', $district);
    $districtIdStmt->execute();
    $districtRow = $districtIdStmt->get_result()->fetch_assoc();
    $districtIdStmt->close();
    if (!$districtRow) {
        $createDistrictStmt = $conn->prepare('INSERT INTO tour_districts (district_title) VALUES (?)');
        $createDistrictStmt->bind_param('s', $district);
        $createDistrictStmt->execute();
        $districtId = (int) $conn->insert_id;
        $createDistrictStmt->close();
    } else {
        $districtId = (int) $districtRow['district_id'];
    }

    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE tour_places SET district_id=?, place_title=?, place_type=?, place_description=?, best_season=?, average_budget_lkr=? WHERE place_id=?');
        $stmt->bind_param('issssii', $districtId, $name, $category, $description, $bestTime, $budget, $id);
    } else {
        $stmt = $conn->prepare('INSERT INTO tour_places (district_id, place_title, place_type, place_description, best_season, average_budget_lkr) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('issssi', $districtId, $name, $category, $description, $bestTime, $budget);
    }
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'place_delete') {
    $id = (int) ($input['id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM tour_places WHERE place_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'user_list') {
    $rows = $conn->query('SELECT user_id AS id, display_name AS full_name, email_address AS email, admin_flag AS is_admin, created_on AS created_at FROM app_users ORDER BY user_id DESC')->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'users' => $rows]);
    exit;
}

if ($action === 'user_create') {
    $name = trim((string) ($input['full_name'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $isAdmin = (int) ($input['is_admin'] ?? 0);

    if ($name === '' || $email === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Name, email and password are required']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email format']);
        exit;
    }
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
        exit;
    }

    $checkStmt = $conn->prepare('SELECT user_id FROM app_users WHERE email_address = ? LIMIT 1');
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    if ($checkStmt->get_result()->fetch_assoc()) {
        $checkStmt->close();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Email already exists']);
        exit;
    }
    $checkStmt->close();

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO app_users (display_name, email_address, passwd_hash, admin_flag) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('sssi', $name, $email, $hash, $isAdmin);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'user_admin_toggle') {
    $id = (int) ($input['id'] ?? 0);
    $isAdmin = (int) ($input['is_admin'] ?? 0);
    $stmt = $conn->prepare('UPDATE app_users SET admin_flag = ? WHERE user_id = ?');
    $stmt->bind_param('ii', $isAdmin, $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'user_delete') {
    $id = (int) ($input['id'] ?? 0);
    if ($id === (int) $_SESSION['admin_user_id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cannot delete current admin account']);
        exit;
    }
    $stmt = $conn->prepare('DELETE FROM app_users WHERE user_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action']);
