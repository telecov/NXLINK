<?php
// ===========================================================
// NXLINK (NXDN) - Notificaciones Telegram
// ===========================================================
// • Resumen estaciones conectadas (cada 1 hora con cron)
// • Reporte diario servidor (12:00)  -> FIX: se envía 1 vez al día desde las 12 en adelante
// • (Opcional) Cambio de IP pública
// • Log rotate: NXDNReflector-YYYY-MM-DD.log (automático)
// ===========================================================

require __DIR__ . '/../includes/telegram.php';

define('LOG_DIR', '/var/log/nxdnreflector/');
define('STATE_FILE', __DIR__ . '/../data/telegram_state.json');
define('TG_REMINDER_INTERVAL', 3600); // 1 hora
define('MAX_LOG_LINES', 500);
define('DEBUG_MODE', true);

// ✅ Hora a partir de la cual se permite enviar el reporte diario (no tiene que ser exacto)
define('DAILY_REPORT_AFTER_HOUR', 12);

function dbg($m){ if(DEBUG_MODE) echo "[DBG] $m\n"; }

// ======================================================
// 1) STATE
// ======================================================
function ensure_state(){
    if(!file_exists(STATE_FILE)){
        $init=[
            'summary'=>['last'=>0],
            'daily'=>['last'=>null],
            'ip_change'=>[
                'last_ip'=>null,
                'detected_ts'=>0,
                'notified'=>false
            ]
        ];
        @mkdir(dirname(STATE_FILE), 0755, true);
        file_put_contents(STATE_FILE, json_encode($init, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        return $init;
    }

    $st=json_decode(@file_get_contents(STATE_FILE), true);
    if(!is_array($st)){
        $st=[
            'summary'=>['last'=>0],
            'daily'=>['last'=>null],
            'ip_change'=>[
                'last_ip'=>null,
                'detected_ts'=>0,
                'notified'=>false
            ]
        ];
    }

    // asegurar claves mínimas
    $st['summary']['last'] = (int)($st['summary']['last'] ?? 0);
    $st['daily']['last']   = ($st['daily']['last'] ?? null);

    if (!isset($st['ip_change']) || !is_array($st['ip_change'])) {
        $st['ip_change']=['last_ip'=>null,'detected_ts'=>0,'notified'=>false];
    }

    // asegurar subclaves
    $st['ip_change']['last_ip']     = ($st['ip_change']['last_ip'] ?? null);
    $st['ip_change']['detected_ts'] = (int)($st['ip_change']['detected_ts'] ?? 0);
    $st['ip_change']['notified']    = (bool)($st['ip_change']['notified'] ?? false);

    return $st;
}

function save_state($st){
    file_put_contents(
        STATE_FILE,
        json_encode($st, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

// ✅ helper: define cuándo enviar el reporte diario
function should_send_daily($state, $today, $hour_now){
    return ($hour_now >= DAILY_REPORT_AFTER_HOUR) && (($state['daily']['last'] ?? null) !== $today);
}

// ======================================================
// 2) DETECTAR LOG MÁS RECIENTE (ROTATE)
// ======================================================
function get_latest_log(){
    $files=glob(LOG_DIR.'NXDNReflector-*.log');
    if(empty($files)) return null;

    usort($files, fn($a,$b)=>filemtime($b)-filemtime($a));
    return $files[0];
}

// ======================================================
// 3) EXTRAER BLOQUE “Currently linked repeaters:” (NXDN)
// ======================================================
function get_currently_linked($logfile){
    $lines=@file($logfile, FILE_IGNORE_NEW_LINES);
    if(!$lines){
        dbg("❌ No se pudo leer el log.");
        return null;
    }

    if(count($lines)>MAX_LOG_LINES){
        $lines=array_slice($lines, -MAX_LOG_LINES);
    }

    $linked=[];
    $found=false;
    $total=count($lines);

    // Buscar desde el final hacia arriba la última aparición del bloque
    for($i=$total-1;$i>=0;$i--){
        $ln=$lines[$i];
        if(strpos($ln,'Currently linked repeaters:')!==false){
            $found=true;

            // Leer hacia adelante
            for($j=$i+1;$j<$total;$j++){
                $ln2=$lines[$j];

                // Si aparece un nuevo bloque, cortamos
                if(strpos($ln2,'Currently linked repeaters:')!==false){
                    break;
                }

                // Parse NXDN
                if(preg_match(
                    '/^[MI]:\s*\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\.\d+\s+([A-Z0-9]{3,12})\s*:\s*([0-9\.]+|\[[0-9a-fA-F\:]+\])\:(\d+)\s+(\d+)\/(\d+)/',
                    $ln2,$m
                )){
                    $linked[]=[
                        'cs'=>$m[1],
                        'ip'=> "{$m[2]}:{$m[3]}",
                        'slot'=>$m[4],
                        'timeout'=>$m[5]
                    ];
                }

                // Si la línea deja de parecer del bloque y ya juntamos algo, cortamos
                if(!empty($linked) && trim($ln2)===''){
                    break;
                }
            }
            break;
        }
    }

    return ($found && !empty($linked)) ? $linked : null;
}

// ======================================================
// MAIN
// ======================================================
date_default_timezone_set('America/Santiago');

$state=ensure_state();
$today=date('Y-m-d');
$hour_now=(int)date('H');

$log=get_latest_log();
if(!$log){
    dbg("❌ No se encontró log válido NXDN.");
    exit;
}
dbg("Usando log: $log");

// ======================================================
// 1) RESUMEN HORARIO DE ESTACIONES
// ======================================================
$linked=get_currently_linked($log);

if($linked){
    $msg="📡 <b>NXLINK — Estaciones Conectadas</b>\n\n";
    foreach($linked as $stn){
        $msg.="• <b>{$stn['cs']}</b> — {$stn['ip']} ({$stn['slot']}/{$stn['timeout']})\n";
    }
    $msg.="\n🕒 ".date('Y-m-d H:i:s');

    if((time() - ($state['summary']['last'] ?? 0)) >= TG_REMINDER_INTERVAL){
        [$ok,$e]=telegram_send($msg);
        if($ok){
            $state['summary']['last']=time();
            save_state($state);
            dbg("✅ Resumen horario enviado.");
        } else {
            dbg("❌ Error Telegram: ".$e);
        }
    } else {
        dbg("Resumen NO enviado (intervalo aún no cumple).");
    }
} else {
    dbg("⚠️ No hay estaciones activas (o no se detectó bloque).");
}

// ======================================================
// 2) REPORTE DIARIO DEL SERVIDOR (12:00)
//    FIX: se envía en la primera ejecución >= 12:00 del día
// ======================================================
if(should_send_daily($state, $today, $hour_now)){

    $temp='N/A';
    if(file_exists('/sys/class/thermal/thermal_zone0/temp')){
        $temp=round(file_get_contents('/sys/class/thermal/thermal_zone0/temp')/1000, 1);
    }

    $uptime=trim(shell_exec("uptime -p"));
    $uptime=str_replace("up ","", $uptime);

    $free=shell_exec("free -m");
    preg_match('/Mem:\s+(\d+)\s+(\d+)\s+(\d+)/', $free, $m);
    $ram_total=$m[1] ?? 0;
    $ram_used=$m[2] ?? 0;
    $ram_pct=$ram_total ? round(($ram_used/$ram_total)*100, 1) : 0;

    $load=trim(shell_exec("cat /proc/loadavg | awk '{print $1\" \"$2\" \"$3}'"));

    $msg="🖥️ <b>NXLINK — Estado del Servidor</b>\n";
    $msg.="📅 ".date('Y-m-d H:i')."\n";
    $msg.="🌡️ Temp CPU: {$temp} °C\n";
    $msg.="⚙️ Uptime: {$uptime}\n";
    $msg.="💾 RAM: {$ram_used}/{$ram_total} MB ({$ram_pct}%)\n";
    $msg.="🔌 Carga CPU: {$load}\n";

    [$ok,$e]=telegram_send($msg);
    if($ok){
        $state['daily']['last']=$today;
        save_state($state);
        dbg("✅ Reporte diario enviado.");
    } else {
        dbg("❌ Error Telegram (diario): ".$e);
    }
}

// ======================================================
// 3) DETECTOR DE CAMBIO DE IP PÚBLICA (CONFIRMADO 15 MIN)
// ======================================================
$ip_actual = trim(shell_exec("curl -s https://api.ipify.org"));
if ($ip_actual) {

    $registro_ip = &$state['ip_change'];

    if (($registro_ip['last_ip'] ?? null) === null) {
        $registro_ip['last_ip'] = $ip_actual;
        $registro_ip['detected_ts'] = 0;
        $registro_ip['notified'] = false;
        save_state($state);
        dbg("IP inicial registrada: $ip_actual");
    }
    elseif ($ip_actual === $registro_ip['last_ip']) {
        $registro_ip['detected_ts'] = 0;
        $registro_ip['notified'] = false;
        save_state($state);
        dbg("IP sin cambios: $ip_actual");
    }
    else {
        dbg("⚠️ IP CAMBIÓ: {$registro_ip['last_ip']} → {$ip_actual}");

        if (($registro_ip['detected_ts'] ?? 0) === 0) {
            $registro_ip['detected_ts'] = time();
            save_state($state);
            dbg("Iniciando temporizador 15 min...");
        }
        elseif (!($registro_ip['notified'] ?? false) && (time() - $registro_ip['detected_ts'] >= 15*60)) {

            $msg = "⚠️ <b>NXLINK — CAMBIO DE IP PÚBLICA</b>\n\n"
                 . "🔄 IP anterior: <code>{$registro_ip['last_ip']}</code>\n"
                 . "🌐 Nueva IP: <code>{$ip_actual}</code>\n\n"
                 . "📌 Actualiza hostfile / DNS / No-IP.\n"
                 . "⏱️ Confirmado tras 15 minutos.";

            [$ok,$e]=telegram_send($msg);
            if($ok){
                $registro_ip['last_ip'] = $ip_actual;
                $registro_ip['notified'] = true;
                $registro_ip['detected_ts'] = 0;
                save_state($state);
                dbg("✅ Notificación IP enviada.");
            } else {
                dbg("❌ Error Telegram (IP): ".$e);
            }
        }
    }
}

dbg("Ejecución finalizada.");
