<?php
session_start();
date_default_timezone_set('America/Santiago');

/* ===========================
   IDIOMA (i18n)
   =========================== */
require_once __DIR__ . "/includes/lang.php";
$langCode = $_SESSION['lang'] ?? 'es';

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
   AUTO-REFRESH (dashboard)
   ============================= */
$AUTO_REFRESH_SECONDS = 10; // cambia a 0 para desactivar

/* =============================
   CSRF
   ============================= */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

/* =============================
   AUTO-DETECCIÓN INTERFAZ PRINCIPAL
   ============================= */
function detect_primary_iface(): ?string {
  $dev = trim((string)@shell_exec("ip route show default 2>/dev/null | awk '{print $5}' | head -n 1"));
  if ($dev !== '') return $dev;

  $dev = trim((string)@shell_exec("ip -o -4 addr show up 2>/dev/null | awk '{print $2}' | head -n 1"));
  if ($dev !== '') return $dev;

  return null;
}

/* =============================
   NMCLI HELPERS
   ============================= */
function nm_get_connection_for_device(string $dev): ?string {
  $cmd = "nmcli -t -f DEVICE,NAME connection show --active 2>/dev/null";
  $out = trim(@shell_exec($cmd));
  if ($out === '') return null;

  foreach (explode("\n", $out) as $line) {
    if (!$line) continue;
    $parts = explode(':', $line, 2);
    if (count($parts) !== 2) continue;
    if ($parts[0] === $dev) return $parts[1];
  }
  return null;
}

/* =============================
   VALIDACIONES
   ============================= */
function is_valid_cidr(string $cidr): bool {
  if (!preg_match('/^(\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $cidr)) return false;
  [$ip, $mask] = explode('/', $cidr, 2);
  $oct = explode('.', $ip);
  foreach ($oct as $o) {
    if ((int)$o < 0 || (int)$o > 255) return false;
  }
  $m = (int)$mask;
  return ($m >= 0 && $m <= 32);
}

function is_valid_ip(string $ip): bool {
  return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
}

function is_valid_dns_list(string $dns): bool {
  $dns = trim($dns);
  if ($dns === '') return true;
  $parts = array_map('trim', explode(',', $dns));
  foreach ($parts as $p) {
    if ($p === '' || !is_valid_ip($p)) return false;
  }
  return true;
}

/* =============================
   GET IP/GW (nmcli -> fallback iproute2)
   ============================= */
function get_ipv4_cidr(string $dev): string {
  $v = trim((string)@shell_exec("nmcli -t -f IP4.ADDRESS device show " . escapeshellarg($dev) . " 2>/dev/null | head -n 1 | cut -d':' -f2"));
  if ($v !== '') return $v;

  $v = trim((string)@shell_exec("ip -o -4 addr show " . escapeshellarg($dev) . " 2>/dev/null | awk '{print $4}' | head -n 1"));
  return $v;
}

function get_gw_ipv4(string $dev): string {
  $v = trim((string)@shell_exec("nmcli -t -f IP4.GATEWAY device show " . escapeshellarg($dev) . " 2>/dev/null | head -n 1 | cut -d':' -f2"));
  if ($v !== '') return $v;

  $v = trim((string)@shell_exec("ip route show default dev " . escapeshellarg($dev) . " 2>/dev/null | awk '{print $3}' | head -n 1"));
  if ($v !== '') return $v;

  $v = trim((string)@shell_exec("ip route show default 2>/dev/null | awk '{print $3}' | head -n 1"));
  return $v;
}

/* =============================
   INTERFAZ SELECCIONADA
   ============================= */
$PRIMARY_IF = detect_primary_iface();
$ETH_IF     = $PRIMARY_IF ?: 'eth0';

/* =============================
   RUTAS
   ============================= */
$iniFile      = '/etc/NXDNReflector/NXDNReflector.ini';
$securityFile = __DIR__ . '/includes/security.json';
$configFile   = __DIR__ . "/includes/config.json";

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
];

/* =============================
   SEGURIDAD
   ============================= */
$security = file_exists($securityFile) ? json_decode(file_get_contents($securityFile), true) : [];
if (!is_array($security)) $security = [];

/* =============================
   LOGIN
   ============================= */
if (!isset($_SESSION['nxdn_auth'])) {

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (!empty($security['password']) && password_verify($_POST['password'], $security['password'])) {
      $_SESSION['nxdn_auth'] = true;
      header("Location: configuracion.php");
      exit;
    } else {
      $error = __("admin_wrong_password");
    }
  }
  ?>
  <!DOCTYPE html>
  <html lang="<?php echo htmlspecialchars($langCode); ?>">
  <head>
    <meta charset="UTF-8">
    <title><?php echo __("cfg_login_title"); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
  </head>
  <body class="login-page">
    <div class="login-wrap">
      <div class="card-custom login-card">

        <div class="d-flex justify-content-center gap-2 mb-2">
          <a href="?lang=es" class="btn btn-outline-light btn-sm <?php echo ($langCode==='es')?'active':''; ?>">🇪🇸 ES</a>
          <a href="?lang=en" class="btn btn-outline-light btn-sm <?php echo ($langCode==='en')?'active':''; ?>">🇺🇸 EN</a>
        </div>

        <div class="title-module text-center">
          <i class="bi bi-shield-lock"></i> <?php echo __("cfg_login_header"); ?>
        </div>

        <div class="divider-soft"></div>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
          <label class="small-label"><?php echo __("admin_password_label"); ?></label>
          <input type="password" name="password" class="form-control mb-3" required>
          <button class="btn btn-primary w-100"><?php echo __("admin_login_btn"); ?></button>
        </form>

        <a href="index.php" class="btn btn-outline-secondary w-100 mt-2">
          ← <?php echo __("admin_back_dashboard"); ?>
        </a>

      </div>
    </div>
  </body>
  </html>
  <?php
  exit;
}

