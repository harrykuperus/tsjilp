<?php
require_once '../auth/common.php';

header('Content-Type: application/json');

$chatId = bin2hex(random_bytes(4));
$time = now();

$chat = [
    'id' => $chatId,
    'title' => 'New chat',
    'created_at' => $time,
    'updated_at' => $time,
    'messages' => []
];

file_put_contents(
    getChatPath($chatId),
    json_encode($chat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

$index = loadChatsIndex();
array_unshift($index, [
    'id' => $chatId,
    'title' => 'New chat',
    'updated_at' => $time
]);
saveChatsIndex($index);

$_SESSION['current_chat_id'] = $chatId;

echo json_encode([
    'ok' => true,
    'chat_id' => $chatId,
    'chat' => $chat
]);