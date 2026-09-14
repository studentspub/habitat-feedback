# Habitat Feedback

Bewertungsportal für Wohnlagen in Deutschland — Karte + strukturierte Kriterien-Bewertung (kein Freitext) auf Straßenabschnitts-Ebene, DSGVO-bewusst geplant.

Planungsdokumente (Konzept, Rechtsrahmen, technischer Bauplan): siehe Artifact-Links, die im Gesprächsverlauf geteilt wurden. Kurzfassung der Architektur:

- **Stack:** PHP 8 + MariaDB/MySQL, kein Framework (bewusst — kein CLI-Zugriff auf Strato-Zielserver).
- **Granularität:** kleinste sichtbare Einheit ist der Straßenabschnitt, nicht die Hausnummer (Sichtbarkeitsschwellen: ≥ 6 Adressen, ≥ 3 Bewertungen).
- **Karte:** Leaflet.js + OpenStreetMap.
- **Geocoding:** Raspberry Pi als periodischer Worker (Nominatim + Overpass), läuft aktuell nur produktiv später — während der Entwicklung nicht benötigt.

## Entwicklungsumgebung

Läuft komplett in einer separaten WSL2-Distro `Debian`, getrennt von anderen WSL-Umgebungen auf dieser Workstation.

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
