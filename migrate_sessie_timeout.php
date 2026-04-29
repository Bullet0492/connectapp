<?php
/**
 * Migratie voor per-gebruiker sessie timeout instelling.
 * Voegt sessie_timeout_minuten kolom toe aan users.
 *   0     = nooit uitloggen (alleen handmatig)
 *   120   = 2 uur (default — gelijk aan oude gedrag)
 *   480   = 8 uur, 1440 = 1 dag, 10080 = 7 dagen, etc.
 *
 * Eenmaal uitvoeren:
 *   https://www.connect4it.nl/app/migrate_sessie_timeout.php
 * Daarna blokkeren in .htaccess.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

vereist_admin();

header('Content-Type: text/plain; charset=utf-8');

try {
    $bestaat = db()->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                              WHERE TABLE_SCHEMA = DATABASE()
                                AND TABLE_NAME = 'users'
                                AND COLUMN_NAME = 'sessie_timeout_minuten'");
    $bestaat->execute();
    if ((int)$bestaat->fetchColumn() === 0) {
        db()->exec("ALTER TABLE users ADD COLUMN sessie_timeout_minuten INT NOT NULL DEFAULT 120");
        echo "[OK] kolom sessie_timeout_minuten toegevoegd (default 120 min)\n";
    } else {
        echo "[OVERSLAAN] kolom bestaat al\n";
    }
} catch (PDOException $e) {
    echo "[FOUT] " . $e->getMessage() . "\n";
}

echo "\nKlaar.\n";
echo "Voeg dit blok toe aan .htaccess:\n\n";
echo "<FilesMatch \"^migrate_sessie_timeout\\.php$\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>\n";
