#!/usr/bin/env bash
# Skrip pengujian Registry & Name Service (implementasi PHP).
# Menyalakan registry (:8100), gateway (:8101), dan mock course (:8103)
# sementara, menjalankan 4 skenario, menyimpan bukti mentah ke docs/bukti/.
# Cara pakai:  bash test_registry.sh
set -u
BUKTI="docs/bukti"
PASS=0; FAIL=0

command -v php >/dev/null 2>&1 || { echo "PHP tidak ditemukan. Install PHP 8+ dulu."; exit 1; }

mkdir -p "$BUKTI"
php -S 127.0.0.1:8100 registry.php      >/tmp/reg.log 2>&1 &  P_REG=$!
php -S 127.0.0.1:8101 gateway.php       >/tmp/gw.log 2>&1 &   P_GW=$!
php -S 127.0.0.1:8103 services/course-mock.php >/tmp/course.log 2>&1 & P_COURSE=$!
trap 'kill $P_REG $P_GW $P_COURSE 2>/dev/null' EXIT

for i in $(seq 1 30); do curl -sf http://127.0.0.1:8100/api/registry >/dev/null 2>&1 && break; sleep 0.3; done

cek() { # cek <nama> <kode_harapan> <kode_dapat>
  if [ "$3" = "$2" ]; then echo "PASS: $1 (HTTP $3)"; PASS=$((PASS+1));
  else echo "FAIL: $1 (harap $2, dapat $3)"; FAIL=$((FAIL+1)); fi
}
code_of() { grep -o 'HTTP [0-9]*' "$1" | awk '{print $2}'; }

echo "=== Skenario 1: lookup nama valid ==="
curl -s -w '\nHTTP %{http_code}\n' http://127.0.0.1:8100/api/registry/course \
  | tee "$BUKTI/s1a-registry-lookup.txt" > /tmp/t1a.txt
cek "registry: nama 'course' ditemukan" 200 "$(code_of /tmp/t1a.txt)"

curl -s -w '\nHTTP %{http_code}\n' http://127.0.0.1:8101/api/gateway/lookup/course \
  | tee "$BUKTI/s1b-gateway-lookup.txt" > /tmp/t1b.txt
cek "gateway: resolve 'course' via fungsi" 200 "$(code_of /tmp/t1b.txt)"

curl -s -w '\nHTTP %{http_code}\n' http://127.0.0.1:8101/api/gateway/course \
  | tee "$BUKTI/s1c-gateway-course.txt" > /tmp/t1c.txt
cek "gateway: teruskan ke course service" 200 "$(code_of /tmp/t1c.txt)"

echo; echo "=== Skenario 2: nama tidak ditemukan ==="
curl -s -w '\nHTTP %{http_code}\n' http://127.0.0.1:8100/api/registry/layanan-aneh \
  | tee "$BUKTI/s2a-registry-404.txt" > /tmp/t2a.txt
cek "registry: nama asing -> 404" 404 "$(code_of /tmp/t2a.txt)"

curl -s -w '\nHTTP %{http_code}\n' http://127.0.0.1:8101/api/gateway/lookup/layanan-aneh \
  | tee "$BUKTI/s2b-gateway-500.txt" > /tmp/t2b.txt
cek "gateway: nama asing -> 500 (error konfigurasi)" 500 "$(code_of /tmp/t2b.txt)"

echo; echo "=== Skenario 3: pindah port TANPA ubah source code ==="
echo "sebelum: $(php -r '$s=json_decode(file_get_contents("services.json"),true); echo $s["course"];')"
# jalankan course di port baru 8203, matikan yang lama, update registry
php -S 127.0.0.1:8203 services/course-mock.php >/tmp/course2.log 2>&1 & P_COURSE2=$!
sleep 1
cp services.json /tmp/services.json.bak
php -r '$s=json_decode(file_get_contents("services.json"),true); $s["course"]="http://127.0.0.1:8203"; file_put_contents("services.json", json_encode($s, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");'
kill $P_COURSE 2>/dev/null
echo "sesudah: $(php -r '$s=json_decode(file_get_contents("services.json"),true); echo $s["course"];')"
echo "(source code gateway & client TIDAK diubah)"

curl -s -w '\nHTTP %{http_code}\n' http://127.0.0.1:8101/api/gateway/course \
  | tee "$BUKTI/s3-ganti-port.txt" > /tmp/t3.txt
cek "gateway: tetap 200 setelah pindah port" 200 "$(code_of /tmp/t3.txt)"
if grep -q '127.0.0.1:8203' /tmp/t3.txt; then
  echo "PASS: gateway memakai alamat BARU (port 8203)"; PASS=$((PASS+1));
else echo "FAIL: gateway tidak memakai alamat baru"; FAIL=$((FAIL+1)); fi
# kembalikan seperti semula
cp /tmp/services.json.bak services.json
kill $P_COURSE2 2>/dev/null
php -S 127.0.0.1:8103 services/course-mock.php >/tmp/course.log 2>&1 & P_COURSE=$!
sleep 1

echo; echo "=== Skenario 4 (bonus): service mati -> 503 ==="
kill $P_COURSE 2>/dev/null
sleep 1
curl -s -w '\nHTTP %{http_code}\n' --max-time 8 http://127.0.0.1:8101/api/gateway/course \
  | tee "$BUKTI/s4-service-503.txt" > /tmp/t4.txt
cek "gateway: course mati -> 503" 503 "$(code_of /tmp/t4.txt)"

echo; echo "----------------------------------------"
echo "Hasil: $PASS lulus, $FAIL gagal"
echo "Bukti mentah tersimpan di $BUKTI/"
[ "$FAIL" -eq 0 ]
