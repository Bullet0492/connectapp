<?php
$paginatitel = 'Domein check';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/domein_functies.php';

sessie_start();
vereist_login();

$base      = basis_url();
$gebruiker = huidig_gebruiker();

$invoer   = trim($_GET['q'] ?? '');
$domein   = $invoer !== '' ? domein_normaliseer($invoer) : null;
$foutje   = null;

// Bij niet-lege input maar onherkenbaar formaat → fout
if ($invoer !== '' && $domein === null) {
    $foutje = 'Ongeldig domein. Voorbeeld: connect4it.nl';
}

// Resultaat-containers
$dns = $whois = $ssl = $spf = $dmarc = $dkim = $vps_treffers = null;
$alle_ips = [];
$mail_banners = [];
$blacklist = [];

if ($domein) {
    @set_time_limit(60);

    // 1. DNS
    $dns = domein_dns_alle($domein);

    // Verzamel alle IPs (A + AAAA + MX-IPs)
    foreach ($dns['A']    ?? [] as $r) if (!empty($r['ip']))    $alle_ips[] = $r['ip'];
    foreach ($dns['AAAA'] ?? [] as $r) if (!empty($r['ipv6']))  $alle_ips[] = $r['ipv6'];

    // Voor MX: resolve host naar IP
    $mx_ips = [];
    foreach ($dns['MX'] ?? [] as $r) {
        if (!empty($r['target'])) {
            $ar = @dns_get_record($r['target'], DNS_A);
            if (is_array($ar)) foreach ($ar as $a) if (!empty($a['ip'])) $mx_ips[] = $a['ip'];
        }
    }

    // 2. VPS detectie (op A+AAAA+MX IPs)
    $vps_treffers = domein_vps_matches_voor_ips(array_merge($alle_ips, $mx_ips));

    // 3. WHOIS
    $whois = domein_whois($domein);

    // 4. SSL
    $ssl = domein_ssl_info($domein);

    // 5. SPF / DMARC / DKIM
    $spf_raw = domein_spf_uit_txt($dns['TXT'] ?? []);
    $spf     = domein_spf_analyse($spf_raw);
    $dmarc   = domein_dmarc($domein);
    $dkim    = domein_dkim_scan($domein);

    // 6. Mail banners
    foreach ($dns['MX'] ?? [] as $r) {
        if (!empty($r['target'])) {
            $banner = domein_mail_banner($r['target']);
            $mail_banners[] = [
                'host'   => $r['target'],
                'pri'    => $r['pri'] ?? 0,
                'banner' => $banner,
            ];
        }
    }

    // 7. Blacklist (alleen IPv4)
    $blacklist_ips = array_unique(array_filter($alle_ips, fn($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)));
    foreach ($blacklist_ips as $ip) {
        $blacklist[$ip] = domein_blacklist_voor_ip($ip);
    }

    // Historie + logboek
    try {
        db()->prepare("INSERT INTO domein_lookups (user_id, user_naam, domein, aangemaakt_op) VALUES (?, ?, ?, NOW())")
            ->execute([$gebruiker['id'], $gebruiker['naam'], $domein]);
    } catch (Exception $e) { /* stil */ }
    log_actie('domein_opzoek', 'Domein: ' . $domein);
}

// Recente lookups (laatste 10 unieke)
$recent = [];
try {
    $recent = db()->query(
        "SELECT domein, MAX(aangemaakt_op) AS laatste
         FROM domein_lookups
         GROUP BY domein
         ORDER BY laatste DESC
         LIMIT 10"
    )->fetchAll();
} catch (Exception $e) { /* tabel nog niet gemigreerd */ }

require_once __DIR__ . '/../includes/header.php';

/**
 * Renders ongewijzigde tekst met hostname-klikbaarheid en linebreaks.
 * Input wordt eerst escaped, daarna worden vpsN.connect4it.hix.nl links gemaakt.
 */
function domein_render_txt(string $tekst): string {
    return domein_linkify_vps(nl2br(h($tekst)));
}
?>

<div class="d-flex align-items-center gap-2 mb-3">
    <i class="ri-global-line" style="font-size:22px;color:#185E9B;"></i>
    <h4 class="fw-bold mb-0">Domein check</h4>
