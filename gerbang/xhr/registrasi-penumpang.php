<?php
/** Registrasi penumpang untuk guest (FR-01, BR-01, BR-02). */
require_once __DIR__ . '/../../rangka/json.php';
require_once __DIR__ . '/../../rangka/request.php';
require_once __DIR__ . '/../../logika/akun.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    kirimJson(405, ['berhasil' => false, 'pesan' => 'Gunakan metode POST.']);
}

try {
    $request = new Request();
    $foto = fotoDariBerkasPengguna($request->files());
    $pengguna = registrasiPenumpang($request->all(), $foto);
    kirimJson(201, ['berhasil' => true, 'pesan' => 'Registrasi penumpang berhasil. Silakan login.', 'data' => $pengguna]);
} catch (FormatRequestTidakDidukung $exception) {
    kirimJson(415, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (InvalidArgumentException $exception) {
    kirimJson(422, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (PDOException $exception) {
    if (($exception->errorInfo[1] ?? null) === 1062) {
        kirimJson(409, ['berhasil' => false, 'pesan' => 'Email atau nomor HP sudah digunakan.']);
    }
    error_log((string) $exception);
    kirimJson(500, ['berhasil' => false, 'pesan' => 'Registrasi belum dapat diproses.']);
} catch (Throwable $exception) {
    error_log((string) $exception);
    kirimJson(500, ['berhasil' => false, 'pesan' => 'Registrasi belum dapat diproses.']);
}
