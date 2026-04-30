<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();

$db = db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$klant = $db->prepare('SELECT * FROM klanten WHERE id = ?');
$klant->execute([$id]);
$klant = $klant->fetch();
if (!$klant) { flash_set('fout', 'Klant niet gevonden.'); header('Location: index.php'); exit; }

$paginatitel = h($klant['naam']);

// Subtabellen ophalen
$contacten = $db->prepare('SELECT * FROM contactpersonen WHERE klant_id = ? ORDER BY naam');
$contacten->execute([$id]);
$contacten = $contacten->fetchAll();

$apparaten = $db->prepare('SELECT * FROM apparaten WHERE klant_id = ? ORDER BY id DESC');
$apparaten->execute([$id]);
$apparaten = $apparaten->fetchAll();

$inloggegevens = $db->prepare("SELECT * FROM inloggegevens WHERE klant_id = ? ORDER BY categorie, label");
$inloggegevens->execute([$id]);
$inloggegevens = $inloggegevens->fetchAll();

$notities = $db->prepare('SELECT * FROM klant_notities WHERE klant_id = ? ORDER BY bijgewerkt_op DESC');
$notities->execute([$id]);
$notities = $notities->fetchAll();

$service = $db->prepare("SELECT s.* FROM service_historie s WHERE s.klant_id = ? ORDER BY s.datum DESC, s.id DESC");
$service->execute([$id]);
$service = $service->fetchAll();

$bestanden = $db->prepare('SELECT * FROM klant_bestanden WHERE klant_id = ? ORDER BY aangemaakt_op DESC');
$bestanden->execute([$id]);
$bestanden = $bestanden->fetchAll();

$contracten = $db->prepare('SELECT * FROM contracten WHERE klant_id = ? ORDER BY eind_datum ASC');
$contracten->execute([$id]);
$contracten = $contracten->fetchAll();

// Office 365
$o365 = $db->prepare('SELECT * FROM klant_o365 WHERE klant_id = ?');
$o365->execute([$id]);
$o365 = $o365->fetch() ?: null;

$o365_licenties = [];
$o365_gebruikers = [];
try {
    $q = $db->prepare('SELECT * FROM klant_o365_licenties WHERE klant_id = ? ORDER BY id');
    $q->execute([$id]);
    $o365_licenties = $q->fetchAll();
    $q = $db->prepare('SELECT * FROM klant_o365_gebruikers WHERE klant_id = ? ORDER BY naam');
    $q->execute([$id]);
    $o365_gebruikers = $q->fetchAll();
} catch (Exception $e) { /* tabellen nog niet aangemaakt */ }

// Yeastar centralen
$yeastar_centralen = $db->prepare('SELECT * FROM klant_yeastar WHERE klant_id = ? ORDER BY id DESC');
$yeastar_centralen->execute([$id]);
$yeastar_centralen = $yeastar_centralen->fetchAll();

// Simpbx
$simpbx = $db->prepare('SELECT * FROM klant_simpbx WHERE klant_id = ?');
$simpbx->execute([$id]);
$simpbx = $simpbx->fetch() ?: null;

// Ziggo
$ziggo = $db->prepare('SELECT * FROM klant_ziggo WHERE klant_id = ?');
$ziggo->execute([$id]);
$ziggo = $ziggo->fetch() ?: null;

// RoutIT
try {
    $routit = $db->prepare('SELECT * FROM klant_routit WHERE klant_id = ?');
    $routit->execute([$id]);
    $routit = $routit->fetch() ?: null;
} catch (PDOException $e) {
    // Tabel bestaat nog niet — migrate_routit.php nog niet uitgevoerd
    $routit = null;
}

// Internet verbindingen (kan meerdere zijn). Primair eerst, dan oudste eerst.
try {
    $st = $db->prepare('SELECT * FROM klant_internet WHERE klant_id = ? ORDER BY is_primair DESC, id ASC');
    $st->execute([$id]);
    $internets = $st->fetchAll() ?: [];
} catch (PDOException $e) {
    // migrate_internet_meervoudig nog niet uitgevoerd? Fallback op oude kolomstructuur
    $st = $db->prepare('SELECT * FROM klant_internet WHERE klant_id = ?');
    $st->execute([$id]);
    $internets = $st->fetchAll() ?: [];
}

// PPPoE wachtwoorden per verbinding decrypten (voor weergave + invul-veld in modal)
$pppoe_wachtwoorden = [];
foreach ($internets as $i => $rij) {
    $pppoe_wachtwoorden[$rij['id'] ?? $i] = !empty($rij['pppoe_wachtwoord_enc'] ?? null)
        ? decrypt_wachtwoord($rij['pppoe_wachtwoord_enc']) : '';
}

// Backward-compat: $internet = primaire (of eerste) verbinding voor overzicht-card
$internet = $internets[0] ?? null;
$pppoe_wachtwoord = $internet ? ($pppoe_wachtwoorden[$internet['id'] ?? 0] ?? '') : '';

// Virusscanner
try {
    $virusscanner = $db->prepare('SELECT * FROM klant_virusscanner WHERE klant_id = ?');
    $virusscanner->execute([$id]);
    $virusscanner = $virusscanner->fetch() ?: null;
} catch (PDOException $e) {
    $virusscanner = null; // tabel nog niet gemigreerd
}

$actieve_tab = $_GET['tab'] ?? 'overzicht';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Broodkruimel -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Klanten</a></li>
        <li class="breadcrumb-item active"><?= h($klant['naam']) ?></li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><?= h($klant['naam']) ?></h4>
        <?php if (!empty($klant['bedrijf'])): ?>
        <p class="text-muted mb-0"><i class="ri-building-line"></i> <?= h($klant['bedrijf']) ?></p>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <?php if (!empty($klant['acs_network_id']) || !empty($klant['acs_device_naam'])): ?>
            <button type="button"
                    class="btn btn-outline-danger btn-sm"
                    onclick="openAcs(<?= htmlspecialchars(json_encode($klant['acs_device_naam'] ?? ''), ENT_QUOTES) ?>, this)"
                    title="Open DrayTek VigorACS (device-naam wordt gekopieerd naar klembord — plak in zoekbalk)">
                <i class="ri-router-line"></i> DrayTek ACS
            </button>
        <?php endif; ?>
        <a href="<?= $base ?>/qr/label_klant.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm" target="_blank" title="QR-label afdrukken">
            <i class="ri-qr-code-line"></i> QR
        </a>
        <button class="btn btn-outline-secondary btn-sm" onclick="openBewerkKlant()">
            <i class="ri-edit-line"></i> Bewerken
        </button>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $actieve_tab === 'overzicht' ? 'active' : '' ?>" href="?id=<?= $id ?>&tab=overzicht">
            <i class="ri-information-line"></i> Overzicht
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $actieve_tab === 'contacten' ? 'active' : '' ?>" href="?id=<?= $id ?>&tab=contacten">
            <i class="ri-contacts-line"></i> Contacten
            <?php if (count($contacten)): ?><span class="badge bg-secondary ms-1" style="font-size:10px;"><?= count($contacten) ?></span><?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $actieve_tab === 'apparaten' ? 'active' : '' ?>" href="?id=<?= $id ?>&tab=apparaten">
            <i class="ri-computer-line"></i> Apparaten
            <?php if (count($apparaten)): ?><span class="badge bg-secondary ms-1" style="font-size:10px;"><?= count($apparaten) ?></span><?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $actieve_tab === 'wachtwoorden' ? 'active' : '' ?>" href="?id=<?= $id ?>&tab=wachtwoorden">
            <i class="ri-key-2-line"></i> Wachtwoorden
            <?php if (count($inloggegevens)): ?><span class="badge bg-secondary ms-1" style="font-size:10px;"><?= count($inloggegevens) ?></span><?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $actieve_tab === 'notities' ? 'active' : '' ?>" href="?id=<?= $id ?>&tab=notities">
            <i class="ri-sticky-note-line"></i> Notities
            <?php if (count($notities)): ?><span class="badge bg-secondary ms-1" style="font-size:10px;"><?= count($notities) ?></span><?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $actieve_tab === 'service' ? 'active' : '' ?>" href="?id=<?= $id ?>&tab=service">
            <i class="ri-tools-line"></i> Service
            <?php if (count($service)): ?><span class="badge bg-secondary ms-1" style="font-size:10px;"><?= count($service) ?></span><?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $actieve_tab === 'bestanden' ? 'active' : '' ?>" href="?id=<?= $id ?>&tab=bestanden">
            <i class="ri-folder-line"></i> Bestanden
            <?php if (count($bestanden)): ?><span class="badge bg-secondary ms-1" style="font-size:10px;"><?= count($bestanden) ?></span><?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $actieve_tab === 'contract' ? 'active' : '' ?>" href="?id=<?= $id ?>&tab=contract">
            <i class="ri-file-shield-2-line"></i> Contract
            <?php if (count($contracten)): ?><span class="badge bg-secondary ms-1" style="font-size:10px;"><?= count($contracten) ?></span><?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $actieve_tab === 'o365' ? 'active' : '' ?>" href="?id=<?= $id ?>&tab=o365">
            <i class="ri-microsoft-line"></i> Office 365
            <?php if ($o365): ?><span class="badge bg-success ms-1" style="font-size:10px;">&#10003;</span><?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <?php $heeft_telefonie = !empty($yeastar_centralen) || !empty($simpbx['actief']) || !empty($ziggo['actief']) || !empty($routit['actief']); ?>
        <a class="nav-link <?= $actieve_tab === 'telefonie' ? 'active' : '' ?>" href="?id=<?= $id ?>&tab=telefonie">
            <i class="ri-phone-line"></i> Telefonie
            <?php if ($heeft_telefonie): ?><span class="badge bg-success ms-1" style="font-size:10px;">&#10003;</span><?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $actieve_tab === 'internet' ? 'active' : '' ?>" href="?id=<?= $id ?>&tab=internet">
            <i class="ri-wifi-line"></i> Internet
            <?php if (!empty($internets)): ?><span class="badge bg-success ms-1" style="font-size:10px;"><?= count($internets) > 1 ? count($internets) : '&#10003;' ?></span><?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <?php $heeft_vs = $virusscanner && ($virusscanner['scanner'] ?? 'geen') !== 'geen'; ?>
        <a class="nav-link <?= $actieve_tab === 'virusscanner' ? 'active' : '' ?>" href="?id=<?= $id ?>&tab=virusscanner">
            <i class="ri-shield-check-line"></i> Virusscanner
            <?php if ($heeft_vs): ?><span class="badge bg-success ms-1" style="font-size:10px;">&#10003;</span><?php endif; ?>
        </a>
    </li>
</ul>

