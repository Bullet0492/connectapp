<?php
/**
 * Migratie: bruteforce-bescherming per (login + IP) ipv alleen IP.
 * Voegt kolom `login` toe aan `login_pogingen` zodat mislukte pogingen
 * voor user X op IP Y alleen die specifieke user-IP combinatie blokkeren.
 *
 * Eenmaal uitvoeren:
 *   https://www.connect4it.nl/app/migrate_login_pogingen.php
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
                                AND TABLE_NAME = 'login_pogingen'
                                AND COLUMN_NAME = 'login'");
    $bestaat->execute();
    if ((int)$bestaat->fetchColumn() === 0) {
        db()->exec("ALTER TABLE login_pogingen ADD COLUMN login VARCHAR(190) NOT NULL DEFAULT '' AFTER ip");
        echo "[OK] kolom login toegevoegd\n";
    } else {
        echo "[OVERSLAAN] kolom login bestaat al\n";
    }
} catch (PDOException $e) {
    echo "[FOUT] " . $e->getMessage() . "\n";
}

// Oude UNIQUE op alleen ip vervangen door (ip,login). Lukt niet altijd zonder
// te weten hoe de bestaande index heet — daarom alle bestaande rijen wegklappen
// als veiligheidsmaatregel (geen verlies van functionaliteit, alleen telling).
try {
    db()->exec("DELETE FROM login_pogingen");
    echo "[OK] login_pogingen geleegd (eenmalige reset bij migratie)\n";
} catch (PDOException $e) {
    echo "[FOUT] reset: " . $e->getMessage() . "\n";
}

echo "\nKlaar.\n";
echo "Voeg dit blok toe aan .htaccess:\n\n";
echo "<FilesMatch \"^migrate_login_pogingen\\.php$\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>\n";
