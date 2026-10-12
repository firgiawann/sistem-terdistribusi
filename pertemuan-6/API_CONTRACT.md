# Kontrak API — Mini-LMS Submission/File Service

Prototipe Kelompok 4 · Sistem Terdistribusi · Pertemuan 6 (File Service)

Implementasi: PHP murni (`index.php`, tanpa framework) — dijalankan dengan
`php -S localhost:8000 index.php`.

Base URL: `http://localhost:8000`

## Prinsip desain (sesuai materi kuliah)

1. **Metadata terpisah dari isi file.** `POST /api/submissions` hanya menyimpan
   metadata (tahap awal boleh tanpa file fisik). Bytes file diunggah belakangan
   lewat `POST /api/submissions/{id}/file`.
2. **Client memakai identifier stabil, bukan path internal.**
   Submission = `sub_<12 hex>`, file = `file_<12 hex>`. Lokasi fisik
   (`storage/xx/file_...`) hanya diketahui server dan **tidak pernah**
   muncul di respons API.
3. **Satu submission = satu file.** Upload kedua ke submission yang sama
   ditolak (409).

## Endpoint

| Method | Endpoint | Fungsi | Sukses |
|---|---|---|---|
| GET | `/` | Halaman info layanan (HTML) | 200 |
| GET | `/api/assignments` | Daftar semua tugas | 200 |
| GET | `/api/assignments/{id}` | Detail satu tugas | 200 / 404 |
| POST | `/api/submissions` | Buat metadata submission (tahap 1) | 201 / 400 / 404 |
| POST | `/api/submissions/{id}/file` | Unggah bytes file (tahap 2) | 201 / 400 / 404 / 409 / 413 / 415 |
| GET | `/api/submissions/{id}` | Detail submission + info file | 200 / 404 |
| GET | `/api/files/{file_id}` | Unduh bytes file | 200 / 404 |

## 1. Buat submission — `POST /api/submissions`

Request (JSON):

```json
{
  "assignment_id": "tugas-01",
  "student_id": "240209501100",
  "student_name": "Firgiawan Listianto",
  "notes": "Pengumpulan laporan"
}
```

Wajib: `assignment_id`, `student_id`. Opsional: `student_name`, `notes`.

Respons `201 Created`:

```json
{
  "id": "sub_065c5642d908",
  "assignment_id": "tugas-01",
  "assignment_title": "Laporan Analisis Protokol",
  "student_id": "240209501100",
  "student_name": "Firgiawan Listianto",
  "notes": "Pengumpulan laporan",
  "status": "draft",
  "created_at": "2026-10-12T12:06:33.623711+00:00",
  "file": null,
  "links": {
    "self": "/api/submissions/sub_065c5642d908",
    "upload_file": "/api/submissions/sub_065c5642d908/file"
  }
}
```

## 2. Unggah file — `POST /api/submissions/{id}/file`

Request (JSON, isi file dalam base64 agar mudah diuji via curl tanpa
dependensi multipart):

```json
{
  "file_name": "laporan.pdf",
  "content_base64": "<isi file dalam base64>"
}
```

Aturan: ekstensi harus ada di `allowed_types` tugasnya, ukuran ≤
`max_file_size_bytes` tugasnya.

Respons `201 Created`:

```json
{
  "file_id": "file_6d2008b7b94f",
  "file_name": "laporan.pdf",
  "size_bytes": 887,
  "mime_type": "application/pdf",
  "uploaded_at": "2026-10-12T12:06:33.743019+00:00",
  "download_url": "/api/files/file_6d2008b7b94f",
  "submission": { "id": "sub_065c5642d908", "status": "dikumpulkan" }
}
```

## 3. Detail submission — `GET /api/submissions/{id}`

Respons `200 OK` (setelah file diunggah):

```json
{
  "id": "sub_065c5642d908",
  "assignment_id": "tugas-01",
  "assignment_title": "Laporan Analisis Protokol",
  "student_id": "240209501100",
  "student_name": "Firgiawan Listianto",
  "status": "dikumpulkan",
  "created_at": "2026-10-12T12:06:33.623711+00:00",
  "file": {
    "file_id": "file_6d2008b7b94f",
    "file_name": "laporan.pdf",
    "size_bytes": 887,
    "mime_type": "application/pdf",
    "uploaded_at": "2026-10-12T12:06:33.743019+00:00",
    "download_url": "/api/files/file_6d2008b7b94f"
  }
}
```

> Catatan: field `file` **tidak memuat** `internal_path`. Client hanya tahu
> `file_id` dan `download_url`.

## 4. Unduh file — `GET /api/files/{file_id}`

Respons `200 OK`: bytes asli file, dengan header
`Content-Type: <mime>` dan
`Content-Disposition: attachment; filename="<nama asli>"`.

## Kode error

Semua error berbentuk:

```json
{ "error": "<kode>", "message": "<penjelasan>" }
```

| HTTP | Kode | Arti | Contoh pemicu |
|---|---|---|---|
| 400 | `input_tidak_lengkap` | Field wajib belum diisi | POST tanpa `student_id` |
| 400 | `json_tidak_valid` | Body bukan JSON valid | Body rusak |
| 400 | `base64_tidak_valid` | Isi base64 rusak | `content_base64` asal |
| 404 | `assignment_tidak_ditemukan` | Tugas tidak ada | `assignment_id: "tugas-999"` |
| 404 | `submission_tidak_ditemukan` | Submission tidak ada | ID submission asal |
| 404 | `file_tidak_ditemukan` | File tidak ada | ID file asal |
| 409 | `file_sudah_diupload` | File sudah ada | Upload kedua ke submission sama |
| 413 | `file_terlalu_besar` | Melebihi batas tugas | 6 MB ke tugas batas 5 MB |
| 415 | `tipe_file_ditolak` | Ekstensi tidak diizinkan | `.exe` ke tugas yang hanya menerima `.pdf`/`.docx` |

## Identifier, lokasi internal, dan bentuk respons

| Konsep | Nilai | Keterangan |
|---|---|---|
| Identifier submission | `sub_065c5642d908` | Stabil, dipakai client di URL & referensi |
| Identifier file | `file_6d2008b7b94f` | Stabil, dipakai client untuk unduh |
| Lokasi internal | `storage/6d/file_6d2008b7b94f` | Hanya server yang tahu; 2 karakter pertama sebagai shard direktori |
| Metadata | `data/submissions.json`, `data/files.json` | JSON sederhana (mudah dibaca & diuji) |
| Nama asli file | `laporan.pdf` | Disimpan di metadata; nama file fisik = `file_id` (tanpa nama asli) |

Relasinya: **submission → (0..1) file → bytes di storage**.
Pemisahan ini membuat layanan bisa dipindah/di-scale: selama `file_id`
stabil, lokasi fisik boleh berubah tanpa client sadar.
