<?php
session_start();
header('Content-Type: application/json');

require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/config/gemini.php';

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
        'error' => 'Gemini API key is missing. Please set it in config/gemini.php.'
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

    $budget = 0;
    if (preg_match('/(?:lkr|rs\.?|rupees?)?\s*([1-9][0-9]{3,6})/i', $userMessage, $budgetMatch)) {
        $budget = (int) $budgetMatch[1];
    }

    $days = 0;
    if (preg_match('/([1-9][0-9]?)\s*(?:days?|nights?)/i', $userMessage, $daysMatch)) {
        $days = (int) $daysMatch[1];
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
                    $reply = "⚠️ I am temporarily in backup mode, but I can still help.\n";
                    $reply .= "🌍 Top suggestions for {$districtTitle}:\n";
                    foreach ($rows as $place) {
                        $reply .= "✨ {$place['place_name']} ({$place['category']}) | 🗓️ Best time: {$place['best_time']} | 💰 Avg budget: LKR {$place['avg_budget_lkr']}\n";
                    }
                    if ($budget > 0 || $days > 0) {
                        $totalTripBudget = $budget > 0 ? $budget : ($rows[0]['avg_budget_lkr'] * max(1, $days));
                        $tripDays = $days > 0 ? $days : 2;
                        $stay = (int) round($totalTripBudget * 0.4);
                        $food = (int) round($totalTripBudget * 0.25);
                        $transport = (int) round($totalTripBudget * 0.2);
                        $activities = max(0, $totalTripBudget - ($stay + $food + $transport));
                        $reply .= "\n🗓️ Quick {$tripDays}-day plan (approx):\n";
                        for ($i = 1; $i <= $tripDays; $i++) {
                            $place = $rows[($i - 1) % count($rows)];
                            $reply .= "- Day {$i}: Visit {$place['place_name']} + local food + evening city walk\n";
                        }
                        $reply .= "\n💸 Estimated budget split (LKR {$totalTripBudget}): Stay {$stay}, Food {$food}, Transport {$transport}, Activities {$activities}.";
                        $reply .= "\n🚌 Tip: Use local bus/train for lower cost, and keep 10% buffer for tickets/snacks.";
                    } else {
                        $reply .= "\n🧳 Tell me your budget + number of days, and I will build a custom mini itinerary for you.";
                    }
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
                $reply = "⚠️ I am temporarily in backup mode. Here are some travel ideas:\n";
                foreach ($rows as $place) {
                    $reply .= "📍 {$place['place_name']} ({$place['district']} - {$place['category']}) | 💸 Approx LKR {$place['avg_budget_lkr']}\n";
                }
                $reply .= "\n🗺️ Tell me district + budget + days, and I will suggest the best plan.";
                return trim($reply);
            }
        }
    }

    return "🤖 Gemini is temporarily unavailable due to quota limits. I can still help! Share your destination, budget, and trip duration, and I will suggest a practical travel plan ✈️";
}

