<?php
/**
 * Migratie voor DrayTek ACS integratie (fase 1: directe link).
 * Eénmaal uitvoeren:
 *   https://www.connect4it.nl/app/migrate_acs.php
 *
 * Daarna blokkeren in .htaccess.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

vereist_admin();

header('Content-Type: text/plain; charset=utf-8');

$statements = [
    "ALTER TABLE klanten ADD COLUMN IF NOT EXISTS acs_network_id VARCHAR(32) DEFAULT NULL",
    "ALTER TABLE klanten ADD COLUMN IF NOT EXISTS acs_device_naam VARCHAR(255) DEFAULT NULL",
];

foreach ($statements as $sql) {
    try {
        db()->exec($sql);
        echo "[OK] " . substr(trim($sql), 0, 80) . "...\n";
    } catch (PDOException $e) {
        echo "[FOUT] " . $e->getMessage() . "\n";
    }
}

echo "\nKlaar.\n";
echo "Voeg dit blok toe aan .htaccess om dit bestand te blokkeren:\n\n";
echo "<FilesMatch \"^migrate_acs\\.php$\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>\n";
