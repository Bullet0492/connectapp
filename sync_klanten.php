<?php
$paginatitel = 'Klanten synchroniseren';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
sessie_start();
vereist_admin();

// ─── Verbinding werkbon database ──────────────────────────────────────────────
function db_werkbon(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=connect4it_werkbondb;charset=utf8mb4',
            'connect4it_werkbondb',
            'y8X5s59dqdehcJFp8Nru',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

$resultaten  = [];
$fout        = null;
$uitgevoerd  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $uitgevoerd = true;

    try {
        $werkbon_klanten = db_werkbon()->query('SELECT * FROM klanten ORDER BY naam')->fetchAll();
        $nieuw = 0;
        $bijgewerkt = 0;
        $overgeslagen = 0;

        foreach ($werkbon_klanten as $wk) {
            $naam      = trim($wk['naam'] ?? '');
            $intra_id  = trim($wk['intra_id'] ?? '');
            if (!$naam) continue;

            // Zoek bestaande klant: eerst op intra_id, dan op exacte naam
            $bestaand = null;
            if ($intra_id !== '') {
                $s = db()->prepare('SELECT id FROM klanten WHERE intra_id = ? LIMIT 1');
                $s->execute([$intra_id]);
                $bestaand = $s->fetch();
            }
            if (!$bestaand) {
                $s = db()->prepare('SELECT id FROM klanten WHERE naam = ? LIMIT 1');
                $s->execute([$naam]);
                $bestaand = $s->fetch();
            }

            if ($bestaand) {
                // Bijwerken — alleen werkbon-velden, connectapp-eigen velden (bedrijf, vps, notities) bewaren
                db()->prepare("UPDATE klanten SET naam=?, adres=?, postcode=?, stad=?, telefoon=?, email=?, intra_id=? WHERE id=?")
                   ->execute([$naam, $wk['adres'] ?? '', $wk['postcode'] ?? '', $wk['stad'] ?? '', $wk['telefoon'] ?? '', $wk['email'] ?? '', $intra_id ?: null, $bestaand['id']]);
                $resultaten[] = ['actie' => 'bijgewerkt', 'naam' => $naam];
                $bijgewerkt++;
            } else {
                // Nieuw aanmaken
                db()->prepare("INSERT INTO klanten (naam, adres, postcode, stad, telefoon, email, intra_id) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$naam, $wk['adres'] ?? '', $wk['postcode'] ?? '', $wk['stad'] ?? '', $wk['telefoon'] ?? '', $wk['email'] ?? '', $intra_id ?: null]);
                $resultaten[] = ['actie' => 'nieuw', 'naam' => $naam];
                $nieuw++;
            }
        }

        log_actie('klanten_gesynchroniseerd', "Nieuw: $nieuw, Bijgewerkt: $bijgewerkt");
        flash_set('succes', "Synchronisatie klaar — $nieuw nieuw, $bijgewerkt bijgewerkt.");

    } catch (Exception $e) {
        $fout = $e->getMessage();
    }
}

// Preview: haal werkbon klanten op zonder op te slaan
$werkbon_klanten_preview = [];
$werkbon_fout = null;
try {
    $werkbon_klanten_preview = db_werkbon()->query('SELECT * FROM klanten ORDER BY naam')->fetchAll();
} catch (Exception $e) {
    $werkbon_fout = $e->getMessage();
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-4">
    <h4 class="fw-bold mb-1">Klanten synchroniseren</h4>
    <p class="text-muted mb-0">Importeer klanten uit de werkbonnen app naar de connect app.</p>
</div>

<?= flash_html() ?>

<?php if ($fout): ?>
<div class="alert alert-danger"><i class="ri-error-warning-line me-2"></i><?= h($fout) ?></div>
<?php endif; ?>

<?php if ($werkbon_fout): ?>
<div class="alert alert-danger"><i class="ri-error-warning-line me-2"></i>Kan werkbon database niet bereiken: <?= h($werkbon_fout) ?></div>
<?php else: ?>

<div class="bg-white rounded-3 border p-4 mb-4" style="max-width:600px;">
    <h6 class="fw-bold mb-3"><i class="ri-refresh-line me-1 text-primary"></i> Werkbonnen → Connect App</h6>
    <p class="text-muted small mb-3">
        Gevonden in werkbonnen app: <strong><?= count($werkbon_klanten_preview) ?> klanten</strong><br>
        Matching op <strong>Intelly ID</strong> (als aanwezig), anders op <strong>naam</strong>.<br>
        Connectapp-eigen velden (bedrijf, VPS, notities) worden <strong>niet overschreven</strong>.
    </p>
    <form method="post">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary px-4">
            <i class="ri-refresh-line me-1"></i> Nu synchroniseren
        </button>
    </form>
</div>

<?php if ($uitgevoerd && !$fout): ?>
<div class="bg-white rounded-3 border p-4" style="max-width:600px;">
    <h6 class="fw-bold mb-3">Resultaat</h6>
    <div class="list-group list-group-flush">
        <?php foreach ($resultaten as $r): ?>
        <div class="list-group-item px-0 py-1 border-0 d-flex align-items-center gap-2">
            <?php if ($r['actie'] === 'nieuw'): ?>
                <span class="badge bg-success" style="font-size:10px;">NIEUW</span>
            <?php else: ?>
                <span class="badge bg-secondary" style="font-size:10px;">BIJGEWERKT</span>
            <?php endif; ?>
            <span class="small"><?= h($r['naam']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
