<?php
/**
 * Mock Course Service — Mini-LMS (port default 8103, bisa dipindah mis. 8203)
 * Service tiruan untuk mendemokan lookup & uji perpindahan alamat.
 *
 *   php -S 127.0.0.1:8103 services/course-mock.php
 *
 * Endpoint:
 *   GET /api/info   -> identitas service (200)
 *   GET /health      -> status service (200)
 */
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');

function send_json(int $status, $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($method === 'GET' && ($path === '/' || $path === '')) {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Course Service (mock)</h1><p>GET /api/info<br>GET /health</p>';
    exit;
}

if ($method === 'GET' && $path === '/api/info') {
    send_json(200, [
        'service'     => 'course-service',
        'name'        => 'course',
        'description' => 'Layanan data mata kuliah (mock untuk demo name service)',
        'version'     => '1.0',
    ]);
}

if ($method === 'GET' && $path === '/health') {
    send_json(200, ['service' => 'course', 'status' => 'ok']);
}

send_json(404, ['error' => 'endpoint_tidak_ditemukan', 'message' => "Endpoint '{$path}' tidak dikenal."]);
