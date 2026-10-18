#!/bin/bash
# Deploy nach Strato (Issue #36). Läuft in Git Bash oder WSL aus der Arbeitskopie.
#
#   bin/deploy_strato.sh            Testlauf: zeigt, was übertragen würde, ändert nichts
#   bin/deploy_strato.sh --los      überträgt ein neues Release und schaltet es live
#   bin/deploy_strato.sh --zurueck  schaltet auf das vorherige Release zurück (Rollback)
#   bin/deploy_strato.sh --status   zeigt Releases und das aktive Release
#
# Aufbau auf dem Webspace (Domain-Docroot = $ZIEL, nicht änderbar ohne Panel):
#   $ZIEL/releases/<zeitstempel>/   je Deploy eine Kopie von app/ und public/ (+ LICENSE, readme)
#   $ZIEL/current -> releases/<…>    das aktive Release
#   $ZIEL/config/config.php         Live-Konfiguration, bleibt über Deploys erhalten (jedes Release
#                                   bekommt config -> ../../config)
#   $ZIEL/index.php, .htaccess, js, css, vendor, data, favicon.svg, robots.txt
#                                   Symlinks auf current/public/…  (Apache folgt Symlinks, geprüft #35)
#   Alles andere (releases/, current/, config/) ist per .htaccess gesperrt (Repo-Root-.htaccess, config/.htaccess).
# Ein Push auf main deployt NICHT; dieses Skript ist der bewusste Schritt (CLAUDE.md).
set -euo pipefail

HOST=${STRATO_HOST:-strato}                 # SSH-Alias aus ~/.ssh/config
ZIEL=${STRATO_ZIEL:-habitat-feedback}       # relativ zum Home (= htdocs) auf dem Webspace
BEHALTEN=3                                  # so viele Releases bleiben liegen
# index.php ist KEIN Symlink, sondern eine Kopie von bin/strato_docroot_index.php (OPcache friert __FILE__ eines
# Symlinks ein und liefert sonst nach dem Release-Wechsel das alte Release weiter).
EINSTIEGE=".htaccess js css vendor data favicon.svg robots.txt"
cd "$(dirname "$0")/.."

modus=${1:---testlauf}
case "$modus" in
  --status)
    ssh "$HOST" "cd $ZIEL && echo 'aktiv:' \$(readlink current) && echo 'Releases:' && ls -1 releases 2>/dev/null"
    exit 0 ;;
  --zurueck)
    ssh "$HOST" "cd $ZIEL && akt=\$(readlink current) && vor=\$(ls -1 releases | grep -v \"\${akt#releases/}\" | tail -1) && [ -n \"\$vor\" ] && ln -sfn releases/\$vor current.neu && mv -T current.neu current && echo \"zurück auf \$vor (vorher \$akt)\""
    exit 0 ;;
  --los|--testlauf) ;;
  *) echo "unbekannte Option: $modus (siehe Kopf des Skripts)"; exit 1 ;;
esac

# autocrlf=true: die Arbeitskopie ist von Windows-Git mit CRLF ausgecheckt, WSL-Git soll das nicht als Änderung zählen
if [ -n "$(git -c core.autocrlf=true status --porcelain --untracked-files=no)" ]; then
  echo "Arbeitskopie hat uncommittete Änderungen – erst committen (deployt wird der Stand der Dateien, nicht von GitHub)."; exit 1
fi
rel=$(date +%Y%m%d-%H%M%S)
stand=$(git log --format=%h -1)
trocken=""; [ "$modus" = "--testlauf" ] && trocken="--dry-run"

echo "Release $rel (Commit $stand) -> $HOST:$ZIEL/releases/$rel ${trocken:+[TESTLAUF]}"
[ -z "$trocken" ] && ssh "$HOST" "mkdir -p $ZIEL/releases"   # rsync legt nur die letzte Ebene selbst an
rsync -az $trocken --itemize-changes \
  --include='/app/***' --include='/public/***' --include='/LICENSE' --include='/readme.md' --include='/.htaccess' \
  --include='/bin/' --include='/bin/strato_docroot_index.php' \
  --exclude='*' \
  ./ "$HOST:$ZIEL/releases/$rel/" | grep -v '^\.d' | sed 's/^/  /' | head -60

if [ "$modus" = "--testlauf" ]; then
  echo "Testlauf beendet, nichts geändert. Scharf: bin/deploy_strato.sh --los"
  exit 0
fi

# Argumente einzeln in Anführungszeichen: ssh fügt sie sonst zu einem String zusammen und die Liste zerfällt
ssh "$HOST" "bash -s '$ZIEL' '$rel' '$BEHALTEN' '$EINSTIEGE'" <<'EOF'
set -euo pipefail
ZIEL=$1; rel=$2; behalten=$3; einstiege=$4
cd "$ZIEL"
[ -f config/config.php ] || { echo "config/config.php fehlt auf dem Webspace – zuerst anlegen (#35)."; exit 1; }
printf 'Require all denied\n' > releases/.htaccess   # /releases/… nie direkt ausliefern
rm -f probe_db.php                                   # Rest aus dem Probelauf #35, falls noch da
ln -sfn ../../config "releases/$rel/config"
echo "$rel" > "releases/$rel/RELEASE"
# Release atomar umschalten
ln -sfn "releases/$rel" current.neu && mv -T current.neu current
# Einstiegspunkte aus public/ in den Docroot (ersetzen auch die Probedateien aus #35)
for n in $einstiege; do
  if [ -e "$n" ] && [ ! -L "$n" ]; then rm -rf "$n"; fi
  ln -sfn "current/public/$n" "$n"
done
# index.php als echte Datei (siehe Kommentar oben bei EINSTIEGE); nur ersetzen, wenn sie sich unterscheidet
if [ -L index.php ] || ! cmp -s "releases/$rel/bin/strato_docroot_index.php" index.php; then
  cp "releases/$rel/bin/strato_docroot_index.php" index.php.neu && mv -f index.php.neu index.php && echo "index.php (Docroot-Einstieg) aktualisiert"   # mv ersetzt atomar, keine Lücke
fi
# alte Releases aufräumen, die letzten $behalten bleiben
ls -1dt releases/*/ | tail -n +$((behalten + 1)) | while read -r alt; do rm -rf "$alt"; echo "entfernt: $alt"; done
echo "aktiv: $(readlink current)"
EOF

echo "--- Prüfung"
BASIS=${STRATO_URL:-https://habitat-feedback.de}
for p in / /quellen /projekt "/api/map?bbox=50.9,6.9,51.0,7.0" /releases/ /current/app/Support/Db.php /config/config.php; do
  printf "  %-40s " "$p"
  curl -s -o /dev/null -D - --max-time 20 "$BASIS$p" | awk 'NR==1{code=$2} tolower($1)=="content-security-policy:"{csp="CSP"} tolower($1)=="content-encoding:"{enc=$2} tolower($1)=="x-powered-by:"{xp="X-Powered-By!"} END{print code, csp, enc, xp}'
done
echo "Fertig. Rollback: bin/deploy_strato.sh --zurueck"
