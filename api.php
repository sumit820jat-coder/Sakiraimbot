<?php
// ============================================================
// api.php — PHANTOM Panel API (Final Fixed)
// ============================================================
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
date_default_timezone_set('UTC');

// DATABASE CREDENTIALS
define('DB_HOST', 'sql108.infinityfree.com'); define('DB_NAME', 'if0_43320186_Xyz');
define('DB_USER', 'if0_43320186'); define('DB_PASS', 'rBS9tVKstV');

function db() {
    static $pdo;
    if (!$pdo) {
        try {
            $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (Throwable $e) {
            out(['ok' => false, 'err' => 'DB Connection failed']);
        }
    }
    return $pdo;
}

function out($a) {
    if (ob_get_length()) ob_clean();
    echo json_encode($a, JSON_UNESCAPED_UNICODE);
    exit;
}

function body() {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    $arr = is_array($json) ? $json : $_POST;
    return array_merge($_GET, $arr);
}

try {
    $input  = body();
    $action = isset($input['a']) ? $input['a'] : (isset($_REQUEST['a']) ? $_REQUEST['a'] : '');

    switch ($action) {
        case 'sync':         do_sync($input);         break;
        case 'login':        do_login($input);        break;
        case 'admin':        do_admin($input);        break;
        case 'panel_sync':   do_panel_sync($input);   break;
        case 'panel_update': do_panel_update($input); break;
        default:             out(['ok' => false, 'err' => 'Unknown action']);
    }
} catch (Throwable $e) { out(['ok' => false, 'err' => 'Server Error']); }

// 1. SYNC
function do_sync($in) {
    $cfg = build_cfg();
    $key = isset($in['key']) ? trim($in['key']) : '';
    $dev = isset($in['device']) ? trim($in['device']) : '';
    $rev = isset($in['rev']) ? (int)$in['rev'] : -1;

    $keyState = null;
    if ($key !== '') $keyState = check_key($key, $dev);

    $cfgJson = json_encode($cfg);
    $newRev  = crc32($cfgJson);
    if ($rev === $newRev) $cfg = null;

    out(['ok' => true, 'rev' => $newRev, 'cfg' => $cfg, 'key' => $keyState, 't' => time()]);
}

// 2. LOGIN (App)
function do_login($in) {
    $key   = isset($in['key'])    ? trim($in['key'])    : '';
    $dev   = isset($in['device']) ? trim($in['device']) : '';
    $name  = isset($in['name'])   ? $in['name']         : '';
    $model = isset($in['model'])  ? $in['model']        : '';
    $appv  = isset($in['appv'])   ? $in['appv']         : '';

    if ($key === '' || $dev === '') out(['ok' => false, 'err' => 'Missing key or device']);

    $st = db()->prepare('SELECT * FROM `keys` WHERE `key_code`=? LIMIT 1');
    $st->execute([$key]);
    $k = $st->fetch(PDO::FETCH_ASSOC);

    if (!$k)                out(['ok' => false, 'err' => 'Invalid license key']);
    if ($k['blocked'] == 1) out(['ok' => false, 'err' => 'This key is blocked']);
    if ($k['expires_at'] > 0 && $k['expires_at'] < time()) out(['ok' => false, 'err' => 'This key has expired']);

    $st = db()->prepare('SELECT `device_id` FROM `devices` WHERE `key_id`=?');
    $st->execute([$k['id']]);
    $devices = $st->fetchAll(PDO::FETCH_COLUMN);
    $bound   = in_array($dev, $devices, true);

    if (!$bound) {
        $limit = (int)$k['device_limit'];
        if ($limit > 0 && count($devices) >= $limit) out(['ok' => false, 'err' => 'Device limit reached ('.$limit.')']);
        
        $st = db()->prepare('INSERT INTO `devices` (`key_id`, `device_id`, `name`, `model`, `appv`, `first_seen`, `last_seen`) VALUES (?,?,?,?,?,?,?)');
        $now = time();
        $st->execute([$k['id'], $dev, $name, $model, $appv, $now, $now]);
        $devices[] = $dev;
    } else {
        db()->prepare('UPDATE `devices` SET `last_seen`=? WHERE `key_id`=? AND `device_id`=?')->execute([time(), $k['id'], $dev]);
    }

    if ($k['expires_at'] == 0 && $k['plan'] !== 'lifetime') {
        $secs = plan_seconds($k['plan'], (int)$k['plan_value']);
        $exp  = time() + $secs;
        db()->prepare('UPDATE `keys` SET `expires_at`=?, `activated_at`=? WHERE `id`=?')->execute([$exp, time(), $k['id']]);
        $k['expires_at'] = $exp;
    }

    $left = $k['expires_at'] > 0 ? human_left($k['expires_at'] - time()) : 'Lifetime';
    out(['ok'=>true, 'text'=>strtoupper($k['plan']).' • '.$left, 'plan'=>$k['plan'], 'left'=>$left]);
}

// 3. ADMIN
function do_admin($in) {
    $op = isset($in['op']) ? $in['op'] : '';

    if ($op === 'login') {
        $key = trim($in['key'] ?? '');
        $st = db()->prepare('SELECT * FROM `admin_keys` WHERE `access_key`=? LIMIT 1');
        $st->execute([$key]);
        if (!$st->fetch()) out(['ok' => false, 'err' => 'Invalid Admin Key']);
        out(['ok' => true, 'token' => $key, 'username' => 'Admin']);
    }

    $token = isset($in['token']) ? $in['token'] : '';
    $st = db()->prepare('SELECT * FROM `admin_keys` WHERE `access_key`=? LIMIT 1');
    $st->execute([$token]);
    if (!$st->fetch()) out(['ok' => false, 'err' => 'Session expired']);

    if ($op === 'stats') {
        $total   = (int) db()->query('SELECT COUNT(*) FROM `keys`')->fetchColumn();
        $blocked = (int) db()->query('SELECT COUNT(*) FROM `keys` WHERE `blocked`=1')->fetchColumn();
        $expired = (int) db()->query('SELECT COUNT(*) FROM `keys` WHERE `expires_at`>0 AND `expires_at`<UNIX_TIMESTAMP()')->fetchColumn();
        $devices = (int) db()->query('SELECT COUNT(*) FROM `devices`')->fetchColumn();
        out(['ok' => true, 'total' => $total, 'active' => max(0, $total - $blocked - $expired), 'blocked' => $blocked, 'expired' => $expired, 'devices' => $devices]);
    }

    // 🔥 FIXED: Key status logic (Inactive vs Active)
    if ($op === 'list') {
        $rows = db()->query('
            SELECT k.*, (SELECT COUNT(*) FROM `devices` d WHERE d.key_id = k.id) AS devices 
            FROM `keys` k ORDER BY k.id DESC LIMIT 500
        ')->fetchAll(PDO::FETCH_ASSOC);
        
        $now = time();
        foreach ($rows as &$r) {
            if ($r['plan'] === 'lifetime') {
                $r['status'] = 'lifetime';
                $r['left'] = 'Forever';
            } else if ($r['activated_at'] == 0) {
                $r['status'] = 'inactive';
                $r['left'] = 'Not Activated';
            } else if ($r['expires_at'] > 0 && $r['expires_at'] < $now) {
                $r['status'] = 'expired';
                $r['left'] = 'Expired';
            } else {
                $r['status'] = 'active';
                $r['left'] = human_left($r['expires_at'] - $now);
            }
            
            if ($r['blocked'] == 1) { $r['status'] = 'blocked'; }
            
            $r['devices'] = (int)$r['devices'];
            $r['device_limit'] = (int)$r['device_limit'];
        }
        out(['ok' => true, 'keys' => $rows]);
    }

    if ($op === 'gen') {
        $plan  = in_array($in['plan'] ?? 'days', ['hours','days','months','lifetime']) ? $in['plan'] : 'days';
        $value = (int)($in['value'] ?? 30);
        $limit = (int)($in['device_limit'] ?? 1);
        $count = max(1, min(100, (int)($in['count'] ?? 1)));
        $seller = trim($in['seller_name'] ?? 'FIXAMODS');
        
        $keys = [];
        $st = db()->prepare('INSERT INTO `keys` (`key_code`, `plan`, `plan_value`, `device_limit`, `seller_name`, `created_at`, `expires_at`, `blocked`) VALUES (?,?,?,?,?,?,0,0)');
        for ($i = 0; $i < $count; $i++) {
            $code = gen_key('PHTM');
            $st->execute([$code, $plan, $value, $limit, $seller, time()]);
            $keys[] = $code;
        }
        out(['ok' => true, 'keys' => $keys]);
    }

    if ($op === 'block' || $op === 'unblock') {
        db()->prepare('UPDATE `keys` SET `blocked`=? WHERE `key_code`=?')->execute([$op === 'block' ? 1 : 0, $in['key'] ?? '']);
        out(['ok' => true]);
    }
    
    if ($op === 'delete') {
        db()->prepare('DELETE d FROM `devices` d JOIN `keys` k ON k.id=d.key_id WHERE k.key_code=?')->execute([$in['key'] ?? '']);
        db()->prepare('DELETE FROM `keys` WHERE `key_code`=?')->execute([$in['key'] ?? '']);
        out(['ok' => true]);
    }

    if ($op === 'brand') {
        $b = read_group('brand');
        foreach (['app_name','edition','hint','footer1','footer2','button','telegram','youtube','logo','seller'] as $k) {
            if (isset($in[$k])) $b[$k] = (string)$in[$k];
        }
        foreach (['force_yt','force_tg'] as $k) if (isset($in[$k])) $b[$k] = (bool)(int)$in[$k];
        write_group('brand', $b);
        out(['ok' => true]);
    }
    if ($op === 'maint') {
        write_group('maint', ['on' => !empty($in['on']), 'title' => $in['title'] ?? 'MAINTENANCE MODE', 'msg' => $in['msg'] ?? 'Service temporarily unavailable.', 'end' => $in['end'] ?? '', 'btn' => $in['btn'] ?? 'OK']);
        out(['ok' => true]);
    }
    if ($op === 'update') {
        write_group('upd', ['on' => !empty($in['on']), 'force' => !empty($in['force']), 'title' => $in['title'] ?? 'NEW UPDATE', 'msg' => $in['msg'] ?? 'A new version is available.', 'url' => $in['url'] ?? '', 'btn' => $in['btn'] ?? 'UPDATE NOW']);
        out(['ok' => true]);
    }

    if ($op === 'get_features') out(['ok' => true, 'features' => db()->query('SELECT * FROM `panel_features` ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC)]);
    if ($op === 'add_feature') {
        db()->prepare('INSERT INTO `panel_features` (`feature_id`, `label`, `url`, `dest_path`, `delete_files`, `created_at`) VALUES (?,?,?,?,?,?)')->execute([$in['feature_id'] ?? '', $in['label'] ?? '', $in['url'] ?? '', $in['dest_path'] ?? '', $in['delete_files'] ?? '', time()]);
        out(['ok' => true]);
    }
    if ($op === 'edit_feature') {
        db()->prepare('UPDATE `panel_features` SET `feature_id`=?, `label`=?, `url`=?, `dest_path`=?, `delete_files`=? WHERE `id`=?')->execute([$in['feature_id'], $in['label'], $in['url'], $in['dest_path'], $in['delete_files'], (int)$in['id']]);
        out(['ok' => true]);
    }
    if ($op === 'delete_feature') {
        db()->prepare('DELETE FROM `panel_features` WHERE `id`=?')->execute([(int)$in['id']]);
        out(['ok' => true]);
    }

    out(['ok' => false, 'err' => 'Unknown op']);
}

// 4. PANEL SYNC
function do_panel_sync($in) {
    $dev = isset($in['device']) ? trim($in['device']) : '';
    $key = isset($in['key']) ? trim($in['key']) : '';
    
    $cfg = build_cfg();
    $keyState = null;
    $userInfo = ['key' => '—', 'first_login' => 0, 'expiry' => 0, 'seller' => 'FIXAMODS Store'];
    
    if ($key !== '') {
        $keyState = check_key($key, $dev);
        $st = db()->prepare('SELECT * FROM `keys` WHERE `key_code`=? LIMIT 1');
        $st->execute([$key]);
        $k = $st->fetch(PDO::FETCH_ASSOC);
        if ($k) {
            $userInfo['key'] = $k['key_code'];
            $userInfo['first_login'] = (int)$k['activated_at'];
            $userInfo['expiry'] = (int)$k['expires_at'];
            $userInfo['seller'] = !empty($k['seller_name']) ? $k['seller_name'] : 'FIXAMODS Store';
        }
    }

    $features = db()->query('SELECT * FROM `panel_features` ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
    $formattedFeatures = [];
    foreach ($features as $f) {
        $delArr = !empty($f['delete_files']) ? array_values(array_filter(array_map('trim', explode(',', $f['delete_files'])))) : [];
        $formattedFeatures[] = ['id' => $f['feature_id'], 'label' => $f['label'], 'url' => $f['url'], 'path' => $f['dest_path'], 'deleteFiles' => $delArr, 'state' => false];
    }

    out(['ok' => true, 'brand' => $cfg['brand'], 'maint' => $cfg['maint'], 'upd' => $cfg['upd'], 'key' => $keyState, 'user' => $userInfo, 'features' => $formattedFeatures]);
}

function do_panel_update($in) { out(['ok' => true]); }

// HELPERS
function read_group($k) {
    $st = db()->prepare('SELECT `v` FROM `settings` WHERE `k`=? LIMIT 1');
    $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? [] : (json_decode($v, true) ?: []);
}
function write_group($k, $data) {
    db()->prepare('INSERT INTO `settings` (`k`, `v`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `v` = VALUES(`v`)')->execute([$k, json_encode($data, JSON_UNESCAPED_UNICODE)]);
}
function build_cfg() {
    $brand = read_group('brand') + ['app_name'=>'FIXAMODS','edition'=>'SECURE ACCESS PORTAL','hint'=>'PHTM-XXXX-XXXX-XXXX','footer1'=>'Contact Admin for Access','footer2'=>'Your session starts on connect.','button'=>'INITIALIZE','telegram'=>'','youtube'=>'','logo'=>'','force_yt'=>false,'force_tg'=>false,'seller'=>'FIXAMODS'];
    $maint = read_group('maint') + ['on'=>false,'title'=>'SYSTEM MAINTENANCE','msg'=>'Service temporarily unavailable.','end'=>'','btn'=>'CLOSE'];
    $upd = read_group('upd') + ['on'=>false,'force'=>true,'title'=>'UPDATE REQUIRED','msg'=>'A new version is available.','url'=>'','btn'=>'UPDATE NOW'];
    return ['brand'=>$brand, 'maint'=>$maint, 'upd'=>$upd];
}
function check_key($key, $device) {
    $st = db()->prepare('SELECT * FROM `keys` WHERE `key_code`=? LIMIT 1');
    $st->execute([$key]);
    $k = $st->fetch(PDO::FETCH_ASSOC);
    if (!$k) return ['valid'=>false,'msg'=>'Key not found'];
    if ($k['blocked'] == 1) return ['valid'=>false,'msg'=>'Key blocked'];
    if ($k['expires_at'] > 0 && $k['expires_at'] < time()) return ['valid'=>false,'msg'=>'Key expired'];
    $st = db()->prepare('SELECT `id` FROM `devices` WHERE `key_id`=? AND `device_id`=?');
    $st->execute([$k['id'], $device]);
    if (!$st->fetch()) return ['valid'=>false,'msg'=>'Device not bound'];
    return ['valid'=>true,'msg'=>'','plan'=>$k['plan'],'expires_at'=>(int)$k['expires_at']];
}
function plan_seconds($plan, $value) {
    switch ($plan) {
        case 'hours':    return max(1,$value) * 3600;
        case 'days':     return max(1,$value) * 86400;
        case 'months':   return max(1,$value) * 30 * 86400;
        case 'lifetime': return 0;
    }
    return 86400;
}
function human_left($secs) {
    if ($secs <= 0) return 'Expired';
    $d = intdiv($secs, 86400); $h = intdiv($secs % 86400, 3600); $m = intdiv($secs % 3600, 60);
    if ($d > 0) return $d.'d '.$h.'h';
    if ($h > 0) return $h.'h '.$m.'m';
    return $m.'m '.($secs % 60).'s';
}
function gen_key($prefix) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $parts = [];
    for ($p = 0; $p < 3; $p++) {
        $s = '';
        for ($i = 0; $i < 4; $i++) $s .= $chars[random_int(0, strlen($chars)-1)];
        $parts[] = $s;
    }
    return $prefix.'-'.implode('-', $parts);
}
?>