# Mencoba CRUD pengguna di Postman

> Login/logout dan seluruh CRUD sudah menggunakan tabel `pengguna`. Respons memakai `id_pengguna` dan `foto_url`, menggantikan `id` dan `foto_profil`. Parameter URL tetap `?id=...`. Akun seed: `admin@satset.test` / `satset123`.

## Format request endpoint aktif

Login, registrasi driver/penumpang, create/update pengguna, dan edit profil memakai class `Request`. Body bisa berupa JSON, form-data, atau x-www-form-urlencoded. Di Postman, hapus header Content-Type manual saat memakai form-data agar boundary dibuat otomatis. File hanya dikirim melalui form-data; validasi field masing-masing endpoint tetap berlaku.

GET membaca parameter dari URL, bukan body. DELETE pengguna dan POST logout tidak memerlukan body. Login dan registrasi bersifat publik. CRUD pengguna tetap khusus admin; profil untuk semua peran yang login. Aksi perubahan yang memerlukan login tetap membutuhkan cookie sesi dan header X-CSRF-Token.

Berkas endpoint modul lain yang masih berupa kerangka komentar belum menyediakan fitur aktif; saat diimplementasikan, gunakan pola Request yang sama.

## Registrasi driver dan penumpang (guest)

Endpoint registrasi publik tidak memerlukan login, cookie sesi, atau header `X-CSRF-Token`.

| Jenis akun | Metode | URL |
|---|---|---|
| Driver | POST | `http://localhost/satset/gerbang/xhr/registrasi-driver.php` |
| Penumpang | POST | `http://localhost/satset/gerbang/xhr/registrasi-penumpang.php` |

Di Postman, pilih Body > raw > JSON. Contoh untuk driver:

```json
{
  "nama": "Rizky Pratama",
  "email": "rizky.driver@example.com",
  "nomor_hp": "081234567899",
  "password": "rahasia123",
  "kendaraan": {
    "merek": "Toyota",
    "model": "Avanza",
    "plat_nomor": "L 9001 XY",
    "warna": "Hitam"
  }
}
```

Untuk penumpang, gunakan endpoint penumpang dan data nama/email/nomor HP yang berbeda, tanpa field `kendaraan`. Jangan kirim `peran`, `status_akun`, ID, atau hash password: field tersebut ditentukan sistem atau ditolak. Field akun wajib ialah `nama`, `email`, `nomor_hp`, dan `password`. `foto_url` opsional (teks path/null pada JSON, atau File pada form-data).

Registrasi driver juga wajib mengisi `kendaraan.merek`, `kendaraan.plat_nomor`, dan `kendaraan.warna`. `model` serta `foto_url` kendaraan opsional. Batas panjang merek/model 50, plat 15, warna 30, dan path foto 255 karakter. Plat disimpan dengan huruf kapital dan harus unik. `jumlah_roda` boleh tidak dikirim; jika dikirim harus 4. ID driver, status online, saldo, dan status aktif kendaraan ditentukan sistem.

Untuk foto, pilih Body > form-data. Contoh key driver:

| Key | Tipe | Contoh |
|---|---|---|
| nama | Text | Rizky Pratama |
| email | Text | rizky.driver@example.com |
| nomor_hp | Text | 081234567899 |
| password | Text | rahasia123 |
| foto_url | File, opsional | Foto profil |
| kendaraan[merek] | Text | Toyota |
| kendaraan[model] | Text, opsional | Avanza |
| kendaraan[plat_nomor] | Text | L 9001 XY |
| kendaraan[warna] | Text | Hitam |
| kendaraan[foto_url] | File, opsional | Foto kendaraan |

Nama field tetap mengikuti kolom tabel; grup `kendaraan[...]` memisahkan foto kendaraan dari foto pengguna. Biarkan Postman mengatur Content-Type multipart secara otomatis. Batas gambar sama dengan CRUD: JPG/PNG/WebP maksimal 2 MB. Foto profil disimpan di `gerbang/unggahan/profil/`, foto kendaraan di `gerbang/unggahan/kendaraan/`.

Respons sukses ialah 201 dengan `berhasil`, `pesan`, dan `data` profil tanpa password/hash. Respons driver juga memuat `data.kendaraan`. Peran mengikuti endpoint dan status akun selalu `aktif`. Driver mendapat satu kendaraan aktif roda 4, data driver default (offline, posisi kosong, Mode Searah nonaktif), dompet, serta transaksi saldo awal sesuai pengaturan. Saldo awal 0 tidak menghasilkan transaksi top-up bernominal 0. Jika salah satu proses gagal, seluruh pembuatan tabel terkait dan file baru dibatalkan. Penumpang hanya mendapat baris pengguna.

