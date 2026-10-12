<?php
/**
 * Gateway — Mini-LMS (port 8101)
 * Meneruskan request ke service lain MELALUI NAMA LOGIS (bukan alamat
 * yang ditanam di kode). Mendemokan:
 *   - resolver function (baca services.json langsung)
 *   - endpoint registry via HTTP (GET /api/registry/{name} di :8100)
 *   - pembedaan error: nama tidak dikenal (500, error konfigurasi)
 *     vs service tidak merespons (503)
 *
 *   php -S 127.0.0.1:8101 gateway.php
 *
 * Endpoint:
 *   GET /api/gateway/lookup/{name}    -> resolve via fungsi (200/500)
 *   GET /api/gateway/registry/{name}  -> resolve via registry service (200/404/503)
 *   GET /api/gateway/course           -> teruskan ke course service (200/500/503)
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

const REGISTRY_URL = 'http://127.0.0.1:8100';

function send_json(int $status, $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function fail(int $status, string $code, string $message, array $extra = []): void
{
    send_json($status, array_merge(['error' => $code, 'message' => $message], $extra));
}

/** @return array{0:int, 1:string|false} [http_status, body] */
function forward_get(string $url): array
{
    $ctx = stream_context_create(['http' => [
        'method'        => 'GET',
        'timeout'       => 5,
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0])
        && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
        $status = (int)$m[1];
    }
    return [$status, $body];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$parts = array_values(array_filter(explode('/', trim((string)$path, '/')), fn($p) => $p !== ''));

if ($method === 'GET' && $parts === []) {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Gateway Mini-LMS</h1><p>GET /api/gateway/lookup/{name} — resolve via fungsi<br>'
        . 'GET /api/gateway/registry/{name} — resolve via registry service<br>'
        . 'GET /api/gateway/course — teruskan ke course service</p>';
    exit;
}

// 1) Resolve memakai fungsi resolver (baca services.json)
if ($method === 'GET' && count($parts) === 4
    && $parts[0] === 'api' && $parts[1] === 'gateway' && $parts[2] === 'lookup') {
    $name = $parts[3];
    $url = resolveService($name);
    if ($url === null) {
        fail(500, 'nama_tidak_ditemukan',
            "Nama service '{$name}' tidak terdaftar — periksa services.json.",
            ['name' => $name]);
    }
    send_json(200, ['name' => $name, 'base_url' => $url, 'via' => 'resolver-function']);
}

// 2) Resolve memakai endpoint registry service (HTTP)
if ($method === 'GET' && count($parts) === 4
    && $parts[0] === 'api' && $parts[1] === 'gateway' && $parts[2] === 'registry') {
    $name = $parts[3];
    [$st, $body] = forward_get(REGISTRY_URL . '/api/registry/' . rawurlencode($name));
    if ($body === false || $st === 0) {
        fail(503, 'registry_tidak_merespons',
            'Registry service tidak merespons.', ['target' => REGISTRY_URL]);
    }
    $data = json_decode((string)$body, true);
    send_json($st, is_array($data) ? $data + ['via' => 'registry-service'] : $body);
}

// 3) Teruskan request ke course service melalui nama logis
if ($method === 'GET' && $parts === ['api', 'gateway', 'course']) {
    $base = resolveService('course');
    if ($base === null) {
        fail(500, 'nama_tidak_ditemukan',
            "Nama service 'course' tidak terdaftar — periksa services.json.");
    }
    [$st, $body] = forward_get(rtrim($base, '/') . '/api/info');
    if ($body === false || $st === 0) {
        fail(503, 'service_tidak_merespons',
            "Service 'course' tidak merespons.", ['target' => $base]);
    }
    $data = json_decode((string)$body, true);
    send_json($st, [
        'via'            => 'gateway',
        'resolved_from'  => 'course',
        'base_url'       => $base,
        'upstream_status'=> $st,
        'data'           => $data,
    ]);
}

fail(404, 'endpoint_tidak_ditemukan', "Endpoint '{$path}' tidak dikenal.");