</div>
<p class="text-muted small mb-3">
    Zoekt DNS, WHOIS, SSL, e-mailbeveiliging (SPF/DMARC/DKIM), mailservers en blacklist-status op.
    Connect4IT VPS-servers worden automatisch herkend en linken direct naar het DirectAdmin-paneel.
</p>

<form method="get" class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="ri-search-line"></i></span>
            <input type="text" name="q" class="form-control form-control-lg"
                   value="<?= h($invoer) ?>"
                   placeholder="connect4it.nl"
                   autofocus autocomplete="off" spellcheck="false">
            <button class="btn btn-primary px-4" type="submit">
                <i class="ri-search-line me-1"></i> Opzoeken
            </button>
        </div>
        <?php if ($foutje): ?>
            <div class="text-danger small mt-2"><?= h($foutje) ?></div>
        <?php endif; ?>
    </div>
</form>

<?php if (!$domein): ?>
    <?php if ($recent): ?>
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="ri-history-line me-1"></i> Recent opgezocht</h6>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($recent as $r): ?>
                        <a href="?q=<?= urlencode($r['domein']) ?>" class="btn btn-sm btn-outline-secondary">
                            <?= h($r['domein']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>

<!-- Header: domeinnaam + links -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="text-muted small">Opgezocht domein</div>
                <h3 class="fw-bold mb-0"><?= h($domein) ?></h3>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="https://<?= h($domein) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                    <i class="ri-external-link-line me-1"></i> Website
                </a>
                <a href="https://www.srvx.nl/?domain=<?= urlencode($domein) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                    <i class="ri-external-link-line me-1"></i> srvx.nl
                </a>
                <a href="https://mxtoolbox.com/SuperTool.aspx?action=mx%3a<?= urlencode($domein) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                    <i class="ri-external-link-line me-1"></i> MXToolbox
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($vps_treffers): ?>
<!-- VPS detectie callout -->
<div class="card shadow-sm border-0 mb-4" style="background:#fff4f0;border-left:4px solid #e8621a !important;">
    <div class="card-body p-4">
        <div class="d-flex align-items-center gap-2 mb-2">
            <i class="ri-server-line" style="font-size:22px;color:#e8621a;"></i>
            <h5 class="fw-bold mb-0">Draait op een Connect4IT VPS</h5>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
        <?php foreach ($vps_treffers as $t): ?>
            <a href="<?= h($t['panel_url']) ?>" target="_blank" rel="noopener" class="btn btn-warning">
                <i class="ri-shield-user-line me-1"></i>
                <?= h($t['vps_host']) ?>
                <span class="badge bg-light text-dark ms-1"><?= h($t['ip']) ?></span>
                <i class="ri-external-link-line ms-1"></i>
            </a>
        <?php endforeach; ?>
        </div>
        <div class="text-muted small mt-2">Klik om het DirectAdmin-paneel op poort 2222 te openen.</div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">

<!-- DNS records -->
<div class="col-12">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3"><i class="ri-git-branch-line me-1"></i> DNS records</h5>

            <?php
            $secties = [
                'A'     => ['A (IPv4)',     'ip'],
                'AAAA'  => ['AAAA (IPv6)',  'ipv6'],
                'MX'    => ['MX (mail)',    null],
                'NS'    => ['NS (nameservers)', 'target'],
                'CNAME' => ['CNAME',        'target'],
                'TXT'   => ['TXT',          null],
                'SOA'   => ['SOA',          null],
                'CAA'   => ['CAA',          null],
                'SRV'   => ['SRV',          null],
            ];
            foreach ($secties as $key => [$titel, $veld]):
                $rijen = $dns[$key] ?? [];
                if (!$rijen) continue;
            ?>
                <div class="mb-3">
                    <div class="fw-semibold text-muted small mb-1"><?= h($titel) ?></div>
                    <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" style="font-family:ui-monospace,monospace;font-size:13px;">
                    <tbody>
                    <?php foreach ($rijen as $r): ?>
                        <tr>
                            <td style="width:80px;color:#6c757d;"><?= h($r['type'] ?? $key) ?></td>
                            <td><?php
                                if ($key === 'MX') {
                                    $host = $r['target'] ?? '';
                                    echo '<span class="text-muted me-2">pri ' . (int)($r['pri'] ?? 0) . '</span>';
                                    echo domein_linkify_vps(h($host));
                                } elseif ($key === 'SOA') {
                                    echo 'mname: ' . domein_linkify_vps(h($r['mname'] ?? '')) . '<br>';
                                    echo 'rname: ' . h($r['rname'] ?? '') . '<br>';
                                    echo 'serial: ' . h($r['serial'] ?? '') . ', refresh: ' . h($r['refresh'] ?? '')
                                         . ', retry: ' . h($r['retry'] ?? '') . ', expire: ' . h($r['expire'] ?? '')
                                         . ', minimum: ' . h($r['minimum-ttl'] ?? '');
                                } elseif ($key === 'CAA') {
                                    echo h(($r['flags'] ?? '') . ' ' . ($r['tag'] ?? '') . ' "' . ($r['value'] ?? '') . '"');
                                } elseif ($key === 'SRV') {
                                    echo h('priority=' . ($r['pri'] ?? '') . ' weight=' . ($r['weight'] ?? '') . ' port=' . ($r['port'] ?? '') . ' ') . domein_linkify_vps(h($r['target'] ?? ''));
                                } elseif ($key === 'TXT') {
                                    echo domein_render_txt(domein_txt_waarde($r));
                                } elseif ($veld) {
                                    echo domein_linkify_vps(h($r[$veld] ?? ''));
                                    $ip = $r['ip'] ?? null;
                                    if ($ip) {
                                        $ptr = domein_reverse_dns($ip);
                                        if ($ptr) echo ' <span class="text-muted">— ' . domein_linkify_vps(h($ptr)) . '</span>';
                                    }
                                } else {
                                    echo h(json_encode($r));
                                }
                            ?></td>
                            <td style="width:70px;" class="text-muted text-end">ttl <?= h($r['ttl'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- E-mailbeveiliging: SPF + DMARC + DKIM -->
<div class="col-lg-6">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3"><i class="ri-mail-check-line me-1"></i> E-mailbeveiliging</h5>

            <!-- SPF -->
            <div class="mb-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="fw-semibold">SPF</span>
                    <?php if ($spf['aanwezig']): ?>
                        <span class="badge bg-success">aanwezig</span>
                    <?php else: ?>
                        <span class="badge bg-danger">ontbreekt</span>
                    <?php endif; ?>
                </div>
                <?php if ($spf['aanwezig']): ?>
                    <code class="d-block p-2 bg-light rounded small"><?= h($spf['raw']) ?></code>
                    <div class="small text-muted mt-1">Eind-mechanisme: <strong><?= h($spf['eind']) ?></strong></div>
                <?php endif; ?>
            </div>

            <!-- DMARC -->
            <div class="mb-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="fw-semibold">DMARC</span>
                    <?php if ($dmarc): ?>
                        <?php $pol = strtolower($dmarc['pairs']['p'] ?? 'none'); ?>
                        <span class="badge bg-<?= $pol === 'reject' ? 'success' : ($pol === 'quarantine' ? 'warning' : 'secondary') ?>">
                            p=<?= h($pol) ?>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger">ontbreekt</span>
                    <?php endif; ?>
                </div>
                <?php if ($dmarc): ?>
                    <code class="d-block p-2 bg-light rounded small"><?= h($dmarc['raw']) ?></code>
                <?php endif; ?>
            </div>

            <!-- DKIM -->
            <div>
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="fw-semibold">DKIM (selectoren geprobeerd: <?= count(DOMEIN_DKIM_SELECTOREN) ?>)</span>
                    <?php if ($dkim): ?>
                        <span class="badge bg-success"><?= count($dkim) ?> gevonden</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">geen bekende selector</span>
                    <?php endif; ?>
                </div>
                <?php foreach ($dkim as $d): ?>
                    <div class="mt-2">
                        <div class="small"><strong><?= h($d['selector']) ?>._domainkey</strong></div>
                        <code class="d-block p-2 bg-light rounded small text-break" style="word-break:break-all;"><?= h($d['txt']) ?></code>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- SSL -->
<div class="col-lg-6">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3"><i class="ri-lock-2-line me-1"></i> SSL certificaat</h5>
            <?php if (!$ssl): ?>
                <div class="text-muted small">Geen SSL verbinding mogelijk op poort 443.</div>
            <?php else: ?>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted">Subject</dt>
                    <dd class="col-sm-8"><?= h($ssl['subject_cn']) ?></dd>
                    <dt class="col-sm-4 text-muted">Uitgever</dt>
                    <dd class="col-sm-8"><?= h(trim(($ssl['issuer_o'] ? $ssl['issuer_o'] . ' — ' : '') . $ssl['issuer_cn'])) ?></dd>
                    <dt class="col-sm-4 text-muted">Geldig van</dt>
                    <dd class="col-sm-8"><?= $ssl['geldig_van'] ? h(date('d-m-Y H:i', $ssl['geldig_van'])) : '—' ?></dd>
                    <dt class="col-sm-4 text-muted">Geldig tot</dt>
                    <dd class="col-sm-8">
                        <?php if ($ssl['geldig_tot']): ?>
                            <?= h(date('d-m-Y H:i', $ssl['geldig_tot'])) ?>
                            <?php
                            $dagen = (int) floor(($ssl['geldig_tot'] - time()) / 86400);
                            $kleur = $dagen < 0 ? 'danger' : ($dagen < 14 ? 'warning' : 'success');
                            $tekst = $dagen < 0 ? 'verlopen' : ($dagen . ' dagen');
                            ?>
                            <span class="badge bg-<?= $kleur ?> ms-1"><?= h($tekst) ?></span>
                        <?php endif; ?>
                    </dd>
                    <?php if ($ssl['sans']): ?>
                        <dt class="col-sm-4 text-muted">SAN</dt>
                        <dd class="col-sm-8" style="word-break:break-all;font-family:ui-monospace,monospace;font-size:12px;">
                            <?= h(implode(', ', array_slice($ssl['sans'], 0, 30))) ?>
                            <?php if (count($ssl['sans']) > 30): ?>
                                <span class="text-muted">… +<?= count($ssl['sans']) - 30 ?> meer</span>
                            <?php endif; ?>
                        </dd>
                    <?php endif; ?>
                </dl>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Mailservers -->
<?php if ($mail_banners): ?>
<div class="col-lg-6">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3"><i class="ri-mail-send-line me-1"></i> Mailservers (SMTP banner)</h5>
            <table class="table table-sm mb-0 small">
                <thead><tr><th>Host</th><th>Pri</th><th>Banner</th></tr></thead>
                <tbody>
                <?php foreach ($mail_banners as $b): ?>
                    <tr>
                        <td><?= domein_linkify_vps(h($b['host'])) ?></td>
                        <td><?= (int)$b['pri'] ?></td>
                        <td><?php if ($b['banner']) echo '<code>' . h($b['banner']) . '</code>'; else echo '<span class="text-muted">geen verbinding</span>'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="small text-muted mt-2">Let op: veel netwerken blokkeren uitgaand verkeer op poort 25, dan lukt de banner-test niet.</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Blacklist -->
<?php if ($blacklist): ?>
<div class="col-lg-6">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3"><i class="ri-shield-cross-line me-1"></i> Blacklist check (DNSBL)</h5>
            <?php foreach ($blacklist as $ip => $checks): ?>
                <div class="mb-2">
                    <div class="fw-semibold small mb-1"><?= h($ip) ?></div>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($checks as $c): ?>
                            <?php if ($c['gelist']): ?>
                                <span class="badge bg-danger" title="<?= h($c['reden'] ?? '') ?>">
                                    <i class="ri-alert-line"></i> <?= h($c['rbl']) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-success"><?= h($c['rbl']) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- WHOIS -->
<div class="col-12">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3"><i class="ri-file-list-3-line me-1"></i> WHOIS</h5>
            <?php if (!$whois): ?>
                <div class="text-muted small">Geen WHOIS-data beschikbaar voor deze TLD.</div>
            <?php else: ?>
                <pre style="background:#f8f9fa;border:1px solid #e9ecef;border-radius:6px;padding:12px;max-height:500px;overflow:auto;font-size:12px;white-space:pre-wrap;word-break:break-word;"><?= domein_linkify_vps(h($whois)) ?></pre>
            <?php endif; ?>
        </div>
    </div>
</div>

</div><!-- /row -->

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
