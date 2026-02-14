<?php
date_default_timezone_set('America/Santiago');

/* ===========================
   CONFIG (DVREF + VISUAL)
   =========================== */
$configFile = __DIR__ . "/includes/config.json";
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
if (!is_array($config)) $config = [];

/* ===========================
   TELEGRAM CONFIG
   =========================== */
require_once __DIR__ . "/includes/telegram.php";

$tgCfg = tg_load_config();
$tgInviteLink = $tgCfg["invite_link"] ?? "";


$titulo      = !empty($config["titulo"]) ? (string)$config["titulo"] : "NXDN LINK";
$subtitulo   = !empty($config["subtitulo"]) ? (string)$config["subtitulo"] : "Reflector NXDN";
$frase       = !empty($config["frase"]) ? (string)$config["frase"] : "La frecuencia que nos mantiene conectados en el mundo digital";
$logo        = !empty($config["logo"]) ? (string)$config["logo"] : "img/nxlink_logo.png";

$tgPrincipal = !empty($config["tg_principal"]) ? (string)$config["tg_principal"] : "30444";


$dvrefApiUrl = "https://dvref.com/api/v2/nxdn/reflectors/";
$dvrefToken  = !empty($config["dvref_token"]) ? trim((string)$config["dvref_token"]) : "";
$dvrefHost   = !empty($config["dvref_host"]) ? strtolower(trim((string)$config["dvref_host"])) : "";
$dvrefPort   = !empty($config["dvref_port"]) ? (int)$config["dvref_port"] : 0;

/* ===========================
   DETECTAR LOG MÁS RECIENTE
   =========================== */
$files = glob("/var/log/nxdnreflector/NXDNReflector-*.log");
if ($files) { rsort($files); $logFile = $files[0]; } else { $logFile = null; }

/* =============================================================
   MODO AJAX: SOLO ESTADO DE TX PARA VU-METER
   ============================================================ */