function getGreetingReply(string $message): string
{
    $normalized = strtolower(trim($message));
    $normalized = preg_replace('/\s+/', ' ', $normalized ?? '');
    $normalized = trim((string) $normalized, " \t\n\r\0\x0B!?.,");

    $greetings = [
        'hi', 'hello', 'hey', 'yo', 'hii', 'helo', 'hai', 'vanakkam', 'good morning', 'good evening'
    ];

    if (!in_array($normalized, $greetings, true)) {
        return '';
    }

    $replies = [
        "Hey there! 👋 Planning a trip to beautiful Sri Lanka? I can help with destinations, budgets, and smart itineraries ✈️",
        "Hello! 🌴 Ready to plan your Sri Lanka adventure? Share your destination, days, and budget.",
        "Hi! 😊 I am your Travel AI assistant. Tell me what you want: beaches, heritage, wildlife, or hill-country vibes?",
        "Hey! 🌍 I can suggest places, hotels, transport tips, and a day-wise plan based on your budget.",
        "Vanakkam! 👋 Let us build your perfect Sri Lanka trip plan together."
    ];

    return $replies[array_rand($replies)];
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

$greetingReply = getGreetingReply($message);
if ($greetingReply !== '') {
    saveMessage(
        $conn,
        $dbAvailable,
        $sessionId,
        'bot',
        $greetingReply
    );
    echo json_encode([
        'success' => true,
        'reply' => $greetingReply,
        'timestamp' => date('h:i A')
    ]);
    exit;
}

$systemPrompt = "You are Travel AI Chatbot, a smart tourism assistant. " .
    "Provide clear, friendly, practical travel advice in a warm Sri Lanka tourism style. " .
    "Always reply in the same language/style as the user (English, Tamil, Sinhala, or Tanglish) unless user asks otherwise. " .
    "If the user writes casually, respond casually but clearly, like a helpful human travel friend. " .
    "Understand the user intent first, think through the best practical recommendation internally, then give a confident final answer. " .
    "Do not mention internal reasoning, model limitations, or that you are an AI unless the user explicitly asks. " .
    "Avoid generic copy-paste replies; tailor each response to the exact user message. " .
    "Write in a ChatGPT-like style: natural, direct, and context-aware. " .
    "Start with the best answer immediately, then add concise actionable details. " .
    "Use short paragraphs or bullet points only when it improves clarity. " .
    "Do not over-greet, do not repeat template intros, and do not sound robotic. " .
    "If the user asks for a plan, provide a practical step-by-step plan with realistic estimates. " .
    "If input is unclear, ask one short clarifying question instead of giving a vague answer. " .
    "Follow Super Answer Mode for travel planning requests. " .
    "When user provides destination/budget/days, structure output exactly as: " .
    "1) Best Plan Summary, 2) Day-wise Itinerary, 3) Budget Breakdown, 4) Transport Tips, 5) Pro Tips. " .
    "Keep each section concise and practical. " .
    "Budget breakdown must include stay, food, transport, activities, and emergency buffer. " .
    "If budget seems too low, suggest a realistic adjusted budget with reason. " .
    "Prefer actionable recommendations over generic descriptions. " .
    "End with one short next-step suggestion. " .
    "Help with destinations, hotels, trip plans, transport, weather preparation, and travel budgets. " .
    "If user preferences are known, personalize recommendations and mention relevant local context. " .
    "Use a natural AI tone: confident, human-like, and not robotic. " .
    "Use clean structure with short sections or bullet points when helpful. " .
    "Include relevant emojis naturally (light usage only). " .
    "Avoid repeating the same opening sentence; vary phrasing across replies. " .
    "When details are missing, ask one short smart follow-up question first. " .
    "When the user gives budget and days, provide: day-wise mini itinerary, estimated cost breakdown (stay/food/transport/activities), and practical local transport tips. " .
    "Always keep answers useful and action-oriented. " .
    "If the user sends only emoji or a very short greeting, respond warmly and invite trip details.";

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

function callGeminiModel(string $model, array $payload): array
{
    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . urlencode(GEMINI_API_KEY);
    $send = static function (bool $allowInsecure) use ($apiUrl, $payload): array {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$allowInsecure);

        // Prefer verified TLS using a local CA bundle if available.
        $caCandidates = [
            PROJECT_ROOT . DIRECTORY_SEPARATOR . 'cacert.pem',
            PROJECT_ROOT . DIRECTORY_SEPARATOR . 'cert' . DIRECTORY_SEPARATOR . 'cacert.pem',
            'C:\\xampp\\php\\extras\\ssl\\cacert.pem'
        ];
        foreach ($caCandidates as $caPath) {
            if (is_file($caPath)) {
                curl_setopt($ch, CURLOPT_CAINFO, $caPath);
                break;
            }
        }

        $apiResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'response' => $apiResponse,
            'curl_error' => $curlError,
            'http_code' => $httpCode
        ];
    };

    $firstTry = $send(false);
    $firstError = strtolower((string) $firstTry['curl_error']);
    if (
        $firstTry['response'] === false &&
        (str_contains($firstError, 'ssl certificate problem') || str_contains($firstError, 'unable to get local issuer certificate'))
    ) {
        $retry = $send(true);
        if ($retry['response'] !== false && (int) $retry['http_code'] > 0) {
            $retry['insecure_tls'] = true;
            return $retry;
        }
        return $retry;
    }

    return $firstTry;
}

$modelChain = array_values(array_unique([
    GEMINI_MODEL,
    'gemini-2.5-flash',
    'gemini-2.0-flash',
    'gemini-2.0-flash-lite'
]));

$responseData = null;
$lastApiError = '';
$lastFailureReason = 'Gemini AI is temporarily unavailable.';

foreach ($modelChain as $modelName) {
    $result = callGeminiModel($modelName, $payload);
    $apiResponse = $result['response'];
    $curlError = (string) $result['curl_error'];
    $httpCode = (int) $result['http_code'];
    $usedInsecureTls = !empty($result['insecure_tls']);

    if ($apiResponse === false || $curlError !== '') {
        $lastApiError = $curlError !== '' ? $curlError : 'Unknown network error.';
        $lastFailureReason = 'Gemini network issue.';
        continue;
    }

    $decoded = json_decode($apiResponse, true);
    if ($httpCode < 400 && isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        $responseData = $decoded;
        if ($usedInsecureTls) {
            $responseData['_warning'] = 'Temporary SSL workaround active on server. Configure php.ini curl.cainfo with a valid cacert.pem for secure verification.';
        }
        break;
    }

    $apiError = $decoded['error']['message'] ?? ('Gemini API HTTP ' . $httpCode);
    $lastApiError = $apiError;
    $normalizedError = strtolower($apiError);

    if (str_contains($normalizedError, 'quota') || str_contains($normalizedError, 'rate limit')) {
        $lastFailureReason = 'Gemini quota/rate-limit reached.';
    } elseif (str_contains($normalizedError, 'api key') || str_contains($normalizedError, 'permission') || str_contains($normalizedError, 'unauth')) {
        $lastFailureReason = 'Gemini API key or permission issue.';
    } elseif (str_contains($normalizedError, 'model')) {
        $lastFailureReason = 'Gemini model not available for this key.';
    } else {
        $lastFailureReason = 'Gemini API returned an error.';
    }
}

if (!is_array($responseData)) {
    $fallbackReply = buildLocalFallbackReply($message, $conn, $dbAvailable);
    saveMessage(
        $conn,
        $dbAvailable,
        $sessionId,
        'bot',
        $fallbackReply
    );
    echo json_encode([
        'success' => true,
        'reply' => $fallbackReply,
        'timestamp' => date('h:i A'),
        'warning' => trim($lastFailureReason . ' ' . ($lastApiError !== '' ? ('Details: ' . $lastApiError) : '') . ' Showing backup mode response.')
    ]);
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

if (isset($responseData['_warning']) && is_string($responseData['_warning'])) {
    $responsePayload['warning'] = $responseData['_warning'];
}

if (!$dbAvailable && $dbWarning !== '') {
    $responsePayload['warning'] = $dbWarning;
}

echo json_encode($responsePayload);
