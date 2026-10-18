<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Bewertung abgeben — Habitat Feedback</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<style>
  body { font-family: sans-serif; max-width: 34em; margin: 2em auto; padding: 0 1em; color: #1B2420; background: #F2F4EF; }
  h1 { font-size: 1.4rem; }
  label { display: block; margin-top: 1.2em; font-weight: 600; }
  .hint { font-weight: 400; font-size: 0.85em; color: #4B564F; margin-top: 2px; }
  input[type=text] { width: 100%; padding: 0.5em; font-size: 1em; box-sizing: border-box; }
  .address-wrap { position: relative; }
  #address-suggestions {
    position: absolute; top: 100%; left: 0; right: 0; background: white; border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2); overflow: hidden; z-index: 10; margin-top: 2px;
  }
  #address-suggestions div { padding: 0.6em 0.8em; cursor: pointer; font-size: 0.9rem; }
  #address-suggestions div:hover, #address-suggestions div.active { background: #EBEEE5; }
  #address-suggestions div.hint { cursor: default; color: #4B564F; }
  .address-row { display: flex; gap: 8px; align-items: flex-start; }
  .address-row .address-wrap { flex: 1; }
  #hausnummer { width: 6.5em; }
  .scale { display: flex; gap: 8px; margin-top: 6px; }
  .scale button {
    flex: 1; padding: 0.6em 0; border: 1px solid #CFD8C9; background: #FBFBF7;
    border-radius: 6px; cursor: pointer; font-size: 1rem;
  }
  .scale button.active { background: #D9971E; border-color: #D9971E; color: #3A2705; font-weight: 600; }
  #submit { margin-top: 2em; padding: 0.8em 1.5em; font-size: 1rem; background: #1B2420; color: white; border: none; border-radius: 6px; cursor: pointer; }
  #submit:disabled { opacity: 0.5; cursor: not-allowed; }
  #result { margin-top: 1.5em; padding: 1em; border-radius: 6px; }
  #result.ok { background: #DEEDE1; }
  #result.error { background: #F3DDD1; }
  a.back { display: inline-block; margin-bottom: 1em; }
  footer { margin-top: 3em; font-size: 0.8em; }
  footer a { color: #31576D; margin-right: 12px; }
</style>
</head>
<body>
<a class="back" href="/">&larr; zurück zur Karte</a>
<h1>Bewertung abgeben</h1>
<p>Keine Kommentare, nur fünf sachliche Kriterien — deine Bewertung fließt in den Durchschnitt für den Straßenabschnitt ein, nicht für eine einzelne Adresse.</p>

<form id="rating-form">
  <label for="address">Adresse
    <div class="hint">Straße und Ort tippen und einen Vorschlag auswählen — vermeidet Tippfehler. Die Hausnummer braucht es nur, um den Straßenabschnitt zu finden; sie wird danach nicht gespeichert.</div>
  </label>
  <div class="address-row">
    <div class="address-wrap">
      <input type="text" id="address" name="address" required placeholder="Straße, Ort" autocomplete="off" aria-label="Straße und Ort">
      <div id="address-suggestions" hidden></div>
    </div>
    <input type="text" id="hausnummer" name="hausnummer" required placeholder="Nr." autocomplete="off" aria-label="Hausnummer" maxlength="12">
  </div>

  <div class="criteria">
    <label>Ruhe <div class="hint">1 = sehr laut, 5 = sehr ruhig</div></label>
    <div class="scale" data-key="ruhe"></div>

    <label>Instandhaltung <div class="hint">1 = vernachlässigt, 5 = gepflegt</div></label>
    <div class="scale" data-key="instandhaltung"></div>

    <label>Umgangston im Viertel <div class="hint">1 = angespannt, 5 = herzlich</div></label>
    <div class="scale" data-key="umgangston"></div>

    <label>Infrastruktur <div class="hint">1 = schlecht, 5 = sehr gut erreichbar</div></label>
    <div class="scale" data-key="infrastruktur"></div>

    <label>Sicherheitsgefühl <div class="hint">1 = unsicher, 5 = sicher</div></label>
    <div class="scale" data-key="sicherheit"></div>
  </div>

  <button type="submit" id="submit">Bewertung absenden</button>
</form>
<div id="result" hidden></div>

<footer><a href="/ueber">Über Habitat Feedback</a><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a></footer>

<script src="/js/bewerten.js?v=<?= filemtime(__DIR__ . '/js/bewerten.js') ?>"></script>
</body>
</html>