Setelah registrasi berhasil, panggil endpoint login dengan email/password baru. Registrasi tidak membuat sesi login otomatis. CRUD `pengguna.php` tetap khusus admin yang login; membuat akun guest tidak memberi akses CRUD.

Status gagal: 422 untuk input tidak valid/field terlarang/gambar salah/kendaraan tidak lengkap, 409 untuk email/nomor HP/plat nomor duplikat, 405 untuk metode selain POST, 415 untuk Content-Type yang tidak didukung, dan 500 jika proses server/database gagal.

Alur driver: `gerbang/xhr/registrasi-driver.php → registrasiDriver() di logika/akun.php → buatPengguna() di logika/pengguna.php → kueri`. Penumpang memakai pola yang sama melalui `registrasiPenumpang()`. Class `Request` pada `rangka/request.php` membaca body dan file; proses bisnis tetap di service. Kedua service juga dapat dipanggil langsung dari PHP.

Service `buatPengguna()` membuka satu transaksi PDO, menyimpan pengguna, memanggil `siapkanDriverBaru()` (service driver) dan `buatDompetAwalDriver()` (service dompet), lalu menyimpan kendaraan. Kueri dipisahkan per tabel:

| Tabel | Berkas kueri | Fungsi |
|---|---|---|
| pengguna | kueri/kueri_pengguna.php | tambahPengguna(), cariPengguna() |
| driver | kueri/kueri_driver.php | tambahDriver() |
| kendaraan | kueri/kueri_kendaraan.php | tambahKendaraan(), cariKendaraan() |
| dompet | kueri/kueri_dompet.php | tambahDompetDriver() |
| transaksi | kueri/kueri_transaksi.php | catatSaldoAwalDompet() |
| pengaturan | kueri/kueri_pengaturan.php | ambilNilaiPengaturan() |

Semua kueri menggunakan koneksi PDO yang sama; service terluar melakukan commit/rollback. Endpoint tetap berisi pembacaan request dan pemanggilan service. Registrasi publik driver wajib membawa kendaraan; create pengguna oleh admin masih dapat membuat driver dasar seperti sebelumnya.

### Request untuk semua form

`rangka/request.php` menggantikan pembaca khusus `formulir_pengguna.php`. Class ini bisa digunakan endpoint lain, termasuk kendaraan, pesanan, atau laporan. Format yang didukung: JSON, multipart/form-data, dan application/x-www-form-urlencoded. File tidak dibatasi nama/jenis di class ini; service fitur masing-masing yang memvalidasi.

```php
require_once __DIR__ . '/../../rangka/request.php';

$request = new Request();
$metode = $request->method();
$data = $request->all();              // Semua field body, tanpa query URL atau file
$nama = $request->input('nama');      // Satu field body
$halaman = $request->query('halaman', 1);
$foto = $request->file('foto_url');    // Array upload PHP atau null
$berkas = $request->files();          // Semua field file
```

Body dibaca sekali dan disimpan dalam instance sehingga all(), input(), dan file() bisa dipanggil berulang. Buat satu instance Request per request HTTP. `file()` tidak memindahkan file atau memeriksa jenis/ukuran; service tetap memanggil helper unggahan. `Request` juga tidak memeriksa login/CSRF atau mengirim respons HTTP. Format tak didukung melempar `FormatRequestTidakDidukung` (controller mengirim 415); body JSON/form-data rusak melempar `InvalidArgumentException` (422).

Pada endpoint pengguna, service `fotoDariBerkasPengguna($request->files())` memastikan nama file adalah `foto_url`. Fitur lain dapat memakai nama file berbeda. Service bisnis tetap menerima array data/file, supaya bisa dipanggil langsung dari view PHP tanpa bergantung class HTTP ini. Instance dibuat manual di controller; belum ada dependency injection otomatis.

## CRUD khusus admin

Pengelolaan profil sendiri tersedia melalui endpoint terpisah; lihat bagian "Profil diri sendiri" di bawah.

Jalankan Apache dan MySQL di Laragon. Impor `basisdata/migration.sql`, salin `.env.contoh` ke `.env`, lalu sesuaikan parameter `DB_*`. Gunakan URL dasar:

`http://localhost/satset/gerbang/xhr/pengguna.php`

| Aksi | Metode dan URL | Body |
|---|---|---|
| Buat | `POST /pengguna.php` | JSON atau form |
| Daftar | `GET /pengguna.php?batas=20&offset=0` | - |
| Detail | `GET /pengguna.php?id=1` | - |
| Ubah | `PUT /pengguna.php?id=1` | JSON atau form berisi kolom yang diubah |
| Nonaktifkan | `DELETE /pengguna.php?id=1` | - |

Untuk POST dan PUT, pilih **Body → raw → JSON** di Postman. Contoh POST:

