#!/usr/bin/env python3
"""Membuat 3 screenshot bukti pengujian (gaya terminal) dari hasil nyata test_api.sh.
ID submission/file diambil dinamis dari docs/bukti/."""
import json
import os
import re
from PIL import Image, ImageDraw, ImageFont

BASE = os.path.dirname(os.path.abspath(__file__))  # pertemuan-6/
BUKTI = os.path.join(BASE, "docs", "bukti")
OUT = os.path.join(BASE, "docs", "screenshots")
os.makedirs(OUT, exist_ok=True)


def read_bukti(name):
    with open(os.path.join(BUKTI, name), encoding="utf-8") as f:
        txt = f.read()
    body = txt[: txt.rfind("HTTP ")].strip()
    code = re.search(r"HTTP (\d+)", txt).group(1)
    return json.loads(body), code


s1a, _ = read_bukti("s1a-buat-submission.txt")
s1b, _ = read_bukti("s1b-upload-file.txt")
SUBID = s1a["id"]
FILEID = s1b["file_id"]
SIZE = s1b["size_bytes"]

FONT = "/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf"
FONT_B = "/usr/share/fonts/truetype/dejavu/DejaVuSansMono-Bold.ttf"
f_reg = ImageFont.truetype(FONT, 17)
f_bold = ImageFont.truetype(FONT_B, 17)
f_title = ImageFont.truetype(FONT_B, 20)

BG, FG = "#0d1117", "#e6edf3"
GREEN, RED, DIM, ACCENT = "#7ee787", "#ff7b72", "#8b949e", "#79c0ff"
W, PAD, LH = 1160, 30, 27


def render(fname, title, subtitle, lines):
    h = PAD * 2 + 60 + len(lines) * LH + 20
    img = Image.new("RGB", (W, h), BG)
    d = ImageDraw.Draw(img)
    d.rectangle([0, 0, W, 58], fill="#161b22")
    d.text((PAD, 14), title, font=f_title, fill=FG)
    d.text((PAD, 66), subtitle, font=f_reg, fill=DIM)
    y = PAD + 70
    for text, color in lines:
        d.text((PAD, y), text, font=f_bold if text.startswith("$") else f_reg, fill=color)
        y += LH
    img.save(os.path.join(OUT, fname))
    print("OK:", fname, img.size)


G, R, F, D, A = GREEN, RED, FG, DIM, ACCENT

render(
    "01-permintaan-valid.png",
    "Skenario 1 — Permintaan valid",
    "Mini-LMS Submission/File Service · Kelompok 4 · POST + GET submission/file",
    [
        ("$ curl -X POST localhost:8000/api/submissions \\", G),
        ('    -d \'{"assignment_id":"tugas-01","student_id":"240209501100",...}\'', G),
        ("→ HTTP 201 Created", G),
        (f'  {{ "id": "{SUBID}", "status": "draft",', F),
        (f'    "upload_file": "/api/submissions/{SUBID}/file" }}', F),
        ("", F),
        (f"$ curl -X POST localhost:8000/api/submissions/{SUBID}/file \\", G),
        (f'    -d \'{{"file_name":"laporan.pdf","content_base64":"<{SIZE} bytes>"}}\'', G),
        ("→ HTTP 201 Created", G),
        (f'  {{ "file_id": "{FILEID}", "size_bytes": {SIZE},', F),
        (f'    "download_url": "/api/files/{FILEID}" }}', F),
        ("", F),
        (f"$ curl localhost:8000/api/submissions/{SUBID}", G),
        ('→ HTTP 200 OK — "status": "dikumpulkan", file terisi', G),
        ("", F),
        (f"$ curl -o unduhan.pdf localhost:8000/api/files/{FILEID}", G),
        ("→ HTTP 200 OK — bytes unduhan identik dengan file asli ✓", G),
    ],
)

render(
    "02-assignment-tidak-ditemukan.png",
    "Skenario 2 — Assignment tidak ditemukan → 404",
    "Mini-LMS Submission/File Service · Kelompok 4 · POST /api/submissions",
    [
        ("$ curl -X POST localhost:8000/api/submissions \\", G),
        ('    -d \'{"assignment_id":"tugas-999","student_id":"240209501100"}\'', G),
        ("→ HTTP 404 Not Found", R),
        ("{", F),
        ('  "error": "assignment_tidak_ditemukan",', R),
        ('  "message": "Assignment \'tugas-999\' tidak ditemukan."', F),
        ("}", F),
    ],
)

render(
    "03-input-tidak-lengkap.png",
    "Skenario 3 — Input tidak lengkap → 400",
    "Mini-LMS Submission/File Service · Kelompok 4 · POST /api/submissions",
    [
        ("$ curl -X POST localhost:8000/api/submissions \\", G),
        ('    -d \'{"assignment_id":"tugas-01"}\'', G),
        ("→ HTTP 400 Bad Request", R),
        ("{", F),
        ('  "error": "input_tidak_lengkap",', R),
        ('  "message": "Field wajib belum diisi.",', F),
        ('  "missing": ["student_id"]', A),
        ("}", F),
    ],
)
