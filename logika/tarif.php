<?php
/**
 * Tarif rekomendasi dan rentang wajar.
 * Modul : Pemesanan
 * Peran : Sistem
 * FR    : FR-07
 * BR    : BR-06, BR-10
 */

require_once __DIR__ . '/../kueri/kueri_pengaturan.php';

/** Hitung tarif untuk jarak yang sudah dibulatkan ke 0,01 km. */
function hitungTarifPerjalanan(int $jarakPerSeratusKm): array
{
    $pengaturan = ambilNilaiPengaturanTarif();
    $batasRupiah = 2147483647; // INT pada detail_pesanan.
    $opsi = ['options' => ['min_range' => 0, 'max_range' => $batasRupiah]];
    $dasar = filter_var($pengaturan['tarif_dasar'] ?? null, FILTER_VALIDATE_INT, $opsi);
    $perKm = filter_var($pengaturan['tarif_per_km'] ?? null, FILTER_VALIDATE_INT, $opsi);
    $persen = $pengaturan['rentang_wajar_persen'] ?? '';
    if ($dasar === false || $perKm === false || ($dasar === 0 && $perKm === 0)
        || !preg_match('/\A([0-9]+)(?:\.([0-9]+))?\z/', $persen, $bagian)) {
        throw new RuntimeException('Konfigurasi tarif perjalanan belum lengkap atau tidak valid.');
    }
    $persenBulat = (int) $bagian[1];
    $desimal = rtrim($bagian[2] ?? '', '0');
    if ($persenBulat > 100 || ($persenBulat === 100 && $desimal !== '')) {
        throw new RuntimeException('Konfigurasi rentang wajar harus antara 0 dan 100 persen.');
    }

    // Tambah 50 sebelum dibagi 100 untuk membulatkan setengah rupiah ke atas.
    $tarif = $dasar + intdiv($jarakPerSeratusKm * $perKm + 50, 100);
    if ($tarif < 1 || $tarif > $batasRupiah) {
        throw new RuntimeException('Tarif rekomendasi di luar batas penyimpanan.');
    }

    // Perkalian digit dari kanan memberi floor(tarif × bagian desimal) tanpa galat float.
    $bagianDesimal = 0;
    for ($i = strlen($desimal) - 1; $i >= 0; $i--) {
        $bagianDesimal = intdiv($tarif * (int) $desimal[$i] + $bagianDesimal, 10);
    }
    // Untuk tarif bulat, ceil(tarif − selisih) sama dengan tarif − floor(selisih).
    $selisih = intdiv($tarif * $persenBulat + $bagianDesimal, 100);
    $maksimum = $tarif + $selisih;
    if ($maksimum > $batasRupiah) {
        throw new RuntimeException('Harga maksimum di luar batas penyimpanan.');
    }

    return [
        'tarif_rekomendasi' => $tarif,
        'harga_min' => max(1, $tarif - $selisih),
        'harga_maks' => $maksimum,
    ];
}

