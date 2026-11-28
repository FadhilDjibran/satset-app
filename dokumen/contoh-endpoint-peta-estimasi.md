# Peta dan estimasi perjalanan

`POST http://localhost/satset/gerbang/xhr/hitung-rute.php` menghitung rute mobil, jarak, perkiraan waktu, dan rentang harga. Estimasi tidak menyimpan pesanan.

Atur `OSRM_URL` pada `.env` atau lingkungan server PHP. Untuk pengembangan, `.env.contoh` memakai `https://router.project-osrm.org`, server demo publik. Layanan demo bersifat best effort dan mengikuti kebijakan pemakaian penyedia. Backend mengirim identitas aplikasi melalui header `User-Agent`. Untuk produksi, gunakan server sendiri yang memiliki profil mobil (`driving`) dan data jalan wilayah layanan. Server lokal dapat memakai `http://127.0.0.1:5000` jika OSRM sudah dijalankan pada port tersebut. Alamat konfigurasi adalah URL dasar dan dapat mencakup awalan path jika lewat proxy. PHP memerlukan ekstensi cURL dan PDO MySQL. [Dokumentasi route API OSRM](https://project-osrm.org/docs/v5.24.0/api/#route-service), [kebijakan server demo](https://github.com/Project-OSRM/osrm-backend/wiki/Api-usage-policy)

Backend mengirim dua pasang koordinat `lng,lat` ke OSRM dan mengambil rute pertama. Backend memakai GeoJSON dengan `overview=simplified`, batas koneksi 2 detik, batas waktu total 5 detik, dan batas respons OSRM 2 MiB.

## Postman dan JavaScript

Login sebagai penumpang melalui `POST login.php` di host yang sama agar Postman menyimpan cookie `SATSETSESSID`. Akun penumpang contoh pada data seed adalah `rina@satset.test` dengan kata sandi `satset123`. Endpoint estimasi memakai cookie sesi dan tidak memerlukan token CSRF karena hanya menghitung hasil.

Pilih **Body → raw → JSON** dan kirim empat koordinat:

```json
{
  "jemput_lat": -7.33,
  "jemput_lng": 112.79,
  "tujuan_lat": -7.29,
  "tujuan_lng": 112.76
}
```

`application/x-www-form-urlencoded` dan `multipart/form-data` juga didukung dengan nama field yang sama. Pada form-data, pilih tipe **Text**. Semua koordinat wajib berupa angka dalam rentang latitude −90 hingga 90 dan longitude −180 hingga 180. Backend membulatkan koordinat ke 7 angka desimal sesuai presisi kolom lokasi dan mengharuskan titik jemput dan tujuan berbeda pada presisi tersebut. Field tambahan dan file ditolak.

Contoh bentuk respons `200`. Nilai dan garis pada contoh ini hanya ilustrasi.

```json
{
  "berhasil": true,
  "data": {
    "jarak_km": 5,
    "estimasi_menit": 12,
    "tarif_rekomendasi": 30000,
    "harga_min": 21000,
    "harga_maks": 39000,
    "rute_geojson": {
      "type": "LineString",
      "coordinates": [[112.79, -7.33], [112.76, -7.29]]
    }
  }
}
```

Dari halaman di `gerbang/penumpang/`, teruskan objek `koordinat` berisi empat field di atas. Contoh ini memakai peta Leaflet 1.x yang sudah diinisialisasi, dan `layerRute` menyimpan garis sebelumnya.

```javascript
const respons = await fetch('../xhr/hitung-rute.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(koordinat)
});
const hasil = await respons.json();
if (!respons.ok || !hasil.berhasil) {
  throw new Error(hasil.pesan || 'Estimasi perjalanan gagal.');
}
layerRute?.remove();
layerRute = L.geoJSON(hasil.data.rute_geojson).addTo(peta);
```

Teruskan GeoJSON langsung ke Leaflet dengan koordinat dalam urutan `[lng, lat]`.

| Status | Arti |
|---|---|
| 401 | Sesi login penumpang belum ada atau sudah tidak aktif |
| 403 | Akun yang login bukan penumpang |
| 405 | Metode selain POST, dengan header `Allow: POST` |
| 415 | Format body tidak didukung |
| 422 | Input tidak valid, rute tidak ditemukan, atau ukuran perjalanan di luar batas |
| 502 | OSRM tidak dapat dihubungi atau hasilnya tidak valid |
| 504 | Layanan rute melewati batas waktu |
| 500 | Konfigurasi tarif atau OSRM tidak valid, atau terjadi kesalahan internal |

Respons gagal berbentuk `{"berhasil": false, "pesan": "..."}`. Jika gagal, tampilkan `pesan` dan tunggu pengguna memilih ulang lokasi atau mencoba lagi sesuai status.

## Panggil langsung dari halaman PHP

Halaman server dalam proyek yang sama dapat memanggil `hitungEstimasiPerjalanan(array $input): array` dari `logika/peta.php` saat form dikirim. Contoh berikut memakai path relatif dari `gerbang/penumpang/`:

```php
require_once __DIR__ . '/../../rangka/penjaga.php';
require_once __DIR__ . '/../../logika/peta.php';

$pengguna = penggunaDariSesi();
if ($pengguna === null || $pengguna['peran'] !== 'penumpang') {
    // Jalankan alur login atau tampilkan halaman akses ditolak.
    return;
}
session_write_close();

// Bagian ini dijalankan oleh handler form POST.
try {
    if ($_FILES !== []) {
        throw new InvalidArgumentException('Estimasi rute tidak menerima file.');
    }
    $estimasi = hitungEstimasiPerjalanan($_POST);
} catch (InvalidArgumentException | RuteTidakTersedia $e) {
    $pesan = $e->getMessage(); // Escape saat merender ke HTML.
} catch (Throwable $e) {
    error_log((string) $e);
    $pesan = 'Estimasi perjalanan belum dapat diproses.';
}
```

Service membaca pengaturan dari database, mengembalikan array, dan melaporkan kegagalan melalui exception. Pemeriksaan peran dan cara menyajikan hasil ditangani oleh pemanggil. Saat JavaScript di browser menghitung ulang estimasi tanpa memuat ulang halaman, gunakan endpoint HTTP.

## Rumus

| Hasil | Perhitungan |
|---|---|
| Jarak | Meter dari OSRM dibagi 1.000 dan dibulatkan menjadi dua angka desimal |
| Estimasi | Detik dari OSRM dibagi 60 dan dibulatkan ke atas, minimum 1 menit |
| Tarif | `round(tarif_dasar + jarak_km × tarif_per_km)` |
| Harga minimum | `max(1, ceil(tarif × (1 − rentang_wajar_persen / 100)))` |
| Harga maksimum | `floor(tarif × (1 + rentang_wajar_persen / 100))` |

Gunakan jarak yang sudah dibulatkan untuk tarif. Ketiga parameter dibaca dari `pengaturan` setiap kali estimasi dihitung. Kedua komponen tarif harus bilangan bulat nonnegatif dengan minimal satu komponen positif. Persentase dapat bernilai 0–100. Harga berupa rupiah bulat. Rute harus menghasilkan jarak positif setelah pembulatan serta muat dalam batas `detail_pesanan`.
