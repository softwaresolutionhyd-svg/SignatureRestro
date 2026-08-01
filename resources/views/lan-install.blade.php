<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1a1a1a">
    <title>Install Signature App (LAN)</title>
    <style>
        :root {
            --bg: #141414;
            --card: #1f1f1f;
            --text: #f5f5f5;
            --muted: #b7b7b7;
            --accent: #c4a574;
            --ok: #3d8b6e;
            --warn: #c47a3a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: radial-gradient(circle at top, #2a2218, var(--bg) 45%);
            color: var(--text);
            min-height: 100vh;
            padding: 20px 16px 40px;
        }
        .wrap { max-width: 520px; margin: 0 auto; }
        h1 { font-size: 1.45rem; margin: 0 0 8px; }
        .lead { color: var(--muted); margin: 0 0 20px; line-height: 1.45; }
        .card {
            background: var(--card);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 14px;
        }
        .card h2 { margin: 0 0 10px; font-size: 1.05rem; }
        .step {
            display: flex;
            gap: 12px;
            margin: 12px 0;
            align-items: flex-start;
        }
        .num {
            flex: 0 0 28px;
            height: 28px;
            border-radius: 999px;
            background: var(--accent);
            color: #111;
            font-weight: 800;
            display: grid;
            place-items: center;
            font-size: .9rem;
        }
        .step p { margin: 0; line-height: 1.45; color: var(--text); }
        .muted { color: var(--muted); font-size: .92rem; }
        .btn {
            display: block;
            width: 100%;
            text-align: center;
            text-decoration: none;
            border: 0;
            border-radius: 12px;
            padding: 14px 16px;
            font-weight: 700;
            font-size: 1rem;
            margin-top: 10px;
        }
        .btn-primary { background: var(--accent); color: #111; }
        .btn-secondary { background: rgba(255,255,255,.08); color: var(--text); }
        .warn {
            border-left: 3px solid var(--warn);
            padding-left: 10px;
            color: #f0d2b0;
            font-size: .92rem;
            line-height: 1.4;
        }
        .ok {
            border-left: 3px solid var(--ok);
            padding-left: 10px;
            color: #b6e2d1;
            font-size: .92rem;
        }
        code {
            background: rgba(255,255,255,.08);
            padding: 2px 6px;
            border-radius: 6px;
            font-size: .88rem;
        }
        .check { margin-top: 8px; font-size: .9rem; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Install Signature App</h1>
    <p class="lead">
        Chrome sirf <strong>trusted HTTPS</strong> pe real <strong>Install app</strong> dikhata hai.
        Certificate warning / red ❌ ho to sirf <em>Create shortcut</em> aata hai.
    </p>

    <div class="card">
        <h2>1) Certificate (CA) install — ek dafa</h2>
        <div class="step">
            <div class="num">1</div>
            <p>Neeche button se <strong>CA file download</strong> karo.</p>
        </div>
        <div class="step">
            <div class="num">2</div>
            <p>
                Phone: <strong>Settings → Security → More security / Encryption &amp; credentials → Install a certificate → CA certificate</strong>
            </p>
        </div>
        <div class="step">
            <div class="num">3</div>
            <p>Downloaded file <code>signature-lan-ca.crt</code> select karke install karo. Warning aaye to bhi Install dabao (sirf is cafe PC ke liye).</p>
        </div>
        <a class="btn btn-primary" href="{{ $caUrl }}">Download CA certificate</a>
        <p class="muted check">Android 13/14: kabhi Settings search mein “CA certificate” likho.</p>
    </div>

    <div class="card">
        <h2>2) Site dubara kholo</h2>
        <div class="step">
            <div class="num">4</div>
            <p>Chrome <strong>poora band</strong> karke dubara kholo (recent se clear / force stop).</p>
        </div>
        <div class="step">
            <div class="num">5</div>
            <p>Yeh URL open karo (http mat use karo):</p>
        </div>
        <p><code>{{ $httpsUrl }}</code></p>
        <p class="ok">Address bar mein red ❌ / “Not secure” nahi hona chahiye. Lock / Secure dikhe.</p>
        <a class="btn btn-secondary" href="{{ $loginUrl }}">Open login</a>
    </div>

    <div class="card">
        <h2>3) Install app</h2>
        <div class="step">
            <div class="num">6</div>
            <p>Chrome menu (⋮) → <strong>Install app</strong> / <strong>Add to Home screen</strong> — ab real install aana chahiye, sirf shortcut nahi.</p>
        </div>
        <p class="warn">
            Agar ab bhi sirf “Create shortcut” aaye: CA install nahi hui, galat file select hui, ya Chrome restart nahi hua.
            Step 1–4 dubara karo.
        </p>
    </div>

    <p class="muted">Server: <code>{{ $host }}</code></p>
</div>
</body>
</html>
