<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Vision – Habitat Feedback</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<!-- Grenzdatei fuer den Nebel (de_maske.js) schon beim Laden des Kopfes anfordern statt erst aus dem Skript (Issue #31). -->
<link rel="preload" href="/data/de_grenze.geojson" as="fetch" crossorigin>
<link rel="stylesheet" href="/vendor/leaflet-1.9.4/leaflet.min.css">
<style>
  html, body { margin: 0; height: 100%; font-family: sans-serif; }
  #map { height: 100%; }
  .wl-popup h3 { margin: 0 0 4px; font-size: 1rem; }
  .wl-popup .score { font-weight: 600; }
  .wl-popup .fake-tag {
    display: inline-block; margin-top: 6px; padding: 2px 6px; background: #D9971E; color: #1B2420;
    border-radius: 4px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;
  }
  /* Aufschlüsselung nach den fünf echten Bewertungskriterien (siehe
     bewerten.view.php) statt nur des Gesamtwerts — sonst suggeriert die
     Vision-Seite eine Detailtiefe, die das echte Bewertungsformular gar
     nicht abbildet. */
  .wl-popup .breakdown { margin-top: 6px; padding-top: 6px; border-top: 1px solid #EBEEE5; }
  .wl-popup .breakdown .row { display: flex; justify-content: space-between; gap: 8px; font-size: 0.78rem; margin-top: 3px; }
  .wl-popup .breakdown .k { color: #4B564F; }
  .wl-popup .breakdown .v { font-weight: 600; }

  /* Legende + Sichtbarkeits-Schalter für die Bewertungspunkte, oben links
     unter dem Zoom-Regler — als topleft-Leaflet-Control stapelt Leaflet das
     automatisch darunter. */
  .ratings-legend {
    background: rgba(255,255,255,0.92); padding: 8px 10px; border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.15); font-size: 0.72rem; color: #1B2420;
  }
  .ratings-legend .legend-title { font-weight: 700; margin-bottom: 4px; }
  .ratings-legend .row { display: flex; align-items: center; gap: 6px; margin-top: 2px; }
  .ratings-legend .dot { width: 10px; height: 10px; border-radius: 50%; flex: none; border: 1px solid rgba(27,36,32,0.4); }
  .ratings-legend #ratings-toggle {
    display: block; width: 100%; box-sizing: border-box; margin-top: 8px; padding: 0.4em 0.6em;
    background: #1B2420; color: white; border: none; border-radius: 6px;
    font-size: 0.74rem; font-weight: 600; cursor: pointer;
  }
  .ratings-legend #ratings-toggle.active { background: #31576D; }

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
  /* Hinweis im Nebel außerhalb Deutschlands (Issue #12, public/js/de_maske.js). */
  .maske-hinweis { background: none; border: none; color: #31576D; font-size: 0.72rem; font-weight: 700;
    text-align: center; line-height: 1.3; text-shadow: 0 0 3px #EBEEE5, 0 0 6px #EBEEE5; }
</style>
</head>
<body>
<div id="vision-banner">
  <span>🔮 <strong>Vision:</strong> So könnte Habitat Feedback aussehen, wenn viele mitmachen — alle Bewertungen hier sind <strong>frei erfunden</strong>.</span>
  <a id="vision-back" href="/">Zur echten Karte</a>
</div>
<div id="map"></div>
<script src="/vendor/leaflet-1.9.4/leaflet.min.js"></script>
<script src="/js/de_maske.js?v=<?= filemtime(__DIR__ . '/js/de_maske.js') ?>"></script>
<script src="/js/vision.js?v=<?= filemtime(__DIR__ . '/js/vision.js') ?>"></script>
</body>
</html>
