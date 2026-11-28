<?php
/** Controller daftar/detail/ubah pengaturan; seluruh aksi khusus admin. */
require_once __DIR__ . '/../../rangka/penjaga.php';
require_once __DIR__ . '/../../rangka/request.php';
require_once __DIR__ . '/../../logika/pengaturan.php';

$request = new Request();
$metode = $request->method();
if (!in_array($metode, ['GET', 'PUT'], true)) {
    header('Allow: GET, PUT');
    kirimJson(405, ['berhasil' => false, 'pesan' => 'Gunakan GET untuk daftar/detail atau PUT untuk mengubah pengaturan.']);
}

try {
    wajibLogin('admin');
    if ($metode === 'PUT') {
        wajibTokenCsrf();
    }
    if (array_diff(array_keys($_GET), ['kunci']) !== []) {
        throw new InvalidArgumentException('Parameter URL hanya boleh kunci.');
    }
    $kunci = $request->query('kunci');
    if ($kunci !== null) {
        if (!is_string($kunci)) {
            throw new InvalidArgumentException('kunci harus berupa teks.');
        }
        $kunci = validasiKunciPengaturan($kunci);
    }
    if ($metode === 'GET') {
        $data = $kunci === null ? ambilDaftarPengaturan() : ambilDetailPengaturan($kunci);
        kirimJson(200, ['berhasil' => true, 'data' => $data]);
    }
    if ($kunci === null) {
        throw new InvalidArgumentException('kunci wajib diisi pada URL untuk mengubah pengaturan.');
    }
    $data = $request->all();
    if ($request->files() !== []) {
        throw new InvalidArgumentException('Pengaturan tidak menerima file.');
    }
    kirimJson(200, ['berhasil' => true, 'pesan' => 'Pengaturan berhasil diperbarui.', 'data' => perbaruiPengaturan($kunci, $data)]);
} catch (FormatRequestTidakDidukung $exception) {
    kirimJson(415, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (PengaturanTidakDitemukan $exception) {
    kirimJson(404, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (InvalidArgumentException $exception) {
    kirimJson(422, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log((string) $exception);
    kirimJson(500, ['berhasil' => false, 'pesan' => 'Pengaturan belum dapat diproses.']);
}
