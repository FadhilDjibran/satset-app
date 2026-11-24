<?php
/**
 * Pengelolaan sesi per peran.
 * Modul : Rangka
 * Peran : -
 * FR    : -
 */

function mulaiSesi(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('SATSETSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    if (!@session_start()) {
        throw new RuntimeException('Sesi tidak dapat dimulai. Periksa session.save_path PHP dan izin direktorinya.');
    }
}

function hapusSesi(): void
{
    mulaiSesi();
    $_SESSION = [];
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $cookie['path'],
        'secure' => $cookie['secure'],
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_destroy();
}

