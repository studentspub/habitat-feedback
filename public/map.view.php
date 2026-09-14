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

  #toolbar {
    position: absolute; top: 10px; left: 50px; right: 10px; z-index: 1000;
    display: flex; gap: 8px; align-items: flex-start; max-width: 460px;
  }
  #search-group { flex: 1; position: relative; }
  #search-label {
    display: inline-block; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;
    color: #1B2420; background: rgba(255,255,255,0.9);
    padding: 2px 8px; border-radius: 4px 4px 0 0; margin-bottom: -1px;
  }
  #search {
    display: block; width: 100%; box-sizing: border-box; padding: 0.6em 0.8em;
    border: 1px solid #CFD8C9; border-radius: 6px; font-size: 0.95rem; box-shadow: 0 1px 4px rgba(0,0,0,0.15);
  }
  #rate-link {
    padding: 0.6em 1em; background: #1B2420; color: white; text-decoration: none;
    border-radius: 6px; font-size: 0.95rem; white-space: nowrap; box-shadow: 0 1px 4px rgba(0,0,0,0.15);
    margin-top: 22px;
  }
  #suggestions {
    position: absolute; top: 100%; left: 0; right: 0; background: white; border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2); overflow: hidden; z-index: 1001; margin-top: 2px;
  }
  #suggestions div { padding: 0.6em 0.8em; cursor: pointer; font-size: 0.9rem; }
  #suggestions div:hover { background: #EBEEE5; }

  #legal {
    position: absolute; bottom: 8px; left: 8px; z-index: 1000;
    background: rgba(255,255,255,0.85); padding: 4px 10px; border-radius: 6px;
    font-size: 0.78rem;
  }
  #legal a { color: #31576D; margin-right: 10px; text-decoration: none; }
  #legal a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div id="toolbar">
  <div id="search-group">
    <span id="search-label">Bereits bewertete Straßen</span>
    <input type="text" id="search" placeholder="z.B. Musterstraße…" autocomplete="off">
    <div id="suggestions" hidden></div>
  </div>
  <a id="rate-link" href="/bewerten">+ Bewerten</a>
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