<!-- ─── Tab: Overzicht ────────────────────────────────────────────────────── -->
<?php
// Vaste kleuren per beheerder [achtergrond, tekst/icoon]
$beheerder_kleuren = [
    'Connect4IT'      => ['#fff4f0', '#e8621a'],
    'Lars Manders'    => ['#f0f7ff', '#185E9B'],
    'Frank Lendering' => ['#f0fff4', '#198754'],
    'Bitcom'          => ['#fff8e1', '#e6a817'],
    'Kirkels'         => ['#fdf0ff', '#9c27b0'],
    'Academy'         => ['#e8f5e9', '#2e7d32'],
];
function beheerder_kleur($naam, $kleuren) {
    if (isset($kleuren[$naam])) return $kleuren[$naam];
    // Vrije tekst: genereer kleur op basis van hash
    $hash = crc32($naam);
    $hue  = abs($hash) % 360;
    return ["hsl($hue,60%,94%)", "hsl($hue,55%,35%)"];
}
?>
<?php if ($actieve_tab === 'overzicht'): ?>
<div class="row g-3">
    <!-- Contactinformatie -->
    <div class="col-md-6">
        <div class="bg-white rounded-3 border p-4">
            <h6 class="fw-bold mb-3">Contactinformatie</h6>
            <table class="table table-sm table-borderless mb-0">
                <?php if (!empty($klant['intra_id'])): ?>
                <tr><td class="text-muted" style="width:40%">Intelly ID</td><td><?= h($klant['intra_id']) ?></td></tr>
                <?php endif; ?>
                <?php if (!empty($klant['intranet_id'])): ?>
                <tr><td class="text-muted" style="width:40%">Intranet ID</td><td><?= h($klant['intranet_id']) ?></td></tr>
                <?php endif; ?>
                <tr><td class="text-muted" style="width:40%">E-mail</td><td><?= h($klant['email'] ?: '—') ?></td></tr>
                <tr><td class="text-muted">Telefoon</td><td><?= h($klant['telefoon'] ?: '—') ?></td></tr>
                <tr><td class="text-muted">Adres</td><td>
                    <?= h($klant['adres'] ?: '—') ?><br>
                    <?= h($klant['postcode'] . ' ' . $klant['stad']) ?>
                </td></tr>
                <?php if (!empty($klant['website'])): ?>
                <tr><td class="text-muted">Website</td><td><a href="<?= h($klant['website']) ?>" target="_blank"><?= h($klant['website']) ?></a></td></tr>
                <?php endif; ?>
            </table>
        </div>
        <?php if (!empty($klant['notities'])): ?>
        <div class="bg-white rounded-3 border p-4 mt-3">
            <h6 class="fw-bold mb-2">Notities</h6>
            <p class="mb-0 text-muted small" style="white-space:pre-line;"><?= h($klant['notities']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Samenvatting + VPS/Beheerder -->
    <div class="col-md-6">
        <div class="bg-white rounded-3 border p-4 h-100">
            <?php if (!empty($klant['beheerder']) || !empty($klant['vps'])): ?>
            <div class="d-flex gap-3 flex-wrap mb-4">
                <?php if (!empty($klant['beheerder'])):
                    [$bg, $fg] = beheerder_kleur($klant['beheerder'], $beheerder_kleuren); ?>
                <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3 flex-fill" style="background:<?= $bg ?>;min-width:120px;">
                    <i class="ri-user-settings-line" style="color:<?= $fg ?>;font-size:20px;flex-shrink:0;"></i>
                    <div>
                        <div style="font-size:10px;color:#6c757d;text-transform:uppercase;letter-spacing:.5px;">Beheerder</div>
                        <div class="fw-bold" style="font-size:13px;color:<?= $fg ?>;"><?= h($klant['beheerder']) ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($klant['vps'])): ?>
                <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3 flex-fill" style="background:#f0f7ff;min-width:120px;">
                    <i class="ri-server-line" style="color:#185E9B;font-size:20px;flex-shrink:0;"></i>
                    <div>
                        <div style="font-size:10px;color:#6c757d;text-transform:uppercase;letter-spacing:.5px;">VPS</div>
                        <div class="fw-bold" style="font-size:13px;color:#185E9B;"><?= h($klant['vps']) ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <hr class="my-0 mb-3">
            <?php endif; ?>
            <h6 class="fw-bold mb-3">Samenvatting</h6>
            <div class="d-flex flex-column gap-2">
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Contactpersonen</span><strong><?= count($contacten) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Apparaten</span><strong><?= count($apparaten) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Wachtwoorden</span><strong><?= count($inloggegevens) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Notities</span><strong><?= count($notities) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Microsoft 365 -->
    <?php if ($o365): ?>
    <div class="col-md-4">
        <div class="bg-white rounded-3 border p-4 h-100">
            <h6 class="fw-bold mb-3"><i class="ri-microsoft-line me-1 text-primary"></i> Microsoft 365</h6>
            <?php if (!empty($o365['tenant_naam'])): ?>
            <div class="text-muted small mb-2"><?= h($o365['tenant_naam']) ?></div>
            <?php endif; ?>
            <?php
            $lic_overzicht = [];
            foreach ($o365_gebruikers as $gu) {
                if (!empty($gu['licentie_type'])) {
                    $lic_overzicht[$gu['licentie_type']] = ($lic_overzicht[$gu['licentie_type']] ?? 0) + 1;
                }
            }
            ?>
            <?php if ($lic_overzicht): ?>
            <div class="d-flex flex-column gap-1">
                <?php foreach ($lic_overzicht as $type => $aantal): ?>
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-size:12px;"><?= h($type) ?></span>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><?= $aantal ?>x</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <span class="text-muted small">Geen licenties ingesteld</span>
            <?php endif; ?>
            <div class="mt-2 small text-muted"><?= count($o365_gebruikers) ?> gebruiker<?= count($o365_gebruikers) !== 1 ? 's' : '' ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Telefonie -->
    <?php
    $ys_overzicht = !empty($yeastar_centralen) ? $yeastar_centralen[0] : null;
    $heeft_tel_overzicht = $ys_overzicht || !empty($simpbx['actief']) || !empty($ziggo['actief']) || !empty($routit['actief']);
    if ($heeft_tel_overzicht):
    ?>
    <div class="col-md-4">
        <div class="bg-white rounded-3 border p-4 h-100">
            <h6 class="fw-bold mb-3"><i class="ri-phone-line me-1 text-success"></i> Telefonie</h6>
            <div class="d-flex flex-column gap-2">
                <?php if ($ys_overzicht): ?>
                <div class="d-flex align-items-center gap-2">
                    <img src="https://www.mister-voip.nl/wp-content/uploads/2025/02/Yeastar_Symbol.png" height="20" alt="Yeastar" style="object-fit:contain;">
                    <span class="fw-medium small">Yeastar</span>
                </div>
                <?php endif; ?>
                <?php if (!empty($simpbx['actief'])): ?>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-medium small" style="color:#2563eb;">SimPBX</span>
                </div>
                <?php endif; ?>
                <?php if (!empty($ziggo['actief'])): ?>
                <div class="d-flex align-items-center gap-2">
                    <img src="https://vodafoneziggo.scene7.com/is/content/vodafoneziggo/ziggo-logo-orange-v1" height="16" alt="Ziggo" style="object-fit:contain;">
                </div>
                <?php endif; ?>
                <?php if (!empty($routit['actief'])): ?>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-medium small" style="color:#003a70;">RoutIT</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Internet -->
    <?php if (!empty($internets)): ?>
    <div class="col-md-4">
        <div class="bg-white rounded-3 border p-4 h-100">
            <h6 class="fw-bold mb-3">
                <i class="ri-wifi-line me-1 text-info"></i> Internet
                <?php if (count($internets) > 1): ?>
                <span class="badge bg-secondary ms-1" style="font-size:10px;"><?= count($internets) ?> verbindingen</span>
                <?php endif; ?>
            </h6>
            <?php foreach ($internets as $iv_idx => $iv): ?>
            <?php if ($iv_idx > 0): ?><hr class="my-2"><?php endif; ?>
            <?php
                $iv_provider_label = $iv['provider'] === 'Anders' ? ($iv['provider_anders'] ?: 'Onbekend') : $iv['provider'];
                $iv_titel = !empty($iv['omschrijving']) ? $iv['omschrijving'] : $iv_provider_label;
            ?>
            <div class="fw-medium mb-1">
                <?= h($iv_titel) ?>
                <?php if (!empty($iv['is_primair']) && count($internets) > 1): ?>
                <span class="badge bg-primary ms-1" style="font-size:9px;">Primair</span>
                <?php endif; ?>
            </div>
            <div class="d-flex flex-column gap-1">
                <?php if (!empty($iv['omschrijving'])): ?>
                <span class="text-muted small"><?= h($iv_provider_label) ?></span>
                <?php endif; ?>
                <?php if (!empty($iv['type'])): ?>
                <span class="text-muted small"><?= h($iv['type']) ?></span>
                <?php endif; ?>
                <?php if (!empty($iv['snelheid_down'])): ?>
                <span class="text-muted small"><?= h($iv['snelheid_down']) ?> / <?= h($iv['snelheid_up']) ?> Mbit</span>
                <?php endif; ?>
                <?php if (!empty($iv['ip_adres'])): ?>
                <span class="text-muted small"><code style="font-size:11px;"><?= h($iv['ip_adres']) ?></code></span>
                <?php endif; ?>
                <?php if (!empty($iv['backup_4g'])): ?>
                <span class="badge bg-warning text-dark mt-1" style="width:fit-content;font-size:10px;"><i class="ri-signal-tower-line me-1"></i>4G backup</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ─── Tab: Contacten ────────────────────────────────────────────────────── -->
