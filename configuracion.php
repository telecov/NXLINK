<?php
session_start();

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
   RED / INTERFACES
   ============================= */
$ETH_IF  = 'enp4s0';   // Ajusta si tu NXDN usa otra (ej: eth0)
$WIFI_IF = 'wlan0';    // Reservado

/* =============================
   CSRF
   ============================= */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
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

/* Validación básica de IP/CIDR, gateway y DNS */
function is_valid_cidr(string $cidr): bool {
    // Ej: 192.168.1.50/24
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
    // Ej: 8.8.8.8,1.1.1.1
    $dns = trim($dns);
    if ($dns === '') return true;
    $parts = array_map('trim', explode(',', $dns));
    foreach ($parts as $p) {
        if ($p === '' || !is_valid_ip($p)) return false;
    }
    return true;
}


/* =============================
   RUTAS
   ============================= */
$iniFile      = '/etc/NXDNReflector/NXDNReflector.ini';
$securityFile = __DIR__ . '/includes/security.json';

/* =============================
   SEGURIDAD
   ============================= */
$security = file_exists($securityFile)
    ? json_decode(file_get_contents($securityFile), true)
    : [];

/* =============================
   LOGIN
   ============================= */
if (!isset($_SESSION['nxdn_auth'])) {

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (!empty($security['password']) &&
            password_verify($_POST['password'], $security['password'])) {

            $_SESSION['nxdn_auth'] = true;
            header("Location: configuracion.php");
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
        <title>Acceso Configuración NXDN</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
	<link rel="stylesheet" href="css/styles.css">
    </head>
    <body class="login-page">
  <div class="login-wrap">
    <div class="card-custom login-card">

        <div class="title-module text-center">
    <i class="bi bi-shield-lock"></i> Configuración NXDN
</div>

        <div class="divider-soft"></div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger text-center"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="post">
            <label class="small-label">Clave de administración</label>
            <input type="password" name="password" class="form-control mb-3" required>
            <button class="btn btn-primary w-100">Acceder</button>
        </form>

<a href="index.php" class="btn btn-outline-secondary w-100 mt-2">
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
   LEER INI COMO TEXTO
   ============================= */
$iniRaw = file_get_contents($iniFile);

/* Extraer valores actuales */
preg_match('/TG\s*=\s*(\d+)/', $iniRaw, $m);        $tg = $m[1] ?? '';
preg_match('/Daemon\s*=\s*(\d+)/', $iniRaw, $m);    $daemon = $m[1] ?? '';
preg_match('/FilePath\s*=\s*(.+)/', $iniRaw, $m);   $filePath = trim($m[1] ?? '');
preg_match('/FileRoot\s*=\s*(.+)/', $iniRaw, $m);   $fileRoot = trim($m[1] ?? '');
preg_match('/Port\s*=\s*(\d+)/', $iniRaw, $m);      $port = $m[1] ?? '';

/* =============================
   GUARDAR CAMBIOS
   ============================= */
if (isset($_POST['save_ini'])) {

    // Backup
    copy($iniFile, $iniFile . '.bak');

    $iniRaw = preg_replace('/TG\s*=\s*\d+/',        'TG='.(int)$_POST['tg'], $iniRaw);
    $iniRaw = preg_replace('/Daemon\s*=\s*\d+/',    'Daemon='.(int)$_POST['daemon'], $iniRaw);
    $iniRaw = preg_replace('/FilePath\s*=.*/',      'FilePath='.trim($_POST['file_path']), $iniRaw);
    $iniRaw = preg_replace('/FileRoot\s*=.*/',      'FileRoot='.trim($_POST['file_root']), $iniRaw);
    $iniRaw = preg_replace('/Port\s*=\s*\d+/',      'Port='.(int)$_POST['port'], $iniRaw);

    file_put_contents($iniFile, $iniRaw);
    $msg = "Configuración NXDN guardada correctamente (backup creado).";
}

/* =============================
   SERVICIO
   ============================= */
if (isset($_POST['service_action'])) {
    $allowed = ['start', 'stop', 'restart'];
    $act = $_POST['service_action'];

    if (in_array($act, $allowed, true)) {
        shell_exec("sudo systemctl {$act} nxdnreflector 2>&1");
    }
}


if (isset($_POST['reboot'])) {
    shell_exec("sudo reboot");
}

/* =============================
   CAMBIAR IP ETHERNET (nmcli)
   ============================= */
if (isset($_POST['action']) && $_POST['action'] === 'net_eth') {

    // CSRF
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $msg = "Token inválido. Refresca la página e inténtalo nuevamente.";
    } else {

        $ip  = trim($_POST['new_ip_eth'] ?? '');
        $gw  = trim($_POST['new_gw_eth'] ?? '');
        $dns = trim($_POST['dns_eth']    ?? '');

        // Validaciones
        $errors = [];

        if ($ip !== '' && !is_valid_cidr($ip)) $errors[] = "IP inválida. Usa formato 192.168.1.50/24";
        if ($gw !== '' && !is_valid_ip($gw))   $errors[] = "Gateway inválido.";
        if (!is_valid_dns_list($dns))          $errors[] = "DNS inválido. Usa formato 8.8.8.8,1.1.1.1";

        if (!$errors && $ip === '' && $gw === '' && $dns === '') {
            $errors[] = "No ingresaste ningún cambio para Ethernet.";
        }

        if ($errors) {
            $msg = implode("<br>", array_map('htmlspecialchars', $errors));
        } else {

            $conn = nm_get_connection_for_device($ETH_IF) ?: 'Wired connection 1';
            $conn_esc = escapeshellarg($conn);

            $out = "";

            // Si cambias IP, forzamos modo manual
            if ($ip !== '') {
                $out .= @shell_exec("sudo nmcli con mod $conn_esc ipv4.addresses " . escapeshellarg($ip) . " 2>&1");
                $out .= @shell_exec("sudo nmcli con mod $conn_esc ipv4.method manual 2>&1");
            }

            if ($gw !== '') {
                $out .= @shell_exec("sudo nmcli con mod $conn_esc ipv4.gateway " . escapeshellarg($gw) . " 2>&1");
            }

            if ($dns !== '') {
                $out .= @shell_exec("sudo nmcli con mod $conn_esc ipv4.dns " . escapeshellarg($dns) . " 2>&1");
            }

            // Aplicar
            $out .= @shell_exec("sudo nmcli con up $conn_esc 2>&1");

            $msg = "✅ Configuración Ethernet actualizada.<br><pre>" . htmlspecialchars($out) . "</pre>";
        }
    }
}


/* =============================
   CAMBIO DE CLAVE
   ============================= */
if (isset($_POST['new_password'])) {
    $security['password'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    file_put_contents($securityFile, json_encode($security, JSON_PRETTY_PRINT));
    session_destroy();
    header("Location: configuracion.php");
    exit;
}

/* =============================
   INFO SISTEMA
   ============================= */
$serviceStatus = trim(shell_exec("systemctl is-active nxdnreflector"));
$ipServidor    = trim(shell_exec("hostname -I"));

// IP/GW Ethernet vía nmcli (más exacto que hostname -I)
$ip_eth = trim(@shell_exec("nmcli -t -f IP4.ADDRESS device show " . escapeshellarg($ETH_IF) . " 2>/dev/null | head -n 1 | cut -d':' -f2"));
$gw_eth = trim(@shell_exec("nmcli -t -f IP4.GATEWAY device show " . escapeshellarg($ETH_IF) . " 2>/dev/null | head -n 1 | cut -d':' -f2"));

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Configuración NXDN</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="css/styles.css">
</head>
<body>

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
              <i class="bi bi-sliders"></i> CONFIGURACION NXLINK
            </div>
            <div class="mt-1" style="font-size:1rem; opacity:0.85;">
              Configuracion Reflector
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
<div class="alert alert-success text-center"><?php echo $msg; ?></div>
<?php endif; ?>

<div class="row g-4">

<div class="col-lg-6">
<div class="card-custom">
<div class="title-module">📝 Configuración NXDN</div>
<div class="divider-soft"></div>

<form method="post">
<label class="small-label">TG Principal</label>
<input type="number" name="tg" class="form-control mb-2" value="<?php echo $tg; ?>">

<label class="small-label">Daemon (0 = OFF / 1 = ON)</label>
<input type="number"
       name="daemon"
       class="form-control mb-3"
       min="0"
       max="1"
       value="<?php echo $daemon; ?>"
       required>


<label class="small-label">Ruta de logs</label>
<input type="text" name="file_path" class="form-control mb-2" value="<?php echo $filePath; ?>">

<label class="small-label">Nombre base de log</label>
<input type="text" name="file_root" class="form-control mb-3" value="<?php echo $fileRoot; ?>">

<label class="small-label">Puerto NXDN</label>
<input type="number" name="port" class="form-control mb-3" value="<?php echo $port; ?>">

<button class="btn btn-primary w-100" name="save_ini">Guardar configuración</button>
</form>
</div>
</div>

<div class="col-lg-6">
<div class="card-custom">
<div class="title-module">🔄 Servicio</div>
<div class="divider-soft"></div>

<p class="small-label">Estado:
<strong><?php echo strtoupper($serviceStatus); ?></strong></p>

<p class="small-label">IP del servidor:<br><?php echo $ipServidor; ?></p>

<form method="post" class="d-flex gap-2 mb-2">
<button class="btn btn-success w-100" name="service_action" value="start">Start</button>
<button class="btn btn-warning w-100" name="service_action" value="restart">Restart</button>
<button class="btn btn-danger w-100" name="service_action" value="stop">Stop</button>
</form>

<form method="post">
<button class="btn btn-outline-danger w-100"
onclick="return confirm('⚠️ Reiniciar el servidor completo?')"
name="reboot">Reiniciar servidor</button>
</form>
</div>
</div>

<div class="col-lg-12">
  <div class="card-custom">
    <div class="title-module">🌐 IP Ethernet (<?php echo htmlspecialchars($ETH_IF); ?>)</div>
    <div class="divider-soft"></div>

    <p class="small-label">IP actual:</p>
    <div class="form-control mb-3" style="background:#1c1f2a; color:#fff;">
      <?php echo $ip_eth ? htmlspecialchars($ip_eth) : 'Sin IP'; ?>
    </div>

    <p class="small-label">Gateway actual:</p>
    <div class="form-control mb-3" style="background:#1c1f2a; color:#fff;">
      <?php echo $gw_eth ? htmlspecialchars($gw_eth) : 'Sin gateway'; ?>
    </div>

    <form method="post" autocomplete="off"
          onsubmit="return confirm('⚠️ Al cambiar IP/Gateway podrías perder conexión remota. ¿Continuar?');">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
      <input type="hidden" name="action" value="net_eth">

      <label class="small-label">Nueva IP (ej: 192.168.1.50/24)</label>
      <input type="text" name="new_ip_eth" class="form-control mb-2" placeholder="Dejar vacío si no quieres cambiarla">

      <label class="small-label">Nuevo gateway (ej: 192.168.1.1)</label>
      <input type="text" name="new_gw_eth" class="form-control mb-2" placeholder="Dejar vacío si no quieres cambiarlo">

      <label class="small-label">DNS (opcional, ej: 8.8.8.8,1.1.1.1)</label>
      <input type="text" name="dns_eth" class="form-control mb-3" placeholder="Dejar vacío si no quieres cambiarlo">

      <button class="btn btn-warning w-100">Aplicar cambios</button>
    </form>

    <p class="small-label mt-3" style="opacity:.8;">
      Recomendación: IP fija y documentada. Si administras remoto, haz el cambio estando en sitio o con consola out-of-band.
    </p>
  </div>
</div>


<div class="col-lg-12">
<div class="card-custom">
<div class="title-module">🔐 Seguridad</div>
<div class="divider-soft"></div>

<form method="post">
<label class="small-label">Nueva clave</label>
<input type="password" name="new_password" class="form-control mb-2">
<button class="btn btn-warning w-100">Cambiar clave</button>
</form>
</div>
</div>

</div>
</main>

<?php include __DIR__ . '/includes/footer_nxdn.php'; ?>

</body>
</html>