if (isset($_GET["ajax"]) && $_GET["ajax"] === "tx") {

    $activeTx = null;
    $lines = ($logFile && file_exists($logFile)) ? @file($logFile) : false;

    if ($lines !== false) {
        foreach (array_reverse($lines) as $line) {

            if (strpos($line, "Received end of transmission") !== false) {
                break;
            }

            if (preg_match('/Transmission from\s+(\d+)\s+at\s+([A-Z0-9]+)\s+to\s+TG\s+(\d+)/', $line, $m)) {
                $activeTx = [
                    "radioid"  => $m[1],
                    "callsign" => $m[2],
                    "tg"       => $m[3],
                    "inicio"   => null
                ];
            }

            if (preg_match('/^[MI]:\s+(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $line, $ts)) {
                if ($activeTx && !$activeTx["inicio"]) {
                    $activeTx["inicio"] = $ts[1];
                }
            }

            if ($activeTx && $activeTx["inicio"]) break;
        }
    }

    if ($activeTx && $activeTx["inicio"]) {
        $duration = time() - strtotime($activeTx["inicio"]);
        $activeTx["duracion"] = gmdate("H:i:s", $duration);
        $activeTx["active"]   = true;
    } else {
        $activeTx = ["active" => false];
    }

    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($activeTx);
    exit;
}

/* =============================================================
   PARSEO PRINCIPAL (LOGS)
   ============================================================ */
$connectedStations = [];
$historyRaw        = [];
$activeTx          = null;

$lines = ($logFile && file_exists($logFile)) ? @file($logFile) : false;

/* --- PARSEO ÚLTIMO BLOQUE "Currently linked repeaters" --- */
if ($lines !== false) {
    $latestBlock  = [];
    $readingBlock = false;

    $lastLines = array_slice($lines, -300);

    foreach ($lastLines as $line) {

        $timestamp = null;
        if (preg_match('/^[MI]:\s+(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $line, $tm)) {
            $timestamp = $tm[1];
        }

        if (strpos($line, "Currently linked repeaters:") !== false) {
            $readingBlock = true;
            $latestBlock  = [];
            continue;
        }

        if ($readingBlock) {
            if (preg_match('/\s*([A-Z0-9]+)\s*:\s*([0-9\.]+|\[[0-9a-fA-F\:]+\])\:(\d+)\s+(\d+)\/120/', $line, $m)) {
                $callsign = trim($m[1]);
                $ip       = trim($m[2]);
                $port     = trim($m[3]);

                $latestBlock[] = [
                    "callsign" => $callsign,
                    "ip"       => "$ip:$port",
                    "desde"    => $timestamp ?: ''
                ];
            } else {
                $readingBlock = false;
            }
        }
    }

    $connectedStations = $latestBlock;

    /* --- HISTORIAL TX --- */
    foreach ($lines as $line) {

        $timestamp = null;
        if (preg_match('/^[MI]:\s+(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $line, $tm)) {
            $timestamp = $tm[1];
        }

        if (preg_match('/Transmission from\s+(\d+)\s+at\s+([A-Z0-9]+)\s+to\s+TG\s+(\d+)/', $line, $m)) {
            $activeTx = [
                "radioid"  => $m[1],
                "callsign" => $m[2],
                "tg"       => $m[3],
                "inicio"   => $timestamp ?: '',
                "fin"      => '',
                "duracion" => ''
            ];
        }

        if (strpos($line, "Received end of transmission") !== false && $activeTx !== null) {
            $activeTx["fin"] = $timestamp ?: '';

            if (!empty($activeTx["inicio"]) && !empty($activeTx["fin"])) {
                $t1 = strtotime($activeTx["inicio"]);
                $t2 = strtotime($activeTx["fin"]);
                if ($t1 !== false && $t2 !== false) {
                    $activeTx["duracion"] = gmdate("H:i:s", abs($t2 - $t1));
                }
            }

            $historyRaw[] = $activeTx;
            $activeTx = null;
        }
    }
}

/* --- HISTORIAL ORDENADO --- */
$history = array_reverse($historyRaw);
$history = array_slice($history, 0, 50);

/* --- ESTADÍSTICAS --- */
$totalTx   = count($historyRaw);
$users     = [];
$totalTime = 0;
$firstTx   = null;
$lastTx    = null;

foreach ($historyRaw as $h) {
    $users[$h["callsign"]] = true;

    if (!empty($h["duracion"])) {
        $parts = explode(":", $h["duracion"]);
        if (count($parts) === 3) {
            [$h1, $m1, $s1] = $parts;
            $totalTime += ((int)$h1 * 3600) + ((int)$m1 * 60) + (int)$s1;
        }
    }

    if (!empty($h["inicio"]) && ($firstTx === null || strtotime($h["inicio"]) < strtotime($firstTx))) {
        $firstTx = $h["inicio"];
    }
    if (!empty($h["fin"]) && ($lastTx === null || strtotime($h["fin"]) > strtotime($lastTx))) {
        $lastTx = $h["fin"];
    }
}

$usersCount   = count($users);
$totalTimeFmt = $totalTime > 0 ? gmdate("H:i:s", $totalTime) : "00:00:00";

if     ($totalTx >= 20) $nivel = "Alto";
elseif ($totalTx >= 10) $nivel = "Moderado";
elseif ($totalTx >= 1)  $nivel = "Bajo";
else                    $nivel = "Sin actividad";

$reflectorStatus = ($lines !== false && count($lines) > 0) ? "ONLINE" : "OFFLINE";

/* =============================================================
   DVREF STATUS CHECK (CACHE 5 MIN)
   ============================================================ */
function dvref_status_check(array $cfg, $cache_file, $cache_ttl = 300) {

    $api = "https://dvref.com/api/v2/nxdn/reflectors/";
    $tok  = trim((string)($cfg["dvref_token"] ?? ""));
    $host = strtolower(trim((string)($cfg["dvref_host"] ?? "")));
    $port = (int)($cfg["dvref_port"] ?? 0);
    $tg   = trim((string)($cfg["tg_principal"] ?? ""));

    // Validaciones mínimas
    if ($api === "" || stripos($api, "http") !== 0) {
        return ['status' => 'CONFIG DVREF INVÁLIDA', 'detail' => 'dvref_api_url debe ser URL (https://...)', 'last_verified_at' => null];
    }
    if ($host === "" || $port <= 0 || $tg === "") {
        return ['status' => 'CONFIG DVREF INCOMPLETA', 'detail' => 'Falta dvref_host / dvref_port / tg_principal', 'last_verified_at' => null];
    }

    // Cache
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_ttl)) {
        $cached = json_decode(file_get_contents($cache_file), true);
        if (is_array($cached)) return $cached;
    }

    // cURL
    $ch = curl_init($api);
    $headers = [
        "Accept: application/json",
        "User-Agent: NXLINK-Dashboard/1.0 (CA2RDP)"
    ];
    // Token opcional (si tu endpoint lo exige, aquí se manda)
    if ($tok !== "") {
        $headers[] = "Authorization: Token {$tok}";
    }

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

    if ($response === false) {
        return ['status' => 'ERROR DVREF', 'detail' => "cURL: {$curlErr}", 'last_verified_at' => null];
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        return ['status' => 'ERROR DVREF', 'detail' => "HTTP {$httpCode}", 'last_verified_at' => null];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return ['status' => 'ERROR DVREF', 'detail' => 'Respuesta no es JSON', 'last_verified_at' => null];
    }

    // DVRef suele devolver lista directa "reflectors": [...] (según docs de ejemplo)
    $reflectors = null;
    if (isset($data["reflectors"]) && is_array($data["reflectors"])) {
        $reflectors = $data["reflectors"];
    } elseif (isset($data["data"]["reflectors"]) && is_array($data["data"]["reflectors"])) {
        // por si viene envuelto (como tu ejemplo P25 viejo)
        $reflectors = $data["data"]["reflectors"];
    } elseif (array_keys($data) === range(0, count($data) - 1)) {
        // lista directa sin wrapper
        $reflectors = $data;
    }

    if (!is_array($reflectors)) {
        return ['status' => 'ERROR DVREF', 'detail' => 'No se encontró lista de reflectores', 'last_verified_at' => null];
    }

    $status = "OFFLINE DVREF";
    $lastVerified = null;

    foreach ($reflectors as $ref) {
        if (!is_array($ref)) continue;

        $dns  = strtolower(trim((string)($ref["dns"] ?? $ref["host"] ?? "")));
        $rport = (int)($ref["port"] ?? 0);

        // En DVRef, "designator" suele ser el identificador (en P25 lo usabas como TG)
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

    @file_put_contents($cache_file, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    return $result;
}

$cacheDir = __DIR__ . "/data";
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);

$dvrefStatus = dvref_status_check($config, $cacheDir . "/dvref_status.json", 300);
$tgOnline = ($dvrefStatus["status"] === "ONLINE DVREF") ? true : false;
$tgApiError = $dvrefStatus["detail"] ?? null;

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($titulo); ?> – Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="css/styles.css">
<link rel="icon" type="image/png" href="img/favicon.png">
<link rel="apple-touch-icon" href="img/favicon.png">
</head>
<body>

<header class="header-banner">
  <div class="container-fluid">
    <div class="row align-items-center g-3">
      <div class="col-md-8">
        <div class="header-title text-light d-flex align-items-center gap-3">
          <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo NXDN"
               style="height:110px; width:auto; border-radius:12px;">

          <div>
            <div class="text-uppercase fw-bold" style="font-size:2.2rem; letter-spacing:0.05em;">
              <i class="bi bi-broadcast"></i> <?php echo htmlspecialchars($titulo); ?>
            </div>

            <div class="mt-1" style="font-size:1rem; opacity:0.85;">
              <?php echo htmlspecialchars($subtitulo); ?>
            </div>

            <div class="mt-1" style="font-size:0.90rem; opacity:0.9; font-style:italic;">
              “<?php echo htmlspecialchars($frase); ?>”
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-4 text-md-end">
        <div class="small-label mb-1">Estado del sistema</div>
        <span class="status-dot status-<?php echo strtolower($reflectorStatus) === 'online' ? 'online' : 'offline'; ?>"></span>
        <span class="fw-semibold"><?php echo $reflectorStatus; ?></span>
        <div class="small-label mt-1">
          Última actualización: <?php echo date("d-m-Y H:i:s"); ?>
        </div>
      </div>
    </div>
  </div>
</header>

<main class="container-fluid py-3">
<?php include __DIR__ . '/includes/nav_nxdn.php'; ?>

<div class="row g-3">

  <div class="col-lg-4">
    <div class="card-custom">
      <div class="title-module">
        <i class="bi bi-speedometer2"></i> Estado General
      </div>
      <div class="divider-soft"></div>

      <p class="mb-1 small-label">TalkGroup Principal</p>
      <p class="mb-2">
        <span class="badge bg-primary">TG <?php echo htmlspecialchars($tgPrincipal); ?></span>
      </p>

      <p class="mb-1 small-label">Estado DVRef</p>
      <p class="mb-2">
        <?php if ($dvrefStatus["status"] === "ONLINE DVREF"): ?>
          <span class="badge bg-success">ONLINE</span>
          <?php if (!empty($dvrefStatus["last_verified_at"])): ?>
            <div class="small-label mt-1" style="opacity:.85;">
              last_verified_at: <?php echo htmlspecialchars($dvrefStatus["last_verified_at"]); ?>
            </div>
          <?php endif; ?>
        <?php elseif (strpos($dvrefStatus["status"], "CONFIG") === 0): ?>
          <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($dvrefStatus["status"]); ?></span>
          <div class="small-label mt-1" style="opacity:.85;"><?php echo htmlspecialchars($dvrefStatus["detail"] ?? ""); ?></div>
        <?php else: ?>
          <span class="badge bg-danger"><?php echo htmlspecialchars($dvrefStatus["status"]); ?></span>
          <?php if (!empty($dvrefStatus["detail"])): ?>
            <div class="small-label mt-1" style="opacity:.85;"><?php echo htmlspecialchars($dvrefStatus["detail"]); ?></div>
          <?php endif; ?>
        <?php endif; ?>
      </p>

      <div class="divider-soft mt-3"></div>
      <div class="title-module mb-2">
        <i class="bi bi-hdd-network"></i> Estado del Servidor
      </div>

      <?php
      $cpuLoad = sys_getloadavg();
      $load1 = round($cpuLoad[0], 2);
      $load5 = round($cpuLoad[1], 2);
      $load15 = round($cpuLoad[2], 2);

      $meminfo = @file("/proc/meminfo") ?: [];
      $memTotal = isset($meminfo[0]) ? (int)filter_var($meminfo[0], FILTER_SANITIZE_NUMBER_INT) : 0;
      $memAvailable = isset($meminfo[2]) ? (int)filter_var($meminfo[2], FILTER_SANITIZE_NUMBER_INT) : 0;
      $memUsed = max(0, $memTotal - $memAvailable);

      $memTotalGB = $memTotal > 0 ? round($memTotal / 1024 / 1024, 2) : 0;
      $memUsedGB  = $memTotal > 0 ? round($memUsed / 1024 / 1024, 2) : 0;
      $ramPercent = $memTotal > 0 ? round(($memUsed / $memTotal) * 100) : 0;

      $diskTotal = @disk_total_space("/") ?: 0;
      $diskFree  = @disk_free_space("/") ?: 0;
      $diskUsed  = max(0, $diskTotal - $diskFree);
      $diskPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100) : 0;
      $diskTotalGB = $diskTotal > 0 ? round($diskTotal / 1024 / 1024 / 1024, 2) : 0;
      $diskUsedGB  = $diskTotal > 0 ? round($diskUsed  / 1024 / 1024 / 1024, 2) : 0;

      $nCpus = (int)(@shell_exec("nproc") ?: 1);
      $nCpus = max(1, $nCpus);
      $cpuPercent = round(($load1 / $nCpus) * 100);
      $cpuPercent = max(0, min(100, $cpuPercent));

      $cpuColor = "bg-success";
      if ($cpuPercent >= 70) $cpuColor = "bg-danger";
      elseif ($cpuPercent >= 40) $cpuColor = "bg-warning";
      ?>

      <div class="mb-3">
        <p class="small-label mb-1">CPU (Load 1m)</p>
        <div class="progress" style="height: 12px;">
          <div class="progress-bar <?php echo $cpuColor; ?>" role="progressbar"
               style="width: <?php echo $cpuPercent; ?>%;"
               aria-valuenow="<?php echo $cpuPercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <p class="mb-0 mt-1"><?php echo $cpuPercent; ?>% <span class="small-label">(<?php echo $nCpus; ?> núcleos)</span></p>
        <p class="mb-0 mt-1 small-label">
          Load Avg → 1m: <strong><?php echo $load1; ?></strong> / 5m: <strong><?php echo $load5; ?></strong> / 15m: <strong><?php echo $load15; ?></strong>
        </p>
      </div>

      <div class="mb-3">
        <p class="small-label mb-1">Memoria RAM</p>
        <div class="progress" style="height: 12px;">
          <div class="progress-bar bg-info" role="progressbar"
               style="width: <?php echo $ramPercent; ?>%;"
               aria-valuenow="<?php echo $ramPercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <p class="mb-0 mt-1"><?php echo $memUsedGB; ?> GB / <?php echo $memTotalGB; ?> GB (<?php echo $ramPercent; ?>%)</p>
      </div>

      <div class="mb-2">
        <p class="small-label mb-1">Disco ( / )</p>
        <div class="progress" style="height: 12px;">
          <div class="progress-bar bg-warning" role="progressbar"
               style="width: <?php echo $diskPercent; ?>%;"
               aria-valuenow="<?php echo $diskPercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <p class="mb-0 mt-1"><?php echo $diskUsedGB; ?> GB / <?php echo $diskTotalGB; ?> GB (<?php echo $diskPercent; ?>%)</p>
      </div>

    </div>
  </div>

  <div class="col-lg-8">
    <div class="card-custom">
      <div class="title-module">
        <i class="bi bi-people"></i> Estaciones conectadas
      </div>
      <div class="divider-soft"></div>

      <div class="table-responsive">
        <table class="table table-dark table-striped table-hover table-sm table-dark-custom align-middle mb-0">
          <thead>
            <tr>
              <th>Indicativo</th>
              <th>IP</th>
              <th>Conectado desde</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!empty($connectedStations)): ?>
            <?php foreach ($connectedStations as $cs): ?>
              <tr>
                <td>
                  <?php if (!empty($cs["callsign"])): ?>
                    <a href="https://www.qrz.com/db/<?php echo urlencode($cs['callsign']); ?>"
                       target="_blank"
                       class="text-info text-decoration-none fw-semibold">
                      <?php echo htmlspecialchars($cs["callsign"]); ?>
                    </a>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($cs["ip"]); ?></td>
                <td><?php echo htmlspecialchars($cs["desde"]); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="3" class="text-center small-label">No hay estaciones conectadas en este momento.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>

