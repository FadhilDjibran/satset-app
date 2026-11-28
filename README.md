# SATSET

**Semua Bisa, Semua Satset.**

SATSET adalah aplikasi ride-hailing berbasis web untuk kendaraan roda 4 dengan tawar-menawar tarif antara penumpang dan driver. Proyek ini dikerjakan oleh Kelompok 1 untuk mata kuliah Manajemen Proyek IT (F081), UPN "Veteran" Jawa Timur.

## Aktor

| Aktor | Portal | Peran |
|---|---|---|
| Penumpang | `gerbang/penumpang/` | Memesan, mengajukan harga, melacak perjalanan, memberi rating, dan melapor. |
| Driver | `gerbang/pengemudi/` | Melihat pesanan, menawar, menjalankan perjalanan, memakai Mode Searah, dan mengelola saldo komisi. |
| Admin (Pengelola) | `gerbang/pengelola/` | Meninjau laporan, memberi sanksi, mengelola pengguna, dan top-up saldo simulasi. |

## Modul dan Fitur

| Modul | Isi |
|---|---|
| Akun | Registrasi, login per peran, profil, dan data kendaraan driver. |
| Pemesanan | Pilih titik di peta, rute dan estimasi (OSRM), tarif rekomendasi dengan rentang wajar, batalkan pesanan. |
| Tawar-menawar | Maksimal 3 ronde dengan timer dan penanda "Tawaran terakhir", pembaruan tanpa muat ulang (AJAX). |
| Mode Searah | Tombol "Pulang" menyaring pesanan yang searah jalan pulang driver. |
| Perjalanan | Posisi driver dan status perjalanan secara langsung di peta, serta riwayat. |
| Dompet | Saldo awal simulasi Rp 100.000, rincian pendapatan bersih, dan komisi dipotong dari saldo (tanpa uang sungguhan). |
| Bantuan | Panduan, laporan beserta bukti, tinjauan dan sanksi oleh admin. |
| Rating | Penumpang dan driver saling menilai 1 sampai 5 setelah perjalanan selesai. |
| Catatan Pesanan | Catatan satu arah dari penumpang ke driver (maksimal 150 karakter). |
| Pengaturan | Admin melihat seluruh parameter sistem, membuka detail berdasarkan kunci, dan mengubah nilai/keterangan. |

Rincian lengkap ada di dokumen Kebutuhan Sistem (UR, FR, hak akses, dan aturan bisnis BR). Setiap berkas PHP di proyek ini memuat ID FR dan BR yang menjadi tanggung jawabnya di bagian atas berkas.

Panduan endpoint pengaturan khusus admin: [dokumen/contoh-endpoint-pengaturan.md](dokumen/contoh-endpoint-pengaturan.md).

## Teknologi

PHP native, MySQL, Leaflet.js, OpenStreetMap, OSRM, JavaScript (AJAX/fetch), XAMPP, Visual Studio Code, Git dan GitHub.

## Struktur Folder

```
satset-app/
├── gerbang/        titik masuk web
│   ├── index.php   pengalihan sesuai peran
│   ├── akses/      daftar, masuk, keluar
│   ├── penumpang/  beranda, pesan, tawaran, perjalanan, riwayat, rating, bantuan, laporan, profil
│   ├── pengemudi/  beranda, searah, perjalanan, dompet, kendaraan, riwayat, rating, bantuan, laporan, profil
│   ├── pengelola/  dasbor, pengguna, laporan, tinjau-laporan, dompet, riwayat, bantuan, profil
│   ├── xhr/        titik akhir JSON untuk polling dan aksi AJAX
│   ├── statis/     gaya, skrip, ikon, vendor (Leaflet)
│   └── unggahan/   foto profil, kendaraan, dan bukti laporan
├── logika/         aturan bisnis per modul
├── kueri/          seluruh perintah SQL (PDO)
├── komponen/       kerangka halaman dan elemen tampilan yang dipakai ulang
├── rangka/         muat, konfigurasi, koneksi, sesi, penjaga
├── basisdata/      skema dan data awal (termasuk akun admin)
├── dokumen/        laporan, hasil wawancara, kebutuhan sistem, mockup
├── .env.contoh     contoh konfigurasi lokal
└── README.md
```

## Cara Menjalankan (XAMPP)

1. Salin folder `satset-app` ke `htdocs` XAMPP, lalu aktifkan Apache dan MySQL.
2. Untuk instalasi baru, impor `basisdata/migration.sql`, lalu `basisdata/seeders/data_awal.sql` di phpMyAdmin. Baca `basisdata/README.md` untuk skema lama yang sudah berisi data dan perubahan yang perlu diterapkan pada backend.
3. Salin `.env.contoh` menjadi `.env`, lalu sesuaikan isinya.
4. Buka `http://localhost/satset-app/gerbang/` di peramban.

## Tim

| Nama | NPM | Peran |
|---|---|---|
| Fadhil Djibran | 23081010030 | Project Manager |
| Berlian Ika Isabela | 23081010056 | System Analyst |
| Muhamad Syaifudin Afandi | 23081010001 | UI/UX Designer |
| Moch. Nasikh Andhyka Pratama | 23081010037 | Database Administrator |
| Achmad Salman Pasha | 23081010262 | Backend Developer 1 |
| Loviano Desta Satriawan | 23081010097 | Backend Developer 2 |
| Lovina Putri Pratama | 23081010018 | Frontend Developer 1 |
| Lucky Fitrianda Wahyudi | 23081010061 | Frontend Developer 2 |
| Andrean Firmansyah | 23081010137 | Quality Assurance |
| Muhammad Faris Syaifulloh | 23081010311 | Technical Writer |

Dosen pengampu: Angga Lisdiyanto, S.Kom., M.Kom.
