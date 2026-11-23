# SATSET

**Semua Bisa, Semua Satset.**

SATSET adalah aplikasi ride-hailing berbasis web dengan tawar-menawar tarif antara penumpang dan pengemudi. Proyek ini dikerjakan oleh Kelompok 1 untuk mata kuliah Manajemen Proyek IT (F081), UPN "Veteran" Jawa Timur.

## Fitur Utama

- Tawar-menawar tarif (maksimal 3 ronde, dengan timer)
- Mode Searah: menampilkan pesanan yang searah dengan perjalanan pulang pengemudi
- Bantuan dan Laporan Masalah (reporting) dengan portal pengelola
- Dompet digital simulasi dan rincian pendapatan bersih pengemudi
- Peta dan estimasi rute (Leaflet.js, OpenStreetMap, OSRM)

## Teknologi

PHP native, MySQL, Leaflet.js, JavaScript (AJAX/fetch), XAMPP, Visual Studio Code, Git dan GitHub.

## Struktur Folder

```
satset-app/
├── gerbang/        titik masuk web: akses, penumpang, pengemudi, pengelola, xhr, statis, unggahan
├── logika/         aturan bisnis (akun, pesanan, negosiasi, tarif, searah, laporan, dst.)
├── kueri/          seluruh perintah SQL (PDO)
├── komponen/       kerangka halaman dan elemen tampilan yang dipakai ulang
├── rangka/         inisialisasi: muat, konfigurasi, koneksi, sesi, penjaga
├── basisdata/      skema dan data awal
├── dokumen/        laporan, hasil wawancara, mockup
├── .env.contoh     contoh konfigurasi lokal
└── README.md
```

## Cara Menjalankan (XAMPP)

1. Salin folder `satset-app` ke `htdocs` XAMPP, lalu aktifkan Apache dan MySQL.
2. Buat database `satset` di phpMyAdmin, lalu impor berkas di `basisdata/` (setelah tersedia).
3. Salin `.env.contoh` menjadi `.env`, lalu sesuaikan isinya.
4. Buka `http://localhost/satset-app/gerbang/` di peramban.

## Alur Kerja Git

- `main`: versi stabil.
- `develop`: tempat semua fitur digabung.
- `fitur/<nama-fitur>`: cabang kerja per fitur, digabung ke `develop` lewat pull request dan di-review minimal satu anggota lain.
- Awalan pesan commit: `fitur:`, `perbaikan:`, `dok:`.

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
