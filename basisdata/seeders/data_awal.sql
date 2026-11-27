-- =====================================================================
--  SATSET - Data awal (seed) untuk pengembangan & demo
--  Jalankan sekali SETELAH basisdata/migration.sql pada tabel yang masih kosong.
--  Menjalankan ulang seed akan ditolak karena ID/kunci unik sudah terisi.
--  Kata sandi semua akun contoh: satset123
--  (hash dibuat dengan password_hash() PHP, cek dengan password_verify())
-- =====================================================================
SET NAMES utf8mb4;
SET time_zone = '+07:00';
SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
USE satset;

-- ---------------------------------------------------------------------
-- Parameter sistem
-- Nilai bertanda (contoh) belum ditetapkan tim; sesuaikan dengan logika/tarif.php
-- ---------------------------------------------------------------------
INSERT INTO pengaturan (kunci, nilai, keterangan) VALUES
('persen_komisi',        '20',     'Persentase komisi dari harga kesepakatan (BR-22)'),
('saldo_awal_driver',    '100000', 'Saldo awal simulasi driver baru (BR-21)'),
('maks_ronde',           '3',      'Batas ronde tawar-menawar (BR-08)'),
('durasi_timer_detik',   '120',    'Lama timer tawar-menawar (contoh, BR-11)'),
('tarif_dasar',          '10000',  'Tarif dasar perjalanan (contoh, BR-06)'),
('tarif_per_km',         '4000',   'Tarif per km (contoh, BR-06)'),
('rentang_wajar_persen', '30',     'Batas +/- persen dari tarif rekomendasi (contoh, BR-06)'),
('radius_pesanan_km',    '5',      'Radius pesanan sekitar driver (contoh, BR-14, BR-16)'),
('radius_tiba_pulang_m', '300',    'Jarak ke titik pulang untuk menonaktifkan Mode Searah (contoh, BR-17)');

-- ---------------------------------------------------------------------
-- Pengguna (1 admin, 2 penumpang, 2 driver)
-- ---------------------------------------------------------------------
SET @hash = '$2y$10$IN89wloiuAPbTQ0dOSRsu.x0ohT/xCTC12sEBLJ9NZ5K1wyIRjZN2';

INSERT INTO pengguna (id_pengguna, nama, email, nomor_hp, kata_sandi_hash, peran) VALUES
(1, 'Admin SATSET',  'admin@satset.test', '081200000001', @hash, 'admin'),
(2, 'Rina Putri',    'rina@satset.test',  '081200000002', @hash, 'penumpang'),
(3, 'Dewi Lestari',  'dewi@satset.test',  '081200000003', @hash, 'penumpang'),
(4, 'Budi Santoso',  'budi@satset.test',  '081200000004', @hash, 'driver'),
(5, 'Andi Wijaya',   'andi@satset.test',  '081200000005', @hash, 'driver');

INSERT INTO driver (id_driver, status_online, posisi_lat, posisi_lng, posisi_diperbarui) VALUES
(4, 1, -7.3335000, 112.7880000, '2026-11-20 08:55:00'),
(5, 1, -7.2905000, 112.7398000, '2026-11-20 08:56:00');

-- Driver 5 sedang memakai Mode Searah (pulang ke Sidoarjo)
UPDATE driver
SET mode_searah_aktif = 1, titik_pulang_lat = -7.4478000, titik_pulang_lng = 112.7183000,
    titik_pulang_alamat = 'Sidoarjo Kota', mode_searah_sejak = '2026-11-20 08:50:00'
WHERE id_driver = 5;

INSERT INTO kendaraan (id_kendaraan, id_driver, merek, model, plat_nomor, warna) VALUES
(1, 4, 'Toyota', 'Avanza', 'L 1234 AB', 'Hitam'),
(2, 5, 'Honda',  'Brio',   'W 5678 CD', 'Putih');

-- Dompet + saldo awal Rp100.000 (BR-21). Saldo Budi sudah dipotong komisi pesanan #1.
INSERT INTO dompet (id_dompet, id_driver, saldo) VALUES
(1, 4, 91000),
(2, 5, 100000);

-- ---------------------------------------------------------------------
-- Pesanan #1: selesai, melalui 2 ronde tawar-menawar dengan Budi
-- Tarif rekomendasi = 10.000 + 4.000 x 8,5 km = 44.000; rentang wajar +/-30%
-- ---------------------------------------------------------------------
INSERT INTO pesanan (id_pesanan, id_penumpang, id_driver, id_kendaraan,
    jemput_lat, jemput_lng, jemput_alamat, tujuan_lat, tujuan_lng, tujuan_alamat,
    harga_ajuan, harga_disepakati, catatan, status, batas_waktu, dibuat_pada) VALUES
