# Bukti Pengujian — Mini-LMS Submission/File Service

Kelompok 4 · Sistem Terdistribusi · Pertemuan 6

Cara menjalankan ulang: `bash test_api.sh` dari folder `pertemuan-6/`
(menyalakan PHP built-in server sementara di port 8000).
Alternatif: import `Mini-LMS.postman_collection.json` ke Postman.
Skrip menjalankan semua skenario,
lalu mematikannya. Hasil terakhir: **8 lulus, 0 gagal**.
Bukti mentah (output curl asli): `docs/bukti/`.

## Skenario 1 — Permintaan valid ✅

Alur lengkap: buat metadata → unggah file → ambil detail → unduh & verifikasi bytes.

![Skenario 1](screenshots/01-permintaan-valid.png)

- `POST /api/submissions` → **201**, submission `sub_065c5642d908` berstatus `draft`.
- `POST /api/submissions/sub_065c5642d908/file` (laporan.pdf, 887 bytes) → **201**,
  `file_id: file_6d2008b7b94f`, status submission menjadi `dikumpulkan`.
- `GET /api/submissions/sub_065c5642d908` → **200**, field `file` terisi
  (tanpa `internal_path` — sesuai kontrak).
- `GET /api/files/file_6d2008b7b94f` → **200**, bytes unduhan **identik**
  dengan file asli (perbandingan biner lulus).

## Skenario 2 — Assignment tidak ditemukan → 404 ✅

![Skenario 2](screenshots/02-assignment-tidak-ditemukan.png)

`POST /api/submissions` dengan `"assignment_id": "tugas-999"` → **404**
`assignment_tidak_ditemukan`.

## Skenario 3 — Input tidak lengkap → 400 ✅

![Skenario 3](screenshots/03-input-tidak-lengkap.png)

`POST /api/submissions` tanpa `student_id` → **400** `input_tidak_lengkap`
dengan daftar field yang kurang: `["student_id"]`.

## Skenario bonus

- **File terlalu besar → 413**: upload 6 MB ke `tugas-01` (batas 5 MB)
  ditolak dengan `file_terlalu_besar`. Bukti: `docs/bukti/s4-file-413.txt`.
- **Tipe file ditolak → 415**: upload `virus.exe` ke `tugas-01`
  (hanya `.pdf`/`.docx`) ditolak dengan `tipe_file_ditolak`.
  Bukti: `docs/bukti/s5-tipe-415.txt`.
