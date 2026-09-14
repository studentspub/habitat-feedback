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
</style>
</head>
<body>
<div id="map"></div>
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
        '<p>' + seg.rating_count + ' Bewertungen &middot; ' + seg.address_count + ' Adressen im Abschnitt</p></div>'
      );
    });
  }

  map.on('moveend', loadPins);
  loadPins();
</script>
</body>
</html>
