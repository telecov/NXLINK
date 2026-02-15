
<?php
date_default_timezone_set('America/Santiago');

/* ===========================
   IDIOMA (i18n)
   =========================== */
require_once __DIR__ . "/includes/lang.php";
$langCode = $_SESSION['lang'] ?? 'es';

/* =============================
   CARGA CONFIG (solo lectura)
   ============================= */
$configFile = __DIR__ . "/includes/config.json";
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
if (!is_array($config)) $config = [];

$config += [
  "titulo"    => "NXDN LINK",
  "subtitulo" => "Reflector NXDN",
  "frase"     => "La frecuencia que nos mantiene conectados",
  "tg_principal" => "30444",
];

$aboutLogo = "img/logo.png";

if (!file_exists(__DIR__ . "/" . $aboutLogo)) {
  $aboutLogo = "img/logo.png";
}

/* Logo fallback */
$logoPath = trim((string)($config['logo'] ?? ''));
if ($logoPath === '' || !file_exists(__DIR__ . '/' . $logoPath)) {
  $logoPath = 'img/nxlink_logo.png';
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($langCode); ?>">
<head>
  <meta charset="UTF-8">
  <title><?php echo __("about_title"); ?> – NXLINK</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logoPath); ?>">
</head>

<body>

<header class="header-banner">
  <div class="container-fluid">
    <div class="row align-items-center g-3">
      <div class="col-md-8">
        <div class="header-title text-light d-flex align-items-center gap-3">
          <img src="<?php echo htmlspecialchars($aboutLogo); ?>" style="height:90px; border-radius:12px;">
          <div>
            <div class="text-uppercase fw-bold" style="font-size:1.9rem; letter-spacing:0.05em;">
              <i class="bi bi-info-circle"></i> <?php echo __("about_header"); ?>
            </div>
            <div class="mt-1" style="font-size:1rem; opacity:0.85;">
              <?php echo htmlspecialchars($config['frase']); ?>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="d-flex flex-column align-items-md-end align-items-start gap-2">
          <div class="d-flex gap-2">
            <a href="?lang=es" class="btn btn-outline-light btn-sm <?php echo ($langCode==='es')?'active':''; ?>">🇪🇸 ES</a>
            <a href="?lang=en" class="btn btn-outline-light btn-sm <?php echo ($langCode==='en')?'active':''; ?>">🇺🇸 EN</a>
          </div>
          <div class="small-label text-md-end">
            <?php echo __("last_update"); ?>: <?php echo date("d-m-Y H:i:s"); ?>
          </div>
        </div>
      </div>

    </div>
  </div>
</header>

