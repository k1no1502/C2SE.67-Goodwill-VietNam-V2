<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$type = $_GET['type'] ?? '';
$type = is_string($type) ? $type : '';

$root = dirname(__DIR__);
$dataDir = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'data';

function readJsonFile(string $path): array
{
    if (!is_file($path)) {
        http_response_code(500);
        echo json_encode(['error' => 'Missing data file'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to read data file'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        http_response_code(500);
        echo json_encode(['error' => 'Invalid JSON data'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $decoded;
}

function resolveDataFile(string $dataDir, array $candidates): ?string
{
    foreach ($candidates as $fileName) {
        $path = $dataDir . DIRECTORY_SEPARATOR . $fileName;
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

function normalizeListFromMap(array $map): array
{
    $out = [];
    foreach ($map as $code => $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim((string)($item['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $out[] = [
            'code' => (string)($item['code'] ?? $code),
            'name' => $name,
            'name_with_type' => trim((string)($item['name_with_type'] ?? '')),
            'slug' => trim((string)($item['slug'] ?? '')),
            'type' => trim((string)($item['type'] ?? '')),
            'parent_code' => (string)($item['parent_code'] ?? ''),
        ];
    }
    return $out;
}

function cleanNamePrefix(string $name): string
{
    $n = trim($name);
    $patterns = [
        '/^thanh pho\s+/iu',
        '/^tinh\s+/iu',
        '/^quan\s+/iu',
        '/^huyen\s+/iu',
        '/^thi xa\s+/iu',
        '/^thi tran\s+/iu',
        '/^phuong\s+/iu',
        '/^xa\s+/iu',
    ];
    foreach ($patterns as $p) {
        $n = preg_replace($p, '', $n) ?? $n;
    }
    return trim($n);
}

function inferDistrictFromWardPath(array $ward): string
{
    $pathWithType = trim((string)($ward['path_with_type'] ?? ''));
    if ($pathWithType === '') {
        return '';
    }
    $parts = array_values(array_filter(array_map('trim', explode(',', $pathWithType))));
    if (count($parts) < 3) {
        return '';
    }
    // Common format: "Phường X, Quận Y, Thành phố Z".
    return $parts[count($parts) - 2] ?? '';
}

// Data source priority:
// 1) VietMap style files (province.json / ward.json) from vietnam_administrative_address.
// 2) Legacy hanhchinhvn style files (tinh_tp.json / quan_huyen.json / xa_phuong.json).
if ($type === 'provinces') {
    $provinceFile = resolveDataFile($dataDir, ['province.json', 'tinh_tp.json']);
    if ($provinceFile === null) {
        http_response_code(500);
        echo json_encode(['error' => 'Missing province data file'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $provinces = readJsonFile($provinceFile);
    $out = array_map(
        static fn($p) => ['code' => $p['code'], 'name' => $p['name']],
        normalizeListFromMap($provinces)
    );

    usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($type === 'districts') {
    $provinceCode = $_GET['province_code'] ?? '';
    $provinceCode = is_string($provinceCode) ? trim($provinceCode) : '';
    if ($provinceCode === '') {
        http_response_code(400);
        echo json_encode(['error' => 'province_code is required'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $districtFile = resolveDataFile($dataDir, ['quan_huyen.json']);

    // If dedicated district file is unavailable, infer district list from ward paths (VietMap style fallback).
    if ($districtFile === null) {
        $wardFile = resolveDataFile($dataDir, ['ward.json', 'xa_phuong.json']);
        if ($wardFile === null) {
            http_response_code(500);
            echo json_encode(['error' => 'Missing district/ward data file'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $wardsMap = readJsonFile($wardFile);
        $districtSet = [];
        foreach ($wardsMap as $wCode => $wRaw) {
            if (!is_array($wRaw)) {
                continue;
            }
            $pCode = (string)($wRaw['parent_code'] ?? '');
            if ($pCode !== $provinceCode) {
                continue;
            }
            $districtName = inferDistrictFromWardPath($wRaw);
            if ($districtName === '') {
                continue;
            }
            $districtName = cleanNamePrefix($districtName);
            if ($districtName === '') {
                continue;
            }
            $districtSet[$districtName] = true;
        }

        $out = [];
        $idx = 1;
        foreach (array_keys($districtSet) as $name) {
            $out[] = ['code' => $provinceCode . '-d' . $idx, 'name' => $name];
            $idx++;
        }
        usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $districts = readJsonFile($districtFile);
    $out = [];
    foreach ($districts as $code => $d) {
        if (($d['parent_code'] ?? '') !== $provinceCode) continue;
        $name = $d['name'] ?? '';
        if ($name === '') continue;
        $out[] = ['code' => (string)$code, 'name' => (string)$name];
    }
    usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($type === 'wards') {
    $districtCode = $_GET['district_code'] ?? '';
    $districtCode = is_string($districtCode) ? trim($districtCode) : '';
    if ($districtCode === '') {
        http_response_code(400);
        echo json_encode(['error' => 'district_code is required'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $wardFile = resolveDataFile($dataDir, ['xa_phuong.json', 'ward.json']);
    if ($wardFile === null) {
        http_response_code(500);
        echo json_encode(['error' => 'Missing ward data file'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $wards = readJsonFile($wardFile);

    // Legacy mode: district_code matches parent_code directly.
    $isLegacyDistrictCode = strpos($districtCode, '-d') === false;

    if (!$isLegacyDistrictCode) {
        // Inferred-district mode from VietMap ward data.
        [$provinceCode, ] = array_pad(explode('-d', $districtCode, 2), 2, '');
        $districtNames = [];
        foreach ($wards as $wCode => $wRaw) {
            if (!is_array($wRaw)) continue;
            if ((string)($wRaw['parent_code'] ?? '') !== $provinceCode) continue;
            $dn = inferDistrictFromWardPath($wRaw);
            if ($dn === '') continue;
            $dn = cleanNamePrefix($dn);
            if ($dn === '') continue;
            $districtNames[$dn] = true;
        }

        $sortedDistricts = array_keys($districtNames);
        usort($sortedDistricts, 'strcasecmp');
        $districtIndex = (int)substr($districtCode, strrpos($districtCode, '-d') + 2);
        $targetDistrict = $sortedDistricts[$districtIndex - 1] ?? '';

        $out = [];
        foreach ($wards as $code => $w) {
            if (!is_array($w)) continue;
            if ((string)($w['parent_code'] ?? '') !== $provinceCode) continue;
            $dn = cleanNamePrefix(inferDistrictFromWardPath($w));
            if ($targetDistrict !== '' && strcasecmp($dn, $targetDistrict) !== 0) continue;
            $name = trim((string)($w['name'] ?? ''));
            if ($name === '') continue;
            $out[] = ['code' => (string)($w['code'] ?? $code), 'name' => $name];
        }
        usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $out = [];
    foreach ($wards as $code => $w) {
        if (($w['parent_code'] ?? '') !== $districtCode) continue;
        $name = $w['name'] ?? '';
        if ($name === '') continue;
        $out[] = ['code' => (string)$code, 'name' => (string)$name];
    }
    usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid type. Use provinces|districts|wards'], JSON_UNESCAPED_UNICODE);

