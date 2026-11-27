<?php
/** Controller profil sendiri untuk semua peran yang login (FR-04, BR-04). */
require_once __DIR__ . '/../../rangka/penjaga.php';
require_once __DIR__ . '/../../rangka/request.php';
require_once __DIR__ . '/../../logika/profil.php';

$request = new Request();
$metode = $request->method();
if (!in_array($metode, ['GET', 'PUT', 'POST'], true)) {
    header('Allow: GET, PUT, POST');
    kirimJson(405, ['berhasil' => false, 'pesan' => 'Gunakan GET untuk melihat profil atau PUT untuk memperbarui profil.']);
}

try {
    $penggunaLogin = wajibLogin();
    $idLogin = (int) $penggunaLogin['id_pengguna'];
    if ($_GET !== []) {
        throw new InvalidArgumentException('Profil memakai akun pada sesi login. Jangan kirim parameter ID atau query URL.');
    }
    if ($metode === 'GET') {
        kirimJson(200, ['berhasil' => true, 'data' => ambilProfil($idLogin)]);
    }

    wajibTokenCsrf();
    $data = $request->all();
    // Alternatif untuk upload dari form biasa / PHP sebelum versi 8.4.
    if ($metode === 'POST') {
        if (($data['_method'] ?? null) !== 'PUT') {
            throw new InvalidArgumentException('Untuk POST profil, tambahkan _method=PUT. Atau langsung gunakan metode PUT.');
        }
        unset($data['_method']);
    }
    $foto = fotoDariBerkasPengguna($request->files());
    $pengguna = perbaruiProfil($idLogin, $data, $foto);
    kirimJson(200, ['berhasil' => true, 'pesan' => 'Profil berhasil diperbarui.', 'data' => $pengguna]);
} catch (FormatRequestTidakDidukung $exception) {
    kirimJson(415, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (PenggunaTidakDitemukan $exception) {
    kirimJson(404, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (InvalidArgumentException $exception) {
    kirimJson(422, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (PDOException $exception) {
    if (($exception->errorInfo[1] ?? null) === 1062) {
        kirimJson(409, ['berhasil' => false, 'pesan' => 'Nomor HP sudah digunakan.']);
    }
    error_log((string) $exception);
    kirimJson(500, ['berhasil' => false, 'pesan' => 'Profil belum dapat diproses.']);
} catch (Throwable $exception) {
    error_log((string) $exception);
    kirimJson(500, ['berhasil' => false, 'pesan' => 'Profil belum dapat diproses.']);
}
