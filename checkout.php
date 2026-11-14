<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];

$amount      = isset($body['amount'])      ? floatval($body['amount'])                 : null;
$phone       = isset($body['phone'])       ? preg_replace('/\s+/', '', $body['phone']) : '';
$email       = isset($body['email'])       ? trim($body['email'])                      : '';
$name        = isset($body['name'])        ? trim($body['name'])                       : '';
$returnUrl   = isset($body['return_url'])  ? trim($body['return_url'])                 : '';
$monthly     = !empty($body['monthly']) && $body['monthly'] !== 'false';
$currency    = isset($body['currency'])    ? strtolower(trim($body['currency']))       : 'usd';
$description = isset($body['description']) ? trim($body['description'])               : 'Donation';

if (!$amount || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid amount.']);
    exit;
}

$cleanAmount = round($amount, 2);
$unitAmount  = (int)round($cleanAmount * 100);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'donate.kylerescuer.org';
$origin = $scheme . '://' . $host;

$defaultSuccessUrl = $origin . '/thank-you/?session_id={CHECKOUT_SESSION_ID}&amount=' . $cleanAmount;
$cancelUrl         = $origin . '/';

$params = [
    'mode' => $monthly ? 'subscription' : 'payment',
    'line_items' => [
        [
            'price_data' => [
                'currency'     => $currency ?: 'usd',
                'product_data' => [
                    'name' => $description ?: 'Donation',
                ],
                'unit_amount'  => $unitAmount,
            ],
            'quantity' => 1,
        ],
    ],
    'success_url' => $returnUrl ?: $defaultSuccessUrl,
    'cancel_url'  => $cancelUrl,
    'metadata'    => [
        'source' => 'kylerescuer',
    ],
];

if (!$monthly) {
    $params['submit_type'] = 'donate';
} else {
    $params['line_items'][0]['price_data']['recurring'] = ['interval' => 'month'];
}

if ($email && strpos($email, '@') !== false) {
    $params['customer_email'] = $email;
}
if ($name) {
    $params['metadata']['donor_name'] = mb_substr($name, 0, 100);
}
if ($phone) {
    $params['metadata']['phone'] = mb_substr($phone, 0, 50);
}

$postFields = http_build_query($params);

$ch = curl_init(STRIPE_API_BASE . '/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postFields,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . STRIPE_SECRET_KEY,
        'Content-Type: application/x-www-form-urlencoded',
    ],
    CURLOPT_TIMEOUT        => 20,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode >= 200 && $httpCode < 300 && isset($data['url'])) {
    echo json_encode([
        'success'     => true,
        'sessionId'   => $data['id'] ?? null,
        'redirectUrl' => $data['url'],
        'url'         => $data['url'],
    ]);
} else {
    $msg = $data['error']['message'] ?? 'Error processing payment.';
    echo json_encode([
        'success' => false,
        'message' => $msg,
    ]);
}
