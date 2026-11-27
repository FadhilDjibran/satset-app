<?php
/** Controller HTTP CRUD pengguna: baca request -> service logika -> respons JSON. */

require_once __DIR__ . '/../../rangka/penjaga.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../logika/pengguna.php';

$metode = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($metode, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    header('Allow: GET, POST, PUT, DELETE');
    kirimJson(405, ['berhasil' => false, 'pesan' => 'Metode HTTP tidak didukung.']);
}

try {
    wajibLogin('admin');
    if ($metode !== 'GET') {
        wajibTokenCsrf();
    }
    $multipart = str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data;');
    // PHP memproses file multipart pada POST. _method=PUT dipakai untuk update foto.
    if ($metode === 'POST' && $multipart && isset($_POST['_method'])) {
        if ($_POST['_method'] !== 'PUT') {
            throw new InvalidArgumentException('_method hanya boleh PUT.');
        }
        $metode = 'PUT';
    }
    $id = null;
    if (isset($_GET['id'])) {
        if (!is_string($_GET['id'])) {
            throw new InvalidArgumentException('id harus bilangan bulat positif.');
        }
        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException('id harus bilangan bulat positif.');
        }
    }

    if ($metode === 'GET') {
        if ($id !== null) {
            kirimJson(200, ['berhasil' => true, 'data' => ambilDetailPengguna($id)]);
        }
        if (!is_scalar($_GET['batas'] ?? 20) || !is_scalar($_GET['offset'] ?? 0)) {
            throw new InvalidArgumentException('batas dan offset harus bilangan bulat.');
        }
        $batas = filter_var($_GET['batas'] ?? 20, FILTER_VALIDATE_INT);
        $offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT);
        if ($batas === false || $offset === false) {
            throw new InvalidArgumentException('batas dan offset harus bilangan bulat.');
        }
        kirimJson(200, ['berhasil' => true, 'data' => ambilDaftarPengguna($batas, $offset)]);
    }

    if (($metode === 'PUT' || $metode === 'DELETE') && $id === null) {
        throw new InvalidArgumentException('id wajib diisi untuk PUT dan DELETE.');
    }
    if ($metode === 'POST' && $id !== null) {
        throw new InvalidArgumentException('POST tidak menerima id pada URL.');
    }

    if ($metode === 'DELETE') {
        nonaktifkanPengguna($id);
        kirimJson(200, ['berhasil' => true, 'pesan' => 'Pengguna dinonaktifkan.']);
    }

    $foto = null;
    if ($multipart) {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'PUT') {
            if (!function_exists('request_parse_body')) {
                kirimJson(415, ['berhasil' => false, 'pesan' => 'PUT form-data memerlukan PHP 8.4+. Gunakan POST form-data dengan _method=PUT.']);
            }
            try {
                [$data, $berkas] = request_parse_body();
            } catch (RequestParseBodyException $exception) {
                throw new InvalidArgumentException('Form-data tidak valid atau melebihi batas upload PHP.');
            }
        } else {
            $data = $_POST;
            $berkas = $_FILES;
        }
        unset($data['_method']);
        if (array_diff(array_keys($berkas), ['foto_url']) !== []) {
            throw new InvalidArgumentException('Nama field file harus foto_url.');
        }
        if (isset($berkas['foto_url']) && ($berkas['foto_url']['error'] ?? null) !== UPLOAD_ERR_NO_FILE) {
            $foto = $berkas['foto_url'];
        }
    } else {
        $data = bacaJson();
    }
    if ($metode === 'POST') {
        kirimJson(201, ['berhasil' => true, 'data' => buatPengguna($data, $foto)]);
    }
    kirimJson(200, ['berhasil' => true, 'data' => perbaruiPengguna($id, $data, $foto)]);
} catch (PenggunaTidakDitemukan $exception) {
    kirimJson(404, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (InvalidArgumentException $exception) {
    kirimJson(422, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (PDOException $exception) {
    $status = $exception->getCode() === '23000' ? 409 : 500;
    kirimJson($status, ['berhasil' => false, 'pesan' => $status === 409 ? 'Email atau nomor HP sudah digunakan.' : 'Terjadi kesalahan database.']);
} catch (Throwable $exception) {
    kirimJson(500, ['berhasil' => false, 'pesan' => 'Terjadi kesalahan server.']);
}
