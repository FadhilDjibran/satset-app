<?php
/**
 * Registrasi, login, sesi, dan pembatasan peran.
 * Modul : Akun
 * Peran : Semua
 * FR    : FR-01, FR-02, FR-03, FR-04
 * BR    : BR-01, BR-02, BR-04
 */

require_once __DIR__ . '/../kueri/kueri_pengguna.php';
require_once __DIR__ . '/../rangka/sesi.php';

function loginPengguna(array $data): ?array
{
    if (!is_string($data['email'] ?? null) || !filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL)
        || !is_string($data['password'] ?? null) || $data['password'] === '') {
        throw new InvalidArgumentException('Email valid dan password wajib diisi.');
    }
    $akun = cariPenggunaUntukLogin(trim($data['email']));
    // Hash pengganti membuat pemeriksaan tetap dilakukan jika email tidak ditemukan.
    $hash = $akun['password_hash'] ?? '$2y$12$hNCGErcRthKAzUdzcWDpkeJ4c6OLGyziyLheTKjPe7GmpTkI1rFJi';
    $cocok = password_verify($data['password'], $hash);
    if (!$cocok || $akun === null || $akun['status_akun'] !== 'aktif') {
        return null;
    }

    mulaiSesi();
    session_regenerate_id(true);
    $_SESSION = [
        'pengguna_id' => (int) $akun['id'],
        'aktivitas_pada' => time(),
        'csrf_token' => bin2hex(random_bytes(32)),
    ];
    return ['pengguna' => cariPengguna((int) $akun['id']), 'csrf_token' => $_SESSION['csrf_token']];
}

