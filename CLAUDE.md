# Habitat Feedback

Bewertungsportal für Wohnlagen in Deutschland — Karte + strukturierte Kriterien-Bewertung (kein Freitext) auf Straßenabschnitts-Ebene, DSGVO-bewusst geplant.

Planungsdokumente (Konzept, Rechtsrahmen, technischer Bauplan): siehe Artifact-Links, die im Gesprächsverlauf geteilt wurden. Kurzfassung der Architektur:

- **Stack:** PHP 8 + MariaDB/MySQL, kein Framework (bewusst — kein CLI-Zugriff auf Strato-Zielserver).
- **Granularität:** kleinste sichtbare Einheit ist der Straßenabschnitt, nicht die Hausnummer (Sichtbarkeitsschwellen: ≥ 6 Adressen, ≥ 3 Bewertungen).
- **Karte:** Leaflet.js + OpenStreetMap.
- **Geocoding:** Raspberry Pi als periodischer Worker (Nominatim + Overpass), läuft aktuell nur produktiv später — während der Entwicklung nicht benötigt.

## Test-Server im Homelab (seit 11.10.2026)

Die Seite läuft 24/7 im Proxmox-Container **LXC 120 `habitat-feedback`** (Debian 13, Apache + PHP 8.4, MariaDB 11.8) und ersetzt WSL-Debian als Testserver. Details und Befehle: [`BETRIEB.md`](BETRIEB.md), Abschnitt „Test-Server im Homelab".

- Aufruf: `http://<interne-adresse>/` (auch `:8842`), im ganzen Heimnetz.
- Claude-Zugriff: `ssh habitat-feedback-lxc "…"` (Nutzer `dev`, passwortloses sudo).
- Repo im Container: `/srv/habitat-feedback`, holt sich `main` alle 2 Minuten selbst von GitHub (`habitat-feedback-pull.timer`). Code-Änderungen sind dort also erst **nach Commit + Push** sichtbar, nicht schon beim Speichern wie in WSL.
- Neue `sql/0xx_*.sql`-Migrationen werden **nicht** automatisch eingespielt: `ssh habitat-feedback-lxc "sudo mysql habitat_feedback_dev < /srv/habitat-feedback/sql/0xx_….sql"`.
- Langlaufende Imports (`bin/import_*.php`) gehören in den Container, nicht nach WSL (kein 90-Sekunden-Idle-Stopp).
- Der Container hängt nur an `vmbr0` (Heimnetz), **nie** an `vmbr1` (Lab).

## Entwicklungsumgebung (WSL, weiterhin nutzbar)

Läuft komplett in einer separaten WSL2-Distro `Debian`, getrennt von anderen WSL-Umgebungen auf dieser Workstation. Seit 11.10.2026 nicht mehr der Haupt-Testserver (siehe oben), bleibt aber als lokale Umgebung bestehen. Die beiden Datenbanken laufen ab der Umstellung **getrennt** auseinander.

- Start: `wsl -d Debian`
- Repo-Pfad in WSL: `/mnt/d/git/habitat-feedback` (= `D:\git\habitat-feedback`, kein Sync nötig)
- Dev-Server: `php -S 0.0.0.0:8842 -t public` → erreichbar unter `http://localhost:8842`
  - **Port 8842, nicht 8000** — 8000 wird vom Nutzer anderweitig genutzt.
- Dev-Datenbank: MariaDB, DB `habitat_feedback_dev`, Nutzer `habitat-feedback` (Zugangsdaten in `config/config.php`, git-ignoriert; Vorlage: `config/config.example.php`)
- MariaDB startet automatisch beim Distro-Boot (`/etc/wsl.conf`).

Der Raspberry Pi des Nutzers ist bereits mit anderen Diensten belastet (Genealogie-Website, tägliches Update von studentspub.com) — für die Entwicklungsphase **nicht** mitbenutzen.

## Git-Workflow

- **Direkt auf `main` committen und pushen**, keine Feature-Branches/PRs nötig (Solo-Projekt, ausdrücklich vom Nutzer so gewünscht).
- Kleine, saubere Commits nach jedem sinnvollen Arbeitsschritt — automatisch, ohne vorher nachzufragen.
- Ein Push auf `main` löst **keinen** automatischen Live-Deploy aus — der Strato-Deploy-Workflow ist bewusst auf manuellen `workflow_dispatch`-Trigger gestellt (siehe Bauplan-Dokument, Abschnitt „Livegang"). Pushen auf `main` ist daher risikofrei.
- `config/config.php` niemals committen (per `.gitignore` ausgeschlossen).

## Weitere Dokumente

- [`BETRIEB.md`](BETRIEB.md) — gesynct. Gängige manuelle Befehle (WSL starten/stoppen, Dev-Server, MariaDB, Troubleshooting).
- `_INFRASTRUKTUR.local.txt` — **NICHT gesynct** (per `.gitignore`). Enthält alle Zugangsdaten/Infrastruktur-Details im Klartext, rein für den Nutzer. Bei jeder neuen Zugangsdaten-relevanten Änderung (neue DB, neuer Service, neuer Token) dort mitpflegen — niemals in eine gesyncte Datei oder in Code schreiben.
