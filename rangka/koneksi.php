<?php
/**
 * Koneksi database (PDO).
 * Modul : Rangka
 * Peran : -
 * FR    : -
 */

require_once __DIR__ . '/muat.php';

function koneksiDatabase(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = konfigurasiEnv('DB_HOST');
    $port = konfigurasiEnv('DB_PORT') ?? '3306';
    $nama = konfigurasiEnv('DB_NAME');
    $pengguna = konfigurasiEnv('DB_USER');
    $sandi = konfigurasiEnv('DB_PASS') ?? '';

    if ($host === null || $nama === null || $pengguna === null) {
        throw new RuntimeException('Konfigurasi database belum lengkap. Salin .env.contoh ke .env lalu isi DB_HOST, DB_NAME, dan DB_USER.');
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $nama);
    $pdo = new PDO($dsn, $pengguna, $sandi, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

