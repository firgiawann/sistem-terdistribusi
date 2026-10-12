#!/usr/bin/env bash
# Skrip pengujian Submission/File Service (implementasi PHP).
# Menyalakan PHP built-in server sementara di port 8000, menguji 5 skenario,
# menyimpan bukti mentah ke docs/bukti/, lalu mematikan server.
# Cara pakai:  bash test_api.sh
set -u
PORT=8000
BASE="http://localhost:$PORT"
BUKTI="docs/bukti"
PASS=0; FAIL=0

command -v php >/dev/null 2>&1 || { echo "PHP tidak ditemukan. Install PHP 8+ dulu."; exit 1; }

mkdir -p "$BUKTI"
php -S 127.0.0.1:$PORT index.php >/tmp/minilms.log 2>&1 &
SRV=$!
trap 'kill $SRV 2>/dev/null' EXIT

# tunggu server siap
for i in $(seq 1 30); do curl -sf "$BASE/api/assignments" >/dev/null 2>&1 && break; sleep 0.3; done

cek() { # cek <nama> <kode_harapan> <kode_dapat> <file_bukti>
  if [ "$3" = "$2" ]; then echo "PASS: $1 (HTTP $3)"; PASS=$((PASS+1));
  else echo "FAIL: $1 (harap $2, dapat $3)"; FAIL=$((FAIL+1)); fi
}

echo "=== Skenario 1: permintaan valid (buat submission -> upload -> ambil -> unduh) ==="
curl -s -w '\nHTTP %{http_code}\n' -X POST "$BASE/api/submissions" \
  -H 'Content-Type: application/json' \
  -d '{"assignment_id":"tugas-01","student_id":"240209501100","student_name":"Firgiawan Listianto","notes":"Pengumpulan laporan"}' \
  | tee "$BUKTI/s1a-buat-submission.txt" > /tmp/s1a.txt
CODE=$(grep -o 'HTTP [0-9]*' /tmp/s1a.txt | awk '{print $2}')
cek "buat metadata submission" 201 "$CODE"
SUBID=$(sed '/^HTTP /d' /tmp/s1a.txt | python3 -c "import json,sys;print(json.load(sys.stdin)['id'])")

# file PDF kecil (dibuat di memori, ~1 KB)
python3 -c "
import base64
pdf = b'%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n' + b'x'*700
open('/tmp/laporan.pdf','wb').write(pdf)
print(base64.b64encode(open('/tmp/laporan.pdf','rb').read()).decode())
" > /tmp/b64.txt
B64=$(cat /tmp/b64.txt)
curl -s -w '\nHTTP %{http_code}\n' -X POST "$BASE/api/submissions/$SUBID/file" \
  -H 'Content-Type: application/json' \
  -d "{\"file_name\":\"laporan.pdf\",\"content_base64\":\"$B64\"}" \
  | tee "$BUKTI/s1b-upload-file.txt" > /tmp/s1b.txt
CODE=$(grep -o 'HTTP [0-9]*' /tmp/s1b.txt | awk '{print $2}')
cek "upload file" 201 "$CODE"
FILEID=$(sed '/^HTTP /d' /tmp/s1b.txt | python3 -c "import json,sys;print(json.load(sys.stdin)['file_id'])")

curl -s -w '\nHTTP %{http_code}\n' "$BASE/api/submissions/$SUBID" \
  | tee "$BUKTI/s1c-get-submission.txt" > /tmp/s1c.txt
CODE=$(grep -o 'HTTP [0-9]*' /tmp/s1c.txt | awk '{print $2}')
cek "ambil detail submission" 200 "$CODE"

curl -s -o /tmp/unduhan.pdf -w 'HTTP %{http_code}\n' "$BASE/api/files/$FILEID" \
  | tee "$BUKTI/s1d-download-file.txt" > /tmp/s1d.txt
CODE=$(grep -o '[0-9]*' /tmp/s1d.txt)
if [ "$CODE" = "200" ] && cmp -s /tmp/laporan.pdf /tmp/unduhan.pdf; then
  echo "PASS: unduh file (HTTP 200, bytes identik)"; PASS=$((PASS+1));
else echo "FAIL: unduh file"; FAIL=$((FAIL+1)); fi

echo; echo "=== Skenario 2: assignment tidak ditemukan -> 404 ==="
curl -s -w '\nHTTP %{http_code}\n' -X POST "$BASE/api/submissions" \
  -H 'Content-Type: application/json' \
  -d '{"assignment_id":"tugas-999","student_id":"240209501100"}' \
  | tee "$BUKTI/s2-assignment-404.txt" > /tmp/s2.txt
CODE=$(grep -o 'HTTP [0-9]*' /tmp/s2.txt | awk '{print $2}')
cek "assignment tidak ditemukan" 404 "$CODE"

echo; echo "=== Skenario 3: input tidak lengkap -> 400 ==="
curl -s -w '\nHTTP %{http_code}\n' -X POST "$BASE/api/submissions" \
  -H 'Content-Type: application/json' \
  -d '{"assignment_id":"tugas-01"}' \
  | tee "$BUKTI/s3-input-400.txt" > /tmp/s3.txt
CODE=$(grep -o 'HTTP [0-9]*' /tmp/s3.txt | awk '{print $2}')
cek "input tidak lengkap" 400 "$CODE"

echo; echo "=== Skenario 4 (bonus): file terlalu besar -> 413 ==="
curl -s -X POST "$BASE/api/submissions" -H 'Content-Type: application/json' \
  -d '{"assignment_id":"tugas-01","student_id":"240209501100"}' | python3 -c "import json,sys;print(json.load(sys.stdin)['id'])" > /tmp/sub4.txt
SUB4=$(cat /tmp/sub4.txt)
python3 -c "
import base64, json
big = base64.b64encode(b'z'*6291456).decode()  # 6 MB > batas 5 MB tugas-01
json.dump({'file_name':'besar.pdf','content_base64':big}, open('/tmp/big.json','w'))
"
curl -s -w '\nHTTP %{http_code}\n' -X POST "$BASE/api/submissions/$SUB4/file" \
  -H 'Content-Type: application/json' \
  -d @/tmp/big.json \
  | tee "$BUKTI/s4-file-413.txt" > /tmp/s4.txt
CODE=$(grep -o 'HTTP [0-9]*' /tmp/s4.txt | awk '{print $2}')
cek "file terlalu besar" 413 "$CODE"

echo; echo "=== Skenario 5 (bonus): tipe file ditolak -> 415 ==="
curl -s -w '\nHTTP %{http_code}\n' -X POST "$BASE/api/submissions/$SUB4/file" \
  -H 'Content-Type: application/json' \
  -d '{"file_name":"virus.exe","content_base64":"TVqQAAMAAAA="}' \
  | tee "$BUKTI/s5-tipe-415.txt" > /tmp/s5.txt
CODE=$(grep -o 'HTTP [0-9]*' /tmp/s5.txt | awk '{print $2}')
cek "tipe file ditolak" 415 "$CODE"

echo; echo "----------------------------------------"
echo "Hasil: $PASS lulus, $FAIL gagal"
echo "Bukti mentah tersimpan di $BUKTI/"
[ "$FAIL" -eq 0 ]
