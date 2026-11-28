<?php
/**
 * Data kendaraan driver.
 * Modul : Akun
 * Peran : Driver
 * FR    : FR-05
 * BR    : BR-03
 */

require_once __DIR__ . '/../kueri/kueri_kendaraan.php';
require_once __DIR__ . '/../kueri/kueri_driver.php';
require_once __DIR__ . '/../rangka/unggahan.php';

class KendaraanTidakDitemukan extends RuntimeException
{
}

function validasiDataKendaraan(array $data, bool $baru = true, bool $bolehAturAktif = false): array
{
    $diizinkan = ['merek', 'model', 'plat_nomor', 'warna', 'foto_url', 'jumlah_roda'];
    if ($bolehAturAktif) {
        $diizinkan[] = 'aktif';
    }
    foreach ($data as $kolom => $_) {
        if (!in_array($kolom, $diizinkan, true)) {
            throw new InvalidArgumentException("Kolom kendaraan.{$kolom} tidak diizinkan.");
        }
    }
    if ($data === []) {
        throw new InvalidArgumentException('Data kendaraan tidak boleh kosong.');
    }
    if ($baru) {
        foreach (['merek', 'plat_nomor', 'warna'] as $kolom) {
            if (!array_key_exists($kolom, $data)) {
                throw new InvalidArgumentException("kendaraan.{$kolom} wajib diisi.");
            }
        }
    }
    foreach (['merek' => 50, 'model' => 50, 'plat_nomor' => 15, 'warna' => 30, 'foto_url' => 255] as $kolom => $batas) {
        if (!array_key_exists($kolom, $data)) {
            continue;
        }
        if (in_array($kolom, ['model', 'foto_url'], true) && $data[$kolom] === null) {
            continue;
        }
        if (!is_string($data[$kolom]) || trim($data[$kolom]) === '' || mb_strlen($data[$kolom], 'UTF-8') > $batas) {
            throw new InvalidArgumentException("kendaraan.{$kolom} harus teks tidak kosong, maksimal {$batas} karakter.");
        }
        $data[$kolom] = trim($data[$kolom]);
    }
    if (array_key_exists('jumlah_roda', $data) && !in_array($data['jumlah_roda'], [4, '4'], true)) {
        throw new InvalidArgumentException('Kendaraan harus memiliki 4 roda.');
    }
    if (array_key_exists('aktif', $data)) {
        if (!in_array($data['aktif'], [0, 1, '0', '1', false, true], true)) {
            throw new InvalidArgumentException('aktif harus bernilai 0 atau 1.');
        }
        $data['aktif'] = (int) $data['aktif'];
    }
    if (isset($data['plat_nomor'])) {
        $data['plat_nomor'] = strtoupper($data['plat_nomor']);
    }
    return $data;
}

/** File foto_url pada endpoint kendaraan (tanpa grup kendaraan[...]). */
function fotoDariFormKendaraan(array $berkas): ?array
{
    if (array_diff(array_keys($berkas), ['foto_url']) !== []) {
        throw new InvalidArgumentException('Field file harus foto_url.');
    }
    $foto = $berkas['foto_url'] ?? null;
    return ($foto['error'] ?? null) === UPLOAD_ERR_NO_FILE ? null : $foto;
}

function ambilDaftarKendaraan(int $batas = 20, int $offset = 0, ?int $idDriver = null): array
{
    if ($batas < 1 || $batas > 100 || $offset < 0 || ($idDriver !== null && $idDriver < 1)) {
        throw new InvalidArgumentException('Batas harus 1-100, offset minimal 0, dan id_driver harus positif.');
    }
    return daftarKendaraan($batas, $offset, $idDriver);
}

/** ID driver opsional selalu berasal dari sesi saat digunakan oleh endpoint driver. */
function ambilDetailKendaraan(int $id, ?int $idDriver = null, bool $kunci = false): array
{
    if ($id < 1 || ($idDriver !== null && $idDriver < 1)) {
        throw new InvalidArgumentException('ID kendaraan dan driver harus positif.');
    }
    $kendaraan = cariKendaraan($id, $idDriver, $kunci);
    if ($kendaraan === null) {
        throw new KendaraanTidakDitemukan('Kendaraan tidak ditemukan.');
    }
    return $kendaraan;
}

