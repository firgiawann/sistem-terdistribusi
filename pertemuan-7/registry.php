<?php
/**
 * Registry Service — Mini-LMS (port 8100)
 * Menyimpan binding nama logis -> alamat jaringan (model registry terpusat).
 *
 *   php -S 127.0.0.1:8100 registry.php
 *
 * Endpoint:
 *   GET /api/registry          -> semua binding (200)
 *   GET /api/registry/{name}   -> satu binding (200) / nama tidak dikenal (404)
 */
declare(strict_types=1);
require __DIR__ . '/resolver.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function send_json(int $status, $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$parts = array_values(array_filter(explode('/', trim((string)$path, '/')), fn($p) => $p !== ''));

if ($method === 'GET' && $parts === []) {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Registry Service</h1><p>GET /api/registry — semua binding<br>'
        . 'GET /api/registry/{name} — satu binding</p>';
    exit;
}

if ($method === 'GET' && $parts === ['api', 'registry']) {
    send_json(200, ['services' => allServices()]);
}

if ($method === 'GET' && count($parts) === 3 && $parts[0] === 'api' && $parts[1] === 'registry') {
    $name = $parts[2];
    $url = resolveService($name);
    if ($url === null) {
        send_json(404, [
            'error'   => 'nama_tidak_ditemukan',
            'message' => "Nama service '{$name}' tidak terdaftar di registry.",
        ]);
    }
    send_json(200, ['name' => $name, 'base_url' => $url]);
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => 'endpoint_tidak_ditemukan', 'message' => "Endpoint '{$path}' tidak dikenal."]);
