<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$available = ['es', 'en'];

// Cambiar idioma
if (isset($_GET['lang']) && in_array($_GET['lang'], $available, true)) {
  $_SESSION['lang'] = $_GET['lang'];
}

$lang_code = $_SESSION['lang'] ?? 'es';

$base = __DIR__ . "/lang";
$file = $base . "/{$lang_code}.php";

$lang = file_exists($file) ? require $file : require $base . "/es.php";

function __($key) {
  global $lang;
  return $lang[$key] ?? $key;
}
