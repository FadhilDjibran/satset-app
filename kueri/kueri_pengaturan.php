<?php
/** SQL tabel pengaturan. */
require_once __DIR__ . '/../rangka/koneksi.php';

function daftarPengaturan(): array
{
    return koneksiDatabase()->query('SELECT kunci, nilai, keterangan, diperbarui_pada FROM pengaturan ORDER BY kunci ASC')->fetchAll();
}

function cariPengaturan(string $kunci, bool $kunciBaris = false): ?array
{
    $sql = 'SELECT kunci, nilai, keterangan, diperbarui_pada FROM pengaturan WHERE kunci = :kunci';
    $perintah = koneksiDatabase()->prepare($sql . ($kunciBaris ? ' FOR UPDATE' : ''));
    $perintah->execute(['kunci' => $kunci]);
    return $perintah->fetch() ?: null;
}

/** Hanya memperbarui baris yang sudah ada; kunci tidak dapat diubah. */
function ubahPengaturan(string $kunci, array $data): void
{
    $kolom = array_intersect_key($data, array_flip(['nilai', 'keterangan']));
    if ($kolom === []) {
        throw new InvalidArgumentException('Tidak ada kolom pengaturan yang dapat diubah.');
    }
    $set = implode(', ', array_map(static fn ($nama) => "`{$nama}` = :{$nama}", array_keys($kolom)));
    koneksiDatabase()->prepare("UPDATE pengaturan SET {$set} WHERE kunci = :kunci")
        ->execute([...$kolom, 'kunci' => $kunci]);
}

function ambilNilaiPengaturan(string $kunci): ?string
{
    $perintah = koneksiDatabase()->prepare('SELECT nilai FROM pengaturan WHERE kunci = :kunci');
    $perintah->execute(['kunci' => $kunci]);
    $nilai = $perintah->fetchColumn();
    return $nilai === false ? null : (string) $nilai;
}

/** Ambil semua parameter estimasi dalam satu pembacaan. */
function ambilNilaiPengaturanTarif(): array
{
    return koneksiDatabase()->query("SELECT kunci, nilai FROM pengaturan
        WHERE kunci IN ('tarif_dasar', 'tarif_per_km', 'rentang_wajar_persen')")
        ->fetchAll(PDO::FETCH_KEY_PAIR);
}
