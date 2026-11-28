<?php
/** Penyimpanan gambar profil/kendaraan; hanya menerima berkas upload HTTP. */
function simpanFotoProfil(array $foto): string
{
    return simpanGambarUpload($foto, 'profil');
}

function simpanFotoKendaraan(array $foto): string
{
    return simpanGambarUpload($foto, 'kendaraan');
}

function simpanGambarUpload(array $foto, string $folder): string
{
    if (!in_array($folder, ['profil', 'kendaraan'], true)) {
        throw new InvalidArgumentException('Folder unggahan tidak diizinkan.');
    }
    if (($foto['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($foto['tmp_name'] ?? null)) {
        throw new InvalidArgumentException('Upload foto gagal. Periksa file dan batas upload PHP.');
    }
    $tmp = $foto['tmp_name'];
    if (!is_uploaded_file($tmp) || filesize($tmp) > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('Foto maksimal 2 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $ekstensi = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($ekstensi[$mime]) || @getimagesize($tmp) === false) {
        throw new InvalidArgumentException('Foto harus gambar JPG, PNG, atau WebP yang valid.');
    }
    $nama = bin2hex(random_bytes(16)) . '.' . $ekstensi[$mime];
    $url = 'unggahan/' . $folder . '/' . $nama;
    $lokasi = dirname(__DIR__) . '/gerbang/' . $url;
    if (!move_uploaded_file($tmp, $lokasi)) {
        throw new RuntimeException('Foto tidak dapat disimpan.');
    }
    // Relatif terhadap gerbang/, tidak bergantung nama folder instalasi.
    return $url;
}

/** Hanya untuk membersihkan file baru jika transaksi database gagal. */
function batalkanFotoProfil(string $url): void
{
    batalkanGambarUpload($url);
}

function batalkanFotoKendaraan(string $url): void
{
    batalkanGambarUpload($url);
}

function batalkanGambarUpload(string $url): void
{
    if (preg_match('~^unggahan/(profil|kendaraan)/[a-f0-9]{32}\.(jpg|png|webp)$~D', $url)
        && is_file(dirname(__DIR__) . '/gerbang/' . $url)) {
        unlink(dirname(__DIR__) . '/gerbang/' . $url);
    }
}
