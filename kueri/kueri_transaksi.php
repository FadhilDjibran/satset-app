<?php
/** SQL tabel transaksi. */
require_once __DIR__ . '/../rangka/koneksi.php';

function catatSaldoAwalDompet(int $idDompet, int $saldo): void
{
    koneksiDatabase()->prepare("INSERT INTO transaksi (id_dompet, jenis, nominal, saldo_sebelum, saldo_sesudah, keterangan)
        VALUES (:id, 'topup', :nominal, 0, :saldo, 'Saldo awal driver (simulasi)')")
        ->execute(['id' => $idDompet, 'nominal' => $saldo, 'saldo' => $saldo]);
}
