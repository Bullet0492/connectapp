<?php
$paginatitel = 'Logboek';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_admin();

$db = db();
$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$per_pagina = 40;

$filter_type = $_GET['type'] ?? '';
$filter_user = $_GET['user'] ?? '';

$where  = [];
$params = [];

if ($filter_type) {
    $where[]  = "actie LIKE ?";
    $params[] = '%' . $filter_type . '%';
}
if ($filter_user) {
    $where[]  = "user_naam = ?";
    $params[] = $filter_user;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totaal = (int)$db->prepare("SELECT COUNT(*) FROM logboek $whereSql")->execute($params) ? $db->prepare("SELECT COUNT(*) FROM logboek $whereSql")->execute($params) : 0;
$telStmt = $db->prepare("SELECT COUNT(*) FROM logboek $whereSql");
$telStmt->execute($params);
$totaal = (int)$telStmt->fetchColumn();

$totaal_paginas = max(1, (int)ceil($totaal / $per_pagina));
$pagina  = min($pagina, $totaal_paginas);
$offset  = ($pagina - 1) * $per_pagina;

$stmt = $db->prepare("SELECT * FROM logboek $whereSql ORDER BY aangemaakt_op DESC LIMIT $per_pagina OFFSET $offset");
$stmt->execute($params);
$items = $stmt->fetchAll();

// Klant namen ophalen voor alle klant_id's in details
$klant_ids = [];
foreach ($items as $log) {
    if (preg_match('/Klant ID:\s*(\d+)/', $log['details'] ?? '', $m)) {
        $klant_ids[] = (int)$m[1];
    }
}
$klant_namen = [];
if ($klant_ids) {
    $in = implode(',', array_unique($klant_ids));
    $kq = $db->query("SELECT id, naam FROM klanten WHERE id IN ($in)");
    foreach ($kq->fetchAll() as $k) {
        $klant_namen[$k['id']] = $k['naam'];
    }
}

// Alle gebruikers voor filter dropdown
$gebruikers = $db->query("SELECT DISTINCT user_naam FROM logboek WHERE user_naam IS NOT NULL ORDER BY user_naam")->fetchAll(PDO::FETCH_COLUMN);

// Actie → [label, icoon, kleur]
$actie_map = [
    // Klanten
    'klant_aangemaakt'              => ['Klant aangemaakt',              'ri-building-2-line',       'success'],
    'klant_bijgewerkt'              => ['Klant bijgewerkt',              'ri-building-2-line',       'primary'],
    'klant_verwijderd'              => ['Klant verwijderd',              'ri-building-2-line',       'danger'],
    // Contactpersonen
    'contact_aangemaakt'            => ['Contactpersoon aangemaakt',     'ri-user-add-line',         'success'],
    'contact_bijgewerkt'            => ['Contactpersoon bijgewerkt',     'ri-user-line',             'primary'],
    'contact_verwijderd'            => ['Contactpersoon verwijderd',     'ri-user-unfollow-line',    'danger'],
    // Apparaten
    'apparaat_aangemaakt'           => ['Apparaat aangemaakt',           'ri-computer-line',         'success'],
    'apparaat_bijgewerkt'           => ['Apparaat bijgewerkt',           'ri-computer-line',         'primary'],
    'apparaat_verwijderd'           => ['Apparaat verwijderd',           'ri-computer-line',         'danger'],
    // Wachtwoorden / inloggegevens
    'inloggegevens_aangemaakt'      => ['Wachtwoord aangemaakt',         'ri-lock-password-line',    'success'],
    'inloggegevens_bijgewerkt'      => ['Wachtwoord bijgewerkt',         'ri-lock-password-line',    'primary'],
    'inloggegevens_verwijderd'      => ['Wachtwoord verwijderd',         'ri-lock-password-line',    'danger'],
    'wachtwoord_bekeken'            => ['Wachtwoord bekeken',            'ri-eye-line',              'secondary'],
    'wachtwoord_gewijzigd'          => ['Eigen wachtwoord gewijzigd',    'ri-lock-2-line',           'primary'],
    'wachtwoord_gereset'            => ['Wachtwoord gereset',            'ri-lock-2-line',           'warning'],
    // Notities
    'notitie_aangemaakt'            => ['Notitie aangemaakt',            'ri-sticky-note-line',      'success'],
    'notitie_bijgewerkt'            => ['Notitie bijgewerkt',            'ri-sticky-note-line',      'primary'],
    'notitie_verwijderd'            => ['Notitie verwijderd',            'ri-sticky-note-line',      'danger'],
    // Bestanden
    'bestand_geupload'              => ['Bestand geüpload',              'ri-upload-2-line',         'success'],
    'bestand_gedownload'            => ['Bestand gedownload',            'ri-download-2-line',       'secondary'],
    'bestand_verwijderd'            => ['Bestand verwijderd',            'ri-file-damage-line',      'danger'],
    // Contracten
    'contract_aangemaakt'           => ['Contract aangemaakt',           'ri-file-paper-2-line',     'success'],
    'contract_bijgewerkt'           => ['Contract bijgewerkt',           'ri-file-paper-2-line',     'primary'],
    'contract_verwijderd'           => ['Contract verwijderd',           'ri-file-paper-2-line',     'danger'],
    // Service
    'service_aangemaakt'            => ['Service aangemaakt',            'ri-tools-line',            'success'],
    'service_bijgewerkt'            => ['Service bijgewerkt',            'ri-tools-line',            'primary'],
    'service_verwijderd'            => ['Service verwijderd',            'ri-tools-line',            'danger'],
    // Internet
    'internet_aangemaakt'           => ['Internet aangemaakt',           'ri-wifi-line',             'success'],
    'internet_bijgewerkt'           => ['Internet bijgewerkt',           'ri-wifi-line',             'primary'],
    // Telefonie
    'telefonie_opgeslagen'          => ['Telefonie opgeslagen',          'ri-phone-line',            'primary'],
    'yeastar_aangemaakt'            => ['Yeastar aangemaakt',            'ri-base-station-line',     'success'],
    'yeastar_bijgewerkt'            => ['Yeastar bijgewerkt',            'ri-base-station-line',     'primary'],
    'yeastar_verwijderd'            => ['Yeastar verwijderd',            'ri-base-station-line',     'danger'],
    'yeastar_wachtwoord_bekeken'    => ['Yeastar wachtwoord bekeken',    'ri-eye-line',              'secondary'],
    'simpbx_aangemaakt'             => ['SimPBX aangemaakt',             'ri-phone-line',            'success'],
    'simpbx_bijgewerkt'             => ['SimPBX bijgewerkt',             'ri-phone-line',            'primary'],
    'simpbx_wachtwoord_bekeken'     => ['SimPBX wachtwoord bekeken',     'ri-eye-line',              'secondary'],
    // Office 365
    'o365_aangemaakt'               => ['Office 365 aangemaakt',         'ri-microsoft-line',        'success'],
    'o365_bijgewerkt'               => ['Office 365 bijgewerkt',         'ri-microsoft-line',        'primary'],
    'o365_verwijderd'               => ['Office 365 verwijderd',         'ri-microsoft-line',        'danger'],
    'o365_wachtwoord_bekeken'       => ['Office 365 wachtwoord bekeken', 'ri-eye-line',              'secondary'],
    'o365_gebruiker_aangemaakt'     => ['O365 gebruiker aangemaakt',     'ri-microsoft-line',        'success'],
    'o365_gebruiker_bijgewerkt'     => ['O365 gebruiker bijgewerkt',     'ri-microsoft-line',        'primary'],
    'o365_gebruiker_verwijderd'     => ['O365 gebruiker verwijderd',     'ri-microsoft-line',        'danger'],
    'o365_gebruiker_wachtwoord_bekeken' => ['O365 gebruiker wachtwoord bekeken', 'ri-eye-line',     'secondary'],
    'o365_licentie_opgeslagen'      => ['O365 licentie opgeslagen',      'ri-microsoft-line',        'primary'],
    'o365_licentie_verwijderd'      => ['O365 licentie verwijderd',      'ri-microsoft-line',        'danger'],
    // Gebruikers / 2FA
    'gebruiker_aangemaakt'          => ['Gebruiker aangemaakt',          'ri-shield-user-line',      'success'],
    'gebruiker_verwijderd'          => ['Gebruiker verwijderd',          'ri-shield-user-line',      'danger'],
    'rol_gewijzigd'                 => ['Rol gewijzigd',                 'ri-shield-user-line',      'warning'],
    '2fa_ingeschakeld'              => ['2FA ingeschakeld',              'ri-shield-keyhole-line',   'success'],
    '2fa_uitgeschakeld'             => ['2FA uitgeschakeld',             'ri-shield-keyhole-line',   'danger'],
];

$kleur_badge = [
    'success'   => ['#d1f5e0', '#0a5c2e'],
    'primary'   => ['#dbeafe', '#1d4ed8'],
    'danger'    => ['#fde8e8', '#991b1b'],
    'warning'   => ['#fef3c7', '#92400e'],
    'secondary' => ['#f1f3f5', '#495057'],
];

function log_pagina_url(int $p, array $extra = []): string {
    $q = array_filter(array_merge(['pagina' => $p > 1 ? $p : null], $extra));
    return 'logboek.php' . ($q ? '?' . http_build_query($q) : '');
}

function format_details(string $details, array $klant_namen, string $base): string {
    // Klant ID vervangen door klikbare naam
    return preg_replace_callback('/Klant ID:\s*(\d+)/', function($m) use ($klant_namen, $base) {
        $kid  = (int)$m[1];
        $naam = $klant_namen[$kid] ?? ('Klant #' . $kid);
        return '<a href="' . $base . '/klanten/detail.php?id=' . $kid . '" class="text-decoration-none fw-medium">' . h($naam) . '</a>';
    }, h($details));
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1">Logboek</h4>
        <p class="text-muted mb-0"><?= $totaal ?> activiteiten geregistreerd</p>
    </div>
    <!-- Filter -->
    <form method="get" class="d-flex gap-2 flex-wrap align-items-center">
        <select name="user" class="form-select form-select-sm rounded-3" style="width:auto;" onchange="this.form.submit()">
            <option value="">Alle gebruikers</option>
            <?php foreach ($gebruikers as $u): ?>
            <option value="<?= h($u) ?>" <?= $filter_user === $u ? 'selected' : '' ?>><?= h($u) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="type" class="form-select form-select-sm rounded-3" style="width:auto;" onchange="this.form.submit()">
            <option value="">Alle acties</option>
            <option value="klant"         <?= $filter_type === 'klant'         ? 'selected' : '' ?>>Klanten</option>
            <option value="contact"       <?= $filter_type === 'contact'       ? 'selected' : '' ?>>Contactpersonen</option>
            <option value="apparaat"      <?= $filter_type === 'apparaat'      ? 'selected' : '' ?>>Apparaten</option>
            <option value="inloggegevens" <?= $filter_type === 'inloggegevens' ? 'selected' : '' ?>>Wachtwoorden</option>
            <option value="wachtwoord"    <?= $filter_type === 'wachtwoord'    ? 'selected' : '' ?>>Wachtwoord acties</option>
            <option value="o365"          <?= $filter_type === 'o365'          ? 'selected' : '' ?>>Office 365</option>
            <option value="telefonie"     <?= $filter_type === 'telefonie'     ? 'selected' : '' ?>>Telefonie</option>
            <option value="internet"      <?= $filter_type === 'internet'      ? 'selected' : '' ?>>Internet</option>
            <option value="service"       <?= $filter_type === 'service'       ? 'selected' : '' ?>>Service</option>
            <option value="bestand"       <?= $filter_type === 'bestand'       ? 'selected' : '' ?>>Bestanden</option>
            <option value="notitie"       <?= $filter_type === 'notitie'       ? 'selected' : '' ?>>Notities</option>
            <option value="gebruiker"     <?= $filter_type === 'gebruiker'     ? 'selected' : '' ?>>Gebruikers</option>
        </select>
        <?php if ($filter_type || $filter_user): ?>
        <a href="logboek.php" class="btn btn-sm btn-outline-secondary rounded-3">Wis filter</a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-white rounded-3 border overflow-hidden">
    <?php if (!$items): ?>
    <p class="text-muted text-center py-5 mb-0">Geen activiteiten gevonden.</p>
    <?php else: ?>
    <?php
    $vorige_dag = null;
    foreach ($items as $log):
        $actie    = $log['actie'] ?? '';
        $info     = $actie_map[$actie] ?? [ucwords(str_replace('_', ' ', $actie)), 'ri-information-line', 'secondary'];
        [$label, $icoon, $kleur] = $info;
        [$bg, $fg] = $kleur_badge[$kleur] ?? $kleur_badge['secondary'];
        $dag      = date('d-m-Y', strtotime($log['aangemaakt_op']));
        $tijd     = date('H:i', strtotime($log['aangemaakt_op']));
        $details  = $log['details'] ?? '';
        $klant_id_match = null;
        if (preg_match('/Klant ID:\s*(\d+)/', $details, $mx)) {
            $klant_id_match = (int)$mx[1];
        }
    ?>
    <?php if ($dag !== $vorige_dag): $vorige_dag = $dag; ?>
    <div class="px-4 py-2 border-bottom" style="background:#f8f9fa;">
        <small class="fw-semibold text-muted" style="letter-spacing:.4px;text-transform:uppercase;font-size:11px;">
            <?php
            $ts = strtotime($dag);
            $vandaag   = strtotime(date('d-m-Y'));
            $gisteren  = strtotime('-1 day', $vandaag);
            if ($ts === $vandaag)        echo 'Vandaag — ' . $dag;
            elseif ($ts === $gisteren)   echo 'Gisteren — ' . $dag;
            else                         echo date('l d F Y', $ts);
            ?>
        </small>
    </div>
    <?php endif; ?>
    <div class="d-flex align-items-start gap-3 px-4 py-3 border-bottom" style="<?= $kleur === 'danger' ? 'background:#fffafa;' : '' ?>">
        <!-- Icoon + kleur -->
        <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle mt-1"
             style="width:34px;height:34px;background:<?= $bg ?>;">
            <i class="<?= $icoon ?>" style="color:<?= $fg ?>;font-size:15px;"></i>
        </div>
        <!-- Label + details -->
        <div class="flex-grow-1 min-width-0">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="fw-semibold" style="font-size:14px;"><?= $label ?></span>
                <?php if ($klant_id_match && isset($klant_namen[$klant_id_match])): ?>
                <a href="<?= $base ?>/klanten/detail.php?id=<?= $klant_id_match ?>" class="badge text-decoration-none" style="background:#f0f4ff;color:#2563eb;font-weight:500;font-size:11px;">
                    <i class="ri-building-2-line me-1"></i><?= h($klant_namen[$klant_id_match]) ?>
                </a>
                <?php endif; ?>
            </div>
            <?php
            // Details opschonen: verwijder "Klant ID: X" als de naam al als badge staat
            $detail_clean = $details;
            if ($klant_id_match) {
                $detail_clean = trim(preg_replace('/,?\s*Klant ID:\s*\d+/', '', $detail_clean));
            }
            if ($detail_clean):
            ?>
            <div class="text-muted mt-1" style="font-size:12px;"><?= format_details($detail_clean, $klant_namen, $base) ?></div>
            <?php endif; ?>
        </div>
        <!-- Gebruiker + tijd -->
        <div class="flex-shrink-0 text-end">
            <div style="font-size:12px;font-weight:600;color:#495057;"><?= h($log['user_naam'] ?? '—') ?></div>
            <div class="text-muted" style="font-size:11px;"><?= $tijd ?></div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($totaal_paginas > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center flex-wrap">
        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= h(log_pagina_url($pagina - 1, ['type' => $filter_type, 'user' => $filter_user])) ?>">Vorige</a>
        </li>
        <?php foreach (pagina_reeks($pagina, $totaal_paginas) as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php else: ?>
            <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
                <a class="page-link" href="<?= h(log_pagina_url($p, ['type' => $filter_type, 'user' => $filter_user])) ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $pagina >= $totaal_paginas ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= h(log_pagina_url($pagina + 1, ['type' => $filter_type, 'user' => $filter_user])) ?>">Volgende</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
