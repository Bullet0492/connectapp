<?php
$paginatitel = 'Veilig versturen';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/sct_functions.php';

sessie_start();
vereist_login();

$base = basis_url();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card shadow-sm border-0">
      <div class="card-body p-4">
        <div class="d-flex align-items-center gap-2 mb-3">
          <i class="ri-shield-keyhole-line" style="font-size:22px;color:#185E9B;"></i>
          <h4 class="fw-bold mb-0">Secret Connect4IT Transmission</h4>
        </div>
        <p class="text-muted small mb-4">
          Typ een vertrouwelijk bericht of kies een bestand. De versleuteling gebeurt in uw browser
          <em>vóór</em> verzending &mdash; onze server ziet nooit de leesbare inhoud. Na het delen
          van de link kan de ontvanger het <strong>één keer</strong> openen, waarna het
          onmiddellijk wordt verwijderd.
        </p>

        <ul class="nav nav-pills mb-3" id="sctTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="sctTabTekstBtn" data-sct-tab="tekst" type="button">
              <i class="ri-chat-3-line me-1"></i> Tekstbericht
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="sctTabBestandBtn" data-sct-tab="bestand" type="button">
              <i class="ri-attachment-2 me-1"></i> Bestand
            </button>
          </li>
        </ul>

        <form id="sctForm" novalidate>
          <div id="sctTabTekst">
            <div class="mb-3">
              <label class="form-label fw-semibold">Bericht</label>
              <textarea id="sctBericht" class="form-control" rows="8" maxlength="20000"
                        placeholder="Plak of typ hier de vertrouwelijke informatie..."></textarea>
              <div class="form-text">Maximaal 20.000 tekens.</div>
            </div>
          </div>

          <div id="sctTabBestand" style="display:none;">
            <div class="mb-3">
              <label class="form-label fw-semibold">Bestand</label>
              <div id="sctDrop" class="border border-2 border-dashed rounded p-4 text-center"
                   style="cursor:pointer; background:#f8fafc;">
                <i class="ri-upload-cloud-2-line" style="font-size:32px;color:#185E9B;"></i>
                <div class="small mt-2">Klik hier of sleep een bestand in dit vak</div>
                <div class="text-muted" style="font-size:12px;">
                  Max <?= (int)(SCT_MAX_BESTAND / 1048576) ?> MB &middot; alles wordt lokaal versleuteld
                </div>
              </div>
              <input type="file" id="sctBestand" class="d-none">
              <div id="sctBestandInfo" class="mt-2 small" style="display:none;">
                <div class="d-flex align-items-center gap-2 p-2 border rounded bg-light">
                  <i class="ri-file-line text-primary"></i>
                  <div class="flex-grow-1">
                    <div class="fw-semibold" id="sctBestandNaam"></div>
                    <div class="text-muted" id="sctBestandMeta" style="font-size:12px;"></div>
                  </div>
                  <button type="button" class="btn btn-sm btn-link text-danger p-0" id="sctBestandWis">
                    <i class="ri-close-line"></i>
                  </button>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Toelichting bij het bestand <span class="text-muted fw-normal">(optioneel)</span></label>
              <textarea id="sctToelichting" class="form-control" rows="3" maxlength="5000"
                        placeholder="Bijv. 'Hierbij de inloggegevens van server X — wachtwoord staat in het document.'"></textarea>
              <div class="form-text">Wordt ook lokaal versleuteld en samen met het bestand verstuurd. Max 5.000 tekens.</div>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Bewaartermijn</label>
              <select id="sctRetentie" class="form-select">
                <?php foreach (SCT_RETENTIE_OPTIES as $uren => $label): ?>
                  <option value="<?= $uren ?>" <?= $uren === 24 ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Na deze periode wordt de link automatisch ongeldig, ook als niet geopend.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Wachtwoord (optioneel)</label>
              <input type="text" id="sctWachtwoord" class="form-control" autocomplete="off"
                     placeholder="Extra beveiliging bovenop de link">
              <div class="form-text">Deel dit via een ander kanaal dan de link.</div>
            </div>
          </div>

          <div class="mt-3">
            <label class="form-label fw-semibold">Notificatie-mail (optioneel)</label>
            <input type="email" id="sctNotify" class="form-control"
                   placeholder="ontvanger@voorbeeld.nl — u ontvangt bericht zodra gelezen">
            <div class="form-text">U krijgt een e-mail op dit adres zodra het bericht geopend wordt.</div>
          </div>

          <div class="d-flex justify-content-end mt-4">
            <button type="submit" class="btn btn-primary" id="sctSubmit">
              <i class="ri-lock-line me-1"></i> Beveiligde link genereren
            </button>
          </div>
        </form>

        <div id="sctResultaat" class="mt-4" style="display:none;">
          <div class="alert alert-success">
            <div class="fw-semibold mb-2">
              <i class="ri-check-line me-1"></i> Link aangemaakt
            </div>
            <p class="small mb-2">
              Deel onderstaande link met de ontvanger. Het bericht verdwijnt direct na openen.
              <strong>De sleutel achter het `#`-teken staat niet op onze server</strong> &mdash;
              zonder die volledige link is er geen enkele manier om het bericht te lezen.
            </p>
            <div class="input-group mb-2">
              <input type="text" id="sctLink" class="form-control font-monospace small" readonly>
              <button class="btn btn-outline-secondary" type="button" id="sctKopieer">
                <i class="ri-file-copy-line"></i> Kopieer
              </button>
            </div>
            <div class="small text-muted" id="sctMeta"></div>
            <button type="button" class="btn btn-link btn-sm mt-2 p-0" id="sctNieuw">
              <i class="ri-add-line"></i> Nieuw bericht aanmaken
            </button>
          </div>
        </div>

        <div id="sctFout" class="mt-3" style="display:none;">
          <div class="alert alert-danger small mb-0" id="sctFoutTekst"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card border-0 bg-light shadow-sm">
      <div class="card-body p-4">
        <h6 class="fw-bold mb-3"><i class="ri-information-line me-1"></i> Zo werkt het</h6>
        <ol class="small mb-3 ps-3">
          <li class="mb-2">Uw browser genereert een willekeurige AES-256 sleutel.</li>
          <li class="mb-2">Bericht of bestand wordt lokaal versleuteld &mdash; alleen de versleutelde bytes gaan naar de server.</li>
          <li class="mb-2">De sleutel komt in het <em>fragment</em> van de link (na `#`) en verlaat uw browser nooit.</li>
          <li class="mb-2">De ontvanger opent de link, de browser haalt de ciphertext op en decodeert lokaal.</li>
          <li>Direct na succesvol openen wordt het bericht of bestand definitief van de server verwijderd.</li>
        </ol>
        <h6 class="fw-bold mb-2 mt-4"><i class="ri-history-line me-1"></i> Mijn berichten</h6>
        <a href="overzicht.php" class="btn btn-sm btn-outline-secondary w-100">
          <i class="ri-list-check me-1"></i> Overzicht openen
        </a>
      </div>
    </div>
  </div>
</div>

<input type="hidden" id="sctCsrf" value="<?= h(csrf_token()) ?>">

<script>
  window.SCT_MAX_BESTAND = <?= (int)SCT_MAX_BESTAND ?>;
</script>
<script src="<?= $base ?>/sct/assets/sct-create.js?v=3"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