</div>

<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="card-custom">
      <div class="title-module">
        <i class="bi bi-graph-up-arrow"></i> Actividad general del reflector
      </div>
      <div class="divider-soft"></div>

      <div class="mb-3">
        <div class="fw-semibold mb-1">
          <i class="bi bi-broadcast-pin"></i> Transmisión en vivo
        </div>

        <div class="vu-container">
          <div id="vumeter" class="vu-bar"></div>
        </div>

        <div id="tx-info" class="mt-2 small-label">Reflector en escucha.</div>
      </div>

      <div class="divider-soft"></div>

      <div class="row text-center mt-2">
        <div class="col-6 col-md">
          <div class="fw-bold fs-5"><?php echo $totalTx; ?></div>
          <div class="small-label">TX hoy</div>
        </div>
        <div class="col-6 col-md">
          <div class="fw-bold fs-5"><?php echo $usersCount; ?></div>
          <div class="small-label">Usuarios</div>
        </div>
        <div class="col-6 col-md">
          <div class="fw-bold fs-5"><?php echo $totalTimeFmt; ?></div>
          <div class="small-label">Tiempo al aire</div>
        </div>
        <div class="col-6 col-md">
          <span class="badge bg-info text-dark mt-2"><?php echo $nivel; ?></span>
          <div class="small-label">Nivel</div>
        </div>
      </div>

      <div class="divider-soft mt-3"></div>
      <p class="small-label mb-0 text-center">
        Primera TX: <strong><?php echo $firstTx ?: "—"; ?></strong> ·
        Última TX: <strong><?php echo $lastTx ?: "—"; ?></strong>
      </p>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="card-custom">
      <div class="title-module">
        <i class="bi bi-clock-history"></i> Historial de tráfico NXDN
      </div>
      <div class="divider-soft"></div>

      <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
        <table class="table table-dark table-striped table-hover table-sm table-dark-custom align-middle mb-0">
          <thead>
            <tr>
              <th>Inicio</th><th>Fin</th><th>Duración</th><th>RadioID</th><th>Indicativo</th><th>TG</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!empty($history)): ?>
            <?php foreach ($history as $tx): ?>
              <tr>
                <td><?php echo htmlspecialchars($tx["inicio"]); ?></td>
                <td><?php echo htmlspecialchars($tx["fin"]); ?></td>
                <td><?php echo htmlspecialchars($tx["duracion"]); ?></td>
                <td><?php echo htmlspecialchars($tx["radioid"]); ?></td>
                <td>
                  <?php if (!empty($tx["callsign"])): ?>
                    <a href="https://www.qrz.com/db/<?php echo urlencode($tx['callsign']); ?>"
                       target="_blank"
                       class="text-info text-decoration-none fw-semibold">
                      <?php echo htmlspecialchars($tx["callsign"]); ?>
                    </a>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($tx["tg"]); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="6" class="text-center small-label">Sin tráfico registrado en el log seleccionado.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/vumeter.js"></script>

<?php include __DIR__ . '/includes/footer_nxdn.php'; ?>
</body>
</html>
