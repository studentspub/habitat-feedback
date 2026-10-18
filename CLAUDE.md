# Habitat Feedback

Bewertungsportal für Wohnlagen in Deutschland — Karte + strukturierte Kriterien-Bewertung (kein Freitext) auf Straßenabschnitts-Ebene, DSGVO-bewusst geplant.

**Name:** Die Seite heißt **Habitat Feedback** (technisch `habitat-feedback`). Der frühere Projektname ist als Marke einer anderen Seite vergeben und wird nirgends mehr verwendet (Entscheidung des Nutzers 14.10.2026). Auch die internen Namen (Container, `/srv`-Pfad, DB, systemd-Units, SSH-Alias, WSL) sind seit 14.10.2026 umgestellt (Issue #25). Neue Dateien, Dienste, Tabellen usw. nie mit dem alten Namen anlegen.

**Ziel Strato, Entwicklung lokal:** Entwickelt und getestet wird in WSL und im Homelab-Container, **Ziel ist die Veröffentlichung auf Strato-Webhosting** (Nutzer 18.10.2026; bis dahin galt „nur lokal“). Deshalb muss jede Änderung dort laufen: PHP 8 ohne Erweiterungen jenseits des Strato-Standards, MariaDB 10.11 (nicht MySQL 8), keine Server-Konfiguration (`my.cnf`, Apache-Module) voraussetzen, Importe lokal und als Dump nach Strato, DB-Größe unter 2 GB je Datenbank im Blick (Stand 18.10.: 1,35 GB). Was nur im Container geht (z. B. InnoDB-Puffer, #29), wird als solches gekennzeichnet. Sammelpunkt für den Livegang: Issue #16.

**Board:** Aufträge laufen über GitHub-Projekt 3 „Habitat Feedback“ — Spalten, Labels und Issue-Vorlage in [`ZUSAMMENARBEIT_Claude.md`](ZUSAMMENARBEIT_Claude.md). Neue Datenquellen erst in [`DATENQUELLEN.md`](DATENQUELLEN.md) bewerten. Ergebnis-Kommentar im Issue mit `Ergebnis` / `Wo` / `Bitte prüfen` / `Offen`.

Planungsdokumente (Konzept, Rechtsrahmen, technischer Bauplan): siehe Artifact-Links, die im Gesprächsverlauf geteilt wurden. Kurzfassung der Architektur:

- **Stack:** PHP 8 + MariaDB/MySQL, kein Framework (bewusst — kein CLI-Zugriff auf Strato-Zielserver).
- **Granularität:** kleinste sichtbare Einheit ist der Straßenabschnitt, nicht die Hausnummer (Sichtbarkeitsschwellen: ≥ 6 Adressen, ≥ 3 Bewertungen).
- **Karte:** Leaflet.js + OpenStreetMap.
- **Geocoding:** Raspberry Pi als periodischer Worker (Nominatim + Overpass), läuft aktuell nur produktiv später — während der Entwicklung nicht benötigt.
- **Adress-Vorschläge beim Tippen** nur aus dem eigenen Straßenverzeichnis (Tabelle `strassen`, `bin/import_strassen.php`, #15), nie live von Nominatim (Nutzungsrichtlinie). Nominatim fragt nur der Worker, einmal je abgeschickter Bewertung.

## Entwickeln in WSL, Container ist nur Testserver (Nutzer 14.10.2026)

- **Entwickelt und getestet wird in WSL-Debian** (`http://localhost:8842`, Code wirkt sofort beim Speichern, siehe unten). Erst wenn es dort läuft: committen + pushen — der Container holt sich den Stand dann selbst.
- Daten und Importe für die Entwicklung gehen in die **WSL-DB**; WSL während langer Läufe wachhalten (`wsl -d Debian -- sleep infinity`, sonst Idle-Stopp nach ~90 s). Im Container wird nur nachgezogen, was der Testserver braucht (Migration + Import).
- Rohdaten-Downloads (VG250, INKAR, OSM-Extrakt …) in WSL unter `/srv/daten/`, nicht ins Repo.

## Test-Server im Homelab (seit 11.10.2026)

Die Seite läuft 24/7 im Proxmox-Container **LXC 120 `habitat-feedback`** (Debian 13, Apache + PHP 8.4, MariaDB 11.8) — Testserver im Heimnetz (Abnahme, andere Geräte), nicht die Entwicklungsumgebung. Details und Befehle: `BETRIEB.md` im privaten Repo `studentspub/habitat-feedback-betrieb` (lokal `D:\git\habitat-feedback-betrieb`), Abschnitt „Test-Server im Homelab".

- Aufruf im Heimnetz: `http://habitat-feedback.fritz.box/` (auch `:8842`); IP und weitere Adressen stehen nur in der privaten Betriebsdoku.
- Claude-Zugriff: `ssh habitat-feedback-lxc "…"` (Nutzer `dev`, passwortloses sudo).
- Repo im Container: `/srv/habitat-feedback`, holt sich `main` alle 2 Minuten selbst von GitHub (`habitat-feedback-pull.timer`). Code-Änderungen sind dort also erst **nach Commit + Push** sichtbar, nicht schon beim Speichern wie in WSL.
- Neue `sql/0xx_*.sql`-Migrationen werden **nicht** automatisch eingespielt: `ssh habitat-feedback-lxc "sudo mysql habitat_feedback_dev < /srv/habitat-feedback/sql/0xx_….sql"`.
- Importe (`bin/import_*.php`) laufen zuerst in WSL (Dev-DB) und werden danach im Container nachgezogen; dort gibt es keinen Idle-Stopp.
- Der Container hängt nur an `vmbr0` (Heimnetz), **nie** an `vmbr1` (Lab).

## Entwicklungsumgebung (WSL) — hier wird entwickelt

Läuft komplett in einer separaten WSL2-Distro `Debian`, getrennt von anderen WSL-Umgebungen auf dieser Workstation. Hauptumgebung für Entwicklung und Tests (Nutzer 14.10.2026). Die Datenbanken von WSL und Container laufen **getrennt**; neue Migrationen/Importe in beiden einspielen.

- Start: `wsl -d Debian`
- Repo-Pfad in WSL: `/mnt/d/git/habitat-feedback` (= `D:\git\habitat-feedback`, kein Sync nötig)
- Dev-Server: `php -S 0.0.0.0:8842 -t public` → erreichbar unter `http://localhost:8842`
  - **Port 8842, nicht 8000** — 8000 wird vom Nutzer anderweitig genutzt.
- Dev-Datenbank: MariaDB, DB `habitat_feedback_dev`, Nutzer `habitat_feedback` (Zugangsdaten in `config/config.php`, git-ignoriert; Vorlage: `config/config.example.php`)
- MariaDB startet automatisch beim Distro-Boot (`/etc/wsl.conf`).

Der Raspberry Pi des Nutzers ist bereits mit anderen Diensten belastet (Genealogie-Website, tägliches Update von studentspub.com) — für die Entwicklungsphase **nicht** mitbenutzen.

## Git-Workflow

- **Direkt auf `main` committen und pushen**, keine Feature-Branches/PRs nötig (Solo-Projekt, ausdrücklich vom Nutzer so gewünscht).
- Kleine, saubere Commits nach jedem sinnvollen Arbeitsschritt — automatisch, ohne vorher nachzufragen.
- Ein Push auf `main` löst **keinen** automatischen Live-Deploy aus — der Strato-Deploy-Workflow ist bewusst auf manuellen `workflow_dispatch`-Trigger gestellt (siehe Bauplan-Dokument, Abschnitt „Livegang"). Pushen auf `main` ist daher risikofrei.
- `config/config.php` niemals committen (per `.gitignore` ausgeschlossen).

## Weitere Dokumente

- `BETRIEB.md` (Befehle für WSL, Container, MariaDB, Importe, Troubleshooting) und `Ideas.md` (Ideensammlung) liegen seit 18.10.2026 im privaten Repo `studentspub/habitat-feedback-betrieb` (lokal `D:\git\habitat-feedback-betrieb`), weil sie Heimnetz-Details enthalten; dieses Repo ist seit dem 18.10.2026 öffentlich (Issue #32/#38), die Seite läuft unter https://habitat-feedback.de (Deploy: `bin/deploy_strato.sh`, Ablauf in der privaten Betriebsdoku). Dort nachlesen, hier nicht neu anlegen.
- `_INFRASTRUKTUR.local.txt` — **NICHT gesynct** (per `.gitignore`). Enthält alle Zugangsdaten/Infrastruktur-Details im Klartext, rein für den Nutzer. Bei jeder neuen Zugangsdaten-relevanten Änderung (neue DB, neuer Service, neuer Token) dort mitpflegen — niemals in eine gesyncte Datei oder in Code schreiben.
