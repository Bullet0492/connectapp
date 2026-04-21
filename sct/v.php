<?php
/**
 * SCT publieke bekijkpagina.
 * GEEN auth. Wordt bereikt via de link die de verzender deelt.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/sct_functions.php';

$id = trim((string)($_GET['id'] ?? ''));
if ($id === '' || !preg_match('/^[a-z0-9]{24}$/i', $id)) {
    http_response_code(404);
    $fout_titel = 'Ongeldige link';
    $fout_tekst = 'Deze link is niet geldig. Controleer of u de volledige link heeft geplakt.';
} else {
    // Lazy cleanup
    sct_verwijder_verlopen();

    $secret = sct_haal_secret($id);
    if (!$secret) {
        sct_log($id, 'niet_gevonden');
        http_response_code(404);
        $fout_titel = 'Bericht niet beschikbaar';
        $fout_tekst = 'Dit bericht bestaat niet, is al gelezen, of is verlopen. Elk bericht kan slechts één keer worden geopend.';
    }
}

$base = rtrim(BASE_URL, '/');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vertrouwelijk bericht — SCT</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
<link rel="icon" href="<?= h($base) ?>/images/logo.png">
<style>
  body {
    background: linear-gradient(135deg, #0e2a44 0%, #185E9B 100%);
    min-height: 100vh;
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
  }
  .sct-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 20px 50px rgba(0,0,0,.25);
    max-width: 680px;
    width: 100%;
    padding: 32px;
  }
  .sct-brand { display:flex; align-items:center; gap:10px; margin-bottom: 4px; }
  .sct-brand img { height: 32px; }
  .sct-brand span { font-size: 14px; font-weight: 700; color: #185E9B; letter-spacing: .3px; }
  .sct-titel { font-weight: 700; font-size: 22px; color: #1b2a3a; margin: 8px 0 6px; }
  .sct-sub { color: #6c757d; font-size: 14px; margin-bottom: 24px; }
  .sct-bericht {
    background: #f6f8fa;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 18px;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 14px;
    white-space: pre-wrap;
    word-break: break-word;
    max-height: 50vh;
    overflow-y: auto;
  }
  .btn-primary { background-color: #185E9B; border-color: #185E9B; }
  .btn-primary:hover { background-color: #134d7e; border-color: #134d7e; }
  .sct-voet { margin-top: 24px; font-size: 12px; color: #95a2b3; text-align: center; }
  .warn-box {
    background: #fff8e1; border: 1px solid #ffe082;
    border-radius: 8px; padding: 12px 14px; font-size: 13px; color: #856404;
    margin-bottom: 20px;
  }
</style>
</head>
<body>

<div class="sct-card">
  <div class="sct-brand">
    <img src="<?= h($base) ?>/images/logo.png" alt="Connect4IT" onerror="this.style.display='none'">
    <span>Connect4IT &middot; SCT</span>
  </div>

<?php if (isset($fout_titel)): ?>

  <div class="text-center py-4">
    <i class="ri-error-warning-line" style="font-size:48px;color:#dc3545;"></i>
    <h2 class="sct-titel mt-3"><?= h($fout_titel) ?></h2>
    <p class="sct-sub"><?= h($fout_tekst) ?></p>
  </div>

<?php else: ?>

  <h1 class="sct-titel">Vertrouwelijk bericht ontvangen</h1>
  <p class="sct-sub">
    Dit bericht is eenmalig leesbaar. Zodra u op <strong>"Bericht openen"</strong> klikt,
    wordt het onmiddellijk en onherstelbaar verwijderd.
  </p>

  <div id="sctStap1">
    <div class="warn-box">
      <i class="ri-alarm-warning-line me-1"></i>
      Lees het bericht pas wanneer u er klaar voor bent. Sluit onbevoegde meekijkers uit;
      zorg dat u het bericht direct kunt kopiëren of noteren &mdash; u krijgt geen tweede kans.
    </div>

    <?php if (!empty($secret['has_password'])): ?>
      <div class="mb-3">
        <label class="form-label fw-semibold">Wachtwoord</label>
        <input type="password" id="sctWachtwoord" class="form-control" autocomplete="off"
               placeholder="Typ het wachtwoord dat de verzender u heeft gegeven">
      </div>
    <?php endif; ?>

    <button id="sctOpen" class="btn btn-primary w-100">
      <i class="ri-eye-line me-1"></i> Bericht openen
    </button>
    <div id="sctFout" class="alert alert-danger mt-3 small" style="display:none;"></div>
  </div>

  <div id="sctStap2" style="display:none;">
    <div class="sct-bericht" id="sctTekst"></div>
    <div class="d-flex gap-2 mt-3">
      <button class="btn btn-outline-secondary btn-sm" id="sctKopieer">
        <i class="ri-file-copy-line me-1"></i> Kopieer tekst
      </button>
      <button class="btn btn-outline-secondary btn-sm ms-auto" id="sctSluit">
        <i class="ri-close-line me-1"></i> Sluit venster
      </button>
    </div>
    <div class="mt-3 small text-muted">
      <i class="ri-check-double-line text-success me-1"></i>
      Dit bericht is nu van onze server verwijderd. Herladen van deze pagina toont niets meer.
    </div>
  </div>

<?php endif; ?>

  <div class="sct-voet">
    Secret Connect4IT Transmission &middot; End-to-end versleuteld &middot;
    <a href="<?= h($base) ?>" class="text-decoration-none" style="color:#95a2b3;">connect4it.nl</a>
  </div>
</div>

<?php if (!isset($fout_titel)): ?>
<script>
  window.SCT_ID = <?= json_encode($id) ?>;
  window.SCT_HAS_PASSWORD = <?= !empty($secret['has_password']) ? 'true' : 'false' ?>;
  window.SCT_API_BASE = <?= json_encode($base . '/sct/api') ?>;
</script>
<script src="<?= h($base) ?>/sct/assets/sct-read.js?v=1"></script>
<?php endif; ?>

</body>
</html>
