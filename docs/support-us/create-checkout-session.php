<?php
/**
 * create-checkout-session.php
 * Creates a Stripe Checkout Session in embedded mode and returns the client_secret.
 *
 * POST body (JSON):
 *   amount_cents  int     Required. Amount in euro cents (e.g. 500 = €5). Min 100, max 100000.
 *   frequency     string  Required. "monthly" or "once".
 *   email         string  Optional. Pre-fill customer email.
 *
 * Response (JSON):
 *   { "clientSecret": "cs_..." }  on success
 *   { "error": "..." }            on failure (HTTP 4xx/5xx)
 */

declare(strict_types=1);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../config/secrets.php';

// ---- Parse input --------------------------------------------------------

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

// Validate amount (cents)
$amount_cents = isset($input['amount_cents']) ? (int) $input['amount_cents'] : 0;
if ($amount_cents < 100 || $amount_cents > 100000) {
    http_response_code(400);
    echo json_encode(['error' => 'Amount must be between €1 and €1000']);
    exit;
}

// Validate frequency
$frequency = $input['frequency'] ?? '';
if (!in_array($frequency, ['monthly', 'once'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Frequency must be "monthly" or "once"']);
    exit;
}

// Validate email (optional — silently drop if malformed)
$email = isset($input['email']) ? trim((string) $input['email']) : '';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = '';
}

// ---- Stripe secret key --------------------------------------------------

$stripe_key = secret('STRIPE_SECRET_KEY');
if ($stripe_key === '') {
    http_response_code(503);
    echo json_encode(['error' => 'Payment provider not configured']);
    exit;
}

// ---- Build return URL ---------------------------------------------------

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$return_url = $scheme . '://' . $host . '/docs/support-us/?session_id={CHECKOUT_SESSION_ID}';

// ---- Build Stripe API request fields ------------------------------------

$is_subscription = ($frequency === 'monthly');
$mode = $is_subscription ? 'subscription' : 'payment';

$fields = [
    'ui_mode'                                                    => 'embedded',
    'mode'                                                       => $mode,
    'line_items[0][price_data][currency]'                        => 'eur',
    'line_items[0][price_data][unit_amount]'                     => (string) $amount_cents,
    'line_items[0][price_data][product_data][name]'              => 'support Tsjilp',
    'line_items[0][quantity]'                                    => '1',
    'return_url'                                                 => $return_url,
];

if ($is_subscription) {
    $fields['line_items[0][price_data][recurring][interval]'] = 'month';
}

if ($email !== '') {
    $fields['customer_email'] = $email;
}

// ---- Call Stripe API ----------------------------------------------------

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($fields),
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $stripe_key,
        'Content-Type: application/x-www-form-urlencoded',
        'Stripe-Version: 2024-04-10',
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_CONNECTTIMEOUT => 8,
]);

$response  = curl_exec($ch);
$http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($response === false || $curl_err !== '') {
    error_log('[stripe] cURL error: ' . $curl_err);
    http_response_code(502);
    echo json_encode(['error' => 'Could not reach payment provider']);
    exit;
}

$data = json_decode($response, true);

if ($http_code !== 200 || !is_array($data)) {
    $msg = $data['error']['message'] ?? ('Stripe returned HTTP ' . $http_code);
    error_log('[stripe] API error: ' . $msg);
    http_response_code(502);
    echo json_encode(['error' => $msg]);
    exit;
}

if (empty($data['client_secret'])) {
    error_log('[stripe] No client_secret in response');
    http_response_code(502);
    echo json_encode(['error' => 'Unexpected response from payment provider']);
    exit;
}

echo json_encode(['clientSecret' => $data['client_secret']]);
