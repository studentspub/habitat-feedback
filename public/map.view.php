<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Habitat Feedback</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/vendor/leaflet-1.9.4/leaflet.min.css">
<link rel="stylesheet" href="/css/map.css?v=<?= filemtime(__DIR__ . '/css/map.css') ?>">
</head>
<body>
<div id="discover-group">
  <button type="button" id="tutorial-btn">🧭 Tutorial starten</button>
  <a id="vision-link" href="/vision">🔮 Vision entdecken</a>
  <button type="button" id="examples-btn">💡 Praxisbeispiele</button>
</div>
<div id="search-group">
  <div class="search-field">
    <span id="search-label" class="search-label">Bereits bewertete Straßen</span>
    <input type="text" id="search" class="search-input" placeholder="z.B. Musterstraße…" autocomplete="off">
    <div id="suggestions" class="search-suggestions" hidden></div>
  </div>
  <div class="search-field">
    <span class="search-label">Adresse finden</span>
    <input type="text" id="address-search" class="search-input" placeholder="Straße oder Ort …" autocomplete="off">
    <div id="address-suggestions" class="search-suggestions" hidden></div>
  </div>
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
<script src="/vendor/leaflet-1.9.4/leaflet.min.js"></script>
<script src="/js/de_maske.js?v=<?= filemtime(__DIR__ . '/js/de_maske.js') ?>"></script>
<script src="/js/map.js?v=<?= filemtime(__DIR__ . '/js/map.js') ?>"></script>
</body>
</html>
