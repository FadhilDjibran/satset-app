<?php
/** Controller lihat/ubah kendaraan sendiri; ID driver selalu dari sesi. */
require_once __DIR__ . '/../../rangka/penjaga.php';
require_once __DIR__ . '/../../rangka/request.php';
require_once __DIR__ . '/../../logika/kendaraan.php';

$request = new Request();
$metode = $request->method();
if (!in_array($metode, ['GET', 'PUT', 'POST'], true)) {
    header('Allow: GET, PUT, POST');
    kirimJson(405, ['berhasil' => false, 'pesan' => 'Driver hanya dapat melihat atau memperbarui kendaraan sendiri.']);
}

try {
    $penggunaLogin = wajibLogin('driver');
    $idDriver = (int) $penggunaLogin['id_pengguna'];
    if ($metode !== 'GET') {
        wajibTokenCsrf();
    }
    if (array_diff(array_keys($_GET), ['id_kendaraan']) !== []) {
        throw new InvalidArgumentException('Parameter hanya id_kendaraan. Pemilik kendaraan ditentukan dari sesi login.');
    }
    $idKendaraan = $request->query('id_kendaraan');
    if (!is_string($idKendaraan)
        || filter_var($idKendaraan, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        throw new InvalidArgumentException('id_kendaraan wajib berupa bilangan bulat positif untuk melihat atau memperbarui kendaraan.');
    }
    $idKendaraan = (int) $idKendaraan;
    if ($metode === 'GET') {
        $data = ambilDetailKendaraanDriver($idDriver, $idKendaraan);
        kirimJson(200, ['berhasil' => true, 'data' => $data]);
    }
    $data = $request->all();
    if ($metode === 'POST') {
        if (($data['_method'] ?? null) !== 'PUT') {
            throw new InvalidArgumentException('Driver tidak dapat create kendaraan. Untuk update melalui POST, tambahkan _method=PUT.');
        }
        unset($data['_method']);
    }
    $foto = fotoDariFormKendaraan($request->files());
    $kendaraan = perbaruiKendaraanDriver($idDriver, $idKendaraan, $data, $foto);
    kirimJson(200, ['berhasil' => true, 'pesan' => 'Kendaraan berhasil diperbarui.', 'data' => $kendaraan]);
} catch (FormatRequestTidakDidukung $exception) {
    kirimJson(415, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (KendaraanTidakDitemukan $exception) {
    kirimJson(404, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (InvalidArgumentException $exception) {
    kirimJson(422, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (PDOException $exception) {
    if (($exception->errorInfo[1] ?? null) === 1062) {
        kirimJson(409, ['berhasil' => false, 'pesan' => 'Plat nomor sudah digunakan.']);
    }
    error_log((string) $exception);
    kirimJson(500, ['berhasil' => false, 'pesan' => 'Data kendaraan belum dapat diproses.']);
} catch (Throwable $exception) {
    error_log((string) $exception);
    kirimJson(500, ['berhasil' => false, 'pesan' => 'Data kendaraan belum dapat diproses.']);
}
