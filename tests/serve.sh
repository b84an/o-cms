#!/bin/bash
# Uso: tests/serve.sh <cartella-dati> [porta] [cartella-tema-extra]
# Copia il repo in una docroot temporanea sotto /s, con una COPIA dei dati, e avvia php -S.
set -e
REPO="$(cd "$(dirname "$0")/.." && pwd)"
DATA="$1"; PORT="${2:-8099}"; EXTRA_THEME="$3"
DOC="${OCMS_TEST_DOC:-/tmp/ocms-test-$PORT}"
rm -rf "$DOC"; mkdir -p "$DOC/s"
(cd "$REPO" && tar --exclude=.git --exclude=tests --exclude=docs -cf - .) | tar -xf - -C "$DOC/s"
rm -rf "$DOC/s/data"; cp -a "$DATA" "$DOC/s/data"; touch "$DOC/s/data/.debug"
[ -n "$EXTRA_THEME" ] && cp -a "$EXTRA_THEME" "$DOC/s/themes/"
cp "$REPO/tests/router.php" "$DOC/router.php"
echo "docroot $DOC porta $PORT"
exec "${PHP:-php}" -d display_errors=1 -d opcache.enable=0 -S 127.0.0.1:$PORT -t "$DOC" "$DOC/router.php"
