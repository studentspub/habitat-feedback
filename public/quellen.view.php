<?php
// Quellen & Lizenzen (Issue #33): jede Information der Seite mit Herausgeber, Lizenz und Stand.
// Datenstände kommen aus der DB (App\Support\Quellen::staende); ohne DB zeigt die Seite „unbekannt“.
use App\Support\Quellen;

try {
    $db = App\Support\Db::get();
} catch (\Throwable $e) {
    $db = null;
}
$gruppen = Quellen::gruppen($db);
$anzahl = array_sum(array_map(static fn(array $g): int => count($g['eintraege']), $gruppen));
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Quellen &amp; Lizenzen — Habitat Feedback</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<style>
  body { font-family: sans-serif; max-width: 64em; margin: 2em auto; padding: 0 1em; color: #1B2420; background: #F2F4EF; line-height: 1.5; }
  h1 { font-size: 1.4rem; }
  h2 { font-size: 1.05rem; margin: 2em 0 0.4em; padding-bottom: 4px; border-bottom: 2px solid #1B2420; }
  .hinweis { font-size: 0.85rem; color: #4B564F; margin: 0 0 0.6em; }
  a { color: #31576D; }
  a.back { display: inline-block; margin-bottom: 1em; }
  nav.gruppen { font-size: 0.85rem; margin: 0.6em 0 1.4em; }
  nav.gruppen a { display: inline-block; margin: 2px 10px 2px 0; }
  table { width: 100%; border-collapse: collapse; font-size: 0.82rem; background: #FBFBF7; }
  th, td { text-align: left; vertical-align: top; padding: 6px 8px; border-bottom: 1px solid #E3E7DE; }
  th { background: #EBEEE5; font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.03em; }
  td.anzeige { font-weight: 600; width: 26%; }
  td.quelle small { display: block; color: #4B564F; font-weight: 400; }
  td.lizenz { width: 24%; }
  td.lizenz .vermerk { display: block; color: #4B564F; font-size: 0.76rem; }
  td.stand { width: 14%; white-space: normal; }
  .lizenzen { font-size: 0.85rem; }
  .lizenzen li { margin-top: 4px; }
  footer { margin-top: 3em; font-size: 0.8em; }
  footer a { margin-right: 12px; }
  @media (max-width: 760px) {
    body { margin: 1em auto; }
    table, tbody, tr, td, td.anzeige, td.lizenz, td.stand { display: block; width: auto; }
    thead { display: none; }
    tr { border: 1px solid #CFD8C9; border-radius: 8px; margin-bottom: 10px; padding: 6px 2px; background: #FBFBF7; }
    td { border: none; padding: 4px 8px; }
    td::before { content: attr(data-l); display: block; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.03em; color: #7C877E; }
    td.anzeige::before { content: none; }
    td.anzeige { font-size: 0.9rem; }
  }
</style>
</head>
<body>
<a class="back" href="/">&larr; zurück zur Karte</a>
<h1>Quellen &amp; Lizenzen</h1>
<p>Habitat Feedback zeigt außer den Bewertungen selbst keine eigenen Zahlen. Diese Seite nennt für <strong>jede Information auf der Seite</strong>
(<?= $anzahl ?> Einträge) den Datensatz, den Herausgeber, die Lizenz mit dem vorgeschriebenen Quellenvermerk und den Datenstand.
Die Daten werden einmal eingelesen und aus unserer eigenen Datenbank ausgeliefert; nur die als „live“ oder „Drittserver“
gekennzeichneten Teile lädt dein Browser direkt bei der jeweiligen Stelle (siehe <a href="/datenschutz">Datenschutz</a>).</p>

<nav class="gruppen">
<?php foreach ($gruppen as $i => $g): ?>
  <a href="#g<?= $i ?>"><?= $h($g['titel']) ?></a>
<?php endforeach; ?>
  <a href="#lizenzen">Lizenztexte</a>
</nav>

<?php foreach ($gruppen as $i => $g): ?>
<h2 id="g<?= $i ?>"><?= $h($g['titel']) ?></h2>
<?php if (!empty($g['hinweis'])): ?><p class="hinweis"><?= $h($g['hinweis']) ?></p><?php endif; ?>
<table>
  <thead><tr><th>Auf der Seite</th><th>Datensatz und Herausgeber</th><th>Lizenz und Quellenvermerk</th><th>Stand</th></tr></thead>
  <tbody>
  <?php foreach ($g['eintraege'] as $e): [$lizName, $lizLink] = Quellen::LIZENZEN[$e['lizenz']]; ?>
    <tr>
      <td class="anzeige" data-l="Auf der Seite"><?= $h($e['anzeige']) ?></td>
      <td class="quelle" data-l="Datensatz und Herausgeber"><?= $h($e['quelle']) ?><small><?= $h($e['herausgeber']) ?> · <a href="<?= $h($e['link']) ?>"<?= str_starts_with($e['link'], 'http') ? ' target="_blank" rel="noopener"' : '' ?>>zur Quelle</a></small></td>
      <td class="lizenz" data-l="Lizenz und Quellenvermerk"><a href="<?= $h($lizLink) ?>"<?= str_starts_with($lizLink, 'http') ? ' target="_blank" rel="noopener"' : '' ?>><?= $h($lizName) ?></a>
        <?php if ($e['vermerk'] !== '–'): ?><span class="vermerk">Vermerk: <?= $h($e['vermerk']) ?></span><?php endif; ?></td>
      <td class="stand" data-l="Stand"><?= $h($e['stand']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endforeach; ?>

<h2 id="lizenzen">Lizenztexte</h2>
<ul class="lizenzen">
<?php foreach (Quellen::LIZENZEN as $k => [$name, $link]): ?>
  <li><a href="<?= $h($link) ?>"<?= str_starts_with($link, 'http') ? ' target="_blank" rel="noopener"' : '' ?>><?= $h($name) ?></a></li>
<?php endforeach; ?>
</ul>
<p class="hinweis">Fehlt eine Quelle oder stimmt ein Vermerk nicht? Bitte melden (siehe <a href="/projekt">Über das Projekt</a>). Stand dieser Seite: wird aus der Datenbank gelesen, Änderungen an den Quellen stehen im Quellcode (<code>app/Support/Quellen.php</code>).</p>

<footer><a href="/ueber">Über Habitat Feedback</a><a href="/projekt">Über das Projekt</a><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a></footer>
</body>
</html>
