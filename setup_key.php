<?php
/**
 * Eenmalig uitvoeren om een encryptie-sleutel te genereren.
 * Kopieer de sleutel naar config.php → ENCRYPT_KEY
 * Verwijder daarna dit bestand!
 *
 * URL: http://localhost/App/setup_key.php
 */
$key = bin2hex(random_bytes(32));
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Sleutel genereren</title>';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>';
echo '<body class="p-4"><div class="alert alert-warning"><strong>⚠️ Eenmalig gebruik!</strong> Kopieer deze sleutel naar <code>config.php</code> bij <code>ENCRYPT_KEY</code>. Verwijder dit bestand daarna direct.</div>';
echo '<div class="bg-light border rounded p-3 mb-3"><code style="font-size:16px;word-break:break-all;">' . $key . '</code></div>';
echo '<button onclick="navigator.clipboard.writeText(\'' . $key . '\')" class="btn btn-primary">Kopiëren</button>';
echo '<p class="mt-3 text-danger"><strong>Waarschuwing:</strong> Als je de sleutel verliest of wijzigt, zijn alle opgeslagen wachtwoorden niet meer te ontsleutelen!</p>';
echo '</body></html>';