<?php elseif ($actieve_tab === 'contacten'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0">Contactpersonen</h6>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalContact">+ Toevoegen</button>
</div>
<?php if (empty($contacten)): ?>
    <div class="bg-white rounded-3 border p-4 text-center text-muted">Nog geen contactpersonen.</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($contacten as $c): ?>
    <div class="col-md-6">
        <div class="bg-white rounded-3 border p-3 d-flex justify-content-between align-items-start gap-2">
            <div>
                <div class="fw-medium"><?= h($c['naam']) ?></div>
                <?php if (!empty($c['functie'])): ?><div class="text-muted small"><?= h($c['functie']) ?></div><?php endif; ?>
                <?php if (!empty($c['email'])): ?><div class="small"><i class="ri-mail-line text-muted"></i> <?= h($c['email']) ?></div><?php endif; ?>
                <?php if (!empty($c['telefoon'])): ?><div class="small"><i class="ri-phone-line text-muted"></i> <?= h($c['telefoon']) ?></div><?php endif; ?>
                <?php if (!empty($c['notities'])): ?><div class="small text-muted mt-1"><?= h($c['notities']) ?></div><?php endif; ?>
            </div>
            <div class="d-flex gap-1 flex-shrink-0">
                <button class="btn btn-sm btn-outline-secondary"
                        onclick="bewerkContact(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)">
                    <i class="ri-edit-line"></i>
                </button>
                <?php if ($gebruiker['rol'] === 'admin'): ?>
                <a href="<?= $base ?>/contactpersonen/verwijderen.php?id=<?= $c['id'] ?>&klant_id=<?= $id ?>"
                   class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Contactpersoon verwijderen?')"><i class="ri-delete-bin-line"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal Contact -->
<div class="modal fade" id="modalContact" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="contactModalTitel">Contactpersoon toevoegen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" action="<?= $base ?>/contactpersonen/opslaan.php" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="klant_id" value="<?= $id ?>">
                    <input type="hidden" name="contact_id" id="contact_id" value="">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-medium">Naam <span class="text-danger">*</span></label>
                            <input type="text" name="naam" id="c_naam" class="form-control rounded-3" required>
                            <div class="invalid-feedback">Vul een naam in.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Functie</label>
                            <input type="text" name="functie" id="c_functie" class="form-control rounded-3" placeholder="Bijv. directeur, beheerder">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">E-mail</label>
                            <input type="email" name="email" id="c_email" class="form-control rounded-3">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Telefoon</label>
                            <input type="text" name="telefoon" id="c_telefoon" class="form-control rounded-3">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Notities</label>
                            <textarea name="notities" id="c_notities" class="form-control rounded-3" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 rounded-3" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-primary flex-grow-1 rounded-3">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ─── Tab: Apparaten ────────────────────────────────────────────────────── -->
<?php elseif ($actieve_tab === 'apparaten'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0">Apparaten</h6>
    <div class="d-flex gap-2">
        <?php if (!empty($apparaten)): ?>
        <a href="<?= $base ?>/qr/labels_apparaten.php?klant_id=<?= $id ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
            <i class="ri-printer-line"></i> Alle QR labels
        </a>
        <?php endif; ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalApparaat">+ Toevoegen</button>
    </div>
</div>
<?php if (empty($apparaten)): ?>
    <div class="bg-white rounded-3 border p-4 text-center text-muted">Nog geen apparaten.</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($apparaten as $a): ?>
    <div class="col-md-6">
        <div class="bg-white rounded-3 border p-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <?php if (!empty($a['qr_code'])): ?>
                    <span class="text-muted small fw-medium"><?= h($a['qr_code']) ?></span>
                    <?php endif; ?>
                    <span class="badge badge-<?= $a['status'] ?> rounded-pill ms-1" style="font-size:10px;"><?= h($a['status']) ?></span>
                </div>
                <div class="d-flex gap-1">
                    <?php if (!empty($a['qr_code'])): ?>
                    <a href="<?= $base ?>/qr/label_apparaat.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="QR-label">
                        <i class="ri-qr-code-line"></i>
                    </a>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-secondary" onclick="bewerkApparaat(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)">
                        <i class="ri-edit-line"></i>
                    </button>
                    <?php if ($gebruiker['rol'] === 'admin'): ?>
                    <a href="<?= $base ?>/apparaten/verwijderen.php?id=<?= $a['id'] ?>&klant_id=<?= $id ?>"
                       class="btn btn-sm btn-outline-danger"
                       onclick="return confirm('Apparaat verwijderen?')"><i class="ri-delete-bin-line"></i></a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-muted small mb-1">
                <strong><?= h(ucfirst($a['type'])) ?></strong>
                <?= !empty($a['merk']) ? ' · ' . h($a['merk']) : '' ?>
                <?= !empty($a['model']) ? ' ' . h($a['model']) : '' ?>
            </div>
            <?php if (!empty($a['serienummer'])): ?>
            <div class="small text-muted"><i class="ri-barcode-line"></i> <?= h($a['serienummer']) ?></div>
            <?php endif; ?>
            <?php if (!empty($a['locatie'])): ?>
            <div class="small text-muted"><i class="ri-map-pin-line"></i> <?= h($a['locatie']) ?></div>
            <?php endif; ?>
            <?php
            $a_wifi_ww = !empty($a['wifi_wachtwoord_enc'] ?? null) ? decrypt_wachtwoord($a['wifi_wachtwoord_enc']) : '';
            $a_gast_ww = !empty($a['gast_wachtwoord_enc'] ?? null) ? decrypt_wachtwoord($a['gast_wachtwoord_enc']) : '';
            $heeft_a_wifi = !empty($a['wifi_ssid'] ?? null) || $a_wifi_ww !== '' || !empty($a['gast_ssid'] ?? null) || $a_gast_ww !== '';
            if ($heeft_a_wifi):
            ?>
            <div class="mt-2 pt-2 border-top">
                <?php if (!empty($a['wifi_ssid'] ?? null) || $a_wifi_ww !== ''): ?>
                <div class="small d-flex align-items-center gap-2 flex-wrap">
                    <i class="ri-wifi-line text-info"></i>
                    <?php if (!empty($a['wifi_ssid'] ?? null)): ?>
                    <code style="font-size:11px;"><?= h($a['wifi_ssid']) ?></code>
                    <?php endif; ?>
                    <?php if ($a_wifi_ww !== ''): ?>
                    <code id="ap_wifi_<?= $a['id'] ?>" style="font-size:11px;">••••••••</code>
                    <button type="button" class="btn btn-sm btn-link p-0" onclick="toonVsGeheim('ap_wifi_<?= $a['id'] ?>', <?= htmlspecialchars(json_encode($a_wifi_ww), ENT_QUOTES) ?>, this)" title="Tonen"><i class="ri-eye-line" style="font-size:13px;"></i></button>
                    <button type="button" class="btn btn-sm btn-link p-0" onclick="kopieer(<?= htmlspecialchars(json_encode($a_wifi_ww), ENT_QUOTES) ?>, this)" title="Kopiëren"><i class="ri-file-copy-line" style="font-size:13px;"></i></button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($a['gast_ssid'] ?? null) || $a_gast_ww !== ''): ?>
                <div class="small d-flex align-items-center gap-2 flex-wrap mt-1">
                    <i class="ri-wifi-line text-warning"></i>
                    <span class="text-muted" style="font-size:11px;">Gast:</span>
                    <?php if (!empty($a['gast_ssid'] ?? null)): ?>
                    <code style="font-size:11px;"><?= h($a['gast_ssid']) ?></code>
                    <?php endif; ?>
                    <?php if ($a_gast_ww !== ''): ?>
                    <code id="ap_gast_<?= $a['id'] ?>" style="font-size:11px;">••••••••</code>
                    <button type="button" class="btn btn-sm btn-link p-0" onclick="toonVsGeheim('ap_gast_<?= $a['id'] ?>', <?= htmlspecialchars(json_encode($a_gast_ww), ENT_QUOTES) ?>, this)" title="Tonen"><i class="ri-eye-line" style="font-size:13px;"></i></button>
                    <button type="button" class="btn btn-sm btn-link p-0" onclick="kopieer(<?= htmlspecialchars(json_encode($a_gast_ww), ENT_QUOTES) ?>, this)" title="Kopiëren"><i class="ri-file-copy-line" style="font-size:13px;"></i></button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal Apparaat -->
<div class="modal fade" id="modalApparaat" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="apparaatModalTitel">Apparaat toevoegen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" action="<?= $base ?>/apparaten/opslaan.php" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="klant_id" value="<?= $id ?>">
                    <input type="hidden" name="apparaat_id" id="apparaat_id" value="">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Type <span class="text-danger">*</span></label>
                            <select name="type" id="a_type" class="form-select rounded-3" required>
                                <option value="">Selecteer type...</option>
                                <option value="desktop">Desktop</option>
                                <option value="laptop">Laptop</option>
                                <option value="server">Server</option>
                                <option value="nuc">NUC</option>
                                <option value="printer">Printer</option>
                                <option value="netwerk">Netwerkapparaat</option>
                                <option value="overig">Overig</option>
                            </select>
                            <div class="invalid-feedback">Selecteer een type.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Status</label>
                            <select name="status" id="a_status" class="form-select rounded-3">
                                <option value="actief">Actief</option>
                                <option value="defect">Defect</option>
                                <option value="retour">Retour</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Merk</label>
                            <input type="text" name="merk" id="a_merk" class="form-control rounded-3" placeholder="Bijv. HP, Dell, Lenovo">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Model</label>
                            <input type="text" name="model" id="a_model" class="form-control rounded-3" placeholder="Bijv. EliteBook 840">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Serienummer (fabrikant)</label>
                            <input type="text" name="serienummer" id="a_serienummer" class="form-control rounded-3" placeholder="Serienummer van het apparaat">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Aanschafdatum</label>
                            <input type="date" name="aanschafdatum" id="a_aanschafdatum" class="form-control rounded-3">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Locatie bij klant</label>
                            <input type="text" name="locatie" id="a_locatie" class="form-control rounded-3" placeholder="Bijv. Serverruimte, Werkplek Jan">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">MAC-adres</label>
                            <input type="text" name="mac_adres" id="a_mac_adres" class="form-control rounded-3" placeholder="AA:BB:CC:DD:EE:FF">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">IP-adres</label>
                            <input type="text" name="ip_adres" id="a_ip_adres" class="form-control rounded-3" placeholder="192.168.1.100">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Firmware / Versie</label>
                            <input type="text" name="firmware" id="a_firmware" class="form-control rounded-3" placeholder="Bijv. v2.3.1">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Notities</label>
                            <textarea name="notities" id="a_notities" class="form-control rounded-3" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <hr class="my-1">
                            <div class="text-muted small fw-medium mb-2"><i class="ri-wifi-line me-1"></i>WiFi <span class="text-muted fw-normal">(invullen voor routers / access points)</span></div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Netwerknaam (SSID)</label>
                            <input type="text" name="wifi_ssid" id="a_wifi_ssid" class="form-control rounded-3" placeholder="Bijv. Connect4IT">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Wachtwoord</label>
                            <div class="input-group">
                                <input type="password" name="wifi_wachtwoord" id="a_wifi_ww" class="form-control rounded-start-3" placeholder="" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleVeld('a_wifi_ww', this)"><i class="ri-eye-line"></i></button>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Gast-SSID</label>
                            <input type="text" name="gast_ssid" id="a_gast_ssid" class="form-control rounded-3" placeholder="Bijv. Connect4IT-Gast">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Gast-wachtwoord</label>
                            <div class="input-group">
                                <input type="password" name="gast_wachtwoord" id="a_gast_ww" class="form-control rounded-start-3" placeholder="" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleVeld('a_gast_ww', this)"><i class="ri-eye-line"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 rounded-3" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-primary flex-grow-1 rounded-3">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ─── Tab: Wachtwoorden ─────────────────────────────────────────────────── -->
<?php elseif ($actieve_tab === 'wachtwoorden'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0">Wachtwoordkluis</h6>
    <button class="btn btn-primary btn-sm" onclick="nieuwWachtwoord()">+ Toevoegen</button>
</div>
<?php if (empty($inloggegevens)): ?>
    <div class="bg-white rounded-3 border p-4 text-center text-muted">Nog geen inloggegevens.</div>
<?php else:
    $categorieen = [];
    foreach ($inloggegevens as $ig) {
        $cat = $ig['categorie'] ?: 'overig';
        $categorieen[$cat][] = $ig;
    }
    $cat_labels = ['router' => 'Router', 'netwerk' => 'Netwerk / Router', 'server' => 'Server / Windows', 'cloud' => 'Cloud / SaaS', 'portaal' => 'Portalen', 'overig' => 'Overig'];
    foreach ($categorieen as $cat => $items):
        if (empty($items)) continue;
?>
<div class="mb-4">
    <h6 class="text-muted fw-semibold mb-2" style="font-size:12px;text-transform:uppercase;letter-spacing:.8px;"><?= h($cat_labels[$cat] ?? ucfirst($cat)) ?></h6>
    <div class="row g-2">
        <?php foreach ($items as $ig): ?>
        <div class="col-12 col-md-6">
            <div class="bg-white rounded-3 border p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="fw-medium"><?= h($ig['label']) ?></span>
                        <span class="badge badge-<?= $ig['categorie'] ?> ms-2 rounded-pill" style="font-size:10px;"><?= $ig['categorie'] ?></span>
                    </div>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-secondary" onclick="bewerkWachtwoord(<?= htmlspecialchars(json_encode($ig), ENT_QUOTES) ?>)">
                            <i class="ri-edit-line"></i>
                        </button>
                        <?php if ($gebruiker['rol'] === 'admin'): ?>
                        <a href="<?= $base ?>/inloggegevens/verwijderen.php?id=<?= $ig['id'] ?>&klant_id=<?= $id ?>"
                           class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Inloggegevens verwijderen?')"><i class="ri-delete-bin-line"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($ig['gebruikersnaam'])): ?>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="text-muted" style="font-size:12px;min-width:90px;">Gebruikersnaam</span>
                    <code class="flex-grow-1" style="font-size:12px;"><?= h($ig['gebruikersnaam']) ?></code>
                    <button class="btn btn-sm p-0 text-muted" title="Kopiëren" onclick="kopieer('<?= h($ig['gebruikersnaam']) ?>', this)">
                        <i class="ri-file-copy-line" style="font-size:14px;"></i>
                    </button>
                </div>
                <?php endif; ?>
                <?php if (!empty($ig['wachtwoord_enc'])): ?>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="text-muted" style="font-size:12px;min-width:90px;">Wachtwoord</span>
                    <code class="flex-grow-1 ww-tekst" data-id="<?= $ig['id'] ?>" style="font-size:12px;">••••••••</code>
                    <button class="btn btn-sm p-0 text-muted" title="Tonen" onclick="toggleWachtwoord(<?= $ig['id'] ?>, this)">
                        <i class="ri-eye-line" style="font-size:14px;"></i>
                    </button>
                    <button class="btn btn-sm p-0 text-muted ww-kopieer" data-id="<?= $ig['id'] ?>" title="Kopiëren" onclick="kopieerWachtwoord(<?= $ig['id'] ?>, this)">
                        <i class="ri-file-copy-line" style="font-size:14px;"></i>
                    </button>
                </div>
                <?php endif; ?>
                <?php if (!empty($ig['url'])): ?>
                <div class="small text-muted">
                    <i class="ri-link"></i> <a href="<?= h($ig['url']) ?>" target="_blank"><?= h($ig['url']) ?></a>
                </div>
                <?php endif; ?>
                <?php if (!empty($ig['notities'])): ?>
                <div class="small text-muted mt-1" style="white-space:pre-line;"><?= h($ig['notities']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; endif; ?>

<!-- Modal Wachtwoord -->
<div class="modal fade" id="modalWachtwoord" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="wwModalTitel">Inloggegevens toevoegen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" action="<?= $base ?>/inloggegevens/opslaan.php" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="klant_id" value="<?= $id ?>">
                    <input type="hidden" name="ig_id" id="ig_id" value="">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-medium">Label / Naam <span class="text-danger">*</span></label>
                            <input type="text" name="label" id="ww_label" class="form-control rounded-3" placeholder="Bijv. WiFi kantoor, Router admin, M365 admin" required>
                            <div class="invalid-feedback">Vul een label in.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Categorie</label>
                            <select name="categorie" id="ww_categorie" class="form-select rounded-3" onchange="toggleWwCategorie()">
                                <option value="router">Router</option>
                                <option value="anders">Zelf invullen...</option>
                            </select>
                        </div>
                        <div class="col-12" id="ww_categorie_anders_veld" style="display:none;">
                            <label class="form-label fw-medium">Omschrijving</label>
                            <input type="text" name="categorie_anders" id="ww_categorie_anders" class="form-control rounded-3" placeholder="bijv. Firewall, Switch, NAS...">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Gebruikersnaam</label>
                            <input type="text" name="gebruikersnaam" id="ww_gebruikersnaam" class="form-control rounded-3" autocomplete="off">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Wachtwoord</label>
                            <div class="input-group">
                                <input type="password" name="wachtwoord" id="ww_wachtwoord" class="form-control rounded-start-3" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleWWVeld()" tabindex="-1">
                                    <i class="ri-eye-line" id="ww_oog"></i>
                                </button>
                            </div>
                            <div class="form-text">Leeg laten om bestaand wachtwoord te behouden bij bewerken.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">URL</label>
                            <input type="text" name="url" id="ww_url" class="form-control rounded-3" placeholder="https://...">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Notities</label>
                            <textarea name="notities" id="ww_notities" class="form-control rounded-3" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 rounded-3" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-primary flex-grow-1 rounded-3">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ─── Tab: Notities ─────────────────────────────────────────────────────── -->
<?php elseif ($actieve_tab === 'notities'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0">Notities & Documentatie</h6>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNotitie">+ Toevoegen</button>
</div>
<?php if (empty($notities)): ?>
    <div class="bg-white rounded-3 border p-4 text-center text-muted">Nog geen notities.</div>
<?php else: ?>
<div class="d-flex flex-column gap-3">
    <?php foreach ($notities as $n): ?>
    <div class="bg-white rounded-3 border p-4">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <h6 class="fw-bold mb-0"><?= h($n['titel']) ?></h6>
            <div class="d-flex gap-1 align-items-center">
                <span class="text-muted" style="font-size:11px;"><?= h(date('d-m-Y', strtotime($n['bijgewerkt_op']))) ?></span>
                <button class="btn btn-sm btn-outline-secondary" onclick="bewerkNotitie(<?= htmlspecialchars(json_encode($n), ENT_QUOTES) ?>)">
                    <i class="ri-edit-line"></i>
                </button>
                <?php if ($gebruiker['rol'] === 'admin'): ?>
                <a href="<?= $base ?>/notities/verwijderen.php?id=<?= $n['id'] ?>&klant_id=<?= $id ?>"
                   class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Notitie verwijderen?')"><i class="ri-delete-bin-line"></i></a>
                <?php endif; ?>
            </div>
        </div>
        <p class="mb-0 text-muted" style="white-space:pre-line;font-size:14px;"><?= h($n['inhoud'] ?? '') ?></p>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal Notitie -->
<div class="modal fade" id="modalNotitie" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="notitieModalTitel">Notitie toevoegen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" action="<?= $base ?>/notities/opslaan.php" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="klant_id" value="<?= $id ?>">
                    <input type="hidden" name="notitie_id" id="notitie_id" value="">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Titel <span class="text-danger">*</span></label>
                        <input type="text" name="titel" id="n_titel" class="form-control rounded-3" required>
                        <div class="invalid-feedback">Vul een titel in.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Inhoud</label>
                        <textarea name="inhoud" id="n_inhoud" class="form-control rounded-3" rows="6" placeholder="Schrijf hier de notitie..."></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 rounded-3" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-primary flex-grow-1 rounded-3">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- ─── Tab: Service ──────────────────────────────────────────────────────── -->
<?php elseif ($actieve_tab === 'service'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0">Servicehistorie</h6>
    <button class="btn btn-primary btn-sm" onclick="nieuwService()">+ Toevoegen</button>
</div>
<?php if (empty($service)): ?>
    <div class="bg-white rounded-3 border p-4 text-center text-muted">Nog geen servicehistorie.</div>
<?php else: ?>
<?php
$type_labels = ['bezoek' => 'Bezoek', 'storing' => 'Storing', 'onderhoud' => 'Onderhoud', 'update' => 'Update', 'overig' => 'Overig'];
$type_kleuren = ['bezoek' => '#185E9B', 'storing' => '#dc3545', 'onderhoud' => '#198754', 'update' => '#0d6efd', 'overig' => '#6c757d'];
foreach ($service as $s):
?>
<div class="bg-white rounded-3 border p-3 mb-2">
    <div class="d-flex justify-content-between align-items-start gap-2">
        <div class="d-flex gap-2 align-items-start flex-grow-1">
            <div style="width:3px;background:<?= $type_kleuren[$s['type']] ?? '#adb5bd' ?>;border-radius:2px;flex-shrink:0;min-height:50px;"></div>
            <div class="flex-grow-1">
                <div class="d-flex gap-2 align-items-center mb-1 flex-wrap">
                    <span class="fw-medium small"><?= h(date('d-m-Y', strtotime($s['datum']))) ?></span>
                    <span class="badge rounded-pill" style="font-size:10px;background:<?= $type_kleuren[$s['type']] ?? '#adb5bd' ?>20;color:<?= $type_kleuren[$s['type']] ?? '#adb5bd' ?>;border:1px solid <?= $type_kleuren[$s['type']] ?? '#adb5bd' ?>40;">
                        <?= $type_labels[$s['type']] ?? $s['type'] ?>
                    </span>
                </div>
                <p class="mb-0 small" style="white-space:pre-line;"><?= h($s['omschrijving']) ?></p>
                <?php if (!empty($s['opgelost_door'])): ?>
                <div class="text-muted mt-1" style="font-size:11px;"><i class="ri-user-line"></i> <?= h($s['opgelost_door']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-1 flex-shrink-0">
            <button class="btn btn-sm btn-outline-secondary" onclick="bewerkService(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)">
                <i class="ri-edit-line"></i>
            </button>
            <?php if ($gebruiker['rol'] === 'admin'): ?>
            <a href="<?= $base ?>/service/verwijderen.php?id=<?= $s['id'] ?>&klant_id=<?= $id ?>"
               class="btn btn-sm btn-outline-danger"
               onclick="return confirm('Verwijderen?')"><i class="ri-delete-bin-line"></i></a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; endif; ?>

<!-- Modal Service -->
<div class="modal fade" id="modalService" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="serviceModalTitel">Service toevoegen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" action="<?= $base ?>/service/opslaan.php" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="klant_id" value="<?= $id ?>">
                    <input type="hidden" name="service_id" id="service_id" value="">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Datum <span class="text-danger">*</span></label>
                            <input type="date" name="datum" id="sv_datum" class="form-control rounded-3" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Type</label>
                            <select name="type" id="sv_type" class="form-select rounded-3">
                                <option value="bezoek">Bezoek</option>
                                <option value="storing">Storing</option>
                                <option value="onderhoud">Onderhoud</option>
                                <option value="update">Update</option>
                                <option value="overig">Overig</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Apparaat (optioneel)</label>
                            <select name="apparaat_id" id="sv_apparaat_id" class="form-select rounded-3">
                                <option value="">— Niet gekoppeld aan apparaat —</option>
                                <?php foreach ($apparaten as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= h(trim($a['merk'] . ' ' . $a['model']) ?: 'Apparaat #' . $a['id']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Omschrijving <span class="text-danger">*</span></label>
                            <textarea name="omschrijving" id="sv_omschrijving" class="form-control rounded-3" rows="4" required placeholder="Wat is er gedaan?"></textarea>
                            <div class="invalid-feedback">Vul een omschrijving in.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Uitgevoerd door</label>
                            <input type="text" name="opgelost_door" id="sv_opgelost_door" class="form-control rounded-3" placeholder="Naam technicus">
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 rounded-3" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-primary flex-grow-1 rounded-3">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ─── Tab: Bestanden ─────────────────────────────────────────────────────── -->
<?php elseif ($actieve_tab === 'bestanden'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0">Bestanden</h6>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalBestand">+ Uploaden</button>
</div>
<?php if (empty($bestanden)): ?>
    <div class="bg-white rounded-3 border p-4 text-center text-muted">Nog geen bestanden geüpload.</div>
<?php else: ?>
<?php
$iconen = ['pdf' => 'ri-file-pdf-line', 'docx' => 'ri-file-word-line', 'doc' => 'ri-file-word-line',
           'xlsx' => 'ri-file-excel-line', 'xls' => 'ri-file-excel-line',
           'jpg' => 'ri-image-line', 'jpeg' => 'ri-image-line', 'png' => 'ri-image-line',
           'zip' => 'ri-file-zip-line', 'rar' => 'ri-file-zip-line'];
?>
<div class="bg-white rounded-3 border">
    <table class="table table-hover mb-0">
        <thead class="table-light">
            <tr><th>Bestandsnaam</th><th>Grootte</th><th>Geüpload op</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($bestanden as $b):
            $ext = strtolower(pathinfo($b['originele_naam'], PATHINFO_EXTENSION));
            $icoon = $iconen[$ext] ?? 'ri-file-line';
        ?>
        <tr>
            <td>
                <i class="<?= $icoon ?> text-muted me-2"></i>
                <?= h($b['originele_naam']) ?>
            </td>
            <td class="text-muted small"><?= round($b['bestandsgrootte'] / 1024, 1) ?> KB</td>
            <td class="text-muted small"><?= h(date('d-m-Y', strtotime($b['aangemaakt_op']))) ?></td>
            <td class="text-end">
                <a href="<?= $base ?>/bestanden/download.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="ri-download-line"></i>
                </a>
                <?php if ($gebruiker['rol'] === 'admin'): ?>
                <a href="<?= $base ?>/bestanden/verwijderen.php?id=<?= $b['id'] ?>&klant_id=<?= $id ?>"
                   class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Bestand verwijderen?')"><i class="ri-delete-bin-line"></i></a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Modal Bestand uploaden -->
<div class="modal fade" id="modalBestand" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold">Bestand uploaden</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" action="<?= $base ?>/bestanden/upload.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="klant_id" value="<?= $id ?>">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Bestand <span class="text-danger">*</span></label>
                        <input type="file" name="bestand" class="form-control rounded-3" required
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip,.rar,.txt,.cfg,.conf">
                        <div class="form-text text-muted">Max. 10 MB. PDF, Word, Excel, afbeeldingen, ZIP, config-bestanden.</div>
                        <div class="invalid-feedback">Selecteer een bestand.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 rounded-3" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-primary flex-grow-1 rounded-3">Uploaden</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ─── Tab: Contract ──────────────────────────────────────────────────────── -->
<?php elseif ($actieve_tab === 'contract'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0">Contracten & SLA</h6>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalContract">+ Toevoegen</button>
</div>
<?php if (empty($contracten)): ?>
    <div class="bg-white rounded-3 border p-4 text-center text-muted">Nog geen contracten.</div>
<?php else:
    $sla_kleuren = ['basis' => '#6c757d', 'standaard' => '#0d6efd', 'premium' => '#198754'];
    foreach ($contracten as $c):
        $verloopt = !empty($c['eind_datum']);
        $dagen_over = $verloopt ? (int)((strtotime($c['eind_datum']) - time()) / 86400) : null;
        $verlopen = $verloopt && $dagen_over < 0;
        $waarschuwing = $verloopt && $dagen_over >= 0 && $dagen_over <= 30;
?>
<div class="bg-white rounded-3 border p-4 mb-3 <?= $verlopen ? 'border-danger' : ($waarschuwing ? 'border-warning' : '') ?>">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <div class="fw-bold mb-1"><?= h($c['omschrijving']) ?></div>
            <span class="badge rounded-pill mb-2" style="background:<?= $sla_kleuren[$c['sla_niveau']] ?>20;color:<?= $sla_kleuren[$c['sla_niveau']] ?>;border:1px solid <?= $sla_kleuren[$c['sla_niveau']] ?>40;font-size:10px;">
                SLA: <?= ucfirst($c['sla_niveau']) ?>
            </span>
        </div>
        <div class="d-flex gap-1">
            <button class="btn btn-sm btn-outline-secondary" onclick="bewerkContract(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)">
                <i class="ri-edit-line"></i>
            </button>
            <?php if ($gebruiker['rol'] === 'admin'): ?>
            <a href="<?= $base ?>/contracten/verwijderen.php?id=<?= $c['id'] ?>&klant_id=<?= $id ?>"
               class="btn btn-sm btn-outline-danger"
               onclick="return confirm('Contract verwijderen?')"><i class="ri-delete-bin-line"></i></a>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-3 small text-muted flex-wrap">
        <?php if (!empty($c['start_datum'])): ?>
        <div><i class="ri-calendar-line"></i> Start: <?= h(date('d-m-Y', strtotime($c['start_datum']))) ?></div>
        <?php endif; ?>
        <?php if ($verloopt): ?>
        <div class="<?= $verlopen ? 'text-danger fw-bold' : ($waarschuwing ? 'text-warning fw-bold' : '') ?>">
            <i class="ri-calendar-close-line"></i>
            Eind: <?= h(date('d-m-Y', strtotime($c['eind_datum']))) ?>
            <?php if ($verlopen): ?><span class="ms-1">(verlopen)</span>
            <?php elseif ($waarschuwing): ?><span class="ms-1">(nog <?= $dagen_over ?> dagen)</span>
            <?php else: ?><span class="ms-1">(nog <?= $dagen_over ?> dagen)</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php if (!empty($c['notities'])): ?>
    <p class="text-muted small mt-2 mb-0" style="white-space:pre-line;"><?= h($c['notities']) ?></p>
    <?php endif; ?>
</div>
<?php endforeach; endif; ?>

<!-- Modal Contract -->
<div class="modal fade" id="modalContract" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="contractModalTitel">Contract toevoegen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" action="<?= $base ?>/contracten/opslaan.php" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="klant_id" value="<?= $id ?>">
                    <input type="hidden" name="contract_id" id="contract_id" value="">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-medium">Omschrijving <span class="text-danger">*</span></label>
                            <input type="text" name="omschrijving" id="ct_omschrijving" class="form-control rounded-3" placeholder="Bijv. Jaarabonnement beheerdiensten" required>
                            <div class="invalid-feedback">Vul een omschrijving in.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">SLA niveau</label>
                            <select name="sla_niveau" id="ct_sla_niveau" class="form-select rounded-3">
                                <option value="basis">Basis</option>
                                <option value="standaard" selected>Standaard</option>
                                <option value="premium">Premium</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Startdatum</label>
                            <input type="date" name="start_datum" id="ct_start_datum" class="form-control rounded-3">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Einddatum</label>
                            <input type="date" name="eind_datum" id="ct_eind_datum" class="form-control rounded-3">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Notities</label>
                            <textarea name="notities" id="ct_notities" class="form-control rounded-3" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 rounded-3" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-primary flex-grow-1 rounded-3">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ─── Tab: Office 365 ───────────────────────────────────────────────────── -->
<?php elseif ($actieve_tab === 'o365'): ?>
<!-- Tenant -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0">Microsoft Office 365</h6>
    <div class="d-flex gap-2">
        <?php if ($o365): ?>
        <a href="<?= $base ?>/o365/tenant_verwijderen.php?klant_id=<?= $id ?>"
           class="btn btn-outline-danger btn-sm"
           onclick="return confirm('Office 365 gegevens verwijderen? Dit verwijdert ook alle licenties en gebruikers.')">
            <i class="ri-delete-bin-line"></i> Verwijderen
        </a>
        <?php endif; ?>
        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalO365Gebruiker" onclick="resetGebruikerModal()">+ Gebruiker</button>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalO365">
            <?= $o365 ? '<i class="ri-edit-line"></i> Tenant' : '+ Toevoegen' ?>
        </button>
    </div>
</div>
<?php if (!$o365): ?>
    <div class="bg-white rounded-3 border p-4 text-center text-muted">Nog geen Office 365 gegevens.</div>
<?php else: ?>
<div class="row g-3">
    <!-- Tenant info -->
    <div class="col-md-6">
        <div class="bg-white rounded-3 border p-4 h-100">
            <h6 class="fw-semibold mb-3">Tenant</h6>
            <table class="table table-sm table-borderless mb-0">
                <?php if (!empty($o365['tenant_naam'])): ?>
                <tr><td class="text-muted" style="width:45%">Tenant naam</td><td><code><?= h($o365['tenant_naam']) ?></code></td></tr>
                <?php endif; ?>
            </table>
            <?php if (!empty($o365['notities'])): ?>
            <p class="mb-0 text-muted small mt-2" style="white-space:pre-line;"><?= h($o365['notities']) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <!-- Beheerder -->
    <div class="col-md-6">
        <div class="bg-white rounded-3 border p-4 h-100">
            <h6 class="fw-semibold mb-3">Beheerdersaccount</h6>
            <?php if (!empty($o365['admin_email'])): ?>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="text-muted" style="font-size:12px;min-width:90px;">E-mail</span>
                <code style="font-size:12px;"><?= h($o365['admin_email']) ?></code>
                <button class="btn btn-sm p-0 text-muted" onclick="kopieer('<?= h($o365['admin_email']) ?>', this)"><i class="ri-file-copy-line" style="font-size:14px;"></i></button>
            </div>
            <?php endif; ?>
            <?php if (!empty($o365['admin_wachtwoord_enc'])): ?>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="text-muted" style="font-size:12px;min-width:90px;">Wachtwoord</span>
                <code class="flex-grow-1 ww-tekst" data-id="o365_<?= $id ?>" style="font-size:12px;">••••••••</code>
                <button class="btn btn-sm p-0 text-muted" onclick="toggleO365Ww(<?= $id ?>, this)"><i class="ri-eye-line" style="font-size:14px;"></i></button>
                <button class="btn btn-sm p-0 text-muted" onclick="kopieerO365Ww(<?= $id ?>, this)"><i class="ri-file-copy-line" style="font-size:14px;"></i></button>
            </div>
            <?php endif; ?>
            <a href="https://admin.microsoft.com" target="_blank" class="btn btn-sm btn-outline-secondary mt-2">
                <i class="ri-external-link-line"></i> Admin Center
            </a>
        </div>
    </div>
    <!-- Licenties (opgeteld vanuit gebruikers) -->
    <?php
    $lic_samenvatting = [];
    foreach ($o365_gebruikers as $gu) {
        if (!empty($gu['licentie_type'])) {
            $lic_samenvatting[$gu['licentie_type']] = ($lic_samenvatting[$gu['licentie_type']] ?? 0) + 1;
        }
    }
    ?>
    <div class="col-md-6">
        <div class="bg-white rounded-3 border p-3">
            <h6 class="fw-semibold mb-2">Licenties</h6>
            <?php if (empty($lic_samenvatting)): ?>
            <p class="text-muted small mb-0">Nog geen licenties toegewezen aan gebruikers.</p>
            <?php else: ?>
            <table class="table table-sm table-borderless mb-0">
                <?php foreach ($lic_samenvatting as $type => $aantal): ?>
                <tr>
                    <td style="font-size:13px;"><?= h($type) ?></td>
                    <td class="text-muted fw-semibold" style="font-size:13px;width:40px;"><?= $aantal ?>x</td>
                </tr>
                <?php endforeach; ?>
                <tr class="border-top">
                    <td class="text-muted" style="font-size:12px;">Totaal</td>
                    <td class="fw-bold" style="font-size:12px;"><?= count($o365_gebruikers) ?></td>
                </tr>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <!-- Gebruikers -->
    <div class="col-md-6">
        <div class="bg-white rounded-3 border p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-semibold mb-0">Gebruikers</h6>
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalO365Gebruiker" onclick="resetGebruikerModal()"><i class="ri-add-line"></i></button>
            </div>
            <?php if (empty($o365_gebruikers)): ?>
            <p class="text-muted small mb-0">Nog geen gebruikers.</p>
            <?php else: ?>
            <?php foreach ($o365_gebruikers as $gu): ?>
            <div class="d-flex align-items-start gap-2 py-2 border-bottom">
                <div class="flex-grow-1" style="min-width:0;">
                    <div class="fw-medium" style="font-size:13px;"><?= h($gu['naam']) ?></div>
                    <div class="text-muted" style="font-size:12px;"><?= h($gu['email']) ?></div>
                    <div class="d-flex gap-1 flex-wrap mt-1">
                        <?php if (!empty($gu['rol'])): ?><span class="badge bg-light text-muted border" style="font-size:10px;"><?= h($gu['rol']) ?></span><?php endif; ?>
                        <?php if (!empty($gu['licentie_type'])): ?><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="font-size:10px;"><?= h($gu['licentie_type']) ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="d-flex flex-column gap-1 flex-shrink-0">
                    <?php if (!empty($gu['wachtwoord_enc'])): ?>
                    <div class="d-flex align-items-center gap-1">
                        <code class="ww-tekst" data-id="o365g_<?= $gu['id'] ?>" style="font-size:11px;">••••••</code>
                        <button class="btn btn-sm p-0 text-muted" onclick="toggleO365GWw(<?= $gu['id'] ?>, this)"><i class="ri-eye-line" style="font-size:13px;"></i></button>
                        <button class="btn btn-sm p-0 text-muted" onclick="kopieerO365GWw(<?= $gu['id'] ?>, this)"><i class="ri-file-copy-line" style="font-size:13px;"></i></button>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm p-0 text-muted" onclick="bewerkGebruiker(<?= htmlspecialchars(json_encode($gu), ENT_QUOTES) ?>)"><i class="ri-edit-line" style="font-size:13px;"></i></button>
                        <?php if ($gebruiker['rol'] === 'admin'): ?>
                        <a href="<?= $base ?>/o365/gebruiker_verwijderen.php?id=<?= $gu['id'] ?>&klant_id=<?= $id ?>" onclick="return confirm('Verwijderen?')" class="btn btn-sm p-0 text-danger"><i class="ri-delete-bin-line" style="font-size:13px;"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Tenant -->
<div class="modal fade" id="modalO365" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold">Office 365 tenant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" action="<?= $base ?>/o365/opslaan.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="klant_id" value="<?= $id ?>">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-medium">Tenant naam</label>
                            <input type="text" name="tenant_naam" class="form-control rounded-3" placeholder="bedrijf.onmicrosoft.com" value="<?= h($o365['tenant_naam'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Beheerder e-mail</label>
                            <input type="email" name="admin_email" class="form-control rounded-3" placeholder="admin@bedrijf.nl" value="<?= h($o365['admin_email'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Beheerder wachtwoord</label>
                            <div class="input-group">
                                <input type="password" name="admin_wachtwoord" id="o365_ww" class="form-control rounded-start-3" placeholder="<?= $o365 ? 'Laat leeg om ongewijzigd te laten' : '' ?>" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleVeld('o365_ww', this)"><i class="ri-eye-line"></i></button>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Notities</label>
                            <textarea name="notities" class="form-control rounded-3" rows="2"><?= h($o365['notities'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 rounded-3" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-primary flex-grow-1 rounded-3">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Licentie -->
<div class="modal fade" id="modalO365Licentie" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="licentieModalTitel">Licentie toevoegen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" action="<?= $base ?>/o365/licentie_opslaan.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="klant_id" value="<?= $id ?>">
                    <input type="hidden" name="licentie_id" id="lic_id" value="">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Type</label>
                        <select name="licentie_type" id="lic_type" class="form-select rounded-3">
                            <?php foreach ([
                                'Microsoft 365 Business Basic',
                                'Microsoft 365 Business Standard',
                                'Microsoft 365 Business Premium',
                                'Microsoft 365 E3',
                                'Microsoft 365 E5',
                                'Office 365 E1',
                                'Office 365 E3',
                                'Exchange Online Plan 1',
                                'Exchange Online Plan 2',
                                'Microsoft Teams Essentials',
                                'Overig',
                            ] as $lt): ?>
                            <option value="<?= h($lt) ?>"><?= h($lt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Aantal</label>
                        <input type="number" name="aantal" id="lic_aantal" class="form-control rounded-3" min="1" value="1">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 rounded-3" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-primary flex-grow-1 rounded-3">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Gebruiker -->
<div class="modal fade" id="modalO365Gebruiker" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="gebruikerModalTitel">Gebruiker toevoegen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" action="<?= $base ?>/o365/gebruiker_opslaan.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="klant_id" value="<?= $id ?>">
                    <input type="hidden" name="gebruiker_id" id="gu_id" value="">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-medium">Naam</label>
                            <input type="text" name="naam" id="gu_naam" class="form-control rounded-3" placeholder="Jan de Vries">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">E-mailadres</label>
                            <input type="text" name="email" id="gu_email" class="form-control rounded-3" placeholder="jan@bedrijf.nl">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Wachtwoord</label>
                            <div class="input-group">
                                <input type="password" name="wachtwoord" id="gu_ww" class="form-control rounded-start-3" placeholder="Laat leeg om ongewijzigd te laten" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleVeld('gu_ww', this)"><i class="ri-eye-line"></i></button>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Rol</label>
                            <select name="rol" id="gu_rol" class="form-select rounded-3">
                                <option value="Gebruiker">Gebruiker</option>
                                <option value="Globale beheerder">Globale beheerder</option>
                                <option value="Gebruikersbeheerder">Gebruikersbeheerder</option>
                                <option value="Factureringsbeheerder">Factureringsbeheerder</option>
                                <option value="Helpdesk beheerder">Helpdesk beheerder</option>
                                <option value="Overig">Overig</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Licentie</label>
                            <select name="licentie_type" id="gu_licentie" class="form-select rounded-3">
                                <option value="">Geen / onbekend</option>
                                <option value="Microsoft 365 Business Basic">M365 Business Basic</option>
                                <option value="Microsoft 365 Business Standard">M365 Business Standard</option>
                                <option value="Microsoft 365 Business Premium">M365 Business Premium</option>
                                <option value="Microsoft 365 E3">Microsoft 365 E3</option>
                                <option value="Microsoft 365 E5">Microsoft 365 E5</option>
                                <option value="Office 365 E1">Office 365 E1</option>
                                <option value="Office 365 E3">Office 365 E3</option>
                                <option value="Exchange Online Plan 1">Exchange Online Plan 1</option>
                                <option value="Exchange Online Plan 2">Exchange Online Plan 2</option>
                                <option value="Microsoft Teams Essentials">Teams Essentials</option>
                                <option value="Overig">Overig</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Notities</label>
                            <textarea name="notities" id="gu_notities" class="form-control rounded-3" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 rounded-3" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-primary flex-grow-1 rounded-3">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ─── Tab: Telefonie ────────────────────────────────────────────────────── -->
<?php elseif ($actieve_tab === 'telefonie'):
    $ys = !empty($yeastar_centralen) ? $yeastar_centralen[0] : null;
    $heeft_tel = $ys || !empty($simpbx['actief']) || !empty($ziggo['actief']) || !empty($routit['actief']);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0"><i class="ri-phone-line me-1"></i> Telefonie</h6>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTelefonie">
        <?= $heeft_tel ? '<i class="ri-edit-line"></i> Bewerken' : '+ Instellen' ?>
    </button>
</div>

<?php if (!$heeft_tel): ?>
<div class="bg-white rounded-3 border p-4 text-center text-muted">
    <i class="ri-phone-off-line" style="font-size:28px;"></i>
    <div class="mt-2">Geen telefonie ingesteld.</div>
</div>
<?php endif; ?>

<?php if ($ys): ?>
<div class="bg-white rounded-3 border p-4 mb-3" style="max-width:500px;">
    <h6 class="fw-bold mb-3 d-flex align-items-center gap-2"><img src="https://www.mister-voip.nl/wp-content/uploads/2025/02/Yeastar_Symbol.png" height="22" alt="Yeastar" style="object-fit:contain;"> Yeastar</h6>
    <?php if (!empty($ys['admin_url'])): ?>
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="text-muted" style="font-size:12px;min-width:90px;">Link</span>
        <a href="<?= h($ys['admin_url']) ?>" target="_blank" class="small text-truncate"><?= h($ys['admin_url']) ?></a>
        <a href="<?= h($ys['admin_url']) ?>" target="_blank" class="btn btn-sm p-0 text-muted flex-shrink-0"><i class="ri-external-link-line" style="font-size:14px;"></i></a>
    </div>
    <?php endif; ?>
    <?php if (!empty($ys['admin_gebruiker'])): ?>
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="text-muted" style="font-size:12px;min-width:90px;">Gebruiker</span>
        <code style="font-size:12px;"><?= h($ys['admin_gebruiker']) ?></code>
        <button class="btn btn-sm p-0 text-muted" onclick="kopieer('<?= h($ys['admin_gebruiker']) ?>', this)"><i class="ri-file-copy-line" style="font-size:14px;"></i></button>
    </div>
    <?php endif; ?>
    <?php if (!empty($ys['admin_wachtwoord_enc'])): ?>
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="text-muted" style="font-size:12px;min-width:90px;">Wachtwoord</span>
        <code class="flex-grow-1 ww-tekst" data-id="ys_<?= $ys['id'] ?>" style="font-size:12px;">••••••••</code>
        <button class="btn btn-sm p-0 text-muted" onclick="toggleYsWw(<?= $ys['id'] ?>, this)"><i class="ri-eye-line" style="font-size:14px;"></i></button>
        <button class="btn btn-sm p-0 text-muted" onclick="kopieerYsWw(<?= $ys['id'] ?>, this)"><i class="ri-file-copy-line" style="font-size:14px;"></i></button>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($simpbx['actief'])): ?>
<div class="bg-white rounded-3 border p-4 mb-3" style="max-width:500px;">
    <h6 class="fw-bold mb-3" style="color:#2563eb;">SimPBX</h6>
    <?php if (!empty($simpbx['naam'])): ?>
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="text-muted" style="font-size:12px;min-width:90px;">Klantnaam</span>
        <span style="font-size:12px;"><?= h($simpbx['naam']) ?></span>
    </div>
    <?php endif; ?>
    <p class="text-muted small mb-0">Klant maakt gebruik van onze eigen SimPBX telefooncentrale.</p>
</div>
<?php endif; ?>

<?php if (!empty($ziggo['actief'])): ?>
<div class="bg-white rounded-3 border p-4 mb-3" style="max-width:500px;">
    <h6 class="fw-bold mb-3"><img src="https://vodafoneziggo.scene7.com/is/content/vodafoneziggo/ziggo-logo-orange-v1" height="18" alt="Ziggo" style="object-fit:contain;"></h6>
    <?php if (!empty($ziggo['naam'])): ?>
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="text-muted" style="font-size:12px;min-width:90px;">Klantnaam</span>
        <span style="font-size:12px;"><?= h($ziggo['naam']) ?></span>
    </div>
    <?php endif; ?>
    <p class="text-muted small mb-0">Klant maakt nu gebruik van Ziggo portaal.</p>
</div>
<?php endif; ?>

<?php if (!empty($routit['actief'])): ?>
<div class="bg-white rounded-3 border p-4 mb-3" style="max-width:500px;">
    <h6 class="fw-bold mb-3" style="color:#003a70;">RoutIT</h6>
    <?php if (!empty($routit['naam'])): ?>
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="text-muted" style="font-size:12px;min-width:90px;">Klantnaam</span>
        <span style="font-size:12px;"><?= h($routit['naam']) ?></span>
    </div>
    <?php endif; ?>
    <p class="text-muted small mb-0">Klant maakt gebruik van RoutIT (HostedVoice).</p>
</div>
<?php endif; ?>

<!-- Modal Telefonie -->
<div class="modal fade" id="modalTelefonie" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold">Telefonie instellen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" action="<?= $base ?>/telefonie/opslaan.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="klant_id" value="<?= $id ?>">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Telefonie</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="heeft_yeastar" id="tel_yeastar" value="1" <?= $ys ? 'checked' : '' ?> onchange="toggleTelType()">
                                <label class="form-check-label" for="tel_yeastar">Yeastar</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="heeft_simpbx" id="tel_simpbx" value="1" <?= !empty($simpbx['actief']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="tel_simpbx">Simpbx</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="heeft_ziggo" id="tel_ziggo" value="1" <?= !empty($ziggo['actief']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="tel_ziggo">Ziggo</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="heeft_routit" id="tel_routit" value="1" <?= !empty($routit['actief']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="tel_routit">RoutIT</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3" id="tel_simpbx_velden" style="display:<?= !empty($simpbx['actief']) ? 'block' : 'none' ?>;">
                        <label class="form-label fw-medium">SimPBX klantnaam</label>
                        <input type="text" name="simpbx_naam" class="form-control rounded-3" placeholder="Naam van de klant in SimPBX" value="<?= h($simpbx['naam'] ?? '') ?>">
                    </div>
                    <div class="mb-3" id="tel_ziggo_velden" style="display:<?= !empty($ziggo['actief']) ? 'block' : 'none' ?>;">
                        <label class="form-label fw-medium">Ziggo klantnaam</label>
                        <input type="text" name="ziggo_naam" class="form-control rounded-3" placeholder="Naam van de klant bij Ziggo" value="<?= h($ziggo['naam'] ?? '') ?>">
                    </div>
                    <div class="mb-3" id="tel_routit_velden" style="display:<?= !empty($routit['actief']) ? 'block' : 'none' ?>;">
                        <label class="form-label fw-medium">RoutIT klantnaam</label>
                        <input type="text" name="routit_naam" class="form-control rounded-3" placeholder="Naam van de klant bij RoutIT" value="<?= h($routit['naam'] ?? '') ?>">
                    </div>
                    <div id="tel_yeastar_velden" style="display:<?= $ys ? 'block' : 'none' ?>;">
                        <div class="mb-3">
                            <label class="form-label fw-medium">Link (beheer URL)</label>
                            <input type="text" name="admin_url" class="form-control rounded-3" placeholder="https://192.168.1.100:8088" value="<?= h($ys['admin_url'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Gebruikersnaam</label>
                            <input type="text" name="admin_gebruiker" class="form-control rounded-3" value="<?= h($ys['admin_gebruiker'] ?? 'admin') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Wachtwoord</label>
                            <div class="input-group">
                                <input type="password" name="admin_wachtwoord" id="tel_ys_ww" class="form-control rounded-start-3" placeholder="<?= $ys ? 'Laat leeg om ongewijzigd te laten' : '' ?>" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleVeld('tel_ys_ww', this)"><i class="ri-eye-line"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 rounded-3" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-primary flex-grow-1 rounded-3">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ─── Tab: Internet ─────────────────────────────────────────────────────── -->
<?php elseif ($actieve_tab === 'internet'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0"><i class="ri-wifi-line me-1"></i> Internet verbindingen</h6>
    <button class="btn btn-primary btn-sm" onclick="nieuwInternet()">+ Toevoegen</button>
</div>
<?php if (empty($internets)): ?>
    <div class="bg-white rounded-3 border p-4 text-center text-muted">Nog geen internet verbindingen.</div>
<?php else: ?>
<div class="d-flex flex-column gap-3">
<?php foreach ($internets as $iv):
    $iv_id = (int)$iv['id'];
    $iv_pppoe_ww = $pppoe_wachtwoorden[$iv_id] ?? '';
    $iv_heeft_pppoe = !empty($iv['pppoe_gebruiker'] ?? null) || $iv_pppoe_ww !== '' || !empty($iv['vlan_id'] ?? null);
    $iv_provider_label = $iv['provider'] === 'Anders' ? ($iv['provider_anders'] ?: 'Anders') : $iv['provider'];
?>
<div class="bg-white rounded-3 border p-4" style="max-width:700px;">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <div class="fw-bold" style="font-size:15px;">
                <?= h($iv['omschrijving'] ?? '') ?: h($iv_provider_label) ?>
                <?php if (!empty($iv['is_primair'])): ?>
                <span class="badge bg-primary ms-1" style="font-size:10px;">Primair</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($iv['omschrijving'])): ?>
            <div class="text-muted small"><?= h($iv_provider_label) ?></div>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick='bewerkInternet(<?= json_encode([
                "id" => $iv_id,
                "omschrijving" => $iv["omschrijving"] ?? "",
                "provider" => $iv["provider"] ?? "",
                "provider_anders" => $iv["provider_anders"] ?? "",
                "type" => $iv["type"] ?? "",
                "snelheid_down" => $iv["snelheid_down"] ?? "",
                "snelheid_up" => $iv["snelheid_up"] ?? "",
                "ip_adres" => $iv["ip_adres"] ?? "",
                "backup_4g" => !empty($iv["backup_4g"]) ? 1 : 0,
                "is_primair" => !empty($iv["is_primair"]) ? 1 : 0,
                "contract_datum" => $iv["contract_datum"] ?? "",
                "notities" => $iv["notities"] ?? "",
                "pppoe_gebruiker" => $iv["pppoe_gebruiker"] ?? "",
                "vlan_id" => $iv["vlan_id"] ?? "",
                "heeft_pppoe_ww" => $iv_pppoe_ww !== '' ? 1 : 0,
            ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="ri-edit-line"></i> Bewerken</button>
            <form method="post" action="<?= $base ?>/internet/verwijderen.php" onsubmit="return confirm('Deze internet verbinding verwijderen?')" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="klant_id" value="<?= $id ?>">
                <input type="hidden" name="internet_id" value="<?= $iv_id ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="ri-delete-bin-line"></i></button>
            </form>
        </div>
    </div>
    <table class="table table-sm table-borderless mb-0">
        <tr><td class="text-muted" style="width:40%">Provider</td><td><strong><?= h($iv_provider_label) ?></strong></td></tr>
        <?php if (!empty($iv['type'])): ?>
        <tr><td class="text-muted">Type verbinding</td><td><?= h($iv['type']) ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($iv['snelheid_down'])): ?>
        <tr><td class="text-muted">Snelheid</td><td><?= h($iv['snelheid_down']) ?> / <?= h($iv['snelheid_up']) ?> Mbit</td></tr>
        <?php endif; ?>
        <?php if (!empty($iv['ip_adres'])): ?>
        <tr><td class="text-muted">Vast IP-adres</td><td><code><?= h($iv['ip_adres']) ?></code></td></tr>
        <?php endif; ?>
        <?php if (!empty($iv['backup_4g'])): ?>
        <tr><td class="text-muted">4G backup</td><td><span class="badge bg-success">Aanwezig</span></td></tr>
        <?php endif; ?>
        <?php if (!empty($iv['contract_datum'])): ?>
        <tr><td class="text-muted">Contract tot</td><td><?= h(date('d-m-Y', strtotime($iv['contract_datum']))) ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($iv['notities'])): ?>
        <tr><td class="text-muted align-top">Notities</td><td style="white-space:pre-line;"><?= h($iv['notities']) ?></td></tr>
        <?php endif; ?>
    </table>

    <?php if ($iv_heeft_pppoe): ?>
    <hr class="my-3">
    <div class="text-muted small fw-medium mb-2"><i class="ri-key-2-line me-1"></i>PPPoE / VLAN</div>
    <table class="table table-sm table-borderless mb-0">
        <?php if (!empty($iv['pppoe_gebruiker'] ?? null)): ?>
        <tr><td class="text-muted" style="width:40%">PPPoE gebruiker</td>
            <td>
                <code style="font-size:12px;"><?= h($iv['pppoe_gebruiker']) ?></code>
                <button type="button" class="btn btn-sm btn-link p-0 ms-1" onclick="kopieer(<?= htmlspecialchars(json_encode($iv['pppoe_gebruiker']), ENT_QUOTES) ?>, this)" title="Kopiëren"><i class="ri-file-copy-line" style="font-size:14px;"></i></button>
            </td>
        </tr>
        <?php endif; ?>
        <?php if ($iv_pppoe_ww !== ''): ?>
        <tr><td class="text-muted">PPPoE wachtwoord</td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <code id="pppoe_ww_weergave_<?= $iv_id ?>" style="font-size:12px;">••••••••</code>
                    <button type="button" class="btn btn-sm btn-link p-0" onclick="toonVsGeheim('pppoe_ww_weergave_<?= $iv_id ?>', <?= htmlspecialchars(json_encode($iv_pppoe_ww), ENT_QUOTES) ?>, this)" title="Tonen"><i class="ri-eye-line" style="font-size:14px;"></i></button>
                    <button type="button" class="btn btn-sm btn-link p-0" onclick="kopieer(<?= htmlspecialchars(json_encode($iv_pppoe_ww), ENT_QUOTES) ?>, this)" title="Kopiëren"><i class="ri-file-copy-line" style="font-size:14px;"></i></button>
                </div>
            </td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($iv['vlan_id'] ?? null)): ?>
        <tr><td class="text-muted">VLAN ID</td><td><code style="font-size:12px;"><?= (int)$iv['vlan_id'] ?></code></td></tr>
        <?php endif; ?>
    </table>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal Internet -->
<div class="modal fade" id="modalInternet" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="internetModalTitel">Internet verbinding</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" action="<?= $base ?>/internet/opslaan.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="klant_id" value="<?= $id ?>">
                    <input type="hidden" name="internet_id" id="int_id" value="">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-medium">Omschrijving <span class="text-muted fw-normal">(bijv. Hoofdkantoor, Werkplaats, Backup)</span></label>
                            <input type="text" name="omschrijving" id="int_omschrijving" class="form-control rounded-3" placeholder="bijv. Hoofdvestiging glasvezel">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Provider <span class="text-danger">*</span></label>
                            <select name="provider" id="int_provider" class="form-select rounded-3" required onchange="toggleAndersVeld()">
                                <option value="">Selecteer provider...</option>
                                <?php foreach (['Routit','Pocos','Delta','Eurofiber','Trined'] as $prov): ?>
                                <option value="<?= $prov ?>"><?= $prov ?></option>
                                <?php endforeach; ?>
                                <option value="Anders">Anders (zelf invullen)</option>
                            </select>
                        </div>
                        <div class="col-12" id="anders_veld" style="display:none;">
                            <label class="form-label fw-medium">Provider naam</label>
                            <input type="text" name="provider_anders" id="int_provider_anders" class="form-control rounded-3" placeholder="Naam van de provider">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Type verbinding</label>
                            <select name="type" id="int_type" class="form-select rounded-3">
                                <option value="">Onbekend</option>
                                <?php foreach (['Glasvezel','ADSL','VDSL'] as $t): ?>
                                <option value="<?= h($t) ?>"><?= h($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-medium">Down (Mbit)</label>
                            <input type="text" name="snelheid_down" id="int_down" class="form-control rounded-3" placeholder="100">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-medium">Up (Mbit)</label>
                            <input type="text" name="snelheid_up" id="int_up" class="form-control rounded-3" placeholder="20">
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="backup_4g" id="int_4g">
                                <label class="form-check-label fw-medium" for="int_4g">4G backup aanwezig</label>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_primair" id="int_primair">
                                <label class="form-check-label fw-medium" for="int_primair">Primaire verbinding</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Vast IP-adres</label>
                            <input type="text" name="ip_adres" id="int_ip" class="form-control rounded-3 font-monospace" placeholder="Bijv. 85.144.xxx.xxx">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Contract looptijd tot</label>
                            <input type="date" name="contract_datum" id="int_contract" class="form-control rounded-3">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Notities</label>
                            <textarea name="notities" id="int_notities" class="form-control rounded-3" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <hr class="my-1">
                            <div class="text-muted small fw-medium mb-2"><i class="ri-key-2-line me-1"></i>PPPoE / VLAN <span class="text-muted fw-normal">(optioneel — invullen indien van toepassing)</span></div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">PPPoE gebruiker</label>
                            <input type="text" name="pppoe_gebruiker" id="int_pppoe_user" class="form-control rounded-3" placeholder="bijv. user@kpn-mobile.com">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">PPPoE wachtwoord</label>
                            <div class="input-group">
                                <input type="password" name="pppoe_wachtwoord" id="int_pppoe_ww" class="form-control rounded-start-3" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleVeld('int_pppoe_ww', this)"><i class="ri-eye-line"></i></button>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">VLAN ID</label>
                            <input type="number" min="1" max="4094" name="vlan_id" id="int_vlan" class="form-control rounded-3" placeholder="bijv. 6 (KPN glasvezel)">
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 rounded-3" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-primary flex-grow-1 rounded-3">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ─── Tab: Virusscanner ─────────────────────────────────────────────────── -->
<?php elseif ($actieve_tab === 'virusscanner'):
    $vs_scanner    = $virusscanner['scanner'] ?? 'geen';
    $vs_anders     = $virusscanner['scanner_anders'] ?? '';
    $vs_licentie   = $virusscanner['licentie_encrypted'] ? decrypt_wachtwoord($virusscanner['licentie_encrypted']) : '';
    $vs_uninstall  = $virusscanner['uninstall_code_encrypted'] ? decrypt_wachtwoord($virusscanner['uninstall_code_encrypted']) : '';
    $vs_vervaldat  = $virusscanner['vervaldatum'] ?? '';
    $vs_notities   = $virusscanner['notities'] ?? '';
    $vs_labels = ['geen' => 'Geen', 'kaspersky' => 'Kaspersky', 'bitdefender' => 'Bitdefender', 'anders' => 'Anders'];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0"><i class="ri-shield-check-line me-1"></i> Virusscanner</h6>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalVirusscanner">
        <?= $virusscanner ? '<i class="ri-edit-line"></i> Bewerken' : '+ Instellen' ?>
    </button>
</div>
<?php if (!$virusscanner || $vs_scanner === 'geen'): ?>
    <div class="bg-white rounded-3 border p-4 text-center text-muted">Nog geen virusscanner ingesteld.</div>
<?php else: ?>
<div class="bg-white rounded-3 border p-4" style="max-width:640px;">
    <table class="table table-sm table-borderless mb-0">
        <tr>
            <td class="text-muted" style="width:40%">Scanner</td>
            <td>
                <?php if ($vs_scanner === 'kaspersky'): ?>
                    <span class="badge" style="background:#00A88E;color:white;font-size:12px;padding:6px 10px;">
                        <i class="ri-shield-check-line"></i> Kaspersky
                    </span>
                <?php elseif ($vs_scanner === 'bitdefender'): ?>
                    <span class="badge" style="background:#B50202;color:white;font-size:12px;padding:6px 10px;">
                        <i class="ri-shield-check-line"></i> Bitdefender
                    </span>
                <?php elseif ($vs_scanner === 'anders'): ?>
                    <span class="badge bg-secondary" style="font-size:12px;padding:6px 10px;"><?= h($vs_anders ?: 'Anders') ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php if ($vs_licentie !== ''): ?>
        <tr>
            <td class="text-muted">Licentiesleutel</td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <code id="vs_lic_weergave" style="font-family:ui-monospace,monospace;">••••••••••••••••</code>
                    <button type="button" class="btn btn-sm btn-link p-0" onclick="toonVsGeheim('vs_lic_weergave', <?= htmlspecialchars(json_encode($vs_licentie), ENT_QUOTES) ?>, this)" title="Tonen">
                        <i class="ri-eye-line"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-link p-0" onclick="kopieer(<?= htmlspecialchars(json_encode($vs_licentie), ENT_QUOTES) ?>, this)" title="Kopieer">
                        <i class="ri-file-copy-line"></i>
                    </button>
                </div>
            </td>
        </tr>
        <?php endif; ?>
        <?php if ($vs_scanner === 'bitdefender' && $vs_uninstall !== ''): ?>
        <tr>
            <td class="text-muted">Uninstall code</td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <code id="vs_unc_weergave" style="font-family:ui-monospace,monospace;">••••••••••••</code>
                    <button type="button" class="btn btn-sm btn-link p-0" onclick="toonVsGeheim('vs_unc_weergave', <?= htmlspecialchars(json_encode($vs_uninstall), ENT_QUOTES) ?>, this)" title="Tonen">
                        <i class="ri-eye-line"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-link p-0" onclick="kopieer(<?= htmlspecialchars(json_encode($vs_uninstall), ENT_QUOTES) ?>, this)" title="Kopieer">
                        <i class="ri-file-copy-line"></i>
                    </button>
                </div>
            </td>
        </tr>
        <?php endif; ?>
        <?php if ($vs_vervaldat): ?>
        <tr>
            <td class="text-muted">Vervaldatum</td>
            <td>
                <?= h(date('d-m-Y', strtotime($vs_vervaldat))) ?>
                <?php
                $dagen = (int) floor((strtotime($vs_vervaldat) - time()) / 86400);
                if ($dagen < 0)       echo ' <span class="badge bg-danger ms-1">verlopen</span>';
                elseif ($dagen < 30)  echo ' <span class="badge bg-warning text-dark ms-1">' . $dagen . ' dagen</span>';
                else                  echo ' <span class="badge bg-success ms-1">' . $dagen . ' dagen</span>';
                ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php if ($vs_notities !== ''): ?>
        <tr>
            <td class="text-muted align-top">Notities</td>
            <td style="white-space:pre-line;"><?= h($vs_notities) ?></td>
        </tr>
        <?php endif; ?>
    </table>
</div>
<?php endif; ?>

<!-- Modal Virusscanner -->
<div class="modal fade" id="modalVirusscanner" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold">Virusscanner instellen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" action="<?= $base ?>/virusscanner/opslaan.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="klant_id" value="<?= $id ?>">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-medium">Scanner</label>
                            <select name="scanner" id="vs_scanner_select" class="form-select rounded-3" onchange="vsToonVelden()">
                                <?php foreach ($vs_labels as $k => $lbl): ?>
                                    <option value="<?= $k ?>" <?= $vs_scanner === $k ? 'selected' : '' ?>><?= h($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12" id="vs_anders_veld" style="display:<?= $vs_scanner === 'anders' ? 'block' : 'none' ?>;">
                            <label class="form-label fw-medium">Naam scanner</label>
                            <input type="text" name="scanner_anders" class="form-control rounded-3"
                                   value="<?= h($vs_anders) ?>" placeholder="bv. ESET, Norton, ...">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Licentiesleutel <span class="text-muted fw-normal">(leeg = niet wijzigen)</span></label>
                            <input type="text" name="licentie" class="form-control rounded-3"
                                   placeholder="<?= $vs_licentie !== '' ? 'Bestaand — laat leeg om te behouden' : '' ?>"
                                   autocomplete="off">
                        </div>
                        <div class="col-12" id="vs_uninstall_veld" style="display:<?= $vs_scanner === 'bitdefender' ? 'block' : 'none' ?>;">
                            <label class="form-label fw-medium">Bitdefender uninstall code <span class="text-muted fw-normal">(leeg = niet wijzigen)</span></label>
                            <input type="text" name="uninstall_code" class="form-control rounded-3"
                                   placeholder="<?= $vs_uninstall !== '' ? 'Bestaand — laat leeg om te behouden' : '' ?>"
                                   autocomplete="off">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Vervaldatum</label>
                            <input type="date" name="vervaldatum" class="form-control rounded-3"
                                   value="<?= h($vs_vervaldat) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Notities</label>
                            <textarea name="notities" class="form-control rounded-3" rows="3"
                                      placeholder="Extra info..."><?= h($vs_notities) ?></textarea>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 rounded-3" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-primary flex-grow-1 rounded-3">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function vsToonVelden() {
    var sel = document.getElementById('vs_scanner_select');
    document.getElementById('vs_anders_veld').style.display   = sel.value === 'anders' ? 'block' : 'none';
    document.getElementById('vs_uninstall_veld').style.display = sel.value === 'bitdefender' ? 'block' : 'none';
}
</script>

<?php endif; ?>

<script>
// ─── Geheim tonen/verbergen (wifi, pppoe, licentie, etc.) ────────────────────
function toonVsGeheim(id, waarde, btn) {
    var el = document.getElementById(id);
    if (!el) return;
    if (el.dataset.zichtbaar === '1') {
        el.textContent = el.dataset.masker || '••••••••';
        el.dataset.zichtbaar = '0';
        var ic1 = btn.querySelector('i'); if (ic1) ic1.className = 'ri-eye-line';
    } else {
        if (!el.dataset.masker) el.dataset.masker = el.textContent;
        el.textContent = waarde;
        el.dataset.zichtbaar = '1';
        var ic2 = btn.querySelector('i'); if (ic2) ic2.className = 'ri-eye-off-line';
    }
}

// ─── Contact bewerken ────────────────────────────────────────────────────────
function bewerkContact(c) {
    document.getElementById('contactModalTitel').textContent = 'Contactpersoon bewerken';
    document.getElementById('contact_id').value = c.id;
    document.getElementById('c_naam').value     = c.naam || '';
    document.getElementById('c_functie').value  = c.functie || '';
    document.getElementById('c_email').value    = c.email || '';
    document.getElementById('c_telefoon').value = c.telefoon || '';
    document.getElementById('c_notities').value = c.notities || '';
    new bootstrap.Modal(document.getElementById('modalContact')).show();
}

// ─── Apparaat bewerken ───────────────────────────────────────────────────────
function bewerkApparaat(a) {
    document.getElementById('apparaatModalTitel').textContent = 'Apparaat bewerken';
    document.getElementById('apparaat_id').value  = a.id;
    document.getElementById('a_type').value       = a.type || '';
    document.getElementById('a_status').value     = a.status || 'actief';
    document.getElementById('a_merk').value       = a.merk || '';
    document.getElementById('a_model').value      = a.model || '';
    document.getElementById('a_serienummer').value= a.serienummer || '';
    document.getElementById('a_aanschafdatum').value = a.aanschafdatum || '';
    document.getElementById('a_locatie').value    = a.locatie || '';
    document.getElementById('a_mac_adres').value  = a.mac_adres || '';
    document.getElementById('a_ip_adres').value   = a.ip_adres || '';
    document.getElementById('a_firmware').value   = a.firmware || '';
    document.getElementById('a_notities').value   = a.notities || '';
    document.getElementById('a_wifi_ssid').value  = a.wifi_ssid || '';
    document.getElementById('a_gast_ssid').value  = a.gast_ssid || '';
    var wifiWw = document.getElementById('a_wifi_ww');
    var gastWw = document.getElementById('a_gast_ww');
    wifiWw.value = '';
    gastWw.value = '';
    wifiWw.placeholder = a.wifi_wachtwoord_enc ? 'Laat leeg om ongewijzigd te laten' : '';
    gastWw.placeholder = a.gast_wachtwoord_enc ? 'Laat leeg om ongewijzigd te laten' : '';
    new bootstrap.Modal(document.getElementById('modalApparaat')).show();
}

// ─── Wachtwoord tonen/verbergen ──────────────────────────────────────────────
var wwCache = {};
function toggleWachtwoord(id, btn) {
    var span = document.querySelector('.ww-tekst[data-id="' + id + '"]');
    if (!span) return;
    if (span.dataset.zichtbaar === '1') {
        span.textContent = '••••••••';
        span.dataset.zichtbaar = '0';
        btn.querySelector('i').className = 'ri-eye-line';
        return;
    }
    if (wwCache[id]) {
        span.textContent = wwCache[id];
        span.dataset.zichtbaar = '1';
        btn.querySelector('i').className = 'ri-eye-off-line';
        return;
    }
    fetch('<?= $base ?>/inloggegevens/toon.php?id=' + id + '&csrf=<?= h(csrf_token()) ?>')
        .then(r => r.json())
        .then(data => {
            if (data.ww) {
                wwCache[id] = data.ww;
                span.textContent = data.ww;
                span.dataset.zichtbaar = '1';
                btn.querySelector('i').className = 'ri-eye-off-line';
            }
        });
}

function kopieerWachtwoord(id, btn) {
    if (wwCache[id]) {
        navigator.clipboard.writeText(wwCache[id]);
        var i = btn.querySelector('i');
        i.className = 'ri-check-line';
        setTimeout(() => i.className = 'ri-file-copy-line', 1500);
        return;
    }
    fetch('<?= $base ?>/inloggegevens/toon.php?id=' + id + '&csrf=<?= h(csrf_token()) ?>')
        .then(r => r.json())
        .then(data => {
            if (data.ww) {
                wwCache[id] = data.ww;
                navigator.clipboard.writeText(data.ww);
                var i = btn.querySelector('i');
                i.className = 'ri-check-line';
                setTimeout(() => i.className = 'ri-file-copy-line', 1500);
            }
        });
}

function kopieer(tekst, btn) {
    navigator.clipboard.writeText(tekst);
    var i = btn.querySelector('i');
    i.className = 'ri-check-line';
    setTimeout(() => i.className = 'ri-file-copy-line', 1500);
}

// ─── Wachtwoord bewerken ──────────────────────────────────────────────────────
function nieuwWachtwoord() {
    document.getElementById('wwModalTitel').textContent = 'Inloggegevens toevoegen';
    document.getElementById('ig_id').value              = '';
    document.getElementById('ww_label').value           = '';
    document.getElementById('ww_categorie').value       = 'router';
    document.getElementById('ww_categorie_anders').value = '';
    toggleWwCategorie();
    document.getElementById('ww_gebruikersnaam').value  = '';
    document.getElementById('ww_wachtwoord').value      = '';
    document.getElementById('ww_url').value             = '';
    document.getElementById('ww_notities').value        = '';
    new bootstrap.Modal(document.getElementById('modalWachtwoord')).show();
}

function bewerkWachtwoord(ig) {
    document.getElementById('wwModalTitel').textContent = 'Inloggegevens bewerken';
    document.getElementById('ig_id').value              = ig.id;
    document.getElementById('ww_label').value           = ig.label || '';
    var vaste = ['router'];
    var cat = ig.categorie || 'router';
    if (vaste.includes(cat)) {
        document.getElementById('ww_categorie').value = cat;
        document.getElementById('ww_categorie_anders').value = '';
    } else {
        document.getElementById('ww_categorie').value = 'anders';
        document.getElementById('ww_categorie_anders').value = cat;
    }
    toggleWwCategorie();
    document.getElementById('ww_gebruikersnaam').value  = ig.gebruikersnaam || '';
    document.getElementById('ww_wachtwoord').value      = '';
    document.getElementById('ww_url').value             = ig.url || '';
    document.getElementById('ww_notities').value        = ig.notities || '';
    new bootstrap.Modal(document.getElementById('modalWachtwoord')).show();
}

// ─── Service bewerken ─────────────────────────────────────────────────────────
function nieuwService() {
    document.getElementById('serviceModalTitel').textContent = 'Service toevoegen';
    document.getElementById('service_id').value        = '';
    document.getElementById('sv_datum').value          = '';
    document.getElementById('sv_type').value           = 'bezoek';
    document.getElementById('sv_apparaat_id').value    = '';
    document.getElementById('sv_omschrijving').value   = '';
    document.getElementById('sv_opgelost_door').value  = '';
    new bootstrap.Modal(document.getElementById('modalService')).show();
}

function bewerkService(s) {
    document.getElementById('serviceModalTitel').textContent = 'Service bewerken';
    document.getElementById('service_id').value        = s.id;
    document.getElementById('sv_datum').value          = s.datum || '';
    document.getElementById('sv_type').value           = s.type || 'bezoek';
    document.getElementById('sv_apparaat_id').value    = s.apparaat_id || '';
    document.getElementById('sv_omschrijving').value   = s.omschrijving || '';
    document.getElementById('sv_opgelost_door').value  = s.opgelost_door || '';
    new bootstrap.Modal(document.getElementById('modalService')).show();
}

// ─── Contract bewerken ─────────────────────────────────────────────────────────
function bewerkContract(c) {
    document.getElementById('contractModalTitel').textContent = 'Contract bewerken';
    document.getElementById('contract_id').value      = c.id;
    document.getElementById('ct_omschrijving').value  = c.omschrijving || '';
    document.getElementById('ct_sla_niveau').value    = c.sla_niveau || 'standaard';
    document.getElementById('ct_start_datum').value   = c.start_datum || '';
    document.getElementById('ct_eind_datum').value    = c.eind_datum || '';
    document.getElementById('ct_notities').value      = c.notities || '';
    new bootstrap.Modal(document.getElementById('modalContract')).show();
}

function toggleWWVeld() {
    var inp = document.getElementById('ww_wachtwoord');
    var oog = document.getElementById('ww_oog');
    if (inp.type === 'password') {
        inp.type = 'text';
        oog.className = 'ri-eye-off-line';
    } else {
        inp.type = 'password';
        oog.className = 'ri-eye-line';
    }
}

// ─── Notitie bewerken ─────────────────────────────────────────────────────────
function bewerkNotitie(n) {
    document.getElementById('notitieModalTitel').textContent = 'Notitie bewerken';
    document.getElementById('notitie_id').value = n.id;
    document.getElementById('n_titel').value    = n.titel || '';
    document.getElementById('n_inhoud').value   = n.inhoud || '';
    new bootstrap.Modal(document.getElementById('modalNotitie')).show();
}

// ─── Telefonie type toggle ────────────────────────────────────────────────────
function toggleTelType() {
    document.getElementById('tel_yeastar_velden').style.display = document.getElementById('tel_yeastar').checked ? 'block' : 'none';
}
var _elSimpbx = document.getElementById('tel_simpbx');
if (_elSimpbx) _elSimpbx.addEventListener('change', function() {
    document.getElementById('tel_simpbx_velden').style.display = this.checked ? 'block' : 'none';
});
var _elZiggo = document.getElementById('tel_ziggo');
if (_elZiggo) _elZiggo.addEventListener('change', function() {
    document.getElementById('tel_ziggo_velden').style.display = this.checked ? 'block' : 'none';
});
var _elRoutit = document.getElementById('tel_routit');
if (_elRoutit) _elRoutit.addEventListener('change', function() {
    document.getElementById('tel_routit_velden').style.display = this.checked ? 'block' : 'none';
});

// ─── Toggle wachtwoord veld in modal ─────────────────────────────────────────
function toggleVeld(id, btn) {
    var inp = document.getElementById(id);
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.querySelector('i').className = 'ri-eye-off-line';
    } else {
        inp.type = 'password';
        btn.querySelector('i').className = 'ri-eye-line';
    }
}

// ─── Wachtwoord categorie toggle ─────────────────────────────────────────────
function toggleWwCategorie() {
    var sel = document.getElementById('ww_categorie');
    var div = document.getElementById('ww_categorie_anders_veld');
    var inp = document.getElementById('ww_categorie_anders');
    if (sel.value === 'anders') {
        div.style.display = 'block';
        inp.required = true;
    } else {
        div.style.display = 'none';
        inp.required = false;
    }
}

// ─── Provider "Anders" veld ───────────────────────────────────────────────────
function toggleAndersVeld() {
    var sel = document.getElementById('int_provider');
    var div = document.getElementById('anders_veld');
    div.style.display = sel.value === 'Anders' ? 'block' : 'none';
}

// ─── Internet verbinding bewerken ─────────────────────────────────────────────
function nieuwInternet() {
    document.getElementById('internetModalTitel').textContent = 'Internet verbinding toevoegen';
    document.getElementById('int_id').value             = '';
    document.getElementById('int_omschrijving').value   = '';
    document.getElementById('int_provider').value       = '';
    document.getElementById('int_provider_anders').value= '';
    document.getElementById('int_type').value           = '';
    document.getElementById('int_down').value           = '';
    document.getElementById('int_up').value             = '';
    document.getElementById('int_ip').value             = '';
    document.getElementById('int_4g').checked           = false;
    document.getElementById('int_primair').checked      = false;
    document.getElementById('int_contract').value       = '';
    document.getElementById('int_notities').value       = '';
    document.getElementById('int_pppoe_user').value     = '';
    document.getElementById('int_pppoe_ww').value       = '';
    document.getElementById('int_pppoe_ww').placeholder = '';
    document.getElementById('int_vlan').value           = '';
    toggleAndersVeld();
    new bootstrap.Modal(document.getElementById('modalInternet')).show();
}

function bewerkInternet(iv) {
    document.getElementById('internetModalTitel').textContent = 'Internet verbinding bewerken';
    document.getElementById('int_id').value             = iv.id;
    document.getElementById('int_omschrijving').value   = iv.omschrijving || '';
    document.getElementById('int_provider').value       = iv.provider || '';
    document.getElementById('int_provider_anders').value= iv.provider_anders || '';
    document.getElementById('int_type').value           = iv.type || '';
    document.getElementById('int_down').value           = iv.snelheid_down || '';
    document.getElementById('int_up').value             = iv.snelheid_up || '';
    document.getElementById('int_ip').value             = iv.ip_adres || '';
    document.getElementById('int_4g').checked           = !!iv.backup_4g;
    document.getElementById('int_primair').checked      = !!iv.is_primair;
    document.getElementById('int_contract').value       = iv.contract_datum || '';
    document.getElementById('int_notities').value       = iv.notities || '';
    document.getElementById('int_pppoe_user').value     = iv.pppoe_gebruiker || '';
    document.getElementById('int_pppoe_ww').value       = '';
    document.getElementById('int_pppoe_ww').placeholder = iv.heeft_pppoe_ww ? 'Laat leeg om ongewijzigd te laten' : '';
    document.getElementById('int_vlan').value           = iv.vlan_id || '';
    toggleAndersVeld();
    new bootstrap.Modal(document.getElementById('modalInternet')).show();
}

// ─── Yeastar modal ────────────────────────────────────────────────────────────
function resetYeastarModal() {
    document.getElementById('yeastarModalTitel').textContent = 'Yeastar centrale toevoegen';
    document.getElementById('yeastar_id').value  = '';
    document.getElementById('ys_model').value    = '';
    document.getElementById('ys_ip').value       = '';
    document.getElementById('ys_poort').value    = '8088';
    document.getElementById('ys_url').value      = '';
    document.getElementById('ys_gebruiker').value= 'admin';
    document.getElementById('ys_ww').value       = '';
    document.getElementById('ys_firmware').value = '';
    document.getElementById('ys_notities').value = '';
}

function bewerkYeastar(ys) {
    document.getElementById('yeastarModalTitel').textContent = 'Yeastar centrale bewerken';
    document.getElementById('yeastar_id').value  = ys.id;
    document.getElementById('ys_model').value    = ys.model || '';
    document.getElementById('ys_ip').value       = ys.ip_adres || '';
    document.getElementById('ys_poort').value    = ys.poort || '8088';
    document.getElementById('ys_url').value      = ys.admin_url || '';
    document.getElementById('ys_gebruiker').value= ys.admin_gebruiker || '';
    document.getElementById('ys_ww').value       = '';
    document.getElementById('ys_firmware').value = ys.firmware || '';
    document.getElementById('ys_notities').value = ys.notities || '';
    new bootstrap.Modal(document.getElementById('modalYeastar')).show();
}

// ─── O365 licentie modal ─────────────────────────────────────────────────────
function resetLicentieModal() {
    document.getElementById('licentieModalTitel').textContent = 'Licentie toevoegen';
    document.getElementById('lic_id').value = '';
    document.getElementById('lic_aantal').value = 1;
}
function bewerkLicentie(lic) {
    document.getElementById('licentieModalTitel').textContent = 'Licentie bewerken';
    document.getElementById('lic_id').value    = lic.id;
    document.getElementById('lic_type').value  = lic.licentie_type || '';
    document.getElementById('lic_aantal').value= lic.aantal || 1;
    new bootstrap.Modal(document.getElementById('modalO365Licentie')).show();
}

// ─── O365 gebruiker modal ─────────────────────────────────────────────────────
function resetGebruikerModal() {
    document.getElementById('gebruikerModalTitel').textContent = 'Gebruiker toevoegen';
    document.getElementById('gu_id').value          = '';
    document.getElementById('gu_naam').value        = '';
    document.getElementById('gu_email').value       = '';
    document.getElementById('gu_ww').value          = '';
    document.getElementById('gu_rol').value         = 'Gebruiker';
    document.getElementById('gu_licentie').value    = '';
    document.getElementById('gu_notities').value    = '';
}
function bewerkGebruiker(gu) {
    document.getElementById('gebruikerModalTitel').textContent = 'Gebruiker bewerken';
    document.getElementById('gu_id').value          = gu.id;
    document.getElementById('gu_naam').value        = gu.naam || '';
    document.getElementById('gu_email').value       = gu.email || '';
    document.getElementById('gu_ww').value          = '';
    document.getElementById('gu_rol').value         = gu.rol || 'Gebruiker';
    document.getElementById('gu_licentie').value    = gu.licentie_type || '';
    document.getElementById('gu_notities').value    = gu.notities || '';
    new bootstrap.Modal(document.getElementById('modalO365Gebruiker')).show();
}

// ─── O365 gebruiker wachtwoord tonen/kopiëren ─────────────────────────────────
var o365gCache = {};
function toggleO365GWw(id, btn) {
    var span = document.querySelector('.ww-tekst[data-id="o365g_' + id + '"]');
    if (!span) return;
    if (span.dataset.zichtbaar === '1') {
        span.textContent = '••••••';
        span.dataset.zichtbaar = '0';
        btn.querySelector('i').className = 'ri-eye-line';
        return;
    }
    if (o365gCache[id]) {
        span.textContent = o365gCache[id];
        span.dataset.zichtbaar = '1';
        btn.querySelector('i').className = 'ri-eye-off-line';
        return;
    }
    fetch('<?= $base ?>/o365/gebruiker_toon.php?id=' + id + '&csrf=<?= h(csrf_token()) ?>')
        .then(r => r.json())
        .then(data => {
            if (data.ww) {
                o365gCache[id] = data.ww;
                span.textContent = data.ww;
                span.dataset.zichtbaar = '1';
                btn.querySelector('i').className = 'ri-eye-off-line';
            }
        });
}
function kopieerO365GWw(id, btn) {
    var doCopy = function(ww) {
        navigator.clipboard.writeText(ww);
        var i = btn.querySelector('i');
        i.className = 'ri-check-line';
        setTimeout(() => i.className = 'ri-file-copy-line', 1500);
    };
    if (o365gCache[id]) { doCopy(o365gCache[id]); return; }
    fetch('<?= $base ?>/o365/gebruiker_toon.php?id=' + id + '&csrf=<?= h(csrf_token()) ?>')
        .then(r => r.json())
        .then(data => { if (data.ww) { o365gCache[id] = data.ww; doCopy(data.ww); } });
}

// ─── O365 wachtwoord tonen/kopiëren ──────────────────────────────────────────
var o365Cache = null;
function toggleO365Ww(klant_id, btn) {
    var span = document.querySelector('.ww-tekst[data-id="o365_' + klant_id + '"]');
    if (!span) return;
    if (span.dataset.zichtbaar === '1') {
        span.textContent = '••••••••';
        span.dataset.zichtbaar = '0';
        btn.querySelector('i').className = 'ri-eye-line';
        return;
    }
    if (o365Cache) {
        span.textContent = o365Cache;
        span.dataset.zichtbaar = '1';
        btn.querySelector('i').className = 'ri-eye-off-line';
        return;
    }
    fetch('<?= $base ?>/o365/toon.php?id=' + klant_id + '&csrf=<?= h(csrf_token()) ?>')
        .then(r => r.json())
        .then(data => {
            if (data.ww) {
                o365Cache = data.ww;
                span.textContent = data.ww;
                span.dataset.zichtbaar = '1';
                btn.querySelector('i').className = 'ri-eye-off-line';
            }
        });
}

function kopieerO365Ww(klant_id, btn) {
    var doCopy = function(ww) {
        navigator.clipboard.writeText(ww);
        var i = btn.querySelector('i');
        i.className = 'ri-check-line';
        setTimeout(() => i.className = 'ri-file-copy-line', 1500);
    };
    if (o365Cache) { doCopy(o365Cache); return; }
    fetch('<?= $base ?>/o365/toon.php?id=' + klant_id + '&csrf=<?= h(csrf_token()) ?>')
        .then(r => r.json())
        .then(data => { if (data.ww) { o365Cache = data.ww; doCopy(data.ww); } });
}

// ─── Yeastar wachtwoord tonen/kopiëren ───────────────────────────────────────
var ysCache = {};
function toggleYsWw(id, btn) {
    var span = document.querySelector('.ww-tekst[data-id="ys_' + id + '"]');
    if (!span) return;
    if (span.dataset.zichtbaar === '1') {
        span.textContent = '••••••••';
        span.dataset.zichtbaar = '0';
        btn.querySelector('i').className = 'ri-eye-line';
        return;
    }
    if (ysCache[id]) {
        span.textContent = ysCache[id];
        span.dataset.zichtbaar = '1';
        btn.querySelector('i').className = 'ri-eye-off-line';
        return;
    }
    fetch('<?= $base ?>/yeastar/toon.php?id=' + id + '&csrf=<?= h(csrf_token()) ?>')
        .then(r => r.json())
        .then(data => {
            if (data.ww) {
                ysCache[id] = data.ww;
                span.textContent = data.ww;
                span.dataset.zichtbaar = '1';
                btn.querySelector('i').className = 'ri-eye-off-line';
            }
        });
}

function kopieerYsWw(id, btn) {
    var doCopy = function(ww) {
        navigator.clipboard.writeText(ww);
        var i = btn.querySelector('i');
        i.className = 'ri-check-line';
        setTimeout(() => i.className = 'ri-file-copy-line', 1500);
    };
    if (ysCache[id]) { doCopy(ysCache[id]); return; }
    fetch('<?= $base ?>/yeastar/toon.php?id=' + id + '&csrf=<?= h(csrf_token()) ?>')
        .then(r => r.json())
        .then(data => { if (data.ww) { ysCache[id] = data.ww; doCopy(data.ww); } });
}

// ─── Simpbx wachtwoord tonen/kopiëren ────────────────────────────────────────
var sbxCache = null;
function toggleSbxWw(klant_id, btn) {
    var span = document.querySelector('.ww-tekst[data-id="sbx_' + klant_id + '"]');
    if (!span) return;
    if (span.dataset.zichtbaar === '1') {
        span.textContent = '••••••••';
        span.dataset.zichtbaar = '0';
        btn.querySelector('i').className = 'ri-eye-line';
        return;
    }
    if (sbxCache) {
        span.textContent = sbxCache;
        span.dataset.zichtbaar = '1';
        btn.querySelector('i').className = 'ri-eye-off-line';
        return;
    }
    fetch('<?= $base ?>/simpbx/toon.php?id=' + klant_id + '&csrf=<?= h(csrf_token()) ?>')
        .then(r => r.json())
        .then(data => {
            if (data.ww) {
                sbxCache = data.ww;
                span.textContent = data.ww;
                span.dataset.zichtbaar = '1';
                btn.querySelector('i').className = 'ri-eye-off-line';
            }
        });
}

function kopieerSbxWw(klant_id, btn) {
    var doCopy = function(ww) {
        navigator.clipboard.writeText(ww);
        var i = btn.querySelector('i');
        i.className = 'ri-check-line';
        setTimeout(() => i.className = 'ri-file-copy-line', 1500);
    };
    if (sbxCache) { doCopy(sbxCache); return; }
    fetch('<?= $base ?>/simpbx/toon.php?id=' + klant_id + '&csrf=<?= h(csrf_token()) ?>')
        .then(r => r.json())
        .then(data => { if (data.ww) { sbxCache = data.ww; doCopy(data.ww); } });
}
</script>

<!-- Modal: Klant bewerken -->
<div class="modal fade" id="modalBewerkKlant" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold">Klant bewerken</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" action="<?= $base ?>/klanten/index.php" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="bewerken_id" value="<?= $id ?>">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Naam <span class="text-danger">*</span></label>
                            <input type="text" name="naam" class="form-control rounded-3" value="<?= h($klant['naam']) ?>" required>
                            <div class="invalid-feedback">Vul een naam in.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Bedrijfsnaam</label>
                            <input type="text" name="bedrijf" class="form-control rounded-3" value="<?= h($klant['bedrijf'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">E-mail</label>
                            <input type="email" name="email" class="form-control rounded-3" value="<?= h($klant['email'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Telefoon</label>
                            <input type="text" name="telefoon" class="form-control rounded-3" value="<?= h($klant['telefoon'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Adres</label>
                            <input type="text" name="adres" class="form-control rounded-3" value="<?= h($klant['adres'] ?? '') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-medium">Postcode</label>
                            <input type="text" name="postcode" class="form-control rounded-3" value="<?= h($klant['postcode'] ?? '') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-medium">Plaats</label>
                            <input type="text" name="stad" class="form-control rounded-3" value="<?= h($klant['stad'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Website</label>
                            <input type="text" name="website" class="form-control rounded-3" value="<?= h($klant['website'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Intelly ID</label>
                            <input type="text" name="intra_id" class="form-control rounded-3" value="<?= h($klant['intra_id'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Intranet ID</label>
                            <input type="text" name="intranet_id" class="form-control rounded-3" value="<?= h($klant['intranet_id'] ?? '') ?>">
                        </div>
                        <?php
                        $vaste_beheerders = ['Connect4IT','Lars Manders','Frank Lendering','Bitcom','Kirkels','Academy'];
                        $huidig_beheerder = $klant['beheerder'] ?? '';
                        $is_anders_bk = !in_array($huidig_beheerder, array_merge([''], $vaste_beheerders));
                        ?>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Beheerder</label>
                            <select name="beheerder" id="bk_beheerder" class="form-select rounded-3" onchange="toggleBewerkBeheerder()">
                                <option value="">— Geen —</option>
                                <?php foreach ($vaste_beheerders as $b): ?>
                                <option value="<?= $b ?>" <?= $huidig_beheerder === $b ? 'selected' : '' ?>><?= $b ?></option>
                                <?php endforeach; ?>
                                <option value="anders" <?= $is_anders_bk ? 'selected' : '' ?>>Anders...</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6" id="bk_beheerder_anders" style="display:<?= $is_anders_bk ? 'block' : 'none' ?>;">
                            <label class="form-label fw-medium">Beheerder naam</label>
                            <input type="text" name="beheerder_anders" class="form-control rounded-3" value="<?= $is_anders_bk ? h($huidig_beheerder) : '' ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">DrayTek ACS Device ID</label>
                            <input type="text" name="acs_network_id" class="form-control rounded-3"
                                   placeholder="bv. 9577"
                                   value="<?= h($klant['acs_network_id'] ?? '') ?>">
                            <div class="form-text small">Getal uit de URL in VigorACS: /device/&lt;id&gt;/device-dashboard</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">DrayTek device-naam</label>
                            <input type="text" name="acs_device_naam" class="form-control rounded-3"
                                   placeholder="bv. 2120Fn_Finance Beheer"
                                   value="<?= h($klant['acs_device_naam'] ?? '') ?>">
                            <div class="form-text small">Exacte naam zoals in VigorACS (optioneel).</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">VPS</label>
                            <select name="vps" class="form-select rounded-3">
                                <option value="">— Geen VPS —</option>
                                <?php foreach (['vps1','vps2','vps3','vps4','vps5','vps6'] as $v): ?>
                                <option value="<?= $v ?>.connect4it.hix.nl" <?= ($klant['vps'] ?? '') === $v.'.connect4it.hix.nl' ? 'selected' : '' ?>><?= $v ?>.connect4it.hix.nl</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Notities</label>
                            <textarea name="notities" class="form-control rounded-3" rows="3"><?= h($klant['notities'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 rounded-3" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-primary flex-grow-1 rounded-3">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
function openBewerkKlant() {
    new bootstrap.Modal(document.getElementById('modalBewerkKlant')).show();
}

function openAcs(deviceNaam, btn) {
    // VigorACS ondersteunt geen deeplinks — we openen root en kopieren de naam zodat
    // de gebruiker 'm kan plakken in de zoekbalk van VigorACS.
    window.open('https://cloudx1.draytek.nl/web/nms/', '_blank', 'noopener');
    if (deviceNaam && navigator.clipboard) {
        navigator.clipboard.writeText(deviceNaam).then(function() {
            var origineel = btn.innerHTML;
            btn.innerHTML = '<i class="ri-check-line"></i> Naam gekopieerd';
            setTimeout(function() { btn.innerHTML = origineel; }, 2000);
        });
    }
}
function toggleBewerkBeheerder() {
    var sel = document.getElementById('bk_beheerder');
    document.getElementById('bk_beheerder_anders').style.display = sel.value === 'anders' ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
