<?php
$segmentId = (int) ($_GET['segment'] ?? 0);
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Bewertungen melden — Habitat Feedback</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { font-family: sans-serif; max-width: 34em; margin: 2em auto; padding: 0 1em; color: #1B2420; background: #F2F4EF; }
  h1 { font-size: 1.4rem; }
  textarea { width: 100%; min-height: 8em; padding: 0.6em; font-size: 1em; box-sizing: border-box; font-family: inherit; }
  #submit { margin-top: 1em; padding: 0.8em 1.5em; font-size: 1rem; background: #1B2420; color: white; border: none; border-radius: 6px; cursor: pointer; }
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
<h1>Bewertungen melden</h1>
<p>Du hast Grund zur Annahme, dass die Bewertungen für diesen Straßenabschnitt unwahr, beleidigend oder anderweitig unzulässig sind? Beschreibe kurz, worum es geht — wir prüfen jede Meldung.</p>

<?php if ($segmentId <= 0): ?>
  <p id="result" class="error">Kein Straßenabschnitt angegeben. Bitte über den „Melden"-Link auf einem Karten-Popup hierher kommen.</p>
<?php else: ?>
  <form id="report-form">
    <input type="hidden" id="segment_id" value="<?= htmlspecialchars((string) $segmentId) ?>">
    <label for="reason">Was ist das Problem?</label>
    <textarea id="reason" required placeholder="z.B. Die Bewertungen widersprechen offensichtlich der tatsächlichen Situation vor Ort, weil…"></textarea>
    <button type="submit" id="submit">Meldung absenden</button>
  </form>
  <div id="result" hidden></div>
<?php endif; ?>

<footer><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a></footer>

<script>
  const form = document.getElementById('report-form');
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const resultEl = document.getElementById('result');
      const submitBtn = document.getElementById('submit');
      submitBtn.disabled = true;
      try {
        const res = await fetch('/api/reports', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            segment_id: parseInt(document.getElementById('segment_id').value, 10),
            reason: document.getElementById('reason').value
          })
        });
        const data = await res.json();
        resultEl.hidden = false;
        if (res.ok) {
          resultEl.className = 'ok';
          resultEl.textContent = data.status || 'Danke, deine Meldung wird geprüft.';
          form.reset();
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
  }
</script>
</body>
</html>
