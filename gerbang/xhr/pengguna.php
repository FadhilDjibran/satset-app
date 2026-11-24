<?php
/** Contoh endpoint CRUD pengguna untuk pengujian lokal via Postman. */

require_once __DIR__ . '/../../rangka/muat.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function kirimJson(int $status, array $isi): never
{
    http_response_code($status);
    echo json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// Belum ada autentikasi; batasi endpoint demo ke komputer sendiri.
if (konfigurasiEnv('APP_ENV') !== 'lokal' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    kirimJson(403, ['berhasil' => false, 'pesan' => 'Endpoint contoh hanya tersedia dari localhost pada APP_ENV=lokal.']);
}

require_once __DIR__ . '/../../logika/pengguna.php';

$metode = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($metode, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    header('Allow: GET, POST, PUT, DELETE');
    kirimJson(405, ['berhasil' => false, 'pesan' => 'Metode HTTP tidak didukung.']);
}

try {
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
            $pengguna = cariPengguna($id);
            $pengguna === null
                ? kirimJson(404, ['berhasil' => false, 'pesan' => 'Pengguna tidak ditemukan.'])
                : kirimJson(200, ['berhasil' => true, 'data' => $pengguna]);
        }
        if (!is_scalar($_GET['batas'] ?? 20) || !is_scalar($_GET['offset'] ?? 0)) {
            throw new InvalidArgumentException('batas dan offset harus bilangan bulat.');
        }
        $batas = filter_var($_GET['batas'] ?? 20, FILTER_VALIDATE_INT);
        $offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT);
        if ($batas === false || $offset === false) {
            throw new InvalidArgumentException('batas dan offset harus bilangan bulat.');
        }
        kirimJson(200, ['berhasil' => true, 'data' => daftarPengguna($batas, $offset)]);
    }

    if (($metode === 'PUT' || $metode === 'DELETE') && $id === null) {
        throw new InvalidArgumentException('id wajib diisi untuk PUT dan DELETE.');
    }
    if ($metode === 'POST' && $id !== null) {
        throw new InvalidArgumentException('POST tidak menerima id pada URL.');
    }

    if ($metode === 'DELETE') {
        if (!hapusPengguna($id)) {
            kirimJson(404, ['berhasil' => false, 'pesan' => 'Pengguna tidak ditemukan.']);
        }
        kirimJson(200, ['berhasil' => true, 'pesan' => 'Pengguna dihapus.']);
    }

    if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
        kirimJson(415, ['berhasil' => false, 'pesan' => 'Gunakan Content-Type: application/json.']);
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || array_is_list($data)) {
        throw new InvalidArgumentException('Body harus objek JSON yang valid.');
    }
    $data = validasiDataPengguna($data, $metode === 'POST');

    if ($metode === 'POST') {
        $idBaru = tambahPengguna($data);
        kirimJson(201, ['berhasil' => true, 'data' => cariPengguna($idBaru)]);
    }
    if (cariPengguna($id) === null) {
        kirimJson(404, ['berhasil' => false, 'pesan' => 'Pengguna tidak ditemukan.']);
    }
    ubahPengguna($id, $data);
    kirimJson(200, ['berhasil' => true, 'data' => cariPengguna($id)]);
} catch (InvalidArgumentException $exception) {
    kirimJson(422, ['berhasil' => false, 'pesan' => $exception->getMessage()]);
} catch (PDOException $exception) {
    $status = $exception->getCode() === '23000' ? 409 : 500;
    kirimJson($status, ['berhasil' => false, 'pesan' => $status === 409 ? 'Email atau nomor HP sudah digunakan.' : 'Terjadi kesalahan database.']);
} catch (Throwable $exception) {
    kirimJson(500, ['berhasil' => false, 'pesan' => 'Terjadi kesalahan server.']);
}
