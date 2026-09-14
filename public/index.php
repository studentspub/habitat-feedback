<?php
// Smoke-test: bestätigt, dass PHP in der habitat-feedback-dev WSL-Distro
// dieselben Dateien liest, die unter D:\git\habitat-feedback liegen.
$config = require __DIR__ . '/../config/config.php';

$pdo = null;
$dbStatus = 'nicht verbunden';
try {
    $db = $config['db'];
    $dsn = "mysql:host={$db['host']};dbname={$db['dbname']};charset={$db['charset']}";
    $pdo = new PDO($dsn, $db['user'], $db['pass']);
    $dbStatus = 'verbunden (' . $pdo->query('SELECT VERSION()')->fetchColumn() . ')';
} catch (PDOException $e) {
    $dbStatus = 'Fehler: ' . $e->getMessage();
}
?>
<!doctype html>
<meta charset="utf-8">
<title>Habitat Feedback Dev-Umgebung</title>
<body style="font-family: sans-serif; max-width: 40em; margin: 3em auto;">
<h1>Habitat Feedback Dev-Umgebung läuft</h1>
<p>PHP-Version: <?= phpversion() ?></p>
<p>Datei gelesen von: <?= __FILE__ ?></p>
<p>MariaDB: <?= htmlspecialchars($dbStatus) ?></p>
</body>
