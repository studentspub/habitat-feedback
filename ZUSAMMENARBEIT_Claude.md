# Zusammenarbeit mit Claude über GitHub Projects

Für jeden neuen Chat: **zuerst `CLAUDE.md` in diesem Repo lesen** (Claude Code lädt sie automatisch). Diese Datei
beschreibt nur das Board und wie Issues für Claude geschrieben werden. Vorbild ist das Board des AI-Projekts
(`D:\Skripte\ZUSAMMENARBEIT_Claude.md`), Spalten, Felder und Ansichten sind von dort kopiert.

Stand: 14.10.2026. Repo `studentspub/habitat-feedback`, **Projekt 3 „Habitat Feedback“**
(https://github.com/users/studentspub/projects/3).

## 0. Rahmen

- **Ziel ist die Veröffentlichung auf Strato** (User 18.10.2026; vom 14. bis 18.10. galt „nur lokal“). Entwickelt
  und getestet wird weiter in WSL und im Homelab-Container. Jede Änderung muss Strato-tauglich sein (siehe
  `CLAUDE.md`); Livegang-Themen (Strato-Probelauf, Cloudflare, Impressum-Adressdienst, DB-Größe, Latenz) sammelt
  Issue #16 und wird bei Bedarf in eigene Issues aufgeteilt.
- **Sprache:** Issues, Kommentare, Board-Texte und Commit-Messages auf Deutsch.
- Commits direkt auf `main` (siehe `CLAUDE.md`). Ein Push ist nach spätestens 2 Minuten im Container sichtbar.

## 1. Das Board

| Spalte | Bedeutung |
|---|---|
| Todo / In progress / Done / Future toDo | Arbeit des Users bzw. Ideen für später (Future toDo) |
| Claude ToDo | Aufträge für Claude, Reihenfolge nach Feld **Priority** (P0 = als Nächstes, P1 = diese Woche, P2 = irgendwann), bei Gleichstand das Älteste zuerst |
| Claude In Progress | Claude arbeitet daran |
| Review | Claude ist fertig, der User nimmt ab (auf der Seite ansehen) und schließt das Issue |
| Claude Done | Claude fertig, keine Abnahme nötig (Doku, Recherche, interne Umbauten ohne sichtbare Änderung) |
| Template | Vorlagen - nie abarbeiten, nie schließen |

Feld **Size** = Aufwand für Claude inkl. Importlaufzeit: XS unter 15 min, S unter 1 h, M bis ein halber Tag,
L ein Tag, XL mehrere Tage.

Ansichten (aus dem AI-Board kopiert): „Meine Arbeit“, „Claude“, „Fragen an mich“, „Vorlagen“, „Backlog“.
Die Ansicht „Rdy to poste“ gehört zum AI-Projekt und kann gelöscht werden (geht nur in der Web-Oberfläche).

Labels: `ForClaude` (Auftrag für Claude), `QuestionToMeFromClaude` (Claude wartet auf eine Antwort),
`DoneByClaude` / `AssistetByClaude`, `Template`, dazu Themen-Labels: `Frontend`, `Backend`, `Datenquelle`,
`Datenbank`, `Homelab`, `Sicherheit`, `Performance`, `Design`, `documentation`, `bug`.

## 2. Ablauf für Claude

1. Der User sagt im Chat, welcher Auftrag dran ist (kein automatischer Start).
2. Beim Start: Status `Claude In Progress`.
3. Unklar oder Entscheidung des Users: Frage als Kommentar + Label `QuestionToMeFromClaude`, am Rest weiterarbeiten.
4. **Ergebnis-Kommentar** im Issue mit vier Überschriften: `Ergebnis` / `Wo` / `Bitte prüfen` / `Offen`.
5. Abschluss: etwas zum Ansehen auf der Seite -> Status `Review`, Issue offen lassen. Sonst `Claude Done` + schließen.
6. Hauptissues mit offenen Unter-Issues bleiben offen; Ergebnisse gehören ins Unter-Issue.

## 3. Neue Issues für Claude schreiben

So aufbauen, dass ein Chat ohne Vorwissen sie abarbeiten kann. „Zuerst lesen“ kurz halten.

```
### <Titel: was am Ende da sein soll>
**Warum:** 1-3 Sätze - wozu, was ist der Anlass
**Zuerst lesen:** Dateien/Issues, die man vorher kennen muss
**Eingabe:** Dateien, Pfade, Datenquellen (ausgeschrieben, nicht „wie letztes Mal“)
**Schritte:** nummeriert; was mit alten Daten passiert
**Fertig wenn:** woran man erkennt, dass es fertig ist
**Übergabe:** Review oder Claude Done
```

Danach: Label `ForClaude` + Themen-Label, ins Projekt 3, Status `Claude ToDo`, Priority und Size setzen.

Neue Datenquellen: erst in [`DATENQUELLEN.md`](DATENQUELLEN.md) bewerten (Lizenz, Raumebene, Nutzen), dann Issue.
`Ideas.md` bleibt die Sammelstelle für Ideen; sobald eine Idee ein Issue hat, steht dort der Issue-Link.
