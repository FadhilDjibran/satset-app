<?php
/**
 * Rute, jarak, dan estimasi perjalanan dari OSRM.
 * Modul : Pemesanan
 * Peran : Sistem
 * FR    : FR-06, FR-07
 */

require_once __DIR__ . '/../rangka/muat.php';
require_once __DIR__ . '/tarif.php';

class RuteTidakTersedia extends RuntimeException
{
}

class LayananRuteGagal extends RuntimeException
{
}

class LayananRuteTimeout extends LayananRuteGagal
{
}

/** API dan halaman PHP memakai service yang sama setelah memeriksa hak akses. */
function hitungEstimasiPerjalanan(array $input): array
{
    $titik = validasiKoordinatPerjalanan($input);
    $rute = ambilRuteOsrm($titik);

    // Satu satuan mewakili 10 meter atau 0,01 km, sesuai DECIMAL(6,2).
    $jarakPerSeratusKm = round($rute->distance / 10);
    $menit = max(1, ceil($rute->duration / 60));
    if ($jarakPerSeratusKm < 1) {
        throw new RuteTidakTersedia('Rute terlalu pendek. Pilih titik jemput dan tujuan yang lebih berjauhan.');
    }
    if ($jarakPerSeratusKm > 999999 || $menit > 65535) {
        throw new RuteTidakTersedia('Jarak atau waktu perjalanan melebihi batas yang dapat diproses.');
    }

    return [
        'jarak_km' => $jarakPerSeratusKm / 100,
        'estimasi_menit' => (int) $menit,
        ...hitungTarifPerjalanan((int) $jarakPerSeratusKm),
        'rute_geojson' => $rute->geometry,
    ];
}

function validasiKoordinatPerjalanan(array $input): array
{
    $batasKoordinat = ['jemput_lat' => 90, 'jemput_lng' => 180, 'tujuan_lat' => 90, 'tujuan_lng' => 180];
    if (array_diff_key($input, $batasKoordinat) !== []) {
        throw new InvalidArgumentException('Kirim hanya koordinat jemput dan tujuan.');
    }
    $titik = [];
    foreach ($batasKoordinat as $nama => $batas) {
        $nilai = $input[$nama] ?? null;
        if ((!is_int($nilai) && !is_float($nilai) && !is_string($nilai)) || !is_numeric($nilai)
            || !is_finite((float) $nilai) || abs((float) $nilai) > $batas) {
            throw new InvalidArgumentException("{$nama} wajib berupa angka antara -{$batas} dan {$batas}.");
        }
        $titik[$nama] = round((float) $nilai, 7);
    }
    if ($titik['jemput_lat'] === $titik['tujuan_lat'] && $titik['jemput_lng'] === $titik['tujuan_lng']) {
        throw new InvalidArgumentException('Titik jemput dan tujuan harus berbeda.');
    }
    return $titik;
}

function ambilRuteOsrm(array $titik): stdClass
{
    $server = konfigurasiEnv('OSRM_URL') ?? '';
    if (!filter_var($server, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('OSRM_URL belum dikonfigurasi atau tidak valid.');
    }
    $bagian = parse_url($server);
    if (!in_array(strtolower($bagian['scheme']), ['http', 'https'], true)
        || isset($bagian['user']) || isset($bagian['pass']) || isset($bagian['query']) || isset($bagian['fragment'])) {
        throw new RuntimeException('OSRM_URL harus berupa URL dasar HTTP atau HTTPS.');
    }

    $koordinat = sprintf('%.7F,%.7F;%.7F,%.7F',
        $titik['jemput_lng'], $titik['jemput_lat'], $titik['tujuan_lng'], $titik['tujuan_lat']);
    $url = rtrim($server, '/') . '/route/v1/driving/' . $koordinat . '?geometries=geojson&overview=simplified&steps=false';
    $klien = curl_init($url);
    if ($klien === false) {
        throw new RuntimeException('Klien HTTP untuk OSRM tidak dapat dibuat.');
    }
    $isi = '';
    curl_setopt_array($klien, [
        CURLOPT_CONNECTTIMEOUT_MS => 2000,
        CURLOPT_TIMEOUT_MS => 5000,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'SATSET/1.0 (development)',
        CURLOPT_WRITEFUNCTION => static function (CurlHandle $klien, string $potongan) use (&$isi): int {
            if (strlen($isi) + strlen($potongan) > 2 * 1024 * 1024) {
                return 0;
            }
            $isi .= $potongan;
            return strlen($potongan);
        },
    ]);
    if (curl_exec($klien) === false) {
        $kode = curl_errno($klien);
        if ($kode === CURLE_OPERATION_TIMEDOUT) {
            throw new LayananRuteTimeout('Layanan rute tidak merespons tepat waktu.');
        }
        throw new LayananRuteGagal("Permintaan ke OSRM gagal (kode cURL {$kode}).");
    }

    $status = (int) curl_getinfo($klien, CURLINFO_RESPONSE_CODE);
    if ($status === 504) {
        throw new LayananRuteTimeout('Layanan rute tidak merespons tepat waktu.');
    }
    $respons = json_decode($isi, false, 32);
    if ($respons instanceof stdClass && in_array($status, [200, 400], true)
        && in_array($respons->code ?? null, ['NoRoute', 'NoSegment'], true)) {
        throw new RuteTidakTersedia('Rute mobil tidak ditemukan untuk titik jemput dan tujuan tersebut.');
    }
    if ($status !== 200 || !$respons instanceof stdClass || ($respons->code ?? null) !== 'Ok'
        || !is_array($respons->routes ?? null) || !(($respons->routes[0] ?? null) instanceof stdClass)) {
        throw new LayananRuteGagal("Respons OSRM tidak valid (HTTP {$status}).");
    }

    $rute = $respons->routes[0];
    foreach (['distance', 'duration'] as $nama) {
        $nilai = $rute->$nama ?? null;
        if ((!is_int($nilai) && !is_float($nilai)) || !is_finite((float) $nilai) || $nilai < 0) {
            throw new LayananRuteGagal('Jarak atau durasi dari OSRM tidak valid.');
        }
    }
    validasiGeometriOsrm($rute->geometry ?? null);
    return $rute;
}

function validasiGeometriOsrm(mixed $geometri): void
{
    if (!$geometri instanceof stdClass || ($geometri->type ?? null) !== 'LineString'
        || !is_array($geometri->coordinates ?? null) || count($geometri->coordinates) < 2) {
        throw new LayananRuteGagal('Geometri rute dari OSRM tidak valid.');
    }
    foreach ($geometri->coordinates as $posisi) {
        if (!is_array($posisi) || count($posisi) !== 2) {
            throw new LayananRuteGagal('Posisi pada geometri rute tidak valid.');
        }
        foreach ([180, 90] as $indeks => $batas) {
            $nilai = $posisi[$indeks];
            if ((!is_int($nilai) && !is_float($nilai)) || !is_finite((float) $nilai) || abs($nilai) > $batas) {
                throw new LayananRuteGagal('Koordinat pada geometri rute tidak valid.');
            }
        }
    }
}

