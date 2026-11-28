<?php
/**
 * Perintah SQL (PDO): Data kendaraan.
 * Modul : Akun
 * Peran : -
 * FR    : -
 */

require_once __DIR__ . '/../rangka/koneksi.php';

function tambahKendaraan(int $idDriver, array $data): int
{
    $db = koneksiDatabase();
    $db->prepare('INSERT INTO kendaraan (id_driver, merek, model, plat_nomor, warna, foto_url)
        VALUES (:id, :merek, :model, :plat, :warna, :foto)')
        ->execute([
            'id' => $idDriver,
            'merek' => $data['merek'],
            'model' => $data['model'] ?? null,
            'plat' => $data['plat_nomor'],
            'warna' => $data['warna'],
            'foto' => $data['foto_url'] ?? null,
        ]);
    return (int) $db->lastInsertId();
}

function cariKendaraan(int $id): ?array
{
    $perintah = koneksiDatabase()->prepare('SELECT id_kendaraan, id_driver, merek, model, plat_nomor, warna,
        foto_url, jumlah_roda, aktif, dibuat_pada FROM kendaraan WHERE id_kendaraan = :id');
    $perintah->execute(['id' => $id]);
    return $perintah->fetch() ?: null;
}