```json
{
  "nama": "Budi Santoso",
  "email": "budi@example.com",
  "nomor_hp": "081234567890",
  "password": "rahasia123",
  "peran": "penumpang"
}
```

Contoh PUT:

```json
{"nama": "Budi S.", "foto_url": null}
```

Respons sukses memakai `berhasil: true` dan `data` (kecuali DELETE, yang mengirim `pesan`). Status: 201 dibuat, 200 berhasil, 404 tidak ditemukan, 409 email/nomor HP duplikat, 422 input tidak valid, 415 tipe body salah. Password di-hash sebelum disimpan dan tidak dikirim balik.

POST memerlukan `nama`, `email` (maksimal 100 karakter), `nomor_hp`, `password` (8-72 byte), dan `peran` (`penumpang` atau `driver`). POST dan PUT boleh menyertakan `foto_url` (teks maksimal 255 karakter atau null) dan `status_akun` (`aktif`, `ditangguhkan`, `nonaktif`). PUT hanya mengirim kolom yang ingin diubah; peran dan ID tidak dapat diubah. Client mengirim `password`, bukan `kata_sandi_hash`.

Pembuatan driver juga membuat baris driver, dompet, dan transaksi saldo awal berdasarkan `pengaturan.saldo_awal_driver`, dalam satu transaksi database. DELETE mengubah status menjadi `nonaktif`; data tetap ada di GET agar riwayat dan relasinya tidak hilang. Akun nonaktif tidak bisa login, dan sesi aktifnya ditolak pada request berikutnya. PUT dengan `status_akun: "aktif"` dapat mengaktifkan kembali akun.

## Login sebelum mencoba CRUD

Semua operasi CRUD pengguna memerlukan login sebagai **admin**. Akun driver dan penumpang dapat login, tetapi menerima 403 saat mengakses CRUD pengguna. Import `basisdata/seeders/data_awal.sql` untuk menyediakan akun dummy.

1. Kirim **POST** ke `http://localhost/satset/gerbang/xhr/login.php`, Body raw JSON:

```json
{"email":"admin@satset.test","password":"satset123"}
```

2. Respons 200 berisi `data.pengguna` dan `data.csrf_token`. Postman menyimpan cookie `SATSETSESSID` otomatis (pastikan cookie jar aktif). Gunakan host yang sama untuk semua request, misalnya selalu `localhost`.
3. Untuk GET pengguna, cukup cookie tersebut. Untuk POST/PUT/DELETE pengguna dan POST logout, tambahkan header **X-CSRF-Token** dengan nilai `data.csrf_token` dari login. Header ini melindungi aksi perubahan ketika autentikasi memakai cookie.
4. Logout: **POST** ke `http://localhost/satset/gerbang/xhr/logout.php`, tanpa body, dengan cookie dan header token. Cookie dan sesi akan dihapus. Akses CRUD setelah logout menghasilkan 401.

Opsional: di tab **Scripts → Post-response** request login, simpan token otomatis:

```javascript
if (pm.response.code === 200) {
  pm.collectionVariables.set('csrf_token', pm.response.json().data.csrf_token);
}
```

Kemudian isi nilai header `X-CSRF-Token` dengan `{{csrf_token}}`.

Sesi kedaluwarsa setelah 30 menit tanpa aktivitas. Status akun diperiksa dari database pada setiap request, sehingga akun yang dihapus atau ditangguhkan kehilangan akses. Login gagal menghasilkan 401, input tidak valid 422, peran atau token salah 403. Login hanya menerima email/password; peran tidak dapat dipilih melalui body login. Password dan hash tidak dikirim dalam respons.

Alur: `gerbang/xhr/login.php → logika/akun.php → kueri/kueri_pengguna.php → sesi`. Pada endpoint CRUD, `rangka/penjaga.php` bertindak sebagai middleware melalui `wajibLogin('admin')` dan `wajibTokenCsrf()` sebelum menjalankan operasi database.

## Pembagian controller, service, dan kueri

`gerbang/xhr/pengguna.php` berperan sebagai controller: membaca metode/parameter/body HTTP, memanggil middleware dan service, lalu mengubah hasil atau exception menjadi respons JSON. Endpoint tidak memanggil fungsi kueri CRUD langsung.

`logika/pengguna.php` berperan sebagai service: memvalidasi data, memeriksa keberadaan akun, mengatur transaksi pembuatan driver, dan memanggil fungsi di `kueri/kueri_pengguna.php`. Service mengembalikan data PHP atau melempar exception; tidak mengirim HTTP/JSON.

