<?php
/** POST dengan cookie sesi dan token CSRF -> akhiri sesi (FR-02). */
require_once __DIR__ . '/../../rangka/penjaga.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    kirimJson(405, ['berhasil' => false, 'pesan' => 'Gunakan metode POST.']);
}
try {
    wajibLogin();
    wajibTokenCsrf();
    hapusSesi();
    kirimJson(200, ['berhasil' => true, 'pesan' => 'Logout berhasil.']);
} catch (Throwable $exception) {
    error_log((string) $exception);
    kirimJson(500, ['berhasil' => false, 'pesan' => 'Terjadi kesalahan server.']);
}
