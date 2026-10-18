#!/bin/bash
# Dump der Referenzdaten für die Strato-Datenbank (Issue #37). Läuft in WSL (Dev-DB) oder im Container.
#
#   bin/dump_fuer_strato.sh                 alle Tabellen: Schema komplett, Daten nur der Referenztabellen
#   bin/dump_fuer_strato.sh poi_locations   nur diese Tabelle(n) (Daten-Update, z. B. neue POI-Kategorien)
#   Umgebung: DB (Standard habitat_feedback_dev), ZIEL (Standard /srv/daten/strato), MYSQL (Standard "sudo mysql")
#
# Ergebnis in $ZIEL: 00_schema.sql.gz (alle CREATE TABLE, mit DROP TABLE IF EXISTS) und je Referenztabelle
# NN_<tabelle>.sql.gz (nur Daten). Bewertungs- und Cache-Tabellen gehen ohne Daten mit: die Live-Seite startet
# mit null Bewertungen. Import auf dem Webspace: siehe BETRIEB.md (privat), Reihenfolge nach Dateiname.
set -euo pipefail
DB=${DB:-habitat_feedback_dev}
ZIEL=${ZIEL:-/srv/daten/strato}
MYSQL=${MYSQL:-sudo mysql}
MYSQLDUMP=${MYSQLDUMP:-sudo mysqldump}
OHNE_DATEN="segments segment_stats ratings geocode_queue reports rate_limit_hits air_quality_cache kreis_lookup_cache address_autocomplete_cache"

mkdir -p "$ZIEL"
alle=$($MYSQL -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema='$DB' AND table_type='BASE TABLE' ORDER BY table_name" "$DB")
if [ $# -gt 0 ]; then
  tabellen="$*"
else
  tabellen=""
  for t in $alle; do
    case " $OHNE_DATEN " in *" $t "*) ;; *) tabellen="$tabellen $t";; esac
  done
  echo "Schema aller Tabellen -> $ZIEL/00_schema.sql.gz"
  # Schema mit DROP TABLE IF EXISTS; utf8mb4 steht in jedem CREATE TABLE, die DB-Voreinstellung auf Strato (utf8mb3) stört nicht
  { echo "SET FOREIGN_KEY_CHECKS=0;"; $MYSQLDUMP --no-data --skip-add-locks --skip-comments --no-tablespaces "$DB"; echo "SET FOREIGN_KEY_CHECKS=1;"; } | gzip > "$ZIEL/00_schema.sql.gz"
fi

n=10
for t in $tabellen; do
  datei=$(printf '%s/%02d_%s.sql.gz' "$ZIEL" "$n" "$t")
  zeilen=$($MYSQL -N -e "SELECT COUNT(*) FROM \`$t\`" "$DB")
  printf '%-26s %10s Zeilen -> %s ... ' "$t" "$zeilen" "$(basename "$datei")"
  $MYSQLDUMP --no-create-info --single-transaction --quick --skip-add-locks --skip-comments --no-tablespaces \
    --extended-insert --net-buffer-length=1000000 "$DB" "$t" | gzip > "$datei"
  echo "$(du -h "$datei" | cut -f1)"
  n=$((n + 1))
done
echo "Fertig: $(ls "$ZIEL"/*.sql.gz | wc -l) Dateien, $(du -sh "$ZIEL" | cut -f1) gesamt"
