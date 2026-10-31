<?php
session_start();
define('DB_HOST', 'sql108.infinityfree.com'); define('DB_NAME', 'if0_43320186_Xyz');
define('DB_USER', 'if0_43320186'); define('DB_PASS', 'rBS9tVKstV');

function db() {
    static $pdo;
    if (!$pdo) $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    return $pdo;
}

// Handle Logout
if (isset($_GET['logout'])) {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
    header("Location: admin.php"); exit;
}

// Session Validation
$session_error = '';
if (isset($_SESSION['admin_key'])) {
    try {
        $stmt = db()->prepare('SELECT * FROM admin_keys WHERE access_key = ? LIMIT 1');
        $stmt->execute([$_SESSION['admin_key']]);
        if (!$stmt->fetch()) {
            $_SESSION = array(); session_destroy();
            $session_error = "Admin key changed. Please login again.";
        }
    } catch (Throwable $e) { $_SESSION = array(); session_destroy(); $session_error = "Session error. Login again."; }
}

// Handle Login
$error = $session_error;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_key'])) {
    $key = trim($_POST['login_key']);
    try {
        $stmt = db()->prepare('SELECT * FROM admin_keys WHERE access_key = ? LIMIT 1');
        $stmt->execute([$key]);
        if ($stmt->fetch()) {
            session_regenerate_id(true);
            $_SESSION['admin_key'] = $key;
            header("Location: admin.php"); exit;
        } else { $error = "Invalid Admin Key"; }
    } catch (Throwable $e) { $error = "Server error"; }
}

