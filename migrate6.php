<?php
/**
 * Migratie 6: Internet tabel + admin_url kolom voor Yeastar
 */
require_once __DIR__ . '/includes/db.php';
$db = db();

echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Migratie 6</title>';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>';
echo '<body class="p-4"><h3>Database migratie 6 — Internet tabel</h3><div class="mt-3">';

$stappen = [
    ["CREATE TABLE IF NOT EXISTS klant_internet (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        klant_id         INT NOT NULL UNIQUE,
        provider         VARCHAR(100) DEFAULT '',
        provider_anders  VARCHAR(200) DEFAULT '',
        type             VARCHAR(50)  DEFAULT '',
        snelheid_down    VARCHAR(20)  DEFAULT '',
        snelheid_up      VARCHAR(20)  DEFAULT '',
        ip_adres         VARCHAR(50)  DEFAULT '',
        contract_datum   DATE         DEFAULT NULL,
        notities         TEXT,
        aangemaakt_op    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (klant_id) REFERENCES klanten(id) ON DELETE CASCADE
    )", "klant_internet tabel aangemaakt"],

    ["ALTER TABLE klant_yeastar ADD COLUMN IF NOT EXISTS admin_url VARCHAR(300) DEFAULT '' AFTER poort",
     "admin_url kolom toegevoegd aan klant_yeastar"],
];

foreach ($stappen as [$sql, $label]) {
    try {
        $db->exec($sql);
        echo '<div class="text-success mb-1">&#10003; ' . htmlspecialchars($label) . '</div>';
    } catch (Exception $e) {
        echo '<div class="text-danger mb-1"><strong>FOUT:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

echo '</div><hr>';
echo '<div class="alert alert-success"><strong>Klaar!</strong> Internet tab is nu beschikbaar op de klantpagina.</div>';
echo '<div class="alert alert-warning"><strong>Vergeet niet:</strong> Verwijder <code>migrate6.php</code> na gebruik.</div>';
echo '</body></html>';