/* =============================
   LEER INI
   ============================= */
$iniRaw = @file_get_contents($iniFile);
if ($iniRaw === false) $iniRaw = "";

/* Extraer valores actuales */
preg_match('/TG\s*=\s*(\d+)/', $iniRaw, $m);        $tg = $m[1] ?? '';
preg_match('/Daemon\s*=\s*(\d+)/', $iniRaw, $m);    $daemon = $m[1] ?? '';
preg_match('/FilePath\s*=\s*(.+)/', $iniRaw, $m);   $filePath = trim($m[1] ?? '');
preg_match('/FileRoot\s*=\s*(.+)/', $iniRaw, $m);   $fileRoot = trim($m[1] ?? '');
preg_match('/Port\s*=\s*(\d+)/', $iniRaw, $m);      $port = $m[1] ?? '';

/* =============================
   GUARDAR CAMBIOS INI
   ============================= */
$msg = null;

if (isset($_POST['save_ini'])) {
  if (is_file($iniFile)) @copy($iniFile, $iniFile . '.bak');

  $iniRaw = preg_replace('/TG\s*=\s*\d+/',        'TG='.(int)($_POST['tg'] ?? 0), $iniRaw);
  $iniRaw = preg_replace('/Daemon\s*=\s*\d+/',    'Daemon='.(int)($_POST['daemon'] ?? 0), $iniRaw);
  $iniRaw = preg_replace('/FilePath\s*=.*/',      'FilePath='.trim((string)($_POST['file_path'] ?? '')), $iniRaw);
  $iniRaw = preg_replace('/FileRoot\s*=.*/',      'FileRoot='.trim((string)($_POST['file_root'] ?? '')), $iniRaw);
  $iniRaw = preg_replace('/Port\s*=\s*\d+/',      'Port='.(int)($_POST['port'] ?? 0), $iniRaw);

  @file_put_contents($iniFile, $iniRaw);
  $msg = __("cfg_saved_ok");
}

/* =============================
   SERVICIO
   ============================= */
if (isset($_POST['service_action'])) {
  $allowed = ['start', 'stop', 'restart'];
  $act = $_POST['service_action'];
  if (in_array($act, $allowed, true)) {
    @shell_exec("sudo systemctl {$act} nxdnreflector 2>&1");
  }
}

if (isset($_POST['reboot'])) {
  @shell_exec("sudo reboot");
}

/* =============================
   CAMBIAR IP (nmcli) - GENÉRICO
   ============================= */
