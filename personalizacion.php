<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");


date_default_timezone_set('America/Santiago');

/* =============================
   LOGOUT
   ============================= */
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}


/* =============================
   ARCHIVOS CONFIG
   ============================= */
$configFile = __DIR__ . "/includes/config.json";
$tgFile     = __DIR__ . "/includes/config_telegram.json";

/* =============================
   CONFIG TELEGRAM (load)
   ============================= */
$tgCfg = file_exists($tgFile) ? json_decode(file_get_contents($tgFile), true) : [];
if (!is_array($tgCfg)) $tgCfg = [];
$tgCfg += [
  "bot_token"    => "",
  "chat_id"      => "",
  "invite_link"  => ""
];

/* =============================
   SEGURIDAD
   ============================= */
$securityFile = __DIR__ . '/includes/security.json';
$security = file_exists($securityFile) ? json_decode(file_get_contents($securityFile), true) : [];
if (!is_array($security)) $security = [];

if (!isset($_SESSION['nxdn_auth'])) {

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (!empty($security['password']) && password_verify($_POST['password'], $security['password'])) {
            $_SESSION['nxdn_auth'] = true;
            header("Location: personalizacion.php");
            exit;
        } else {
            $error = "Clave incorrecta.";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Acceso – Personalización NXDN</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="css/styles.css">
    </head>
    <body class="login-page">
  <div class="login-wrap">
    <div class="card-custom login-card">

        <div class="title-module text-center">🔐 Acceso requerido</div>
        <div class="divider-soft"></div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <label class="small-label">Clave de administración</label>
            <input type="password" name="password" class="form-control mb-3" required>
            <button class="btn btn-primary w-100">Acceder</button>
        </form>
<a href="index.php" class="btn btn-outline-secondary w-100">
    ← Volver al Dashboard
</a>

    </div>
</div>

    </body>
    </html>
    <?php
    exit;
}

/* =============================
   CARGA CONFIG PRINCIPAL
   ============================= */
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
if (!is_array($config)) $config = [];

$config += [
    "titulo"       => "NXDN LINK",
    "subtitulo"    => "Reflector NXDN",
    "frase"        => "La frecuencia que nos mantiene conectados",
    "tg_principal" => "30444",
    "logo"         => "img/nxlink_logo.png",
    "dvref_token"  => "",
    "dvref_host"   => "",
    "dvref_port"   => ""
];

function clean_text($v) {
    return is_string($v) ? trim($v) : "";
}

/* =============================
   HELPERS (Telegram / Logs / DVRef)
   ============================= */
function tg_send_raw(string $botToken, string $chatId, string $text, string $parseMode = "HTML"): array {
    if ($botToken === "" || $chatId === "") return [false, "Falta bot_token o chat_id"];

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $payload = [
        "chat_id" => $chatId,
        "text" => $text,
        "parse_mode" => $parseMode,
        "disable_web_page_preview" => true
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_TIMEOUT => 10,
    ]);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) return [false, "cURL: $err"];
    if ($code < 200 || $code >= 300) return [false, "HTTP $code"];
    $j = json_decode($resp, true);
    if (!is_array($j) || empty($j["ok"])) return [false, "Respuesta inválida Telegram"];
    return [true, null];
}

function get_latest_nxdn_log(): ?string {
    $files = glob("/var/log/nxdnreflector/NXDNReflector-*.log");
    if (empty($files)) return null;
    usort($files, fn($a,$b)=>filemtime($b)-filemtime($a));
    return $files[0];
}

function get_currently_linked_from_log(string $logFile, int $maxLines = 500): ?array {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES);
    if (!$lines) return null;

    if (count($lines) > $maxLines) $lines = array_slice($lines, -$maxLines);

    $linked = [];
    $found = false;
    $total = count($lines);

    for ($i = $total - 1; $i >= 0; $i--) {
        $ln = $lines[$i];
        if (strpos($ln, 'Currently linked repeaters:') !== false) {
            $found = true;

            for ($j = $i + 1; $j < $total; $j++) {
                $ln2 = $lines[$j];
                if (strpos($ln2, 'Currently linked repeaters:') !== false) break;

                if (preg_match(
                    '/^[MI]:\s*\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\.\d+\s+([A-Z0-9]{3,12})\s*:\s*([0-9\.]+|\[[0-9a-fA-F\:]+\])\:(\d+)\s+(\d+)\/(\d+)/',
                    $ln2, $m
                )) {
                    $linked[] = [
                        'cs' => $m[1],
                        'ip' => "{$m[2]}:{$m[3]}",
                        'slot' => $m[4],
                        'timeout' => $m[5]
                    ];
                }
            }
            break;
        }
    }

    return ($found && !empty($linked)) ? $linked : null;
}

