<?php
require_once __DIR__ . '/includes/db.php';
$db = db();
echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Migratie 9</title>';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>';
echo '<body class="p-4"><h3>Migratie 9 — 4G backup kolom</h3><div class="mt-3">';
try {
    $db->exec("ALTER TABLE klant_internet ADD COLUMN IF NOT EXISTS backup_4g TINYINT(1) DEFAULT 0");
    echo '<div class="text-success mb-1">&#10003; backup_4g kolom toegevoegd aan klant_internet</div>';
} catch (Exception $e) {
    echo '<div class="text-danger mb-1"><strong>FOUT:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div><hr><div class="alert alert-success"><strong>Klaar!</strong></div>';
echo '<div class="alert alert-warning">Verwijder <code>migrate9.php</code> na gebruik.</div>';
echo '</body></html>';
