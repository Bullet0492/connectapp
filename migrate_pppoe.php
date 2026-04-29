<?php
/**
 * Migratie voor PPPoE gegevens (uitbreiding van klant_internet).
 * Voegt PPPoE gebruiker + wachtwoord (encrypted) en VLAN ID toe.
 * Eenmaal uitvoeren:
 *   https://www.connect4it.nl/app/migrate_pppoe.php
 * Daarna blokkeren in .htaccess.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

vereist_admin();

header('Content-Type: text/plain; charset=utf-8');

$kolommen = [
    'pppoe_gebruiker'        => "VARCHAR(150) NULL",
    'pppoe_wachtwoord_enc'   => "TEXT NULL",
    'vlan_id'                => "SMALLINT UNSIGNED NULL",
];

foreach ($kolommen as $naam => $def) {
    try {
        $bestaat = db()->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                                  WHERE TABLE_SCHEMA = DATABASE()
                                    AND TABLE_NAME = 'klant_internet'
                                    AND COLUMN_NAME = ?");
        $bestaat->execute([$naam]);
        if ((int)$bestaat->fetchColumn() === 0) {
            db()->exec("ALTER TABLE klant_internet ADD COLUMN $naam $def");
            echo "[OK] kolom $naam toegevoegd\n";
        } else {
            echo "[OVERSLAAN] kolom $naam bestaat al\n";
        }
    } catch (PDOException $e) {
        echo "[FOUT] $naam: " . $e->getMessage() . "\n";
    }
}

echo "\nKlaar.\n";
echo "Voeg dit blok toe aan .htaccess:\n\n";
echo "<FilesMatch \"^migrate_pppoe\\.php$\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>\n";
