<?php
require_once __DIR__ . '/includes/db.php';
$db = db();
echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Migratie 10</title>';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>';
echo '<body class="p-4"><h3>Migratie 10 — VPS kolom</h3><div class="mt-3">';
try {
    $db->exec("ALTER TABLE klanten ADD COLUMN IF NOT EXISTS vps VARCHAR(50) DEFAULT NULL");
    echo '<div class="text-success mb-1">&#10003; vps kolom toegevoegd aan klanten</div>';
} catch (Exception $e) {
    echo '<div class="text-danger mb-1"><strong>FOUT:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
}
echo '</div><hr><div class="alert alert-success"><strong>Klaar!</strong></div>';
echo '<div class="alert alert-warning">Verwijder <code>migrate10.php</code> na gebruik.</div>';
echo '</body></html>';
