<?php
require_once __DIR__ . '/_auth.php';

$user = api_require_user();
$roleName = strtolower(trim((string)($user['role_name'] ?? '')));
$roleId = (int)($user['role_id'] ?? 0);
$isAdmin = $roleId === 1 || in_array($roleName, ['admin', 'administrator', 'quan tri vien', 'quan tri'], true);

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    api_json(false, ['message' => 'Missing order_id.'], 400);
}

function geocodeAddressCached(string $address): ?array
{
    $address = trim($address);
    if ($address === '') {
        return null;
    }

    $googleConfigPath = __DIR__ . '/../config/google.php';
    $googleConfig = file_exists($googleConfigPath) ? require $googleConfigPath : [];
    $googleMapsKey = trim((string)($googleConfig['maps_api_key'] ?? ''));

    $cacheDir = __DIR__ . '/../cache';
    $cacheFile = $cacheDir . '/geocode.json';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }

    if (function_exists('mb_strtolower')) {
        $norm = mb_strtolower($address, 'UTF-8');
    } else {
        $norm = strtolower($address);
    }
    // Use a versioned key so we can improve geocode strategy without being stuck on stale cache.
    $key = md5('v2|' . $norm);
    $cache = [];
    if (is_file($cacheFile)) {
        $raw = @file_get_contents($cacheFile);
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded)) {
            $cache = $decoded;
        }
    }

    if (isset($cache[$key]) && is_array($cache[$key])) {
        $hit = $cache[$key];
        if (isset($hit['lat'], $hit['lng']) && is_numeric($hit['lat']) && is_numeric($hit['lng'])) {
            return ['lat' => (float)$hit['lat'], 'lng' => (float)$hit['lng']];
        }
    }

    $hints = extractAddressHints($address);
    $candidates = [$address];
    if (!preg_match('/viet\s*nam|vietnam/i', $address)) {
        $candidates[] = $address . ', Việt Nam';
    }
    if (!empty($hints['district']) && !empty($hints['city'])) {
        $candidates[] = trim($hints['district'] . ', ' . $hints['city'] . ', Việt Nam');
    }
    if (!empty($hints['city'])) {
        $candidates[] = trim($hints['city'] . ', Việt Nam');
    }

    $seen = [];
    $uniqueCandidates = [];
    foreach ($candidates as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate === '') {
            continue;
        }
        $normCandidate = function_exists('mb_strtolower') ? mb_strtolower($candidate, 'UTF-8') : strtolower($candidate);
        if (isset($seen[$normCandidate])) {
            continue;
        }
        $seen[$normCandidate] = true;
        $uniqueCandidates[] = $candidate;
    }

    $resolveWithGoogle = static function (string $query, string $apiKey): ?array {
        $gUrl = 'https://maps.googleapis.com/maps/api/geocode/json?region=vn&language=vi&address=' . rawurlencode($query) . '&key=' . rawurlencode($apiKey);
        $gResp = null;

        if (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 4,
                    'header' => "Accept: application/json\r\n",
                ],
            ]);
            $tmp = @file_get_contents($gUrl, false, $ctx);
            if (is_string($tmp) && $tmp !== '') {
                $gResp = $tmp;
            }
        }

        if ($gResp === null && function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $gUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $tmp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code >= 200 && $code < 300 && is_string($tmp) && $tmp !== '') {
                $gResp = $tmp;
            }
        }

        if (!$gResp) {
            return null;
        }

        $gJson = json_decode($gResp, true);
        $first = is_array($gJson) && !empty($gJson['results'][0]) ? $gJson['results'][0] : null;
        $loc = $first['geometry']['location'] ?? null;
        if (!is_array($loc) || !isset($loc['lat'], $loc['lng']) || !is_numeric($loc['lat']) || !is_numeric($loc['lng'])) {
            return null;
        }

        return ['lat' => (float)$loc['lat'], 'lng' => (float)$loc['lng']];
    };

    $resolveWithNominatim = static function (string $query): ?array {
        $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=vn&q=' . rawurlencode($query);
        $resp = null;

        if (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 4,
                    'header' => "User-Agent: GW_VN/1.0 (server geocode)\r\nAccept: application/json\r\n",
                ],
            ]);
            $tmp = @file_get_contents($url, false, $ctx);
            if (is_string($tmp) && $tmp !== '') {
                $resp = $tmp;
            }
        }

        if ($resp === null && function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'User-Agent: GW_VN/1.0 (server geocode)'
                ],
            ]);
            $tmp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code >= 200 && $code < 300 && is_string($tmp) && $tmp !== '') {
                $resp = $tmp;
            }
        }

        if (!$resp) {
            return null;
        }
        $json = json_decode($resp, true);
        if (!is_array($json) || empty($json[0]['lat']) || empty($json[0]['lon'])) {
            return null;
        }

        return ['lat' => (float)$json[0]['lat'], 'lng' => (float)$json[0]['lon']];
    };

    foreach ($uniqueCandidates as $candidate) {
        if ($googleMapsKey !== '') {
            $googleResult = $resolveWithGoogle($candidate, $googleMapsKey);
            if ($googleResult) {
                $cache[$key] = $googleResult + ['address' => $candidate, 'ts' => time(), 'source' => 'google'];
                @file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE));
                return $googleResult;
            }
        }

        $nominatimResult = $resolveWithNominatim($candidate);
        if ($nominatimResult) {
            $cache[$key] = $nominatimResult + ['address' => $candidate, 'ts' => time(), 'source' => 'nominatim'];
            @file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE));
            return $nominatimResult;
        }
    }

    return null;
}

