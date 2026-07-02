#!/usr/bin/env bash
#
# ChatHelp — Cloudflare cache KALICI çözüm (terminalden tek seferde)
# -----------------------------------------------------------------
# /chat/ için: (1) edge cache bypass, (2) tarayıcıya no-store başlığı,
# (3) mevcut cache'i temizle. Bir kez çalıştır — bir daha cache derdi olmaz.
#
# KULLANIM:
#   1) Aşağıdaki CF_TOKEN satırına Cloudflare API token'ını yapıştır.
#   2) Terminalde:  bash cloudflare-cache-fix.sh
#
set -uo pipefail
# NOT: burada bilerek "set -e" YOK — API bir hata JSON'u dönüp grep deseni
# eşleşmeyince (örn. token izinleri eksikse) script eskiden HİÇBİR HATA
# MESAJI GÖSTERMEDEN sessizce ölüyordu (set -e + pipefail + grep'in boş
# eşleşmede exit 1 vermesi). Şimdi her adım kendi hata kontrolünü yapıyor
# ve başarısızsa Cloudflare'in HAM cevabını (gerçek hata mesajını) basıyor.

# ====== DOLDUR (veya CF_TOKEN'i ortam değişkeni olarak ver) ======
CF_TOKEN="${CF_TOKEN:-BURAYA_API_TOKEN_YAPISTIR}"
DOMAIN="${DOMAIN:-chat-help.com}"
# =================================================================

API="https://api.cloudflare.com/client/v4"
HDRS=(-H "Authorization: Bearer ${CF_TOKEN}" -H "Content-Type: application/json")

if [ "${CF_TOKEN}" = "BURAYA_API_TOKEN_YAPISTIR" ]; then
  echo "HATA: Önce CF_TOKEN satırına Cloudflare API token'ını yapıştır (veya ortam değişkeni olarak ver)."
  exit 1
fi

echo "→ Zone ID alınıyor (${DOMAIN})..."
ZONE_RAW=$(curl -s "${HDRS[@]}" "${API}/zones?name=${DOMAIN}")
ZONE=$(printf '%s' "${ZONE_RAW}" | grep -o '"id":"[a-f0-9]\{32\}"' | head -1 | cut -d'"' -f4)

if [ -z "${ZONE}" ]; then
  echo "HATA: Zone bulunamadı. Token izinleri / domain adı doğru mu?"
  echo "Cloudflare'in ham cevabı (gerçek hata burada):"
  echo "${ZONE_RAW}"
  exit 1
fi
echo "  Zone: ${ZONE}"

echo "→ 1/3: Edge cache bypass kuralı (/chat)..."
R1=$(curl -s -X PUT "${HDRS[@]}" \
  "${API}/zones/${ZONE}/rulesets/phases/http_request_cache_settings/entrypoint" \
  --data '{"rules":[{"expression":"(starts_with(http.request.uri.path, \"/chat\"))","action":"set_cache_settings","action_parameters":{"cache":false}}]}')
echo "${R1}" | grep -o '"success":[a-z]*' | head -1
if ! printf '%s' "${R1}" | grep -q '"success":true'; then
  echo "  ⚠ Basarisiz. Ham cevap:"; echo "${R1}"
fi

echo "→ 2/3: Tarayıcı no-store başlık kuralı (/chat)..."
R2=$(curl -s -X PUT "${HDRS[@]}" \
  "${API}/zones/${ZONE}/rulesets/phases/http_response_headers_transform/entrypoint" \
  --data '{"rules":[{"expression":"(starts_with(http.request.uri.path, \"/chat\"))","action":"rewrite","action_parameters":{"headers":{"Cache-Control":{"operation":"set","value":"no-store, no-cache, must-revalidate"}}}}]}')
echo "${R2}" | grep -o '"success":[a-z]*' | head -1
if ! printf '%s' "${R2}" | grep -q '"success":true'; then
  echo "  ⚠ Basarisiz. Ham cevap:"; echo "${R2}"
fi

echo "→ 3/3: Mevcut cache temizleniyor (purge everything)..."
R3=$(curl -s -X POST "${HDRS[@]}" \
  "${API}/zones/${ZONE}/purge_cache" \
  --data '{"purge_everything":true}')
echo "${R3}" | grep -o '"success":[a-z]*' | head -1
if ! printf '%s' "${R3}" | grep -q '"success":true'; then
  echo "  ⚠ Basarisiz. Ham cevap:"; echo "${R3}"
fi

echo ""
echo "✅ Bitti. Artık /chat/ her zaman taze sürüm verecek (edge + tarayıcı)."
echo "Test: gizli pencerede  https://chat-help.com/chat/?t=$(date +%s)"
echo ""
echo "NOT: Üç satırda da 'success:true' görmen lazım. 'Basarisiz' yazan bir adım"
echo "     varsa yukarıdaki ham cevabı bana yapıştır — tam olarak hangi izin"
echo "     eksik net görünür."
