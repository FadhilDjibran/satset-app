<?php
/** Controller daftar/detail kendaraan khusus admin; akses hanya baca. */
require_once __DIR__ . '/../../rangka/penjaga.php';
require_once __DIR__ . '/../../rangka/request.php';
require_once __DIR__ . '/../../logika/kendaraan.php';

$request = new Request();
$metode = $request->method();
if ($metode !== 'GET') {
    header('Allow: GET');
    kirimJson(405, ['berhasil' => false, 'pesan' => 'Admin hanya dapat melihat data kendaraan. Gunakan metode GET.']);
}

try {
    wajibLogin('admin');
    $parameter = ['id_kendaraan' => null, 'id_driver' => null, 'batas' => 20, 'offset' => 0];
    if (array_diff(array_keys($_GET), array_keys($parameter)) !== []) {
        throw new InvalidArgumentException('Parameter hanya id_kendaraan, id_driver, batas, atau offset.');
    }
    foreach ($parameter as $nama => $default) {
        $nilai = $request->query($nama, $default);
        if ($nilai === null) {
            continue;
        }
        if ((!is_int($nilai) && !is_string($nilai))
            || filter_var($nilai, FILTER_VALIDATE_INT, ['options' => ['min_range' => $nama === 'offset' ? 0 : 1]]) === false) {
            throw new InvalidArgumentException("Parameter {$nama} tidak valid.");
        }
        $parameter[$nama] = (int) $nilai;
    }
    $idKendaraan = $parameter['id_kendaraan'];
    $data = $idKendaraan === null
        ? ambilDaftarKendaraan($parameter['batas'], $parameter['offset'], $parameter['id_driver'])
        : ambilDetailKendaraan($idKendaraan, $parameter['id_driver']);
    kirimJson(200, ['berhasil' => true, 'data' => $data]);
} catch (KendaraanTidakDitemukan $exception) {
    kirimJson(404, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (InvalidArgumentException $exception) {
    kirimJson(422, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log((string) $exception);
    kirimJson(500, ['berhasil' => false, 'pesan' => 'Data kendaraan belum dapat diproses.']);
}
