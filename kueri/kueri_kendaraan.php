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
    $db->prepare('INSERT INTO kendaraan (id_driver, merek, model, plat_nomor, warna, foto_url, aktif)
        VALUES (:id, :merek, :model, :plat, :warna, :foto, :aktif)')
        ->execute([
            'id' => $idDriver,
            'merek' => $data['merek'],
            'model' => $data['model'] ?? null,
            'plat' => $data['plat_nomor'],
            'warna' => $data['warna'],
            'foto' => $data['foto_url'] ?? null,
            'aktif' => $data['aktif'] ?? 1,
        ]);
    return (int) $db->lastInsertId();
}

function cariKendaraan(int $id, ?int $idDriver = null, bool $kunci = false): ?array
{
    $sql = 'SELECT id_kendaraan, id_driver, merek, model, plat_nomor, warna,
        foto_url, jumlah_roda, aktif, dibuat_pada FROM kendaraan WHERE id_kendaraan = :id';
    $parameter = ['id' => $id];
    if ($idDriver !== null) {
        $sql .= ' AND id_driver = :driver';
        $parameter['driver'] = $idDriver;
    }
    if ($kunci) {
        $sql .= ' FOR UPDATE';
    }
    $perintah = koneksiDatabase()->prepare($sql);
    $perintah->execute($parameter);
    return $perintah->fetch() ?: null;
}

function daftarKendaraan(int $batas, int $offset, ?int $idDriver = null): array
{
    $sql = 'SELECT id_kendaraan, id_driver, merek, model, plat_nomor, warna,
        foto_url, jumlah_roda, aktif, dibuat_pada FROM kendaraan';
    if ($idDriver !== null) {
        $sql .= ' WHERE id_driver = :driver';
    }
    $perintah = koneksiDatabase()->prepare($sql . ' ORDER BY id_kendaraan DESC LIMIT :batas OFFSET :offset');
    if ($idDriver !== null) {
        $perintah->bindValue(':driver', $idDriver, PDO::PARAM_INT);
    }
    $perintah->bindValue(':batas', $batas, PDO::PARAM_INT);
    $perintah->bindValue(':offset', $offset, PDO::PARAM_INT);
    $perintah->execute();
    return $perintah->fetchAll();
}

function ubahKendaraan(int $id, array $data, ?int $idDriver = null): void
{
    $kolom = array_intersect_key($data, array_flip(['merek', 'model', 'plat_nomor', 'warna', 'foto_url', 'jumlah_roda', 'aktif']));
    if ($kolom === []) {
        throw new InvalidArgumentException('Tidak ada kolom kendaraan yang dapat diubah.');
    }
    $set = implode(', ', array_map(static fn ($nama) => "`{$nama}` = :{$nama}", array_keys($kolom)));
    $sql = "UPDATE kendaraan SET {$set} WHERE id_kendaraan = :id";
    $parameter = [...$kolom, 'id' => $id];
    // Filter pemilik juga diterapkan pada UPDATE, bukan hanya saat membaca data.
    if ($idDriver !== null) {
        $sql .= ' AND id_driver = :driver';
        $parameter['driver'] = $idDriver;
    }
    koneksiDatabase()->prepare($sql)->execute($parameter);
}

function nonaktifkanDataKendaraan(int $id): void
{
    koneksiDatabase()->prepare('UPDATE kendaraan SET aktif = 0 WHERE id_kendaraan = :id')->execute(['id' => $id]);
}

