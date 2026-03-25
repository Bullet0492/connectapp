<?php
/**
 * Migratie 5: Office 365, Yeastar en Simpbx tabellen
 */
require_once __DIR__ . '/includes/db.php';
$db = db();

echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Migratie 5</title>';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>';
echo '<body class="p-4"><h3>Database migratie 5 — Office 365, Yeastar, Simpbx</h3><div class="mt-3">';

$stappen = [
    ["CREATE TABLE IF NOT EXISTS klant_o365 (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        klant_id            INT NOT NULL UNIQUE,
        tenant_naam         VARCHAR(200) DEFAULT '',
        tenant_id           VARCHAR(100) DEFAULT '',
        admin_email         VARCHAR(200) DEFAULT '',
        admin_wachtwoord_enc TEXT,
        licentie_type       VARCHAR(100) DEFAULT '',
        aantal_licenties    INT DEFAULT 0,
        mfa_actief          TINYINT(1) DEFAULT 0,
        conditional_access  TINYINT(1) DEFAULT 0,
        notities            TEXT,
        aangemaakt_op       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (klant_id) REFERENCES klanten(id) ON DELETE CASCADE
    )", "klant_o365 tabel aangemaakt"],

    ["CREATE TABLE IF NOT EXISTS klant_yeastar (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        klant_id            INT NOT NULL,
        model               VARCHAR(100) DEFAULT '',
        ip_adres            VARCHAR(50)  DEFAULT '',
        poort               VARCHAR(10)  DEFAULT '8088',
        firmware            VARCHAR(100) DEFAULT '',
        admin_gebruiker     VARCHAR(100) DEFAULT 'admin',
        admin_wachtwoord_enc TEXT,
        notities            TEXT,
        aangemaakt_op       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (klant_id) REFERENCES klanten(id) ON DELETE CASCADE
    )", "klant_yeastar tabel aangemaakt"],

    ["CREATE TABLE IF NOT EXISTS klant_simpbx (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        klant_id            INT NOT NULL UNIQUE,
        actief              TINYINT(1)   DEFAULT 0,
        aantal_extensies    INT          DEFAULT 0,
        sip_domein          VARCHAR(200) DEFAULT '',
        admin_url           VARCHAR(300) DEFAULT '',
        admin_gebruiker     VARCHAR(100) DEFAULT '',
        admin_wachtwoord_enc TEXT,
        notities            TEXT,
        aangemaakt_op       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (klant_id) REFERENCES klanten(id) ON DELETE CASCADE
    )", "klant_simpbx tabel aangemaakt"],
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
echo '<div class="alert alert-success"><strong>Klaar!</strong> Office 365, Yeastar en Simpbx tabs zijn nu beschikbaar op de klantpagina.</div>';
echo '<div class="alert alert-warning"><strong>Vergeet niet:</strong> Verwijder <code>migrate5.php</code> na gebruik.</div>';
echo '</body></html>';
