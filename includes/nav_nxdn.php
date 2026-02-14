<?php
// Cargar Telegram config (portable aunque esté en /html o /html/NXDN)
require_once __DIR__ . "/telegram.php";
$tgCfg = tg_load_config();
$tgInviteLink = $tgCfg["invite_link"] ?? "";
?>

<div class="d-flex justify-content-end align-items-center gap-2 mb-3 p-2 rounded nav-nx-controls">

    <a href="index.php"
       class="btn btn-outline-success btn-sm">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <a href="personalizacion.php"
       class="btn btn-outline-info btn-sm"> 
        <i class="bi bi-palette"></i> Personalización
    </a>

    <a href="configuracion.php"
       class="btn btn-outline-warning btn-sm">
        <i class="bi bi-gear"></i> Configuración
    </a>

    <a href="about.php"
       class="btn btn-outline-light btn-sm">
        <i class="bi bi-info-circle"></i> About
    </a>

    <?php if (!empty($tgInviteLink)): ?>
    <a href="<?php echo htmlspecialchars($tgInviteLink); ?>"
       target="_blank"
       class="btn btn-outline-primary btn-sm">
        <i class="bi bi-telegram"></i> Telegram
    </a>
    <?php endif; ?>

</div>
