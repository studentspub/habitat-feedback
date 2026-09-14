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

  /* Drei Bedienfelder stapeln sich unten rechts (Census, POI, Klima) und
     können zusammen höher werden als der sichtbare Kartenausschnitt —
     ohne Deckel würden sie irgendwann mit "Straße bewerten?" oben rechts
     kollidieren. Deckel auf den gesamten Eckbereich statt auf jedes Feld
     einzeln, damit es unabhängig davon greift, wie viele Felder aktiv sind. */
  .leaflet-bottom.leaflet-right { max-height: calc(100vh - 110px); overflow-y: auto; padding-right: 2px; }

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
  .poi-marker { border-radius: 50%; border: 1.5px solid white; box-shadow: 0 0 0 1px rgba(27,36,32,0.3); }

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
</style>
</head>
<body>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
  const map = L.map('map').setView([51.1657, 10.4515], 6); // Deutschland-Übersicht

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>-Mitwirkende'
  }).addTo(map);

  L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(map);

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

  async function loadPins() {
    const b = map.getBounds();
    const bbox = [b.getSouth(), b.getWest(), b.getNorth(), b.getEast()].join(',');
    const res = await fetch('/api/map?bbox=' + encodeURIComponent(bbox));
    const data = await res.json();

    (data.segments || []).forEach(seg => {
      const marker = L.circleMarker([seg.centroid_lat, seg.centroid_lon], {
        radius: 9,
        color: '#1B2420',
        weight: 1,
        fillColor: colorForScore(parseFloat(seg.avg_overall)),
        fillOpacity: 0.85
      }).addTo(map);

      marker.bindPopup(
        '<div class="wl-popup"><h3>' + seg.street_name + ' (Abschnitt ' + seg.segment_index + ')</h3>' +
        '<p>' + seg.postal_code + ' ' + seg.city + '</p>' +
        '<p class="score">Gesamt: ' + parseFloat(seg.avg_overall).toFixed(1) + ' / 5</p>' +
        '<p>' + seg.rating_count + ' Bewertungen &middot; ' + seg.address_count + ' Adressen im Abschnitt</p>' +
        '<p><a href="/melden?segment=' + seg.id + '">Bewertungen melden</a></p></div>'
      );
    });
  }

  map.on('moveend', debounce(loadPins, 350));
  loadPins();

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
      (data.points || []).map(p => L.circleMarker([p.lat, p.lon], {
        radius: 5, className: 'poi-marker', color: cat.color, weight: 1,
        fillColor: cat.color, fillOpacity: 0.9
      }).bindTooltip(p.name || cat.label))
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

    card.innerHTML = rows.map(([k, v]) => '<div class="row"><span class="k">' + k + '</span><span class="v">' + v + '</span></div>').join('')
      + '<div class="src">Klima: DWD, 1991–2020 · Luft: Umweltbundesamt' + (air.gemessen_bis ? ', Stand ' + air.gemessen_bis.slice(0, 10) : '') + '</div>';
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

  // Wirtschaft — Arbeitslosenquote des Kreises der Kartenmitte. Anders als
  // Klima/Luft (nächste Station, reine DB-Abfrage) steckt hinter /api/economy
  // serverseitig ein zweistufiger Nachschlag (erst Kreis per Reverse-Geocoding
  // bestimmen, dann Quote aus der eigenen DB lesen) — für die Karte selbst
  // macht das keinen Unterschied, genau dasselbe Kärtchen-Muster wie beim
  // Klima-Kontrollfeld.
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

    hint.textContent = 'Wert für die Kartenmitte — beim Verschieben der Karte aktualisiert sich die Anzeige.';

    if (data.quote !== null && data.quote !== undefined) {
      card.innerHTML =
        '<div class="row"><span class="k">Arbeitslosenquote (' + data.kreis + ')</span><span class="v">' + data.quote.toFixed(1).replace('.', ',') + ' %</span></div>'
        + '<div class="src">Statistik der Bundesagentur für Arbeit, Stand ' + data.monat + '</div>';
    } else {
      card.innerHTML = '<div class="row"><span class="k">Arbeitslosenquote</span><span class="v">Keine Daten verfügbar</span></div>';
    }
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
</script>
</body>
</html>
