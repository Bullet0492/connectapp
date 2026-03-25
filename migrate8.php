<?php
/**
 * Migratie 8: licentie_type kolom toevoegen aan klant_o365_gebruikers
 */
require_once __DIR__ . '/includes/db.php';
$db = db();

echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Migratie 8</title>';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>';
echo '<body class="p-4"><h3>Database migratie 8 — Licentie per gebruiker</h3><div class="mt-3">';

try {
    $db->exec("ALTER TABLE klant_o365_gebruikers ADD COLUMN IF NOT EXISTS licentie_type VARCHAR(150) DEFAULT ''");
    echo '<div class="text-success mb-1">&#10003; licentie_type kolom toegevoegd aan klant_o365_gebruikers</div>';
} catch (Exception $e) {
    echo '<div class="text-danger mb-1"><strong>FOUT:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
}

echo '</div><hr>';
echo '<div class="alert alert-success"><strong>Klaar!</strong></div>';
echo '<div class="alert alert-warning"><strong>Vergeet niet:</strong> Verwijder <code>migrate8.php</code> na gebruik.</div>';
echo '</body></html>';
