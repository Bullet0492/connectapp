<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();
$base = basis_url();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>QR scannen - Connect App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #000; color: #fff; height: 100dvh; overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

        #video-container {
            position: fixed;
            inset: 0;
        }
        #qr-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Overlay met uitsnede */
        .scan-overlay {
            position: fixed;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }
        .scan-box {
            width: min(70vw, 280px);
            aspect-ratio: 1/1;
            position: relative;
            box-shadow: 0 0 0 2000px rgba(0,0,0,.55);
            border-radius: 12px;
        }
        /* Hoeklijnen */
        .scan-box::before, .scan-box::after,
        .scan-box span::before, .scan-box span::after {
            content: '';
            position: absolute;
            width: 28px;
            height: 28px;
            border-color: #fff;
            border-style: solid;
        }
        .scan-box::before  { top: 0;    left: 0;  border-width: 3px 0 0 3px; border-radius: 8px 0 0 0; }
        .scan-box::after   { top: 0;    right: 0; border-width: 3px 3px 0 0; border-radius: 0 8px 0 0; }
        .scan-box span::before { bottom: 0; left: 0;  border-width: 0 0 3px 3px; border-radius: 0 0 0 8px; }
        .scan-box span::after  { bottom: 0; right: 0; border-width: 0 3px 3px 0; border-radius: 0 0 8px 0; }

        /* Scan lijn animatie */
        .scan-line {
            position: absolute;
            left: 10%;
            right: 10%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #fff, transparent);
            animation: scan 2s ease-in-out infinite;
        }
        @keyframes scan {
            0%   { top: 15%; opacity: 1; }
            50%  { top: 80%; opacity: 1; }
            100% { top: 15%; opacity: 1; }
        }

        .scan-label {
            margin-top: 24px;
            font-size: 14px;
            color: rgba(255,255,255,.8);
            text-align: center;
            pointer-events: none;
        }

        /* Topbalk */
        .top-bar {
            position: fixed;
            top: 0; left: 0; right: 0;
            padding: 16px;
            background: linear-gradient(to bottom, rgba(0,0,0,.7), transparent);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 10;
            pointer-events: all;
        }
        .top-bar a {
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 15px;
        }

        /* Status onderaan */
        .bottom-bar {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            padding: 20px 20px max(20px, env(safe-area-inset-bottom));
            background: linear-gradient(to top, rgba(0,0,0,.7), transparent);
            text-align: center;
            z-index: 10;
        }
        #qr-status {
            font-size: 14px;
            color: rgba(255,255,255,.85);
        }
        #qr-status.error {
            color: #ff6b6b;
        }
        canvas { display: none; }
    </style>
</head>
<body>

<div id="video-container">
    <video id="qr-video" autoplay playsinline muted></video>
    <canvas id="qr-canvas"></canvas>
</div>

<div class="scan-overlay">
    <div class="scan-box">
        <span></span>
        <div class="scan-line"></div>
    </div>
    <div class="scan-label">Richt op het QR-label van de klant</div>
</div>

<div class="top-bar">
    <a href="<?= $base ?>/klanten/index.php">
        <i class="ri-arrow-left-line" style="font-size:20px;"></i>
        Terug
    </a>
</div>

<div class="bottom-bar">
    <div id="qr-status">Camera starten...</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
var qrStream = null;
var scanning = false;

function setStatus(msg, isError) {
    var el = document.getElementById('qr-status');
    el.innerHTML = msg;
    el.className = isError ? 'error' : '';
}

function startCamera() {
    setStatus('Camera starten...');
    navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }
    })
    .then(function(stream) {
        qrStream = stream;
        var video = document.getElementById('qr-video');
        video.srcObject = stream;
        video.play();
        video.addEventListener('playing', function() {
            scanning = true;
            setStatus('Zoeken naar QR-code...');
            requestAnimationFrame(tick);
        }, { once: true });
    })
    .catch(function(err) {
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
            setStatus('❌ Cameratoegang geweigerd. Tik op het slotje in de adresbalk → Camera → Toestaan, en laad de pagina opnieuw.', true);
        } else if (err.name === 'NotFoundError') {
            setStatus('❌ Geen camera gevonden op dit apparaat.', true);
        } else if (err.name === 'NotReadableError') {
            setStatus('❌ Camera is in gebruik door een andere app. Sluit andere apps en probeer opnieuw.', true);
        } else {
            setStatus('❌ ' + err.name + ': ' + err.message, true);
        }
    });
}

function tick() {
    if (!scanning) return;
    var video = document.getElementById('qr-video');
    var canvas = document.getElementById('qr-canvas');
    if (video.readyState < video.HAVE_ENOUGH_DATA) {
        requestAnimationFrame(tick);
        return;
    }
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    var ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0);
    var img = ctx.getImageData(0, 0, canvas.width, canvas.height);
    var code = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
    if (code) {
        scanning = false;
        handleResult(code.data);
        return;
    }
    requestAnimationFrame(tick);
}

function handleResult(tekst) {
    setStatus('✓ QR-code gevonden!');
    var match = tekst.match(/[?&]id=(\d+)/);
    if (match) {
        window.location.href = '<?= $base ?>/klanten/detail.php?id=' + match[1];
    } else {
        setStatus('Onbekende QR-code. Opnieuw scannen...', true);
        setTimeout(function() {
            scanning = true;
            setStatus('Zoeken naar QR-code...');
            requestAnimationFrame(tick);
        }, 2000);
    }
}

startCamera();
</script>
</body>
</html>
