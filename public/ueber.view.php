<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Über Habitat Feedback</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<style>
  body { font-family: sans-serif; max-width: 40em; margin: 2em auto; padding: 0 1em; color: #1B2420; background: #F2F4EF; line-height: 1.6; }
  h1 { font-size: 1.4rem; }
  h2 { font-size: 1.1rem; margin-top: 1.8em; }
  a.back { display: inline-block; margin-bottom: 1em; }
  footer { margin-top: 3em; font-size: 0.8em; }
  footer a { color: #31576D; margin-right: 12px; }
</style>
</head>
<body>
<a class="back" href="/">&larr; zurück zur Karte</a>
<h1>Über Habitat Feedback</h1>
<p>Habitat Feedback zeigt, wie es sich an einer Wohnlage tatsächlich lebt — auf Basis strukturierter Bewertungen echter Bewohner, nicht auf Basis dessen, was Makler oder Vermieter erzählen.</p>

<h2>Warum Kriterien statt Freitext?</h2>
<p>Keine Kommentare, sondern fünf feste Kriterien von 1 bis 5: Ruhe, Instandhaltung, Umgangston im Viertel, Infrastruktur, Sicherheitsgefühl. Das ist nicht nur datenschutzfreundlicher, sondern hat auch Rechtsprechung im Rücken — der BGH hat 2009 in Sachen spickmich.de entschieden, dass Bewertungen anhand fester, sachlicher Kriterien zulässig sind, solange keine unwahren Tatsachenbehauptungen oder Beleidigungen im Spiel sind.</p>

<h2>Warum Straßenabschnitt statt Hausnummer?</h2>
<p>Eine einzelne Bewertung für ein Einfamilienhaus wäre faktisch eine Bewertung der dort wohnenden Person, nicht der Wohnlage. Deshalb ist die kleinste sichtbare Einheit der <strong>Straßenabschnitt</strong> — und selbst der wird erst ab mindestens 6 unterschiedlichen Adressen <em>und</em> mindestens 3 unabhängigen Bewertungen angezeigt. Beide Schwellen müssen erreicht sein, weil sie unterschiedliche Risiken abdecken: genug Gebäude, damit kein Einzelhaus durchscheint, und genug Stimmen, damit keine Einzelperson erkennbar wird.</p>

<h2>Wie wird Mehrfachbewertung verhindert?</h2>
<p>Ganz ohne Konto: ein technisches Signal aus einem Teil deiner IP-Adresse und deinem Browser wird mit dem jeweiligen Straßenabschnitt verrechnet und als nicht zurückrechenbarer Hash gespeichert. Dasselbe Gerät kann einen Abschnitt so nur alle 12 Monate neu bewerten — aber für verschiedene Abschnitte entstehen unterschiedliche, nicht miteinander verknüpfbare Werte.</p>

<h2>Woher kommen die Karten- und Adressdaten?</h2>
<p>Von <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>-Mitwirkenden — die Kachelanzeige, die Geokodierung (Nominatim) und die Ermittlung, wie viele Adressen ein Abschnitt hat (Overpass), laufen alle über offene, quelloffene Dienste des OpenStreetMap-Projekts. Die Straßen-Vorschläge beim Tippen kommen aus einem eigenen Straßenverzeichnis, das wir aus dem OpenStreetMap-Datensatz für Deutschland (über <a href="https://download.geofabrik.de" target="_blank" rel="noopener">Geofabrik</a>) erstellen, Lizenz ODbL. Auch die Standorte im Feld „Freizeit &amp; Infrastruktur“ (Spielplätze, Kitas und Schulen, Ärzte und Apotheken, Supermärkte, Haltestellen, öffentliche Toiletten, Briefkästen, Packstationen und Paketshops, E-Bike-Ladestationen) stammen aus OpenStreetMap und werden einmal eingelesen statt bei jedem Kartenschwenk abgefragt; nur die Ladesäulen für Autos kommen aus dem Register der Bundesnetzagentur.</p>

<h2>Woher kommen die Statistiken?</h2>
<p>Aus amtlichen, frei nutzbaren Quellen, jeweils mit Stand an der Zahl: Kennzahlen zu Nahversorgung, Erreichbarkeit, Pendeln, Grün, Gesundheit und Wohnen aus der <a href="https://www.inkar.de" target="_blank" rel="noopener">Laufenden Raumbeobachtung des BBSR (INKAR)</a> des <a href="https://www.bbsr.bund.de" target="_blank" rel="noopener">Bundesinstituts für Bau-, Stadt- und Raumforschung</a>; Gemeinde- und Kreisgrenzen © GeoBasis-DE / <a href="https://www.bkg.bund.de" target="_blank" rel="noopener">BKG</a> (beide Datenlizenz Deutschland – Namensnennung 2.0); Klimadaten vom Deutschen Wetterdienst; Arbeitsmarkt, Kriminalität und Regionalstatistik von Bundesagentur für Arbeit, BKA und Destatis; Unfallorte aus dem Unfallatlas der Statistischen Ämter des Bundes und der Länder; Ladesäulen aus dem Ladesäulenregister der Bundesnetzagentur (Bundesnetzagentur.de, CC BY 4.0); Abfahrten je Haltestelle aus dem Fahrplan-Datensatz von DELFI e.V. über GTFS.de; Lärm von der Europäischen Umweltagentur, Hochwasserflächen von der Bundesanstalt für Gewässerkunde; Zensus 2022 und Breitbandatlas.</p>

<h2>Was, wenn eine Bewertung falsch wirkt?</h2>
<p>Über den „Bewertungen melden"-Link an jedem Kartenmarker kann jeder — auch ohne eigenes Konto — eine Meldung abgeben, die geprüft wird. Details dazu und zur Datenverarbeitung insgesamt: <a href="/datenschutz">Datenschutzerklärung</a>.</p>

<footer><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a></footer>
</body>
</html>
