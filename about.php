<?php
date_default_timezone_set('America/Santiago');

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
  "logo"      => "img/nxlink_logo.png"
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>About – NXLINK</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($config['logo']); ?>">
</head>

<body>

<header class="header-banner">
  <div class="container-fluid">
    <div class="row align-items-center g-3">
      <div class="col-md-8">
        <div class="header-title text-light d-flex align-items-center gap-3">
          <img src="<?php echo htmlspecialchars($config['logo']); ?>" style="height:90px; border-radius:12px;">
          <div>
            <div class="text-uppercase fw-bold" style="font-size:1.9rem; letter-spacing:0.05em;">
              <i class="bi bi-info-circle"></i> ABOUT NXLINK
            </div>
            <div class="mt-1" style="font-size:1rem; opacity:0.85;">
              <?php echo htmlspecialchars($config['frase']); ?>
            </div>
          </div>
        </div>

      </div>
      <div class="col-md-4 text-md-end small-label">
        Última actualización: <?php echo date("d-m-Y H:i:s"); ?>
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
        <div class="title-module"><i class="bi bi-broadcast"></i> NXLink</div>
        <div class="divider-soft"></div>

        <p class="mb-2">
          <strong>NXLink Dashboard</strong> es el panel web del reflector NXDN diseñado para mostrar de forma
          <strong>clara</strong> el estado del sistema, la <strong>actividad</strong> y la operación diaria,
          para que cualquier radioaficionado pueda ver qué está pasando y sumarse al modo digital.
        </p>

        <ul class="mb-2">
          <li><strong>Claridad inmediata:</strong> estado, actividad y datos útiles en una sola vista.</li>
          <li><strong>Diseño ligero:</strong> recarga periódica y componentes Bootstrap para estabilidad.</li>
          <li><strong>Personalizable:</strong> título, subtítulo, frase y logo desde Personalización.</li>
          <li><strong>Enfoque comunidad:</strong> pensado para operación real, sin ruido visual.</li>
        </ul>

        <div class="p-3 border rounded-3" style="background: rgba(255,255,255,0.02);">
          <p class="mb-2">
            Creado por <strong>CA2RDP / TELECOVIAJERO</strong> como parte del <strong>universo de dashboards web</strong>
            para reflectores digitales (P25 · YSF · NXDN), buscando una experiencia moderna y fácil de entender.
          </p>
          <p class="mb-0">
            NXLink fue inspirado y motivado por mi amigo <strong>Jonathan CE4KRC</strong>, quien apoyó e impulsó la idea
            de darle a NXDN una interfaz web a la altura del ecosistema.
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

    <!-- ¿Qué es NXDN en radioafición? -->
    <div class="col-lg-12">
      <div class="card-custom">
        <div class="title-module"><i class="bi bi-question-circle"></i> ¿Qué es NXDN en radioafición y cómo lo usamos?</div>
        <div class="divider-soft"></div>

        <p class="mb-2">
          <strong>NXDN</strong> es un modo digital utilizado por radioaficionados para experimentar con comunicaciones
          claras y estables. En el hobby, lo usamos principalmente conectándonos a un <strong>reflector</strong>
          mediante <strong>hotspots / MMDVM</strong> o gateways, para conversar y mantener una red activa a nivel local e internacional.
        </p>

        <ul class="mb-0">
          <li><strong>Hotspots / MMDVM:</strong> enlazan tu equipo NXDN con el reflector usando Internet.</li>
          <li><strong>Salas / TG:</strong> agrupan conversaciones por comunidad (por ejemplo <code><?php echo htmlspecialchars($config['tg_principal']); ?></code>).</li>
          <li><strong>Buenas prácticas:</strong> identifica tu indicativo, deja pausas y evita solapes.</li>
        </ul>
      </div>
    </div>

    <!-- Universo dashboard -->
