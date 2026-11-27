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
require_once __DIR__ . '/pengguna.php';

/** Service registrasi publik; tidak membutuhkan atau membuat sesi login. */
function registrasiDriver(array $input, ?array $foto = null): array
{
    return registrasiAkun($input, 'driver', $foto);
}

function registrasiPenumpang(array $input, ?array $foto = null): array
{
    return registrasiAkun($input, 'penumpang', $foto);
}

/** Peran berasal dari endpoint, bukan dari body yang dikirim guest. */
function registrasiAkun(array $input, string $peran, ?array $foto = null): array
{
    if (!in_array($peran, ['driver', 'penumpang'], true)) {
        throw new InvalidArgumentException('Registrasi hanya untuk driver atau penumpang.');
    }
    $diizinkan = ['nama', 'email', 'nomor_hp', 'password', 'foto_url'];
    foreach ($input as $kolom => $_) {
        if (!in_array($kolom, $diizinkan, true)) {
            throw new InvalidArgumentException("Kolom {$kolom} tidak diizinkan pada registrasi. Peran dan status akun ditentukan sistem.");
        }
    }
    $input['peran'] = $peran;
    $input['status_akun'] = 'aktif';

    return buatPengguna($input, $foto);
}

function loginPengguna(array $data): ?array
{
    if (!is_string($data['email'] ?? null) || !filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL)
        || !is_string($data['password'] ?? null) || $data['password'] === '') {
        throw new InvalidArgumentException('Email valid dan password wajib diisi.');
    }
    $akun = cariPenggunaUntukLogin(trim($data['email']));
    // Hash pengganti membuat pemeriksaan tetap dilakukan jika email tidak ditemukan.
    $hash = $akun['kata_sandi_hash'] ?? '$2y$12$hNCGErcRthKAzUdzcWDpkeJ4c6OLGyziyLheTKjPe7GmpTkI1rFJi';
    $cocok = password_verify($data['password'], $hash);
    if (!$cocok || $akun === null || $akun['status_akun'] !== 'aktif') {
        return null;
    }

    mulaiSesi();
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('ID sesi tidak dapat diperbarui.');
    }
    $_SESSION = [
        'pengguna_id' => (int) $akun['id_pengguna'],
        'aktivitas_pada' => time(),
        'csrf_token' => bin2hex(random_bytes(32)),
    ];
    return ['pengguna' => cariPengguna((int) $akun['id_pengguna']), 'csrf_token' => $_SESSION['csrf_token']];
}

