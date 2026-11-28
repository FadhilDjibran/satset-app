<?php
/**
 * Data kendaraan driver.
 * Modul : Akun
 * Peran : Driver
 * FR    : FR-05
 * BR    : BR-03
 */

require_once __DIR__ . '/../kueri/kueri_kendaraan.php';

function validasiDataKendaraan(array $data): array
{
    $diizinkan = ['merek', 'model', 'plat_nomor', 'warna', 'foto_url', 'jumlah_roda'];
    foreach ($data as $kolom => $_) {
        if (!in_array($kolom, $diizinkan, true)) {
            throw new InvalidArgumentException("Kolom kendaraan.{$kolom} tidak diizinkan.");
        }
    }
    foreach (['merek', 'plat_nomor', 'warna'] as $kolom) {
        if (!array_key_exists($kolom, $data)) {
            throw new InvalidArgumentException("kendaraan.{$kolom} wajib diisi.");
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
    $data['plat_nomor'] = strtoupper($data['plat_nomor']);
    return $data;
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

