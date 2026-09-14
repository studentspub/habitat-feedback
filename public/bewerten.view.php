<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Bewertung abgeben — Habitat Feedback</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { font-family: sans-serif; max-width: 34em; margin: 2em auto; padding: 0 1em; color: #1B2420; background: #F2F4EF; }
  h1 { font-size: 1.4rem; }
  label { display: block; margin-top: 1.2em; font-weight: 600; }
  .hint { font-weight: 400; font-size: 0.85em; color: #4B564F; margin-top: 2px; }
  input[type=text] { width: 100%; padding: 0.5em; font-size: 1em; box-sizing: border-box; }
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
    <div class="hint">z.B. „Musterstraße 12, 04109 Leipzig" — die genaue Hausnummer wird nach der Zuordnung nicht gespeichert.</div>
  </label>
  <input type="text" id="address" name="address" required placeholder="Straße Hausnummer, PLZ Ort">

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

<script>
  const criteria = ['ruhe', 'instandhaltung', 'umgangston', 'infrastruktur', 'sicherheit'];
  const scores = {};

  document.querySelectorAll('.scale').forEach(el => {
    const key = el.dataset.key;
    for (let i = 1; i <= 5; i++) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = i;
      btn.addEventListener('click', () => {
        scores[key] = i;
        el.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
      });
      el.appendChild(btn);
    }
  });

  document.getElementById('rating-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const resultEl = document.getElementById('result');
    const submitBtn = document.getElementById('submit');

    if (criteria.some(k => !scores[k])) {
      resultEl.hidden = false;
      resultEl.className = 'error';
      resultEl.textContent = 'Bitte alle fünf Kriterien bewerten.';
      return;
    }

    submitBtn.disabled = true;
    try {
      const res = await fetch('/api/ratings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ address: document.getElementById('address').value, scores })
      });
      const data = await res.json();
      resultEl.hidden = false;
      if (res.ok) {
        resultEl.className = 'ok';
        resultEl.textContent = data.hinweis || 'Danke, deine Bewertung wurde übermittelt.';
        document.getElementById('rating-form').reset();
        document.querySelectorAll('.scale button.active').forEach(b => b.classList.remove('active'));
        Object.keys(scores).forEach(k => delete scores[k]);
      } else {
        resultEl.className = 'error';
        resultEl.textContent = data.error || 'Etwas ist schiefgelaufen.';
      }
    } catch (err) {
      resultEl.hidden = false;
      resultEl.className = 'error';
      resultEl.textContent = 'Verbindung fehlgeschlagen. Bitte später erneut versuchen.';
    } finally {
      submitBtn.disabled = false;
    }
  });
</script>
</body>
</html>