function fallbackCoordsForAddress(string $address): array
{
    $a = strtolower($address);
    $fallbacks = [
        'da nang' => ['lat' => 16.047079, 'lng' => 108.206230],
        'ha noi' => ['lat' => 21.028511, 'lng' => 105.804817],
        'ho chi minh' => ['lat' => 10.776889, 'lng' => 106.700806],
        'tp hcm' => ['lat' => 10.776889, 'lng' => 106.700806],
        'hcm' => ['lat' => 10.776889, 'lng' => 106.700806],
    ];

    foreach ($fallbacks as $needle => $coords) {
        if (str_contains($a, $needle)) {
            return $coords;
        }
    }

    return ['lat' => 16.0, 'lng' => 107.5];
}

function interpolatePoint(array $start, array $end, float $ratio, float $offsetScale = 0.0): array
{
    $ratio = max(0.0, min(1.0, $ratio));
    $lat = $start['lat'] + (($end['lat'] - $start['lat']) * $ratio);
    $lng = $start['lng'] + (($end['lng'] - $start['lng']) * $ratio);

    if ($offsetScale != 0.0) {
        $dx = $end['lng'] - $start['lng'];
        $dy = $end['lat'] - $start['lat'];
        $magnitude = sqrt(($dx * $dx) + ($dy * $dy));
        if ($magnitude > 0) {
            $lat += ($dx / $magnitude) * $offsetScale;
            $lng += (-$dy / $magnitude) * $offsetScale;
        }
    }

    return [
        'lat' => round($lat, 7),
        'lng' => round($lng, 7),
    ];
}

function resolveTrackingStatus(array $order): string
{
    $lastMileStatus = strtolower(trim((string)($order['shipping_last_mile_status'] ?? '')));
    if ($lastMileStatus !== '') {
        return $lastMileStatus;
    }

    return match (strtolower(trim((string)($order['status'] ?? 'pending')))) {
        'confirmed', 'processing' => 'waiting_pickup',
        'shipping' => 'in_transit',
        'delivered' => 'delivered',
        'cancelled' => 'cancelled',
        default => 'created',
    };
}

function normalizeVietnameseText(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        $text = strtolower($text);
    }

    $map = [
        'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
        'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
        'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
        'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
        'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
        'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
        'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
        'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
        'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
        'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
        'đ' => 'd',
    ];
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/i', ' ', $text) ?? $text;
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;

    return trim($text);
}

