<?php
/**
 * Mini-LMS Submission/File Service — Prototipe Tugas Kelompok 4
 * Mata Kuliah : Sistem Terdistribusi — Pertemuan 6 (File Service)
 *
 * PHP murni, tanpa framework, tanpa composer.
 *
 * Cara menjalankan:
 *   php -S localhost:8000 index.php
 *
 * Desain mengikuti materi kuliah:
 * - Metadata submission TERPISAH dari isi file (bytes). Client memakai
 *   identifier stabil (sub_xxx / file_xxx), bukan path internal server.
 * - Alur dua tahap: (1) POST /api/submissions menyimpan metadata dulu
 *   (status "draft"), (2) POST /api/submissions/{id}/file mengunggah
 *   bytes file (status menjadi "dikumpulkan").
 * - Kode status sesuai materi: 400 input tidak lengkap, 404 tidak
 *   ditemukan, 413 file terlalu besar, 415 tipe file ditolak.
 */
declare(strict_types=1);

const DATA_DIR = __DIR__ . '/data';
const STORAGE_DIR = __DIR__ . '/storage';
const MAX_BODY_BYTES = 20 * 1024 * 1024; // 20 MB, cukup untuk file 10 MB + overhead base64

$assignments = [];  // id => array
$submissions = [];  // sub_id => array
$files = [];        // file_id => array (internal_path hanya di sisi server)

/* ------------------------------------------------------------------ util */
function now_iso(): string
{
    return gmdate('Y-m-d\TH:i:sP');
}

function new_id(string $prefix): string
{
    return $prefix . '_' . bin2hex(random_bytes(6));
}

function load_json(string $path, $default)
{
    if (!is_file($path)) return $default;
    $data = json_decode((string)file_get_contents($path), true);
    return json_last_error() === JSON_ERROR_NONE ? $data : $default;
}

function save_json(string $path, $data): void
{
    $tmp = $path . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    rename($tmp, $path);
}

function init_store(): void
{
    global $assignments, $submissions, $files;
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0777, true);
    if (!is_dir(STORAGE_DIR)) mkdir(STORAGE_DIR, 0777, true);
    foreach (load_json(DATA_DIR . '/assignments.json', []) as $a) {
        $assignments[$a['id']] = $a;
    }
    $submissions = load_json(DATA_DIR . '/submissions.json', []);
    $files = load_json(DATA_DIR . '/files.json', []);
}

function persist(): void
{
    global $submissions, $files;
    save_json(DATA_DIR . '/submissions.json', $submissions);
    save_json(DATA_DIR . '/files.json', $files);
}

/** Simpan bytes ke lokasi internal; path TIDAK pernah diekspos ke client. */
function store_file_bytes(string $file_id, string $data): string
{
    $subdir = STORAGE_DIR . '/' . substr($file_id, 5, 2); // 2 char pertama sbg shard
    if (!is_dir($subdir)) mkdir($subdir, 0777, true);
    $internal_path = $subdir . '/' . $file_id;
    file_put_contents($internal_path, $data);
    return $internal_path;
}

function mime_for(string $filename): string
{
    $map = [
        'pdf'  => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'zip'  => 'application/zip',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'txt'  => 'text/plain',
    ];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return $map[$ext] ?? 'application/octet-stream';
}

/** Bentuk file yang boleh dilihat client: tanpa internal_path. */
function public_file_info(array $f): array
{
    return [
        'file_id'      => $f['file_id'],
        'file_name'    => $f['original_name'],
        'size_bytes'   => $f['size_bytes'],
        'mime_type'    => $f['mime_type'],
        'uploaded_at'  => $f['uploaded_at'],
        'download_url' => '/api/files/' . $f['file_id'],
    ];
}

function public_submission(array $s): array
{
    global $assignments, $files;
    $a = $assignments[$s['assignment_id']] ?? [];
    $out = [
        'id'               => $s['id'],
        'assignment_id'    => $s['assignment_id'],
        'assignment_title' => $a['title'] ?? null,
        'student_id'       => $s['student_id'],
        'student_name'     => $s['student_name'] ?? null,
        'notes'            => $s['notes'] ?? null,
        'status'           => $s['status'],
        'created_at'       => $s['created_at'],
        'file'             => null,
    ];
    if (!empty($s['file_id']) && isset($files[$s['file_id']])) {
        $out['file'] = public_file_info($files[$s['file_id']]);
    }
    return $out;
}

