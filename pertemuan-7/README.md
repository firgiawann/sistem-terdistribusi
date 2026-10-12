# P07.2 — Registry dan Uji Perubahan Alamat Service (Mini-LMS)

**Kelompok 4 · Sistem Terdistribusi – Pilihan C · Pertemuan 7 (Name Service & Service Discovery)**

## Identitas Kelompok

| No | Nama | NIM |
|----|------|-----|
| 1 | Musyrifah Sa'adah | 230209501011 |
| 2 | Wa Ode Nabil Salsabila | 240209501040 |
| 3 | Aulia Ramadhani | 240209501054 |
| 4 | Firgiawan Listianto | 240209501100 |

## Deskripsi

Prototipe name service untuk Mini-LMS: memisahkan **nama logis** service
(mis. `course`) dari **alamat jaringan** (mis. `http://127.0.0.1:8103`).
Gateway me-resolve nama sebelum membuka koneksi, sehingga alamat service
bisa berubah tanpa mengubah source code client maupun gateway.

Dibangun dengan **PHP murni** (tanpa framework, tanpa composer).

## Arsitektur

```
Client ──GET /api/gateway/course──▶ Gateway :8101 ──resolve('course')──▶ services.json / Registry :8100
                                          │                                        │
                                          │◀── http://127.0.0.1:8103 ──────────────┘
                                          └──GET /api/info──▶ Course Service :8103 ──▶ 200
```

Diagram lengkap: [`docs/diagram-lookup.svg`](docs/diagram-lookup.svg)

| Komponen | File | Port | Peran |
|---|---|---|---|
| Registry | `services.json` | — | Binding nama → alamat (7 service) |
| Pustaka resolver | `resolver.php` | — | Fungsi `resolveService($name)` |
| Registry Service | `registry.php` | 8100 | Endpoint `GET /api/registry/{name}` |
| Gateway | `gateway.php` | 8101 | Resolve nama lalu teruskan request |
| Course (mock) | `services/course-mock.php` | 8103 | Service tiruan untuk demo lookup |

## Struktur folder

```
pertemuan-7/
├── services.json                    # binding 7 service Mini-LMS
├── resolver.php                     # fungsi resolveService()
├── registry.php                     # registry service (:8100)
├── gateway.php                      # gateway (:8101)
├── services/course-mock.php         # mock course service (:8103)
├── test_registry.sh                 # skrip pengujian otomatis
├── gen_screenshots.py               # pembuat bukti SVG + diagram
├── Mini-LMS-Registry.postman_collection.json
├── README.md                        # file ini
└── docs/
    ├── TESTING.md                   # ringkasan bukti pengujian
    ├── diagram-lookup.svg           # diagram alur lookup
    ├── bukti/                       # output mentah pengujian
    └── screenshots/                 # 3 bukti pengujian (SVG)
```

## Cara menjalankan

Butuh PHP 8+. Jalankan tiga service di tiga terminal (dari folder `pertemuan-7/`):

```bash
php -S 127.0.0.1:8100 registry.php
php -S 127.0.0.1:8101 gateway.php
php -S 127.0.0.1:8103 services/course-mock.php
```

## Cara menguji

Otomatis:

```bash
cd pertemuan-7
bash test_registry.sh
```

Hasil terakhir: **8 lulus, 0 gagal**. Bukti mentah: `docs/bukti/`.

Dengan Postman: import `Mini-LMS-Registry.postman_collection.json`
(`base_url` sesuai port gateway/registry yang berjalan).

Manual (tiga service harus jalan):

```bash
# lookup langsung ke registry
curl 127.0.0.1:8100/api/registry/course
curl 127.0.0.1:8100/api/registry/layanan-aneh   # -> 404

# via gateway
curl 127.0.0.1:8101/api/gateway/lookup/course
curl 127.0.0.1:8101/api/gateway/course            # diteruskan ke course
curl 127.0.0.1:8101/api/gateway/lookup/layanan-aneh  # -> 500

# uji pindah port: jalankan course di 8203, ubah services.json,
# panggil lagi gateway TANPA ubah kode -> tetap 200
```

## Kontrak endpoint (ringkas)

| Method | Endpoint | Sukses |
|---|---|---|
| `GET` | `/api/registry` | 200 |
| `GET` | `/api/registry/{name}` | 200 / 404 (`nama_tidak_ditemukan`) |
| `GET` | `/api/gateway/lookup/{name}` | 200 / 500 (`nama_tidak_ditemukan`) |
| `GET` | `/api/gateway/registry/{name}` | 200 / 404 / 503 |
| `GET` | `/api/gateway/course` | 200 / 500 / 503 (`service_tidak_merespons`) |

Pembedaan error sesuai materi: nama tidak dikenal → error konfigurasi
(404 di registry, 500 di gateway); service tidak merespons → 503 beserta
target yang gagal.

## Bukti pengujian

Ringkasan: [`docs/TESTING.md`](docs/TESTING.md) · Bukti mentah: `docs/bukti/`

| # | Skenario | Hasil |
|---|---|---|
| 1 | Lookup nama valid (registry + gateway teruskan ke course) | ✅ lulus |
| 2 | Nama tidak ditemukan → 404 (registry) / 500 (gateway) | ✅ lulus |
| 3 | Ganti port 8103→8203 tanpa ubah source code → tetap 200 | ✅ lulus |
| 4 | Service mati → 503 (bonus) | ✅ lulus |

## Pembagian kontribusi

| Nama | Kontribusi |
|------|-----------|
| Musyrifah Sa'adah | Model name service & dokumentasi (`README.md`, diagram lookup) |
| Wa Ode Nabil Salsabila | Perancangan registry & resolver (`services.json`, `resolver.php`, `registry.php`) |
| Aulia Ramadhani | Pengujian & bukti (`test_registry.sh`, koleksi Postman, 3 screenshot, `TESTING.md`) |
| Firgiawan Listianto | Implementasi gateway & mock service (`gateway.php`, `course-mock.php`), integrasi & publikasi repository |
