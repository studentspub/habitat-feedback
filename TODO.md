# Offene Punkte

## Design: Praxisbeispiele-Button eckig statt abgerundet

„Tutorial starten" und „Vision entdecken" (`#tutorial-btn`, `#vision-link`
in [map.view.php](public/map.view.php)) sind abgerundete Pill-Buttons
(`border-radius: 20px`, in `#tutorial-btn, #vision-link` deklariert).
„💡 Praxisbeispiele" (`#examples-btn`) wurde separat als eigene Regel
ergänzt und hat dieses `border-radius` nicht mitbekommen — optisch fällt
der dritte Button in der Gruppe `#discover-group` dadurch aus der Reihe.

**Fix:** `#examples-btn` in dieselbe abgerundete Regel wie die anderen
beiden aufnehmen (z.B. Selektor zu `#tutorial-btn, #vision-link,
#examples-btn { border-radius: 20px; ... }` erweitern, eigene
Hintergrundfarbe `#3B7A52` bleibt bestehen).

## Karten-Suche: auf allgemeine Straßensuche ausweiten?

Aktuell durchsucht die Sucheleiste auf der echten Karte
(`/api/search`, [SearchController.php](app/Http/SearchController.php))
nur die eigene DB, gefiltert auf dieselben Sichtbarkeitsschwellen wie die
Karten-Pins (≥6 Adressen, ≥3 Bewertungen) — sie berührt OpenStreetMap/
Nominatim gar nicht.

Der eigentliche Nominatim-Zugriff läuft komplett getrennt über
`/api/address-autocomplete`
([AddressAutocompleteController.php](app/Http/AddressAutocompleteController.php)),
das für das Bewertungsformular *jede* Adresse in Deutschland nachschlagen
kann — bereits abgesichert mit 30-Tage-Cache, Mindestabstand 1,1s
zwischen Live-Anfragen und eigenem User-Agent gemäß Nominatims
Nutzungsrichtlinie.

**Offene Frage für die Diskussion:** Eine Ausweitung der Karten-Suche auf
„jede Straße" könnte technisch den bereits abgesicherten
Autocomplete-Endpunkt mitbenutzen — API-Last ist also vermutlich *kein*
echter Blocker mehr. Der wahrscheinlichere Grund, die Karten-Suche auf
sichtbare Abschnitte zu beschränken, ist die **Sichtbarkeitsschwelle**
selbst: eine allgemeine Suche würde verraten, dass eine Adresse mit z.B.
nur 1–2 Bewertungen (noch unsichtbar) bereits erfasst wird — genau das,
was die Schwelle eigentlich verhindern soll.

Vor einer Umsetzung klären: Ist eine allgemeine Suche gewünscht (mit dem
Trade-off, dass sie indirekt verrät, welche noch-nicht-sichtbaren
Adressen bereits Bewertungen haben), oder soll die Suche bewusst auf
sichtbare Abschnitte beschränkt bleiben?
