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
set -euo pipefail

# ====== DOLDUR (veya CF_TOKEN'i ortam değişkeni olarak ver) ======
CF_TOKEN="${CF_TOKEN:-BURAYA_API_TOKEN_YAPISTIR}"
DOMAIN="${DOMAIN:-chat-help.com}"
# =================================================================

API="https://api.cloudflare.com/client/v4"
HDRS=(-H "Authorization: Bearer ${CF_TOKEN}" -H "Content-Type: application/json")

if [ "${CF_TOKEN}" = "BURAYA_API_TOKEN_YAPISTIR" ]; then
  echo "HATA: Önce CF_TOKEN satırına Cloudflare API token'ını yapıştır."
  exit 1
fi

echo "→ Zone ID alınıyor (${DOMAIN})..."
ZONE=$(curl -s "${HDRS[@]}" "${API}/zones?name=${DOMAIN}" \
  | grep -o '"id":"[a-f0-9]\{32\}"' | head -1 | cut -d'"' -f4)

if [ -z "${ZONE}" ]; then
  echo "HATA: Zone bulunamadı. Token izinleri / domain adı doğru mu?"
  exit 1
fi
echo "  Zone: ${ZONE}"

echo "→ 1/3: Edge cache bypass kuralı (/chat)..."
curl -s -X PUT "${HDRS[@]}" \
  "${API}/zones/${ZONE}/rulesets/phases/http_request_cache_settings/entrypoint" \
  --data '{"rules":[{"expression":"(starts_with(http.request.uri.path, \"/chat\"))","action":"set_cache_settings","action_parameters":{"cache":false}}]}' \
  | grep -o '"success":[a-z]*' | head -1

echo "→ 2/3: Tarayıcı no-store başlık kuralı (/chat)..."
curl -s -X PUT "${HDRS[@]}" \
  "${API}/zones/${ZONE}/rulesets/phases/http_response_headers_transform/entrypoint" \
  --data '{"rules":[{"expression":"(starts_with(http.request.uri.path, \"/chat\"))","action":"rewrite","action_parameters":{"headers":{"Cache-Control":{"operation":"set","value":"no-store, no-cache, must-revalidate"}}}}]}' \
  | grep -o '"success":[a-z]*' | head -1

echo "→ 3/3: Mevcut cache temizleniyor (purge everything)..."
curl -s -X POST "${HDRS[@]}" \
  "${API}/zones/${ZONE}/purge_cache" \
  --data '{"purge_everything":true}' \
  | grep -o '"success":[a-z]*' | head -1

echo ""
echo "✅ Bitti. Artık /chat/ her zaman taze sürüm verecek (edge + tarayıcı)."
echo "Test: gizli pencerede  https://chat-help.com/chat/?t=$(date +%s)"
echo ""
echo "NOT: Üç satırda da 'success:true' görmen lazım. 'success:false' varsa"
echo "     token izinleri eksik demektir — bana çıktıyı yapıştır."
