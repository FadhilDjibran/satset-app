-- =====================================================================
--  SATSET - Aplikasi Web Ride-Hailing dengan Tawar-Menawar Harga
--  Skema basis data MySQL / MariaDB
--  Kelompok 1 - Manajemen Proyek IT F081 - UPN "Veteran" Jawa Timur
--  Penanggung jawab: Database Administrator
--
--  Diuji pada  : MariaDB 10.11. Sintaks dibatasi pada fitur yang ada di
--                MariaDB 10.4+ (bawaan XAMPP) dan MySQL 8.0.16+
--                (CHECK, generated column STORED).
--  Engine      : InnoDB, charset utf8mb4
--  Cara pakai  : phpMyAdmin > Import > pilih file ini, atau
--                mysql -u root < basisdata/migration.sql
--
--  Konvensi:
--  - Nama tabel & kolom: bahasa Indonesia, snake_case, tunggal.
--  - PK: id_<tabel>, INT UNSIGNED AUTO_INCREMENT.
--  - Uang: INT (Rupiah, tanpa desimal) + CHECK >= 0.
--  - Koordinat: DECIMAL(10,7) (akurasi +/- 1 cm, cukup untuk Leaflet).
--  - FK memakai ON DELETE RESTRICT: data tidak dihapus fisik,
--    akun dinonaktifkan lewat pengguna.status_akun.
--  - Kode BR-xx / FR-xx merujuk dokumen KEBUTUHAN SISTEM.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';
-- Mode ketat agar nilai tidak valid ditolak, bukan dipotong diam-diam
SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- Instalasi awal; tidak menghapus database atau memindahkan data dari tabel user lama.
-- Jalankan pada database baru/belum memiliki tabel skema ini.
CREATE DATABASE IF NOT EXISTS satset CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE satset;

