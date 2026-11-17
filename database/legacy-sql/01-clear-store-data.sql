-- ============================================================================
-- 01 — Bersihkan data toko Mitra Utama (jalankan SEBELUM 02-migrate-items.sql)
--
-- Database sasaran : u225207914_store_mu
-- Yang DIHAPUS     : barang (produk, satuan produk, merek, kategori, satuan) dan semua data transaksi
--                    (penjualan, retur, pembatalan, surat jalan, pesanan, pengeluaran, shift, tutup hari,
--                    opname, stok & mutasi stok, urutan nomor dokumen, audit).
-- Yang DIPERTAHANKAN: gudang, kasir (register), pengaturan struk, kategori pengeluaran, alasan opname,
--                    sopir, tabel migrations. Hanya counter nomor nota register direset ke 0.
--
-- TIDAK BISA DIBATALKAN. Ambil backup dulu:
--   mysqldump -uroot u225207914_store_mu > backup-u225207914_store_mu.sql
-- Jalankan:
--   mysql -uroot u225207914_store_mu < database/legacy-sql/01-clear-store-data.sql
-- ============================================================================

USE u225207914_store_mu;

-- Memakai DELETE berurutan (anak dulu, induk kemudian), bukan TRUNCATE: TRUNCATE ditolak MySQL/MariaDB di hosting
-- bila tabelnya dirujuk foreign key (galat 1701), walau SET FOREIGN_KEY_CHECKS = 0.
START TRANSACTION;

-- Transaksi: retur, pembatalan, surat jalan, pesanan pelanggan
DELETE FROM sale_return_items;
DELETE FROM sale_returns;
DELETE FROM sale_cancellations;
DELETE FROM delivery_note_items;
DELETE FROM delivery_note_sales;
DELETE FROM delivery_notes;
DELETE FROM customer_order_payments;
DELETE FROM customer_order_items;
DELETE FROM customer_orders;

-- Transaksi: penjualan, pengeluaran, shift kasir, tutup hari
DELETE FROM sale_payments;
DELETE FROM sale_items;
DELETE FROM sales;
DELETE FROM expenses;
DELETE FROM daily_closings;
DELETE FROM cash_shifts;

-- Opname, stok, mutasi stok
DELETE FROM stock_opname_items;
DELETE FROM stock_opnames;
DELETE FROM stock_movements;
DELETE FROM stocks;

-- Barang beserta data acuannya
DELETE FROM product_units;
DELETE FROM products;
DELETE FROM brands;
DELETE FROM categories;
DELETE FROM units;

-- Nomor dokumen (INV, RET, SJ, OPN, ...) dan jejak audit mulai dari awal
DELETE FROM document_sequences;
DELETE FROM audits;

UPDATE registers SET last_sale_sequence = 0;

COMMIT;

-- DELETE tidak mengembalikan penomoran id; setel ulang supaya mulai dari 1 seperti toko baru.
ALTER TABLE sale_return_items AUTO_INCREMENT = 1;
ALTER TABLE sale_returns AUTO_INCREMENT = 1;
ALTER TABLE sale_cancellations AUTO_INCREMENT = 1;
ALTER TABLE delivery_note_items AUTO_INCREMENT = 1;
ALTER TABLE delivery_notes AUTO_INCREMENT = 1;
ALTER TABLE customer_order_payments AUTO_INCREMENT = 1;
ALTER TABLE customer_order_items AUTO_INCREMENT = 1;
ALTER TABLE customer_orders AUTO_INCREMENT = 1;
ALTER TABLE sale_payments AUTO_INCREMENT = 1;
ALTER TABLE sale_items AUTO_INCREMENT = 1;
ALTER TABLE sales AUTO_INCREMENT = 1;
ALTER TABLE expenses AUTO_INCREMENT = 1;
ALTER TABLE daily_closings AUTO_INCREMENT = 1;
ALTER TABLE cash_shifts AUTO_INCREMENT = 1;
ALTER TABLE stock_opname_items AUTO_INCREMENT = 1;
ALTER TABLE stock_opnames AUTO_INCREMENT = 1;
ALTER TABLE stock_movements AUTO_INCREMENT = 1;
ALTER TABLE product_units AUTO_INCREMENT = 1;
ALTER TABLE products AUTO_INCREMENT = 1;
ALTER TABLE brands AUTO_INCREMENT = 1;
ALTER TABLE categories AUTO_INCREMENT = 1;
ALTER TABLE audits AUTO_INCREMENT = 1;

-- Hasil: semua baris di bawah harus 0.
SELECT 'products' AS tabel, COUNT(*) AS jumlah FROM products
UNION ALL SELECT 'product_units', COUNT(*) FROM product_units
UNION ALL SELECT 'brands', COUNT(*) FROM brands
UNION ALL SELECT 'categories', COUNT(*) FROM categories
UNION ALL SELECT 'units', COUNT(*) FROM units
UNION ALL SELECT 'stocks', COUNT(*) FROM stocks
UNION ALL SELECT 'stock_movements', COUNT(*) FROM stock_movements
UNION ALL SELECT 'stock_opnames', COUNT(*) FROM stock_opnames
UNION ALL SELECT 'cash_shifts', COUNT(*) FROM cash_shifts
UNION ALL SELECT 'sales', COUNT(*) FROM sales
UNION ALL SELECT 'sale_returns', COUNT(*) FROM sale_returns
UNION ALL SELECT 'delivery_notes', COUNT(*) FROM delivery_notes
UNION ALL SELECT 'customer_orders', COUNT(*) FROM customer_orders
UNION ALL SELECT 'expenses', COUNT(*) FROM expenses
UNION ALL SELECT 'daily_closings', COUNT(*) FROM daily_closings
UNION ALL SELECT 'document_sequences', COUNT(*) FROM document_sequences
UNION ALL SELECT 'audits', COUNT(*) FROM audits;
