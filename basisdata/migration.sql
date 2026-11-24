-- Skema awal akun SATSET. Jalankan sekali melalui mysql atau tab SQL phpMyAdmin.
CREATE DATABASE IF NOT EXISTS satset
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE satset;

CREATE TABLE IF NOT EXISTS `user` (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL,
  nomor_hp VARCHAR(20) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  peran ENUM('penumpang', 'driver', 'admin') NOT NULL,
  status_akun ENUM('aktif', 'ditangguhkan') NOT NULL DEFAULT 'aktif',
  foto_profil VARCHAR(255) DEFAULT NULL,
  dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_email (email),
  UNIQUE KEY uq_user_nomor_hp (nomor_hp)
) ENGINE=InnoDB;
