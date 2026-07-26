<?php
/**
 * Export bookings to CSV (opens directly in Excel / Google Sheets).
 * Honours the same filters as bookings.php.
 */
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();

$status  = (string)($_GET['status']  ?? '');
$service = (string)($_GET['service'] ?? '');
$range   = (string)($_GET['range']   ?? '');
$q       = trim((string)($_GET['q']  ?? ''));

$where  = [];
$params = [];

if (in_array($status, ['pending','confirmed','assigned','completed','cancelled'], true)) {
    $where[] = '`status` = ?';   $params[] = $status;
}
if (in_array($service, ['airport','city','city_to_city','hourly','rental'], true)) {
    $where[] = '`service_type` = ?'; $params[] = $service;
}
if ($range === 'upcoming')      { $where[] = '`pickup_at` >= NOW()'; }
elseif ($range === 'past')      { $where[] = '`pickup_at` < NOW()'; }
elseif ($range === 'today')     { $where[] = 'DATE(`pickup_at`) = CURDATE()'; }

if ($q !== '') {
    $where[] = '(`reference` LIKE ? OR `full_name` LIKE ? OR `email` LIKE ?
                 OR `phone` LIKE ? OR `pickup_address` LIKE ? OR `dropoff_address` LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$rows = db_all("SELECT * FROM `bookings` $where_sql ORDER BY `pickup_at` DESC, `id` DESC", $params);

app_log('admin.log', $admin['email'] . ' exported ' . count($rows) . ' bookings');

$filename = 'prime-luxury-rides-bookings-' . date('Y-m-d-Hi') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM so Excel reads accented characters correctly.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Reference', 'Received', 'Status', 'Payment',
    'Service', 'Vehicle',
    'Customer', 'Email', 'Phone',
    'Pickup address', 'Drop-off address',
    'Pickup date/time', 'Return date/time',
    'Hours', 'Distance (km)',
    'Passengers', 'Luggage', 'Flight',
    'Pricing method', 'Subtotal', 'Membership', 'Discount', 'HST', 'Total (CAD)',
    'Assigned chauffeur', 'Customer requests', 'Internal notes',
]);

foreach ($rows as $b) {
    fputcsv($out, [
        $b['reference'],
        $b['created_at'],
        ucfirst((string)$b['status']),
        ucfirst(str_replace('_', ' ', (string)$b['payment_status'])),
        service_label((string)$b['service_type']),
        (string)$b['vehicle_name'],
        (string)$b['full_name'],
        (string)$b['email'],
        (string)$b['phone'],
        (string)$b['pickup_address'],
        (string)$b['dropoff_address'],
        (string)$b['pickup_at'],
        (string)$b['return_at'],
        $b['hours'] !== null ? (int)$b['hours'] : '',
        $b['distance_km'] !== null ? number_format((float)$b['distance_km'], 1, '.', '') : '',
        (int)$b['passengers'],
        (int)$b['luggage'],
        (string)$b['flight_number'],
        (string)$b['pricing_method'],
        number_format((float)$b['subtotal'], 2, '.', ''),
        ucfirst((string)$b['membership_tier']),
        number_format((float)$b['discount'], 2, '.', ''),
        number_format((float)$b['hst'], 2, '.', ''),
        number_format((float)$b['total'], 2, '.', ''),
        (string)$b['assigned_driver'],
        (string)$b['notes'],
        (string)$b['admin_notes'],
    ]);
}

fclose($out);
exit;
