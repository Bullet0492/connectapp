<?php
$paginatitel = 'Klanten';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();

$db   = db();
$zoek = trim($_GET['zoek'] ?? '');
$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$per_pagina = 15;

// POST: opslaan of bijwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $velden = ['naam','bedrijf','adres','postcode','stad','telefoon','email','website','intra_id','intranet_id','notities','vps','beheerder','acs_network_id','acs_device_naam'];
    if (trim($_POST['beheerder'] ?? '') === 'anders') {
        $_POST['beheerder'] = trim($_POST['beheerder_anders'] ?? '') ?: null;
    }
    $data = [];
    foreach ($velden as $v) {
        $data[$v] = trim($_POST[$v] ?? '');
    }
    $bewerken_id = (int)($_POST['bewerken_id'] ?? 0);

    // Intranet ID is verplicht bij aanmaken (niet bij bewerken, want oude klanten kunnen leeg zijn)
    if (!$bewerken_id && $data['intranet_id'] === '') {
        flash_set('fout', 'Intranet ID is verplicht bij het aanmaken van een nieuwe klant.');
        header('Location: index.php');
        exit;
    }

    if ($data['naam'] !== '') {
        if ($bewerken_id) {
            $db->prepare("UPDATE klanten SET naam=:naam, bedrijf=:bedrijf, adres=:adres, postcode=:postcode,
                stad=:stad, telefoon=:telefoon, email=:email, website=:website, intra_id=:intra_id, intranet_id=:intranet_id, notities=:notities, vps=:vps, beheerder=:beheerder,
                acs_network_id=:acs_network_id, acs_device_naam=:acs_device_naam
                WHERE id=:id")->execute(array_merge($data, ['id' => $bewerken_id]));
            log_actie('klant_bijgewerkt', 'Naam: ' . $data['naam'] . ', ID: ' . $bewerken_id);
            flash_set('succes', 'Klant bijgewerkt.');
            header('Location: detail.php?id=' . $bewerken_id);
            exit;
        } else {
            $db->prepare("INSERT INTO klanten (naam,bedrijf,adres,postcode,stad,telefoon,email,website,intra_id,intranet_id,notities,vps,beheerder,acs_network_id,acs_device_naam)
                VALUES (:naam,:bedrijf,:adres,:postcode,:stad,:telefoon,:email,:website,:intra_id,:intranet_id,:notities,:vps,:beheerder,:acs_network_id,:acs_device_naam)")
                ->execute($data);
            log_actie('klant_aangemaakt', 'Naam: ' . $data['naam']);
            flash_set('succes', 'Klant aangemaakt.');
        }
    }
    header('Location: index.php');
    exit;
}

// Zoekquery
$whereStr = '';
$params   = [];
if ($zoek !== '') {
    $whereStr = "WHERE naam LIKE ? OR bedrijf LIKE ? OR email LIKE ? OR telefoon LIKE ? OR adres LIKE ? OR postcode LIKE ? OR stad LIKE ? OR intra_id LIKE ? OR intranet_id LIKE ?";
    $params   = array_fill(0, 9, "%$zoek%");
}

$telStmt = $db->prepare("SELECT COUNT(*) FROM klanten $whereStr");
$telStmt->execute($params);
$totaal = (int)$telStmt->fetchColumn();
$totaal_paginas = max(1, (int)ceil($totaal / $per_pagina));
$pagina = min($pagina, $totaal_paginas);
$offset = ($pagina - 1) * $per_pagina;

$stmt = $db->prepare("SELECT * FROM klanten $whereStr ORDER BY CASE WHEN naam REGEXP '^[A-Za-z]' THEN 0 ELSE 1 END, naam LIMIT $per_pagina OFFSET $offset");
$stmt->execute($params);
$klanten = $stmt->fetchAll();

// Bewerken: laad klantdata
$bewerken_klant = null;
$bewerken_id = (int)($_GET['bewerken'] ?? 0);
if ($bewerken_id) {
    $s = $db->prepare('SELECT * FROM klanten WHERE id = ?');
    $s->execute([$bewerken_id]);
    $bewerken_klant = $s->fetch();
}

// Volgend intranet-ID: min 3000, anders hoogste bestaande + 1
$max_intranet = (int)$db->query("SELECT COALESCE(MAX(CAST(intranet_id AS UNSIGNED)), 0) FROM klanten")->fetchColumn();
$volgende_intranet_id = max(3000, $max_intranet + 1);

function klant_pagina_url(int $p): string {
    global $zoek;
    $params = array_filter(['zoek' => $zoek, 'pagina' => $p > 1 ? $p : ''], fn($v) => $v !== '');
    return 'index.php?' . http_build_query($params);
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="mb-4">
    <h4 class="fw-bold mb-1">Klanten</h4>
    <p class="text-muted mb-0"><?= $totaal ?> klanten geregistreerd</p>
</div>

<div class="mb-4 d-flex gap-2 flex-wrap">
    <button class="btn btn-primary px-4" id="btn-nieuwe-klant" data-bs-toggle="modal" data-bs-target="#modalKlant">
        + Nieuwe klant
    </button>
    <a href="<?= $base ?>/qr/scan.php" class="btn btn-outline-secondary px-3">
        <i class="ri-qr-scan-2-line me-1"></i> QR scannen
    </a>
</div>

<!-- Zoekbalk -->
<div class="mb-4">
    <form method="get" id="zoekform">
        <div class="input-group">
            <span class="input-group-text bg-white border-end-0">
                <i class="ri-search-line" style="font-size:16px;color:#6c757d;"></i>
            </span>
            <input type="search" name="zoek" id="zoek_input" class="form-control border-start-0 border-end-0 ps-0"
                   placeholder="Zoek op naam, bedrijf, adres, telefoon, e-mail..."
                   value="<?= h($zoek) ?>">
            <?php if ($zoek !== ''): ?>
            <a href="index.php" class="input-group-text bg-white text-muted" title="Wissen">
                <i class="ri-close-line" style="font-size:16px;"></i>
            </a>
            <?php else: ?>
            <button type="submit" class="input-group-text bg-white text-muted border-start-0">
                <i class="ri-arrow-right-line" style="font-size:16px;"></i>
            </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Klantenkaarten -->
<?php if (empty($klanten)): ?>
    <div class="text-center text-muted py-5">Geen klanten gevonden.</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($klanten as $k): ?>
    <div class="col-md-6 col-lg-4">
        <div class="bg-white rounded-3 border p-4 h-100 d-flex flex-column">
            <div class="fw-bold mb-1"><?= h($k['naam']) ?></div>
            <?php if (!empty($k['bedrijf'])): ?>
            <div class="d-flex align-items-center gap-2 text-muted small mb-1">
                <i class="ri-building-line" style="font-size:13px;"></i><?= h($k['bedrijf']) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($k['email'])): ?>
            <div class="d-flex align-items-center gap-2 text-muted small mb-1">
                <i class="ri-mail-line" style="font-size:13px;"></i><?= h($k['email']) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($k['telefoon'])): ?>
            <div class="d-flex align-items-center gap-2 text-muted small mb-1">
                <i class="ri-phone-line" style="font-size:13px;"></i><?= h($k['telefoon']) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($k['adres'])): ?>
            <div class="d-flex align-items-center gap-2 text-muted small mb-1">
                <i class="ri-map-pin-line" style="font-size:13px;"></i>
                <?= h($k['adres']) ?><?= !empty($k['postcode']) ? ', ' . h($k['postcode']) : '' ?><?= !empty($k['stad']) ? ' ' . h($k['stad']) : '' ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($k['intra_id'])): ?>
            <div class="d-flex align-items-center gap-2 text-muted small mb-1">
                <i class="ri-hashtag" style="font-size:13px;"></i>Intelly: <?= h($k['intra_id']) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($k['intranet_id'])): ?>
            <div class="d-flex align-items-center gap-2 text-muted small mb-1">
                <i class="ri-hashtag" style="font-size:13px;"></i>Intranet: <?= h($k['intranet_id']) ?>
            </div>
            <?php endif; ?>

            <div class="d-flex gap-2 mt-auto pt-3 border-top flex-wrap">
                <a href="<?= $base ?>/klanten/detail.php?id=<?= $k['id'] ?>" class="btn btn-sm btn-primary d-flex align-items-center gap-1">
                    <i class="ri-eye-line" style="font-size:13px;"></i>
                    Detail
                </a>
                <a href="index.php?bewerken=<?= $k['id'] ?>" class="btn btn-sm btn-outline-secondary">Bewerken</a>
                <a href="<?= $base ?>/qr/label_klant.php?id=<?= $k['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="QR-label afdrukken">
                    <i class="ri-qr-code-line"></i>
                </a>
                <?php if ($gebruiker['rol'] === 'admin'): ?>
                <a href="verwijderen.php?id=<?= $k['id'] ?>"
                   class="btn btn-sm btn-outline-danger ms-auto"
                   onclick="return confirm('Klant en alle bijbehorende gegevens verwijderen?')">
                    <i class="ri-delete-bin-line"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Paginering -->
