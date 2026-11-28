<?php
/**
 * Hitung rute, jarak, estimasi waktu, tarif rekomendasi, dan rentang wajar (OSRM).
 * Modul : Pemesanan
 * Peran : Penumpang
 * FR    : FR-07
 * BR    : BR-06
 */

require_once __DIR__ . '/../../rangka/penjaga.php';
require_once __DIR__ . '/../../rangka/request.php';
require_once __DIR__ . '/../../logika/peta.php';

$request = new Request();
if ($request->method() !== 'POST') {
    header('Allow: POST');
    kirimJson(405, ['berhasil' => false, 'pesan' => 'Gunakan metode POST.']);
}

try {
    wajibLogin('penumpang');
    // Simpan aktivitas sesi dan lepaskan kuncinya sebelum menunggu layanan rute.
    session_write_close();

    $input = $request->all();
    if ($request->files() !== []) {
        throw new InvalidArgumentException('Estimasi rute tidak menerima file.');
    }
    kirimJson(200, ['berhasil' => true, 'data' => hitungEstimasiPerjalanan($input)]);
} catch (FormatRequestTidakDidukung $exception) {
    kirimJson(415, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (InvalidArgumentException | RuteTidakTersedia $exception) {
    kirimJson(422, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (LayananRuteTimeout $exception) {
    kirimJson(504, ['berhasil' => false, 'pesan' => 'Layanan rute terlalu lama merespons. Coba lagi.']);
} catch (LayananRuteGagal $exception) {
    error_log((string) $exception);
    kirimJson(502, ['berhasil' => false, 'pesan' => 'Layanan rute belum dapat digunakan. Coba lagi.']);
} catch (Throwable $exception) {
    error_log((string) $exception);
    kirimJson(500, ['berhasil' => false, 'pesan' => 'Estimasi perjalanan belum dapat diproses.']);
}

