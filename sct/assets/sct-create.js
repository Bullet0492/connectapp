/**
 * SCT create — client-side AES-GCM encryptie.
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

  function toonFout(tekst) {
    foutTekst.textContent = tekst;
    foutBox.style.display = 'block';
    resultaatBox.style.display = 'none';
  }

  function resetForm() {
    form.reset();
    resultaatBox.style.display = 'none';
    foutBox.style.display = 'none';
  }

  // Base64url helpers
  function bufToB64url(buf) {
    const bytes = new Uint8Array(buf);
    let s = '';
    for (let i = 0; i < bytes.length; i++) s += String.fromCharCode(bytes[i]);
    return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  async function versleutel(plaintext) {
    const enc = new TextEncoder();
    const key = await crypto.subtle.generateKey(
      { name: 'AES-GCM', length: 256 },
      true,
      ['encrypt', 'decrypt']
    );
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const ct = await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv: iv },
      key,
      enc.encode(plaintext)
    );
    const raw = await crypto.subtle.exportKey('raw', key);
    return {
      ciphertext: bufToB64url(ct),
      iv: bufToB64url(iv),
      key: bufToB64url(raw),
    };
  }

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    foutBox.style.display = 'none';

    const bericht = berichtEl.value.trim();
    if (!bericht) {
      toonFout('Typ eerst een bericht.');
      return;
    }
    if (bericht.length > 20000) {
      toonFout('Bericht is te lang (max 20.000 tekens).');
      return;
    }

    if (!window.crypto || !window.crypto.subtle) {
      toonFout('Uw browser ondersteunt geen veilige encryptie. Gebruik een moderne browser via HTTPS.');
      return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Versleutelen...';

    try {
      const { ciphertext, iv, key } = await versleutel(bericht);

      const res = await fetch('api/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          csrf_token: csrfEl.value,
          ciphertext: ciphertext,
          iv: iv,
          retentie_uren: parseInt(retentieEl.value, 10),
          wachtwoord: wachtwoordEl.value || null,
          notify_email: notifyEl.value.trim() || null,
        }),
      });

      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error(data.fout || 'Opslaan mislukt.');
      }

      const link = data.base_url + '/sct/v.php?id=' + encodeURIComponent(data.id) + '#' + key;
      linkEl.value = link;
      metaEl.textContent = 'Bericht-ID: ' + data.id + '  ·  Verloopt automatisch op: ' + data.verloopt_op;
      resultaatBox.style.display = 'block';
      form.reset();
      linkEl.focus();
      linkEl.select();
    } catch (err) {
      toonFout(err.message || 'Er ging iets mis.');
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="ri-lock-line me-1"></i> Beveiligde link genereren';
    }
  });

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
