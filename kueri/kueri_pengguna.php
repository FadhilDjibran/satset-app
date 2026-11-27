<?php
/**
 * Perintah SQL (PDO): Pengguna, profil, dan penangguhan.
 * Modul : Akun, Admin
 * Peran : -
 * FR    : -
 */

require_once __DIR__ . '/../rangka/koneksi.php';

// Hash kata sandi hanya dibaca oleh alur autentikasi, bukan respons profil.
const KOLOM_PENGGUNA_PUBLIK = 'id_pengguna, nama, email, nomor_hp, peran, status_akun, foto_url, dibuat_pada, diperbarui_pada';

/** Hanya dipakai logika login; jangan kembalikan password_hash sebagai JSON. */
function cariPenggunaUntukLogin(string $email): ?array
{
    $perintah = koneksiDatabase()->prepare('SELECT id_pengguna, kata_sandi_hash, status_akun FROM pengguna WHERE email = :email');
    $perintah->execute(['email' => $email]);
    return $perintah->fetch() ?: null;
}

function cariPengguna(int $id): ?array
{
    $perintah = koneksiDatabase()->prepare('SELECT ' . KOLOM_PENGGUNA_PUBLIK . ' FROM pengguna WHERE id_pengguna = :id');
    $perintah->execute(['id' => $id]);
    return $perintah->fetch() ?: null;
}

function daftarPengguna(int $batas = 20, int $offset = 0): array
{
    if ($batas < 1 || $batas > 100 || $offset < 0) {
        throw new InvalidArgumentException('Batas harus 1-100 dan offset tidak boleh negatif.');
    }

    $perintah = koneksiDatabase()->prepare('SELECT ' . KOLOM_PENGGUNA_PUBLIK . ' FROM pengguna ORDER BY id_pengguna DESC LIMIT :batas OFFSET :offset');
    $perintah->bindValue(':batas', $batas, PDO::PARAM_INT);
    $perintah->bindValue(':offset', $offset, PDO::PARAM_INT);
    $perintah->execute();
    return $perintah->fetchAll();
}

function tambahPengguna(array $data): int
{
    $perintah = koneksiDatabase()->prepare(
        'INSERT INTO pengguna (nama, email, nomor_hp, kata_sandi_hash, peran, foto_url, status_akun) VALUES (:nama, :email, :nomor_hp, :kata_sandi_hash, :peran, :foto_url, :status_akun)'
    );
    $perintah->execute([
        'nama' => $data['nama'],
        'email' => $data['email'],
        'nomor_hp' => $data['nomor_hp'],
        'kata_sandi_hash' => $data['kata_sandi_hash'],
        'peran' => $data['peran'],
        'foto_url' => $data['foto_url'] ?? null,
        'status_akun' => $data['status_akun'] ?? 'aktif',
    ]);
    return (int) koneksiDatabase()->lastInsertId();
}

function ubahPengguna(int $id, array $data): bool
{
    $diizinkan = ['nama', 'email', 'nomor_hp', 'kata_sandi_hash', 'foto_url', 'status_akun'];
    $kolom = array_intersect_key($data, array_flip($diizinkan));
    if ($kolom === []) {
        throw new InvalidArgumentException('Tidak ada kolom pengguna yang dapat diubah.');
    }

    $set = implode(', ', array_map(static fn ($nama) => "`{$nama}` = :{$nama}", array_keys($kolom)));
    $perintah = koneksiDatabase()->prepare("UPDATE pengguna SET {$set} WHERE id_pengguna = :id");
    $perintah->execute([...$kolom, 'id' => $id]);
    return $perintah->rowCount() > 0;
}

function hapusPengguna(int $id): bool
{
    // Tidak menghapus riwayat/FK. DELETE API bermakna penonaktifan akun.
    $perintah = koneksiDatabase()->prepare("UPDATE pengguna SET status_akun = 'nonaktif' WHERE id_pengguna = :id");
    $perintah->execute(['id' => $id]);
    return cariPengguna($id) !== null;
}

/** Dipanggil di dalam transaksi pembuatan akun driver. */
function buatDataAwalDriver(int $id, int $saldo): void
{
    $db = koneksiDatabase();
    $db->prepare('INSERT INTO driver (id_driver) VALUES (?)')->execute([$id]);
    $db->prepare('INSERT INTO dompet (id_driver, saldo) VALUES (?, ?)')->execute([$id, $saldo]);
    $idDompet = (int) $db->lastInsertId();
    if ($saldo > 0) {
        $db->prepare("INSERT INTO transaksi (id_dompet, jenis, nominal, saldo_sebelum, saldo_sesudah, keterangan) VALUES (?, 'topup', ?, 0, ?, 'Saldo awal driver (simulasi)')")
            ->execute([$idDompet, $saldo, $saldo]);
    }
}

function bacaSaldoAwalDriver(): ?string
{
    $nilai = koneksiDatabase()->query("SELECT nilai FROM pengaturan WHERE kunci = 'saldo_awal_driver'")->fetchColumn();
    return $nilai === false ? null : (string) $nilai;
}

