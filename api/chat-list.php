<?php
require_once '../auth/common.php';

header('Content-Type: application/json');

$index = loadChatsIndex();

usort($index, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));

echo json_encode([
    'ok' => true,
    'current_chat_id' => $_SESSION['current_chat_id'] ?? null,
    'chats' => $index
]);