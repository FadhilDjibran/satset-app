<?php
/** SQL tabel pengaturan. */
require_once __DIR__ . '/../rangka/koneksi.php';

function ambilNilaiPengaturan(string $kunci): ?string
{
    $perintah = koneksiDatabase()->prepare('SELECT nilai FROM pengaturan WHERE kunci = :kunci');
    $perintah->execute(['kunci' => $kunci]);
    $nilai = $perintah->fetchColumn();
    return $nilai === false ? null : (string) $nilai;
}
