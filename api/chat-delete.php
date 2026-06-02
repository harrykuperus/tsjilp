<?php
require_once '../auth/common.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$chatId = $input['chat_id'] ?? '';

$path = getChatPath($chatId);

if (!$chatId || !file_exists($path)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Chat not found']);
    exit;
}

unlink($path);

$index = loadChatsIndex();
$index = array_values(array_filter($index, fn($c) => $c['id'] !== $chatId));
saveChatsIndex($index);

if (($_SESSION['current_chat_id'] ?? null) === $chatId) {
    $_SESSION['current_chat_id'] = $index[0]['id'] ?? null;
}

echo json_encode([
    'ok' => true,
    'current_chat_id' => $_SESSION['current_chat_id'] ?? null
]);