if (!isset($_SESSION['admin_key']) || !empty($session_error)) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>FIXAMODS · Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #00E5FF; --bg: #05070A; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: var(--bg); color: #fff; font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; padding: 20px; }
        .bg-grid { position: absolute; inset: 0; background-image: linear-gradient(rgba(0,229,255,0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(0,229,255,0.05) 1px, transparent 1px); background-size: 50px 50px; z-index: -1; animation: gridMove 15s linear infinite; }
        @keyframes gridMove { from { background-position: 0 0; } to { background-position: 0 50px; } }
        .orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.4; z-index: -1; }
        .orb1 { width: 300px; height: 300px; background: var(--primary); top: -100px; left: -100px; }
        .orb2 { width: 250px; height: 250px; background: #9D4EDD; bottom: -50px; right: -50px; }
        .login-card { background: rgba(10, 15, 28, 0.6); backdrop-filter: blur(24px); border: 1px solid rgba(0,229,255,0.2); padding: 40px 25px; border-radius: 24px; width: 100%; max-width: 400px; text-align: center; box-shadow: 0 30px 60px rgba(0,0,0,0.6); }
        .logo { font-family: 'Orbitron', sans-serif; font-size: 26px; font-weight: 900; color: #fff; margin-bottom: 10px; letter-spacing: 2px; text-shadow: 0 0 20px rgba(0,229,255,0.4); }
        .logo span { color: var(--primary); }
        input { width: 100%; padding: 16px; background: rgba(0,0,0,0.4); border: 1px solid #333; border-radius: 12px; color: #fff; font-size: 16px; outline: none; text-align: center; font-family: monospace; margin-bottom: 15px; }
        input:focus { border-color: var(--primary); box-shadow: 0 0 15px rgba(0,229,255,0.2); }
        button { width: 100%; padding: 16px; background: linear-gradient(135deg, #00E5FF, #9D4EDD); border: none; border-radius: 12px; font-weight: 800; font-family: 'Orbitron', sans-serif; font-size: 15px; cursor: pointer; color: #000; letter-spacing: 1px; }
        button:active { transform: scale(0.98); }
        .err { color: #FF5252; background: rgba(255,82,82,0.1); padding: 10px; border-radius: 8px; margin-top: 15px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="bg-grid"></div><div class="orb orb1"></div><div class="orb orb2"></div>
    <div class="login-card">
        <div class="logo">FIXA<span>MODS</span></div>
        <p style="color:#8793AD;font-size:12px;margin-bottom:25px;text-transform:uppercase;">Secure Admin Access</p>
        <form method="POST">
            <input type="password" name="login_key" placeholder="ENTER ACCESS KEY" required autocomplete="off">
            <button type="submit">UNLOCK</button>
        </form>
        <?php if ($error) echo "<div class='err'>$error</div>"; ?>
    </div>
</body>
</html>
<?php exit; } $adminKey = $_SESSION['admin_key']; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>FIXAMODS · Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --bg: #05070A; --card: rgba(15, 22, 40, 0.6); --primary: #00E5FF; --secondary: #9D4EDD; 
            --green: #00D084; --red: #FF5252; --yellow: #FFAA00; --text: #E2E8F0; --muted: #94A3B8; --border: rgba(0,229,255,0.15); 
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; padding-bottom: 80px; }
        
        .orb { position: fixed; border-radius: 50%; filter: blur(80px); z-index: -1; opacity: 0.3; animation: float 10s infinite alternate; }
        .orb1 { width: 300px; height: 300px; background: var(--primary); top: -100px; left: -100px; }
        .orb2 { width: 300px; height: 300px; background: var(--secondary); bottom: 50px; right: -100px; animation-delay: -5s; }
        @keyframes float { 0% { transform: translate(0,0) scale(1); } 100% { transform: translate(40px, 40px) scale(1.1); } }

        .topbar { position: sticky; top: 0; z-index: 100; background: rgba(5, 7, 10, 0.8); backdrop-filter: blur(10px); display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid var(--border); }
        .brand { font-family: 'Orbitron', sans-serif; font-size: 20px; font-weight: 900; color: #fff; letter-spacing: 1px; }
        .brand span { color: var(--primary); }
        .logout-icon { color: var(--red); font-size: 20px; text-decoration: none; padding: 5px; }

        .main { padding: 20px; max-width: 600px; margin: 0 auto; }
        .page { display: none; animation: fadeIn 0.3s ease-out; }
        .page.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .page-title { font-family: 'Orbitron', sans-serif; font-size: 20px; margin-bottom: 20px; color: #fff; display: flex; align-items: center; gap: 10px; }
        .page-title::before { content: ''; display: block; width: 3px; height: 20px; background: var(--primary); border-radius: 3px; }

        .card { background: var(--card); backdrop-filter: blur(20px); border: 1px solid var(--border); border-radius: 16px; padding: 20px; margin-bottom: 15px; box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        .card h3 { font-size: 14px; color: var(--primary); margin-bottom: 15px; font-family: 'Orbitron', sans-serif; }

        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .stat-box { background: rgba(15, 22, 40, 0.7); border: 1px solid var(--border); border-radius: 12px; padding: 15px; text-align: center; }
        .stat-box .title { font-size: 11px; color: var(--muted); text-transform: uppercase; font-weight: 700; margin-bottom: 5px; }
        .stat-box .val { font-size: 24px; font-weight: 900; font-family: 'Orbitron', sans-serif; }

        label { display: block; font-size: 11px; color: var(--muted); margin-bottom: 6px; font-weight: 700; text-transform: uppercase; }
        input, select { width: 100%; padding: 14px; background: rgba(0,0,0,0.4); border: 1px solid #333; border-radius: 10px; color: #fff; outline: none; font-family: 'Inter', sans-serif; font-size: 14px; margin-bottom: 12px; }
        input:focus, select:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(0,229,255,0.1); }
        
        .btn { width: 100%; padding: 15px; border-radius: 10px; border: none; cursor: pointer; font-weight: 800; font-family: 'Orbitron', sans-serif; font-size: 14px; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #000; transition: 0.2s; }
        .btn:active { transform: scale(0.97); }

        .list-item { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 15px; margin-bottom: 10px; display: flex; flex-direction: column; gap: 10px; }
        .list-header { display: flex; justify-content: space-between; align-items: center; }
        .list-key { font-family: 'Orbitron', monospace; font-weight: 700; font-size: 14px; color: #fff; }
        .list-details { display: flex; justify-content: space-between; font-size: 12px; color: var(--muted); }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .badge.ok { background: rgba(0,208,132,0.15); color: var(--green); border: 1px solid rgba(0,208,132,0.3); }
        .badge.warn { background: rgba(255,82,82,0.15); color: var(--red); border: 1px solid rgba(255,82,82,0.3); }
        .badge.inactive { background: rgba(255,170,0,0.15); color: var(--yellow); border: 1px solid rgba(255,170,0,0.3); }

        .del-btn { background: rgba(255,82,82,0.1); color: var(--red); border: 1px solid rgba(255,82,82,0.3); padding: 8px 12px; border-radius: 8px; font-size: 11px; font-weight: bold; width: 100%; margin-top: 5px; }

        .switch-box { display: flex; justify-content: space-between; align-items: center; padding: 15px; background: rgba(0,0,0,0.3); border: 1px solid #333; border-radius: 10px; margin-bottom: 12px; }
        .switch-box span { font-size: 13px; font-weight: 600; }
        .switch-ui { width: 46px; height: 24px; border-radius: 12px; background: #222; position: relative; transition: 0.3s; }
        .switch-ui::after { content: ''; position: absolute; width: 18px; height: 18px; border-radius: 50%; background: #666; top: 3px; left: 3px; transition: 0.3s; }
        .switch-box.on .switch-ui { background: rgba(0,208,132,0.2); border: 1px solid var(--green); }
        .switch-box.on .switch-ui::after { left: 25px; background: var(--green); }

        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; background: rgba(10, 15, 28, 0.95); backdrop-filter: blur(20px); border-top: 1px solid var(--border); display: flex; justify-content: space-around; padding: 10px 0; z-index: 1000; }
        .nav-item { display: flex; flex-direction: column; align-items: center; gap: 4px; color: var(--muted); font-size: 10px; font-weight: 600; cursor: pointer; transition: 0.3s; width: 20%; }
        .nav-item svg { width: 22px; height: 22px; fill: currentColor; }
        .nav-item.active { color: var(--primary); }
        .nav-item.active svg { filter: drop-shadow(0 0 5px var(--primary)); }

        #toast { position: fixed; bottom: 90px; left: 50%; transform: translateX(-50%) translateY(20px); padding: 12px 20px; background: rgba(15,22,40,0.9); border: 1px solid var(--primary); border-radius: 10px; font-size: 13px; font-weight: 600; opacity: 0; transition: 0.3s; z-index: 1000; pointer-events: none; width: max-content; max-width: 90%; text-align: center; }
        #toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    </style>
</head>
<body>
    <div class="orb orb1"></div><div class="orb orb2"></div>

    <div class="topbar">
        <div class="brand">FIXA<span>MODS.</span></div>
        <a href="?logout=1" class="logout-icon">⏻</a>
    </div>

    <div class="main">
        <!-- DASHBOARD -->
        <div id="page-dashboard" class="page active">
            <div class="page-title">Dashboard</div>
            <div class="stats-grid" id="statsGrid"></div>
        </div>

        <!-- GENERATE KEYS -->
        <div id="page-generate" class="page">
            <div class="page-title">Generate Keys</div>
            <div class="card">
                <label>Plan Type</label>
                <select id="gPlan"><option value="days" selected>Days</option><option value="hours">Hours</option><option value="months">Months</option><option value="lifetime">Lifetime</option></select>
                
                <label>Value</label>
                <input type="number" id="gValue" value="30">
                
                <label>Device Limit</label>
                <input type="number" id="gLimit" value="1">
                
                <label>Quantity</label>
                <input type="number" id="gCount" value="1">
                
                <label>Seller Name</label>
                <input type="text" id="gSeller" value="FIXAMODS">
                
                <button class="btn" onclick="genKeys()">⚡ Generate Now</button>
                <div id="genOut" style="margin-top: 20px;"></div>
            </div>
        </div>

        <!-- MANAGE KEYS -->
        <div id="page-keys" class="page">
            <div class="page-title">Manage Keys</div>
            <div id="keysList"></div>
        </div>

        <!-- FLOATING PANEL -->
        <div id="page-features" class="page">
            <div class="page-title">Floating Panel</div>
            <div class="card">
                <h3 id="fTitle">Add New Feature</h3>
                <input type="hidden" id="fIdEdit">
                
                <label>Feature ID</label>
                <input type="text" id="fId" placeholder="e.g. gun">
                
                <label>Label</label>
                <input type="text" id="fLabel" placeholder="e.g. GUN HOLOGRAM">
                
                <label>Download URL (.zip)</label>
                <input type="text" id="fUrl" placeholder="https://...">
                
                <label>Destination Path</label>
                <input type="text" id="fPath" value="/sdcard/Android/data/com.dts.freefireth/files/">
                
                <label>Delete Files (comma separated)</label>
                <input type="text" id="fDel" placeholder="gun.mod, gun.bin">
                
                <button class="btn" id="saveFeatureBtn" onclick="saveFeature()" style="margin-bottom:10px;">+ Add Feature</button>
                <button class="btn" id="cancelEditBtn" onclick="resetFeatureForm()" style="display:none; background:transparent; border:1px solid #555; color:#fff;">Cancel Edit</button>
            </div>
            <div id="featureList" style="margin-top: 15px;"></div>
        </div>

        <!-- SETTINGS -->
        <div id="page-settings" class="page">
            <div class="page-title">Settings</div>
            
            <div class="card">
                <h3>Branding</h3>
                <label>App Name</label><input id="b_app_name">
                <label>Edition</label><input id="b_edition">
                <label>Hint</label><input id="b_hint">
                <label>Button Text</label><input id="b_button">
                <label>Telegram</label><input id="b_telegram">
                <label>YouTube</label><input id="b_youtube">
                <label>Logo URL</label><input id="b_logo">
                <button class="btn" onclick="saveBrand()">Save Branding</button>
            </div>

            <div class="card">
                <h3>Maintenance Mode</h3>
                <div class="switch-box" onclick="toggleSwitch(this, 'm_on')"><span>Enable Maintenance</span><input type="checkbox" id="m_on"><div class="switch-ui"></div></div>
                <label>Title</label><input id="m_title">
                <label>Message</label><input id="m_msg">
                <button class="btn" onclick="saveMaint()">Save Maintenance</button>
            </div>

            <div class="card">
                <h3>Update Mode</h3>
                <div class="switch-box" onclick="toggleSwitch(this, 'u_on')"><span>Enable Update</span><input type="checkbox" id="u_on"><div class="switch-ui"></div></div>
                <div class="switch-box" onclick="toggleSwitch(this, 'u_force')"><span>Force Update</span><input type="checkbox" id="u_force"><div class="switch-ui"></div></div>
                <label>APK URL</label><input id="u_url">
                <button class="btn" onclick="saveUpdate()">Save Update</button>
            </div>
        </div>
    </div>

    <div class="bottom-nav">
        <div class="nav-item active" onclick="nav('dashboard', this)">
            <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
            <span>Home</span>
        </div>
        <div class="nav-item" onclick="nav('generate', this)">
            <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            <span>Generate</span>
        </div>
        <div class="nav-item" onclick="nav('keys', this)">
            <svg viewBox="0 0 24 24"><path d="M12.65 10C11.83 7.67 9.61 6 7 6c-3.31 0-6 2.69-6 6s2.69 6 6 6c2.61 0 4.83-1.67 5.65-4H17v4h4v-4h2v-4H12.65zM7 14c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>
            <span>Keys</span>
        </div>
        <div class="nav-item" onclick="nav('features', this)">
            <svg viewBox="0 0 24 24"><path d="M3 17v2h6v-2H3zM3 5v2h10V5H3zm10 16v-2h8v-2h-8v-2h-2v6h2zM7 9v2H3v2h4v2h2V9H7zm14 4v-2H11v2h10zm-6-4h2V7h4V5h-4V3h-2v6z"/></svg>
            <span>Panel</span>
        </div>
        <div class="nav-item" onclick="nav('settings', this)">
            <svg viewBox="0 0 24 24"><path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.06-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.73,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.06,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.49-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/></svg>
            <span>Settings</span>
        </div>
    </div>

    <div id="toast">Saved!</div>

    <script>
        const API = "api.php";
        const TOKEN = '<?php echo htmlspecialchars($adminKey); ?>';

        async function api(action, data = {}) {
            const body = new URLSearchParams({ a: action, token: TOKEN, ...data });
            try {
                const r = await fetch(API, { method: 'POST', body });
                const j = await r.json();
                if (j.err === 'Session expired' || j.err === 'Session check failed') {
                    toast('Session expired, redirecting...', true);
                    setTimeout(() => window.location.href = 'admin.php?logout=1', 800);
                    return { ok: false };
                }
                return j;
            } catch(e) { return { ok: false, err: 'Network error' }; }
        }

        function toast(msg, isErr = false) {
            const el = document.getElementById('toast'); el.textContent = msg;
            el.style.borderColor = isErr ? 'var(--red)' : 'var(--green)';
            el.classList.add('show'); setTimeout(() => el.classList.remove('show'), 2500);
        }

        function toggleSwitch(el, id) {
            const input = document.getElementById(id);
            input.checked = !input.checked; el.classList.toggle('on', input.checked);
        }

        function nav(page, el) {
            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
            document.getElementById('page-' + page).classList.add('active'); el.classList.add('active');
            
            if(page === 'keys') loadKeys();
            if(page === 'features') loadFeatures();
            if(page === 'settings') loadCfg();
        }

        window.onload = () => { loadStats(); loadCfg(); };

        async function loadStats() {
            const r = await api('admin', { op: 'stats' }); if (!r.ok) return;
            document.getElementById('statsGrid').innerHTML = `
                <div class="stat-box"><div class="title">Total Keys</div><div class="val" style="color:#fff;">${r.total}</div></div>
                <div class="stat-box"><div class="title">Active</div><div class="val" style="color:var(--green);">${r.active}</div></div>
                <div class="stat-box"><div class="title">Blocked</div><div class="val" style="color:var(--red);">${r.blocked}</div></div>
                <div class="stat-box"><div class="title">Devices</div><div class="val" style="color:var(--primary);">${r.devices}</div></div>
            `;
        }

        async function genKeys() {
            const data = { 
                op: 'gen', 
                plan: document.getElementById('gPlan').value, 
                value: document.getElementById('gValue').value, 
                device_limit: document.getElementById('gLimit').value, 
                count: document.getElementById('gCount').value, 
                seller_name: document.getElementById('gSeller').value 
            };
            const r = await api('admin', data);
            if (!r.ok) return toast(r.err, true);
            document.getElementById('genOut').innerHTML = r.keys.map(k => `
                <div style="padding:15px; background:rgba(0,229,255,0.05); border:1px dashed var(--primary); border-radius:10px; margin-bottom:10px; text-align:center; cursor:pointer; font-family:monospace; font-weight:bold; color:#fff;" onclick="navigator.clipboard.writeText('${k}');toast('Copied!')">
                    ${k} <br><span style="font-size:10px;color:var(--muted);font-weight:normal;">Tap to copy</span>
                </div>
            `).join('');
            toast('Keys Generated!'); loadStats();
        }

        async function loadKeys() {
            const r = await api('admin', { op: 'list' }); if (!r.ok) return;
            if(r.keys.length === 0) {
                document.getElementById('keysList').innerHTML = '<div style="text-align:center;color:var(--muted);padding:20px;">No keys found</div>';
                return;
            }
            document.getElementById('keysList').innerHTML = r.keys.map(k => {
                let badgeClass = 'inactive';
                let badgeText = 'Inactive';
                if (k.blocked == 1) { badgeClass = 'warn'; badgeText = 'Blocked'; }
                else if (k.status === 'expired') { badgeClass = 'warn'; badgeText = 'Expired'; }
                else if (k.status === 'active') { badgeClass = 'ok'; badgeText = 'Active'; }
                else if (k.status === 'lifetime') { badgeClass = 'ok'; badgeText = 'Lifetime'; }
                
                return `
                <div class="list-item">
                    <div class="list-header">
                        <div class="list-key">${k.key_code}</div>
                        <div class="badge ${badgeClass}">${badgeText}</div>
                    </div>
                    <div class="list-details">
                        <span>Plan: <b>${k.plan.toUpperCase()} ${k.plan_value}</b></span>
                        <span>Devices: <b>${k.devices}/${k.device_limit}</b></span>
                    </div>
                    <div class="list-details">
                        <span>Seller: <b>${k.seller_name}</b></span>
                        <span>Left: <b style="color:var(--primary);">${k.left}</b></span>
                    </div>
                    <button class="del-btn" onclick="quickAct('${k.key_code}','delete')">Delete Key</button>
                </div>`;
            }).join('');
        }

        async function quickAct(key, op) {
            if (!confirm('Delete this key?')) return;
            await api('admin', { op, key }); toast('Deleted'); loadKeys(); loadStats();
        }

        async function loadFeatures() {
            const r = await api('admin', { op: 'get_features' }); if (!r.ok) return;
            window.currentFeatures = r.features;
            if(r.features.length === 0) {
                document.getElementById('featureList').innerHTML = '<div style="text-align:center;color:var(--muted);padding:20px;">No features added</div>';
                return;
            }
            document.getElementById('featureList').innerHTML = r.features.map(f => `
                <div class="list-item">
                    <div class="list-header">
                        <div class="list-key" style="color:var(--primary);">${f.label}</div>
                    </div>
                    <div class="list-details">
                        <span>ID: <b>${f.feature_id}</b></span>
                    </div>
                    <div style="display:flex; gap:10px; margin-top:5px;">
                        <button class="del-btn" style="background:rgba(157,78,221,0.1); color:#9D4EDD; border-color:rgba(157,78,221,0.3);" onclick="editFeature(${f.id})">Edit</button>
                        <button class="del-btn" onclick="delFeature(${f.id})">Delete</button>
                    </div>
                </div>
            `).join('');
        }

        function editFeature(id) {
            const f = window.currentFeatures.find(x => x.id == id);
            document.getElementById('fIdEdit').value = f.id; 
            document.getElementById('fId').value = f.feature_id;
            document.getElementById('fLabel').value = f.label; 
            document.getElementById('fUrl').value = f.url;
            document.getElementById('fPath').value = f.dest_path; 
            document.getElementById('fDel').value = f.delete_files;
            document.getElementById('fTitle').innerText = 'Edit Feature'; 
            document.getElementById('saveFeatureBtn').innerText = 'Update';
            document.getElementById('cancelEditBtn').style.display = 'block'; 
            window.scrollTo(0, 0);
        }

        function resetFeatureForm() {
            document.getElementById('fIdEdit').value = ''; 
            document.getElementById('fId').value = ''; 
            document.getElementById('fLabel').value = '';
            document.getElementById('fUrl').value = ''; 
            document.getElementById('fPath').value = '/sdcard/Android/data/com.dts.freefireth/files/';
            document.getElementById('fDel').value = ''; 
            document.getElementById('fTitle').innerText = 'Add New Feature';
            document.getElementById('saveFeatureBtn').innerText = '+ Add Feature'; 
            document.getElementById('cancelEditBtn').style.display = 'none';
        }

        async function saveFeature() {
            const id = document.getElementById('fIdEdit').value;
            const data = { feature_id: document.getElementById('fId').value, label: document.getElementById('fLabel').value, url: document.getElementById('fUrl').value, dest_path: document.getElementById('fPath').value, delete_files: document.getElementById('fDel').value };
            let r;
            if (id) { data.op = 'edit_feature'; data.id = id; r = await api('admin', data); }
            else { data.op = 'add_feature'; r = await api('admin', data); }
            if (r.ok) { toast('Saved!'); resetFeatureForm(); loadFeatures(); } else toast(r.err, true);
        }

        async function delFeature(id) {
            if (!confirm('Delete this feature?')) return;
            await api('admin', { op: 'delete_feature', id }); toast('Deleted'); loadFeatures();
        }

        async function loadCfg() {
            const r = await api('sync', { rev: '-1' }); if (!r.ok) return;
            const b = r.cfg.brand || {}, m = r.cfg.maint || {}, u = r.cfg.upd || {};
            ['app_name','edition','hint','button','telegram','youtube','logo'].forEach(k => { const el = document.getElementById('b_'+k); if(el) el.value = b[k]||''; });
            document.getElementById('m_on').checked = !!m.on; document.getElementById('m_on').parentElement.classList.toggle('on', !!m.on);
            document.getElementById('m_title').value = m.title||''; document.getElementById('m_msg').value = m.msg||'';
            document.getElementById('u_on').checked = !!u.on; document.getElementById('u_on').parentElement.classList.toggle('on', !!u.on);
            document.getElementById('u_force').checked = !!u.force; document.getElementById('u_force').parentElement.classList.toggle('on', !!u.force);
            document.getElementById('u_url').value = u.url||'';
        }

        async function saveBrand() {
            const r = await api('admin', { op: 'brand', app_name: document.getElementById('b_app_name').value, edition: document.getElementById('b_edition').value, hint: document.getElementById('b_hint').value, button: document.getElementById('b_button').value, telegram: document.getElementById('b_telegram').value, youtube: document.getElementById('b_youtube').value, logo: document.getElementById('b_logo').value });
            toast(r.ok ? 'Saved!' : 'Failed', !r.ok);
        }
        
        async function saveMaint() {
            const r = await api('admin', { op: 'maint', on: document.getElementById('m_on').checked ? 1 : 0, title: document.getElementById('m_title').value, msg: document.getElementById('m_msg').value });
            toast(r.ok ? 'Saved!' : 'Failed', !r.ok);
        }
        
        async function saveUpdate() {
            const r = await api('admin', { op: 'update', on: document.getElementById('u_on').checked ? 1 : 0, force: document.getElementById('u_force').checked ? 1 : 0, url: document.getElementById('u_url').value });
            toast(r.ok ? 'Saved!' : 'Failed', !r.ok);
        }
    </script>
</body>
</html>