function ambilDaftarKendaraanDriver(int $idLogin, int $batas = 20, int $offset = 0): array
{
    return ambilDaftarKendaraan($batas, $offset, $idLogin);
}

function ambilDetailKendaraanDriver(int $idLogin, int $id): array
{
    return ambilDetailKendaraan($id, $idLogin);
}

function buatKendaraan(array $input, ?array $foto = null): array
{
    $idDriver = $input['id_driver'] ?? null;
    if ((!is_int($idDriver) && !is_string($idDriver))
        || filter_var($idDriver, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        throw new InvalidArgumentException('id_driver wajib berupa bilangan bulat positif.');
    }
    $idDriver = (int) $idDriver;
    unset($input['id_driver']);
    if ($foto !== null) {
        $input['foto_url'] = null;
    }
    $data = validasiDataKendaraan($input, true, true);
    $db = koneksiDatabase();
    $urlBaru = null;
    $db->beginTransaction();
    try {
        // Kunci driver agar dua request create tidak sama-sama lolos pemeriksaan.
        if (cariDriver($idDriver, true) === null) {
            throw new InvalidArgumentException('id_driver tidak terdaftar sebagai driver.');
        }
        if (daftarKendaraan(1, 0, $idDriver) !== []) {
            throw new InvalidArgumentException('Driver sudah memiliki kendaraan. Perbarui kendaraan yang sudah ada.');
        }
        if ($foto !== null) {
            $data['foto_url'] = $urlBaru = simpanFotoKendaraan($foto);
        }
        $id = tambahKendaraan($idDriver, $data);
        $kendaraan = ambilDetailKendaraan($id);
        $db->commit();
        return $kendaraan;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($urlBaru !== null) {
            batalkanFotoKendaraan($urlBaru);
        }
        throw $exception;
    }
}

function perbaruiKendaraanAdmin(int $id, array $input, ?array $foto = null): array
{
    return simpanPerubahanKendaraan($id, $input, $foto);
}

function perbaruiKendaraanDriver(int $idLogin, int $id, array $input, ?array $foto = null): array
{
    return simpanPerubahanKendaraan($id, $input, $foto, $idLogin);
}

function simpanPerubahanKendaraan(int $id, array $input, ?array $foto = null, ?int $idDriver = null): array
{
    if ($foto !== null) {
        $input['foto_url'] = null;
    }
    $data = validasiDataKendaraan($input, false, $idDriver === null);
    $db = koneksiDatabase();
    $urlBaru = null;
    $db->beginTransaction();
    try {
        // Kunci row serta cek pemilik sebelum menyimpan file atau mengubah data.
        ambilDetailKendaraan($id, $idDriver, true);
        if ($foto !== null) {
            $data['foto_url'] = $urlBaru = simpanFotoKendaraan($foto);
        }
        ubahKendaraan($id, $data, $idDriver);
        $kendaraan = ambilDetailKendaraan($id, $idDriver);
        $db->commit();
        return $kendaraan;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($urlBaru !== null) {
            batalkanFotoKendaraan($urlBaru);
        }
        throw $exception;
    }
}

function nonaktifkanKendaraan(int $id): void
{
    $db = koneksiDatabase();
    $db->beginTransaction();
    try {
        ambilDetailKendaraan($id, null, true);
        nonaktifkanDataKendaraan($id);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

/** Membaca grup upload kendaraan[foto_url] dari struktur $_FILES PHP. */
function fotoDariBerkasKendaraan(array $grup): ?array
{
    $foto = [];
    foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $atribut) {
        if (!is_array($grup[$atribut] ?? null) || array_keys($grup[$atribut]) !== ['foto_url']) {
            throw new InvalidArgumentException('Field file kendaraan harus kendaraan[foto_url].');
        }
        $foto[$atribut] = $grup[$atribut]['foto_url'];
    }
    if (!is_int($foto['error']) || !is_string($foto['tmp_name']) || !is_string($foto['name']) || !is_int($foto['size'])) {
        throw new InvalidArgumentException('Format file kendaraan tidak valid.');
    }
    return $foto['error'] === UPLOAD_ERR_NO_FILE ? null : $foto;
}

