<?php
require_once __DIR__ . '/_base.php';

$logisticsPath = __DIR__ . '/../../config/logistics.php';
$config = file_exists($logisticsPath) ? require $logisticsPath : [];
$warehouse = $config['warehouse'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $lat = $payload['lat'] ?? null;
    $lng = $payload['lng'] ?? null;
    $address = trim((string)($payload['address'] ?? ''));

    $lat = ($lat === '' || $lat === null) ? null : (float)$lat;
    $lng = ($lng === '' || $lng === null) ? null : (float)$lng;

    if ($lat === null || $lng === null) {
        api_json(false, ['message' => 'Missing lat/lng.'], 400);
    }
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        api_json(false, ['message' => 'Invalid coordinates.'], 400);
    }

    $config['warehouse'] = array_merge(
        [
            'name' => 'Kho hang Goodwill',
            'address' => '328 Ngo Quyen, Son Tra, Da Nang, Viet Nam',
            'lat' => null,
            'lng' => null,
        ],
        $warehouse,
        [
            'address' => $address !== '' ? $address : ($warehouse['address'] ?? '328 Ngo Quyen, Son Tra, Da Nang, Viet Nam'),
            'lat' => $lat,
            'lng' => $lng
        ]
    );

    $php = "<?php\nreturn " . var_export($config, true) . ";\n";
    if (@file_put_contents($logisticsPath, $php) === false) {
        api_json(false, ['message' => 'Failed to write config.'], 500);
    }

    api_json(true, ['message' => 'Saved', 'warehouse' => $config['warehouse']]);
}

$warehouseLat = isset($warehouse['lat']) && is_numeric($warehouse['lat']) ? (float)$warehouse['lat'] : 16.047079;
$warehouseLng = isset($warehouse['lng']) && is_numeric($warehouse['lng']) ? (float)$warehouse['lng'] : 108.206230;
$warehouseAddress = (string)($warehouse['address'] ?? '328 Ngo Quyen, Son Tra, Da Nang, Viet Nam');
$warehouseName = (string)($warehouse['name'] ?? 'Kho hang Goodwill');

api_json(true, [
    'warehouse' => [
        'name' => $warehouseName,
        'address' => $warehouseAddress,
        'lat' => $warehouseLat,
        'lng' => $warehouseLng
    ]
]);
?>
