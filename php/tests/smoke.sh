#!/bin/bash
# ProBlog HTTP duman testi.
# Calisan bir sunucuya karsi tum kullanici yolculugunu (kayit -> giris ->
# gonderi -> begeni -> yorum -> CSRF korumasi -> cikis) dener.
#
# Kullanim: bash tests/smoke.sh [http://127.0.0.1:8010]
# Not: sunucunun onceden calisir durumda olmasi gerekir (php -S ... -t php).

set -uo pipefail

BASE="${1:-http://127.0.0.1:8010}"
TMP_DIR=$(mktemp -d)
JAR="$TMP_DIR/cookies.txt"
FAIL=0

pass() { echo "  ok   - $1"; }
fail() { echo "  FAIL - $1"; FAIL=1; }

cleanup() { rm -rf "$TMP_DIR"; }
trap cleanup EXIT

EMAIL="smoketest_$(date +%s)@example.com"

echo "ProBlog duman testi -> $BASE"
echo "test e-postasi: $EMAIL"
echo

echo "Kayit"
curl -s -c "$JAR" "$BASE/register.php" -o "$TMP_DIR/reg.html"
TOKEN=$(grep -o 'name="csrf_token" value="[^"]*"' "$TMP_DIR/reg.html" | head -1 | sed -E 's/.*value="([^"]*)"/\1/')
curl -s -i -c "$JAR" -b "$JAR" \
  -d "csrf_token=$TOKEN" \
  --data-urlencode "name=Smoke Test" \
  --data-urlencode "email=$EMAIL" \
  --data-urlencode "password=test1234" \
  "$BASE/register.php" -o "$TMP_DIR/reg_resp.html"
grep -qi "^Location: /index.php" "$TMP_DIR/reg_resp.html" && pass "kayit basarili, anasayfaya yonlendirildi" || fail "kayit basarisiz"

echo
echo "Anasayfa"
curl -s -b "$JAR" -c "$JAR" "$BASE/index.php" -o "$TMP_DIR/home.html"
grep -q "Ana Sayfa" "$TMP_DIR/home.html" && pass "anasayfa yukleniyor" || fail "anasayfa yuklenemedi"

echo
echo "Gonderi olusturma"
TOKEN2=$(grep -o 'name="csrf_token" value="[^"]*"' "$TMP_DIR/home.html" | head -1 | sed -E 's/.*value="([^"]*)"/\1/')
curl -s -i -c "$JAR" -b "$JAR" \
  -d "csrf_token=$TOKEN2" \
  --data-urlencode "title=Duman testi yazisi" \
  --data-urlencode "content=Bu duman testi tarafindan olusturulan otomatik bir gonderidir, en az yirmi karakter." \
  "$BASE/actions/create_post.php" -o "$TMP_DIR/post_resp.html"
grep -qi "^Location: /index.php" "$TMP_DIR/post_resp.html" && pass "gonderi olusturuldu" || fail "gonderi olusturulamadi"

curl -s -b "$JAR" -c "$JAR" "$BASE/index.php" -o "$TMP_DIR/home2.html"
grep -q "Duman testi yazisi" "$TMP_DIR/home2.html" && pass "gonderi akiste gorunuyor" || fail "gonderi akiste gorunmuyor"

echo
echo "Begeni ve yorum (AJAX)"
POST_ID=$(grep -o 'data-post-id="[^"]*"' "$TMP_DIR/home2.html" | head -1 | sed -E 's/.*"([^"]*)"$/\1/')

LIKE_RESP=$(curl -s -b "$JAR" -c "$JAR" -X POST \
  -H "X-CSRF-Token: $TOKEN2" -H "X-Requested-With: fetch" \
  --data-urlencode "post_id=$POST_ID" \
  "$BASE/actions/like.php")
echo "$LIKE_RESP" | grep -q '"liked":true' && pass "begeni calisiyor" || fail "begeni calismiyor ($LIKE_RESP)"

COMMENT_RESP=$(curl -s -b "$JAR" -c "$JAR" -X POST \
  -H "X-CSRF-Token: $TOKEN2" -H "X-Requested-With: fetch" \
  --data-urlencode "post_id=$POST_ID" \
  --data-urlencode "content=Duman testi yorumu" \
  "$BASE/actions/comment_add.php")
echo "$COMMENT_RESP" | grep -q '"comments_count":1' && pass "yorum calisiyor" || fail "yorum calismiyor ($COMMENT_RESP)"

echo
echo "Guvenlik"
NOCSRF=$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR" -c "$JAR" -X POST \
  -H "X-Requested-With: fetch" \
  --data-urlencode "post_id=$POST_ID" \
  "$BASE/actions/like.php")
[ "$NOCSRF" = "403" ] && pass "CSRF token olmadan istek reddediliyor (403)" || fail "CSRF korumasi calismiyor (kod: $NOCSRF)"

NOAUTH=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/actions/like.php")
[ "$NOAUTH" = "401" ] && pass "oturumsuz istek reddediliyor (401)" || fail "oturum korumasi calismiyor (kod: $NOAUTH)"

echo
echo "Cikis"
curl -s -i -b "$JAR" -c "$JAR" -X POST -d "csrf_token=$TOKEN2" "$BASE/logout.php" -o "$TMP_DIR/logout_resp.html"
grep -qi "^Location: /login.php" "$TMP_DIR/logout_resp.html" && pass "cikis basarili" || fail "cikis basarisiz"

REDIRECTED=$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR" -c "$JAR" "$BASE/index.php")
[ "$REDIRECTED" = "302" ] && pass "cikis sonrasi anasayfaya erisim login'e yonlendiriyor" || fail "cikis sonrasi anasayfa hala erisilebilir (kod: $REDIRECTED)"

echo
if [ "$FAIL" -eq 0 ]; then
  echo "TUMU BASARILI"
  exit 0
fi

echo "BAZI TESTLER BASARISIZ"
exit 1