<?php if ($totaal_paginas > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center flex-wrap">
        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= h(klant_pagina_url($pagina - 1)) ?>">Vorige</a>
        </li>
        <?php foreach (pagina_reeks($pagina, $totaal_paginas) as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php else: ?>
            <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
                <a class="page-link" href="<?= h(klant_pagina_url($p)) ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $pagina >= $totaal_paginas ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= h(klant_pagina_url($pagina + 1)) ?>">Volgende</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
<?php endif; ?>

<!-- Modal: Nieuwe / bewerken klant -->
<div class="modal fade" id="modalKlant" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-3 border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold"><?= $bewerken_klant ? 'Klant bewerken' : 'Nieuwe klant' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="bewerken_id" value="<?= $bewerken_klant ? $bewerken_klant['id'] : '' ?>">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Naam <span class="text-danger">*</span></label>
                            <input type="text" name="naam" class="form-control rounded-3" placeholder="Voor- en achternaam"
                                   value="<?= $bewerken_klant ? h($bewerken_klant['naam']) : '' ?>" required>
                            <div class="invalid-feedback">Vul een naam in.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Bedrijfsnaam</label>
                            <input type="text" name="bedrijf" class="form-control rounded-3" placeholder="Bedrijfsnaam"
                                   value="<?= $bewerken_klant ? h($bewerken_klant['bedrijf'] ?? '') : '' ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">E-mail</label>
                            <input type="email" name="email" class="form-control rounded-3" placeholder="email@voorbeeld.nl"
                                   value="<?= $bewerken_klant ? h($bewerken_klant['email'] ?? '') : '' ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Telefoon</label>
                            <input type="text" name="telefoon" class="form-control rounded-3" placeholder="06-12345678"
                                   value="<?= $bewerken_klant ? h($bewerken_klant['telefoon'] ?? '') : '' ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Adres</label>
                            <input type="text" name="adres" class="form-control rounded-3" placeholder="Straat en huisnummer"
                                   value="<?= $bewerken_klant ? h($bewerken_klant['adres'] ?? '') : '' ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-medium">Postcode</label>
                            <input type="text" name="postcode" class="form-control rounded-3" placeholder="1234 AB"
                                   value="<?= $bewerken_klant ? h($bewerken_klant['postcode'] ?? '') : '' ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-medium">Plaats</label>
                            <input type="text" name="stad" class="form-control rounded-3" placeholder="Plaatsnaam"
                                   value="<?= $bewerken_klant ? h($bewerken_klant['stad'] ?? '') : '' ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Website</label>
                            <input type="text" name="website" class="form-control rounded-3" placeholder="https://..."
                                   value="<?= $bewerken_klant ? h($bewerken_klant['website'] ?? '') : '' ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Intelly ID</label>
                            <input type="text" name="intra_id" class="form-control rounded-3" placeholder="Intelly ID"
                                   value="<?= $bewerken_klant ? h($bewerken_klant['intra_id'] ?? '') : '' ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Intranet ID <span class="text-danger" id="intranet_id_required_star"<?= $bewerken_klant ? ' style="display:none;"' : '' ?>>*</span></label>
                            <input type="text" name="intranet_id" id="klant_intranet_id" class="form-control rounded-3"
                                   placeholder="Intranet ID"
                                   data-volgende="<?= h((string)$volgende_intranet_id) ?>"
                                   value="<?= $bewerken_klant ? h($bewerken_klant['intranet_id'] ?? '') : h((string)$volgende_intranet_id) ?>"
                                   <?= $bewerken_klant ? '' : 'required' ?>>
                            <div class="form-text small">
                                <?= $bewerken_klant ? 'Aanpasbaar voor oude klanten.' : 'Volgende vrije nummer. Mag je overschrijven voor oude klanten.' ?>
                            </div>
                            <div class="invalid-feedback">Intranet ID is verplicht.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Beheerder</label>
                            <select name="beheerder" id="klant_beheerder" class="form-select rounded-3" onchange="toggleBeheerderAnders()">
                                <option value="">— Geen —</option>
                                <?php foreach (['Connect4IT','Lars Manders','Frank Lendering','Bitcom','Kirkels','Academy'] as $b): ?>
                                <?php $huidig = $bewerken_klant['beheerder'] ?? ''; ?>
                                <option value="<?= $b ?>" <?= $huidig === $b ? 'selected' : '' ?>><?= $b ?></option>
                                <?php endforeach; ?>
                                <?php
                                $vaste = ['','Connect4IT','Lars Manders','Frank Lendering','Bitcom','Kirkels','Academy'];
                                $is_anders = !in_array($bewerken_klant['beheerder'] ?? '', $vaste);
                                ?>
                                <option value="anders" <?= $is_anders ? 'selected' : '' ?>>Anders...</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6" id="beheerder_anders_veld" style="display:<?= $is_anders ? 'block' : 'none' ?>;">
                            <label class="form-label fw-medium">Beheerder naam</label>
                            <input type="text" name="beheerder_anders" id="beheerder_anders_input" class="form-control rounded-3" value="<?= $is_anders ? h($bewerken_klant['beheerder'] ?? '') : '' ?>" placeholder="Naam beheerder...">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">DrayTek ACS Device ID</label>
                            <input type="text" name="acs_network_id" class="form-control rounded-3"
                                   placeholder="bv. 9577"
                                   value="<?= $bewerken_klant ? h($bewerken_klant['acs_network_id'] ?? '') : '' ?>">
                            <div class="form-text small">Getal uit de URL in VigorACS: /device/&lt;id&gt;/device-dashboard</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">DrayTek device-naam</label>
                            <input type="text" name="acs_device_naam" class="form-control rounded-3"
                                   placeholder="bv. 2120Fn_Finance Beheer"
                                   value="<?= $bewerken_klant ? h($bewerken_klant['acs_device_naam'] ?? '') : '' ?>">
                            <div class="form-text small">Exacte naam zoals in VigorACS (optioneel).</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">VPS</label>
                            <select name="vps" class="form-select rounded-3">
                                <option value="">— Geen VPS —</option>
                                <?php foreach (['vps1','vps2','vps3','vps4','vps5','vps6'] as $v): ?>
                                <option value="<?= $v ?>.connect4it.hix.nl" <?= ($bewerken_klant ? ($bewerken_klant['vps'] ?? '') : '') === $v . '.connect4it.hix.nl' ? 'selected' : '' ?>>
                                    <?= $v ?>.connect4it.hix.nl
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Notities</label>
                            <textarea name="notities" class="form-control rounded-3" rows="3" placeholder="Extra info..."><?= $bewerken_klant ? h($bewerken_klant['notities'] ?? '') : '' ?></textarea>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 rounded-3" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-primary flex-grow-1 rounded-3"><?= $bewerken_klant ? 'Opslaan' : 'Toevoegen' ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($bewerken_klant): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new bootstrap.Modal(document.getElementById('modalKlant')).show();
});
</script>
<?php endif; ?>

