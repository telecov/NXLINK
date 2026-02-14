<?php
date_default_timezone_set('America/Santiago');

require __DIR__ . '/../telegram.php';

define('LOG_DIR', '/var/log/nxdnreflector/');
define('DEBUG_MODE', true);

define('COOLDOWN_SECONDS', 20);
define('MAX_TRACK', 300);

// ✅ Ignorar callsigns “ruidosos” (repos que conectan/desconectan muchas veces)
define('IGNORE_CALLSIGNS', ['DVREFCK']);

function dbg($m){ if(DEBUG_MODE) echo "[DBG] $m\n"; }

function get_latest_log(): ?string {
    $files = glob(LOG_DIR . "NXDNReflector-*.log");
    if (!$files) return null;

    usort($files, function($a, $b) {
        preg_match('/(\d{4}-\d{2}-\d{2})/', basename($a), $ma);
        preg_match('/(\d{4}-\d{2}-\d{2})/', basename($b), $mb);

        $ta = isset($ma[1]) ? strtotime($ma[1]) : @filemtime($a);
        $tb = isset($mb[1]) ? strtotime($mb[1]) : @filemtime($b);

        return $tb <=> $ta;
    });

    return $files[0] ?? null;
}

$lastEvent = [];

function can_send(string $cs, string $type): bool {
    global $lastEvent;

    $key = strtoupper($cs) . "|" . $type;
    $now = time();

    if (isset($lastEvent[$key]) && ($now - $lastEvent[$key] < COOLDOWN_SECONDS)) {
        return false;
    }

    $lastEvent[$key] = $now;

    if (count($lastEvent) > MAX_TRACK) {
        asort($lastEvent);
        $lastEvent = array_slice($lastEvent, -MAX_TRACK, null, true);
    }

    return true;
}

function run_tail(string $file): bool {

    if (!is_readable($file)) {
        dbg("❌ Archivo NO legible: $file");
        sleep(2);
        return false;
    }

    dbg("🟢 Escuchando: $file");

    $cmd  = 'tail -n 0 -F ' . escapeshellarg($file);
    $proc = popen($cmd, 'r');

    if (!$proc) {
        dbg("❌ ERROR: no pude iniciar tail");
        sleep(2);
        return false;
    }

    while (!feof($proc)) {

        $latest = get_latest_log();
        if ($latest && $latest !== $file) {
            dbg("🔁 Nuevo log detectado → $latest");
            pclose($proc);
            return false;
        }

        $line = fgets($proc);
        if (!$line) { usleep(200000); continue; }

        $line = trim($line);
        if ($line === '') continue;

        // CONNECT
        if (preg_match('/\bAdding\s+([A-Z0-9]{3,12})\s*\(([^)]+)\)/i', $line, $m)) {
            $cs = strtoupper($m[1]);
            $ip = trim($m[2]);

            // ✅ FILTRO: ignora callsigns en lista negra
            if (in_array($cs, IGNORE_CALLSIGNS, true)) {
                continue;
            }

            if (can_send($cs, 'add')) {
                $msg = "✅ <b>Estación conectada</b>\n"
                     . "<b>{$cs}</b>\n"
                     . "🌐 {$ip}\n"
                     . "⏰ " . date('Y-m-d H:i:s');
                $r = telegram_send($msg);
                dbg("Conectada: $cs → " . ((isset($r['ok']) && $r['ok']) ? 'OK' : 'FAIL'));
            }
            continue;
        }

        // DISCONNECT
        if (preg_match('/\bRemoving\s+([A-Z0-9]{3,12})\s*\(([^)]+)\)\s*(disappeared|unlinked)/i', $line, $m)) {
            $cs = strtoupper($m[1]);
            $ip = trim($m[2]);
            $why = strtolower(trim($m[3]));

            // ✅ FILTRO: ignora callsigns en lista negra
            if (in_array($cs, IGNORE_CALLSIGNS, true)) {
                continue;
            }

            if (can_send($cs, 'rem')) {
                $msg = "❌ <b>Estación desconectada</b>\n"
                     . "<b>{$cs}</b>\n"
                     . "🌐 {$ip}\n"
                     . "ℹ️ {$why}\n"
                     . "⏰ " . date('Y-m-d H:i:s');
                $r = telegram_send($msg);
                dbg("Desconectada: $cs → " . ((isset($r['ok']) && $r['ok']) ? 'OK' : 'FAIL'));
            }
            continue;
        }
    }

    dbg("⚠ tail finalizó, reiniciando...");
    pclose($proc);
    sleep(1);
    return false;
}

dbg("🔄 Iniciando NXDN realtime...");

if (!telegram_ready()) {
    dbg("❌ Telegram NO configurado (bot_token/chat_id).");
    exit(1);
}

$current = get_latest_log();
if (!$current) {
    dbg("❌ No hay logs en " . LOG_DIR);
    exit(1);
}

dbg("Log inicial: $current");

while (true) {
    run_tail($current);
    sleep(1);

    $new = get_latest_log();
    if ($new && $new !== $current) {
        dbg("🔄 Cambiando a nuevo log: $new");
        $current = $new;
        sleep(1);
    }
}