| Aksi | Fungsi service di logika | Fungsi kueri utama |
|---|---|---|
| Daftar | `ambilDaftarPengguna()` | `daftarPengguna()` |
| Detail | `ambilDetailPengguna()` | `cariPengguna()` |
| Buat | `buatPengguna()` | `tambahPengguna()`, serta kueri data awal driver |
| Ubah | `perbaruiPengguna()` | `cariPengguna()`, `ubahPengguna()` |
| Nonaktifkan | `nonaktifkanPengguna()` | `cariPengguna()`, `hapusPengguna()` |

`buatPengguna()` kini mengembalikan array profil pengguna yang sudah dibuat, bukan ID saja. View PHP nantinya bisa memanggil service yang sama setelah memeriksa login, hak akses, dan CSRF untuk perubahan data.

## Upload foto di Postman

Untuk membuat pengguna dengan foto, gunakan POST pada URL pengguna, lalu Body > form-data:

| Key | Tipe | Contoh |
|---|---|---|
| nama | Text | Rizky Pratama |
| email | Text | rizky.baru@example.com |
| nomor_hp | Text | 081234567899 |
| password | Text | rahasia123 |
| peran | Text | driver |
| foto_url | File | Pilih JPG, PNG, atau WebP, maksimal 2 MB |

Tetap sertakan cookie login admin dan header `X-CSRF-Token`. Hapus header `Content-Type: application/json` yang diisi manual; Postman membuat `multipart/form-data` beserta boundary otomatis. Gunakan field `foto_url` bertipe File; backend menyimpan file lalu mengisi kolom database dengan path gambar.

Respons `foto_url` berupa path relatif terhadap `gerbang/`, misalnya `unggahan/profil/abc123.png`. URL akses pada instalasi lokal menjadi `http://localhost/satset/gerbang/unggahan/profil/abc123.png`.

Untuk mengganti foto pada PHP 8.4+, gunakan PUT `pengguna.php?id=6`, Body form-data berisi `foto_url` (File). Kolom teks lain boleh disertakan jika ingin ikut diubah. Backend membaca PUT multipart dengan `request_parse_body()`. Untuk PHP lebih lama, gunakan POST dengan `_method` (Text) = `PUT` sebagai alternatif. Update JSON biasa tetap memakai PUT.

Service menyimpan foto sebelum kueri dijalankan dan membersihkan file baru jika transaksi gagal. Foto lama tidak otomatis dihapus saat diganti atau saat `foto_url` dikosongkan.

## Profil diri sendiri

URL: `http://localhost/satset/gerbang/xhr/profil.php` (tanpa parameter ID).

| Aksi | Metode | Akses |
|---|---|---|
| Lihat profil sendiri | GET | Cookie login semua peran |
| Edit profil sendiri | PUT | Cookie login dan X-CSRF-Token |

Login lewat `login.php` dahulu. Postman menyimpan cookie `SATSETSESSID`; gunakan host yang sama saat mengakses profil. GET mengembalikan profil akun yang login (tanpa password/hash). Endpoint tidak menerima query URL, ID akun pada body, atau pilihan peran.

Untuk edit, pilih PUT, Body > raw > JSON dan tambahkan header `X-CSRF-Token` dari login:

```json
{
  "nama": "Rizky Pratama Baru",
  "nomor_hp": "081234567890"
}
```

Kirim hanya field yang ingin diubah. Sesuai FR-04, field yang dapat diubah ialah `nama`, `nomor_hp`, dan `foto_url`. Email, password, peran, status akun, serta ID tidak dapat diubah lewat endpoint profil ini. Validasi nama/nomor HP mengikuti aturan CRUD pengguna. Nomor HP tetap unik antar akun.

Untuk upload, gunakan PUT, Body > form-data dengan `foto_url` bertipe File (JPG/PNG/WebP maksimal 2 MB). Header Content-Type diatur otomatis oleh Postman. Field nama dan nomor_hp boleh ikut dikirim. Untuk mengosongkan foto, gunakan JSON `{"foto_url": null}`. Alternatif PHP sebelum 8.4: POST form-data dengan `_method` (Text) = `PUT`. Foto lama tetap tersimpan di direktori; file baru dibersihkan jika penyimpanan database gagal.

Respons berhasil memakai status 200 dan `data` profil terbaru. Status gagal: 401 belum login/sesi tidak aktif, 403 token CSRF salah, 422 input atau kolom tidak diizinkan, 409 nomor HP sudah digunakan, dan 415 tipe request tidak didukung.

Alur: `gerbang/xhr/profil.php → wajibLogin() → ambilProfil()/perbaruiProfil() di logika/profil.php → service pengguna → kueri`. ID diberikan controller dari hasil middleware, bukan dari input client. Service profil dapat dipakai view PHP dengan ID terpercaya dari sesi setelah pemeriksaan login dan CSRF.
