<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Vision – Habitat Feedback</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
  html, body { margin: 0; height: 100%; font-family: sans-serif; }
  #map { height: 100%; }
  .wl-popup h3 { margin: 0 0 4px; font-size: 1rem; }
  .wl-popup .score { font-weight: 600; }
  .wl-popup .fake-tag {
    display: inline-block; margin-top: 6px; padding: 2px 6px; background: #D9971E; color: #1B2420;
    border-radius: 4px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;
  }

  /* Banner bleibt dauerhaft sichtbar (nicht wegklickbar) — anders als das
     Willkommens-Overlay auf der echten Karte muss hier jederzeit klar sein,
     dass die gesamte Seite mit erfundenen Beispieldaten arbeitet. */
  #vision-banner {
    position: absolute; top: 10px; left: 50%; transform: translateX(-50%); z-index: 1000;
    background: #1B2420; color: white; border-radius: 10px; padding: 10px 18px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.25); font-size: 0.85rem; max-width: 92vw;
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap; justify-content: center; text-align: center;
  }
  #vision-banner strong { color: #D9971E; }
  #vision-back {
    padding: 0.5em 1.1em; background: #D9971E; color: #1B2420; text-decoration: none;
    border-radius: 6px; font-size: 0.82rem; font-weight: 700; white-space: nowrap;
  }
</style>
</head>
<body>
<div id="vision-banner">
  <span>🔮 <strong>Vision:</strong> So könnte Habitat Feedback aussehen, wenn viele mitmachen — alle Bewertungen hier sind <strong>frei erfunden</strong>.</span>
  <a id="vision-back" href="/">Zur echten Karte</a>
</div>
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

  // Deterministischer Pseudozufall (mulberry32) statt Math.random(): die
  // Beispieldaten sollen bei jedem Aufruf gleich aussehen, statt bei jedem
  // Neuladen zu "flackern" — sonst wirkt die Vision-Seite eher wie ein Fehler
  // als wie ein stabiler Ausblick.
  function mulberry32(seed) {
    return function () {
      seed |= 0; seed = (seed + 0x6D2B79F5) | 0;
      let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }
  const rand = mulberry32(42);
  function pick(arr) { return arr[Math.floor(rand() * arr.length)]; }

  // Grobe Koordinaten größerer deutscher Städte, rein zur Streuung der
  // Beispiel-Marker über die Landkarte — keine echten Adress- oder
  // Bewertungsdaten.
  const CITIES = [
    ['Berlin', 52.5200, 13.4050], ['Hamburg', 53.5511, 9.9937], ['München', 48.1351, 11.5820],
    ['Köln', 50.9375, 6.9603], ['Frankfurt am Main', 50.1109, 8.6821], ['Stuttgart', 48.7758, 9.1829],
    ['Düsseldorf', 51.2277, 6.7735], ['Leipzig', 51.3397, 12.3731], ['Dortmund', 51.5136, 7.4653],
    ['Essen', 51.4556, 7.0116], ['Bremen', 53.0793, 8.8017], ['Dresden', 51.0504, 13.7373],
    ['Hannover', 52.3759, 9.7320], ['Nürnberg', 49.4521, 11.0767], ['Duisburg', 51.4344, 6.7623],
    ['Bochum', 51.4818, 7.2162], ['Wuppertal', 51.2562, 7.1508], ['Bielefeld', 52.0302, 8.5325],
    ['Bonn', 50.7374, 7.0982], ['Münster', 51.9607, 7.6261], ['Karlsruhe', 49.0069, 8.4037],
    ['Mannheim', 49.4875, 8.4660], ['Augsburg', 48.3705, 10.8978], ['Wiesbaden', 50.0782, 8.2398],
    ['Mönchengladbach', 51.1805, 6.4428], ['Braunschweig', 52.2689, 10.5268], ['Kiel', 54.3233, 10.1228],
    ['Chemnitz', 50.8278, 12.9214], ['Halle (Saale)', 51.4964, 11.9683], ['Magdeburg', 52.1205, 11.6276],
    ['Freiburg im Breisgau', 47.9990, 7.8421], ['Lübeck', 53.8655, 10.6866], ['Rostock', 54.0887, 12.1400],
    ['Potsdam', 52.3906, 13.0645], ['Erfurt', 50.9848, 11.0299], ['Saarbrücken', 49.2401, 6.9969]
  ];
  const STREET_STEMS = [
    'Hauptstraße', 'Gartenweg', 'Lindenallee', 'Bahnhofstraße', 'Schulstraße', 'Ahornweg',
    'Bergstraße', 'Wiesenweg', 'Kirchgasse', 'Talstraße', 'Birkenweg', 'Feldweg', 'Rosenweg',
    'Ringstraße', 'Mühlenweg', 'Am Stadtpark', 'Kastanienallee', 'Uferstraße'
  ];

  CITIES.forEach(([city, lat, lon]) => {
    const segments = 3 + Math.floor(rand() * 5); // 3–7 Beispiel-Abschnitte je Stadt
    for (let i = 0; i < segments; i++) {
      const segLat = lat + (rand() - 0.5) * 0.06;
      const segLon = lon + (rand() - 0.5) * 0.09;
      const score = Math.round((2 + rand() * 3) * 10) / 10; // 2.0–5.0
      const street = pick(STREET_STEMS);
      const ratingCount = 3 + Math.floor(rand() * 40);

      L.circleMarker([segLat, segLon], {
        radius: 9, color: '#1B2420', weight: 1,
        fillColor: colorForScore(score), fillOpacity: 0.85
      }).addTo(map).bindPopup(
        '<div class="wl-popup"><h3>' + street + ' (Abschnitt ' + (i + 1) + ')</h3>' +
        '<p>' + city + '</p>' +
        '<p class="score">Gesamt: ' + score.toFixed(1) + ' / 5</p>' +
        '<p>' + ratingCount + ' Bewertungen</p>' +
        '<span class="fake-tag">Beispieldaten</span></div>'
      );
    }
  });
</script>
</body>
</html>
