<?php
/**
 * Daftar, detail, dan perubahan parameter sistem.
 * Modul : Admin
 * Peran : Admin
 * FR    : FR-03
 * BR    : BR-08, BR-21, BR-22
 */

require_once __DIR__ . '/../kueri/kueri_pengaturan.php';

class PengaturanTidakDitemukan extends RuntimeException
{
}

function validasiKunciPengaturan(string $kunci): string
{
    $kunci = trim($kunci);
    if ($kunci === '' || mb_strlen($kunci, 'UTF-8') > 50) {
        throw new InvalidArgumentException('kunci harus berupa teks tidak kosong, maksimal 50 karakter.');
    }
    return $kunci;
}

function ambilDaftarPengaturan(): array
{
    return daftarPengaturan();
}

function ambilDetailPengaturan(string $kunci, bool $kunciBaris = false): array
{
    $pengaturan = cariPengaturan(validasiKunciPengaturan($kunci), $kunciBaris);
    if ($pengaturan === null) {
        throw new PengaturanTidakDitemukan('Pengaturan tidak ditemukan.');
    }
    return $pengaturan;
}

function validasiDataPengaturan(string $kunci, array $data): array
{
    if ($data === [] || array_diff(array_keys($data), ['nilai', 'keterangan']) !== []) {
        throw new InvalidArgumentException('Kirim minimal satu kolom: nilai atau keterangan. Kolom lain tidak diizinkan.');
    }
    if (array_key_exists('nilai', $data)) {
        $nilai = $data['nilai'];
        if (!is_string($nilai) && !is_int($nilai) && !is_float($nilai)) {
            throw new InvalidArgumentException('nilai harus berupa teks atau angka, tidak boleh null.');
        }
        $nilai = trim((string) $nilai);
        if ($nilai === '' || mb_strlen($nilai, 'UTF-8') > 100) {
            throw new InvalidArgumentException('nilai tidak boleh kosong, maksimal 100 karakter.');
        }
        validasiNilaiPengaturan($kunci, $nilai);
        $data['nilai'] = $nilai;
    }
    if (array_key_exists('keterangan', $data) && $data['keterangan'] !== null) {
        if (!is_string($data['keterangan']) || mb_strlen($data['keterangan'], 'UTF-8') > 255) {
            throw new InvalidArgumentException('keterangan harus berupa teks maksimal 255 karakter atau null.');
        }
        $data['keterangan'] = trim($data['keterangan']);
    }
    return $data;
}

/** Jaga parameter bawaan tetap dapat dipakai modul yang membacanya. */
function validasiNilaiPengaturan(string $kunci, string $nilai): void
{
    $bilanganBulat = [
        'saldo_awal_driver' => [0, 2147483647],
        'maks_ronde' => [1, 3],
        'durasi_timer_detik' => [1, 2147483647],
        'tarif_dasar' => [0, 2147483647],
        'tarif_per_km' => [0, 2147483647],
        'radius_tiba_pulang_m' => [1, 2147483647],
    ];
    if (isset($bilanganBulat[$kunci])) {
        [$min, $maks] = $bilanganBulat[$kunci];
        if (filter_var($nilai, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $maks]]) === false) {
            throw new InvalidArgumentException("{$kunci} harus bilangan bulat antara {$min} dan {$maks}.");
        }
    }
    if (in_array($kunci, ['persen_komisi', 'rentang_wajar_persen', 'radius_pesanan_km'], true)) {
        $angka = (float) $nilai;
        if (!preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $nilai) || !is_finite($angka)
            || ($kunci === 'radius_pesanan_km' ? $angka <= 0 : $angka > 100)) {
            throw new InvalidArgumentException("{$kunci} harus angka desimal positif; persentase antara 0 dan 100.");
        }
        if ($kunci === 'persen_komisi' && !preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/D', $nilai)) {
            throw new InvalidArgumentException('persen_komisi maksimal 2 angka di belakang koma.');
        }
    }
}

function perbaruiPengaturan(string $kunci, array $input): array
{
    $kunci = validasiKunciPengaturan($kunci);
    $db = koneksiDatabase();
    $db->beginTransaction();
    try {
        // Pakai kunci dari database agar validasi tetap berlaku pada kolasi tanpa peka huruf besar.
        $pengaturan = ambilDetailPengaturan($kunci, true);
        $data = validasiDataPengaturan($pengaturan['kunci'], $input);
        ubahPengaturan($pengaturan['kunci'], $data);
        $hasil = ambilDetailPengaturan($pengaturan['kunci']);
        $db->commit();
        return $hasil;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}
