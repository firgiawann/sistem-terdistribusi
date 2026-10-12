# P06.2 — Prototipe Submission/File Service (Mini-LMS)

**Kelompok 4 · Sistem Terdistribusi – Pilihan C · Pertemuan 6 (File Service)**

## Identitas Kelompok

| No | Nama | NIM |
|----|------|-----|
| 1 | Musyrifah Sa'adah | 230209501011 |
| 2 | Wa Ode Nabil Salsabila | 240209501040 |
| 3 | Aulia Ramadhani | 240209501054 |
| 4 | Firgiawan Listianto | 240209501100 |

## Deskripsi

Prototipe layanan pengumpulan tugas (submission) dan penyimpanan file untuk
Mini-LMS. Dibangun dengan **PHP murni** (tanpa framework, tanpa composer) —
cukup jalankan dengan PHP built-in server.

Prinsip desain mengikuti materi kuliah:

- **Metadata terpisah dari isi file** — `POST /api/submissions` menyimpan
  metadata dulu (status `draft`); bytes file diunggah belakangan lewat
  `POST /api/submissions/{id}/file` (status menjadi `dikumpulkan`).
- **Client memakai identifier stabil** (`sub_xxx` / `file_xxx`), bukan path
  internal server. Lokasi fisik (`storage/xx/file_...`) tidak pernah muncul
  di respons API.

## Struktur folder

```
pertemuan-6/
├── index.php                       # server Submission/File Service (router + handler)
├── test_api.sh                     # skrip pengujian otomatis (bash + curl)
├── gen_screenshots.py              # pembuat screenshot bukti pengujian
├── Mini-LMS.postman_collection.json# koleksi Postman siap import
├── API_CONTRACT.md                 # kontrak endpoint lengkap
├── README.md                       # file ini
├── data/
│   └── assignments.json            # data awal tugas (seed)
├── docs/
│   ├── TESTING.md                  # ringkasan bukti pengujian
│   ├── bukti/                      # output mentah hasil pengujian (curl)
│   └── screenshots/                # 3 screenshot bukti pengujian
└── .gitignore
```

Data runtime (`data/submissions.json`, `data/files.json`, `storage/`)
dibuat otomatis saat server berjalan dan tidak ikut di-commit.

## Cara menjalankan

Butuh PHP 8+ (cek: `php --version`).

```bash
cd pertemuan-6
php -S localhost:8000 index.php
```

Buka `http://localhost:8000` untuk halaman info layanan.

## Cara menguji

Otomatis (curl):

```bash
cd pertemuan-6
bash test_api.sh
```

Skrip menyalakan PHP built-in server sementara, menjalankan 5 skenario
(8 pemeriksaan), menyimpan bukti mentah ke `docs/bukti/`, lalu mematikan
server. Hasil terakhir: **8 lulus, 0 gagal**.

Dengan Postman: import `Mini-LMS.postman_collection.json`, pastikan
`base_url = http://localhost:8000`, jalankan request berurutan
(1 → 4 → 6 → 7; `sub_id` & `file_id` tersimpan otomatis).

Uji manual contoh (server harus jalan):

```bash
# 1. Buat metadata submission
curl -X POST localhost:8000/api/submissions -H 'Content-Type: application/json' \
  -d '{"assignment_id":"tugas-01","student_id":"240209501100","student_name":"Firgiawan Listianto"}'

# 2. Unggah file (isi base64) ke submission tersebut
B64=$(base64 -w0 laporan.pdf)
curl -X POST localhost:8000/api/submissions/<SUB_ID>/file -H 'Content-Type: application/json' \
  -d "{\"file_name\":\"laporan.pdf\",\"content_base64\":\"$B64\"}"

# 3. Lihat detail submission
curl localhost:8000/api/submissions/<SUB_ID>

# 4. Unduh file
curl -O localhost:8000/api/files/<FILE_ID>
```

## Kontrak API (ringkas)

| Method | Endpoint | Sukses |
|---|---|---|
| `GET` | `/api/assignments` | 200 |
| `POST` | `/api/submissions` | 201 / 400 / 404 |
| `POST` | `/api/submissions/{id}/file` | 201 / 400 / 404 / 409 / 413 / 415 |
| `GET` | `/api/submissions/{id}` | 200 / 404 |
| `GET` | `/api/files/{file_id}` | 200 / 404 |

Detail lengkap (contoh request/respons, kode error, penjelasan identifier
dan lokasi internal): [`API_CONTRACT.md`](API_CONTRACT.md).

## Bukti pengujian

Ringkasan: [`docs/TESTING.md`](docs/TESTING.md) · Bukti mentah: `docs/bukti/`

| # | Skenario | Hasil |
|---|---|---|
| 1 | Permintaan valid (buat → unggah → ambil → unduh, bytes identik) | ✅ lulus |
| 2 | Assignment tidak ditemukan → 404 | ✅ lulus |
| 3 | Input tidak lengkap → 400 | ✅ lulus |
| 4 | File terlalu besar → 413 (bonus) | ✅ lulus |
| 5 | Tipe file ditolak → 415 (bonus) | ✅ lulus |

## Pembagian kontribusi

| Nama | Kontribusi |
|------|-----------|
| Musyrifah Sa'adah | Penyusunan kontrak API & dokumentasi (`API_CONTRACT.md`, `README.md`) |
| Wa Ode Nabil Salsabila | Perancangan model file service (identifier, skema metadata, struktur storage) |
| Aulia Ramadhani | Pengujian & bukti (`test_api.sh`, koleksi Postman, 3 screenshot, `TESTING.md`) |
| Firgiawan Listianto | Implementasi server (`index.php`), integrasi & publikasi repository |
