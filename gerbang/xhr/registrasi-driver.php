<?php
/** Registrasi driver untuk guest (FR-01, BR-01, BR-02, BR-21). */
require_once __DIR__ . '/../../rangka/json.php';
require_once __DIR__ . '/../../rangka/request.php';
require_once __DIR__ . '/../../logika/akun.php';

$request = new Request();
if ($request->method() !== 'POST') {
    header('Allow: POST');
    kirimJson(405, ['berhasil' => false, 'pesan' => 'Gunakan metode POST.']);
}

try {
    $berkas = $request->files();
    if (array_diff(array_keys($berkas), ['foto_url', 'kendaraan']) !== []) {
        throw new InvalidArgumentException('Field file harus foto_url atau kendaraan[foto_url].');
    }
    $foto = fotoDariBerkasPengguna(array_intersect_key($berkas, ['foto_url' => true]));
    $fotoKendaraan = isset($berkas['kendaraan']) ? fotoDariBerkasKendaraan($berkas['kendaraan']) : null;
    $pengguna = registrasiDriver($request->all(), $foto, $fotoKendaraan);
    kirimJson(201, ['berhasil' => true, 'pesan' => 'Registrasi driver berhasil. Silakan login.', 'data' => $pengguna]);
} catch (FormatRequestTidakDidukung $exception) {
    kirimJson(415, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (InvalidArgumentException $exception) {
    kirimJson(422, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (PDOException $exception) {
    if (($exception->errorInfo[1] ?? null) === 1062) {
        kirimJson(409, ['berhasil' => false, 'pesan' => 'Email, nomor HP, atau plat nomor sudah digunakan.']);
    }
    error_log((string) $exception);
    kirimJson(500, ['berhasil' => false, 'pesan' => 'Registrasi belum dapat diproses.']);
} catch (Throwable $exception) {
    error_log((string) $exception);
    kirimJson(500, ['berhasil' => false, 'pesan' => 'Registrasi belum dapat diproses.']);
}
