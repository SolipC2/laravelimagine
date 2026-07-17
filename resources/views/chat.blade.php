<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Laravel Imagine · Grok</title>
    <style>
        :root { color-scheme: dark; --bg:#0b1220; --panel:#151e32; --line:#2a3654; --text:#e8eefc; --muted:#93a0bd; --accent:#7c9cff; --ok:#4ade80; --err:#f87171; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; font-family: ui-sans-serif, system-ui, sans-serif; background: radial-gradient(1200px 600px at 10% -10%, #1a2748, var(--bg)); color: var(--text); }
        main { max-width: 720px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
        h1 { font-size: 1.6rem; margin: 0 0 .25rem; }
        .sub { color: var(--muted); margin: 0 0 1.5rem; font-size: .95rem; }
        .badge { display:inline-block; font-size:.75rem; padding:.15rem .5rem; border-radius:999px; border:1px solid var(--line); color:var(--muted); margin-right:.35rem; }
        .badge.on { border-color: #355f3a; color: var(--ok); }
        .card { background: var(--panel); border: 1px solid var(--line); border-radius: 1rem; padding: 1rem; box-shadow: 0 20px 50px rgba(0,0,0,.25); }
        label { display:block; font-size:.85rem; color:var(--muted); margin-bottom:.35rem; }
        textarea { width:100%; min-height: 110px; resize: vertical; border-radius:.75rem; border:1px solid var(--line); background:#0d1528; color:var(--text); padding:.75rem; font: inherit; }
        .row { display:flex; gap:.75rem; flex-wrap:wrap; margin-top:.75rem; align-items:center; }
        select, button { border-radius:.65rem; border:1px solid var(--line); background:#0d1528; color:var(--text); padding:.55rem .9rem; font: inherit; }
        button { background: linear-gradient(180deg, #8aa8ff, #6b86e6); border:none; color:#0b1220; font-weight:600; cursor:pointer; }
        button:disabled { opacity:.55; cursor:wait; }
        #status { margin-top:1rem; min-height:1.25rem; color:var(--muted); font-size:.9rem; }
        #status.err { color: var(--err); }
        #status.ok { color: var(--ok); }
        #result { margin-top:1rem; }
        #result img, #result video { max-width:100%; border-radius:.75rem; border:1px solid var(--line); background:#000; }
        pre { white-space: pre-wrap; word-break: break-all; font-size:.75rem; color:var(--muted); max-height: 8rem; overflow:auto; }
    </style>
</head>
<body>
<main>
    <h1>Grok Imagine connector</h1>
    <p class="sub">laravel/ai image path · app-level video client · test chat</p>
    <p>
        <span class="badge {{ $hasKey ? 'on' : '' }}">XAI_API_KEY {{ $hasKey ? 'set' : 'missing' }}</span>
        <span class="badge {{ $fakeVideo ? 'on' : '' }}">video fake {{ $fakeVideo ? 'on' : 'off' }}</span>
    </p>

    <div class="card">
        <form id="chat-form">
            <label for="prompt">Prompt</label>
            <textarea id="prompt" name="prompt" required placeholder="A crystal rocket lifting off under twin moons…"></textarea>
            <div class="row">
                <div>
                    <label for="mode">Mode</label>
                    <select id="mode" name="mode">
                        <option value="image">Image (grok-imagine-image)</option>
                        <option value="video">Video (grok-imagine-video)</option>
                    </select>
                </div>
                <div>
                    <label for="fake">Gateway</label>
                    <select id="fake" name="fake">
                        <option value="1" selected>Fake (tests / no key)</option>
                        <option value="0">Live xAI</option>
                    </select>
                </div>
                <div style="margin-top:1.15rem">
                    <button type="submit" id="go">Generate</button>
                </div>
            </div>
        </form>
        <div id="status"></div>
        <div id="result"></div>
    </div>
</main>
<script>
const form = document.getElementById('chat-form');
const statusEl = document.getElementById('status');
const resultEl = document.getElementById('result');
const btn = document.getElementById('go');
const csrf = document.querySelector('meta[name="csrf-token"]').content;

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    statusEl.className = '';
    statusEl.textContent = 'Generating…';
    resultEl.innerHTML = '';
    btn.disabled = true;
    try {
        const body = {
            prompt: document.getElementById('prompt').value,
            mode: document.getElementById('mode').value,
            fake: document.getElementById('fake').value === '1',
            duration: 6,
        };
        const res = await fetch('/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (!data.ok) {
            statusEl.className = 'err';
            statusEl.textContent = data.error || ('HTTP ' + res.status);
            return;
        }
        statusEl.className = 'ok';
        statusEl.textContent = 'OK · ' + data.result.type;
        const r = data.result;
        if (r.type === 'image') {
            const src = r.base64
                ? ('data:' + (r.mime || 'image/png') + ';base64,' + r.base64)
                : r.url;
            resultEl.innerHTML = '<img alt="generated" src="' + src + '"><pre>' + JSON.stringify({url:r.url,mime:r.mime,meta:r.meta}, null, 2) + '</pre>';
        } else {
            const v = r.url
                ? '<video controls src="' + r.url + '"></video>'
                : '';
            resultEl.innerHTML = v + '<pre>' + JSON.stringify(r, null, 2) + '</pre>';
        }
    } catch (err) {
        statusEl.className = 'err';
        statusEl.textContent = String(err);
    } finally {
        btn.disabled = false;
    }
});
</script>
</body>
</html>
