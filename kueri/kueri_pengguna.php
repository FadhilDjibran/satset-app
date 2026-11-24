<?php
/**
 * Perintah SQL (PDO): Pengguna, profil, dan penangguhan.
 * Modul : Akun, Admin
 * Peran : -
 * FR    : -
 */

require_once __DIR__ . '/../rangka/koneksi.php';

// Kolom password_hash hanya dibaca oleh alur autentikasi, bukan respons profil.
const KOLOM_PENGGUNA_PUBLIK = 'id, nama, email, nomor_hp, peran, status_akun, foto_profil, dibuat_pada, diperbarui_pada';

function cariPengguna(int $id): ?array
{
    $perintah = koneksiDatabase()->prepare('SELECT ' . KOLOM_PENGGUNA_PUBLIK . ' FROM `user` WHERE id = :id');
    $perintah->execute(['id' => $id]);
    return $perintah->fetch() ?: null;
}

function daftarPengguna(int $batas = 20, int $offset = 0): array
{
    if ($batas < 1 || $batas > 100 || $offset < 0) {
        throw new InvalidArgumentException('Batas harus 1-100 dan offset tidak boleh negatif.');
    }

    $perintah = koneksiDatabase()->prepare('SELECT ' . KOLOM_PENGGUNA_PUBLIK . ' FROM `user` ORDER BY id DESC LIMIT :batas OFFSET :offset');
    $perintah->bindValue(':batas', $batas, PDO::PARAM_INT);
    $perintah->bindValue(':offset', $offset, PDO::PARAM_INT);
    $perintah->execute();
    return $perintah->fetchAll();
}

function tambahPengguna(array $data): int
{
    $perintah = koneksiDatabase()->prepare(
        'INSERT INTO `user` (nama, email, nomor_hp, password_hash, peran) VALUES (:nama, :email, :nomor_hp, :password_hash, :peran)'
    );
    $perintah->execute([
        'nama' => $data['nama'],
        'email' => $data['email'],
        'nomor_hp' => $data['nomor_hp'],
        'password_hash' => $data['password_hash'],
        'peran' => $data['peran'],
    ]);
    return (int) koneksiDatabase()->lastInsertId();
}

function ubahPengguna(int $id, array $data): bool
{
    $diizinkan = ['nama', 'email', 'nomor_hp', 'password_hash', 'foto_profil', 'status_akun'];
    $kolom = array_intersect_key($data, array_flip($diizinkan));
    if ($kolom === []) {
        throw new InvalidArgumentException('Tidak ada kolom pengguna yang dapat diubah.');
    }

    $set = implode(', ', array_map(static fn ($nama) => "`{$nama}` = :{$nama}", array_keys($kolom)));
    $perintah = koneksiDatabase()->prepare("UPDATE `user` SET {$set} WHERE id = :id");
    $perintah->execute([...$kolom, 'id' => $id]);
    return $perintah->rowCount() > 0;
}

function hapusPengguna(int $id): bool
{
    $perintah = koneksiDatabase()->prepare('DELETE FROM `user` WHERE id = :id');
    $perintah->execute(['id' => $id]);
    return $perintah->rowCount() > 0;
}

