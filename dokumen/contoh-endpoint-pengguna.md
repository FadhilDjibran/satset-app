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

Endpoint ini untuk pengembangan lokal: hanya melayani request dari komputer sendiri ketika `APP_ENV=lokal`. Belum ada login dan otorisasi. Sebelum dipakai di lingkungan bersama, hubungkan ke sesi dan aturan hak akses; jangan membuka CRUD pengguna untuk semua pengunjung.