/* ------------------------------------------------------------------ http */
/* CORS: izinkan halaman tester (mis. di GitHub Pages) memanggil API ini. */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

function fail(int $status, string $code, string $message, array $extra = []): void
{
    send_json($status, array_merge(['error' => $code, 'message' => $message], $extra));
}

/** @return array{0: ?array, 1: ?string}  [data, kode_error] */
function read_json_body(): array
{
    $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($len <= 0) return [[], null];
    if ($len > MAX_BODY_BYTES) return [null, 'body_terlalu_besar'];
    $data = json_decode((string)file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) return [null, 'json_tidak_valid'];
    return [$data ?? [], null];
}

function landing(): void
{
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
<!doctype html><html lang="id"><head><meta charset="utf-8">
<title>Mini-LMS Submission/File Service</title>
<style>body{font-family:system-ui,sans-serif;max-width:720px;margin:40px auto;
padding:0 20px;color:#1f2937}code{background:#f3f4f6;padding:2px 6px;border-radius:4px}
li{margin:6px 0}</style></head><body>
<h1>Mini-LMS Submission/File Service</h1>
<p>Prototipe Kelompok 4 — Sistem Terdistribusi, Pertemuan 6 (File Service).</p>
<h3>Endpoint</h3>
<ul>
<li><code>GET /api/assignments</code> — daftar tugas</li>
<li><code>POST /api/submissions</code> — buat metadata submission (tahap 1)</li>
<li><code>POST /api/submissions/{id}/file</code> — unggah file (tahap 2)</li>
<li><code>GET /api/submissions/{id}</code> — detail submission</li>
<li><code>GET /api/files/{file_id}</code> — unduh file</li>
</ul>
<p>Lihat <code>README.md</code> untuk cara menjalankan &amp; menguji.</p>
</body></html>
HTML;
    exit;
}

/* ------------------------------------------------------------------ route */
init_store();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$parts = array_values(array_filter(explode('/', trim((string)$path, '/')), fn($p) => $p !== ''));

if ($method === 'GET' && $parts === []) {
    landing();
}

// Health check untuk exit ticket UTS (name service materi P07)
if ($method === 'GET' && $parts === ['health']) {
    send_json(200, ['service' => 'submission', 'status' => 'ok', 'version' => '1.0']);
}

if ($parts[0] ?? null === 'api' && count($parts) >= 2) {
    $resource = $parts[1];

    // GET /api/assignments
    if ($method === 'GET' && $resource === 'assignments' && count($parts) === 2) {
        send_json(200, ['assignments' => array_values($assignments)]);
    }

    // GET /api/assignments/{id}
    if ($method === 'GET' && $resource === 'assignments' && count($parts) === 3) {
        $a = $assignments[$parts[2]] ?? null;
        if (!$a) fail(404, 'assignment_tidak_ditemukan', "Assignment '{$parts[2]}' tidak ditemukan.");
        send_json(200, $a);
    }

    // POST /api/submissions  -> buat metadata (tahap 1)
    if ($method === 'POST' && $resource === 'submissions' && count($parts) === 2) {
        [$data, $err] = read_json_body();
        if ($err) fail(400, $err, 'Body harus berupa JSON yang valid.');
        $missing = [];
        foreach (['assignment_id', 'student_id'] as $k) {
            if (empty($data[$k])) $missing[] = $k;
        }
        if ($missing) {
            fail(400, 'input_tidak_lengkap', 'Field wajib belum diisi.', ['missing' => $missing]);
        }
        $assignment = $assignments[$data['assignment_id']] ?? null;
        if (!$assignment) {
            fail(404, 'assignment_tidak_ditemukan', "Assignment '{$data['assignment_id']}' tidak ditemukan.");
        }
        $sub_id = new_id('sub');
        $submissions[$sub_id] = [
            'id'            => $sub_id,
            'assignment_id' => $assignment['id'],
            'student_id'    => $data['student_id'],
            'student_name'  => $data['student_name'] ?? null,
            'notes'         => $data['notes'] ?? null,
            'status'        => 'draft',
            'created_at'    => now_iso(),
            'file_id'       => null,
        ];
        persist();
        $out = public_submission($submissions[$sub_id]);
        $out['links'] = [
            'self'        => "/api/submissions/{$sub_id}",
            'upload_file' => "/api/submissions/{$sub_id}/file",
        ];
        send_json(201, $out);
    }

    // POST /api/submissions/{id}/file  -> unggah bytes (tahap 2)
    if ($method === 'POST' && $resource === 'submissions' && count($parts) === 4 && $parts[3] === 'file') {
        $sub_id = $parts[2];
        if (!isset($submissions[$sub_id])) {
            fail(404, 'submission_tidak_ditemukan', "Submission '{$sub_id}' tidak ditemukan.");
        }
        $sub = &$submissions[$sub_id];
        if (!empty($sub['file_id'])) {
            fail(409, 'file_sudah_diupload', 'Submission ini sudah memiliki file.');
        }
        [$data, $err] = read_json_body();
        if ($err) fail(400, $err, 'Body harus berupa JSON yang valid.');
        $missing = [];
        foreach (['file_name', 'content_base64'] as $k) {
            if (empty($data[$k])) $missing[] = $k;
        }
        if ($missing) {
            fail(400, 'input_tidak_lengkap', "Field 'file_name' dan 'content_base64' wajib diisi.", ['missing' => $missing]);
        }
        $raw = base64_decode((string)$data['content_base64'], true);
        if ($raw === false) {
            fail(400, 'base64_tidak_valid', "Isi 'content_base64' bukan base64 yang valid.");
        }
        $assignment = $assignments[$sub['assignment_id']] ?? [];
        $ext = strtolower(pathinfo((string)$data['file_name'], PATHINFO_EXTENSION));
        $ext = $ext === '' ? '' : '.' . $ext;
        $allowed = array_map('strtolower', $assignment['allowed_types'] ?? []);
        if ($allowed && !in_array($ext, $allowed, true)) {
            fail(415, 'tipe_file_ditolak', "Tipe '{$ext}' tidak diizinkan.", ['allowed_types' => $allowed]);
        }
        $max_size = $assignment['max_file_size_bytes'] ?? (5 * 1024 * 1024);
        if (strlen($raw) > $max_size) {
            fail(413, 'file_terlalu_besar',
                'Ukuran ' . strlen($raw) . " bytes melebihi batas {$max_size} bytes.",
                ['max_file_size_bytes' => $max_size]);
        }

        $file_id = new_id('file');
        $internal_path = store_file_bytes($file_id, $raw);
        $files[$file_id] = [
            'file_id'       => $file_id,
            'submission_id' => $sub_id,
            'original_name' => basename((string)$data['file_name']),
            'size_bytes'    => strlen($raw),
            'mime_type'     => mime_for((string)$data['file_name']),
            'internal_path' => $internal_path, // TIDAK diekspos ke client
            'uploaded_at'   => now_iso(),
        ];
        $sub['file_id'] = $file_id;
        $sub['status'] = 'dikumpulkan';
        persist();
        send_json(201, array_merge(
            public_file_info($files[$file_id]),
            ['submission' => ['id' => $sub_id, 'status' => $sub['status']]]
        ));
    }

    // GET /api/submissions/{id}
    if ($method === 'GET' && $resource === 'submissions' && count($parts) === 3) {
        $s = $submissions[$parts[2]] ?? null;
        if (!$s) fail(404, 'submission_tidak_ditemukan', "Submission '{$parts[2]}' tidak ditemukan.");
        send_json(200, public_submission($s));
    }

    // GET /api/files/{file_id}  -> unduh bytes
    if ($method === 'GET' && $resource === 'files' && count($parts) === 3) {
        $f = $files[$parts[2]] ?? null;
        if (!$f) fail(404, 'file_tidak_ditemukan', "File '{$parts[2]}' tidak ditemukan.");
        if (!is_file($f['internal_path'])) {
            fail(404, 'file_tidak_ditemukan', 'Metadata ada tetapi bytes file hilang di storage.');
        }
        http_response_code(200);
        header('Content-Type: ' . $f['mime_type']);
        header('Content-Length: ' . filesize($f['internal_path']));
        header('Content-Disposition: attachment; filename="' . $f['original_name'] . '"');
        readfile($f['internal_path']);
        exit;
    }
}

fail(404, 'endpoint_tidak_ditemukan', "Endpoint '{$path}' tidak dikenal.");