function warehouseWithMeta(array $warehouse): array
{
    $warehouse['city_normalized'] = normalizeVietnameseText((string)($warehouse['city'] ?? ''));
    $warehouse['district_normalized'] = normalizeVietnameseText((string)($warehouse['district'] ?? ''));
    $warehouse['address_normalized'] = normalizeVietnameseText((string)($warehouse['address'] ?? ''));
    return $warehouse;
}

function extractAddressHints(string $address): array
{
    $segments = array_values(array_filter(array_map('trim', explode(',', $address))));
    $district = '';
    $city = '';

    foreach ($segments as $segment) {
        $norm = normalizeVietnameseText($segment);
        if ($district === '' && (
            str_contains($norm, 'quan ') ||
            str_contains($norm, 'huyen ') ||
            str_contains($norm, 'thi xa') ||
            str_contains($norm, 'thi tran') ||
            str_contains($norm, 'thanh pho thu duc') ||
            str_contains($norm, 'tp thu duc')
        )) {
            $district = $segment;
        }
    }

    $cityKeywords = ['ha noi', 'da nang', 'ho chi minh', 'tp ho chi minh', 'can tho', 'hai phong', 'hue'];
    foreach (array_reverse($segments) as $segment) {
        $norm = normalizeVietnameseText($segment);
        if ($norm === '' || $norm === 'viet nam') {
            continue;
        }
        foreach ($cityKeywords as $kw) {
            if (str_contains($norm, $kw)) {
                $city = $segment;
                break 2;
            }
        }
        if ($city === '') {
            $city = $segment;
        }
    }

    return [
        'district' => $district,
        'city' => $city,
    ];
}

function slugifyWarehouseCode(string $text): string
{
    $value = normalizeVietnameseText($text);
    $value = str_replace(' ', '_', $value);
    $value = preg_replace('/_+/', '_', $value) ?? $value;
    $value = trim($value, '_');
    return $value !== '' ? $value : 'unknown';
}

function getDistrictWarehouseCacheFile(): string
{
    return __DIR__ . '/../cache/district_warehouses.json';
}

