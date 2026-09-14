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
    position: absolute; bottom: 8px; left: 8px; z-index: 1000;
    background: rgba(255,255,255,0.85); padding: 4px 10px; border-radius: 6px;
    font-size: 0.78rem;
  }
  #legal a { color: #31576D; margin-right: 10px; text-decoration: none; }
  #legal a:hover { text-decoration: underline; }

  /* Amtliche Statistik-Ebene, wie eine Wetterkarten-Umschaltung — eigenständig
     von den Bewertungs-Pins, per echtem Button an/aus, mit Legende, die sich
     der aktuell aktiven Rasterauflösung anpasst. */
  .census-control {
    background: rgba(255,255,255,0.92); padding: 10px; border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.15); font-size: 0.82rem; width: 210px;
  }
  #census-btn {
    display: block; width: 100%; box-sizing: border-box; padding: 0.55em 0.8em;
    background: #1B2420; color: white; border: none; border-radius: 6px;
    font-size: 0.88rem; font-weight: 600; cursor: pointer; text-align: center;
  }
  #census-btn.active { background: #31576D; }
  .census-control .hint { font-size: 0.72rem; color: #4B564F; margin-top: 8px; line-height: 1.4; }
  .census-legend { display: none; margin-top: 8px; }
  .census-legend.visible { display: block; }
  .census-legend .row { display: flex; align-items: center; gap: 6px; font-size: 0.72rem; margin-top: 2px; }
  .census-legend .swatch { width: 14px; height: 14px; border-radius: 3px; flex: none; }
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

  map.on('moveend', loadPins);
  loadPins();

  // Amtliche Statistik-Ebene: Zensus 2022, Einwohnerzahl. Direkter Abruf
  // beim ArcGIS-Dienst des Statistischen Bundesamts — öffentlicher,
  // unauthentifizierter Geodatendienst ohne Nominatim-artige
  // Ratenlimit-Problematik, daher (anders als die Adresssuche) ohne
  // eigenen Server-Proxy.
  //
  // Der Dienst liefert bereits fertige amtliche Aggregate auf drei
  // Rasterstufen (100m/1km/10km) im selben Layer-Schema — eigenes
  // Aufsummieren der 100m-Zellen im Browser wäre nicht nur unnötig,
  // sondern bei größeren Kartenausschnitten (z.B. "ganz Hessen") auch gar
  // nicht möglich: der Dienst liefert pro Abfrage maximal 2000 Zellen, und
  // Hessen allein hätte ca. 44.500 100m-Zellen. Stattdessen wird die
  // Rasterstufe anhand der tatsächlichen Fläche des Kartenausschnitts
  // gewählt — automatisch gröber beim Rauszoomen, automatisch feiner beim
  // Reinzoomen, wie bei einer Wetterkarte.
  const CENSUS_TIERS = [
    {
      layer: 0, maxAreaKm2: 20, label: '100m-Raster (Straßenblock)',
      breaks: [
        { max: 9, color: '#EFF3FF', label: '0–9' },
        { max: 49, color: '#BDD7E7', label: '10–49' },
        { max: 99, color: '#6BAED6', label: '50–99' },
        { max: 199, color: '#2171B5', label: '100–199' },
        { max: Infinity, color: '#08306B', label: '200+' }
      ]
    },
    {
      layer: 1, maxAreaKm2: 1500, label: '1km-Raster (Stadt/Region)',
      breaks: [
        { max: 499, color: '#EFF3FF', label: '0–499' },
        { max: 1999, color: '#BDD7E7', label: '500–1.999' },
        { max: 4999, color: '#6BAED6', label: '2.000–4.999' },
        { max: 9999, color: '#2171B5', label: '5.000–9.999' },
        { max: Infinity, color: '#08306B', label: '10.000+' }
      ]
    },
    {
      layer: 2, maxAreaKm2: 150000, label: '10km-Raster (Bundesland-Maßstab)',
      breaks: [
        { max: 4999, color: '#EFF3FF', label: '0–4.999' },
        { max: 24999, color: '#BDD7E7', label: '5.000–24.999' },
        { max: 74999, color: '#6BAED6', label: '25.000–74.999' },
        { max: 199999, color: '#2171B5', label: '75.000–199.999' },
        { max: Infinity, color: '#08306B', label: '200.000+' }
      ]
    }
  ];
  const CENSUS_URL_BASE = 'https://services2.arcgis.com/jUpNdisbWqRpMo35/arcgis/rest/services/Zensus2022_grid_final/FeatureServer/';

  function viewportAreaKm2() {
    const b = map.getBounds();
    const midLatRad = (b.getNorth() + b.getSouth()) / 2 * Math.PI / 180;
    const heightKm = (b.getNorth() - b.getSouth()) * 111;
    const widthKm = (b.getEast() - b.getWest()) * 111 * Math.cos(midLatRad);
    return Math.max(heightKm, 0) * Math.max(widthKm, 0);
  }

  function pickCensusTier() {
    const area = viewportAreaKm2();
    return CENSUS_TIERS.find(t => area <= t.maxAreaKm2) || null;
  }

  let censusLayer = null;
  let censusEnabled = false;
  let censusRequestId = 0; // verhindert, dass eine späte Antwort eine bereits
                            // ausgeschaltete/überholte Anzeige wieder aufleben lässt

  function renderCensusLegend(tier) {
    const hint = document.getElementById('census-hint');
    const legend = document.getElementById('census-legend');
    if (!tier) {
      hint.textContent = 'Weiter reinzoomen, um Einwohnerzahlen zu sehen (z.B. auf Bundesland-Ebene beginnen).';
      legend.classList.remove('visible');
      return;
    }
    hint.textContent = 'Amtliche Zensus-2022-Daten, ' + tier.label + '. Weiter reinzoomen für feinere Auflösung.';
    legend.innerHTML = tier.breaks.map(b =>
      '<div class="row"><span class="swatch" style="background:' + b.color + '"></span>' + b.label + ' Einw.</div>'
    ).join('');
    legend.classList.add('visible');
  }

  async function loadCensusLayer() {
    const myRequestId = ++censusRequestId;

    if (censusLayer) { map.removeLayer(censusLayer); censusLayer = null; }
    if (!censusEnabled) return;

    const tier = pickCensusTier();
    renderCensusLegend(tier);
    if (!tier) return;

    const b = map.getBounds();
    const bbox = [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()].join(',');
    const url = CENSUS_URL_BASE + tier.layer + '/query?' + new URLSearchParams({
      geometry: bbox, geometryType: 'esriGeometryEnvelope', inSR: '4326', outSR: '4326',
      spatialRel: 'esriSpatialRelIntersects', outFields: 'Einwohner', returnGeometry: 'true', f: 'geojson'
    });
    const res = await fetch(url);
    const geojson = await res.json();

    // Zwischenzeitlich wieder ausgeschaltet oder von einer neueren Anfrage
    // überholt? Dann dieses (jetzt veraltete) Ergebnis verwerfen.
    if (myRequestId !== censusRequestId || !censusEnabled) return;

    censusLayer = L.geoJSON(geojson, {
      style: f => ({
        fillColor: (tier.breaks.find(br => (f.properties.Einwohner || 0) <= br.max) || tier.breaks[tier.breaks.length - 1]).color,
        fillOpacity: 0.55, color: 'rgba(27,36,32,0.2)', weight: 1
      })
    }).addTo(map);
    censusLayer.bringToBack();
  }

  const CensusControl = L.Control.extend({
    options: { position: 'bottomright' },
    onAdd: function () {
      const el = L.DomUtil.create('div', 'census-control');
      el.innerHTML =
        '<button type="button" id="census-btn">Einwohnerzahlen anzeigen</button>' +
        '<div class="hint" id="census-hint"></div>' +
        '<div class="census-legend" id="census-legend"></div>';
      L.DomEvent.disableClickPropagation(el);
      return el;
    }
  });
  map.addControl(new CensusControl());

  document.getElementById('census-btn').addEventListener('click', (e) => {
    censusEnabled = !censusEnabled;
    e.target.textContent = censusEnabled ? 'Einwohnerzahlen ausblenden' : 'Einwohnerzahlen anzeigen';
    e.target.classList.toggle('active', censusEnabled);
    if (!censusEnabled) {
      document.getElementById('census-hint').textContent = '';
      document.getElementById('census-legend').classList.remove('visible');
    }
    loadCensusLayer();
  });
  map.on('moveend zoomend', loadCensusLayer);

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
