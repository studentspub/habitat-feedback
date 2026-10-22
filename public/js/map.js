// Logik der Kartenseite (aus map.view.php ausgelagert, Issue #13: eigene Datei = Browser-Cache, CSP ohne Inline-Skripte).
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
  habitatMaske(map); // Nebel über dem Ausland, Bewegungsgrenze (Issue #12)

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

  // Street-View-Männchen (Issue #11): Wir betten kein Street View ein,
  // sondern erzeugen nur einen Link nach Googles „Maps URLs“-Schema (kein
  // API-Key). Bewusst kein window.open beim Ablegen: ein echter Link im
  // Popup wird von keinem Popup-Blocker geschluckt, und erst der bewusste
  // Klick schickt den Nutzer (und damit Daten) zu Google.
  const STREETVIEW_URL = 'https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=';
  const PEGMAN_ICON = L.divIcon({ className: 'pegman-icon', html: '🧍', iconSize: [26, 26], iconAnchor: [13, 24] });
  let streetViewMarker = null;

  function streetViewPopupHtml(latlng) {
    const viewpoint = latlng.lat.toFixed(6) + ',' + latlng.lng.toFixed(6);
    return '<div class="sv-popup"><a href="' + STREETVIEW_URL + viewpoint + '" target="_blank" rel="noopener noreferrer">📷 Straßenansicht bei Google öffnen ↗</a>' +
      '<div class="hint">Öffnet Google Maps in einem neuen Tab — sofern Google an dieser Stelle Bilder hat. Männchen verschieben, um die Stelle zu ändern.</div></div>';
  }

  // Nach dem Absetzen per Finger (Handy, Rückmeldung in #27) war das Männchen
  // bis zum nächsten Neuzeichnen unsichtbar. Deshalb nach jedem Ziehen: Marker
  // neu aufsetzen (frisches Icon an der neuen Stelle), Kartengröße neu lesen
  // und die Karte so nachziehen, dass Männchen und Popup im Bild sind.
  function streetViewMarkerAuffrischen() {
    if (!streetViewMarker) return;
    const latlng = streetViewMarker.getLatLng();
    map.removeLayer(streetViewMarker);
    map.invalidateSize({ pan: false });
    streetViewMarker.addTo(map);
    map.panInside(latlng, { paddingTopLeft: [20, 150], paddingBottomRight: [20, 70] });
    streetViewMarker.openPopup();
  }

  function placeStreetViewMarker(latlng) {
    if (streetViewMarker) map.removeLayer(streetViewMarker);
    streetViewMarker = L.marker(latlng, { icon: PEGMAN_ICON, draggable: true, autoPan: true }).addTo(map);
    streetViewMarker.bindPopup(() => streetViewPopupHtml(streetViewMarker.getLatLng()), { offset: [0, -18] });
    streetViewMarker.on('dragstart', () => streetViewMarker.closePopup());
    streetViewMarker.on('dragend', streetViewMarkerAuffrischen);
    streetViewMarker.openPopup();
    document.querySelector('.streetview-control a').classList.add('active');
  }

  function removeStreetViewMarker() {
    if (streetViewMarker) map.removeLayer(streetViewMarker);
    streetViewMarker = null;
    document.querySelector('.streetview-control a').classList.remove('active');
  }

  const StreetViewControl = L.Control.extend({
    options: { position: 'topleft' },
    onAdd: function () {
      const el = L.DomUtil.create('div', 'leaflet-bar streetview-control');
      el.innerHTML = '<a href="#" role="button" title="Straßenansicht: Männchen auf die Karte setzen und an die gewünschte Stelle ziehen (oder Rechtsklick / lange drücken auf die Karte)" aria-label="Straßenansicht">🧍</a>';
      L.DomEvent.disableClickPropagation(el);
      L.DomEvent.on(el.firstChild, 'click', e => {
        L.DomEvent.preventDefault(e);
        if (streetViewMarker) removeStreetViewMarker(); else placeStreetViewMarker(map.getCenter());
      });
      return el;
    }
  });
  map.addControl(new StreetViewControl());
  // Rechtsklick bzw. langes Drücken (Handy) setzt das Männchen direkt dorthin.
  map.on('contextmenu', e => placeStreetViewMarker(e.latlng));

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

  // Veraltete Anfragen abbrechen (Issue #13): wer schnell schwenkt, erzeugt
  // Anfragen, deren Antworten ohnehin verworfen würden — bisher wurden sie
  // trotzdem vollständig geladen. Je Name läuft höchstens eine Anfrage;
  // abgebrochen liefert holeJson() null.
  //
  // Rückgabe (Issue #28): Objekt = Daten; null = abgebrochen, weil eine neuere
  // Anfrage läuft (nichts tun, die neuere aktualisiert die Anzeige); false =
  // fehlgeschlagen (Server weg, Netz weg, HTTP-Fehler) - dann zeigen die
  // Kärtchen einen Hinweis statt für immer „Lädt…“. Je Name wird ein Fehler nur
  // einmal in der Konsole gemeldet, bis es wieder klappt.
  const laufendeAnfragen = {};
  const gemeldeteFehler = {};
  const NICHT_ERREICHBAR = 'Daten gerade nicht erreichbar. Beim nächsten Verschieben der Karte wird es erneut versucht.';
  async function holeJson(name, url) {
    if (laufendeAnfragen[name]) laufendeAnfragen[name].abort();
    const controller = new AbortController();
    laufendeAnfragen[name] = controller;
    try {
      const res = await fetch(url, { signal: controller.signal });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      delete gemeldeteFehler[name];
      return data;
    } catch (e) {
      if (e.name === 'AbortError') return null;
      if (!gemeldeteFehler[name]) {
        gemeldeteFehler[name] = true;
        console.warn('Anfrage „' + name + '“ fehlgeschlagen (' + url + '): ' + e.message);
      }
      return false;
    }
  }

  // Ausschnitt bzw. Kartenmitte auf ein 0,01°-Raster (≈ 1 km) runden —
  // Ausschnitt nach außen, damit nichts fehlt. Gleiche Stelle = gleiche URL,
  // dann greifen die Cache-Control-Header der Referenzdaten-Endpunkte.
  function rasterBbox() {
    const b = map.getBounds();
    return [Math.floor(b.getWest() * 100) / 100, Math.floor(b.getSouth() * 100) / 100,
      Math.ceil(b.getEast() * 100) / 100, Math.ceil(b.getNorth() * 100) / 100].join(',');
  }
  function rasterMitte() {
    const c = map.getCenter();
    return { lat: (Math.round(c.lat * 100) / 100).toFixed(2), lon: (Math.round(c.lng * 100) / 100).toFixed(2) };
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
    const data = await holeJson('pins', '/api/map?bbox=' + encodeURIComponent(bbox));
    if (!data) return;

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
        '<p class="unfall-zeile" data-lat="' + escapeHtml(seg.centroid_lat) + '" data-lon="' + escapeHtml(seg.centroid_lon) + '">Unfälle in 250 m: lädt …</p>' +
        '<p><a href="/melden?segment=' + encodeURIComponent(seg.id) + '">Bewertungen melden</a></p></div>'
      );
    });
  }

  map.on('moveend', debounce(loadPins, 350));

  // Unfallzahl im Bewertungs-Popup erst beim Öffnen holen (Issue #20).
  map.on('popupopen', async (e) => {
    const el = e.popup.getElement() && e.popup.getElement().querySelector('.unfall-zeile');
    if (!el) return;
    const u = await holeJson('unfall-popup', '/api/unfaelle?' + new URLSearchParams({ lat: el.dataset.lat, lon: el.dataset.lon, radius: 250 }));
    if (u === null) return;
    if (u === false) { el.textContent = 'Unfalldaten gerade nicht erreichbar.'; return; }
    el.textContent = u.jahre
      ? 'Unfälle mit Personenschaden in 250 m (' + u.jahre + '): ' + u.gesamt + ' — davon ' + u.rad + ' mit Rad, ' + u.fuss + ' mit Fußgängern'
      : 'Keine Unfalldaten geladen.';
  });
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

  // Amtliche Statistik-Ebenen: Zensus 2022. Seit Issue #23 aus der eigenen
  // DB (/api/zensus, Gitterdaten von Destatis, bin/import_zensus.php) statt
  // live vom ArcGIS-Dienst bei Esri — keine Besucher-IP mehr in die USA.
  // /api/zensus liefert dasselbe GeoJSON-Format wie früher ArcGIS.
  //
  // Die Daten liegen fertig aggregiert auf drei Rasterstufen (100m/1km/10km)
  // vor; Hessen allein hätte ca. 44.500 100m-Zellen. Die Rasterstufe wird
  // daher anhand der tatsächlichen Fläche des Kartenausschnitts gewählt,
  // wie bei einer Wetterkarte.
  const CENSUS_RESOLUTIONS = [
    { layer: 0, stufe: '100m', maxAreaKm2: 20, label: '100m-Raster (Straßenblock)' },
    { layer: 1, stufe: '1km', maxAreaKm2: 1500, label: '1km-Raster (Stadt/Region)' },
    { layer: 2, stufe: '10km', maxAreaKm2: 150000, label: '10km-Raster (Bundesland-Maßstab)' }
  ];
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
      // ­ = weiche Trennstelle: unsichtbar, erlaubt in schmalen Feldern (Tablet, #27) einen sauberen Umbruch.
      key: 'population', label: 'Einwohner­dichte', field: 'Einwohner', unit: 'Einw.',
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
      key: 'ownership', label: 'Eigentümer­quote', field: 'Eigentuemerquote', unit: '%',
      breaks: [
        { max: 20, color: '#EDF8E9' }, { max: 40, color: '#BAE4B3' }, { max: 60, color: '#74C476' },
        { max: 80, color: '#31A354' }, { max: Infinity, color: '#006D2C' }
      ],
      labels: ['bis 20%', '20–40%', '40–60%', '60–80%', 'ab 80%']
    },
    {
      key: 'vacancy', label: 'Leerstands­quote', field: 'Leerstandsquote', unit: '%',
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

  // Zensus-Zellen auf Canvas (Issue #26): als SVG war jede Zelle ein eigenes
  // <path>-Element - bei zwei Kennzahlen auf Stadtteil-Zoom über 2.000, bis
  // zu 8.000 auf großen Bildschirmen -, dasselbe Problem wie die POI-Marker
  // vor #13. Ein gemeinsamer Canvas für alle Kennzahlen zeichnet alles in
  // ein Bild. Eigene Ebene zwischen Nebel (350) und den übrigen Daten-Ebenen
  // (overlayPane 400): so liegt der Zensus immer unter Pins, POIs, Unfällen
  // und ÖPNV, egal in welcher Reihenfolge die Ebenen eingeschaltet wurden
  // (bringToBack() ordnet nur innerhalb eines Renderers, nicht Canvas gegen Canvas).
  map.createPane('zensusPane').style.zIndex = 380;
  const zensusRenderer = L.canvas({ padding: 0.5, pane: 'zensusPane' });

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
      hint.textContent = 'Amtliche Zensus-2022-Daten (Statistisches Bundesamt), ' + resolution.label + '. Weiter reinzoomen für feinere Auflösung.';
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
    const url = '/api/zensus?' + new URLSearchParams({ stufe: resolution.stufe, feld: metric.field, bbox: rasterBbox() });
    const geojson = await holeJson('zensus-' + metric.key, url);
    if (!geojson) return;

    // Zwischenzeitlich wieder ausgeschaltet oder von einer neueren Anfrage
    // für dieselbe Kennzahl überholt? Dann dieses veraltete Ergebnis verwerfen.
    if (myRequestId !== state.requestId || !state.enabled) return;

    // Zellen ohne Wert (z.B. unbewohnte Fläche) werden nicht eingefärbt,
    // statt fälschlich in die unterste Stufe zu fallen.
    geojson.features = geojson.features.filter(f => f.properties[metric.field] !== null && f.properties[metric.field] !== undefined);

    // interactive: false - die Zellen haben weder Popup noch Tooltip; sonst
    // prüfte der Canvas bei jeder Mausbewegung alle Zellen auf Treffer.
    state.layer = L.geoJSON(geojson, {
      renderer: zensusRenderer, pane: 'zensusPane', interactive: false,
      style: f => ({
        fillColor: breaks.find(br => f.properties[metric.field] <= br.max).color,
        fillOpacity: CENSUS_FILL_OPACITY, color: 'rgba(27,36,32,0.15)', weight: 1
      })
    }).addTo(map);
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
  // Farben (Issue #10): je Kategorie ein eigener Farbton mit genug Abstand
  // in Ton UND Helligkeit, damit auch ähnliche Paare (Unis/ÖPNV,
  // Freizeit/Kita) auseinanderzuhalten sind; keine Grüntöne, weil der Rand
  // neongrün ist. textColor = Schrift auf dem aktiven Knopf (hell bei
  // dunklen Farben, dunkel bei Gelb/Orange).
  const POI_CATEGORIES = [
    { key: 'playground', label: 'Spielplätze', color: '#EC407A', textColor: 'white' },
    { key: 'food_drink', label: 'Kneipen & Cafés', color: '#FF8F00', textColor: '#1B2420' },
    { key: 'leisure', label: 'Freizeit', color: '#00897B', textColor: 'white' },
    { key: 'university', label: 'Unis', color: '#8E24AA', textColor: 'white' },
    { key: 'school', label: 'Kitas & Schulen', color: '#FDD835', textColor: '#1B2420' },
    { key: 'doctor', label: 'Ärzte & Apotheken', color: '#E53935', textColor: 'white' },
    { key: 'supermarket', label: 'Supermärkte', color: '#1E88E5', textColor: 'white' },
    { key: 'transit', label: 'ÖPNV-Haltestellen', color: '#263238', textColor: 'white' },
    // Ladesäulen kommen nicht aus OSM, sondern aus dem BNetzA-Register (Issue #22)
    { key: 'charging', label: 'Ladesäulen', color: '#6D4C41', textColor: 'white', quelle: 'Ladesäulen: Bundesnetzagentur.de (CC BY 4.0)' },
    // Issue #1: drei weitere OSM-Kategorien. Hellgrau, Indigo und Cyan halten
    // Abstand zu den neun bestehenden Tönen (kein Grün wegen des Neonrands).
    // Toiletten haben einen Unterschalter „nur barrierefrei“ (Merkmal b aus /api/poi).
    { key: 'toilets', label: 'Toiletten', color: '#CFD8DC', textColor: '#1B2420',
      unterschalter: { id: 'poi-toilets-barrierefrei', label: 'nur barrierefreie Toiletten', filter: p => p.b === 1 } },
    { key: 'post', label: 'Post & Pakete', color: '#3949AB', textColor: 'white' },
    { key: 'ebike', label: 'E-Bike-Laden', color: '#00BCD4', textColor: '#1B2420' }
  ];
  const POI_MIN_ZOOM = 14;
  const POI_BORDER_COLOR = '#39FF14'; // leuchtendes Neongrün (TODO.md, User)

  // POI-Dreiecke auf Canvas (Issue #13): früher war jedes Dreieck ein
  // eigenes DOM-Element (divIcon + SVG) — bei 8 Kategorien × 500 Punkten
  // bis zu 4.000 Elemente, die bei jedem Zoom neu positioniert wurden.
  // Jetzt zeichnet ein gemeinsamer Canvas-Renderer alles in ein Bild.
  // Form und Maße wie das frühere 22-px-SVG: Spitze 8,5 px über, Grundlinie
  // 8 px unter der Mitte, 19 px breit; Rand neongrün 2,25 px (Issue #10).
  L.Canvas.include({
    _updatePoiDreieck(layer) {
      if (!this._drawing || layer._empty()) return;
      const p = layer._point, ctx = this._ctx;
      ctx.beginPath();
      ctx.moveTo(p.x, p.y - 8.5);
      ctx.lineTo(p.x + 9.5, p.y + 8);
      ctx.lineTo(p.x - 9.5, p.y + 8);
      ctx.closePath();
      this._fillStroke(ctx, layer);
    }
  });
  const PoiDreieck = L.CircleMarker.extend({
    _updatePath() { this._renderer._updatePoiDreieck(this); }
  });
  const poiRenderer = L.canvas({ padding: 0.5 });

  const poiState = {};
  POI_CATEGORIES.forEach(c => { poiState[c.key] = { layer: null, enabled: false, punkte: [] }; });
  let poiRequestId = 0;

  function updatePoiHint() {
    const hint = document.getElementById('poi-hint');
    const anyEnabled = POI_CATEGORIES.some(c => poiState[c.key].enabled);
    if (!anyEnabled) { hint.textContent = ''; return; }
    if (map.getZoom() < POI_MIN_ZOOM) {
      hint.textContent = 'Weiter reinzoomen, um Standorte zu sehen (ab Stadtteil-Ebene).';
    } else {
      const extra = POI_CATEGORIES.filter(c => c.quelle && poiState[c.key].enabled).map(c => ' · ' + c.quelle).join('');
      hint.textContent = 'Daten von OpenStreetMap-Mitwirkenden' + extra + '.';
    }
  }

  function removePoiLayer(key) {
    const state = poiState[key];
    if (state.layer) { map.removeLayer(state.layer); state.layer = null; }
  }

  // Eine Anfrage für alle aktiven Kategorien statt einer je Kategorie.
  // Die alten Dreiecke bleiben stehen, bis die neuen da sind (kein Flackern).
  async function loadPois() {
    const myRequestId = ++poiRequestId;
    updatePoiHint();
    const aktiv = POI_CATEGORIES.filter(c => poiState[c.key].enabled);
    if (aktiv.length === 0 || map.getZoom() < POI_MIN_ZOOM) {
      POI_CATEGORIES.forEach(c => removePoiLayer(c.key));
      return;
    }

    const data = await holeJson('poi', '/api/poi?' + new URLSearchParams({ categories: aktiv.map(c => c.key).join(','), bbox: rasterBbox() }));
    if (!data || myRequestId !== poiRequestId) return;

    POI_CATEGORIES.forEach(c => removePoiLayer(c.key));
    aktiv.forEach(cat => {
      if (!poiState[cat.key].enabled) return;
      poiState[cat.key].punkte = (data.categories || {})[cat.key] || [];
      zeichnePoi(cat);
    });
  }

  // Zeichnet die zuletzt geladenen Punkte einer Kategorie; ein Unterschalter
  // (Issue #1, „nur barrierefreie Toiletten“) filtert nur, ohne neue Anfrage.
  function zeichnePoi(cat) {
    removePoiLayer(cat.key);
    let punkte = poiState[cat.key].punkte;
    if (cat.unterschalter && document.getElementById(cat.unterschalter.id).checked) {
      punkte = punkte.filter(cat.unterschalter.filter);
    }
    poiState[cat.key].layer = L.layerGroup(punkte.map(p => new PoiDreieck([p.lat, p.lon], {
      renderer: poiRenderer, radius: 9, color: POI_BORDER_COLOR, weight: 2.25, opacity: 1,
      fillColor: cat.color, fillOpacity: 1, lineJoin: 'round'
    }).bindTooltip(escapeHtml(p.name || cat.label)))).addTo(map);
  }

  const PoiControl = L.Control.extend({
    options: { position: 'bottomright' },
    onAdd: function () {
      const el = L.DomUtil.create('div', 'poi-control');
      el.innerHTML =
        '<div class="panel-title">Freizeit &amp; Infrastruktur</div>' +
        '<div class="poi-metric-grid">' +
        POI_CATEGORIES.map(c => '<button type="button" class="poi-metric-btn" data-key="' + c.key + '">' + c.label + '</button>').join('') +
        '</div>' +
        // Unterschalter (Issue #1) erscheinen nur, solange ihre Kategorie an ist.
        POI_CATEGORIES.filter(c => c.unterschalter).map(c =>
          '<label class="poi-unterschalter" for="' + c.unterschalter.id + '" hidden>' +
          '<input type="checkbox" id="' + c.unterschalter.id + '"> ' + c.unterschalter.label + '</label>').join('') +
        '<div class="hint" id="poi-hint"></div>';
      L.DomEvent.disableClickPropagation(el);
      return el;
    }
  });
  map.addControl(new PoiControl());

  const poiByKey = Object.fromEntries(POI_CATEGORIES.map(c => [c.key, c]));
  const loadPoisNachKlick = debounce(loadPois, 150); // mehrere schnelle Klicks = eine Anfrage
  POI_CATEGORIES.filter(c => c.unterschalter).forEach(cat => {
    document.getElementById(cat.unterschalter.id).addEventListener('change', () => {
      if (poiState[cat.key].enabled && map.getZoom() >= POI_MIN_ZOOM) zeichnePoi(cat);
    });
  });
  document.querySelectorAll('.poi-metric-btn').forEach(btn => {
    const cat = poiByKey[btn.dataset.key];
    btn.addEventListener('click', () => {
      const state = poiState[cat.key];
      state.enabled = !state.enabled;
      btn.classList.toggle('active', state.enabled);
      btn.style.background = state.enabled ? cat.color : '';
      btn.style.color = state.enabled ? cat.textColor : '';
      if (cat.unterschalter) document.querySelector('label[for="' + cat.unterschalter.id + '"]').hidden = !state.enabled;
      if (state.enabled) {
        loadPoisNachKlick();
      } else {
        removePoiLayer(cat.key); // ausschalten braucht keine neue Anfrage
        updatePoiHint();
      }
    });
  });
  map.on('moveend zoomend', debounce(loadPois, 350));

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
    const mitte = new URLSearchParams(rasterMitte());
    const [climate, air] = await Promise.all([
      holeJson('klima', '/api/climate?' + mitte),
      holeJson('luft', '/api/air-quality?' + mitte)
    ]);
    if (climate === null || air === null) return; // abgebrochen, neuere Anfrage läuft

    if (myRequestId !== climateRequestId || !climateEnabled) return;
    if (climate === false || air === false) {
      hint.textContent = NICHT_ERREICHBAR;
      card.classList.remove('visible');
      return;
    }

    hint.textContent = 'Werte für die Kartenmitte — beim Verschieben der Karte aktualisiert sich die Anzeige.';

    const rows = [];
    if (climate.temperatur) {
      rows.push(['Ø Jahrestemperatur', climate.temperatur.jahreswert + ' °C']);
      rows.push(['↳ wärmster/kältester Monat', monthLabel('jul') + ' ' + climate.temperatur.monate.jul + ' °C / ' + monthLabel('jan') + ' ' + climate.temperatur.monate.jan + ' °C']);
    }
    if (climate.niederschlag) {
      rows.push(['Ø Jahresniederschlag', climate.niederschlag.jahreswert + ' mm']);
    }
    // 1-km-Raster des DWD genau am Ort (Issue #19): heute (1991–2020) und
    // in Klammern früher (1961–1990) — der Klimawandel vor der eigenen Haustür.
    if (climate.raster) {
      const r = climate.raster;
      const kenntag = (label, w, einheit) => {
        if (w.heute === null) return;
        rows.push([label, Math.round(w.heute) + ' ' + einheit + (w.frueher !== null ? ' (früher ' + Math.round(w.frueher) + ')' : '')]);
      };
      kenntag('Heiße Tage ≥ 30 °C / Jahr', r.heiss, 'Tage');
      kenntag('Sommertage ≥ 25 °C / Jahr', r.sommer, 'Tage');
      kenntag('Frosttage / Jahr', r.frost, 'Tage');
      kenntag('Tage mit Schneedecke', r.schnee, 'Tage');
      kenntag('Starkregen ≥ 20 mm / Jahr', r.starkregen, 'Tage');
      kenntag('Sonnenstunden / Jahr', r.sonne, 'h');
    }
    if (air.index_label) {
      rows.push(['Luftqualität (' + air.station + ')', air.index_label + ' · PM10 ' + (air.werte.PM10 ?? '–') + ' · NO2 ' + (air.werte.NO2 ?? '–') + ' µg/m³']);
    } else {
      rows.push(['Luftqualität', 'Keine Daten in der Nähe verfügbar']);
    }

    card.innerHTML = rows.map(([k, v]) => '<div class="row"><span class="k">' + escapeHtml(k) + '</span><span class="v">' + escapeHtml(v) + '</span></div>').join('')
      + '<div class="src">Klima: Deutscher Wetterdienst, 1991–2020' + (climate.raster ? ' (Kenntage am Ort, 1-km-Raster; „früher“ = 1961–1990)' : '') + ' · Luft: Umweltbundesamt' + (air.gemessen_bis ? ', Stand ' + escapeHtml(String(air.gemessen_bis).slice(0, 10)) : '') + '</div>';
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
    const data = await holeJson('wirtschaft', '/api/economy?' + new URLSearchParams(rasterMitte()));
    if (data === null) return; // abgebrochen, neuere Anfrage läuft

    if (myRequestId !== economyRequestId || !economyEnabled) return;
    if (data === false) {
      hint.textContent = NICHT_ERREICHBAR;
      card.classList.remove('visible');
      return;
    }

    const ort = data.gemeinde && data.gemeinde.name !== (data.kreis || '').split(',')[0] ? data.gemeinde.name + ', ' + (data.kreis || '–') : (data.kreis || '–');
    hint.textContent = 'Werte für den Kreis der Kartenmitte (' + ort + ') — beim Verschieben der Karte aktualisiert sich die Anzeige.';

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

  // Lebensqualität aus BBSR INKAR (Issue #18): Gemeinde- und Kreiswerte der
  // Kartenmitte (Fußwege zu Hausarzt/Apotheke/Schule/Supermarkt/Haltestelle,
  // Fahrzeiten, Pendeln, Grün, Unfälle, Ärzte, Kitas, Mieten …), je mit dem
  // Wert für ganz Deutschland. Grün/rot nur, wo „besser" eindeutig ist.
  let regionEnabled = false;
  let regionRequestId = 0;

  function zahlDe(wert, stellen) {
    return wert.toLocaleString('de-DE', { minimumFractionDigits: stellen, maximumFractionDigits: stellen });
  }

  async function loadRegionCard() {
    const myRequestId = ++regionRequestId;
    const card = document.getElementById('region-card');
    const hint = document.getElementById('region-hint');
    if (!regionEnabled) { card.classList.remove('visible'); hint.textContent = ''; return; }

    hint.textContent = 'Lädt…';
    const c = map.getCenter();
    const [data, unfall, oepnv] = await Promise.all([
      holeJson('region', '/api/region?' + new URLSearchParams(rasterMitte())),
      // Unfälle genauer als die 0,01°-Rasterung (≈ 100 m), sonst läge der 500-m-Kreis neben der Kartenmitte
      holeJson('unfall-umkreis', '/api/unfaelle?' + new URLSearchParams({ lat: c.lat.toFixed(3), lon: c.lng.toFixed(3), radius: 500 })),
      holeJson('oepnv-umkreis', '/api/oepnv?' + new URLSearchParams({ lat: c.lat.toFixed(3), lon: c.lng.toFixed(3), radius: 600 }))
    ]);
    if (data === null || myRequestId !== regionRequestId || !regionEnabled) return;
    if (data === false) {
      hint.textContent = NICHT_ERREICHBAR;
      card.classList.remove('visible');
      return;
    }
    if (!data.gemeinde) {
      hint.textContent = 'Die Kartenmitte liegt außerhalb Deutschlands.';
      card.classList.remove('visible');
      return;
    }

    hint.textContent = 'Gemeinde ' + data.gemeinde.name + ' (' + data.kreis.bez + ' ' + data.kreis.name + '). „DE" = Wert für ganz Deutschland.';
    card.innerHTML = data.gruppen.map(g => '<div class="grp">' + escapeHtml(g.titel) + '</div>' + g.werte.map(w => {
      const wert = w.wert === null ? 'keine Daten' : zahlDe(w.wert, w.stellen) + ' ' + w.einheit;
      let klasse = '';
      if (w.wert !== null && w.deutschland !== null && w.besser) {
        klasse = (w.besser === 'hoch' ? w.wert >= w.deutschland : w.wert <= w.deutschland) ? ' gut' : ' schlecht';
      }
      const de = w.deutschland !== null ? '<span class="de">DE ' + escapeHtml(zahlDe(w.deutschland, w.stellen)) + '</span>' : '';
      const info = '<button type="button" class="info-btn" data-info="' + escapeHtml(w.info + (w.jahr ? ' Stand: ' + w.jahr + '.' : '')) + '">i</button>';
      return '<div class="row"><span class="k">' + escapeHtml(w.label) + info + '</span><span class="v' + klasse + '">' + escapeHtml(wert) + de + '</span></div>';
    }).join('')).join('') + oepnvGruppeHtml(oepnv) + unfallGruppeHtml(unfall) + '<div class="src">' + escapeHtml(data.quelle) + ' · <a href="https://www.bbsr.bund.de" target="_blank" rel="noopener">Bundesinstitut für Bau-, Stadt- und Raumforschung</a>'
      + (oepnv && oepnv.stichtag ? ' · ÖPNV: ' + escapeHtml(oepnv.quelle) : '')
      + (unfall && unfall.jahre ? ' · Unfälle: ' + escapeHtml(unfall.quelle) : '') + '</div>';
    card.classList.add('visible');
  }

  // ÖPNV-Takt aus GTFS Deutschland (Issue #24): Stationen in 600 m um die
  // Kartenmitte mit ihren Abfahrten an einem normalen Werktag — „wie oft
  // fährt hier etwas“ statt nur „gibt es eine Haltestelle“.
  function oepnvGruppeHtml(o) {
    if (!o || !o.stichtag) return '';
    const datum = new Date(o.stichtag + 'T12:00:00').toLocaleDateString('de-DE', { weekday: 'short', day: 'numeric', month: 'numeric', year: 'numeric' });
    const info = 'Abfahrten aller Linien und Richtungen an einem normalen Werktag (' + datum + '), aus dem Fahrplan-Datensatz für ganz Deutschland. '
      + 'Zum Vergleich: ab ~100 Abfahrten am Tag fährt tagsüber etwa alle 15–20 Minuten etwas.';
    const zeile = (k, v, i) => '<div class="row"><span class="k">' + escapeHtml(k)
      + (i ? '<button type="button" class="info-btn" data-info="' + escapeHtml(i) + '">i</button>' : '')
      + '</span><span class="v">' + escapeHtml(v) + '</span></div>';
    let html = '<div class="grp">ÖPNV im Umkreis ' + o.radius + ' m (Werktag)</div>'
      + zeile('Haltestellen / Abfahrten', o.anzahl + ' / ' + o.abfahrten_summe.toLocaleString('de-DE'), info);
    if (o.stationen.length === 0) return html + zeile('Nächste Haltestelle', 'keine im Umkreis');
    return html + o.stationen.slice(0, 3).map(s => zeile(s.name + ' (' + s.entfernung + ' m)',
      s.abfahrten.toLocaleString('de-DE') + ' · ' + s.verkehrsmittel)).join('');
  }

  // Unfallatlas (Issue #20): Unfälle mit Personenschaden im Umkreis der
  // Kartenmitte. Mehr Unfälle heißt oft nur mehr Verkehr — deshalb als
  // Zählung ohne Rot/Grün.
  function unfallGruppeHtml(u) {
    if (!u || !u.jahre) return '';
    const zeile = (k, v, info) => '<div class="row"><span class="k">' + escapeHtml(k)
      + (info ? '<button type="button" class="info-btn" data-info="' + escapeHtml(info) + '">i</button>' : '')
      + '</span><span class="v">' + escapeHtml(v) + '</span></div>';
    return '<div class="grp">Unfälle im Umkreis ' + u.radius + ' m (' + escapeHtml(u.jahre) + ')</div>'
      + zeile('Mit Personenschaden', u.gesamt.toLocaleString('de-DE'), 'Alle polizeilich erfassten Straßenverkehrsunfälle mit Verletzten oder Getöteten in ' + u.radius + ' m um die Kartenmitte. Viel Verkehr (Hauptstraßen, Innenstadt) bedeutet fast immer auch mehr Unfälle.')
      + zeile('davon mit Getöteten / Schwerverletzten', u.getoetet + ' / ' + u.schwer)
      + zeile('davon mit Radbeteiligung', u.rad.toLocaleString('de-DE'))
      + zeile('davon mit Fußgängern', u.fuss.toLocaleString('de-DE'));
  }

  const RegionControl = L.Control.extend({
    options: { position: 'topright' },
    onAdd: function () {
      const el = L.DomUtil.create('div', 'economy-control region-control');
      el.innerHTML =
        '<button type="button" id="region-btn">Lebensqualität (BBSR) anzeigen</button>' +
        '<div class="neben-reihe"><button type="button" id="unfall-btn" class="neben-btn">Unfallorte</button>' +
        '<button type="button" id="oepnv-btn" class="neben-btn">ÖPNV-Takt</button></div>' +
        '<div class="neben-reihe"><button type="button" id="laerm-btn" class="neben-btn">Lärm</button>' +
        '<button type="button" id="hochwasser-btn" class="neben-btn">Hochwasser</button></div>' +
        '<div class="hint" id="ebenen-legende"></div>' +
        '<div class="hint" id="region-hint"></div>' +
        '<div class="economy-card region-card" id="region-card"></div>';
      L.DomEvent.disableClickPropagation(el);
      L.DomEvent.disableScrollPropagation(el);
      attachInfoPopovers(el);
      return el;
    }
  });
  const regionControl = new RegionControl();
  map.addControl(regionControl);

  document.getElementById('region-btn').addEventListener('click', (e) => {
    regionEnabled = !regionEnabled;
    e.target.textContent = regionEnabled ? 'Lebensqualität ausblenden' : 'Lebensqualität (BBSR) anzeigen';
    e.target.classList.toggle('active', regionEnabled);
    loadRegionCard();
  });
  map.on('moveend', debounce(loadRegionCard, 350));

  // Unfallorte als Punkte (ab Zoom 15): Farbe nach Schwere, Canvas wie die POIs.
  const UNFALL_MIN_ZOOM = 15;
  const UNFALL_FARBE = { 1: '#4A0D0D', 2: '#E53935', 3: '#FB8C00' };
  const UNFALL_TEXT = { 1: 'mit Getöteten', 2: 'mit Schwerverletzten', 3: 'mit Leichtverletzten' };
  const unfallRenderer = L.canvas({ padding: 0.5 });
  let unfallEnabled = false;
  let unfallLayer = null;
  let unfallRequestId = 0;

  async function loadUnfallPunkte() {
    const myRequestId = ++unfallRequestId;
    const hint = document.getElementById('region-hint');
    if (!unfallEnabled || map.getZoom() < UNFALL_MIN_ZOOM) {
      if (unfallLayer) { map.removeLayer(unfallLayer); unfallLayer = null; }
      if (unfallEnabled && !regionEnabled) hint.textContent = 'Unfallorte: weiter reinzoomen (ab Stadtteil-Ebene).';
      return;
    }
    const data = await holeJson('unfall-punkte', '/api/unfaelle/punkte?bbox=' + rasterBbox());
    if (!data || myRequestId !== unfallRequestId || !unfallEnabled) return;
    if (unfallLayer) map.removeLayer(unfallLayer);
    unfallLayer = L.layerGroup(data.punkte.map(([lat, lon, jahr, kat, rad, fuss]) => L.circleMarker([lat, lon], {
      renderer: unfallRenderer, radius: kat === 3 ? 4 : 5, weight: 1, color: '#FFFFFF', opacity: 0.9,
      fillColor: UNFALL_FARBE[kat], fillOpacity: 0.9
    }).bindTooltip(jahr + ': Unfall ' + UNFALL_TEXT[kat] + (rad ? ', mit Rad' : '') + (fuss ? ', mit Fußgänger' : '')))).addTo(map);
    if (!regionEnabled) hint.textContent = 'Unfallorte ' + (data.gekuerzt ? '(gekürzt, schwerste zuerst) ' : '') + '— Statistische Ämter des Bundes und der Länder, Unfallatlas.';
  }

  document.getElementById('unfall-btn').addEventListener('click', (e) => {
    unfallEnabled = !unfallEnabled;
    e.target.classList.toggle('active', unfallEnabled);
    e.target.textContent = unfallEnabled ? 'Unfallorte aus' : 'Unfallorte';
    if (!unfallEnabled && !regionEnabled) document.getElementById('region-hint').textContent = '';
    loadUnfallPunkte();
  });
  map.on('moveend', debounce(loadUnfallPunkte, 350));

  // ÖPNV-Takt als Kartenebene (Issue #24, ab Zoom 13): Kreis je Station,
  // Größe und Farbe nach Abfahrten am Werktag.
  const OEPNV_MIN_ZOOM = 13;
  const OEPNV_STUFEN = [
    { min: 500, farbe: '#1B5E20', r: 9, text: '≥ 500' }, { min: 150, farbe: '#43A047', r: 7, text: '150–499' },
    { min: 50, farbe: '#FDD835', r: 6, text: '50–149' }, { min: 20, farbe: '#FB8C00', r: 5, text: '20–49' },
    { min: 0, farbe: '#9E9E9E', r: 4, text: '< 20' }
  ];
  const oepnvRenderer = L.canvas({ padding: 0.5 });
  let oepnvEnabled = false;
  let oepnvLayer = null;
  let oepnvRequestId = 0;

  async function loadOepnvPunkte() {
    const myRequestId = ++oepnvRequestId;
    if (!oepnvEnabled || map.getZoom() < OEPNV_MIN_ZOOM) {
      if (oepnvLayer) { map.removeLayer(oepnvLayer); oepnvLayer = null; }
      return;
    }
    const data = await holeJson('oepnv-punkte', '/api/oepnv/punkte?bbox=' + rasterBbox());
    if (!data || myRequestId !== oepnvRequestId || !oepnvEnabled) return;
    if (oepnvLayer) map.removeLayer(oepnvLayer);
    // kleinste zuerst zeichnen, damit große Knoten obenauf liegen
    oepnvLayer = L.layerGroup(data.punkte.slice().reverse().map(([lat, lon, name, abf, abfTag, vm]) => {
      const st = OEPNV_STUFEN.find(s => abf >= s.min);
      return L.circleMarker([lat, lon], {
        renderer: oepnvRenderer, radius: st.r, weight: 1.5, color: '#FFFFFF', opacity: 1, fillColor: st.farbe, fillOpacity: 0.95
      }).bindTooltip(escapeHtml(name) + ': ' + abf.toLocaleString('de-DE') + ' Abfahrten/Werktag (6–20 Uhr: ' + abfTag.toLocaleString('de-DE') + ') · ' + escapeHtml(vm));
    })).addTo(map);
  }

  document.getElementById('oepnv-btn').addEventListener('click', (e) => {
    oepnvEnabled = !oepnvEnabled;
    e.target.classList.toggle('active', oepnvEnabled);
    e.target.textContent = oepnvEnabled ? 'ÖPNV-Takt aus' : 'ÖPNV-Takt';
    ebenenAktiv.oepnv = oepnvEnabled;
    ebenenLegendeAktualisieren();
    loadOepnvPunkte();
  });
  map.on('moveend', debounce(loadOepnvPunkte, 350));

  // Lärm und Hochwasser als zuschaltbare WMS-Ebenen (Issue #21), wie Breitband
  // ohne eigenen Import. Lärm: EU-Umgebungslärmkartierung (END) über die
  // Europäische Umweltagentur, Straße + Schiene, Lden. Hochwasser: Flächen
  // eines 100-jährlichen Hochwassers (HQ100) an Flüssen und Küste, BfG.
  const EEA_LAERM = 'https://copernicus.discomap.eea.europa.eu/arcgis/services/noiseStoryMap/';
  const BFG_HW = 'https://geoportal.bafg.de/arcgis/services/HWRMRL/';
  const laermLayer = L.layerGroup(['road', 'rail'].map(art => L.tileLayer.wms(EEA_LAERM + 'NoiseContours_' + art + '_lden/ImageServer/WMSServer', {
    layers: 'NoiseContours_' + art + '_lden', format: 'image/png', transparent: true, opacity: 0.55,
    attribution: 'Lärm: <a href="https://www.eea.europa.eu" target="_blank" rel="noopener">EEA</a> (END-Lärmkartierung)'
  })));
  const hochwasserLayer = L.layerGroup(['HWRMRL_DE_LM', 'HWRMRL_DE_SM'].map(dienst => L.tileLayer.wms(BFG_HW + dienst + '/MapServer/WMSServer', {
    layers: '0', format: 'image/png', transparent: true, opacity: 0.6,
    attribution: 'Hochwasser: <a href="https://geoportal.bafg.de/karten/HWRM/" target="_blank" rel="noopener">BfG</a> / Länder (HWRM-RL, Stand 2019)'
  })));
  const EBENEN_LEGENDE = {
    laerm: 'Lärm (Lden, Mittel über Tag, Abend und Nacht): '
      + '<span class="lg" style="background:#FFD54F"></span>55–59 '
      + '<span class="lg" style="background:#FF8F00"></span>60–64 '
      + '<span class="lg" style="background:#E53935"></span>65–69 '
      + '<span class="lg" style="background:#8E1B1B"></span>70–74 '
      + '<span class="lg" style="background:#9C8FD0"></span>≥ 75 dB. Kartiert sind Ballungsräume und Hauptverkehrswege, nicht jede Straße.',
    hochwasser: '<span class="lg" style="background:#0070FF"></span>Überflutungsfläche bei einem 100-jährlichen Hochwasser (HQ100) an Flüssen und Küste. '
      + 'Ohne Schutz durch Deiche nicht gezeigt; Starkregen fehlt. Stand 2019, gemeldet von den Ländern.'
  };
  EBENEN_LEGENDE.oepnv = 'ÖPNV-Takt (Abfahrten je Werktag, ab Zoom ' + OEPNV_MIN_ZOOM + '): '
    + OEPNV_STUFEN.map(s => '<span class="lg" style="background:' + s.farbe + '"></span>' + s.text).join(' ');
  const ebenenAktiv = { laerm: false, hochwasser: false, oepnv: false };

  function ebenenLegendeAktualisieren() {
    document.getElementById('ebenen-legende').innerHTML =
      Object.keys(ebenenAktiv).filter(k => ebenenAktiv[k]).map(k => '<div>' + EBENEN_LEGENDE[k] + '</div>').join('');
  }

  [['laerm-btn', 'laerm', laermLayer], ['hochwasser-btn', 'hochwasser', hochwasserLayer]].forEach(([id, key, layer]) => {
    document.getElementById(id).addEventListener('click', (e) => {
      ebenenAktiv[key] = !ebenenAktiv[key];
      e.target.classList.toggle('active', ebenenAktiv[key]);
      if (ebenenAktiv[key]) layer.addTo(map); else map.removeLayer(layer);
      ebenenLegendeAktualisieren();
    });
  });

  // Handy-Layout (Issue #27): unter 760 px Breite liegen die Bedienfelder nicht
  // mehr dauerhaft auf der Karte (sie verdeckten sie dort komplett), sondern
  // in einer Schublade von unten, die der Knopf „Ebenen“ unten rechts öffnet.
  // Die Schublade IST der Leaflet-Container unten rechts (CSS in map.css,
  // body.ebenen-offen); die Felder bleiben dieselben Elemente mit denselben
  // IDs, das Tutorial findet sie weiter. Nur per DOM verschoben werden die
  // drei Entdecken-Knöpfe und das Lebensqualität-Feld (oben rechts -> in die
  // Schublade) sowie die Quellenangabe (nach unten links, damit sie bei
  // geschlossener Schublade sichtbar bleibt). Bewusst kein setPosition():
  // das riefe onAdd() neu auf und verlöre die gebundenen Klick-Handler.
  const HANDY = window.matchMedia('(max-width: 760px)');
  const schublade = document.querySelector('.leaflet-bottom.leaflet-right');
  schublade.id = 'ebenen-schublade';
  const schubladeKopf = L.DomUtil.create('div', 'ebenen-kopf', schublade);
  schubladeKopf.innerHTML = '<span>Ebenen &amp; Kennzahlen</span><button type="button" id="ebenen-schliessen" aria-label="Schließen">✕</button>';
  // Tippen und Wischen auf dem Schubladen-Hintergrund darf die Karte nicht bewegen.
  L.DomEvent.disableClickPropagation(schublade);
  L.DomEvent.disableScrollPropagation(schublade);

  const ebenenBtn = document.getElementById('ebenen-btn');

  function schubladeZeigen(offen) {
    document.body.classList.toggle('ebenen-offen', offen);
    ebenenBtn.setAttribute('aria-expanded', offen ? 'true' : 'false');
  }

  // Desktop und Tablet: derselbe Knopf blendet die Felder unten rechts aus und
  // wieder ein (body.ebenen-zu, CSS in map.css). Anfangs sichtbar; wer sie
  // zuklappt, findet sie beim nächsten Besuch wieder zugeklappt (localStorage).
  // Eingeschaltete Ebenen bleiben dabei auf der Karte, nur die Felder gehen weg.
  const FELDER_ZU_KEY = 'hf_ebenen_zu';
  let felderZuGemerkt = false;
  try { felderZuGemerkt = localStorage.getItem(FELDER_ZU_KEY) === '1'; } catch (e) {}

  function felderZeigen(sichtbar) {
    document.body.classList.toggle('ebenen-zu', !sichtbar);
    ebenenBtn.textContent = sichtbar ? '☰ Ebenen ausblenden' : '☰ Ebenen einblenden';
    ebenenBtn.setAttribute('aria-expanded', sichtbar ? 'true' : 'false');
  }

  function handyLayout(aktiv) {
    const discover = document.getElementById('discover-group');
    const region = regionControl.getContainer();
    const quelle = map.attributionControl.getContainer();
    if (aktiv) {
      schublade.appendChild(discover);
      schublade.appendChild(region);
      document.querySelector('.leaflet-bottom.leaflet-left').appendChild(quelle);
      ebenenBtn.textContent = '☰ Ebenen';
      schubladeZeigen(false);
    } else {
      document.body.insertBefore(discover, document.getElementById('search-group'));
      document.querySelector('.leaflet-top.leaflet-right').appendChild(region);
      schublade.appendChild(quelle);
      schubladeZeigen(false);
      felderZeigen(!felderZuGemerkt);
    }
  }
  handyLayout(HANDY.matches);
  HANDY.addEventListener('change', e => handyLayout(e.matches));
  ebenenBtn.addEventListener('click', () => {
    if (HANDY.matches) { schubladeZeigen(true); return; }
    felderZuGemerkt = !felderZuGemerkt;
    try { localStorage.setItem(FELDER_ZU_KEY, felderZuGemerkt ? '1' : '0'); } catch (e) {}
    felderZeigen(!felderZuGemerkt);
  });
  document.getElementById('ebenen-schliessen').addEventListener('click', () => schubladeZeigen(false));
  map.on('click', () => { if (HANDY.matches) schubladeZeigen(false); });

  // Tastaturbedienung für die Vorschlagslisten beider Suchfelder (Issue #28, wie
  // im Bewertungsformular bewerten.js): Pfeil hoch/runter markiert, Enter wählt
  // den markierten Eintrag (löst dessen Klick aus), Escape schließt. Einträge
  // sind die div[data-i] in der Liste; Hinweiszeilen (.hint) zählen nicht mit.
  // Rückgabe: Funktion, die die Markierung beim Neuaufbau der Liste zurücksetzt.
  function tastaturFuerVorschlaege(input, liste) {
    let markiert = -1;
    const eintraege = () => liste.querySelectorAll('div[data-i]');
    function markieren(i) {
      const items = eintraege();
      markiert = items.length ? Math.max(-1, Math.min(i, items.length - 1)) : -1;
      items.forEach((el, k) => el.classList.toggle('active', k === markiert));
    }
    input.addEventListener('keydown', (e) => {
      if (liste.hidden) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        markieren(markiert + (e.key === 'ArrowDown' ? 1 : -1));
      } else if (e.key === 'Enter' && markiert >= 0) {
        e.preventDefault();
        eintraege()[markiert].click();
      } else if (e.key === 'Escape') {
        liste.hidden = true;
      }
    });
    return () => { markiert = -1; };
  }

  function hinweisZeile(liste, text) {
    const leer = document.createElement('div');
    leer.className = 'hint';
    leer.textContent = text;
    liste.appendChild(leer);
  }

  // Suche mit Autovervollständigung: bereits sichtbare, bewertete Abschnitte.
  // Über holeJson() (Issue #28): eine langsame alte Antwort kann eine neuere
  // nicht mehr überschreiben, Ausfälle zeigen einen Hinweis statt nichts.
  const searchInput = document.getElementById('search');
  const suggestions = document.getElementById('suggestions');
  const sucheZuruecksetzen = tastaturFuerVorschlaege(searchInput, suggestions);
  let searchTimeout = null;

  searchInput.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    const q = searchInput.value.trim();
    if (q.length < 2) {
      suggestions.hidden = true;
      return;
    }
    searchTimeout = setTimeout(async () => {
      const data = await holeJson('suche', '/api/search?q=' + encodeURIComponent(q));
      if (data === null) return;
      suggestions.innerHTML = '';
      sucheZuruecksetzen();
      if (data === false) {
        hinweisZeile(suggestions, 'Suche gerade nicht erreichbar.');
        suggestions.hidden = false;
        return;
      }
      if (!data.results || data.results.length === 0) {
        suggestions.hidden = true;
        return;
      }
      data.results.forEach((r, i) => {
        const item = document.createElement('div');
        item.dataset.i = i;
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

  // „Adresse finden" (Issue #14) — jede Straße und jeder Ort in Deutschland,
  // nur zum Hinspringen: kein Pin, keine Bewertungsdaten (sonst verriete das
  // Feld, ob eine noch unsichtbare Adresse schon bewertet wurde). Vorschläge
  // aus dem eigenen Straßenverzeichnis plus Gemeinden (Issue #15, orte=1) —
  // kein externer Dienst, daher ab 3 Zeichen und mit kurzer Pause.
  const addressSearch = document.getElementById('address-search');
  const addressSuggestions = document.getElementById('address-suggestions');
  const adresseZuruecksetzen = tastaturFuerVorschlaege(addressSearch, addressSuggestions);
  let addressTimeout = null;

  addressSearch.addEventListener('input', () => {
    clearTimeout(addressTimeout);
    const q = addressSearch.value.trim();
    if (q.length < 3) {
      addressSuggestions.hidden = true;
      return;
    }
    addressTimeout = setTimeout(async () => {
      const data = await holeJson('adresse', '/api/address-autocomplete?orte=1&q=' + encodeURIComponent(q));
      if (data === null) return;
      addressSuggestions.innerHTML = '';
      adresseZuruecksetzen();
      if (data === false) {
        hinweisZeile(addressSuggestions, 'Suche gerade nicht erreichbar.');
        addressSuggestions.hidden = false;
        return;
      }
      const treffer = (data.results || []).filter(r => r.lat !== undefined);
      if (treffer.length === 0) {
        hinweisZeile(addressSuggestions, data.hinweis ? 'Straßenverzeichnis nicht verfügbar.' : 'Keine Straße und kein Ort gefunden.');
      }
      treffer.forEach((r, i) => {
        const item = document.createElement('div');
        item.dataset.i = i;
        item.textContent = r.label;
        item.addEventListener('click', () => {
          if (r.bbox) {
            // Gemeinde: ganzen Ort zeigen, [minLon, minLat, maxLon, maxLat]
            map.fitBounds([[r.bbox[1], r.bbox[0]], [r.bbox[3], r.bbox[2]]], { maxZoom: 14 });
          } else {
            map.setView([r.lat, r.lon], 17);
          }
          addressSuggestions.hidden = true;
          addressSearch.value = r.label;
        });
        addressSuggestions.appendChild(item);
      });
      addressSuggestions.hidden = false;
    }, 200);
  });

  document.addEventListener('click', (e) => {
    if (e.target !== addressSearch) addressSuggestions.hidden = true;
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
    return 'Elf Kategorien aus OpenStreetMap und die Ladesäulen der Bundesnetzagentur, jede als eigenfarbiges Dreieck auf der Karte:' +
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
    { selector: '#search-group', title: 'Straßen finden', text: 'Oben findest du bereits bewertete Straßen. Darunter kannst du jede Adresse in Deutschland suchen — die Karte springt dann einfach dorthin.' },
    { selector: '#rate-cta', title: 'Jetzt beitragen', text: 'Wohnst du hier? Über diesen Button gibst du deine eigene Bewertung zu deiner Straße ab, fünf Kriterien, etwa zwei Minuten.' },
    { selector: '.streetview-control', title: 'Straßenansicht', text: 'Setzt ein Männchen auf die Karte. Zieh es an die gewünschte Stelle, dann öffnet ein Link Google Street View dort (sofern Google Bilder hat). Rechtsklick oder langes Drücken auf die Karte geht auch.' },
    { selector: '.ratings-control', title: 'Bewertungen ein-/ausblenden', text: 'Blendet die farbigen Bewertungs-Punkte aus — praktisch, wenn du nur die Statistik-Ebenen sehen willst. Was Grün, Orange und Rotbraun bedeuten, erklärt die kleine Legende oben links unter dem Zoom-Regler.' },
    { selector: '.census-control', title: 'Amtliche Statistik', html: censusTourHtml },
    { selector: '.poi-control', title: 'Freizeit & Infrastruktur', html: poiTourHtml },
    { selector: '.climate-control', title: 'Klima & Luft', text: 'Temperatur, Niederschlag und Luftqualität für die Kartenmitte — dazu heiße Tage, Frost, Schnee, Starkregen und Sonnenstunden genau an diesem Ort, heute und vor 30 Jahren.' },
    { selector: '.broadband-control', title: 'Breitband', text: 'Verfügbarkeit von schnellem Festnetz-Internet (≥ 50 Mbit/s).' },
    {
      selector: '.economy-control', title: 'Wirtschaft',
      text: 'Arbeitslosenquote, Kriminalität, Einkommen, Wanderungssaldo u.v.m. für den Kreis der Kartenmitte — hier am Beispiel Frankfurt am Main. Die kleinen (i)-Symbole neben den Zeilen erklären, warum die jeweilige Zahl für die Wohnlage relevant ist.',
      onEnter: economyTourEnter
    },
    { selector: '.region-control', title: 'Lebensqualität', text: 'Amtliche Kennzahlen für die Gemeinde der Kartenmitte: wie weit es zu Fuß zu Hausarzt, Apotheke, Grundschule, Supermarkt und Haltestelle ist, Fahrzeiten, Pendeln, Grün, Unfälle — dazu Ärzte, Kitas und Mieten im Kreis. Jeweils mit dem Wert für ganz Deutschland. Darunter lassen sich Unfallorte, der ÖPNV-Takt (Abfahrten je Haltestelle), Lärm und Hochwasserflächen auf der Karte einblenden.' },
    { selector: '#vision-link', title: 'Vision', text: 'Ein Blick auf eine Beispielkarte mit vielen (frei erfundenen) Bewertungen — so könnte es aussehen, wenn viele Menschen mitmachen.' },
    { selector: '#examples-btn', title: 'Praxisbeispiele', text: 'Fünf fiktive Personen — Rentnerin, Student, Pendlerin, Kapitalanleger, Familie — und je drei Rückschlüsse, wie sie die Kennzahlen auf dieser Karte für ihre eigene Entscheidung nutzen könnten.' },
    { selector: '#legal', title: 'Rechtliches', text: 'Impressum, Datenschutz, Quellen & Lizenzen und die Projektseite: Habitat Feedback ist unkommerziell, quelloffen und privat betrieben.' }
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

    const tw = Math.min(320, window.innerWidth - 20); // Handy: schmaler als der Bildschirm (Issue #27)
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
    // Handy (Issue #27): Ziele in der Schublade brauchen die offene Schublade,
    // für alle anderen muss sie zu sein, sonst verdeckt sie die Karte.
    if (HANDY.matches) {
      const ziel = document.querySelector(step.selector);
      const inSchublade = schublade.contains(ziel);
      schubladeZeigen(inSchublade);
      // Kein scrollIntoView(): das scrollt auch den #map-Container (overflow:
      // hidden) mit, Leaflet setzt ihn gleich zurück und der Rahmen säße falsch.
      if (inSchublade) {
        const abstand = ziel.getBoundingClientRect().top - schublade.getBoundingClientRect().top;
        schublade.scrollTop += abstand - (schublade.clientHeight - ziel.offsetHeight) / 2;
      }
    }
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
    // Desktop: zugeklappte Felder für die Tour zeigen, tourEnd() stellt den
    // gemerkten Zustand wieder her.
    if (!HANDY.matches) felderZeigen(true);
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
    if (HANDY.matches) schubladeZeigen(false); else felderZeigen(!felderZuGemerkt);
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
