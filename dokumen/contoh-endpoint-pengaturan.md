# Pengaturan sistem di Postman

Endpoint: `http://localhost/satset/gerbang/xhr/pengaturan.php`.

Seluruh aksi hanya dapat diakses admin aktif. Login melalui POST `login.php`, misalnya dengan akun seed `admin@satset.test` / `satset123`. Gunakan cookie `SATSETSESSID` pada host yang sama. Untuk perubahan, tambahkan header `X-CSRF-Token` dari `data.csrf_token` respons login.

| Aksi | Metode | URL |
|---|---|---|
| Seluruh pengaturan | GET | `pengaturan.php` |
| Detail | GET | `pengaturan.php?kunci=persen_komisi` |
| Ubah | PUT | `pengaturan.php?kunci=persen_komisi` |

GET daftar mengembalikan semua baris, diurutkan berdasarkan `kunci`, tanpa pagination. GET detail dan PUT mengembalikan satu objek dengan `kunci`, `nilai`, `keterangan`, dan `diperbarui_pada`. Respons memakai bentuk `{"berhasil": true, "data": ...}`. Nilai tetap dikembalikan sebagai string sesuai kolom database.

Contoh PUT, pilih Body > raw > JSON dan header `Content-Type: application/json`:

```json
{
  "nilai": "25",
  "keterangan": "Komisi sebesar 25 persen dari harga kesepakatan"
}
```

Kirim minimal satu dari `nilai` atau `keterangan`; field yang tidak dikirim tetap dipertahankan. `nilai` menerima teks atau angka, disimpan sebagai teks tidak kosong maksimal 100 karakter. `keterangan` berupa teks maksimal 255 karakter atau null untuk mengosongkan. `kunci` (maksimal 50 karakter) hanya dikirim melalui URL dan tidak dapat diganti; `diperbarui_pada` dikelola database. Kolom lain dan upload file ditolak.

PUT juga menerima x-www-form-urlencoded atau form-data dengan field teks yang sama. Untuk form-data, biarkan Postman mengatur header Content-Type beserta boundary secara otomatis. PUT multipart memerlukan PHP 8.4+. Endpoint hanya mendukung GET dan PUT; POST, PATCH, DELETE, serta `_method` tidak tersedia.

Validasi nilai parameter bawaan:

| Kunci | Nilai yang diizinkan |
|---|---|
| `persen_komisi` | 0–100, maksimal 2 angka desimal, gunakan titik |
| `rentang_wajar_persen` | 0–100, desimal boleh memakai titik |
| `saldo_awal_driver`, `tarif_dasar`, `tarif_per_km` | Bilangan bulat 0–2147483647 |
| `maks_ronde` | Bilangan bulat 1–3 (BR-08) |
| `durasi_timer_detik`, `radius_tiba_pulang_m` | Bilangan bulat 1–2147483647 |
| `radius_pesanan_km` | Angka lebih besar dari 0, desimal boleh memakai titik |

Pengaturan lain yang sudah ada di database mengikuti batas teks umum. Tidak ada aksi untuk menambah atau menghapus pengaturan. Mengubah `saldo_awal_driver` memengaruhi pembuatan dompet driver berikutnya; saldo driver yang sudah terdaftar tidak ikut diubah.

| Status | Arti |
|---|---|
| 200 | Berhasil membaca/mengubah |
| 401 | Belum login, sesi kedaluwarsa, atau akun tidak aktif |
| 403 | Bukan admin atau token CSRF tidak valid |
| 404 | Kunci pengaturan tidak ditemukan |
| 405 | Metode selain GET/PUT; header `Allow: GET, PUT` |
| 415 | Format body tidak didukung |
| 422 | Parameter, field, atau nilai tidak valid |
| 500 | Kesalahan server/database |

Alur: controller `gerbang/xhr/pengaturan.php` → middleware admin/CSRF → service `logika/pengaturan.php` → SQL `kueri/kueri_pengaturan.php`. Update memakai transaksi dan mengunci baris sebelum validasi serta penyimpanan; pengiriman ulang nilai yang sama tetap berhasil. Skema dan seed yang ada dapat dipakai tanpa migrasi tambahan.