-- ---------------------------------------------------------------------
-- 1. pengaturan
--    Parameter sistem yang bisa diubah tanpa mengubah kode (BR-22).
-- ---------------------------------------------------------------------
CREATE TABLE pengaturan (
    kunci           VARCHAR(50)   NOT NULL,
    nilai           VARCHAR(100)  NOT NULL,
    keterangan      VARCHAR(255)  NULL,
    diperbarui_pada DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (kunci)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. pengguna
--    Superclass Pengguna; Penumpang dan Admin cukup dibedakan lewat
--    kolom peran karena tidak punya atribut khusus. (BR-01, BR-02, BR-04)
-- ---------------------------------------------------------------------
CREATE TABLE pengguna (
    id_pengguna     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nama            VARCHAR(100)  NOT NULL,
    email           VARCHAR(100)  NOT NULL,
    nomor_hp        VARCHAR(20)   NOT NULL,
    kata_sandi_hash VARCHAR(255)  NOT NULL COMMENT 'hasil password_hash() PHP',
    foto_url        VARCHAR(255)  NULL,
    peran           ENUM('penumpang','driver','admin') NOT NULL,
    status_akun     ENUM('aktif','ditangguhkan','nonaktif') NOT NULL DEFAULT 'aktif',
    dibuat_pada     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    diperbarui_pada DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_pengguna),
    UNIQUE KEY uq_pengguna_email   (email),      -- BR-01
    UNIQUE KEY uq_pengguna_nomor_hp (nomor_hp),  -- BR-01
    KEY idx_pengguna_peran_status (peran, status_akun)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. driver
--    Subclass Driver (relasi 1:1 dengan pengguna, PK sekaligus FK).
--    Menyimpan status online, posisi terakhir (FR-20, BR-14) dan
--    Mode Searah (FR-16..FR-19, BR-15..BR-19).
-- ---------------------------------------------------------------------
CREATE TABLE driver (
    id_driver           INT UNSIGNED  NOT NULL,
    status_online       TINYINT(1)    NOT NULL DEFAULT 0,
    posisi_lat          DECIMAL(10,7) NULL,
    posisi_lng          DECIMAL(10,7) NULL,
    posisi_diperbarui   DATETIME      NULL,
    mode_searah_aktif   TINYINT(1)    NOT NULL DEFAULT 0,
    titik_pulang_lat    DECIMAL(10,7) NULL,
    titik_pulang_lng    DECIMAL(10,7) NULL,
    titik_pulang_alamat VARCHAR(255)  NULL,
    mode_searah_sejak   DATETIME      NULL,
    PRIMARY KEY (id_driver),
    KEY idx_driver_online (status_online, posisi_lat, posisi_lng),
    CONSTRAINT fk_driver_pengguna FOREIGN KEY (id_driver)
        REFERENCES pengguna (id_pengguna) ON DELETE RESTRICT ON UPDATE RESTRICT,
    -- BR-15: Mode Searah hanya aktif jika titik pulang sudah ditetapkan
    CONSTRAINT ck_driver_searah CHECK (
        mode_searah_aktif = 0
        OR (titik_pulang_lat IS NOT NULL AND titik_pulang_lng IS NOT NULL)
    ),
    CONSTRAINT ck_driver_posisi CHECK (
        (posisi_lat IS NULL AND posisi_lng IS NULL)
        OR (posisi_lat BETWEEN -90 AND 90 AND posisi_lng BETWEEN -180 AND 180)
    )
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. kendaraan
--    Driver 1 --- 1..* Kendaraan. Hanya satu kendaraan aktif per driver
--    (dijaga kolom bantu kunci_aktif + UNIQUE). (FR-05, BR-03, BR-05)
-- ---------------------------------------------------------------------
CREATE TABLE kendaraan (
    id_kendaraan  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_driver     INT UNSIGNED  NOT NULL,
    merek         VARCHAR(50)   NOT NULL,
    model         VARCHAR(50)   NULL,
    plat_nomor    VARCHAR(15)   NOT NULL,
    warna         VARCHAR(30)   NOT NULL,
    foto_url      VARCHAR(255)  NULL,
    jumlah_roda   TINYINT UNSIGNED NOT NULL DEFAULT 4,
    aktif         TINYINT(1)    NOT NULL DEFAULT 1,
    kunci_aktif   INT UNSIGNED AS (IF(aktif = 1, id_driver, NULL)) STORED,
    dibuat_pada   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_kendaraan),
    UNIQUE KEY uq_kendaraan_plat (plat_nomor),
    UNIQUE KEY uq_kendaraan_satu_aktif (kunci_aktif),
    KEY idx_kendaraan_driver (id_driver),
    CONSTRAINT fk_kendaraan_driver FOREIGN KEY (id_driver)
        REFERENCES driver (id_driver) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_kendaraan_roda4 CHECK (jumlah_roda = 4)   -- BR-05
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. dompet
--    Driver 1 --- 1 Dompet. Saldo simulasi, tidak boleh negatif (BR-25).
-- ---------------------------------------------------------------------
CREATE TABLE dompet (
    id_dompet       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_driver       INT UNSIGNED  NOT NULL,
    saldo           INT           NOT NULL DEFAULT 0,
    diperbarui_pada DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_dompet),
    UNIQUE KEY uq_dompet_driver (id_driver),
    CONSTRAINT fk_dompet_driver FOREIGN KEY (id_driver)
        REFERENCES driver (id_driver) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_dompet_saldo CHECK (saldo >= 0)            -- BR-25
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. pesanan
--    Penumpang 1 --- 0..* Pesanan; Driver 0..1 --- 0..* Pesanan.
--    Kolom bantu kunci_* + UNIQUE menjamin:
--      - penumpang hanya punya 1 pesanan aktif (BR-05)
--      - driver hanya punya 1 perjalanan aktif (BR-13)
-- ---------------------------------------------------------------------
CREATE TABLE pesanan (
    id_pesanan        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_penumpang      INT UNSIGNED  NOT NULL,
    id_driver         INT UNSIGNED  NULL COMMENT 'terisi setelah kesepakatan',
    id_kendaraan      INT UNSIGNED  NULL COMMENT 'kendaraan yang dipakai saat perjalanan',
    jemput_lat        DECIMAL(10,7) NOT NULL,
    jemput_lng        DECIMAL(10,7) NOT NULL,
    jemput_alamat     VARCHAR(255)  NULL,
    tujuan_lat        DECIMAL(10,7) NOT NULL,
    tujuan_lng        DECIMAL(10,7) NOT NULL,
    tujuan_alamat     VARCHAR(255)  NULL,
    harga_ajuan       INT           NOT NULL COMMENT 'harga awal dari penumpang (ronde 0)',
    harga_disepakati  INT           NULL COMMENT 'terkunci setelah kesepakatan (BR-12)',
    catatan           VARCHAR(150)  NULL COMMENT 'BR-32: maks 150 karakter',
    status            ENUM('mencari_driver','disepakati','menuju_jemput','penumpang_naik',
                           'berjalan','selesai','dibatalkan','kedaluwarsa')
                      NOT NULL DEFAULT 'mencari_driver',
    batas_waktu       DATETIME      NOT NULL COMMENT 'akhir timer tawar-menawar (BR-11)',
    dibuat_pada       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    diperbarui_pada   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    kunci_penumpang_aktif INT UNSIGNED AS (
        IF(status IN ('mencari_driver','disepakati','menuju_jemput','penumpang_naik','berjalan'),
           id_penumpang, NULL)) STORED,
    kunci_driver_aktif INT UNSIGNED AS (
        IF(status IN ('disepakati','menuju_jemput','penumpang_naik','berjalan'),
           id_driver, NULL)) STORED,
    PRIMARY KEY (id_pesanan),
    UNIQUE KEY uq_pesanan_penumpang_aktif (kunci_penumpang_aktif),   -- BR-05
    UNIQUE KEY uq_pesanan_driver_aktif (kunci_driver_aktif),         -- BR-13
    KEY idx_pesanan_penumpang (id_penumpang, dibuat_pada),
    KEY idx_pesanan_driver (id_driver, dibuat_pada),
    KEY idx_pesanan_kendaraan (id_kendaraan),
    KEY idx_pesanan_cari (status, jemput_lat, jemput_lng),           -- BR-14
    CONSTRAINT fk_pesanan_penumpang FOREIGN KEY (id_penumpang)
        REFERENCES pengguna (id_pengguna) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_pesanan_driver FOREIGN KEY (id_driver)
        REFERENCES driver (id_driver) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_pesanan_kendaraan FOREIGN KEY (id_kendaraan)
        REFERENCES kendaraan (id_kendaraan) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_pesanan_harga CHECK (harga_ajuan > 0 AND (harga_disepakati IS NULL OR harga_disepakati > 0)),
    -- Setelah kesepakatan, driver dan harga wajib terisi (BR-12)
    CONSTRAINT ck_pesanan_sepakat CHECK (
        status IN ('mencari_driver','dibatalkan','kedaluwarsa')
        OR (id_driver IS NOT NULL AND harga_disepakati IS NOT NULL)
    ),
    CONSTRAINT ck_pesanan_koordinat CHECK (
        jemput_lat BETWEEN -90 AND 90 AND jemput_lng BETWEEN -180 AND 180
        AND tujuan_lat BETWEEN -90 AND 90 AND tujuan_lng BETWEEN -180 AND 180
    )
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. detail_pesanan
--    Pesanan 1 --- 1 Detail. Hasil perhitungan rute & tarif (FR-07),
--    rincian komisi/pendapatan bersih (FR-23, BR-22, BR-23) dan
--    waktu tiap tahap perjalanan.
-- ---------------------------------------------------------------------
CREATE TABLE detail_pesanan (
    id_pesanan         INT UNSIGNED  NOT NULL,
    jarak_km           DECIMAL(6,2)  NOT NULL,
    estimasi_menit     SMALLINT UNSIGNED NOT NULL,
    rute_geojson       MEDIUMTEXT    NULL COMMENT 'geometri rute OSRM untuk Leaflet',
    tarif_rekomendasi  INT           NOT NULL,
    harga_min          INT           NOT NULL COMMENT 'batas bawah rentang wajar (BR-06)',
    harga_maks         INT           NOT NULL COMMENT 'batas atas rentang wajar (BR-06)',
    persen_komisi      DECIMAL(5,2)  NULL COMMENT 'salinan pengaturan saat kesepakatan',
    nominal_komisi     INT           NULL,
    pendapatan_bersih  INT           NULL,
    status_komisi      ENUM('belum','dipotong','tertunda') NOT NULL DEFAULT 'belum',
    waktu_disepakati   DATETIME      NULL,
    waktu_dijemput     DATETIME      NULL,
    waktu_selesai      DATETIME      NULL,
    waktu_dibatalkan   DATETIME      NULL,
    PRIMARY KEY (id_pesanan),
    CONSTRAINT fk_detail_pesanan FOREIGN KEY (id_pesanan)
        REFERENCES pesanan (id_pesanan) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_detail_angka CHECK (jarak_km > 0 AND tarif_rekomendasi > 0),
    CONSTRAINT ck_detail_rentang CHECK (harga_min > 0 AND harga_min <= tarif_rekomendasi
                                        AND tarif_rekomendasi <= harga_maks),
    CONSTRAINT ck_detail_komisi CHECK (
        (nominal_komisi IS NULL AND pendapatan_bersih IS NULL)
        OR (nominal_komisi >= 0 AND pendapatan_bersih >= 0)
    ),
    CONSTRAINT ck_detail_persen CHECK (persen_komisi IS NULL OR persen_komisi BETWEEN 0 AND 100)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. tawaran
--    Pesanan 1 --- 0..* Tawaran; Driver 1 --- 0..* Tawaran.
--    Tiap pasangan (pesanan, driver) maksimal 3 ronde (BR-08).
--    Ronde 0 = harga_ajuan di tabel pesanan.
--    Hanya satu tawaran berstatus 'diterima' per pesanan (BR-12).
-- ---------------------------------------------------------------------
CREATE TABLE tawaran (
    id_tawaran        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_pesanan        INT UNSIGNED  NOT NULL,
    id_driver         INT UNSIGNED  NOT NULL COMMENT 'driver lawan negosiasi',
    ronde             TINYINT UNSIGNED NOT NULL,
    pengaju           ENUM('penumpang','driver') NOT NULL,
    harga             INT           NOT NULL,
    tawaran_terakhir  TINYINT(1) AS (IF(ronde = 3, 1, 0)) STORED,      -- BR-09
    status            ENUM('menunggu','diterima','ditolak','diganti','kedaluwarsa')
                      NOT NULL DEFAULT 'menunggu',
    dibuat_pada       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    kunci_diterima    INT UNSIGNED AS (IF(status = 'diterima', id_pesanan, NULL)) STORED,
    PRIMARY KEY (id_tawaran),
    UNIQUE KEY uq_tawaran_ronde (id_pesanan, id_driver, ronde),
    UNIQUE KEY uq_tawaran_satu_diterima (kunci_diterima),
    KEY idx_tawaran_driver (id_driver, status),
    CONSTRAINT fk_tawaran_pesanan FOREIGN KEY (id_pesanan)
        REFERENCES pesanan (id_pesanan) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_tawaran_driver FOREIGN KEY (id_driver)
        REFERENCES driver (id_driver) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_tawaran_ronde CHECK (ronde BETWEEN 1 AND 3),        -- BR-08
    CONSTRAINT ck_tawaran_harga CHECK (harga > 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. transaksi
--    Dompet 1 --- 0..* Transaksi; Pesanan 1 --- 0..1 Transaksi komisi;
--    Admin 1 --- 0..* Transaksi top-up. Setiap perubahan saldo tercatat (BR-25).
-- ---------------------------------------------------------------------
CREATE TABLE transaksi (
    id_transaksi   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_dompet      INT UNSIGNED  NOT NULL,
    jenis          ENUM('topup','komisi') NOT NULL,
    nominal        INT           NOT NULL,
    saldo_sebelum  INT           NOT NULL,
    saldo_sesudah  INT           NOT NULL,
    id_pesanan     INT UNSIGNED  NULL COMMENT 'wajib untuk komisi',
    id_admin       INT UNSIGNED  NULL COMMENT 'admin pelaku top-up; NULL = saldo awal sistem',
    keterangan     VARCHAR(255)  NULL,
    dibuat_pada    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_transaksi),
    UNIQUE KEY uq_transaksi_komisi_pesanan (id_pesanan),   -- 1 komisi per pesanan
    KEY idx_transaksi_dompet (id_dompet, dibuat_pada),
    KEY idx_transaksi_admin (id_admin),
    CONSTRAINT fk_transaksi_dompet FOREIGN KEY (id_dompet)
        REFERENCES dompet (id_dompet) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_transaksi_pesanan FOREIGN KEY (id_pesanan)
        REFERENCES pesanan (id_pesanan) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_transaksi_admin FOREIGN KEY (id_admin)
        REFERENCES pengguna (id_pengguna) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_transaksi_nominal CHECK (nominal > 0 AND saldo_sebelum >= 0 AND saldo_sesudah >= 0),
    CONSTRAINT ck_transaksi_jenis CHECK (
        (jenis = 'topup'  AND id_pesanan IS NULL AND saldo_sesudah = saldo_sebelum + nominal)
        OR (jenis = 'komisi' AND id_pesanan IS NOT NULL AND id_admin IS NULL
            AND saldo_sesudah = saldo_sebelum - nominal)
    )
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. rating
--     Pesanan 1 --- 0..2 Rating; Pengguna memberi & menerima rating.
--     Satu pihak sekali per perjalanan (BR-30).
-- ---------------------------------------------------------------------
CREATE TABLE rating (
    id_rating     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_pesanan    INT UNSIGNED  NOT NULL,
    id_pemberi    INT UNSIGNED  NOT NULL,
    id_penerima   INT UNSIGNED  NOT NULL,
    skor          TINYINT UNSIGNED NOT NULL,
    ulasan        VARCHAR(255)  NULL,
    dibuat_pada   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_rating),
    UNIQUE KEY uq_rating_sekali (id_pesanan, id_pemberi),   -- BR-30
    KEY idx_rating_penerima (id_penerima),
    KEY idx_rating_pemberi (id_pemberi),
    CONSTRAINT fk_rating_pesanan FOREIGN KEY (id_pesanan)
        REFERENCES pesanan (id_pesanan) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_rating_pemberi FOREIGN KEY (id_pemberi)
        REFERENCES pengguna (id_pengguna) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_rating_penerima FOREIGN KEY (id_penerima)
        REFERENCES pengguna (id_pengguna) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_rating_skor CHECK (skor BETWEEN 1 AND 5),  -- BR-30
    CONSTRAINT ck_rating_beda CHECK (id_pemberi <> id_penerima)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. laporan
--     Pengguna melaporkan / dilaporkan; Pesanan 1 --- 0..* Laporan;
--     Admin 1 --- 0..* Laporan (meninjau). (FR-28..FR-30, BR-26..BR-28)
-- ---------------------------------------------------------------------
CREATE TABLE laporan (
    id_laporan        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_pesanan        INT UNSIGNED  NOT NULL,
    id_pelapor        INT UNSIGNED  NOT NULL,
    id_terlapor       INT UNSIGNED  NOT NULL,
    kategori          ENUM('tidak_membayar','menunggu_terlalu_lama','perilaku_tidak_sopan',
                           'order_bermasalah','sengketa_tarif','pembatalan',
                           'penumpang_atau_driver_tidak_ditemukan','lainnya') NOT NULL,  -- BR-27
    deskripsi         TEXT          NOT NULL,
    foto_bukti_url    VARCHAR(255)  NULL,
    status            ENUM('baru','ditinjau','selesai','ditolak') NOT NULL DEFAULT 'baru',  -- BR-28
    id_admin_peninjau INT UNSIGNED  NULL,
    catatan_admin     TEXT          NULL,
    dibuat_pada       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ditinjau_pada     DATETIME      NULL,
    diselesaikan_pada DATETIME      NULL,
    PRIMARY KEY (id_laporan),
    UNIQUE KEY uq_laporan_satu_per_pesanan (id_pesanan, id_pelapor),  -- BR-26
    KEY idx_laporan_status (status, dibuat_pada),
    KEY idx_laporan_pelapor (id_pelapor),
    KEY idx_laporan_terlapor (id_terlapor),
    KEY idx_laporan_admin (id_admin_peninjau),
    CONSTRAINT fk_laporan_pesanan FOREIGN KEY (id_pesanan)
        REFERENCES pesanan (id_pesanan) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_laporan_pelapor FOREIGN KEY (id_pelapor)
        REFERENCES pengguna (id_pengguna) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_laporan_terlapor FOREIGN KEY (id_terlapor)
        REFERENCES pengguna (id_pengguna) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_laporan_admin FOREIGN KEY (id_admin_peninjau)
        REFERENCES pengguna (id_pengguna) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_laporan_beda CHECK (id_pelapor <> id_terlapor),
    -- Laporan yang sudah ditinjau/selesai/ditolak wajib punya admin peninjau
    CONSTRAINT ck_laporan_peninjau CHECK (status = 'baru' OR id_admin_peninjau IS NOT NULL)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 12. sanksi
--     Laporan 1 --- 0..1 Sanksi; Sanksi 0..* --- 1 Pengguna.
--     Wajib alasan tertulis (BR-29).
-- ---------------------------------------------------------------------
CREATE TABLE sanksi (
    id_sanksi       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_laporan      INT UNSIGNED  NOT NULL,
    id_pengguna     INT UNSIGNED  NOT NULL COMMENT 'pengguna yang dikenai sanksi',
    id_admin        INT UNSIGNED  NOT NULL,
    jenis           ENUM('peringatan','penangguhan_sementara','penonaktifan') NOT NULL,
    alasan          TEXT          NOT NULL,
    waktu_mulai     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    waktu_berakhir  DATETIME      NULL,
    PRIMARY KEY (id_sanksi),
    UNIQUE KEY uq_sanksi_laporan (id_laporan),     -- 0..1 sanksi per laporan
    KEY idx_sanksi_pengguna (id_pengguna, waktu_mulai),
    KEY idx_sanksi_admin (id_admin),
    CONSTRAINT fk_sanksi_laporan FOREIGN KEY (id_laporan)
        REFERENCES laporan (id_laporan) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_sanksi_pengguna FOREIGN KEY (id_pengguna)
        REFERENCES pengguna (id_pengguna) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_sanksi_admin FOREIGN KEY (id_admin)
        REFERENCES pengguna (id_pengguna) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_sanksi_alasan CHECK (CHAR_LENGTH(TRIM(alasan)) >= 10),   -- BR-29
    CONSTRAINT ck_sanksi_waktu CHECK (
        (jenis = 'penangguhan_sementara' AND waktu_berakhir IS NOT NULL AND waktu_berakhir > waktu_mulai)
        OR (jenis <> 'penangguhan_sementara' AND waktu_berakhir IS NULL)
    )
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- VIEW
-- ---------------------------------------------------------------------
-- Rata-rata rating yang diterima tiap pengguna (BR-31).
-- rata_rata NULL berarti tampilkan label "Baru".
CREATE VIEW v_rating_pengguna AS
SELECT p.id_pengguna,
       p.nama,
       p.peran,
       COUNT(r.id_rating)       AS jumlah_rating,
       ROUND(AVG(r.skor), 1)    AS rata_rata
FROM pengguna p
LEFT JOIN rating r ON r.id_penerima = p.id_pengguna
GROUP BY p.id_pengguna, p.nama, p.peran;

-- Ringkasan pesanan untuk riwayat & dasbor admin (FR-22, FR-32)
CREATE VIEW v_ringkasan_pesanan AS
SELECT ps.id_pesanan,
       ps.status,
       ps.dibuat_pada,
       pn.nama               AS nama_penumpang,
       pd.nama               AS nama_driver,
       k.plat_nomor,
       ps.jemput_alamat,
       ps.tujuan_alamat,
       dp.jarak_km,
       ps.harga_ajuan,
       ps.harga_disepakati,
       dp.nominal_komisi,
       dp.pendapatan_bersih,
       dp.status_komisi
FROM pesanan ps
JOIN pengguna pn            ON pn.id_pengguna = ps.id_penumpang
LEFT JOIN pengguna pd       ON pd.id_pengguna = ps.id_driver
LEFT JOIN kendaraan k       ON k.id_kendaraan = ps.id_kendaraan
LEFT JOIN detail_pesanan dp ON dp.id_pesanan  = ps.id_pesanan;
