// Deutschland-Maske (Issue #12, Ideas.md „Visuelle Eingrenzung", Variante C):
// Ein Welt-Rechteck mit allen Landflächen Deutschlands als Löchern legt
// einen hellen Nebel über das Ausland; die Nachbarländer schimmern durch,
// Deutschland bleibt klar. Dazu Bewegungsgrenze (maxBounds) und Mindest-Zoom,
// damit man die Karte nicht nach Spanien schieben kann.
//
// Grenze: public/data/de_grenze.geojson, erzeugt von bin/build_de_grenze.php
// aus BKG VG250 (© GeoBasis-DE / BKG 2023, dl-de/by-2.0), auf 40 m
// vereinfacht. Weil 40 m bei Zoom 16 an der Grenze sichtbar wären, wird der
// Nebel ab Zoom 12 deutlich durchsichtiger.
//
// Einbinden: nach Leaflet laden, dann habitatMaske(map) aufrufen.
(function () {
  const DE_BOUNDS = L.latLngBounds([47.27, 5.87], [55.06, 15.04]);
  const BEWEGUNG = DE_BOUNDS.pad(0.25);
  const NEBEL_FARBE = '#EBEEE5';
  const DECKKRAFT_WEIT = 0.75;
  const DECKKRAFT_NAH = 0.35;
  const ZOOM_NAH = 12;
  // Hinweis im Nebel, je Nachbarland einmal (Punkte sicher im Ausland,
  // innerhalb der Bewegungsgrenze).
  const HINWEIS_ORTE = [[52.3, 5.0], [48.4, 6.0], [52.6, 16.1], [49.8, 14.3], [47.2, 12.9], [55.7, 9.4]];

  window.habitatMaske = function (map) {
    map.setMinZoom(5);
    map.setMaxBounds(BEWEGUNG);
    map.options.maxBoundsViscosity = 1.0;

    // Eigene Ebene zwischen Kacheln (200) und Daten-Ebenen (400): Zensus,
    // Bewertungen und POIs liegen über dem Nebel, Breitband-Kacheln darunter.
    const pane = map.createPane('maskePane');
    pane.style.zIndex = 350;
    pane.style.pointerEvents = 'none';

    const hinweise = L.layerGroup(HINWEIS_ORTE.map(ll => L.marker(ll, {
      pane: 'maskePane', interactive: false, keyboard: false,
      icon: L.divIcon({ className: 'maske-hinweis', html: 'Habitat Feedback deckt nur Deutschland ab', iconSize: [170, 34], iconAnchor: [85, 17] })
    })));

    fetch('/data/de_grenze.geojson')
      .then(res => res.json())
      .then(gj => {
        const ringe = gj.features[0].geometry.coordinates.map(poly => poly[0].map(([lon, lat]) => [lat, lon]));
        const welt = [[-89, -179], [-89, 179], [89, 179], [89, -179]];
        const maske = L.polygon([welt, ...ringe], {
          pane: 'maskePane', interactive: false, fillRule: 'evenodd',
          stroke: true, color: '#31576D', weight: 1.5, opacity: 0.6,
          fillColor: NEBEL_FARBE, fillOpacity: DECKKRAFT_WEIT
        }).addTo(map);
        map.attributionControl.addAttribution('Grenze: &copy; GeoBasis-DE / <a href="https://www.bkg.bund.de" target="_blank" rel="noopener">BKG</a> 2023 (dl-de/by-2.0)');

        const anpassen = () => {
          const nah = map.getZoom() >= ZOOM_NAH;
          maske.setStyle({ fillOpacity: nah ? DECKKRAFT_NAH : DECKKRAFT_WEIT });
          if (nah) hinweise.remove(); else hinweise.addTo(map);
        };
        map.on('zoomend', anpassen);
        anpassen();
      })
      .catch(() => { /* ohne Grenzdatei bleibt die Karte einfach unmaskiert */ });
  };
})();