if (isset($_POST['action']) && $_POST['action'] === 'net_eth') {

  if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
    $msg = __("csrf_invalid");
  } else {

    $errors = [];
    $ip  = trim((string)($_POST['new_ip_eth'] ?? ''));
    $gw  = trim((string)($_POST['new_gw_eth'] ?? ''));
    $dns = trim((string)($_POST['dns_eth']    ?? ''));

    if ($ip !== '' && !is_valid_cidr($ip)) $errors[] = __("cfg_ip_invalid");
    if ($gw !== '' && !is_valid_ip($gw))   $errors[] = __("cfg_gw_invalid");
    if (!is_valid_dns_list($dns))          $errors[] = __("cfg_dns_invalid");

    if (!$errors && $ip === '' && $gw === '' && $dns === '') $errors[] = __("cfg_no_changes");

    $PRIMARY_IF = detect_primary_iface();
    $ETH_IF = $PRIMARY_IF ?: $ETH_IF;

    if (!$errors && !$PRIMARY_IF) $errors[] = "No se detectó una interfaz activa para configurar.";

    if ($errors) {
      $msg = implode("<br>", array_map('htmlspecialchars', $errors));
    } else {

      $conn = nm_get_connection_for_device($ETH_IF);

      if (!$conn) {
        $msg = "La interfaz <strong>".htmlspecialchars($ETH_IF)."</strong> no está gestionada por NetworkManager, por eso no puedo aplicar cambios con nmcli.";
      } else {

        $conn_esc = escapeshellarg($conn);
        $out = "";

        if ($ip !== '') {
          $out .= @shell_exec("sudo nmcli con mod $conn_esc ipv4.addresses " . escapeshellarg($ip) . " 2>&1");
          $out .= @shell_exec("sudo nmcli con mod $conn_esc ipv4.method manual 2>&1");
        }
        if ($gw !== '') $out .= @shell_exec("sudo nmcli con mod $conn_esc ipv4.gateway " . escapeshellarg($gw) . " 2>&1");
        if ($dns !== '') $out .= @shell_exec("sudo nmcli con mod $conn_esc ipv4.dns " . escapeshellarg($dns) . " 2>&1");

        $out .= @shell_exec("sudo nmcli con up $conn_esc 2>&1");
        $msg = __("cfg_eth_updated") . "<br><pre>" . htmlspecialchars($out) . "</pre>";
      }
    }
  }
}

/* =============================
   CAMBIO DE CLAVE
   ============================= */
if (isset($_POST['new_password'])) {
  $security['password'] = password_hash((string)$_POST['new_password'], PASSWORD_DEFAULT);
  @file_put_contents($securityFile, json_encode($security, JSON_PRETTY_PRINT));
  session_destroy();
  header("Location: configuracion.php");
  exit;
}

/* =============================
   INFO SISTEMA
   ============================= */
$serviceStatus = trim((string)@shell_exec("systemctl is-active nxdnreflector"));
$ipServidor    = trim((string)@shell_exec("hostname -I"));

$PRIMARY_IF = detect_primary_iface();
$ETH_IF = $PRIMARY_IF ?: $ETH_IF;

$ip_eth = $PRIMARY_IF ? get_ipv4_cidr($PRIMARY_IF) : '';
$gw_eth = $PRIMARY_IF ? get_gw_ipv4($PRIMARY_IF) : '';

/* ===== INFO PRO (SOLO LO QUE QUIERES) ===== */
$hostName  = trim((string)@shell_exec("hostname 2>/dev/null"));
$sysUptime = trim((string)@shell_exec("uptime -p 2>/dev/null"));
if ($sysUptime === '') $sysUptime = trim((string)@shell_exec("uptime 2>/dev/null"));

$svcUptime = trim((string)@shell_exec("systemctl show -p ActiveEnterTimestamp nxdnreflector 2>/dev/null | cut -d'=' -f2"));

$primaryPrettyIp = $ip_eth ?: "—";

/* Log tail */
$today   = date("Y-m-d");
$logFile = "/var/log/nxdnreflector/NXDNReflector-$today.log";
if (!file_exists($logFile)) {
  $files = glob("/var/log/nxdnreflector/NXDNReflector-*.log");
  if ($files) { rsort($files); $logFile = $files[0]; }
}
$logTail = '';
if (!empty($logFile) && file_exists($logFile)) {
  $logTail = trim((string)@shell_exec("tail -n 5 " . escapeshellarg($logFile) . " 2>/dev/null"));
}

/* Estado service */
$statusClass = ($serviceStatus === "active") ? "success" : "danger";

$logoConfiguracion = "img/logo.png";
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($langCode); ?>">
<head>
  <meta charset="UTF-8">
  <title><?php echo __("cfg_title"); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <?php if ($AUTO_REFRESH_SECONDS > 0): ?>
    <meta http-equiv="refresh" content="<?php echo (int)$AUTO_REFRESH_SECONDS; ?>">
  <?php endif; ?>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>

