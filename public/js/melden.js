// Meldeformular /melden (aus melden.view.php ausgelagert, Issue #30: keine Inline-Skripte,
// damit die Seite dieselbe Content-Security-Policy bekommt wie die übrigen Seiten).
(function () {
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
})();
