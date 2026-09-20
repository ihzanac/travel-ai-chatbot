<?php
session_start();
header('Content-Type: application/json');

require_once dirname(__DIR__, 2) . '/includes/database.php';

if (!$dbAvailable || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database is not available.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please login first.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST is allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = trim((string) ($input['action'] ?? ''));
$userId = (int) $_SESSION['user_id'];

if ($action === 'new') {
    $title = trim((string) ($input['title'] ?? 'New Chat'));
    if ($title === '') {
        $title = 'New Chat';
    }
    $sessionKey = bin2hex(random_bytes(16));

    $stmt = $conn->prepare('INSERT INTO chat_threads (user_id, conversation_key, conversation_title) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $userId, $sessionKey, $title);
    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Unable to create chat session.']);
        exit;
    }
    $stmt->close();
    $_SESSION['chat_session_id'] = $sessionKey;

    echo json_encode([
        'success' => true,
        'session' => ['session_key' => $sessionKey, 'title' => $title]
    ]);
    exit;
}

if ($action === 'list') {
    $stmt = $conn->prepare('SELECT conversation_key AS session_key, conversation_title AS session_title, updated_on AS updated_at FROM chat_threads WHERE user_id = ? ORDER BY updated_on DESC');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $sessions = [];
    while ($row = $result->fetch_assoc()) {
        $sessions[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'sessions' => $sessions]);
    exit;
}

if ($action === 'load') {
    $sessionKey = trim((string) ($input['session_key'] ?? ''));
    if ($sessionKey === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'session_key is required.']);
        exit;
    }

    $checkStmt = $conn->prepare('SELECT thread_id FROM chat_threads WHERE user_id = ? AND conversation_key = ? LIMIT 1');
    $checkStmt->bind_param('is', $userId, $sessionKey);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    if (!$checkResult->fetch_assoc()) {
        $checkStmt->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Chat session not found.']);
        exit;
    }
    $checkStmt->close();

    $_SESSION['chat_session_id'] = $sessionKey;

    $stmt = $conn->prepare('SELECT sender_role AS sender, message_body AS message, created_on AS created_at FROM chat_entries WHERE conversation_key = ? ORDER BY entry_id ASC');
    $stmt->bind_param('s', $sessionKey);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;
}

if ($action === 'rename') {
    $sessionKey = trim((string) ($input['session_key'] ?? ''));
    $title = trim((string) ($input['title'] ?? ''));
    if ($sessionKey === '' || $title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'session_key and title are required.']);
        exit;
    }
    if (strlen($title) > 255) {
        $title = substr($title, 0, 255);
    }

    $checkStmt = $conn->prepare('SELECT thread_id FROM chat_threads WHERE user_id = ? AND conversation_key = ? LIMIT 1');
    $checkStmt->bind_param('is', $userId, $sessionKey);
    $checkStmt->execute();
    if (!$checkStmt->get_result()->fetch_assoc()) {
        $checkStmt->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Chat session not found.']);
        exit;
    }
    $checkStmt->close();

    $upd = $conn->prepare('UPDATE chat_threads SET conversation_title = ?, updated_on = CURRENT_TIMESTAMP WHERE user_id = ? AND conversation_key = ?');
    $upd->bind_param('sis', $title, $userId, $sessionKey);
    if (!$upd->execute()) {
        $upd->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Could not save chat name.']);
        exit;
    }
    $upd->close();

    echo json_encode(['success' => true, 'title' => $title]);
    exit;
}

if ($action === 'delete') {
    $sessionKey = trim((string) ($input['session_key'] ?? ''));
    if ($sessionKey === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'session_key is required.']);
        exit;
    }

    $checkStmt = $conn->prepare('SELECT thread_id FROM chat_threads WHERE user_id = ? AND conversation_key = ? LIMIT 1');
    $checkStmt->bind_param('is', $userId, $sessionKey);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    if (!$checkResult->fetch_assoc()) {
        $checkStmt->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Chat session not found.']);
        exit;
    }
    $checkStmt->close();

    $deleteMessagesStmt = $conn->prepare('DELETE FROM chat_entries WHERE conversation_key = ?');
    $deleteMessagesStmt->bind_param('s', $sessionKey);
    $deleteMessagesStmt->execute();
    $deleteMessagesStmt->close();

    $deleteSessionStmt = $conn->prepare('DELETE FROM chat_threads WHERE user_id = ? AND conversation_key = ?');
    $deleteSessionStmt->bind_param('is', $userId, $sessionKey);
    $deleteSessionStmt->execute();
    $deleteSessionStmt->close();

    if (isset($_SESSION['chat_session_id']) && $_SESSION['chat_session_id'] === $sessionKey) {
        $_SESSION['chat_session_id'] = '';
    }

    echo json_encode(['success' => true, 'message' => 'Chat deleted successfully.']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action.']);