function dvref_status_check_nxdn(array $cfg, string $cacheFile, int $cacheTtl = 300): array {

    $api  = "https://dvref.com/api/v2/nxdn/reflectors/"; // FIJO
    $tok  = trim((string)($cfg["dvref_token"] ?? ""));
    $host = strtolower(trim((string)($cfg["dvref_host"] ?? "")));
    $port = (int)($cfg["dvref_port"] ?? 0);
    $tg   = trim((string)($cfg["tg_principal"] ?? ""));

    if ($host === "" || $port <= 0 || $tg === "") {
        return ['status' => 'CONFIG DVREF INCOMPLETA', 'detail' => 'Falta dvref_host / dvref_port / tg_principal', 'last_verified_at' => null];
    }

    if ($cacheTtl > 0 && file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
        $cached = json_decode(@file_get_contents($cacheFile), true);
        if (is_array($cached)) return $cached;
    }

    $ch = curl_init($api);
    $headers = [
        "Accept: application/json",
        "User-Agent: NXLINK-Dashboard/1.0 (CA2RDP)"
    ];
    if ($tok !== "") $headers[] = "Authorization: Token {$tok}";

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) return ['status' => 'ERROR DVREF', 'detail' => "cURL: {$curlErr}", 'last_verified_at' => null];
    if ($httpCode < 200 || $httpCode >= 300) return ['status' => 'ERROR DVREF', 'detail' => "HTTP {$httpCode}", 'last_verified_at' => null];

    $data = json_decode($response, true);
    if (!is_array($data)) return ['status' => 'ERROR DVREF', 'detail' => 'Respuesta no es JSON', 'last_verified_at' => null];

    $reflectors = null;
    if (isset($data["reflectors"]) && is_array($data["reflectors"])) $reflectors = $data["reflectors"];
    elseif (isset($data["data"]["reflectors"]) && is_array($data["data"]["reflectors"])) $reflectors = $data["data"]["reflectors"];
    elseif (array_keys($data) === range(0, count($data) - 1)) $reflectors = $data;

    if (!is_array($reflectors)) return ['status' => 'ERROR DVREF', 'detail' => 'No se encontró lista de reflectores', 'last_verified_at' => null];

    $status = "OFFLINE DVREF";
    $lastVerified = null;

    foreach ($reflectors as $ref) {
        if (!is_array($ref)) continue;

        $dns  = strtolower(trim((string)($ref["dns"] ?? $ref["host"] ?? "")));
        $rport = (int)($ref["port"] ?? 0);
        $designator = (string)($ref["designator"] ?? $ref["tg"] ?? $ref["talkgroup"] ?? "");

        if ($dns === $host && $rport === $port && $designator === $tg) {
            $status = "ONLINE DVREF";
            $lastVerified = $ref["last_verified_at"] ?? null;
            break;
        }
    }

    $result = [
        "status" => $status,
        "detail" => ($status === "ONLINE DVREF") ? null : "No hubo match exacto (dns+port+designator)",
        "last_verified_at" => $lastVerified,
        "checked_at" => date("c")
    ];

    @mkdir(dirname($cacheFile), 0755, true);
    @file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

    return $result;
}

/* =============================
   GUARDAR / ACCIONES
   ============================= */
