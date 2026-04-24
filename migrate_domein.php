<?php
/**
 * Migratie voor domein-check module
 *
 * Draai deze één keer op productie:
 *   https://www.connect4it.nl/app/migrate_domein.php
 *
 * Beveilig daarna in .htaccess of verwijder het bestand.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

vereist_admin();

header('Content-Type: text/plain; charset=utf-8');

$statements = [
    "CREATE TABLE IF NOT EXISTS domein_lookups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        user_naam VARCHAR(255) NULL,
        domein VARCHAR(255) NOT NULL,
        aangemaakt_op DATETIME NOT NULL,
        INDEX idx_domein (domein),
        INDEX idx_aangemaakt (aangemaakt_op)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($statements as $sql) {
    try {
        db()->exec($sql);
        echo "[OK] " . substr(trim($sql), 0, 60) . "...\n";
    } catch (PDOException $e) {
        echo "[FOUT] " . $e->getMessage() . "\n";
    }
}

echo "\nKlaar.\n";
echo "Voeg dit blok toe aan .htaccess om dit bestand te blokkeren:\n\n";
echo "<FilesMatch \"^migrate_domein\\.php$\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>\n";
