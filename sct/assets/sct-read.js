/**
 * SCT read — haalt ciphertext op en decodeert met de key uit het URL fragment.
 * De key verlaat nooit de browser. Werkt voor tekst én bestanden.
 */
(function () {
  'use strict';

  const openBtn = document.getElementById('sctOpen');
  const wachtwoordEl = document.getElementById('sctWachtwoord');
  const foutBox = document.getElementById('sctFout');
  const stap1 = document.getElementById('sctStap1');
  const stap2 = document.getElementById('sctStap2');

  // Tekst
  const tekstBox = document.getElementById('sctTekstBox');
  const tekstEl = document.getElementById('sctTekst');
  const kopieerBtn = document.getElementById('sctKopieer');
  const sluitBtn = document.getElementById('sctSluit');

  // Bestand
  const bestandBox = document.getElementById('sctBestandBox');
  const dlNaamEl = document.getElementById('sctDlNaam');
  const dlMetaEl = document.getElementById('sctDlMeta');
  const dlToelichtingWrap = document.getElementById('sctDlToelichtingWrap');
  const dlToelichtingEl = document.getElementById('sctDlToelichting');
  const dlOpnieuwBtn = document.getElementById('sctDownloadOpnieuw');
  const sluitBtn2 = document.getElementById('sctSluit2');

  let blobUrl = null;
  let blobNaam = null;

  function toonFout(tekst) {
    foutBox.textContent = tekst;
    foutBox.style.display = 'block';
  }

  function b64urlToBuf(s) {
    s = (s || '').replace(/-/g, '+').replace(/_/g, '/');
    while (s.length % 4) s += '=';
    const bin = atob(s);
    const buf = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
    return buf;
  }

  function getKeyFromFragment() {
    const hash = window.location.hash || '';
    return hash.replace(/^#/, '').trim();
  }

  function formatBytes(n) {
    if (n < 1024) return n + ' B';
    if (n < 1048576) return (n / 1024).toFixed(1) + ' KB';
    if (n < 1073741824) return (n / 1048576).toFixed(1) + ' MB';
    return (n / 1073741824).toFixed(2) + ' GB';
  }

  async function importKey(keyB64) {
    return crypto.subtle.importKey(
      'raw',
      b64urlToBuf(keyB64),
      { name: 'AES-GCM', length: 256 },
      false,
      ['decrypt']
    );
  }

  async function decryptToText(key, ivB64, ctBuf) {
    const pt = await crypto.subtle.decrypt(
      { name: 'AES-GCM', iv: b64urlToBuf(ivB64) },
      key,
      ctBuf
    );
    return new TextDecoder().decode(pt);
  }

  async function decryptToBuffer(key, ivB64, ctBuf) {
    return crypto.subtle.decrypt(
      { name: 'AES-GCM', iv: b64urlToBuf(ivB64) },
      key,
      ctBuf
    );
  }

  function startDownload(blob, naam) {
    if (blobUrl) URL.revokeObjectURL(blobUrl);
    blobUrl = URL.createObjectURL(blob);
    blobNaam = naam;
    const a = document.createElement('a');
    a.href = blobUrl;
    a.download = naam;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }

  async function openenTekst(key, wachtwoord) {
    const res = await fetch(window.SCT_API_BASE + '/read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: window.SCT_ID, wachtwoord: wachtwoord }),
    });

    const ctype = res.headers.get('Content-Type') || '';
    if (!ctype.includes('application/json')) {
      throw new Error('Onverwacht antwoord van server.');
    }
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.fout || 'Ophalen mislukt.');

    const plaintext = await decryptToText(key, data.iv, b64urlToBuf(data.ciphertext));
    tekstEl.textContent = plaintext;
    tekstBox.style.display = 'block';
    bestandBox.style.display = 'none';
    stap1.style.display = 'none';
    stap2.style.display = 'block';
  }

  async function openenBestand(key, wachtwoord) {
    const res = await fetch(window.SCT_API_BASE + '/read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: window.SCT_ID, wachtwoord: wachtwoord }),
    });

    const ctype = res.headers.get('Content-Type') || '';
    if (!res.ok || !ctype.includes('application/octet-stream')) {
      // Foutpad: server gaf JSON met fout terug
      let foutTekst = 'Ophalen mislukt.';
      try {
        const data = await res.json();
        if (data && data.fout) foutTekst = data.fout;
      } catch (_) {}
      throw new Error(foutTekst);
    }

    const ivB64 = res.headers.get('X-SCT-Iv');
    const metaCtB64 = res.headers.get('X-SCT-Meta-Ct');
    const metaIvB64 = res.headers.get('X-SCT-Meta-Iv');
    const mimetype = res.headers.get('X-SCT-Mimetype') || 'application/octet-stream';

    if (!ivB64 || !metaCtB64 || !metaIvB64) {
      throw new Error('Metadata ontbreekt in respons.');
    }

    const ctBuf = await res.arrayBuffer();

    // Decrypt metadata
    const metaPt = await decryptToBuffer(key, metaIvB64, b64urlToBuf(metaCtB64));
    let meta;
    try {
      meta = JSON.parse(new TextDecoder().decode(metaPt));
    } catch (_) {
      meta = { naam: 'bestand', type: mimetype, grootte: ctBuf.byteLength };
    }

    // Decrypt bestand
    const ptBuf = await decryptToBuffer(key, ivB64, ctBuf);
    const blob = new Blob([ptBuf], { type: meta.type || mimetype });

    dlNaamEl.textContent = meta.naam || 'bestand';
    dlMetaEl.textContent = ' · ' + formatBytes(blob.size);

    if (meta.toelichting && dlToelichtingWrap && dlToelichtingEl) {
      dlToelichtingEl.textContent = meta.toelichting;
      dlToelichtingWrap.style.display = 'block';
    } else if (dlToelichtingWrap) {
      dlToelichtingWrap.style.display = 'none';
    }

    bestandBox.style.display = 'block';
    tekstBox.style.display = 'none';
    stap1.style.display = 'none';
    stap2.style.display = 'block';

    startDownload(blob, meta.naam || 'bestand');
  }

  openBtn.addEventListener('click', async function () {
    foutBox.style.display = 'none';

    const keyB64 = getKeyFromFragment();
    if (!keyB64) {
      toonFout('De decryptiesleutel ontbreekt in de link. Vraag de verzender om de volledige URL opnieuw te sturen (inclusief het stuk na het #-teken).');
      return;
    }

    if (!window.crypto || !window.crypto.subtle) {
      toonFout('Uw browser ondersteunt geen veilige decryptie. Gebruik een moderne browser via HTTPS.');
      return;
    }

    const wachtwoord = window.SCT_HAS_PASSWORD && wachtwoordEl ? wachtwoordEl.value : null;
    if (window.SCT_HAS_PASSWORD && (!wachtwoord || wachtwoord.length === 0)) {
      toonFout('Dit bericht is beschermd met een wachtwoord.');
      return;
    }

    openBtn.disabled = true;
    const origLabel = openBtn.innerHTML;
    openBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Bezig met openen...';

    try {
      const key = await importKey(keyB64);
      if (window.SCT_TYPE === 'file') {
        await openenBestand(key, wachtwoord);
      } else {
        await openenTekst(key, wachtwoord);
      }

      // Wis de fragment uit de URL zodat bookmarks/history geen sleutel bevatten
      try {
        history.replaceState(null, '', window.location.pathname + window.location.search);
      } catch (_) {}
    } catch (err) {
      toonFout(err.message || 'Kon het bericht niet openen.');
      openBtn.disabled = false;
      openBtn.innerHTML = origLabel;
    }
  });

  if (kopieerBtn) {
    kopieerBtn.addEventListener('click', async function () {
      try {
        await navigator.clipboard.writeText(tekstEl.textContent);
        kopieerBtn.innerHTML = '<i class="ri-check-line me-1"></i> Gekopieerd';
        setTimeout(() => {
          kopieerBtn.innerHTML = '<i class="ri-file-copy-line me-1"></i> Kopieer tekst';
        }, 1500);
      } catch (_) {
        const range = document.createRange();
        range.selectNodeContents(tekstEl);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
      }
    });
  }

  if (dlOpnieuwBtn) {
    dlOpnieuwBtn.addEventListener('click', function () {
      if (!blobUrl || !blobNaam) return;
      const a = document.createElement('a');
      a.href = blobUrl;
      a.download = blobNaam;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    });
  }

  function sluitVenster() {
    if (blobUrl) { URL.revokeObjectURL(blobUrl); blobUrl = null; }
    if (tekstEl) tekstEl.textContent = '';
    stap2.style.display = 'none';
    try { window.close(); } catch (_) {}
    document.body.innerHTML =
      '<div style="color:#fff;text-align:center;padding:40px;font-family:sans-serif;">' +
      '<h3>Gesloten</h3>' +
      '<p>U kunt dit venster nu veilig sluiten.</p></div>';
  }

  if (sluitBtn) sluitBtn.addEventListener('click', sluitVenster);
  if (sluitBtn2) sluitBtn2.addEventListener('click', sluitVenster);
})();
