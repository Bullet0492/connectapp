<?php
/**
 * Migratie 3: Microsoft 365 SSO koppeling + laatste_login bijhouden
 * URL: https://www.connect4it.nl/app/migrate3.php
 * Eenmalig uitvoeren, daarna verwijderen of toegang blokkeren.
 */
require_once __DIR__ . '/includes/db.php';
$db = db();

echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Migratie 3</title>';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>';
echo '<body class="p-4"><h3>Database migratie 3 — Microsoft SSO</h3><div class="mt-3">';

$stappen = [
    [
        "ALTER TABLE users ADD COLUMN ms_oid VARCHAR(36) NULL DEFAULT NULL UNIQUE AFTER email",
        "users.ms_oid kolom toegevoegd (Microsoft object-ID)"
    ],
    [
        "ALTER TABLE users ADD COLUMN laatste_login DATETIME NULL DEFAULT NULL AFTER ms_oid",
        "users.laatste_login kolom toegevoegd"
    ],
];

foreach ($stappen as [$sql, $label]) {
    try {
        $db->exec($sql);
        echo '<div class="text-success mb-1">&#10003; ' . htmlspecialchars($label) . '</div>';
    } catch (Exception $e) {
        // Kolom bestaat al = geen probleem
        if (str_contains($e->getMessage(), 'Duplicate column')) {
            echo '<div class="text-info mb-1">&#8505; Al aanwezig: ' . htmlspecialchars($label) . '</div>';
        } else {
            echo '<div class="text-danger mb-1"><strong>FOUT:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

echo '</div><hr>';
echo '<div class="alert alert-success"><strong>Klaar!</strong> Je kunt nu Microsoft 365-login instellen in <code>config.php</code>.</div>';
echo '<div class="alert alert-warning"><strong>Vergeet niet:</strong> Verwijder of blokkeer <code>migrate3.php</code> na gebruik.</div>';
echo '</body></html>';
