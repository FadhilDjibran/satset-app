<?php
/** Service profil sendiri (FR-04): nama, nomor HP, dan foto. */
require_once __DIR__ . '/pengguna.php';

/** ID harus berasal dari pengguna yang sudah diverifikasi oleh middleware. */
function ambilProfil(int $idLogin): array
{
    return ambilDetailPengguna($idLogin);
}

function perbaruiProfil(int $idLogin, array $input, ?array $foto = null): array
{
    foreach ($input as $kolom => $_) {
        if (!in_array($kolom, ['nama', 'nomor_hp', 'foto_url'], true)) {
            throw new InvalidArgumentException("Kolom {$kolom} tidak dapat diubah melalui profil. Gunakan nama, nomor_hp, atau foto_url.");
        }
    }
    // Memakai validasi, transaksi, kueri, dan pembersihan file yang sama dengan CRUD.
    return perbaruiPengguna($idLogin, $input, $foto);
}
