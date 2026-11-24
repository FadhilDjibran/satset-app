<?php
/**
 * Pemuat otomatis dan pembaca .env.
 * Modul : Rangka
 * Peran : -
 * FR    : -
 */

function konfigurasiEnv(string $nama): ?string
{
    static $nilai = null;

    if ($nilai === null) {
        $nilai = [];
        $berkas = dirname(__DIR__) . '/.env';
        if (is_file($berkas)) {
            foreach (file($berkas, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $baris) {
                $baris = trim($baris);
                if ($baris === '' || str_starts_with($baris, '#') || !str_contains($baris, '=')) {
                    continue;
                }
                [$kunci, $isi] = explode('=', $baris, 2);
                $nilai[trim($kunci)] = trim(trim($isi), "\"'");
            }
        }
    }

    $dariSistem = getenv($nama);
    if ($dariSistem !== false) {
        return $dariSistem;
    }

    return $nilai[$nama] ?? null;
}

