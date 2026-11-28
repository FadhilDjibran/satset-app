<?php
/**
 * Saldo, top-up, rincian pendapatan, dan komisi.
 * Modul : Dompet
 * Peran : Driver, Admin
 * FR    : FR-23 s.d. FR-26
 * BR    : BR-20 s.d. BR-25
 */

require_once __DIR__ . '/../kueri/kueri_dompet.php';
require_once __DIR__ . '/../kueri/kueri_transaksi.php';
require_once __DIR__ . '/../kueri/kueri_pengaturan.php';

/** Transaksi database dikelola service pembuatan akun, bukan service ini. */
function buatDompetAwalDriver(int $idDriver): void
{
    $saldo = filter_var(ambilNilaiPengaturan('saldo_awal_driver'), FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);
    if ($saldo === false) {
        throw new RuntimeException('Pengaturan saldo awal driver belum valid.');
    }
    $idDompet = tambahDompetDriver($idDriver, $saldo);
    if ($saldo > 0) {
        catatSaldoAwalDompet($idDompet, $saldo);
    }
}