<header class="header-banner">
  <div class="container-fluid">
    <div class="row align-items-center g-3">

      <div class="col-md-8 col-12">
        <div class="header-title text-light d-flex align-items-center gap-3">
          <img src="<?php echo htmlspecialchars($logoConfiguracion); ?>" style="height:90px; border-radius:12px;">
          <div>
            <div class="text-uppercase fw-bold" style="font-size:1.7rem; letter-spacing:0.05em;">
              <i class="bi bi-sliders"></i> <?php echo __("cfg_header"); ?>
            </div>
            <div class="mt-1" style="font-size:1rem; opacity:0.85;">
              <?php echo __("cfg_subheader"); ?>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-4 col-12">
        <div class="d-flex flex-column align-items-md-end align-items-start gap-1">

          <div class="d-flex gap-2">
            <a href="?lang=es" class="btn btn-outline-light btn-sm <?php echo ($langCode==='es')?'active':''; ?>">🇪🇸 ES</a>
            <a href="?lang=en" class="btn btn-outline-light btn-sm <?php echo ($langCode==='en')?'active':''; ?>">🇺🇸 EN</a>
          </div>

          <div class="small-label text-light" style="opacity:.85;">
            <i class="bi bi-clock-history"></i>
            <?php echo __("last_update"); ?>: <?php echo date("d-m-Y H:i:s"); ?>
            <?php if ($AUTO_REFRESH_SECONDS > 0): ?>
              <span style="opacity:.75;">(auto <?php echo (int)$AUTO_REFRESH_SECONDS; ?>s)</span>
            <?php endif; ?>
          </div>

          <a href="?logout=1" class="btn btn-outline-light btn-sm">
            <i class="bi bi-box-arrow-right"></i> <?php echo __("admin_logout"); ?>
          </a>
        </div>
      </div>

    </div>
  </div>
</header>

