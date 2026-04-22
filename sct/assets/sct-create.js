/**
 * SCT create — client-side AES-GCM encryptie voor tekst én bestanden.
 * De sleutel verlaat nooit de browser (alleen in URL fragment).
 */
(function () {
  'use strict';

  const form = document.getElementById('sctForm');
  const berichtEl = document.getElementById('sctBericht');
  const retentieEl = document.getElementById('sctRetentie');
  const wachtwoordEl = document.getElementById('sctWachtwoord');
  const notifyEl = document.getElementById('sctNotify');
  const submitBtn = document.getElementById('sctSubmit');
  const csrfEl = document.getElementById('sctCsrf');
  const resultaatBox = document.getElementById('sctResultaat');
  const foutBox = document.getElementById('sctFout');
  const foutTekst = document.getElementById('sctFoutTekst');
  const linkEl = document.getElementById('sctLink');
  const metaEl = document.getElementById('sctMeta');
  const kopieerBtn = document.getElementById('sctKopieer');
  const nieuwBtn = document.getElementById('sctNieuw');

  // Tabs
  const tabTekstBtn = document.getElementById('sctTabTekstBtn');
  const tabBestandBtn = document.getElementById('sctTabBestandBtn');
  const paneelTekst = document.getElementById('sctTabTekst');
  const paneelBestand = document.getElementById('sctTabBestand');

  // File input
  const dropEl = document.getElementById('sctDrop');
  const fileEl = document.getElementById('sctBestand');
  const fileInfoEl = document.getElementById('sctBestandInfo');
  const fileNaamEl = document.getElementById('sctBestandNaam');
  const fileMetaEl = document.getElementById('sctBestandMeta');
  const fileWisBtn = document.getElementById('sctBestandWis');

  const MAX_BESTAND = window.SCT_MAX_BESTAND || 26214400;
  let modus = 'tekst';  // 'tekst' of 'bestand'
  let gekozenBestand = null;

  function toonFout(tekst) {
    foutTekst.textContent = tekst;
    foutBox.style.display = 'block';
    resultaatBox.style.display = 'none';
  }

  function resetForm() {
    form.reset();
    gekozenBestand = null;
    fileEl.value = '';
    fileInfoEl.style.display = 'none';
    resultaatBox.style.display = 'none';
    foutBox.style.display = 'none';
  }

  function zetModus(nieuw) {
    modus = nieuw;
    if (nieuw === 'tekst') {
      tabTekstBtn.classList.add('active');
      tabBestandBtn.classList.remove('active');
      paneelTekst.style.display = '';
      paneelBestand.style.display = 'none';
    } else {
      tabBestandBtn.classList.add('active');
      tabTekstBtn.classList.remove('active');
      paneelBestand.style.display = '';
      paneelTekst.style.display = 'none';
    }
    foutBox.style.display = 'none';
  }

  tabTekstBtn.addEventListener('click', () => zetModus('tekst'));
  tabBestandBtn.addEventListener('click', () => zetModus('bestand'));

  // Drop-zone
  dropEl.addEventListener('click', () => fileEl.click());
  dropEl.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropEl.classList.add('border-primary');
    dropEl.style.background = '#eef5fc';
  });
  dropEl.addEventListener('dragleave', () => {
    dropEl.classList.remove('border-primary');
    dropEl.style.background = '#f8fafc';
  });
  dropEl.addEventListener('drop', (e) => {
    e.preventDefault();
    dropEl.classList.remove('border-primary');
    dropEl.style.background = '#f8fafc';
    const f = e.dataTransfer.files && e.dataTransfer.files[0];
    if (f) kiesBestand(f);
  });
  fileEl.addEventListener('change', () => {
    const f = fileEl.files && fileEl.files[0];
    if (f) kiesBestand(f);
  });
  fileWisBtn.addEventListener('click', () => {
    gekozenBestand = null;
    fileEl.value = '';
    fileInfoEl.style.display = 'none';
  });

  function formatBytes(n) {
    if (n < 1024) return n + ' B';
    if (n < 1048576) return (n / 1024).toFixed(1) + ' KB';
    if (n < 1073741824) return (n / 1048576).toFixed(1) + ' MB';
    return (n / 1073741824).toFixed(2) + ' GB';
  }

  function kiesBestand(f) {
    if (f.size > MAX_BESTAND) {
      toonFout('Bestand is te groot. Maximum is ' + formatBytes(MAX_BESTAND) + '.');
      return;
    }
    gekozenBestand = f;
    fileNaamEl.textContent = f.name;
    fileMetaEl.textContent = formatBytes(f.size) + (f.type ? (' · ' + f.type) : '');
    fileInfoEl.style.display = 'block';
    foutBox.style.display = 'none';
  }

  // Base64url helpers
  function bufToB64url(buf) {
    const bytes = new Uint8Array(buf);
    let s = '';
    const chunk = 0x8000;
    for (let i = 0; i < bytes.length; i += chunk) {
      s += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
    }
    return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  async function genereerKey() {
    return crypto.subtle.generateKey(
      { name: 'AES-GCM', length: 256 },
      true,
      ['encrypt', 'decrypt']
    );
  }

  async function versleutelBuffer(key, buffer) {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const ct = await crypto.subtle.encrypt({ name: 'AES-GCM', iv: iv }, key, buffer);
    return { ct: new Uint8Array(ct), iv: iv };
  }

  async function versleutelTekst(plaintext) {
    const key = await genereerKey();
    const { ct, iv } = await versleutelBuffer(key, new TextEncoder().encode(plaintext));
    const raw = await crypto.subtle.exportKey('raw', key);
    return {
      ciphertext: bufToB64url(ct),
      iv: bufToB64url(iv),
      key: bufToB64url(raw),
    };
  }

  async function versleutelBestand(bestand) {
    const key = await genereerKey();

    // Bestand zelf versleutelen
    const buf = await bestand.arrayBuffer();
    const { ct: fileCt, iv: fileIv } = await versleutelBuffer(key, buf);

    // Bestandsnaam apart versleutelen met zelfde sleutel maar eigen IV
    const meta = JSON.stringify({
      naam: bestand.name || 'bestand',
      type: bestand.type || 'application/octet-stream',
      grootte: bestand.size,
    });
    const { ct: metaCt, iv: metaIv } = await versleutelBuffer(key, new TextEncoder().encode(meta));

    const raw = await crypto.subtle.exportKey('raw', key);
    return {
      fileBlob: new Blob([fileCt], { type: 'application/octet-stream' }),
      iv: bufToB64url(fileIv),
      metaCt: bufToB64url(metaCt),
      metaIv: bufToB64url(metaIv),
      key: bufToB64url(raw),
      grootte: fileCt.byteLength,
      origGrootte: bestand.size,
      mimetype: bestand.type || 'application/octet-stream',
    };
  }

  async function verzendTekst() {
    const bericht = berichtEl.value.trim();
    if (!bericht) throw new Error('Typ eerst een bericht.');
    if (bericht.length > 20000) throw new Error('Bericht is te lang (max 20.000 tekens).');

    const { ciphertext, iv, key } = await versleutelTekst(bericht);

    const res = await fetch('api/create.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        type: 'text',
        csrf_token: csrfEl.value,
        ciphertext: ciphertext,
        iv: iv,
        retentie_uren: parseInt(retentieEl.value, 10),
        wachtwoord: wachtwoordEl.value || null,
        notify_email: notifyEl.value.trim() || null,
      }),
    });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.fout || 'Opslaan mislukt.');
    return { data: data, key: key };
  }

  async function verzendBestand() {
    if (!gekozenBestand) throw new Error('Kies eerst een bestand.');
    if (gekozenBestand.size > MAX_BESTAND) {
      throw new Error('Bestand is te groot (max ' + formatBytes(MAX_BESTAND) + ').');
    }

    const enc = await versleutelBestand(gekozenBestand);

    const fd = new FormData();
    fd.append('type', 'file');
    fd.append('csrf_token', csrfEl.value);
    fd.append('iv', enc.iv);
    fd.append('meta_ct', enc.metaCt);
    fd.append('meta_iv', enc.metaIv);
    fd.append('mimetype', enc.mimetype);
    fd.append('origineel_bytes', String(enc.origGrootte));
    fd.append('retentie_uren', String(parseInt(retentieEl.value, 10)));
    if (wachtwoordEl.value) fd.append('wachtwoord', wachtwoordEl.value);
    const notify = notifyEl.value.trim();
    if (notify) fd.append('notify_email', notify);
    fd.append('bestand', enc.fileBlob, 'ciphertext.bin');

    const res = await fetch('api/create.php', {
      method: 'POST',
      body: fd,
    });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.fout || 'Opslaan mislukt.');
    return { data: data, key: enc.key };
  }

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    foutBox.style.display = 'none';

    if (!window.crypto || !window.crypto.subtle) {
      toonFout('Uw browser ondersteunt geen veilige encryptie. Gebruik een moderne browser via HTTPS.');
      return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Versleutelen...';

    try {
      const { data, key } = modus === 'bestand' ? await verzendBestand() : await verzendTekst();

      const link = data.base_url + '/sct/v.php?id=' + encodeURIComponent(data.id) + '#' + key;
      linkEl.value = link;
      const typeLabel = data.type === 'file' ? 'Bestand' : 'Bericht';
      metaEl.textContent = typeLabel + '-ID: ' + data.id + '  ·  Verloopt automatisch op: ' + data.verloopt_op;
      resultaatBox.style.display = 'block';
      resetFormBehoudResultaat();
      linkEl.focus();
      linkEl.select();
    } catch (err) {
      toonFout(err.message || 'Er ging iets mis.');
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="ri-lock-line me-1"></i> Beveiligde link genereren';
    }
  });

  function resetFormBehoudResultaat() {
    berichtEl.value = '';
    if (wachtwoordEl) wachtwoordEl.value = '';
    if (notifyEl) notifyEl.value = '';
    gekozenBestand = null;
    fileEl.value = '';
    fileInfoEl.style.display = 'none';
  }

  kopieerBtn.addEventListener('click', async function () {
    try {
      await navigator.clipboard.writeText(linkEl.value);
      kopieerBtn.innerHTML = '<i class="ri-check-line"></i> Gekopieerd';
      setTimeout(() => {
        kopieerBtn.innerHTML = '<i class="ri-file-copy-line"></i> Kopieer';
      }, 1500);
    } catch (err) {
      linkEl.select();
      document.execCommand('copy');
    }
  });

  nieuwBtn.addEventListener('click', resetForm);
})();
