<?php
/**
 * Migratie 2: MAC/IP apparaten, servicehistorie, bestanden, contracten
 * URL: http://localhost/App/migrate2.php
 */
require_once __DIR__ . '/includes/db.php';
$db = db();

echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Migratie 2</title>';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>';
echo '<body class="p-4"><h3>Database migratie 2</h3><div class="mt-3">';

$stappen = [];

// ─── Apparaten: extra velden ─────────────────────────────────────────────────
$stappen[] = ["ALTER TABLE apparaten ADD COLUMN IF NOT EXISTS mac_adres   VARCHAR(50) DEFAULT ''", "apparaten.mac_adres toegevoegd"];
$stappen[] = ["ALTER TABLE apparaten ADD COLUMN IF NOT EXISTS ip_adres    VARCHAR(50) DEFAULT ''", "apparaten.ip_adres toegevoegd"];
$stappen[] = ["ALTER TABLE apparaten ADD COLUMN IF NOT EXISTS firmware    VARCHAR(100) DEFAULT ''", "apparaten.firmware toegevoegd"];

// ─── Servicehistorie ─────────────────────────────────────────────────────────
$stappen[] = ["CREATE TABLE IF NOT EXISTS service_historie (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    klant_id        INT NOT NULL,
    apparaat_id     INT NULL,
    datum           DATE NOT NULL,
    type            ENUM('bezoek','storing','onderhoud','update','overig') NOT NULL DEFAULT 'bezoek',
    omschrijving    TEXT NOT NULL,
    opgelost_door   VARCHAR(100) DEFAULT '',
    aangemaakt_door INT NULL,
    aangemaakt_op   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (klant_id) REFERENCES klanten(id) ON DELETE CASCADE
)", "service_historie tabel aangemaakt"];

// ─── Bestanden ───────────────────────────────────────────────────────────────
$stappen[] = ["CREATE TABLE IF NOT EXISTS klant_bestanden (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    klant_id        INT NOT NULL,
    bestandsnaam    VARCHAR(255) NOT NULL,
    originele_naam  VARCHAR(255) NOT NULL,
    bestandstype    VARCHAR(100) DEFAULT '',
    bestandsgrootte INT DEFAULT 0,
    aangemaakt_door INT NULL,
    aangemaakt_op   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (klant_id) REFERENCES klanten(id) ON DELETE CASCADE
)", "klant_bestanden tabel aangemaakt"];

// ─── Contracten ──────────────────────────────────────────────────────────────
$stappen[] = ["CREATE TABLE IF NOT EXISTS contracten (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    klant_id        INT NOT NULL,
    omschrijving    VARCHAR(200) NOT NULL,
    sla_niveau      ENUM('basis','standaard','premium') NOT NULL DEFAULT 'standaard',
    start_datum     DATE NULL,
    eind_datum      DATE NULL,
    notities        TEXT,
    aangemaakt_op   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (klant_id) REFERENCES klanten(id) ON DELETE CASCADE
)", "contracten tabel aangemaakt"];

foreach ($stappen as [$sql, $label]) {
    try {
        $db->exec($sql);
        echo '<div class="text-success mb-1">&#10003; ' . htmlspecialchars($label) . '</div>';
    } catch (Exception $e) {
        echo '<div class="text-danger mb-1"><strong>FOUT:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Uploads map aanmaken
$uploads = __DIR__ . '/uploads/bestanden';
if (!is_dir($uploads)) {
    mkdir($uploads, 0755, true);
    echo '<div class="text-success mb-1">&#10003; uploads/bestanden map aangemaakt</div>';
} else {
    echo '<div class="text-info mb-1">&#8505; uploads/bestanden map bestaat al</div>';
}

// .htaccess voor uploads map (blokkeer PHP uitvoering)
$htaccess = __DIR__ . '/uploads/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "php_flag engine off\nOptions -Indexes\n");
    echo '<div class="text-success mb-1">&#10003; uploads/.htaccess aangemaakt (PHP geblokkeerd)</div>';
}

echo '</div><hr>';
echo '<div class="alert alert-success"><strong>Klaar!</strong> Ga naar <a href="login.php">de app</a>.</div>';
echo '</body></html>';
