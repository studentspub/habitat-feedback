# Habitat Feedback

Bewertungsportal für Wohnlagen in Deutschland: eine Karte, auf der Menschen ihre eigene Straße anonym nach fünf
Kriterien bewerten (Ruhe, Instandhaltung, Umgangston, Infrastruktur, Sicherheitsgefühl), ergänzt um amtliche
Statistik und offene Geodaten zur Umgebung. Kleinste sichtbare Einheit ist der Straßenabschnitt, nie die Adresse;
Werte erscheinen erst ab mehreren Bewertungen.

Das Projekt ist **unkommerziell** und wird privat als Dienst an der Gesellschaft betrieben: keine Werbung, kein
Verkauf von Daten, kein Konto nötig. Der Code steht unter der **AGPL-3.0**, die Daten kommen aus offenen Quellen.

## Was die Karte zeigt

- Bewertungen je Straßenabschnitt (ab 6 Adressen und 3 Bewertungen sichtbar) mit Farbskala.
- Zensus 2022 als Raster (Einwohnerdichte, Alter, Miete, Eigentümerquote, Leerstand).
- Freizeit und Infrastruktur aus OpenStreetMap (Spielplätze, Kitas und Schulen, Ärzte und Apotheken, Supermärkte,
  Haltestellen, Toiletten, Post und Pakete, E-Bike-Laden) sowie Ladesäulen der Bundesnetzagentur.
- Klima und Luft (DWD, Umweltbundesamt), Breitband, Lärm und Hochwasser als zuschaltbare Ebenen.
- Kreis- und Gemeindekennzahlen (Arbeitsmarkt, Kriminalität, Wohnen, Wirtschaft; BBSR INKAR), ÖPNV-Takt aus GTFS,
  Unfälle aus dem Unfallatlas.

Alle Quellen mit Lizenz: [`DATENQUELLEN.md`](DATENQUELLEN.md) und auf der Seite unter `/quellen`.

## Technik

PHP 8 ohne Framework, MariaDB 10.11 oder neuer (Spatial-Indizes), Leaflet 1.9.4 mit OpenStreetMap-Kacheln. Kein
Build-Schritt, keine externen PHP-Pakete. Die Seite ist für einfaches Webhosting ausgelegt (Apache mit `.htaccess`,
Datenbank per Dump).

```
app/        Controller (app/Http), Fachlogik, Hilfsklassen
bin/        Importskripte für Referenzdaten (einmalig, lokal ausführen)
public/     Web-Root: index.php (Router), Views, js/, css/, vendor/leaflet
sql/        Migrationen 001 ... in Reihenfolge einspielen
worker/     Geocode-Worker (Python), verarbeitet eingereichte Bewertungen
```

### Lokal starten

1. `config/config.example.php` nach `config/config.php` kopieren und Datenbankzugang, `fingerprint_salt` und
   `pi_api_token` eintragen (`config.php` ist git-ignoriert).
2. Datenbank anlegen und die Migrationen aus `sql/` in Nummernreihenfolge einspielen.
3. `php -S 0.0.0.0:8842 -t public` und `http://localhost:8842` öffnen.
4. Referenzdaten nach Bedarf importieren (`php bin/import_*.php`, Quellen und Hinweise in `DATENQUELLEN.md`).
   Ohne Importe läuft die Karte, zeigt aber nur Bewertungen.

## Betrieb auf Webhosting

Die Live-Seite läuft auf einfachem Webhosting (Apache, PHP-FastCGI, MariaDB auf einem Nachbarserver). Deploy per
`bin/deploy_strato.sh` (rsync über SSH): jedes Release liegt in `releases/<zeit>/`, ein Symlink `current` zeigt
auf das aktive, die Einstiegspunkte aus `public/` liegen als Symlinks im Docroot, `config/config.php` bleibt auf dem
Server und wird je Release verlinkt. `--testlauf` zeigt nur, `--los` schaltet live, `--zurueck` geht ein Release
zurück. Ein Push auf `main` deployt nichts. Referenzdaten kommen als Dump (`bin/dump_fuer_strato.sh`), nicht über
Importe auf dem Server.

## Mitmachen

Fehler und Ideen bitte als Issue. Zusammenarbeit mit Claude Code läuft über das Projektboard, Ablauf in
[`ZUSAMMENARBEIT_Claude.md`](ZUSAMMENARBEIT_Claude.md). Sicherheitslücken nicht öffentlich melden, siehe
[`SECURITY.md`](SECURITY.md).

## Lizenzen

- Code: [GNU Affero General Public License v3.0](LICENSE). Wer den Code verändert und als Dienst betreibt, stellt
  seine Änderungen ebenfalls unter diese Lizenz bereit.
- Leaflet: BSD-2-Clause, Lizenztext unter `public/vendor/leaflet-1.9.4/LICENSE`.
- `public/data/de_grenze.geojson`: abgeleitet aus BKG VG250, © GeoBasis-DE / BKG (2023), Datenlizenz Deutschland
  Namensnennung 2.0, siehe `public/data/LIESMICH.txt`.
- Daten in der Datenbank: jeweils Lizenz der Quelle (ODbL, dl-de/by-2.0, CC BY 4.0, Behördenbedingungen), Liste in
  `DATENQUELLEN.md`.
