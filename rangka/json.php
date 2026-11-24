<?php
/** Respons dan pembacaan request untuk endpoint JSON. */

function kirimJson(int $status, array $isi): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function bacaJson(): array
{
    $tipe = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($tipe !== 'application/json') {
        kirimJson(415, ['berhasil' => false, 'pesan' => 'Gunakan Content-Type: application/json.']);
    }
    $objek = json_decode(file_get_contents('php://input'));
    if (json_last_error() !== JSON_ERROR_NONE || !$objek instanceof stdClass) {
        throw new InvalidArgumentException('Body harus objek JSON yang valid.');
    }
    return (array) $objek;
}
