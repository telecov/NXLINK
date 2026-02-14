<?php
// ===========================================================
// NXDN - Telegram helper (config + send + ready)
// Lee: /includes/config_telegram.json
// ===========================================================

/**
 * Devuelve ruta base del proyecto, funcione si NXDN está en /html/NXDN
 * o si está directo en /html (sin subcarpeta).
 */
function tg_base_dir(): string {
    // Este archivo vive en .../includes/telegram.php
    return dirname(__DIR__); // sube a carpeta raíz del proyecto
}

/** Ruta absoluta del config Telegram */
function tg_config_path(): string {
    return tg_base_dir() . '/includes/config_telegram.json';
}

/** Carga config Telegram desde JSON */
function tg_load_config(): array {
    $file = tg_config_path();

    $cfg = [
        'bot_token'   => '',
        'chat_id'     => '',
        'invite_link' => ''
    ];

    if (is_readable($file)) {
        $raw = file_get_contents($file);
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $cfg = array_merge($cfg, $json);
        }
    }

    // Normaliza
    $cfg['bot_token']   = trim((string)($cfg['bot_token'] ?? ''));
    $cfg['chat_id']     = trim((string)($cfg['chat_id'] ?? ''));
    $cfg['invite_link'] = trim((string)($cfg['invite_link'] ?? ''));

    return $cfg;
}

/** True si hay token y chat_id válidos */
function telegram_ready(): bool {
    $cfg = tg_load_config();
    return ($cfg['bot_token'] !== '' && $cfg['chat_id'] !== '');
}

/**
 * Envía mensaje Telegram (HTML). Retorna array con ok/error.
 * - $chat_id opcional: si no lo pasas usa el del JSON.
 */
function telegram_send(string $message, ?string $chat_id = null): array {
    $cfg = tg_load_config();

    $token = $cfg['bot_token'];
    $chat  = $chat_id !== null ? trim($chat_id) : $cfg['chat_id'];

    if ($token === '' || $chat === '') {
        return ['ok' => false, 'error' => 'Telegram no configurado (token/chat_id vacío)'];
    }

    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    $payload = [
        'chat_id'                  => $chat,
        'text'                     => $message,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => true
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => 'NXDN-Dashboard/1.0 (CA2RDP)'
    ]);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        return ['ok' => false, 'error' => "cURL: {$err}"];
    }

    $json = json_decode($resp, true);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => "Respuesta no JSON (HTTP {$code})", 'raw' => $resp];
    }

    if ($code < 200 || $code >= 300 || empty($json['ok'])) {
        $desc = $json['description'] ?? 'Error desconocido';
        return ['ok' => false, 'error' => "Telegram API: {$desc}", 'http' => $code];
    }

    return ['ok' => true, 'result' => $json['result'] ?? null];
}
