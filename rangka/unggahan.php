<?php
/** Penyimpanan foto profil; hanya menerima berkas upload HTTP berupa gambar. */
function simpanFotoProfil(array $foto): string
{
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
    $lokasi = dirname(__DIR__) . '/gerbang/unggahan/profil/' . $nama;
    if (!move_uploaded_file($tmp, $lokasi)) {
        throw new RuntimeException('Foto tidak dapat disimpan.');
    }
    // Relatif terhadap gerbang/, tidak bergantung nama folder instalasi.
    return 'unggahan/profil/' . $nama;
}

/** Hanya untuk membersihkan file baru jika transaksi database gagal. */
function batalkanFotoProfil(string $url): void
{
    if (preg_match('~^unggahan/profil/[a-f0-9]{32}\.(jpg|png|webp)$~D', $url)) {
        unlink(dirname(__DIR__) . '/gerbang/' . $url);
    }
}
