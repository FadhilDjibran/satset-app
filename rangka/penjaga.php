<?php
/**
 * Pembatasan akses halaman sesuai peran (FR-03).
 * Modul : Rangka
 * Peran : -
 * FR    : -
 */

require_once __DIR__ . '/json.php';
require_once __DIR__ . '/sesi.php';
require_once __DIR__ . '/../kueri/kueri_pengguna.php';

/** Middleware: cek sesi dan status terbaru dari database pada setiap request. */
function penggunaDariSesi(): ?array
{
    mulaiSesi();
    if (!isset($_SESSION['pengguna_id'], $_SESSION['aktivitas_pada']) || time() - $_SESSION['aktivitas_pada'] > 1800) {
        hapusSesi();
        return null;
    }
    $pengguna = cariPengguna((int) $_SESSION['pengguna_id']);
    if ($pengguna === null || $pengguna['status_akun'] !== 'aktif') {
        hapusSesi();
        return null;
    }
    $_SESSION['aktivitas_pada'] = time();
    return $pengguna;
}

function wajibLogin(?string $peran = null): array
{
    $pengguna = penggunaDariSesi();
    if ($pengguna === null) {
        kirimJson(401, ['berhasil' => false, 'pesan' => 'Silakan login terlebih dahulu.']);
    }
    if ($peran !== null && $pengguna['peran'] !== $peran) {
        kirimJson(403, ['berhasil' => false, 'pesan' => 'Akses hanya untuk ' . $peran . '.']);
    }
    return $pengguna;
}

function wajibTokenCsrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        kirimJson(403, ['berhasil' => false, 'pesan' => 'X-CSRF-Token tidak valid. Gunakan token dari respons login.']);
    }
}