<main class="container-fluid py-3">
  <?php include __DIR__ . '/includes/nav_nxdn.php'; ?>

  <div class="row g-4">

    <!-- NXLink: propósito -->
    <div class="col-lg-12">
      <div class="card-custom">
        <div class="title-module"><i class="bi bi-broadcast"></i> <?php echo __("about_section_nxlink_title"); ?></div>
        <div class="divider-soft"></div>

        <p class="mb-2">
          <?php echo __("about_nxlink_p1"); ?>
        </p>

        <ul class="mb-2">
          <li><?php echo __("about_nxlink_li1"); ?></li>
          <li><?php echo __("about_nxlink_li2"); ?></li>
          <li><?php echo __("about_nxlink_li3"); ?></li>
          <li><?php echo __("about_nxlink_li4"); ?></li>
        </ul>

        <div class="p-3 border rounded-3" style="background: rgba(255,255,255,0.02);">
          <p class="mb-2">
            <?php echo __("about_creator_p1"); ?>
          </p>
          <p class="mb-0">
            <?php echo __("about_inspired_p1"); ?>
          </p>

          <div class="mt-2 small">
            <span class="badge bg-primary-subtle text-primary border">NXDN</span>
            <span class="badge bg-primary-subtle text-primary border">TG <?php echo htmlspecialchars($config['tg_principal']); ?></span>
            <span class="badge bg-primary-subtle text-primary border">ZONA DMR CL</span>
            <span class="ms-1" style="opacity:.75;">73</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ¿Qué es NXDN? -->
    <div class="col-lg-12">
      <div class="card-custom">
        <div class="title-module"><i class="bi bi-question-circle"></i> <?php echo __("about_section_nxdn_title"); ?></div>
        <div class="divider-soft"></div>

        <p class="mb-2">
          <?php echo __("about_nxdn_p1"); ?>
        </p>

        <ul class="mb-0">
          <li><?php echo __("about_nxdn_li1"); ?></li>
          <li><?php echo __("about_nxdn_li2"); ?></li>
          <li><?php echo __("about_nxdn_li3"); ?></li>
        </ul>
      </div>
    </div>

    <!-- Universo -->
    <div class="col-lg-12">
      <div class="card-custom">
        <div class="title-module"><i class="bi bi-diagram-3"></i> <?php echo __("about_section_universe_title"); ?></div>
        <div class="divider-soft"></div>

        <p class="mb-2"><?php echo __("about_universe_p1"); ?></p>

        <div class="row g-3">
          <div class="col-md-4">
            <div class="p-3 border rounded-3" style="background: rgba(255,255,255,0.02);">
              <div class="fw-bold"><i class="bi bi-router"></i> Lynk25</div>
              <div style="opacity:.85;"><?php echo __("about_universe_lynk25"); ?></div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="p-3 border rounded-3" style="background: rgba(255,255,255,0.02);">
              <div class="fw-bold"><i class="bi bi-broadcast-pin"></i> LuxLink Fusion</div>
              <div style="opacity:.85;"><?php echo __("about_universe_luxlink"); ?></div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="p-3 border rounded-3" style="background: rgba(255,255,255,0.02);">
              <div class="fw-bold"><i class="bi bi-wifi"></i> NXLink</div>
              <div style="opacity:.85;"><?php echo __("about_universe_nxlink"); ?></div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="p-3 border rounded-3" style="background: rgba(255,255,255,0.02);">
              <div class="fw-bold"><i class="bi bi-radioactive"></i> AuroxLink</div>
              <div style="opacity:.85;"><?php echo __("about_universe_aurox"); ?></div>
            </div>
          </div>
        </div>

        <p class="mt-3 mb-0" style="opacity:.85;">
          <?php echo __("about_universe_philosophy"); ?>
        </p>
      </div>
    </div>

    <!-- Recursos -->
    <div class="col-lg-12">
      <div class="card-custom">
        <div class="title-module"><i class="bi bi-globe2"></i> <?php echo __("about_section_resources_title"); ?></div>
        <div class="divider-soft"></div>

        <ul class="list-unstyled mb-0">
          <li>🔗 <a class="link-light text-decoration-none" href="https://github.com/g4klx/NXDNReflector" target="_blank" rel="noopener">NXDNReflector (G4KLX)</a></li>
          <li>🔗 <a class="link-light text-decoration-none" href="https://github.com/g4klx/NXDNClients" target="_blank" rel="noopener">NXDN Clients (G4KLX)</a></li>
          <li>🔗 <a class="link-light text-decoration-none" href="https://www.radioid.net/" target="_blank" rel="noopener">RadioID (IDs & Callsigns)</a></li>
          <li>🔗 <a class="link-light text-decoration-none" href="https://zonadmr.cl/" target="_blank" rel="noopener">ZONA DMR CL · Web & Blog</a></li>
        </ul>
      </div>
    </div>

    <!-- Agradecimientos -->
    <div class="col-lg-7">
      <div class="card-custom h-100">
        <div class="title-module"><i class="bi bi-award"></i> <?php echo __("about_section_thanks_title"); ?></div>
        <div class="divider-soft"></div>

        <p class="mb-2"><?php echo __("about_thanks_p1"); ?></p>
        <p class="mb-3"><?php echo __("about_thanks_p2"); ?></p>

        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-outline-light" href="https://github.com/g4klx/NXDNReflector" target="_blank" rel="noopener">
            <i class="bi bi-download me-1"></i> NXDNReflector
          </a>
          <a class="btn btn-outline-light" href="https://github.com/g4klx/NXDNClients" target="_blank" rel="noopener">
            <i class="bi bi-tools me-1"></i> NXDN Clients
          </a>
          <a class="btn btn-outline-light" href="https://github.com/nostar" target="_blank" rel="noopener">
            <i class="bi bi-box me-1"></i> NOSTAR
          </a>
          <a class="btn btn-outline-light" href="https://github.com/telecov" target="_blank" rel="noopener">
            <i class="bi bi-github me-1"></i> GitHub Telecov
          </a>
        </div>

        <div class="small-label mt-3" style="opacity:.8;">
          <?php echo __("about_links_note"); ?>
        </div>
      </div>
    </div>

    <!-- Apoyo -->
    <div class="col-lg-5">
      <div class="card-custom h-100">
        <div class="title-module"><i class="bi bi-heart"></i> <?php echo __("about_section_support_title"); ?></div>
        <div class="divider-soft"></div>

        <p class="mb-3"><?php echo __("about_support_p1"); ?></p>

        <div class="d-flex align-items-center gap-3 mb-4" style="font-size:1.7rem;">
          <a href="https://www.youtube.com/@Telecoviajero" target="_blank" rel="noopener" class="text-danger" data-bs-toggle="tooltip" title="YouTube">
            <i class="bi bi-youtube"></i>
          </a>
          <a href="https://www.tiktok.com/@telecoviajero" target="_blank" rel="noopener" class="text-light" data-bs-toggle="tooltip" title="TikTok">
            <i class="bi bi-tiktok"></i>
          </a>
          <a href="https://www.instagram.com/telecoviajero" target="_blank" rel="noopener" class="text-warning" data-bs-toggle="tooltip" title="Instagram">
            <i class="bi bi-instagram"></i>
          </a>
        </div>

        <div class="d-grid mt-auto">
          <form action="https://www.paypal.com/donate" method="post" target="_top">
            <input type="hidden" name="hosted_button_id" value="DGA8ADD7EA63Y" />
            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-heart-fill me-2"></i> <?php echo __("about_donate_btn"); ?>
            </button>
          </form>
        </div>

        <div class="small-label mt-3" style="opacity:.8;">
          <?php echo __("about_support_note"); ?>
        </div>
      </div>
    </div>

  </div>
</main>

<?php include __DIR__ . '/includes/footer_nxdn.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  [...tooltipTriggerList].map(el => new bootstrap.Tooltip(el));
</script>
</body>
</html>
