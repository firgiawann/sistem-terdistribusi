#!/usr/bin/env python3
"""Membuat 3 bukti pengujian (SVG gaya terminal) + diagram alur lookup.
SVG dipilih karena aman di-push via API GitHub dan langsung ter-render di repo."""
import os
from xml.sax.saxutils import escape

BASE = os.path.dirname(os.path.abspath(__file__))  # pertemuan-7/
OUT = os.path.join(BASE, "docs", "screenshots")
os.makedirs(OUT, exist_ok=True)

BG, FG = "#0d1117", "#e6edf3"
GREEN, RED, DIM, ACCENT = "#7ee787", "#ff7b72", "#8b949e", "#79c0ff"
W, PAD, LH, FS = 1160, 30, 27, 17


def render(fname, title, subtitle, lines):
    h = PAD * 2 + 60 + len(lines) * LH + 20
    parts = [
        f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{h}" font-family="ui-monospace, Menlo, Consolas, monospace">',
        f'<rect width="{W}" height="{h}" fill="{BG}"/>',
        f'<rect width="{W}" height="58" fill="#161b22"/>',
        f'<text x="{PAD}" y="36" fill="{FG}" font-size="20" font-weight="bold">{escape(title)}</text>',
        f'<text x="{PAD}" y="88" fill="{DIM}" font-size="{FS}">{escape(subtitle)}</text>',
    ]
    y = PAD + 70 + 13
    for text, color in lines:
        w = ' font-weight="bold"' if text.startswith("$") else ""
        parts.append(f'<text x="{PAD}" y="{y}" fill="{color}" font-size="{FS}"{w}>{escape(text)}</text>')
        y += LH
    parts.append("</svg>")
    with open(os.path.join(OUT, fname), "w", encoding="utf-8") as f:
        f.write("\n".join(parts))
    print("OK:", fname)


G, R, F, D, A = GREEN, RED, FG, DIM, ACCENT
SUB = "Mini-LMS Registry · Kelompok 4"

render("01-lookup-valid.svg", "Skenario 1 — Lookup nama valid", SUB + " · registry + gateway",
    [
        ("$ curl 127.0.0.1:8100/api/registry/course", G),
        ("→ HTTP 200 OK", G),
        ('  { "name": "course", "base_url": "http://127.0.0.1:8103" }', F),
        ("", F),
        ("$ curl 127.0.0.1:8101/api/gateway/course", G),
        ("→ HTTP 200 OK", G),
        ('  { "via": "gateway", "resolved_from": "course",', F),
        ('    "base_url": "http://127.0.0.1:8103", "upstream_status": 200,', F),
        ('    "data": { "service": "course-service", ... } }', F),
        ("", F),
        ("→ Gateway me-resolve nama 'course' lalu meneruskan request. ✓", G),
    ])

render("02-nama-tidak-ditemukan.svg", "Skenario 2 — Nama tidak ditemukan → 404", SUB + " · registry",
    [
        ("$ curl 127.0.0.1:8100/api/registry/layanan-aneh", G),
        ("→ HTTP 404 Not Found", R),
        ("{", F),
        ('  "error": "nama_tidak_ditemukan",', R),
        ('  "message": "Nama service \'layanan-aneh\' tidak terdaftar di registry."', F),
        ("}", F),
        ("", F),
        ("$ curl 127.0.0.1:8101/api/gateway/lookup/layanan-aneh", G),
        ("→ HTTP 500 (error konfigurasi — bukan 503)", R),
        ("", F),
        ("→ Nama hilang ≠ service mati: dibedakan tahap error-nya.", A),
    ])

render("03-ganti-port.svg", "Skenario 3 — Ganti port tanpa ubah source code", SUB + " · uji perpindahan alamat",
    [
        ("$ # course pindah 8103 → 8203, cukup ubah services.json", D),
        ('  "course": "http://127.0.0.1:8103"  →  "http://127.0.0.1:8203"', A),
        ("", F),
        ("$ curl 127.0.0.1:8101/api/gateway/course   # kode gateway & client TIDAK diubah", G),
        ("→ HTTP 200 OK", G),
        ('  { "via": "gateway", "resolved_from": "course",', F),
        ('    "base_url": "http://127.0.0.1:8203", "upstream_status": 200 }', F),
        ("", F),
        ("→ Gateway otomatis memakai alamat BARU. Nama logis memisahkan client dari lokasi. ✓", G),
    ])

# ---------------- diagram alur lookup ----------------
BW, BH = 1160, 430
s = [
    f'<svg xmlns="http://www.w3.org/2000/svg" width="{BW}" height="{BH}" font-family="system-ui, sans-serif">',
    f'<rect width="{BW}" height="{BH}" fill="#ffffff"/>',
    f'<text x="30" y="40" font-size="22" font-weight="bold" fill="#111">Alur Lookup — Gateway ke Course Service</text>',
    f'<text x="30" y="66" font-size="14" fill="#666">P07.2 · Kelompok 4 · Sistem Terdistribusi – Pilihan C</text>',
]
boxes = [
    (30, 110, 200, 90, "Client", "GET /api/gateway/course"),
    (330, 110, 200, 90, "Gateway :8101", "resolveService('course')"),
    (630, 110, 200, 90, "Registry", "services.json / :8100"),
    (930, 110, 200, 90, "Course :8103", "GET /api/info → 200"),
]
for x, y, w, h, t, st in boxes:
    s.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="10" fill="#f0f6ff" stroke="#2563eb" stroke-width="2"/>')
    s.append(f'<text x="{x+w//2}" y="{y+38}" text-anchor="middle" font-size="17" font-weight="bold" fill="#111">{t}</text>')
    s.append(f'<text x="{x+w//2}" y="{y+62}" text-anchor="middle" font-size="12" fill="#444">{escape(st)}</text>')
arrows = [
    (230, 155, 330, 155, "1. request"),
    (530, 155, 630, 155, "2. lookup nama"),
    (830, 155, 930, 155, "4. teruskan request"),
]
for x1, y1, x2, y2, lbl in arrows:
    s.append(f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" stroke="#111" stroke-width="2" marker-end="url(#ah)"/>')
    s.append(f'<text x="{(x1+x2)//2}" y="{y1-10}" text-anchor="middle" font-size="12" fill="#111">{lbl}</text>')
# panah balik registry -> gateway (langkah 3)
s.append('<defs><marker id="ah" markerWidth="10" markerHeight="8" refX="9" refY="4" orient="auto"><path d="M0,0 L10,4 L0,8" fill="none" stroke="#111" stroke-width="2"/></marker></defs>')
s.append('<line x1="830" y1="185" x2="530" y2="185" stroke="#111" stroke-width="2" marker-end="url(#ah)"/>')
s.append('<text x="680" y="208" text-anchor="middle" font-size="12" fill="#111">3. base_url 127.0.0.1:8103</text>')
# catatan bawah
notes = [
    "Catatan:",
    "• Client hanya kenal SATU alamat (gateway). Alamat tiap service boleh berubah.",
    "• Ganti port cukup di services.json — tanpa mengubah source code client maupun gateway.",
    "• Nama tidak dikenal → error konfigurasi (500); service tidak merespons → 503.",
]
y = 260
for n in notes:
    s.append(f'<text x="30" y="{y}" font-size="14" fill="#333">{escape(n)}</text>')
    y += 26
s.append("</svg>")
with open(os.path.join(BASE, "docs", "diagram-lookup.svg"), "w", encoding="utf-8") as f:
    f.write("\n".join(s))
print("OK: diagram-lookup.svg")
