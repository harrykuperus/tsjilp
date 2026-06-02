<?php
require_once __DIR__ . '/contacts-common.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $context = contacts_resolve_owner_context();
    $payload = contacts_load_file($context['contacts_file']);

    echo json_encode([
        'ok' => true,
        'contacts' => contacts_records_for_response($payload)
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
