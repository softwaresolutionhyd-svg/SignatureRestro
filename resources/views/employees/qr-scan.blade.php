<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>QR Attendance Scan — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    <style>
        body.qr-kiosk {
            margin: 0;
            min-height: 100vh;
            background: #0f172a;
            color: #e2e8f0;
            font-family: ui-sans-serif, system-ui, sans-serif;
        }
        .kiosk-wrap { max-width: 720px; margin: 0 auto; padding: 1.25rem; }
        .kiosk-head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .kiosk-head h1 { font-size: 1.25rem; margin: 0; }
        .cam-wrap {
            width: 100%;
            background: #020617;
            border-radius: 12px;
            overflow: hidden;
            aspect-ratio: 4 / 3;
            position: relative;
        }
        #cam { width: 100%; height: 100%; object-fit: cover; display: block; }
        #usbInput {
            position: absolute; left: -9999px; width: 1px; height: 1px; opacity: 0;
        }
        .result {
            margin-top: 1rem;
            padding: 1rem 1.15rem;
            border-radius: 12px;
            min-height: 4.5rem;
            background: #1e293b;
        }
        .result.ok { background: #14532d; color: #dcfce7; }
        .result.already { background: #1e3a5f; color: #dbeafe; }
        .result.fail { background: #7f1d1d; color: #fee2e2; }
        .result .name { font-size: 1.4rem; font-weight: 700; }
        .hint { color: #94a3b8; font-size: .9rem; margin-top: .75rem; }
    </style>
</head>
<body class="qr-kiosk">
    <div class="kiosk-wrap">
        <div class="kiosk-head">
            <h1><i class="bi bi-qr-code-scan me-2"></i>QR Attendance</h1>
            <a class="btn btn-sm btn-outline-light" href="{{ url('/') }}">Home</a>
        </div>
        <p class="mb-2">Camera ya USB scanner se employee card scan karein. Attendance grid khula hona zaroori nahi.</p>
        <div class="cam-wrap">
            <video id="cam" playsinline muted autoplay></video>
        </div>
        <input id="usbInput" type="text" autocomplete="off" autocapitalize="off" spellcheck="false">
        <div class="result" id="resultBox">
            <div class="text-secondary">Waiting for QR…</div>
        </div>
        <p class="hint">Phone camera se card scan karne par bhi Present automatic lag jayegi — QR attendance URL kholta hai.</p>
    </div>

    <script>
    (() => {
        const resultBox = document.getElementById('resultBox');
        const usbInput = document.getElementById('usbInput');
        const video = document.getElementById('cam');
        const checkInBase = @json(rtrim(url('/a'), '/'));
        let lastToken = '';
        let lastAt = 0;
        let busy = false;

        function tokenFromPayload(raw) {
            const text = String(raw || '').trim();
            const m = text.match(/\/a\/([a-fA-F0-9]{64})(?:[/?#]|$)/);
            if (m) return m[1].toLowerCase();
            if (/^[a-fA-F0-9]{64}$/.test(text)) return text.toLowerCase();
            return '';
        }

        function show(state, html) {
            resultBox.className = 'result ' + state;
            resultBox.innerHTML = html;
        }

        function beep(ok) {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = ok ? 880 : 220;
                gain.gain.value = 0.08;
                osc.start();
                osc.stop(ctx.currentTime + 0.12);
            } catch (e) {}
        }

        async function mark(token) {
            const now = Date.now();
            if (!token || busy) return;
            if (token === lastToken && (now - lastAt) < 4000) return;
            lastToken = token;
            lastAt = now;
            busy = true;
            show('', '<div>Checking…</div>');
            try {
                const res = await fetch(checkInBase + '/' + token + '?format=json', {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                const data = await res.json();
                const already = !!(data.already || (data.ok && /already/i.test(String(data.message || data.title || ''))));
                const name = (data.employee && data.employee.name) ? data.employee.name : '';
                const no = (data.employee && data.employee.employee_no) ? data.employee.employee_no : '';
                const state = already ? 'already' : (data.ok ? 'ok' : 'fail');
                const heading = already
                    ? 'Attendance already punched'
                    : (data.title || (data.ok ? 'Present' : 'Failed'));
                show(state, '<div class="name">' + heading + '</div>'
                    + (name ? '<div class="mt-1">' + name + (no ? ' · ' + no : '') + '</div>' : '')
                    + '<div class="mt-1">' + (data.message || '') + '</div>'
                    + '<div class="small mt-1">' + (data.date || '') + ' · ' + (data.time || '') + '</div>');
                beep(!!data.ok || already);
            } catch (e) {
                show('fail', '<div>Scan failed. Network / camera page dubara try karein.</div>');
                beep(false);
            } finally {
                busy = false;
                setTimeout(() => usbInput.focus(), 50);
            }
        }

        usbInput.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const token = tokenFromPayload(usbInput.value);
            usbInput.value = '';
            mark(token);
        });

        document.addEventListener('click', () => usbInput.focus());
        usbInput.focus();

        async function startCamera() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' } },
                    audio: false
                });
                video.srcObject = stream;
                await video.play();
            } catch (e) {
                resultBox.insertAdjacentHTML('beforeend', '<div class="small mt-2">Camera nahi mili — USB scanner ya phone camera se card scan karein.</div>');
                return;
            }

            if (!('BarcodeDetector' in window)) {
                resultBox.insertAdjacentHTML('beforeend', '<div class="small mt-2">Is browser mein live camera QR nahi. USB scanner ya phone camera use karein.</div>');
                return;
            }

            const detector = new BarcodeDetector({ formats: ['qr_code'] });
            const tick = async () => {
                try {
                    if (video.readyState >= 2) {
                        const codes = await detector.detect(video);
                        if (codes && codes[0] && codes[0].rawValue) {
                            mark(tokenFromPayload(codes[0].rawValue));
                        }
                    }
                } catch (e) {}
                requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        }

        startCamera();
    })();
    </script>
</body>
</html>
