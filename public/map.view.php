<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Habitat Feedback</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
  html, body { margin: 0; height: 100%; font-family: sans-serif; }
  #map { height: 100%; }
  .wl-popup h3 { margin: 0 0 4px; font-size: 1rem; }
  .wl-popup .score { font-weight: 600; }

  /* Zwei bewusst getrennte Bereiche — Suche (links) und Bewerten-Einstieg
     (rechts) haben nichts miteinander zu tun und sollen auch nicht wie
     eine zusammengehörige Werkzeugleiste aussehen. */
  #search-group {
    position: absolute; top: 10px; left: 50px; z-index: 1000; width: 280px;
  }
  #search-label {
    display: inline-block; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;
    color: #1B2420; background: rgba(255,255,255,0.9);
    padding: 2px 8px; border-radius: 4px 4px 0 0; margin-bottom: -1px;
  }
  #search {
    display: block; width: 100%; box-sizing: border-box; padding: 0.6em 0.8em;
    border: 1px solid #CFD8C9; border-radius: 6px; font-size: 0.95rem; box-shadow: 0 1px 4px rgba(0,0,0,0.15);
  }
  #suggestions {
    position: absolute; top: 100%; left: 0; right: 0; background: white; border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2); overflow: hidden; z-index: 1001; margin-top: 2px;
  }
  #suggestions div { padding: 0.6em 0.8em; cursor: pointer; font-size: 0.9rem; }
  #suggestions div:hover { background: #EBEEE5; }

  #rate-cta {
    position: absolute; top: 10px; right: 10px; z-index: 1000;
    background: rgba(255,255,255,0.92); border-radius: 8px; padding: 10px 14px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.15);
    display: flex; flex-direction: column; align-items: stretch; gap: 8px;
  }
  #rate-cta-heading { font-size: 0.85rem; font-weight: 600; color: #1B2420; white-space: nowrap; }
  #rate-link {
    padding: 0.55em 1em; background: #1B2420; color: white; text-decoration: none;
    border-radius: 6px; font-size: 0.9rem; text-align: center; white-space: nowrap;
  }

  #legal {
    position: absolute; bottom: 42px; left: 8px; z-index: 1000;
    background: rgba(255,255,255,0.85); padding: 4px 10px; border-radius: 6px;
    font-size: 0.78rem;
  }
  #legal a { color: #31576D; margin-right: 10px; text-decoration: none; }
  #legal a:hover { text-decoration: underline; }

  /* Leaflets Standard-Maßstab ist winzig und kaum lesbar (11px, dünner
     Rahmen, halbtransparent) — mehrere Ebenen (Breitband, Census-Raster)
     hängen aber an genau diesem Wert ("ab 1 km sichtbar"), er muss also
     leicht ablesbar sein statt eine Randnotiz zu sein. */
  .leaflet-control-scale-line {
    font-size: 0.85rem; font-weight: 600; color: #1B2420;
    background: rgba(255,255,255,0.92); border: 2px solid #1B2420; border-top: none;
    padding: 3px 8px 2px; border-radius: 0 0 5px 5px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.15);
  }

  /* Die Bedienfelder unten rechts (Census, POI, Klima, Breitband,
     Wirtschaft, ...) wachsen mit jeder neuen Kennzahl weiter — als reine
     Ein-Spalten-Liste würden sie irgendwann höher als der sichtbare
     Kartenausschnitt und mit "Straße bewerten?" oben rechts kollidieren.
     Statt Scrollen: eine zweite (dritte, ...) Spalte per flex-wrap, sobald
     die Höhe ausgeschöpft ist. direction:rtl auf dem Container lässt neue
     Spalten nach LINKS wachsen (vom rechten Kartenrand weg) statt über den
     Kartenrand hinaus nach rechts — direction:ltr auf den Feldern selbst
     macht das für ihren eigenen Inhalt wieder rückgängig. */
  .leaflet-bottom.leaflet-right {
    display: flex; flex-flow: column wrap; align-content: flex-start;
    direction: rtl; max-height: calc(100vh - 110px); gap: 8px; padding-right: 2px;
  }
  .leaflet-bottom.leaflet-right > * { direction: ltr; }
  /* Leaflets eigenes CSS gibt jedem Control float:left/clear:both plus
     margin-right/-bottom (für die normale Block-Stapelung gedacht) — im
     Flex-Container oben wird das float ignoriert, die Margins blieben aber
     bestehen und würden sich mit dem eigenen "gap" doppelt addieren. */
  .leaflet-bottom.leaflet-right .leaflet-control { margin: 0; }

  /* Amtliche Statistik-Ebenen, wie eine Wetterkarten-Umschaltung — eigenständig
     von den Bewertungs-Pins. Mehrere Kennzahlen gleichzeitig aktivierbar
     (überlappende Darstellung), jede mit eigener Farbskala und niedriger
     Deckkraft, damit die Kartenunterlage lesbar bleibt. */
  .census-control {
    background: rgba(255,255,255,0.92); padding: 10px; border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.15); font-size: 0.82rem; width: 230px;
    max-height: 70vh; overflow-y: auto;
  }
  .census-control .panel-title {
    display: block; margin: -10px -10px 10px -10px; padding: 0.55em 0.8em;
    background: #1B2420; color: white; border-radius: 8px 8px 0 0;
    font-size: 0.88rem; font-weight: 600; text-align: center;
  }
  .census-metric-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
  .census-metric-btn {
    box-sizing: border-box; padding: 0.5em 0.5em; background: #FBFBF7; color: #1B2420;
    border: 1px solid #CFD8C9; border-radius: 6px; font-size: 0.76rem; font-weight: 600;
    cursor: pointer; text-align: center; line-height: 1.25;
  }
  .census-metric-btn.active { color: white; border-color: transparent; }
  .census-control .hint { font-size: 0.72rem; color: #4B564F; margin-top: 8px; line-height: 1.4; }
  .census-legend-group { display: none; margin-top: 6px; padding-top: 6px; border-top: 1px solid #EBEEE5; }
  .census-legend-group.visible { display: block; }
  .census-legend-group .metric-name { font-size: 0.72rem; font-weight: 600; margin-top: 4px; }
  .census-legend-group .row { display: flex; align-items: center; gap: 6px; font-size: 0.7rem; margin-top: 2px; }
  .census-legend-group .swatch { width: 14px; height: 14px; border-radius: 3px; flex: none; }

  /* Freizeit/Infrastruktur aus OpenStreetMap — eigenes Bedienfeld, eigene
     Marker statt Flächen, weil das hier Punktdaten sind (siehe Datenquellen-
     Recherche). Läuft technisch über /api/poi (eigener Server-Proxy mit
     Cache), nicht direkt gegen Overpass — anders als der Zensus-ArcGIS-
     Dienst hat Overpass eine Nutzungsrichtlinie, die von häufigen
     interaktiven Anfragen ohne eigenes Caching abrät. */
  .poi-control {
    background: rgba(255,255,255,0.92); padding: 10px; border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.15); font-size: 0.82rem; width: 230px;
    max-height: 70vh; overflow-y: auto;
  }
  .poi-control .panel-title {
    display: block; margin: -10px -10px 10px -10px; padding: 0.55em 0.8em;
    background: #1B2420; color: white; border-radius: 8px 8px 0 0;
    font-size: 0.88rem; font-weight: 600; text-align: center;
  }
  .poi-metric-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
  .poi-metric-btn {
    box-sizing: border-box; padding: 0.5em 0.5em; background: #FBFBF7; color: #1B2420;
    border: 1px solid #CFD8C9; border-radius: 6px; font-size: 0.76rem; font-weight: 600;
    cursor: pointer; text-align: center; line-height: 1.25;
  }
  .poi-metric-btn.active { color: white; border-color: transparent; }
  .poi-control .hint { font-size: 0.72rem; color: #4B564F; margin-top: 8px; line-height: 1.4; }
  /* Dreiecke statt Kreise, bewusst anders als die runden Bewertungs-Pins —
     sobald beide Ebenen gleichzeitig an sind, muss auf einen Blick erkennbar
     sein, was eine Bewertung und was ein OSM-Infrastrukturpunkt ist.
     Leaflets divIcon setzt standardmäßig einen weißen Kasten-Hintergrund
     samt Rahmen (für Icon-Bilder gedacht) — hier per !important entfernt,
     damit nur das SVG-Dreieck selbst sichtbar bleibt. */
  .poi-triangle-icon { background: none !important; border: none !important; }

  /* Bewertungs-Pins — eigene Ebene mit An/Aus-Schalter (wie Klima/Breitband/
     Wirtschaft), damit man bei Bedarf nur die Statistik-Ebenen sehen kann,
     ohne dass die Bewertungspunkte den Blick verstellen. */
  .ratings-control {
    background: rgba(255,255,255,0.92); padding: 10px; border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.15); font-size: 0.82rem; width: 230px;
  }
  #ratings-btn {
    display: block; width: 100%; box-sizing: border-box; padding: 0.55em 0.8em;
    background: #1B2420; color: white; border: none; border-radius: 6px;
    font-size: 0.88rem; font-weight: 600; cursor: pointer; text-align: center;
  }
  #ratings-btn.active { background: #31576D; }
  .ratings-control .hint { font-size: 0.72rem; color: #4B564F; margin-top: 8px; line-height: 1.4; }

  /* Farblegende für die Bewertungs-Pins, oben links unter dem Zoom-Regler —
     als eigener topleft-Leaflet-Control stapelt Leaflet sie dort automatisch
     darunter, kein manuelles Positionieren nötig. */
  .ratings-legend {
    background: rgba(255,255,255,0.92); padding: 8px 10px; border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.15); font-size: 0.72rem; color: #1B2420;
  }
  .ratings-legend .legend-title { font-weight: 700; margin-bottom: 4px; }
  .ratings-legend .row { display: flex; align-items: center; gap: 6px; margin-top: 2px; }
  .ratings-legend .dot { width: 10px; height: 10px; border-radius: 50%; flex: none; border: 1px solid rgba(27,36,32,0.4); }

  /* Klima & Luft — kein Kachel-/Punkt-Layer, sondern ein Kontext-Kärtchen
     für die aktuelle Kartenmitte: beide Kennzahlen sind stationsbasiert
     und variieren kaum innerhalb einer Stadt, ein Raster wäre hier
     irreführend genau. Aktualisiert sich beim Verschieben der Karte. */
  .climate-control {
    background: rgba(255,255,255,0.92); padding: 10px; border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.15); font-size: 0.82rem; width: 230px;
  }
  #climate-btn {
    display: block; width: 100%; box-sizing: border-box; padding: 0.55em 0.8em;
    background: #1B2420; color: white; border: none; border-radius: 6px;
    font-size: 0.88rem; font-weight: 600; cursor: pointer; text-align: center;
  }
  #climate-btn.active { background: #31576D; }
  .climate-control .hint { font-size: 0.72rem; color: #4B564F; margin-top: 8px; line-height: 1.4; }
  .climate-card { display: none; margin-top: 8px; padding-top: 8px; border-top: 1px solid #EBEEE5; }
  .climate-card.visible { display: block; }
  .climate-card .row { display: flex; justify-content: space-between; gap: 8px; font-size: 0.78rem; margin-top: 4px; }
  .climate-card .row .k { color: #4B564F; }
  .climate-card .row .v { font-weight: 600; text-align: right; }
  .climate-card .src { font-size: 0.68rem; color: #7C877E; margin-top: 6px; }

  /* Breitband — anders als Zensus/POI ein WMS-Dienst: die Bundesnetzagentur
     liefert fertig eingefärbte Kachelbilder, keine Rohdaten. Wir können also
     keine eigene Farbskala/Legende zeichnen (siehe Insight im Gespräch) —
     nur an/ausschalten, daher ein einzelner Knopf wie bei Klima & Luft. */
  .broadband-control {
    background: rgba(255,255,255,0.92); padding: 10px; border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.15); font-size: 0.82rem; width: 230px;
  }
  #broadband-btn {
    display: block; width: 100%; box-sizing: border-box; padding: 0.55em 0.8em;
    background: #1B2420; color: white; border: none; border-radius: 6px;
    font-size: 0.88rem; font-weight: 600; cursor: pointer; text-align: center;
  }
  #broadband-btn.active { background: #31576D; }
  .broadband-control .hint { font-size: 0.72rem; color: #4B564F; margin-top: 8px; line-height: 1.4; }

  /* Wirtschaft — bislang nur die Arbeitslosenquote des Kreises der
     Kartenmitte, daher dieselbe Kärtchen-Bauweise wie Klima & Luft statt
     einer Flächen-/Punkt-Ebene. Eigenes Bedienfeld statt Erweiterung des
     Klima-Kärtchens, weil es inhaltlich eine andere Kategorie ist
     (Wirtschaft vs. Umwelt) — so bleiben die Recherche-Kategorien aus dem
     Gespräch auch in der UI als eigenständige Bausteine erkennbar. */
  .economy-control {
    background: rgba(255,255,255,0.92); padding: 10px; border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.15); font-size: 0.82rem; width: 230px;
  }
  #economy-btn {
    display: block; width: 100%; box-sizing: border-box; padding: 0.55em 0.8em;
    background: #1B2420; color: white; border: none; border-radius: 6px;
    font-size: 0.88rem; font-weight: 600; cursor: pointer; text-align: center;
  }
  #economy-btn.active { background: #31576D; }
  .economy-control .hint { font-size: 0.72rem; color: #4B564F; margin-top: 8px; line-height: 1.4; }
  .economy-card { display: none; margin-top: 8px; padding-top: 8px; border-top: 1px solid #EBEEE5; }
  .economy-card.visible { display: block; }
  .economy-card .row { display: flex; justify-content: space-between; gap: 8px; font-size: 0.78rem; margin-top: 4px; }
  .economy-card .row .k { color: #4B564F; }
  .economy-card .row .v { font-weight: 600; text-align: right; }
  .economy-card .src { font-size: 0.68rem; color: #7C877E; margin-top: 6px; }

  /* Info-Buttons neben Kennzahlen-Labels — erklären auf Klick, warum die
     Zahl für eine Wohnlagen-Einschätzung überhaupt von Belang ist (nicht
     jede Statistik erklärt sich von selbst). Bewusst Klick statt Hover:
     funktioniert auch mit dem nativen title-Tooltip nicht gut lesbar wegen
     der Kärtchen-Breite von nur 230px, ein eigenes Popover kann umbrechen. */
  .info-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 14px; height: 14px; margin-left: 3px; padding: 0; border: none;
    border-radius: 50%; background: #CFD8C9; color: #1B2420;
    font-size: 0.62rem; font-weight: 700; line-height: 1; cursor: pointer;
    vertical-align: middle;
  }
  .info-btn:hover, .info-btn:focus-visible { background: #31576D; color: white; }
  .info-popover {
    position: absolute; z-index: 1200; max-width: 220px; background: #1B2420; color: white;
    font-size: 0.72rem; font-weight: 400; line-height: 1.4; padding: 8px 10px;
    border-radius: 6px; box-shadow: 0 2px 10px rgba(0,0,0,0.25); margin-top: 4px;
  }

  /* Tutorial — geführte Tour durch die Bedienelemente. #tour-spotlight nutzt
     einen riesigen box-shadow statt clip-path: bei jedem Schritt ändert sich
     nur Position/Größe eines einzigen Elements statt eines Pfads. Der
     "Rahmen" um den jeweiligen Zielbutton ist ein zusätzlicher Outline direkt
     an diesem Spotlight-Element. #tour-clickguard fängt währenddessen Klicks
     auf der restlichen Seite ab, damit die Tour nicht durch versehentliches
     Umschalten eines Panels durcheinanderkommt. */
  #discover-group {
    position: absolute; top: 10px; left: 50%; transform: translateX(-50%); z-index: 1000;
    display: flex; gap: 8px;
  }
  #tutorial-btn, #vision-link {
    border: none; border-radius: 20px; padding: 0.5em 1.2em; font-weight: 700;
    font-size: 0.85rem; cursor: pointer; box-shadow: 0 1px 4px rgba(0,0,0,0.25);
    text-decoration: none; white-space: nowrap;
  }
  #tutorial-btn { background: #D9971E; color: #1B2420; }
  #vision-link { background: #31576D; color: white; }
  #examples-btn { background: #3B7A52; color: white; }
  #tour-clickguard { position: fixed; inset: 0; z-index: 2999; background: transparent; }
  #tour-spotlight {
    position: fixed; z-index: 3000; border-radius: 8px; pointer-events: none;
    box-shadow: 0 0 0 9999px rgba(9, 12, 10, 0.6);
    outline: 3px solid #D9971E; outline-offset: 3px;
    transition: left 0.25s ease, top 0.25s ease, width 0.25s ease, height 0.25s ease;
  }
  #tour-tooltip {
    position: fixed; z-index: 3001; background: #1B2420; color: white; border-radius: 10px;
    padding: 14px 16px; box-shadow: 0 4px 16px rgba(0,0,0,0.35); font-size: 0.85rem;
    transition: left 0.25s ease, top 0.25s ease;
  }
  #tour-tooltip .tour-progress { font-size: 0.68rem; color: #CFD8C9; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px; }
  #tour-tooltip .tour-title { font-weight: 700; font-size: 0.95rem; margin-bottom: 4px; }
  #tour-tooltip .tour-text {
    line-height: 1.4; color: #EBEEE5; max-height: 220px; overflow-y: auto;
  }
  /* Einige Tour-Schritte (Zensus, OSM) listen mehrere Kennzahlen/Kategorien
     statt eines einzelnen Fließtexts auf — dieselbe Farbpunkt-Optik wie in
     den echten Panel-Legenden, damit die Erklärung im Tutorial zu dem passt,
     was man auf der Karte tatsächlich sieht. */
  #tour-tooltip .tour-list { list-style: none; margin: 6px 0 0; padding: 0; }
  #tour-tooltip .tour-list li { display: flex; align-items: flex-start; gap: 6px; margin-top: 5px; }
  #tour-tooltip .tour-swatch {
    width: 10px; height: 10px; border-radius: 50%; flex: none; display: inline-block; margin-top: 3px;
  }
  #tour-tooltip .tour-swatch.triangle { border-radius: 0; clip-path: polygon(50% 0%, 0% 100%, 100% 100%); }
  #tour-tooltip .tour-actions { display: flex; align-items: center; justify-content: space-between; margin-top: 12px; gap: 8px; }
  #tour-tooltip .tour-skip { background: none; border: none; color: #CFD8C9; font-size: 0.75rem; cursor: pointer; padding: 0.3em 0; text-decoration: underline; }
  #tour-tooltip .tour-nav { display: flex; gap: 6px; }
  #tour-tooltip .tour-back, #tour-tooltip .tour-next {
    border: none; border-radius: 6px; padding: 0.4em 0.9em; font-size: 0.8rem; font-weight: 600; cursor: pointer;
  }
  #tour-tooltip .tour-back { background: #31576D; color: white; }
  #tour-tooltip .tour-back:disabled { opacity: 0.4; cursor: default; }
  #tour-tooltip .tour-next { background: #D9971E; color: #1B2420; }

  /* Willkommens-Overlay — erscheint einmalig, dezent verzögert statt sofort
     beim Laden, und merkt sich per localStorage, dass es (egal über welchen
     Weg) schon gesehen wurde. Bewusst kein Modal: pointer-events bleiben
     überall sonst auf der Karte aktiv, das Overlay blockiert nichts. */
  #welcome-overlay {
    position: absolute; bottom: 16px; left: 50%; z-index: 1000;
    transform: translateX(-50%) translateY(16px); opacity: 0; pointer-events: none;
    transition: opacity 0.4s ease, transform 0.4s ease;
    background: rgba(255,255,255,0.97); border-radius: 10px; padding: 14px 34px 14px 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2); max-width: 320px; width: calc(100% - 32px);
  }
  #welcome-overlay.visible { opacity: 1; transform: translateX(-50%) translateY(0); pointer-events: auto; }
  #welcome-overlay .welcome-close {
    position: absolute; top: 6px; right: 8px; background: none; border: none; font-size: 1rem;
    color: #7C877E; cursor: pointer; line-height: 1; padding: 4px;
  }
  #welcome-overlay h3 { margin: 0 0 4px; font-size: 0.92rem; color: #1B2420; }
  #welcome-overlay p { margin: 0 0 10px; font-size: 0.8rem; color: #4B564F; line-height: 1.4; }
  #welcome-overlay .welcome-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
  #welcome-overlay .welcome-cta {
    padding: 0.5em 1em; background: #1B2420; color: white; border-radius: 6px;
    text-decoration: none; font-size: 0.8rem; font-weight: 600;
  }
  #welcome-overlay .welcome-dismiss { font-size: 0.76rem; color: #31576D; text-decoration: none; cursor: pointer; }

  /* Praxisbeispiele — eigenes Modal statt eines weiteren 230px-Bedienfelds:
     fünf ausformulierte Personas samt Rückschlüssen sind zu lang für die
     schmalen Panels der anderen Ebenen. Jede Persona ist einzeln auf-/
     zuklappbar (Akkordeon), alle standardmäßig zu, damit das Modal beim
     Öffnen nicht als Textwand erschlägt. */
  #examples-backdrop { position: fixed; inset: 0; z-index: 3500; background: rgba(9, 12, 10, 0.55); }
  #examples-modal {
    position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 3501;
    background: #F2F4EF; color: #1B2420; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.35);
    width: min(640px, calc(100vw - 32px)); max-height: min(720px, calc(100vh - 32px));
    display: flex; flex-direction: column; overflow: hidden;
  }
  /* Das [hidden]-Attribut hat im Browser nur eine ID-schwache
     UA-Stylesheet-Regel (display:none) hinterlegt — die eigene
     "display: flex"-Regel oben hat als ID-Selektor höhere Spezifität und
     würde sie sonst überstimmen, sodass das Modal trotz hidden sichtbar
     bliebe. Dieser Selektor gewinnt gezielt dagegen. */
  #examples-modal[hidden] { display: none; }
  .examples-header { background: #1B2420; color: white; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; }
  .examples-header h2 { margin: 0; font-size: 1.05rem; }
  .examples-header button { background: none; border: none; color: white; font-size: 1.1rem; cursor: pointer; padding: 4px; line-height: 1; }
  .examples-intro { padding: 12px 18px 0; font-size: 0.82rem; color: #4B564F; line-height: 1.4; }
  .examples-list { padding: 12px 18px 18px; overflow-y: auto; }
  .example-item { border: 1px solid #CFD8C9; border-radius: 8px; margin-top: 10px; overflow: hidden; }
  .example-item:first-child { margin-top: 0; }
  .example-toggle {
    display: flex; width: 100%; align-items: center; justify-content: space-between; gap: 10px;
    background: white; border: none; padding: 10px 14px; text-align: left; cursor: pointer; color: #1B2420;
  }
  .example-toggle .name { display: block; font-size: 0.88rem; font-weight: 700; }
  .example-toggle .teaser { display: block; font-size: 0.76rem; font-weight: 400; color: #4B564F; margin-top: 2px; }
  .example-toggle .chevron { flex: none; transition: transform 0.2s ease; color: #4B564F; }
  .example-item.open .example-toggle .chevron { transform: rotate(180deg); }
  .example-body { display: none; padding: 0 14px 14px; background: #FBFBF7; }
  .example-item.open .example-body { display: block; }
  .example-body .story { font-size: 0.84rem; line-height: 1.5; margin: 10px 0; }
  .example-body .insights-title { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; color: #31576D; margin-top: 4px; }
  .example-body ol { margin: 6px 0 0; padding-left: 18px; font-size: 0.82rem; line-height: 1.5; }
  .example-body li { margin-top: 4px; }
</style>
</head>
<body>
<div id="discover-group">
  <button type="button" id="tutorial-btn">🧭 Tutorial starten</button>
  <a id="vision-link" href="/vision">🔮 Vision entdecken</a>
  <button type="button" id="examples-btn">💡 Praxisbeispiele</button>
</div>
<div id="search-group">
  <span id="search-label">Bereits bewertete Straßen</span>
  <input type="text" id="search" placeholder="z.B. Musterstraße…" autocomplete="off">
  <div id="suggestions" hidden></div>
</div>

<div id="rate-cta">
  <span id="rate-cta-heading">Straße bewerten?</span>
  <a id="rate-link" href="/bewerten">Bewertung abgeben</a>
</div>
<div id="map"></div>
<div id="legal">
  <a href="/ueber">Über Habitat Feedback</a><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a>
</div>

<div id="welcome-overlay">
  <button type="button" class="welcome-close" aria-label="Schließen">✕</button>
  <h3>👋 Willkommen bei Habitat Feedback</h3>
  <p>Entdecke, wie sich Straßen wirklich anfühlen — oder gib in wenigen Minuten deine eigene, anonyme Einschätzung ab.</p>
  <div class="welcome-actions">
    <a class="welcome-cta" href="/bewerten">Straße bewerten</a>
    <a class="welcome-dismiss">Vielleicht später</a>
  </div>
</div>

<div id="examples-backdrop" hidden></div>
<div id="examples-modal" hidden>
  <div class="examples-header">
    <h2>💡 Für wen lohnt sich Habitat Feedback?</h2>
    <button type="button" id="examples-close" aria-label="Schließen">✕</button>
  </div>
  <div class="examples-intro">Fünf fiktive Beispiele, wie sich die Kennzahlen auf der Karte in der Praxis lesen lassen.</div>
  <div class="examples-list" id="examples-list"></div>
</div>

<div id="tour-clickguard" hidden></div>
<div id="tour-spotlight" hidden></div>
<div id="tour-tooltip" hidden>
  <div class="tour-progress"></div>
  <div class="tour-title"></div>
  <div class="tour-text"></div>
  <div class="tour-actions">
    <button type="button" class="tour-skip">Tour beenden</button>
    <div class="tour-nav">
      <button type="button" class="tour-back">Zurück</button>
      <button type="button" class="tour-next">Weiter</button>
    </div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
  // Alles, was aus API-Antworten stammt (OSM-Namen, Nominatim-Straßen,
  // UBA-Stationen, importierte Statistik-Texte), geht nur hierüber in
  // HTML-Strings. Leaflet behandelt Popup-/Tooltip-Strings als HTML, und
  // OSM-Namen kann jeder editieren (Stored XSS, Issue #9).
  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);
  }

  const map = L.map('map').setView([51.1657, 10.4515], 6); // Deutschland-Übersicht

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>-Mitwirkende'
  }).addTo(map);

  L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(map);

  // Farblegende für die Bewertungs-Pins — eigener topleft-Control, damit
  // Leaflet ihn automatisch unter dem Zoom-Regler einreiht statt ihn manuell
  // positionieren zu müssen.
  const RatingsLegendControl = L.Control.extend({
    options: { position: 'topleft' },
    onAdd: function () {
      const el = L.DomUtil.create('div', 'ratings-legend');
      el.innerHTML =
        '<div class="legend-title">Bewertungen</div>' +
        '<div class="row"><span class="dot" style="background:#3B7A52"></span>ab 4,0 — gut</div>' +
        '<div class="row"><span class="dot" style="background:#D9971E"></span>3,0–3,9 — mittel</div>' +
        '<div class="row"><span class="dot" style="background:#AA4A25"></span>unter 3,0 — eher schlecht</div>';
      L.DomEvent.disableClickPropagation(el);
      return el;
    }
  });
  map.addControl(new RatingsLegendControl());

  // Vier verschiedene Ebenen (Bewertungen, Zensus, OSM-Kategorien, Klima)
  // hängen jeweils eigenständig an moveend/zoomend. Ohne Debounce feuert
  // jede einzeln bei jedem Zwischenschritt einer Kartenbewegung — bei
  // mehreren aktiven Ebenen gleichzeitig stapeln sich so schnell viele
  // Anfragen, die unser einfacher Dev-Server (`php -S`, bewusst schlicht,
  // bearbeitet Anfragen nacheinander statt parallel) erst nach und nach
  // abarbeitet. Ergebnis: die Karte zeigt eine Weile noch den alten
  // Ausschnitt, obwohl man längst weitergezoomt hat. debounce() sorgt
  // dafür, dass erst 350ms nach der letzten Bewegung überhaupt eine neue
  // Anfragenrunde losgeht, statt bei jedem Zwischenschritt.
  function debounce(fn, delayMs) {
    let timeout = null;
    return (...args) => {
      clearTimeout(timeout);
      timeout = setTimeout(() => fn(...args), delayMs);
    };
  }

  function colorForScore(score) {
    if (score >= 4) return '#3B7A52';
    if (score >= 3) return '#D9971E';
    return '#AA4A25';
  }

  // Bewertungs-Pins leben in einer eigenen LayerGroup statt direkt auf der
  // Karte — dadurch lässt sich die gesamte Ebene mit einem Handgriff aus-
  // blenden (Toggle weiter unten) und bei jedem Kartenzug leeren wir sie
  // zuerst per clearLayers(), statt Marker aus vorherigen Ausschnitten
  // stillschweigend liegen zu lassen.
  const ratingsLayer = L.layerGroup().addTo(map);
  let ratingsEnabled = true;

  async function loadPins() {
    const b = map.getBounds();
    const bbox = [b.getSouth(), b.getWest(), b.getNorth(), b.getEast()].join(',');
    const res = await fetch('/api/map?bbox=' + encodeURIComponent(bbox));
    const data = await res.json();

    ratingsLayer.clearLayers();
    if (!ratingsEnabled) return;

    (data.segments || []).forEach(seg => {
      const marker = L.circleMarker([seg.centroid_lat, seg.centroid_lon], {
        radius: 9,
        color: '#1B2420',
        weight: 1,
        fillColor: colorForScore(parseFloat(seg.avg_overall)),
        fillOpacity: 0.85
      }).addTo(ratingsLayer);

      marker.bindPopup(
        '<div class="wl-popup"><h3>' + escapeHtml(seg.street_name) + ' (Abschnitt ' + escapeHtml(seg.segment_index) + ')</h3>' +
        '<p>' + escapeHtml(seg.postal_code) + ' ' + escapeHtml(seg.city) + '</p>' +
        '<p class="score">Gesamt: ' + parseFloat(seg.avg_overall).toFixed(1) + ' / 5</p>' +
        '<p>' + escapeHtml(seg.rating_count) + ' Bewertungen &middot; ' + escapeHtml(seg.address_count) + ' Adressen im Abschnitt</p>' +
        '<p><a href="/melden?segment=' + encodeURIComponent(seg.id) + '">Bewertungen melden</a></p></div>'
      );
    });
  }

  map.on('moveend', debounce(loadPins, 350));
  loadPins();

  const RatingsControl = L.Control.extend({
    options: { position: 'bottomright' },
    onAdd: function () {
      const el = L.DomUtil.create('div', 'ratings-control');
      el.innerHTML =
        '<button type="button" id="ratings-btn" class="active">Bewertungen ausblenden</button>' +
        '<div class="hint">Blendet die Bewertungs-Punkte aus, z.B. um nur die Statistik-Ebenen zu sehen.</div>';
      L.DomEvent.disableClickPropagation(el);
      return el;
    }
  });
  map.addControl(new RatingsControl());

  document.getElementById('ratings-btn').addEventListener('click', (e) => {
    ratingsEnabled = !ratingsEnabled;
    e.target.textContent = ratingsEnabled ? 'Bewertungen ausblenden' : 'Bewertungen anzeigen';
    e.target.classList.toggle('active', ratingsEnabled);
    if (ratingsEnabled) { loadPins(); } else { ratingsLayer.clearLayers(); }
  });

  // Amtliche Statistik-Ebenen: Zensus 2022. Direkter Abruf beim
  // ArcGIS-Dienst des Statistischen Bundesamts — öffentlicher,
  // unauthentifizierter Geodatendienst ohne Nominatim-artige
  // Ratenlimit-Problematik, daher (anders als die Adresssuche) ohne
  // eigenen Server-Proxy.
  //
  // Der Dienst liefert dieselben Felder bereits fertig aggregiert auf drei
  // Rasterstufen (100m/1km/10km) — eigenes Aufsummieren im Browser wäre bei
  // größeren Kartenausschnitten (z.B. "ganz Hessen") auch gar nicht
  // möglich: max. 2000 Zellen pro Abfrage, Hessen allein hätte ca. 44.500
  // 100m-Zellen. Die Rasterstufe wird daher anhand der tatsächlichen
  // Fläche des Kartenausschnitts gewählt, wie bei einer Wetterkarte.
  const CENSUS_RESOLUTIONS = [
    { layer: 0, maxAreaKm2: 20, label: '100m-Raster (Straßenblock)' },
    { layer: 1, maxAreaKm2: 1500, label: '1km-Raster (Stadt/Region)' },
    { layer: 2, maxAreaKm2: 150000, label: '10km-Raster (Bundesland-Maßstab)' }
  ];
  const CENSUS_URL_BASE = 'https://services2.arcgis.com/jUpNdisbWqRpMo35/arcgis/rest/services/Zensus2022_grid_final/FeatureServer/';
  const CENSUS_FILL_OPACITY = 0.32; // bewusst niedrig — bei mehreren gleichzeitig
                                     // aktiven Ebenen muss die Karte lesbar bleiben

  // Fünf Kennzahlen, alle unabhängig voneinander an/ausschaltbar (mehrere
  // gleichzeitig = überlappende Darstellung). "Einwohner" ist eine Summe
  // und braucht deshalb je Rasterstufe eigene Stufengrenzen (eine 10km-Zelle
  // hat naturgemäß mehr Einwohner als eine 100m-Zelle); die übrigen vier
  // Felder sind bereits Durchschnitte/Quoten und bleiben über alle
  // Rasterstufen hinweg gleich eingeteilt.
  const CENSUS_METRICS = [
    {
      key: 'population', label: 'Einwohnerdichte', field: 'Einwohner', unit: 'Einw.',
      breaksByLayer: {
        0: [
          { max: 9, color: '#EFF3FF' }, { max: 49, color: '#BDD7E7' }, { max: 99, color: '#6BAED6' },
          { max: 199, color: '#2171B5' }, { max: Infinity, color: '#08306B' }
        ],
        1: [
          { max: 499, color: '#EFF3FF' }, { max: 1999, color: '#BDD7E7' }, { max: 4999, color: '#6BAED6' },
          { max: 9999, color: '#2171B5' }, { max: Infinity, color: '#08306B' }
        ],
        2: [
          { max: 4999, color: '#EFF3FF' }, { max: 24999, color: '#BDD7E7' }, { max: 74999, color: '#6BAED6' },
          { max: 199999, color: '#2171B5' }, { max: Infinity, color: '#08306B' }
        ]
      },
      labelsByLayer: {
        0: ['0–9', '10–49', '50–99', '100–199', '200+'],
        1: ['0–499', '500–1.999', '2.000–4.999', '5.000–9.999', '10.000+'],
        2: ['0–4.999', '5.000–24.999', '25.000–74.999', '75.000–199.999', '200.000+']
      }
    },
    {
      key: 'age', label: 'Ø Alter', field: 'Durchschnittsalter', unit: 'Jahre',
      breaks: [
        { max: 35, color: '#F2F0F7' }, { max: 42, color: '#CBC9E2' }, { max: 46, color: '#9E9AC8' },
        { max: 50, color: '#756BB1' }, { max: Infinity, color: '#54278F' }
      ],
      labels: ['bis 35', '35–42', '42–46', '46–50', 'ab 50']
    },
    {
      key: 'rent', label: 'Ø Miete/m²', field: 'durchschnMieteQM', unit: '€/m²',
      breaks: [
        { max: 6, color: '#FEEDDE' }, { max: 8, color: '#FDBE85' }, { max: 10, color: '#FD8D3C' },
        { max: 13, color: '#E6550D' }, { max: Infinity, color: '#A63603' }
      ],
      labels: ['bis 6€', '6–8€', '8–10€', '10–13€', 'ab 13€']
    },
    {
      key: 'ownership', label: 'Eigentümerquote', field: 'Eigentuemerquote', unit: '%',
      breaks: [
        { max: 20, color: '#EDF8E9' }, { max: 40, color: '#BAE4B3' }, { max: 60, color: '#74C476' },
        { max: 80, color: '#31A354' }, { max: Infinity, color: '#006D2C' }
      ],
      labels: ['bis 20%', '20–40%', '40–60%', '60–80%', 'ab 80%']
    },
    {
      key: 'vacancy', label: 'Leerstandsquote', field: 'Leerstandsquote', unit: '%',
      breaks: [
        { max: 2, color: '#FEE5D9' }, { max: 4, color: '#FCAE91' }, { max: 6, color: '#FB6A4A' },
        { max: 10, color: '#DE2D26' }, { max: Infinity, color: '#A50F15' }
      ],
      labels: ['bis 2%', '2–4%', '4–6%', '6–10%', 'ab 10%']
    }
  ];
  // Laufender Zustand pro Kennzahl: Leaflet-Layer, an/aus, Anfrage-Zähler
  // (verhindert, dass eine späte Antwort eine bereits ausgeschaltete oder
  // von einer neueren Anfrage überholte Anzeige wieder aufleben lässt).
  const censusState = {};
  CENSUS_METRICS.forEach(m => { censusState[m.key] = { layer: null, enabled: false, requestId: 0 }; });

  function viewportAreaKm2() {
    const b = map.getBounds();
    const midLatRad = (b.getNorth() + b.getSouth()) / 2 * Math.PI / 180;
    const heightKm = (b.getNorth() - b.getSouth()) * 111;
    const widthKm = (b.getEast() - b.getWest()) * 111 * Math.cos(midLatRad);
    return Math.max(heightKm, 0) * Math.max(widthKm, 0);
  }

  function pickResolution() {
    const area = viewportAreaKm2();
    return CENSUS_RESOLUTIONS.find(r => area <= r.maxAreaKm2) || null;
  }

  function metricBreaks(metric, resolution) {
    return metric.breaksByLayer ? metric.breaksByLayer[resolution.layer] : metric.breaks;
  }
  function metricLabels(metric, resolution) {
    return metric.labelsByLayer ? metric.labelsByLayer[resolution.layer] : metric.labels;
  }

  function updateCensusPanel() {
    const resolution = pickResolution();
    const hint = document.getElementById('census-hint');
    const anyEnabled = CENSUS_METRICS.some(m => censusState[m.key].enabled);

    if (anyEnabled && !resolution) {
      hint.textContent = 'Weiter reinzoomen, um Werte zu sehen (z.B. auf Bundesland-Ebene beginnen).';
    } else if (anyEnabled) {
      hint.textContent = 'Amtliche Zensus-2022-Daten, ' + resolution.label + '. Weiter reinzoomen für feinere Auflösung.';
    } else {
      hint.textContent = '';
    }

    const legendGroup = document.getElementById('census-legend-group');
    const activeMetrics = CENSUS_METRICS.filter(m => censusState[m.key].enabled && resolution);
    if (activeMetrics.length === 0) {
      legendGroup.classList.remove('visible');
      return;
    }
    legendGroup.innerHTML = activeMetrics.map(m => {
      const breaks = metricBreaks(m, resolution);
      const labels = metricLabels(m, resolution);
      return '<div class="metric-name">' + m.label + '</div>' +
        breaks.map((b, i) => '<div class="row"><span class="swatch" style="background:' + b.color + '"></span>' + labels[i] + '</div>').join('');
    }).join('');
    legendGroup.classList.add('visible');
  }

  async function loadCensusMetric(metric) {
    const state = censusState[metric.key];
    const myRequestId = ++state.requestId;

    if (state.layer) { map.removeLayer(state.layer); state.layer = null; }
    if (!state.enabled) { updateCensusPanel(); return; }

    const resolution = pickResolution();
    updateCensusPanel();
    if (!resolution) return;

    const breaks = metricBreaks(metric, resolution);
    const b = map.getBounds();
    const bbox = [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()].join(',');
    const url = CENSUS_URL_BASE + resolution.layer + '/query?' + new URLSearchParams({
      geometry: bbox, geometryType: 'esriGeometryEnvelope', inSR: '4326', outSR: '4326',
      spatialRel: 'esriSpatialRelIntersects', outFields: metric.field, returnGeometry: 'true', f: 'geojson'
    });
    const res = await fetch(url);
    const geojson = await res.json();

    // Zwischenzeitlich wieder ausgeschaltet oder von einer neueren Anfrage
    // für dieselbe Kennzahl überholt? Dann dieses veraltete Ergebnis verwerfen.
    if (myRequestId !== state.requestId || !state.enabled) return;

    // Zellen ohne Wert (z.B. unbewohnte Fläche) werden nicht eingefärbt,
    // statt fälschlich in die unterste Stufe zu fallen.
    geojson.features = geojson.features.filter(f => f.properties[metric.field] !== null && f.properties[metric.field] !== undefined);

    state.layer = L.geoJSON(geojson, {
      style: f => ({
        fillColor: breaks.find(br => f.properties[metric.field] <= br.max).color,
        fillOpacity: CENSUS_FILL_OPACITY, color: 'rgba(27,36,32,0.15)', weight: 1
      })
    }).addTo(map);
    state.layer.bringToBack();
  }

  const CensusControl = L.Control.extend({
    options: { position: 'bottomright' },
    onAdd: function () {
      const el = L.DomUtil.create('div', 'census-control');
      el.innerHTML =
        '<div class="panel-title">Amtliche Statistik (Zensus 2022)</div>' +
        '<div class="census-metric-grid">' +
        CENSUS_METRICS.map(m => '<button type="button" class="census-metric-btn" data-key="' + m.key + '">' + m.label + '</button>').join('') +
        '</div>' +
        '<div class="hint" id="census-hint"></div>' +
        '<div class="census-legend-group" id="census-legend-group"></div>';
      L.DomEvent.disableClickPropagation(el);
      return el;
    }
  });
  map.addControl(new CensusControl());

  const metricByKey = Object.fromEntries(CENSUS_METRICS.map(m => [m.key, m]));
  document.querySelectorAll('.census-metric-btn').forEach(btn => {
    const metric = metricByKey[btn.dataset.key];
    btn.addEventListener('click', () => {
      const state = censusState[metric.key];
      state.enabled = !state.enabled;
      btn.classList.toggle('active', state.enabled);
      btn.style.background = state.enabled ? metric.breaks ? metric.breaks[metric.breaks.length - 1].color : metric.breaksByLayer[0][4].color : '';
      loadCensusMetric(metric);
    });
  });
  map.on('moveend zoomend', debounce(() => CENSUS_METRICS.forEach(loadCensusMetric), 350));

  // Freizeit & Infrastruktur aus OpenStreetMap. Läuft über /api/poi (unseren
  // eigenen Server-Proxy mit Cache) statt direkt gegen Overpass — siehe
  // Kommentar bei den CSS-Regeln oben. Punktdaten statt Flächenraster, daher
  // Marker statt Choropleth, und ein fester Mindest-Zoom (dichte Städte
  // hätten sonst tausende Treffer in einem Rutsch).
  const POI_CATEGORIES = [
    { key: 'playground', label: 'Spielplätze', color: '#3B7A52' },
    { key: 'food_drink', label: 'Kneipen & Cafés', color: '#A63603' },
    { key: 'leisure', label: 'Freizeit', color: '#31576D' },
    { key: 'university', label: 'Unis', color: '#54278F' },
    { key: 'school', label: 'Kitas & Schulen', color: '#2171B5' },
    { key: 'doctor', label: 'Ärzte & Apotheken', color: '#DE2D26' },
    { key: 'supermarket', label: 'Supermärkte', color: '#D9971E' },
    { key: 'transit', label: 'ÖPNV-Haltestellen', color: '#1B2420' }
  ];
  const POI_MIN_ZOOM = 14;

  // Kleines Dreieck als Inline-SVG statt divIcon-Text — SVG erlaubt einen
  // echten weißen Rand (stroke) um die Farbfläche, der reine CSS-Border-Trick
  // für Dreiecke (Border-Winkel) kann das nicht sauber, siehe CSS-Kommentar
  // bei .poi-triangle-icon.
  function poiTriangleIcon(color) {
    const svg = '<svg width="16" height="16" viewBox="0 0 16 16">' +
      '<polygon points="8,1.5 15,14.5 1,14.5" fill="' + color + '" stroke="white" stroke-width="1.5" stroke-linejoin="round"/></svg>';
    return L.divIcon({ className: 'poi-triangle-icon', html: svg, iconSize: [16, 16], iconAnchor: [8, 8] });
  }

  const poiState = {};
  POI_CATEGORIES.forEach(c => { poiState[c.key] = { layer: null, enabled: false, requestId: 0 }; });

  function updatePoiHint() {
    const hint = document.getElementById('poi-hint');
    const anyEnabled = POI_CATEGORIES.some(c => poiState[c.key].enabled);
    if (!anyEnabled) { hint.textContent = ''; return; }
    if (map.getZoom() < POI_MIN_ZOOM) {
      hint.textContent = 'Weiter reinzoomen, um Standorte zu sehen (ab Stadtteil-Ebene).';
    } else {
      hint.textContent = 'Daten von OpenStreetMap-Mitwirkenden.';
    }
  }

  async function loadPoiCategory(cat) {
    const state = poiState[cat.key];
    const myRequestId = ++state.requestId;

    if (state.layer) { map.removeLayer(state.layer); state.layer = null; }
    if (!state.enabled) { updatePoiHint(); return; }

    updatePoiHint();
    if (map.getZoom() < POI_MIN_ZOOM) return;

    const b = map.getBounds();
    const bbox = [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()].join(',');
    const res = await fetch('/api/poi?' + new URLSearchParams({ category: cat.key, bbox }));
    const data = await res.json();

    if (myRequestId !== state.requestId || !state.enabled) return;

    if (data.hinweis) {
      document.getElementById('poi-hint').textContent = data.hinweis;
    }

    state.layer = L.layerGroup(
      (data.points || []).map(p => L.marker([p.lat, p.lon], {
        icon: poiTriangleIcon(cat.color)
      }).bindTooltip(escapeHtml(p.name || cat.label)))
    ).addTo(map);
  }

  const PoiControl = L.Control.extend({
    options: { position: 'bottomright' },
    onAdd: function () {
      const el = L.DomUtil.create('div', 'poi-control');
      el.innerHTML =
        '<div class="panel-title">Freizeit &amp; Infrastruktur (OSM)</div>' +
        '<div class="poi-metric-grid">' +
        POI_CATEGORIES.map(c => '<button type="button" class="poi-metric-btn" data-key="' + c.key + '">' + c.label + '</button>').join('') +
        '</div>' +
        '<div class="hint" id="poi-hint"></div>';
      L.DomEvent.disableClickPropagation(el);
      return el;
    }
  });
  map.addControl(new PoiControl());

  const poiByKey = Object.fromEntries(POI_CATEGORIES.map(c => [c.key, c]));
  document.querySelectorAll('.poi-metric-btn').forEach(btn => {
    const cat = poiByKey[btn.dataset.key];
    btn.addEventListener('click', () => {
      const state = poiState[cat.key];
      state.enabled = !state.enabled;
      btn.classList.toggle('active', state.enabled);
      btn.style.background = state.enabled ? cat.color : '';
      loadPoiCategory(cat);
    });
  });
  map.on('moveend zoomend', debounce(() => POI_CATEGORIES.forEach(loadPoiCategory), 350));

  // Klima & Luft — Kontext-Kärtchen für die Kartenmitte statt Kachel-Layer
  // (siehe CSS-Kommentar oben). Zwei eigenständige, aber inhaltlich
  // zusammengehörige Datenquellen: Klimanormalwerte kommen aus unserer
  // eigenen DB (einmalig importiert), Luftqualität live von der UBA.
  let climateEnabled = false;
  let climateRequestId = 0;

  function monthLabel(key) {
    return { jan: 'Jan', feb: 'Feb', mar: 'Mär', apr: 'Apr', mai: 'Mai', jun: 'Jun', jul: 'Jul', aug: 'Aug', sep: 'Sep', okt: 'Okt', nov: 'Nov', dez: 'Dez' }[key];
  }

  async function loadClimateCard() {
    const myRequestId = ++climateRequestId;
    const card = document.getElementById('climate-card');
    const hint = document.getElementById('climate-hint');

    if (!climateEnabled) { card.classList.remove('visible'); hint.textContent = ''; return; }

    hint.textContent = 'Lädt…';
    const center = map.getCenter();
    const [climateRes, airRes] = await Promise.all([
      fetch('/api/climate?' + new URLSearchParams({ lat: center.lat, lon: center.lng })),
      fetch('/api/air-quality?' + new URLSearchParams({ lat: center.lat, lon: center.lng }))
    ]);
    const climate = await climateRes.json();
    const air = await airRes.json();

    if (myRequestId !== climateRequestId || !climateEnabled) return;

    hint.textContent = 'Werte für die Kartenmitte — beim Verschieben der Karte aktualisiert sich die Anzeige.';

    const rows = [];
    if (climate.temperatur) {
      rows.push(['Ø Jahrestemperatur', climate.temperatur.jahreswert + ' °C']);
      rows.push(['↳ wärmster/kältester Monat', monthLabel('jul') + ' ' + climate.temperatur.monate.jul + ' °C / ' + monthLabel('jan') + ' ' + climate.temperatur.monate.jan + ' °C']);
    }
    if (climate.niederschlag) {
      rows.push(['Ø Jahresniederschlag', climate.niederschlag.jahreswert + ' mm']);
    }
    if (air.index_label) {
      rows.push(['Luftqualität (' + air.station + ')', air.index_label + ' · PM10 ' + (air.werte.PM10 ?? '–') + ' · NO2 ' + (air.werte.NO2 ?? '–') + ' µg/m³']);
    } else {
      rows.push(['Luftqualität', 'Keine Daten in der Nähe verfügbar']);
    }

    card.innerHTML = rows.map(([k, v]) => '<div class="row"><span class="k">' + escapeHtml(k) + '</span><span class="v">' + escapeHtml(v) + '</span></div>').join('')
      + '<div class="src">Klima: DWD, 1991–2020 · Luft: Umweltbundesamt' + (air.gemessen_bis ? ', Stand ' + escapeHtml(String(air.gemessen_bis).slice(0, 10)) : '') + '</div>';
    card.classList.add('visible');
  }

  const ClimateControl = L.Control.extend({
    options: { position: 'bottomright' },
    onAdd: function () {
      const el = L.DomUtil.create('div', 'climate-control');
      el.innerHTML =
        '<button type="button" id="climate-btn">Klima &amp; Luft anzeigen</button>' +
        '<div class="hint" id="climate-hint"></div>' +
        '<div class="climate-card" id="climate-card"></div>';
      L.DomEvent.disableClickPropagation(el);
      return el;
    }
  });
  map.addControl(new ClimateControl());

  document.getElementById('climate-btn').addEventListener('click', (e) => {
    climateEnabled = !climateEnabled;
    e.target.textContent = climateEnabled ? 'Klima & Luft ausblenden' : 'Klima & Luft anzeigen';
    e.target.classList.toggle('active', climateEnabled);
    loadClimateCard();
  });
  map.on('moveend', debounce(loadClimateCard, 350));

  // Breitband — WMS-Kachel-Layer der Bundesnetzagentur (Breitbandatlas),
  // öffentlich ohne Nutzungsbeschränkung/Vereinbarung (siehe GetCapabilities-
  // Dokumentation). "≥ 50 Mbit/s, alle Unternehmen" als bewusst einfache
  // Schwelle für "brauchbare Grundversorgung" — kein amtlich vorgegebener
  // Wert, sondern unsere eigene Wahl für eine erste, verständliche Ebene.
  // Anders als Zensus (ArcGIS-Rohdaten) liefert WMS fertige Bildkacheln:
  // keine eigene Farbskala möglich, nur an/aus, daher ohne Legende.
  const broadbandLayer = L.tileLayer.wms('https://breitbandatlas-wms.gigabit-grundbuch.online/WMS/Breitbandatlas?', {
    layers: 'Breitband_Festnetz__100m_Gitter__Alle_Unternehmen_≥_50_Mbit/s55313',
    format: 'image/png',
    transparent: true,
    version: '1.3.0',
    opacity: 0.6,
    attribution: '© BNetzA. Datenstand: 12.2025.'
  });
  let broadbandEnabled = false;

  const BroadbandControl = L.Control.extend({
    options: { position: 'bottomright' },
    onAdd: function () {
      const el = L.DomUtil.create('div', 'broadband-control');
      el.innerHTML =
        '<button type="button" id="broadband-btn">Breitband anzeigen</button>' +
        '<div class="hint">Festnetz-Verfügbarkeit &ge; 50 Mbit/s, alle Anbieter (100m-Raster). Sichtbar, sobald der Maßstab unten links „1 km" oder genauer anzeigt. &copy; BNetzA, Datenstand 12.2025.</div>';
      L.DomEvent.disableClickPropagation(el);
      return el;
    }
  });
  map.addControl(new BroadbandControl());

  document.getElementById('broadband-btn').addEventListener('click', (e) => {
    broadbandEnabled = !broadbandEnabled;
    e.target.textContent = broadbandEnabled ? 'Breitband ausblenden' : 'Breitband anzeigen';
    e.target.classList.toggle('active', broadbandEnabled);
    if (broadbandEnabled) {
      broadbandLayer.addTo(map);
    } else {
      map.removeLayer(broadbandLayer);
    }
  });

  // Info-Buttons (kleines "i" neben einem Kennzahlen-Label) — Klick statt
  // Hover, weil die Panels mit 230px zu schmal für ein lesbares
  // title-Tooltip sind. containerEl ist ein Leaflet-Control-Element mit
  // disableClickPropagation — ein globaler document-Klick-Handler würde
  // Klicks darin nie sehen, deshalb pro Control ein eigener Listener.
  function attachInfoPopovers(containerEl) {
    containerEl.addEventListener('click', (e) => {
      containerEl.querySelectorAll('.info-popover').forEach((p) => p.remove());
      const btn = e.target.closest('.info-btn');
      if (!btn || btn.dataset.open === '1') {
        if (btn) btn.dataset.open = '';
        return;
      }
      containerEl.querySelectorAll('.info-btn').forEach((b) => { b.dataset.open = ''; });
      const pop = document.createElement('div');
      pop.className = 'info-popover';
      pop.textContent = btn.dataset.info;
      btn.insertAdjacentElement('afterend', pop);
      btn.dataset.open = '1';
    });
  }

  // Wirtschaft — Arbeitslosenquote und Kriminalitätsrate des Kreises der
  // Kartenmitte. Anders als Klima/Luft (nächste Station, reine DB-Abfrage)
  // steckt hinter /api/economy serverseitig ein zweistufiger Nachschlag
  // (erst Kreis per Reverse-Geocoding bestimmen, dann beide Kennzahlen aus
  // der eigenen DB lesen) — für die Karte selbst macht das keinen
  // Unterschied, genau dasselbe Kärtchen-Muster wie beim Klima-Kontrollfeld.
  let economyEnabled = false;
  let economyRequestId = 0;

  async function loadEconomyCard() {
    const myRequestId = ++economyRequestId;
    const card = document.getElementById('economy-card');
    const hint = document.getElementById('economy-hint');

    if (!economyEnabled) { card.classList.remove('visible'); hint.textContent = ''; return; }

    hint.textContent = 'Lädt…';
    const center = map.getCenter();
    const res = await fetch('/api/economy?' + new URLSearchParams({ lat: center.lat, lon: center.lng }));
    const data = await res.json();

    if (myRequestId !== economyRequestId || !economyEnabled) return;

    hint.textContent = 'Werte für die Kartenmitte (' + (data.kreis || '–') + ') — beim Verschieben der Karte aktualisiert sich die Anzeige.';

    const rows = [];
    const sources = [];
    const number = (n) => n.toLocaleString('de-DE');

    if (data.arbeitslosenquote) {
      rows.push(['Arbeitslosenquote', data.arbeitslosenquote.quote.toFixed(1).replace('.', ',') + ' %',
        'Anteil der Arbeitslosen an allen Erwerbspersonen im Kreis — klassischer Indikator für die wirtschaftliche Lage vor Ort.']);
      sources.push('Arbeitslosenquote: Statistik der Bundesagentur für Arbeit, Stand ' + data.arbeitslosenquote.monat);
    } else {
      rows.push(['Arbeitslosenquote', 'Keine Daten verfügbar']);
    }
    if (data.kriminalitaet) {
      rows.push(['Straftaten je 100.000 Einw.', number(Math.round(data.kriminalitaet.haeufigkeitszahl)),
        'Häufigkeitszahl der Polizeilichen Kriminalstatistik — alle erfassten Straftaten, normiert auf 100.000 Einwohner, damit Kreise unterschiedlicher Größe vergleichbar sind.']);
      sources.push('Kriminalitätsrate: Polizeiliche Kriminalstatistik (BKA), Berichtsjahr ' + data.kriminalitaet.jahr);
    } else {
      rows.push(['Straftaten je 100.000 Einw.', 'Keine Daten verfügbar']);
    }
    if (data.grundsicherungImAlter) {
      rows.push(['Empfänger Grundsicherung im Alter', number(data.grundsicherungImAlter.anzahl) + ' (±2, gerundet)',
        'Anzahl der Rentner/-innen im Kreis, die ergänzende Grundsicherung beziehen — ein Signal für Altersarmut in der Region.']);
      sources.push('Grundsicherung im Alter: Regionaldatenbank Deutschland (Destatis), Berichtsjahr ' + data.grundsicherungImAlter.jahr);
    } else {
      rows.push(['Empfänger Grundsicherung im Alter', 'Keine Daten verfügbar']);
    }
    if (data.elterngeld) {
      rows.push(['Elterngeld-Bezieher (4 Quartale)', number(data.elterngeld.anzahl),
        'Anzahl der Elterngeld-Leistungsbezüge im Kreis über die letzten vier Quartale — grober Anhaltspunkt für junge Familien in der Region.']);
      sources.push('Elterngeld: Regionaldatenbank Deutschland (Destatis), bis Berichtsjahr ' + data.elterngeld.jahr);
    } else {
      rows.push(['Elterngeld-Bezieher (4 Quartale)', 'Keine Daten verfügbar']);
    }
    if (data.wohnungsbestand) {
      rows.push(['Wohnungsbestand', number(data.wohnungsbestand.anzahl),
        'Anzahl der Wohnungen im Kreis zum Stichtag 31.12. — direktester Indikator für das Wohnungsangebot vor Ort; die Wachstumsrate über mehrere Jahre zeigt, ob eine Region mit dem Bedarf mithält.']);
      sources.push('Wohnungsbestand: Regionaldatenbank Deutschland (Destatis), Stand 31.12.' + data.wohnungsbestand.jahr);
    } else {
      rows.push(['Wohnungsbestand', 'Keine Daten verfügbar']);
    }
    if (data.einkommen) {
      rows.push(['Verfügbares Einkommen je Einw.', number(Math.round(data.einkommen.jeEinwohner)) + ' €',
        'Verfügbares Einkommen der privaten Haushalte je Einwohner und Jahr — gängiger Kaufkraft-/Wohlstandsindikator zum Vergleich von Regionen.']);
      sources.push('Verfügbares Einkommen: Regionaldatenbank Deutschland (Destatis), Berichtsjahr ' + data.einkommen.jahr);
    } else {
      rows.push(['Verfügbares Einkommen je Einw.', 'Keine Daten verfügbar']);
    }
    if (data.wanderungssaldo) {
      const saldo = data.wanderungssaldo.saldo;
      rows.push(['Wanderungssaldo', (saldo > 0 ? '+' : '') + number(saldo),
        'Zuzüge minus Fortzüge über Gemeindegrenzen im Kreis binnen eines Jahres. Positiv = die Region zieht mehr Menschen an, als sie verliert — ein Signal für Nachfragedruck auf den Wohnungsmarkt.']);
      sources.push('Wanderungssaldo: Regionaldatenbank Deutschland (Destatis), Berichtsjahr ' + data.wanderungssaldo.jahr);
    } else {
      rows.push(['Wanderungssaldo', 'Keine Daten verfügbar']);
    }
    if (data.baugenehmigungen) {
      rows.push(['Baugenehmigungen (Wohnungen)', number(data.baugenehmigungen.anzahl),
        'Neu genehmigte Wohnungen im Kreis binnen eines Jahres — Frühindikator: zeigt, was in den nächsten 1–2 Jahren an zusätzlichem Angebot voraussichtlich nachkommt.']);
      sources.push('Baugenehmigungen: Regionaldatenbank Deutschland (Destatis), Berichtsjahr ' + data.baugenehmigungen.jahr);
    } else {
      rows.push(['Baugenehmigungen (Wohnungen)', 'Keine Daten verfügbar']);
    }
    if (data.baufertigstellungen) {
      rows.push(['Baufertigstellungen (Wohnungen)', number(data.baufertigstellungen.anzahl),
        'Tatsächlich fertiggestellte Wohnungen im Kreis binnen eines Jahres. Deutlich weniger Fertigstellungen als Genehmigungen deutet auf Bau-Rückstand oder -Verzögerungen in der Region hin.']);
      sources.push('Baufertigstellungen: Regionaldatenbank Deutschland (Destatis), Berichtsjahr ' + data.baufertigstellungen.jahr);
    } else {
      rows.push(['Baufertigstellungen (Wohnungen)', 'Keine Daten verfügbar']);
    }
    if (data.gewerbeanzeigen) {
      const saldo = data.gewerbeanzeigen.anmeldungen - data.gewerbeanzeigen.abmeldungen;
      rows.push(['Gewerbe-Saldo (An–Abmeldungen)', (saldo > 0 ? '+' : '') + number(saldo),
        'Neu angemeldete minus abgemeldete Gewerbe im Kreis binnen eines Jahres — Signal für wirtschaftliche Dynamik bzw. Gründungsklima vor Ort.']);
      sources.push('Gewerbeanzeigen: Regionaldatenbank Deutschland (Destatis), Berichtsjahr ' + data.gewerbeanzeigen.jahr);
    } else {
      rows.push(['Gewerbe-Saldo (An–Abmeldungen)', 'Keine Daten verfügbar']);
    }
    if (data.insolvenzen) {
      rows.push(['Insolvenzen (gesamt)', number(data.insolvenzen.anzahl),
        'Beantragte Insolvenzverfahren im Kreis binnen eines Jahres (Unternehmen und Privatpersonen zusammen) — Signal für wirtschaftliche Stabilität der Region.']);
      sources.push('Insolvenzen: Regionaldatenbank Deutschland (Destatis), Berichtsjahr ' + data.insolvenzen.jahr);
    } else {
      rows.push(['Insolvenzen (gesamt)', 'Keine Daten verfügbar']);
    }

    card.innerHTML = rows.map(([k, v, info]) => {
      const infoBtn = info ? '<button type="button" class="info-btn" data-info="' + escapeHtml(info) + '">i</button>' : '';
      return '<div class="row"><span class="k">' + escapeHtml(k) + infoBtn + '</span><span class="v">' + escapeHtml(v) + '</span></div>';
    }).join('')
      + '<div class="src">' + escapeHtml(sources.join(' · ')) + '</div>';
    card.classList.add('visible');
  }

  const EconomyControl = L.Control.extend({
    options: { position: 'bottomright' },
    onAdd: function () {
      const el = L.DomUtil.create('div', 'economy-control');
      el.innerHTML =
        '<button type="button" id="economy-btn">Wirtschaft anzeigen</button>' +
        '<div class="hint" id="economy-hint"></div>' +
        '<div class="economy-card" id="economy-card"></div>';
      L.DomEvent.disableClickPropagation(el);
      attachInfoPopovers(el);
      return el;
    }
  });
  map.addControl(new EconomyControl());

  document.getElementById('economy-btn').addEventListener('click', (e) => {
    economyEnabled = !economyEnabled;
    e.target.textContent = economyEnabled ? 'Wirtschaft ausblenden' : 'Wirtschaft anzeigen';
    e.target.classList.toggle('active', economyEnabled);
    loadEconomyCard();
  });
  map.on('moveend', debounce(loadEconomyCard, 350));

  // Suche mit Autovervollständigung
  const searchInput = document.getElementById('search');
  const suggestions = document.getElementById('suggestions');
  let searchTimeout = null;

  searchInput.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    const q = searchInput.value.trim();
    if (q.length < 2) {
      suggestions.hidden = true;
      return;
    }
    searchTimeout = setTimeout(async () => {
      const res = await fetch('/api/search?q=' + encodeURIComponent(q));
      const data = await res.json();
      suggestions.innerHTML = '';
      if (!data.results || data.results.length === 0) {
        suggestions.hidden = true;
        return;
      }
      data.results.forEach(r => {
        const item = document.createElement('div');
        item.textContent = r.street_name + ' (Abschnitt ' + r.segment_index + '), ' + r.postal_code + ' ' + r.city;
        item.addEventListener('click', () => {
          map.setView([r.centroid_lat, r.centroid_lon], 16);
          suggestions.hidden = true;
          searchInput.value = item.textContent;
        });
        suggestions.appendChild(item);
      });
      suggestions.hidden = false;
    }, 250);
  });

  document.addEventListener('click', (e) => {
    if (e.target !== searchInput) suggestions.hidden = true;
  });

  // Tutorial — geführte Tour durch die Bedienelemente. Selektoren zeigen auf
  // bereits vorhandene Panels/Controls; fehlt eines (z.B. künftig entfernt),
  // wird der Schritt beim Durchlaufen übersprungen statt die Tour abzubrechen.
  // Census/POI beschreiben ihre Kennzahlen/Kategorien als Liste statt eines
  // einzelnen Sammelsatzes (html statt text) — beide Panels fassen sonst zu
  // viele unterschiedliche Dinge hinter einem Button zusammen, um sie in
  // einem Satz verständlich zu erklären.
  const CENSUS_METRIC_DESC = {
    population: 'Einwohner je Rasterzelle',
    age: 'Durchschnittsalter der Bevölkerung',
    rent: 'Ø Nettokaltmiete pro Quadratmeter',
    ownership: 'Anteil selbstgenutzten Eigentums',
    vacancy: 'Anteil leerstehender Wohnungen'
  };

  function censusTourHtml() {
    return 'Fünf Kennzahlen aus dem Zensus 2022, einzeln oder kombiniert als Kartenraster einblendbar:' +
      '<ul class="tour-list">' +
      CENSUS_METRICS.map(m => '<li><span class="tour-swatch" style="background:' + (m.breaks ? m.breaks[m.breaks.length - 1].color : m.breaksByLayer[0][4].color) + '"></span><span><strong>' + m.label + ':</strong> ' + CENSUS_METRIC_DESC[m.key] + '</span></li>').join('') +
      '</ul>';
  }

  function poiTourHtml() {
    return 'Acht Kategorien aus OpenStreetMap, jede als eigenfarbiges Dreieck auf der Karte:' +
      '<ul class="tour-list">' +
      POI_CATEGORIES.map(c => '<li><span class="tour-swatch triangle" style="background:' + c.color + '"></span><span>' + c.label + '</span></li>').join('') +
      '</ul>';
  }

  // Wirtschaft ist das dichteste Panel (zwölf Kennzahlen) — die Tour springt
  // hier bewusst zu einem Beispielort (Frankfurt am Main), an dem zuverlässig
  // für alle Kennzahlen Daten vorliegen, und schaltet das Panel automatisch
  // scharf, statt nur auf den Button zu zeigen. So sieht man beim ersten
  // Kontakt eine echte Aufschlüsselung statt eines leeren Panels.
  const TOUR_EXAMPLE_LOCATION = [50.1109, 8.6821];

  function economyTourEnter() {
    map.setView(TOUR_EXAMPLE_LOCATION, 11);
    if (!economyEnabled) {
      economyEnabled = true;
      const btn = document.getElementById('economy-btn');
      btn.textContent = 'Wirtschaft ausblenden';
      btn.classList.add('active');
    }
    loadEconomyCard();
  }

  const TOUR_STEPS = [
    { selector: '#search-group', title: 'Straßen finden', text: 'Hier findest du bereits bewertete Straßen über die Suche.' },
    { selector: '#rate-cta', title: 'Straße bewerten', text: 'Wohnst du hier? Über diesen Button gibst du deine eigene Bewertung zu deiner Straße ab.' },
    { selector: '.ratings-control', title: 'Bewertungen ein-/ausblenden', text: 'Blendet die farbigen Bewertungs-Punkte aus — praktisch, wenn du nur die Statistik-Ebenen sehen willst. Was Grün, Orange und Rotbraun bedeuten, erklärt die kleine Legende oben links unter dem Zoom-Regler.' },
    { selector: '.census-control', title: 'Amtliche Statistik', html: censusTourHtml },
    { selector: '.poi-control', title: 'Freizeit & Infrastruktur', html: poiTourHtml },
    { selector: '.climate-control', title: 'Klima & Luft', text: 'Temperatur, Niederschlag und Luftqualität für die aktuelle Kartenmitte.' },
    { selector: '.broadband-control', title: 'Breitband', text: 'Verfügbarkeit von schnellem Festnetz-Internet (≥ 50 Mbit/s).' },
    {
      selector: '.economy-control', title: 'Wirtschaft',
      text: 'Arbeitslosenquote, Kriminalität, Einkommen, Wanderungssaldo u.v.m. für den Kreis der Kartenmitte — hier am Beispiel Frankfurt am Main. Die kleinen (i)-Symbole neben den Zeilen erklären, warum die jeweilige Zahl für die Wohnlage relevant ist.',
      onEnter: economyTourEnter
    },
    { selector: '#vision-link', title: 'Vision', text: 'Ein Blick auf eine Beispielkarte mit vielen (frei erfundenen) Bewertungen — so könnte es aussehen, wenn viele Menschen mitmachen.' },
    { selector: '#examples-btn', title: 'Praxisbeispiele', text: 'Fünf fiktive Personen — Rentnerin, Student, Pendlerin, Kapitalanleger, Familie — und je drei Rückschlüsse, wie sie die Kennzahlen auf dieser Karte für ihre eigene Entscheidung nutzen könnten.' },
    { selector: '#legal', title: 'Rechtliches', text: 'Impressum, Datenschutzerklärung und mehr über Habitat Feedback.' }
  ];

  const tourClickguard = document.getElementById('tour-clickguard');
  const tourSpotlight = document.getElementById('tour-spotlight');
  const tourTooltip = document.getElementById('tour-tooltip');
  let tourStep = -1;

  function tourPosition(step) {
    const target = document.querySelector(step.selector);
    if (!target) return false;
    const r = target.getBoundingClientRect();
    const pad = 6;
    Object.assign(tourSpotlight.style, {
      left: (r.left - pad) + 'px', top: (r.top - pad) + 'px',
      width: (r.width + pad * 2) + 'px', height: (r.height + pad * 2) + 'px'
    });

    const tw = 320;
    let left = r.left + r.width / 2 - tw / 2;
    left = Math.max(10, Math.min(left, window.innerWidth - tw - 10));
    // Reichhaltigere Schritte (Listen mit bis zu 8 Einträgen) sind deutlich
    // höher als ein einzelner Satz — 300px statt 150px als Platzreserve,
    // bevor die Tooltip-Box oberhalb statt unterhalb des Ziels landet.
    let top = r.bottom + pad + 14;
    if (top + 300 > window.innerHeight) top = Math.max(10, r.top - pad - 14 - 300);
    Object.assign(tourTooltip.style, { left: left + 'px', top: top + 'px', width: tw + 'px' });

    tourTooltip.querySelector('.tour-progress').textContent = (tourStep + 1) + ' / ' + TOUR_STEPS.length;
    tourTooltip.querySelector('.tour-title').textContent = step.title;
    const textEl = tourTooltip.querySelector('.tour-text');
    if (step.html) {
      textEl.innerHTML = typeof step.html === 'function' ? step.html() : step.html;
    } else {
      textEl.textContent = step.text;
    }
    const backBtn = tourTooltip.querySelector('.tour-back');
    backBtn.disabled = tourStep === 0;
    tourTooltip.querySelector('.tour-next').textContent = tourStep === TOUR_STEPS.length - 1 ? 'Fertig' : 'Weiter';
    return true;
  }

  function tourShowFrom(index, direction) {
    const prevStep = (tourStep >= 0 && tourStep < TOUR_STEPS.length) ? TOUR_STEPS[tourStep] : null;
    tourStep = index;
    while (tourStep >= 0 && tourStep < TOUR_STEPS.length && !document.querySelector(TOUR_STEPS[tourStep].selector)) {
      tourStep += direction;
    }
    if (tourStep < 0 || tourStep >= TOUR_STEPS.length) { tourEnd(); return; }

    const step = TOUR_STEPS[tourStep];
    if (prevStep && prevStep !== step && prevStep.onLeave) prevStep.onLeave();
    if (step.onEnter) step.onEnter();
    tourPosition(step);
    // onEnter kann Panels asynchron nachladen (z.B. Wirtschaft) — die
    // Zielgröße ändert sich dadurch erst nach der Antwort, daher eine kurze
    // Nachkorrektur der Position, falls der Nutzer inzwischen nicht schon
    // weitergeklickt hat.
    if (step.onEnter) {
      setTimeout(() => { if (tourStep >= 0 && TOUR_STEPS[tourStep] === step) tourPosition(step); }, 400);
    }
  }

  function tourStart() {
    tourClickguard.hidden = false;
    tourSpotlight.hidden = false;
    tourTooltip.hidden = false;
    tourShowFrom(0, 1);
  }

  function tourEnd() {
    tourStep = -1;
    tourClickguard.hidden = true;
    tourSpotlight.hidden = true;
    tourTooltip.hidden = true;
  }

  function tourNext() {
    if (tourStep >= TOUR_STEPS.length - 1) { tourEnd(); return; }
    tourShowFrom(tourStep + 1, 1);
  }

  function tourBack() {
    if (tourStep <= 0) return;
    tourShowFrom(tourStep - 1, -1);
  }

  document.getElementById('tutorial-btn').addEventListener('click', tourStart);
  tourTooltip.querySelector('.tour-next').addEventListener('click', tourNext);
  tourTooltip.querySelector('.tour-back').addEventListener('click', tourBack);
  tourTooltip.querySelector('.tour-skip').addEventListener('click', tourEnd);
  window.addEventListener('keydown', (e) => { if (tourStep >= 0 && e.key === 'Escape') tourEnd(); });
  window.addEventListener('resize', () => { if (tourStep >= 0) tourPosition(TOUR_STEPS[tourStep]); });

  // Willkommens-Overlay — nur beim allerersten Besuch, dezent verzögert statt
  // sofort beim Laden, und danach dauerhaft weg (localStorage), egal auf
  // welchem der drei Wege (Schließen, "Vielleicht später" oder tatsächlich
  // bewerten) es verlassen wurde. Kein Timer zum automatischen Ausblenden —
  // wer die Karte gerade erst entdeckt, soll in Ruhe lesen können.
  (function () {
    const STORAGE_KEY = 'wl_welcome_dismissed';
    let alreadySeen = false;
    try { alreadySeen = !!localStorage.getItem(STORAGE_KEY); } catch (e) {}
    if (alreadySeen) return;

    const overlay = document.getElementById('welcome-overlay');
    function dismissWelcome() {
      overlay.classList.remove('visible');
      try { localStorage.setItem(STORAGE_KEY, '1'); } catch (e) {}
    }
    overlay.querySelector('.welcome-close').addEventListener('click', dismissWelcome);
    overlay.querySelector('.welcome-dismiss').addEventListener('click', dismissWelcome);
    overlay.querySelector('.welcome-cta').addEventListener('click', dismissWelcome);
    setTimeout(() => overlay.classList.add('visible'), 1200);
  })();

  // Praxisbeispiele — fünf fiktive Personas, die zeigen, wie sich die
  // vorhandenen Kennzahlen (Bewertungskriterien, Zensus, Wirtschaft,
  // OSM-Kategorien, Breitband) tatsächlich für eine Entscheidung nutzen
  // lassen. Bewusst nur 3 Rückschlüsse pro Person statt einer erschöpfenden
  // Liste — die Idee ist ein Denkanstoß, keine vollständige Anleitung.
  const EXAMPLES = [
    {
      name: 'Helga, 71 — auf der Suche nach einem ruhigen Alterssitz',
      teaser: 'Rentnerin, zieht näher zur Familie',
      story: 'Helga Brenner, 71, verwitwet, wohnt seit 40 Jahren in einer Kölner Altbauwohnung im 4. Stock ohne Aufzug — die Treppen werden ihr zu anstrengend. Sie möchte näher zu ihrer Tochter nach Bonn ziehen, aber nicht direkt bei ihr wohnen. Mit dabei: ihr Mischlingshund „Keks". Sie gärtnert leidenschaftlich gern und hat ihren Führerschein vor zwei Jahren abgegeben — wichtig sind ihr kurze Wege zu Fuß und ein vertrautes, ruhiges Viertel.',
      insights: [
        '<strong>Ruhe</strong>- und <strong>Sicherheitsgefühl</strong>-Bewertungen zusammen vergleichen: Beide Kriterien zeigen, welche Abschnitte sich für abendliche Spaziergänge mit Keks eignen.',
        'POI-Kategorien <strong>Ärzte und Apotheken</strong> und <strong>ÖPNV-Haltestellen</strong> gleichzeitig einblenden — ohne Auto zählt jeder Schritt bis zur nächsten Haltestelle oder zum Hausarzt.',
        'Zensus-Kennzahl <strong>Ø Alter</strong>: ein hoher Wert deutet oft auf eine etablierte, seit Jahrzehnten gewachsene Nachbarschaft hin, in der Helga sich eher wiederfindet als in einem jungen Neubauviertel.'
      ]
    },
    {
      name: 'Kevin, 22 — WG-Zimmer für das 5. Semester',
      teaser: 'Student, knappes Budget',
      story: 'Kevin Ostrowski, 22, 5. Semester Wirtschaftsinformatik an der Frankfurt University of Applied Sciences. Zieht aus dem Wohnheim aus, weil er dort keinen Platz mehr bekommt, sucht WG-Zimmer oder kleine Wohnung. Studentenjob im Café, knappes Budget von rund 500 € warm, spielt abends gern Volleyball und geht gern mit Freunden aus. Braucht kurze Wege zur Uni und mag urbanes Flair.',
      insights: [
        'Zensus-Kennzahl <strong>Ø Miete/m²</strong> als erster Budget-Check — bevor Kevin sich einzelne Abschnitte genauer ansieht, filtert die Kennzahl grob, welche Viertel finanziell überhaupt realistisch sind.',
        'POI-Kategorien <strong>Unis</strong> und <strong>ÖPNV-Haltestellen</strong> gleichzeitig einblenden — zeigt auf einen Blick, wie weit ein Abschnitt vom Campus entfernt liegt und ob eine gute Anbindung in der Nähe ist.',
        'POI-Kategorie <strong>Kneipen und Cafés</strong> als informelles Signal für studentisches Leben im Viertel — viele Treffpunkte in Laufnähe sprechen für die Art Nachbarschaft, in der Kevin sich wohlfühlen würde.'
      ]
    },
    {
      name: 'Julia, 34 — Eigentumswohnung im Rhein-Main-Gebiet',
      teaser: 'Angestellte, pendelt nach Frankfurt',
      story: 'Julia Vogt, 34, Teamleiterin im Risikomanagement einer Frankfurter Bank. Sucht eine Eigentumswohnung im Rhein-Main-Gebiet, will aber nicht direkt im teuren Frankfurter Zentrum kaufen. Arbeitet zwei Tage die Woche im Homeoffice, pendelt die restlichen drei Tage mit der S-Bahn. Legt Wert auf ein gutes Fitnessstudio und Restaurants in der Nähe und denkt langfristig auch über Familienplanung nach.',
      insights: [
        'Wirtschafts-Kennzahlen <strong>Arbeitslosenquote</strong> und <strong>Wanderungssaldo</strong> des Kreises zusammen betrachten — niedrige Arbeitslosigkeit plus positiver Wanderungssaldo deuten auf eine wirtschaftlich stabile Region mit Zuzugsdruck hin.',
        '<strong>Breitband</strong>-Ebene einblenden, bevor die engere Auswahl beginnt — bei zwei Homeoffice-Tagen pro Woche ist eine verlässliche Internetverbindung Grundvoraussetzung, nicht nur Komfort.',
        'Zensus-Kennzahl <strong>Ø Miete/m²</strong> zwischen Frankfurter Stadtteilen und dem Umland gegenüberstellen, um die eigentliche Abwägung zu treffen: günstigerer Kaufpreis im Umland gegen längere Pendelzeit.'
      ]
    },
    {
      name: 'Markus, 52 — eine Wohnung als Kapitalanlage',
      teaser: 'Investor, denkt langfristig',
      story: 'Markus Thalberg, 52, Ingenieur, hat vor Kurzem eine kleine Erbschaft erhalten und überlegt, statt eines Aktiensparplans eine Wohnung als Kapitalanlage zu kaufen und zu vermieten. Kein Interesse an Sanierungsobjekten — die Wohnung soll unkompliziert vermietbar sein. Er denkt in Zeiträumen von 15 bis 20 Jahren und will vor allem eine Region mit stabiler oder steigender Nachfrage finden.',
      insights: [
        'Wirtschafts-Kennzahl <strong>Wanderungssaldo</strong> als Nachfrage-Indikator — ein dauerhaft positiver Saldo (mehr Zuzüge als Fortzüge) spricht für Wohnungsknappheit und tendenziell stabile bis steigende Mieten.',
        '<strong>Baugenehmigungen</strong> und <strong>Baufertigstellungen</strong> gegenüberstellen — klafft hier über mehrere Jahre eine große Lücke, deutet das auf einen Bau-Rückstand hin: das Angebot hält nicht mit der Nachfrage Schritt.',
        'Zensus-Kennzahl <strong>Leerstandsquote</strong> — ein niedriger Wert zeigt, dass Wohnraum in der Region knapp und stark ausgelastet ist, was das Vermietungsrisiko senkt.'
      ]
    },
    {
      name: 'Melanie & Tobias, Anfang 40 — mehr Platz für die Familie',
      teaser: 'Eltern zweier Kinder (8 und 5)',
      story: 'Melanie und Tobias Hartwig, beide Anfang 40, zwei Kinder — Emma (8) und Paul (5). Sie wohnen zur Miete in einer 3-Zimmer-Wohnung, die für vier Personen zu eng geworden ist, und suchen ein Reihenhaus oder eine große Wohnung mit Garten oder zumindest Balkon. Emma kommt bald in die weiterführende Schule, Paul wird im nächsten Jahr eingeschult. Ein Auto ist vorhanden, aber die Kinder sollen perspektivisch allein zur Schule laufen können.',
      insights: [
        'POI-Kategorien <strong>Kitas und Schulen</strong> und <strong>Spielplätze</strong> gemeinsam einblenden — zeigt sofort, ob genug Einrichtungen in fußläufiger Entfernung liegen, damit Emma und Paul irgendwann eigenständig unterwegs sein können.',
        '<strong>Sicherheitsgefühl</strong>-Bewertung zusammen mit der Wirtschafts-Kennzahl <strong>Straftaten je 100.000 Einwohner</strong> betrachten — die eine ist die Wahrnehmung der Nachbarschaft, die andere die amtliche Statistik des ganzen Kreises.',
        'Zensus-Kennzahl <strong>Eigentümerquote</strong> — ein hoher Wert deutet oft auf eine Siedlung mit vielen Eigenheimen und wenig Fluktuation hin, häufig ein Zeichen für gewachsene, familienfreundliche Nachbarschaften.'
      ]
    }
  ];

  function renderExamples() {
    const list = document.getElementById('examples-list');
    list.innerHTML = EXAMPLES.map((ex, i) =>
      '<div class="example-item" data-index="' + i + '">' +
        '<button type="button" class="example-toggle">' +
          '<span><span class="name">' + ex.name + '</span><span class="teaser">' + ex.teaser + '</span></span>' +
          '<span class="chevron">▾</span>' +
        '</button>' +
        '<div class="example-body">' +
          '<p class="story">' + ex.story + '</p>' +
          '<div class="insights-title">Was die Zahlen zeigen</div>' +
          '<ol>' + ex.insights.map(t => '<li>' + t + '</li>').join('') + '</ol>' +
        '</div>' +
      '</div>'
    ).join('');
    list.querySelectorAll('.example-toggle').forEach(btn => {
      btn.addEventListener('click', () => btn.closest('.example-item').classList.toggle('open'));
    });
  }
  renderExamples();

  const examplesBackdrop = document.getElementById('examples-backdrop');
  const examplesModal = document.getElementById('examples-modal');
  function examplesOpen() { examplesBackdrop.hidden = false; examplesModal.hidden = false; }
  function examplesClose() { examplesBackdrop.hidden = true; examplesModal.hidden = true; }

  document.getElementById('examples-btn').addEventListener('click', examplesOpen);
  document.getElementById('examples-close').addEventListener('click', examplesClose);
  examplesBackdrop.addEventListener('click', examplesClose);
  window.addEventListener('keydown', (e) => { if (!examplesModal.hidden && e.key === 'Escape') examplesClose(); });
</script>
</body>
</html>
