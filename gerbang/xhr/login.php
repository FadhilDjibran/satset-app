<?php
/** POST email/password (JSON atau form) -> sesi pengguna (FR-02, BR-04). */
require_once __DIR__ . '/../../rangka/json.php';
require_once __DIR__ . '/../../rangka/request.php';
require_once __DIR__ . '/../../logika/akun.php';

$request = new Request();
if ($request->method() !== 'POST') {
    header('Allow: POST');
    kirimJson(405, ['berhasil' => false, 'pesan' => 'Gunakan metode POST.']);
}
try {
    $hasil = loginPengguna($request->all());
    if ($hasil === null) {
        kirimJson(401, ['berhasil' => false, 'pesan' => 'Email/password salah atau akun tidak aktif.']);
    }
    kirimJson(200, ['berhasil' => true, 'data' => $hasil]);
} catch (FormatRequestTidakDidukung $exception) {
    kirimJson(415, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (InvalidArgumentException $exception) {
    kirimJson(422, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log((string) $exception);
    kirimJson(500, ['berhasil' => false, 'pesan' => 'Terjadi kesalahan server.']);
}
