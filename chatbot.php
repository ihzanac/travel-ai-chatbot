<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST requests are allowed.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

$message = '';
if (is_array($input) && isset($input['message'])) {
    $message = trim((string) $input['message']);
} elseif (isset($_POST['message'])) {
    $message = trim((string) $_POST['message']);
}

$message = filter_var($message, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);

if ($message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please enter a message.']);
    exit;
}

if (strlen($message) > 1000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message is too long. Maximum 1000 characters allowed.']);
    exit;
}

if (GEMINI_API_KEY === 'PASTE_YOUR_GEMINI_API_KEY_HERE' || GEMINI_API_KEY === '') {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Gemini API key is missing. Please set it in config.php.'
    ]);
    exit;
}

if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'PHP cURL extension is not enabled. Enable curl in php.ini or run via XAMPP Apache.'
    ]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Please login to chat.'
    ]);
    exit;
}

if (!isset($_SESSION['chat_session_id'])) {
    $_SESSION['chat_session_id'] = '';
}

function ensureStringSessionForUser(?mysqli $conn, bool $dbAvailable, int $userId): string
{
    if (isset($_SESSION['chat_session_id']) && $_SESSION['chat_session_id'] !== '') {
        return (string) $_SESSION['chat_session_id'];
    }

    if ($dbAvailable && $conn) {
        $sessionKey = bin2hex(random_bytes(16));
        $title = 'New Chat';
        $stmt = $conn->prepare('INSERT INTO chat_threads (user_id, conversation_key, conversation_title) VALUES (?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('iss', $userId, $sessionKey, $title);
            if ($stmt->execute()) {
                $stmt->close();
                $_SESSION['chat_session_id'] = $sessionKey;
                return $sessionKey;
            }
            $stmt->close();
        }
    }

    $fallback = bin2hex(random_bytes(16));
    $_SESSION['chat_session_id'] = $fallback;
    return $fallback;
}

$userId = (int) $_SESSION['user_id'];
$sessionId = ensureStringSessionForUser($conn, $dbAvailable, $userId);

function buildLocalFallbackReply(string $userMessage, ?mysqli $conn, bool $dbAvailable): string
{
    $messageLower = strtolower($userMessage);
    $districts = [
        'colombo', 'kandy', 'galle', 'jaffna', 'ella', 'anuradhapura', 'nuwara eliya',
        'trincomalee', 'matara', 'batticaloa', 'negombo', 'kalpitiya', 'yala'
    ];
    $matchedDistrict = '';

    foreach ($districts as $district) {
        if (str_contains($messageLower, $district)) {
            $matchedDistrict = $district;
            break;
        }
    }

    if ($dbAvailable && $conn) {
        if ($matchedDistrict !== '') {
            $districtTitle = ucwords($matchedDistrict);
            $stmt = $conn->prepare(
                'SELECT p.place_title AS place_name, p.place_type AS category, p.best_season AS best_time, p.average_budget_lkr AS avg_budget_lkr FROM tour_places p INNER JOIN tour_districts d ON d.district_id = p.district_id WHERE d.district_title = ? ORDER BY p.average_budget_lkr ASC LIMIT 3'
            );
            if ($stmt) {
                $stmt->bind_param('s', $districtTitle);
                $stmt->execute();
                $result = $stmt->get_result();
                $rows = [];
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
                $stmt->close();

                if (!empty($rows)) {
                    $reply = "I am temporarily in backup mode (Gemini quota reached), but I can still help.\n";
                    $reply .= "Top suggestions for {$districtTitle}:\n";
                    foreach ($rows as $place) {
                        $reply .= "- {$place['place_name']} ({$place['category']}), best time: {$place['best_time']}, avg budget: LKR {$place['avg_budget_lkr']}\n";
                    }
                    $reply .= "\nAsk me your budget and number of days, and I will build a custom mini itinerary.";
                    return trim($reply);
                }
            }
        }

        $genericStmt = $conn->prepare(
            'SELECT d.district_title AS district, p.place_title AS place_name, p.place_type AS category, p.average_budget_lkr AS avg_budget_lkr FROM tour_places p INNER JOIN tour_districts d ON d.district_id = p.district_id ORDER BY p.created_on DESC LIMIT 5'
        );
        if ($genericStmt) {
            $genericStmt->execute();
            $result = $genericStmt->get_result();
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $genericStmt->close();

            if (!empty($rows)) {
                $reply = "I am temporarily in backup mode (Gemini quota reached). Here are some travel ideas:\n";
                foreach ($rows as $place) {
                    $reply .= "- {$place['place_name']} ({$place['district']} - {$place['category']}), approx LKR {$place['avg_budget_lkr']}\n";
                }
                $reply .= "\nTell me district + budget + days, and I will suggest the best plan.";
                return trim($reply);
            }
        }
    }

    return "Gemini is temporarily unavailable due to quota limits. I can still help: tell me your destination, budget, and trip duration, and I will suggest a practical travel plan.";
}

