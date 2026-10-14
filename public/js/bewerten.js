// Bewertungsformular (/bewerten). Ausgelagert aus der View (Issue #15), damit
// die Seite dieselbe strenge Content-Security-Policy bekommt wie die Karte.
(function () {
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

  // Straßen-Vorschläge aus dem eigenen Straßenverzeichnis (Issue #15,
  // /api/address-autocomplete) — beim Tippen fragt niemand außer unserem
  // Server. Nominatim kommt erst nach dem Absenden ins Spiel (Geocode-Worker).
  const strasseInput = document.getElementById('address');
  const hausnummerInput = document.getElementById('hausnummer');
  const suggestions = document.getElementById('address-suggestions');
  let timeout = null;
  let gewaehlt = null;   // {strasse, plz, ort} des angeklickten Vorschlags
  let markiert = -1;
  let laufend = null;

  function waehlen(r, hausnummer) {
    gewaehlt = r;
    strasseInput.value = r.label;
    suggestions.hidden = true;
    if (hausnummer && !hausnummerInput.value) {
      hausnummerInput.value = hausnummer;
    }
    hausnummerInput.focus();
  }

  function markieren(i) {
    const items = suggestions.querySelectorAll('div[data-i]');
    markiert = Math.max(-1, Math.min(i, items.length - 1));
    items.forEach((el, k) => el.classList.toggle('active', k === markiert));
  }

  strasseInput.addEventListener('input', () => {
    clearTimeout(timeout);
    gewaehlt = null;
    const q = strasseInput.value.trim();
    if (q.length < 3) {
      suggestions.hidden = true;
      return;
    }
    timeout = setTimeout(async () => {
      if (laufend) laufend.abort();
      laufend = new AbortController();
      let data;
      try {
        const res = await fetch('/api/address-autocomplete?q=' + encodeURIComponent(q), { signal: laufend.signal });
        data = await res.json();
      } catch (err) {
        return; // abgebrochen (weitergetippt) oder offline — das Feld bleibt frei benutzbar
      }
      suggestions.innerHTML = '';
      markiert = -1;
      const treffer = data.results || [];
      if (treffer.length === 0) {
        const leer = document.createElement('div');
        leer.className = 'hint';
        leer.textContent = 'Keine Straße gefunden — du kannst die Adresse trotzdem so absenden.';
        suggestions.appendChild(leer);
      }
      treffer.forEach((r, i) => {
        const item = document.createElement('div');
        item.dataset.i = i;
        item.textContent = r.label;
        item.addEventListener('click', () => waehlen(r, data.hausnummer));
        suggestions.appendChild(item);
      });
      suggestions.hidden = false;
      suggestions._treffer = treffer;
      suggestions._hausnummer = data.hausnummer;
    }, 200);
  });

  strasseInput.addEventListener('keydown', (e) => {
    if (suggestions.hidden) return;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      markieren(markiert + (e.key === 'ArrowDown' ? 1 : -1));
    } else if (e.key === 'Enter' && markiert >= 0) {
      e.preventDefault();
      waehlen(suggestions._treffer[markiert], suggestions._hausnummer);
    } else if (e.key === 'Escape') {
      suggestions.hidden = true;
    }
  });

  document.addEventListener('click', (e) => {
    if (e.target !== strasseInput) suggestions.hidden = true;
  });

  // "Kantstraße, 10623 Berlin" + "12" -> "Kantstraße 12, 10623 Berlin". Ohne
  // gewählten Vorschlag wird die Hausnummer vor das erste Komma gesetzt.
  function adresse() {
    const hn = hausnummerInput.value.trim();
    if (gewaehlt) {
      return gewaehlt.strasse + ' ' + hn + ', ' + (gewaehlt.plz ? gewaehlt.plz + ' ' : '') + gewaehlt.ort;
    }
    const text = strasseInput.value.trim();
    const komma = text.indexOf(',');
    return komma < 0 ? (text + ' ' + hn).trim() : text.slice(0, komma) + ' ' + hn + text.slice(komma);
  }

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
        body: JSON.stringify({ address: adresse(), scores })
      });
      const data = await res.json();
      resultEl.hidden = false;
      if (res.ok) {
        resultEl.className = 'ok';
        resultEl.textContent = data.hinweis || 'Danke, deine Bewertung wurde übermittelt.';
        document.getElementById('rating-form').reset();
        gewaehlt = null;
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
})();