$msg = null;
$msgErr = null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['password'])) {

    $action = $_POST["action"] ?? "";

    // ====== GUARDAR IDENTIDAD
    if ($action === "save_visual") {

        if (isset($_POST["titulo"]))    $config["titulo"] = clean_text($_POST["titulo"]);
        if (isset($_POST["subtitulo"])) $config["subtitulo"] = clean_text($_POST["subtitulo"]);
        if (isset($_POST["frase"]))     $config["frase"] = clean_text($_POST["frase"]);

        if (!empty($_FILES["logo"]["name"]) && is_uploaded_file($_FILES["logo"]["tmp_name"])) {
            $allowed = ["png","jpg","jpeg","webp","gif"];
            $ext = strtolower(pathinfo($_FILES["logo"]["name"], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed, true)) {
                $newLogoRel = "img/logo_custom." . $ext;
                $newLogoAbs = __DIR__ . "/" . $newLogoRel;
                if (@move_uploaded_file($_FILES["logo"]["tmp_name"], $newLogoAbs)) {
                    $config["logo"] = $newLogoRel;
                }
            }
        }

        @file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        $msg = "✅ Identidad visual guardada.";
    }

    // ====== GUARDAR DVREF
    if ($action === "save_dvref") {
        if (isset($_POST["tg_principal"])) $config["tg_principal"] = clean_text($_POST["tg_principal"]);
        if (isset($_POST["dvref_token"]))  $config["dvref_token"]  = clean_text($_POST["dvref_token"]);
        if (isset($_POST["dvref_host"]))   $config["dvref_host"]   = clean_text($_POST["dvref_host"]);
        if (isset($_POST["dvref_port"]))   $config["dvref_port"]   = clean_text($_POST["dvref_port"]);

        @file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        $msg = "✅ Configuración DVRef guardada.";
    }

    // ====== GUARDAR TELEGRAM
    if ($action === "save_telegram") {
        $tgCfg["bot_token"]   = trim((string)($_POST["tg_bot_token"] ?? ""));
        $tgCfg["chat_id"]     = trim((string)($_POST["tg_chat_id"] ?? ""));
        $tgCfg["invite_link"] = trim((string)($_POST["tg_invite_link"] ?? ""));

        @file_put_contents($tgFile, json_encode($tgCfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        $msg = "✅ Telegram guardado.";
    }

    // ====== BOTONES MENSAJES (MANUAL)
    if ($action === "tg_test") {
        [$ok,$err] = tg_send_raw($tgCfg["bot_token"], $tgCfg["chat_id"], "✅ <b>NXLINK</b> — Prueba Telegram OK\n🕒 ".date("Y-m-d H:i:s"));
        if ($ok) $msg = "✅ Mensaje de prueba enviado.";
        else $msgErr = "❌ Telegram: ".$err;
    }

    if ($action === "tg_send_linked") {
        $log = get_latest_nxdn_log();
        if (!$log) {
            $msgErr = "❌ No se encontró log NXDN en /var/log/nxdnreflector/";
        } else {
            $linked = get_currently_linked_from_log($log);
            if (!$linked) {
                $msgErr = "⚠️ No se detectaron estaciones conectadas (o no se encontró el bloque) en el log más reciente.";
            } else {
                $text = "📡 <b>NXLINK — Estaciones Conectadas</b>\n\n";
                foreach ($linked as $stn) {
                    $text .= "• <b>{$stn['cs']}</b> — {$stn['ip']} ({$stn['slot']}/{$stn['timeout']})\n";
                }
                $text .= "\n🕒 ".date("Y-m-d H:i:s");
                [$ok,$err] = tg_send_raw($tgCfg["bot_token"], $tgCfg["chat_id"], $text);
                if ($ok) $msg = "✅ Resumen de estaciones enviado.";
                else $msgErr = "❌ Telegram: ".$err;
            }
        }
    }

    if ($action === "tg_send_server") {
        $temp='N/A';
        if (file_exists('/sys/class/thermal/thermal_zone0/temp')) $temp = round(file_get_contents('/sys/class/thermal/thermal_zone0/temp')/1000,1);

        $uptime = trim(shell_exec("uptime -p"));
        $uptime = str_replace("up ","",$uptime);

        $free = shell_exec("free -m");
        preg_match('/Mem:\s+(\d+)\s+(\d+)\s+(\d+)/',$free,$m);
        $ram_total=$m[1] ?? 0;
        $ram_used=$m[2] ?? 0;
        $ram_pct=$ram_total ? round(($ram_used/$ram_total)*100,1) : 0;

        $load=trim(shell_exec("cat /proc/loadavg | awk '{print $1\" \"$2\" \"$3}'"));

        $text="🖥️ <b>NXLINK — Estado del Servidor</b>\n";
        $text.="📅 ".date('Y-m-d H:i')."\n";
        $text.="🌡️ Temp CPU: {$temp} °C\n";
        $text.="⚙️ Uptime: {$uptime}\n";
        $text.="💾 RAM: {$ram_used}/{$ram_total} MB ({$ram_pct}%)\n";
        $text.="🔌 Carga CPU: {$load}\n";

        [$ok,$err] = tg_send_raw($tgCfg["bot_token"], $tgCfg["chat_id"], $text);
        if ($ok) $msg = "✅ Estado del servidor enviado.";
        else $msgErr = "❌ Telegram: ".$err;
    }

    if ($action === "tg_send_dvref") {
        $cacheFile = __DIR__ . "/data/dvref_status.json";
        $dv = dvref_status_check_nxdn($config, $cacheFile, 0); // forzar ahora

        $text="🌐 <b>NXLINK — Estado DVRef</b>\n\n";
        $text.="Estado: <b>".htmlspecialchars($dv["status"])."</b>\n";
        if (!empty($dv["last_verified_at"])) $text.="last_verified_at: <code>".htmlspecialchars($dv["last_verified_at"])."</code>\n";
        if (!empty($dv["detail"])) $text.="\nDetalle: <code>".htmlspecialchars($dv["detail"])."</code>\n";
        $text.="\n🕒 ".date("Y-m-d H:i:s");

        [$ok,$err] = tg_send_raw($tgCfg["bot_token"], $tgCfg["chat_id"], $text);
        if ($ok) $msg = "✅ Estado DVRef enviado.";
        else $msgErr = "❌ Telegram: ".$err;
    }

    // recargar por si guardaste
    $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : $config;
    if (!is_array($config)) $config = [];
    $config += [
        "titulo"=>"NXDN LINK","subtitulo"=>"Reflector NXDN","frase"=>"La frecuencia que nos mantiene conectados",
        "tg_principal"=>"30444","logo"=>"img/nxlink_logo.png","dvref_token"=>"","dvref_host"=>"","dvref_port"=>""
    ];

    $tgCfg = file_exists($tgFile) ? json_decode(file_get_contents($tgFile), true) : $tgCfg;
    if (!is_array($tgCfg)) $tgCfg = ["bot_token"=>"","chat_id"=>"","invite_link"=>""];
    $tgCfg += ["bot_token"=>"","chat_id"=>"","invite_link"=>""];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Personalización – NXLINK</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="css/styles.css">


<header class="header-banner">
  <div class="container-fluid">
    <div class="row align-items-center g-3">

      <?php
        $logoPath = trim((string)($config['logo'] ?? ''));
        if ($logoPath === '' || !file_exists(__DIR__ . '/' . $logoPath)) {
          $logoPath = 'img/nxlink_logo.png'; // fallback
        }
      ?>

      <!-- IZQUIERDA: logo + títulos -->
      <div class="col-md-8 col-12">
        <div class="header-title text-light d-flex align-items-center gap-3">
          <img
            src="<?php echo htmlspecialchars($logoPath); ?>"
            alt="Logo NXLINK"
            style="height:72px; width:72px; border-radius:12px; object-fit:contain; background:rgba(255,255,255,.06); padding:6px;"
          >
          <div>
            <div class="text-uppercase fw-bold" style="font-size:1.7rem; letter-spacing:0.05em;">
              <i class="bi bi-sliders"></i> PERSONALIZACIÓN NXLINK
            </div>
            <div class="mt-1" style="font-size:1rem; opacity:0.85;">
              Ajusta parámetros visuales, DVRef y Telegram
            </div>
          </div>
        </div>
      </div>

      <!-- DERECHA: hora + logout -->
      <div class="col-md-4 col-12">
        <div class="d-flex flex-column align-items-md-end align-items-start gap-1">
          <div class="small-label text-light" style="opacity:.85;">
            <i class="bi bi-clock-history"></i>
            Última actualización: <?php echo date("d-m-Y H:i:s"); ?>
          </div>

          <a href="?logout=1" class="btn btn-outline-light btn-sm">
            <i class="bi bi-box-arrow-right"></i> Cerrar sesión
          </a>
        </div>
      </div>

    </div>
  </div>
</header>


<main class="container-fluid py-3">
<?php include __DIR__ . '/includes/nav_nxdn.php'; ?>

<?php if (!empty($msg)): ?>
  <div class="alert alert-success text-center fw-semibold"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>
<?php if (!empty($msgErr)): ?>
  <div class="alert alert-danger text-center fw-semibold"><?php echo htmlspecialchars($msgErr); ?></div>
<?php endif; ?>

<div class="row g-4">

  <!-- IDENTIDAD VISUAL -->
  <div class="col-lg-6">
    <div class="card-custom">
      <div class="title-module"><i class="bi bi-palette"></i> Identidad Visual</div>
      <div class="divider-soft"></div>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_visual">

        <label class="small-label">Título del Dashboard</label>
        <input type="text" name="titulo" class="form-control mb-3" value="<?php echo htmlspecialchars($config['titulo']); ?>">

        <label class="small-label">Subtítulo</label>
        <input type="text" name="subtitulo" class="form-control mb-3" value="<?php echo htmlspecialchars($config['subtitulo']); ?>">

        <label class="small-label">Frase destacada</label>
        <input type="text" name="frase" class="form-control mb-3" value="<?php echo htmlspecialchars($config['frase']); ?>">

        <label class="small-label">Logo actual</label><br>
        <img src="<?php echo htmlspecialchars($config['logo']); ?>" style="height:80px; border-radius:10px; margin-bottom:10px;">
        <input type="file" name="logo" class="form-control mb-3" accept=".png,.jpg,.jpeg,.webp,.gif">

        <button class="btn btn-primary w-100 mt-2">Guardar Identidad</button>
      </form>
    </div>
  </div>

  <!-- DVREF -->
  <div class="col-lg-6">
    <div class="card-custom">
      <div class="title-module"><i class="bi bi-broadcast-pin"></i> Configuración DVRef</div>
      <div class="divider-soft"></div>

      <form method="post">
        <input type="hidden" name="action" value="save_dvref">

        <label class="small-label">TG Principal</label>
        <input type="number" name="tg_principal" class="form-control mb-3" value="<?php echo htmlspecialchars($config['tg_principal']); ?>">

        <label class="small-label">DVRef Token</label>
        <input type="text" name="dvref_token" class="form-control mb-3"
               value="<?php echo htmlspecialchars($config['dvref_token']); ?>"
               placeholder="Token DVRef (si aplica)">

        <label class="small-label">Host (DNS) publicado en DVRef</label>
        <input type="text" name="dvref_host" class="form-control mb-3"
               value="<?php echo htmlspecialchars($config['dvref_host']); ?>"
               placeholder="reflectores.zonadmr.cl">

        <label class="small-label">Puerto publicado en DVRef</label>
        <input type="number" name="dvref_port" class="form-control mb-3"
               value="<?php echo htmlspecialchars($config['dvref_port']); ?>"
               placeholder="41400">

        <button class="btn btn-primary w-100 mt-2">Guardar DVRef</button>
      </form>
    </div>
  </div>

  <!-- TELEGRAM + BOTONES MENSAJES -->
  <div class="col-lg-6">
    <div class="card-custom">
      <div class="title-module"><i class="bi bi-telegram"></i> Telegram</div>
      <div class="divider-soft"></div>

      <form method="post">
        <input type="hidden" name="action" value="save_telegram">

        <label class="small-label">Bot Token</label>
        <input type="password" name="tg_bot_token" class="form-control mb-3"
               value="<?php echo htmlspecialchars($tgCfg['bot_token'] ?? ''); ?>"
               placeholder="123456:ABCDEF...">

        <label class="small-label">Chat ID</label>
        <input type="text" name="tg_chat_id" class="form-control mb-3"
               value="<?php echo htmlspecialchars($tgCfg['chat_id'] ?? ''); ?>"
               placeholder="-1001234567890 o 12345678">

        <label class="small-label">Link de invitación (opcional)</label>
        <input type="text" name="tg_invite_link" class="form-control mb-3"
               value="<?php echo htmlspecialchars($tgCfg['invite_link'] ?? ''); ?>"
               placeholder="https://t.me/+xxxxx">

        <button class="btn btn-primary w-100 mt-2">Guardar Telegram</button>
      </form>

      <?php if (!empty($tgCfg["invite_link"])): ?>
        <div class="divider-soft mt-3"></div>
        <a class="btn btn-outline-light w-100" href="<?php echo htmlspecialchars($tgCfg["invite_link"]); ?>" target="_blank">
          <i class="bi bi-box-arrow-up-right"></i> Abrir invitación
        </a>
      <?php endif; ?>

      <div class="divider-soft mt-3"></div>
      <div class="title-module"><i class="bi bi-send"></i> Mensajes (manual)</div>
      <div class="small-label" style="opacity:.85;">Usa estos botones para probar antes de automatizar con CRON.</div>

      <div class="d-grid gap-2 mt-2">
        <form method="post">
          <input type="hidden" name="action" value="tg_test">
          <button class="btn btn-outline-success w-100">
            <i class="bi bi-check2-circle"></i> Probar Telegram
          </button>
        </form>

        <form method="post">
          <input type="hidden" name="action" value="tg_send_linked">
          <button class="btn btn-outline-info w-100">
            <i class="bi bi-people"></i> Enviar estaciones conectadas (ahora)
          </button>
        </form>

        <form method="post">
          <input type="hidden" name="action" value="tg_send_server">
          <button class="btn btn-outline-warning w-100">
            <i class="bi bi-hdd-network"></i> Enviar estado del servidor (ahora)
          </button>
        </form>

        <form method="post">
          <input type="hidden" name="action" value="tg_send_dvref">
          <button class="btn btn-outline-primary w-100">
            <i class="bi bi-globe2"></i> Enviar estado DVRef (ahora)
          </button>
        </form>
      </div>

      <div class="small-label mt-3" style="opacity:.85;">
        Log detectado: <b><?php echo htmlspecialchars(get_latest_nxdn_log() ?: "No encontrado"); ?></b>
      </div>

    </div>
  </div>

</div>
</main>

<?php include __DIR__ . '/includes/footer_nxdn.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
