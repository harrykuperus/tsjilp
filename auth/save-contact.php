<?php
require_once __DIR__ . '/contacts-common.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (empty($_SESSION['user_id'])) {
        throw new RuntimeException('Not logged in');
    }
    $userId = (string) $_SESSION['user_id'];

    $payload = load_user_contacts_by_id($userId);
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $contactId = trim((string)($input['id'] ?? ''));
    $existing = [];

    if ($contactId !== '' && isset($payload['contacts'][$contactId]) && is_array($payload['contacts'][$contactId])) {
        $existing = $payload['contacts'][$contactId];
    }

    if ($existing === []) {
        $matched = find_matching_contact_record($payload, $input);
        if (is_array($matched)) {
            $existing = $matched;
        }
    }

    $contact = contacts_hydrate_record($input, $existing);

    if ($contact['display_name'] === '') {
        throw new RuntimeException('Display name is required');
    }

    $payload['contacts'][$contact['id']] = $contact;
    save_user_contacts_by_id($userId, $payload);

    echo json_encode([
        'ok' => true,
        'contact' => $contact,
        'contacts' => contacts_records_for_response($payload)
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
