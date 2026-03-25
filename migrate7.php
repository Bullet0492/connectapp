<?php
/**
 * Migratie 7: O365 licenties en gebruikers tabellen, verwijder oude kolommen
 */
require_once __DIR__ . '/includes/db.php';
$db = db();

echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Migratie 7</title>';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>';
echo '<body class="p-4"><h3>Database migratie 7 — Office 365 licenties &amp; gebruikers</h3><div class="mt-3">';

$stappen = [
    ["CREATE TABLE IF NOT EXISTS klant_o365_licenties (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        klant_id      INT NOT NULL,
        licentie_type VARCHAR(150) NOT NULL,
        aantal        INT DEFAULT 1,
        FOREIGN KEY (klant_id) REFERENCES klanten(id) ON DELETE CASCADE
    )", "klant_o365_licenties tabel aangemaakt"],

    ["CREATE TABLE IF NOT EXISTS klant_o365_gebruikers (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        klant_id      INT NOT NULL,
        naam          VARCHAR(200) DEFAULT '',
        email         VARCHAR(200) DEFAULT '',
        wachtwoord_enc TEXT,
        rol           VARCHAR(100) DEFAULT 'Gebruiker',
        notities      TEXT,
        aangemaakt_op TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (klant_id) REFERENCES klanten(id) ON DELETE CASCADE
    )", "klant_o365_gebruikers tabel aangemaakt"],

    ["ALTER TABLE klant_o365
        DROP COLUMN IF EXISTS licentie_type,
        DROP COLUMN IF EXISTS aantal_licenties,
        DROP COLUMN IF EXISTS mfa_actief,
        DROP COLUMN IF EXISTS conditional_access",
     "Oude kolommen verwijderd uit klant_o365"],
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
echo '<div class="alert alert-success"><strong>Klaar!</strong> Office 365 tab ondersteunt nu meerdere licentietypen en gebruikers.</div>';
echo '<div class="alert alert-warning"><strong>Vergeet niet:</strong> Verwijder <code>migrate7.php</code> na gebruik.</div>';
echo '</body></html>';
