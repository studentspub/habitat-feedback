<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Datenschutzerklärung — Habitat Feedback</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { font-family: sans-serif; max-width: 40em; margin: 2em auto; padding: 0 1em; color: #1B2420; background: #F2F4EF; line-height: 1.6; }
  h1 { font-size: 1.4rem; }
  h2 { font-size: 1.1rem; margin-top: 1.8em; }
  .todo { background: #F3DDD1; border-left: 4px solid #AA4A25; padding: 1em; margin: 1.5em 0; }
  .todo strong { display: block; margin-bottom: 4px; }
  code { background: #EBEEE5; padding: 1px 5px; border-radius: 4px; }
  a.back { display: inline-block; margin-bottom: 1em; }
</style>
</head>
<body>
<a class="back" href="/">&larr; zurück zur Karte</a>
<h1>Datenschutzerklärung</h1>

<div class="todo">
  <strong>Entwurf, keine Rechtsberatung</strong>
  Dieser Text beschreibt technisch korrekt, welche Daten Habitat Feedback tatsächlich verarbeitet (Stand des Codes). Er ersetzt keine anwaltliche Prüfung vor dem Livegang — insbesondere die Kontaktangaben des Verantwortlichen unten sind noch Platzhalter.
</div>

<h2>Verantwortlicher</h2>
<p>[Vor- und Nachname]<br>[Straße Hausnummer, PLZ Ort]<br>E-Mail: [deine-email@beispiel.de]</p>
<p>(siehe auch <a href="/impressum">Impressum</a>)</p>

<h2>Was beim Abgeben einer Bewertung passiert</h2>
<p>Du gibst eine Adresse und fünf Bewertungskriterien (je 1–5) ein. Kein Freitext, kein Klarname, keine E-Mail-Pflicht.</p>
<ul>
  <li>Die Adresse wird verwendet, um sie einem <strong>Straßenabschnitt</strong> zuzuordnen (Koordinaten über den Kartendienst OpenStreetMap/Nominatim). Die genaue Hausnummer wird danach <strong>nicht dauerhaft gespeichert</strong> — in der Datenbank landet nur Straße, Abschnitt, Postleitzahl und Ort.</li>
  <li>Deine Bewertung wird erst öffentlich sichtbar, wenn der Abschnitt mindestens 6 unterschiedliche Adressen und mindestens 3 unabhängige Bewertungen erreicht hat. Einzelne Bewertungen werden nie angezeigt, nur der Durchschnitt.</li>
</ul>

<h2>Erkennung von Mehrfachbewertungen (berechtigtes Interesse, Art. 6 Abs. 1 lit. f DSGVO)</h2>
<p>Damit nicht eine Person denselben Abschnitt beliebig oft bewertet, wird beim Absenden ein technisches Signal aus einem Teil deiner IP-Adresse und deinem Browser-Kennzeichen (User-Agent) gebildet. Dieses Signal wird zusammen mit einem geheimen, nur auf dem Server bekannten Wert kryptografisch verrechnet (Hashing) — das Ergebnis lässt sich nicht auf deine IP-Adresse zurückrechnen. Die volle IP-Adresse wird nicht gespeichert, sondern vor der Verarbeitung gekürzt (z.B. <code>192.168.1.0/24</code> statt der vollständigen Adresse).</p>
<p>Dieses Signal wird zusätzlich mit dem jeweiligen Straßenabschnitt verrechnet, bevor es gespeichert wird — dasselbe Gerät erzeugt für unterschiedliche Abschnitte unterschiedliche, nicht miteinander verknüpfbare Werte.</p>

<h2>Melde-Funktion</h2>
<p>Meldungen zu einem Straßenabschnitt (Text + Zeitpunkt) werden gespeichert, um sie zu prüfen. Es wird keine Kontaktmöglichkeit zum Meldenden erfasst, es sei denn, du gibst sie freiwillig im Text an.</p>

<h2>Was nicht erfasst wird</h2>
<ul>
  <li>Keine Nutzerkonten, keine Klarnamen, keine Pflicht-E-Mail-Adresse.</li>
  <li>Keine Cookies zu Tracking-/Werbezwecken.</li>
  <li>Keine Weitergabe an Dritte außer den technisch notwendigen Diensten zur Kartendarstellung (OpenStreetMap-Kachelserver) und Geokodierung (Nominatim/Overpass, beide Projekte der OpenStreetMap Foundation).</li>
</ul>

<h2>Zuschaltbare Karten-Ebenen</h2>
<p>Einige Ebenen lädt dein Browser erst beim Einschalten direkt von den jeweiligen Stellen; dabei wird wie bei jedem Bildabruf deine IP-Adresse übertragen: Breitband (Bundesnetzagentur), Lärm (Europäische Umweltagentur) und Hochwasser (Bundesanstalt für Gewässerkunde). Die Zensus-Daten kommen von unserem eigenen Server. Solange eine Ebene aus ist, wird nichts geladen.</p>

<h2>Links zu Google Street View</h2>
<p>Über das Männchen auf der Karte kannst du einen Link zu Google Street View erzeugen. Solange du den Link nicht anklickst, werden keine Daten an Google übertragen. Erst mit dem Klick öffnet sich Google Maps in einem neuen Tab; dort gelten die Datenschutzbestimmungen von Google.</p>

<h2>Deine Rechte</h2>
<p>Da Bewertungen ohne Personenbezug zu dir gespeichert werden (kein Konto, kein Klarname), kann eine nachträgliche Zuordnung zu deiner Person in der Regel nicht hergestellt werden. Für Fragen oder ein Löschbegehren bezüglich einer konkreten, von dir abgegebenen Bewertung wende dich über die oben genannte E-Mail-Adresse an uns.</p>
</body>
</html>
