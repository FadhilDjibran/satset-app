-- Jalankan setelah migration.sql. Data ini hanya untuk pengembangan lokal.
-- Password semua akun: Dummy12345!
-- Hash dibuat dengan PHP password_hash('Dummy12345!', PASSWORD_DEFAULT).
-- Tiap INSERT aman dijalankan ulang: akun yang sudah ada tidak diubah.
USE satset;

INSERT INTO `user` (nama, email, nomor_hp, password_hash, peran)
SELECT 'Budi Penumpang', 'budi.penumpang@dummy.satset.test', '081200000001',
       '$2y$12$hNCGErcRthKAzUdzcWDpkeJ4c6OLGyziyLheTKjPe7GmpTkI1rFJi', 'penumpang'
WHERE NOT EXISTS (
  SELECT 1 FROM `user`
  WHERE email = 'budi.penumpang@dummy.satset.test' OR nomor_hp = '081200000001'
);

INSERT INTO `user` (nama, email, nomor_hp, password_hash, peran)
SELECT 'Sari Driver', 'sari.driver@dummy.satset.test', '081200000002',
       '$2y$12$hNCGErcRthKAzUdzcWDpkeJ4c6OLGyziyLheTKjPe7GmpTkI1rFJi', 'driver'
WHERE NOT EXISTS (
  SELECT 1 FROM `user`
  WHERE email = 'sari.driver@dummy.satset.test' OR nomor_hp = '081200000002'
);

-- Admin hanya dibuat lewat seed/sistem, bukan endpoint registrasi publik.
INSERT INTO `user` (nama, email, nomor_hp, password_hash, peran)
SELECT 'Admin SATSET', 'admin@dummy.satset.test', '081200000003',
       '$2y$12$hNCGErcRthKAzUdzcWDpkeJ4c6OLGyziyLheTKjPe7GmpTkI1rFJi', 'admin'
WHERE NOT EXISTS (
  SELECT 1 FROM `user`
  WHERE email = 'admin@dummy.satset.test' OR nomor_hp = '081200000003'
);