(1, 2, 4, 1,
    -7.3332000, 112.7889000, 'UPN "Veteran" Jawa Timur, Gunung Anyar',
    -7.2655000, 112.7517000, 'Tunjungan Plaza, Surabaya',
    40000, 45000, 'Tunggu di depan gerbang utama, pakai jaket biru',
    'selesai', '2026-11-20 09:02:00', '2026-11-20 09:00:00');

INSERT INTO detail_pesanan (id_pesanan, jarak_km, estimasi_menit, tarif_rekomendasi,
    harga_min, harga_maks, persen_komisi, nominal_komisi, pendapatan_bersih, status_komisi,
    waktu_disepakati, waktu_dijemput, waktu_selesai) VALUES
(1, 8.50, 25, 44000, 30800, 57200, 20.00, 9000, 36000, 'dipotong',
    '2026-11-20 09:01:30', '2026-11-20 09:10:00', '2026-11-20 09:38:00');

-- Ronde 1: Budi tawar balik 50.000 (diganti oleh ronde 2)
-- Ronde 1: Andi tawar balik 55.000 (ditolak karena penumpang sepakat dengan Budi)
-- Ronde 2: Rina naikkan ke 45.000, Budi menerima -> kesepakatan
INSERT INTO tawaran (id_tawaran, id_pesanan, id_driver, ronde, pengaju, harga, status, dibuat_pada) VALUES
(1, 1, 4, 1, 'driver',    50000, 'diganti',  '2026-11-20 09:00:20'),
(2, 1, 5, 1, 'driver',    55000, 'ditolak',  '2026-11-20 09:00:25'),
(3, 1, 4, 2, 'penumpang', 45000, 'diterima', '2026-11-20 09:01:00');

INSERT INTO transaksi (id_transaksi, id_dompet, jenis, nominal, saldo_sebelum, saldo_sesudah,
    id_pesanan, id_admin, keterangan, dibuat_pada) VALUES
(1, 1, 'topup',  100000,      0, 100000, NULL, NULL, 'Saldo awal driver (simulasi)', '2026-11-19 10:00:00'),
(2, 2, 'topup',  100000,      0, 100000, NULL, NULL, 'Saldo awal driver (simulasi)', '2026-11-19 10:05:00'),
(3, 1, 'komisi',   9000, 100000,  91000,    1, NULL, 'Komisi 20% pesanan #1',        '2026-11-20 09:38:00');

INSERT INTO rating (id_pesanan, id_pemberi, id_penerima, skor, ulasan) VALUES
(1, 2, 4, 5, 'Driver ramah dan tepat waktu'),
(1, 4, 2, 4, NULL);

-- Laporan: Budi melaporkan Rina menunggu terlalu lama; sudah ditinjau, diberi peringatan
INSERT INTO laporan (id_laporan, id_pesanan, id_pelapor, id_terlapor, kategori, deskripsi,
    status, id_admin_peninjau, catatan_admin, dibuat_pada, ditinjau_pada, diselesaikan_pada) VALUES
(1, 1, 4, 2, 'menunggu_terlalu_lama',
    'Penumpang baru keluar 8 menit setelah driver tiba di titik jemput.',
    'selesai', 1, 'Bukti waktu tiba sesuai riwayat posisi.',
    '2026-11-20 10:00:00', '2026-11-20 13:00:00', '2026-11-20 13:05:00');

INSERT INTO sanksi (id_laporan, id_pengguna, id_admin, jenis, alasan, waktu_mulai) VALUES
(1, 2, 1, 'peringatan', 'Membuat driver menunggu lebih dari 5 menit di titik jemput.', '2026-11-20 13:05:00');

-- ---------------------------------------------------------------------
-- Pesanan #2: masih mencari driver, Andi (Mode Searah) mengajukan tawaran
-- ---------------------------------------------------------------------
INSERT INTO pesanan (id_pesanan, id_penumpang, jemput_lat, jemput_lng, jemput_alamat,
    tujuan_lat, tujuan_lng, tujuan_alamat, harga_ajuan, status, batas_waktu, dibuat_pada) VALUES
(2, 3, -7.2900000, 112.7400000, 'Stasiun Gubeng, Surabaya',
    -7.4400000, 112.7200000, 'Alun-alun Sidoarjo', 65000,
    'mencari_driver', '2026-11-20 09:02:00', '2026-11-20 09:00:00');

INSERT INTO detail_pesanan (id_pesanan, jarak_km, estimasi_menit, tarif_rekomendasi, harga_min, harga_maks) VALUES
(2, 17.20, 40, 78800, 55160, 102440);

INSERT INTO tawaran (id_pesanan, id_driver, ronde, pengaju, harga, status, dibuat_pada) VALUES
(2, 5, 1, 'driver', 72000, 'menunggu', '2026-11-20 09:00:40');
