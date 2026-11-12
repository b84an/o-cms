#!/bin/bash
# Crea il pacchetto di release per l'updater integrato.
# Uso: tools/build-release.sh 2.0.0
# Produce dist/o-cms-<versione>.zip e dist/o-cms-<versione>.zip.sha256:
# caricare ENTRAMBI come asset della release GitHub (tag v<versione>),
# altrimenti l'updater rifiuta l'aggiornamento.
set -euo pipefail
cd "$(dirname "$0")/.."
VER="${1:?versione mancante, es. 2.0.0}"
grep -q "define('OCMS_VERSION', '$VER')" index.php || { echo "OCMS_VERSION in index.php non è $VER"; exit 1; }
grep -q "define('OCMS_VERSION', '$VER')" admin/index.php || { echo "OCMS_VERSION in admin/index.php non è $VER"; exit 1; }
[ -z "$(git status --porcelain)" ] || { echo "Ci sono modifiche non committate"; exit 1; }
for f in $(git ls-files '*.php'); do php -l "$f" >/dev/null || { echo "Errore di sintassi: $f"; exit 1; }; done
mkdir -p dist
ZIP="o-cms-$VER.zip"
git archive --format=zip --prefix=o-cms/ -o "dist/$ZIP" HEAD
( cd dist && sha256sum "$ZIP" > "$ZIP.sha256" )
cat "dist/$ZIP.sha256"
