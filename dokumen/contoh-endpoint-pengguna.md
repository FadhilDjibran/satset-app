# Mencoba CRUD pengguna di Postman

Jalankan Apache dan MySQL di Laragon. Impor `basisdata/migration.sql`, salin `.env.contoh` ke `.env`, lalu sesuaikan parameter `DB_*`. Gunakan URL dasar:

`http://localhost/satset/gerbang/xhr/pengguna.php`

| Aksi | Metode dan URL | Body |
|---|---|---|
| Buat | `POST /pengguna.php` | JSON wajib |
| Daftar | `GET /pengguna.php?batas=20&offset=0` | - |
| Detail | `GET /pengguna.php?id=1` | - |
| Ubah | `PUT /pengguna.php?id=1` | JSON berisi kolom yang diubah |
| Hapus | `DELETE /pengguna.php?id=1` | - |

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
{"nama": "Budi S."}
```

Respons sukses memakai `berhasil: true` dan `data` (kecuali DELETE, yang mengirim `pesan`). Status: 201 dibuat, 200 berhasil, 404 tidak ditemukan, 409 email/nomor HP duplikat, 422 input tidak valid, 415 tipe body salah. Password di-hash sebelum disimpan dan tidak dikirim balik.

## Login sebelum mencoba CRUD

Semua operasi CRUD pengguna memerlukan login sebagai **admin**. Akun driver dan penumpang dapat login, tetapi menerima 403 saat mengakses CRUD pengguna. Import `basisdata/seeder_user.sql` untuk menyediakan akun dummy.

1. Kirim **POST** ke `http://localhost/satset/gerbang/xhr/login.php`, Body raw JSON:

```json
{"email":"admin@dummy.satset.test","password":"Dummy12345!"}
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
