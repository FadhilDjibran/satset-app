# Skema dan data awal SATSET

- `migration.sql`: skema lengkap dari `skema_SATSET.sql` (12 tabel dan 2 view).
- `seeders/data_awal.sql`: data awal dari `data_awal_SATSET.sql`, menggantikan seeder satu tabel sebelumnya.

## Urutan impor

1. Jalankan MySQL/MariaDB.
2. Untuk instalasi baru, impor `migration.sql` melalui phpMyAdmin > Import.
3. Setelah skema berhasil dibuat, impor `seeders/data_awal.sql` satu kali.

Skema ditujukan untuk MySQL 8.0.16+ atau MariaDB 10.4+, mengikuti keterangan pada berkas sumber. Skema menggunakan foreign key, CHECK, dan generated column.

Perintah `DROP DATABASE` dari sumber dihilangkan. Migrasi ini membuat struktur awal, bukan mengonversi tabel `user` lama menjadi `pengguna`. Jika database sudah berisi data/skema lama, cadangkan dahulu dan rencanakan pemindahan datanya sebelum menggunakan skema baru. Impor ulang pada tabel yang sudah ada atau seed yang sudah terisi akan menghasilkan konflik.

## Akun demo

Password semua akun pada data awal: `satset123`.

| Email | Peran |
|---|---|
| admin@satset.test | admin |
| rina@satset.test | penumpang |
| dewi@satset.test | penumpang |
| budi@satset.test | driver |
| andi@satset.test | driver |

## Dampak pada backend

Login, logout, pembacaan profil sesi, serta CRUD pengguna sudah memakai skema baru. Respons JSON memakai `id_pengguna` dan `foto_url`; hash hanya dibaca internal untuk autentikasi. Pemetaan kolom dari versi lama:

| Lama | Baru |
|---|---|
| user | pengguna |
| id | id_pengguna |
| password_hash | kata_sandi_hash |
| foto_profil | foto_url |

Email dibatasi 100 karakter. Status akun memiliki nilai tambahan `nonaktif`. Pembuatan driver melalui `buatPengguna()` menyimpan data terkait pada tabel `driver`, `dompet`, dan transaksi saldo awal secara atomik. DELETE endpoint menonaktifkan akun, mengikuti rancangan skema yang memakai foreign key `ON DELETE RESTRICT`.

Registrasi publik driver kini wajib menyertakan data `kendaraan`. Akun, kendaraan, driver, dompet, serta catatan saldo awal disimpan dalam satu transaksi. Driver memakai default offline/posisi kosong/Mode Searah nonaktif; kendaraan baru aktif dengan jumlah roda 4. Saldo awal mengikuti `pengaturan.saldo_awal_driver`. SQL pengguna, driver, kendaraan, dompet, transaksi, dan pengaturan berada pada file `kueri/kueri_<tabel>.php` masing-masing, dengan koordinasi di service `logika/`.
