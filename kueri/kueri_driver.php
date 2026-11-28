<?php
/** SQL tabel driver. Status/posisi/mode memakai nilai default skema. */
require_once __DIR__ . '/../rangka/koneksi.php';

function tambahDriver(int $idPengguna): void
{
    koneksiDatabase()->prepare('INSERT INTO driver (id_driver) VALUES (:id)')
        ->execute(['id' => $idPengguna]);
}

function cariDriver(int $id, bool $kunci = false): ?array
{
    $perintah = koneksiDatabase()->prepare('SELECT * FROM driver WHERE id_driver = :id' . ($kunci ? ' FOR UPDATE' : ''));
    $perintah->execute(['id' => $id]);
    return $perintah->fetch() ?: null;
}
