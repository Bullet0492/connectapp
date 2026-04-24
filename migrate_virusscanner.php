<?php
/**
 * Migratie voor virusscanner module (per klant).
 * Eenmaal uitvoeren:
 *   https://www.connect4it.nl/app/migrate_virusscanner.php
 * Daarna blokkeren in .htaccess.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

vereist_admin();

header('Content-Type: text/plain; charset=utf-8');

$sql = "CREATE TABLE IF NOT EXISTS klant_virusscanner (
    id INT AUTO_INCREMENT PRIMARY KEY,
    klant_id INT NOT NULL,
    scanner VARCHAR(50) NOT NULL DEFAULT 'geen',
    scanner_anders VARCHAR(100) NULL,
    licentie_encrypted TEXT NULL,
    uninstall_code_encrypted TEXT NULL,
    vervaldatum DATE NULL,
    notities TEXT NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    bijgewerkt_op DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_klant (klant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    db()->exec($sql);
    echo "[OK] klant_virusscanner tabel aangemaakt\n";
} catch (PDOException $e) {
    echo "[FOUT] " . $e->getMessage() . "\n";
}

echo "\nKlaar.\n";
echo "Voeg dit blok toe aan .htaccess:\n\n";
echo "<FilesMatch \"^migrate_virusscanner\\.php$\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>\n";
