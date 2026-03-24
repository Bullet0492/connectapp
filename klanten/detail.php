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

$service = $db->prepare("SELECT s.*, a.qr_code AS apparaat_qr FROM service_historie s LEFT JOIN apparaten a ON a.id = s.apparaat_id WHERE s.klant_id = ? ORDER BY s.datum DESC, s.id DESC");
$service->execute([$id]);
$service = $service->fetchAll();

$bestanden = $db->prepare('SELECT * FROM klant_bestanden WHERE klant_id = ? ORDER BY aangemaakt_op DESC');
$bestanden->execute([$id]);
$bestanden = $bestanden->fetchAll();

$contracten = $db->prepare('SELECT * FROM contracten WHERE klant_id = ? ORDER BY eind_datum ASC');
$contracten->execute([$id]);
$contracten = $contracten->fetchAll();

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
    <a href="index.php?bewerken=<?= $id ?>" class="btn btn-outline-secondary btn-sm">
        <i class="ri-edit-line"></i> Bewerken
    </a>
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
</ul>

<!-- ─── Tab: Overzicht ────────────────────────────────────────────────────── -->
<?php if ($actieve_tab === 'overzicht'): ?>
<div class="row g-3">
    <div class="col-md-6">
        <div class="bg-white rounded-3 border p-4">
            <h6 class="fw-bold mb-3">Contactinformatie</h6>
            <table class="table table-sm table-borderless mb-0">
                <tr><td class="text-muted" style="width:40%">E-mail</td><td><?= h($klant['email'] ?: '—') ?></td></tr>
                <tr><td class="text-muted">Telefoon</td><td><?= h($klant['telefoon'] ?: '—') ?></td></tr>
                <?php if (!empty($klant['website'])): ?>
                <tr><td class="text-muted">Website</td><td><a href="<?= h($klant['website']) ?>" target="_blank"><?= h($klant['website']) ?></a></td></tr>
                <?php endif; ?>
                <tr><td class="text-muted">Adres</td><td>
                    <?= h($klant['adres'] ?: '—') ?><br>
                    <?= h($klant['postcode'] . ' ' . $klant['stad']) ?>
                </td></tr>
                <?php if (!empty($klant['intra_id'])): ?>
                <tr><td class="text-muted">Intelly ID</td><td><?= h($klant['intra_id']) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="bg-white rounded-3 border p-4">
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
        <?php if (!empty($klant['notities'])): ?>
        <div class="bg-white rounded-3 border p-4 mt-3">
            <h6 class="fw-bold mb-2">Notities</h6>
            <p class="mb-0 text-muted small" style="white-space:pre-line;"><?= h($klant['notities']) ?></p>
        </div>
        <?php endif; ?>
    </div>
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
        <a href="<?= $base ?>/qr/labels.php?klant_id=<?= $id ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
            <i class="ri-printer-line"></i> Labels afdrukken
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
                    <span class="fw-bold" style="font-size:15px;"><?= h($a['qr_code']) ?></span>
                    <span class="badge badge-<?= $a['status'] ?> ms-2 rounded-pill" style="font-size:10px;"><?= h($a['status']) ?></span>
                </div>
                <div class="d-flex gap-1">
                    <a href="<?= $base ?>/qr/labels.php?apparaat_id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="Label afdrukken">
                        <i class="ri-qr-code-line"></i>
                    </a>
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
            <?php if (!empty($a['garantie_tot'])): ?>
            <div class="small text-muted"><i class="ri-shield-check-line"></i> Garantie t/m <?= h($a['garantie_tot']) ?></div>
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
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Garantie tot</label>
                            <input type="date" name="garantie_tot" id="a_garantie_tot" class="form-control rounded-3">
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
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalWachtwoord">+ Toevoegen</button>
</div>
<?php if (empty($inloggegevens)): ?>
    <div class="bg-white rounded-3 border p-4 text-center text-muted">Nog geen inloggegevens.</div>
<?php else:
    $categorieen = ['netwerk' => [], 'server' => [], 'cloud' => [], 'portaal' => [], 'overig' => []];
    foreach ($inloggegevens as $ig) {
        $categorieen[$ig['categorie']][] = $ig;
    }
    $cat_labels = ['netwerk' => 'Netwerk / Router', 'server' => 'Server / Windows', 'cloud' => 'Cloud / SaaS', 'portaal' => 'Portalen', 'overig' => 'Overig'];
    foreach ($categorieen as $cat => $items):
        if (empty($items)) continue;
?>
<div class="mb-4">
    <h6 class="text-muted fw-semibold mb-2" style="font-size:12px;text-transform:uppercase;letter-spacing:.8px;"><?= $cat_labels[$cat] ?></h6>
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
                            <select name="categorie" id="ww_categorie" class="form-select rounded-3">
                                <option value="netwerk">Netwerk / Router</option>
                                <option value="server">Server / Windows</option>
                                <option value="cloud">Cloud / SaaS</option>
                                <option value="portaal">Portalen</option>
                                <option value="overig">Overig</option>
                            </select>
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
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalService">+ Toevoegen</button>
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
                    <?php if (!empty($s['apparaat_qr'])): ?>
                    <span class="badge bg-light text-muted border" style="font-size:10px;"><?= h($s['apparaat_qr']) ?></span>
                    <?php endif; ?>
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
                                <option value="<?= $a['id'] ?>"><?= h($a['qr_code'] . ' — ' . trim($a['merk'] . ' ' . $a['model'])) ?></option>
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

<?php endif; ?>

<script>
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
    document.getElementById('a_garantie_tot').value  = a.garantie_tot || '';
    document.getElementById('a_locatie').value    = a.locatie || '';
    document.getElementById('a_mac_adres').value  = a.mac_adres || '';
    document.getElementById('a_ip_adres').value   = a.ip_adres || '';
    document.getElementById('a_firmware').value   = a.firmware || '';
    document.getElementById('a_notities').value   = a.notities || '';
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
function bewerkWachtwoord(ig) {
    document.getElementById('wwModalTitel').textContent = 'Inloggegevens bewerken';
    document.getElementById('ig_id').value              = ig.id;
    document.getElementById('ww_label').value           = ig.label || '';
    document.getElementById('ww_categorie').value       = ig.categorie || 'overig';
    document.getElementById('ww_gebruikersnaam').value  = ig.gebruikersnaam || '';
    document.getElementById('ww_wachtwoord').value      = '';
    document.getElementById('ww_url').value             = ig.url || '';
    document.getElementById('ww_notities').value        = ig.notities || '';
    new bootstrap.Modal(document.getElementById('modalWachtwoord')).show();
}

// ─── Service bewerken ─────────────────────────────────────────────────────────
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
