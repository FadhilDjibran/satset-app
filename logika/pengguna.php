<?php
/**
 * Kelola pengguna dan data dasbor.
 * Modul : Admin
 * Peran : Admin
 * FR    : FR-31, FR-32
 * BR    : BR-04
 */

require_once __DIR__ . '/../kueri/kueri_pengguna.php';
require_once __DIR__ . '/../rangka/unggahan.php';

class PenggunaTidakDitemukan extends RuntimeException
{
}

/** Aturan field upload pengguna, terpisah dari pembaca Request yang umum. */
function fotoDariBerkasPengguna(array $berkas): ?array
{
    if (array_diff(array_keys($berkas), ['foto_url']) !== []) {
        throw new InvalidArgumentException('Nama field file harus foto_url.');
    }
    $foto = $berkas['foto_url'] ?? null;
    return ($foto['error'] ?? null) === UPLOAD_ERR_NO_FILE ? null : $foto;
}

/** Service daftar: aturan pagination sebelum memanggil kueri. */
function ambilDaftarPengguna(int $batas = 20, int $offset = 0): array
{
    if ($batas < 1 || $batas > 100 || $offset < 0) {
        throw new InvalidArgumentException('Batas harus 1-100 dan offset tidak boleh negatif.');
    }
    return daftarPengguna($batas, $offset);
}

/** Service detail: gunakan exception agar bisa dipakai oleh API maupun view PHP. */
function ambilDetailPengguna(int $id): array
{
    if ($id < 1) {
        throw new InvalidArgumentException('id harus bilangan bulat positif.');
    }
    $pengguna = cariPengguna($id);
    if ($pengguna === null) {
        throw new PenggunaTidakDitemukan('Pengguna tidak ditemukan.');
    }
    return $pengguna;
}

/** Service update: validasi -> cek keberadaan -> UPDATE -> baca hasil. */
function perbaruiPengguna(int $id, array $input, ?array $foto = null): array
{
    if ($foto !== null) {
        $input['foto_url'] = null;
    }
    $data = validasiDataPengguna($input, false);
    ambilDetailPengguna($id);
    $urlBaru = null;
    $db = koneksiDatabase();
    $db->beginTransaction();
    try {
        if ($foto !== null) {
            $data['foto_url'] = $urlBaru = simpanFotoProfil($foto);
        }
        ubahPengguna($id, $data);
        $pengguna = ambilDetailPengguna($id);
        $db->commit();
        return $pengguna;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($urlBaru !== null) {
            batalkanFotoProfil($urlBaru);
        }
        throw $exception;
    }
}

/** Service delete: data tetap disimpan untuk menjaga riwayat. */
function nonaktifkanPengguna(int $id): void
{
    ambilDetailPengguna($id);
    if (!hapusPengguna($id)) {
        throw new PenggunaTidakDitemukan('Pengguna tidak ditemukan.');
    }
}

/** Data daftar untuk view HTML; ambil satu ekstra untuk mengetahui halaman berikutnya. */
function dataHalamanPengguna(int $halaman): array
{
    if ($halaman < 1 || $halaman > 1000000) {
        throw new InvalidArgumentException('Nomor halaman tidak valid.');
    }
    $perHalaman = 20;
    $baris = ambilDaftarPengguna($perHalaman + 1, ($halaman - 1) * $perHalaman);
    return [
        'pengguna' => array_slice($baris, 0, $perHalaman),
        'ada_berikutnya' => count($baris) > $perHalaman,
    ];
}

function validasiDataPengguna(array $data, bool $baru): array
{
    $diizinkan = $baru
        ? ['nama', 'email', 'nomor_hp', 'password', 'peran', 'foto_url', 'status_akun']
        : ['nama', 'email', 'nomor_hp', 'password', 'foto_url', 'status_akun'];

    foreach ($data as $kunci => $_) {
        if (!in_array($kunci, $diizinkan, true)) {
            throw new InvalidArgumentException("Kolom {$kunci} tidak diizinkan.");
        }
    }
    if ($data === []) {
        throw new InvalidArgumentException('Data pengguna tidak boleh kosong.');
    }

    foreach (['nama' => 100, 'email' => 100, 'nomor_hp' => 20, 'password' => 255, 'foto_url' => 255] as $kolom => $maksimal) {
        if (!array_key_exists($kolom, $data)) {
            continue;
        }
        if ($kolom === 'foto_url' && $data[$kolom] === null) {
            continue;
        }
        if (!is_string($data[$kolom]) || trim($data[$kolom]) === '' || mb_strlen($data[$kolom], 'UTF-8') > $maksimal) {
            throw new InvalidArgumentException("{$kolom} wajib berupa teks, tidak kosong, maksimal {$maksimal} karakter.");
        }
        if ($kolom !== 'password') {
            $data[$kolom] = trim($data[$kolom]);
        }
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
    if (array_key_exists('peran', $data) && !in_array($data['peran'], ['penumpang', 'driver'], true)) {
        throw new InvalidArgumentException('Peran hanya boleh penumpang atau driver.');
    }
    if (array_key_exists('status_akun', $data) && !in_array($data['status_akun'], ['aktif', 'ditangguhkan', 'nonaktif'], true)) {
        throw new InvalidArgumentException('Status akun tidak valid.');
    }
    if (isset($data['password'])) {
        if (strlen($data['password']) < 8 || strlen($data['password']) > 72) {
            throw new InvalidArgumentException('Password harus 8-72 byte.');
        }
        $data['kata_sandi_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        unset($data['password']);
    }

    return $data;
}

/** Satu transaksi agar pengguna, driver, dompet, dan saldo awal selalu konsisten. */
function buatPengguna(array $input, ?array $foto = null): array
{
    if ($foto !== null) {
        $input['foto_url'] = null;
    }
    $data = validasiDataPengguna($input, true);
    $urlBaru = null;
    $db = koneksiDatabase();
    $db->beginTransaction();
    try {
        if ($foto !== null) {
            $data['foto_url'] = $urlBaru = simpanFotoProfil($foto);
        }
        $id = tambahPengguna($data);
        if ($data['peran'] === 'driver') {
            $saldo = filter_var(bacaSaldoAwalDriver(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);
            if ($saldo === false) {
                throw new RuntimeException('Pengaturan saldo awal driver belum valid.');
            }
            buatDataAwalDriver($id, $saldo);
        }
        $pengguna = ambilDetailPengguna($id);
        $db->commit();
        return $pengguna;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($urlBaru !== null) {
            batalkanFotoProfil($urlBaru);
        }
        throw $exception;
    }
}

