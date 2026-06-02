<?php
require_once '../auth/common.php';

header('Content-Type: application/json');

$chatId = $_GET['chat_id'] ?? '';
$path = getChatPath($chatId);

if (!$chatId || !file_exists($path)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Chat not found']);
    exit;
}

$_SESSION['current_chat_id'] = $chatId;

$chat = json_decode(file_get_contents($path), true);

echo json_encode([
    'ok' => true,
    'chat' => $chat
]);