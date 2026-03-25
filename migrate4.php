<?php
/**
 * Migratie 4: 2FA (TOTP) ondersteuning
 * URL: https://www.connect4it.nl/app/migrate4.php
 * Eenmalig uitvoeren, daarna verwijderen.
 */
require_once __DIR__ . '/includes/db.php';
$db = db();

echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Migratie 4</title>';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>';
echo '<body class="p-4"><h3>Database migratie 4 — 2FA (TOTP)</h3><div class="mt-3">';

$stappen = [
    [
        "ALTER TABLE users ADD COLUMN totp_secret TEXT NULL DEFAULT NULL AFTER ms_oid",
        "users.totp_secret kolom toegevoegd (versleuteld TOTP-geheim)"
    ],
    [
        "ALTER TABLE users ADD COLUMN totp_actief TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret",
        "users.totp_actief kolom toegevoegd"
    ],
];

foreach ($stappen as [$sql, $label]) {
    try {
        $db->exec($sql);
        echo '<div class="text-success mb-1">&#10003; ' . htmlspecialchars($label) . '</div>';
    } catch (Exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate column')) {
            echo '<div class="text-info mb-1">&#8505; Al aanwezig: ' . htmlspecialchars($label) . '</div>';
        } else {
            echo '<div class="text-danger mb-1"><strong>FOUT:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

echo '</div><hr>';
echo '<div class="alert alert-success"><strong>Klaar!</strong> Gebruikers kunnen nu 2FA inschakelen via <code>gebruikers/2fa.php</code>.</div>';
echo '<div class="alert alert-warning"><strong>Vergeet niet:</strong> Verwijder of blokkeer <code>migrate4.php</code> na gebruik.</div>';
echo '</body></html>';
