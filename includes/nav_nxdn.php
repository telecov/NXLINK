<?php
// 1) Idioma: solo si NO está cargado
if (!function_exists('__')) {
  require_once __DIR__ . "/lang.php"; // <-- si tu cargador se llama lang.php dentro de /includes
  // Si tu cargador se llama "lang.php" OK.
  // Si se llama "lang.php" en otra ruta, ajusta.
}

// 2) Telegram
require_once __DIR__ . "/telegram.php";
$tgCfg = tg_load_config();
$tgInviteLink = $tgCfg["invite_link"] ?? "";

// 3) Helper: mantener lang en links (evita redeclare)
if (!function_exists('nx_link')) {
  function nx_link(string $path): string {
    $lang = $_SESSION['lang'] ?? 'es';
    $sep = (strpos($path, '?') !== false) ? '&' : '?';
    return $path . $sep . 'lang=' . urlencode($lang);
  }
}
?>

<div class="d-flex justify-content-end align-items-center gap-2 mb-3 p-2 rounded nav-nx-controls">

  <a href="<?php echo nx_link('index.php'); ?>" class="btn btn-outline-success btn-sm">
    <i class="bi bi-speedometer2"></i> <?php echo __("nav_dashboard"); ?>
  </a>

  <a href="<?php echo nx_link('personalizacion.php'); ?>" class="btn btn-outline-info btn-sm">
    <i class="bi bi-palette"></i> <?php echo __("nav_personalization"); ?>
  </a>

  <a href="<?php echo nx_link('configuracion.php'); ?>" class="btn btn-outline-warning btn-sm">
    <i class="bi bi-gear"></i> <?php echo __("nav_settings"); ?>
  </a>

  <a href="<?php echo nx_link('about.php'); ?>" class="btn btn-outline-light btn-sm">
    <i class="bi bi-info-circle"></i> <?php echo __("nav_about"); ?>
  </a>

  <?php if (!empty($tgInviteLink)): ?>
    <a href="<?php echo htmlspecialchars($tgInviteLink); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-telegram"></i> <?php echo __("nav_telegram"); ?>
    </a>
  <?php endif; ?>

</div>

