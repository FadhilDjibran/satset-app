<?php
/**
 * Kelola pengguna dan data dasbor.
 * Modul : Admin
 * Peran : Admin
 * FR    : FR-31, FR-32
 * BR    : BR-04
 */

require_once __DIR__ . '/../kueri/kueri_pengguna.php';

function validasiDataPengguna(array $data, bool $baru): array
{
    $diizinkan = $baru
        ? ['nama', 'email', 'nomor_hp', 'password', 'peran']
        : ['nama', 'email', 'nomor_hp', 'password', 'foto_profil', 'status_akun'];

    foreach ($data as $kunci => $_) {
        if (!in_array($kunci, $diizinkan, true)) {
            throw new InvalidArgumentException("Kolom {$kunci} tidak diizinkan.");
        }
    }
    if ($data === []) {
        throw new InvalidArgumentException('Data pengguna tidak boleh kosong.');
    }

    foreach (['nama' => 100, 'email' => 255, 'nomor_hp' => 20, 'password' => 255, 'foto_profil' => 255] as $kolom => $maksimal) {
        if (!array_key_exists($kolom, $data)) {
            continue;
        }
        if (!is_string($data[$kolom]) || trim($data[$kolom]) === '' || mb_strlen($data[$kolom], 'UTF-8') > $maksimal) {
            throw new InvalidArgumentException("{$kolom} wajib berupa teks, tidak kosong, maksimal {$maksimal} karakter.");
        }
        $data[$kolom] = trim($data[$kolom]);
    }

    if ($baru) {
        foreach (['nama', 'email', 'nomor_hp', 'password', 'peran'] as $wajib) {
            if (!array_key_exists($wajib, $data)) {
                throw new InvalidArgumentException("{$wajib} wajib diisi.");
            }
        }
    }
    if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Format email tidak valid.');
    }
    if (isset($data['nomor_hp']) && !preg_match('/^\+?[0-9]{8,15}$/', $data['nomor_hp'])) {
        throw new InvalidArgumentException('Nomor HP harus 8-15 digit; tanda + boleh di depan.');
    }
    if (isset($data['peran']) && !in_array($data['peran'], ['penumpang', 'driver'], true)) {
        throw new InvalidArgumentException('Peran hanya boleh penumpang atau driver.');
    }
    if (isset($data['status_akun']) && !in_array($data['status_akun'], ['aktif', 'ditangguhkan'], true)) {
        throw new InvalidArgumentException('Status akun tidak valid.');
    }
    if (isset($data['password'])) {
        if (strlen($data['password']) < 8) {
            throw new InvalidArgumentException('Password minimal 8 karakter.');
        }
        $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        unset($data['password']);
    }

    return $data;
}