function readDistrictWarehouseCache(): array
{
    $file = getDistrictWarehouseCacheFile();
    if (!is_file($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : [];
}

function writeDistrictWarehouseCache(array $cache): void
{
    $file = getDistrictWarehouseCacheFile();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($file, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function getOrCreateDistrictWarehouse(string $address, array $destination, array $mainWarehouse): ?array
{
    $hints = extractAddressHints($address);
    $districtLabel = trim((string)($hints['district'] ?? ''));
    $cityLabel = trim((string)($hints['city'] ?? ''));

    if ($districtLabel === '' && $cityLabel === '') {
        return null;
    }

    $districtNorm = normalizeVietnameseText($districtLabel);
    $cityNorm = normalizeVietnameseText($cityLabel);
    $cacheKey = ($cityNorm !== '' ? $cityNorm : 'unknown_city') . '|' . ($districtNorm !== '' ? $districtNorm : 'unknown_district');

    $cache = readDistrictWarehouseCache();
    if (isset($cache[$cacheKey]) && is_array($cache[$cacheKey])) {
        return warehouseWithMeta($cache[$cacheKey]);
    }

    $lookupAddress = trim(implode(', ', array_filter([$districtLabel, $cityLabel, 'Việt Nam'])));
    $geo = $lookupAddress !== '' ? geocodeAddressCached($lookupAddress) : null;
    if (!$geo) {
        $source = [
            'lat' => (float)($mainWarehouse['lat'] ?? 0),
            'lng' => (float)($mainWarehouse['lng'] ?? 0),
        ];
        $target = [
            'lat' => (float)($destination['lat'] ?? 0),
            'lng' => (float)($destination['lng'] ?? 0),
        ];
        $geo = interpolatePoint($source, $target, 0.9, -0.012);
    }

    $name = $districtLabel !== ''
        ? ('Kho trung chuyển ' . $districtLabel)
        : ('Kho trung chuyển ' . ($cityLabel !== '' ? $cityLabel : 'khu vực đích'));
    $resolvedAddress = $lookupAddress !== '' ? $lookupAddress : ($address !== '' ? $address : $name);

    $warehouse = [
        'code' => 'district_' . slugifyWarehouseCode($cityNorm . '_' . $districtNorm),
        'name' => $name,
        'address' => $resolvedAddress,
        'city' => $cityLabel,
        'district' => $districtLabel,
        'lat' => (float)($geo['lat'] ?? 0),
        'lng' => (float)($geo['lng'] ?? 0),
        'auto_generated' => true,
    ];

    $cache[$cacheKey] = $warehouse;
    writeDistrictWarehouseCache($cache);

    return warehouseWithMeta($warehouse);
}

function findDestinationWarehouse(string $address, array $warehouses): ?array
{
    $normalizedAddress = normalizeVietnameseText($address);
    if ($normalizedAddress === '') {
        return null;
    }

    $bestWarehouse = null;
    $bestScore = 0;
    foreach ($warehouses as $warehouse) {
        if (!is_array($warehouse)) {
            continue;
        }

        $candidate = warehouseWithMeta($warehouse);
        $score = 0;
        if ($candidate['district_normalized'] !== '' && str_contains($normalizedAddress, $candidate['district_normalized'])) {
            $score += 120;
        }
        if ($candidate['city_normalized'] !== '' && str_contains($normalizedAddress, $candidate['city_normalized'])) {
            $score += 60;
        }
        if ($candidate['address_normalized'] !== '' && str_contains($normalizedAddress, $candidate['address_normalized'])) {
            $score += 30;
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestWarehouse = $candidate;
        }
    }

    return $bestScore > 0 ? $bestWarehouse : null;
}

function buildFallbackLastMileWarehouse(array $mainWarehouse, array $destination, string $address): array
{
    $source = [
        'lat' => (float)$mainWarehouse['lat'],
        'lng' => (float)$mainWarehouse['lng'],
    ];
    $target = [
        'lat' => (float)$destination['lat'],
        'lng' => (float)$destination['lng'],
    ];
    $point = interpolatePoint($source, $target, 0.9, -0.012);

    $hints = extractAddressHints($address);
    $districtLabel = trim((string)$hints['district']);
    $cityLabel = trim((string)$hints['city']);
    $name = $districtLabel !== ''
        ? ('Kho trung chuyển ' . $districtLabel)
        : 'Kho giao khu vực đích';

    return warehouseWithMeta([
        'code' => 'dynamic_last_mile',
        'name' => $name,
        'address' => $address !== '' ? $address : 'Kho giao khu vực gần khách hàng',
        'city' => $cityLabel,
        'district' => $districtLabel,
        'lat' => $point['lat'],
        'lng' => $point['lng'],
    ]);
}

function buildRelayPath(array $mainWarehouse, array $destination, string $destinationAddress, array $logisticsConfig): array
{
    $mainWarehouse = warehouseWithMeta($mainWarehouse);
    $destinationWarehouse = getOrCreateDistrictWarehouse($destinationAddress, $destination, $mainWarehouse);
    if (!$destinationWarehouse) {
        $destinationWarehouse = findDestinationWarehouse($destinationAddress, $logisticsConfig['destination_warehouses'] ?? []);
        if (!$destinationWarehouse) {
            $destinationWarehouse = buildFallbackLastMileWarehouse($mainWarehouse, $destination, $destinationAddress);
        }
    }

    $path = [];
    $mainLat = (float)$mainWarehouse['lat'];
    $destLat = (float)$destinationWarehouse['lat'];
    $sameCity = $destinationWarehouse['city_normalized'] !== ''
        && $destinationWarehouse['city_normalized'] === $mainWarehouse['city_normalized'];

    if (!$sameCity) {
        $directionKey = $destLat >= $mainLat ? 'northbound' : 'southbound';
        $relayHubs = $logisticsConfig['relay_network'][$directionKey] ?? [];
        foreach ($relayHubs as $hub) {
            if (!is_array($hub)) {
                continue;
            }
            $hub = warehouseWithMeta($hub);
            $hubLat = (float)($hub['lat'] ?? 0);

            if ($directionKey === 'northbound') {
                if ($hubLat > $mainLat && $hubLat < ($destLat + 0.2)) {
                    $path[] = $hub;
                }
            } else {
                if ($hubLat < $mainLat && $hubLat > ($destLat - 0.2)) {
                    $path[] = $hub;
                }
            }
        }
    }

    $lastPath = !empty($path) ? $path[count($path) - 1] : null;
    if (!$lastPath || (($lastPath['code'] ?? '') !== ($destinationWarehouse['code'] ?? ''))) {
        $path[] = $destinationWarehouse;
    }

    return [
        'destination_warehouse' => $destinationWarehouse,
        'path' => $path,
    ];
}

function buildTransitHubEvents(array $warehouse, array $destination, string $destinationAddress, array $logisticsConfig): array
{
    $route = buildRelayPath($warehouse, $destination, $destinationAddress, $logisticsConfig);
    $path = $route['path'];

    $hubs = [];
    foreach ($path as $index => $hub) {
        $isLast = $index === count($path) - 1;
        $hubs[] = [
            'status_code' => $isLast ? 'last_mile_hub' : 'transit_hub_' . ($index + 1),
            'title' => trim((string)($hub['name'] ?? ($isLast ? 'Kho giao khu vực' : 'Kho trung chuyển'))),
            'note' => $isLast
                ? 'Đơn hàng đã đến kho thuộc quận hoặc thành phố đích và sẵn sàng giao cho khách.'
                : 'Xe tải đang trung chuyển đơn hàng giữa các kho trên tuyến đi.',
            'location_address' => trim((string)($hub['address'] ?? 'Kho trung chuyển')),
            'lat' => (float)($hub['lat'] ?? 0),
            'lng' => (float)($hub['lng'] ?? 0),
            'city' => (string)($hub['city'] ?? ''),
            'district' => (string)($hub['district'] ?? ''),
            'warehouse_code' => (string)($hub['code'] ?? ''),
        ];
    }

    return $hubs;
}

function deriveTrackingContext(array $order, array $hubEvents, array $logisticsConfig): array
{
    $explicitStatus = strtolower(trim((string)($order['shipping_last_mile_status'] ?? '')));
    if ($explicitStatus !== '' && !in_array($explicitStatus, ['in_transit', 'picked_up'], true)) {
        return [
            'status' => $explicitStatus,
            'reached_hubs' => count($hubEvents),
        ];
    }

    $baseStatus = $explicitStatus !== '' ? 'in_transit' : resolveTrackingStatus($order);
    if ($baseStatus !== 'in_transit' || empty($hubEvents)) {
        return [
            'status' => $baseStatus,
            'reached_hubs' => 0,
        ];
    }

    $secondsPerHub = (int)($logisticsConfig['simulation']['transit_seconds_per_hub'] ?? 180);
    $secondsPerHub = max(60, $secondsPerHub);

    $startAt = strtotime((string)($order['created_at'] ?? ''));
    if ($startAt === false || $startAt <= 0) {
        $startAt = time();
    }

    $elapsed = max(0, time() - $startAt);
    $reachedHubs = (int)floor($elapsed / $secondsPerHub);
    $hubCount = count($hubEvents);

    if ($reachedHubs >= $hubCount) {
        return [
            'status' => 'out_for_delivery',
            'reached_hubs' => $hubCount,
        ];
    }

    return [
        'status' => 'in_transit',
        'reached_hubs' => max(0, $reachedHubs),
    ];
}

function buildSyntheticTrackingEvents(
    array $order,
    array $warehouse,
    array $destination,
    array $hubEvents,
    string $trackingStatus,
    int $reachedHubCount
): array
{
    $createdAt = (string)($order['created_at'] ?? date('Y-m-d H:i:s'));

    $warehouseEvent = [
        'type' => 'warehouse',
        'status_code' => 'warehouse',
        'title' => trim((string)($warehouse['name'] ?? 'Kho hàng')),
        'note' => 'Đơn hàng đang được chuẩn bị tại kho chính.',
        'location_address' => trim((string)($warehouse['address'] ?? 'Kho hàng')),
        'lat' => (float)$warehouse['lat'],
        'lng' => (float)$warehouse['lng'],
        'place_id' => $warehouse['place_id'] ?? null,
        'occurred_at' => $createdAt,
    ];

    $destinationEvent = [
        'type' => 'destination',
        'status_code' => 'destination',
        'title' => 'Địa chỉ nhận hàng',
        'note' => 'Giao tới địa chỉ của khách hàng.',
        'location_address' => trim((string)($destination['address'] ?? 'Địa chỉ giao hàng')),
        'lat' => (float)$destination['lat'],
        'lng' => (float)$destination['lng'],
        'place_id' => $destination['place_id'] ?? null,
        'occurred_at' => date('Y-m-d H:i:s'),
    ];

    $events = [$warehouseEvent];
    foreach ($hubEvents as $index => $hub) {
        $events[] = [
            'type' => $hub['status_code'] === 'last_mile_hub' ? 'last_mile_hub' : 'transit_hub',
            'status_code' => $hub['status_code'],
            'title' => $hub['title'],
            'note' => $hub['note'],
            'location_address' => $hub['location_address'],
            'lat' => $hub['lat'],
            'lng' => $hub['lng'],
            'occurred_at' => date('Y-m-d H:i:s', strtotime($createdAt . ' +' . (($index + 1) * 2) . ' hours')),
        ];
    }
    $events[] = $destinationEvent;

    $lastHubEvent = !empty($hubEvents)
        ? $events[count($events) - 2]
        : $warehouseEvent;

    $hubOnlyEvents = array_slice($events, 1, count($hubEvents));

    return match ($trackingStatus) {
        'out_for_delivery', 'delivered' => [$lastHubEvent, $destinationEvent],
        'in_transit' => array_merge(
            [$warehouseEvent],
            array_slice($hubOnlyEvents, 0, min(count($hubOnlyEvents), max(1, $reachedHubCount + 1)))
        ),
        'picked_up' => !empty($hubEvents)
            ? [$warehouseEvent, $events[1]]
            : [$warehouseEvent, $destinationEvent],
        'waiting_pickup', 'created' => [$warehouseEvent],
        default => [$warehouseEvent, $destinationEvent],
    };
}

try {
    $orderParams = [$orderId];
    $hasShippingGeo = false;
    try {
        $hasShippingGeo = !empty(Database::fetchAll("SHOW COLUMNS FROM orders LIKE 'shipping_lat'"));
    } catch (Exception $e) {
        $hasShippingGeo = false;
    }

    $orderSql = $hasShippingGeo
        ? "SELECT order_id, user_id, status, shipping_last_mile_status, created_at, shipping_address, shipping_lat, shipping_lng, shipping_place_id FROM orders WHERE order_id = ?"
        : "SELECT order_id, user_id, status, shipping_last_mile_status, created_at, shipping_address FROM orders WHERE order_id = ?";
    if (!$isAdmin) {
        $orderSql .= " AND user_id = ?";
        $orderParams[] = (int)$user['user_id'];
    }

    $order = Database::fetch($orderSql, $orderParams);
    if (!$order) {
        api_json(false, ['message' => 'Order not found.'], 404);
    }

    $logisticsConfigPath = __DIR__ . '/../config/logistics.php';
    $logisticsConfig = file_exists($logisticsConfigPath) ? require $logisticsConfigPath : [];
    $warehouse = $logisticsConfig['warehouse'] ?? [
        'name' => 'Warehouse',
        'address' => '328 Ngo Quyen, Man Thai, Son Tra, Da Nang, Vietnam',
        'lat' => null,
        'lng' => null,
    ];

    $warehouseLat = $warehouse['lat'] ?? null;
    $warehouseLng = $warehouse['lng'] ?? null;
    $warehousePlaceId = trim((string)($warehouse['place_id'] ?? ''));
    if (($warehouseLat === null || $warehouseLng === null) && !empty($warehouse['address'])) {
        $geo = geocodeAddressCached((string)$warehouse['address']);
        if ($geo) {
            $warehouseLat = $geo['lat'];
            $warehouseLng = $geo['lng'];
        }
    }

    $destinationAddress = trim((string)($order['shipping_address'] ?? ''));
    $events = [];
    $hubEvents = [];
    $trackingStatus = resolveTrackingStatus($order);
    $reachedHubCount = 0;
    $firstEvent = null;
    $lastEvent = null;
    if ($destinationAddress !== '') {
        $destLat = null;
        $destLng = null;
        $storedLat = $hasShippingGeo ? ($order['shipping_lat'] ?? null) : null;
        $storedLng = $hasShippingGeo ? ($order['shipping_lng'] ?? null) : null;
        if ($storedLat !== null && $storedLng !== null && is_numeric($storedLat) && is_numeric($storedLng)) {
            $destLat = (float)$storedLat;
            $destLng = (float)$storedLng;
        } else {
            $geo = geocodeAddressCached($destinationAddress);
            if ($geo) {
                $destLat = $geo['lat'];
                $destLng = $geo['lng'];
            } else {
                $fallback = fallbackCoordsForAddress($destinationAddress);
                $destLat = $fallback['lat'];
                $destLng = $fallback['lng'];
            }
        }
        $warehouseData = [
            'name' => $warehouse['name'] ?? 'Warehouse',
            'address' => $warehouse['address'] ?? 'Warehouse',
            'city' => $warehouse['city'] ?? '',
            'district' => $warehouse['district'] ?? '',
            'lat' => (float)$warehouseLat,
            'lng' => (float)$warehouseLng,
            'place_id' => $warehousePlaceId !== '' ? $warehousePlaceId : null,
        ];
        $destinationData = [
            'address' => $destinationAddress,
            'lat' => (float)$destLat,
            'lng' => (float)$destLng,
            'place_id' => $hasShippingGeo ? (($order['shipping_place_id'] ?? '') !== '' ? (string)$order['shipping_place_id'] : null) : null,
        ];

        $hubEvents = buildTransitHubEvents($warehouseData, $destinationData, $destinationAddress, $logisticsConfig);
        $trackingContext = deriveTrackingContext($order, $hubEvents, $logisticsConfig);
        $trackingStatus = (string)$trackingContext['status'];
        $reachedHubCount = (int)$trackingContext['reached_hubs'];
        $syntheticEvents = buildSyntheticTrackingEvents(
            $order,
            $warehouseData,
            $destinationData,
            $hubEvents,
            $trackingStatus,
            $reachedHubCount
        );

        usort($syntheticEvents, static function (array $left, array $right): int {
            return strcmp((string)($left['occurred_at'] ?? ''), (string)($right['occurred_at'] ?? ''));
        });

        $events = $syntheticEvents;

        $firstEvent = $events[0] ?? null;
        $lastEvent = !empty($events) ? $events[count($events) - 1] : null;
    }

    api_json(true, [
        'order_id' => (int)$order['order_id'],
        'destination_address' => $destinationAddress,
        'warehouse' => $warehouse,
        'tracking_status' => $trackingStatus ?? resolveTrackingStatus($order),
        'map_origin_label' => (string)(($firstEvent['title'] ?? $warehouse['name'] ?? 'Kho hàng')),
        'map_origin_address' => (string)(($firstEvent['location_address'] ?? $warehouse['address'] ?? 'Kho hàng')),
        'map_destination_label' => (string)(($lastEvent['title'] ?? 'Địa chỉ nhận hàng')),
        'map_destination_address' => (string)(($lastEvent['location_address'] ?? $destinationAddress)),
        'selected_last_mile_hub' => !empty($hubEvents) ? $hubEvents[count($hubEvents) - 1] : null,
        'reached_hubs' => $reachedHubCount ?? 0,
        'events' => $events,
    ]);
} catch (Exception $e) {
    error_log('get-order-tracking-events error: ' . $e->getMessage());
    api_json(false, ['message' => 'Unable to fetch tracking events.'], 500);
}
?>
