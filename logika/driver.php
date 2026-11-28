<?php
/** Default driver dan dompet baru; dipanggil dalam transaksi pembuatan akun. */
require_once __DIR__ . '/../kueri/kueri_driver.php';
require_once __DIR__ . '/dompet.php';

function siapkanDriverBaru(int $idPengguna): void
{
    tambahDriver($idPengguna);
    buatDompetAwalDriver($idPengguna);
}