<script>
(function() {
    var inp = document.getElementById('zoek_input');
    var form = document.getElementById('zoekform');
    if (!inp || !form) return;
    if (inp.value) { inp.focus(); inp.setSelectionRange(inp.value.length, inp.value.length); }
    var timer;
    inp.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(function() { form.submit(); }, 400);
    });
})();

function toggleBeheerderAnders() {
    var sel = document.getElementById('klant_beheerder');
    var div = document.getElementById('beheerder_anders_veld');
    div.style.display = sel.value === 'anders' ? 'block' : 'none';
}

document.getElementById('btn-nieuwe-klant').addEventListener('click', function() {
    var modal = document.getElementById('modalKlant');
    modal.querySelector('.modal-title').textContent = 'Nieuwe klant';
    var form = modal.querySelector('form');
    form.querySelector('[name="bewerken_id"]').value = '';
    ['naam','bedrijf','email','telefoon','adres','postcode','stad','website','intra_id','notities','vps','beheerder','beheerder_anders','acs_network_id','acs_device_naam'].forEach(function(f) {
        var el = form.querySelector('[name="' + f + '"]');
        if (el) el.value = '';
    });
    // Intranet ID: vul met volgende vrije nummer en maak verplicht
    var intra = document.getElementById('klant_intranet_id');
    if (intra) {
        intra.value = intra.dataset.volgende || '';
        intra.setAttribute('required', 'required');
        document.getElementById('intranet_id_required_star').style.display = '';
    }
    document.getElementById('beheerder_anders_veld').style.display = 'none';
    form.classList.remove('was-validated');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
