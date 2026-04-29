<?php
/**
 * Migratie voor RoutIT (telefonie module).
 * Eenmaal uitvoeren:
 *   https://www.connect4it.nl/app/migrate_routit.php
 * Daarna blokkeren in .htaccess.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

vereist_admin();

header('Content-Type: text/plain; charset=utf-8');

$sql = "CREATE TABLE IF NOT EXISTS klant_routit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    klant_id INT NOT NULL,
    actief TINYINT(1) NOT NULL DEFAULT 0,
    naam VARCHAR(150) NULL,
    aangemaakt_op DATETIME DEFAULT CURRENT_TIMESTAMP,
    bijgewerkt_op DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_klant (klant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    db()->exec($sql);
    echo "[OK] klant_routit tabel aangemaakt\n";
} catch (PDOException $e) {
    echo "[FOUT] " . $e->getMessage() . "\n";
}

echo "\nKlaar.\n";
echo "Voeg dit blok toe aan .htaccess:\n\n";
echo "<FilesMatch \"^migrate_routit\\.php$\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>\n";
