<?php
/**
 * Perintah SQL (PDO): Saldo dan riwayat transaksi.
 * Modul : Dompet
 * Peran : -
 * FR    : -
 */

require_once __DIR__ . '/../rangka/koneksi.php';

function tambahDompetDriver(int $idDriver, int $saldo): int
{
    $db = koneksiDatabase();
    $db->prepare('INSERT INTO dompet (id_driver, saldo) VALUES (:id, :saldo)')
        ->execute(['id' => $idDriver, 'saldo' => $saldo]);
    return (int) $db->lastInsertId();
}

