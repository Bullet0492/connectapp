<?php
$paginatitel = 'Mijn SCT berichten';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/sct_functions.php';

sessie_start();
vereist_login();

$gebruiker = huidig_gebruiker();
$base = basis_url();

// Lazy cleanup
sct_verwijder_verlopen();

// Actieve secrets van deze gebruiker
$stmt = db()->prepare(
    'SELECT id, type, has_password, notify_email, retentie_uren, aangemaakt_op, verloopt_op,
            bestandsgrootte, mimetype
       FROM sct_secrets
      WHERE sender_user_id = ?
      ORDER BY aangemaakt_op DESC'
);
$stmt->execute([$gebruiker['id']]);
$actief = $stmt->fetchAll();

// Recente log van deze gebruiker (op basis van ids in zijn logboek)
$stmt = db()->prepare(
    "SELECT l.secret_id, l.actie, l.tijdstip, l.ip
       FROM sct_access_log l
      WHERE l.secret_id IN (
            SELECT id FROM sct_secrets WHERE sender_user_id = ?
        )
         OR l.secret_id IN (
            SELECT secret_id FROM sct_access_log WHERE ip = ? AND actie = 'aangemaakt'
         )
      ORDER BY l.tijdstip DESC
      LIMIT 100"
);
$stmt->execute([$gebruiker['id'], ip_adres()]);
$logregels = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1"><i class="ri-list-check me-1"></i> Mijn SCT berichten</h4>
    <div class="text-muted small">Actieve en gearchiveerde beveiligde transmissies</div>
  </div>
  <a href="index.php" class="btn btn-primary">
    <i class="ri-add-line me-1"></i> Nieuw bericht
  </a>
</div>

<div class="card shadow-sm border-0 mb-4">
  <div class="card-body p-0">
    <div class="px-4 pt-3 pb-2 border-bottom d-flex align-items-center gap-2">
      <i class="ri-shield-keyhole-line text-primary"></i>
      <strong>Actief (wachtend op lezen)</strong>
      <span class="badge bg-primary-subtle text-primary ms-auto"><?= count($actief) ?></span>
    </div>

    <?php if (!$actief): ?>
      <div class="p-4 text-center text-muted small">
        Geen actieve berichten. Maak er één aan via <a href="index.php">Nieuw bericht</a>.
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead class="small text-muted">
            <tr>
              <th class="ps-4">ID</th>
              <th>Type</th>
              <th>Aangemaakt</th>
              <th>Verloopt</th>
              <th>Opties</th>
              <th>Notificatie</th>
              <th class="pe-4"></th>
            </tr>
          </thead>
          <tbody>
          <?php
          function sct_format_bytes(int $n): string {
              if ($n < 1024) return $n . ' B';
              if ($n < 1048576) return number_format($n / 1024, 1) . ' KB';
              if ($n < 1073741824) return number_format($n / 1048576, 1) . ' MB';
              return number_format($n / 1073741824, 2) . ' GB';
          }
          foreach ($actief as $s): ?>
            <tr>
              <td class="ps-4 font-monospace small"><?= h(substr($s['id'], 0, 10)) ?>…</td>
              <td class="small">
                <?php if (($s['type'] ?? 'text') === 'file'): ?>
                  <span class="badge bg-info-subtle text-info-emphasis">
                    <i class="ri-attachment-2"></i> Bestand
                  </span>
                  <?php if (!empty($s['bestandsgrootte'])): ?>
                    <div class="text-muted" style="font-size:11px;">
                      <?= h(sct_format_bytes((int)$s['bestandsgrootte'])) ?>
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary-emphasis">
                    <i class="ri-chat-3-line"></i> Tekst
                  </span>
                <?php endif; ?>
              </td>
              <td class="small"><?= h(date('d-m-Y H:i', strtotime($s['aangemaakt_op']))) ?></td>
              <td class="small">
                <?= h(date('d-m-Y H:i', strtotime($s['verloopt_op']))) ?>
                <div class="text-muted" style="font-size:11px;"><?= (int)$s['retentie_uren'] ?>u bewaartermijn</div>
              </td>
              <td class="small">
                <?php if ($s['has_password']): ?>
                  <span class="badge bg-warning-subtle text-warning-emphasis"><i class="ri-lock-line"></i> Wachtwoord</span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="small"><?= $s['notify_email'] ? h($s['notify_email']) : '<span class="text-muted">—</span>' ?></td>
              <td class="pe-4 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="sctVerwijder('<?= h($s['id']) ?>')">
                  <i class="ri-delete-bin-line"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card shadow-sm border-0">
  <div class="card-body p-0">
    <div class="px-4 pt-3 pb-2 border-bottom d-flex align-items-center gap-2">
      <i class="ri-history-line text-primary"></i>
      <strong>Recente activiteit</strong>
      <span class="badge bg-secondary-subtle text-secondary ms-auto"><?= count($logregels) ?></span>
    </div>

    <?php if (!$logregels): ?>
      <div class="p-4 text-center text-muted small">Nog geen activiteit.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead class="small text-muted">
            <tr>
              <th class="ps-4">Tijdstip</th>
              <th>Bericht-ID</th>
              <th>Actie</th>
              <th class="pe-4">IP</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $actie_styles = [
              'aangemaakt'     => ['success',   'ri-add-line',            'Aangemaakt'],
              'bekeken'        => ['primary',   'ri-eye-line',            'Bekeken'],
              'verlopen'       => ['secondary', 'ri-time-line',           'Verlopen'],
              'fout_wachtwoord'=> ['warning',   'ri-lock-unlock-line',    'Fout wachtwoord'],
              'niet_gevonden'  => ['secondary', 'ri-question-line',       'Niet gevonden'],
              'rate_limit'     => ['danger',    'ri-shield-cross-line',   'Rate limit'],
          ];
          foreach ($logregels as $l):
              $stijl = $actie_styles[$l['actie']] ?? ['secondary', 'ri-information-line', $l['actie']];
          ?>
            <tr>
              <td class="ps-4 small"><?= h(date('d-m-Y H:i:s', strtotime($l['tijdstip']))) ?></td>
              <td class="font-monospace small"><?= h(substr($l['secret_id'], 0, 10)) ?>…</td>
              <td>
                <span class="badge bg-<?= $stijl[0] ?>-subtle text-<?= $stijl[0] ?>-emphasis">
                  <i class="<?= $stijl[1] ?>"></i> <?= h($stijl[2]) ?>
                </span>
              </td>
              <td class="pe-4 small text-muted font-monospace"><?= h($l['ip']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<form id="sctVerwijderForm" method="post" action="api/verwijder.php" style="display:none;">
  <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="id" id="sctVerwijderId">
</form>

<script>
function sctVerwijder(id) {
  if (!confirm('Dit bericht nu intrekken? De link wordt direct ongeldig.')) return;
  document.getElementById('sctVerwijderId').value = id;
  document.getElementById('sctVerwijderForm').submit();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
