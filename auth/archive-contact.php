<?php
require_once __DIR__ . '/contacts-common.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $context = contacts_resolve_owner_context();
    $payload = contacts_load_file($context['contacts_file']);
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $contactId = trim((string)($input['id'] ?? ''));

    if ($contactId === '') {
        throw new RuntimeException('Missing contact id');
    }

    if (empty($payload['contacts'][$contactId]) || !is_array($payload['contacts'][$contactId])) {
        throw new RuntimeException('Contact not found');
    }

    $payload['contacts'][$contactId]['status'] = 'archived';
    $payload['contacts'][$contactId]['updated_at'] = date('Y-m-d H:i:s');
    contacts_save_file($context['contacts_file'], $payload);

    echo json_encode([
        'ok' => true,
        'id' => $contactId
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
