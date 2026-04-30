<?php
/**
 * Migratie: meerdere internetverbindingen per klant.
 * - Voegt id (AUTO_INCREMENT PK) toe als die ontbreekt
 * - Verwijdert UNIQUE-constraint op klant_id (als die er is) en zet er een gewone INDEX op
 * - Voegt omschrijving + is_primair kolommen toe
 * - Markeert per klant 1 bestaande rij als primair (laagste id)
 *
 * Eenmaal uitvoeren:
 *   https://www.connect4it.nl/app/migrate_internet_meervoudig.php
 * Daarna blokkeren in .htaccess.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

vereist_admin();
header('Content-Type: text/plain; charset=utf-8');

$db = db();

function kolom_bestaat(PDO $db, string $tabel, string $kolom): bool {
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $st->execute([$tabel, $kolom]);
    return (int)$st->fetchColumn() > 0;
}

function index_info(PDO $db, string $tabel, string $kolom): array {
    $st = $db->prepare("SELECT INDEX_NAME, NON_UNIQUE FROM information_schema.STATISTICS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $st->execute([$tabel, $kolom]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// 1. id-kolom toevoegen indien afwezig
if (!kolom_bestaat($db, 'klant_internet', 'id')) {
    // Eventuele PK op klant_id eerst droppen
    try {
        $st = $db->query("SHOW KEYS FROM klant_internet WHERE Key_name = 'PRIMARY'");
        if ($st->fetch()) {
            $db->exec("ALTER TABLE klant_internet DROP PRIMARY KEY");
            echo "[OK] bestaande PRIMARY KEY (op klant_id) verwijderd\n";
        }
    } catch (PDOException $e) {
        echo "[INFO] geen PK te droppen: " . $e->getMessage() . "\n";
    }
    $db->exec("ALTER TABLE klant_internet ADD COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
    echo "[OK] kolom id (AUTO_INCREMENT PK) toegevoegd\n";
} else {
    echo "[OVERSLAAN] kolom id bestaat al\n";
}

// 2. UNIQUE-constraint op klant_id verwijderen, gewone INDEX zetten
$indexen = index_info($db, 'klant_internet', 'klant_id');
$heeft_unique = false;
$heeft_index  = false;
foreach ($indexen as $ix) {
    if ((int)$ix['NON_UNIQUE'] === 0 && $ix['INDEX_NAME'] !== 'PRIMARY') {
        try {
            $db->exec("ALTER TABLE klant_internet DROP INDEX `" . $ix['INDEX_NAME'] . "`");
            echo "[OK] UNIQUE-index '" . $ix['INDEX_NAME'] . "' op klant_id verwijderd\n";
            $heeft_unique = true;
        } catch (PDOException $e) {
            echo "[FOUT] verwijderen UNIQUE: " . $e->getMessage() . "\n";
        }
    } elseif ((int)$ix['NON_UNIQUE'] === 1) {
        $heeft_index = true;
    }
}
if (!$heeft_index) {
    try {
        $db->exec("ALTER TABLE klant_internet ADD INDEX idx_klant_id (klant_id)");
        echo "[OK] gewone INDEX op klant_id toegevoegd\n";
    } catch (PDOException $e) {
        echo "[INFO] index niet toegevoegd: " . $e->getMessage() . "\n";
    }
} else {
    echo "[OVERSLAAN] gewone INDEX op klant_id bestaat al\n";
}

// 3. Nieuwe kolommen
$nieuwe = [
    'omschrijving' => "VARCHAR(150) NULL",
    'is_primair'   => "TINYINT(1) NOT NULL DEFAULT 0",
];
foreach ($nieuwe as $naam => $def) {
    if (!kolom_bestaat($db, 'klant_internet', $naam)) {
        $db->exec("ALTER TABLE klant_internet ADD COLUMN $naam $def");
        echo "[OK] kolom $naam toegevoegd\n";
    } else {
        echo "[OVERSLAAN] kolom $naam bestaat al\n";
    }
}

// 4. Per klant 1 rij als primair markeren (alleen als nog geen primair gezet)
$st = $db->query("SELECT klant_id, MIN(id) AS eerste_id FROM klant_internet GROUP BY klant_id");
$gemarkeerd = 0;
while ($rij = $st->fetch(PDO::FETCH_ASSOC)) {
    $check = $db->prepare("SELECT COUNT(*) FROM klant_internet WHERE klant_id = ? AND is_primair = 1");
    $check->execute([$rij['klant_id']]);
    if ((int)$check->fetchColumn() === 0) {
        $db->prepare("UPDATE klant_internet SET is_primair = 1 WHERE id = ?")->execute([$rij['eerste_id']]);
        $gemarkeerd++;
    }
}
echo "[OK] $gemarkeerd klant(en) gekregen een primaire verbinding\n";

echo "\nKlaar.\n";
echo "Voeg dit blok toe aan .htaccess:\n\n";
echo "<FilesMatch \"^migrate_internet_meervoudig\\.php$\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>\n";
