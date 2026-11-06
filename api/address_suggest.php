<?php
/**
 * Автокомплит адресов через 2ГИС Suggest API 3.0
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$debug = isset($_GET['debug']);

$q = trim($_GET['q'] ?? '');
if (!$q || mb_strlen($q) < 2) {
    echo json_encode(['suggestions' => []]);
    exit;
}

$key = GIS_CATALOG_KEY ?: GIS_API_KEY;
if (!$key) {
    http_response_code(500);
    echo json_encode(['error' => 'API key missing']);
    exit;
}

$lat = $_GET['lat'] ?? null;
$lon = $_GET['lon'] ?? null;

// Убрали type — он вызывает ошибку в Suggest API
$params = [
    'q'            => $q,
    'key'          => $key,
    'suggest_type' => 'address',
    'fields'       => 'items.point',
    'locale'       => 'ru_RU',
];

if ($lat && $lon) {
    $params['location'] = $lon . ',' . $lat;
}

$url = 'https://catalog.api.2gis.com/3.0/suggests?' . http_build_query($params);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($debug) {
    echo json_encode([
        'url'        => $url,
        'http_code'  => $httpCode,
        'curl_error' => $curlError,
        'raw'        => $response,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($response === false || $httpCode !== 200) {
    error_log("address_suggest error: HTTP $httpCode, cURL: $curlError, URL: $url");
    echo json_encode(['suggestions' => [], 'error' => '2GIS API error (HTTP ' . $httpCode . ')']);
    exit;
}

$data = json_decode($response, true);
$suggestions = [];

if (!empty($data['result']['items'])) {
    foreach ($data['result']['items'] as $item) {
        $type = $item['type'] ?? '';

        // Пропускаем служебные подсказки
        if ($type === 'user_query') {
            continue;
        }

        $name = $item['name'] ?? '';
        $fullName = $item['full_name'] ?? '';
        if (!$name && !$fullName) {
            continue;
        }

        $point = $item['point'] ?? null;
        $rLat = $point['lat'] ?? null;
        $rLon = $point['lon'] ?? null;

        $typeLabel = match($type) {
            'building'  => 'дом',
            'street'    => 'улица',
            'locality'  => 'город',
            'district'  => 'район',
            'station'   => 'станция',
            'stop'      => 'остановка',
            default     => $type,
        };

        $suggestions[] = [
            'text' => $fullName ?: $name,
            'lat'  => $rLat !== null ? (float)$rLat : null,
            'lon'  => $rLon !== null ? (float)$rLon : null,
            'type' => $typeLabel,
        ];
    }
}

echo json_encode(['suggestions' => $suggestions], JSON_UNESCAPED_UNICODE);
