<?php
/**
 * POST /api/quote.php  — live price quote for the booking wizard.
 * Returns JSON. Never throws to the client.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/pricing.php';
require_once __DIR__ . '/../includes/customer.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$raw   = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

if (!csrf_check($input['csrf'] ?? null)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'error' => 'Your session expired. Please refresh the page.']);
    exit;
}

$cust      = customer();
$cust_tier = $cust ? (string)$cust['membership_tier'] : 'none';

try {
    $quote = calculate_quote([
        'service_type' => (string)($input['service_type'] ?? ''),
        'vehicle_id'   => (int)($input['vehicle_id'] ?? 0),
        'distance_km'  => (float)($input['distance_km'] ?? 0),
        'duration_min' => (float)($input['duration_min'] ?? 0),
        'hours'        => (int)($input['hours'] ?? 0),
        'days'         => (int)($input['days'] ?? 0),
        'pickup'       => (string)($input['pickup'] ?? ''),
        'dropoff'      => (string)($input['dropoff'] ?? ''),
        'is_return'    => !empty($input['is_return']),
        'stops'        => (int)($input['stops'] ?? 0),
        // Membership comes from the signed-in account, never the request
        // body — a crafted POST must not be able to claim a discount.
        'membership'   => $cust_tier,
    ]);
} catch (Throwable $ex) {
    app_log('errors.log', 'quote.php: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'We could not calculate a price. Please call us to book.']);
    exit;
}

if (!$quote['ok']) {
    echo json_encode(['ok' => false, 'error' => $quote['error']]);
    exit;
}

// Format for direct display — the client does no money maths.
echo json_encode([
    'ok'     => true,
    'method' => $quote['method'],
    'lines'  => array_map(fn($l) => [
        'label'  => $l['label'],
        'amount' => money($l['amount']),
    ], $quote['lines']),
    'subtotal'         => money($quote['subtotal']),
    'discount'         => money($quote['discount']),
    'discount_percent' => $quote['discount_percent'],
    'has_discount'     => $quote['discount'] > 0,
    'membership'       => $quote['membership'],
    'hst'              => money($quote['hst']),
    'hst_rate'         => $quote['hst_rate'],
    'total'            => money($quote['total']),
    'total_raw'        => $quote['total'],
    'notes'            => $quote['notes'],
    'hours'            => $quote['hours'] ?? null,
    'days'             => $quote['days'] ?? null,
    'stops'            => $quote['stops'] ?? 0,
    'is_return'        => $quote['is_return'] ?? false,
], JSON_UNESCAPED_UNICODE);