<div class="col-lg-12">
  <div class="card-custom">
    <div class="title-module"><i class="bi bi-diagram-3"></i> Universo Dashboard Web</div>
    <div class="divider-soft"></div>

    <p class="mb-2">
      NXLink es parte del ecosistema de dashboards web de Telecoviajero:
    </p>

    <div class="row g-3">
      <div class="col-md-4">
        <div class="p-3 border rounded-3" style="background: rgba(255,255,255,0.02);">
          <div class="fw-bold"><i class="bi bi-router"></i> Lynk25</div>
          <div style="opacity:.85;">Dashboard reflector P25</div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="p-3 border rounded-3" style="background: rgba(255,255,255,0.02);">
          <div class="fw-bold"><i class="bi bi-broadcast-pin"></i> LuxLink Fusion</div>
          <div style="opacity:.85;">Dashboard reflector YSF</div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="p-3 border rounded-3" style="background: rgba(255,255,255,0.02);">
          <div class="fw-bold"><i class="bi bi-wifi"></i> NXLink</div>
          <div style="opacity:.85;">Dashboard reflector NXDN</div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="p-3 border rounded-3" style="background: rgba(255,255,255,0.02);">
          <div class="fw-bold"><i class="bi bi-radioactive"></i> AuroxLink</div>
          <div style="opacity:.85;">Dashboard SVXLink</div>
        </div>
      </div>
    </div><!-- /row -->

    <p class="mt-3 mb-0" style="opacity:.85;">
      Filosofía común: <strong>claridad</strong>, <strong>estabilidad</strong>, <strong>estética moderna</strong> y herramientas reales para operar.
    </p>
  </div>
</div>


    <!-- Recursos -->
    <div class="col-lg-12">
      <div class="card-custom">
        <div class="title-module"><i class="bi bi-globe2"></i> Recursos NXDN</div>
        <div class="divider-soft"></div>

        <ul class="list-unstyled mb-0">
          <li>🔗 <a class="link-light text-decoration-none" href="https://github.com/g4klx/NXDNReflector" target="_blank" rel="noopener">
            NXDNReflector (G4KLX)
          </a></li>
          <li>🔗 <a class="link-light text-decoration-none" href="https://github.com/g4klx/NXDNClients" target="_blank" rel="noopener">
            NXDN Clients (G4KLX)
          </a></li>
          <li>🔗 <a class="link-light text-decoration-none" href="https://www.radioid.net/" target="_blank" rel="noopener">
            RadioID (IDs e Indicativos)
          </a></li>
          <li>🔗 <a class="link-light text-decoration-none" href="https://zonadmr.cl/" target="_blank" rel="noopener">
            ZONA DMR CL · Web y Blog
          </a></li>
        </ul>
      </div>
    </div>

    <!-- Agradecimientos + Apoyo -->
    <div class="col-lg-7">
      <div class="card-custom h-100">
        <div class="title-module"><i class="bi bi-award"></i> Agradecimientos</div>
        <div class="divider-soft"></div>

        <p class="mb-2">
          Gracias a <strong>Jonathan Naylor, G4KLX</strong> por el trabajo open-source que hace posible
          los reflectores y utilidades NXDN.
        </p>
        <p class="mb-3">
          como tambien a <strong>NOSTAR</strong> por el instalador de los reflectores YSF P25 NXDN, que ayudan a la comunidad a experimetar con los modos digitales.
        </p>

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
          Los enlaces apuntan a repos públicos para revisar código y documentación.
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card-custom h-100">
        <div class="title-module"><i class="bi bi-heart"></i> Apoya a NXLink</div>
        <div class="divider-soft"></div>

        <p class="mb-3">
          Si deseas apoyar este proyecto de manera <strong>voluntaria</strong>, puedes realizar una donación.
          También puedes seguirme en mis redes sociales para más contenido de radioafición y proyectos técnicos.
        </p>

        <!-- Redes sociales -->
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

        <!-- Donación PayPal  -->
        <div class="d-grid mt-auto">
          <form action="https://www.paypal.com/donate" method="post" target="_top">
            <input type="hidden" name="hosted_button_id" value="DGA8ADD7EA63Y" />
            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-heart-fill me-2"></i> Donación voluntaria
            </button>
          </form>
        </div>

        <div class="small-label mt-3" style="opacity:.8;">
          Gracias por apoyar el crecimiento de la red y el desarrollo del dashboard.
        </div>

      </div>
    </div>

  </div>
</main>

<?php include __DIR__ . '/includes/footer_nxdn.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Tooltips Bootstrap
  const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  [...tooltipTriggerList].map(el => new bootstrap.Tooltip(el));
</script>
</body>
</html>