function saveMessage(
    ?mysqli $conn,
    bool $dbAvailable,
    string $sessionId,
    string $sender,
    string $message
): void
{
    if (!$dbAvailable || !$conn) {
        return;
    }

    $sql = "INSERT INTO chat_entries (conversation_key, sender_role, message_body) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('sss', $sessionId, $sender, $message);
        $stmt->execute();
        $stmt->close();
        $updateStmt = $conn->prepare('UPDATE chat_threads SET updated_on = CURRENT_TIMESTAMP WHERE conversation_key = ?');
        if ($updateStmt) {
            $updateStmt->bind_param('s', $sessionId);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }
}

function getRecentContext(
    ?mysqli $conn,
    bool $dbAvailable,
    string $sessionId,
    int $limit = 8
): array
{
    if (!$dbAvailable || !$conn) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT sender_role AS sender, message_body AS message FROM chat_entries WHERE conversation_key = ? ORDER BY entry_id DESC LIMIT ?"
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('si', $sessionId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return array_reverse($rows);
}

saveMessage(
    $conn,
    $dbAvailable,
    $sessionId,
    'user',
    $message
);
$history = getRecentContext(
    $conn,
    $dbAvailable,
    $sessionId,
    8
);

$systemPrompt = "You are Travel AI Chatbot, a smart tourism assistant. " .
    "Provide clear, friendly, practical travel advice. " .
    "Help with destinations, hotels, trip plans, transport, weather preparation, and travel budgets. " .
    "If user preferences are known, personalize recommendations. " .
    "Keep answers concise but useful. Use bullet points when helpful.";

$conversationText = "";
foreach ($history as $item) {
    $speaker = $item['sender'] === 'user' ? 'User' : 'Assistant';
    $conversationText .= $speaker . ': ' . $item['message'] . "\n";
}

$payload = [
    'contents' => [
        [
            'parts' => [
                [
                    'text' => $systemPrompt . "\n\nConversation history:\n" . $conversationText .
                        "\nLatest user message: " . $message
                ]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.7,
        'topK' => 32,
        'topP' => 0.9,
        'maxOutputTokens' => 600
    ]
];

$apiUrl = GEMINI_ENDPOINT . '?key=' . urlencode(GEMINI_API_KEY);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$apiResponse = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($apiResponse === false || $curlError) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error' => 'Gemini AI is unavailable right now. Please try again.'
    ]);
    exit;
}

$responseData = json_decode($apiResponse, true);

if ($httpCode >= 400) {
    $apiError = $responseData['error']['message'] ?? 'Gemini API returned an error.';
    $normalizedError = strtolower($apiError);

    if (str_contains($normalizedError, 'quota') || str_contains($normalizedError, 'rate limit')) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Gemini quota exceeded. Enable billing/quota to get real AI answers.'
        ]);
        exit;
    }

    http_response_code(502);
    echo json_encode(['success' => false, 'error' => $apiError]);
    exit;
}

$botReply = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (trim($botReply) === '') {
    $botReply = "I could not generate a response this time. Please try again with a different travel question.";
}

saveMessage(
    $conn,
    $dbAvailable,
    $sessionId,
    'bot',
    $botReply
);

$responsePayload = [
    'success' => true,
    'reply' => $botReply,
    'timestamp' => date('h:i A')
];

if (!$dbAvailable && $dbWarning !== '') {
    $responsePayload['warning'] = $dbWarning;
}

echo json_encode($responsePayload);
