/**
 * SCT read — haalt ciphertext op en decodeert met de key uit het URL fragment.
 * De key verlaat nooit de browser.
 */
(function () {
  'use strict';

  const openBtn = document.getElementById('sctOpen');
  const wachtwoordEl = document.getElementById('sctWachtwoord');
  const foutBox = document.getElementById('sctFout');
  const stap1 = document.getElementById('sctStap1');
  const stap2 = document.getElementById('sctStap2');
  const tekstEl = document.getElementById('sctTekst');
  const kopieerBtn = document.getElementById('sctKopieer');
  const sluitBtn = document.getElementById('sctSluit');

  function toonFout(tekst) {
    foutBox.textContent = tekst;
    foutBox.style.display = 'block';
  }

  function b64urlToBuf(s) {
    s = s.replace(/-/g, '+').replace(/_/g, '/');
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

  async function decodeer(ciphertextB64, ivB64, keyB64) {
    const key = await crypto.subtle.importKey(
      'raw',
      b64urlToBuf(keyB64),
      { name: 'AES-GCM', length: 256 },
      false,
      ['decrypt']
    );
    const pt = await crypto.subtle.decrypt(
      { name: 'AES-GCM', iv: b64urlToBuf(ivB64) },
      key,
      b64urlToBuf(ciphertextB64)
    );
    return new TextDecoder().decode(pt);
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
    openBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Bezig met openen...';

    try {
      const res = await fetch(window.SCT_API_BASE + '/read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id: window.SCT_ID,
          wachtwoord: wachtwoord,
        }),
      });

      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error(data.fout || 'Ophalen mislukt.');
      }

      const plaintext = await decodeer(data.ciphertext, data.iv, keyB64);

      tekstEl.textContent = plaintext;
      stap1.style.display = 'none';
      stap2.style.display = 'block';

      // Wis de fragment uit de URL zodat bookmarks/history geen sleutel bevatten
      try {
        history.replaceState(null, '', window.location.pathname + window.location.search);
      } catch (_) {}
    } catch (err) {
      toonFout(err.message || 'Kon het bericht niet openen.');
      openBtn.disabled = false;
      openBtn.innerHTML = '<i class="ri-eye-line me-1"></i> Bericht openen';
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

  if (sluitBtn) {
    sluitBtn.addEventListener('click', function () {
      tekstEl.textContent = '';
      stap2.style.display = 'none';
      try { window.close(); } catch (_) {}
      document.body.innerHTML =
        '<div style="color:#fff;text-align:center;padding:40px;font-family:sans-serif;">' +
        '<h3>Bericht gesloten</h3>' +
        '<p>U kunt dit venster nu veilig sluiten.</p></div>';
    });
  }
})();