<main class="container-fluid py-3">
  <?php include __DIR__ . '/includes/nav_nxdn.php'; ?>

  <?php if (!empty($msg)): ?>
    <?php $msgClass = (stripos($msg, 'no está gestionada') !== false) ? 'warning' : 'success'; ?>
    <div class="alert alert-<?php echo $msgClass; ?> text-center"><?php echo $msg; ?></div>
  <?php endif; ?>

  <div class="row g-4">

    <!-- NXDN CONFIG -->
    <div class="col-lg-6">
      <div class="card-custom">
        <div class="title-module">📝 <?php echo __("cfg_nxdn_config"); ?></div>
        <div class="divider-soft"></div>

        <form method="post">
          <label class="small-label"><?php echo __("cfg_tg_main"); ?></label>
          <input type="number" name="tg" class="form-control mb-2" value="<?php echo htmlspecialchars($tg); ?>">

          <label class="small-label"><?php echo __("cfg_daemon"); ?></label>
          <input type="number" name="daemon" class="form-control mb-3" min="0" max="1"
                 value="<?php echo htmlspecialchars($daemon); ?>" required>

          <label class="small-label"><?php echo __("cfg_log_path"); ?></label>
          <input type="text" name="file_path" class="form-control mb-2" value="<?php echo htmlspecialchars($filePath); ?>">

          <label class="small-label"><?php echo __("cfg_log_root"); ?></label>
          <input type="text" name="file_root" class="form-control mb-3" value="<?php echo htmlspecialchars($fileRoot); ?>">

          <label class="small-label"><?php echo __("cfg_port"); ?></label>
          <input type="number" name="port" class="form-control mb-3" value="<?php echo htmlspecialchars($port); ?>">

          <button class="btn btn-primary w-100" name="save_ini"><?php echo __("cfg_save_btn"); ?></button>
        </form>
      </div>
    </div>

    <!-- SERVICIO (LIMPIO) -->
    <div class="col-lg-6">
      <div class="card-custom">
        <div class="title-module">🔄 <?php echo __("cfg_service"); ?></div>
        <div class="divider-soft"></div>

        <div class="row g-3">

          <div class="col-md-6">
            <p class="small-label mb-1"><?php echo __("cfg_status"); ?>:</p>
            <div class="alert alert-<?php echo $statusClass; ?> text-center mb-0">
              <strong><?php echo htmlspecialchars(strtoupper($serviceStatus)); ?></strong>
            </div>
          </div>

          <div class="col-md-6">
            <p class="small-label mb-1">Activo desde:</p>
            <div class="form-control" style="background:#1c1f2a; color:#fff;">
              <?php echo $svcUptime ? htmlspecialchars($svcUptime) : "—"; ?>
            </div>
          </div>

          <div class="col-md-6">
            <p class="small-label mb-1">Servidor:</p>
            <div class="form-control" style="background:#1c1f2a; color:#fff;">
              <?php echo htmlspecialchars($hostName ?: "—"); ?>
            </div>
          </div>

          <div class="col-md-6">
            <p class="small-label mb-1">Uptime sistema:</p>
            <div class="form-control" style="background:#1c1f2a; color:#fff;">
              <?php echo htmlspecialchars($sysUptime ?: "—"); ?>
            </div>
          </div>

          <div class="col-md-6">
            <p class="small-label mb-1">IP servidor:</p>
            <div class="form-control" style="background:#1c1f2a; color:#fff;">
              <?php echo htmlspecialchars($ipServidor ?: "—"); ?>
            </div>
          </div>

          <div class="col-md-6">
            <p class="small-label mb-1">Interfaz principal (<?php echo htmlspecialchars($ETH_IF); ?>):</p>
            <div class="form-control" style="background:#1c1f2a; color:#fff;">
              <?php echo htmlspecialchars($primaryPrettyIp); ?>
            </div>
          </div>

        </div>

        <?php if (!empty($logTail)): ?>
          <div class="mt-3">
            <div class="small-label mb-2">Últimas líneas del log:</div>
            <pre class="form-control" style="background:#0f1118; color:#d7d7d7; white-space:pre-wrap;"><?php echo htmlspecialchars($logTail); ?></pre>
          </div>
        <?php endif; ?>

        <div class="divider-soft my-3"></div>

        <form method="post" class="d-flex gap-2 mb-2">
          <button class="btn btn-success w-100" name="service_action" value="start"><?php echo __("cfg_start"); ?></button>
          <button class="btn btn-warning w-100" name="service_action" value="restart"><?php echo __("cfg_restart"); ?></button>
          <button class="btn btn-danger w-100" name="service_action" value="stop"><?php echo __("cfg_stop"); ?></button>
        </form>

        <form method="post" class="mb-2">
          <button class="btn btn-outline-danger w-100"
                  onclick="return confirm('<?php echo __("cfg_reboot_confirm"); ?>')"
                  name="reboot"><?php echo __("cfg_reboot"); ?></button>
        </form>

      </div>
    </div>

    <!-- ETHERNET -->
    <div class="col-lg-12">
      <div class="card-custom">
        <div class="title-module">🌐 <?php echo __("cfg_eth_title"); ?> (<?php echo htmlspecialchars($ETH_IF); ?>)</div>
        <div class="divider-soft"></div>

        <p class="small-label"><?php echo __("cfg_current_ip"); ?>:</p>
        <div class="form-control mb-3" style="background:#1c1f2a; color:#fff;">
          <?php echo $ip_eth ? htmlspecialchars($ip_eth) : __("cfg_no_ip"); ?>
        </div>

        <p class="small-label"><?php echo __("cfg_current_gw"); ?>:</p>
        <div class="form-control mb-3" style="background:#1c1f2a; color:#fff;">
          <?php echo $gw_eth ? htmlspecialchars($gw_eth) : __("cfg_no_gw"); ?>
        </div>

        <form method="post" autocomplete="off"
              onsubmit="return confirm('<?php echo __("cfg_change_net_confirm"); ?>');">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
          <input type="hidden" name="action" value="net_eth">

          <label class="small-label"><?php echo __("cfg_new_ip"); ?></label>
          <input type="text" name="new_ip_eth" class="form-control mb-2" placeholder="<?php echo __("cfg_leave_blank"); ?>">

          <label class="small-label"><?php echo __("cfg_new_gw"); ?></label>
          <input type="text" name="new_gw_eth" class="form-control mb-2" placeholder="<?php echo __("cfg_leave_blank"); ?>">

          <label class="small-label"><?php echo __("cfg_dns"); ?></label>
          <input type="text" name="dns_eth" class="form-control mb-3" placeholder="<?php echo __("cfg_leave_blank"); ?>">

          <button class="btn btn-warning w-100"><?php echo __("cfg_apply_changes"); ?></button>
        </form>

        <p class="small-label mt-3" style="opacity:.8;">
          <?php echo __("cfg_net_reco"); ?>
        </p>
      </div>
    </div>

    <!-- SEGURIDAD -->
    <div class="col-lg-12">
      <div class="card-custom">
        <div class="title-module">🔐 <?php echo __("cfg_security"); ?></div>
        <div class="divider-soft"></div>

        <form method="post">
          <label class="small-label"><?php echo __("cfg_new_password"); ?></label>
          <input type="password" name="new_password" class="form-control mb-2">
          <button class="btn btn-warning w-100"><?php echo __("cfg_change_password"); ?></button>
        </form>
      </div>
    </div>

  </div>
</main>

<?php include __DIR__ . '/includes/footer_nxdn.php'; ?>
</body>
</html>
