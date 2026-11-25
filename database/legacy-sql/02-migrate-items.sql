-- ============================================================================
-- 02 — Isi semua barang (data siap pakai, tidak membaca database lain)
-- (jalankan SETELAH 01-clear-store-data.sql)
--
-- Database sasaran : u225207914_store_mu
-- Isi              : 40 satuan, 215 kategori, 462 merek, 3858 barang (3847 aktif) dan 4010 satuan barang
--                    (152 di antaranya satuan tambahan selain satuan dasar).
-- Syarat           : migrasi tenant 2026_11_25_094904_add_is_conversion_confirmed_to_product_units_table sudah dijalankan
--                    (kolom product_units.is_conversion_confirmed ada). Jalankan php artisan tenants:migrate dulu.
-- Stok             : TIDAK diisi. Stok mulai dari 0; isi lewat stok opname. Transaksi tidak dimigrasi.
-- Sumber           : DB lama mitra_utama_existing per 17 Nov 2026, SEMUA barang (pemilik Mitra Utama dan Cahaya Baru), diturunkan dengan aturan
--                    app/Services/LegacyProductImporter.php:
--   * SKU = kode lama tanpa spasi ("MU 2409" -> "MU-2409" karena bentrok dengan "MU2409"); kode lama ada di products.legacy_sku.
--   * Satuan dasar = satuan harga beli; harga belinya = modal (0 bila data lama belum punya modal).
--   * Satuan tambahan yang isi per satuan dasarnya pasti masuk apa adanya.
--   * Satuan tambahan yang isinya TIDAK pasti (105 satuan di 97 barang, konversi lama 1 atau 0) tetap masuk dengan
--     konversi 1 dan ditandai BELUM DIKONFIRMASI (is_conversion_confirmed = 0). Satuan itu tidak bisa dijual, diopname, atau dicetak
--     labelnya sampai admin mengisi konversi yang benar (selain 1) di data barang; begitu disimpan, satuan itu dianggap terkonfirmasi.
--     Jumlahnya tampil di dashboard toko;
--     daftarnya ada di bagian paling bawah file ini.
--   * Kategori kosong -> "Lain-lain"; merek kosong -> merek "-" (989 barang); barcode diisi oleh 03-migrate-barcodes.sql.
-- Aman dijalankan ulang (INSERT IGNORE): satuan, kategori, merek, dan barang (dikenali lewat SKU) yang sudah ada dilewati dan TIDAK diubah.
-- Satuan barang hanya ditambahkan untuk barang yang baru dibuat oleh run ini, jadi barang lama tidak bertambah satuannya.
--
-- Jalankan:
--   mysql -u<user> -p u225207914_store_mu < database/legacy-sql/02-migrate-items.sql
-- ============================================================================

USE u225207914_store_mu;
SET NAMES utf8mb4;
SET @now = NOW();

START TRANSACTION;

-- Satuan
INSERT IGNORE INTO units (code, name, created_at, updated_at)
VALUES
('100 METER', '100 Meter', @now, @now),
('100GR-110PCS', '100Gr/110Pcs', @now, @now),
('100GR-145PCS', '100Gr/145Pcs', @now, @now),
('2-1000', '2/1000', @now, @now),
('25 METER', '25 Meter', @now, @now),
('3-1000', '3/1000', @now, @now),
('3PCS', '3Pcs', @now, @now),
('4-1000', '4/1000', @now, @now),
('50 METER', '50 Meter', @now, @now),
('80 METER', '80 Meter', @now, @now),
('AMBIL SENDIRI', 'Ambil Sendiri', @now, @now),
('BKS', 'Bungkus', @now, @now),
('BTG', 'Batang', @now, @now),
('BTL', 'Botol', @now, @now),
('DUS', 'Dus', @now, @now),
('DUS-PCS', 'Dus/Pcs', @now, @now),
('GEN', 'Gen', @now, @now),
('GLN', 'Galon', @now, @now),
('GR', 'Gram', @now, @now),
('GROSIR-LEMBAR', 'Grosir-Lembar', @now, @now),
('GROSIR-PCS', 'Grosir-Pcs', @now, @now),
('IKAT', 'Ikat', @now, @now),
('KAIZEN', 'Kaizen', @now, @now),
('KG', 'Kilogram', @now, @now),
('KLG', 'Kaleng', @now, @now),
('KUBIK', 'Kubik', @now, @now),
('LBR', 'Lembar', @now, @now),
('LSN', 'Lusin', @now, @now),
('LTR', 'Liter', @now, @now),
('MTR', 'Meter', @now, @now),
('PAIL', 'Pail', @now, @now),
('PAK', 'Pak', @now, @now),
('PCS', 'Pieces', @now, @now),
('PER 10', 'Per 10', @now, @now),
('POT', 'Pot', @now, @now),
('PSG', 'Pasang', @now, @now),
('ROL', 'Roll', @now, @now),
('SAK', 'Sak', @now, @now),
('SET', 'Set', @now, @now),
('UNIT', 'Unit', @now, @now);

-- Kategori (dari jenis lama)
INSERT IGNORE INTO categories (name, created_at, updated_at)
VALUES
('ACCU', @now, @now),
('AEROSOL', @now, @now),
('AIR AKI', @now, @now),
('AKSESORIS', @now, @now),
('ALAT', @now, @now),
('ALAT DAPUR', @now, @now),
('ALAT LISTRIK', @now, @now),
('ALAT UKUR', @now, @now),
('AMPLAS', @now, @now),
('BAJA RINGAN', @now, @now),
('BAK', @now, @now),
('BAKUL', @now, @now),
('BAN', @now, @now),
('BASKOM', @now, @now),
('BATA', @now, @now),
('BATRE', @now, @now),
('BATU GOSOK', @now, @now),
('BAUT', @now, @now),
('BESI', @now, @now),
('BETEL', @now, @now),
('BLENDER', @now, @now),
('BOX MAKANAN', @now, @now),
('BOX MCB', @now, @now),
('BUSI', @now, @now),
('CAIRAN', @now, @now),
('CANGKUL', @now, @now),
('CAPITAN', @now, @now),
('CAT', @now, @now),
('CERMIN', @now, @now),
('CETAKAN', @now, @now),
('CETOK', @now, @now),
('CLOSET', @now, @now),
('CONTAINER', @now, @now),
('CORONG', @now, @now),
('CUTTER', @now, @now),
('CYLINDER', @now, @now),
('DEMPUL', @now, @now),
('DISPENSER', @now, @now),
('DODOS', @now, @now),
('DONGKRAK', @now, @now),
('DRUM', @now, @now),
('EMBER', @now, @now),
('ENGSEL', @now, @now),
('EPOXI', @now, @now),
('ESKOM', @now, @now),
('FILTER', @now, @now),
('FISHER', @now, @now),
('FITING', @now, @now),
('GAGANG PINTU', @now, @now),
('GANTUNGAN', @now, @now),
('GAS', @now, @now),
('GAYUNG', @now, @now),
('GEMBOK', @now, @now),
('GEMUK', @now, @now),
('GEN', @now, @now),
('GERGAJI', @now, @now),
('GEROBAK', @now, @now),
('GLASSBLOCK', @now, @now),
('GORDEN', @now, @now),
('GRENDEL', @now, @now),
('GUNTING', @now, @now),
('HAK ANGIN', @now, @now),
('IRUS', @now, @now),
('ISOLASI', @now, @now),
('ISOLATOR', @now, @now),
('JARING', @now, @now),
('JEPIT IKAN', @now, @now),
('KABEL', @now, @now),
('KACA', @now, @now),
('KACAMATA', @now, @now),
('KAIN LAP', @now, @now),
('KAMPAS', @now, @now),
('KANEBO', @now, @now),
('KAPI', @now, @now),
('KARPET', @now, @now),
('KAWAT', @now, @now),
('KAWAT LAS', @now, @now),
('KAWAT RAM', @now, @now),
('KEPALA COK', @now, @now),
('KERAMIK', @now, @now),
('KERANJANG', @now, @now),
('KESET', @now, @now),
('KIKIR', @now, @now),
('KIPAS', @now, @now),
('KLEM', @now, @now),
('KLEM TARIK', @now, @now),
('KLEP', @now, @now),
('KOKER', @now, @now),
('KOMPOR', @now, @now),
('KRAN', @now, @now),
('Kuas', @now, @now),
('KUKU MACAN', @now, @now),
('Kunci', @now, @now),
('KUNCI PINTU', @now, @now),
('KURSI', @now, @now),
('LAKBAN', @now, @now),
('LAMPU', @now, @now),
('LEM', @now, @now),
('LINGGIS', @now, @now),
('LIST PLAFON', @now, @now),
('MAGICCOM', @now, @now),
('MATA BOR', @now, @now),
('MATA GURINDA', @now, @now),
('MATA ROUTER', @now, @now),
('MATA SERKEL', @now, @now),
('MCB', @now, @now),
('MENIE', @now, @now),
('MESIN', @now, @now),
('METERAN', @now, @now),
('MIKSER', @now, @now),
('MUR', @now, @now),
('NAMPAN', @now, @now),
('NYLON', @now, @now),
('OBAT', @now, @now),
('OBENG', @now, @now),
('OLI', @now, @now),
('OVEN', @now, @now),
('OVERVAL', @now, @now),
('PAKING', @now, @now),
('Paku', @now, @now),
('PALU', @now, @now),
('PANCI', @now, @now),
('PARANG', @now, @now),
('PARUT', @now, @now),
('PEL LANTAI', @now, @now),
('PEMBERAT JARING', @now, @now),
('PEMBERSIH', @now, @now),
('PENGEPEL', @now, @now),
('PENGGOSOK', @now, @now),
('PENSIL', @now, @now),
('PILLOX', @now, @now),
('PINTU', @now, @now),
('PIPA', @now, @now),
('PIRING', @now, @now),
('PISAU', @now, @now),
('PLAMIR', @now, @now),
('PLASTIK', @now, @now),
('PLASTIK PAGAR', @now, @now),
('PLYWOOD', @now, @now),
('POMPA', @now, @now),
('POT', @now, @now),
('PRASMANAN', @now, @now),
('PROFIL', @now, @now),
('RANTAI', @now, @now),
('RICE COOKER', @now, @now),
('RODA', @now, @now),
('S/PART', @now, @now),
('SAKLAR', @now, @now),
('SAMBUNGAN', @now, @now),
('SAPU', @now, @now),
('SARINGAN', @now, @now),
('SARUNG TANGAN', @now, @now),
('SEKOP', @now, @now),
('SELANG', @now, @now),
('SEMEN', @now, @now),
('SEMPROTAN', @now, @now),
('SENDOK', @now, @now),
('SENDOK MAKAN', @now, @now),
('SENDOK NASI', @now, @now),
('SENDOK SEMEN', @now, @now),
('SENG', @now, @now),
('SEPATU', @now, @now),
('SERAT', @now, @now),
('SEROK', @now, @now),
('SETRIKA', @now, @now),
('SHAMPO', @now, @now),
('SHOWER', @now, @now),
('SIKAT', @now, @now),
('SIKU TUKANG', @now, @now),
('SKRUP', @now, @now),
('SLANG', @now, @now),
('SOLASI', @now, @now),
('SOLDER', @now, @now),
('SOLET', @now, @now),
('SPAN', @now, @now),
('SPARE PART', @now, @now),
('SPRAYER', @now, @now),
('STEKER', @now, @now),
('STRAPLESS', @now, @now),
('SUMBU', @now, @now),
('SUTIL', @now, @now),
('TALANG', @now, @now),
('TALENAN', @now, @now),
('TALI', @now, @now),
('TANG', @now, @now),
('TANGGA', @now, @now),
('TAPISAN', @now, @now),
('TAPLAK', @now, @now),
('TARIKAN LACI', @now, @now),
('TARIKAN PINTU', @now, @now),
('TATAH', @now, @now),
('TEER', @now, @now),
('TEFLON', @now, @now),
('TEHEL', @now, @now),
('TEMPAT', @now, @now),
('TERMINAL COK', @now, @now),
('TERMINALJ', @now, @now),
('TERMOS AIR', @now, @now),
('TERMOS NASI', @now, @now),
('TERPAL', @now, @now);

INSERT IGNORE INTO categories (name, created_at, updated_at)
VALUES
('TES PEN', @now, @now),
('THINNER', @now, @now),
('TIKAR', @now, @now),
('TIMBANGAN', @now, @now),
('TINNER', @now, @now),
('TOPENG', @now, @now),
('TOPLES', @now, @now),
('TRIPLEK', @now, @now),
('TROWOL', @now, @now),
('TUDUNG SAJI', @now, @now),
('V-BELT', @now, @now),
('VERNIS', @now, @now),
('WAJAN', @now, @now),
('WASHTAFEL', @now, @now),
('WATERPASS', @now, @now);

-- Merek
INSERT IGNORE INTO brands (name, created_at, updated_at)
VALUES
('2 MACAN', @now, @now),
('555', @now, @now),
('808', @now, @now),
('A&N', @now, @now),
('ACCURA', @now, @now),
('ACLOSE', @now, @now),
('ADDIBOND', @now, @now),
('ADDITON HE', @now, @now),
('AFOS', @now, @now),
('AIWO', @now, @now),
('ALDO', @now, @now),
('ALFAGLOSS', @now, @now),
('ALKALINE', @now, @now),
('ALTEX', @now, @now),
('ALWIN', @now, @now),
('AMICO', @now, @now),
('ANANTA', @now, @now),
('ANDOZA', @now, @now),
('ANGSA', @now, @now),
('AP', @now, @now),
('ARASHI', @now, @now),
('ARCADIA', @now, @now),
('ARCEL', @now, @now),
('ARIES', @now, @now),
('ARIES GOLD', @now, @now),
('ARISTA', @now, @now),
('ARMATUREN', @now, @now),
('ART', @now, @now),
('ARTCO', @now, @now),
('ARWANA', @now, @now),
('ASIA TILE', @now, @now),
('ATLANTIS', @now, @now),
('ATN', @now, @now),
('ATS', @now, @now),
('AURORA', @now, @now),
('AUTOSOL', @now, @now),
('AVIA', @now, @now),
('Avian', @now, @now),
('AVITEX', @now, @now),
('BAHCO', @now, @now),
('BANDO', @now, @now),
('BANO', @now, @now),
('BE ONE', @now, @now),
('BELOCCA', @now, @now),
('BENZ', @now, @now),
('BERLIN', @now, @now),
('BERLUTI', @now, @now),
('BERRY', @now, @now),
('BISON', @now, @now),
('BLACKFOOT', @now, @now),
('BLADE', @now, @now),
('BLITAR', @now, @now),
('BLITZ', @now, @now),
('BONUCCI', @now, @now),
('BOSCH', @now, @now),
('BOSHINI', @now, @now),
('BOYO', @now, @now),
('BRABUS', @now, @now),
('BROCO', @now, @now),
('BUAYA', @now, @now),
('CAB', @now, @now),
('CALISTA', @now, @now),
('CAP JAGO', @now, @now),
('CAP TANGAN', @now, @now),
('CARRER', @now, @now),
('CASTERS', @now, @now),
('CASTOL', @now, @now),
('CAT', @now, @now),
('CHING KIANG BRAND', @now, @now),
('CHIYODA', @now, @now),
('CITICON', @now, @now),
('CLARIS', @now, @now),
('CLARITA', @now, @now),
('CLASSIC', @now, @now),
('CLIO', @now, @now),
('CLIPPER', @now, @now),
('CORALEY', @now, @now),
('CORE', @now, @now),
('COSMOS', @now, @now),
('CROCODILE', @now, @now),
('DELTA', @now, @now),
('DENALI', @now, @now),
('DENGFENG', @now, @now),
('DEROCCA', @now, @now),
('DESTRONK', @now, @now),
('DEXTA', @now, @now),
('DEXTONE', @now, @now),
('DIAMOND', @now, @now),
('DINEMATE', @now, @now),
('DINNER WARE', @now, @now),
('DITTON', @now, @now),
('DOMAX', @now, @now),
('DOORMART', @now, @now),
('DOZIRO', @now, @now),
('DRAGON', @now, @now),
('DUMBO', @now, @now),
('E-B', @now, @now),
('EAGLE', @now, @now),
('EAST', @now, @now),
('ECLAT', @now, @now),
('ECOVA', @now, @now),
('ED', @now, @now),
('EKO', @now, @now),
('EKONOMAT', @now, @now),
('ELBRIS', @now, @now),
('ELEPHANT', @now, @now),
('ELGLADIO', @now, @now),
('ELGLADO', @now, @now),
('ELMES', @now, @now),
('ELSEN', @now, @now),
('ELYSSION', @now, @now),
('ENDURO', @now, @now),
('ENGLAND', @now, @now),
('EPIC', @now, @now),
('ERNESTO', @now, @now),
('ETERNA', @now, @now),
('EYE', @now, @now),
('FAWI', @now, @now),
('FEDERAL', @now, @now),
('FIBER', @now, @now),
('FISCH', @now, @now),
('FIX', @now, @now),
('FLASH', @now, @now),
('FLEXOVIT', @now, @now),
('FLO', @now, @now),
('FN', @now, @now),
('FORTUNA', @now, @now),
('FOX', @now, @now),
('FRANCO', @now, @now),
('FREDER', @now, @now),
('FRT', @now, @now),
('FUJI STAR', @now, @now),
('FUJIYAMA', @now, @now),
('FUKUDA', @now, @now),
('FUKUNG', @now, @now),
('G', @now, @now),
('GAJAH', @now, @now),
('GARFIELD', @now, @now),
('GETA', @now, @now),
('GG', @now, @now),
('GIANOCOLO', @now, @now),
('GIANT', @now, @now),
('GINZO', @now, @now),
('GLADIO', @now, @now),
('GLOBE', @now, @now),
('GLX', @now, @now),
('GNT', @now, @now),
('GONEO', @now, @now),
('GOOD', @now, @now),
('GRASIA TILE', @now, @now),
('GRAVINDO', @now, @now),
('GRESIK', @now, @now),
('GRT', @now, @now),
('GT SUPER', @now, @now),
('GUNINDO', @now, @now),
('HACHIDA', @now, @now),
('HAITI', @now, @now),
('HAMPER', @now, @now),
('HAND PUMP', @now, @now),
('HANDSOME', @now, @now),
('HARDWARES', @now, @now),
('HASSTON', @now, @now),
('HD', @now, @now),
('HENDSO', @now, @now),
('HERCULES', @now, @now),
('HI TEE', @now, @now),
('HIDROFILL', @now, @now),
('HOCK', @now, @now),
('HOMESTAR', @now, @now),
('HONDA', @now, @now),
('HORRISON', @now, @now),
('HPP', @now, @now),
('HSJ', @now, @now),
('HSS', @now, @now),
('HUANLI', @now, @now),
('HUBEN', @now, @now),
('HYPERLITE', @now, @now),
('I MOTO', @now, @now),
('IDEKU', @now, @now),
('IGM', @now, @now),
('IGUANA', @now, @now),
('IKAD', @now, @now),
('IKIMURA', @now, @now),
('IMOTO', @now, @now),
('IMTAN', @now, @now),
('IMUNDEX', @now, @now),
('INDIA', @now, @now),
('INDIAN', @now, @now),
('INDOGLASS', @now, @now),
('INNOPLAS', @now, @now),
('IRC', @now, @now),
('IRON', @now, @now),
('ISARPLAS', @now, @now),
('ISCO', @now, @now),
('ITC', @now, @now),
('JAWA', @now, @now),
('JAYA', @now, @now),
('JAYA MULIA', @now, @now),
('JCK', @now, @now),
('JORAN', @now, @now);

INSERT IGNORE INTO brands (name, created_at, updated_at)
VALUES
('JUNIOR', @now, @now),
('JUPITER', @now, @now),
('KAISHA', @now, @now),
('KAWI', @now, @now),
('KEN MASTER', @now, @now),
('KENKO', @now, @now),
('KENZO', @now, @now),
('KEYOLA', @now, @now),
('KIMITOS', @now, @now),
('KIMORA', @now, @now),
('KING', @now, @now),
('KINGSTONE', @now, @now),
('KINIK', @now, @now),
('KIRIN', @now, @now),
('KITAKO', @now, @now),
('KMP', @now, @now),
('KOALA', @now, @now),
('KODAI', @now, @now),
('KRANZ', @now, @now),
('KRISBOW', @now, @now),
('KUDA', @now, @now),
('KUDA TERBANG', @now, @now),
('KURA', @now, @now),
('KYOSO', @now, @now),
('KZK', @now, @now),
('LAFMA', @now, @now),
('LAKONI', @now, @now),
('LEGION', @now, @now),
('LEMONY', @now, @now),
('LEONA', @now, @now),
('LION STAR', @now, @now),
('LIPPRO', @now, @now),
('LISTPRO', @now, @now),
('LOBSTER', @now, @now),
('LOVE', @now, @now),
('LOVINA', @now, @now),
('LUCKY', @now, @now),
('LUCKY STAR', @now, @now),
('LUMBA-LUMBA', @now, @now),
('MADICO', @now, @now),
('MAGHNUM', @now, @now),
('MAJESTY', @now, @now),
('MAKITA', @now, @now),
('MAKITA MT', @now, @now),
('MAKTEC', @now, @now),
('MAS', @now, @now),
('MASPION', @now, @now),
('MASTER', @now, @now),
('MATA', @now, @now),
('MATAHARI', @now, @now),
('MATRIX', @now, @now),
('MATSUI', @now, @now),
('MAXIM', @now, @now),
('MDK', @now, @now),
('MDN', @now, @now),
('MDR', @now, @now),
('MEDITRAN', @now, @now),
('MEGACOOLS', @now, @now),
('MEIKO', @now, @now),
('MERCURY', @now, @now),
('MESRAN', @now, @now),
('MIDEA', @now, @now),
('MILLIARD', @now, @now),
('MILLION', @now, @now),
('MITA PRODUK', @now, @now),
('MIYAKO', @now, @now),
('MIYAMOTO', @now, @now),
('MIYOKO', @now, @now),
('MKK', @now, @now),
('MOLLAR', @now, @now),
('MONTANA', @now, @now),
('MORIMOTO', @now, @now),
('MORTAR', @now, @now),
('MORTISE', @now, @now),
('MUGEN', @now, @now),
('MULLER', @now, @now),
('MULTIPLAST', @now, @now),
('MURAKI', @now, @now),
('NACHI', @now, @now),
('NAGATA', @now, @now),
('NAGOYA', @now, @now),
('NANOLITE', @now, @now),
('NATIONAL', @now, @now),
('NEVADA', @now, @now),
('NEW STAR', @now, @now),
('NEW WEST', @now, @now),
('NEW WEST GOLD', @now, @now),
('NEWEST', @now, @now),
('NGK', @now, @now),
('NIKITA', @now, @now),
('NINJA', @now, @now),
('Nippon', @now, @now),
('NIPPON PAINT', @now, @now),
('NISHIO', @now, @now),
('NISO', @now, @now),
('NO DROP', @now, @now),
('NOL TIGA', @now, @now),
('NONA', @now, @now),
('NOQIWA', @now, @now),
('OENTOENG', @now, @now),
('OKAYAMA', @now, @now),
('OLIMPIA', @now, @now),
('OLIQ', @now, @now),
('OPPLE', @now, @now),
('ORCHID', @now, @now),
('OREGON', @now, @now),
('ORIS', @now, @now),
('ORNAMEN', @now, @now),
('ORYX', @now, @now),
('OSIWA', @now, @now),
('OWNER', @now, @now),
('PAGODA', @now, @now),
('PAJERO', @now, @now),
('PAK TUKANG', @now, @now),
('PALEM', @now, @now),
('PANASONIC', @now, @now),
('PEDANG', @now, @now),
('PERDANA', @now, @now),
('PERMATA KERAMIK', @now, @now),
('PESONA 112', @now, @now),
('PHILIP', @now, @now),
('PIKOLITE', @now, @now),
('PINGUIN', @now, @now),
('PIONEER', @now, @now),
('PLATINUM', @now, @now),
('POLAR', @now, @now),
('POLIBEST', @now, @now),
('POLYSTAR', @now, @now),
('POPEYE', @now, @now),
('POWER', @now, @now),
('POWER SERIES', @now, @now),
('PRIMAX', @now, @now),
('PRIMERO', @now, @now),
('PROFILA 88', @now, @now),
('PROHEX', @now, @now),
('PUSH ON', @now, @now),
('Q2', @now, @now),
('QUAD', @now, @now),
('RABBIT', @now, @now),
('RADAR', @now, @now),
('RAINBOW', @now, @now),
('RAJAWALI', @now, @now),
('RAJUT', @now, @now),
('RAPIT', @now, @now),
('RB 2.6', @now, @now),
('RED FOX', @now, @now),
('RIVOTI', @now, @now),
('RORED', @now, @now),
('ROSSA', @now, @now),
('ROTARY', @now, @now),
('ROYAL', @now, @now),
('ROYALPLAST', @now, @now),
('RRT', @now, @now),
('RUBICON', @now, @now),
('RUSA HITAM', @now, @now),
('S-LOCK', @now, @now),
('SAFILO', @now, @now),
('SAGOLA', @now, @now),
('SAILING', @now, @now),
('SAKAI', @now, @now),
('SAKURA', @now, @now),
('SANPOLAC', @now, @now),
('SANSHAN', @now, @now),
('SANXUE', @now, @now),
('SANYO', @now, @now),
('SCHLIEPER', @now, @now),
('SCHNEIDER', @now, @now),
('SCISSORS', @now, @now),
('SCRAPER', @now, @now),
('SEA GULL', @now, @now),
('SEAL WARE', @now, @now),
('SEGITIGA', @now, @now),
('SEKAI', @now, @now),
('SELLERY', @now, @now),
('SENGON', @now, @now),
('SERENITY', @now, @now),
('SHF', @now, @now),
('SHIMIZU', @now, @now),
('SHOPIE', @now, @now),
('SINDA', @now, @now),
('SKF.SKC', @now, @now),
('SNI', @now, @now),
('SOBAR', @now, @now),
('SOLIGEN', @now, @now),
('SOLO', @now, @now),
('SONDA', @now, @now),
('SOS', @now, @now),
('SPEED', @now, @now),
('SQUASH', @now, @now),
('STAINLISSTEEL', @now, @now),
('STALLION', @now, @now),
('STAMCO', @now, @now),
('STAVIC', @now, @now),
('STIHLL', @now, @now),
('STRAUSS', @now, @now),
('SUMMO', @now, @now),
('SUPER', @now, @now),
('SUPRA', @now, @now),
('SUWO', @now, @now),
('SUZUKA', @now, @now);

INSERT IGNORE INTO brands (name, created_at, updated_at)
VALUES
('TAIWAN', @now, @now),
('TAIYO', @now, @now),
('TANDON', @now, @now),
('TB', @now, @now),
('TECH WELL', @now, @now),
('TEER', @now, @now),
('TEKIPO', @now, @now),
('TEKIRO', @now, @now),
('TIERA TILE', @now, @now),
('TIGER BABY', @now, @now),
('TIRIMAN', @now, @now),
('TOHO', @now, @now),
('TOLEDO', @now, @now),
('TONASA', @now, @now),
('TOP', @now, @now),
('TORPEDO LEVEL', @now, @now),
('TRIANGLE', @now, @now),
('TRILLIUN', @now, @now),
('TRIPLE S', @now, @now),
('TRS 66', @now, @now),
('TURBO', @now, @now),
('TY', @now, @now),
('TYRO', @now, @now),
('UCHIDA', @now, @now),
('UTICON', @now, @now),
('VALLEN', @now, @now),
('VANDA', @now, @now),
('VARIA', @now, @now),
('VICO', @now, @now),
('VINO', @now, @now),
('VIPER', @now, @now),
('VNZ', @now, @now),
('VOXY', @now, @now),
('VPR', @now, @now),
('VRP', @now, @now),
('VRZ', @now, @now),
('VTECH', @now, @now),
('WAKAMOTO', @now, @now),
('WANLY', @now, @now),
('WARLOCK', @now, @now),
('WD', @now, @now),
('WEBER', @now, @now),
('WEITELANG', @now, @now),
('WIKEA', @now, @now),
('WILA', @now, @now),
('WILSON', @now, @now),
('WINN GAS', @now, @now),
('WINNER', @now, @now),
('WIPRO', @now, @now),
('WONDERFULL', @now, @now),
('WOOD', @now, @now),
('WOWO', @now, @now),
('XENIA', @now, @now),
('XP TOOLS', @now, @now),
('YAMADA', @now, @now),
('YAMALUBE', @now, @now),
('YAMAOKE', @now, @now),
('YONGMA', @now, @now),
('YOZURI', @now, @now),
('YUASA', @now, @now),
('Z-PRO', @now, @now),
('-', @now, @now);

-- Barang (dicari lagi lewat SKU, nama kategori, dan nama merek; SKU yang sudah ada dilewati)
INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU-2409' AS sku, 'MCB IP C16' AS name, 'MCB' AS category_name, 'SCHNEIDER' AS brand_name, 'PCS' AS base_unit_code, 72875.00 AS cost_price, 8.000 AS min_stock, 1 AS is_active, 'MU 2409' AS legacy_sku
UNION ALL SELECT 'MU0001', 'HAK ANGIN 8\"', 'HAK ANGIN', 'IGM', 'PCS', 29167.00, 12.000, 1, 'MU0001'
UNION ALL SELECT 'MU0002', 'SENG TALANG MAROON 45CM', 'SENG', 'KOALA', 'MTR', 14750.00, 3.000, 1, 'MU0002'
UNION ALL SELECT 'MU0003', 'PAKU 5CM', 'Paku', '-', 'KG', 15200.00, 88.000, 1, 'MU0003'
UNION ALL SELECT 'MU0004', 'PAKU 7CM', 'Paku', '-', 'KG', 15200.00, 88.000, 1, 'MU0004'
UNION ALL SELECT 'MU0005', 'PAKU 10CM', 'Paku', '-', 'KG', 15200.00, 88.000, 1, 'MU0005'
UNION ALL SELECT 'MU0006', 'PAKU 3CM', 'Paku', '-', 'KG', 16000.00, 0.000, 1, 'MU0006'
UNION ALL SELECT 'MU0007', 'PAKU 4CM', 'Paku', '-', 'KG', 12500.00, 88.000, 1, 'MU0007'
UNION ALL SELECT 'MU0008', 'HAK ANGIN BOLA 8\"CR', 'HAK ANGIN', 'VALLEN', 'PCS', 6666.00, 12.000, 1, 'MU0008'
UNION ALL SELECT 'MU0009', 'PAKU KAPAL 10 cm', 'Paku', '-', 'KG', 19200.00, 88.000, 1, 'MU0009'
UNION ALL SELECT 'MU0010', 'PAKU KAPAL 12 CM', 'Paku', '-', 'KG', 19200.00, 88.000, 1, 'MU0010'
UNION ALL SELECT 'MU0011', 'KAWAT NYAMUK PARABOLA', 'KAWAT RAM', '-', 'MTR', 20000.00, 0.000, 1, 'MU0011'
UNION ALL SELECT 'MU0012', 'GPUTIH 30Pdk', 'Kunci', 'POPEYE', 'PCS', 12000.00, 5.000, 1, 'MU0012'
UNION ALL SELECT 'MU0013', 'MAGNETIC NUT', 'MUR', '-', 'PCS', 15000.00, 0.000, 1, 'MU0013'
UNION ALL SELECT 'MU0014', 'TALI TAMBANG 10MM', 'TALI', '-', 'KG', 40000.00, 0.000, 1, 'MU0014'
UNION ALL SELECT 'MU0015', 'NO DROP PAIL', 'CAT', 'NO DROP', 'PCS', 1230200.00, 0.000, 1, 'MU0015'
UNION ALL SELECT 'MU0016', 'PISAU FLASH 10', 'PISAU', 'FLASH', 'PCS', 19200.00, 0.000, 1, 'MU0016'
UNION ALL SELECT 'MU0017', 'GOLOK STAINLESS B.018', 'PISAU', '-', 'PCS', 48000.00, 0.000, 1, 'MU0017'
UNION ALL SELECT 'MU0018', 'PISAU FLASH 8', 'PISAU', 'FLASH', 'PCS', 15200.00, 0.000, 1, 'MU0018'
UNION ALL SELECT 'MU0019', 'MATA BOR BESI 6MM', 'MATA BOR', 'WIPRO', 'PCS', 17875.00, 10.000, 1, 'MU0019'
UNION ALL SELECT 'MU0020', 'TALI TAMBANG 6MM', 'TALI', '-', 'KG', 40000.00, 0.000, 1, 'MU0020'
UNION ALL SELECT 'MU0021', 'SUTEL MBI', 'SUTIL', '-', 'PCS', 18400.00, 0.000, 1, 'MU0021'
UNION ALL SELECT 'MU0022', 'MATA BOR BESI 7MM', 'MATA BOR', 'WIPRO', 'PCS', 23925.00, 10.000, 1, 'MU0022'
UNION ALL SELECT 'MU0023', 'KOMPOR HOCK 16 SUMBU', 'KOMPOR', 'HOCK', 'UNIT', 285480.00, 0.000, 1, 'MU0023'
UNION ALL SELECT 'MU0024', 'KOMPOR HOCK 22 SUMBU', 'KOMPOR', 'HOCK', 'UNIT', 380000.00, 0.000, 1, 'MU0024'
UNION ALL SELECT 'MU0025', 'KOMPOR HOCK 30 SUMBU', 'KOMPOR', 'HOCK', 'UNIT', 728000.00, 0.000, 1, 'MU0025'
UNION ALL SELECT 'MU0026', 'TESPEN TOYOSAKI 8\"', 'TES PEN', 'HARDWARES', 'PCS', 13000.00, 0.000, 1, 'MU0026'
UNION ALL SELECT 'MU0027', 'MATA BOR TEMBOK 12X310MM', 'MATA BOR', 'ALWIN', 'PCS', 40000.00, 0.000, 1, 'MU0027'
UNION ALL SELECT 'MU0028', 'MATA BOR TEMBOK 10X310MM', 'MATA BOR', 'ALWIN', 'PCS', 38400.00, 6.000, 1, 'MU0028'
UNION ALL SELECT 'MU0029', 'MATA BOR TEMBOK 14X310MM', 'MATA BOR', 'ALWIN', 'PCS', 48000.00, 0.000, 1, 'MU0029'
UNION ALL SELECT 'MU0030', 'SERBET SEGITIGA', 'KAIN LAP', 'SEGITIGA', 'PCS', 8000.00, 0.000, 1, 'MU0030'
UNION ALL SELECT 'MU0031', 'KANEBO ART', 'KANEBO', 'ART', 'PCS', 13000.00, 0.000, 1, 'MU0031'
UNION ALL SELECT 'MU0032', 'GS 15 WATT', 'LEM', '-', 'PCS', 24000.00, 0.000, 1, 'MU0032'
UNION ALL SELECT 'MU0033', 'B 58 BANDO', 'ALAT', 'BANDO', 'PCS', 60000.00, 0.000, 1, 'MU0033'
UNION ALL SELECT 'MU0034', 'NIPPE 2000 388-T', 'CAT', 'NIPPON PAINT', 'KLG', 109915.00, 0.000, 1, 'MU0034'
UNION ALL SELECT 'MU0035', 'LEM PHILIPIN NON-SAG 1 PINT 800G', 'LEM', 'PIONEER', 'SET', 154980.00, 0.000, 1, 'MU0035'
UNION ALL SELECT 'MU0036', 'GEMBOK BELUCI40 PDK', 'GEMBOK', 'BERLUTI', 'PCS', 50000.00, 0.000, 1, 'MU0036'
UNION ALL SELECT 'MU0037', 'KESET SUMBU', 'KESET', '-', 'PCS', 12000.00, 0.000, 1, 'MU0037'
UNION ALL SELECT 'MU0038', 'KESET PENTUL DOORMART', 'KESET', 'DOORMART', 'PCS', 24000.00, 0.000, 1, 'MU0038'
UNION ALL SELECT 'MU0039', 'KESET HANDUK', 'KESET', '-', 'PCS', 20000.00, 0.000, 1, 'MU0039'
UNION ALL SELECT 'MU0040', 'KESET DOORMART MOTIF GARIS', 'KESET', 'DOORMART', 'PCS', 20000.00, 0.000, 1, 'MU0040'
UNION ALL SELECT 'MU0041', 'KRAN GUCI VINO', 'KRAN', 'VINO', 'PCS', 8000.00, 0.000, 1, 'MU0041'
UNION ALL SELECT 'MU0042', 'KESET DORMART KARTUN', 'KESET', 'DOORMART', 'PCS', 20000.00, 0.000, 1, 'MU0042'
UNION ALL SELECT 'MU0043', 'GEMBOK BELUCI40 PJG', 'GEMBOK', 'BERLUTI', 'PCS', 52000.00, 0.000, 1, 'MU0043'
UNION ALL SELECT 'MU0044', 'KESET SERABUT KELAPA 1M', 'KESET', '-', 'PCS', 56000.00, 0.000, 1, 'MU0044'
UNION ALL SELECT 'MU0045', 'SUMBU KOMPOR HOCK', 'SUMBU', 'HOCK', 'ROL', 135000.00, 0.000, 1, 'MU0045'
UNION ALL SELECT 'MU0046', 'CEREK EMAS BESAR', 'TEMPAT', '-', 'PCS', 216000.00, 0.000, 1, 'MU0046'
UNION ALL SELECT 'MU0047', 'KEPALA KOMPOR SUMBU 16', 'KOMPOR', 'HOCK', 'PCS', 91500.00, 2.000, 1, 'MU0047'
UNION ALL SELECT 'MU0048', 'HANGER FOLLDING  20 STICK', 'GANTUNGAN', 'KAISHA', 'PCS', 20000.00, 0.000, 1, 'MU0048'
UNION ALL SELECT 'MU0049', '16 OZ PALU KAMBING VPR', 'PALU', 'VPR', 'PCS', 26500.00, 6.000, 1, 'MU0049'
UNION ALL SELECT 'MU0050', 'PALU KAMBING USA 8OZ PLS', 'PALU', 'HSJ', 'PCS', 16500.00, 6.000, 1, 'MU0050'
UNION ALL SELECT 'MU0051', 'GEMBOK OWNER PUTIH 40 PJG', 'Kunci', 'OWNER', 'PCS', 17000.00, 12.000, 1, 'MU0051'
UNION ALL SELECT 'MU0052', 'NIPPE 2000 318-T', 'CAT', 'NIPPON PAINT', 'KLG', 131542.00, 0.000, 1, 'MU0052'
UNION ALL SELECT 'MU0053', 'MATA BOR BESI 9MM', 'MATA BOR', 'WIPRO', 'PCS', 43450.00, 10.000, 1, 'MU0053'
UNION ALL SELECT 'MU0054', 'WASKOM 14 ALASKA', 'BASKOM', '-', 'PCS', 4000.00, 0.000, 1, 'MU0054'
UNION ALL SELECT 'MU0055', 'MATA GURINDA BESAR WIPRO', 'MATA GURINDA', 'WIPRO', 'PCS', 55000.00, 0.000, 1, 'MU0055'
UNION ALL SELECT 'MU0056', 'DRINKJAR 8 OVAL', 'TEMPAT', '-', 'PCS', 118400.00, 0.000, 1, 'MU0056'
UNION ALL SELECT 'MU0057', 'DRINKJAR 10 OVAL', 'TEMPAT', '-', 'PCS', 132000.00, 0.000, 1, 'MU0057'
UNION ALL SELECT 'MU0058', 'WASKOM VIOLET 15', 'BASKOM', '-', 'PCS', 3500.00, 0.000, 1, 'MU0058'
UNION ALL SELECT 'MU0059', 'WAJAN 15 CM', 'WAJAN', '-', 'PCS', 36000.00, 0.000, 1, 'MU0059'
UNION ALL SELECT 'MU0060', 'WAJAN 16', 'WAJAN', '-', 'PCS', 48000.00, 0.000, 1, 'MU0060'
UNION ALL SELECT 'MU0061', 'WAJAN 18', 'WAJAN', '-', 'PCS', 84000.00, 0.000, 1, 'MU0061'
UNION ALL SELECT 'MU0062', 'WAJAN 20', 'WAJAN', '-', 'PCS', 104000.00, 0.000, 1, 'MU0062'
UNION ALL SELECT 'MU0063', 'WAJAN 22', 'WAJAN', '-', 'PCS', 120000.00, 0.000, 1, 'MU0063'
UNION ALL SELECT 'MU0064', 'BLACK PB102', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 2.000, 1, 'MU0064'
UNION ALL SELECT 'MU0065', 'WHITE PB101', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 0.000, 1, 'MU0065'
UNION ALL SELECT 'MU0066', 'BRIGHT BLUE PB119', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 0.000, 1, 'MU0066'
UNION ALL SELECT 'MU0067', 'NIPPE 2000 470', 'CAT', 'NIPPON PAINT', 'KLG', 62400.00, 0.000, 1, 'MU0067'
UNION ALL SELECT 'MU0068', 'TIKAR LIPAT KING', 'TIKAR', 'KING', 'LBR', 68000.00, 0.000, 1, 'MU0068'
UNION ALL SELECT 'MU0069', 'NIPPE 2000 428-T', 'CAT', 'NIPPON PAINT', 'KLG', 62400.00, 0.000, 1, 'MU0069'
UNION ALL SELECT 'MU0070', 'NIPPE 2000 1013', 'CAT', 'NIPPON PAINT', 'KLG', 62400.00, 0.000, 1, 'MU0070'
UNION ALL SELECT 'MU0071', 'GEMBOK OWNER PUTIH 40 PDK', 'Kunci', 'OWNER', 'PCS', 16000.00, 12.000, 1, 'MU0071'
UNION ALL SELECT 'MU0072', 'NIPPE 2000 046', 'CAT', 'NIPPON PAINT', 'KLG', 62400.00, 0.000, 1, 'MU0072'
UNION ALL SELECT 'MU0073', 'GEMBOK HENDSO 40MM', 'Kunci', 'HENDSO', 'PCS', 20000.00, 0.000, 1, 'MU0073'
UNION ALL SELECT 'MU0074', 'SAN-POLY', 'CAIRAN', '-', 'KLG', 12000.00, 0.000, 1, 'MU0074'
UNION ALL SELECT 'MU0075', 'POLIBAG', 'KOKER', '-', 'KG', 23000.00, 0.000, 1, 'MU0075'
UNION ALL SELECT 'MU0076', 'CANGKUL BLITAR', 'CANGKUL', 'BLITAR', 'PCS', 200000.00, 0.000, 1, 'MU0076'
UNION ALL SELECT 'MU0077', 'PARANG BUAYA ENGLAND GG KAYU', 'PARANG', 'BUAYA', 'PCS', 75000.00, 0.000, 1, 'MU0077'
UNION ALL SELECT 'MU0078', 'MAKITA MT M.GURINDA M0910B', 'MESIN', 'MAKITA MT', 'UNIT', 604500.00, 3.000, 1, 'MU0078'
UNION ALL SELECT 'MU0079', 'SKAP MAKITA MT M1902B', 'MESIN', 'MAKITA MT', 'UNIT', 1092000.00, 1.000, 1, 'MU0079'
UNION ALL SELECT 'MU0080', 'KAWAT BENDRAT', 'KAWAT', '-', 'KG', 20000.00, 0.000, 1, 'MU0080'
UNION ALL SELECT 'MU0081', 'LEM SEPATU', 'TEHEL', 'PERMATA KERAMIK', 'PCS', 20000.00, 0.000, 1, 'MU0081'
UNION ALL SELECT 'MU0082', 'VITAL NAILS LEM PAKU', 'LEM', '-', 'PCS', 10000.00, 0.000, 1, 'MU0082'
UNION ALL SELECT 'MU0083', 'CEREK EMAS KECIL', 'TEMPAT', '-', 'UNIT', 200000.00, 0.000, 1, 'MU0083'
UNION ALL SELECT 'MU0084', 'KIPAS DUDUK COSMOS 12-BFE', 'KIPAS', 'MIYOKO', 'UNIT', 260000.00, 1.000, 1, 'MU0084'
UNION ALL SELECT 'MU0085', 'GPUTIH 50PJG', 'Kunci', 'GRT', 'PCS', 22400.00, 5.000, 1, 'MU0085'
UNION ALL SELECT 'MU0086', 'GPUTIH 30PJG', 'Kunci', 'POPEYE', 'PCS', 14400.00, 5.000, 1, 'MU0086'
UNION ALL SELECT 'MU0087', 'GPUTIH 40PJG', 'Kunci', 'POPEYE', 'PCS', 17600.00, 5.000, 1, 'MU0087'
UNION ALL SELECT 'MU0088', 'TRIPLEK MELAMIN PUTIH', 'TRIPLEK', '-', 'LBR', 170000.00, 0.000, 1, 'MU0088'
UNION ALL SELECT 'MU0089', 'SENG SOKKA KOTAK', 'SENG', '-', 'LBR', 32000.00, 0.000, 1, 'MU0089'
UNION ALL SELECT 'MU0090', 'QUERA GREY 40X40', 'TEHEL', 'JUPITER', 'DUS', 72794.00, 8.000, 1, 'MU0090'
UNION ALL SELECT 'MU0091', 'KAWAT LAS RB 3.2MM', 'KAWAT LAS', 'RB 2.6', 'KG', 32000.00, 0.000, 1, 'MU0091'
UNION ALL SELECT 'MU0092', 'TANG LANCIP 4.5 TEKIPO', 'TANG', 'TEKIPO', 'PCS', 29150.00, 0.000, 1, 'MU0092'
UNION ALL SELECT 'MU0093', 'CLOSET DUDUK NON TABUNG', 'CLOSET', '-', 'UNIT', 791000.00, 0.000, 1, 'MU0093'
UNION ALL SELECT 'MU0094', 'CLOSET DUDUK VOLK', 'CLOSET', '-', 'UNIT', 950000.00, 2.000, 1, 'MU0094'
UNION ALL SELECT 'MU0095', 'TANG LANCIP 6 TEKIPO', 'TANG', 'TEKIPO', 'PCS', 42075.00, 0.000, 1, 'MU0095'
UNION ALL SELECT 'MU0096', 'SEMEN MORTAR ACIAN', 'SEMEN', 'CITICON', 'SAK', 78588.00, 0.000, 1, 'MU0096'
UNION ALL SELECT 'MU0097', 'ARIES GOLD 805 20KG', 'CAT', 'ARIES GOLD', 'PAIL', 324090.00, 0.000, 1, 'MU0097'
UNION ALL SELECT 'MU0098', 'ARIES GOLD 303 20KG', 'CAT', 'ARIES GOLD', 'PAIL', 357700.00, 0.000, 1, 'MU0098'
UNION ALL SELECT 'MU0099', 'ARIES GOLD 203 20KG', 'CAT', 'ARIES GOLD', 'PAIL', 357700.00, 0.000, 1, 'MU0099'
UNION ALL SELECT 'MU0100', 'ARIES GOLD 603 20KG', 'CAT', 'ARIES GOLD', 'PAIL', 357700.00, 0.000, 1, 'MU0100'
UNION ALL SELECT 'MU0101', 'SAGOLA F75G SPEED BLUE', 'ALAT', 'SPEED', 'PCS', 304000.00, 0.000, 1, 'MU0101'
UNION ALL SELECT 'MU0102', 'SAGOLA 472 SPEED BLUE', 'ALAT', 'SPEED', 'PCS', 220000.00, 0.000, 1, 'MU0102'
UNION ALL SELECT 'MU0103', 'SENG MERAH GELOMBANG POLOS 0,20', 'SENG', 'KOALA', 'LBR', 45387.00, 3.000, 1, 'MU0103'
UNION ALL SELECT 'MU0104', 'AVITEX 522 25KG', 'CAT', 'AVITEX', 'PAIL', 440000.00, 0.000, 1, 'MU0104'
UNION ALL SELECT 'MU0105', 'TERPAL A8 RAJAWALI 5X7', 'TERPAL', 'MATAHARI', 'LBR', 341910.00, 0.000, 1, 'MU0105'
UNION ALL SELECT 'MU0106', 'PHILLIPS KOMPLIT MIXER', 'MIKSER', 'HANDSOME', 'PCS', 600000.00, 0.000, 1, 'MU0106'
UNION ALL SELECT 'MU0107', 'NIPPE COPPER GOLD', 'CAT', 'NIPPON PAINT', 'KLG', 135547.00, 0.000, 1, 'MU0107'
UNION ALL SELECT 'MU0108', 'GEMBOK BELUCI30 PDK', 'GEMBOK', 'BERLUTI', 'PCS', 40000.00, 0.000, 1, 'MU0108'
UNION ALL SELECT 'MU0109', 'KUAS ARCEL 2 1/2\'\'', 'Kuas', 'ARCEL', 'PCS', 8000.00, 0.000, 1, 'MU0109'
UNION ALL SELECT 'MU0110', 'KUAS BIASA 1 1/2\"', 'Kuas', 'SANSHAN', 'PCS', 5000.00, 12.000, 1, 'MU0110'
UNION ALL SELECT 'MU0111', 'KUAS BIASA 2\"', 'Kuas', 'FAWI', 'PCS', 6000.00, 0.000, 1, 'MU0111'
UNION ALL SELECT 'MU0112', 'KUAS BIASA 2 1/2\"', 'Kuas', 'SANSHAN', 'PCS', 7000.00, 0.000, 1, 'MU0112'
UNION ALL SELECT 'MU0113', 'KUAS IGUANA 3\" REFILL', 'Kuas', 'IGUANA', 'PCS', 9000.00, 0.000, 1, 'MU0113'
UNION ALL SELECT 'MU0114', 'KUAS ARCEL 4\"', 'Kuas', 'ARCEL', 'PCS', 18400.00, 0.000, 1, 'MU0114'
UNION ALL SELECT 'MU0115', 'HOLO BAJA RINGAN 32X32X0.25', 'BAJA RINGAN', '-', 'BTG', 23000.00, 0.000, 1, 'MU0115'
UNION ALL SELECT 'MU0116', 'CLOSET DUDUK AMERICAN STANDARD', 'CLOSET', '-', 'UNIT', 1160000.00, 2.000, 1, 'MU0116'
UNION ALL SELECT 'MU0117', 'TEMPAT CUCI PIRING 2 LUBANG', 'WASHTAFEL', '-', 'SET', 290000.00, 0.000, 1, 'MU0117'
UNION ALL SELECT 'MU0118', 'TANG KOMBINASI 6\" TEKIPO', 'TANG', 'TEKIPO', 'PCS', 42075.00, 0.000, 1, 'MU0118'
UNION ALL SELECT 'MU0119', 'CALSIUM', 'SEMEN', '-', 'SAK', 90000.00, 0.000, 1, 'MU0119'
UNION ALL SELECT 'MU0120', 'SEMEN PUTIH GARUDA', 'SEMEN', '-', 'SAK', 110000.00, 0.000, 1, 'MU0120'
UNION ALL SELECT 'MU0121', 'CAT AVIAN 200CC SW', 'CAT', 'Avian', 'KLG', 18400.00, 8.000, 1, 'MU0121'
UNION ALL SELECT 'MU0122', 'KARPET DAIGE 2X25M', 'KARPET', 'ORCHID', 'MTR', 29200.00, 0.000, 1, 'MU0122'
UNION ALL SELECT 'MU0123', 'TALI TAMBANG 1.5 MM', 'TALI', 'CAP JAGO', 'PCS', 6400.00, 0.000, 1, 'MU0123'
UNION ALL SELECT 'MU0124', 'BAK CAT', 'BAK', 'CAP JAGO', 'PCS', 13600.00, 0.000, 1, 'MU0124'
UNION ALL SELECT 'MU0125', 'METERAN 7.5M BLACKFOOT', 'ALAT UKUR', 'BLACKFOOT', 'PCS', 31200.00, 6.000, 1, 'MU0125'
UNION ALL SELECT 'MU0126', 'KARET TALANG 45 cm', 'TALANG', '-', 'MTR', 14400.00, 0.000, 1, 'MU0126'
UNION ALL SELECT 'MU0127', 'KARET TALANG 50 cm', 'TALANG', '-', 'MTR', 16000.00, 0.000, 1, 'MU0127'
UNION ALL SELECT 'MU0128', 'BOCORAN BAK AIR B', 'ALAT', '-', 'PCS', 4167.00, 0.000, 1, 'MU0128'
UNION ALL SELECT 'MU0129', 'BOCORAN BAK AIR A', 'ALAT', '-', 'PCS', 8750.00, 0.000, 1, 'MU0129'
UNION ALL SELECT 'MU0130', 'SOLDER LURUS HASTON', 'SOLDER', 'HASSTON', 'PCS', 33000.00, 12.000, 1, 'MU0130'
UNION ALL SELECT 'MU0131', 'Diamond Wheel Turbo RED', 'MATA GURINDA', 'TAIYO', 'PCS', 52972.00, 0.000, 1, 'MU0131'
UNION ALL SELECT 'MU0132', 'TIMAH JARING', 'PEMBERAT JARING', '-', 'PCS', 36000.00, 0.000, 1, 'MU0132'
UNION ALL SELECT 'MU0133', 'SENG DIAMOND KOTAK', 'SENG', '-', 'LBR', 40000.00, 0.000, 1, 'MU0133'
UNION ALL SELECT 'MU0134', 'SENG ROLL WARNA 90cm', 'TALANG', '-', 'PCS', 36000.00, 0.000, 1, 'MU0134'
UNION ALL SELECT 'MU0135', 'TEMPAT CUCI PIRING 2 LUBANG SAYAP', 'WASHTAFEL', '-', 'SET', 611000.00, 0.000, 1, 'MU0135'
UNION ALL SELECT 'MU0136', 'BCP 1 LB NON SAYAP', 'WASHTAFEL', 'FLO', 'SET', 174000.00, 0.000, 1, 'MU0136'
UNION ALL SELECT 'MU0137', 'BCP 1 LB NON SAYAP SAVIC 60X45X20', 'WASHTAFEL', '-', 'SET', 137500.00, 0.000, 1, 'MU0137'
UNION ALL SELECT 'MU0138', 'TANGGA ALUMUNIUM 1 1.5 M', 'TANGGA', '-', 'SET', 480000.00, 0.000, 1, 'MU0138'
UNION ALL SELECT 'MU0139', 'GAS PORTABEL PURITY', 'GAS', '-', 'PCS', 19500.00, 0.000, 1, 'MU0139'
UNION ALL SELECT 'MU0140', 'KARPET MALAYSIA PENDEK', 'KARPET', '-', 'LBR', 280000.00, 0.000, 1, 'MU0140'
UNION ALL SELECT 'MU0141', 'KARPET MALAYSIA PANJANG 220', 'KARPET', '-', 'LBR', 460000.00, 0.000, 1, 'MU0141'
UNION ALL SELECT 'MU0142', 'TALI RAFIAH', 'TALI', '-', 'KG', 23000.00, 0.000, 1, 'MU0142'
UNION ALL SELECT 'MU0143', 'TEMPAT SOLDER', 'ALAT', 'WIPRO', 'PCS', 38400.00, 0.000, 1, 'MU0143'
UNION ALL SELECT 'MU0144', 'AVIAN SYNTHETIC 160', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU0144'
UNION ALL SELECT 'MU0145', 'BESI GORDEN', 'BESI', '555', 'MTR', 12000.00, 0.000, 1, 'MU0145'
UNION ALL SELECT 'MU0146', 'TALI SENAR 110', 'TALI', '-', 'ROL', 5000.00, 0.000, 1, 'MU0146'
UNION ALL SELECT 'MU0147', 'SERAT FIBER', 'SERAT', '-', 'LBR', 2400.00, 0.000, 1, 'MU0147'
UNION ALL SELECT 'MU0148', 'GEROBAK ARTCO MERAH', 'GEROBAK', 'ARTCO', 'UNIT', 690000.00, 0.000, 1, 'MU0148'
UNION ALL SELECT 'MU0149', 'KAWAT RAM 1\'', 'KAWAT', '-', 'ROL', 119000.00, 0.000, 1, 'MU0149'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU0150' AS sku, 'KAWAT LOKET 1/2x4 kg' AS name, 'KAWAT' AS category_name, '-' AS brand_name, 'MTR' AS base_unit_code, 11764.00 AS cost_price, 0.000 AS min_stock, 1 AS is_active, 'MU0150' AS legacy_sku
UNION ALL SELECT 'MU0151', 'KAWAT LOCKET 1/4x4 KG', 'KAWAT', '-', 'MTR', 12000.00, 0.000, 1, 'MU0151'
UNION ALL SELECT 'MU0152', '16 OZ PALU KAMBING IGM', 'PALU', 'IGM', 'PCS', 37500.00, 6.000, 1, 'MU0152'
UNION ALL SELECT 'MU0153', 'SEKOP SANSHAN PAPAK', 'SEKOP', 'SANSHAN', 'PCS', 80000.00, 0.000, 1, 'MU0153'
UNION ALL SELECT 'MU0154', 'SEKOP SANSHAN LANCIP', 'SEKOP', 'SANSHAN', 'PCS', 80000.00, 0.000, 1, 'MU0154'
UNION ALL SELECT 'MU0155', 'BATU GOSOK 8 ORANGE', 'BATU GOSOK', 'SANSHAN', 'PCS', 7500.00, 12.000, 1, 'MU0155'
UNION ALL SELECT 'MU0156', 'TALI TAMBANG 12MM', 'TALI', '-', 'KG', 40000.00, 0.000, 1, 'MU0156'
UNION ALL SELECT 'MU0157', 'THINNER RAJAKA BOTOL', 'TINNER', '-', 'BTL', 0.00, 0.000, 1, 'MU0157'
UNION ALL SELECT 'MU0158', 'SAPU NAGOYA BUNGA', 'SAPU', '-', 'PCS', 0.00, 0.000, 1, 'MU0158'
UNION ALL SELECT 'MU0159', 'PAKU KAPAL 7', 'Paku', '-', 'KG', 0.00, 88.000, 1, 'MU0159'
UNION ALL SELECT 'MU0160', 'TECTOOL KUNCI PAS 8X9', 'ALAT', 'WIPRO', 'PCS', 6200.00, 0.000, 1, 'MU0160'
UNION ALL SELECT 'MU0161', 'SIKAT NAGOYA TANPA GAGANG', 'SIKAT', '-', 'PCS', 0.00, 0.000, 1, 'MU0161'
UNION ALL SELECT 'MU0162', 'SIKAT NAGOYA GAGANG PENDEK', 'SIKAT', '-', 'PCS', 0.00, 0.000, 1, 'MU0162'
UNION ALL SELECT 'MU0163', 'HOLO 3.5X3.5 1.5MM GALVANISH', 'BESI', '-', 'BTG', 152000.00, 0.000, 1, 'MU0163'
UNION ALL SELECT 'MU0164', 'HOLO 15x35x1.2MM GALVANISH', 'BESI', '-', 'BTG', 81000.00, 0.000, 1, 'MU0164'
UNION ALL SELECT 'MU0165', 'Silicone sealent Elgladio', 'LEM', 'ELGLADIO', 'PCS', 12000.00, 0.000, 1, 'MU0165'
UNION ALL SELECT 'MU0166', 'STUD 5/8', 'BAUT', '-', 'BTG', 30000.00, 0.000, 1, 'MU0166'
UNION ALL SELECT 'MU0167', 'SARUNG TANGAN BINTIK HITAM', 'SARUNG TANGAN', 'MATAHARI', 'PSG', 2400.00, 50.000, 1, 'MU0167'
UNION ALL SELECT 'MU0168', 'JARING 2 1/2\' x 70x80', 'JARING', '-', 'UNIT', 204600.00, 0.000, 1, 'MU0168'
UNION ALL SELECT 'MU0169', 'JARING 2\' x 70x80', 'JARING', '-', 'UNIT', 227100.00, 0.000, 1, 'MU0169'
UNION ALL SELECT 'MU0170', 'OBENG BB MERAH WIPRO', 'OBENG', 'WIPRO', 'PCS', 13000.00, 6.000, 1, 'MU0170'
UNION ALL SELECT 'MU0171', 'WARING 120CM', 'JARING', '-', 'MTR', 5000.00, 100.000, 1, 'MU0171'
UNION ALL SELECT 'MU0172', 'STUD 1/2', 'BAUT', '-', 'BTG', 14000.00, 0.000, 1, 'MU0172'
UNION ALL SELECT 'MU0173', 'TANG KOMBINASI 7 TEKIPO', 'TANG', 'TEKIPO', 'PCS', 47300.00, 0.000, 1, 'MU0173'
UNION ALL SELECT 'MU0174', 'TERPAL A8 RAJAWALI 6x7', 'TERPAL', 'MATAHARI', 'LBR', 406660.00, 0.000, 1, 'MU0174'
UNION ALL SELECT 'MU0175', 'TERPAL A5 GAJAH 6X7', 'TERPAL', 'MATAHARI', 'LBR', 282000.00, 0.000, 1, 'MU0175'
UNION ALL SELECT 'MU0176', 'TALI TAMBANG 3MM', 'TALI', '-', 'KG', 68000.00, 0.000, 1, 'MU0176'
UNION ALL SELECT 'MU0177', 'TALI TAMBANG 4MM', 'TALI', '-', 'KG', 40000.00, 0.000, 1, 'MU0177'
UNION ALL SELECT 'MU0178', 'TALI  TAMBANG 5MM', 'TALI', '-', 'KG', 40000.00, 0.000, 1, 'MU0178'
UNION ALL SELECT 'MU0179', 'TALI TAMBANG 8 mm', 'TALI', '-', 'KG', 40000.00, 0.000, 1, 'MU0179'
UNION ALL SELECT 'MU0180', 'SEMEN WARNA COKLAT', 'SEMEN', '-', 'BKS', 8000.00, 0.000, 1, 'MU0180'
UNION ALL SELECT 'MU0181', 'GEMBOK BELUCI30 PJG', 'GEMBOK', 'BERLUTI', 'PCS', 40000.00, 0.000, 1, 'MU0181'
UNION ALL SELECT 'MU0182', 'SOCK 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 2500.00, 0.000, 1, 'MU0182'
UNION ALL SELECT 'MU0183', 'TEE 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 2736.00, 0.000, 1, 'MU0183'
UNION ALL SELECT 'MU0184', 'TEE 1', 'SAMBUNGAN', 'POWER', 'PCS', 6108.00, 0.000, 1, 'MU0184'
UNION ALL SELECT 'MU0185', 'TERPAL A8 RAJAWALI 2X3', 'TERPAL', 'MATAHARI', 'LBR', 59132.00, 0.000, 1, 'MU0185'
UNION ALL SELECT 'MU0186', 'POMPA AIR SHIMIZU PS 135E', 'MESIN', 'SHIMIZU', 'UNIT', 755700.00, 0.000, 1, 'MU0186'
UNION ALL SELECT 'MU0187', 'POMPA AIR UCHIDA MP 219', 'MESIN', 'UCHIDA', 'UNIT', 480000.00, 0.000, 1, 'MU0187'
UNION ALL SELECT 'MU0188', 'PARUT KELAPA', 'MESIN', '-', 'UNIT', 320000.00, 0.000, 1, 'MU0188'
UNION ALL SELECT 'MU0189', 'BCP MINIMALIS 30X15 KFK', 'WASHTAFEL', 'SKF.SKC', 'SET', 305000.00, 6.000, 1, 'MU0189'
UNION ALL SELECT 'MU0190', 'LINGGIS BESAR', 'LINGGIS', '-', 'PCS', 41600.00, 0.000, 1, 'MU0190'
UNION ALL SELECT 'MU0191', 'LINGGIS SEDANG', 'LINGGIS', '-', 'PCS', 36000.00, 0.000, 1, 'MU0191'
UNION ALL SELECT 'MU0192', 'KESET KASAR', 'KESET', '-', 'PCS', 16000.00, 0.000, 1, 'MU0192'
UNION ALL SELECT 'MU0193', 'CANGKUL CROCODILE', 'CANGKUL', 'BOYO', 'PCS', 52500.00, 6.000, 1, 'MU0193'
UNION ALL SELECT 'MU0194', 'KUNCI BESI 6', 'ALAT', '-', 'PCS', 18000.00, 0.000, 1, 'MU0194'
UNION ALL SELECT 'MU0195', '8 OZ PALU KAMBING IGM', 'PALU', 'IGM', 'PCS', 31000.00, 6.000, 1, 'MU0195'
UNION ALL SELECT 'MU0196', 'GARUK MERAH', 'ALAT', '-', 'PCS', 19000.00, 12.000, 1, 'MU0196'
UNION ALL SELECT 'MU0197', 'TEE 3', 'SAMBUNGAN', 'POWER', 'PCS', 28770.00, 0.000, 1, 'MU0197'
UNION ALL SELECT 'MU0198', 'ELBOW 2 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 12790.00, 0.000, 1, 'MU0198'
UNION ALL SELECT 'MU0199', 'TERPAL A8 RAJAWALI  4X5', 'TERPAL', 'MATAHARI', 'LBR', 194080.00, 0.000, 1, 'MU0199'
UNION ALL SELECT 'MU0200', 'TIKAR LANTAI SEDANG', 'TIKAR', '-', 'LBR', 60000.00, 0.000, 1, 'MU0200'
UNION ALL SELECT 'MU0201', 'TIKAR LANTAI BESAR', 'TIKAR', '-', 'LBR', 100000.00, 0.000, 1, 'MU0201'
UNION ALL SELECT 'MU0202', 'SHOULDER BELT MIYAMOTO', 'S/PART', 'MIYAMOTO', 'PCS', 20000.00, 8.000, 1, 'MU0202'
UNION ALL SELECT 'MU0203', 'DEMPUL AUTOLUX 66 3KG', 'DEMPUL', 'NIPPON PAINT', 'KLG', 120000.00, 0.000, 1, 'MU0203'
UNION ALL SELECT 'MU0204', 'DEMPUL MOBIL ALPHAGLOSS 2 KG', 'DEMPUL', 'ALFAGLOSS', 'KLG', 125000.00, 6.000, 1, 'MU0204'
UNION ALL SELECT 'MU0205', 'WATERPASS DP-2001B 48\"', 'WATERPASS', 'WIPRO', 'PCS', 235000.00, 0.000, 1, 'MU0205'
UNION ALL SELECT 'MU0206', 'KARPET BAYI DORAEMON', 'KARPET', 'ROYALPLAST', 'MTR', 24000.00, 0.000, 1, 'MU0206'
UNION ALL SELECT 'MU0207', 'KARPET BANTENG 1.2x17m', 'KARPET', 'PINGUIN', 'MTR', 9411.00, 0.000, 1, 'MU0207'
UNION ALL SELECT 'MU0208', 'TAPLAK MEJA PERAK EMAS', 'TAPLAK', '-', 'MTR', 52000.00, 0.000, 1, 'MU0208'
UNION ALL SELECT 'MU0209', 'TAPLAK MEJA PLASTIK BIASA', 'TAPLAK', '-', 'MTR', 32000.00, 0.000, 1, 'MU0209'
UNION ALL SELECT 'MU0210', 'TAPLAK MEJA KECIL', 'TAPLAK', '-', 'MTR', 24000.00, 0.000, 1, 'MU0210'
UNION ALL SELECT 'MU0211', 'PLASTIK BENING TEBAL 03X50M', 'PLASTIK', '-', 'MTR', 21000.00, 0.000, 1, 'MU0211'
UNION ALL SELECT 'MU0212', 'PLASTIK BENING TIPIS 025X50M', 'PLASTIK', '-', 'MTR', 19000.00, 0.000, 1, 'MU0212'
UNION ALL SELECT 'MU0213', 'TEE 2 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 19180.00, 0.000, 1, 'MU0213'
UNION ALL SELECT 'MU0214', 'SOCK 1 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 3030.00, 9.000, 1, 'MU0214'
UNION ALL SELECT 'MU0215', 'SOCK PIPA 3X1 1.5 INCI', 'SAMBUNGAN', 'POWER', 'PCS', 11000.00, 0.000, 1, 'MU0215'
UNION ALL SELECT 'MU0216', 'TUTUP PIPA 4', 'SAMBUNGAN', 'POWER', 'PCS', 24600.00, 9.000, 1, 'MU0216'
UNION ALL SELECT 'MU0217', 'TUTUP PIPA 3', 'SAMBUNGAN', 'POWER', 'PCS', 13060.00, 10.000, 1, 'MU0217'
UNION ALL SELECT 'MU0218', 'TUTUP PIPA 2', 'SAMBUNGAN', 'POWER', 'PCS', 8350.00, 0.000, 1, 'MU0218'
UNION ALL SELECT 'MU0219', 'SAMBUNGAN TALANG', 'SAMBUNGAN', 'POWER', 'PCS', 10570.00, 0.000, 1, 'MU0219'
UNION ALL SELECT 'MU0220', 'TUTUP TALANG', 'SAMBUNGAN', 'POWER', 'PCS', 9240.00, 0.000, 1, 'MU0220'
UNION ALL SELECT 'MU0221', 'PALU BODEM 10 LB', 'PALU', '-', 'PCS', 75000.00, 0.000, 1, 'MU0221'
UNION ALL SELECT 'MU0222', 'CORONG TALANG', 'SAMBUNGAN', 'POWER', 'PCS', 32410.00, 0.000, 1, 'MU0222'
UNION ALL SELECT 'MU0223', 'GAET TALANG', 'SAMBUNGAN', 'POWER', 'PCS', 7640.00, 0.000, 1, 'MU0223'
UNION ALL SELECT 'MU0224', 'ELBOW 5', 'SAMBUNGAN', 'POWER', 'PCS', 91710.00, 0.000, 1, 'MU0224'
UNION ALL SELECT 'MU0225', 'SOCK DRAT PROFIL TANK 1 INCI', 'SAMBUNGAN', 'POWER', 'PCS', 11000.00, 0.000, 1, 'MU0225'
UNION ALL SELECT 'MU0226', 'PAKU BETON 21/2INCI', 'Paku', 'GOOD', 'DUS', 16000.00, 0.000, 1, 'MU0226'
UNION ALL SELECT 'MU0227', 'PAKU BETON 1 INCI', 'Paku', 'GOOD', 'DUS', 16000.00, 0.000, 1, 'MU0227'
UNION ALL SELECT 'MU0228', 'SOCK DRAT PROFIL TANK 3/4 INCI', 'SAMBUNGAN', 'POWER', 'PCS', 8000.00, 0.000, 1, 'MU0228'
UNION ALL SELECT 'MU0229', 'SOCK 1', 'SAMBUNGAN', 'POWER', 'PCS', 3308.00, 0.000, 1, 'MU0229'
UNION ALL SELECT 'MU0230', 'SOCK DRAT 1 X 3/4', 'SAMBUNGAN', 'POWER', 'PCS', 2355.00, 0.000, 1, 'MU0230'
UNION ALL SELECT 'MU0231', 'TEE 2', 'SAMBUNGAN', 'POWER', 'PCS', 10780.00, 0.000, 1, 'MU0231'
UNION ALL SELECT 'MU0232', 'PALU BODEM 6 LB', 'PALU', 'IGM', 'PCS', 42000.00, 0.000, 1, 'MU0232'
UNION ALL SELECT 'MU0233', 'TUTUP PIPA 3/4', 'SAMBUNGAN', 'POWER', 'PCS', 1630.00, 0.000, 1, 'MU0233'
UNION ALL SELECT 'MU0234', 'PAKU BETON HITAM 100MM MILLION', 'Paku', 'MILLION', 'DUS', 32000.00, 6.000, 1, 'MU0234'
UNION ALL SELECT 'MU0235', 'KAPI BESI NP 1\"', 'KAPI', 'NIPPON PAINT', 'PCS', 8300.00, 0.000, 1, 'MU0235'
UNION ALL SELECT 'MU0236', 'SPAN LANCIP GG KAYU IGM', 'SPAN', 'IGM', 'PCS', 18750.00, 0.000, 1, 'MU0236'
UNION ALL SELECT 'MU0237', 'HAOSHIN MESIN ALKON 3\"', 'MESIN', 'HONDA', 'UNIT', 3400000.00, 5.000, 1, 'MU0237'
UNION ALL SELECT 'MU0238', 'DIAMOND WHEEL TURBO HIJAU', 'MATA GURINDA', 'FUJIYAMA', 'PCS', 45000.00, 0.000, 1, 'MU0238'
UNION ALL SELECT 'MU0239', 'HOLO 50X50X1,5X600', 'BESI', '-', 'BTG', 197600.00, 0.000, 1, 'MU0239'
UNION ALL SELECT 'MU0240', 'PALU BODEM 8 LB', 'PALU', '-', 'PCS', 56000.00, 0.000, 1, 'MU0240'
UNION ALL SELECT 'MU0241', 'HOLO 40X40X600 GALVANISH', 'BESI', '-', 'BTG', 184800.00, 0.000, 1, 'MU0241'
UNION ALL SELECT 'MU0242', 'HOLO 20X30X1,8X600', 'BESI', '-', 'BTG', 155000.00, 10.000, 1, 'MU0242'
UNION ALL SELECT 'MU0243', 'DINABOLT 12X99', 'BAUT', '-', 'PCS', 3500.00, 0.000, 1, 'MU0243'
UNION ALL SELECT 'MU0244', 'HOLO 14X34X1,5X600', 'BESI', '-', 'BTG', 132000.00, 0.000, 1, 'MU0244'
UNION ALL SELECT 'MU0245', 'YAMAOKE MESIN ALKON 2\"', 'MESIN', 'YAMAOKE', 'UNIT', 1015100.00, 5.000, 1, 'MU0245'
UNION ALL SELECT 'MU0246', 'BESI U 65X38X25X600', 'BESI', '-', 'BTG', 310000.00, 0.000, 1, 'MU0246'
UNION ALL SELECT 'MU0247', 'MATA GURINDA FLEXOVIT', 'MATA GURINDA', 'FLEXOVIT', 'PCS', 24000.00, 0.000, 1, 'MU0247'
UNION ALL SELECT 'MU0248', 'SKRUP GIPSUM YUSTAR 3/4', 'SKRUP', '-', 'DUS', 19500.00, 0.000, 1, 'MU0248'
UNION ALL SELECT 'MU0249', 'KAWAT AYAM', 'KAWAT RAM', 'TAIWAN', 'MTR', 10000.00, 30.000, 1, 'MU0249'
UNION ALL SELECT 'MU0250', 'BESI U 100X50X3,1X600', 'BESI', '-', 'BTG', 461000.00, 0.000, 1, 'MU0250'
UNION ALL SELECT 'MU0251', 'BESI SIKU 50X50X5X600', 'BESI', '-', 'BTG', 234600.00, 0.000, 1, 'MU0251'
UNION ALL SELECT 'MU0252', 'BESI SIKU 30x30x3x600', 'BESI', '-', 'BTG', 148750.00, 0.000, 1, 'MU0252'
UNION ALL SELECT 'MU0253', 'GUNTING DAHAN 03 SK5', 'BESI', '-', 'PCS', 75000.00, 0.000, 1, 'MU0253'
UNION ALL SELECT 'MU0254', 'FULL PENETRATE ANTI KARAT 500ML', 'CAIRAN', 'MEGACOOLS', 'BTL', 36000.00, 0.000, 1, 'MU0254'
UNION ALL SELECT 'MU0255', 'CONTACT CLEANER 500ML', 'CAIRAN', 'MEGACOOLS', 'BTL', 36800.00, 0.000, 1, 'MU0255'
UNION ALL SELECT 'MU0256', 'ARIES EMULSION 660 20KG', 'CAT', 'ARIES', 'PAIL', 225200.00, 0.000, 1, 'MU0256'
UNION ALL SELECT 'MU0257', 'ARIES EMULSION 681 20KG', 'CAT', 'ARIES', 'PAIL', 225200.00, 0.000, 1, 'MU0257'
UNION ALL SELECT 'MU0258', 'ARIES EMULSION 686 20KG', 'CAT', 'ARIES', 'PAIL', 225200.00, 0.000, 1, 'MU0258'
UNION ALL SELECT 'MU0259', 'ARIES EMULSION 687 20KG', 'CAT', 'ARIES', 'PAIL', 225200.00, 0.000, 1, 'MU0259'
UNION ALL SELECT 'MU0260', 'ARIES EMULSION 690 20KG', 'CAT', 'ARIES', 'PAIL', 225200.00, 0.000, 1, 'MU0260'
UNION ALL SELECT 'MU0261', 'TALI 8MM GULUNG', 'TALI', '-', 'KG', 40000.00, 0.000, 1, 'MU0261'
UNION ALL SELECT 'MU0262', 'ARIES EMULSION 694 20KG', 'CAT', 'ARIES', 'PAIL', 225200.00, 0.000, 1, 'MU0262'
UNION ALL SELECT 'MU0263', 'TALI 10MM GULUNG', 'TALI', '-', 'KG', 40000.00, 0.000, 1, 'MU0263'
UNION ALL SELECT 'MU0264', 'ARIES GOLD 501 20KG', 'CAT', 'ARIES GOLD', 'PAIL', 357700.00, 0.000, 1, 'MU0264'
UNION ALL SELECT 'MU0265', 'DESERTA BLUE 20X20', 'TEHEL', 'ACCURA', 'DUS', 60000.00, 0.000, 1, 'MU0265'
UNION ALL SELECT 'MU0266', 'ARIES GOLD 702 20KG', 'CAT', 'ARIES GOLD', 'PAIL', 357700.00, 0.000, 1, 'MU0266'
UNION ALL SELECT 'MU0267', 'ARIES GOLD SB 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU0267'
UNION ALL SELECT 'MU0268', 'ARIES 660 5KG', 'CAT', 'ARIES', 'POT', 60400.00, 0.000, 1, 'MU0268'
UNION ALL SELECT 'MU0269', 'ARIES 671 5KG', 'CAT', 'ARIES', 'POT', 60400.00, 0.000, 1, 'MU0269'
UNION ALL SELECT 'MU0270', 'ARIES 681 5KG', 'CAT', 'ARIES', 'POT', 60400.00, 0.000, 1, 'MU0270'
UNION ALL SELECT 'MU0271', 'ARIES 686 5KG', 'CAT', 'ARIES', 'POT', 60400.00, 0.000, 1, 'MU0271'
UNION ALL SELECT 'MU0272', 'ARIES 687 5KG', 'CAT', 'ARIES', 'POT', 60400.00, 0.000, 1, 'MU0272'
UNION ALL SELECT 'MU0273', 'ARIES 690 5KG', 'CAT', 'ARIES', 'POT', 60400.00, 0.000, 1, 'MU0273'
UNION ALL SELECT 'MU0274', 'ARIES 691 5KG', 'CAT', 'ARIES', 'POT', 60400.00, 0.000, 1, 'MU0274'
UNION ALL SELECT 'MU0275', 'ARIES 694 5KG', 'CAT', 'ARIES', 'POT', 60400.00, 0.000, 1, 'MU0275'
UNION ALL SELECT 'MU0276', 'ARIES EMULSION 625 20KG', 'CAT', 'ARIES', 'PAIL', 225200.00, 0.000, 1, 'MU0276'
UNION ALL SELECT 'MU0277', 'ARIES GOLD 202 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU0277'
UNION ALL SELECT 'MU0278', 'ARIES GOLD 204 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU0278'
UNION ALL SELECT 'MU0279', 'ARIES GOLD 206  5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU0279'
UNION ALL SELECT 'MU0280', 'ARIES GOLD 305 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU0280'
UNION ALL SELECT 'MU0281', 'ARIES GOLD 501 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU0281'
UNION ALL SELECT 'MU0282', 'ARIES GOLD 504 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU0282'
UNION ALL SELECT 'MU0283', 'ARIES GOLD 505 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU0283'
UNION ALL SELECT 'MU0284', 'ARIES GOLD 602 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU0284'
UNION ALL SELECT 'MU0285', 'ARIES GOLD 702 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU0285'
UNION ALL SELECT 'MU0286', 'ARIES GOLD 202 20KG', 'CAT', 'ARIES GOLD', 'PAIL', 357700.00, 0.000, 1, 'MU0286'
UNION ALL SELECT 'MU0287', 'ARIES GOLD 204 20KG', 'CAT', 'ARIES GOLD', 'PAIL', 357700.00, 0.000, 1, 'MU0287'
UNION ALL SELECT 'MU0288', 'ARIES GOLD 206 20KG', 'CAT', 'ARIES GOLD', 'PAIL', 357700.00, 0.000, 1, 'MU0288'
UNION ALL SELECT 'MU0289', 'ARIES GOLD 403 20KG', 'CAT', 'ARIES GOLD', 'PAIL', 357700.00, 0.000, 1, 'MU0289'
UNION ALL SELECT 'MU0290', 'BOYO POLITUR 600 1L', 'VERNIS', 'BOYO', 'KLG', 86000.00, 0.000, 1, 'MU0290'
UNION ALL SELECT 'MU0291', 'BOYO POLITUR 609 0,5L', 'VERNIS', 'BOYO', 'KLG', 35000.00, 0.000, 1, 'MU0291'
UNION ALL SELECT 'MU0292', 'ROMAN OGEE BIT 1/4XR1/4', 'MATA SERKEL', 'WIPRO', 'PCS', 120000.00, 0.000, 1, 'MU0292'
UNION ALL SELECT 'MU0293', 'BESI STRIP 10X06X600', 'BESI', '-', 'BTG', 400000.00, 0.000, 1, 'MU0293'
UNION ALL SELECT 'MU0294', 'MACTEC BOR LISTRIK', 'MESIN', 'MAKTEC', 'UNIT', 488500.00, 0.000, 1, 'MU0294'
UNION ALL SELECT 'MU0295', 'MACTEC CIRCULAR SAW 7\"', 'MATA SERKEL', '-', 'PCS', 1069500.00, 0.000, 1, 'MU0295'
UNION ALL SELECT 'MU0296', 'MAKITA PLANER 155MM 1805N', 'MATA SERKEL', 'MAKITA', 'PCS', 6160000.00, 0.000, 1, 'MU0296'
UNION ALL SELECT 'MU0297', 'LIPRO TANG LANCIP 6\"NEW', 'TANG', 'LIPPRO', 'PCS', 40800.00, 0.000, 1, 'MU0297'
UNION ALL SELECT 'MU0298', 'MAKITA AERMATURE 180N', 'MATA SERKEL', '-', 'PCS', 900000.00, 0.000, 1, 'MU0298'
UNION ALL SELECT 'MU0299', 'MAKITA CB 153A', 'MATA SERKEL', '-', 'PCS', 22500.00, 0.000, 1, 'MU0299'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU0300' AS sku, 'BESI STRIP 40X04X600' AS name, 'BESI' AS category_name, '-' AS brand_name, 'BTG' AS base_unit_code, 104000.00 AS cost_price, 0.000 AS min_stock, 1 AS is_active, 'MU0300' AS legacy_sku
UNION ALL SELECT 'MU0301', 'PIPA BESI HITAM 2X1,5X600', 'BESI', '-', 'BTG', 219200.00, 0.000, 1, 'MU0301'
UNION ALL SELECT 'MU0302', 'ATS TANG RIVET 9.5\"', 'TANG', 'ATS', 'PCS', 40000.00, 0.000, 1, 'MU0302'
UNION ALL SELECT 'MU0303', 'PALU KAMBING USA 12OZ PLS', 'PALU', 'HSJ', 'PCS', 20000.00, 0.000, 1, 'MU0303'
UNION ALL SELECT 'MU0304', 'WD 40 6.5 OZ (191ML)', 'AEROSOL', '-', 'PCS', 58357.00, 0.000, 1, 'MU0304'
UNION ALL SELECT 'MU0305', 'MAKTEC M.PROFIL MT362', 'MESIN', 'MAKTEC', 'UNIT', 2000000.00, 0.000, 1, 'MU0305'
UNION ALL SELECT 'MU0306', 'MAKTEC POWER PLANER', 'MESIN', 'MAKTEC', 'UNIT', 540000.00, 0.000, 1, 'MU0306'
UNION ALL SELECT 'MU0307', 'LIPRO OBENG TEMBUS HANDLE KARET 4\" (+) 100MM', 'OBENG', 'LIPPRO', 'PCS', 22000.00, 0.000, 1, 'MU0307'
UNION ALL SELECT 'MU0308', 'LIPRO OBENG TEMBUS HANDLE KARET 4\" (-) 100MM', 'OBENG', 'LIPPRO', 'PCS', 22000.00, 0.000, 1, 'MU0308'
UNION ALL SELECT 'MU0309', 'LIPRO OBENG TEMBUS HANDLE KARET 5\" (+)125MM', 'OBENG', 'LIPPRO', 'PCS', 23000.00, 0.000, 1, 'MU0309'
UNION ALL SELECT 'MU0310', 'LIPRO OBENG TEMBUS HANDLE KARET 5\" (-)125MM', 'OBENG', 'LIPPRO', 'PCS', 23000.00, 0.000, 1, 'MU0310'
UNION ALL SELECT 'MU0311', 'LIPRO OBENG TEMBUS HANDLE KARET 6\" (+)150MM', 'OBENG', 'LIPPRO', 'PCS', 24000.00, 0.000, 1, 'MU0311'
UNION ALL SELECT 'MU0312', 'LIPRO OBENG TEMBUS HANDLE KARET 6\" (-)150MM', 'OBENG', 'LIPPRO', 'PCS', 24000.00, 0.000, 1, 'MU0312'
UNION ALL SELECT 'MU0313', 'LIPRO OBENG TEMBUS HANDLE KARET 8\" (+)200MM', 'OBENG', 'LIPPRO', 'PCS', 27000.00, 0.000, 1, 'MU0313'
UNION ALL SELECT 'MU0314', 'LIPRO OBENG TEMBUS HANDLE KARET 8\" (-)200MM', 'OBENG', 'LIPPRO', 'PCS', 27000.00, 0.000, 1, 'MU0314'
UNION ALL SELECT 'MU0315', 'KABEL TIES WIPRO 3.6X200', 'KLEM TARIK', 'WIPRO', 'PAK', 25000.00, 0.000, 1, 'MU0315'
UNION ALL SELECT 'MU0316', 'LEM SILICON BLACK ACETIC 300 GR', 'LEM', 'TAIYO', 'BTL', 20900.00, 6.000, 1, 'MU0316'
UNION ALL SELECT 'MU0317', 'NACHI MATA BOR BESI 10.5MM', 'MATA BOR', 'NACHI', 'PCS', 147060.00, 0.000, 1, 'MU0317'
UNION ALL SELECT 'MU0318', 'NACHI MATA BOR BESI 6MM', 'MATA BOR', 'NACHI', 'PCS', 47780.00, 0.000, 1, 'MU0318'
UNION ALL SELECT 'MU0319', 'NACHI MATA BOR BESI 6,5MM', 'MATA BOR', 'NACHI', 'PCS', 54560.00, 0.000, 1, 'MU0319'
UNION ALL SELECT 'MU0320', 'NACHI MATA BOR BESI 8MM', 'MATA BOR', 'NACHI', 'PCS', 75400.00, 0.000, 1, 'MU0320'
UNION ALL SELECT 'MU0321', 'NACHI MATA BOR BESI 10MM', 'MATA BOR', 'NACHI', 'PCS', 125120.00, 6.000, 1, 'MU0321'
UNION ALL SELECT 'MU0322', 'NACHI MATA BOR BESI 12MM', 'MATA BOR', 'NACHI', 'PCS', 183920.00, 0.000, 1, 'MU0322'
UNION ALL SELECT 'MU0323', 'NACHI MATA BOR BESI 3MM', 'MATA BOR', 'NACHI', 'PCS', 19720.00, 10.000, 1, 'MU0323'
UNION ALL SELECT 'MU0324', 'NACHI MATA BOR BESI 3,5MM', 'MATA BOR', 'NACHI', 'PCS', 22040.00, 10.000, 1, 'MU0324'
UNION ALL SELECT 'MU0325', 'NACHI MATA BOR BESI 4MM', 'MATA BOR', 'NACHI', 'PCS', 25540.00, 0.000, 1, 'MU0325'
UNION ALL SELECT 'MU0326', 'NACHI MATA BOR BESI 5MM', 'MATA BOR', 'NACHI', 'PCS', 36500.00, 10.000, 1, 'MU0326'
UNION ALL SELECT 'MU0327', 'LIPRO KUNCI RING PAS 9MM', 'Kunci', 'LIPPRO', 'PCS', 17600.00, 0.000, 1, 'MU0327'
UNION ALL SELECT 'MU0328', 'LIPRO KUNCI RING PAS 17mm', 'Kunci', 'LIPPRO', 'PCS', 24000.00, 0.000, 1, 'MU0328'
UNION ALL SELECT 'MU0329', 'STEKER SAKLAR 3 CABANG', 'Kunci', 'WIPRO', 'PCS', 35700.00, 12.000, 1, 'MU0329'
UNION ALL SELECT 'MU0330', 'KABEL TIES WIPRO 2.5X100', 'KLEM TARIK', 'WIPRO', 'BKS', 10000.00, 10.000, 1, 'MU0330'
UNION ALL SELECT 'MU0331', 'MSN CUCI MOBIL PSP 38', 'MESIN', 'WIPRO', 'UNIT', 1210000.00, 0.000, 1, 'MU0331'
UNION ALL SELECT 'MU0332', 'MSN POMPA CUCI MOBIL PSP 28', 'MESIN', 'WIPRO', 'UNIT', 935000.00, 0.000, 1, 'MU0332'
UNION ALL SELECT 'MU0333', 'SEMEN WARNA KUNING', 'SEMEN', '-', 'BKS', 8000.00, 0.000, 1, 'MU0333'
UNION ALL SELECT 'MU0334', 'SEMEN WARNA BIRU', 'SEMEN', '-', 'BKS', 8000.00, 0.000, 1, 'MU0334'
UNION ALL SELECT 'MU0335', 'SEMEN WARNA HIJAU', 'SEMEN', '-', 'BKS', 8000.00, 0.000, 1, 'MU0335'
UNION ALL SELECT 'MU0336', 'SEMEN WARNA MERAH', 'SEMEN', '-', 'BKS', 8000.00, 0.000, 1, 'MU0336'
UNION ALL SELECT 'MU0337', 'SEMEN WARNA PUTIH RAJAWALI', 'SEMEN', 'RAJAWALI', 'BKS', 8750.00, 0.000, 1, 'MU0337'
UNION ALL SELECT 'MU0338', 'MULLER SILVER/CP FISH', 'KUNCI PINTU', 'MULLER', 'SET', 144000.00, 0.000, 1, 'MU0338'
UNION ALL SELECT 'MU0339', 'HOLO BAJA RINGAN 31X31X0.2', 'BAJA RINGAN', '-', 'BTG', 23000.00, 0.000, 1, 'MU0339'
UNION ALL SELECT 'MU0340', 'MATA BOR KAYU 1/2\' X 400MM', 'MATA BOR', 'WIPRO', 'PCS', 70000.00, 0.000, 1, 'MU0340'
UNION ALL SELECT 'MU0341', 'TANG RIVET', 'TANG', 'LOBSTER', 'PCS', 55200.00, 0.000, 1, 'MU0341'
UNION ALL SELECT 'MU0342', 'TANG BUAYA 10\'', 'TANG', 'WIPRO', 'PCS', 84000.00, 6.000, 1, 'MU0342'
UNION ALL SELECT 'MU0343', 'PIPA BESI HITAM 2X1,7X600', 'BATA', '-', 'BTG', 293600.00, 0.000, 1, 'MU0343'
UNION ALL SELECT 'MU0344', 'OBENG TESTPEN DC OT-05', 'OBENG', 'WIPRO', 'PCS', 27500.00, 0.000, 1, 'MU0344'
UNION ALL SELECT 'MU0345', 'ELBOW 3/4 JAYA', 'SAMBUNGAN', 'JAYA', 'PCS', 1264.00, 0.000, 1, 'MU0345'
UNION ALL SELECT 'MU0346', 'KUNCI PIPA 10\'', 'Kunci', 'WIPRO', 'PCS', 125000.00, 2.000, 1, 'MU0346'
UNION ALL SELECT 'MU0347', 'KUNCI PIPA 12\'', 'Kunci', 'WIPRO', 'PCS', 150000.00, 2.000, 1, 'MU0347'
UNION ALL SELECT 'MU0348', 'KUNCI PIPA 14\'', 'Kunci', 'WIPRO', 'PCS', 180000.00, 2.000, 1, 'MU0348'
UNION ALL SELECT 'MU0349', 'LIPRO KUNCI SOCK CHROME 1/2X8MM (6PT)', 'Kunci', 'LIPPRO', 'PCS', 9000.00, 0.000, 1, 'MU0349'
UNION ALL SELECT 'MU0350', 'LIPRO KUNCI SOCK CHROME 1/2X9MM (6PT)', 'Kunci', 'LIPPRO', 'PCS', 9000.00, 0.000, 1, 'MU0350'
UNION ALL SELECT 'MU0351', 'LIPRO KUNCI SOCK CHROME 1/2X10MM (6PT)', 'Kunci', 'LIPPRO', 'PCS', 9000.00, 0.000, 1, 'MU0351'
UNION ALL SELECT 'MU0352', 'LIPRO KUNCI SOCK CHROME 1/2X11MM (6PT)', 'Kunci', 'LIPPRO', 'PCS', 9000.00, 0.000, 1, 'MU0352'
UNION ALL SELECT 'MU0353', 'LIPRO KUNCI SOCK CHROME 1/2X13MM (6PT)', 'Kunci', 'LIPPRO', 'PCS', 9000.00, 0.000, 1, 'MU0353'
UNION ALL SELECT 'MU0354', 'LIPRO KUNCI SOCK CHROME 1/2X15MM (6PT)', 'Kunci', 'LIPPRO', 'PCS', 10300.00, 0.000, 1, 'MU0354'
UNION ALL SELECT 'MU0355', 'MAKITA VAN BELT N 1900 B', 'V-BELT', 'MAKITA', 'PCS', 55250.00, 0.000, 1, 'MU0355'
UNION ALL SELECT 'MU0356', 'DOMAX GUNTING SENG LURUS 10\'', 'GUNTING', 'DOMAX', 'PCS', 97600.00, 0.000, 1, 'MU0356'
UNION ALL SELECT 'MU0357', 'PALU KARET 16OZ', 'PALU', 'WIPRO', 'PCS', 45000.00, 6.000, 1, 'MU0357'
UNION ALL SELECT 'MU0358', 'KLEP POMPA AIR SANYO', 'S/PART', 'WIPRO', 'PCS', 15000.00, 0.000, 1, 'MU0358'
UNION ALL SELECT 'MU0359', 'CB SKAP', 'S/PART', '-', 'PCS', 25000.00, 0.000, 1, 'MU0359'
UNION ALL SELECT 'MU0360', 'KUNCI INGGRIS 15\'', 'Kunci', 'FUKUNG', 'PCS', 75000.00, 0.000, 1, 'MU0360'
UNION ALL SELECT 'MU0361', 'KLEP POMPA SHIMIZU', 'S/PART', 'WIPRO', 'PCS', 20000.00, 0.000, 1, 'MU0361'
UNION ALL SELECT 'MU0362', 'KUNCI INGGRIS 8\' WIPRO KBS-8', 'Kunci', 'WIPRO', 'PCS', 55000.00, 0.000, 1, 'MU0362'
UNION ALL SELECT 'MU0363', 'LIPRO GREASE GUN 500CC (9620-50)', 'ALAT', 'LIPPRO', 'PCS', 164000.00, 0.000, 1, 'MU0363'
UNION ALL SELECT 'MU0364', 'METERAN 5M BLACKFOOT', 'ALAT UKUR', 'BLACKFOOT', 'PCS', 17100.00, 6.000, 1, 'MU0364'
UNION ALL SELECT 'MU0365', 'METERAN 3M BLACKFOOT', 'ALAT UKUR', 'BLACKFOOT', 'PCS', 12000.00, 6.000, 1, 'MU0365'
UNION ALL SELECT 'MU0366', 'TERPAL A8 RAJAWALI 7X8', 'TERPAL', 'MATAHARI', 'LBR', 536160.00, 0.000, 1, 'MU0366'
UNION ALL SELECT 'MU0367', 'MAGNET PENJEPIT SKRUP MP-25', 'OBENG', 'WIPRO', 'PCS', 12100.00, 0.000, 1, 'MU0367'
UNION ALL SELECT 'MU0368', 'GERGAJI BAHCO 20\' ORANGE', 'GERGAJI', 'BAHCO', 'PCS', 125000.00, 6.000, 1, 'MU0368'
UNION ALL SELECT 'MU0369', 'GERGAJI BAHCO 19\' BIRU', 'GERGAJI', 'BAHCO', 'PCS', 135000.00, 0.000, 1, 'MU0369'
UNION ALL SELECT 'MU0370', 'IGNITION COIL TU-26 MIYAMOTO', 'S/PART', 'MIYAMOTO', 'PCS', 42500.00, 6.000, 1, 'MU0370'
UNION ALL SELECT 'MU0371', 'MALIQA GREEN 25X40', 'TEHEL', 'JUPITER', 'DUS', 69940.00, 8.000, 1, 'MU0371'
UNION ALL SELECT 'MU0372', 'TALI GAS HANDSPRAYER 35CM', 'S/PART', 'MIYAMOTO', 'PCS', 8000.00, 6.000, 1, 'MU0372'
UNION ALL SELECT 'MU0373', 'KRAN TAMAN HONXI 1/2', 'TATAH', 'STRAUSS', 'PCS', 12000.00, 0.000, 1, 'MU0373'
UNION ALL SELECT 'MU0374', 'TATAH KAYU 5/8 FIBER', 'TATAH', 'FIBER', 'PCS', 21600.00, 0.000, 1, 'MU0374'
UNION ALL SELECT 'MU0375', 'MATA BOR PAGODA 4-20', 'MATA BOR', 'BISON', 'PCS', 38500.00, 0.000, 1, 'MU0375'
UNION ALL SELECT 'MU0376', 'TATAH KAYU 1/2 \' STRAUSS', 'TATAH', 'STRAUSS', 'PCS', 20000.00, 6.000, 1, 'MU0376'
UNION ALL SELECT 'MU0377', 'MATA BOR PAGODA  SET 3 PCS', 'MATA BOR', 'BISON', 'PCS', 65000.00, 0.000, 1, 'MU0377'
UNION ALL SELECT 'MU0378', 'TATAH KAYU 3/4 \' FIBER', 'TATAH', 'FIBER', 'PCS', 23750.00, 6.000, 1, 'MU0378'
UNION ALL SELECT 'MU0379', 'TATAH KAYU 1 1/4 \' FIBER', 'TATAH', 'FIBER', 'PCS', 28750.00, 6.000, 1, 'MU0379'
UNION ALL SELECT 'MU0380', 'DOUBLE TAPE FOAM 1X5M', 'ISOLASI', 'MUGEN', 'PCS', 5000.00, 0.000, 1, 'MU0380'
UNION ALL SELECT 'MU0381', 'TATAH KAYU 1 GG.KAYU', 'TATAH', 'FIBER', 'PCS', 19166.00, 5.000, 1, 'MU0381'
UNION ALL SELECT 'MU0382', 'BAN HIDUP ARTCO', 'BAN', 'ARTCO', 'PCS', 135000.00, 6.000, 1, 'MU0382'
UNION ALL SELECT 'MU0383', 'ARCADIA BLACK 40X40', 'KERAMIK', 'ARCADIA', 'DUS', 71394.00, 0.000, 1, 'MU0383'
UNION ALL SELECT 'MU0384', 'LAKBAN GIPSUN', 'LAKBAN', 'NEVADA', 'PCS', 8500.00, 7.000, 1, 'MU0384'
UNION ALL SELECT 'MU0385', 'ARIES GOLD 302 5KG', 'TATAH', 'EAGLE', 'GLN', 101000.00, 0.000, 1, 'MU0385'
UNION ALL SELECT 'MU0386', 'TATAH KAYU 1 1/2 GG.KAYU', 'TATAH', 'FIBER', 'PCS', 26250.00, 5.000, 1, 'MU0386'
UNION ALL SELECT 'MU0387', 'TANG KOMBINASI MULLER 6\"', 'TANG', 'MULLER', 'PCS', 35000.00, 0.000, 1, 'MU0387'
UNION ALL SELECT 'MU0388', 'TAPISAN SANTAN BESAR', 'TAPISAN', '-', 'PCS', 6400.00, 0.000, 1, 'MU0388'
UNION ALL SELECT 'MU0389', 'SAMBUNGAN INSTALASI 3 CABANG', 'PIPA', 'NISO', 'PCS', 4000.00, 0.000, 1, 'MU0389'
UNION ALL SELECT 'MU0390', 'WASKOM RAJUT', 'BASKOM', 'RAJUT', 'PCS', 3200.00, 0.000, 1, 'MU0390'
UNION ALL SELECT 'MU0391', 'TAPISAN SANTAN KECIL', 'TAPISAN', '-', 'PCS', 5600.00, 0.000, 1, 'MU0391'
UNION ALL SELECT 'MU0392', 'PARANG BUAYA 18 GG PVC', 'PARANG', 'BUAYA', 'PCS', 25000.00, 0.000, 1, 'MU0392'
UNION ALL SELECT 'MU0393', 'SENG BIRU 0.25X6M', 'SENG', '-', 'LBR', 201748.00, 0.000, 1, 'MU0393'
UNION ALL SELECT 'MU0394', 'HAMMER KAMBING MINI IGM', 'PALU', 'IGM', 'PCS', 27000.00, 0.000, 1, 'MU0394'
UNION ALL SELECT 'MU0395', 'BOR PANJANG 1/2X500', 'MATA BOR', 'MDK', 'PCS', 44800.00, 0.000, 1, 'MU0395'
UNION ALL SELECT 'MU0396', 'BOR KAPAL 5/8X500', 'MATA BOR', 'MADICO', 'PCS', 54400.00, 0.000, 1, 'MU0396'
UNION ALL SELECT 'MU0397', 'BOR PANJANG 3/4 MDK', 'MATA BOR', 'MDK', 'PCS', 60000.00, 0.000, 1, 'MU0397'
UNION ALL SELECT 'MU0398', 'BOR JCK 5/16', 'MATA BOR', 'JCK', 'PCS', 28000.00, 0.000, 1, 'MU0398'
UNION ALL SELECT 'MU0399', 'MATA BOR KAYU 5/8 PENDEK MDK', 'MATA BOR', 'MDK', 'PCS', 28000.00, 0.000, 1, 'MU0399'
UNION ALL SELECT 'MU0400', 'BOR PENDEK 3/4 MDK', 'MATA BOR', 'MDK', 'PCS', 32000.00, 0.000, 1, 'MU0400'
UNION ALL SELECT 'MU0401', 'PISAU ID 105', 'PISAU', '-', 'PCS', 20000.00, 0.000, 1, 'MU0401'
UNION ALL SELECT 'MU0402', 'SETRIKA PHILIPS DST 0510 HIJAU', 'SETRIKA', 'PHILIP', 'UNIT', 145000.00, 1.000, 1, 'MU0402'
UNION ALL SELECT 'MU0403', 'MATA OBENG TUMBUK PH1 +', 'OBENG', 'WIPRO', 'PCS', 11500.00, 0.000, 1, 'MU0403'
UNION ALL SELECT 'MU0404', 'MATA OBENG TUMBUK PH3 +', 'OBENG', 'WIPRO', 'PCS', 11500.00, 0.000, 1, 'MU0404'
UNION ALL SELECT 'MU0405', 'HAND SOCKET 8MM X 1/2\' WIPRO', 'Kunci', 'WIPRO', 'PCS', 11000.00, 0.000, 1, 'MU0405'
UNION ALL SELECT 'MU0406', 'KOBOT LINGGIS KECIL 8', 'LINGGIS', '-', 'PCS', 30000.00, 0.000, 1, 'MU0406'
UNION ALL SELECT 'MU0407', 'TARIKAN LACI BULAT CHROOM', 'AIR AKI', 'YUASA', 'PCS', 0.00, 0.000, 1, 'MU0407'
UNION ALL SELECT 'MU0408', 'DODOS BISON', 'DODOS', 'BISON', 'PCS', 41600.00, 0.000, 1, 'MU0408'
UNION ALL SELECT 'MU0409', 'GRENDEL KURA 2\'\'', 'GRENDEL', '-', 'PCS', 3500.00, 12.000, 1, 'MU0409'
UNION ALL SELECT 'MU0410', 'OVERVAL KOTAK 2\" IGM', 'GRENDEL', 'IGM', 'PCS', 1791.00, 12.000, 1, 'MU0410'
UNION ALL SELECT 'MU0411', 'OVERVAL KOTAK 2 1/2\" IGM', 'GRENDEL', 'IGM', 'PCS', 1958.00, 12.000, 1, 'MU0411'
UNION ALL SELECT 'MU0412', 'CETOK CORALEY PVC', 'CETOK', 'CORALEY', 'PCS', 7000.00, 0.000, 1, 'MU0412'
UNION ALL SELECT 'MU0414', 'KAWAT HARMONIKA 1M TEBAL', 'KAWAT', '-', 'MTR', 55000.00, 0.000, 1, 'MU0414'
UNION ALL SELECT 'MU0415', 'KAWAT HARMONIKA 2 M TEBAL', 'KAWAT', '-', 'MTR', 110000.00, 0.000, 1, 'MU0415'
UNION ALL SELECT 'MU0416', 'PROFILA 88 250 L', 'PROFIL', 'PROFILA 88', 'SET', 807500.00, 0.000, 1, 'MU0416'
UNION ALL SELECT 'MU0417', 'PROFILA 88 450 L', 'PROFIL', 'PROFILA 88', 'SET', 1187500.00, 0.000, 1, 'MU0417'
UNION ALL SELECT 'MU0418', 'KUNCI GT ATS PENDEK 40', 'GEMBOK', 'ATS', 'PCS', 25000.00, 0.000, 1, 'MU0418'
UNION ALL SELECT 'MU0419', 'TROWOL BESI LANCIP', 'TROWOL', 'FUKUDA', 'PCS', 21000.00, 0.000, 1, 'MU0419'
UNION ALL SELECT 'MU0420', 'PENSIL TUKANG MAKITA', 'PENSIL', 'MAKITA', 'PCS', 2000.00, 0.000, 1, 'MU0420'
UNION ALL SELECT 'MU0421', 'JAYA MULYA 122', 'TARIKAN PINTU', 'FRANCO', 'SET', 61600.00, 0.000, 1, 'MU0421'
UNION ALL SELECT 'MU0422', 'BOR BESI MAS 5MM IGM', 'MATA BOR', 'IGM', 'PCS', 15200.00, 0.000, 1, 'MU0422'
UNION ALL SELECT 'MU0423', 'BOR BESI MAS 6MM IGM', 'MATA BOR', 'IGM', 'PCS', 8000.00, 0.000, 1, 'MU0423'
UNION ALL SELECT 'MU0424', 'BOR BESI BETON 6MM', 'MATA BOR', 'ALWIN', 'PCS', 18400.00, 10.000, 1, 'MU0424'
UNION ALL SELECT 'MU0425', 'BOR BESI BETON 5MM', 'MATA BOR', 'JORAN', 'PCS', 16000.00, 0.000, 1, 'MU0425'
UNION ALL SELECT 'MU0426', 'BOR BESI MAS 10MM IGM', 'MATA BOR', '-', 'PCS', 19000.00, 0.000, 1, 'MU0426'
UNION ALL SELECT 'MU0427', 'KIKIR SEGI TIGA 3 INDIA', 'KIKIR', 'INDIA', 'PCS', 13600.00, 6.000, 1, 'MU0427'
UNION ALL SELECT 'MU0428', 'KIKIR SEGI TIGA 3 1/2 INDIA', 'KIKIR', 'INDIA', 'PCS', 15000.00, 0.000, 1, 'MU0428'
UNION ALL SELECT 'MU0429', 'KIKIR SEGI TIGA 4 INDIA', 'KIKIR', 'INDIA', 'PCS', 7083.00, 7.000, 1, 'MU0429'
UNION ALL SELECT 'MU0430', 'KIKIR SEGI TIGA 5 HERIN', 'KIKIR', 'INDIA', 'PCS', 7916.00, 12.000, 1, 'MU0430'
UNION ALL SELECT 'MU0431', 'KIKIR SEGI TIGA 6 PROHEX', 'KIKIR', 'PROHEX', 'PCS', 19000.00, 0.000, 1, 'MU0431'
UNION ALL SELECT 'MU0432', 'GANCO RRT', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU0432'
UNION ALL SELECT 'MU0433', 'KUNCI ATS PANJANG 30', 'V-BELT', 'ATS', 'PCS', 20000.00, 0.000, 1, 'MU0433'
UNION ALL SELECT 'MU0434', 'KUNCI GT ATS PANJANG 40', 'V-BELT', 'ATS', 'PCS', 28000.00, 0.000, 1, 'MU0434'
UNION ALL SELECT 'MU0435', 'KUNCI GT ATS PANJANG 50', 'V-BELT', 'ATS', 'PCS', 30000.00, 0.000, 1, 'MU0435'
UNION ALL SELECT 'MU0436', 'ISOLATOR NACHI HITAM', 'ISOLATOR', 'NACHI', 'PCS', 12000.00, 0.000, 1, 'MU0436'
UNION ALL SELECT 'MU0437', 'MCB IP C4', 'MCB', 'SCHNEIDER', 'PCS', 91000.00, 0.000, 1, 'MU0437'
UNION ALL SELECT 'MU0438', 'MCB IP C6', 'MCB', 'SCHNEIDER', 'PCS', 72875.00, 0.000, 1, 'MU0438'
UNION ALL SELECT 'MU0439', 'MCB IP 10A DOMAI', 'MCB', 'SCHNEIDER', 'PCS', 72875.00, 0.000, 1, 'MU0439'
UNION ALL SELECT 'MU0440', 'SAKLAR DINDING PANASONIK DOUBLE 3900', 'SAKLAR', 'PANASONIC', 'PCS', 16000.00, 0.000, 1, 'MU0440'
UNION ALL SELECT 'MU0441', 'STOP KONTAK TEMPEL PANASONIK', 'TERMINAL COK', 'PANASONIC', 'PCS', 13000.00, 0.000, 1, 'MU0441'
UNION ALL SELECT 'MU0442', 'SAKLAR DINDING PANASONIK TUNGGAL 3001', 'SAKLAR', 'PANASONIC', 'PCS', 11000.00, 0.000, 1, 'MU0442'
UNION ALL SELECT 'MU0443', 'SELANG SPIRAL 6 INCI', 'SELANG', '-', 'MTR', 168000.00, 5.000, 1, 'MU0443'
UNION ALL SELECT 'MU0444', 'KABEL NYM 2X2,5 ETERNA', 'KABEL', 'ETERNA', '50 METER', 1138000.00, 0.000, 1, 'MU0444'
UNION ALL SELECT 'MU0445', 'KABEL NYM 2X1,5 ETERNA', 'KABEL', 'ETERNA', '50 METER', 748000.00, 0.000, 1, 'MU0445'
UNION ALL SELECT 'MU0446', 'MATA BOR OBENG HEAVY DUTY PH2', 'OBENG', 'WIPRO', 'PCS', 6600.00, 0.000, 1, 'MU0446'
UNION ALL SELECT 'MU0447', 'KABEL NYA 1.5 ETERNA MERAH', 'KABEL', 'ETERNA', '50 METER', 284000.00, 0.000, 1, 'MU0447'
UNION ALL SELECT 'MU0448', 'KABEL NYA 1.5 ETERNA HITAM', 'KABEL', 'ETERNA', '50 METER', 284000.00, 0.000, 1, 'MU0448'
UNION ALL SELECT 'MU0449', 'KABEL NYA 2,5 ETERNA MERAH', 'KABEL', 'ETERNA', '50 METER', 452000.00, 0.000, 1, 'MU0449'
UNION ALL SELECT 'MU0450', 'KABEL NYA 2,5 ETERNA HITAM', 'KABEL', 'ETERNA', '50 METER', 452000.00, 0.000, 1, 'MU0450'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU0451' AS sku, 'TERMINAL KABEL 4L +  5 MTR' AS name, 'KABEL' AS category_name, 'PAGODA' AS brand_name, 'PCS' AS base_unit_code, 23000.00 AS cost_price, 0.000 AS min_stock, 1 AS is_active, 'MU0451' AS legacy_sku
UNION ALL SELECT 'MU0452', 'SUTEL 555', 'SUTIL', '555', 'PCS', 36800.00, 0.000, 1, 'MU0452'
UNION ALL SELECT 'MU0453', 'SUTEL SINDA', 'SUTIL', 'SINDA', 'PCS', 27200.00, 0.000, 1, 'MU0453'
UNION ALL SELECT 'MU0454', 'KABEL NYM 3X2,5 ETERNA', 'KABEL', 'ETERNA', '50 METER', 1569000.00, 0.000, 1, 'MU0454'
UNION ALL SELECT 'MU0455', 'ENGKEL DAN KONTAK BROKO NEW', 'MATA SERKEL', 'BROCO', 'PCS', 16750.00, 0.000, 1, 'MU0455'
UNION ALL SELECT 'MU0456', 'TUTUP PIPA 1', 'SAMBUNGAN', 'POWER', 'PCS', 2480.00, 0.000, 1, 'MU0456'
UNION ALL SELECT 'MU0457', 'SIKAT WC BT 020', 'SIKAT', 'MASPION', 'PCS', 13600.00, 0.000, 1, 'MU0457'
UNION ALL SELECT 'MU0458', 'PIPA BESI GALVANISH 3/4 \'', 'BESI', '-', 'BTG', 134600.00, 0.000, 1, 'MU0458'
UNION ALL SELECT 'MU0459', 'PIPA BESI GALVANISH 1,5X2X6', 'BESI', '-', 'BTG', 281600.00, 0.000, 1, 'MU0459'
UNION ALL SELECT 'MU0460', 'PANCI TD 28CM', 'MATA SERKEL', '-', 'PCS', 48200.00, 0.000, 1, 'MU0460'
UNION ALL SELECT 'MU0461', 'STOCK BASKET BS', 'MATA SERKEL', '-', 'PCS', 9200.00, 0.000, 1, 'MU0461'
UNION ALL SELECT 'MU0462', 'STOCK BASKET A4', 'MATA SERKEL', '-', 'PCS', 11675.00, 0.000, 1, 'MU0462'
UNION ALL SELECT 'MU0463', 'AKTIVE BASKET L', 'KAWAT', '-', 'PCS', 14500.00, 0.000, 1, 'MU0463'
UNION ALL SELECT 'MU0464', 'KERANJANG HAMPER', 'KERANJANG', 'HAMPER', 'PCS', 53600.00, 0.000, 1, 'MU0464'
UNION ALL SELECT 'MU0465', 'MONTANA 27 L', 'TEMPAT', '-', 'PCS', 133250.00, 0.000, 1, 'MU0465'
UNION ALL SELECT 'MU0466', 'KERANJANG DECO BASKET', 'KERANJANG', 'MASPION', 'PCS', 68800.00, 0.000, 1, 'MU0466'
UNION ALL SELECT 'MU0467', 'KERANJANG BAJU HAMPER', 'KERANJANG', 'HAMPER', 'PCS', 70400.00, 0.000, 1, 'MU0467'
UNION ALL SELECT 'MU0468', 'TEMPAT SAMPAH GREEN LEFT', 'TEMPAT', 'MASPION', 'PCS', 172000.00, 0.000, 1, 'MU0468'
UNION ALL SELECT 'MU0469', 'TEMPAT SAMPAH TRS 66', 'TEMPAT', 'TRS 66', 'PCS', 156800.00, 0.000, 1, 'MU0469'
UNION ALL SELECT 'MU0470', 'BAK MANDI BAYI HAITI', 'BAK', 'HAITI', 'PCS', 46625.00, 0.000, 1, 'MU0470'
UNION ALL SELECT 'MU0471', 'BAK MANDI BAYI LOVINA', 'BAK', 'LOVINA', 'PCS', 71200.00, 0.000, 1, 'MU0471'
UNION ALL SELECT 'MU0472', 'PIPA BESI HITAM 1X1,5X6', 'BESI', '-', 'BTG', 125600.00, 0.000, 1, 'MU0472'
UNION ALL SELECT 'MU0473', 'BESI ULIR 10\"', 'BESI', '-', 'BTG', 40000.00, 0.000, 1, 'MU0473'
UNION ALL SELECT 'MU0474', 'BESI TRALIS KOTAK', 'BESI', '-', 'BTG', 16800.00, 0.000, 1, 'MU0474'
UNION ALL SELECT 'MU0475', 'TALI 12MM GULUNG', 'TALI', '-', 'KG', 40000.00, 0.000, 1, 'MU0475'
UNION ALL SELECT 'MU0476', 'KERANJANG BAJU BESAR', 'KERANJANG', '-', 'PCS', 24900.00, 0.000, 1, 'MU0476'
UNION ALL SELECT 'MU0477', 'DECO BASKET', 'MATA SERKEL', '-', 'PCS', 52250.00, 0.000, 1, 'MU0477'
UNION ALL SELECT 'MU0478', 'TIMBA SUPER 4 GALON', 'EMBER', 'MASPION', 'PCS', 24000.00, 0.000, 1, 'MU0478'
UNION ALL SELECT 'MU0479', 'TIMBA SUPER 3 GALON', 'EMBER', '-', 'PCS', 19375.00, 0.000, 1, 'MU0479'
UNION ALL SELECT 'MU0480', 'TIMBA ELEGANT 6 + TUTUP', 'EMBER', '-', 'PCS', 40650.00, 0.000, 1, 'MU0480'
UNION ALL SELECT 'MU0481', 'WASKOM NAGATA 817', 'BASKOM', 'NAGATA', 'PCS', 41900.00, 0.000, 1, 'MU0481'
UNION ALL SELECT 'MU0482', 'WASKOM NAGATA 814', 'BASKOM', 'NAGATA', 'PCS', 0.00, 0.000, 1, 'MU0482'
UNION ALL SELECT 'MU0483', 'TEMPAT SAMPAH KRISBOW', 'TEMPAT', 'KRISBOW', 'PCS', 400000.00, 0.000, 1, 'MU0483'
UNION ALL SELECT 'MU0484', 'CERMIN B M013', 'CERMIN', 'MASPION', 'PCS', 46400.00, 0.000, 1, 'MU0484'
UNION ALL SELECT 'MU0485', 'CERMIN B M012', 'CERMIN', 'MASPION', 'PCS', 46400.00, 0.000, 1, 'MU0485'
UNION ALL SELECT 'MU0486', 'CERMIN B M014', 'CERMIN', 'MASPION', 'PCS', 42400.00, 0.000, 1, 'MU0486'
UNION ALL SELECT 'MU0487', 'CERMIN MARIOR BOX C19', 'CERMIN', '-', 'PCS', 138400.00, 0.000, 1, 'MU0487'
UNION ALL SELECT 'MU0488', 'KAPAK CAP MATA 700', 'MATA SERKEL', 'SCHLIEPER', 'PCS', 80000.00, 6.000, 1, 'MU0488'
UNION ALL SELECT 'MU0489', 'TEMPAT DONAT 5000', 'CONTAINER', 'VARIA', 'SET', 17000.00, 0.000, 1, 'MU0489'
UNION ALL SELECT 'MU0490', 'KACA WM 18', 'CERMIN', '-', 'PCS', 108800.00, 0.000, 1, 'MU0490'
UNION ALL SELECT 'MU0491', 'TEMPAT DONAT 7200', 'CONTAINER', 'VARIA', 'SET', 20000.00, 0.000, 1, 'MU0491'
UNION ALL SELECT 'MU0492', 'KRAN AQUA PENCET', 'KRAN', '-', 'PCS', 6000.00, 0.000, 1, 'MU0492'
UNION ALL SELECT 'MU0493', 'SKRUP KALSIBOT 1\'', 'SKRUP', '-', 'DUS', 100000.00, 0.000, 1, 'MU0493'
UNION ALL SELECT 'MU0494', 'HAK ANGIN GOLD 8\" JM 502', 'HAK ANGIN', 'JAYA MULIA', 'SET', 5833.00, 0.000, 1, 'MU0494'
UNION ALL SELECT 'MU0495', 'SQUAS JAR 26L', 'MATA SERKEL', '-', 'PCS', 70250.00, 0.000, 1, 'MU0495'
UNION ALL SELECT 'MU0496', 'KURSI EKSPANA', 'KURSI', 'MASPION', 'PCS', 62400.00, 0.000, 1, 'MU0496'
UNION ALL SELECT 'MU0497', 'SPONGE MOP BC 074', 'MATA SERKEL', '-', 'PCS', 101300.00, 0.000, 1, 'MU0497'
UNION ALL SELECT 'MU0498', 'BESI STRIP 3X1,7X400', 'BESI', '-', 'BTG', 45000.00, 0.000, 1, 'MU0498'
UNION ALL SELECT 'MU0499', 'FRYPAN CASABLANKA 26', 'MATA SERKEL', '-', 'PCS', 99500.00, 0.000, 1, 'MU0499'
UNION ALL SELECT 'MU0500', 'OVAL FLOOR MOP BC 070', 'MATA SERKEL', '-', 'PCS', 28000.00, 0.000, 1, 'MU0500'
UNION ALL SELECT 'MU0501', 'SAPU DRAGON', 'SAPU', 'DRAGON', 'PCS', 26400.00, 0.000, 1, 'MU0501'
UNION ALL SELECT 'MU0502', 'SAPU JERMAN', 'MATA SERKEL', '-', 'PCS', 26600.00, 0.000, 1, 'MU0502'
UNION ALL SELECT 'MU0503', 'SIKAT WC BT 020 S', 'SIKAT', 'MASPION', 'PCS', 20000.00, 0.000, 1, 'MU0503'
UNION ALL SELECT 'MU0504', 'SAPU DEBU BC 082', 'MATA SERKEL', '-', 'PCS', 113300.00, 0.000, 1, 'MU0504'
UNION ALL SELECT 'MU0505', 'SIKAT WC 024', 'MATA SERKEL', '-', 'PCS', 10400.00, 0.000, 1, 'MU0505'
UNION ALL SELECT 'MU0506', 'SIKAT TOILET SET  BT 033S', 'MATA SERKEL', '-', 'PCS', 23900.00, 0.000, 1, 'MU0506'
UNION ALL SELECT 'MU0507', 'AMPLAS GRIT 1000', 'AMPLAS', '-', 'LBR', 4800.00, 0.000, 1, 'MU0507'
UNION ALL SELECT 'MU0508', 'VIALE GRISS 50X50', 'TEHEL', 'DELTA', 'DUS', 68400.00, 8.000, 1, 'MU0508'
UNION ALL SELECT 'MU0509', 'KUNCI PINTU 258M-NB/GP SANSHAN', 'KUNCI PINTU', 'SANSHAN', 'SET', 97000.00, 2.000, 1, 'MU0509'
UNION ALL SELECT 'MU0510', 'KAPI BESI NP 3\"', 'KAPI', 'NIPPON PAINT', 'PCS', 11500.00, 0.000, 1, 'MU0510'
UNION ALL SELECT 'MU0511', 'ANSELMO GREY 50X50', 'TEHEL', 'ACCURA', 'DUS', 88000.00, 8.000, 1, 'MU0511'
UNION ALL SELECT 'MU0512', 'Gancu biru RRT CRD', 'ALAT', 'RRT', 'PCS', 68000.00, 0.000, 1, 'MU0512'
UNION ALL SELECT 'MU0513', 'PEL-PEL.AN BESAR', 'PENGEPEL', 'NAGOYA', 'PCS', 30000.00, 0.000, 1, 'MU0513'
UNION ALL SELECT 'MU0514', 'KAWAT PANCI', 'PENGGOSOK', '-', 'PCS', 2500.00, 0.000, 1, 'MU0514'
UNION ALL SELECT 'MU0515', 'TERPAL A5L LUMBA-LUMBA 7X8', 'TERPAL', 'LUMBA-LUMBA', 'LBR', 347170.00, 0.000, 1, 'MU0515'
UNION ALL SELECT 'MU0516', 'AMPLAS GRIT 80', 'AMPLAS', 'TAIYO', 'LBR', 2522.00, 0.000, 1, 'MU0516'
UNION ALL SELECT 'MU0517', 'TERPAL A5L LUMBA LUMBA 3X4', 'TERPAL', 'LUMBA-LUMBA', 'LBR', 72653.00, 0.000, 1, 'MU0517'
UNION ALL SELECT 'MU0518', 'TERPAL A5L LUMBA-LUMBA 4X5', 'TERPAL', 'LUMBA-LUMBA', 'LBR', 120080.00, 0.000, 1, 'MU0518'
UNION ALL SELECT 'MU0519', 'TERPAL A5L MATAHARI BARU 4X6', 'TERPAL', 'MATAHARI', 'LBR', 142280.00, 0.000, 1, 'MU0519'
UNION ALL SELECT 'MU0520', 'TEMPAT MINYAK TANAH 30 SUMBU', 'TEMPAT', 'HOCK', 'PCS', 84000.00, 6.000, 1, 'MU0520'
UNION ALL SELECT 'MU0521', 'BESI STRIP 2X1,7X400', 'BESI', '-', 'BTG', 29600.00, 0.000, 1, 'MU0521'
UNION ALL SELECT 'MU0522', 'BESI STRIP 1,5X1X400', 'BESI', '-', 'BTG', 24800.00, 0.000, 1, 'MU0522'
UNION ALL SELECT 'MU0523', 'EMBER COR ORANGE TC 15', 'MATA SERKEL', '-', 'PCS', 6000.00, 30.000, 1, 'MU0523'
UNION ALL SELECT 'MU0524', 'TEE 3/4 JAYA', 'SAMBUNGAN', 'JAYA', 'PCS', 1792.00, 0.000, 1, 'MU0524'
UNION ALL SELECT 'MU0525', 'SANSAW STIHLL 210 MS/18\'', 'MATA SERKEL', '-', 'PCS', 2600000.00, 0.000, 1, 'MU0525'
UNION ALL SELECT 'MU0526', 'IRUS NONA 38CM', 'IRUS', 'NONA', 'PCS', 38400.00, 0.000, 1, 'MU0526'
UNION ALL SELECT 'MU0527', 'BAJA RINGAN U 3X5X400', 'BESI', '-', 'BTG', 40000.00, 0.000, 1, 'MU0527'
UNION ALL SELECT 'MU0528', 'SIKU RAK MERAH', 'BESI', '-', 'BTG', 35000.00, 10.000, 1, 'MU0528'
UNION ALL SELECT 'MU0529', 'PANCI SET GNS 5', 'PANCI', '555', 'SET', 352800.00, 0.000, 1, 'MU0529'
UNION ALL SELECT 'MU0530', 'CONTROL LEVER', 'MATA SERKEL', '-', 'PCS', 23000.00, 0.000, 1, 'MU0530'
UNION ALL SELECT 'MU0531', 'FLEXIBLE SHAFT FR 3001', 'MATA SERKEL', '-', 'PCS', 100000.00, 0.000, 1, 'MU0531'
UNION ALL SELECT 'MU0532', 'PISAU FLASH 8', 'PISAU', 'FLASH', 'PCS', 15200.00, 0.000, 1, 'MU0532'
UNION ALL SELECT 'MU0533', 'RUBBER BUFFER FR3000', 'MATA SERKEL', '-', 'PCS', 8000.00, 0.000, 1, 'MU0533'
UNION ALL SELECT 'MU0534', 'SPARK PLUG 6F', 'MATA SERKEL', '-', 'PCS', 20000.00, 0.000, 1, 'MU0534'
UNION ALL SELECT 'MU0535', 'KARET LIDAH ALCON', 'MATA SERKEL', 'NOQIWA', 'PCS', 36000.00, 6.000, 1, 'MU0535'
UNION ALL SELECT 'MU0536', 'GIANICOLO PER KAMPAS SENSOR', 'MATA SERKEL', 'GIANOCOLO', 'PCS', 8000.00, 6.000, 1, 'MU0536'
UNION ALL SELECT 'MU0537', 'PROFIL HIDROFILL 700 L', 'PROFIL', 'HIDROFILL', 'UNIT', 1200000.00, 0.000, 1, 'MU0537'
UNION ALL SELECT 'MU0538', 'KANFAS + PER', 'MATA SERKEL', '-', 'PCS', 46500.00, 0.000, 1, 'MU0538'
UNION ALL SELECT 'MU0539', 'COIL NW HITAM', 'MATA SERKEL', '-', 'PCS', 73000.00, 0.000, 1, 'MU0539'
UNION ALL SELECT 'MU0540', 'SIKAT LANTAI DRAGON', 'SIKAT', 'DRAGON', 'PCS', 24000.00, 0.000, 1, 'MU0540'
UNION ALL SELECT 'MU0541', 'PANCI SET KOREA', 'PANCI', '-', 'SET', 402400.00, 0.000, 1, 'MU0541'
UNION ALL SELECT 'MU0542', 'TEFLON MAXIM VALENTINO 26 CM', 'TEFLON', 'MAXIM', 'PCS', 106000.00, 0.000, 1, 'MU0542'
UNION ALL SELECT 'MU0543', 'MULLER 975233', 'V-BELT', 'MULLER', 'SET', 224000.00, 0.000, 1, 'MU0543'
UNION ALL SELECT 'MU0544', 'MULLER 68209', 'V-BELT', 'MULLER', 'SET', 224000.00, 3.000, 1, 'MU0544'
UNION ALL SELECT 'MU0545', 'KIPAS DUDUK COSMOS 12-BFD', 'KIPAS', 'COSMOS', 'UNIT', 255000.00, 1.000, 1, 'MU0545'
UNION ALL SELECT 'MU0546', 'YONGMA SMC 5049', 'MAGICCOM', 'YONGMA', 'UNIT', 1843200.00, 0.000, 1, 'MU0546'
UNION ALL SELECT 'MU0547', 'MULLER 978288', 'KUNCI PINTU', 'MULLER', 'SET', 224000.00, 3.000, 1, 'MU0547'
UNION ALL SELECT 'MU0548', 'DOOR LOCK WANLY W5017', 'MATA SERKEL', '-', 'SET', 65000.00, 0.000, 1, 'MU0548'
UNION ALL SELECT 'MU0549', 'MULLER ECO 58205 BLACK', 'KUNCI PINTU', 'MULLER', 'SET', 155000.00, 3.000, 1, 'MU0549'
UNION ALL SELECT 'MU0550', 'PHILIPS ESSENTIAL 8W', 'LAMPU', 'PHILIP', 'PCS', 25600.00, 0.000, 1, 'MU0550'
UNION ALL SELECT 'MU0551', 'PHILIPS LED 10W', 'LAMPU', 'PHILIP', 'PCS', 44000.00, 0.000, 1, 'MU0551'
UNION ALL SELECT 'MU0552', 'PHILIPS ESSENTIAL 11W', 'LAMPU', 'PHILIP', 'PCS', 29600.00, 0.000, 1, 'MU0552'
UNION ALL SELECT 'MU0553', 'PHILIPS TORNADO 12W', 'TEER', 'HANDSOME', 'PCS', 34400.00, 0.000, 1, 'MU0553'
UNION ALL SELECT 'MU0554', 'POMPA AIR SANYO  P-H137 C', 'MESIN', 'SANYO', 'UNIT', 656300.00, 0.000, 1, 'MU0554'
UNION ALL SELECT 'MU0555', 'HAK ANGIN SS BULAT 056', 'HAK ANGIN', 'VALLEN', 'PCS', 6666.00, 12.000, 1, 'MU0555'
UNION ALL SELECT 'MU0556', 'PHILIPS LED 14,5W', 'LAMPU', 'PHILIP', 'PCS', 58680.00, 5.000, 1, 'MU0556'
UNION ALL SELECT 'MU0557', 'DOOR LOCK SOBAR 833-6338', 'MATA SERKEL', '-', 'SET', 85000.00, 0.000, 1, 'MU0557'
UNION ALL SELECT 'MU0558', 'DOOR LOCK WAR CX9', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU0558'
UNION ALL SELECT 'MU0559', 'AMANI BOLT', 'ACCU', '-', 'PCS', 0.00, 0.000, 1, 'MU0559'
UNION ALL SELECT 'MU0560', 'PHILIPS LED 8W', 'TEER', 'HANDSOME', 'PCS', 40800.00, 0.000, 1, 'MU0560'
UNION ALL SELECT 'MU0561', 'ARIES EMULSION 641 20KG', 'CAT', 'ARIES', 'PAIL', 225200.00, 0.000, 1, 'MU0561'
UNION ALL SELECT 'MU0562', 'DEROCCA 008', 'V-BELT', 'DEROCCA', 'SET', 119200.00, 0.000, 1, 'MU0562'
UNION ALL SELECT 'MU0563', 'AVIAN SYNTHETIC 915', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU0563'
UNION ALL SELECT 'MU0564', 'SISIK IKAN', 'MATA SERKEL', '-', 'PCS', 9000.00, 0.000, 1, 'MU0564'
UNION ALL SELECT 'MU0565', 'CENTONG NASI CROWN', 'SENDOK', 'STAINLISSTEEL', 'PCS', 13600.00, 0.000, 1, 'MU0565'
UNION ALL SELECT 'MU0566', 'SENDOK SOUP STAINLISS KECIL', 'SENDOK', 'STAINLISSTEEL', 'PCS', 13600.00, 0.000, 1, 'MU0566'
UNION ALL SELECT 'MU0567', 'SENDOK MAKAN BULAT', 'SENDOK', '-', 'PCS', 4000.00, 0.000, 1, 'MU0567'
UNION ALL SELECT 'MU0568', 'EROS KERANG', 'SENDOK', 'INDOGLASS', 'PCS', 4000.00, 0.000, 1, 'MU0568'
UNION ALL SELECT 'MU0569', 'KIKIR SANSAW 5.5\' WIPRO', 'MATA SERKEL', 'WIPRO', 'PCS', 16000.00, 0.000, 1, 'MU0569'
UNION ALL SELECT 'MU0570', 'MASTER CVT BELT CONDITIONER', 'OLI', 'MASTER', 'KLG', 14400.00, 0.000, 1, 'MU0570'
UNION ALL SELECT 'MU0571', 'ARIES GOLD 405 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU0571'
UNION ALL SELECT 'MU0572', 'ARIES GOLD 503 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU0572'
UNION ALL SELECT 'MU0573', 'ARIES GOLD 603 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU0573'
UNION ALL SELECT 'MU0574', 'YONGMA SMC 5301', 'MAGICCOM', 'YONGMA', 'PCS', 600800.00, 0.000, 1, 'MU0574'
UNION ALL SELECT 'MU0575', 'ELYSSION SOCKET DRIVE WRENCH', 'MATA SERKEL', 'ELYSSION', 'PCS', 12000.00, 0.000, 1, 'MU0575'
UNION ALL SELECT 'MU0576', 'HAND SOKCET KUNCI 8-32 WIPRO', 'V-BELT', 'WIPRO', 'SET', 560000.00, 0.000, 1, 'MU0576'
UNION ALL SELECT 'MU0577', 'WIPRO PENYEDOT TIMAH STS-101', 'MATA SERKEL', 'WIPRO', 'PCS', 52000.00, 0.000, 1, 'MU0577'
UNION ALL SELECT 'MU0578', 'MATA GERGAJI BESI LENOX 12\'', 'MATA SERKEL', '-', 'PCS', 15200.00, 0.000, 1, 'MU0578'
UNION ALL SELECT 'MU0579', 'MT JICSAW NO 3 WIPRO', 'MATA SERKEL', 'WIPRO', 'PCS', 9600.00, 0.000, 1, 'MU0579'
UNION ALL SELECT 'MU0580', 'MATA GURINDA BOSCH 110MM DIAMOND DISC', 'MATA SERKEL', '-', 'PCS', 45000.00, 12.000, 1, 'MU0580'
UNION ALL SELECT 'MU0581', 'ALAT AMPLAS BULAT VELCRO', 'MATA SERKEL', '-', 'PCS', 11000.00, 0.000, 1, 'MU0581'
UNION ALL SELECT 'MU0582', 'DINABOLT 12X60', 'BAUT', '-', 'PCS', 2500.00, 0.000, 1, 'MU0582'
UNION ALL SELECT 'MU0583', 'SELANG BUANG KARET BIRU 3 X 100', 'SLANG', 'NOQIWA', '100 METER', 760000.00, 5.000, 1, 'MU0583'
UNION ALL SELECT 'MU0584', 'DONGKRAK BOTOL WIPRO 15 TON', 'DONGKRAK', 'WIPRO', 'UNIT', 600000.00, 0.000, 1, 'MU0584'
UNION ALL SELECT 'MU0585', 'SPED SOLAR WIPRO / SEMPROT OLI', 'MATA SERKEL', 'WIPRO', 'UNIT', 192000.00, 0.000, 1, 'MU0585'
UNION ALL SELECT 'MU0586', 'DONGKRAK BOTOL 3 TON', 'DONGKRAK', 'WIPRO', 'UNIT', 264000.00, 0.000, 1, 'MU0586'
UNION ALL SELECT 'MU0587', 'DONGKRAK BOTOL 5 TON', 'DONGKRAK', 'WIPRO', 'UNIT', 360000.00, 0.000, 1, 'MU0587'
UNION ALL SELECT 'MU0588', 'DONGKRAK BOTOL WIPRO 10 TON', 'DONGKRAK', 'WIPRO', 'UNIT', 480000.00, 0.000, 1, 'MU0588'
UNION ALL SELECT 'MU0589', 'KACAMATA SMOKE SAFETY BLACK', 'KACAMATA', 'WIPRO', 'PCS', 20000.00, 0.000, 1, 'MU0589'
UNION ALL SELECT 'MU0590', 'SARUNG TANGAN WIPRO', 'MATA SERKEL', 'WIPRO', 'PSG', 28000.00, 0.000, 1, 'MU0590'
UNION ALL SELECT 'MU0591', 'TANG LAS GERMANY WIPRO', 'MATA SERKEL', 'WIPRO', 'UNIT', 56000.00, 0.000, 1, 'MU0591'
UNION ALL SELECT 'MU0592', 'TANG MASA WIPRO', 'MATA SERKEL', 'WIPRO', 'UNIT', 44000.00, 0.000, 1, 'MU0592'
UNION ALL SELECT 'MU0593', 'PIPA PVC 1 C PUTIH', 'PIPA', 'POWER', 'BTG', 28000.00, 0.000, 1, 'MU0593'
UNION ALL SELECT 'MU0594', 'WATERPASS DP-88D 18\"', 'WATERPASS', 'WIPRO', 'PCS', 35750.00, 0.000, 1, 'MU0594'
UNION ALL SELECT 'MU0595', 'WATERPASS WIPRO DP2001 18\'', 'WATERPASS', 'WIPRO', 'PCS', 74250.00, 0.000, 1, 'MU0595'
UNION ALL SELECT 'MU0596', 'METERAN 3M WIPRO', 'ALAT UKUR', 'WIPRO', 'PCS', 32000.00, 6.000, 1, 'MU0596'
UNION ALL SELECT 'MU0597', 'SEMEN NAT GREY GIANT MORTAR 510 04', 'SEMEN', 'Avian', 'BKS', 10294.00, 0.000, 1, 'MU0597'
UNION ALL SELECT 'MU0598', 'METERAN 50M WIPRO', 'ALAT UKUR', 'WIPRO', 'PCS', 105000.00, 4.000, 1, 'MU0598'
UNION ALL SELECT 'MU0599', 'METERAN 100M WIPRO', 'MATA SERKEL', 'WIPRO', 'PCS', 170000.00, 4.000, 1, 'MU0599'
UNION ALL SELECT 'MU0600', 'GUNTING SENG JUNGKIT LURUS 10\' WIPRO', 'GUNTING', 'WIPRO', 'PCS', 144000.00, 0.000, 1, 'MU0600'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU0601' AS sku, 'GERGAJI KAYU 16\' WIPRO' AS name, 'MATA SERKEL' AS category_name, 'WIPRO' AS brand_name, 'PCS' AS base_unit_code, 68000.00 AS cost_price, 0.000 AS min_stock, 1 AS is_active, 'MU0601' AS legacy_sku
UNION ALL SELECT 'MU0602', 'SILICON GUN WIPRO', 'MATA SERKEL', 'WIPRO', 'PCS', 28000.00, 0.000, 1, 'MU0602'
UNION ALL SELECT 'MU0603', 'KUNCI RING PAS WIPRO 10MM', 'V-BELT', 'WIPRO', 'PCS', 18000.00, 6.000, 1, 'MU0603'
UNION ALL SELECT 'MU0604', 'KUNCI RING PAS WIPRO 12MM', 'V-BELT', 'WIPRO', 'PCS', 22000.00, 6.000, 1, 'MU0604'
UNION ALL SELECT 'MU0605', 'KUNCI RING PAS WIPRO 14MM', 'V-BELT', 'WIPRO', 'PCS', 25000.00, 6.000, 1, 'MU0605'
UNION ALL SELECT 'MU0606', 'KUNCI RING PAS WIPRO 19MM', 'V-BELT', 'WIPRO', 'PCS', 38000.00, 2.000, 1, 'MU0606'
UNION ALL SELECT 'MU0607', 'KUNCI RING PAS WIPRO 22MM', 'V-BELT', 'WIPRO', 'PCS', 43200.00, 2.000, 1, 'MU0607'
UNION ALL SELECT 'MU0608', 'KUNCI RING PAS WIPRO 24MM', 'V-BELT', 'WIPRO', 'PCS', 62400.00, 2.000, 1, 'MU0608'
UNION ALL SELECT 'MU0609', 'KUNCI RING PAS SET 8-24MM WIPRO', 'ALAT', 'WIPRO', 'SET', 215000.00, 0.000, 1, 'MU0609'
UNION ALL SELECT 'MU0610', 'KUNCI PAS RING SET 8-32MM WIPRO', 'V-BELT', 'WIPRO', 'SET', 429000.00, 0.000, 1, 'MU0610'
UNION ALL SELECT 'MU0611', 'KUNCI INGGRIS 6\' WIPRO KBC-6', 'ALAT', 'WIPRO', 'PCS', 41250.00, 2.000, 1, 'MU0611'
UNION ALL SELECT 'MU0612', 'KUNCI INGGRIS 12\' WIPRO KBS-12', 'MATA SERKEL', 'WIPRO', 'PCS', 93500.00, 2.000, 1, 'MU0612'
UNION ALL SELECT 'MU0613', 'KUNCI PINTU 268M-NB/GP', 'KUNCI PINTU', 'SANSHAN', 'SET', 97000.00, 0.000, 1, 'MU0613'
UNION ALL SELECT 'MU0614', 'KUNCI L SET 9 PCS ALWIN', 'V-BELT', 'ALWIN', 'SET', 71500.00, 6.000, 1, 'MU0614'
UNION ALL SELECT 'MU0615', 'KUNCI T HITAM WIPRO 8MM', 'ALAT', 'WIPRO', 'PCS', 25000.00, 0.000, 1, 'MU0615'
UNION ALL SELECT 'MU0616', 'KUNCI T HITAM WIPRO 10MM', 'MATA SERKEL', 'WIPRO', 'PCS', 32000.00, 2.000, 1, 'MU0616'
UNION ALL SELECT 'MU0617', 'OBENG ARLOJI', 'OBENG', 'WIPRO', 'SET', 32000.00, 0.000, 1, 'MU0617'
UNION ALL SELECT 'MU0618', 'WIPRO GREASE GUN', 'MATA SERKEL', 'WIPRO', 'PCS', 184000.00, 0.000, 1, 'MU0618'
UNION ALL SELECT 'MU0619', 'TANG KOMBINASI 7\" TKC-175', 'TANG', 'WIPRO', 'PCS', 38500.00, 0.000, 1, 'MU0619'
UNION ALL SELECT 'MU0620', 'TANG KOMBINASI 8\" TKC-200', 'TANG', 'WIPRO', 'PCS', 41250.00, 0.000, 1, 'MU0620'
UNION ALL SELECT 'MU0621', 'TANG KOMBINASI 7\' WIPRO', 'TANG', 'WIPRO', 'PCS', 68000.00, 0.000, 1, 'MU0621'
UNION ALL SELECT 'MU0622', 'MATA BOR BESI WIPRO 3.0', 'MATA SERKEL', 'WIPRO', 'PCS', 8000.00, 10.000, 1, 'MU0622'
UNION ALL SELECT 'MU0623', 'MATA BOR BESI WIPRO  4.0', 'MATA SERKEL', 'WIPRO', 'PCS', 11000.00, 10.000, 1, 'MU0623'
UNION ALL SELECT 'MU0624', 'MATA BOR BESI WIPRO  5.0', 'MATA SERKEL', 'WIPRO', 'PCS', 13475.00, 10.000, 1, 'MU0624'
UNION ALL SELECT 'MU0625', 'WIPRO DW-1112S', 'MATA GURINDA', 'WIPRO', 'PCS', 56000.00, 0.000, 1, 'MU0625'
UNION ALL SELECT 'MU0626', 'KEPALA BOR M/DRAT 13MM', 'S/PART', 'WIPRO', 'PCS', 60000.00, 0.000, 1, 'MU0626'
UNION ALL SELECT 'MU0627', 'ROUND NOSE BIT 204-011', 'MATA SERKEL', 'WIPRO', 'PCS', 32000.00, 0.000, 1, 'MU0627'
UNION ALL SELECT 'MU0628', 'CORNER ROUND BIT 301-021', 'MATA SERKEL', 'WIPRO', 'PCS', 48000.00, 0.000, 1, 'MU0628'
UNION ALL SELECT 'MU0629', 'BEADING BIT 302-051', 'MATA SERKEL', '-', 'PCS', 56000.00, 0.000, 1, 'MU0629'
UNION ALL SELECT 'MU0630', 'BEADING BIT 302-451', 'MATA SERKEL', 'WIPRO', 'PCS', 80000.00, 0.000, 1, 'MU0630'
UNION ALL SELECT 'MU0631', 'BEADING BIT 302-452', 'MATA SERKEL', 'WIPRO', 'PCS', 110000.00, 0.000, 1, 'MU0631'
UNION ALL SELECT 'MU0632', 'KEPALA SEKOP HITAM', 'SEKOP', '-', 'PCS', 33000.00, 0.000, 1, 'MU0632'
UNION ALL SELECT 'MU0633', 'ROMAN OGEE BIT 308-031', 'MATA SERKEL', 'WIPRO', 'PCS', 145000.00, 0.000, 1, 'MU0633'
UNION ALL SELECT 'MU0634', 'OGE BIT 309-031', 'MATA SERKEL', 'WIPRO', 'PCS', 145000.00, 0.000, 1, 'MU0634'
UNION ALL SELECT 'MU0635', 'OGEE FILLET BIT 310-031', 'MATA SERKEL', 'WIPRO', 'PCS', 175000.00, 0.000, 1, 'MU0635'
UNION ALL SELECT 'MU0636', 'CLASSICAL ROMAN OGEE BITS 312-031', 'MATA SERKEL', 'WIPRO', 'PCS', 72000.00, 0.000, 1, 'MU0636'
UNION ALL SELECT 'MU0637', 'CLASSIKAL COVE BIT 313-031', 'MATA SERKEL', 'WIPRO', 'PCS', 90000.00, 0.000, 1, 'MU0637'
UNION ALL SELECT 'MU0638', 'WIPRO MT SERKEL 7\"X24T SILENT HOLE', 'MATA SERKEL', 'WIPRO', 'PCS', 68750.00, 0.000, 1, 'MU0638'
UNION ALL SELECT 'MU0639', 'WD-40 220ML SMART STRAW', 'SPRAYER', 'WD', 'KLG', 92000.00, 0.000, 1, 'MU0639'
UNION ALL SELECT 'MU0640', 'SIKAT BAJA BENGKOK WIPRO', 'SIKAT', 'WIPRO', 'PCS', 16000.00, 0.000, 1, 'MU0640'
UNION ALL SELECT 'MU0641', 'BAN DALAM ARTCO', 'BAN', 'ARTCO', 'PCS', 20500.00, 20.000, 1, 'MU0641'
UNION ALL SELECT 'MU0642', 'AMPLAS BULAT 4\" 80', 'AMPLAS', 'WIPRO', 'LBR', 3850.00, 0.000, 1, 'MU0642'
UNION ALL SELECT 'MU0643', 'AMPLAS DUCO 600', 'AMPLAS', 'FUJI STAR', 'LBR', 4800.00, 0.000, 1, 'MU0643'
UNION ALL SELECT 'MU0644', 'AMPLAS GRIT 600', 'AMPLAS', '-', 'LBR', 4800.00, 0.000, 1, 'MU0644'
UNION ALL SELECT 'MU0645', 'AMPLAS DUCO 80', 'AMPLAS', 'FUJI STAR', 'LBR', 4800.00, 0.000, 1, 'MU0645'
UNION ALL SELECT 'MU0646', 'BAK MANDI WALRUS BLUE,PINK,WHITE', 'BAK', '-', 'UNIT', 472000.00, 3.000, 1, 'MU0646'
UNION ALL SELECT 'MU0647', 'YONGMA SMC 3053', 'MAGICCOM', 'YONGMA', 'PCS', 644000.00, 0.000, 1, 'MU0647'
UNION ALL SELECT 'MU0648', 'AMPLAS ROLL AA280', 'AMPLAS', '-', 'MTR', 8000.00, 0.000, 1, 'MU0648'
UNION ALL SELECT 'MU0649', 'AMPLAS ROLL AA400', 'AMPLAS', '-', 'MTR', 8000.00, 0.000, 1, 'MU0649'
UNION ALL SELECT 'MU0650', 'AMPLAS ROLL AA80', 'AMPLAS', '-', 'MTR', 8000.00, 0.000, 1, 'MU0650'
UNION ALL SELECT 'MU0651', 'AVITEX 776 5KG', 'CAT', 'AVITEX', 'GLN', 162500.00, 0.000, 1, 'MU0651'
UNION ALL SELECT 'MU0652', 'KIRIN KRC 138', 'MAGICCOM', 'KIRIN', 'PCS', 317600.00, 0.000, 1, 'MU0652'
UNION ALL SELECT 'MU0653', 'GUNTING KECIL', 'GUNTING', 'GUNINDO', 'PCS', 5600.00, 12.000, 1, 'MU0653'
UNION ALL SELECT 'MU0654', 'MAGIC COM CRJ 3301', 'MAGICCOM', 'COSMOS', 'UNIT', 260000.00, 1.000, 1, 'MU0654'
UNION ALL SELECT 'MU0655', 'BODY PELOR ARCEL 8516', 'MATA SERKEL', '-', 'SET', 150000.00, 0.000, 1, 'MU0655'
UNION ALL SELECT 'MU0656', 'ATS OVERVAL OCP 2 1/2INCH', 'OVERVAL', 'ATS', 'PCS', 3200.00, 0.000, 1, 'MU0656'
UNION ALL SELECT 'MU0657', 'ATS OVERVAL PT OGP 3', 'OVERVAL', 'ATS', 'PCS', 4800.00, 0.000, 1, 'MU0657'
UNION ALL SELECT 'MU0658', 'CAT AVIAN 200CC 197', 'CAT', 'Avian', 'KLG', 18400.00, 8.000, 1, 'MU0658'
UNION ALL SELECT 'MU0659', 'TANG CATUT KAKA TUA 9\'\'', 'TANG', 'IGM', 'PCS', 10416.00, 6.000, 1, 'MU0659'
UNION ALL SELECT 'MU0660', 'CAT AVIAN 200CC 198', 'CAT', 'Avian', 'KLG', 18400.00, 8.000, 1, 'MU0660'
UNION ALL SELECT 'MU0661', 'PIRING 9 GARIS', 'PIRING', 'MERCURY', 'PCS', 3200.00, 0.000, 1, 'MU0661'
UNION ALL SELECT 'MU0662', 'GERGAJI MULLER 18\'', 'GERGAJI', 'MULLER', 'PCS', 60500.00, 6.000, 1, 'MU0662'
UNION ALL SELECT 'MU0663', 'CAT AVIAN 200CC 645', 'CAT', 'Avian', 'KLG', 18400.00, 8.000, 1, 'MU0663'
UNION ALL SELECT 'MU0664', 'CAT AVIAN 200CC 652', 'CAT', 'Avian', 'KLG', 18400.00, 8.000, 1, 'MU0664'
UNION ALL SELECT 'MU0665', 'CAT AVIAN 200CC 750', 'CAT', 'Avian', 'KLG', 18400.00, 8.000, 1, 'MU0665'
UNION ALL SELECT 'MU0666', 'CAT AVIAN 200CC 911', 'CAT', 'Avian', 'KLG', 18400.00, 8.000, 1, 'MU0666'
UNION ALL SELECT 'MU0667', 'OTOMATIS TANDON ST70', 'ALAT', 'RADAR', 'PCS', 70000.00, 7.000, 1, 'MU0667'
UNION ALL SELECT 'MU0668', 'REL LACI 35CM', 'ALAT', 'HUBEN', 'PSG', 14500.00, 12.000, 1, 'MU0668'
UNION ALL SELECT 'MU0669', 'BOX MCB 1', 'BOX MCB', 'DEXTA', 'PCS', 4000.00, 20.000, 1, 'MU0669'
UNION ALL SELECT 'MU0670', 'BOX MCB 2 FASE', 'BOX MCB', 'DEXTA', 'PCS', 6400.00, 0.000, 1, 'MU0670'
UNION ALL SELECT 'MU0671', 'BOX MCB 4', 'BOX MCB', '-', 'PCS', 40000.00, 0.000, 1, 'MU0671'
UNION ALL SELECT 'MU0672', 'BOX MCB 8', 'BOX MCB', '-', 'PCS', 48000.00, 0.000, 1, 'MU0672'
UNION ALL SELECT 'MU0673', 'STOP KONTAK BROKO TANAM', 'SAKLAR', 'BROCO', 'PCS', 13600.00, 0.000, 1, 'MU0673'
UNION ALL SELECT 'MU0674', 'SAKLAR TANAM DOBEL BROCO', 'SAKLAR', 'BROCO', 'PCS', 16125.00, 0.000, 1, 'MU0674'
UNION ALL SELECT 'MU0675', 'SAKLAR TEMPEL DOBEL BROCO', 'SAKLAR', 'BROCO', 'PCS', 17500.00, 0.000, 1, 'MU0675'
UNION ALL SELECT 'MU0676', 'TERMINAL 4 LB MT-554', 'STEKER', 'MATSUI', 'PCS', 18537.00, 12.000, 1, 'MU0676'
UNION ALL SELECT 'MU0682', 'HAND SOCKET 10MM X 1/2\' WIPRO', 'Kunci', 'WIPRO', 'PCS', 11000.00, 0.000, 1, 'MU0682'
UNION ALL SELECT 'MU0683', 'HAND SOCKET 15MM X 1/2\' WIPRO', 'Kunci', 'WIPRO', 'PCS', 12100.00, 0.000, 1, 'MU0683'
UNION ALL SELECT 'MU0684', 'BOYO POLITUR 601 0.5L', 'VERNIS', 'BOYO', 'KLG', 35000.00, 0.000, 1, 'MU0684'
UNION ALL SELECT 'MU0685', 'BOYO VERNIS TRASPARAN 1 LT', 'VERNIS', 'BOYO', 'KLG', 86000.00, 6.000, 1, 'MU0685'
UNION ALL SELECT 'MU0686', 'OBENG GG KARET GNT 4+', 'OBENG', '-', 'PCS', 18000.00, 6.000, 1, 'MU0686'
UNION ALL SELECT 'MU0687', 'ISI CUTTER KENKO A-100', 'PISAU', 'KENKO', 'PAK', 8000.00, 0.000, 1, 'MU0687'
UNION ALL SELECT 'MU0688', 'ISI CUTTER KENKO L-150', 'PISAU', 'KENKO', 'PAK', 12000.00, 0.000, 1, 'MU0688'
UNION ALL SELECT 'MU0689', 'DONGKRAK LIPRO 2 TON', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU0689'
UNION ALL SELECT 'MU0690', 'DONGKRARK LIPRO 3 TON', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU0690'
UNION ALL SELECT 'MU0691', 'DERFOS NO120', 'MATA SERKEL', '-', 'PCS', 12800.00, 0.000, 1, 'MU0691'
UNION ALL SELECT 'MU0692', 'TEMPAT SAMPAH CALISTA', 'TEMPAT', 'CALISTA', 'PCS', 23200.00, 0.000, 1, 'MU0692'
UNION ALL SELECT 'MU0693', 'DIAMOND WHEL WET 4', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU0693'
UNION ALL SELECT 'MU0694', 'ENGSEL SN 3\'', 'ENGSEL', 'IGM', 'SET', 12800.00, 6.000, 1, 'MU0694'
UNION ALL SELECT 'MU0695', 'ENGSEL SN 5\'', 'ENGSEL', 'NISHIO', 'SET', 15000.00, 24.000, 1, 'MU0695'
UNION ALL SELECT 'MU0696', 'OVERVAL KOTAK 3\" IGM', 'OVERVAL', 'IGM', 'PCS', 2125.00, 12.000, 1, 'MU0696'
UNION ALL SELECT 'MU0697', 'IRUS NONA 40CM', 'IRUS', 'NONA', 'PCS', 40000.00, 0.000, 1, 'MU0697'
UNION ALL SELECT 'MU0698', 'ENGSEL VEER 4\" IE-006', 'ENGSEL', 'IGM', 'PSG', 49500.00, 0.000, 1, 'MU0698'
UNION ALL SELECT 'MU0699', 'KUAS SANSHAN 4\'', 'Kuas', 'SANSHAN', 'PCS', 12000.00, 0.000, 1, 'MU0699'
UNION ALL SELECT 'MU0700', 'PANCI SET GNS 6', 'PANCI', '555', 'SET', 352800.00, 0.000, 1, 'MU0700'
UNION ALL SELECT 'MU0701', 'TEMPAT SAMPAH CALISTA 5', 'TEMPAT', 'CALISTA', 'PCS', 20000.00, 0.000, 1, 'MU0701'
UNION ALL SELECT 'MU0702', 'RED GASKET  80 g', 'PEL LANTAI', 'DESTRONK', 'PCS', 13750.00, 12.000, 1, 'MU0702'
UNION ALL SELECT 'MU0703', 'MATA GURINDA POTONG', 'MATA GURINDA', 'TAIYO', 'PCS', 1981.00, 200.000, 1, 'MU0703'
UNION ALL SELECT 'MU0704', 'GRENDEL HTM KURA 10\'', 'GLASSBLOCK', 'KURA', 'PCS', 12000.00, 0.000, 1, 'MU0704'
UNION ALL SELECT 'MU0705', 'GRENDEL KURA  5\'', 'GRENDEL', 'KURA', 'PCS', 6400.00, 12.000, 1, 'MU0705'
UNION ALL SELECT 'MU0706', 'GRENDEL KURA 8\'', 'GRENDEL', 'KURA', 'PCS', 10400.00, 0.000, 1, 'MU0706'
UNION ALL SELECT 'MU0707', 'SEMEN MORTAR', 'SEMEN', 'MORTAR', 'SAK', 121000.00, 0.000, 1, 'MU0707'
UNION ALL SELECT 'MU0708', 'LAKBAN KERTAS DIKO KECIL 17m', 'LAKBAN', 'PAJERO', 'PCS', 5740.00, 0.000, 1, 'MU0708'
UNION ALL SELECT 'MU0709', 'CETOK GG FIBER 6\'', 'CETOK', 'IGM', 'PCS', 14166.00, 0.000, 1, 'MU0709'
UNION ALL SELECT 'MU0710', 'KUNCI PINTU 222M-NB/CP', 'KUNCI PINTU', 'SANSHAN', 'SET', 97000.00, 0.000, 1, 'MU0710'
UNION ALL SELECT 'MU0711', 'OVER SOCK 3/4X1/2 JAYA', 'SAMBUNGAN', 'JAYA', 'PCS', 1024.00, 0.000, 1, 'MU0711'
UNION ALL SELECT 'MU0712', 'GEMBOK ORYX 50MM PENDEK', 'V-BELT', 'ORYX', 'PCS', 16800.00, 0.000, 1, 'MU0712'
UNION ALL SELECT 'MU0713', 'HANDSPRYER SOLO', 'MESIN', 'SOLO', 'UNIT', 328000.00, 5.000, 1, 'MU0713'
UNION ALL SELECT 'MU0714', 'BLENDER PHILIPS HR 2116', 'BLENDER', 'PHILIP', 'SET', 685000.00, 1.000, 1, 'MU0714'
UNION ALL SELECT 'MU0715', 'HAND TAPS M12X1.25', 'ALAT', 'WIPRO', 'PAK', 49500.00, 0.000, 1, 'MU0715'
UNION ALL SELECT 'MU0716', 'HAND TAPS M10X1.25', 'ALAT', 'WIPRO', 'PAK', 36300.00, 0.000, 1, 'MU0716'
UNION ALL SELECT 'MU0717', 'HAND TAPS M8X1.25', 'ALAT', 'WIPRO', 'PAK', 27000.00, 0.000, 1, 'MU0717'
UNION ALL SELECT 'MU0718', 'HAND TAPS M6X1', 'ALAT', 'WIPRO', 'PAK', 24000.00, 0.000, 1, 'MU0718'
UNION ALL SELECT 'MU0719', 'HAND TAPS M5X0.8', 'MATA SERKEL', 'SKF.SKC', 'PAK', 7200.00, 0.000, 1, 'MU0719'
UNION ALL SELECT 'MU0720', 'HAND TAPS M4X0.7', 'MATA SERKEL', 'SKF.SKC', 'PAK', 6400.00, 0.000, 1, 'MU0720'
UNION ALL SELECT 'MU0721', 'BOR J1Z-SD05-13A', 'MATA SERKEL', '-', 'SET', 545000.00, 0.000, 1, 'MU0721'
UNION ALL SELECT 'MU0722', 'IRON ENGSEL T 1O', 'ENGSEL', 'KURA', 'PCS', 9000.00, 12.000, 1, 'MU0722'
UNION ALL SELECT 'MU0723', 'IRON ENGSEL T 6\'', 'ENGSEL', 'KURA', 'PCS', 8750.00, 12.000, 1, 'MU0723'
UNION ALL SELECT 'MU0724', 'ENGSEL LACI 2\"', 'MATA SERKEL', '-', 'PSG', 3400.00, 24.000, 1, 'MU0724'
UNION ALL SELECT 'MU0725', 'ENGSEL LACI 3\"', 'MATA SERKEL', '-', 'PSG', 4200.00, 12.000, 1, 'MU0725'
UNION ALL SELECT 'MU0726', 'JET SHOWER', 'SHOWER', 'HOMESTAR', 'PCS', 42900.00, 0.000, 1, 'MU0726'
UNION ALL SELECT 'MU0727', 'DISPENSER MIYAKO WD-189H', 'DISPENSER', 'MIYAKO', 'UNIT', 175000.00, 1.000, 1, 'MU0727'
UNION ALL SELECT 'MU0728', 'IMBUDAR PANASONIK', 'TRIPLEK', 'PANASONIC', 'PCS', 5000.00, 0.000, 1, 'MU0728'
UNION ALL SELECT 'MU0729', 'IMPEK MOTOR WIPRO AIW1218A', 'MATA SERKEL', 'WIPRO', 'UNIT', 1760000.00, 0.000, 1, 'MU0729'
UNION ALL SELECT 'MU0730', 'IRON GRENDEL HT 10INCH', 'GRENDEL', 'IRON', 'PCS', 8000.00, 0.000, 1, 'MU0730'
UNION ALL SELECT 'MU0731', 'IRON GRENDEL HITAM TIPIS 10', 'MATA SERKEL', '-', 'PCS', 3000.00, 0.000, 1, 'MU0731'
UNION ALL SELECT 'MU0732', 'ISOLATOR PVC', 'MATA SERKEL', '-', '3PCS', 1600.00, 0.000, 1, 'MU0732'
UNION ALL SELECT 'MU0733', 'GRENDEL 4\'\'', 'GRENDEL', 'CAB', 'PCS', 8000.00, 0.000, 1, 'MU0733'
UNION ALL SELECT 'MU0734', 'MAKTEC JIK SAW MT430', 'MESIN', 'MAKTEC', 'UNIT', 1420000.00, 0.000, 1, 'MU0734'
UNION ALL SELECT 'MU0735', 'KRAN BESI3/4 PDK', 'KRAN', 'HPP', 'PCS', 9000.00, 12.000, 1, 'MU0735'
UNION ALL SELECT 'MU0736', 'DISPENSER MIYAKO WD-185H', 'DISPENSER', 'MIYOKO', 'UNIT', 130000.00, 1.000, 1, 'MU0736'
UNION ALL SELECT 'MU0737', 'KABEL AUDIO RCA 2 : 1', 'KABEL', '-', 'PSG', 5000.00, 6.000, 1, 'MU0737'
UNION ALL SELECT 'MU0738', 'IRUS KAYU 10', 'IRUS', '-', 'PCS', 4500.00, 0.000, 1, 'MU0738'
UNION ALL SELECT 'MU0739', 'IMTAN KEONG 3\"', 'ALAT', 'IMTAN', 'UNIT', 1800000.00, 0.000, 1, 'MU0739'
UNION ALL SELECT 'MU0740', 'KABEL ROLL FORTUNA 10M', 'KABEL', 'FOX', 'PCS', 53000.00, 0.000, 1, 'MU0740'
UNION ALL SELECT 'MU0741', 'BATA RINGAN 10CM', 'BATA', 'CITICON', 'PCS', 18000.00, 8.000, 1, 'MU0741'
UNION ALL SELECT 'MU0742', 'KIKIR SANSAW 4.8 MM WIPRO', 'MATA SERKEL', 'WIPRO', 'PCS', 16000.00, 0.000, 1, 'MU0742'
UNION ALL SELECT 'MU0743', 'KENMASTER KLEM SELANG 4', 'MATA SERKEL', '-', 'SET', 10000.00, 0.000, 1, 'MU0743'
UNION ALL SELECT 'MU0744', 'KAWAT LAS RB 2,6MM', 'KAWAT LAS', 'RB 2.6', 'KG', 29000.00, 0.000, 1, 'MU0744'
UNION ALL SELECT 'MU0745', 'SEMPROTAN SERBA GUNA', 'SEMPROTAN', 'SANXUE', 'PCS', 8000.00, 0.000, 1, 'MU0745'
UNION ALL SELECT 'MU0746', 'KUNCI ATS 11MM', 'V-BELT', 'ATS', 'PCS', 16000.00, 0.000, 1, 'MU0746'
UNION ALL SELECT 'MU0747', 'KRAN GT SUPER 3/4', 'KRAN', 'GT SUPER', 'PCS', 32500.00, 6.000, 1, 'MU0747'
UNION ALL SELECT 'MU0748', 'KRAN GT SUPER 1/2', 'KRAN', 'GT SUPER', 'PCS', 30000.00, 7.000, 1, 'MU0748'
UNION ALL SELECT 'MU0749', 'AMPLAS ROLL AA180', 'AMPLAS', '-', 'MTR', 8000.00, 0.000, 1, 'MU0749'
UNION ALL SELECT 'MU0750', 'SEMPROTAN 7 MODEL POPEYE', 'SEMPROTAN', 'POPEYE', 'PCS', 20000.00, 6.000, 1, 'MU0750'
UNION ALL SELECT 'MU0751', 'GIPSUN', 'PLYWOOD', '-', 'LBR', 64000.00, 0.000, 1, 'MU0751'
UNION ALL SELECT 'MU0752', 'BAUT 5/8 25 CM', 'BAUT', '-', 'PCS', 10000.00, 0.000, 1, 'MU0752'
UNION ALL SELECT 'MU0753', 'KLEP SUMUR ROYAL 1INCH', 'KLEP', 'ROYAL', 'PCS', 11000.00, 12.000, 1, 'MU0753'
UNION ALL SELECT 'MU0754', 'KLEP SUMUR ROYAL 3/4', 'KLEP', 'ROYAL', 'PCS', 10500.00, 12.000, 1, 'MU0754'
UNION ALL SELECT 'MU0755', 'KONTAK TANAM BROCO', 'STEKER', 'BROCO', 'PCS', 13600.00, 0.000, 1, 'MU0755'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU0756' AS sku, 'TERMINAL COK 2 LUBANG BROCO' AS name, 'TERMINAL COK' AS category_name, 'BROCO' AS brand_name, 'PCS' AS base_unit_code, 30752.00 AS cost_price, 16.000 AS min_stock, 1 AS is_active, 'MU0756' AS legacy_sku
UNION ALL SELECT 'MU0757', 'TERMINAL COK 3 LUBANG BROCO', 'TERMINAL COK', 'BROCO', 'PCS', 33968.00, 16.000, 1, 'MU0757'
UNION ALL SELECT 'MU0758', 'TERMINAL COK 4 LUBANG BROCO', 'TERMINAL COK', 'BROCO', 'PCS', 39207.00, 16.000, 1, 'MU0758'
UNION ALL SELECT 'MU0759', 'TERMINAL COK 5 LUBANG BROCO', 'TERMINAL COK', 'BROCO', 'PCS', 45088.00, 16.000, 1, 'MU0759'
UNION ALL SELECT 'MU0760', 'KAWAT LOKET 1\"', 'MATA SERKEL', '-', 'MTR', 12000.00, 0.000, 1, 'MU0760'
UNION ALL SELECT 'MU0761', 'KENMASTER WHEL KRING', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU0761'
UNION ALL SELECT 'MU0762', 'OVER SOCK 1X3/4 JAYA', 'SAMBUNGAN', 'JAYA', 'PCS', 1368.00, 0.000, 1, 'MU0762'
UNION ALL SELECT 'MU0763', 'PHILIPS ESSENTIAL 14W', 'LAMPU', 'PHILIP', 'PCS', 31200.00, 0.000, 1, 'MU0763'
UNION ALL SELECT 'MU0764', 'PHILIPS LED 19W', 'TEER', 'HANDSOME', 'PCS', 81360.00, 5.000, 1, 'MU0764'
UNION ALL SELECT 'MU0765', 'LEM VIPLAS 40GR', 'LEM', 'Avian', 'PCS', 9000.00, 0.000, 1, 'MU0765'
UNION ALL SELECT 'MU0766', 'LEM FOX 1KG', 'LEM', 'FOX', 'KLG', 32000.00, 0.000, 1, 'MU0766'
UNION ALL SELECT 'MU0767', 'LEM FOX PLASTIK', 'LEM', 'FOX', 'BKS', 20000.00, 0.000, 1, 'MU0767'
UNION ALL SELECT 'MU0768', 'LIPRO POWER BLADE', 'MATA SERKEL', 'LIPPRO', 'PCS', 50000.00, 0.000, 1, 'MU0768'
UNION ALL SELECT 'MU0769', 'LIPRO KUNCI Y 10X12X14', 'V-BELT', 'LIPPRO', 'PCS', 16000.00, 0.000, 1, 'MU0769'
UNION ALL SELECT 'MU0770', 'KEPALA SANYO PLENDES', 'S/PART', '-', 'PSG', 5000.00, 12.000, 1, 'MU0770'
UNION ALL SELECT 'MU0771', 'TRAVO LAS 160E WIPRO', 'MESIN', 'WIPRO', 'SET', 1720000.00, 0.000, 1, 'MU0771'
UNION ALL SELECT 'MU0772', 'TRAVO LAS 120E WIPRO', 'MESIN', 'WIPRO', 'SET', 1040000.00, 0.000, 1, 'MU0772'
UNION ALL SELECT 'MU0773', 'LAKONI TRAVO LAS 205E', 'MESIN', 'LAKONI', 'SET', 2028000.00, 0.000, 1, 'MU0773'
UNION ALL SELECT 'MU0774', 'LIPRO TANG SNAPRING 7INCH', 'TANG', 'LIPPRO', 'PCS', 48000.00, 0.000, 1, 'MU0774'
UNION ALL SELECT 'MU0775', 'AMPLAS DUCO 500', 'AMPLAS', 'FUJI STAR', 'LBR', 4800.00, 0.000, 1, 'MU0775'
UNION ALL SELECT 'MU0776', 'ELBOW 4 JAYA', 'SAMBUNGAN', 'JAYA', 'PCS', 14824.00, 0.000, 1, 'MU0776'
UNION ALL SELECT 'MU0777', 'VPR', 'MATA SERKEL', '-', 'PCS', 20000.00, 0.000, 1, 'MU0777'
UNION ALL SELECT 'MU0778', 'LAKBAN DIKO MINI', 'LAKBAN', '-', 'PCS', 2400.00, 0.000, 1, 'MU0778'
UNION ALL SELECT 'MU0779', 'WATERPAS  MAXI', 'WATERPASS', '2 MACAN', 'PCS', 18000.00, 0.000, 1, 'MU0779'
UNION ALL SELECT 'MU0780', 'KUNCI LACI 101 CAMEL', 'Kunci', 'FREDER', 'PCS', 3750.00, 6.000, 1, 'MU0780'
UNION ALL SELECT 'MU0781', 'SIKAT BAJA EAGLE BRAND', 'SIKAT', 'EAGLE', 'PCS', 8000.00, 0.000, 1, 'MU0781'
UNION ALL SELECT 'MU0782', 'SPREY HOST', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU0782'
UNION ALL SELECT 'MU0783', 'TAMPAH TANGGUNG', 'TAPISAN', 'ATLANTIS', 'PCS', 12000.00, 0.000, 1, 'MU0783'
UNION ALL SELECT 'MU0784', 'AVIAN SYNTHETIC 189', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU0784'
UNION ALL SELECT 'MU0785', 'AVIAN SYNTHETIC 191', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU0785'
UNION ALL SELECT 'MU0786', 'ENGSEL BUBUT 3/4', 'ENGSEL', '-', 'PSG', 7000.00, 0.000, 1, 'MU0786'
UNION ALL SELECT 'MU0787', 'AVIAN SYNTHETIC 753', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU0787'
UNION ALL SELECT 'MU0788', 'SARUNG TANGAN KARET 39 CM', 'SARUNG TANGAN', 'IGM', 'PCS', 12000.00, 0.000, 1, 'MU0788'
UNION ALL SELECT 'MU0789', 'AVIAN HAMMERTON 022', 'CAT', 'Avian', 'KLG', 61600.00, 0.000, 1, 'MU0789'
UNION ALL SELECT 'MU0790', 'AVIAN HAMMERTON 101', 'CAT', 'Avian', 'KLG', 61600.00, 0.000, 1, 'MU0790'
UNION ALL SELECT 'MU0791', 'AVIAN HAMMERTON 003', 'CAT', 'Avian', 'KLG', 61600.00, 0.000, 1, 'MU0791'
UNION ALL SELECT 'MU0792', 'WOOD ECO 104', 'VERNIS', 'WOOD', 'KLG', 88000.00, 0.000, 1, 'MU0792'
UNION ALL SELECT 'MU0793', 'BAUT 5/8 30 CM', 'BAUT', '-', 'PCS', 12000.00, 0.000, 1, 'MU0793'
UNION ALL SELECT 'MU0794', 'KRAN BESI 1/2 PDK', 'KRAN', 'HPP', 'PCS', 15000.00, 6.000, 1, 'MU0794'
UNION ALL SELECT 'MU0795', 'LEM FOX 70G', 'LEM', 'FOX', 'KLG', 13000.00, 0.000, 1, 'MU0795'
UNION ALL SELECT 'MU0796', 'BOYO POLITUR 601 1L', 'VERNIS', 'BOYO', 'KLG', 86000.00, 0.000, 1, 'MU0796'
UNION ALL SELECT 'MU0797', 'BOYO POLITUR 607 1L', 'VERNIS', 'BOYO', 'KLG', 86000.00, 0.000, 1, 'MU0797'
UNION ALL SELECT 'MU0798', 'BOYO POLITUR 600 0,5L', 'VERNIS', 'BOYO', 'KLG', 35000.00, 0.000, 1, 'MU0798'
UNION ALL SELECT 'MU0799', 'SARUNG TANGAN KARET LATEX', 'SARUNG TANGAN', 'KEN MASTER', 'PCS', 16000.00, 0.000, 1, 'MU0799'
UNION ALL SELECT 'MU0800', 'ARIES GOLD 602 20KG', 'CAT', 'ARIES GOLD', 'PAIL', 357700.00, 0.000, 1, 'MU0800'
UNION ALL SELECT 'MU0801', 'ARIES GOLD 305 20KG', 'CAT', 'ARIES GOLD', 'PAIL', 357700.00, 0.000, 1, 'MU0801'
UNION ALL SELECT 'MU0802', 'AMPLAS GRIT 800', 'AMPLAS', '-', 'LBR', 4800.00, 0.000, 1, 'MU0802'
UNION ALL SELECT 'MU0803', 'AVITEX 040 25KG', 'CAT', 'AVITEX', 'PAIL', 440000.00, 0.000, 1, 'MU0803'
UNION ALL SELECT 'MU0804', 'AVITEX 602 25KG', 'CAT', 'AVITEX', 'PAIL', 440000.00, 2.000, 1, 'MU0804'
UNION ALL SELECT 'MU0805', 'GRENDEL IGM SS 2\'', 'GRENDEL', 'IGM', 'PCS', 24000.00, 6.000, 1, 'MU0805'
UNION ALL SELECT 'MU0806', 'ENGSEL BUBUT 5/8', 'ENGSEL', '-', 'PSG', 6000.00, 0.000, 1, 'MU0806'
UNION ALL SELECT 'MU0807', 'KIKIR SETENGAH BULAT 4\'\'', 'KIKIR', 'JCK', 'PCS', 52000.00, 0.000, 1, 'MU0807'
UNION ALL SELECT 'MU0808', 'ARIES GOLD SW 20KG', 'CAT', 'ARIES GOLD', 'PAIL', 357700.00, 0.000, 1, 'MU0808'
UNION ALL SELECT 'MU0809', 'KUNCI GEMBOK SAFILO 40', 'V-BELT', '-', 'PCS', 0.00, 0.000, 1, 'MU0809'
UNION ALL SELECT 'MU0810', 'KUNCI GEMBOK SAFILO 50', 'V-BELT', 'SAFILO', 'PCS', 30400.00, 0.000, 1, 'MU0810'
UNION ALL SELECT 'MU0811', 'OVERSOCK F1/2-M3/8', 'Kunci', 'WIPRO', 'PCS', 31900.00, 0.000, 1, 'MU0811'
UNION ALL SELECT 'MU0812', 'OVERVAL KEYOLA STAINLESS 4', 'OVERVAL', 'KEYOLA', 'PCS', 42400.00, 0.000, 1, 'MU0812'
UNION ALL SELECT 'MU0813', 'RENESOLA LED 15W', 'LAMPU', '-', 'PCS', 37000.00, 0.000, 1, 'MU0813'
UNION ALL SELECT 'MU0814', 'BAUT 5/8 20 CM', 'BAUT', '-', 'PCS', 7000.00, 0.000, 1, 'MU0814'
UNION ALL SELECT 'MU0815', 'DOOR LOCK AMANI K5007', 'V-BELT', '-', 'PCS', 0.00, 0.000, 1, 'MU0815'
UNION ALL SELECT 'MU0816', 'DOOR LOCK AMANI K5002', 'V-BELT', '-', 'PCS', 0.00, 0.000, 1, 'MU0816'
UNION ALL SELECT 'MU0817', 'DOOR LOCK AMANI K5003', 'V-BELT', '-', 'PCS', 0.00, 0.000, 1, 'MU0817'
UNION ALL SELECT 'MU0818', 'MULLER 68211', 'V-BELT', 'MULLER', 'SET', 224000.00, 0.000, 1, 'MU0818'
UNION ALL SELECT 'MU0819', 'KUNCI PINTU VPR 3016 SNCP', 'KUNCI PINTU', 'VPR', 'SET', 50000.00, 0.000, 1, 'MU0819'
UNION ALL SELECT 'MU0820', 'RING PLAT 1/2', 'BAUT', '-', 'PCS', 790.00, 0.000, 1, 'MU0820'
UNION ALL SELECT 'MU0821', 'MULLER SILVER/CP STAR', 'TARIKAN PINTU', 'MULLER', 'SET', 144000.00, 0.000, 1, 'MU0821'
UNION ALL SELECT 'MU0822', 'STAR JUMBO CK MUDA', 'TARIKAN PINTU', 'JAYA MULIA', 'SET', 96000.00, 0.000, 1, 'MU0822'
UNION ALL SELECT 'MU0823', 'SPAN PVC LANCIP', 'MATA SERKEL', '-', 'PCS', 14000.00, 0.000, 1, 'MU0823'
UNION ALL SELECT 'MU0824', 'GANTUNGAN BAJU BANO BLACK', 'GANTUNGAN', 'BANO', 'PCS', 48800.00, 0.000, 1, 'MU0824'
UNION ALL SELECT 'MU0825', 'SOBAR BARREL BOLT 6+12', 'V-BELT', '-', 'PCS', 0.00, 0.000, 1, 'MU0825'
UNION ALL SELECT 'MU0826', 'MURR 1/2', 'BAUT', '-', 'PCS', 1600.00, 0.000, 1, 'MU0826'
UNION ALL SELECT 'MU0827', 'ORIS CETOK SEMEN KORATEK 11INCH', 'SENDOK SEMEN', 'ORIS', 'PCS', 24000.00, 0.000, 1, 'MU0827'
UNION ALL SELECT 'MU0828', 'AVITEX 700 5KG', 'CAT', 'AVITEX', 'GLN', 135000.00, 0.000, 1, 'MU0828'
UNION ALL SELECT 'MU0829', 'AVITEX 725 5KG', 'CAT', 'AVITEX', 'GLN', 135000.00, 0.000, 1, 'MU0829'
UNION ALL SELECT 'MU0830', 'AVITEX 770 5KG', 'CAT', 'AVITEX', 'GLN', 135000.00, 0.000, 1, 'MU0830'
UNION ALL SELECT 'MU0831', 'AVITEX 675 5KG', 'CAT', 'AVITEX', 'GLN', 135000.00, 0.000, 1, 'MU0831'
UNION ALL SELECT 'MU0832', 'AVITEX 755 5KG', 'CAT', 'AVITEX', 'GLN', 135000.00, 0.000, 1, 'MU0832'
UNION ALL SELECT 'MU0833', 'AVITEX 745 5KG', 'CAT', 'AVITEX', 'GLN', 135000.00, 0.000, 1, 'MU0833'
UNION ALL SELECT 'MU0834', 'AVITEX 720 5KG', 'CAT', 'AVITEX', 'GLN', 135000.00, 0.000, 1, 'MU0834'
UNION ALL SELECT 'MU0835', 'AVITEX 771 5KG', 'CAT', 'AVITEX', 'GLN', 135000.00, 0.000, 1, 'MU0835'
UNION ALL SELECT 'MU0836', 'AVITEX 775 5KG', 'CAT', 'AVITEX', 'GLN', 135000.00, 0.000, 1, 'MU0836'
UNION ALL SELECT 'MU0837', 'METERAN LASER 50M MEAS', 'METERAN', '-', 'PCS', 350000.00, 0.000, 1, 'MU0837'
UNION ALL SELECT 'MU0838', 'AVITEX SW 5KG', 'CAT', 'AVITEX', 'GLN', 135000.00, 0.000, 1, 'MU0838'
UNION ALL SELECT 'MU0839', 'AVITEX 660 5KG', 'CAT', 'AVITEX', 'GLN', 135000.00, 0.000, 1, 'MU0839'
UNION ALL SELECT 'MU0840', 'AVITEX 765 5KG', 'CAT', 'AVITEX', 'GLN', 135000.00, 0.000, 1, 'MU0840'
UNION ALL SELECT 'MU0841', 'HAK ANGIN BULAT RING 8\" GOLD 502', 'HAK ANGIN', 'VALLEN', 'PCS', 6666.00, 0.000, 1, 'MU0841'
UNION ALL SELECT 'MU0842', 'BAUT 1/2 20 CM', 'BAUT', '-', 'PCS', 5600.00, 0.000, 1, 'MU0842'
UNION ALL SELECT 'MU0843', 'METERAN LASER 100M', 'METERAN', '-', 'UNIT', 405000.00, 0.000, 1, 'MU0843'
UNION ALL SELECT 'MU0844', 'MECHANICAL PERSONAL 207799', 'MATA SERKEL', '-', 'UNIT', 0.00, 0.000, 1, 'MU0844'
UNION ALL SELECT 'MU0845', 'MECHANICAL PERSONAL 256312', 'MATA SERKEL', '-', 'UNIT', 0.00, 0.000, 1, 'MU0845'
UNION ALL SELECT 'MU0846', 'PISAU S SUWO', 'PISAU', 'SUWO', 'PCS', 14400.00, 0.000, 1, 'MU0846'
UNION ALL SELECT 'MU0847', 'AVIAN SYNTHETIC 642', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU0847'
UNION ALL SELECT 'MU0848', 'AMPLAS GRIT 500', 'AMPLAS', '-', 'LBR', 4800.00, 20.000, 1, 'MU0848'
UNION ALL SELECT 'MU0849', 'MATA CUTTER L150', 'CUTTER', '-', 'PCS', 1875.00, 0.000, 1, 'MU0849'
UNION ALL SELECT 'MU0850', 'TERMINAL COK 1 LUBANG BROCO', 'TERMINAL COK', 'BROCO', 'PCS', 16437.00, 16.000, 1, 'MU0850'
UNION ALL SELECT 'MU0851', 'KABEL NYYHY 2X1,5 ETERNA HITAM', 'KABEL', 'ETERNA', 'MTR', 14672.00, 0.000, 1, 'MU0851'
UNION ALL SELECT 'MU0852', 'KABEL PERDANA 1,5 WARNA SERABUT', 'KABEL', 'PERDANA', '100 METER', 228000.00, 0.000, 1, 'MU0852'
UNION ALL SELECT 'MU0853', 'KABEL NYZ 2X2,3 MERAH HITAM', 'KABEL', '-', 'MTR', 0.00, 0.000, 1, 'MU0853'
UNION ALL SELECT 'MU0854', 'KABEL NYYHY 2X2,5 PERDANA PUTIH', 'KABEL', 'PERDANA', 'MTR', 21247.00, 0.000, 1, 'MU0854'
UNION ALL SELECT 'MU0855', 'KABEL NYM 3X1,5 ETERNA', 'KABEL', 'ETERNA', '50 METER', 1029000.00, 0.000, 1, 'MU0855'
UNION ALL SELECT 'MU0856', 'JASON AIR INFLATOR', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU0856'
UNION ALL SELECT 'MU0857', 'KRAN KOMPRESOR TORA 1/4\"', 'KRAN', '-', 'PCS', 38500.00, 0.000, 1, 'MU0857'
UNION ALL SELECT 'MU0858', 'NIPPE 2000 222 CLEAR MATT', 'CAT', 'NIPPON PAINT', 'KLG', 83914.00, 0.000, 1, 'MU0858'
UNION ALL SELECT 'MU0859', 'KOIL MESIN BABAT STIHL', 'MATA SERKEL', 'STIHLL', 'PCS', 280000.00, 6.000, 1, 'MU0859'
UNION ALL SELECT 'MU0860', 'SARINGAN PASANG 30 SUMBU KOMPOR HOCK', 'MATA SERKEL', 'HOCK', 'PCS', 60320.00, 2.000, 1, 'MU0860'
UNION ALL SELECT 'MU0861', 'KIPAS 5 DAUN YAMAOKE NS80', 'KIPAS', 'YAMAOKE', 'PCS', 200000.00, 6.000, 1, 'MU0861'
UNION ALL SELECT 'MU0862', 'MAKTEC MT-370', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU0862'
UNION ALL SELECT 'MU0863', 'MAKITA MT M.BOR MT0600B', 'MESIN', 'MAKITA MT', 'UNIT', 487500.00, 1.000, 1, 'MU0863'
UNION ALL SELECT 'MU0864', 'MAKTEC MT-431', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU0864'
UNION ALL SELECT 'MU0865', 'MAKITA SKAF N1900B', 'MESIN', 'MAKITA', 'PCS', 2192000.00, 0.000, 1, 'MU0865'
UNION ALL SELECT 'MU0866', 'MAKITA N5900 B', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU0866'
UNION ALL SELECT 'MU0867', 'BOSCH GWS 8-100C', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU0867'
UNION ALL SELECT 'MU0868', 'MAKTEC M.POTONG MT230', 'MESIN', 'MAKTEC', 'UNIT', 2800000.00, 0.000, 1, 'MU0868'
UNION ALL SELECT 'MU0869', 'MAKTEC M.POTONG MT243', 'MESIN', 'MAKTEC', 'UNIT', 1551200.00, 0.000, 1, 'MU0869'
UNION ALL SELECT 'MU0870', 'MAKTEC M.BOR MT870', 'MESIN', 'MAKTEC', 'UNIT', 1200000.00, 0.000, 1, 'MU0870'
UNION ALL SELECT 'MU0871', 'MESIN KETINTING NOQIWA DX390', 'MESIN', '-', 'UNIT', 0.00, 0.000, 1, 'MU0871'
UNION ALL SELECT 'MU0872', 'SIKAT NAGOYA GAGANG PANJANG', 'SIKAT', '-', 'PCS', 0.00, 0.000, 1, 'MU0872'
UNION ALL SELECT 'MU0873', 'SAPU KAIN SUMBU PANJANG', 'SAPU', '-', 'PCS', 0.00, 0.000, 1, 'MU0873'
UNION ALL SELECT 'MU0874', 'IMTAN KEONG 4\"', 'S/PART', 'IMTAN', 'UNIT', 2000000.00, 0.000, 1, 'MU0874'
UNION ALL SELECT 'MU0875', 'PROFILA 88 TANK 2200 L', 'KLEM TARIK', 'IMUNDEX', 'PCS', 3140000.00, 0.000, 1, 'MU0875'
UNION ALL SELECT 'MU0876', 'KLEM SELANG KAWAT 3 1/2\"', 'KLEM', 'JCK', 'PCS', 10450.00, 0.000, 1, 'MU0876'
UNION ALL SELECT 'MU0877', 'KRAN AMICO 3/4', 'KRAN', 'AMICO', 'PCS', 13750.00, 6.000, 1, 'MU0877'
UNION ALL SELECT 'MU0878', 'KLEM SELANG KAWAT 3\"', 'KLEM', 'JCK', 'PCS', 7425.00, 0.000, 1, 'MU0878'
UNION ALL SELECT 'MU0879', 'KLEM SELANG PLAT 1\"', 'MATA SERKEL', '-', 'PCS', 8000.00, 50.000, 1, 'MU0879'
UNION ALL SELECT 'MU0880', 'KLEM SELANG PLAT 5/8', 'KLEM', '-', 'PCS', 9600.00, 50.000, 1, 'MU0880'
UNION ALL SELECT 'MU0881', 'KLEM SELANG PLAT 3/4', 'KLEM', '-', 'PCS', 0.00, 50.000, 1, 'MU0881'
UNION ALL SELECT 'MU0882', 'KLEM SELANG KAWAT 3/4', 'KLEM', '-', 'PCS', 0.00, 0.000, 1, 'MU0882'
UNION ALL SELECT 'MU0883', 'KLEM SELANG PLAT 4\"', 'KLEM', 'TAIWAN', 'PCS', 6320.00, 50.000, 1, 'MU0883'
UNION ALL SELECT 'MU0884', 'KLEM SELANG KAWAT 2\"', 'KLEM', 'JCK', 'PCS', 5500.00, 0.000, 1, 'MU0884'
UNION ALL SELECT 'MU0885', 'KLEM SELANG KAWAT 1\" KUNINGAN', 'KLEM', '-', 'PCS', 0.00, 0.000, 1, 'MU0885'
UNION ALL SELECT 'MU0886', 'KLEM SELANG KAWAT 2 1/4\"', 'KLEM', 'JCK', 'PCS', 5775.00, 0.000, 1, 'MU0886'
UNION ALL SELECT 'MU0887', 'KLEM SELANG KAWAT 1/2', 'KLEM', 'JCK', 'PCS', 3025.00, 0.000, 1, 'MU0887'
UNION ALL SELECT 'MU0888', 'AMPLAS GRIT 1500', 'AMPLAS', '-', 'LBR', 4800.00, 0.000, 1, 'MU0888'
UNION ALL SELECT 'MU0889', 'KUAS ROLL GLX 9\"', 'Kuas', 'GLX', 'PCS', 16250.00, 0.000, 1, 'MU0889'
UNION ALL SELECT 'MU0890', 'FISCER S 5', 'FISHER', '-', 'PAK', 0.00, 0.000, 1, 'MU0890'
UNION ALL SELECT 'MU0891', 'STAR JUMBO MERAH', 'TARIKAN PINTU', 'JAYA MULIA', 'SET', 88000.00, 0.000, 1, 'MU0891'
UNION ALL SELECT 'MU0892', 'ELMES Z6', 'TARIKAN PINTU', 'ELMES', 'SET', 160000.00, 0.000, 1, 'MU0892'
UNION ALL SELECT 'MU0893', 'MULLER SILVER/CP WAVE', 'KUNCI PINTU', 'MULLER', 'PCS', 144000.00, 0.000, 1, 'MU0893'
UNION ALL SELECT 'MU0894', 'MATA BOR BETON 8MM', 'MATA BOR', 'ALWIN', 'PCS', 9900.00, 6.000, 1, 'MU0894'
UNION ALL SELECT 'MU0895', 'DINABOLT 10X97', 'BAUT', 'WILA', 'PCS', 2500.00, 0.000, 1, 'MU0895'
UNION ALL SELECT 'MU0896', 'PIPA INSTALASI ELBOW 20MM', 'SAMBUNGAN', 'NISO', 'PCS', 4000.00, 0.000, 1, 'MU0896'
UNION ALL SELECT 'MU0897', 'POMPA SEPEDA RUSH', 'POMPA', 'RUSA HITAM', 'UNIT', 53000.00, 4.000, 1, 'MU0897'
UNION ALL SELECT 'MU0898', 'SELANG POMPA SEPEDA', 'SELANG', '-', 'PCS', 5000.00, 7.000, 1, 'MU0898'
UNION ALL SELECT 'MU0899', 'PALU KIMORA 12OZ', 'PALU', 'IGM', 'PCS', 34400.00, 0.000, 1, 'MU0899'
UNION ALL SELECT 'MU0900', 'PALU CAP MATA 0,5OZ', 'Paku', 'EYE', 'PCS', 46400.00, 0.000, 1, 'MU0900'
UNION ALL SELECT 'MU0901', 'SKRUP BAJA RINGAN 12X20 KUNINGAN 600 PCS', 'SKRUP', 'WILSON', 'DUS', 147000.00, 0.000, 1, 'MU0901'
UNION ALL SELECT 'MU0902', 'PAKU BETON VRP 1-1 1/2', 'Paku', 'VRP', 'PAK', 16000.00, 0.000, 1, 'MU0902'
UNION ALL SELECT 'MU0903', 'PAKU BETON VRP 1-1 1/4', 'Paku', 'VRP', 'PAK', 16000.00, 0.000, 1, 'MU0903'
UNION ALL SELECT 'MU0904', 'STAR JUMBO MERAH', 'TARIKAN PINTU', 'JAYA MULIA', 'SET', 96000.00, 0.000, 1, 'MU0904'
UNION ALL SELECT 'MU0905', 'STAR KOTAK CROM', 'TARIKAN PINTU', 'JAYA MULIA', 'PCS', 96000.00, 0.000, 1, 'MU0905'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU0906' AS sku, 'STAR 262B' AS name, 'TARIKAN PINTU' AS category_name, 'JAYA MULIA' AS brand_name, 'SET' AS base_unit_code, 88000.00 AS cost_price, 0.000 AS min_stock, 1 AS is_active, 'MU0906' AS legacy_sku
UNION ALL SELECT 'MU0907', 'STAR 605B', 'TARIKAN PINTU', 'JAYA MULIA', 'SET', 88000.00, 0.000, 1, 'MU0907'
UNION ALL SELECT 'MU0908', '1 1/2\" SKRUP BAJA SKRUP GYPSUN', 'SKRUP', 'MAJESTY', 'PAK', 70312.00, 0.000, 1, 'MU0908'
UNION ALL SELECT 'MU0909', 'KRAN WASHTAFEL LONJONG', 'KRAN', 'BANO', 'PCS', 92000.00, 0.000, 1, 'MU0909'
UNION ALL SELECT 'MU0910', 'PAKU BETON 3\'\'', 'Paku', 'MATRIX', 'DUS', 16000.00, 12.000, 1, 'MU0910'
UNION ALL SELECT 'MU0911', 'PAKU BETON 2\'\'', 'Paku', 'MATRIX', 'DUS', 16000.00, 6.000, 1, 'MU0911'
UNION ALL SELECT 'MU0912', 'PAKU BETON 4\'\'', 'Paku', 'MATRIX', 'DUS', 16000.00, 6.000, 1, 'MU0912'
UNION ALL SELECT 'MU0913', 'SERBET ARWANA', 'KAIN LAP', 'ARWANA', 'LBR', 5600.00, 0.000, 1, 'MU0913'
UNION ALL SELECT 'MU0914', 'SERBET NOL TIGA', 'KAIN LAP', 'NOL TIGA', 'PCS', 4000.00, 0.000, 1, 'MU0914'
UNION ALL SELECT 'MU0915', 'PAKU SKRUB 1 1/2 \"X8', 'Paku', '-', 'DUS', 13600.00, 0.000, 1, 'MU0915'
UNION ALL SELECT 'MU0916', 'MALIOBORO BEIGE 25X50', 'TEHEL', 'DIAMOND', 'DUS', 100000.00, 8.000, 1, 'MU0916'
UNION ALL SELECT 'MU0917', 'PALU KARET 16OZ', 'PALU', 'DUMBO', 'PCS', 20000.00, 0.000, 1, 'MU0917'
UNION ALL SELECT 'MU0918', 'PALU KARET 12OZ', 'PALU', 'DUMBO', 'PCS', 17600.00, 0.000, 1, 'MU0918'
UNION ALL SELECT 'MU0919', 'PALU 4 LB', 'PALU', 'IGM', 'PCS', 76000.00, 0.000, 1, 'MU0919'
UNION ALL SELECT 'MU0920', 'PALU 3LB', 'PALU', 'KURA', 'PCS', 68000.00, 0.000, 1, 'MU0920'
UNION ALL SELECT 'MU0921', 'PAKU SKRUB 1\'\'X6', 'Paku', '-', 'DUS', 8000.00, 0.000, 1, 'MU0921'
UNION ALL SELECT 'MU0922', 'PAKU SKRUB 3/4  +', 'Paku', '-', 'DUS', 4700.00, 0.000, 1, 'MU0922'
UNION ALL SELECT 'MU0923', 'BAKUL KECIL', 'BAKUL', '-', 'PCS', 14400.00, 0.000, 1, 'MU0923'
UNION ALL SELECT 'MU0924', 'SARINGAN TEH IKIMURA', 'SARINGAN', 'IKIMURA', 'PCS', 2400.00, 0.000, 1, 'MU0924'
UNION ALL SELECT 'MU0925', 'SENDOK MAKAN ROYAL', 'SENDOK', 'ROYAL', 'GR', 19200.00, 0.000, 1, 'MU0925'
UNION ALL SELECT 'MU0926', 'IRUS STAINLESS STEEL', 'IRUS', 'DINEMATE', 'PCS', 12000.00, 0.000, 1, 'MU0926'
UNION ALL SELECT 'MU0927', 'TOPLES MULTIPLAS 10L', 'TOPLES', 'MULTIPLAST', 'PCS', 20000.00, 0.000, 1, 'MU0927'
UNION ALL SELECT 'MU0928', 'SHOWER SOBAR', 'SHOWER', 'SOBAR', 'PCS', 70400.00, 0.000, 1, 'MU0928'
UNION ALL SELECT 'MU0929', 'SHOWER GRAVINDO', 'SHOWER', 'GRAVINDO', 'PCS', 48800.00, 0.000, 1, 'MU0929'
UNION ALL SELECT 'MU0930', 'KERAN DAPUR MDR', 'KRAN', 'MDR', 'PCS', 36000.00, 0.000, 1, 'MU0930'
UNION ALL SELECT 'MU0931', 'HAK ANGIN CRISTAL 8\"', 'HAK ANGIN', 'JAYA MULIA', 'PCS', 4200.00, 6.000, 1, 'MU0931'
UNION ALL SELECT 'MU0932', 'KLEP ALKON 2\"', 'KLEP', 'MIYAMOTO', 'PCS', 13500.00, 0.000, 1, 'MU0932'
UNION ALL SELECT 'MU0933', 'LAMPU ECLAT SPIRAL 13 WATT', 'LAMPU', 'ECLAT', 'PCS', 32000.00, 0.000, 1, 'MU0933'
UNION ALL SELECT 'MU0934', 'TAHITI TOSCA 40X40', 'TEHEL', 'JUPITER', 'DUS', 67818.00, 8.000, 1, 'MU0934'
UNION ALL SELECT 'MU0935', 'CUTTER FIXING BOLT', 'S/PART', 'NOQIWA', 'PCS', 0.00, 6.000, 1, 'MU0935'
UNION ALL SELECT 'MU0936', 'PISTON RING SET BG 328 36MM', 'S/PART', 'MIYAMOTO', 'SET', 8000.00, 6.000, 1, 'MU0936'
UNION ALL SELECT 'MU0937', 'LAMPU ECLAT ESENSIAL 14 WATT', 'LAMPU', 'ECLAT', 'PCS', 29600.00, 0.000, 1, 'MU0937'
UNION ALL SELECT 'MU0938', 'LAMPU ECLAT ESENSIAL 15 WATT', 'LAMPU', 'ECLAT', 'PCS', 31200.00, 0.000, 1, 'MU0938'
UNION ALL SELECT 'MU0939', 'SAMBUNGAN ALKON 3\'X3\'', 'S/PART', 'MIYAMOTO', 'PCS', 48000.00, 6.000, 1, 'MU0939'
UNION ALL SELECT 'MU0940', 'LAMPU ECLAT ESENSIAL 18 WATT', 'LAMPU', 'ECLAT', 'PCS', 31200.00, 0.000, 1, 'MU0940'
UNION ALL SELECT 'MU0941', 'TEMPAT MINYAK TANAH 16 SUMBU', 'S/PART', 'HOCK', 'PCS', 35500.00, 3.000, 1, 'MU0941'
UNION ALL SELECT 'MU0942', 'KIKIR PLAT KASAR 12\"', 'KIKIR', 'WIPRO', 'PCS', 96000.00, 4.000, 1, 'MU0942'
UNION ALL SELECT 'MU0943', 'KIKIR PLAT HALUS 10\"', 'KIKIR', 'WIPRO', 'PCS', 83000.00, 4.000, 1, 'MU0943'
UNION ALL SELECT 'MU0944', 'PHILIPS TORNADOL 5W', 'LAMPU', 'PHILIP', 'PCS', 32800.00, 0.000, 1, 'MU0944'
UNION ALL SELECT 'MU0945', 'KEPALA BOR M/SOCK 16MM', 'S/PART', 'WIPRO', 'PCS', 79750.00, 2.000, 1, 'MU0945'
UNION ALL SELECT 'MU0946', 'PHILIPS TORNADO 8W', 'LAMPU', 'PHILIP', 'PCS', 33600.00, 0.000, 1, 'MU0946'
UNION ALL SELECT 'MU0947', 'KEPALA BOR M/DRAT 16MM', 'S/PART', 'WIPRO', 'PCS', 140000.00, 2.000, 1, 'MU0947'
UNION ALL SELECT 'MU0948', 'PHILIPS LED 4W', 'LAMPU', 'PHILIP', 'PCS', 26400.00, 5.000, 1, 'MU0948'
UNION ALL SELECT 'MU0949', 'PHILIPS LED 3W', 'TEER', 'HANDSOME', 'PCS', 21600.00, 5.000, 1, 'MU0949'
UNION ALL SELECT 'MU0950', 'SEMEN NAT CREAM GIANT MORTAR 510 01', 'SEMEN', 'Avian', 'BKS', 10294.00, 0.000, 1, 'MU0950'
UNION ALL SELECT 'MU0951', 'NANOLITE LED 30W', 'LAMPU', 'NANOLITE', 'PCS', 108000.00, 5.000, 1, 'MU0951'
UNION ALL SELECT 'MU0952', 'CLUTCH DRUM PULI M BABAT', 'S/PART', 'MIYAMOTO', 'PCS', 13500.00, 6.000, 1, 'MU0952'
UNION ALL SELECT 'MU0953', 'OPPLE LED 4.5W', 'TEER', 'OPPLE', 'PCS', 25600.00, 0.000, 1, 'MU0953'
UNION ALL SELECT 'MU0954', 'KUNCI BUSI MESIN ALKON', 'S/PART', 'MIYAMOTO', 'PCS', 12000.00, 6.000, 1, 'MU0954'
UNION ALL SELECT 'MU0955', 'STIK LURUS BENGKOK MESIN HANDSPRAYER', 'S/PART', 'MIYAMOTO', 'PSG', 37000.00, 6.000, 1, 'MU0955'
UNION ALL SELECT 'MU0956', 'NIRWANA GREY 40X40', 'TEHEL', '-', 'DUS', 59000.00, 8.000, 1, 'MU0956'
UNION ALL SELECT 'MU0957', 'DAKOTA BROWN 40X40', 'TEHEL', 'LEGION', 'DUS', 59000.00, 8.000, 1, 'MU0957'
UNION ALL SELECT 'MU0958', 'STOP KRAN 3/4 BLITZ', 'KRAN', 'BLITZ', 'PCS', 17500.00, 5.000, 1, 'MU0958'
UNION ALL SELECT 'MU0959', 'DOUBLE TAPE KERTAS', 'LAKBAN', 'MUGEN', 'PCS', 5000.00, 0.000, 1, 'MU0959'
UNION ALL SELECT 'MU0960', 'KRAN AIR 556 V2', 'KRAN', '-', 'PCS', 70400.00, 0.000, 1, 'MU0960'
UNION ALL SELECT 'MU0961', 'KRAN AIR 1707', 'KRAN', '-', 'PCS', 70400.00, 0.000, 1, 'MU0961'
UNION ALL SELECT 'MU0962', 'KRAN TAMAN HONXI 3/4', 'KRAN', '-', 'PCS', 14000.00, 6.000, 1, 'MU0962'
UNION ALL SELECT 'MU0963', 'KRAN AIR ANGSA', 'KRAN', '-', 'PCS', 92000.00, 0.000, 1, 'MU0963'
UNION ALL SELECT 'MU0964', 'HANGER KAMAR MANDI V1', 'MATA SERKEL', '-', 'UNIT', 0.00, 0.000, 1, 'MU0964'
UNION ALL SELECT 'MU0965', 'HANGER KAMAR MANDI V2', 'MATA SERKEL', '-', 'UNIT', 0.00, 0.000, 1, 'MU0965'
UNION ALL SELECT 'MU0966', 'KRAN AIR 557', 'KRAN', '-', 'PCS', 70400.00, 0.000, 1, 'MU0966'
UNION ALL SELECT 'MU0967', 'KRAN ANGSA K.KECIL', 'KRAN', '-', 'PCS', 70400.00, 5.000, 1, 'MU0967'
UNION ALL SELECT 'MU0968', 'STOP KRAN TOILET SK-018', 'KRAN', 'SANSHAN', 'PCS', 23000.00, 0.000, 1, 'MU0968'
UNION ALL SELECT 'MU0969', 'BOSCH M.GURINDA GWS 060', 'MESIN', 'BOSCH', 'UNIT', 835200.00, 0.000, 1, 'MU0969'
UNION ALL SELECT 'MU0970', 'MASKER', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU0970'
UNION ALL SELECT 'MU0971', 'BOSCH M.BOR GSB 550', 'MESIN', 'BOSCH', 'UNIT', 484800.00, 0.000, 1, 'MU0971'
UNION ALL SELECT 'MU0972', 'JAM MAYOMI QUARTS', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU0972'
UNION ALL SELECT 'MU0973', 'JAM MIRANDO QUARTS', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU0973'
UNION ALL SELECT 'MU0974', 'CERMIN DINDING MASPION WM-12', 'CERMIN', 'MASPION', 'PCS', 64800.00, 0.000, 1, 'MU0974'
UNION ALL SELECT 'MU0975', 'CERMIN DINDING MASPION WM-18', 'CERMIN', 'MASPION', 'PCS', 171200.00, 0.000, 1, 'MU0975'
UNION ALL SELECT 'MU0976', 'FISCHER FRT M10 4X45MM', 'FISHER', 'FRT', 'PAK', 15200.00, 0.000, 1, 'MU0976'
UNION ALL SELECT 'MU0977', 'FISCHER FRT M8 4X40MM', 'FISHER', 'FRT', 'PAK', 12800.00, 0.000, 1, 'MU0977'
UNION ALL SELECT 'MU0978', 'FISCHER FRT M6 3X30MM', 'FISHER', 'FRT', 'PAK', 15200.00, 0.000, 1, 'MU0978'
UNION ALL SELECT 'MU0979', 'FISCHER FRT M12 6X60MM', 'FISHER', 'FRT', 'PAK', 16800.00, 0.000, 1, 'MU0979'
UNION ALL SELECT 'MU0980', 'CAVANNI ROLLER LOCKCASE', 'MATA SERKEL', '-', 'SET', 0.00, 0.000, 1, 'MU0980'
UNION ALL SELECT 'MU0981', 'PIPA INSTALASI 4 CABANG', 'PIPA', 'NISO', 'PCS', 4800.00, 6.000, 1, 'MU0981'
UNION ALL SELECT 'MU0982', 'AFOS LOCKBODY 8502 - 45', 'V-BELT', 'AFOS', 'SET', 0.00, 0.000, 1, 'MU0982'
UNION ALL SELECT 'MU0983', 'E.B DOOR LOCK SS 304', 'MATA SERKEL', '-', 'SET', 0.00, 0.000, 1, 'MU0983'
UNION ALL SELECT 'MU0984', 'MUBEO ADLLER BARREL BOLT 518 6\"+12\"', 'GRENDEL', 'MULLER', 'SET', 55200.00, 0.000, 1, 'MU0984'
UNION ALL SELECT 'MU0985', 'HORISON US500 OVERPAL', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU0985'
UNION ALL SELECT 'MU0986', 'ENGSEL GP 4\"', 'ENGSEL', 'IGM', 'SET', 13333.00, 24.000, 1, 'MU0986'
UNION ALL SELECT 'MU0987', 'HANDSPRAYER NOQIWA 2TAK 16L', 'MESIN', 'NOQIWA', 'UNIT', 1320333.00, 9.000, 1, 'MU0987'
UNION ALL SELECT 'MU0988', 'WATER MUR 1\'\'', 'SAMBUNGAN', 'POWER', 'PCS', 46380.00, 0.000, 1, 'MU0988'
UNION ALL SELECT 'MU0989', 'WIPRO GLUE GUN 40W', 'LEM', 'WIPRO', 'PCS', 74250.00, 0.000, 1, 'MU0989'
UNION ALL SELECT 'MU0990', 'LEM TEMBAK TOHO', 'MATA SERKEL', 'TOHO', 'PCS', 92000.00, 0.000, 1, 'MU0990'
UNION ALL SELECT 'MU0991', 'GUNTING JUNIOR J 500', 'GUNTING', 'JUNIOR', 'PCS', 10400.00, 0.000, 1, 'MU0991'
UNION ALL SELECT 'MU0992', 'OVERVAL SILVER', 'OVERVAL', '-', 'PCS', 2400.00, 0.000, 1, 'MU0992'
UNION ALL SELECT 'MU0993', 'PISAU KATER ZRM', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU0993'
UNION ALL SELECT 'MU0994', 'ALKALINE AAA', 'MATA SERKEL', '-', 'PSG', 0.00, 0.000, 1, 'MU0994'
UNION ALL SELECT 'MU0995', 'NACHI TAPE 48', 'MATA SERKEL', '-', 'PCS', 7000.00, 0.000, 1, 'MU0995'
UNION ALL SELECT 'MU0996', 'NACHI MASKING TAPE 24', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU0996'
UNION ALL SELECT 'MU0997', 'LAKBAN ANTI BOCOR PUTIH 5CMX3M', 'LAKBAN', 'MUGEN', 'PCS', 15000.00, 0.000, 1, 'MU0997'
UNION ALL SELECT 'MU0998', 'POLAR DOUBLE TAPE KECIL', 'LAKBAN', 'POLAR', 'PCS', 12000.00, 0.000, 1, 'MU0998'
UNION ALL SELECT 'MU0999', 'LAKBAN BENING BESAR', 'LAKBAN', 'PAJERO', 'PCS', 10000.00, 0.000, 1, 'MU0999'
UNION ALL SELECT 'MU1000', 'LAKBAN ALUMUNIUM', 'LAKBAN', 'BERRY', 'PCS', 64000.00, 0.000, 1, 'MU1000'
UNION ALL SELECT 'MU1001', 'LAKBAN BENING KECIL NACHI', 'ISOLASI', 'NACHI', 'PCS', 2400.00, 0.000, 1, 'MU1001'
UNION ALL SELECT 'MU1002', 'TINNER BLADE 1 LTR', 'TINNER', 'BLADE', 'KLG', 33000.00, 0.000, 1, 'MU1002'
UNION ALL SELECT 'MU1003', 'TINNER BLADE 5 LTR', 'TINNER', 'BLADE', 'KLG', 11000.00, 0.000, 1, 'MU1003'
UNION ALL SELECT 'MU1004', 'GRENDEL ETB-201-3\'\'', 'GRENDEL', 'EPIC', 'PCS', 13600.00, 0.000, 1, 'MU1004'
UNION ALL SELECT 'MU1005', 'SENDOK SOUP STAINLIS BESAR', 'SENDOK', 'STAINLISSTEEL', 'PCS', 13600.00, 0.000, 1, 'MU1005'
UNION ALL SELECT 'MU1006', 'EROS TRIANGLE', 'SENDOK', 'TRIANGLE', 'PCS', 8000.00, 0.000, 1, 'MU1006'
UNION ALL SELECT 'MU1007', 'EROS MAHKOTA', 'IRUS', 'STAINLISSTEEL', 'PCS', 24000.00, 0.000, 1, 'MU1007'
UNION ALL SELECT 'MU1008', 'RODA PELOR SET 4 PCS', 'RODA', '-', 'SET', 40000.00, 0.000, 1, 'MU1008'
UNION ALL SELECT 'MU1009', 'PASAH KAYU BAMUNG', 'PARUT', '-', 'PCS', 25000.00, 0.000, 1, 'MU1009'
UNION ALL SELECT 'MU1010', 'GINZO', 'MATA SERKEL', 'GINZO', 'PCS', 9000.00, 0.000, 1, 'MU1010'
UNION ALL SELECT 'MU1011', 'YELLOW PB112', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 0.000, 1, 'MU1011'
UNION ALL SELECT 'MU1012', 'PARUT TY', 'PARUT', 'TY', 'PCS', 10000.00, 0.000, 1, 'MU1012'
UNION ALL SELECT 'MU1013', 'PINCUK', 'SEROK', '-', 'PCS', 4000.00, 0.000, 1, 'MU1013'
UNION ALL SELECT 'MU1014', 'PINCUK TANGGUNG', 'SEROK', '-', 'PCS', 6400.00, 0.000, 1, 'MU1014'
UNION ALL SELECT 'MU1015', 'PINCUK BESAR', 'SEROK', '-', 'PCS', 13600.00, 0.000, 1, 'MU1015'
UNION ALL SELECT 'MU1016', 'SEROK KAYU 24', 'SEROK', '-', 'PCS', 24000.00, 0.000, 1, 'MU1016'
UNION ALL SELECT 'MU1017', 'SEROK KAYU 26', 'SEROK', '-', 'PCS', 36000.00, 0.000, 1, 'MU1017'
UNION ALL SELECT 'MU1018', 'SEROK KAYU 32', 'SEROK', '-', 'PCS', 32000.00, 0.000, 1, 'MU1018'
UNION ALL SELECT 'MU1019', 'TERMOS NASI 30Liter USA', 'TERMOS NASI', '-', 'PCS', 189000.00, 0.000, 1, 'MU1019'
UNION ALL SELECT 'MU1020', 'TUTUP SAJI STAINLIS 70 CM', 'TUDUNG SAJI', '-', 'PCS', 102000.00, 0.000, 1, 'MU1020'
UNION ALL SELECT 'MU1021', 'POT + TATAAN 5822', 'POT', 'CLARIS', 'PCS', 16000.00, 0.000, 1, 'MU1021'
UNION ALL SELECT 'MU1022', 'POT + TATAAN 5825', 'POT', 'CLARIS', 'PCS', 18400.00, 0.000, 1, 'MU1022'
UNION ALL SELECT 'MU1023', 'POT RG HITAM GANTUNG', 'POT', 'MASPION', 'PCS', 9600.00, 0.000, 1, 'MU1023'
UNION ALL SELECT 'MU1024', 'TALENAN B C086', 'TALENAN', 'MASPION', 'PCS', 28000.00, 0.000, 1, 'MU1024'
UNION ALL SELECT 'MU1025', 'TALENAN B C087', 'TALENAN', 'MASPION', 'PCS', 44000.00, 0.000, 1, 'MU1025'
UNION ALL SELECT 'MU1026', 'NAMPAN SEGI KOMET 1', 'NAMPAN', '-', 'PCS', 6400.00, 0.000, 1, 'MU1026'
UNION ALL SELECT 'MU1027', 'NAMPAN SEGI KOMET 2', 'NAMPAN', '-', 'PCS', 8000.00, 0.000, 1, 'MU1027'
UNION ALL SELECT 'MU1028', 'NAMPAN SEGI KOMET 3', 'NAMPAN', '-', 'PCS', 11200.00, 0.000, 1, 'MU1028'
UNION ALL SELECT 'MU1029', 'NAMPAN SEGI KOMET 4', 'NAMPAN', '-', 'PCS', 16000.00, 0.000, 1, 'MU1029'
UNION ALL SELECT 'MU1030', 'CERMIN BM12', 'CERMIN', '-', 'PCS', 33250.00, 0.000, 1, 'MU1030'
UNION ALL SELECT 'MU1031', 'CERMIN BM13', 'CERMIN', '-', 'PCS', 33250.00, 0.000, 1, 'MU1031'
UNION ALL SELECT 'MU1032', 'CERMIN BM14', 'CERMIN', '-', 'PCS', 29200.00, 0.000, 1, 'MU1032'
UNION ALL SELECT 'MU1033', 'HANGER', 'GANTUNGAN', '-', 'IKAT', 11200.00, 0.000, 1, 'MU1033'
UNION ALL SELECT 'MU1034', 'WAKUL CENGKEH JB', 'MATA SERKEL', '-', 'PCS', 19200.00, 0.000, 1, 'MU1034'
UNION ALL SELECT 'MU1035', 'WAKUL CENGKEH 18', 'MATA SERKEL', '-', 'PCS', 8400.00, 0.000, 1, 'MU1035'
UNION ALL SELECT 'MU1036', 'BEAUTY HG 24 MP', 'GANTUNGAN', '-', 'PCS', 33500.00, 0.000, 1, 'MU1036'
UNION ALL SELECT 'MU1037', 'POT 40 H 5560 HLK', 'POT', 'WINNER', 'PCS', 10000.00, 0.000, 1, 'MU1037'
UNION ALL SELECT 'MU1038', 'POT 60 H 5560 H LK', 'POT', 'WINNER', 'PCS', 84000.00, 0.000, 1, 'MU1038'
UNION ALL SELECT 'MU1039', 'POT 22 VANDA', 'POT', 'VANDA', 'PCS', 6400.00, 0.000, 1, 'MU1039'
UNION ALL SELECT 'MU1040', 'POT 27 VANDA', 'POT', 'VANDA', 'PCS', 8000.00, 0.000, 1, 'MU1040'
UNION ALL SELECT 'MU1041', 'POT 32 VANDA', 'POT', 'VANDA', 'PCS', 10400.00, 0.000, 1, 'MU1041'
UNION ALL SELECT 'MU1042', 'POT 37 VANDA', 'POT', 'VANDA', 'PCS', 14400.00, 0.000, 1, 'MU1042'
UNION ALL SELECT 'MU1043', 'TERMOS 5500 P', 'TERMOS AIR', '-', 'PCS', 60000.00, 0.000, 1, 'MU1043'
UNION ALL SELECT 'MU1044', 'TERMOS 599 C', 'TERMOS AIR', '-', 'PCS', 68000.00, 0.000, 1, 'MU1044'
UNION ALL SELECT 'MU1045', 'TERMOS 2511 SS', 'TERMOS AIR', '-', 'PCS', 80000.00, 0.000, 1, 'MU1045'
UNION ALL SELECT 'MU1046', 'TERMOS NASI 6 Ltr USA', 'TERMOS NASI', 'MASPION', 'PCS', 72000.00, 0.000, 1, 'MU1046'
UNION ALL SELECT 'MU1047', 'TERMOS NASI 10 Ltr USA', 'TERMOS NASI', 'MASPION', 'PCS', 90400.00, 0.000, 1, 'MU1047'
UNION ALL SELECT 'MU1048', 'TERMOS NASI 14 Ltr USA', 'TERMOS NASI', 'MASPION', 'PCS', 143200.00, 0.000, 1, 'MU1048'
UNION ALL SELECT 'MU1049', 'TERMOS NASI 17 Ltr USA', 'TERMOS NASI', 'MASPION', 'PCS', 144000.00, 0.000, 1, 'MU1049'
UNION ALL SELECT 'MU1050', 'TERMOS NASI 20 Ltr USA', 'TERMOS NASI', 'MASPION', 'PCS', 184000.00, 0.000, 1, 'MU1050'
UNION ALL SELECT 'MU1051', 'TERMOS NASI 26 Ltr USA', 'TERMOS NASI', 'MASPION', 'PCS', 228000.00, 0.000, 1, 'MU1051'
UNION ALL SELECT 'MU1052', 'BAK MEMBLE 24', 'EMBER', '-', 'PCS', 21250.00, 0.000, 1, 'MU1052'
UNION ALL SELECT 'MU1053', 'BAK MEMBLE 26', 'EMBER', '-', 'PCS', 24200.00, 0.000, 1, 'MU1053'
UNION ALL SELECT 'MU1054', 'BAK MEMBLE 28', 'EMBER', '-', 'PCS', 27100.00, 0.000, 1, 'MU1054'
UNION ALL SELECT 'MU1055', 'BAK MEMBLE 32', 'EMBER', '-', 'PCS', 33400.00, 0.000, 1, 'MU1055'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU1056' AS sku, 'BAK MEMBLE 36' AS name, 'EMBER' AS category_name, '-' AS brand_name, 'PCS' AS base_unit_code, 36700.00 AS cost_price, 0.000 AS min_stock, 1 AS is_active, 'MU1056' AS legacy_sku
UNION ALL SELECT 'MU1057', 'TIMBA COR 18 BRG', 'EMBER', '-', 'PCS', 14400.00, 0.000, 1, 'MU1057'
UNION ALL SELECT 'MU1058', 'TIMBA COR 20 BRG', 'EMBER', '-', 'PCS', 15200.00, 0.000, 1, 'MU1058'
UNION ALL SELECT 'MU1059', 'TIMBA COR 22 BRG', 'EMBER', '-', 'PCS', 12000.00, 0.000, 1, 'MU1059'
UNION ALL SELECT 'MU1060', 'TIMBA COR 24 BRG', 'EMBER', '-', 'PCS', 14500.00, 0.000, 1, 'MU1060'
UNION ALL SELECT 'MU1061', 'TIMBA COR 26 BRG', 'EMBER', '-', 'PCS', 16250.00, 0.000, 1, 'MU1061'
UNION ALL SELECT 'MU1062', 'TIMBA 60 Ltr HITAM', 'EMBER', '-', 'PCS', 29200.00, 0.000, 1, 'MU1062'
UNION ALL SELECT 'MU1063', 'TIMBA 70 Ltr HITAM', 'EMBER', '-', 'PCS', 32000.00, 0.000, 1, 'MU1063'
UNION ALL SELECT 'MU1064', 'SOLET PUTIH', 'SOLET', 'IKIMURA', 'PCS', 2000.00, 0.000, 1, 'MU1064'
UNION ALL SELECT 'MU1065', 'SENDOK NASI RAINBOW', 'SENDOK NASI', 'RAINBOW', 'PCS', 4000.00, 0.000, 1, 'MU1065'
UNION ALL SELECT 'MU1066', 'ENTONG LUCKY', 'SENDOK NASI', 'LUCKY', 'PCS', 4000.00, 0.000, 1, 'MU1066'
UNION ALL SELECT 'MU1067', 'SENDOK NASI PUTIH', 'SENDOK NASI', '-', 'PCS', 3200.00, 0.000, 1, 'MU1067'
UNION ALL SELECT 'MU1068', 'TUTUP SAJI 534 LK', 'TUDUNG SAJI', '-', 'PCS', 36400.00, 0.000, 1, 'MU1068'
UNION ALL SELECT 'MU1069', 'KERANJANG BAJU OLIVIA', 'KERANJANG', '-', 'PCS', 15900.00, 0.000, 1, 'MU1069'
UNION ALL SELECT 'MU1070', 'KERANJANG LUXIO K', 'KERANJANG', 'MULTIPLAST', 'PCS', 40000.00, 0.000, 1, 'MU1070'
UNION ALL SELECT 'MU1071', 'KERANJANG LUXIO B', 'KERANJANG', 'MULTIPLAST', 'PCS', 48000.00, 0.000, 1, 'MU1071'
UNION ALL SELECT 'MU1072', 'PIRING ANYAM K 2MC', 'PIRING', '-', 'PCS', 3200.00, 0.000, 1, 'MU1072'
UNION ALL SELECT 'MU1073', 'PIRING SEGI LMN POLOS', 'PIRING', 'LEMONY', 'PCS', 7200.00, 0.000, 1, 'MU1073'
UNION ALL SELECT 'MU1074', 'PIRING ROTAN ANYAM', 'PIRING', 'DINNER WARE', 'PCS', 6400.00, 0.000, 1, 'MU1074'
UNION ALL SELECT 'MU1075', 'CORONG 9 CM', 'CORONG', '-', 'PCS', 4000.00, 0.000, 1, 'MU1075'
UNION ALL SELECT 'MU1076', 'CORONG 12 CM', 'CORONG', 'HOMESTAR', 'PCS', 5600.00, 0.000, 1, 'MU1076'
UNION ALL SELECT 'MU1077', 'CORONG 15 CM', 'CORONG', 'HOMESTAR', 'PCS', 6400.00, 0.000, 1, 'MU1077'
UNION ALL SELECT 'MU1078', 'CORONG 20', 'CORONG', 'HOMESTAR', 'PCS', 8000.00, 0.000, 1, 'MU1078'
UNION ALL SELECT 'MU1079', 'CORONG 25 CM', 'CORONG', 'HOMESTAR', 'PCS', 10400.00, 0.000, 1, 'MU1079'
UNION ALL SELECT 'MU1080', 'CORONG 30', 'CORONG', '-', 'PCS', 10000.00, 0.000, 1, 'MU1080'
UNION ALL SELECT 'MU1081', 'SEAL WARE 5 Ltr', 'MATA SERKEL', '-', 'PCS', 12000.00, 0.000, 1, 'MU1081'
UNION ALL SELECT 'MU1082', 'SEAL WARE 10 Ltr', 'MATA SERKEL', '-', 'PCS', 13750.00, 0.000, 1, 'MU1082'
UNION ALL SELECT 'MU1083', 'TOPLES GG SEGI 6Ltr', 'TOPLES', '-', 'PCS', 11100.00, 0.000, 1, 'MU1083'
UNION ALL SELECT 'MU1084', 'TOPLES GG SEGI 12 Ltr', 'TOPLES', '-', 'PCS', 15500.00, 0.000, 1, 'MU1084'
UNION ALL SELECT 'MU1085', 'SELANG BUANG KARET BIRU 4 X 100', 'SLANG', 'NOQIWA', 'ROL', 508000.00, 5.000, 1, 'MU1085'
UNION ALL SELECT 'MU1086', 'TOPLES ODATE 0,5 KG', 'TOPLES', '-', 'PCS', 6900.00, 0.000, 1, 'MU1086'
UNION ALL SELECT 'MU1087', 'TOPLES ODATE 0,6 KG', 'TOPLES', '-', 'PCS', 7900.00, 0.000, 1, 'MU1087'
UNION ALL SELECT 'MU1088', 'TOPLES ODATE 1 KG', 'TOPLES', '-', 'PCS', 10600.00, 0.000, 1, 'MU1088'
UNION ALL SELECT 'MU1089', 'BAK MEMBLE 23', 'EMBER', '-', 'PCS', 14200.00, 0.000, 1, 'MU1089'
UNION ALL SELECT 'MU1090', 'KIPAS BERDIRI HACHIDA WF 168', 'KIPAS', 'HACHIDA', 'PCS', 160000.00, 1.000, 1, 'MU1090'
UNION ALL SELECT 'MU1091', 'KIPAS MEJA OKAYAMA OK-1075', 'KIPAS', 'OKAYAMA', 'PCS', 155000.00, 1.000, 1, 'MU1091'
UNION ALL SELECT 'MU1092', 'KIPAS BERDIRI MIYAKO KAS-1606X GB', 'KIPAS', 'MIYAKO', 'PCS', 225000.00, 1.000, 1, 'MU1092'
UNION ALL SELECT 'MU1093', 'MIYAKO MCM 638 BATIK PARANG', 'MAGICCOM', 'MIYAKO', 'UNIT', 285000.00, 0.000, 1, 'MU1093'
UNION ALL SELECT 'MU1094', 'TURBO 1180', 'MAGICCOM', 'TURBO', 'PCS', 352000.00, 0.000, 1, 'MU1094'
UNION ALL SELECT 'MU1095', 'YONGMA 7033', 'MAGICCOM', 'YONGMA', 'PCS', 644000.00, 0.000, 1, 'MU1095'
UNION ALL SELECT 'MU1096', 'SETRIKA PHILIPS DST 0510 BIRU', 'SETRIKA', 'PHILIP', 'UNIT', 145000.00, 1.000, 1, 'MU1096'
UNION ALL SELECT 'MU1097', 'SETRIKA MASPION EX-1000', 'SETRIKA', 'MASPION', 'PCS', 120000.00, 1.000, 1, 'MU1097'
UNION ALL SELECT 'MU1098', 'MIXER MIYAKO SM 625', 'ALAT DAPUR', 'MIYAKO', 'UNIT', 285000.00, 1.000, 1, 'MU1098'
UNION ALL SELECT 'MU1099', 'SETRIKA PHILIPS HD-1172', 'SETRIKA', 'HANDSOME', 'PCS', 324000.00, 0.000, 1, 'MU1099'
UNION ALL SELECT 'MU1100', 'WD-40 (333ML)', 'AEROSOL', '-', 'PCS', 113000.00, 0.000, 1, 'MU1100'
UNION ALL SELECT 'MU1101', 'KIPAS DINDING ARASHI AR228 PW7', 'KIPAS', 'ARASHI', 'UNIT', 210000.00, 1.000, 1, 'MU1101'
UNION ALL SELECT 'MU1102', 'MIKSER PHILIPS 1552', 'MIKSER', 'HANDSOME', 'PCS', 492000.00, 0.000, 1, 'MU1102'
UNION ALL SELECT 'MU1103', 'MIKSER PHILIPS 1559', 'MIKSER', 'HANDSOME', 'PCS', 600000.00, 0.000, 1, 'MU1103'
UNION ALL SELECT 'MU1104', 'PANCI ORSID 55', 'PANCI', 'ORCHID', 'PCS', 874000.00, 0.000, 1, 'MU1104'
UNION ALL SELECT 'MU1105', 'PANCI ORSID 40', 'PANCI', 'ORCHID', 'PCS', 524000.00, 0.000, 1, 'MU1105'
UNION ALL SELECT 'MU1106', 'PANCI ORSID 50', 'PANCI', 'ORCHID', 'PCS', 724000.00, 0.000, 1, 'MU1106'
UNION ALL SELECT 'MU1107', 'PANCI ORSID 60', 'PANCI', 'ORCHID', 'PCS', 1025000.00, 0.000, 1, 'MU1107'
UNION ALL SELECT 'MU1108', 'PANCI ORSID 45', 'PANCI', 'ORCHID', 'PCS', 625000.00, 0.000, 1, 'MU1108'
UNION ALL SELECT 'MU1109', 'POT 15 H 5560 HLK', 'POT', 'WINNER', 'PCS', 2400.00, 0.000, 1, 'MU1109'
UNION ALL SELECT 'MU1110', 'POT 17 H 5560 H LK', 'POT', 'WINNER', 'PCS', 4000.00, 0.000, 1, 'MU1110'
UNION ALL SELECT 'MU1111', 'POT 20 H 5560 H LK', 'POT', 'WINNER', 'PCS', 5600.00, 0.000, 1, 'MU1111'
UNION ALL SELECT 'MU1112', 'POT 25 H 5560 H LK', 'POT', 'WINNER', 'PCS', 6400.00, 0.000, 1, 'MU1112'
UNION ALL SELECT 'MU1113', 'POT 30 H 5560 H LK', 'POT', 'WINNER', 'PCS', 8000.00, 0.000, 1, 'MU1113'
UNION ALL SELECT 'MU1114', 'PANCI JAWA 28 CM', 'PANCI', 'JAWA', 'PCS', 146000.00, 0.000, 1, 'MU1114'
UNION ALL SELECT 'MU1115', 'PANCI JAWA 36 CM', 'PANCI', 'JAWA', 'PCS', 216000.00, 0.000, 1, 'MU1115'
UNION ALL SELECT 'MU1116', 'TOPLES ODATE 2 1/2 Ltr', 'TOPLES', '-', 'PCS', 19200.00, 0.000, 1, 'MU1116'
UNION ALL SELECT 'MU1117', 'TOPLES ODATE 4', 'TOPLES', '-', 'PCS', 24200.00, 0.000, 1, 'MU1117'
UNION ALL SELECT 'MU1118', 'PANCI JAWA 40 CM', 'PANCI', 'JAWA', 'PCS', 294000.00, 0.000, 1, 'MU1118'
UNION ALL SELECT 'MU1119', 'PANCI JAWA 26 CM', 'PANCI', 'JAWA', 'PCS', 135000.00, 0.000, 1, 'MU1119'
UNION ALL SELECT 'MU1120', 'TURBO 1200', 'MAGICCOM', 'TURBO', 'PCS', 352000.00, 0.000, 1, 'MU1120'
UNION ALL SELECT 'MU1121', 'MAGIC COM CRJ 3232', 'MAGICCOM', 'COSMOS', 'UNIT', 280000.00, 0.000, 1, 'MU1121'
UNION ALL SELECT 'MU1122', 'TERMOS NASI 30 Ltr USA', 'TERMOS NASI', 'MASPION', 'PCS', 260000.00, 0.000, 1, 'MU1122'
UNION ALL SELECT 'MU1123', 'SOUP BOWL SUPRA 24 CM', 'PRASMANAN', 'SUPRA', 'PCS', 280000.00, 0.000, 1, 'MU1123'
UNION ALL SELECT 'MU1124', 'SCRAPER CUTTER', 'CUTTER', 'HANDSOME', 'PCS', 18500.00, 0.000, 1, 'MU1124'
UNION ALL SELECT 'MU1125', 'MIKSER MIYOKO SM-625', 'MIKSER', 'MIYOKO', 'PCS', 276000.00, 0.000, 1, 'MU1125'
UNION ALL SELECT 'MU1126', 'MUR TANDON 3/4 ABS', 'MUR', '-', 'PCS', 13500.00, 0.000, 1, 'MU1126'
UNION ALL SELECT 'MU1127', 'MAGIC COM CRJ 3306', 'MAGICCOM', 'COSMOS', 'UNIT', 250000.00, 0.000, 1, 'MU1127'
UNION ALL SELECT 'MU1128', 'SOUP BOWL NEW OVAL PAN SS 0.05', 'PRASMANAN', '-', 'PCS', 380000.00, 0.000, 1, 'MU1128'
UNION ALL SELECT 'MU1129', 'MIKSER MIYOKO HM-620', 'MIKSER', 'MIYOKO', 'PCS', 192000.00, 0.000, 1, 'MU1129'
UNION ALL SELECT 'MU1130', 'PHILIPS 3132', 'MAGICCOM', 'HANDSOME', 'PCS', 684000.00, 0.000, 1, 'MU1130'
UNION ALL SELECT 'MU1131', 'ALISTA LUNCH BOX 4 TW', 'BOX MAKANAN', 'ARISTA', 'PCS', 52000.00, 0.000, 1, 'MU1131'
UNION ALL SELECT 'MU1132', 'SAPU KAIN SUMBU PENDEK', 'SAPU', '-', 'PCS', 0.00, 0.000, 1, 'MU1132'
UNION ALL SELECT 'MU1133', 'AMPLAS BULAT P120', 'AMPLAS', '-', 'LBR', 2000.00, 0.000, 1, 'MU1133'
UNION ALL SELECT 'MU1134', 'CLIO PLASTIK V', 'BOX MAKANAN', 'CLIO', 'PCS', 32000.00, 0.000, 1, 'MU1134'
UNION ALL SELECT 'MU1135', 'DISPENSER MONTANG 21 Ltr', 'DISPENSER', 'MONTANA', 'PCS', 160000.00, 0.000, 1, 'MU1135'
UNION ALL SELECT 'MU1136', 'DISPENSER MONTANG 27 Ltr', 'DISPENSER', 'MONTANA', 'PCS', 176000.00, 0.000, 1, 'MU1136'
UNION ALL SELECT 'MU1137', 'DISPENSER SHOPIE 6688', 'DISPENSER', 'SHOPIE', 'PCS', 228000.00, 0.000, 1, 'MU1137'
UNION ALL SELECT 'MU1138', 'SQUASH JAR 26 Ltr', 'DISPENSER', 'SQUASH', 'PCS', 92000.00, 0.000, 1, 'MU1138'
UNION ALL SELECT 'MU1139', 'ARISTA LUNCH BOX 2 TW', 'BOX MAKANAN', 'ARISTA', 'PCS', 32000.00, 0.000, 1, 'MU1139'
UNION ALL SELECT 'MU1140', 'ARISTA LUNCH BOX 3 TW', 'BOX MAKANAN', 'ARISTA', 'PCS', 44000.00, 0.000, 1, 'MU1140'
UNION ALL SELECT 'MU1141', 'CALSIBOOT', 'PLYWOOD', '-', 'LBR', 88000.00, 0.000, 1, 'MU1141'
UNION ALL SELECT 'MU1142', 'KUNCI RING PASS WIPRO 17MM', 'V-BELT', 'WIPRO', 'PCS', 28000.00, 2.000, 1, 'MU1142'
UNION ALL SELECT 'MU1143', 'MILK TEA BUCKET 10 Ltr', 'DISPENSER', '-', 'PCS', 280000.00, 0.000, 1, 'MU1143'
UNION ALL SELECT 'MU1144', 'PORTA 8 Ltr', 'DISPENSER', '-', 'PCS', 160000.00, 0.000, 1, 'MU1144'
UNION ALL SELECT 'MU1145', 'PORTA 10 Ltr', 'DISPENSER', '-', 'PCS', 176000.00, 0.000, 1, 'MU1145'
UNION ALL SELECT 'MU1146', 'NAMPAN STAINLIS 50 CM', 'NAMPAN', '555', 'PCS', 48000.00, 0.000, 1, 'MU1146'
UNION ALL SELECT 'MU1147', 'NAMPAN STAINLIS 40CM', 'NAMPAN', '555', 'PCS', 40000.00, 0.000, 1, 'MU1147'
UNION ALL SELECT 'MU1148', 'SUTIL KAYU KECIL', 'SUTIL', '-', 'PCS', 4500.00, 0.000, 1, 'MU1148'
UNION ALL SELECT 'MU1149', 'MUR TANDON 1 ABS', 'MUR', '-', 'PCS', 16500.00, 0.000, 1, 'MU1149'
UNION ALL SELECT 'MU1150', 'ENTONG KAYU BESAR', 'SENDOK NASI', '-', 'PCS', 8000.00, 0.000, 1, 'MU1150'
UNION ALL SELECT 'MU1151', 'ENTONG KAYU KECIL', 'SENDOK NASI', '-', 'PCS', 7000.00, 0.000, 1, 'MU1151'
UNION ALL SELECT 'MU1152', 'TALENAN KAYU BESAR', 'TALENAN', '-', 'PCS', 16000.00, 0.000, 1, 'MU1152'
UNION ALL SELECT 'MU1153', 'TALENAN KAYU KECIL', 'TALENAN', '-', 'PCS', 15000.00, 0.000, 1, 'MU1153'
UNION ALL SELECT 'MU1154', 'SUTIL KAYU BESAR (KAYU KELAPA)', 'SUTIL', '-', 'PCS', 32000.00, 0.000, 1, 'MU1154'
UNION ALL SELECT 'MU1155', 'TALI GAS M BABAT W ELBOW', 'S/PART', 'MKK', 'PCS', 11340.00, 0.000, 1, 'MU1155'
UNION ALL SELECT 'MU1156', 'KOCOKAN TELUR SPIRAL', 'ALAT DAPUR', '-', 'PCS', 16500.00, 0.000, 1, 'MU1156'
UNION ALL SELECT 'MU1157', 'PARUTAN KEJU', 'PARUT', '-', 'PCS', 7000.00, 0.000, 1, 'MU1157'
UNION ALL SELECT 'MU1158', 'SUTIL BOCOR', 'SUTIL', '-', 'PCS', 5600.00, 0.000, 1, 'MU1158'
UNION ALL SELECT 'MU1159', 'SIKAT BAJA MANGKOK KUNINGAN', 'SIKAT', 'KING', 'PCS', 8500.00, 12.000, 1, 'MU1159'
UNION ALL SELECT 'MU116', 'KRAN TEMBOK ARCEL 1/2\'', 'KRAN', 'ARCEL', 'PCS', 20000.00, 0.000, 1, 'MU116'
UNION ALL SELECT 'MU1160', 'SIKAT MANGKOK KEPANG', 'SIKAT', 'KING', 'PCS', 18000.00, 0.000, 1, 'MU1160'
UNION ALL SELECT 'MU1161', 'MUR TANDON 3/4', 'MUR', 'TANDON', 'PCS', 8000.00, 0.000, 1, 'MU1161'
UNION ALL SELECT 'MU1162', 'MUR TANDON 1', 'MUR', 'TANDON', 'PCS', 10400.00, 0.000, 1, 'MU1162'
UNION ALL SELECT 'MU1163', 'OBENG RADIO ANANTA 8+', 'OBENG', 'RUBICON', 'PCS', 12000.00, 0.000, 1, 'MU1163'
UNION ALL SELECT 'MU1164', 'OBENG RADIO ATS 4+', 'OBENG', 'ATS', 'PCS', 6000.00, 0.000, 1, 'MU1164'
UNION ALL SELECT 'MU1165', 'OBENG RADIO  6+', 'OBENG', 'RUBICON', 'PCS', 10400.00, 6.000, 1, 'MU1165'
UNION ALL SELECT 'MU1166', 'GEMBOK ORYX 40MM PENDEK', 'V-BELT', 'ORYX', 'PCS', 13600.00, 0.000, 1, 'MU1166'
UNION ALL SELECT 'MU1167', 'KLEM PIPA PVC', 'KERANJANG', '-', 'PCS', 1000.00, 0.000, 1, 'MU1167'
UNION ALL SELECT 'MU1168', 'KUNCI PINTU HSJ', 'KUNCI PINTU', 'HSJ', 'SET', 100000.00, 0.000, 1, 'MU1168'
UNION ALL SELECT 'MU1169', 'IRON ENGSEL T 8\'', 'ENGSEL', 'KURA', 'PCS', 6485.00, 12.000, 1, 'MU1169'
UNION ALL SELECT 'MU1170', 'GRENDEL OVERVAL TEBAL 2\'', 'GRENDEL', '-', 'PCS', 8333.00, 24.000, 1, 'MU1170'
UNION ALL SELECT 'MU1171', 'GUNTING SENG HD 8\'', 'GUNTING', 'TIRIMAN', 'PCS', 23000.00, 6.000, 1, 'MU1171'
UNION ALL SELECT 'MU1172', 'GEMBOK KUNING 20MM', 'GEMBOK', 'ATS', 'PCS', 13000.00, 12.000, 1, 'MU1172'
UNION ALL SELECT 'MU1173', 'BASKOM LEMONY', 'BASKOM', 'LEMONY', 'PCS', 13600.00, 0.000, 1, 'MU1173'
UNION ALL SELECT 'MU1174', 'GEMBOK KIMORA 50MM', 'V-BELT', 'KIMORA', 'PCS', 24000.00, 0.000, 1, 'MU1174'
UNION ALL SELECT 'MU1176', 'GRENDEL PEER COKLAT', 'GRENDEL', 'RIVOTI', 'PCS', 2916.00, 0.000, 1, 'MU1176'
UNION ALL SELECT 'MU1177', 'DIAMOND WHEEL TURBO HANDZO', 'MATA GURINDA', 'HANDSOME', 'PCS', 42500.00, 0.000, 1, 'MU1177'
UNION ALL SELECT 'MU1178', 'LEM SILICON CLEAR ACETIC 300 GR', 'LEM', 'TAIYO', 'BTL', 20900.00, 6.000, 1, 'MU1178'
UNION ALL SELECT 'MU1179', 'KUNCI KUDA KECIL', 'V-BELT', 'KUDA', 'PCS', 85500.00, 0.000, 1, 'MU1179'
UNION ALL SELECT 'MU1180', 'KUNCI KUDA BESR', 'V-BELT', 'KUDA', 'PCS', 89600.00, 0.000, 1, 'MU1180'
UNION ALL SELECT 'MU1182', 'TANG POTONG 8\" TPC-200', 'TANG', 'WIPRO', 'PCS', 38500.00, 6.000, 1, 'MU1182'
UNION ALL SELECT 'MU1183', 'TANG KOMBINASI  IGM 6\'\"', 'V-BELT', 'NISHIO', 'PCS', 32500.00, 12.000, 1, 'MU1183'
UNION ALL SELECT 'MU1184', 'KLEM PIPA PVC 3/4', 'KLEM', '-', 'PCS', 1000.00, 0.000, 1, 'MU1184'
UNION ALL SELECT 'MU1185', 'GRENDEL OVERVAL TEBAL 4\'', 'GRENDEL', 'FRANCO', 'PCS', 11666.00, 12.000, 1, 'MU1185'
UNION ALL SELECT 'MU1186', 'WIPRO KUNCI RING PASS 13MM', 'V-BELT', 'WIPRO', 'PCS', 18400.00, 0.000, 1, 'MU1186'
UNION ALL SELECT 'MU1187', 'KUNCI RING PASS WIPRO 8MM', 'ALAT', 'WIPRO', 'PCS', 12000.00, 0.000, 1, 'MU1187'
UNION ALL SELECT 'MU1188', 'WIPRO KUNCI RING PASS 9MM', 'V-BELT', 'WIPRO', 'PCS', 12800.00, 0.000, 1, 'MU1188'
UNION ALL SELECT 'MU1189', 'RODA PINTU FRT BULAT 4', 'RODA', 'FRT', 'PCS', 32000.00, 0.000, 1, 'MU1189'
UNION ALL SELECT 'MU1190', 'RODA PINTU FRT KOTAK 4', 'RODA', 'FRT', 'PCS', 32000.00, 0.000, 1, 'MU1190'
UNION ALL SELECT 'MU1191', 'ENGSEL H OCP 110', 'ENGSEL', 'MADICO', 'PSG', 5000.00, 0.000, 1, 'MU1191'
UNION ALL SELECT 'MU1192', 'RODA HIDUP KARET 4 HITAM', 'RODA', 'TRIPLE S', 'PCS', 21500.00, 0.000, 1, 'MU1192'
UNION ALL SELECT 'MU1193', 'RODA HIDUP KARET 3 HITAM', 'RODA', 'TRIPLE S', 'PCS', 14750.00, 6.000, 1, 'MU1193'
UNION ALL SELECT 'MU1194', 'RODA PINTU KARET MATI 4', 'RODA', 'TRIPLE S', 'PCS', 28000.00, 0.000, 1, 'MU1194'
UNION ALL SELECT 'MU1195', 'RODA PINTU FRT BULAT 3', 'RODA', 'FRT', 'PCS', 24000.00, 0.000, 1, 'MU1195'
UNION ALL SELECT 'MU1196', 'SPOT SAGOLA K-3', 'ALAT', 'SAGOLA', 'UNIT', 117500.00, 0.000, 1, 'MU1196'
UNION ALL SELECT 'MU1197', 'SPOT SAGOLA G-330C', 'MATA SERKEL', 'SAGOLA', 'UNIT', 208000.00, 0.000, 1, 'MU1197'
UNION ALL SELECT 'MU1198', 'NYLON KOTAK 2.6X50M KAIDA', 'NYLON', 'NOQIWA', 'ROL', 30000.00, 0.000, 1, 'MU1198'
UNION ALL SELECT 'MU1199', 'ANGKER ROTER', 'MATA SERKEL', 'MAKITA', 'PCS', 164000.00, 0.000, 1, 'MU1199'
UNION ALL SELECT 'MU1200', 'ANGKER SKAP MT 192', 'MATA SERKEL', 'MAKITA', 'PCS', 208000.00, 0.000, 1, 'MU1200'
UNION ALL SELECT 'MU1201', 'LAVENDER SN/CP KUNCI PINTU S-LOCK', 'KUNCI PINTU', 'S-LOCK', 'SET', 75000.00, 0.000, 1, 'MU1201'
UNION ALL SELECT 'MU1202', 'VEMBEL SKAP', 'MATA SERKEL', '-', 'PCS', 52250.00, 0.000, 1, 'MU1202'
UNION ALL SELECT 'MU1203', 'KARUBURATOR MESIN BABAT', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1203'
UNION ALL SELECT 'MU1204', 'AMPLAS BULAT P180', 'AMPLAS', 'VPR', 'PCS', 1200.00, 0.000, 1, 'MU1204'
UNION ALL SELECT 'MU1205', 'KAMPAS SENSOR M.FACTURING', 'MATA SERKEL', 'NEW WEST GOLD', 'PCS', 52000.00, 0.000, 1, 'MU1205'
UNION ALL SELECT 'MU1206', 'MATA SKAP MAKITA D-16346', 'MATA SERKEL', 'MAKITA', 'PCS', 159900.00, 0.000, 1, 'MU1206'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU1207' AS sku, 'RANTAI SENSOR 38 MATA / 73 RD' AS name, 'RANTAI' AS category_name, 'OREGON' AS brand_name, 'PCS' AS base_unit_code, 150000.00 AS cost_price, 6.000 AS min_stock, 1 AS is_active, 'MU1207' AS legacy_sku
UNION ALL SELECT 'MU1208', 'STIHLL RANTAI SENSOR 26 MATA', 'RANTAI', 'STIHLL', 'PCS', 148000.00, 6.000, 1, 'MU1208'
UNION ALL SELECT 'MU1209', 'NGK BPM6A M.SENSOR', 'BUSI', 'NGK', 'PCS', 16450.00, 20.000, 1, 'MU1209'
UNION ALL SELECT 'MU1210', 'TULIP SN/GP KUNCI PINTU S-LOCK', 'KUNCI PINTU', 'S-LOCK', 'SET', 75000.00, 0.000, 1, 'MU1210'
UNION ALL SELECT 'MU1211', 'NOWEC1700 NOQIWA', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1211'
UNION ALL SELECT 'MU1212', 'NOQIWA KEPALA BUSI ALKON', 'MATA SERKEL', 'NOQIWA', 'PCS', 24000.00, 0.000, 1, 'MU1212'
UNION ALL SELECT 'MU1213', 'ON/OFF MESIN NOQIWA', 'MATA SERKEL', 'NOQIWA', 'PCS', 17600.00, 6.000, 1, 'MU1213'
UNION ALL SELECT 'MU1214', 'TALI GAS M BABAT MKK', 'S/PART', 'MKK', 'PCS', 9450.00, 0.000, 1, 'MU1214'
UNION ALL SELECT 'MU1215', 'FER STATER ALKON NOQIWA', 'MATA SERKEL', 'NOQIWA', 'PCS', 100000.00, 0.000, 1, 'MU1215'
UNION ALL SELECT 'MU1216', 'SEMEN NAT PUTIH GIANT MORTAR 510 03', 'SEMEN', 'Avian', 'BKS', 10294.00, 0.000, 1, 'MU1216'
UNION ALL SELECT 'MU1217', 'SEROK SAMPAH MULTIPLAST', 'SEROK', 'MULTIPLAST', 'PCS', 6000.00, 0.000, 1, 'MU1217'
UNION ALL SELECT 'MU1218', 'NOQIWA CARBURATOR MESIN ALCON', 'MATA SERKEL', 'NOQIWA', 'PCS', 140000.00, 6.000, 1, 'MU1218'
UNION ALL SELECT 'MU1219', 'POTENSIO ASSY SPEED CONTROL', 'S/PART', 'MIYAMOTO', 'PCS', 14000.00, 6.000, 1, 'MU1219'
UNION ALL SELECT 'MU1220', 'KUEL M.BABAT NOQIWA BG328', 'MATA SERKEL', 'NOQIWA', 'PCS', 76000.00, 6.000, 1, 'MU1220'
UNION ALL SELECT 'MU1221', 'ROSE SN/CP KUNCI PINTU S-LOCK', 'KUNCI PINTU', 'S-LOCK', 'SET', 75000.00, 0.000, 1, 'MU1221'
UNION ALL SELECT 'MU1222', 'KUEL M.ALCON NOQIWA', 'MATA SERKEL', 'NOQIWA', 'PCS', 56000.00, 6.000, 1, 'MU1222'
UNION ALL SELECT 'MU1223', 'BOSTER CB411A', 'MATA SERKEL', 'MAKITA', 'PCS', 28000.00, 0.000, 1, 'MU1223'
UNION ALL SELECT 'MU1224', 'BOSTER CB51A', 'MATA SERKEL', 'MAKITA', 'PCS', 28000.00, 0.000, 1, 'MU1224'
UNION ALL SELECT 'MU1225', 'BOSTER CB153A', 'MATA SERKEL', 'MAKITA', 'SET', 28000.00, 0.000, 1, 'MU1225'
UNION ALL SELECT 'MU1226', 'KUEL SENSOR BESAR', 'MATA SERKEL', 'NEW WEST', 'PCS', 80000.00, 0.000, 1, 'MU1226'
UNION ALL SELECT 'MU1227', 'NGK BP6ES M.ALCON', 'BUSI', 'NGK', 'PCS', 15695.00, 0.000, 1, 'MU1227'
UNION ALL SELECT 'MU1228', 'KEPALA NOZZEL 8 LB', 'S/PART', 'MIYAMOTO', 'PCS', 4000.00, 6.000, 1, 'MU1228'
UNION ALL SELECT 'MU1229', 'SPROKET KITAKO', 'MATA SERKEL', 'KITAKO', 'PCS', 43200.00, 0.000, 1, 'MU1229'
UNION ALL SELECT 'MU1230', 'SLINDER PISTON ITALIA', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1230'
UNION ALL SELECT 'MU1231', 'AMPLAS DUCO 2000', 'AMPLAS', 'FUJI STAR', 'LBR', 4750.00, 0.000, 1, 'MU1231'
UNION ALL SELECT 'MU1232', 'TEFLON MAXIM VALENTINO 12CM', 'TEFLON', 'MAXIM', 'PCS', 55500.00, 0.000, 1, 'MU1232'
UNION ALL SELECT 'MU1233', 'PISAU MESIN  BABAT NOQIWA 410X70X1.2MM', 'PISAU', 'NOQIWA', 'PCS', 48000.00, 6.000, 1, 'MU1233'
UNION ALL SELECT 'MU1234', 'PISAU MESIN BABAT BULAT', 'PISAU', 'MKK', 'PCS', 28600.00, 0.000, 1, 'MU1234'
UNION ALL SELECT 'MU1235', 'PISAU MESIN BABAT NOQIWA 305X90X1.6MM', 'PISAU', 'NOQIWA', 'PCS', 40000.00, 6.000, 1, 'MU1235'
UNION ALL SELECT 'MU1236', 'SAFETYBELT NOQIWA', 'MATA SERKEL', 'NOQIWA', 'PCS', 17000.00, 6.000, 1, 'MU1236'
UNION ALL SELECT 'MU1237', 'TUTUP TANGKI MESIN BABAT NOQIWA', 'MATA SERKEL', 'NOQIWA', 'PCS', 8000.00, 6.000, 1, 'MU1237'
UNION ALL SELECT 'MU1238', 'PISTON STIHL MESIN BABAT', 'MATA SERKEL', 'STIHLL', 'PCS', 44000.00, 0.000, 1, 'MU1238'
UNION ALL SELECT 'MU1239', 'PAKING KOP GX390 NOQIWA', 'MATA SERKEL', 'NOQIWA', 'PCS', 20000.00, 0.000, 1, 'MU1239'
UNION ALL SELECT 'MU1240', 'PAKING KOP GX160 NOQIWA', 'MATA SERKEL', 'NOQIWA', 'PCS', 16000.00, 0.000, 1, 'MU1240'
UNION ALL SELECT 'MU1241', 'PAKING SET GX160 NOQIWA', 'MATA SERKEL', 'NOQIWA', 'PCS', 20000.00, 0.000, 1, 'MU1241'
UNION ALL SELECT 'MU1242', 'ROTARY GREASE K', 'GEMUK', 'ROTARY', 'KLG', 28000.00, 0.000, 1, 'MU1242'
UNION ALL SELECT 'MU1243', 'PAKING SET GX390 NOQIWA', 'MATA SERKEL', 'NOQIWA', 'PCS', 44000.00, 0.000, 1, 'MU1243'
UNION ALL SELECT 'MU1244', 'PER GAS GX390 NOQIWA', 'MATA SERKEL', 'NOQIWA', 'PCS', 16000.00, 0.000, 1, 'MU1244'
UNION ALL SELECT 'MU1245', 'PER GAS GX160 NOQIWA', 'MATA SERKEL', 'NOQIWA', 'PCS', 8000.00, 0.000, 1, 'MU1245'
UNION ALL SELECT 'MU1246', 'POT LUCKY STAR NO 5333', 'POT', 'LUCKY STAR', 'PCS', 12000.00, 0.000, 1, 'MU1246'
UNION ALL SELECT 'MU1247', 'SAFETY GUARD MESIN BABAT NOQIWA', 'MATA SERKEL', 'NOQIWA', 'PCS', 24000.00, 6.000, 1, 'MU1247'
UNION ALL SELECT 'MU1248', 'KAMPAS M.BABAT ASLI STIHLL', 'KAMPAS', 'STIHLL', 'PCS', 96000.00, 6.000, 1, 'MU1248'
UNION ALL SELECT 'MU1249', 'KAMPAS MESIN BABAT NOQIWA', 'KAMPAS', 'NOQIWA', 'PCS', 16666.00, 6.000, 1, 'MU1249'
UNION ALL SELECT 'MU1250', 'SIL KEONG NS NOQIWA', 'MATA SERKEL', 'NOQIWA', 'PCS', 144000.00, 6.000, 1, 'MU1250'
UNION ALL SELECT 'MU1251', 'KARBU MESIN BABAT STIHL ASLI', 'MATA SERKEL', '-', 'PCS', 0.00, 6.000, 1, 'MU1251'
UNION ALL SELECT 'MU1252', 'KIPAS 5 DAUN NS100 YAMAOKE', 'MATA SERKEL', 'YAMAOKE', 'PCS', 280000.00, 6.000, 1, 'MU1252'
UNION ALL SELECT 'MU1253', 'ROTARY GRESE 0.45 kg', 'GEMUK', 'ROTARY', 'KLG', 54400.00, 24.000, 1, 'MU1253'
UNION ALL SELECT 'MU1254', 'KIPAS 4 DAUN ALKON KECIL NOQIWA', 'KIPAS', 'NOQIWA', 'PCS', 120000.00, 6.000, 1, 'MU1254'
UNION ALL SELECT 'MU1255', 'KIPAS 3 DAUN M.ALKON KECIL NOQIWA', 'KIPAS', 'NOQIWA', 'PCS', 112000.00, 0.000, 1, 'MU1255'
UNION ALL SELECT 'MU1256', 'KIPAS 3 DAUN MESIN ALKON NOQIWA', 'KIPAS', 'NOQIWA', 'PCS', 112000.00, 6.000, 1, 'MU1256'
UNION ALL SELECT 'MU1257', 'AMPLAS BULAT P60', 'AMPLAS', '-', 'LBR', 2000.00, 6.000, 1, 'MU1257'
UNION ALL SELECT 'MU1258', 'KIPAS MESIN NS50 YAMAOKE', 'KIPAS', 'YAMAOKE', 'PCS', 120000.00, 6.000, 1, 'MU1258'
UNION ALL SELECT 'MU1259', 'MATA CIRCLE 185X20MM MAKITA', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1259'
UNION ALL SELECT 'MU1260', 'MATA CIRCLE 184MM BOSCH', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1260'
UNION ALL SELECT 'MU1261', 'MATA CIRCLE 230X25.4MM MAKITA', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1261'
UNION ALL SELECT 'MU1262', 'MAKITA MATA CIRCLE 255X25.4 MM', 'MATA SERKEL', 'MAKITA', 'PCS', 415200.00, 0.000, 1, 'MU1262'
UNION ALL SELECT 'MU1263', 'BERLIN MATA CIRCLE MESIN SOMEL', 'MATA SERKEL', 'BERLIN', 'PCS', 2480000.00, 0.000, 1, 'MU1263'
UNION ALL SELECT 'MU1264', 'BERLIN MATA CIRCLE ORDEN', 'MATA SERKEL', 'BERLIN', 'PCS', 1080000.00, 0.000, 1, 'MU1264'
UNION ALL SELECT 'MU1265', 'ALPINA GREY 50X50', 'TEHEL', 'JUPITER', 'DUS', 95000.00, 0.000, 1, 'MU1265'
UNION ALL SELECT 'MU1266', 'POMPA AIR MASPION', 'MESIN', 'MASPION', 'UNIT', 504000.00, 0.000, 1, 'MU1266'
UNION ALL SELECT 'MU1267', 'DINABOLT 10X77', 'BAUT', 'WOWO', 'PCS', 2000.00, 0.000, 1, 'MU1267'
UNION ALL SELECT 'MU1268', 'KUNCI PINTU KENZO PARIS', 'PINTU', '-', 'SET', 149600.00, 0.000, 1, 'MU1268'
UNION ALL SELECT 'MU1269', 'FLEKSIBLE SHAFT ASSY 84CM', 'S/PART', 'MIYAMOTO', 'UNIT', 0.00, 6.000, 1, 'MU1269'
UNION ALL SELECT 'MU1270', 'MESIN BABAT STHILL', 'MESIN', 'STIHLL', 'UNIT', 1968000.00, 0.000, 1, 'MU1270'
UNION ALL SELECT 'MU1271', 'MCB 16 AMPER DOMAI', 'MCB', 'SCHNEIDER', 'PCS', 52000.00, 0.000, 1, 'MU1271'
UNION ALL SELECT 'MU1272', 'MCB 2 AMPER DOMAI', 'MCB', 'SCHNEIDER', 'PCS', 60000.00, 0.000, 1, 'MU1272'
UNION ALL SELECT 'MU1273', 'MCB 20 AMPER DOMAI', 'MCB', 'SCHNEIDER', 'PCS', 52000.00, 0.000, 1, 'MU1273'
UNION ALL SELECT 'MU1274', 'MCB BROCO 16A', 'MCB', 'BROCO', 'PCS', 36000.00, 0.000, 1, 'MU1274'
UNION ALL SELECT 'MU1275', 'MCB BROCO 2A', 'MCB', 'BROCO', 'PCS', 36000.00, 0.000, 1, 'MU1275'
UNION ALL SELECT 'MU1276', 'MCB BROCO 20A', 'MCB', 'BROCO', 'PCS', 36800.00, 0.000, 1, 'MU1276'
UNION ALL SELECT 'MU1277', 'MCB BROCO 4A', 'MCB', 'BROCO', 'PCS', 36000.00, 0.000, 1, 'MU1277'
UNION ALL SELECT 'MU1278', 'MCB BROCO 6A', 'MCB', 'BROCO', 'PCS', 36000.00, 0.000, 1, 'MU1278'
UNION ALL SELECT 'MU1279', 'MAKITA M.SKAP 1805N', 'MESIN', 'MAKITA', 'UNIT', 6160000.00, 0.000, 1, 'MU1279'
UNION ALL SELECT 'MU1280', 'MAKITA 5008MG', 'MATA SERKEL', '-', 'SET', 3960000.00, 0.000, 1, 'MU1280'
UNION ALL SELECT 'MU1281', 'MAKTEC M.AMPLAS MT924', 'MESIN', 'MAKTEC', 'UNIT', 832000.00, 0.000, 1, 'MU1281'
UNION ALL SELECT 'MU1282', 'K.PINTU ELBRUS BR-PL-C31-99', 'PINTU', '-', 'SET', 112000.00, 0.000, 1, 'MU1282'
UNION ALL SELECT 'MU1283', 'MAKITA MT M.AMPLAS M9200B', 'MESIN', 'MAKITA MT', 'UNIT', 735000.00, 1.000, 1, 'MU1283'
UNION ALL SELECT 'MU1284', 'MAKITA MT M.PROFIL M3701B', 'MESIN', 'MAKITA MT', 'UNIT', 833000.00, 1.000, 1, 'MU1284'
UNION ALL SELECT 'MU1285', 'MAKITA MATA CILCULAR SAW 7X24T TIPE D', 'MATA SERKEL', '-', 'PCS', 160000.00, 0.000, 1, 'MU1285'
UNION ALL SELECT 'MU1286', 'MAKITA MATA CIRCULER SAW 7X60T TIPE P', 'MATA SERKEL', '-', 'PCS', 131000.00, 0.000, 1, 'MU1286'
UNION ALL SELECT 'MU1287', 'MENI PEDANG 1KG', 'MENIE', 'PEDANG', 'KLG', 39000.00, 0.000, 1, 'MU1287'
UNION ALL SELECT 'MU1288', 'KRAN AMICO 1/2', 'KRAN', 'AMICO', 'PCS', 12916.00, 24.000, 1, 'MU1288'
UNION ALL SELECT 'MU1289', 'SEMPROTAN AIR HDT', 'SEMPROTAN', '-', 'PCS', 16500.00, 7.000, 1, 'MU1289'
UNION ALL SELECT 'MU1290', 'ARIES GOLD 504 20KG', 'CAT', 'ARIES GOLD', 'PAIL', 357700.00, 0.000, 1, 'MU1290'
UNION ALL SELECT 'MU1291', 'KAWAT SOLDER K', 'KAWAT', '-', 'PCS', 76000.00, 0.000, 1, 'MU1291'
UNION ALL SELECT 'MU1292', 'MESIN CUCI MOBIL WIPRO  QH 025', 'MESIN', 'WIPRO', 'UNIT', 2805000.00, 0.000, 1, 'MU1292'
UNION ALL SELECT 'MU1293', 'KOMPOR BAKAR 1,5 LTR', 'KOMPOR', 'KOALA', 'UNIT', 240000.00, 0.000, 1, 'MU1293'
UNION ALL SELECT 'MU1294', 'SERKEL MAKITA MT M5801B', 'MATA SERKEL', 'MAKTEC', 'UNIT', 1625000.00, 1.000, 1, 'MU1294'
UNION ALL SELECT 'MU1295', 'METERAN MULLER 7.5M', 'METERAN', 'MULLER', 'PCS', 45000.00, 0.000, 1, 'MU1295'
UNION ALL SELECT 'MU1296', 'METERAN SOLIGEN 5M', 'METERAN', 'SOLIGEN', 'PCS', 21666.00, 12.000, 1, 'MU1296'
UNION ALL SELECT 'MU1297', 'ELBOW DRAT KUNINGAN 1/2', 'SAMBUNGAN', 'JAYA', 'PCS', 10250.00, 0.000, 1, 'MU1297'
UNION ALL SELECT 'MU1298', 'MAXEL PLUS GEL X6', 'MATA SERKEL', '-', 'LBR', 44000.00, 0.000, 1, 'MU1298'
UNION ALL SELECT 'MU1299', 'PLASTIK PAGAR GRAFIR BAMBU/GARDENIA', 'PLASTIK PAGAR', '-', 'MTR', 46008.00, 0.000, 1, 'MU1299'
UNION ALL SELECT 'MU1300', 'EPOXY RESIN&HARDENER 1KG', 'PEL LANTAI', 'Avian', 'SET', 134400.00, 0.000, 1, 'MU1300'
UNION ALL SELECT 'MU1301', 'SUZUKA CLEAR VERNIS 222', 'VERNIS', 'SUZUKA', 'KLG', 55200.00, 0.000, 1, 'MU1301'
UNION ALL SELECT 'MU1302', 'LEM SILICON WHITE  ACETIC 300 GR', 'LEM', 'TAIYO', 'BTL', 20900.00, 6.000, 1, 'MU1302'
UNION ALL SELECT 'MU1303', 'KUNCI PINTU 278M-NB/CP SANSHAN', 'KUNCI PINTU', 'SANSHAN', 'SET', 97000.00, 2.000, 1, 'MU1303'
UNION ALL SELECT 'MU1304', 'LEM FOX 600G', 'LEM', 'FOX', 'KLG', 59500.00, 0.000, 1, 'MU1304'
UNION ALL SELECT 'MU1305', 'DOOR LOCK MUBEO ADLLER', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1305'
UNION ALL SELECT 'MU1306', 'TOPLES MULTIPLAST 6L', 'TOPLES', 'MULTIPLAST', 'PCS', 16000.00, 0.000, 1, 'MU1306'
UNION ALL SELECT 'MU1307', 'LEM RAJAWALI', 'PEL LANTAI', 'RAJAWALI', 'BKS', 21000.00, 0.000, 1, 'MU1307'
UNION ALL SELECT 'MU1308', 'AVIAN SYNTHETIC 732', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU1308'
UNION ALL SELECT 'MU1309', 'KAWAT SOLDER B', 'KAWAT', '-', 'PCS', 149600.00, 0.000, 1, 'MU1309'
UNION ALL SELECT 'MU1310', 'KRAN TEMBOK ARCEL GG 1/2', 'KRAN', 'ARCEL', 'PCS', 20800.00, 0.000, 1, 'MU1310'
UNION ALL SELECT 'MU1311', 'BESI U 80X50X3,0', 'BESI', '-', 'BTG', 345000.00, 0.000, 1, 'MU1311'
UNION ALL SELECT 'MU1312', 'HANDSPRAYER GOLDEN STAR 16L', 'MESIN', 'MATRIX', 'UNIT', 480000.00, 0.000, 1, 'MU1312'
UNION ALL SELECT 'MU1313', 'DEROCCA 007', 'V-BELT', 'DEROCCA', 'SET', 119200.00, 0.000, 1, 'MU1313'
UNION ALL SELECT 'MU1314', 'KAPI GG PVC 4', 'KAPI', 'WANLY', 'PCS', 10000.00, 0.000, 1, 'MU1314'
UNION ALL SELECT 'MU1315', 'GAS PORTABLE TOKAI', 'GAS', 'WONDERFULL', 'PCS', 18000.00, 0.000, 1, 'MU1315'
UNION ALL SELECT 'MU1316', 'MEDITRAN SX 1Ltr', 'OLI', 'MEDITRAN', 'BTL', 66100.00, 0.000, 1, 'MU1316'
UNION ALL SELECT 'MU1317', 'SARINGAN GOT SAKURA', 'MATA SERKEL', 'SAKURA', 'PCS', 7000.00, 0.000, 1, 'MU1317'
UNION ALL SELECT 'MU1318', 'SARINGAN GOT NS ORI', 'SARINGAN', '-', 'PCS', 6000.00, 0.000, 1, 'MU1318'
UNION ALL SELECT 'MU1319', 'SARINGAN LANTAI KM BANO', 'SARINGAN', 'BANO', 'PCS', 60000.00, 0.000, 1, 'MU1319'
UNION ALL SELECT 'MU1320', 'SARINGAN LANTAI KM NISHIO', 'SARINGAN', 'NISHIO', 'PCS', 12000.00, 0.000, 1, 'MU1320'
UNION ALL SELECT 'MU1321', 'LEM PIPA ISARPLAS', 'PEL LANTAI', 'ISARPLAS', 'PCS', 8000.00, 0.000, 1, 'MU1321'
UNION ALL SELECT 'MU1322', 'KRAN TB 1/2 GG', 'KRAN', 'GG', 'PCS', 21600.00, 0.000, 1, 'MU1322'
UNION ALL SELECT 'MU1323', 'SEAL TAPE', 'PEL LANTAI', 'ERNESTO', 'PCS', 1750.00, 60.000, 1, 'MU1323'
UNION ALL SELECT 'MU1324', 'DOOR LOCK BELLACO FE801', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1324'
UNION ALL SELECT 'MU1325', 'KUNCI GT ATS PANJANG 30', 'V-BELT', '-', 'PCS', 15000.00, 0.000, 1, 'MU1325'
UNION ALL SELECT 'MU1326', 'KUNCI GT ATS PANJANG 40', 'V-BELT', '-', 'PCS', 30000.00, 0.000, 1, 'MU1326'
UNION ALL SELECT 'MU1327', 'KUNCI GT ATS PANJANG 50', 'V-BELT', '-', 'PCS', 25000.00, 0.000, 1, 'MU1327'
UNION ALL SELECT 'MU1328', 'KRAN ANGSA ORANGE', 'KRAN', 'GRAVINDO', 'PCS', 75000.00, 0.000, 1, 'MU1328'
UNION ALL SELECT 'MU1329', 'STANG GERGAJI BISON GG KARET', 'GERGAJI', 'BISON', 'PCS', 23750.00, 0.000, 1, 'MU1329'
UNION ALL SELECT 'MU1330', 'KRAN AIR KP 04 3/4', 'KRAN', 'KRANZ', 'PCS', 11200.00, 0.000, 1, 'MU1330'
UNION ALL SELECT 'MU1331', 'MATA GERGAJI BESI SANDFLEX', 'GERGAJI', '-', 'PCS', 13500.00, 12.000, 1, 'MU1331'
UNION ALL SELECT 'MU1332', 'DOOR LOCK BELLACO FE811', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1332'
UNION ALL SELECT 'MU1333', 'CETOK GG KARET 7\'', 'SENDOK SEMEN', 'IGM', 'PCS', 17000.00, 6.000, 1, 'MU1333'
UNION ALL SELECT 'MU1334', 'KINGSTONE SENDOK SEMEN', 'SENDOK SEMEN', 'KINGSTONE', 'PCS', 13600.00, 0.000, 1, 'MU1334'
UNION ALL SELECT 'MU1335', 'TATAH KAYU GG  FIBER 1 1/2\"', 'TATAH', 'CLIPPER', 'PCS', 31250.00, 6.000, 1, 'MU1335'
UNION ALL SELECT 'MU1336', 'ENGSEL HPP GOLD 3\'', 'ENGSEL', 'HPP', 'SET', 8000.00, 0.000, 1, 'MU1336'
UNION ALL SELECT 'MU1337', 'ENGSEL HPP 3\' SILVER', 'ENGSEL', 'HPP', 'SET', 8000.00, 0.000, 1, 'MU1337'
UNION ALL SELECT 'MU1338', 'ENGSEL GP 5\'', 'ENGSEL', 'IGM', 'SET', 15000.00, 24.000, 1, 'MU1338'
UNION ALL SELECT 'MU1339', 'TARIKAN LEMARI ARCEL', 'MATA SERKEL', 'ARCEL', 'PCS', 17600.00, 0.000, 1, 'MU1339'
UNION ALL SELECT 'MU1340', 'DOOR LOCK BELLACO FE814', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1340'
UNION ALL SELECT 'MU1341', 'TARIKAN LACI TYRO CHROME', 'V-BELT', 'TYRO', 'PCS', 8000.00, 0.000, 1, 'MU1341'
UNION ALL SELECT 'MU1342', 'STEKER + SAKLAR BROCO TANAM', 'SAKLAR', 'BROCO', 'PCS', 16800.00, 0.000, 1, 'MU1342'
UNION ALL SELECT 'MU1343', 'TARIKAN LACI GLADIO 929 PUTIH', 'V-BELT', 'GLADIO', 'PCS', 8000.00, 0.000, 1, 'MU1343'
UNION ALL SELECT 'MU1344', 'TARIKAN LACI SHF159', 'TARIKAN LACI', 'SHF', 'PCS', 10400.00, 0.000, 1, 'MU1344'
UNION ALL SELECT 'MU1345', 'TARIKAN LACI TYRO 112', 'V-BELT', 'TYRO', 'PCS', 8000.00, 0.000, 1, 'MU1345'
UNION ALL SELECT 'MU1346', 'TARIKAN LEMARI CHROME', 'TARIKAN LACI', '-', 'PCS', 8000.00, 0.000, 1, 'MU1346'
UNION ALL SELECT 'MU1347', 'TARIKAN LACI SHF 063', 'V-BELT', 'SHF', 'PCS', 8000.00, 0.000, 1, 'MU1347'
UNION ALL SELECT 'MU1348', 'TARIKAN LACI BULAT', 'MATA SERKEL', '-', 'PCS', 4800.00, 0.000, 1, 'MU1348'
UNION ALL SELECT 'MU1349', 'TARIKAN LACI GLADIO 939 PUTIH', 'V-BELT', 'GLADIO', 'PCS', 8000.00, 0.000, 1, 'MU1349'
UNION ALL SELECT 'MU1350', 'TERMINAL KABEL  3L + 5M', 'TERMINALJ', 'PAGODA', 'PCS', 21000.00, 0.000, 1, 'MU1350'
UNION ALL SELECT 'MU1351', 'DOOR LOCK BELLACO FE826', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1351'
UNION ALL SELECT 'MU1352', 'FITING TEMPEL BROCO BULAT 1212', 'FITING', 'BROCO', 'PCS', 10500.00, 0.000, 1, 'MU1352'
UNION ALL SELECT 'MU1353', 'HANGER HOLDER (FITING)', 'FITING', 'ATN', 'PCS', 12000.00, 12.000, 1, 'MU1353'
UNION ALL SELECT 'MU1354', 'FITING TEMPEL BROCO KOTAK 1211', 'FITING', 'BROCO', 'PCS', 10500.00, 0.000, 1, 'MU1354'
UNION ALL SELECT 'MU1355', 'CUNTRA 2P BROCO', 'KEPALA COK', 'BROCO', 'PCS', 3200.00, 0.000, 1, 'MU1355'
UNION ALL SELECT 'MU1356', 'STEKER SAKLAR BROCO', 'STEKER', 'BROCO', 'PCS', 21150.00, 10.000, 1, 'MU1356'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU1357' AS sku, 'OBENG GG KARET GNT 4-' AS name, 'OBENG' AS category_name, 'RAPIT' AS brand_name, 'PCS' AS base_unit_code, 18000.00 AS cost_price, 0.000 AS min_stock, 1 AS is_active, 'MU1357' AS legacy_sku
UNION ALL SELECT 'MU1358', 'TESPEN BB USA', 'OVEN', 'HANDSOME', 'PCS', 12000.00, 0.000, 1, 'MU1358'
UNION ALL SELECT 'MU1359', 'DOOR LOCK BELLACO FE827', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1359'
UNION ALL SELECT 'MU1360', 'TESPEN  OT-01 WIPRO', 'TES PEN', 'WIPRO', 'PCS', 7150.00, 0.000, 1, 'MU1360'
UNION ALL SELECT 'MU1361', 'SAKLAR LEONA PUTIH SINGLE', 'SAKLAR', 'LEONA', 'PCS', 12800.00, 0.000, 1, 'MU1361'
UNION ALL SELECT 'MU1362', 'SAKLAR LEONA PUTIH DOUBLE', 'SAKLAR', 'LEONA', 'PCS', 15200.00, 0.000, 1, 'MU1362'
UNION ALL SELECT 'MU1363', 'STOP KONTAK LEONA', 'STEKER', 'LEONA', 'PCS', 12000.00, 0.000, 1, 'MU1363'
UNION ALL SELECT 'MU1364', 'FITING GANTUNG BROCO HITAM', 'FITING', 'BROCO', 'PCS', 3600.00, 0.000, 1, 'MU1364'
UNION ALL SELECT 'MU1365', 'KEPALA COK  BULAT UTICON', 'KEPALA COK', 'RABBIT', 'PCS', 10377.00, 16.000, 1, 'MU1365'
UNION ALL SELECT 'MU1366', '3 CABANG STEKER BROCO', 'FITING', 'BROCO', 'PCS', 21450.00, 0.000, 1, 'MU1366'
UNION ALL SELECT 'MU1367', 'STEKER 2P FUBA', 'MATA SERKEL', '-', 'PCS', 5000.00, 24.000, 1, 'MU1367'
UNION ALL SELECT 'MU1368', 'KLEM KABEL IMUNDEX 7MM', 'KLEM', 'IMUNDEX', 'DUS', 8800.00, 0.000, 1, 'MU1368'
UNION ALL SELECT 'MU1369', 'DOOR LOCK SOBAR 919-15', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1369'
UNION ALL SELECT 'MU1370', 'KLEM KABEL IMUNDEX 8MM', 'KLEM', 'IMUNDEX', 'DUS', 14150.00, 0.000, 1, 'MU1370'
UNION ALL SELECT 'MU1371', 'KLEM KABEL FRT 9MM', 'KLEM', 'FRT', 'BKS', 4000.00, 24.000, 1, 'MU1371'
UNION ALL SELECT 'MU1372', 'KLEM KABEL FRT 10MM', 'KLEM', 'FRT', 'BKS', 5500.00, 24.000, 1, 'MU1372'
UNION ALL SELECT 'MU1373', 'KLEM KABEL IMUNDEX 12MM', 'KLEM', 'IMUNDEX', 'DUS', 20000.00, 0.000, 1, 'MU1373'
UNION ALL SELECT 'MU1374', 'FITING BULAT MOTIF', 'FITING', '-', 'PCS', 10400.00, 0.000, 1, 'MU1374'
UNION ALL SELECT 'MU1375', 'SHOWER HOSE 1.5M', 'SHOWER', 'HOMESTAR', 'PCS', 41800.00, 0.000, 1, 'MU1375'
UNION ALL SELECT 'MU1376', 'PRIMAG WIN PUTIH', 'MATA SERKEL', '-', 'PAK', 27500.00, 0.000, 1, 'MU1376'
UNION ALL SELECT 'MU1377', 'PANASONIC 72312', 'MATA SERKEL', '-', 'PAK', 45400.00, 0.000, 1, 'MU1377'
UNION ALL SELECT 'MU1378', 'FITING COLOK + SAKLAR WAKAMOTO', 'FITING', 'WAKAMOTO', 'PCS', 4500.00, 0.000, 1, 'MU1378'
UNION ALL SELECT 'MU1379', 'SEAL ALKON 3\' NQWWP20H-0211', 'S/PART', 'NOQIWA', 'PCS', 50000.00, 6.000, 1, 'MU1379'
UNION ALL SELECT 'MU1380', 'DOOR LOCK SOBAR 919-71', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1380'
UNION ALL SELECT 'MU1381', 'PROFILA 88 TANK 1200', 'MATA SERKEL', '-', 'PAK', 1700000.00, 0.000, 1, 'MU1381'
UNION ALL SELECT 'MU1382', 'SOCK 3/4', 'SAMBUNGAN', 'QUAD', 'PCS', 1070.00, 9.000, 1, 'MU1382'
UNION ALL SELECT 'MU1383', 'DENGKLEK GM', 'KURSI', '-', 'PCS', 10000.00, 0.000, 1, 'MU1383'
UNION ALL SELECT 'MU1384', 'DOOR LOCK WAR CX5', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1384'
UNION ALL SELECT 'MU1385', 'CONTRA BROCO', 'STEKER', 'BROCO', 'PCS', 10178.00, 0.000, 1, 'MU1385'
UNION ALL SELECT 'MU1386', 'T AC 2 KAKI FUBA', 'MATA SERKEL', '-', 'PCS', 9000.00, 0.000, 1, 'MU1386'
UNION ALL SELECT 'MU1388', 'T AC 3 KAKI FUBA', 'MATA SERKEL', '-', 'PCS', 10000.00, 0.000, 1, 'MU1388'
UNION ALL SELECT 'MU1389', 'DOOR LOCK WAR CX3', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1389'
UNION ALL SELECT 'MU1390', 'ELBOW 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 2113.00, 0.000, 1, 'MU1390'
UNION ALL SELECT 'MU1391', 'SOCK DRAT 3/4', 'SAMBUNGAN', 'POWER', 'PCS', 2084.00, 9.000, 1, 'MU1391'
UNION ALL SELECT 'MU1392', 'KIKIR BAHCO 4 100MM', 'KIKIR', 'BAHCO', 'PCS', 32000.00, 0.000, 1, 'MU1392'
UNION ALL SELECT 'MU1393', 'TEE AW 1/2 INCI', 'SAMBUNGAN', 'POWER', 'PCS', 2500.00, 0.000, 1, 'MU1393'
UNION ALL SELECT 'MU1394', 'ELBOW 3/4', 'SAMBUNGAN', 'POWER', 'PCS', 2741.00, 9.000, 1, 'MU1394'
UNION ALL SELECT 'MU1395', 'SOCK 5', 'SAMBUNGAN', 'POWER', 'PCS', 38890.00, 0.000, 1, 'MU1395'
UNION ALL SELECT 'MU1396', 'SOCK KRAN 3/4', 'SAMBUNGAN', 'POWER', 'PCS', 2350.00, 0.000, 1, 'MU1396'
UNION ALL SELECT 'MU1397', 'TEE 3/4', 'SAMBUNGAN', 'POWER', 'PCS', 3734.00, 9.000, 1, 'MU1397'
UNION ALL SELECT 'MU1398', 'DOOR LOCK WAR CX1', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1398'
UNION ALL SELECT 'MU1399', 'ELBOW 1', 'SAMBUNGAN', 'POWER', 'PCS', 4620.00, 0.000, 1, 'MU1399'
UNION ALL SELECT 'MU1400', 'TEMPAT MINYAK TANAH 22 SUMBU', 'TEMPAT', 'HOCK', 'PCS', 42300.00, 3.000, 1, 'MU1400'
UNION ALL SELECT 'MU1401', 'ELBOW 2', 'SAMBUNGAN', 'POWER', 'PCS', 8090.00, 0.000, 1, 'MU1401'
UNION ALL SELECT 'MU1402', '3/4X6 SKRUP GYPSUM HITAM MAJESTY (750 PCS)', 'SKRUP', 'MAJESTY', 'DUS', 110000.00, 0.000, 1, 'MU1402'
UNION ALL SELECT 'MU1403', 'TEE 1 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 7200.00, 0.000, 1, 'MU1403'
UNION ALL SELECT 'MU1404', 'OVER SOCK 2X1 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 5070.00, 0.000, 1, 'MU1404'
UNION ALL SELECT 'MU1405', 'ELBOW 1 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 4880.00, 0.000, 1, 'MU1405'
UNION ALL SELECT 'MU1406', 'SOCK 2', 'SAMBUNGAN', 'POWER', 'PCS', 4610.00, 0.000, 1, 'MU1406'
UNION ALL SELECT 'MU1407', 'KENI 1/2 X 3/4 KUNINGAN', 'SAMBUNGAN', 'POWER', 'PCS', 12000.00, 0.000, 1, 'MU1407'
UNION ALL SELECT 'MU1408', 'ELBOW 3', 'SAMBUNGAN', 'POWER', 'PCS', 18820.00, 20.000, 1, 'MU1408'
UNION ALL SELECT 'MU1409', 'SOCK 3', 'SAMBUNGAN', 'POWER', 'PCS', 11540.00, 9.000, 1, 'MU1409'
UNION ALL SELECT 'MU1410', 'ELBOW KUNINGAN 3/4', 'SAMBUNGAN', 'POWER', 'PCS', 14834.00, 0.000, 1, 'MU1410'
UNION ALL SELECT 'MU1411', 'ELBOW 4', 'SAMBUNGAN', 'POWER', 'PCS', 37370.00, 6.000, 1, 'MU1411'
UNION ALL SELECT 'MU1412', 'SOCK 4', 'SAMBUNGAN', 'POWER', 'PCS', 20690.00, 0.000, 1, 'MU1412'
UNION ALL SELECT 'MU1413', 'TEE 4', 'SAMBUNGAN', 'POWER', 'PCS', 49190.00, 0.000, 1, 'MU1413'
UNION ALL SELECT 'MU1414', 'GRENDEL ETB-201-4', 'GRENDEL', 'EPIC', 'PCS', 15200.00, 0.000, 1, 'MU1414'
UNION ALL SELECT 'MU1415', 'DOOR LOCK YSK HARDWARES YS04', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1415'
UNION ALL SELECT 'MU1416', 'ARCEL LOCK SILINDER', 'V-BELT', 'ARCEL', 'PCS', 36000.00, 0.000, 1, 'MU1416'
UNION ALL SELECT 'MU1417', 'MAS LOCK SILINDER', 'V-BELT', 'MAS', 'PCS', 27200.00, 0.000, 1, 'MU1417'
UNION ALL SELECT 'MU1418', 'ANTI SUMBAT MR. KERAT', 'CAIRAN', '-', 'PCS', 35000.00, 7.000, 1, 'MU1418'
UNION ALL SELECT 'MU1419', 'CARRERA WASH & GLOSS 400ML', 'MATA SERKEL', '-', 'PCS', 4690.00, 0.000, 1, 'MU1419'
UNION ALL SELECT 'MU1420', 'CARRERA WASH & GROSS 800ML', 'MATA SERKEL', '-', 'PCS', 7116.00, 0.000, 1, 'MU1420'
UNION ALL SELECT 'MU1421', 'SAN POLY', 'CAT', '-', 'KLG', 17000.00, 0.000, 1, 'MU1421'
UNION ALL SELECT 'MU1422', 'CARRERA PENGKILAP BAN 200ML', 'MATA SERKEL', '-', 'PCS', 8697.00, 0.000, 1, 'MU1422'
UNION ALL SELECT 'MU1423', 'CARRERA SABUT SPON', 'MATA SERKEL', '-', 'PCS', 9954.00, 0.000, 1, 'MU1423'
UNION ALL SELECT 'MU1424', 'CARRERA SPON SERBA GUNA', 'ALAT', 'CARRER', 'PCS', 7898.00, 0.000, 1, 'MU1424'
UNION ALL SELECT 'MU1425', 'CARRERA RADIATOR COOLANT 5L', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1425'
UNION ALL SELECT 'MU1426', 'KLINPAK CLING WRAP REFFIL 30X30', 'MATA SERKEL', '-', 'PCS', 11815.00, 0.000, 1, 'MU1426'
UNION ALL SELECT 'MU1427', 'KLINPAK REFFIL CLING WARP JUMBO', 'MATA SERKEL', '-', 'PCS', 15730.00, 0.000, 1, 'MU1427'
UNION ALL SELECT 'MU1428', 'SARINGAN PASANG 22 SUMBU KOMPOR HOCK', 'SARINGAN', 'HOCK', 'PCS', 36150.00, 4.000, 1, 'MU1428'
UNION ALL SELECT 'MU1429', 'KUNCI LACI HUBEN', 'Kunci', '-', 'PCS', 0.00, 5.000, 1, 'MU1429'
UNION ALL SELECT 'MU1430', 'SARINGAN PASANG 16 SUMBU KOMPOR HOCK', 'SARINGAN', 'HOCK', 'PCS', 21320.00, 2.000, 1, 'MU1430'
UNION ALL SELECT 'MU1431', 'SARINGAN DALAM 16 SUMBU KOMPOR HOCK', 'SARINGAN', 'HOCK', 'PCS', 15000.00, 4.000, 1, 'MU1431'
UNION ALL SELECT 'MU1432', 'SARINGAN DALAM 22 SUMBU KOMPOR HOCK', 'SARINGAN', 'HOCK', 'PCS', 22500.00, 4.000, 1, 'MU1432'
UNION ALL SELECT 'MU1433', 'LEM LALAT NEW', 'MATA SERKEL', '-', 'PCS', 5398.00, 0.000, 1, 'MU1433'
UNION ALL SELECT 'MU1434', 'OVER SOCK 3X1 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 10040.00, 0.000, 1, 'MU1434'
UNION ALL SELECT 'MU1435', 'OVER SOCK 6X4', 'SAMBUNGAN', 'POWER', 'PCS', 58950.00, 0.000, 1, 'MU1435'
UNION ALL SELECT 'MU1436', 'OVER SOCK 4X3', 'SAMBUNGAN', 'POWER', 'PCS', 21040.00, 0.000, 1, 'MU1436'
UNION ALL SELECT 'MU1437', 'TOP DRAWENER LOCK', 'V-BELT', '-', 'PCS', 0.00, 0.000, 1, 'MU1437'
UNION ALL SELECT 'MU1438', 'AUTOSOL', 'PEL LANTAI', 'AUTOSOL', 'PCS', 16000.00, 0.000, 1, 'MU1438'
UNION ALL SELECT 'MU1439', 'GRT DRAWNER LOCK', 'V-BELT', 'GRT', 'PCS', 4000.00, 0.000, 1, 'MU1439'
UNION ALL SELECT 'MU1440', 'TATAH KAYU GG  FIBER 1\"', 'TATAH', 'CLIPPER', 'PCS', 24583.00, 6.000, 1, 'MU1440'
UNION ALL SELECT 'MU1441', 'OVER SOCK 3X2 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 10830.00, 0.000, 1, 'MU1441'
UNION ALL SELECT 'MU1442', 'BOSCH 550W 13MM 2800RPM', 'MATA SERKEL', '-', 'PCS', 505.00, 0.000, 1, 'MU1442'
UNION ALL SELECT 'MU1443', 'FLAP DISC A100', 'AMPLAS', 'WIPRO', 'PCS', 10400.00, 12.000, 1, 'MU1443'
UNION ALL SELECT 'MU1444', 'FLAP DISC A120', 'AMPLAS', 'WIPRO', 'PCS', 10400.00, 0.000, 1, 'MU1444'
UNION ALL SELECT 'MU1445', 'OVER SOCK 3X2', 'SAMBUNGAN', 'POWER', 'PCS', 10760.00, 0.000, 1, 'MU1445'
UNION ALL SELECT 'MU1446', 'INNOPLAST MASTER BAMBO CLEAR 40M', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1446'
UNION ALL SELECT 'MU1447', 'INNOPLAST BAMBU WARNA ECO 6X50M', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1447'
UNION ALL SELECT 'MU1448', 'INNOPLAST TULIP PASIR 5/50M', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1448'
UNION ALL SELECT 'MU1449', 'FLEXILON STRIPLINE 0.8MMX1MX40M', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1449'
UNION ALL SELECT 'MU1450', 'LOCK BODY MDN KUNCI PELOR', 'KUNCI PINTU', 'MDN', 'SET', 19573.00, 0.000, 1, 'MU1450'
UNION ALL SELECT 'MU1451', 'ROCKHAS NYAMUK PLASTIK CREAM', 'JARING', '-', 'MTR', 16000.00, 0.000, 1, 'MU1451'
UNION ALL SELECT 'MU1452', 'KAITAN HORDEN', 'MATA SERKEL', '-', 'PCS', 8000.00, 0.000, 1, 'MU1452'
UNION ALL SELECT 'MU1453', 'KYK BACKING PAD 100XM10', 'MATA SERKEL', '-', 'PCS', 8000.00, 0.000, 1, 'MU1453'
UNION ALL SELECT 'MU1454', 'ARCEL PULL HANDEL', 'ACCU', '-', 'PCS', 0.00, 0.000, 1, 'MU1454'
UNION ALL SELECT 'MU1455', 'OVER SOCK 4X2 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 19980.00, 0.000, 1, 'MU1455'
UNION ALL SELECT 'MU1456', 'MESRAN SUPER 20W-50', 'OLI', 'MESRAN', 'BTL', 53300.00, 0.000, 1, 'MU1456'
UNION ALL SELECT 'MU1457', 'YAMALUBE SPORT OIL', 'OLI', 'YAMALUBE', 'BTL', 38400.00, 0.000, 1, 'MU1457'
UNION ALL SELECT 'MU1458', 'YAMALUBE GEAR OIL MATIC', 'OLI', 'YAMALUBE', 'BTL', 10400.00, 0.000, 1, 'MU1458'
UNION ALL SELECT 'MU1459', 'MEDITRAN S OIL SAE 40 1L', 'OLI', 'MEDITRAN', 'BTL', 57500.00, 0.000, 1, 'MU1459'
UNION ALL SELECT 'MU1460', 'MEDITRAN S OIL SAE 40 5L', 'OLI', 'MEDITRAN', 'BTL', 260750.00, 0.000, 1, 'MU1460'
UNION ALL SELECT 'MU1461', 'MESRAN OIL SAE 40 1L', 'OLI', 'MESRAN', 'BTL', 52500.00, 0.000, 1, 'MU1461'
UNION ALL SELECT 'MU1462', 'MESRANIA OIL SAE 20 2T', 'OLI', 'MESRAN', 'BTL', 58700.00, 0.000, 1, 'MU1462'
UNION ALL SELECT 'MU1463', 'FEDERAL OIL AT 1L', 'OLI', 'FEDERAL', 'PCS', 28000.00, 0.000, 1, 'MU1463'
UNION ALL SELECT 'MU1464', 'IRC 2.25/2.50-18', 'BAN', 'IRC', 'PCS', 22000.00, 0.000, 1, 'MU1464'
UNION ALL SELECT 'MU1465', 'EPIC 9286 HITAM', 'TARIKAN PINTU', 'EPIC', 'SET', 108000.00, 0.000, 1, 'MU1465'
UNION ALL SELECT 'MU1466', 'DRAIN PLUG IMTWP15-0206', 'S/PART', 'NOQIWA', 'PCS', 18108.00, 6.000, 1, 'MU1466'
UNION ALL SELECT 'MU1467', 'AS RODA ARTCO', 'S/PART', 'ARTCO', 'PCS', 28000.00, 0.000, 1, 'MU1467'
UNION ALL SELECT 'MU1468', 'IRC 80/90-16', 'BAN', 'IRC', 'PCS', 20000.00, 0.000, 1, 'MU1468'
UNION ALL SELECT 'MU1469', 'IRC 2.75/3.00-18', 'BAN', 'IRC', 'PCS', 20000.00, 0.000, 1, 'MU1469'
UNION ALL SELECT 'MU1470', 'MIZZLE 50/90', 'BAN', '-', 'PCS', 20000.00, 0.000, 1, 'MU1470'
UNION ALL SELECT 'MU1471', 'ARS PULL HANDLE', 'ACCU', '-', 'PCS', 0.00, 0.000, 1, 'MU1471'
UNION ALL SELECT 'MU1472', 'CLOSET JONGKOK STANDARD LEXUS', 'CLOSET', '-', 'UNIT', 105000.00, 5.000, 1, 'MU1472'
UNION ALL SELECT 'MU1473', 'CLOSET JONGKOK LURUS BIASA', 'CLOSET', '-', 'UNIT', 115000.00, 5.000, 1, 'MU1473'
UNION ALL SELECT 'MU1474', 'CLOSET JONGKOK LURUS DUTY BEIGE, GREEN', 'CLOSET', '-', 'UNIT', 180000.00, 5.000, 1, 'MU1474'
UNION ALL SELECT 'MU1475', 'VELCRO AMPLAS ALDO P80', 'AMPLAS', '-', 'PCS', 1750.00, 0.000, 1, 'MU1475'
UNION ALL SELECT 'MU1476', 'VELCRO SANDING DISC 4 INCH', 'MATA SERKEL', '-', 'PCS', 1750.00, 0.000, 1, 'MU1476'
UNION ALL SELECT 'MU1477', 'STAR LIST CROM', 'TARIKAN PINTU', 'JAYA MULIA', 'SET', 96000.00, 0.000, 1, 'MU1477'
UNION ALL SELECT 'MU1478', 'MULLER BLACK/CP STAR', 'TARIKAN PINTU', 'MULLER', 'PCS', 144000.00, 0.000, 1, 'MU1478'
UNION ALL SELECT 'MU1479', 'KRAN HQ PINK 1/2', 'KRAN', 'KRANZ', 'PCS', 5594.00, 0.000, 1, 'MU1479'
UNION ALL SELECT 'MU1480', 'KRAN HQ PINK 3/4', 'KRAN', '-', 'PCS', 5874.00, 0.000, 1, 'MU1480'
UNION ALL SELECT 'MU1481', 'KRAN HQ HITAM 1/2', 'KRAN', 'KRANZ', 'PCS', 13151.00, 0.000, 1, 'MU1481'
UNION ALL SELECT 'MU1482', 'YUASA PAFECTA N200 12A-200Ah (KOSONGAN)', 'ACCU', 'YUASA', 'UNIT', 3136350.00, 0.000, 1, 'MU1482'
UNION ALL SELECT 'MU1483', 'YUASA PAFECTA N100 12A-100Ah (KOSONGAN)', 'ACCU', 'YUASA', 'UNIT', 1505100.00, 0.000, 1, 'MU1483'
UNION ALL SELECT 'MU1484', 'AIR AKI TAMBAH 1LTR', 'ACCU', 'YUASA', 'PCS', 8100.00, 6.000, 1, 'MU1484'
UNION ALL SELECT 'MU1485', 'AIR ACCU ZUUR 5LTR', 'ACCU', 'YUASA', 'GEN', 40000.00, 0.000, 1, 'MU1485'
UNION ALL SELECT 'MU1486', 'AIR AKI ZUUR 1LTR', 'ACCU', 'YUASA', 'BTL', 20000.00, 6.000, 1, 'MU1486'
UNION ALL SELECT 'MU1487', 'BOSSINI', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1487'
UNION ALL SELECT 'MU1488', 'BELOCCA CORE', 'V-BELT', 'BELOCCA', 'PCS', 43200.00, 0.000, 1, 'MU1488'
UNION ALL SELECT 'MU1489', 'DENGKLEK LUCKY STAR', 'KURSI', 'LUCKY STAR', 'PCS', 30000.00, 0.000, 1, 'MU1489'
UNION ALL SELECT 'MU1490', 'DOOR STOP MAGNET PION ARCEL 318', 'AKSESORIS', 'ARCEL', 'PCS', 34000.00, 0.000, 1, 'MU1490'
UNION ALL SELECT 'MU1491', 'BELOCCA KEY RING', 'V-BELT', 'BELOCCA', 'PCS', 31200.00, 0.000, 1, 'MU1491'
UNION ALL SELECT 'MU1492', 'WEITELANG 4', 'OVERVAL', 'WEITELANG', 'PCS', 48000.00, 0.000, 1, 'MU1492'
UNION ALL SELECT 'MU1493', 'GOLD PB123 PILLOX', 'PILLOX', 'NIPPON PAINT', 'PCS', 24831.00, 5.000, 1, 'MU1493'
UNION ALL SELECT 'MU1494', 'YFL SERIES 6', 'V-BELT', '-', 'PCS', 0.00, 0.000, 1, 'MU1494'
UNION ALL SELECT 'MU1495', 'YFL SERIES 4', 'V-BELT', '-', 'PCS', 0.00, 0.000, 1, 'MU1495'
UNION ALL SELECT 'MU1496', 'TUTUP CYLINER PINTU ARCEL 8503', 'AKSESORIS', 'ARCEL', 'PCS', 45000.00, 0.000, 1, 'MU1496'
UNION ALL SELECT 'MU1497', 'KUNCI BESI 8', 'ALAT', '-', 'PCS', 18000.00, 0.000, 1, 'MU1497'
UNION ALL SELECT 'MU1498', 'KUNCI BESI 10', 'ALAT', '-', 'PCS', 18000.00, 0.000, 1, 'MU1498'
UNION ALL SELECT 'MU1499', 'KUNCI BESI 12', 'ALAT', '-', 'PCS', 18000.00, 0.000, 1, 'MU1499'
UNION ALL SELECT 'MU1500', 'HAK ANGIN BIASA JM', 'HAK ANGIN', 'JAYA MULIA', 'SET', 3333.00, 0.000, 1, 'MU1500'
UNION ALL SELECT 'MU1501', 'HAK ANGIN BROWN KOTAK', 'V-BELT', '-', 'PCS', 6400.00, 0.000, 1, 'MU1501'
UNION ALL SELECT 'MU1502', 'KEN MASTER KUNCI L', 'V-BELT', 'KEN MASTER', 'PCS', 9600.00, 0.000, 1, 'MU1502'
UNION ALL SELECT 'MU1503', 'GRANDEL OVERFALL 3', 'V-BELT', '-', 'PCS', 0.00, 0.000, 1, 'MU1503'
UNION ALL SELECT 'MU1504', 'SUZUKA CAT S480 200CC', 'PAKING', 'SUZUKA', 'PCS', 20000.00, 6.000, 1, 'MU1504'
UNION ALL SELECT 'MU1505', 'GRANDEL OVERFALL 2', 'V-BELT', '-', 'PCS', 0.00, 0.000, 1, 'MU1505'
UNION ALL SELECT 'MU1506', 'KEPALA KOMPOR SUMBU 30', 'SUMBU', 'HOCK', 'PCS', 190000.00, 4.000, 1, 'MU1506'
UNION ALL SELECT 'MU1507', 'ATS HAND RIVETER', 'TANG', 'ATS', 'PCS', 40000.00, 0.000, 1, 'MU1507'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU1508' AS sku, 'BOYO PLAMIR TEMBOK 25KG' AS name, 'PLAMIR' AS category_name, 'BOYO' AS brand_name, 'PAIL' AS base_unit_code, 275200.00 AS cost_price, 0.000 AS min_stock, 1 AS is_active, 'MU1508' AS legacy_sku
UNION ALL SELECT 'MU1509', 'BOYO PLAMIR TEMBOK 5KG', 'PLAMIR', 'BOYO', 'POT', 65400.00, 0.000, 1, 'MU1509'
UNION ALL SELECT 'MU1510', 'TALI STATER M.BABAT', 'TALI', 'MIYAMOTO', 'PCS', 15000.00, 0.000, 1, 'MU1510'
UNION ALL SELECT 'MU1511', 'ELASTEX WATER BLUE', 'CAT', 'NIPPON PAINT', 'KLG', 254000.00, 0.000, 1, 'MU1511'
UNION ALL SELECT 'MU1512', 'LEM TEMBAK KECIL', 'PEL LANTAI', '-', 'PCS', 4000.00, 0.000, 1, 'MU1512'
UNION ALL SELECT 'MU1513', 'MULLER LOCK BODY', 'Kunci', 'MULLER', 'PCS', 0.00, 0.000, 1, 'MU1513'
UNION ALL SELECT 'MU1514', 'ELBOW TALANG', 'SAMBUNGAN', 'POWER', 'PCS', 24230.00, 0.000, 1, 'MU1514'
UNION ALL SELECT 'MU1515', 'GAYUNG TANAYA', 'GAYUNG', '-', 'PCS', 8000.00, 0.000, 1, 'MU1515'
UNION ALL SELECT 'MU1516', 'KUAS LUKIS 2', 'Kuas', '-', 'PCS', 1916.00, 12.000, 1, 'MU1516'
UNION ALL SELECT 'MU1517', 'KUAS LUKIS 10', 'Kuas', '-', 'PCS', 2916.00, 12.000, 1, 'MU1517'
UNION ALL SELECT 'MU1518', 'TUTUP GORDEN GOLD', 'AKSESORIS', '-', 'PCS', 8000.00, 0.000, 1, 'MU1518'
UNION ALL SELECT 'MU1519', 'JET SHOWER SET', 'SHOWER', 'HOMESTAR', 'SET', 81400.00, 0.000, 1, 'MU1519'
UNION ALL SELECT 'MU1520', 'BUE ICE (ACE)', 'KIPAS', '-', 'UNIT', 1200000.00, 0.000, 1, 'MU1520'
UNION ALL SELECT 'MU1521', 'KUAS LUKIS 4', 'Kuas', 'IGUANA', 'PCS', 2083.00, 12.000, 1, 'MU1521'
UNION ALL SELECT 'MU1522', 'SEROK BAMBU 35', 'SEROK', '-', 'PCS', 60000.00, 0.000, 1, 'MU1522'
UNION ALL SELECT 'MU1523', 'ARIES 625 5KG', 'CAT', 'ARIES', 'POT', 60400.00, 0.000, 1, 'MU1523'
UNION ALL SELECT 'MU1524', 'MULLER 68206', 'V-BELT', 'MULLER', 'SET', 224000.00, 0.000, 1, 'MU1524'
UNION ALL SELECT 'MU1525', 'SEROK BAMBU 30', 'SEROK', '-', 'PCS', 56000.00, 0.000, 1, 'MU1525'
UNION ALL SELECT 'MU1526', 'AVIAN SYNTHETIC SB', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU1526'
UNION ALL SELECT 'MU1527', 'NIPPE 2000 310', 'CAT', 'NIPPON PAINT', 'KLG', 62400.00, 0.000, 1, 'MU1527'
UNION ALL SELECT 'MU1528', 'AVIAN SYNTHETIC 752', 'CAT', 'Avian', 'KLG', 85000.00, 0.000, 1, 'MU1528'
UNION ALL SELECT 'MU1529', 'ESKOM TANGGUNG', 'ESKOM', 'MERCURY', 'PCS', 13600.00, 0.000, 1, 'MU1529'
UNION ALL SELECT 'MU1530', 'GPUTIH 50PDK', 'Kunci', 'GRT', 'PCS', 20000.00, 5.000, 1, 'MU1530'
UNION ALL SELECT 'MU1532', 'GPUTIH 40PDK', 'Kunci', 'POPEYE', 'PCS', 16000.00, 5.000, 1, 'MU1532'
UNION ALL SELECT 'MU1533', 'SEROK BAMBU 25', 'SEROK', '-', 'PCS', 52000.00, 0.000, 1, 'MU1533'
UNION ALL SELECT 'MU1534', 'SEROK BAMBU 20', 'SEROK', '-', 'PCS', 60000.00, 0.000, 1, 'MU1534'
UNION ALL SELECT 'MU1535', 'PLYWOOD SM 90X1220X2440', 'PLYWOOD', '-', 'LBR', 102500.00, 0.000, 1, 'MU1535'
UNION ALL SELECT 'MU1536', 'PLYWOOD SM 48X1220X2440', 'PLYWOOD', '-', 'LBR', 68500.00, 0.000, 1, 'MU1536'
UNION ALL SELECT 'MU1537', 'MATA BOR BESI 12MM', 'MATA BOR', 'WIPRO', 'PCS', 88000.00, 0.000, 1, 'MU1537'
UNION ALL SELECT 'MU1538', 'MATA BOR BESI 11MM', 'MATA BOR', 'WIPRO', 'PCS', 71500.00, 10.000, 1, 'MU1538'
UNION ALL SELECT 'MU1539', 'MATA BOR BESI 10MM', 'MATA BOR', 'WIPRO', 'PCS', 53350.00, 10.000, 1, 'MU1539'
UNION ALL SELECT 'MU1540', 'FLAP DISC A40', 'AMPLAS', 'WIPRO', 'PCS', 11200.00, 12.000, 1, 'MU1540'
UNION ALL SELECT 'MU1541', 'NIPPE 2000 435', 'CAT', 'NIPPON PAINT', 'KLG', 62400.00, 0.000, 1, 'MU1541'
UNION ALL SELECT 'MU1542', 'NIPPE 2000 480', 'CAT', 'NIPPON PAINT', 'KLG', 62400.00, 0.000, 1, 'MU1542'
UNION ALL SELECT 'MU1543', 'NIPPE 2000 403-T', 'CAT', 'NIPPON PAINT', 'KLG', 62400.00, 0.000, 1, 'MU1543'
UNION ALL SELECT 'MU1544', 'FOOTKLEP PVC 1 JUMBO PUTIH', 'PIPA', '-', 'PCS', 9000.00, 0.000, 1, 'MU1544'
UNION ALL SELECT 'MU1545', 'PLASTIK PAGAR BIRU 3D', 'PLASTIK PAGAR', '-', 'MTR', 59752.00, 0.000, 1, 'MU1545'
UNION ALL SELECT 'MU1546', 'BLACK METALIC PBM02', 'PILLOX', 'NIPPON PAINT', 'PCS', 23200.00, 0.000, 1, 'MU1546'
UNION ALL SELECT 'MU1547', 'GREEN PB110 PILLOX', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 0.000, 1, 'MU1547'
UNION ALL SELECT 'MU1548', 'PLASTIK PAGAR MOTIF WARNA', 'PLASTIK PAGAR', '-', 'MTR', 59752.00, 0.000, 1, 'MU1548'
UNION ALL SELECT 'MU1549', 'TIKAR LANTAI KECIL', 'TIKAR', '-', 'LBR', 36000.00, 0.000, 1, 'MU1549'
UNION ALL SELECT 'MU1550', 'FLAP DISC A80', 'AMPLAS', 'WIPRO', 'PCS', 10400.00, 0.000, 1, 'MU1550'
UNION ALL SELECT 'MU1551', 'FLAP DISC A180', 'AMPLAS', 'WIPRO', 'PCS', 10400.00, 0.000, 1, 'MU1551'
UNION ALL SELECT 'MU1552', 'FLAP DISC A320', 'AMPLAS', 'WIPRO', 'PCS', 10400.00, 0.000, 1, 'MU1552'
UNION ALL SELECT 'MU1553', 'PERKASA GEL MERAH MARON', 'MATA SERKEL', '-', 'PCS', 46000.00, 0.000, 1, 'MU1553'
UNION ALL SELECT 'MU1554', 'GOLD COIN 4', 'MATA SERKEL', '-', 'PCS', 11000.00, 0.000, 1, 'MU1554'
UNION ALL SELECT 'MU1555', 'AVIAN SYNTHETIC 750', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU1555'
UNION ALL SELECT 'MU1556', 'FLAP DISC A400', 'AMPLAS', 'WIPRO', 'PCS', 10400.00, 0.000, 1, 'MU1556'
UNION ALL SELECT 'MU1557', 'AVIAN SYNTHETIC 192', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU1557'
UNION ALL SELECT 'MU1558', 'AVIAN SYNTHETIC 309', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU1558'
UNION ALL SELECT 'MU1559', 'AVIAN SYNTHETIC 331', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU1559'
UNION ALL SELECT 'MU1560', 'DINABOLT 12X75', 'BAUT', '-', 'PCS', 6400.00, 0.000, 1, 'MU1560'
UNION ALL SELECT 'MU1561', 'KUNCI L BINTANG 9 PCS TS219', 'Kunci', 'WIPRO', 'SET', 85000.00, 0.000, 1, 'MU1561'
UNION ALL SELECT 'MU1562', 'AMPLAS GRIT 2000', 'AMPLAS', 'TAIYO', 'LBR', 2432.00, 0.000, 1, 'MU1562'
UNION ALL SELECT 'MU1563', 'AURORA YELLOWISH', 'TEHEL', 'XENIA', 'DUS', 63200.00, 0.000, 1, 'MU1563'
UNION ALL SELECT 'MU1564', 'NOZZLE MESIN HANDSPRAYER MERAH', 'S/PART', 'NOQIWA', 'PCS', 41750.00, 0.000, 1, 'MU1564'
UNION ALL SELECT 'MU1565', 'MATA GURINDA POTONG WD', 'MATA GURINDA', 'WD', 'PCS', 3300.00, 0.000, 1, 'MU1565'
UNION ALL SELECT 'MU1566', 'MATA GURINDA POTONG NIPPON RESIBOND', 'MATA GURINDA', 'Nippon', 'PCS', 24500.00, 0.000, 1, 'MU1566'
UNION ALL SELECT 'MU1567', 'HANDLE MESIN SEMPROT RED HANDLE', 'S/PART', 'NOQIWA', 'PCS', 14195.00, 12.000, 1, 'MU1567'
UNION ALL SELECT 'MU1568', 'STOP KRAN MESIN HANSPRAYER BALL COCK', 'S/PART', 'NOQIWA', 'PCS', 21935.00, 12.000, 1, 'MU1568'
UNION ALL SELECT 'MU1569', 'TERMINAL 2 LB MT-552', 'TERMINAL COK', 'MATSUI', 'PCS', 10826.00, 12.000, 1, 'MU1569'
UNION ALL SELECT 'MU1570', 'SENTER KEPALA 200W', 'AKSESORIS', '-', 'UNIT', 157500.00, 3.000, 1, 'MU1570'
UNION ALL SELECT 'MU1571', 'SAKLAR DOBEL HTM TEMPEL', 'SAKLAR', 'BROCO', 'PCS', 12000.00, 0.000, 1, 'MU1571'
UNION ALL SELECT 'MU1572', 'TERMINAL COK 6 L BROCO', 'TERMINAL COK', 'BROCO', 'PCS', 57698.00, 0.000, 1, 'MU1572'
UNION ALL SELECT 'MU1573', 'HANDSPRAYER MURAKI 15L', 'SPRAYER', 'MURAKI', 'UNIT', 1020333.00, 0.000, 1, 'MU1573'
UNION ALL SELECT 'MU1574', 'METERAN 3M SOLIGEN', 'METERAN', 'SOLIGEN', 'PCS', 15000.00, 12.000, 1, 'MU1574'
UNION ALL SELECT 'MU1575', 'TANG CATUT MULLER', 'TANG', 'MULLER', 'PCS', 30000.00, 0.000, 1, 'MU1575'
UNION ALL SELECT 'MU1576', 'KRAN ANGSA BIRU', 'KRAN', 'ANGSA', 'PCS', 116000.00, 0.000, 1, 'MU1576'
UNION ALL SELECT 'MU1577', 'ARIES GOLD 506 20KG', 'CAT', 'ACCURA', 'PAIL', 324090.00, 0.000, 1, 'MU1577'
UNION ALL SELECT 'MU1578', 'KRAN ANGSA HITAM', 'KRAN', 'ANGSA', 'PCS', 116000.00, 0.000, 1, 'MU1578'
UNION ALL SELECT 'MU1579', 'Gantungan baju pentol Bano', 'GANTUNGAN', 'BANO', 'PCS', 40000.00, 0.000, 1, 'MU1579'
UNION ALL SELECT 'MU1580', 'MATA GURINDA GOSOK NIPPON RESIBOND', 'MATA GURINDA', 'Nippon', 'PCS', 13250.00, 0.000, 1, 'MU1580'
UNION ALL SELECT 'MU1581', 'SKRUP HAK BUKA NO 8', 'SKRUP', 'KURA', 'PCS', 381.00, 6.000, 1, 'MU1581'
UNION ALL SELECT 'MU1582', 'CIRCLE SAW BLADE TCT CSW-0724 7\" 24T', 'MATA SERKEL', 'WIPRO', 'PCS', 94250.00, 0.000, 1, 'MU1582'
UNION ALL SELECT 'MU1583', 'HALCON BEIGE 40X40', 'TEHEL', 'ACCURA', 'DUS', 60000.00, 8.000, 1, 'MU1583'
UNION ALL SELECT 'MU1585', 'AVIAN SYNTHETIC 187', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU1585'
UNION ALL SELECT 'MU1586', 'PISAU PEMOTONG DAGING DIAMOND', 'PISAU', 'DIAMOND', 'PCS', 35200.00, 0.000, 1, 'MU1586'
UNION ALL SELECT 'MU1587', 'TARIKAN LACI 112 KUNING', 'V-BELT', 'SHF', 'PCS', 8000.00, 0.000, 1, 'MU1587'
UNION ALL SELECT 'MU1588', 'CETOK GG KAYU 6\'', 'CETOK', 'STAVIC', 'PCS', 16000.00, 0.000, 1, 'MU1588'
UNION ALL SELECT 'MU1589', 'GLASS BLOCK', 'GLASSBLOCK', '-', 'PCS', 27000.00, 0.000, 1, 'MU1589'
UNION ALL SELECT 'MU1590', 'KENALPOT ALCON GX160', 'MATA SERKEL', 'NOQIWA', 'PCS', 120000.00, 0.000, 1, 'MU1590'
UNION ALL SELECT 'MU1591', 'KACA MATA SAFETY', 'KACAMATA', 'WIPRO', 'PCS', 20000.00, 0.000, 1, 'MU1591'
UNION ALL SELECT 'MU1592', 'KACAMATA SAFETY', 'KACAMATA', 'WIPRO', 'PCS', 20000.00, 0.000, 1, 'MU1592'
UNION ALL SELECT 'MU1593', 'KUAS BIASA 5\"', 'Kuas', 'MDN', 'PCS', 16500.00, 12.000, 1, 'MU1593'
UNION ALL SELECT 'MU1594', 'AMPLAS DUCO 1500', 'AMPLAS', 'FUJI STAR', 'PCS', 4800.00, 0.000, 1, 'MU1594'
UNION ALL SELECT 'MU1595', 'KRAN HQ HITAM 3/4', 'KRAN', '-', 'PCS', 13703.00, 0.000, 1, 'MU1595'
UNION ALL SELECT 'MU1596', 'RODA CASTERS MATI', 'RODA', 'CASTERS', 'PCS', 9600.00, 0.000, 1, 'MU1596'
UNION ALL SELECT 'MU1597', 'RODA CASTERS 2\'\'', 'RODA', 'CASTERS', 'PCS', 8800.00, 0.000, 1, 'MU1597'
UNION ALL SELECT 'MU1598', 'RODA CASTERS BULAT HITAM', 'RODA', 'CASTERS', 'PCS', 8000.00, 0.000, 1, 'MU1598'
UNION ALL SELECT 'MU1599', 'RODA CASTERS GEPENG HITAM', 'RODA', 'CASTERS', 'PCS', 8000.00, 6.000, 1, 'MU1599'
UNION ALL SELECT 'MU1600', 'SEPROTAN UJUNG KUNING', 'MATA SERKEL', '-', 'PCS', 15000.00, 0.000, 1, 'MU1600'
UNION ALL SELECT 'MU1601', 'DISPENSER MIYAKO WD-290PHC', 'DISPENSER', 'MIYOKO', 'UNIT', 332000.00, 0.000, 1, 'MU1601'
UNION ALL SELECT 'MU1602', 'AMPLAS GRIT 100', 'AMPLAS', 'TAIYO', 'LBR', 2342.00, 0.000, 1, 'MU1602'
UNION ALL SELECT 'MU1603', 'HIACE GREY 50X50', 'TEHEL', 'JUPITER', 'DUS', 74599.00, 8.000, 1, 'MU1603'
UNION ALL SELECT 'MU1604', 'AVIAN SYNTHETIC 178', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU1604'
UNION ALL SELECT 'MU1605', 'SEMPROTAN KUNINGAN', 'KRAN', '-', 'UNIT', 17500.00, 7.000, 1, 'MU1605'
UNION ALL SELECT 'MU1606', 'SELANG FLEXIBLE 40CM ERNESTO', 'SELANG', 'ERNESTO', 'PCS', 20000.00, 0.000, 1, 'MU1606'
UNION ALL SELECT 'MU1607', 'KRAN ANGSA DRAT BAWAH ONDA V634T', 'KRAN', '-', 'PCS', 57500.00, 0.000, 1, 'MU1607'
UNION ALL SELECT 'MU1608', 'KRAN WASTAFEL ONDA Y316FA', 'KRAN', '-', 'PCS', 62500.00, 0.000, 1, 'MU1608'
UNION ALL SELECT 'MU1609', 'KRAN ANGSA K.BESAR HTM', 'KRAN', '-', 'PCS', 65000.00, 5.000, 1, 'MU1609'
UNION ALL SELECT 'MU1610', 'KRAN TEMBOK SOLIGEN 3/4', 'KRAN', 'SOLIGEN', 'PCS', 28000.00, 0.000, 1, 'MU1610'
UNION ALL SELECT 'MU1611', 'KRAN PVC UJUNG PUTIH TRANSPARAN', 'KRAN', '-', 'DUS', 75000.00, 0.000, 1, 'MU1611'
UNION ALL SELECT 'MU1612', 'KRAN PVC COP MDR', 'KRAN', '-', 'DUS', 120000.00, 0.000, 1, 'MU1612'
UNION ALL SELECT 'MU1613', 'KRAN PVC ONGKEL 1/2', 'KRAN', '-', 'DUS', 120000.00, 0.000, 1, 'MU1613'
UNION ALL SELECT 'MU1614', 'AVIAN SYNTHETIC SW 1 LTR', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU1614'
UNION ALL SELECT 'MU1615', 'DEROCCA 004', 'V-BELT', 'DEROCCA', 'SET', 119200.00, 0.000, 1, 'MU1615'
UNION ALL SELECT 'MU1616', 'KRAN PVC ONGKEL 3/4 AMICO', 'KRAN', '-', 'DUS', 135000.00, 0.000, 1, 'MU1616'
UNION ALL SELECT 'MU1617', 'OTOMATIS POMPA RADAR 3/8', 'MATA SERKEL', 'RADAR', 'PCS', 60000.00, 6.000, 1, 'MU1617'
UNION ALL SELECT 'MU1618', 'OTOMATIS POMPA RADARORI 1/4', 'MATA SERKEL', '-', 'PCS', 50000.00, 0.000, 1, 'MU1618'
UNION ALL SELECT 'MU1619', 'OTOMATIS POMPA HUAN LIE 3/8', 'MATA SERKEL', '-', 'PCS', 32500.00, 0.000, 1, 'MU1619'
UNION ALL SELECT 'MU1620', 'OTOMATIS POMPA HUAN LIE 1/4', 'MATA SERKEL', 'HUANLI', 'PCS', 36800.00, 0.000, 1, 'MU1620'
UNION ALL SELECT 'MU1621', 'OTAMATIS POMPA SANYO 1/4', 'MATA SERKEL', '-', 'PCS', 87000.00, 0.000, 1, 'MU1621'
UNION ALL SELECT 'MU1622', 'PISAU DEMPUL POLYSTAR 7\'\'', 'KAPI', 'POLYSTAR', 'PCS', 6400.00, 0.000, 1, 'MU1622'
UNION ALL SELECT 'MU1623', 'BASKOM CLARITA 20', 'BASKOM', 'CLARITA', 'PCS', 13600.00, 0.000, 1, 'MU1623'
UNION ALL SELECT 'MU1624', 'SIFON WASTAFEL PLASTIK ARMATUREN', 'KRAN', 'ARMATUREN', 'PCS', 22400.00, 0.000, 1, 'MU1624'
UNION ALL SELECT 'MU1625', 'AVITEX 745 25KG', 'CAT', 'AVITEX', 'PAIL', 440000.00, 0.000, 1, 'MU1625'
UNION ALL SELECT 'MU1626', 'AVITEX 725 25KG', 'CAT', 'AVITEX', 'PAIL', 440000.00, 0.000, 1, 'MU1626'
UNION ALL SELECT 'MU1627', 'ESKOM KECIL', 'ESKOM', 'MERCURY', 'PCS', 10400.00, 0.000, 1, 'MU1627'
UNION ALL SELECT 'MU1628', 'AVITEX 720 25KG', 'CAT', 'AVITEX', 'PAIL', 440000.00, 0.000, 1, 'MU1628'
UNION ALL SELECT 'MU1629', 'AVITEX 700 25KG', 'CAT', 'AVITEX', 'PAIL', 440000.00, 0.000, 1, 'MU1629'
UNION ALL SELECT 'MU1630', 'AVITEX 675 25KG', 'CAT', 'AVITEX', 'PAIL', 440000.00, 0.000, 1, 'MU1630'
UNION ALL SELECT 'MU1631', 'AVITEX 771 25KG', 'CAT', 'AVITEX', 'PAIL', 440000.00, 0.000, 1, 'MU1631'
UNION ALL SELECT 'MU1632', 'AVITEX 770 25KG', 'CAT', 'AVITEX', 'PAIL', 440000.00, 0.000, 1, 'MU1632'
UNION ALL SELECT 'MU1633', 'AVITEX 765 25KG', 'CAT', 'AVITEX', 'PAIL', 440000.00, 0.000, 1, 'MU1633'
UNION ALL SELECT 'MU1634', 'ARIES SW 5KG', 'CAT', 'ARIES', 'POT', 60400.00, 0.000, 1, 'MU1634'
UNION ALL SELECT 'MU1635', 'RODA PINTU FRT KOTAK 3', 'RODA', 'FRT', 'PCS', 24000.00, 0.000, 1, 'MU1635'
UNION ALL SELECT 'MU1636', 'AVIAN SYNTHETIC 466', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU1636'
UNION ALL SELECT 'MU1637', 'OLIQ GRENDEL HTM TEBAL 3INCH', 'GRENDEL', 'OLIQ', 'PCS', 12000.00, 0.000, 1, 'MU1637'
UNION ALL SELECT 'MU1638', 'OLIQ GRENDEL HTM TEBAL 5INCH', 'MATA SERKEL', '-', 'PCS', 6000.00, 0.000, 1, 'MU1638'
UNION ALL SELECT 'MU1639', 'GRENDEL KODAI 2\'', 'GRENDEL', 'KODAI', 'PCS', 9600.00, 12.000, 1, 'MU1639'
UNION ALL SELECT 'MU1640', 'DINABOLT 10X65', 'BAUT', '-', 'PCS', 2000.00, 0.000, 1, 'MU1640'
UNION ALL SELECT 'MU1641', 'PERBAN TALANG STAMCO 1M', 'ISOLASI', 'STAMCO', 'PCS', 6500.00, 6.000, 1, 'MU1641'
UNION ALL SELECT 'MU1642', 'GRENDEL IGM SS PUTIH 2\'', 'GRENDEL', 'IGM', 'PCS', 8750.00, 6.000, 1, 'MU1642'
UNION ALL SELECT 'MU1643', 'OVERVAL HORRISON 3\'\'', 'OVERVAL', 'HORRISON', 'PCS', 5000.00, 0.000, 1, 'MU1643'
UNION ALL SELECT 'MU1644', 'OVERVAL HORRISON 4\'\'', 'OVERVAL', 'HORRISON', 'PCS', 6000.00, 0.000, 1, 'MU1644'
UNION ALL SELECT 'MU1645', 'PAKU BETON HITAM 50MM MILLION', 'Paku', 'MILLION', 'DUS', 12500.00, 6.000, 1, 'MU1645'
UNION ALL SELECT 'MU1646', 'AVIAN SYNTHETIC 480', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU1646'
UNION ALL SELECT 'MU1647', 'ARIES EMULSION SW 20KG', 'CAT', 'ARIES', 'PAIL', 225200.00, 0.000, 1, 'MU1647'
UNION ALL SELECT 'MU1648', 'KAWAT HARMONIKA 2M SEDANG', 'KAWAT', '-', 'MTR', 72000.00, 0.000, 1, 'MU1648'
UNION ALL SELECT 'MU1649', 'KAWAT DURI 4 kg', 'KAWAT', '-', 'ROL', 58500.00, 0.000, 1, 'MU1649'
UNION ALL SELECT 'MU1650', 'ESKOM BESAR', 'ESKOM', 'MERCURY', 'PCS', 16000.00, 0.000, 1, 'MU1650'
UNION ALL SELECT 'MU1651', 'BAKUL BESAR', 'BAKUL', '-', 'PCS', 28000.00, 0.000, 1, 'MU1651'
UNION ALL SELECT 'MU1652', 'MAT BLACK PB102A', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 2.000, 1, 'MU1652'
UNION ALL SELECT 'MU1653', 'KITTY PINK PB121', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 0.000, 1, 'MU1653'
UNION ALL SELECT 'MU1654', 'AVIAN SYNTHETIC 198', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU1654'
UNION ALL SELECT 'MU1655', 'SILVER PB118', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 0.000, 1, 'MU1655'
UNION ALL SELECT 'MU1656', 'NIPPE 2000 CLEAR', 'CAT', 'NIPPON PAINT', 'KLG', 89355.00, 0.000, 1, 'MU1656'
UNION ALL SELECT 'MU1657', 'EMBER CITRA', 'EMBER', '-', 'SET', 15000.00, 0.000, 1, 'MU1657'
UNION ALL SELECT 'MU1658', 'FRT BOOT UK. 41', 'SEPATU', 'FRT', 'SET', 60000.00, 0.000, 1, 'MU1658'
UNION ALL SELECT 'MU1659', 'GEROBAK PASIR VPR', 'GEROBAK', 'VPR', 'UNIT', 311000.00, 0.000, 1, 'MU1659'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU1660' AS sku, 'BAK CAT HIJAU' AS name, 'MATA SERKEL' AS category_name, '-' AS brand_name, 'PCS' AS base_unit_code, 28000.00 AS cost_price, 0.000 AS min_stock, 1 AS is_active, 'MU1660' AS legacy_sku
UNION ALL SELECT 'MU1661', 'NIPPON PAINT YELLOW', 'CAT', 'NIPPON PAINT', 'KLG', 68000.00, 0.000, 1, 'MU1661'
UNION ALL SELECT 'MU1662', 'STRIPING DISC 100X16 PERONTOK CAT', 'MATA GURINDA', 'PAK TUKANG', 'PCS', 33930.00, 0.000, 1, 'MU1662'
UNION ALL SELECT 'MU1663', 'ENGSEL MAJESTY 4\'\'', 'ENGSEL', 'MAJESTY', 'PCS', 21600.00, 0.000, 1, 'MU1663'
UNION ALL SELECT 'MU1664', 'PARUTAN PLASTIK 3037', 'PARUT', 'INDOGLASS', 'SET', 20000.00, 0.000, 1, 'MU1664'
UNION ALL SELECT 'MU1665', 'ADAPTOR PLUG UTICON', 'TERMINAL COK', 'RABBIT', 'PCS', 24000.00, 0.000, 1, 'MU1665'
UNION ALL SELECT 'MU1666', 'NAMPAN LUCKY STAR', 'NAMPAN', 'LUCKY STAR', 'PCS', 40000.00, 0.000, 1, 'MU1666'
UNION ALL SELECT 'MU1667', 'LIPPRO KUNCI T 8 mm', 'V-BELT', 'LIPPRO', 'PCS', 24000.00, 0.000, 1, 'MU1667'
UNION ALL SELECT 'MU1668', 'NEW DIOR', 'MATA SERKEL', '-', 'SET', 0.00, 0.000, 1, 'MU1668'
UNION ALL SELECT 'MU1669', 'KINIK MATA GURINDA BESAR', 'MATA GURINDA', 'KINIK', 'PCS', 48000.00, 0.000, 1, 'MU1669'
UNION ALL SELECT 'MU1670', 'OBENG RADIO 12+', 'OBENG', 'POPEYE', 'PCS', 12000.00, 0.000, 1, 'MU1670'
UNION ALL SELECT 'MU1671', 'REMES PAKING 6\'', 'PAKING', '-', 'MTR', 3000.00, 0.000, 1, 'MU1671'
UNION ALL SELECT 'MU1672', 'KUNCI PINTU 366L-NB/GP', 'KUNCI PINTU', 'SANSHAN', 'SET', 115000.00, 0.000, 1, 'MU1672'
UNION ALL SELECT 'MU1673', 'WIPRO KUNCI L 8 mm', 'V-BELT', 'WIPRO', 'PCS', 19000.00, 0.000, 1, 'MU1673'
UNION ALL SELECT 'MU1674', 'WIPRO KUNCI L 5 mm', 'ALAT', 'WIPRO', 'PCS', 7975.00, 12.000, 1, 'MU1674'
UNION ALL SELECT 'MU1675', 'LIPPRO KUNCI PIPA 24\'', 'V-BELT', 'LIPPRO', 'PCS', 200000.00, 2.000, 1, 'MU1675'
UNION ALL SELECT 'MU1676', 'SPRAY GUN DG-10', 'MATA SERKEL', '-', 'PAIL', 192000.00, 0.000, 1, 'MU1676'
UNION ALL SELECT 'MU1677', 'NOQIWA CLUTCH ASSY NQW767-0209', 'S/PART', 'NOQIWA', 'PCS', 48000.00, 6.000, 1, 'MU1677'
UNION ALL SELECT 'MU1678', 'SOCK DRAT 1/2', 'SAMBUNGAN', '-', 'PCS', 1418.00, 0.000, 1, 'MU1678'
UNION ALL SELECT 'MU1679', 'OBENG BALAK BALIK WIPRO BIRU', 'OBENG', 'WIPRO', 'PCS', 16800.00, 0.000, 1, 'MU1679'
UNION ALL SELECT 'MU1680', 'SAKLAR TEMPEL TUNGGAL BROCO', 'SAKLAR', 'BROCO', 'PCS', 12700.00, 10.000, 1, 'MU1680'
UNION ALL SELECT 'MU1681', 'OLI ENDURO 4T', 'OLI', 'ENDURO', 'BTL', 44000.00, 0.000, 1, 'MU1681'
UNION ALL SELECT 'MU1682', 'WIPRO KUNCI L 14 mm', 'V-BELT', 'WIPRO', 'PCS', 88000.00, 0.000, 1, 'MU1682'
UNION ALL SELECT 'MU1683', 'DINABOLT 8X65', 'BAUT', '-', 'PCS', 2000.00, 0.000, 1, 'MU1683'
UNION ALL SELECT 'MU1684', 'WATER PUMP COVER', 'MATA SERKEL', 'I MOTO', 'PCS', 144000.00, 6.000, 1, 'MU1684'
UNION ALL SELECT 'MU1685', 'HANDLE GAS M.BABAT', 'S/PART', 'MIYAMOTO', 'PCS', 7000.00, 7.000, 1, 'MU1685'
UNION ALL SELECT 'MU1686', 'RECOIL STARTER ASSY (BIG)', 'S/PART', 'MIYAMOTO', 'SET', 50000.00, 7.000, 1, 'MU1686'
UNION ALL SELECT 'MU1687', 'NYLON CUTTER NOQIWA', 'MATA SERKEL', 'NOQIWA', 'SET', 48000.00, 0.000, 1, 'MU1687'
UNION ALL SELECT 'MU1688', 'CHAIN SAW MOTORO', 'MATA SERKEL', '-', 'UNIT', 1750000.00, 0.000, 1, 'MU1688'
UNION ALL SELECT 'MU1689', 'MEIKO 4L + 1,5 M', 'TERMINALJ', 'MEIKO', 'PCS', 15500.00, 0.000, 1, 'MU1689'
UNION ALL SELECT 'MU1690', 'MEIKO 5L + 1,5 M', 'TERMINALJ', 'MEIKO', 'PCS', 16500.00, 0.000, 1, 'MU1690'
UNION ALL SELECT 'MU1691', 'STEKER UTICON', 'CLOSET', 'RABBIT', 'PCS', 5800.00, 0.000, 1, 'MU1691'
UNION ALL SELECT 'MU1692', 'PISAU BERANG HITAM B-018', 'PISAU', '-', 'PCS', 48000.00, 0.000, 1, 'MU1692'
UNION ALL SELECT 'MU1693', 'PISAU 495', 'PISAU', '-', 'PCS', 4200.00, 0.000, 1, 'MU1693'
UNION ALL SELECT 'MU1694', 'KUNCI PINTU 369L-NB/GP', 'KUNCI PINTU', 'SANSHAN', 'SET', 115000.00, 0.000, 1, 'MU1694'
UNION ALL SELECT 'MU1695', 'KUNCI PINTU 338L-NB/GP', 'KUNCI PINTU', 'SANSHAN', 'SET', 115000.00, 0.000, 1, 'MU1695'
UNION ALL SELECT 'MU1696', 'SUTIL TRIJAYA TANGGUNG', 'SUTIL', 'STAINLISSTEEL', 'PCS', 7200.00, 0.000, 1, 'MU1696'
UNION ALL SELECT 'MU1697', 'OVERVAL HORRISON 5', 'OVERVAL', 'HORRISON', 'PCS', 9600.00, 0.000, 1, 'MU1697'
UNION ALL SELECT 'MU1698', 'OVERVAL HORRISON 6', 'OVERVAL', 'HORRISON', 'PCS', 14400.00, 0.000, 1, 'MU1698'
UNION ALL SELECT 'MU1699', 'GRENDEL KURA 6\'\'', 'GRENDEL', 'KURA', 'PCS', 8800.00, 0.000, 1, 'MU1699'
UNION ALL SELECT 'MU1700', 'TERMINAL KABEL 2L X 3M', 'TERMINAL COK', 'FREDER', 'PCS', 21600.00, 0.000, 1, 'MU1700'
UNION ALL SELECT 'MU1701', 'RUBY BROWN 50X50', 'TEHEL', 'DELTA', 'DUS', 69500.00, 8.000, 1, 'MU1701'
UNION ALL SELECT 'MU1702', 'KUAS ROLL TOKIO 8\"', 'Kuas', 'SANSHAN', 'PCS', 20000.00, 0.000, 1, 'MU1702'
UNION ALL SELECT 'MU1703', 'AVITEX 755 25KG', 'CAT', 'AVITEX', 'PAIL', 440000.00, 0.000, 1, 'MU1703'
UNION ALL SELECT 'MU1711', 'PAKU TRIPLEK', 'Paku', '-', 'KG', 14000.00, 88.000, 1, 'MU1711'
UNION ALL SELECT 'MU1712', 'PAKU SENG', 'Paku', '-', 'KG', 19000.00, 88.000, 1, 'MU1712'
UNION ALL SELECT 'MU1713', 'KEPALA AKI KUNINGAN', 'S/PART', '-', 'SET', 21500.00, 0.000, 1, 'MU1713'
UNION ALL SELECT 'MU1714', 'LAKBAN HITAM PAJERO', 'LAKBAN', 'PAJERO', 'PCS', 12000.00, 12.000, 1, 'MU1714'
UNION ALL SELECT 'MU1715', 'LAKBAN COKLAT PAJERO', 'LAKBAN', 'PAJERO', 'PCS', 10000.00, 0.000, 1, 'MU1715'
UNION ALL SELECT 'MU1716', 'LAKBAN KERTAS DIKO BESAR 17m', 'LAKBAN', 'PAJERO', 'PCS', 11200.00, 0.000, 1, 'MU1716'
UNION ALL SELECT 'MU1717', 'WEFER AIR', 'SAPU', '-', 'PCS', 0.00, 0.000, 1, 'MU1717'
UNION ALL SELECT 'MU1718', 'MATA BOR BESI 2.5 MM HSS M42', 'MATA BOR', 'WIPRO', 'PCS', 11825.00, 0.000, 1, 'MU1718'
UNION ALL SELECT 'MU1719', 'OTOMAT YAMADA 1/8', 'MATA SERKEL', 'YAMADA', 'PCS', 56000.00, 0.000, 1, 'MU1719'
UNION ALL SELECT 'MU1720', 'OBENG GG KARET GNT 6+', 'OBENG', 'GNT', 'PCS', 20000.00, 0.000, 1, 'MU1720'
UNION ALL SELECT 'MU1721', 'OBENG GG KARET GNT 6-', 'OBENG', 'GNT', 'PCS', 18000.00, 0.000, 1, 'MU1721'
UNION ALL SELECT 'MU1722', 'GRENDEL KURA 3\'', 'GRENDEL', 'KURA', 'PCS', 5000.00, 12.000, 1, 'MU1722'
UNION ALL SELECT 'MU1723', 'KIPAS DINDING MIYAKO KAW-1662', 'KIPAS', 'MIYAKO', 'UNIT', 265000.00, 1.000, 1, 'MU1723'
UNION ALL SELECT 'MU1724', 'FLAP DISC A60', 'AMPLAS', 'WIPRO', 'PCS', 10400.00, 12.000, 1, 'MU1724'
UNION ALL SELECT 'MU1725', 'OBENG BB KUNING WIPRO', 'OBENG', 'WIPRO', 'PCS', 11200.00, 6.000, 1, 'MU1725'
UNION ALL SELECT 'MU1726', 'TEER INDIAN 10 KG', 'TEER', 'INDIAN', 'PAIL', 237500.00, 0.000, 1, 'MU1726'
UNION ALL SELECT 'MU1727', 'WIPRO KUNCI L 10mm', 'V-BELT', 'WIPRO', 'PCS', 44000.00, 0.000, 1, 'MU1727'
UNION ALL SELECT 'MU1728', 'KUNCI T 14 mm WIPRO', 'V-BELT', 'LIPPRO', 'PCS', 24000.00, 0.000, 1, 'MU1728'
UNION ALL SELECT 'MU1729', 'GUNTING TANAMAN MULLER 302', 'GUNTING', 'MULLER', 'PCS', 40000.00, 12.000, 1, 'MU1729'
UNION ALL SELECT 'MU1730', 'POT HITAM PBB 30', 'POT', 'EKO', 'PCS', 6400.00, 0.000, 1, 'MU1730'
UNION ALL SELECT 'MU1731', 'TERMINAL KABEL 2L X 5M', 'TERMINAL COK', '-', 'PCS', 25800.00, 0.000, 1, 'MU1731'
UNION ALL SELECT 'MU1732', 'REMES PAKING 3\'', 'PAKING', '-', 'MTR', 3000.00, 0.000, 1, 'MU1732'
UNION ALL SELECT 'MU1733', 'REMES PAKING 4\'', 'PAKING', '-', 'MTR', 3000.00, 0.000, 1, 'MU1733'
UNION ALL SELECT 'MU1734', 'OVER SOCK 3/4 X 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 2252.00, 0.000, 1, 'MU1734'
UNION ALL SELECT 'MU1735', 'NOQIWA GEAR CASE ASSY', 'MATA SERKEL', 'NOQIWA', 'PCS', 160000.00, 6.000, 1, 'MU1735'
UNION ALL SELECT 'MU1736', 'CARBURATOR MESIN BABAT RUMPUT', 'S/PART', 'MIYAMOTO', 'PCS', 55000.00, 7.000, 1, 'MU1736'
UNION ALL SELECT 'MU1737', 'DINABOLT 10X50', 'BAUT', '-', 'PCS', 4000.00, 0.000, 1, 'MU1737'
UNION ALL SELECT 'MU1738', 'SOCK DRAT 1', 'SAMBUNGAN', 'POWER', 'PCS', 2716.00, 0.000, 1, 'MU1738'
UNION ALL SELECT 'MU1739', 'ELBOW DRAT 3/4', 'SAMBUNGAN', 'POWER', 'PCS', 3600.00, 0.000, 1, 'MU1739'
UNION ALL SELECT 'MU1740', 'KUNCI PINTU CLASSIC', 'V-BELT', 'CLASSIC', 'SET', 80000.00, 0.000, 1, 'MU1740'
UNION ALL SELECT 'MU1741', 'LUCKY POT 16 CM', 'PANCI', 'JAWA', 'SET', 38000.00, 0.000, 1, 'MU1741'
UNION ALL SELECT 'MU1742', 'GANTUNGAN BULAT MASPION', 'GANTUNGAN', 'MASPION', 'PCS', 35000.00, 0.000, 1, 'MU1742'
UNION ALL SELECT 'MU1743', 'KRAN 3/4 VNZ', 'KRAN', '-', 'PCS', 19000.00, 0.000, 1, 'MU1743'
UNION ALL SELECT 'MU1744', 'WALL SEALER 20KG', 'CAT', 'NIPPON PAINT', 'PAIL', 1024000.00, 0.000, 1, 'MU1744'
UNION ALL SELECT 'MU1745', 'PENGGOSOK PANCI', 'MATA SERKEL', 'STAINLISSTEEL', 'PCS', 4000.00, 0.000, 1, 'MU1745'
UNION ALL SELECT 'MU1746', 'PANCI JAWA 30', 'PANCI', 'JAWA', 'PCS', 166000.00, 0.000, 1, 'MU1746'
UNION ALL SELECT 'MU1747', 'KUNCI PINTU 358L-NB/CP', 'KUNCI PINTU', 'SANSHAN', 'SET', 102500.00, 1.000, 1, 'MU1747'
UNION ALL SELECT 'MU1748', 'MULLER SILVER CP ROSE', 'SETRIKA', 'MULLER', 'SET', 144000.00, 0.000, 1, 'MU1748'
UNION ALL SELECT 'MU1749', 'WIPRO SOCK 24MM', 'V-BELT', 'WIPRO', 'PCS', 25600.00, 0.000, 1, 'MU1749'
UNION ALL SELECT 'MU1750', 'WIPRO SOCK 32MM', 'V-BELT', 'WIPRO', 'PCS', 48000.00, 0.000, 1, 'MU1750'
UNION ALL SELECT 'MU1751', 'WIPRO SOCK 8MM', 'V-BELT', 'WIPRO', 'PCS', 10400.00, 0.000, 1, 'MU1751'
UNION ALL SELECT 'MU1752', 'WIPRO SOCK 9MM', 'V-BELT', 'WIPRO', 'PCS', 10400.00, 0.000, 1, 'MU1752'
UNION ALL SELECT 'MU1753', 'WIPRO SOCK 10MM', 'V-BELT', 'WIPRO', 'PCS', 10400.00, 0.000, 1, 'MU1753'
UNION ALL SELECT 'MU1754', 'WIPRO SOCK 11MM', 'V-BELT', 'WIPRO', 'PCS', 11200.00, 0.000, 1, 'MU1754'
UNION ALL SELECT 'MU1755', 'WIPRO SOCK 12MM', 'V-BELT', 'WIPRO', 'PCS', 11200.00, 0.000, 1, 'MU1755'
UNION ALL SELECT 'MU1756', 'WIPRO SOCK 13MM', 'V-BELT', 'WIPRO', 'PCS', 12000.00, 0.000, 1, 'MU1756'
UNION ALL SELECT 'MU1757', 'WIPRO SOCK 14MM', 'V-BELT', 'WIPRO', 'PCS', 12000.00, 0.000, 1, 'MU1757'
UNION ALL SELECT 'MU1758', 'WIPRO SOCK 15MM', 'V-BELT', 'WIPRO', 'PCS', 12800.00, 0.000, 1, 'MU1758'
UNION ALL SELECT 'MU1759', 'WIPRO SOCK 16MM', 'V-BELT', 'WIPRO', 'PCS', 12800.00, 0.000, 1, 'MU1759'
UNION ALL SELECT 'MU1760', 'WIPRO SOCK 17MM', 'V-BELT', 'WIPRO', 'PCS', 14400.00, 0.000, 1, 'MU1760'
UNION ALL SELECT 'MU1761', 'WIPRO SOCK 18MM', 'V-BELT', 'WIPRO', 'PCS', 16000.00, 0.000, 1, 'MU1761'
UNION ALL SELECT 'MU1762', 'WIPRO SOCK 19MM', 'V-BELT', 'WIPRO', 'PCS', 16000.00, 0.000, 1, 'MU1762'
UNION ALL SELECT 'MU1763', 'WIPRO SOCK 22MM', 'V-BELT', 'WIPRO', 'PCS', 20000.00, 0.000, 1, 'MU1763'
UNION ALL SELECT 'MU1764', 'SIKU RAK TEBAL 10X12', 'BESI', 'KURA', 'SET', 40000.00, 0.000, 1, 'MU1764'
UNION ALL SELECT 'MU1765', 'SIKU RAK TEBAL 8x10 (190)', 'BESI', 'KURA', 'SET', 18000.00, 12.000, 1, 'MU1765'
UNION ALL SELECT 'MU1766', 'SIKU RAK TEBAL 6X8', 'BESI', 'KURA', 'SET', 28000.00, 12.000, 1, 'MU1766'
UNION ALL SELECT 'MU1767', 'SIKU RAK TEBAL 5X6 (120)', 'BESI', 'KURA', 'PSG', 10500.00, 12.000, 1, 'MU1767'
UNION ALL SELECT 'MU1768', 'TARIKAN LACI SHF161', 'TARIKAN LACI', 'SHF', 'PCS', 10400.00, 0.000, 1, 'MU1768'
UNION ALL SELECT 'MU1769', 'TARIKAN LACI 118AT', 'TARIKAN LACI', '-', 'PCS', 8000.00, 0.000, 1, 'MU1769'
UNION ALL SELECT 'MU1770', 'TARIKAN LACI 04PTH', 'TARIKAN LACI', 'IGM', 'PCS', 8000.00, 0.000, 1, 'MU1770'
UNION ALL SELECT 'MU1771', 'TARIKAN LACI YELLOW', 'TARIKAN LACI', 'IGM', 'PCS', 8000.00, 0.000, 1, 'MU1771'
UNION ALL SELECT 'MU1772', 'TARIKAN LACI 01PTH', 'TARIKAN LACI', 'IGM', 'PCS', 8000.00, 0.000, 1, 'MU1772'
UNION ALL SELECT 'MU1773', 'TARIKAN LACI 018CP', 'TARIKAN LACI', 'IGM', 'PCS', 8000.00, 0.000, 1, 'MU1773'
UNION ALL SELECT 'MU1774', 'TARIKAN LACI IGM 018GP', 'TARIKAN LACI', 'IGM', 'PCS', 8000.00, 0.000, 1, 'MU1774'
UNION ALL SELECT 'MU1775', 'TARIKAN LACI TOP', 'TARIKAN LACI', 'TOP', 'PCS', 8000.00, 0.000, 1, 'MU1775'
UNION ALL SELECT 'MU1776', 'GPUTIH 60 PJG', 'GEMBOK', 'GRT', 'PCS', 18500.00, 5.000, 1, 'MU1776'
UNION ALL SELECT 'MU1777', 'STANG GERGAJI BESI IGM GG KARET', 'GERGAJI', 'IGM', 'PCS', 20833.00, 0.000, 1, 'MU1777'
UNION ALL SELECT 'MU1778', 'GPUTIH 60PDK', 'GEMBOK', 'GRT', 'PCS', 18000.00, 5.000, 1, 'MU1778'
UNION ALL SELECT 'MU1779', 'GEMBOK GLOBE 30MM', 'V-BELT', 'GLOBE', 'PCS', 14166.00, 12.000, 1, 'MU1779'
UNION ALL SELECT 'MU1780', 'GEMBOK GLOBE 25MM', 'V-BELT', 'GLOBE', 'PCS', 10400.00, 12.000, 1, 'MU1780'
UNION ALL SELECT 'MU1781', 'GEMBOK GLOBE 20MM', 'V-BELT', 'GLOBE', 'PCS', 8541.00, 12.000, 1, 'MU1781'
UNION ALL SELECT 'MU1782', 'GRENDEL KURA 4\'\'', 'GRENDEL', 'KURA', 'PCS', 4000.00, 12.000, 1, 'MU1782'
UNION ALL SELECT 'MU1783', 'TARIKAN LACI EB', 'TARIKAN LACI', 'E-B', 'PCS', 8000.00, 0.000, 1, 'MU1783'
UNION ALL SELECT 'MU1784', 'MCB IP C20', 'MCB', 'SCHNEIDER', 'PCS', 72875.00, 0.000, 1, 'MU1784'
UNION ALL SELECT 'MU1785', 'KAPI GG PVC 2 1/2\'\'', 'KAPI', 'SCRAPER', 'PCS', 6400.00, 0.000, 1, 'MU1785'
UNION ALL SELECT 'MU1786', 'KAPI GG PVC 2\'\'', 'KAPI', 'SCRAPER', 'PCS', 5600.00, 0.000, 1, 'MU1786'
UNION ALL SELECT 'MU1787', 'KAPI GG PVC 1 1/2', 'KAPI', 'SCRAPER', 'PCS', 4800.00, 0.000, 1, 'MU1787'
UNION ALL SELECT 'MU1789', 'MATA BOR BESI 8MM', 'MATA BOR', 'WIPRO', 'PCS', 32175.00, 10.000, 1, 'MU1789'
UNION ALL SELECT 'MU1790', 'LIPPRO KUNCI PIPA 18\'', 'V-BELT', 'LIPPRO', 'PCS', 148000.00, 2.000, 1, 'MU1790'
UNION ALL SELECT 'MU1791', 'KUNCI T HITAM WIPRO 12mm', 'ALAT', 'WIPRO', 'PCS', 24000.00, 0.000, 1, 'MU1791'
UNION ALL SELECT 'MU1792', 'SAKLAR TANAM TUNGGAL BROCO', 'SAKLAR', 'BROCO', 'PCS', 12000.00, 0.000, 1, 'MU1792'
UNION ALL SELECT 'MU1793', 'STALL HITAM 20X20X1.7', 'BESI', '-', 'BTG', 80000.00, 0.000, 1, 'MU1793'
UNION ALL SELECT 'MU1794', 'SPECIAL RED PB115 PILLOX', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 2.000, 1, 'MU1794'
UNION ALL SELECT 'MU1795', 'METALIC SILVER PBM01', 'PILLOX', 'NIPPON PAINT', 'PCS', 23200.00, 0.000, 1, 'MU1795'
UNION ALL SELECT 'MU1796', 'MATEX PUTTY 1KG', 'CAT', 'NIPPON PAINT', 'KLG', 23496.00, 0.000, 1, 'MU1796'
UNION ALL SELECT 'MU1797', 'ELASTEX 4KG 1185P', 'CAT', 'NIPPON PAINT', 'KLG', 254000.00, 0.000, 1, 'MU1797'
UNION ALL SELECT 'MU1798', 'DEMPUL KAYU', 'DEMPUL', 'ALTEX', 'KLG', 37600.00, 0.000, 1, 'MU1798'
UNION ALL SELECT 'MU1799', 'SARUNG TANGAN LAS', 'SARUNG TANGAN', 'ELSEN', 'PSG', 34400.00, 0.000, 1, 'MU1799'
UNION ALL SELECT 'MU1800', 'MKK NYLON ROLL 40M', 'NYLON', 'MKK', 'ROL', 34500.00, 0.000, 1, 'MU1800'
UNION ALL SELECT 'MU1801', 'SAPU DAUN 214', 'SAPU', 'NAGATA', 'PCS', 54400.00, 0.000, 1, 'MU1801'
UNION ALL SELECT 'MU1802', 'SPI RING 14', 'MATA SERKEL', '-', 'PCS', 2000.00, 0.000, 1, 'MU1802'
UNION ALL SELECT 'MU1803', 'SPI RING 16', 'MATA SERKEL', '-', 'PCS', 2000.00, 0.000, 1, 'MU1803'
UNION ALL SELECT 'MU1804', 'SPI RING 17', 'MATA SERKEL', '-', 'PCS', 2000.00, 0.000, 1, 'MU1804'
UNION ALL SELECT 'MU1805', 'FITING TANAM SONDA', 'FITING', 'SONDA', 'PCS', 44000.00, 0.000, 1, 'MU1805'
UNION ALL SELECT 'MU1806', 'SOK PIPA 5/8', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1806'
UNION ALL SELECT 'MU1807', 'WATER MUR 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 27590.00, 0.000, 1, 'MU1807'
UNION ALL SELECT 'MU1808', 'KUNCI LACI 808 BESAR', 'Kunci', '808', 'PCS', 19200.00, 6.000, 1, 'MU1808'
UNION ALL SELECT 'MU1809', 'SELANG BENSIN MESIN BABAT', 'S/PART', 'NOQIWA', 'PCS', 9300.00, 6.000, 1, 'MU1809'
UNION ALL SELECT 'MU1810', 'TIMBANGAN DIGITAL 40KG', 'TIMBANGAN', '-', 'PCS', 385000.00, 0.000, 1, 'MU1810'
UNION ALL SELECT 'MU1811', 'METERAN TOLEDO 3M', 'METERAN', 'TOLEDO', 'PCS', 8166.00, 0.000, 1, 'MU1811'
UNION ALL SELECT 'MU1812', 'SEMEN MORTAR GIANT', 'SEMEN', 'GIANT', 'SAK', 107800.00, 0.000, 1, 'MU1812'
UNION ALL SELECT 'MU1813', 'PEMBERAT', 'ALAT UKUR', '-', 'PCS', 10000.00, 0.000, 1, 'MU1813'
UNION ALL SELECT 'MU1814', 'BESI STRIP 2.85 X 0.4 X 400', 'BESI', '-', 'LBR', 50000.00, 0.000, 1, 'MU1814'
UNION ALL SELECT 'MU1815', 'SIKAT BAJA BENGKOK ELGLADIO', 'SIKAT', 'ELGLADIO', 'PCS', 5000.00, 0.000, 1, 'MU1815'
UNION ALL SELECT 'MU1816', 'STOP KRAN MDN 1\'', 'KRAN', 'MDN', 'PCS', 20000.00, 0.000, 1, 'MU1816'
UNION ALL SELECT 'MU1817', 'STOP KRAN INS 3/4', 'KRAN', '-', 'PCS', 8900.00, 0.000, 1, 'MU1817'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU1818' AS sku, 'LEM SENG STAMCO 10 M' AS name, 'ISOLASI' AS category_name, 'STAMCO' AS brand_name, 'ROL' AS base_unit_code, 65000.00 AS cost_price, 6.000 AS min_stock, 1 AS is_active, 'MU1818' AS legacy_sku
UNION ALL SELECT 'MU1819', 'OVERVAL 3\'', 'OVERVAL', 'FAWI', 'PCS', 4000.00, 0.000, 1, 'MU1819'
UNION ALL SELECT 'MU1820', 'SANSAW MAKNUM', 'MATA SERKEL', '-', 'UNIT', 1450000.00, 0.000, 1, 'MU1820'
UNION ALL SELECT 'MU1821', 'S/PART NQWWP30-0217', 'S/PART', '-', 'PCS', 184000.00, 0.000, 1, 'MU1821'
UNION ALL SELECT 'MU1822', 'S/PART NQWWP30-0213', 'S/PART', '-', 'PCS', 27500.00, 0.000, 1, 'MU1822'
UNION ALL SELECT 'MU1823', 'STARTER ROPE', 'MATA SERKEL', '-', 'MTR', 5000.00, 0.000, 1, 'MU1823'
UNION ALL SELECT 'MU1824', 'MESIN BABAT RUMPUT NOQIWA', 'MATA SERKEL', '-', 'UNIT', 639000.00, 0.000, 1, 'MU1824'
UNION ALL SELECT 'MU1825', 'WIPRO STAPLES GUN', 'STRAPLESS', 'WIPRO', 'PCS', 88000.00, 0.000, 1, 'MU1825'
UNION ALL SELECT 'MU1826', 'WIPRO PALU 16OZ', 'PALU', 'WIPRO', 'PCS', 72000.00, 0.000, 1, 'MU1826'
UNION ALL SELECT 'MU1827', 'K.PINTU VRZ 1308 S/NB', 'PINTU', 'VRZ', 'SET', 120000.00, 0.000, 1, 'MU1827'
UNION ALL SELECT 'MU1828', 'KINIK  A60TBF', 'MATA GURINDA', 'KINIK', 'PCS', 8000.00, 0.000, 1, 'MU1828'
UNION ALL SELECT 'MU1831', 'BABY BLUE PB125', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 0.000, 1, 'MU1831'
UNION ALL SELECT 'MU1832', 'KINIK A36P', 'MATA GURINDA', 'WIPRO', 'PCS', 12000.00, 0.000, 1, 'MU1832'
UNION ALL SELECT 'MU1833', 'WIPRO DUSTER 3 WAY DG 10AA', 'MATA SERKEL', 'WIPRO', 'SET', 100000.00, 0.000, 1, 'MU1833'
UNION ALL SELECT 'MU1834', 'WOOD ECO 103 1LT', 'PAKING', '-', 'KLG', 88000.00, 0.000, 1, 'MU1834'
UNION ALL SELECT 'MU1835', 'KUNCI PINTU 378L-NB/GP SANSHAN', 'KUNCI PINTU', 'SANSHAN', 'SET', 102500.00, 2.000, 1, 'MU1835'
UNION ALL SELECT 'MU1836', 'WIPRO KABEL TIES 4.8X300MM', 'KLEM TARIK', 'WIPRO', 'BKS', 42350.00, 0.000, 1, 'MU1836'
UNION ALL SELECT 'MU1837', 'KABEL TIES WIPRO 2.5X150MM', 'KLEM TARIK', 'WIPRO', 'BKS', 11200.00, 10.000, 1, 'MU1837'
UNION ALL SELECT 'MU1838', 'WIPRO AS-725B', 'MATA SERKEL', 'WIPRO', 'SET', 5200000.00, 0.000, 1, 'MU1838'
UNION ALL SELECT 'MU1839', 'WIPRO AIW-1218', 'MATA SERKEL', 'WIPRO', 'SET', 1760000.00, 0.000, 1, 'MU1839'
UNION ALL SELECT 'MU1840', 'GORDEN KOTAK SET SR-052 PUTIH 1.35-2.5M', 'OVERVAL', 'SANSHAN', 'SET', 73500.00, 0.000, 1, 'MU1840'
UNION ALL SELECT 'MU1842', 'WIPRO HAND SOCKET 8-24', 'V-BELT', 'WIPRO', 'SET', 304000.00, 0.000, 1, 'MU1842'
UNION ALL SELECT 'MU1843', 'SOLDER MODEL PISTOL WIPRO', 'SOLDER', 'WIPRO', 'PCS', 72000.00, 0.000, 1, 'MU1843'
UNION ALL SELECT 'MU1844', 'LIPPRO KUNCI T 10 mm', 'V-BELT', 'LIPPRO', 'PCS', 24000.00, 0.000, 1, 'MU1844'
UNION ALL SELECT 'MU1845', 'UTICON 2T', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1845'
UNION ALL SELECT 'MU1846', 'TROWEL BATA RINGAN', 'MATA SERKEL', 'WEBER', 'PCS', 72000.00, 0.000, 1, 'MU1846'
UNION ALL SELECT 'MU1847', 'TRIPLEX 5MM SENGON', 'TRIPLEK', 'SENGON', 'LBR', 95000.00, 0.000, 1, 'MU1847'
UNION ALL SELECT 'MU1848', 'TARIKAN LACI BULAT KAYU', 'MATA SERKEL', '-', 'PCS', 950000.00, 0.000, 1, 'MU1848'
UNION ALL SELECT 'MU1849', 'TESPEN GARFIELD BB', 'TES PEN', 'GARFIELD', 'PCS', 7000.00, 0.000, 1, 'MU1849'
UNION ALL SELECT 'MU1850', 'TINNER AVIAN A 1 LTR', 'TINNER', 'Avian', 'KLG', 33000.00, 0.000, 1, 'MU1850'
UNION ALL SELECT 'MU1851', 'TALANG KOTAK', 'TALANG', '-', 'BTG', 150220.00, 0.000, 1, 'MU1851'
UNION ALL SELECT 'MU1852', 'ARMA BROWN 50X50', 'TEHEL', 'DELTA', 'DUS', 66500.00, 8.000, 1, 'MU1852'
UNION ALL SELECT 'MU1853', 'TINNER AVIA SUPER 1 LTR', 'THINNER', 'Avian', 'KLG', 42000.00, 0.000, 1, 'MU1853'
UNION ALL SELECT 'MU1854', 'TINNER AVIAN A  SPECIAL 5 LTR', 'THINNER', 'Avian', 'KLG', 166000.00, 0.000, 1, 'MU1854'
UNION ALL SELECT 'MU1855', 'TINNER AVIA A SPECIAL 1LTR', 'THINNER', 'Avian', 'KLG', 38000.00, 0.000, 1, 'MU1855'
UNION ALL SELECT 'MU1856', 'TINNER AVIA SUPER 5 LTR', 'THINNER', 'AVIA', 'KLG', 179000.00, 0.000, 1, 'MU1856'
UNION ALL SELECT 'MU1857', 'TINNER  SUPER 3,75 LT', 'TINNER', 'SUPER', 'KLG', 190100.00, 12.000, 1, 'MU1857'
UNION ALL SELECT 'MU1858', 'TINNER A SPECIAL TD 3,75 LTR', 'TINNER', '-', 'KLG', 159500.00, 6.000, 1, 'MU1858'
UNION ALL SELECT 'MU1859', 'TERPAL A5L LUMBA LUMBA 2X3', 'TERPAL', 'NINJA', 'LBR', 36932.00, 0.000, 1, 'MU1859'
UNION ALL SELECT 'MU1860', 'ISIAN LEM TEMBAK KECIL', 'PEL LANTAI', '-', 'PCS', 1500.00, 50.000, 1, 'MU1860'
UNION ALL SELECT 'MU1861', 'ISIAN LEM TEMBAK BESAR', 'LEM', 'WIPRO', 'PCS', 2500.00, 12.000, 1, 'MU1861'
UNION ALL SELECT 'MU1862', 'HAZELO GOLD 50X50', 'TEHEL', 'JUPITER', 'DUS', 72399.00, 8.000, 1, 'MU1862'
UNION ALL SELECT 'MU1863', 'TANG CATUT KAKA TUA STAVIC LITE 10\"', 'MATA SERKEL', 'MULLER', 'PCS', 12000.00, 6.000, 1, 'MU1863'
UNION ALL SELECT 'MU1864', 'SEROK BAMBU 15', 'SEROK', '-', 'PCS', 46400.00, 0.000, 1, 'MU1864'
UNION ALL SELECT 'MU1865', 'SUZUKA EPOXY FILLER 1 LT', 'PAKING', 'SUZUKA', 'SET', 63000.00, 0.000, 1, 'MU1865'
UNION ALL SELECT 'MU1866', 'POWER TOOLS', 'MATA SERKEL', '-', 'PCS', 0.00, 0.000, 1, 'MU1866'
UNION ALL SELECT 'MU1867', 'PISAU ID 102', 'PISAU', '-', 'PCS', 8300.00, 0.000, 1, 'MU1867'
UNION ALL SELECT 'MU1869', 'MATEX CEILING WHITE 5KG', 'CAT', 'NIPPON PAINT', 'GLN', 119000.00, 0.000, 1, 'MU1869'
UNION ALL SELECT 'MU1870', 'GORDEN SET SR-051 0.65-1.2M', 'GORDEN', 'SANSHAN', 'SET', 65000.00, 0.000, 1, 'MU1870'
UNION ALL SELECT 'MU1871', 'POMPA AIR SANYO P-H125A-S', 'MESIN', 'SANYO', 'UNIT', 801300.00, 0.000, 1, 'MU1871'
UNION ALL SELECT 'MU1872', 'KAPI GG PVC 3\"', 'KAPI', '-', 'PCS', 12000.00, 0.000, 1, 'MU1872'
UNION ALL SELECT 'MU1873', 'GRENDEL OVERVAL 4\" HIJAU', 'GRENDEL', 'VOXY', 'PCS', 7500.00, 0.000, 1, 'MU1873'
UNION ALL SELECT 'MU1874', 'SEAL TAPE IGM SUPER 20M', 'LAKBAN', 'IGM', 'PCS', 4500.00, 6.000, 1, 'MU1874'
UNION ALL SELECT 'MU1875', 'TOPLES ODATE 0,3 KG', 'TOPLES', '-', 'PCS', 5000.00, 0.000, 1, 'MU1875'
UNION ALL SELECT 'MU1876', 'ENGSEL SN 4\'', 'ENGSEL', 'IGM', 'SET', 13333.00, 6.000, 1, 'MU1876'
UNION ALL SELECT 'MU1877', 'TERPAL A8 RAJAWALI 6X8', 'TERPAL', 'MATAHARI', 'LBR', 462160.00, 0.000, 1, 'MU1877'
UNION ALL SELECT 'MU1878', 'PROFILA 1100', 'PROFIL', '-', 'UNIT', 1550000.00, 0.000, 1, 'MU1878'
UNION ALL SELECT 'MU1879', 'PROFILA 88 300', 'PROFIL', 'PROFILA 88', 'UNIT', 995000.00, 0.000, 1, 'MU1879'
UNION ALL SELECT 'MU1880', 'PROFIL HIDROFILL 2200', 'PROFIL', 'HIDROFILL', 'UNIT', 4950000.00, 0.000, 1, 'MU1880'
UNION ALL SELECT 'MU1881', 'PROFIL HIDROFILL 1200', 'PROFIL', 'HIDROFILL', 'UNIT', 2515000.00, 0.000, 1, 'MU1881'
UNION ALL SELECT 'MU1882', 'PROFILA 650 Liter', 'PROFIL', '-', 'UNIT', 1030000.00, 0.000, 1, 'MU1882'
UNION ALL SELECT 'MU1884', 'ELASTEX YO 1185P', 'CAT', 'NIPPON PAINT', 'POT', 254000.00, 0.000, 1, 'MU1884'
UNION ALL SELECT 'MU1888', 'BESI GORDEN KOTAK', 'BESI', '-', 'BTG', 48000.00, 0.000, 1, 'MU1888'
UNION ALL SELECT 'MU1889', 'ELASTEX YO 1211A', 'CAT', 'NIPPON PAINT', 'POT', 254000.00, 0.000, 1, 'MU1889'
UNION ALL SELECT 'MU1890', 'ELASTEX BGG 1681D', 'CAT', 'NIPPON PAINT', 'POT', 254000.00, 0.000, 1, 'MU1890'
UNION ALL SELECT 'MU1891', 'ELASTEX 05 GREY', 'CAT', 'NIPPON PAINT', 'POT', 254000.00, 0.000, 1, 'MU1891'
UNION ALL SELECT 'MU1892', 'ELASTEX 07 WATER BLUE', 'CAT', 'NIPPON PAINT', 'POT', 254000.00, 0.000, 1, 'MU1892'
UNION ALL SELECT 'MU1893', 'ELASTEX 10 BLACK', 'CAT', 'NIPPON PAINT', 'POT', 254000.00, 0.000, 1, 'MU1893'
UNION ALL SELECT 'MU1894', 'ELASTEX YO 1149D', 'CAT', 'NIPPON PAINT', 'KLG', 254000.00, 0.000, 1, 'MU1894'
UNION ALL SELECT 'MU1895', 'ELASTEX YO 1185P', 'CAT', 'NIPPON PAINT', 'KLG', 254000.00, 0.000, 1, 'MU1895'
UNION ALL SELECT 'MU1896', 'ELASTEX YO 1211A', 'CAT', 'NIPPON PAINT', 'KLG', 254000.00, 0.000, 1, 'MU1896'
UNION ALL SELECT 'MU1897', 'ELASTEX BGG 1681D', 'CAT', 'NIPPON PAINT', 'KLG', 254000.00, 0.000, 1, 'MU1897'
UNION ALL SELECT 'MU1898', 'ELASTEX 02 WHITE', 'CAT', 'NIPPON PAINT', 'KLG', 254000.00, 0.000, 1, 'MU1898'
UNION ALL SELECT 'MU1899', 'ELASTEX 10 BLACK', 'CAT', 'NIPPON PAINT', 'KLG', 254000.00, 0.000, 1, 'MU1899'
UNION ALL SELECT 'MU1900', 'NP 2000 BLACK MATT', 'CAT', 'NIPPON PAINT', 'KLG', 62400.00, 0.000, 1, 'MU1900'
UNION ALL SELECT 'MU1901', 'LEM EPOXI 1,6KG', 'LEM', 'NIPPON PAINT', 'SET', 220186.00, 0.000, 1, 'MU1901'
UNION ALL SELECT 'MU1902', 'LEM EPOXI 0,35KG', 'LEM', 'NIPPON PAINT', 'KLG', 50641.00, 0.000, 1, 'MU1902'
UNION ALL SELECT 'MU1903', 'AIR CLEANER COVER', 'MATA SERKEL', 'NOQIWA', 'PCS', 14400.00, 6.000, 1, 'MU1903'
UNION ALL SELECT 'MU1904', 'BOLT PROTECTION COVER', 'MATA SERKEL', 'NOQIWA', 'PCS', 8000.00, 6.000, 1, 'MU1904'
UNION ALL SELECT 'MU1905', 'MULTIROOF', 'SENG', '-', 'LBR', 24000.00, 0.000, 1, 'MU1905'
UNION ALL SELECT 'MU1906', 'SENG PUTIH KOTAK CBM 3 M x 025', 'SENG', '-', 'LBR', 80000.00, 0.000, 1, 'MU1906'
UNION ALL SELECT 'MU1907', 'TOPLES 5L', 'TOPLES', 'MULTIPLAST', 'PCS', 12000.00, 0.000, 1, 'MU1907'
UNION ALL SELECT 'MU1908', 'TERMINAL KABEL 3L X 3M', 'TERMINAL COK', 'KITAKO', 'PCS', 23400.00, 3.000, 1, 'MU1908'
UNION ALL SELECT 'MU1909', 'SENG BIRU GELOMBANG POLOS', 'SENG', 'KOALA', 'LBR', 48452.00, 0.000, 1, 'MU1909'
UNION ALL SELECT 'MU1910', 'SOCK 2 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 7470.00, 0.000, 1, 'MU1910'
UNION ALL SELECT 'MU1911', 'BAUT 20 CM PENDEK', 'BAUT', '-', 'BTG', 6000.00, 0.000, 1, 'MU1911'
UNION ALL SELECT 'MU1912', 'BAUT 1/2 18 CM', 'BAUT', '-', 'BTG', 4800.00, 0.000, 1, 'MU1912'
UNION ALL SELECT 'MU1913', 'BAUT 1/2 25 CM', 'BAUT', '-', 'BTG', 6400.00, 0.000, 1, 'MU1913'
UNION ALL SELECT 'MU1914', 'BAUT 1/2 10 CM', 'BAUT', '-', 'BTG', 3200.00, 0.000, 1, 'MU1914'
UNION ALL SELECT 'MU1915', 'MAGIC COM CRJ 323S SWG', 'MAGICCOM', 'COSMOS', 'UNIT', 260000.00, 0.000, 1, 'MU1915'
UNION ALL SELECT 'MU1916', 'MIYAKO MCM 508 BATIK WAYANG', 'MAGICCOM', 'MIYAKO', 'UNIT', 250000.00, 0.000, 1, 'MU1916'
UNION ALL SELECT 'MU1917', 'MINAMI MRC 1880 M', 'RICE COOKER', '-', 'UNIT', 380000.00, 0.000, 1, 'MU1917'
UNION ALL SELECT 'MU1918', 'AVITEX 040 5KG', 'CAT', 'AVITEX', 'GLN', 135000.00, 0.000, 1, 'MU1918'
UNION ALL SELECT 'MU1919', 'AVITEX 522 5KG', 'CAT', 'AVITEX', 'GLN', 135000.00, 0.000, 1, 'MU1919'
UNION ALL SELECT 'MU1920', 'MATA OBENG + BUNGA PZ1X65', 'OBENG', 'WIPRO', 'PCS', 8525.00, 0.000, 1, 'MU1920'
UNION ALL SELECT 'MU1921', 'AVITEX 601 5KG', 'CAT', 'AVITEX', 'GLN', 135000.00, 0.000, 1, 'MU1921'
UNION ALL SELECT 'MU1922', 'AVITEX 710 5KG', 'CAT', 'AVITEX', 'GLN', 135000.00, 0.000, 1, 'MU1922'
UNION ALL SELECT 'MU1923', 'AVITEX 602 5KG', 'CAT', 'AVITEX', 'GLN', 135000.00, 0.000, 1, 'MU1923'
UNION ALL SELECT 'MU1924', 'AVITEX SW 25KG', 'CAT', 'AVITEX', 'PAIL', 711000.00, 0.000, 1, 'MU1924'
UNION ALL SELECT 'MU1925', 'AVITEX 661 5KG', 'CAT', 'AVITEX', 'GLN', 135000.00, 0.000, 1, 'MU1925'
UNION ALL SELECT 'MU1926', 'ARIES GOLD 103 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU1926'
UNION ALL SELECT 'MU1927', 'ARIES GOLD 102 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU1927'
UNION ALL SELECT 'MU1928', 'ARIES GOLD SW 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU1928'
UNION ALL SELECT 'MU1929', 'ARIES GOLD 601 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU1929'
UNION ALL SELECT 'MU1930', 'ARIES GOLD 503 20KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU1930'
UNION ALL SELECT 'MU1931', 'ARIES GOLD 303 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU1931'
UNION ALL SELECT 'MU1932', 'ARIES GOLD 307 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU1932'
UNION ALL SELECT 'MU1933', 'ARIES GOLD 403 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU1933'
UNION ALL SELECT 'MU1934', 'ARIES GOLD 805 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU1934'
UNION ALL SELECT 'MU1935', 'ARIES GOLD 203 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU1935'
UNION ALL SELECT 'MU1936', 'PENUTUP BAK SAKURA', 'MATA SERKEL', 'SAKURA', 'PCS', 8000.00, 0.000, 1, 'MU1936'
UNION ALL SELECT 'MU1937', 'KRAN VNZ BULAT', 'KRAN', 'VNZ', 'PCS', 24000.00, 0.000, 1, 'MU1937'
UNION ALL SELECT 'MU1938', 'KRAN VNZ MOTIF', 'KRAN', 'VNZ', 'PCS', 23200.00, 0.000, 1, 'MU1938'
UNION ALL SELECT 'MU1939', 'AVIAN SYNTHETIC 652', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU1939'
UNION ALL SELECT 'MU1940', 'MINI ROLLER Z-PRO', 'Kuas', 'Z-PRO', 'PCS', 8000.00, 0.000, 1, 'MU1940'
UNION ALL SELECT 'MU1941', 'MINI ROLLER YSK HARDWARES', 'Kuas', 'HARDWARES', 'PCS', 8000.00, 0.000, 1, 'MU1941'
UNION ALL SELECT 'MU1942', 'KUAS ROLL WIPRO', 'Kuas', 'WIPRO', 'PCS', 30000.00, 0.000, 1, 'MU1942'
UNION ALL SELECT 'MU1943', 'SARINGAN LANTAI KM CLASSID', 'SARINGAN', 'CLASSIC', 'PCS', 40000.00, 0.000, 1, 'MU1943'
UNION ALL SELECT 'MU1944', 'SELANG BUANG PLASTIK NOQIWA 2X100M', 'SELANG', 'NOQIWA', 'DUS', 299700.00, 5.000, 1, 'MU1944'
UNION ALL SELECT 'MU1945', 'SELANG BUANG PLASTIK NOQIWA 4X50M', 'SELANG', 'NOQIWA', 'DUS', 344100.00, 5.000, 1, 'MU1945'
UNION ALL SELECT 'MU1946', 'SELANG BUANG PLASTIK NOQIWA 3X50M', 'SELANG', 'NOQIWA', 'DUS', 227550.00, 5.000, 1, 'MU1946'
UNION ALL SELECT 'MU1947', 'SELANG BUANG PLASTIK NOQIWA 4X100M', 'SELANG', 'NOQIWA', 'DUS', 699300.00, 5.000, 1, 'MU1947'
UNION ALL SELECT 'MU1948', 'SELANG BUANG PLASTIK NOQIWA 3X100M', 'SELANG', 'NOQIWA', 'DUS', 427350.00, 5.000, 1, 'MU1948'
UNION ALL SELECT 'MU1949', 'SELANG BUANG PLASTIK NOQIWA 2X50M', 'SELANG', 'NOQIWA', 'DUS', 150960.00, 5.000, 1, 'MU1949'
UNION ALL SELECT 'MU1950', 'SKRUP PUTIH 10X1', 'SKRUP', '-', 'PCS', 0.00, 0.000, 1, 'MU1950'
UNION ALL SELECT 'MU1951', 'ENGSEL GD 3\"', 'ENGSEL', 'IGM', 'PCS', 12800.00, 6.000, 1, 'MU1951'
UNION ALL SELECT 'MU1952', 'KUAS ROLL WIPRO KAPAL', 'Kuas', 'WIPRO', 'PCS', 15000.00, 0.000, 1, 'MU1952'
UNION ALL SELECT 'MU1953', 'SENG TALANG 80CM', 'TALANG', '-', 'MTR', 45000.00, 3.000, 1, 'MU1953'
UNION ALL SELECT 'MU1954', 'JET WASHER CORDLESS 24V', 'MESIN', 'DOZIRO', 'UNIT', 771180.00, 0.000, 1, 'MU1954'
UNION ALL SELECT 'MU1955', 'GRENDEL OVERVAL TEBAL 3\'\'', 'GRENDEL', 'FRANCO', 'PCS', 10833.00, 12.000, 1, 'MU1955'
UNION ALL SELECT 'MU1956', 'TERMINAL KABEL 3L X 5M', 'TERMINAL COK', 'YONGMA', 'PCS', 28200.00, 0.000, 1, 'MU1956'
UNION ALL SELECT 'MU1957', 'MESIN SENSOR STHIL', 'MESIN', 'STIHLL', 'UNIT', 12480000.00, 0.000, 1, 'MU1957'
UNION ALL SELECT 'MU1958', 'MESIN SENSOR MAGNUM', 'MESIN', 'MAGHNUM', 'UNIT', 1512000.00, 0.000, 1, 'MU1958'
UNION ALL SELECT 'MU1959', 'MESIN SENSOR NEWWEST', 'MESIN', 'NEWEST', 'UNIT', 2000000.00, 0.000, 1, 'MU1959'
UNION ALL SELECT 'MU1960', 'POMPA AIR CELUP RED FOX', 'MESIN', 'RED FOX', 'UNIT', 400000.00, 0.000, 1, 'MU1960'
UNION ALL SELECT 'MU1961', 'TEE 4\'X3\'', 'SAMBUNGAN', 'POWER', 'PCS', 43070.00, 0.000, 1, 'MU1961'
UNION ALL SELECT 'MU1962', 'POT HJ 1040', 'POT', 'DENALI', 'PCS', 16000.00, 0.000, 1, 'MU1962'
UNION ALL SELECT 'MU1963', 'POT HJ 1010', 'POT', 'DENALI', 'PCS', 8000.00, 0.000, 1, 'MU1963'
UNION ALL SELECT 'MU1964', 'POT HJ 1020', 'POT', 'DENALI', 'PCS', 8000.00, 0.000, 1, 'MU1964'
UNION ALL SELECT 'MU1965', 'POT HJ 1050', 'POT', 'DENALI', 'PCS', 25600.00, 0.000, 1, 'MU1965'
UNION ALL SELECT 'MU1966', 'KIPAS BERDIRI COSMOS 16-SN', 'KIPAS', 'COSMOS', 'UNIT', 295000.00, 1.000, 1, 'MU1966'
UNION ALL SELECT 'MU1967', 'CUSHION RUBBER COMP', 'MATA SERKEL', 'NOQIWA', 'PCS', 8800.00, 6.000, 1, 'MU1967'
UNION ALL SELECT 'MU1968', 'ON/OFF SWITCH', 'MATA SERKEL', 'NOQIWA', 'PCS', 12000.00, 6.000, 1, 'MU1968'
UNION ALL SELECT 'MU1969', 'WATER MUR 3/4', 'SAMBUNGAN', 'JAYA', 'PCS', 3685.00, 12.000, 1, 'MU1969'
UNION ALL SELECT 'MU1970', 'TERMINAL KABEL 4L X 3M', 'TERMINAL COK', 'HONDA', 'PCS', 25200.00, 0.000, 1, 'MU1970'
UNION ALL SELECT 'MU1971', 'SEKOP GALI SPADAN', 'SEKOP', 'CROCODILE', 'PCS', 39583.00, 12.000, 1, 'MU1971'
UNION ALL SELECT 'MU1972', 'SEKOP CAP MATA PAPAK', 'SEKOP', 'SCHLIEPER', 'PCS', 87500.00, 0.000, 1, 'MU1972'
UNION ALL SELECT 'MU1973', 'SEKOP CAP MATA LANCIP', 'SEKOP', 'SANXUE', 'PCS', 87500.00, 12.000, 1, 'MU1973'
UNION ALL SELECT 'MU1974', 'SELANG SPIRAL 2 INCI', 'SELANG', '-', 'MTR', 45600.00, 5.000, 1, 'MU1974'
UNION ALL SELECT 'MU1975', 'SELANG SPIRAL 3 INCI', 'SELANG', '-', 'MTR', 68800.00, 5.000, 1, 'MU1975'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU1976' AS sku, 'SELANG SPIRAL 4 INCI' AS name, 'SELANG' AS category_name, '-' AS brand_name, 'MTR' AS base_unit_code, 96000.00 AS cost_price, 5.000 AS min_stock, 1 AS is_active, 'MU1976' AS legacy_sku
UNION ALL SELECT 'MU1977', 'HANDSPRAYER NOQIWA 2 TAK 20L', 'MESIN', 'NOQIWA', 'UNIT', 1320333.00, 9.000, 1, 'MU1977'
UNION ALL SELECT 'MU1978', 'TUTUP PIPA 2 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 10570.00, 0.000, 1, 'MU1978'
UNION ALL SELECT 'MU1979', 'KUAS ARCEL 5\'', 'Kuas', 'ARCEL', 'PCS', 20000.00, 0.000, 1, 'MU1979'
UNION ALL SELECT 'MU1980', 'MATA GURINDA BOSCH', 'MATA GURINDA', 'BOSCH', 'PCS', 12000.00, 0.000, 1, 'MU1980'
UNION ALL SELECT 'MU1981', 'OVER SOCK 1 X 3/4', 'SAMBUNGAN', 'POWER', 'PCS', 3096.00, 0.000, 1, 'MU1981'
UNION ALL SELECT 'MU1982', 'MATA GURINDA DWTF-105', 'MATA GURINDA', 'WIPRO', 'PCS', 49500.00, 0.000, 1, 'MU1982'
UNION ALL SELECT 'MU1983', 'HAK ANGIN BIASA KWK', 'HAK ANGIN', '-', 'PCS', 1750.00, 0.000, 1, 'MU1983'
UNION ALL SELECT 'MU1984', 'ELASTEX GREY', 'CAT', 'NIPPON PAINT', 'PCS', 254000.00, 0.000, 1, 'MU1984'
UNION ALL SELECT 'MU1985', 'MELAMIC WOOD FILLER WATER BASE', 'DEMPUL', 'NIPPON PAINT', 'KLG', 38000.00, 0.000, 1, 'MU1985'
UNION ALL SELECT 'MU1986', 'COPPER ANTI FOULING', 'DEMPUL', 'NIPPON PAINT', 'KLG', 92685.00, 0.000, 1, 'MU1986'
UNION ALL SELECT 'MU1987', 'RANTAI PVC 6MM', 'RANTAI', 'TORPEDO LEVEL', 'MTR', 8500.00, 6.000, 1, 'MU1987'
UNION ALL SELECT 'MU1988', 'KRAN ANGSA BANO HITAM', 'KRAN', 'BANO', 'PCS', 22400.00, 0.000, 1, 'MU1988'
UNION ALL SELECT 'MU1989', 'GANTUNGAN KAMAR MANDI', 'GANTUNGAN', 'ARCEL', 'PCS', 205600.00, 0.000, 1, 'MU1989'
UNION ALL SELECT 'MU1990', 'TOWEL RACK STAINLESS', 'GANTUNGAN', 'STAINLISSTEEL', 'PCS', 237600.00, 0.000, 1, 'MU1990'
UNION ALL SELECT 'MU1991', 'PAKU BETON 7CM', 'Paku', 'FRT', 'PCS', 16000.00, 0.000, 1, 'MU1991'
UNION ALL SELECT 'MU1992', 'GERGAJI CAP MATA', 'GERGAJI', 'SCHLIEPER', 'PCS', 80000.00, 6.000, 1, 'MU1992'
UNION ALL SELECT 'MU1993', 'GEMBOK ORYX 30MM PANJANG', 'V-BELT', 'ORYX', 'PCS', 10400.00, 0.000, 1, 'MU1993'
UNION ALL SELECT 'MU1994', 'AVIAN SYNTHETIC 194', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU1994'
UNION ALL SELECT 'MU1995', 'AVIAN SYNTHETIC 330', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU1995'
UNION ALL SELECT 'MU1996', 'GEMBOK ORYX 60MM PENDEK', 'V-BELT', 'ORYX', 'PCS', 56000.00, 0.000, 1, 'MU1996'
UNION ALL SELECT 'MU1997', 'AVIAN SYNTHETIC 631', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU1997'
UNION ALL SELECT 'MU1998', 'AVIAN SYNTHETIC 660', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU1998'
UNION ALL SELECT 'MU1999', 'GEMBOK ORYX 30MM PENDEK', 'V-BELT', 'ORYX', 'PCS', 10400.00, 0.000, 1, 'MU1999'
UNION ALL SELECT 'MU2000', 'CAT NO DROP 009', 'PAKING', 'NO DROP', 'POT', 275000.00, 0.000, 1, 'MU2000'
UNION ALL SELECT 'MU2001', 'CAT NO DROP 010', 'PAKING', 'NO DROP', 'POT', 275000.00, 0.000, 1, 'MU2001'
UNION ALL SELECT 'MU2002', 'CAT NO DROP 002', 'PAKING', 'NO DROP', 'POT', 275000.00, 0.000, 1, 'MU2002'
UNION ALL SELECT 'MU2003', 'CAT NO DROP 022', 'PAKING', 'NO DROP', 'POT', 275000.00, 0.000, 1, 'MU2003'
UNION ALL SELECT 'MU2004', 'PILLOX S-315', 'PILLOX', 'SUZUKA', 'PCS', 23200.00, 0.000, 1, 'MU2004'
UNION ALL SELECT 'MU2005', 'PILLOX SS-2033', 'PILLOX', 'SUZUKA', 'PCS', 31200.00, 0.000, 1, 'MU2005'
UNION ALL SELECT 'MU2006', 'PILLOX SS-2037', 'PILLOX', 'SUZUKA', 'PCS', 31200.00, 0.000, 1, 'MU2006'
UNION ALL SELECT 'MU2007', 'PILLOX SS-2051', 'PILLOX', 'SUZUKA', 'PCS', 31200.00, 0.000, 1, 'MU2007'
UNION ALL SELECT 'MU2008', 'PILLOX SS-2050', 'PILLOX', 'SUZUKA', 'PCS', 31200.00, 12.000, 1, 'MU2008'
UNION ALL SELECT 'MU2009', 'PILLOX S-222', 'PILLOX', 'SUZUKA', 'PCS', 23200.00, 0.000, 1, 'MU2009'
UNION ALL SELECT 'MU2010', 'PILLOX S-472', 'PILLOX', 'SUZUKA', 'PCS', 23200.00, 0.000, 1, 'MU2010'
UNION ALL SELECT 'MU2011', 'PILLOX S-41', 'PILLOX', 'SUZUKA', 'PCS', 23200.00, 0.000, 1, 'MU2011'
UNION ALL SELECT 'MU2012', 'PILLOX S-470', 'PILLOX', 'SUZUKA', 'PCS', 23200.00, 12.000, 1, 'MU2012'
UNION ALL SELECT 'MU2013', 'PILLOX S-480M', 'PILLOX', 'SUZUKA', 'PCS', 23200.00, 12.000, 1, 'MU2013'
UNION ALL SELECT 'MU2014', 'PILLOX S-480', 'PILLOX', 'SUZUKA', 'PCS', 23200.00, 0.000, 1, 'MU2014'
UNION ALL SELECT 'MU2015', 'PILLOX S-033', 'PILLOX', 'SUZUKA', 'PCS', 23200.00, 0.000, 1, 'MU2015'
UNION ALL SELECT 'MU2016', 'PILLOX SM-133', 'PILLOX', 'SUZUKA', 'PCS', 23200.00, 0.000, 1, 'MU2016'
UNION ALL SELECT 'MU2017', 'PILLOX SF-1', 'PILLOX', 'SUZUKA', 'PCS', 31200.00, 0.000, 1, 'MU2017'
UNION ALL SELECT 'MU2018', 'PILLOX SF-2', 'PILLOX', 'SUZUKA', 'PCS', 31200.00, 0.000, 1, 'MU2018'
UNION ALL SELECT 'MU2019', 'PILLOX SM-110', 'PILLOX', 'SUZUKA', 'PCS', 23200.00, 0.000, 1, 'MU2019'
UNION ALL SELECT 'MU2020', 'PILLOX SM-115', 'PILLOX', 'SUZUKA', 'PCS', 23200.00, 0.000, 1, 'MU2020'
UNION ALL SELECT 'MU2021', 'PILLOX SM-109', 'PILLOX', 'SUZUKA', 'PCS', 23200.00, 12.000, 1, 'MU2021'
UNION ALL SELECT 'MU2022', 'PILLOX SM-116', 'PILLOX', 'SUZUKA', 'PCS', 23200.00, 0.000, 1, 'MU2022'
UNION ALL SELECT 'MU2023', 'PILLOX SM-205', 'PILLOX', 'SUZUKA', 'PCS', 23200.00, 0.000, 1, 'MU2023'
UNION ALL SELECT 'MU2024', 'ARIES 641 5KG', 'CAT', 'ARIES', 'POT', 60400.00, 0.000, 1, 'MU2024'
UNION ALL SELECT 'MU2025', 'ARIES 683 5KG', 'CAT', 'ARIES', 'POT', 60400.00, 0.000, 1, 'MU2025'
UNION ALL SELECT 'MU2026', 'TINNER AVIA A 5L', 'TINNER', 'Avian', 'KLG', 124000.00, 0.000, 1, 'MU2026'
UNION ALL SELECT 'MU2027', 'ARIES 686 5KG', 'CAT', 'ARIES', 'POT', 60400.00, 0.000, 1, 'MU2027'
UNION ALL SELECT 'MU2028', 'BOYO POLITUR 610 1LTR', 'VERNIS', 'BOYO', 'KLG', 86000.00, 6.000, 1, 'MU2028'
UNION ALL SELECT 'MU2029', 'BOYO POLITUR 610 0,5L', 'VERNIS', 'BOYO', 'KLG', 35000.00, 0.000, 1, 'MU2029'
UNION ALL SELECT 'MU2030', 'SODA API 1KG', 'PEMBERSIH', 'Avian', 'KLG', 82900.00, 0.000, 1, 'MU2030'
UNION ALL SELECT 'MU2031', 'SODA API 250 GRAM', 'PEMBERSIH', 'Avian', 'KLG', 32100.00, 0.000, 1, 'MU2031'
UNION ALL SELECT 'MU2032', 'BASKOM NAGATA', 'BASKOM', 'NAGATA', 'PCS', 0.00, 0.000, 1, 'MU2032'
UNION ALL SELECT 'MU2033', 'SUZUKA CAT 1KG S470', 'PAKING', 'SUZUKA', 'KLG', 87000.00, 0.000, 1, 'MU2033'
UNION ALL SELECT 'MU2034', 'SUZUKA CAT 1KG S480', 'PAKING', 'SUZUKA', 'KLG', 85000.00, 0.000, 1, 'MU2034'
UNION ALL SELECT 'MU2035', 'SUZUKA CAT SM116 200CC', 'MATA SERKEL', 'WIPRO', 'PCS', 30000.00, 0.000, 1, 'MU2035'
UNION ALL SELECT 'MU2036', 'TALAM ROTI KOTAK', 'CETAKAN', 'STAINLISSTEEL', 'PCS', 32000.00, 0.000, 1, 'MU2036'
UNION ALL SELECT 'MU2037', 'CAT SUZUKA SS2037 200CC', 'CAT', 'SUZUKA', 'KLG', 28000.00, 0.000, 1, 'MU2037'
UNION ALL SELECT 'MU2038', 'CAT SUZUKA SS2050 200CC', 'CAT', 'SUZUKA', 'KLG', 28000.00, 0.000, 1, 'MU2038'
UNION ALL SELECT 'MU2039', 'SUZUKA CAT S033 200CC', 'CAT', 'SUZUKA', 'KLG', 23000.00, 0.000, 1, 'MU2039'
UNION ALL SELECT 'MU2040', 'CAT SUZUKA SM110 200CC', 'CAT', 'SUZUKA', 'KLG', 24000.00, 0.000, 1, 'MU2040'
UNION ALL SELECT 'MU2041', 'CAT SUZUKA S315 200CC', 'CAT', 'SUZUKA', 'KLG', 20000.00, 0.000, 1, 'MU2041'
UNION ALL SELECT 'MU2042', 'CAT SUZUKA SM115 200CC', 'CAT', 'SUZUKA', 'KLG', 24000.00, 0.000, 1, 'MU2042'
UNION ALL SELECT 'MU2043', 'CAT SUZUKA SM109 200CC', 'CAT', 'SUZUKA', 'KLG', 24000.00, 0.000, 1, 'MU2043'
UNION ALL SELECT 'MU2044', 'CAT DASAR 5400 NIPPON', 'CAT', 'NIPPON PAINT', 'GLN', 130000.00, 0.000, 1, 'MU2044'
UNION ALL SELECT 'MU2045', 'TEER SPECIAL 1 KG', 'THINNER', 'TEER', 'KLG', 26400.00, 0.000, 1, 'MU2045'
UNION ALL SELECT 'MU2046', 'HI GLOSS PU CLEAR 1L', 'CAT', 'NIPPON PAINT', 'KLG', 145159.00, 0.000, 1, 'MU2046'
UNION ALL SELECT 'MU2047', 'KEPALA BOR M/SOCK 13MM', 'S/PART', 'WIPRO', 'PCS', 75000.00, 2.000, 1, 'MU2047'
UNION ALL SELECT 'MU2048', 'WARLOCK CX9', 'KUNCI PINTU', 'WARLOCK', 'SET', 119200.00, 0.000, 1, 'MU2048'
UNION ALL SELECT 'MU2049', 'WARLOCK CX3', 'KUNCI PINTU', 'WARLOCK', 'SET', 119200.00, 0.000, 1, 'MU2049'
UNION ALL SELECT 'MU2050', 'ELBRIS C3161', 'KUNCI PINTU', 'ELBRIS', 'SET', 112000.00, 0.000, 1, 'MU2050'
UNION ALL SELECT 'MU2051', 'KUNCI PINTU KENZO GERMAN', 'KUNCI PINTU', 'KENZO', 'SET', 149600.00, 2.000, 1, 'MU2051'
UNION ALL SELECT 'MU2052', 'KUNCI PINTU KENZO ITALY', 'KUNCI PINTU', 'KENZO', 'SET', 149600.00, 2.000, 1, 'MU2052'
UNION ALL SELECT 'MU2053', 'MATA GURINDA GOSOK WIPRO MAX', 'MATA GURINDA', 'WIPRO', 'PCS', 12000.00, 0.000, 1, 'MU2053'
UNION ALL SELECT 'MU2054', 'KUNCI PINTU FAWI', 'KUNCI PINTU', 'MULLER', 'SET', 80000.00, 0.000, 1, 'MU2054'
UNION ALL SELECT 'MU2055', 'AS RODA RUSH', 'S/PART', 'RUSA HITAM', 'PCS', 8500.00, 0.000, 1, 'MU2055'
UNION ALL SELECT 'MU2056', 'PERATA KERAMIK', 'ALAT', 'KUDA TERBANG', 'SET', 37500.00, 0.000, 1, 'MU2056'
UNION ALL SELECT 'MU2057', 'KRAN SOLIGEN 3/4', 'KRAN', 'SOLIGEN', 'PCS', 35000.00, 0.000, 1, 'MU2057'
UNION ALL SELECT 'MU2058', 'ELBRIS C3199', 'KUNCI PINTU', 'ELBRIS', 'SET', 112000.00, 0.000, 1, 'MU2058'
UNION ALL SELECT 'MU2059', 'VRZ B08S', 'KUNCI PINTU', 'VRZ', 'SET', 120000.00, 0.000, 1, 'MU2059'
UNION ALL SELECT 'MU2060', 'WARLOCK CX5', 'KUNCI PINTU', 'WARLOCK', 'SET', 119200.00, 0.000, 1, 'MU2060'
UNION ALL SELECT 'MU2061', 'KUNCI PINTU BOSHINI', 'KUNCI PINTU', 'BOSHINI', 'SET', 48000.00, 0.000, 1, 'MU2061'
UNION ALL SELECT 'MU2062', 'JAYA MULYA 121', 'TARIKAN PINTU', 'FRANCO', 'SET', 61600.00, 0.000, 1, 'MU2062'
UNION ALL SELECT 'MU2063', 'PEGANGAN PINTU FRANCO 122', 'KUNCI PINTU', 'FRANCO', 'SET', 61600.00, 0.000, 1, 'MU2063'
UNION ALL SELECT 'MU2064', 'PEGANGAN PINTU NTR 121', 'TARIKAN PINTU', 'JAYA MULIA', 'SET', 61600.00, 0.000, 1, 'MU2064'
UNION ALL SELECT 'MU2065', 'PEGANGAN PINTU NTR 127', 'TARIKAN PINTU', 'JAYA MULIA', 'SET', 61600.00, 0.000, 1, 'MU2065'
UNION ALL SELECT 'MU2067', 'EPIC 9282 PUTIH', 'KUNCI PINTU', 'EPIC', 'SET', 108000.00, 0.000, 1, 'MU2067'
UNION ALL SELECT 'MU2068', 'PAPAN GOSOK BESI', 'KAPI', 'FUKUDA', 'PCS', 24000.00, 0.000, 1, 'MU2068'
UNION ALL SELECT 'MU2069', 'PAPAN GOSOK PLASTIK', 'KAPI', '-', 'PCS', 16000.00, 0.000, 1, 'MU2069'
UNION ALL SELECT 'MU2070', 'SENDOK SEMEN GOOD CAP TANGAN', 'SENDOK SEMEN', 'CAP TANGAN', 'PCS', 12000.00, 0.000, 1, 'MU2070'
UNION ALL SELECT 'MU2071', 'KAPI BESI NP 4\"', 'KAPI', 'NIPPON PAINT', 'PCS', 12600.00, 0.000, 1, 'MU2071'
UNION ALL SELECT 'MU2072', 'KAPI BESI NP 5\"', 'KAPI', 'NIPPON PAINT', 'PCS', 13600.00, 0.000, 1, 'MU2072'
UNION ALL SELECT 'MU2073', 'SODA API VTECH 250 ML', 'CAT', 'VTECH', 'KLG', 36140.00, 0.000, 1, 'MU2073'
UNION ALL SELECT 'MU2074', 'PISAU DEMPUL KURA', 'KAPI', 'KURA', 'PCS', 22400.00, 0.000, 1, 'MU2074'
UNION ALL SELECT 'MU2075', 'PISAU DEMPUL POLYSTAR 5', 'KAPI', 'POLYSTAR', 'PCS', 4000.00, 0.000, 1, 'MU2075'
UNION ALL SELECT 'MU2076', 'PISAU DEMPUL POLYSTAR 9', 'KAPI', 'POLYSTAR', 'PCS', 8000.00, 0.000, 1, 'MU2076'
UNION ALL SELECT 'MU2077', 'DODOS 2KG', 'DODOS', '-', 'PCS', 60000.00, 0.000, 1, 'MU2077'
UNION ALL SELECT 'MU2078', 'PISAU DEMPUL POLYSTAR 11', 'KAPI', 'POLYSTAR', 'PCS', 8000.00, 24.000, 1, 'MU2078'
UNION ALL SELECT 'MU2079', 'ARIES EMULSION 643 20KG', 'CAT', 'ARIES', 'PAIL', 225200.00, 0.000, 1, 'MU2079'
UNION ALL SELECT 'MU2080', 'ARIES EMULSION 691 20KG', 'CAT', 'ARIES', 'PAIL', 225200.00, 0.000, 1, 'MU2080'
UNION ALL SELECT 'MU2081', 'ARIES EMULSION 690 20KG', 'CAT', 'ARIES', 'PAIL', 225200.00, 0.000, 1, 'MU2081'
UNION ALL SELECT 'MU2082', 'ARIES EMULSION 660 20KG', 'CAT', 'ARIES', 'PAIL', 225200.00, 0.000, 1, 'MU2082'
UNION ALL SELECT 'MU2083', 'ARIES EMULSION 624 20KG', 'CAT', 'ARIES', 'PAIL', 225200.00, 0.000, 1, 'MU2083'
UNION ALL SELECT 'MU2084', 'ARIES EMULSION  694 20KG', 'CAT', 'ARIES', 'PAIL', 225200.00, 0.000, 1, 'MU2084'
UNION ALL SELECT 'MU2085', 'ARIES EMULSION 681 20KG', 'CAT', 'ARIES', 'PAIL', 225200.00, 0.000, 1, 'MU2085'
UNION ALL SELECT 'MU2086', 'ARIES EMULSION 686 20KG', 'CAT', 'ARIES', 'PAIL', 225200.00, 0.000, 1, 'MU2086'
UNION ALL SELECT 'MU2087', 'TOPLES SEGI 4 9L', 'TOPLES', 'SEAL WARE', 'PCS', 27200.00, 0.000, 1, 'MU2087'
UNION ALL SELECT 'MU2088', 'ARIES GOLD 404 20KG', 'CAT', 'ARIES GOLD', 'PAIL', 357700.00, 0.000, 1, 'MU2088'
UNION ALL SELECT 'MU2089', 'ARIES GOLD 505 20KG', 'CAT', 'ARIES GOLD', 'PAIL', 357700.00, 0.000, 1, 'MU2089'
UNION ALL SELECT 'MU2090', 'ARIES GOLD 502 20KG', 'CAT', 'ARIES', 'PAIL', 357700.00, 0.000, 1, 'MU2090'
UNION ALL SELECT 'MU2091', 'ARIES GOLD 808 5KG', 'CAT', 'ARIES GOLD', 'GLN', 84000.00, 2.000, 1, 'MU2091'
UNION ALL SELECT 'MU2092', 'ARIES GOLD 302 20KG', 'CAT', 'ARIES GOLD', 'PAIL', 357700.00, 0.000, 1, 'MU2092'
UNION ALL SELECT 'MU2093', 'SELANG MILLIARD 1\'', 'SELANG', 'MILLIARD', 'MTR', 12800.00, 0.000, 1, 'MU2093'
UNION ALL SELECT 'MU2094', 'SELANG MILLIARD 3/4', 'SELANG', 'MILLIARD', 'MTR', 10400.00, 0.000, 1, 'MU2094'
UNION ALL SELECT 'MU2095', 'SELANG MILLIARD 5/8', 'SELANG', 'MILLIARD', 'MTR', 9600.00, 0.000, 1, 'MU2095'
UNION ALL SELECT 'MU2096', 'SELANG MILLIARD 1/2', 'SELANG', 'MILLIARD', 'MTR', 8800.00, 0.000, 1, 'MU2096'
UNION ALL SELECT 'MU2097', 'SELANG WATERPASS', 'SELANG', 'MILLIARD', 'MTR', 4800.00, 0.000, 1, 'MU2097'
UNION ALL SELECT 'MU2098', 'SELANG MILLIARD SUPERFLEX 5/8', 'SELANG', 'MILLIARD', '50 METER', 410000.00, 0.000, 1, 'MU2098'
UNION ALL SELECT 'MU2099', 'SELANG MILLIARD SUPERFLEX 3/4', 'SELANG', 'MILLIARD', '50 METER', 590000.00, 0.000, 1, 'MU2099'
UNION ALL SELECT 'MU2100', 'SELANG WATERPAS TR 1/4', 'SELANG', 'MILLIARD', '100 METER', 126000.00, 0.000, 1, 'MU2100'
UNION ALL SELECT 'MU2101', 'SELANG BENANG 1\'', 'SELANG', 'SEA GULL', 'MTR', 30000.00, 0.000, 1, 'MU2101'
UNION ALL SELECT 'MU2102', 'SELANG BENANG 3/4', 'SELANG', 'SEA GULL', 'MTR', 22000.00, 0.000, 1, 'MU2102'
UNION ALL SELECT 'MU2103', 'SELANG BENANG 5/8', 'SELANG', 'SEA GULL', 'MTR', 17000.00, 0.000, 1, 'MU2103'
UNION ALL SELECT 'MU2104', 'SELANG BENANG 2', 'SELANG', 'SEA GULL', 'MTR', 54400.00, 0.000, 1, 'MU2104'
UNION ALL SELECT 'MU2105', 'SELANG BENANG 1 1/2', 'SELANG', 'SEA GULL', 'MTR', 39200.00, 0.000, 1, 'MU2105'
UNION ALL SELECT 'MU2106', 'BAK CAT HITAM', 'MATA SERKEL', '-', 'PCS', 16000.00, 0.000, 1, 'MU2106'
UNION ALL SELECT 'MU2107', 'MESRAN OLI SAE 40 4LTR', 'OLI', 'MESRAN', 'GEN', 195667.00, 0.000, 1, 'MU2107'
UNION ALL SELECT 'MU2108', 'MEDITRAN OLI SX 4LTR', 'OLI', 'MEDITRAN', 'GEN', 247883.00, 0.000, 1, 'MU2108'
UNION ALL SELECT 'MU2109', 'OLI CAT 1LTR', 'OLI', 'CAT', 'BTL', 0.00, 0.000, 1, 'MU2109'
UNION ALL SELECT 'MU2110', 'OLI CAT 5LTR', 'OLI', 'CAT', 'GEN', 0.00, 0.000, 1, 'MU2110'
UNION ALL SELECT 'MU2111', 'OLI RORED 90 5LTR', 'OLI', 'RORED', 'GEN', 290000.00, 0.000, 1, 'MU2111'
UNION ALL SELECT 'MU2112', 'OLI RORED 140 5LTR', 'OLI', 'RORED', 'GEN', 313000.00, 0.000, 1, 'MU2112'
UNION ALL SELECT 'MU2113', 'KUKU MACAN POLOS PANJANG', 'KUKU MACAN', '-', 'PCS', 2400.00, 0.000, 1, 'MU2113'
UNION ALL SELECT 'MU2114', 'KUKU MACAN MOTIF PANJANG', 'KUKU MACAN', '-', 'PCS', 3200.00, 0.000, 1, 'MU2114'
UNION ALL SELECT 'MU2115', 'SELANG KOMPRESOR PANJANG', 'SELANG', 'ALDO', 'PCS', 144000.00, 0.000, 1, 'MU2115'
UNION ALL SELECT 'MU2116', 'SELANG KOMPRESOR TANGGUNG', 'SELANG', 'ALDO', 'PCS', 116000.00, 0.000, 1, 'MU2116'
UNION ALL SELECT 'MU2117', 'SELANG KOMPRESOR PENDEK', 'SELANG', 'ALDO', 'PCS', 88000.00, 0.000, 1, 'MU2117'
UNION ALL SELECT 'MU2118', 'PENYEDOT TIMAH STS-102', 'MATA SERKEL', 'WIPRO', 'PCS', 44000.00, 0.000, 1, 'MU2118'
UNION ALL SELECT 'MU2119', 'GUNTING TANAMAN MULLER ML-110', 'GUNTING', 'MULLER', 'PCS', 48000.00, 6.000, 1, 'MU2119'
UNION ALL SELECT 'MU2120', 'KRAN ANGIN 1/4', 'KRAN', 'FRT', 'PCS', 30400.00, 0.000, 1, 'MU2120'
UNION ALL SELECT 'MU21200', 'STOP KONTAK TEMPEL TUNGGAL BROCO', 'MATA SERKEL', 'BROCO', 'PCS', 16000.00, 12.000, 1, 'MU21200'
UNION ALL SELECT 'MU2121', 'SELANG BENANG 1/2\" TRILLIUN', 'SLANG', '-', 'MTR', 0.00, 0.000, 1, 'MU2121'
UNION ALL SELECT 'MU2122', 'KIPAS MEJA COSMOS 12-LDA TWINO', 'KIPAS', 'COSMOS', 'UNIT', 220000.00, 1.000, 1, 'MU2122'
UNION ALL SELECT 'MU2123', 'FITTING TANAM PANASONIC', 'FITING', 'PANASONIC', 'PCS', 48000.00, 0.000, 1, 'MU2123'
UNION ALL SELECT 'MU2124', 'FITING TANAM PRIMAX', 'FITING', 'PRIMAX', 'PCS', 32000.00, 0.000, 1, 'MU2124'
UNION ALL SELECT 'MU2125', 'KAP LAMPU', 'ALAT', '-', 'PCS', 20000.00, 0.000, 1, 'MU2125'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU2126' AS sku, 'ISOLASI KABEL NATIONAL' AS name, 'SOLASI' AS category_name, 'NATIONAL' AS brand_name, 'PCS' AS base_unit_code, 8000.00 AS cost_price, 30.000 AS min_stock, 1 AS is_active, 'MU2126' AS legacy_sku
UNION ALL SELECT 'MU2127', 'KLEM KABEL IMUNDEX 6MM', 'KLEM', 'IMUNDEX', 'DUS', 8000.00, 0.000, 1, 'MU2127'
UNION ALL SELECT 'MU2128', 'STEKER TANAM BROCO', 'STEKER', 'BROCO', 'PCS', 13600.00, 0.000, 1, 'MU2128'
UNION ALL SELECT 'MU2129', 'STEKER SHI GEN', 'STEKER', 'PANASONIC', 'PCS', 10400.00, 0.000, 1, 'MU2129'
UNION ALL SELECT 'MU2130', 'OLI HIDROLIK SAE 10', 'OLI', 'MESRAN', 'LTR', 42000.00, 0.000, 1, 'MU2130'
UNION ALL SELECT 'MU2131', 'ELBOW 5/8', 'SAMBUNGAN', '-', 'PCS', 800.00, 0.000, 1, 'MU2131'
UNION ALL SELECT 'MU2132', 'SOCK KRAN 1/2', 'SAMBUNGAN', '-', 'PCS', 1802.00, 0.000, 1, 'MU2132'
UNION ALL SELECT 'MU2133', 'SOCK 1/2 KABEL', 'SAMBUNGAN', '-', 'PCS', 800.00, 0.000, 1, 'MU2133'
UNION ALL SELECT 'MU2134', 'KLEM PIPA KABEL 20MM', 'KLEM', '-', 'PCS', 400.00, 0.000, 1, 'MU2134'
UNION ALL SELECT 'MU2135', 'SAMBUNGAN CABANG 4', 'SAMBUNGAN', '-', 'PCS', 4000.00, 0.000, 1, 'MU2135'
UNION ALL SELECT 'MU2136', 'SAKLAR TUNGGAL HTM TEMPEL', 'SAKLAR', 'BROCO', 'PCS', 10000.00, 0.000, 1, 'MU2136'
UNION ALL SELECT 'MU2137', 'KLEM PIPA KABEL 5/8', 'KLEM', '-', 'PCS', 800.00, 0.000, 1, 'MU2137'
UNION ALL SELECT 'MU2138', 'KLEM PIPA KABEL 1', 'KLEM', '-', 'PCS', 2400.00, 0.000, 1, 'MU2138'
UNION ALL SELECT 'MU2139', 'KLEM PIPA KABEL 1/2', 'KLEM', '-', 'PCS', 800.00, 0.000, 1, 'MU2139'
UNION ALL SELECT 'MU2140', 'KLEM PIPA KABEL 3/4', 'KLEM', '-', 'PCS', 1600.00, 0.000, 1, 'MU2140'
UNION ALL SELECT 'MU2141', 'GUNTING RANTING TELESCOPIC 100CM', 'GUNTING', '-', 'PCS', 195000.00, 0.000, 1, 'MU2141'
UNION ALL SELECT 'MU2142', 'TIMBANGAN BADAN 130KG', 'TIMBANGAN', '-', 'UNIT', 168000.00, 0.000, 1, 'MU2142'
UNION ALL SELECT 'MU2143', 'HANDSPRAYER SUMURA 2 FUNGSI', 'MESIN', 'DRAGON', 'UNIT', 520000.00, 5.000, 1, 'MU2143'
UNION ALL SELECT 'MU2144', 'TIMBANGAN BARANG 5KG', 'TIMBANGAN', 'FRT', 'UNIT', 232000.00, 3.000, 1, 'MU2144'
UNION ALL SELECT 'MU2145', 'TIMBANGAN BARANG 10KG', 'TIMBANGAN', 'FRT', 'UNIT', 236000.00, 0.000, 1, 'MU2145'
UNION ALL SELECT 'MU2146', 'TIMBANGAN BARANG 15KG', 'TIMBANGAN', 'FRT', 'UNIT', 240000.00, 0.000, 1, 'MU2146'
UNION ALL SELECT 'MU2147', 'LEM G/KOREA', 'LEM', 'G', 'PCS', 8000.00, 24.000, 1, 'MU2147'
UNION ALL SELECT 'MU2148', 'LEM CASTOL KECIL', 'PEL LANTAI', 'CASTOL', 'PCS', 11200.00, 0.000, 1, 'MU2148'
UNION ALL SELECT 'MU2149', 'LEM CASTOL BESAR', 'PEL LANTAI', 'CASTOL', 'PCS', 14400.00, 0.000, 1, 'MU2149'
UNION ALL SELECT 'MU2150', 'KACAMATA SAFETY', 'KACAMATA', 'WIPRO', 'PCS', 20000.00, 0.000, 1, 'MU2150'
UNION ALL SELECT 'MU2151', 'KACA SPION MOTOR', 'KACA', 'ITC', 'PCS', 20000.00, 0.000, 1, 'MU2151'
UNION ALL SELECT 'MU2152', 'BATRAI ALKALINE', 'BATRE', 'ALKALINE', 'SET', 8000.00, 0.000, 1, 'MU2152'
UNION ALL SELECT 'MU2153', 'KUNCI PINTU 388L NB/GP', 'KUNCI PINTU', 'SANSHAN', 'SET', 115000.00, 0.000, 1, 'MU2153'
UNION ALL SELECT 'MU2154', 'TOPENG LAS+KACA (HITAM&PUTIH)', 'TOPENG', '-', 'PCS', 32000.00, 0.000, 1, 'MU2154'
UNION ALL SELECT 'MU2155', 'POMPA TANGAN IP-018 425X37MM', 'POMPA', 'IGM', 'PCS', 50000.00, 0.000, 1, 'MU2155'
UNION ALL SELECT 'MU2156', 'POMPA TANGAN HAND PUMP', 'POMPA', 'HAND PUMP', 'PCS', 64000.00, 0.000, 1, 'MU2156'
UNION ALL SELECT 'MU2157', 'KRAN SELERY', 'KRAN', 'SELLERY', 'PCS', 28000.00, 0.000, 1, 'MU2157'
UNION ALL SELECT 'MU2158', 'KRAN VICO', 'KRAN', 'VICO', 'PCS', 21600.00, 0.000, 1, 'MU2158'
UNION ALL SELECT 'MU2159', 'TANG KOMBINASI 6\" TKC-150', 'TANG', 'WIPRO', 'PCS', 33000.00, 0.000, 1, 'MU2159'
UNION ALL SELECT 'MU2160', 'TANG LANCIP 6\" TLC-150', 'TANG', 'WIPRO', 'PCS', 30250.00, 0.000, 1, 'MU2160'
UNION ALL SELECT 'MU2161', 'GUNTING KODOK NEVADA', 'GUNTING', 'NEVADA', 'PCS', 16000.00, 12.000, 1, 'MU2161'
UNION ALL SELECT 'MU2162', 'AIR DUSTER AD10', 'SEMPROTAN', 'BENZ', 'PCS', 20000.00, 0.000, 1, 'MU2162'
UNION ALL SELECT 'MU2163', 'KUNCI RING PAS WIPRO 15MM', 'V-BELT', 'WIPRO', 'PCS', 29000.00, 6.000, 1, 'MU2163'
UNION ALL SELECT 'MU2164', 'KUNCI L WIPRO 6MM', 'V-BELT', 'WIPRO', 'PCS', 11000.00, 12.000, 1, 'MU2164'
UNION ALL SELECT 'MU2165', 'WIPRO RING PASS 8MM', 'V-BELT', 'WIPRO', 'PCS', 12000.00, 0.000, 1, 'MU2165'
UNION ALL SELECT 'MU2166', 'KUNCI RING PASS LIPPRO 17MM', 'V-BELT', 'LIPPRO', 'PCS', 24000.00, 0.000, 1, 'MU2166'
UNION ALL SELECT 'MU2167', 'SOLDER WIPRO SLC-80', 'SOLDER', 'WIPRO', 'PCS', 136000.00, 0.000, 1, 'MU2167'
UNION ALL SELECT 'MU2168', 'SOLDER WIPRO GAGANG KAYU', 'SOLDER', 'WIPRO', 'PCS', 120000.00, 0.000, 1, 'MU2168'
UNION ALL SELECT 'MU2169', 'HAND SOCKET SET 8-32 RS-24SM', 'V-BELT', 'WIPRO', 'SET', 1000000.00, 0.000, 1, 'MU2169'
UNION ALL SELECT 'MU2170', 'SHAMPO MOBIL', 'SHAMPO', 'CARRER', 'PCS', 16000.00, 0.000, 1, 'MU2170'
UNION ALL SELECT 'MU2171', 'TERMINAL KABEL 4L X 5M', 'TERMINAL COK', 'SAKAI', 'PCS', 30000.00, 0.000, 1, 'MU2171'
UNION ALL SELECT 'MU2172', 'HAND SOCKET 16MM X 1/2\' WIPRO', 'Kunci', 'WIPRO', 'PCS', 12925.00, 0.000, 1, 'MU2172'
UNION ALL SELECT 'MU2173', 'PINTU KAMAR MANDI', 'PINTU', 'SOS', 'SET', 720000.00, 0.000, 1, 'MU2173'
UNION ALL SELECT 'MU2174', 'KEPALA COK BULAT MATSUI MT-300', 'STEKER', 'MATSUI', 'PCS', 5000.00, 12.000, 1, 'MU2174'
UNION ALL SELECT 'MU2175', 'STEKER SAKLAR MATSUI MT-305', 'STEKER', 'MATSUI', 'PCS', 11000.00, 12.000, 1, 'MU2175'
UNION ALL SELECT 'MU2176', 'BATA RINGAN 7.5CM', 'BATA', 'CITICON', 'PCS', 15000.00, 8.000, 1, 'MU2176'
UNION ALL SELECT 'MU2177', 'BOSTER CB-85A', 'MATA SERKEL', 'MAKITA', 'PCS', 28000.00, 0.000, 1, 'MU2177'
UNION ALL SELECT 'MU2178', 'AIWO LED 18W', 'LAMPU', 'AIWO', 'PCS', 25000.00, 6.000, 1, 'MU2178'
UNION ALL SELECT 'MU2179', 'AIWO LED 30W', 'LAMPU', 'AIWO', 'PCS', 40000.00, 6.000, 1, 'MU2179'
UNION ALL SELECT 'MU2180', 'AIWO LED 40W', 'LAMPU', 'AIWO', 'PCS', 60000.00, 6.000, 1, 'MU2180'
UNION ALL SELECT 'MU2181', 'AIWO LED 50 W', 'LAMPU', 'AIWO', 'PCS', 70000.00, 6.000, 1, 'MU2181'
UNION ALL SELECT 'MU2182', 'HAKANO GREY 50X50', 'TEHEL', 'JUPITER', 'DUS', 72399.00, 8.000, 1, 'MU2182'
UNION ALL SELECT 'MU2183', 'OLIMPIC BROWN 50X50', 'TEHEL', 'DELTA', 'DUS', 63000.00, 8.000, 1, 'MU2183'
UNION ALL SELECT 'MU2184', 'OLIMPIC CREAM 50X50', 'TEHEL', 'DELTA', 'DUS', 63000.00, 8.000, 1, 'MU2184'
UNION ALL SELECT 'MU2185', 'TRIPWOOD CREAM 50X50', 'TEHEL', 'DELTA', 'DUS', 61000.00, 8.000, 1, 'MU2185'
UNION ALL SELECT 'MU2186', 'BUBLE BEIGE 25X25', 'TEHEL', 'TIERA TILE', 'DUS', 57500.00, 8.000, 1, 'MU2186'
UNION ALL SELECT 'MU2187', 'OBENG SCREW DRIVER 4\'\'', 'OBENG', '-', 'PCS', 16000.00, 0.000, 1, 'MU2187'
UNION ALL SELECT 'MU2188', 'OBENG RADIO ANANTA 6\'\'+', 'OBENG', '-', 'PCS', 10000.00, 0.000, 1, 'MU2188'
UNION ALL SELECT 'MU2189', 'OBENG RUBYCON 4X8 +', 'OBENG', '-', 'PCS', 12000.00, 0.000, 1, 'MU2189'
UNION ALL SELECT 'MU2190', 'OBENG RADIO 6 -', 'OBENG', '-', 'PCS', 10400.00, 0.000, 1, 'MU2190'
UNION ALL SELECT 'MU2191', 'OBENG RADIO ATS 4 -', 'OBENG', '-', 'PCS', 6000.00, 6.000, 1, 'MU2191'
UNION ALL SELECT 'MU2192', 'GUNTING SCISSOR', 'GUNTING', 'SCISSORS', 'PCS', 12000.00, 0.000, 1, 'MU2192'
UNION ALL SELECT 'MU2193', 'LEM KACA ODOL CLEAR 35 GR', 'LEM', 'TAIYO', 'PCS', 6000.00, 0.000, 1, 'MU2193'
UNION ALL SELECT 'MU2194', 'TANG POTONG 6\" TPC-150', 'TANG', 'WIPRO', 'PCS', 33000.00, 0.000, 1, 'MU2194'
UNION ALL SELECT 'MU2195', 'WIPRO KUNCI PAS 8X10', 'V-BELT', 'WIPRO', 'PCS', 4800.00, 6.000, 1, 'MU2195'
UNION ALL SELECT 'MU2196', 'OBENG RADIO 5-', 'OBENG', '-', 'PCS', 8000.00, 6.000, 1, 'MU2196'
UNION ALL SELECT 'MU2197', 'DEXTONE LEM BESI 48g', 'LEM', 'DEXTONE', 'PCS', 15000.00, 24.000, 1, 'MU2197'
UNION ALL SELECT 'MU2198', 'CUTTER USA IDEKU', 'CUTTER', 'IDEKU', 'PCS', 9000.00, 12.000, 1, 'MU2198'
UNION ALL SELECT 'MU2199', 'PROHEX GLUE GUN', 'ALAT', 'PROHEX', 'UNIT', 32000.00, 6.000, 1, 'MU2199'
UNION ALL SELECT 'MU2200', 'WAJAN 32', 'WAJAN', 'OLIMPIA', 'PCS', 456000.00, 0.000, 1, 'MU2200'
UNION ALL SELECT 'MU2201', 'WAJAN 32', 'WAJAN', 'ED', 'PCS', 689600.00, 0.000, 1, 'MU2201'
UNION ALL SELECT 'MU2202', 'GLACIER GREY 40X40', 'TEHEL', 'JUPITER', 'DUS', 52499.00, 8.000, 1, 'MU2202'
UNION ALL SELECT 'MU2203', 'PAKU KALSIBOARD 1\' 1KG', 'Paku', 'KAWI', 'DUS', 35000.00, 6.000, 1, 'MU2203'
UNION ALL SELECT 'MU2204', 'SAMBUNGAN ALKON 2\' X 1', 'GEMUK', '-', 'PCS', 24000.00, 0.000, 1, 'MU2204'
UNION ALL SELECT 'MU2205', 'SAMBUNGAN ALKON 2\'X3/4\'', 'GEMUK', '-', 'PCS', 22400.00, 0.000, 1, 'MU2205'
UNION ALL SELECT 'MU2206', 'SAMBUNGAN ALKON 3\'X2\'', 'GEMUK', '-', 'PCS', 35200.00, 0.000, 1, 'MU2206'
UNION ALL SELECT 'MU2207', 'SAMBUNGAN ALKON 3\' X 1\'', 'GEMUK', '-', 'PCS', 30400.00, 5.000, 1, 'MU2207'
UNION ALL SELECT 'MU2208', 'MCB 10 A BROCO', 'MCB', 'BROCO', 'PCS', 36800.00, 0.000, 1, 'MU2208'
UNION ALL SELECT 'MU2209', 'GRENDEL JEPIT', 'GRENDEL', 'LAFMA', 'PCS', 3000.00, 0.000, 1, 'MU2209'
UNION ALL SELECT 'MU2210', 'DRAWER LOCK GOOD', 'V-BELT', 'GOOD', 'PCS', 5600.00, 0.000, 1, 'MU2210'
UNION ALL SELECT 'MU2211', 'VIPLAS KALENG 360GR', 'PEL LANTAI', 'Avian', 'KLG', 56200.00, 12.000, 1, 'MU2211'
UNION ALL SELECT 'MU2212', 'HAK ANGIN VINO', 'V-BELT', 'VINO', 'PCS', 9600.00, 0.000, 1, 'MU2212'
UNION ALL SELECT 'MU2213', 'HAK ANGIN GOLD', 'V-BELT', '-', 'PCS', 7200.00, 0.000, 1, 'MU2213'
UNION ALL SELECT 'MU2214', 'MENIE PEDANG 5KG', 'MENIE', 'PEDANG', 'KLG', 188000.00, 0.000, 1, 'MU2214'
UNION ALL SELECT 'MU2215', 'SODA API VTECH 1 L', 'CAT', 'VTECH', 'KLG', 100330.00, 0.000, 1, 'MU2215'
UNION ALL SELECT 'MU2216', 'BEE BRAND JR66 654', 'CAT', 'NIPPON PAINT', 'KLG', 71022.00, 0.000, 1, 'MU2216'
UNION ALL SELECT 'MU2217', 'BASKOM NIKITA 04', 'BASKOM', 'NIKITA', 'PCS', 3200.00, 0.000, 1, 'MU2217'
UNION ALL SELECT 'MU2218', 'WASKOM ROSSA 12', 'BASKOM', 'ROSSA', 'PCS', 5600.00, 0.000, 1, 'MU2218'
UNION ALL SELECT 'MU2219', 'BASKOM KMP 023', 'BASKOM', 'KMP', 'PCS', 4000.00, 0.000, 1, 'MU2219'
UNION ALL SELECT 'MU2220', 'WASKOM MAYORI 12', 'BASKOM', 'CLARITA', 'PCS', 3200.00, 0.000, 1, 'MU2220'
UNION ALL SELECT 'MU2221', 'WASKOM DIAMOND 12', 'BASKOM', 'DIAMOND', 'PCS', 3200.00, 0.000, 1, 'MU2221'
UNION ALL SELECT 'MU2222', 'PANGGANGAN IKAN BBQ DRILL', 'MATA SERKEL', '-', 'PCS', 34400.00, 0.000, 1, 'MU2222'
UNION ALL SELECT 'MU2223', 'LAHER ARTCO', 'MATA SERKEL', '-', 'PCS', 8000.00, 0.000, 1, 'MU2223'
UNION ALL SELECT 'MU2224', 'BASKOM HITAM 23', 'BASKOM', '-', 'PCS', 22400.00, 0.000, 1, 'MU2224'
UNION ALL SELECT 'MU2225', 'KERANJANG BAJU BIASA', 'KERANJANG', '-', 'PCS', 24000.00, 0.000, 1, 'MU2225'
UNION ALL SELECT 'MU2226', 'BAN LUAR ARTCO', 'BAN', 'ARTCO', 'PCS', 62000.00, 20.000, 1, 'MU2226'
UNION ALL SELECT 'MU2227', 'WASKOM 888', 'BASKOM', '808', 'PCS', 4000.00, 0.000, 1, 'MU2227'
UNION ALL SELECT 'MU2228', 'SARINGAN DALAM 30 SUMBU KOMPOR HOCK', 'SARINGAN', 'HOCK', 'PCS', 41600.00, 2.000, 1, 'MU2228'
UNION ALL SELECT 'MU2229', 'SEROK MINYAK KOTOR', 'SEROK', '-', 'PCS', 16000.00, 0.000, 1, 'MU2229'
UNION ALL SELECT 'MU2230', 'POT 18 H 5560 H LK', 'POT', 'WINNER', 'PCS', 4000.00, 0.000, 1, 'MU2230'
UNION ALL SELECT 'MU2231', 'GORDEN SET SR-051 1.2-2MTR', 'GORDEN', 'SANSHAN', 'SET', 75000.00, 0.000, 1, 'MU2231'
UNION ALL SELECT 'MU2232', 'PENJEPIT IKAN', 'CAPITAN', '-', 'PCS', 16000.00, 0.000, 1, 'MU2232'
UNION ALL SELECT 'MU2233', 'IRUS MITA PRODU', 'IRUS', 'MITA PRODUK', 'PCS', 4000.00, 0.000, 1, 'MU2233'
UNION ALL SELECT 'MU2234', 'CAPITAN STAINLESS STEEL', 'CAPITAN', 'A&N', 'PCS', 8000.00, 0.000, 1, 'MU2234'
UNION ALL SELECT 'MU2235', 'SUTEL STAINLESS STEEL', 'SUTIL', 'STAINLISSTEEL', 'PCS', 6400.00, 0.000, 1, 'MU2235'
UNION ALL SELECT 'MU2236', 'CAT AVIAN 200CC 309', 'CAT', 'Avian', 'KLG', 18400.00, 8.000, 1, 'MU2236'
UNION ALL SELECT 'MU2237', 'CAT AVIAN 200CC 660', 'CAT', 'Avian', 'KLG', 18400.00, 8.000, 1, 'MU2237'
UNION ALL SELECT 'MU2238', 'SEROK SAMPAH PESONA', 'SEROK', 'PESONA 112', 'PCS', 12000.00, 0.000, 1, 'MU2238'
UNION ALL SELECT 'MU2239', 'BAK MANDI BAYI SUMMO', 'BAK', 'SUMMO', 'PCS', 36000.00, 0.000, 1, 'MU2239'
UNION ALL SELECT 'MU2240', 'CAT AVIAN 200CC 732', 'CAT', 'Avian', 'KLG', 18400.00, 8.000, 1, 'MU2240'
UNION ALL SELECT 'MU2241', 'BAK MANDI BAYI TIGER', 'BAK', 'TIGER BABY', 'PCS', 43200.00, 0.000, 1, 'MU2241'
UNION ALL SELECT 'MU2242', 'CAT AVIAN 200CC 192', 'CAT', 'Avian', 'KLG', 18400.00, 8.000, 1, 'MU2242'
UNION ALL SELECT 'MU2243', 'BAK MANDI BAYI LOVE', 'BAK', 'LOVE', 'PCS', 41600.00, 0.000, 1, 'MU2243'
UNION ALL SELECT 'MU2244', 'ONGKIR', 'TEHEL', '-', 'UNIT', 0.00, 0.000, 1, 'MU2244'
UNION ALL SELECT 'MU2246', 'GAYUNG FYH', 'GAYUNG', 'MASPION', 'PCS', 8800.00, 0.000, 1, 'MU2246'
UNION ALL SELECT 'MU2247', 'BAK MANDI BAYI ENGLAND', 'BAK', 'ENGLAND', 'PCS', 57600.00, 0.000, 1, 'MU2247'
UNION ALL SELECT 'MU2248', 'FAWI FK 504 M', 'V-BELT', 'FAWI', 'SET', 0.00, 0.000, 1, 'MU2248'
UNION ALL SELECT 'MU2249', 'KUNCI HSH 8213 SN/CP', 'KUNCI PINTU', 'HSJ', 'SET', 0.00, 0.000, 1, 'MU2249'
UNION ALL SELECT 'MU2250', 'BERLUTI LOG BODY', 'V-BELT', 'BERLUTI', 'SET', 0.00, 0.000, 1, 'MU2250'
UNION ALL SELECT 'MU2251', 'KUNCI PINTU MORTISE', 'V-BELT', 'MORTISE', 'SET', 0.00, 0.000, 1, 'MU2251'
UNION ALL SELECT 'MU2252', 'TINNER A SPECIAL 0,8L', 'TINNER', '-', 'KLG', 36900.00, 0.000, 1, 'MU2252'
UNION ALL SELECT 'MU2253', 'BEE BRAND JR66 SW', 'CAT', 'NIPPON PAINT', 'KLG', 57600.00, 0.000, 1, 'MU2253'
UNION ALL SELECT 'MU2254', 'BEE BRAND JR66 SB', 'CAT', 'NIPPON PAINT', 'KLG', 57600.00, 0.000, 1, 'MU2254'
UNION ALL SELECT 'MU2255', 'BEE BRAND JR66 666', 'CAT', 'NIPPON PAINT', 'KLG', 57600.00, 0.000, 1, 'MU2255'
UNION ALL SELECT 'MU2256', 'BEE BRAND JR66 634', 'CAT', 'NIPPON PAINT', 'KLG', 57600.00, 0.000, 1, 'MU2256'
UNION ALL SELECT 'MU2257', 'BEE BRAND JR66 COCO BROWN', 'CAT', 'NIPPON PAINT', 'KLG', 57600.00, 0.000, 1, 'MU2257'
UNION ALL SELECT 'MU2258', 'Kuas Roll Kapal', 'Kuas', 'VICO', 'PCS', 24000.00, 0.000, 1, 'MU2258'
UNION ALL SELECT 'MU2259', 'ENGSEL GRT 4\'', 'ENGSEL', 'GRT', 'SET', 21600.00, 0.000, 1, 'MU2259'
UNION ALL SELECT 'MU2260', 'POT PUTIH DENALI 1040', 'POT', 'DENALI', 'PCS', 14000.00, 0.000, 1, 'MU2260'
UNION ALL SELECT 'MU2261', 'POT DENALI COKLAT1010', 'POT', 'DENALI', 'PCS', 6000.00, 0.000, 1, 'MU2261'
UNION ALL SELECT 'MU2262', 'POT DENALI PTH 1020', 'POT', 'DENALI', 'PCS', 9000.00, 0.000, 1, 'MU2262'
UNION ALL SELECT 'MU2263', 'POT DENALI CKL 1030', 'POT', 'DENALI', 'PCS', 10000.00, 0.000, 1, 'MU2263'
UNION ALL SELECT 'MU2264', 'POT DENALI CKL 1040', 'POT', 'DENALI', 'PCS', 15000.00, 0.000, 1, 'MU2264'
UNION ALL SELECT 'MU2265', 'POT DENALI CKL 1020', 'POT', 'DENALI', 'PCS', 8000.00, 0.000, 1, 'MU2265'
UNION ALL SELECT 'MU2266', 'POT DENALI PTH 1030', 'POT', 'DENALI', 'PCS', 13000.00, 0.000, 1, 'MU2266'
UNION ALL SELECT 'MU2267', 'POT DENALI PTH 1050', 'POT', 'DENALI', 'PCS', 25000.00, 0.000, 1, 'MU2267'
UNION ALL SELECT 'MU2268', 'POT DENALI PTH1010', 'POT', 'DENALI', 'PCS', 8000.00, 0.000, 1, 'MU2268'
UNION ALL SELECT 'MU2269', 'POT OENTOENG 24', 'POT', 'OENTOENG', 'PCS', 15000.00, 0.000, 1, 'MU2269'
UNION ALL SELECT 'MU2270', 'POT OENTOENG 21', 'POT', 'OENTOENG', 'PCS', 9000.00, 0.000, 1, 'MU2270'
UNION ALL SELECT 'MU2271', 'POT HJ 1030', 'POT', 'DENALI', 'PCS', 12800.00, 0.000, 1, 'MU2271'
UNION ALL SELECT 'MU2272', 'TINNER SUPER 0.8 Ltr', 'TINNER', 'SUPER', 'KLG', 43400.00, 12.000, 1, 'MU2272'
UNION ALL SELECT 'MU2273', 'KUAS BIASA 3\'', 'Kuas', 'FAWI', 'PCS', 8000.00, 0.000, 1, 'MU2273'
UNION ALL SELECT 'MU2274', 'KLEP ALKON 4\'', 'KLEP', 'NOQIWA', 'PCS', 76000.00, 0.000, 1, 'MU2274'
UNION ALL SELECT 'MU2275', 'KLEP ALKON 3\'', 'KLEP', 'NOQIWA', 'PCS', 60000.00, 0.000, 1, 'MU2275'
UNION ALL SELECT 'MU2276', 'KLEM SLANG PLAT 2.5\'', 'KLEM', '-', 'PCS', 0.00, 0.000, 1, 'MU2276'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU2277' AS sku, 'BEE BRAND JR66 301M' AS name, 'CAT' AS category_name, 'NIPPON PAINT' AS brand_name, 'KLG' AS base_unit_code, 72500.00 AS cost_price, 0.000 AS min_stock, 1 AS is_active, 'MU2277' AS legacy_sku
UNION ALL SELECT 'MU2278', 'AIR RADIATOR 1 L', 'CAIRAN', '-', 'BTL', 14500.00, 0.000, 1, 'MU2278'
UNION ALL SELECT 'MU2279', 'LAMPU EKONOMAT 13 W 3 WRN', 'LAMPU', 'EKONOMAT', 'PCS', 48500.00, 0.000, 1, 'MU2279'
UNION ALL SELECT 'MU2280', 'POT HTM 35', 'POT', '-', 'PCS', 9500.00, 0.000, 1, 'MU2280'
UNION ALL SELECT 'MU2281', 'SAPU BERLIAN', 'SAPU', '-', 'PCS', 25000.00, 0.000, 1, 'MU2281'
UNION ALL SELECT 'MU2282', 'SAPU 2 MACAN SEDANG', 'SAPU', '2 MACAN', 'PCS', 39100.00, 10.000, 1, 'MU2282'
UNION ALL SELECT 'MU2283', 'JEPIT IKAN PLASTI', 'JEPIT IKAN', '-', 'PCS', 7000.00, 0.000, 1, 'MU2283'
UNION ALL SELECT 'MU2284', 'SEAL WARE 24 LTR', 'TOPLES', '-', 'PCS', 32500.00, 0.000, 1, 'MU2284'
UNION ALL SELECT 'MU2285', 'SEAL WARE 16 LTR', 'TOPLES', '-', 'PCS', 20000.00, 0.000, 1, 'MU2285'
UNION ALL SELECT 'MU2286', 'BEE BRAND JR66 9104', 'CAT', 'NIPPON PAINT', 'KLG', 57600.00, 0.000, 1, 'MU2286'
UNION ALL SELECT 'MU2287', 'BEE BRAND JR66 620', 'CAT', 'NIPPON PAINT', 'PCS', 57600.00, 0.000, 1, 'MU2287'
UNION ALL SELECT 'MU2288', 'TUTUP PIPA 1 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 6390.00, 9.000, 1, 'MU2288'
UNION ALL SELECT 'MU2289', 'PHILIPS LED 12W', 'LAMPU', 'PHILIP', 'PCS', 50000.00, 0.000, 1, 'MU2289'
UNION ALL SELECT 'MU2290', 'HI EPOXY FILLER GREY', 'EPOXI', 'NIPPON PAINT', 'PCS', 105109.00, 0.000, 1, 'MU2290'
UNION ALL SELECT 'MU2291', 'PALU KAMBING USA 16OZ GRG', 'PALU', 'HENDSO', 'PCS', 30000.00, 6.000, 1, 'MU2291'
UNION ALL SELECT 'MU2292', 'BENANG WOLL', 'TALI', '-', 'IKAT', 5000.00, 20.000, 1, 'MU2292'
UNION ALL SELECT 'MU2293', 'KAPAK CAP MATA 600', 'ALAT', 'MATA', 'PCS', 95000.00, 0.000, 1, 'MU2293'
UNION ALL SELECT 'MU2294', 'TECTOOL KUNCI PAS 12X14', 'V-BELT', 'WIPRO', 'PCS', 9000.00, 6.000, 1, 'MU2294'
UNION ALL SELECT 'MU2295', 'WIPRO KUNCI PASS 14X17', 'V-BELT', 'WIPRO', 'PCS', 8800.00, 0.000, 1, 'MU2295'
UNION ALL SELECT 'MU2296', 'TECTOOL KUNCI PASS 16X17', 'V-BELT', 'WIPRO', 'PCS', 11500.00, 6.000, 1, 'MU2296'
UNION ALL SELECT 'MU2297', 'TECTOOL KUNCI PASS 20X22', 'V-BELT', 'WIPRO', 'PCS', 17000.00, 0.000, 1, 'MU2297'
UNION ALL SELECT 'MU2298', 'TECTOOL KUNCI PASS 25X28', 'V-BELT', 'WIPRO', 'PCS', 31000.00, 0.000, 1, 'MU2298'
UNION ALL SELECT 'MU2299', 'WIPRO KUNCI PAS 30X32', 'V-BELT', 'WIPRO', 'PCS', 27200.00, 0.000, 1, 'MU2299'
UNION ALL SELECT 'MU2300', 'WIPRO GEMBOK G1-25', 'V-BELT', 'WIPRO', 'PCS', 20000.00, 0.000, 1, 'MU2300'
UNION ALL SELECT 'MU2301', 'WIPRO GEMBOK G1-32', 'V-BELT', 'WIPRO', 'PCS', 28000.00, 0.000, 1, 'MU2301'
UNION ALL SELECT 'MU2302', 'WIPRO GEMBOK G1-38', 'V-BELT', 'WIPRO', 'PCS', 36000.00, 0.000, 1, 'MU2302'
UNION ALL SELECT 'MU2303', 'WIPRO GEMBOK G1-50', 'V-BELT', 'WIPRO', 'PCS', 56000.00, 0.000, 1, 'MU2303'
UNION ALL SELECT 'MU2304', 'WIPRO GEMBOK G1-63', 'V-BELT', 'WIPRO', 'PCS', 76000.00, 0.000, 1, 'MU2304'
UNION ALL SELECT 'MU2305', 'WIPRO GEMBOK G1-75', 'V-BELT', 'WIPRO', 'PCS', 96000.00, 0.000, 1, 'MU2305'
UNION ALL SELECT 'MU2306', 'WIPRO GEMBOK G5-40', 'V-BELT', 'WIPRO', 'PCS', 104000.00, 0.000, 1, 'MU2306'
UNION ALL SELECT 'MU2307', 'WIPRO GEMBOK G5-50', 'V-BELT', 'WIPRO', 'PCS', 116000.00, 0.000, 1, 'MU2307'
UNION ALL SELECT 'MU2308', 'WIPRO GEMBOK G5-60', 'V-BELT', 'WIPRO', 'PCS', 128000.00, 0.000, 1, 'MU2308'
UNION ALL SELECT 'MU2309', 'WIPRO GEMBOK G5-70', 'V-BELT', 'WIPRO', 'PCS', 152000.00, 0.000, 1, 'MU2309'
UNION ALL SELECT 'MU2310', 'WIPRO GLUE GUN 10W', 'MATA SERKEL', 'WIPRO', 'PCS', 46750.00, 0.000, 1, 'MU2310'
UNION ALL SELECT 'MU2311', 'WIPRO MATA BOR OBENG 65MM +', 'V-BELT', 'WIPRO', 'PCS', 12000.00, 5.000, 1, 'MU2311'
UNION ALL SELECT 'MU2312', 'WIPRO MATA OBENG DOUBLE', 'MATA BOR', 'WIPRO', 'PCS', 12000.00, 5.000, 1, 'MU2312'
UNION ALL SELECT 'MU2313', 'WIPRO KUNCI KEPALA BOR 10MM', 'V-BELT', 'WIPRO', 'PCS', 16000.00, 3.000, 1, 'MU2313'
UNION ALL SELECT 'MU2314', 'WIPRO TANG POTONG 6\'\'', 'TANG', 'WIPRO', 'PCS', 52000.00, 0.000, 1, 'MU2314'
UNION ALL SELECT 'MU2315', 'WIPRO TANG POTONG 8\'\'', 'TANG', 'WIPRO', 'PCS', 56000.00, 0.000, 1, 'MU2315'
UNION ALL SELECT 'MU2316', 'DRIJEN 30L', 'GEN', '-', 'PCS', 68000.00, 0.000, 1, 'MU2316'
UNION ALL SELECT 'MU2317', 'DRUM BIRU', 'DRUM', '-', 'PCS', 400000.00, 0.000, 1, 'MU2317'
UNION ALL SELECT 'MU2318', 'WIPRO PLANNER BLADE TCT 82X29X3MM', 'MATA SERKEL', 'WIPRO', 'PSG', 180000.00, 0.000, 1, 'MU2318'
UNION ALL SELECT 'MU2319', 'MATEX CEILING WHITE', 'CAT', 'NIPPON PAINT', 'PAIL', 380000.00, 0.000, 1, 'MU2319'
UNION ALL SELECT 'MU2320', 'KLEP POMPA 1\'', 'MATA SERKEL', '-', 'PCS', 17000.00, 0.000, 1, 'MU2320'
UNION ALL SELECT 'MU2321', 'WATER PASS WIPRO 1 MTR', 'WATERPASS', 'WIPRO', 'PCS', 200000.00, 0.000, 1, 'MU2321'
UNION ALL SELECT 'MU2322', 'OBAT SOLDER', 'OBAT', '-', 'KLG', 40000.00, 0.000, 1, 'MU2322'
UNION ALL SELECT 'MU2323', 'SPAN PVC PAPAK', 'MATA SERKEL', '-', 'PCS', 13000.00, 0.000, 1, 'MU2323'
UNION ALL SELECT 'MU2324', 'TANG LANCIP IGM 6\'', 'TANG', '-', 'PCS', 27500.00, 6.000, 1, 'MU2324'
UNION ALL SELECT 'MU2325', 'TANG LANCIP 8\" TLC-200', 'TANG', 'WIPRO', 'PCS', 38500.00, 6.000, 1, 'MU2325'
UNION ALL SELECT 'MU2326', 'TANG KOMBINASI IGM 8 \'', 'TANG', 'IGM', 'PCS', 40000.00, 0.000, 1, 'MU2326'
UNION ALL SELECT 'MU2327', 'AUTOLUX 66 DE PUTTY', 'DEMPUL', 'NIPPON PAINT', 'KLG', 43000.00, 0.000, 1, 'MU2327'
UNION ALL SELECT 'MU2328', 'CETOK GG KARET 8\'', 'CETOK', 'IGM', 'PCS', 19000.00, 0.000, 1, 'MU2328'
UNION ALL SELECT 'MU2329', 'GUNTING SENG 10\'', 'GUNTING', 'FRT', 'PCS', 22083.00, 6.000, 1, 'MU2329'
UNION ALL SELECT 'MU2330', 'KUNCI PINTU KENZO MONACO', 'V-BELT', 'KENZO', 'SET', 150000.00, 0.000, 1, 'MU2330'
UNION ALL SELECT 'MU2331', 'OVEN HOCK 04', 'OVEN', 'HOCK', 'UNIT', 360000.00, 0.000, 1, 'MU2331'
UNION ALL SELECT 'MU2332', 'PIRING PLASTIK TEBAL', 'PIRING', '-', 'PCS', 6000.00, 0.000, 1, 'MU2332'
UNION ALL SELECT 'MU2334', 'SILICONE RED 35GR', 'PEL LANTAI', 'HANDSOME', 'PCS', 13000.00, 0.000, 1, 'MU2334'
UNION ALL SELECT 'MU2335', 'MATA BOR BETON 4MM', 'MATA BOR', 'JORAN', 'PCS', 13000.00, 0.000, 1, 'MU2335'
UNION ALL SELECT 'MU2336', 'SAW BLADE 7 X 40', 'MATA SERKEL', 'MAKITA', 'PCS', 160000.00, 0.000, 1, 'MU2336'
UNION ALL SELECT 'MU2337', 'KUNCI RING C 11 X 13', 'V-BELT', 'WIPRO', 'PCS', 30000.00, 0.000, 1, 'MU2337'
UNION ALL SELECT 'MU2338', 'KUNCI RING C 13X15', 'V-BELT', 'WIPRO', 'PCS', 32000.00, 0.000, 1, 'MU2338'
UNION ALL SELECT 'MU2339', 'KUNCI RING C 14X17', 'V-BELT', 'WIPRO', 'PCS', 40000.00, 0.000, 1, 'MU2339'
UNION ALL SELECT 'MU2340', 'KUNCI RING C 19X22', 'V-BELT', 'WIPRO', 'PCS', 60000.00, 0.000, 1, 'MU2340'
UNION ALL SELECT 'MU2341', 'KUNCI RING S10X11', 'V-BELT', 'WIPRO', 'PCS', 40000.00, 0.000, 1, 'MU2341'
UNION ALL SELECT 'MU2342', 'KUNCI RING S 12X13', 'V-BELT', 'WIPRO', 'PCS', 40000.00, 0.000, 1, 'MU2342'
UNION ALL SELECT 'MU2343', 'KUNCI RING S 14X15', 'V-BELT', 'WIPRO', 'PCS', 45000.00, 0.000, 1, 'MU2343'
UNION ALL SELECT 'MU2344', 'KUNCI RING S 16X17', 'V-BELT', 'WIPRO', 'PCS', 43000.00, 0.000, 1, 'MU2344'
UNION ALL SELECT 'MU2345', 'KUNCI RING S 18X19', 'V-BELT', 'WIPRO', 'PCS', 60000.00, 0.000, 1, 'MU2345'
UNION ALL SELECT 'MU2346', 'KUNCI SOCK Y 8-10-12', 'V-BELT', 'WIPRO', 'PCS', 31000.00, 0.000, 1, 'MU2346'
UNION ALL SELECT 'MU2347', 'KUNCI SOCK Y 10-11-13', 'V-BELT', 'WIPRO', 'PCS', 35000.00, 0.000, 1, 'MU2347'
UNION ALL SELECT 'MU2348', 'KUNCI SOCK Y 10-12-14', 'V-BELT', 'WIPRO', 'PCS', 35000.00, 0.000, 1, 'MU2348'
UNION ALL SELECT 'MU2349', 'KUNCI SOCK Y 12-14-17', 'V-BELT', 'WIPRO', 'PCS', 38000.00, 0.000, 1, 'MU2349'
UNION ALL SELECT 'MU2350', 'MATA GURINDA  355X3X25.4MM', 'MATA GURINDA', 'WIPRO', 'PCS', 35000.00, 0.000, 1, 'MU2350'
UNION ALL SELECT 'MU2351', 'MATA BOR KIPAS SET', 'MATA BOR', 'RABBIT', 'SET', 75000.00, 0.000, 1, 'MU2351'
UNION ALL SELECT 'MU2352', 'RODA ORANGE 3\'', 'RODA', 'WIPRO', 'PCS', 55000.00, 0.000, 1, 'MU2352'
UNION ALL SELECT 'MU2353', 'PLANNER VBELT2821', 'V-BELT', 'WIPRO', 'PCS', 25000.00, 0.000, 1, 'MU2353'
UNION ALL SELECT 'MU2354', 'PLANNER V-BELT2110', 'V-BELT', 'WIPRO', 'PCS', 25000.00, 0.000, 1, 'MU2354'
UNION ALL SELECT 'MU2355', 'KUNCI L MUR', 'Kunci', 'WIPRO', 'PCS', 15500.00, 0.000, 1, 'MU2355'
UNION ALL SELECT 'MU2356', 'KUAS REFILL KECIL', 'Kuas', '-', 'PCS', 2500.00, 7.000, 1, 'MU2356'
UNION ALL SELECT 'MU2357', 'GEMBOK KUNING 25MM', 'LEM', '-', 'SET', 9000.00, 0.000, 1, 'MU2357'
UNION ALL SELECT 'MU2358', 'GEMBOK KUNING 30MM', 'LEM', '-', 'SET', 10000.00, 0.000, 1, 'MU2358'
UNION ALL SELECT 'MU2359', 'KLEP SUMUR 3/4 KCL', 'KLEP', 'ROYAL', 'PCS', 10000.00, 6.000, 1, 'MU2359'
UNION ALL SELECT 'MU2360', 'KLEP SUMUR 1\' KCL', 'KLEP', 'ROYAL', 'PCS', 15000.00, 0.000, 1, 'MU2360'
UNION ALL SELECT 'MU2361', 'SIKU TUKANG 12\' BONUCCI', 'SIKU TUKANG', '-', 'PCS', 18750.00, 7.000, 1, 'MU2361'
UNION ALL SELECT 'MU2362', 'MT BOR KAYU 1/2\' PNDK', 'MATA BOR', 'AUTOSOL', 'PCS', 19000.00, 0.000, 1, 'MU2362'
UNION ALL SELECT 'MU2363', 'OBENG BB USA 5\' MOLLAR', 'OBENG', 'MOLLAR', 'PCS', 23500.00, 0.000, 1, 'MU2363'
UNION ALL SELECT 'MU2364', 'TATAH KAYU 1/4\' CLIPPER', 'TATAH', 'CLIPPER', 'PCS', 17000.00, 0.000, 1, 'MU2364'
UNION ALL SELECT 'MU2365', 'PAKU KALSIBOT 1\' 400gr', 'Paku', 'MAJESTY', 'DUS', 9750.00, 6.000, 1, 'MU2365'
UNION ALL SELECT 'MU2366', 'HIGH QUALITY', 'Kunci', '-', 'PCS', 38400.00, 0.000, 1, 'MU2366'
UNION ALL SELECT 'MU2367', 'MESIN MOLEN', 'MESIN', 'HERCULES', 'UNIT', 11200000.00, 0.000, 1, 'MU2367'
UNION ALL SELECT 'MU2368', 'FITING GANTUNG ANTI AIR', 'STEKER', 'BROCO', 'PCS', 7850.00, 12.000, 1, 'MU2368'
UNION ALL SELECT 'MU2369', 'PEL LANTAI DRAGON KECIL', 'PEL LANTAI', 'DRAGON', 'PCS', 25000.00, 0.000, 1, 'MU2369'
UNION ALL SELECT 'MU2370', 'METERAN TOLEDO 5 M', 'METERAN', 'TOLEDO', 'PCS', 10416.00, 0.000, 1, 'MU2370'
UNION ALL SELECT 'MU2371', 'STOP KRAN 2\'\'', 'SAMBUNGAN', 'SANSHAN', 'PCS', 60000.00, 0.000, 1, 'MU2371'
UNION ALL SELECT 'MU2372', 'ENGSEL BESI GUDANG', 'ENGSEL', 'SANSHAN', 'PCS', 36000.00, 0.000, 1, 'MU2372'
UNION ALL SELECT 'MU2373', 'ENGSEL T GUDANG', 'ENGSEL', 'SANSHAN', 'PCS', 36000.00, 0.000, 1, 'MU2373'
UNION ALL SELECT 'MU2374', 'RODA PAGAR HD TEBAL 60MM', 'RODA', '-', 'PCS', 30400.00, 0.000, 1, 'MU2374'
UNION ALL SELECT 'MU2375', 'RODA PAGAR HD 60MM U', 'RODA', 'SANSHAN', 'PCS', 60000.00, 0.000, 1, 'MU2375'
UNION ALL SELECT 'MU2376', 'RODA PAGAR HD 60MM V', 'RODA', 'SANSHAN', 'PCS', 60000.00, 0.000, 1, 'MU2376'
UNION ALL SELECT 'MU2377', 'RODA PAGAR HD 70MM U', 'RODA', 'SANSHAN', 'PCS', 64000.00, 0.000, 1, 'MU2377'
UNION ALL SELECT 'MU2378', 'RODA PAGAR HD 70MM V', 'RODA', 'SANSHAN', 'PCS', 64000.00, 0.000, 1, 'MU2378'
UNION ALL SELECT 'MU2379', 'RODA PAGAR HD 70MM U', 'RODA', '-', 'PCS', 32000.00, 0.000, 1, 'MU2379'
UNION ALL SELECT 'MU2380', 'RODA PAGAR 70MM V', 'RODA', '-', 'PCS', 32000.00, 0.000, 1, 'MU2380'
UNION ALL SELECT 'MU2381', 'TANGGA ALUMUNIUM 2 MTR', 'TANGGA', 'SANSHAN', 'PCS', 700000.00, 0.000, 1, 'MU2381'
UNION ALL SELECT 'MU2382', 'CLOSET DUDUK GETA', 'CLOSET', 'GETA', 'PCS', 1400000.00, 2.000, 1, 'MU2382'
UNION ALL SELECT 'MU2383', 'BEE BRAND JR66 605', 'CAT', 'NIPPON PAINT', 'PCS', 57600.00, 0.000, 1, 'MU2383'
UNION ALL SELECT 'MU2384', 'BEE BRAND JR66 604', 'CAT', 'NIPPON PAINT', 'KLG', 57600.00, 0.000, 1, 'MU2384'
UNION ALL SELECT 'MU2385', 'BEE BRAND JR66 700', 'CAT', 'NIPPON PAINT', 'KLG', 57600.00, 0.000, 1, 'MU2385'
UNION ALL SELECT 'MU2386', 'BEE BRAND JR66 701', 'CAT', 'NIPPON PAINT', 'KLG', 57600.00, 0.000, 1, 'MU2386'
UNION ALL SELECT 'MU2387', 'BEE BRAND JR66 372M', 'CAT', 'NIPPON PAINT', 'KLG', 57600.00, 0.000, 1, 'MU2387'
UNION ALL SELECT 'MU2388', 'BEE BRAND JR66 606', 'CAT', 'NIPPON PAINT', 'KLG', 57600.00, 0.000, 1, 'MU2388'
UNION ALL SELECT 'MU2389', 'BEE BRAND JR66 614', 'CAT', 'NIPPON PAINT', 'KLG', 57600.00, 0.000, 1, 'MU2389'
UNION ALL SELECT 'MU2390', 'BEE BRAND JR66 610', 'CAT', 'NIPPON PAINT', 'KLG', 57600.00, 0.000, 1, 'MU2390'
UNION ALL SELECT 'MU2391', 'NIPPE 2000 047', 'CAT', 'NIPPON PAINT', 'KLG', 62400.00, 0.000, 1, 'MU2391'
UNION ALL SELECT 'MU2392', 'NIPPE 2000 315', 'CAT', 'NIPPON PAINT', 'KLG', 62400.00, 0.000, 1, 'MU2392'
UNION ALL SELECT 'MU2393', 'NIPPE 2000 1031', 'CAT', 'NIPPON PAINT', 'KLG', 62400.00, 0.000, 1, 'MU2393'
UNION ALL SELECT 'MU2394', 'NIPPE 2000 444', 'CAT', 'NIPPON PAINT', 'KLG', 62400.00, 0.000, 1, 'MU2394'
UNION ALL SELECT 'MU2395', 'NIPPE 2000 319', 'CAT', 'NIPPON PAINT', 'KLG', 62400.00, 0.000, 1, 'MU2395'
UNION ALL SELECT 'MU2396', 'TINNER NIPPE 2000 A SPECIAL H.GLOSS 1L', 'TINNER', 'NIPPON PAINT', 'KLG', 36000.00, 0.000, 1, 'MU2396'
UNION ALL SELECT 'MU2397', 'CAS HANDSPRAYER', 'S/PART', 'YAMAOKE', 'SET', 37000.00, 6.000, 1, 'MU2397'
UNION ALL SELECT 'MU2398', 'Motor Hand Sprayer', 'S/PART', 'I MOTO', 'SET', 110000.00, 0.000, 1, 'MU2398'
UNION ALL SELECT 'MU2399', 'BAUT SENG 12X50', 'Paku', 'FIX', 'DUS', 79000.00, 0.000, 1, 'MU2399'
UNION ALL SELECT 'MU2400', 'Kran Wastefel PVC', 'KRAN', '-', 'PCS', 35000.00, 0.000, 1, 'MU2400'
UNION ALL SELECT 'MU2401', 'Paku Pines', 'Paku', '-', 'DUS', 479.00, 12.000, 1, 'MU2401'
UNION ALL SELECT 'MU2402', 'AMPLAS BULAT 150', 'AMPLAS', '-', 'LBR', 550.00, 0.000, 1, 'MU2402'
UNION ALL SELECT 'MU2403', 'AMPLAS BULAT P100', 'AMPLAS', '-', 'PCS', 550.00, 20.000, 1, 'MU2403'
UNION ALL SELECT 'MU2404', 'KIKIR SEGITIGA 4\'', 'KIKIR', 'WANLY', 'PCS', 5500.00, 0.000, 1, 'MU2404'
UNION ALL SELECT 'MU2405', 'SEMPROTAN POMPA 2 LTR', 'POMPA', 'PRIMERO', 'SET', 40000.00, 0.000, 1, 'MU2405'
UNION ALL SELECT 'MU2406', 'Semprotan 1/2 L', 'SEMPROTAN', '-', 'SET', 9500.00, 0.000, 1, 'MU2406'
UNION ALL SELECT 'MU2407', 'Semprotan 1 L', 'SEMPROTAN', '-', 'SET', 12500.00, 0.000, 1, 'MU2407'
UNION ALL SELECT 'MU2408', 'Kran Wastafel Stainless', 'KRAN', '-', 'PCS', 47500.00, 0.000, 1, 'MU2408'
UNION ALL SELECT 'MU2409', 'STATER ALKON', 'S/PART', 'NOQIWA', 'PCS', 60000.00, 6.000, 1, 'MU2409'
UNION ALL SELECT 'MU2410', 'Starter hand spryer', 'S/PART', 'NOQIWA', 'SET', 70000.00, 0.000, 1, 'MU2410'
UNION ALL SELECT 'MU2411', 'Starter pawl', 'S/PART', 'NOQIWA', 'PCS', 5000.00, 0.000, 1, 'MU2411'
UNION ALL SELECT 'MU2412', 'Pisau Parut Kelapa', 'S/PART', '-', 'PCS', 40000.00, 0.000, 1, 'MU2412'
UNION ALL SELECT 'MU2413', 'SELANG WASTAFEL AVUR 1M', 'S/PART', 'SOLIGEN', 'PCS', 16500.00, 6.000, 1, 'MU2413'
UNION ALL SELECT 'MU2414', 'Muller M8016 Lock Body', 'KUNCI PINTU', 'MULLER', 'SET', 0.00, 0.000, 1, 'MU2414'
UNION ALL SELECT 'MU2415', 'SENAR NYLON BESAR', 'NYLON', '-', 'PCS', 0.00, 0.000, 1, 'MU2415'
UNION ALL SELECT 'MU2416', 'SENAR NYLON KECIL', 'NYLON', '-', 'PCS', 0.00, 0.000, 1, 'MU2416'
UNION ALL SELECT 'MU2417', 'RODA PAGAR HD TEBAL TIPE U', 'RODA', '-', 'PCS', 20000.00, 0.000, 1, 'MU2417'
UNION ALL SELECT 'MU2418', 'BALI GREY', 'TEHEL', 'GRESIK', 'DUS', 0.00, 0.000, 1, 'MU2418'
UNION ALL SELECT 'MU2419', 'Karung 50 Kg Biasa', 'ALAT', '-', 'PCS', 0.00, 0.000, 1, 'MU2419'
UNION ALL SELECT 'MU2420', 'Karung 50 Kg Tebal', 'ALAT', '-', 'PCS', 0.00, 0.000, 1, 'MU2420'
UNION ALL SELECT 'MU2421', 'Baut 12 8x150', 'BAUT', '-', 'PSG', 0.00, 20.000, 1, 'MU2421'
UNION ALL SELECT 'MU2422', 'Baut 12 8x100', 'BAUT', '-', 'PSG', 0.00, 20.000, 1, 'MU2422'
UNION ALL SELECT 'MU2423', 'Baut 12 8x50', 'BAUT', '-', 'PSG', 0.00, 0.000, 1, 'MU2423'
UNION ALL SELECT 'MU2424', 'Baut 12 8x20', 'BAUT', '-', 'PSG', 0.00, 0.000, 1, 'MU2424'
UNION ALL SELECT 'MU2425', 'Baut 12 8x40', 'BAUT', '-', 'PSG', 0.00, 0.000, 1, 'MU2425'
UNION ALL SELECT 'MU2426', 'Baut 12 8x30', 'BAUT', '-', 'PSG', 0.00, 0.000, 1, 'MU2426'
UNION ALL SELECT 'MU2427', 'Baut 10 6x50', 'BAUT', '-', 'PSG', 0.00, 0.000, 1, 'MU2427'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU2428' AS sku, 'Baut 10 6x40' AS name, 'BAUT' AS category_name, '-' AS brand_name, 'PSG' AS base_unit_code, 0.00 AS cost_price, 0.000 AS min_stock, 1 AS is_active, 'MU2428' AS legacy_sku
UNION ALL SELECT 'MU2429', 'Baut 10 6x30', 'BAUT', '-', 'PSG', 0.00, 0.000, 1, 'MU2429'
UNION ALL SELECT 'MU2430', 'Baut 14 10x50', 'BAUT', '-', 'PSG', 0.00, 100.000, 1, 'MU2430'
UNION ALL SELECT 'MU2431', 'Baut 14 10x40', 'BAUT', '-', 'PSG', 0.00, 100.000, 1, 'MU2431'
UNION ALL SELECT 'MU2432', 'Baut 14 10x30', 'BAUT', '-', 'PSG', 0.00, 20.000, 1, 'MU2432'
UNION ALL SELECT 'MU2433', 'Baut 14 10x20', 'BAUT', '-', 'PSG', 0.00, 20.000, 1, 'MU2433'
UNION ALL SELECT 'MU2434', 'Baut 17 12x50', 'BAUT', '-', 'PSG', 0.00, 100.000, 1, 'MU2434'
UNION ALL SELECT 'MU2435', 'Baut 17 12x40', 'BAUT', '-', 'PSG', 0.00, 0.000, 1, 'MU2435'
UNION ALL SELECT 'MU2436', 'SEPATU BOOT KUNING CORE UK. 40', 'SEPATU', 'CORE', 'PSG', 75000.00, 0.000, 1, 'MU2436'
UNION ALL SELECT 'MU2437', 'JERIGEN 30L', 'ALAT', '-', 'PCS', 0.00, 0.000, 1, 'MU2437'
UNION ALL SELECT 'MU2438', 'LAMPU KUNING 15 W', 'LAMPU', 'CHIYODA', 'PCS', 10000.00, 0.000, 1, 'MU2438'
UNION ALL SELECT 'MU2439', 'PIPA JAYA 1 D', 'PIPA', 'JAYA', 'BTG', 0.00, 0.000, 1, 'MU2439'
UNION ALL SELECT 'MU2440', 'PIPA JAYA 3 D', 'PIPA', 'JAYA', 'BTG', 0.00, 0.000, 1, 'MU2440'
UNION ALL SELECT 'MU2441', 'PIPA JAYA 4 D', 'PIPA', 'JAYA', 'BTG', 0.00, 0.000, 1, 'MU2441'
UNION ALL SELECT 'MU2442', 'SEPATU BOOT KUNING YOZURI UK. 43', 'SEPATU', 'YOZURI', 'PSG', 75000.00, 0.000, 1, 'MU2442'
UNION ALL SELECT 'MU2443', 'SEPATU BOOT KUNING CORE UK. 42', 'SEPATU', 'CORE', 'PSG', 75000.00, 0.000, 1, 'MU2443'
UNION ALL SELECT 'MU2444', 'SEPATU BOOT KUNING HONXI UK. 41', 'SEPATU', 'HONDA', 'PSG', 75000.00, 0.000, 1, 'MU2444'
UNION ALL SELECT 'MU2445', 'SEPATU AP BOOT UK. 39', 'SEPATU', 'AP', 'PSG', 0.00, 0.000, 1, 'MU2445'
UNION ALL SELECT 'MU2446', 'KAWAT RAM 1/2\'', 'KAWAT', '-', 'ROL', 60000.00, 7.000, 1, 'MU2446'
UNION ALL SELECT 'MU2447', 'KAWAT RAM 1/4\'', 'KAWAT', '-', 'ROL', 85000.00, 0.000, 1, 'MU2447'
UNION ALL SELECT 'MU2448', 'LISBON GREY 40X40', 'TEHEL', 'ACCURA', 'DUS', 0.00, 8.000, 1, 'MU2448'
UNION ALL SELECT 'MU2449', 'FRT BOOT UK.39', 'SEPATU', 'FRT', 'PSG', 75000.00, 0.000, 1, 'MU2449'
UNION ALL SELECT 'MU2450', 'FRT BOOT UK.40', 'SEPATU', 'FRT', 'PSG', 75000.00, 0.000, 1, 'MU2450'
UNION ALL SELECT 'MU2451', 'GIBSUM', 'TRIPLEK', '-', 'LBR', 0.00, 0.000, 1, 'MU2451'
UNION ALL SELECT 'MU2452', 'ANGIN-ANGIN RO 66', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2452'
UNION ALL SELECT 'MU2453', 'ANGIN-ANGIN RO 90', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2453'
UNION ALL SELECT 'MU2454', 'ANGIN-ANGIN RO 57', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2454'
UNION ALL SELECT 'MU2455', 'ANGIN-ANGIN RO 85', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2455'
UNION ALL SELECT 'MU2456', 'ANGIN-ANGIN RO 37', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2456'
UNION ALL SELECT 'MU2457', 'ANGIN-ANGIN RO 49', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2457'
UNION ALL SELECT 'MU2458', 'ANGIN-ANGIN RO 48', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2458'
UNION ALL SELECT 'MU2459', 'ANGIN-ANGIN RO 21', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2459'
UNION ALL SELECT 'MU2460', 'ANGIN-ANGIN RO 20', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2460'
UNION ALL SELECT 'MU2461', 'STIK SPRAYER', 'ALAT', 'MIYAMOTO', 'PCS', 10000.00, 7.000, 1, 'MU2461'
UNION ALL SELECT 'MU2462', 'HANDLE SPRAYER', 'ALAT', '-', 'PCS', 0.00, 6.000, 1, 'MU2462'
UNION ALL SELECT 'MU2463', 'KEPALA SPRAYER', 'ALAT', '-', 'SET', 0.00, 0.000, 1, 'MU2463'
UNION ALL SELECT 'MU2464', 'SELANG BUANG KARET 4\' 50 M', 'SELANG', 'NOQIWA', 'ROL', 528000.00, 5.000, 1, 'MU2464'
UNION ALL SELECT 'MU2465', 'SELANG BUANG KARET 4\' 100 M', 'SELANG', 'NOQIWA', 'MTR', 16000.00, 0.000, 1, 'MU2465'
UNION ALL SELECT 'MU2466', 'HAND SOCKET 22MM X 1/2\' WIPRO', 'Kunci', 'WIPRO', 'PCS', 17875.00, 5.000, 1, 'MU2466'
UNION ALL SELECT 'MU2467', 'TERMINAL KABEL 5L X 5M', 'TERMINAL COK', 'NOQIWA', 'PCS', 31800.00, 0.000, 1, 'MU2467'
UNION ALL SELECT 'MU2468', 'KARET SENG', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2468'
UNION ALL SELECT 'MU2469', 'SUMBU KEONG', 'AKSESORIS', '-', 'MTR', 0.00, 0.000, 1, 'MU2469'
UNION ALL SELECT 'MU2470', 'KAIN MAJUN', 'KAIN LAP', '-', 'KG', 0.00, 0.000, 1, 'MU2470'
UNION ALL SELECT 'MU2471', 'TAMBANG PUTIH', 'TALI', '-', 'KG', 0.00, 0.000, 1, 'MU2471'
UNION ALL SELECT 'MU2472', 'ARCHI LED 18W', 'LAMPU', '-', 'PCS', 23500.00, 12.000, 1, 'MU2472'
UNION ALL SELECT 'MU2473', 'TERPAL A8 RAJAWALI 4x6', 'TERPAL', 'MATAHARI', 'LBR', 231080.00, 0.000, 1, 'MU2473'
UNION ALL SELECT 'MU2474', 'SENG TALANG BIRU 45CM', 'TALANG', 'KOALA', 'MTR', 20000.00, 3.000, 1, 'MU2474'
UNION ALL SELECT 'MU2475', 'STEKER SAKLAR 3 CABANG ST-6833', 'TERMINAL COK', 'LUMBA-LUMBA', 'PCS', 24000.00, 0.000, 1, 'MU2475'
UNION ALL SELECT 'MU2476', 'STARTER PULLEY NQWBG328-0304', 'S/PART', 'NOQIWA', 'PCS', 6486.00, 6.000, 1, 'MU2476'
UNION ALL SELECT 'MU2477', 'MUFFLER ASSY KENAPOT', 'S/PART', 'NOQIWA', 'PCS', 40000.00, 6.000, 1, 'MU2477'
UNION ALL SELECT 'MU2478', 'STEKER 3 CABANG KAKI 3', 'TERMINAL COK', 'MIYAMOTO', 'PCS', 6800.00, 7.000, 1, 'MU2478'
UNION ALL SELECT 'MU2479', 'SPEED CONTROL', 'S/PART', 'NOQIWA', 'PCS', 25000.00, 0.000, 1, 'MU2479'
UNION ALL SELECT 'MU2480', 'TUTUP AS CLUTCH', 'S/PART', 'NOQIWA', 'PCS', 50000.00, 6.000, 1, 'MU2480'
UNION ALL SELECT 'MU2491', 'LINGGIS KECIL', 'LINGGIS', '-', 'PCS', 25000.00, 0.000, 1, 'MU2491'
UNION ALL SELECT 'MU2492', 'KAWAT HARMONIKA 1M SEDANG', 'KAWAT', '-', 'MTR', 35000.00, 0.000, 1, 'MU2492'
UNION ALL SELECT 'MU2493', 'BEE BRAND 704', 'CAT', 'NIPPON PAINT', 'KLG', 52994.00, 0.000, 1, 'MU2493'
UNION ALL SELECT 'MU2494', 'BEE BRAND 623', 'CAT', 'NIPPON PAINT', 'KLG', 57600.00, 0.000, 1, 'MU2494'
UNION ALL SELECT 'MU2495', 'STEKER BROCO 1P', 'STEKER', 'BROCO', 'PCS', 2175.00, 12.000, 1, 'MU2495'
UNION ALL SELECT 'MU2496', 'FITTING BROCO BULAT HITAM', 'FITING', 'BROCO', 'PCS', 6000.00, 15.000, 1, 'MU2496'
UNION ALL SELECT 'MU2497', 'OB LEM GYPSUM', 'LEM', '-', 'SAK', 0.00, 0.000, 1, 'MU2497'
UNION ALL SELECT 'MU2498', 'FITTING BROCO KOTAK 1210', 'LEM', '-', 'SAK', 10500.00, 12.000, 1, 'MU2498'
UNION ALL SELECT 'MU2499', 'FITTING KOMBINASI BROCO', 'FITING', 'BROCO', 'PCS', 7500.00, 12.000, 1, 'MU2499'
UNION ALL SELECT 'MU2500', 'TERMINAL 5 LB MT-555', 'STEKER', 'MATSUI', 'PCS', 22885.00, 12.000, 1, 'MU2500'
UNION ALL SELECT 'MU2501', 'ISIAN STAPLES TEMBAK', 'S/PART', '-', 'DUS', 0.00, 0.000, 1, 'MU2501'
UNION ALL SELECT 'MU2503', 'SEROK BAMBU 27', 'SENDOK MAKAN', '-', 'PCS', 0.00, 0.000, 1, 'MU2503'
UNION ALL SELECT 'MU2504', 'AMPLAS GRIT 320', 'AMPLAS', '-', 'MTR', 8000.00, 0.000, 1, 'MU2504'
UNION ALL SELECT 'MU2506', 'RING PLAT 5/8', 'BAUT', '-', 'PCS', 0.00, 0.000, 1, 'MU2506'
UNION ALL SELECT 'MU2507', 'MURR 5/8', 'BAUT', '-', 'PCS', 0.00, 0.000, 1, 'MU2507'
UNION ALL SELECT 'MU2508', 'KUAS LUKIS', 'Kuas', '-', 'PCS', 0.00, 0.000, 1, 'MU2508'
UNION ALL SELECT 'MU2509', 'NIPPON WARNA CAMPUR', 'CAT', 'NIPPON PAINT', 'PCS', 0.00, 0.000, 1, 'MU2509'
UNION ALL SELECT 'MU2510', 'KAWAT LAS KARBIT', 'KAWAT', '-', 'KG', 0.00, 0.000, 1, 'MU2510'
UNION ALL SELECT 'MU2511', 'RODA GORDEN SILVER', 'AKSESORIS', '-', 'PAK', 0.00, 0.000, 1, 'MU2511'
UNION ALL SELECT 'MU2512', 'RODA GORDEN GOLD', 'AKSESORIS', '-', 'PAK', 0.00, 0.000, 1, 'MU2512'
UNION ALL SELECT 'MU2513', 'RODA GORDEN COKLAT', 'AKSESORIS', '-', 'PAK', 0.00, 0.000, 1, 'MU2513'
UNION ALL SELECT 'MU2514', 'RING GORDEN HITAM', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2514'
UNION ALL SELECT 'MU2515', 'TUTUP GORDEN HITAM', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2515'
UNION ALL SELECT 'MU2516', 'KAITAN GORDEN SILVER', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2516'
UNION ALL SELECT 'MU2517', 'RODA GORDEN PUTIH', 'AKSESORIS', '-', 'PAK', 0.00, 0.000, 1, 'MU2517'
UNION ALL SELECT 'MU2519', 'BETEL 8\"\'X16MM', 'BETEL', 'WIPRO', 'PCS', 50000.00, 6.000, 1, 'MU2519'
UNION ALL SELECT 'MU2520', 'BETEL 10\"\'X18MM', 'BETEL', 'WIPRO', 'PCS', 65000.00, 6.000, 1, 'MU2520'
UNION ALL SELECT 'MU2521', 'BOR BETON 10MM', 'MATA BOR', 'ALWIN', 'PCS', 14575.00, 6.000, 1, 'MU2521'
UNION ALL SELECT 'MU2522', 'OBENG GG KARET GNT 8+', 'OBENG', 'GNT', 'PCS', 20000.00, 0.000, 1, 'MU2522'
UNION ALL SELECT 'MU2523', 'OBENG GG KARET GNT 8-', 'OBENG', 'GNT', 'PCS', 20000.00, 0.000, 1, 'MU2523'
UNION ALL SELECT 'MU2524', 'OBENG - 8\"X3MM 0803S', 'OBENG', 'WIPRO', 'PCS', 23000.00, 0.000, 1, 'MU2524'
UNION ALL SELECT 'MU2525', 'OBENG TESPEN BB', 'OBENG', 'WIPRO', 'PCS', 30000.00, 0.000, 1, 'MU2525'
UNION ALL SELECT 'MU2526', 'PALU KARET 8OZ', 'PALU', 'WIPRO', 'PCS', 30000.00, 0.000, 1, 'MU2526'
UNION ALL SELECT 'MU2527', 'JET SHOWER MULLER 129 CROM', 'SHOWER', 'MULLER', 'PCS', 67500.00, 6.000, 1, 'MU2527'
UNION ALL SELECT 'MU2528', 'SHOWER TOILET SS-011 IVORY', 'SHOWER', 'SANSHAN', 'PCS', 40000.00, 0.000, 1, 'MU2528'
UNION ALL SELECT 'MU2529', 'SHOWER TOILET SS-011 BIRU', 'SHOWER', 'SANSHAN', 'PCS', 40000.00, 0.000, 1, 'MU2529'
UNION ALL SELECT 'MU2530', 'GEMBOK RANTAI', 'GEMBOK', 'SANSHAN', 'PCS', 25000.00, 0.000, 1, 'MU2530'
UNION ALL SELECT 'MU2531', 'TERMINAL SAKLAR 4L X 3M', 'TERMINAL COK', '-', 'PCS', 115500.00, 0.000, 1, 'MU2531'
UNION ALL SELECT 'MU2532', 'BRAKET REL KOTAK SILVER', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2532'
UNION ALL SELECT 'MU2533', 'BRAKET KOTAK GOLD', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2533'
UNION ALL SELECT 'MU2534', 'BESI KAITAN GORDEN', 'AKSESORIS', '-', 'BKS', 0.00, 0.000, 1, 'MU2534'
UNION ALL SELECT 'MU2535', 'AVIAN 190 100CC', 'CAT', 'Avian', 'PCS', 0.00, 8.000, 1, 'MU2535'
UNION ALL SELECT 'MU2536', 'AVIAN 192 100CC', 'CAT', 'Avian', 'PCS', 0.00, 8.000, 1, 'MU2536'
UNION ALL SELECT 'MU2537', 'AVIAN 309 100CC', 'CAT', 'Avian', 'PCS', 0.00, 8.000, 1, 'MU2537'
UNION ALL SELECT 'MU2538', 'AVIAN 466 100CC', 'CAT', 'Avian', 'PCS', 0.00, 8.000, 1, 'MU2538'
UNION ALL SELECT 'MU2539', 'AVIAN 650 100CC', 'CAT', 'Avian', 'PCS', 0.00, 0.000, 1, 'MU2539'
UNION ALL SELECT 'MU2540', 'AVIAN 657 100CC', 'CAT', 'Avian', 'PCS', 0.00, 8.000, 1, 'MU2540'
UNION ALL SELECT 'MU2541', 'AVIAN 732 100CC', 'CAT', 'Avian', 'PCS', 0.00, 8.000, 1, 'MU2541'
UNION ALL SELECT 'MU2542', 'AVIAN 733 100CC', 'CAT', 'Avian', 'PCS', 0.00, 8.000, 1, 'MU2542'
UNION ALL SELECT 'MU2543', 'AVIAN SB 100CC', 'CAT', 'Avian', 'PCS', 0.00, 8.000, 1, 'MU2543'
UNION ALL SELECT 'MU2544', 'AVIAN SW 100CC', 'CAT', 'Avian', 'PCS', 0.00, 8.000, 1, 'MU2544'
UNION ALL SELECT 'MU2545', 'WOOD ECO 112', 'CAT', 'Avian', 'KLG', 88000.00, 0.000, 1, 'MU2545'
UNION ALL SELECT 'MU2546', 'WOOD ECO 101', 'CAT', 'Avian', 'KLG', 88000.00, 0.000, 1, 'MU2546'
UNION ALL SELECT 'MU2547', 'WOOD ECO CG', 'CAT', 'Avian', 'KLG', 88000.00, 0.000, 1, 'MU2547'
UNION ALL SELECT 'MU2548', 'CLOSET PVC', 'CLOSET', 'MASPION', 'SET', 92000.00, 0.000, 1, 'MU2548'
UNION ALL SELECT 'MU2549', 'LEM FOX 300gr', 'LEM', 'FOX', 'KLG', 36000.00, 0.000, 1, 'MU2549'
UNION ALL SELECT 'MU2550', 'A 30 BANDO', 'V-BELT', 'BANDO', 'PCS', 14700.00, 0.000, 1, 'MU2550'
UNION ALL SELECT 'MU2551', 'A 51 BANDO', 'V-BELT', 'BANDO', 'PCS', 21500.00, 0.000, 1, 'MU2551'
UNION ALL SELECT 'MU2552', 'A 52 BANDO', 'V-BELT', 'BANDO', 'PCS', 22000.00, 0.000, 1, 'MU2552'
UNION ALL SELECT 'MU2553', 'A 53 BANDO', 'V-BELT', 'BANDO', 'PCS', 22500.00, 0.000, 1, 'MU2553'
UNION ALL SELECT 'MU2554', 'A 54 BANDO', 'V-BELT', 'BANDO', 'PCS', 23000.00, 0.000, 1, 'MU2554'
UNION ALL SELECT 'MU2555', 'A 55 BANDO', 'V-BELT', 'BANDO', 'PCS', 23000.00, 0.000, 1, 'MU2555'
UNION ALL SELECT 'MU2556', 'A 56 BANDO', 'V-BELT', 'BANDO', 'PCS', 23500.00, 0.000, 1, 'MU2556'
UNION ALL SELECT 'MU2557', 'A 57 BANDO', 'V-BELT', 'BANDO', 'PCS', 24000.00, 0.000, 1, 'MU2557'
UNION ALL SELECT 'MU2558', 'A 58 BANDO', 'V-BELT', 'BANDO', 'PCS', 24500.00, 0.000, 1, 'MU2558'
UNION ALL SELECT 'MU2559', 'A 59 BANDO', 'V-BELT', 'BANDO', 'PCS', 25000.00, 0.000, 1, 'MU2559'
UNION ALL SELECT 'MU2560', 'A 60 BANDO', 'V-BELT', 'BANDO', 'PCS', 25500.00, 0.000, 1, 'MU2560'
UNION ALL SELECT 'MU2561', 'A 61 BANDO', 'V-BELT', 'BANDO', 'PCS', 26000.00, 0.000, 1, 'MU2561'
UNION ALL SELECT 'MU2562', 'A 62 BANDO', 'V-BELT', 'BANDO', 'PCS', 26000.00, 0.000, 1, 'MU2562'
UNION ALL SELECT 'MU2563', 'A 63 BANDO', 'V-BELT', 'BANDO', 'PCS', 26500.00, 0.000, 1, 'MU2563'
UNION ALL SELECT 'MU2564', 'A 64 BANDO', 'V-BELT', 'BANDO', 'PCS', 27000.00, 0.000, 1, 'MU2564'
UNION ALL SELECT 'MU2565', 'A 65 BANDO', 'V-BELT', 'BANDO', 'PCS', 27500.00, 0.000, 1, 'MU2565'
UNION ALL SELECT 'MU2566', 'A 40 BANDO', 'V-BELT', 'BANDO', 'PCS', 17000.00, 0.000, 1, 'MU2566'
UNION ALL SELECT 'MU2567', 'A 41 BANDO', 'V-BELT', 'BANDO', 'PCS', 17500.00, 0.000, 1, 'MU2567'
UNION ALL SELECT 'MU2568', 'A 42 BANDO', 'V-BELT', 'BANDO', 'PCS', 18000.00, 0.000, 1, 'MU2568'
UNION ALL SELECT 'MU2569', 'A 43 BANDO', 'V-BELT', 'BANDO', 'PCS', 18000.00, 0.000, 1, 'MU2569'
UNION ALL SELECT 'MU2570', 'A 44 BANDO', 'V-BELT', 'BANDO', 'PCS', 18500.00, 0.000, 1, 'MU2570'
UNION ALL SELECT 'MU2571', 'A 45 BANDO', 'V-BELT', 'BANDO', 'PCS', 19000.00, 0.000, 1, 'MU2571'
UNION ALL SELECT 'MU2572', 'A 46 BANDO', 'V-BELT', 'BANDO', 'PCS', 19500.00, 0.000, 1, 'MU2572'
UNION ALL SELECT 'MU2573', 'A 47 BANDO', 'V-BELT', 'BANDO', 'PCS', 20000.00, 0.000, 1, 'MU2573'
UNION ALL SELECT 'MU2574', 'A 48 BANDO', 'V-BELT', 'BANDO', 'PCS', 20500.00, 0.000, 1, 'MU2574'
UNION ALL SELECT 'MU2575', 'A 49 BANDO', 'V-BELT', 'BANDO', 'PCS', 21000.00, 0.000, 1, 'MU2575'
UNION ALL SELECT 'MU2576', 'A 50 BANDO', 'V-BELT', 'BANDO', 'PCS', 21000.00, 0.000, 1, 'MU2576'
UNION ALL SELECT 'MU2577', 'B 60 BANDO', 'V-BELT', 'BANDO', 'PCS', 38000.00, 0.000, 1, 'MU2577'
UNION ALL SELECT 'MU2578', 'B 61 BANDO', 'V-BELT', 'BANDO', 'PCS', 38500.00, 0.000, 1, 'MU2578'
UNION ALL SELECT 'MU2579', 'B 62 BANDO', 'V-BELT', 'BANDO', 'PCS', 39000.00, 0.000, 1, 'MU2579'
UNION ALL SELECT 'MU2580', 'B 63 BANDO', 'V-BELT', 'BANDO', 'PCS', 40000.00, 0.000, 1, 'MU2580'
UNION ALL SELECT 'MU2581', 'B 64 BANDO', 'V-BELT', 'BANDO', 'PCS', 40500.00, 0.000, 1, 'MU2581'
UNION ALL SELECT 'MU2582', 'B 65 BANDO', 'V-BELT', 'BANDO', 'PCS', 41000.00, 0.000, 1, 'MU2582'
UNION ALL SELECT 'MU2583', 'B 66 BANDO', 'V-BELT', 'BANDO', 'PCS', 49250.00, 0.000, 1, 'MU2583'
UNION ALL SELECT 'MU2584', 'B 67 BANDO', 'V-BELT', 'BANDO', 'PCS', 50250.00, 0.000, 1, 'MU2584'
UNION ALL SELECT 'MU2585', 'B 68 BANDO', 'V-BELT', 'BANDO', 'PCS', 51000.00, 0.000, 1, 'MU2585'
UNION ALL SELECT 'MU2586', 'B 69 BANDO', 'V-BELT', 'BANDO', 'PCS', 43500.00, 0.000, 1, 'MU2586'
UNION ALL SELECT 'MU2587', 'B 70 BANDO', 'V-BELT', 'BANDO', 'PCS', 44500.00, 0.000, 1, 'MU2587'
UNION ALL SELECT 'MU2588', 'B 71 BANDO', 'V-BELT', 'BANDO', 'PCS', 45000.00, 0.000, 1, 'MU2588'
UNION ALL SELECT 'MU2589', 'B 72 BANDO', 'V-BELT', 'BANDO', 'PCS', 45500.00, 0.000, 1, 'MU2589'
UNION ALL SELECT 'MU2590', 'B 73 BANDO', 'V-BELT', 'BANDO', 'PCS', 54700.00, 0.000, 1, 'MU2590'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU2591' AS sku, 'B 74 BANDO' AS name, 'V-BELT' AS category_name, 'BANDO' AS brand_name, 'PCS' AS base_unit_code, 55500.00 AS cost_price, 0.000 AS min_stock, 1 AS is_active, 'MU2591' AS legacy_sku
UNION ALL SELECT 'MU2592', 'B 75 BANDO', 'V-BELT', 'BANDO', 'PCS', 47500.00, 0.000, 1, 'MU2592'
UNION ALL SELECT 'MU2593', 'B 76 BANDO', 'V-BELT', 'BANDO', 'PCS', 57000.00, 0.000, 1, 'MU2593'
UNION ALL SELECT 'MU2594', 'B 77 BANDO', 'V-BELT', 'BANDO', 'PCS', 57700.00, 0.000, 1, 'MU2594'
UNION ALL SELECT 'MU2595', 'B 78 BANDO', 'V-BELT', 'BANDO', 'PCS', 58500.00, 0.000, 1, 'MU2595'
UNION ALL SELECT 'MU2596', 'B 79 BANDO', 'V-BELT', 'BANDO', 'PCS', 50000.00, 0.000, 1, 'MU2596'
UNION ALL SELECT 'MU2597', 'B 80 BANDO', 'V-BELT', 'BANDO', 'PCS', 60000.00, 0.000, 1, 'MU2597'
UNION ALL SELECT 'MU2598', 'B 50 BANDO', 'V-BELT', 'BANDO', 'PCS', 32000.00, 0.000, 1, 'MU2598'
UNION ALL SELECT 'MU2599', 'B 51 BANDO', 'V-BELT', 'BANDO', 'PCS', 38500.00, 0.000, 1, 'MU2599'
UNION ALL SELECT 'MU2600', 'B 52 BANDO', 'V-BELT', 'BANDO', 'PCS', 33000.00, 0.000, 1, 'MU2600'
UNION ALL SELECT 'MU2601', 'B 53 BANDO', 'V-BELT', 'BANDO', 'PCS', 33500.00, 0.000, 1, 'MU2601'
UNION ALL SELECT 'MU2602', 'B 54 BANDO', 'V-BELT', 'BANDO', 'PCS', 34500.00, 0.000, 1, 'MU2602'
UNION ALL SELECT 'MU2603', 'B 55 BANDO', 'V-BELT', 'BANDO', 'PCS', 35000.00, 0.000, 1, 'MU2603'
UNION ALL SELECT 'MU2604', 'B 56 BANDO', 'V-BELT', 'BANDO', 'PCS', 35500.00, 0.000, 1, 'MU2604'
UNION ALL SELECT 'MU2605', 'KUNCI LACI 808 KECIL', 'Kunci', '808', 'PCS', 17500.00, 5.000, 1, 'MU2605'
UNION ALL SELECT 'MU2606', 'KUNCI BULAT 857 SS', 'Kunci', 'BOSHINI', 'SET', 45000.00, 0.000, 1, 'MU2606'
UNION ALL SELECT 'MU2607', 'CYLINDER MULLER KOMP BS', 'CYLINDER', 'MULLER', 'SET', 42084.00, 0.000, 1, 'MU2607'
UNION ALL SELECT 'MU2608', 'KUNCI EVELYN GEMINI', 'Kunci', 'MULLER', 'SET', 65000.00, 0.000, 1, 'MU2608'
UNION ALL SELECT 'MU2609', 'KUNCI EVELYN TAURUS', 'Kunci', 'MULLER', 'SET', 65000.00, 0.000, 1, 'MU2609'
UNION ALL SELECT 'MU2610', 'PAKU RIVET 429', 'Paku', 'RIVOTI', 'PCS', 50.00, 0.000, 1, 'MU2610'
UNION ALL SELECT 'MU2611', 'POLYBAG', 'PLASTIK', '-', 'KG', 0.00, 0.000, 1, 'MU2611'
UNION ALL SELECT 'MU2612', 'TINNER NIPPE 2000 A SPECIAL H.GLOSS 3.7L', 'TINNER', 'NIPPON PAINT', 'GLN', 134000.00, 0.000, 1, 'MU2612'
UNION ALL SELECT 'MU2613', 'PAKU RIVET 440', 'Paku', 'RIVOTI', 'PCS', 50.00, 0.000, 1, 'MU2613'
UNION ALL SELECT 'MU2614', 'PAKU RIVET 450', 'Paku', 'AP', 'PCS', 50.00, 0.000, 1, 'MU2614'
UNION ALL SELECT 'MU2615', 'PAKU RIVET 550 NICHIE', 'Paku', 'AP', 'PCS', 250.00, 0.000, 1, 'MU2615'
UNION ALL SELECT 'MU2616', 'PAKU RIVET 635', 'Paku', 'RIVOTI', 'PCS', 250.00, 0.000, 1, 'MU2616'
UNION ALL SELECT 'MU2617', 'PAKU RIVET 665 NICHIE', 'Paku', '-', 'PCS', 250.00, 0.000, 1, 'MU2617'
UNION ALL SELECT 'MU2618', 'PAKU RIVET 423', 'Paku', 'BENZ', 'PCS', 50.00, 0.000, 1, 'MU2618'
UNION ALL SELECT 'MU2619', 'CYLINDER MULLER TANGGUNG BS', 'CYLINDER', 'MULLER', 'SET', 0.00, 0.000, 1, 'MU2619'
UNION ALL SELECT 'MU2620', 'RORED SAE 90 1L', 'OLI', '-', 'BTL', 62100.00, 0.000, 1, 'MU2620'
UNION ALL SELECT 'MU2621', 'KUAS ROLL NIPPON', 'Kuas', 'MULLER', 'PCS', 30000.00, 12.000, 1, 'MU2621'
UNION ALL SELECT 'MU2622', 'YAMAOKE MESIN ALKON 3\"', 'MESIN', 'YAMAOKE', 'SET', 1145100.00, 5.000, 1, 'MU2622'
UNION ALL SELECT 'MU2623', 'KUNCI L 4MM STANDART', 'Kunci', 'WIPRO', 'PCS', 6400.00, 0.000, 1, 'MU2623'
UNION ALL SELECT 'MU2624', 'KAWAT RAM 1\' TEBAL', 'KAWAT RAM', '-', 'MTR', 0.00, 0.000, 1, 'MU2624'
UNION ALL SELECT 'MU2625', 'BOX UK. 35X52X17', 'BOX MAKANAN', '-', 'PCS', 0.00, 0.000, 1, 'MU2625'
UNION ALL SELECT 'MU2626', 'RING', 'BAUT', '-', 'PCS', 0.00, 0.000, 1, 'MU2626'
UNION ALL SELECT 'MU2627', 'ENGSEL LACI 2.5\"', 'ENGSEL', '-', 'PSG', 3750.00, 12.000, 1, 'MU2627'
UNION ALL SELECT 'MU2628', 'SELANG HANDSPRAYER', 'S/PART', 'NOQIWA', 'PCS', 8500.00, 6.000, 1, 'MU2628'
UNION ALL SELECT 'MU2629', 'MORTAR UTAMA SEMEN NAT', 'SEMEN', 'MORTAR', 'PCS', 0.00, 0.000, 1, 'MU2629'
UNION ALL SELECT 'MU2630', 'BOX UK. 25X15X40', 'BOX MAKANAN', '-', 'PCS', 0.00, 0.000, 1, 'MU2630'
UNION ALL SELECT 'MU2631', 'POT HTM 20', 'POT', '-', 'PCS', 0.00, 0.000, 1, 'MU2631'
UNION ALL SELECT 'MU2632', 'POT HTM 25', 'POT', '-', 'PCS', 0.00, 0.000, 1, 'MU2632'
UNION ALL SELECT 'MU2633', 'POT HTM 30', 'POT', '-', 'PCS', 0.00, 0.000, 1, 'MU2633'
UNION ALL SELECT 'MU2634', 'POT HTM 40', 'POT', '-', 'PCS', 0.00, 0.000, 1, 'MU2634'
UNION ALL SELECT 'MU2635', 'POT GANTUNG S', 'POT', '-', 'PCS', 0.00, 0.000, 1, 'MU2635'
UNION ALL SELECT 'MU2636', 'POT GANTUNG L', 'POT', '-', 'PCS', 0.00, 0.000, 1, 'MU2636'
UNION ALL SELECT 'MU2637', 'RING PLAT KECIL', 'BAUT', '-', 'PCS', 0.00, 0.000, 1, 'MU2637'
UNION ALL SELECT 'MU2638', 'METALLIC PAINT REAL GOLD', 'CAT', 'NIPPON PAINT', 'KLG', 0.00, 0.000, 1, 'MU2638'
UNION ALL SELECT 'MU2639', 'TUTUP GORDEN KOTAK G', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2639'
UNION ALL SELECT 'MU2640', 'TUTUP GORDEN KOTAK S', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2640'
UNION ALL SELECT 'MU2641', 'TUTUP GORDEN KOTAK C', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2641'
UNION ALL SELECT 'MU2642', 'BRACKET GORDEN KOTAK G', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2642'
UNION ALL SELECT 'MU2643', 'BRACKET GORDEN KOTAK S', 'AKSESORIS', '-', 'PCS', 0.00, 0.000, 1, 'MU2643'
UNION ALL SELECT 'MU2644', 'TALANG PUTIH', 'TALANG', '-', '50 METER', 0.00, 3.000, 1, 'MU2644'
UNION ALL SELECT 'MU2645', 'PAKU BETON 2 CM', 'Paku', '-', 'PCS', 0.00, 0.000, 1, 'MU2645'
UNION ALL SELECT 'MU2646', 'MATA BOR BESI 3 MM HSS M42', 'MATA BOR', 'WIPRO', 'PCS', 12650.00, 0.000, 1, 'MU2646'
UNION ALL SELECT 'MU2647', 'OLI REM', 'OLI', '-', 'BTL', 7000.00, 0.000, 1, 'MU2647'
UNION ALL SELECT 'MU2648', 'PLASTIK PAGAR POLOS', 'PLASTIK', '-', 'MTR', 44464.00, 0.000, 1, 'MU2648'
UNION ALL SELECT 'MU2649', 'SOCK DRAT 1/2 X 3/4', 'SAMBUNGAN', 'POWER', 'PCS', 2243.00, 0.000, 1, 'MU2649'
UNION ALL SELECT 'MU2650', 'ELBOW DRAT 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 2400.00, 0.000, 1, 'MU2650'
UNION ALL SELECT 'MU2651', 'ELBOW DRAT 1/2X3/4', 'SAMBUNGAN', 'POWER', 'PCS', 2650.00, 0.000, 1, 'MU2651'
UNION ALL SELECT 'MU2652', 'SOCK KRAN 3/4X1/2', 'SAMBUNGAN', 'POWER', 'PCS', 2029.00, 0.000, 1, 'MU2652'
UNION ALL SELECT 'MU2654', 'ELBOW DRAT 1', 'SAMBUNGAN', 'POWER', 'PCS', 5210.00, 0.000, 1, 'MU2654'
UNION ALL SELECT 'MU2655', 'AVIAN SYNTHETIC 197', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU2655'
UNION ALL SELECT 'MU2656', 'AVIAN SYNTHETIC 670', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU2656'
UNION ALL SELECT 'MU2657', 'MAGIC COM CRJ 323S SSV', 'MAGICCOM', 'COSMOS', 'UNIT', 260000.00, 0.000, 1, 'MU2657'
UNION ALL SELECT 'MU2658', 'AVIAN 750 100CC', 'CAT', 'Avian', 'KLG', 0.00, 8.000, 1, 'MU2658'
UNION ALL SELECT 'MU2659', 'AVIAN 197 100CC', 'CAT', 'Avian', 'KLG', 0.00, 8.000, 1, 'MU2659'
UNION ALL SELECT 'MU2660', 'AVIAN 193 100CC', 'CAT', 'Avian', 'KLG', 0.00, 8.000, 1, 'MU2660'
UNION ALL SELECT 'MU2661', 'AVIAN 198 100CC', 'CAT', 'Avian', 'KLG', 0.00, 8.000, 1, 'MU2661'
UNION ALL SELECT 'MU2662', 'AVIAN 301 100CC', 'CAT', 'Avian', 'KLG', 0.00, 0.000, 1, 'MU2662'
UNION ALL SELECT 'MU2663', 'AVIAN 645 100CC', 'CAT', 'Avian', 'KLG', 0.00, 8.000, 1, 'MU2663'
UNION ALL SELECT 'MU2664', 'AVIAN 194 100CC', 'CAT', 'Avian', 'KLG', 0.00, 8.000, 1, 'MU2664'
UNION ALL SELECT 'MU2665', 'AVIAN 480 100CC', 'CAT', 'Avian', 'KLG', 0.00, 8.000, 1, 'MU2665'
UNION ALL SELECT 'MU2666', 'AVIAN 652 100CC', 'CAT', 'Avian', 'KLG', 0.00, 8.000, 1, 'MU2666'
UNION ALL SELECT 'MU2667', 'AVIAN SB 200CC', 'CAT', 'Avian', 'KLG', 0.00, 8.000, 1, 'MU2667'
UNION ALL SELECT 'MU2668', 'KUAS ARCEL 3\"', 'Kuas', 'ARCEL', 'PCS', 16000.00, 24.000, 1, 'MU2668'
UNION ALL SELECT 'MU2669', 'KUAS ARCEL 1\"', 'Kuas', 'ARCEL', 'PCS', 3000.00, 24.000, 1, 'MU2669'
UNION ALL SELECT 'MU2670', 'KRAN AMICO BRASS 1/2\"', 'KRAN', 'AMICO', 'PCS', 42080.00, 0.000, 1, 'MU2670'
UNION ALL SELECT 'MU2671', 'KRAN AMICO BRASS 3/4\"', 'KRAN', 'AMICO', 'PCS', 47916.00, 7.000, 1, 'MU2671'
UNION ALL SELECT 'MU2672', 'KUAS ROLL BUDGET 9\'', 'Kuas', 'NIPPON PAINT', 'PCS', 15895.00, 7.000, 1, 'MU2672'
UNION ALL SELECT 'MU2673', 'KABEL TRANSPARAN 2X30', 'KABEL', '-', 'MTR', 1500.00, 0.000, 1, 'MU2673'
UNION ALL SELECT 'MU2674', 'KABEL TRANSPARAN 2X50', 'KABEL', '-', 'MTR', 2000.00, 0.000, 1, 'MU2674'
UNION ALL SELECT 'MU2675', 'KABEL TRANSPARAN 2X80', 'KABEL', '-', 'MTR', 2500.00, 0.000, 1, 'MU2675'
UNION ALL SELECT 'MU2676', 'OLI REM 300 ML', 'OLI', '-', 'BTL', 23500.00, 0.000, 1, 'MU2676'
UNION ALL SELECT 'MU2677', 'LAMPU MORGEN 17 W 4 JAM', 'LAMPU', '-', 'PCS', 94500.00, 6.000, 1, 'MU2677'
UNION ALL SELECT 'MU2678', 'PHILIPS LED 6W', 'LAMPU', '-', 'PCS', 30000.00, 0.000, 1, 'MU2678'
UNION ALL SELECT 'MU2679', 'HAK ANGIN BULAT 8\" CROME', 'HAK ANGIN', 'JAYA MULIA', 'PCS', 5000.00, 6.000, 1, 'MU2679'
UNION ALL SELECT 'MU2680', 'MINAMI MRC-1880 M', 'RICE COOKER', '-', 'SET', 0.00, 0.000, 1, 'MU2680'
UNION ALL SELECT 'MU2681', 'TERPAL A8 RAJAWALI 3X4', 'TERPAL', 'MATAHARI', 'LBR', 117053.00, 0.000, 1, 'MU2681'
UNION ALL SELECT 'MU2682', 'SEMEN GRESIK', 'SEMEN', 'GRESIK', 'SAK', 112000.00, 50.000, 1, 'MU2682'
UNION ALL SELECT 'MU2683', 'KUAS LUKIS 12', 'Kuas', 'GRESIK', 'PCS', 3750.00, 12.000, 1, 'MU2683'
UNION ALL SELECT 'MU2684', 'SEMEN BOSOWA', 'SEMEN', 'TONASA', 'SAK', 80000.00, 20.000, 1, 'MU2684'
UNION ALL SELECT 'MU2685', 'KUAS LUKIS 8', 'Kuas', 'TONASA', 'PCS', 2500.00, 12.000, 1, 'MU2685'
UNION ALL SELECT 'MU2686', 'PASIR', 'SEMEN', '-', 'SAK', 0.00, 0.000, 1, 'MU2686'
UNION ALL SELECT 'MU2687', 'PASIR AMBIL SENDIRI', 'SEMEN', '-', 'SAK', 0.00, 0.000, 1, 'MU2687'
UNION ALL SELECT 'MU2688', 'SEMEN UB', 'SEMEN', '-', 'SAK', 0.00, 0.000, 1, 'MU2688'
UNION ALL SELECT 'MU2689', 'MATA GURINDA DWTT 105S', 'MATA GURINDA', 'WIPRO', 'PCS', 0.00, 0.000, 1, 'MU2689'
UNION ALL SELECT 'MU2690', 'MATA GURINDA DWW-041G WIPRO', 'MATA GURINDA', 'WIPRO', 'PCS', 0.00, 0.000, 1, 'MU2690'
UNION ALL SELECT 'MU2691', 'POMPA AIR SANYO  P-H137AC', 'ALAT', 'SANYO', 'UNIT', 895000.00, 0.000, 1, 'MU2691'
UNION ALL SELECT 'MU2692', 'SENG BIRU GELOMBANG 4M', 'SENG', 'DIAMOND', 'LBR', 137624.00, 0.000, 1, 'MU2692'
UNION ALL SELECT 'MU2693', 'CLASSIC GAGANG PINTU', 'GAGANG PINTU', 'CLASSIC', 'SET', 0.00, 0.000, 1, 'MU2693'
UNION ALL SELECT 'MU2694', 'SENG SAKURA BIRU', 'SENG', 'SAKURA', 'LBR', 80000.00, 3.000, 1, 'MU2694'
UNION ALL SELECT 'MU2695', 'SENG PUTIH', 'SENG', 'GAJAH', 'LBR', 49500.00, 0.000, 1, 'MU2695'
UNION ALL SELECT 'MU2696', 'BISMA ALKON 3\"', 'MESIN', '-', 'UNIT', 975000.00, 0.000, 1, 'MU2696'
UNION ALL SELECT 'MU2697', 'SENG BENING GELOMBANG', 'SENG', '-', 'LBR', 63000.00, 0.000, 1, 'MU2697'
UNION ALL SELECT 'MU2698', 'MASKER OBAT SINGLE', 'ALAT', 'WIPRO', 'PCS', 45000.00, 0.000, 1, 'MU2698'
UNION ALL SELECT 'MU2699', 'SENG KOTAK BERLIAN', 'SENG', '-', 'LBR', 0.00, 0.000, 1, 'MU2699'
UNION ALL SELECT 'MU2700', 'BESI BETON 6MM', 'BESI', 'SNI', 'BTG', 25004.00, 50.000, 1, 'MU2700'
UNION ALL SELECT 'MU2701', 'BESI BETON 8MM', 'BESI', 'SNI', 'BTG', 44686.00, 50.000, 1, 'MU2701'
UNION ALL SELECT 'MU2702', 'BESI BETON 10 MM', 'BESI', 'SNI', 'BTG', 68860.00, 50.000, 1, 'MU2702'
UNION ALL SELECT 'MU2703', 'BESI BETON 12 MM', 'BESI', 'SNI', 'BTG', 99940.00, 50.000, 1, 'MU2703'
UNION ALL SELECT 'MU2704', 'BESI BETON 16 MM', 'BESI', 'SNI', 'BTG', 224000.00, 0.000, 1, 'MU2704'
UNION ALL SELECT 'MU2705', 'BESI PLAT 1MM', 'BESI', 'SNI', 'BTG', 0.00, 0.000, 1, 'MU2705'
UNION ALL SELECT 'MU2706', 'BESI PLAT 1.5MM', 'BESI', 'SNI', 'BTG', 0.00, 0.000, 1, 'MU2706'
UNION ALL SELECT 'MU2707', 'BESI PLAT 2 MM', 'BESI', 'SNI', 'BTG', 0.00, 0.000, 1, 'MU2707'
UNION ALL SELECT 'MU2708', 'BESI SIKU 40x40x4x600', 'BESI', 'SNI', 'BTG', 166000.00, 0.000, 1, 'MU2708'
UNION ALL SELECT 'MU2709', 'PROFILA TANK 750 L', 'PROFIL', 'PROFILA 88', 'UNIT', 1320000.00, 0.000, 1, 'MU2709'
UNION ALL SELECT 'MU2710', 'CONTRA PUSH ON', 'TERMINAL COK', 'PUSH ON', 'PCS', 4950.00, 0.000, 1, 'MU2710'
UNION ALL SELECT 'MU2711', 'GUNTING sedang', 'GUNTING', 'GUNINDO', 'PCS', 0.00, 0.000, 1, 'MU2711'
UNION ALL SELECT 'MU2712', 'BESI STRIP', 'BESI', 'SNI', 'BTG', 0.00, 0.000, 1, 'MU2712'
UNION ALL SELECT 'MU2713', 'BESI HOLO 20x40x1.6x600', 'BESI', 'SNI', 'BTG', 107100.00, 0.000, 1, 'MU2713'
UNION ALL SELECT 'MU2714', 'MATA BOR BESI 4.5 MM HSS M42', 'MATA BOR', 'WIPRO', 'PCS', 26400.00, 0.000, 1, 'MU2714'
UNION ALL SELECT 'MU2715', 'HOLO 20X40X1.2X600 GALVANISH', 'BESI', 'SNI', 'BTG', 110000.00, 0.000, 1, 'MU2715'
UNION ALL SELECT 'MU2716', 'TRIPLEK 3MM PALEM', 'PLYWOOD', 'PALEM', 'LBR', 0.00, 100.000, 1, 'MU2716'
UNION ALL SELECT 'MU2717', 'TRIPLEK 3MM SENGON', 'PLYWOOD', 'SENGON', 'LBR', 0.00, 100.000, 1, 'MU2717'
UNION ALL SELECT 'MU2718', 'TRIPLEK 4MM PALEM', 'PLYWOOD', 'PALEM', 'LBR', 0.00, 100.000, 1, 'MU2718'
UNION ALL SELECT 'MU2719', 'TRIPLEK 4MM SENGON', 'PLYWOOD', 'SENGON', 'LBR', 0.00, 100.000, 1, 'MU2719'
UNION ALL SELECT 'MU2720', 'TRIPLEK 5 MM PALEM', 'PLYWOOD', 'PALEM', 'LBR', 0.00, 100.000, 1, 'MU2720'
UNION ALL SELECT 'MU2721', 'TRIPLEK 10 MM PALEM', 'PLYWOOD', 'PALEM', 'LBR', 100000.00, 100.000, 1, 'MU2721'
UNION ALL SELECT 'MU2722', 'TRIPLEK 12 MM PALEM', 'PLYWOOD', 'PALEM', 'LBR', 0.00, 100.000, 1, 'MU2722'
UNION ALL SELECT 'MU2723', 'REGAL GREY 25X25', 'TEHEL', 'ASIA TILE', 'DUS', 0.00, 8.000, 1, 'MU2723'
UNION ALL SELECT 'MU2724', 'HALCON GREY 40X40', 'TEHEL', 'ACCURA', 'DUS', 60000.00, 8.000, 1, 'MU2724'
UNION ALL SELECT 'MU2725', 'HICKORY BEIGE', 'TEHEL', '-', 'DUS', 66000.00, 0.000, 1, 'MU2725'
UNION ALL SELECT 'MU2726', 'HICKORY GREY 40X40', 'TEHEL', 'ACCURA', 'DUS', 66000.00, 8.000, 1, 'MU2726'
UNION ALL SELECT 'MU2727', 'HARTES GREY', 'TEHEL', '-', 'DUS', 0.00, 0.000, 1, 'MU2727'
UNION ALL SELECT 'MU2728', 'HONOLULU GREEN', 'TEHEL', '-', 'DUS', 60000.00, 0.000, 1, 'MU2728'
UNION ALL SELECT 'MU2729', 'HONOLULU BEIGE', 'TEHEL', '-', 'DUS', 60000.00, 0.000, 1, 'MU2729'
UNION ALL SELECT 'MU2730', 'HAINES BEIGE 40X40', 'TEHEL', 'ACCURA', 'DUS', 0.00, 8.000, 1, 'MU2730'
UNION ALL SELECT 'MU2731', 'ESMERALDA GOLD', 'TEHEL', '-', 'DUS', 75000.00, 0.000, 0, 'MU2731'
UNION ALL SELECT 'MU2732', 'ESMERALDA GRIGIO', 'TEHEL', '-', 'DUS', 75000.00, 0.000, 0, 'MU2732'
UNION ALL SELECT 'MU2733', 'MATA BOR TEMBOK 8X310', 'MATA BOR', 'ALWIN', 'PCS', 24200.00, 6.000, 1, 'MU2733'
UNION ALL SELECT 'MU2734', 'ALLWIN KUNCI L 8 PCS', 'ALAT', 'ALWIN', 'PCS', 35750.00, 2.000, 1, 'MU2734'
UNION ALL SELECT 'MU2735', 'BELGIUM GREY 50X50', 'TEHEL', 'JUPITER', 'DUS', 72399.00, 8.000, 1, 'MU2735'
UNION ALL SELECT 'MU2736', 'RIVIERA RED 25X25', 'TEHEL', 'ASIA TILE', 'DUS', 0.00, 8.000, 1, 'MU2736'
UNION ALL SELECT 'MU2737', 'ROMA GREY 25X25', 'TEHEL', 'ASIA TILE', 'DUS', 0.00, 8.000, 1, 'MU2737'
UNION ALL SELECT 'MU2738', 'EMERALD GREEN 20X25', 'TEHEL', '-', 'DUS', 0.00, 0.000, 0, 'MU2738'
UNION ALL SELECT 'MU2739', 'EMERALD BEIGE 20X25', 'TEHEL', '-', 'DUS', 0.00, 8.000, 1, 'MU2739'
UNION ALL SELECT 'MU2740', 'SERUNI BLUE 25X25', 'TEHEL', '-', 'DUS', 0.00, 8.000, 1, 'MU2740'
UNION ALL SELECT 'MU2741', 'SELANG M.SEMPROT', 'S/PART', 'NOQIWA', 'PCS', 75000.00, 6.000, 1, 'MU2741'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU2742' AS sku, 'B 103 BANDO' AS name, 'V-BELT' AS category_name, 'BANDO' AS brand_name, 'PCS' AS base_unit_code, 77250.00 AS cost_price, 0.000 AS min_stock, 1 AS is_active, 'MU2742' AS legacy_sku
UNION ALL SELECT 'MU2743', 'RUANDA BLUE 25X25', 'TEHEL', 'GRASIA TILE', 'DUS', 0.00, 8.000, 1, 'MU2743'
UNION ALL SELECT 'MU2744', 'STEKER GONEO', 'STEKER', '-', 'PCS', 14500.00, 8.000, 1, 'MU2744'
UNION ALL SELECT 'MU2745', 'IKAD PINK 25X40', 'TEHEL', '-', 'DUS', 82150.00, 8.000, 1, 'MU2745'
UNION ALL SELECT 'MU2746', 'YOKO 82 TOBA BLUE', 'CAT', 'Avian', 'GLN', 155800.00, 0.000, 1, 'MU2746'
UNION ALL SELECT 'MU2747', 'DESERTA BEIGE 20X20', 'TEHEL', '-', 'DUS', 0.00, 0.000, 1, 'MU2747'
UNION ALL SELECT 'MU2748', 'SAMAN BROWN 20X40', 'TEHEL', 'DIAMOND', 'DUS', 0.00, 8.000, 1, 'MU2748'
UNION ALL SELECT 'MU2749', 'B 102 BANDO', 'TEHEL', '-', 'DUS', 76500.00, 0.000, 1, 'MU2749'
UNION ALL SELECT 'MU2750', 'B 101 BANDO', 'V-BELT', 'BANDO', 'PCS', 75750.00, 0.000, 1, 'MU2750'
UNION ALL SELECT 'MU2751', 'B 100 BANDO', 'V-BELT', 'BANDO', 'PCS', 75000.00, 0.000, 1, 'MU2751'
UNION ALL SELECT 'MU2752', 'B 106 BANDO', 'V-BELT', 'BANDO', 'PCS', 79500.00, 0.000, 1, 'MU2752'
UNION ALL SELECT 'MU2753', 'YOKO CHILI RED 81', 'CAT', 'Avian', 'GLN', 155800.00, 0.000, 1, 'MU2753'
UNION ALL SELECT 'MU2754', 'JARUM KERAS M12', 'ALAT', '-', 'SET', 0.00, 0.000, 1, 'MU2754'
UNION ALL SELECT 'MU2755', 'KAPI DEMPUL BESI SET', 'KAPI', '-', 'SET', 25000.00, 5.000, 1, 'MU2755'
UNION ALL SELECT 'MU2756', 'FILTER M.ALKON', 'S/PART', 'YAMAOKE', 'PCS', 0.00, 6.000, 1, 'MU2756'
UNION ALL SELECT 'MU2757', 'HANDSPRAYER MURAKI 20L', 'SPRAYER', 'MURAKI', 'UNIT', 1045333.00, 8.000, 1, 'MU2757'
UNION ALL SELECT 'MU2758', 'SALSA BROWN 20X40', 'TEHEL', '-', 'DUS', 0.00, 8.000, 1, 'MU2758'
UNION ALL SELECT 'MU2759', 'KENALPOT M.ALKON YAMAOKE', 'S/PART', 'YAMAOKE', 'PCS', 85000.00, 6.000, 1, 'MU2759'
UNION ALL SELECT 'MU2760', 'IKAD 40X40', 'TEHEL', 'IKAD', 'DUS', 76045.00, 8.000, 1, 'MU2760'
UNION ALL SELECT 'MU2761', 'PIPA PVC 4\" C PUTIH K', 'PIPA', 'POWER', 'BTG', 110379.00, 0.000, 1, 'MU2761'
UNION ALL SELECT 'MU2762', 'KUNCI LACI 502 CAMEL', 'Kunci', '-', 'PCS', 9000.00, 6.000, 1, 'MU2762'
UNION ALL SELECT 'MU2763', 'METERAN TOLEDO 7.5M', 'METERAN', 'TOLEDO', 'PCS', 19000.00, 0.000, 1, 'MU2763'
UNION ALL SELECT 'MU2764', 'CAKALELE BROWN 20X40', 'TEHEL', 'DIAMOND', 'DUS', 0.00, 8.000, 1, 'MU2764'
UNION ALL SELECT 'MU2765', 'L KYOTO BLK 40X40', 'TEHEL', 'IKAD', 'DUS', 74370.00, 8.000, 1, 'MU2765'
UNION ALL SELECT 'MU2766', 'AMAZON BEIGE 40X40', 'TEHEL', 'PLATINUM', 'DUS', 0.00, 8.000, 1, 'MU2766'
UNION ALL SELECT 'MU2767', 'KLEM PIPA 1/2 + PAKU', 'KLEM', '-', 'BKS', 5500.00, 7.000, 1, 'MU2767'
UNION ALL SELECT 'MU2768', 'KLEM PIPA 3/4 + PAKU', 'S/PART', '-', 'BKS', 6000.00, 7.000, 1, 'MU2768'
UNION ALL SELECT 'MU2769', 'MAGIC COM CRJ 323S SSO', 'MAGICCOM', 'COSMOS', 'UNIT', 260000.00, 0.000, 1, 'MU2769'
UNION ALL SELECT 'MU2770', 'PETRONAS GREY 40X40', 'TEHEL', '-', 'DUS', 0.00, 8.000, 1, 'MU2770'
UNION ALL SELECT 'MU2771', 'GAGANG LUMINO HITAM', 'GAGANG PINTU', '-', 'PSG', 0.00, 0.000, 1, 'MU2771'
UNION ALL SELECT 'MU2772', 'KLEM PIPA 1 + PAKU', 'KLEM', '-', 'BKS', 7500.00, 7.000, 1, 'MU2772'
UNION ALL SELECT 'MU2773', 'KRAN TEMBOK VALEN 1/2', 'KRAN', 'VALLEN', 'PCS', 25000.00, 7.000, 1, 'MU2773'
UNION ALL SELECT 'MU2774', 'GAGANG LUMINO SILVER', 'GAGANG PINTU', '-', 'PSG', 0.00, 0.000, 1, 'MU2774'
UNION ALL SELECT 'MU2775', 'PENDET BROWN 20X40', 'TEHEL', 'DIAMOND', 'DUS', 0.00, 8.000, 1, 'MU2775'
UNION ALL SELECT 'MU2776', 'SIKU TUKANG 12\" STALLION', 'SIKU TUKANG', 'STALLION', 'PCS', 9500.00, 7.000, 1, 'MU2776'
UNION ALL SELECT 'MU2777', 'SERIMPI BROWN 20X40', 'TEHEL', 'DIAMOND', 'DUS', 0.00, 8.000, 1, 'MU2777'
UNION ALL SELECT 'MU2778', 'MALIOBORO GREY 25X50', 'TEHEL', 'DIAMOND', 'DUS', 100000.00, 8.000, 1, 'MU2778'
UNION ALL SELECT 'MU2779', 'PHILIPPE 02 60X60', 'TEHEL', 'SERENITY', 'DUS', 0.00, 8.000, 1, 'MU2779'
UNION ALL SELECT 'MU2780', 'IVORY IND 60X60', 'TEHEL', '-', 'DUS', 0.00, 0.000, 1, 'MU2780'
UNION ALL SELECT 'MU2781', 'PHILIPPE 01 60X60', 'TEHEL', 'SERENITY', 'DUS', 0.00, 8.000, 1, 'MU2781'
UNION ALL SELECT 'MU2782', 'PHILIPPE 03 60X60', 'TEHEL', '-', 'DUS', 0.00, 0.000, 0, 'MU2782'
UNION ALL SELECT 'MU2783', 'SEVILLA DARK 60X60', 'TEHEL', '-', 'DUS', 0.00, 0.000, 1, 'MU2783'
UNION ALL SELECT 'MU2784', 'SAVOY GREY 60X60', 'TEHEL', 'PLATINUM', 'DUS', 0.00, 8.000, 1, 'MU2784'
UNION ALL SELECT 'MU2785', 'FONTE BLACK 40X40', 'TEHEL', 'ASIA TILE', 'DUS', 63000.00, 8.000, 1, 'MU2785'
UNION ALL SELECT 'MU2786', 'BOX UK. 25X30X40', 'BOX MAKANAN', '-', 'PCS', 0.00, 0.000, 1, 'MU2786'
UNION ALL SELECT 'MU2787', 'TEHEL DARI RUMAH', 'TEHEL', '-', 'DUS', 0.00, 0.000, 1, 'MU2787'
UNION ALL SELECT 'MU2788', 'KLEM PIPA 5/8 + PAKU', 'KLEM', 'FRT', 'BKS', 12500.00, 7.000, 1, 'MU2788'
UNION ALL SELECT 'MU2789', 'SENTER KEPALA WD-303', 'AKSESORIS', '-', 'UNIT', 65000.00, 8.000, 1, 'MU2789'
UNION ALL SELECT 'MU2790', 'KENALPOT M.SEMPROT', 'S/PART', 'NOQIWA', 'PCS', 30000.00, 6.000, 1, 'MU2790'
UNION ALL SELECT 'MU2791', 'WAKUL SUMO T', 'ALAT', 'SUMMO', 'PCS', 20000.00, 0.000, 1, 'MU2791'
UNION ALL SELECT 'MU2792', 'NEPAL GREY 50X50', 'TEHEL', 'JUPITER', 'DUS', 72399.00, 8.000, 1, 'MU2792'
UNION ALL SELECT 'MU2793', 'SWISS BEIGE 50X50', 'TEHEL', 'JUPITER', 'DUS', 72399.00, 8.000, 1, 'MU2793'
UNION ALL SELECT 'MU2794', 'DELMAR BROWN 40X40', 'TEHEL', 'DIAMOND', 'DUS', 0.00, 8.000, 1, 'MU2794'
UNION ALL SELECT 'MU2795', 'RAVENA GREEN 40X40', 'TEHEL', 'DIAMOND', 'DUS', 0.00, 8.000, 1, 'MU2795'
UNION ALL SELECT 'MU2796', 'RAVENA BROWN 40X40', 'TEHEL', 'DIAMOND', 'DUS', 0.00, 8.000, 1, 'MU2796'
UNION ALL SELECT 'MU2797', 'VIOLA VERDE 40X40', 'TEHEL', 'DIAMOND', 'DUS', 0.00, 8.000, 1, 'MU2797'
UNION ALL SELECT 'MU2798', 'VIOLA BROWN 40X40', 'TEHEL', 'DIAMOND', 'DUS', 0.00, 8.000, 1, 'MU2798'
UNION ALL SELECT 'MU2799', 'WAKUL SUMO B', 'ALAT', 'SUMMO', 'PCS', 13000.00, 0.000, 1, 'MU2799'
UNION ALL SELECT 'MU2800', 'JARUM KARUNG', 'ALAT', '-', 'PCS', 850.00, 7.000, 1, 'MU2800'
UNION ALL SELECT 'MU2801', 'HANEDA GREY 40X40', 'TEHEL', 'ACCURA', 'DUS', 5.00, 8.000, 1, 'MU2801'
UNION ALL SELECT 'MU2802', 'GRANIT CREAM BE ONE 60X60', 'TEHEL', 'BE ONE', 'DUS', 0.00, 8.000, 1, 'MU2802'
UNION ALL SELECT 'MU2803', 'RODA RELL 345 MULLER', 'RODA', 'MULLER', 'SET', 98500.00, 7.000, 1, 'MU2803'
UNION ALL SELECT 'MU2804', 'SIKU TUKANG 12 FUKUDA', 'SIKU TUKANG', 'FUKUDA', 'PCS', 9583.00, 7.000, 1, 'MU2804'
UNION ALL SELECT 'MU2805', 'PIPA BESI PUTIH 2X1.5X600', 'PIPA', '-', 'BTG', 0.00, 0.000, 1, 'MU2805'
UNION ALL SELECT 'MU2806', 'GEMBOK GLOBE 40MM', 'GEMBOK', 'GLOBE', 'PCS', 17500.00, 12.000, 1, 'MU2806'
UNION ALL SELECT 'MU2807', 'ALENZA GREY 40X40', 'TEHEL', 'JUPITER', 'DUS', 51399.00, 8.000, 1, 'MU2807'
UNION ALL SELECT 'MU2808', 'PENGGARIS WATERPAS 24\" IGM', 'WATERPASS', 'IGM', 'PCS', 55000.00, 7.000, 1, 'MU2808'
UNION ALL SELECT 'MU2809', 'KUAS ROLL KAPAL IDEKU 4\"', 'Kuas', 'IDEKU', 'PCS', 5416.00, 7.000, 1, 'MU2809'
UNION ALL SELECT 'MU2810', 'DALLAS BLACK 40X40', 'TEHEL', 'DIAMOND', 'DUS', 0.00, 8.000, 1, 'MU2810'
UNION ALL SELECT 'MU2811', 'KUAS ROLL KAPAL HASTON BUSA', 'Kuas', 'HASSTON', 'PCS', 18500.00, 7.000, 1, 'MU2811'
UNION ALL SELECT 'MU2812', 'OBENG BB WIPRO HIJAU', 'OBENG', 'WIPRO', 'PCS', 0.00, 6.000, 1, 'MU2812'
UNION ALL SELECT 'MU2813', 'PIPA PVC 5/8 C ABU K', 'PIPA', 'POWER', 'BTG', 9532.00, 0.000, 1, 'MU2813'
UNION ALL SELECT 'MU2814', 'PIPA PVC 1/2 AW ABU', 'PIPA', 'POWER', 'BTG', 41100.00, 0.000, 1, 'MU2814'
UNION ALL SELECT 'MU2815', 'PIPA PVC 1/2 C PUTIH', 'PIPA', 'POWER', 'BTG', 18200.00, 9.000, 1, 'MU2815'
UNION ALL SELECT 'MU2816', 'PIPA PVC 3/4 AW PUTIH', 'PIPA', 'POWER', 'BTG', 42000.00, 9.000, 1, 'MU2816'
UNION ALL SELECT 'MU2817', 'PIPA PVC 3/4 C PUTIH K', 'PIPA', 'POWER', 'BTG', 26400.00, 0.000, 1, 'MU2817'
UNION ALL SELECT 'MU2818', 'PIPA PVC 3/4 C ABU', 'PIPA', 'POWER', 'BTG', 28200.00, 9.000, 1, 'MU2818'
UNION ALL SELECT 'MU2819', 'PIPA PVC 1 AW PUTIH', 'PIPA', 'POWER', 'BTG', 49580.00, 9.000, 1, 'MU2819'
UNION ALL SELECT 'MU2820', 'PIPA PVC 1 C ABU', 'PIPA', 'POWER', 'BTG', 30826.00, 9.000, 1, 'MU2820'
UNION ALL SELECT 'MU2821', 'PIPA PVC 1 1/2 D PUTIH', 'PIPA', 'POWER', 'BTG', 52657.00, 9.000, 1, 'MU2821'
UNION ALL SELECT 'MU2822', 'PIPA PVC 1 1/2 C ABU', 'PIPA', 'POWER', 'BTG', 46195.00, 9.000, 1, 'MU2822'
UNION ALL SELECT 'MU2823', 'PIPA PVC 2 D PUTIH', 'PIPA', 'POWER', 'BTG', 67487.00, 9.000, 1, 'MU2823'
UNION ALL SELECT 'MU2824', 'PIPA PVC 2 C ABU K', 'PIPA', 'POWER', 'BTG', 59337.00, 0.000, 1, 'MU2824'
UNION ALL SELECT 'MU2825', 'PIPA PVC 3 D ABU', 'PIPA', 'POWER', 'BTG', 152656.00, 9.000, 1, 'MU2825'
UNION ALL SELECT 'MU2826', 'PIPA PVC 3 D PUTIH', 'PIPA', 'POWER', 'BTG', 121214.00, 9.000, 1, 'MU2826'
UNION ALL SELECT 'MU2827', 'PIPA PVC 4 D PUTIH', 'PIPA', 'POWER', 'BTG', 190652.00, 9.000, 1, 'MU2827'
UNION ALL SELECT 'MU2828', 'PIPA PVC 4 D ABU', 'PIPA', 'POWER', 'BTG', 214530.00, 9.000, 1, 'MU2828'
UNION ALL SELECT 'MU2829', 'PIPA PVC 5 D PUTIH', 'PIPA', 'POWER', 'BTG', 293624.00, 9.000, 1, 'MU2829'
UNION ALL SELECT 'MU2830', 'PIPA PVC 2 1/2 D ABU K', 'PIPA', 'POWER', 'BTG', 91244.00, 0.000, 1, 'MU2830'
UNION ALL SELECT 'MU2831', 'PIPA PVC 2 1/2 C ABU', 'PIPA', 'POWER', 'BTG', 75487.00, 9.000, 1, 'MU2831'
UNION ALL SELECT 'MU2832', 'JAYA 1 D', 'PIPA', 'JAYA', 'BTG', 0.00, 0.000, 1, 'MU2832'
UNION ALL SELECT 'MU2833', 'JAYA 3 D', 'PIPA', 'JAYA', 'BTG', 0.00, 0.000, 1, 'MU2833'
UNION ALL SELECT 'MU2834', 'JAYA 4 D', 'PIPA', 'JAYA', 'BTG', 0.00, 0.000, 1, 'MU2834'
UNION ALL SELECT 'MU2835', 'JAYA 5 D', 'PIPA', 'JAYA', 'BTG', 0.00, 9.000, 1, 'MU2835'
UNION ALL SELECT 'MU2836', 'JAYA 3/4 D', 'PIPA', 'JAYA', 'BTG', 0.00, 0.000, 1, 'MU2836'
UNION ALL SELECT 'MU2837', 'KAWAT LAS STAINLESS SS 308 2.6', 'KAWAT LAS', 'TECH WELL', 'KG', 152000.00, 0.000, 1, 'MU2837'
UNION ALL SELECT 'MU2838', 'KAWAT LAS STAINLESS SS 308 2 MM', 'KAWAT LAS', 'TECH WELL', 'KG', 179000.00, 0.000, 1, 'MU2838'
UNION ALL SELECT 'MU2839', 'BOSTER CB 204', 'S/PART', 'MAKITA', 'DUS', 80000.00, 0.000, 1, 'MU2839'
UNION ALL SELECT 'MU2840', 'KAWAT LAS RD 460 2MM', 'KAWAT LAS', 'RB 2.6', 'KG', 30450.00, 0.000, 1, 'MU2840'
UNION ALL SELECT 'MU2841', 'PIPA PVC 1/2 C ABU ABU', 'PIPA', 'POWER', 'BTG', 19600.00, 9.000, 1, 'MU2841'
UNION ALL SELECT 'MU2842', 'REVENA BROWN', 'KERAMIK', 'ACCURA', 'DUS', 0.00, 0.000, 0, 'MU2842'
UNION ALL SELECT 'MU2843', 'BRECIA CREAM', 'KERAMIK', 'ACCURA', 'DUS', 0.00, 0.000, 1, 'MU2843'
UNION ALL SELECT 'MU2844', 'IGNITION COIL YMKYX200 - 0405', 'S/PART', 'YAMAOKE', 'PCS', 32162.00, 6.000, 1, 'MU2844'
UNION ALL SELECT 'MU2845', 'STIK CUCI MOTOR 90CM', 'S/PART', 'NOQIWA', 'PCS', 34414.00, 6.000, 1, 'MU2845'
UNION ALL SELECT 'MU2846', 'STIK CUCI MOTOR 60CM', 'S/PART', 'NOQIWA', 'PCS', 36486.00, 6.000, 1, 'MU2846'
UNION ALL SELECT 'MU2847', 'RECOIL STARTER ASSY NQW767 - 0301', 'S/PART', 'NOQIWA', 'UNIT', 23423.00, 6.000, 1, 'MU2847'
UNION ALL SELECT 'MU2848', 'METERAN 30M WIPRO', 'METERAN', 'WIPRO', 'PCS', 75000.00, 4.000, 1, 'MU2848'
UNION ALL SELECT 'MU2849', 'KUNCI PAS SET 6-24MM', 'Kunci', 'WIPRO', 'SET', 297000.00, 2.000, 1, 'MU2849'
UNION ALL SELECT 'MU2850', 'KUNCI PIPA 8\'', 'Kunci', 'WIPRO', 'PCS', 90000.00, 2.000, 1, 'MU2850'
UNION ALL SELECT 'MU2851', 'VALDA RED 20X25', 'TEHEL', 'ASIA TILE', 'DUS', 49000.00, 8.000, 1, 'MU2851'
UNION ALL SELECT 'MU2852', 'VALDA GREEN 20X25', 'TEHEL', 'ASIA TILE', 'DUS', 49000.00, 10.000, 0, 'MU2852'
UNION ALL SELECT 'MU2853', 'VALDA BLUE 20X25', 'TEHEL', 'ASIA TILE', 'DUS', 49000.00, 10.000, 0, 'MU2853'
UNION ALL SELECT 'MU2854', 'METRO BASIC 20X25', 'TEHEL', 'ASIA TILE', 'DUS', 54500.00, 10.000, 1, 'MU2854'
UNION ALL SELECT 'MU2855', 'SODA API KRISTAL 0.5 KG', 'ALAT', '-', 'BKS', 14000.00, 7.000, 1, 'MU2855'
UNION ALL SELECT 'MU2856', 'OREGON GREY 40X40', 'TEHEL', 'ASIA TILE', 'DUS', 59500.00, 8.000, 1, 'MU2856'
UNION ALL SELECT 'MU2857', 'OAKWOOD BROWN 40X40', 'TEHEL', 'ASIA TILE', 'DUS', 54500.00, 8.000, 1, 'MU2857'
UNION ALL SELECT 'MU2858', 'MOSCOW CREAM 40X40', 'TEHEL', 'ASIA TILE', 'DUS', 50000.00, 8.000, 1, 'MU2858'
UNION ALL SELECT 'MU2859', 'ANDROMEDA GREEN 40X40', 'TEHEL', 'ASIA TILE', 'DUS', 54500.00, 8.000, 1, 'MU2859'
UNION ALL SELECT 'MU2860', 'ISTANBUL BLUE 40X40', 'TEHEL', 'ASIA TILE', 'DUS', 54500.00, 8.000, 1, 'MU2860'
UNION ALL SELECT 'MU2861', 'MADISON GREEN 40X40', 'TEHEL', 'ASIA TILE', 'DUS', 50000.00, 8.000, 1, 'MU2861'
UNION ALL SELECT 'MU2862', 'OTTO GREY 40X40', 'TEHEL', 'ASIA TILE', 'DUS', 50000.00, 8.000, 1, 'MU2862'
UNION ALL SELECT 'MU2863', 'ISTANBUL GREY 40X40', 'TEHEL', 'ASIA TILE', 'DUS', 45500.00, 8.000, 1, 'MU2863'
UNION ALL SELECT 'MU2864', 'AVITEX 712 5KG', 'CAT', 'AVITEX', 'GLN', 162500.00, 0.000, 1, 'MU2864'
UNION ALL SELECT 'MU2865', 'AVITEX 740 5KG', 'CAT', 'AVITEX', 'GLN', 162500.00, 0.000, 1, 'MU2865'
UNION ALL SELECT 'MU2866', 'AVITEX 750 5KG', 'CAT', 'AVITEX', 'GLN', 162500.00, 0.000, 1, 'MU2866'
UNION ALL SELECT 'MU2867', 'AVITEX 751 5KG', 'CAT', 'AVITEX', 'GLN', 162500.00, 0.000, 1, 'MU2867'
UNION ALL SELECT 'MU2868', 'GUNTING BESAR', 'GUNTING', 'GUNINDO', 'PCS', 0.00, 12.000, 1, 'MU2868'
UNION ALL SELECT 'MU2869', 'TALI GAS MESIN BABAT', 'S/PART', 'NOQIWA', 'PCS', 0.00, 6.000, 1, 'MU2869'
UNION ALL SELECT 'MU2870', 'BOSTER CB-459', 'S/PART', 'MAKITA', 'DUS', 46200.00, 10.000, 1, 'MU2870'
UNION ALL SELECT 'MU2871', 'CUTTER L-500', 'CUTTER', 'KENKO', 'PCS', 12083.00, 6.000, 1, 'MU2871'
UNION ALL SELECT 'MU2872', 'BESI STRIP 1,75X0.2X400', 'BESI', '-', 'BTG', 0.00, 0.000, 1, 'MU2872'
UNION ALL SELECT 'MU2873', 'SOCK 6', 'SAMBUNGAN', 'POWER', 'PCS', 50610.00, 0.000, 1, 'MU2873'
UNION ALL SELECT 'MU2874', 'TUTUP GORDEN SILVER', 'AKSESORIS', '-', 'PCS', 7000.00, 0.000, 1, 'MU2874'
UNION ALL SELECT 'MU2875', 'SERBET 3810', 'KAIN LAP', '-', 'PCS', 15000.00, 0.000, 1, 'MU2875'
UNION ALL SELECT 'MU2876', 'AA 111 BANDO', 'V-BELT', 'BANDO', 'PCS', 117500.00, 0.000, 1, 'MU2876'
UNION ALL SELECT 'MU2877', 'ACCURA ARTIC WHITE 40X40', 'TEHEL', 'ACCURA', 'DUS', 72000.00, 0.000, 0, 'MU2877'
UNION ALL SELECT 'MU2878', 'SKRUP BAJA RINGAN 12X40 KUNINGAN', 'SKRUP', 'FIX', 'PCS', 350.00, 500.000, 1, 'MU2878'
UNION ALL SELECT 'MU2879', 'SKRUP BAJA RINGAN 12X50 KUNINGAN', 'SKRUP', 'WILSON', 'PCS', 640.00, 200.000, 1, 'MU2879'
UNION ALL SELECT 'MU2880', 'SKRUP BAJA RINGAN 12X25 575 PCS', 'SKRUP', 'WILSON', 'DUS', 152950.00, 0.000, 1, 'MU2880'
UNION ALL SELECT 'MU2881', 'SKRUP BAJA RINGAN 12X30', 'SKRUP', 'FIX', 'PCS', 350.00, 0.000, 1, 'MU2881'
UNION ALL SELECT 'MU2882', 'GEMBOK GLOBE 50MM', 'GEMBOK', 'GLOBE', 'PCS', 35000.00, 0.000, 1, 'MU2882'
UNION ALL SELECT 'MU2883', 'KEPALA SPRAYER 2 MATA', 'S/PART', 'NOQIWA', 'PCS', 6000.00, 0.000, 1, 'MU2883'
UNION ALL SELECT 'MU2884', 'velg', 'ACCU', '-', 'SET', 0.00, 0.000, 1, 'MU2884'
UNION ALL SELECT 'MU2885', 'POT OENTOENG 27', 'POT', 'OENTOENG', 'PCS', 0.00, 0.000, 1, 'MU2885'
UNION ALL SELECT 'MU2886', 'ALAMEDA GREY 50X50', 'TEHEL', 'ACCURA', 'DUS', 88000.00, 8.000, 1, 'MU2886'
UNION ALL SELECT 'MU2887', 'ALMOND BEIGE 50X50', 'TEHEL', 'ACCURA', 'DUS', 88000.00, 8.000, 1, 'MU2887'
UNION ALL SELECT 'MU2888', 'B 104 BANDO', 'V-BELT', 'BANDO', 'PCS', 78000.00, 0.000, 1, 'MU2888'
UNION ALL SELECT 'MU2889', 'CLUTCH DRUM HANDSPRAYER', 'S/PART', 'MIYAMOTO', 'PCS', 15000.00, 7.000, 1, 'MU2889'
UNION ALL SELECT 'MU2890', 'ARENAS BEIGE 50x50', 'TEHEL', 'ACCURA', 'DUS', 88000.00, 8.000, 1, 'MU2890'
UNION ALL SELECT 'MU2891', 'ALDA BLUE 30X30', 'TEHEL', 'JUPITER', 'DUS', 48999.00, 8.000, 1, 'MU2891'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU2892' AS sku, 'PALACIO BEIGE 50x50' AS name, 'TEHEL' AS category_name, 'ACCURA' AS brand_name, 'DUS' AS base_unit_code, 88000.00 AS cost_price, 8.000 AS min_stock, 1 AS is_active, 'MU2892' AS legacy_sku
UNION ALL SELECT 'MU2893', 'AXEL GREY 30X30', 'TEHEL', 'JUPITER', 'DUS', 48999.00, 8.000, 1, 'MU2893'
UNION ALL SELECT 'MU2894', 'ALAMEDA BEIGE 50X50', 'TEHEL', 'ACCURA', 'DUS', 88000.00, 8.000, 1, 'MU2894'
UNION ALL SELECT 'MU2895', 'LAMPU KUNING 25 W', 'LAMPU', 'CHIYODA', 'PCS', 10000.00, 0.000, 1, 'MU2895'
UNION ALL SELECT 'MU2896', 'KUKU TEHEL', 'KUKU MACAN', 'ACCURA', 'PCS', 500.00, 0.000, 1, 'MU2896'
UNION ALL SELECT 'MU2897', 'LIST KOTAK 60CM', 'TEHEL', '-', 'PCS', 20000.00, 0.000, 1, 'MU2897'
UNION ALL SELECT 'MU2898', 'LIST KOTAK 25 CM', 'TEHEL', '-', 'PCS', 0.00, 0.000, 1, 'MU2898'
UNION ALL SELECT 'MU2899', 'SENG GELOMBANG ALUMUNIUM PUTIH', 'SENG', '-', 'LBR', 55000.00, 3.000, 1, 'MU2899'
UNION ALL SELECT 'MU2900', 'KAWAT RAM LOKET', 'KAWAT RAM', '-', 'MTR', 81500.00, 0.000, 1, 'MU2900'
UNION ALL SELECT 'MU2901', 'BESI PLAT 3MM', 'BESI', '-', 'LBR', 1332000.00, 0.000, 1, 'MU2901'
UNION ALL SELECT 'MU2902', 'GERGAJI BESI GNT', 'GERGAJI', 'GNT', 'SET', 0.00, 0.000, 1, 'MU2902'
UNION ALL SELECT 'MU2903', 'DAVINA PINK', 'TEHEL', 'DIAMOND', 'DUS', 50000.00, 0.000, 1, 'MU2903'
UNION ALL SELECT 'MU2904', 'OBENG POPEYE USA', 'OBENG', 'POPEYE', 'PCS', 14500.00, 0.000, 1, 'MU2904'
UNION ALL SELECT 'MU2905', 'OBENG RADIO ANANTA 10+', 'OBENG', 'ANANTA', 'PCS', 15000.00, 0.000, 1, 'MU2905'
UNION ALL SELECT 'MU2906', 'MATA BOR TEMBOK 6 MM', 'MATA BOR', 'JORAN', 'PCS', 20000.00, 0.000, 1, 'MU2906'
UNION ALL SELECT 'MU2907', 'NEO HOBBIE GREY 40X40', 'TEHEL', 'ACCURA', 'DUS', 60000.00, 8.000, 1, 'MU2907'
UNION ALL SELECT 'MU2908', 'LISBON BEIGE 40X40', 'TEHEL', 'ACCURA', 'DUS', 60000.00, 8.000, 1, 'MU2908'
UNION ALL SELECT 'MU2909', 'MADURA BEIGE 40X40', 'TEHEL', '-', 'DUS', 62100.00, 8.000, 1, 'MU2909'
UNION ALL SELECT 'MU2910', 'DALIANA PINK', 'TEHEL', '-', 'DUS', 67250.00, 0.000, 1, 'MU2910'
UNION ALL SELECT 'MU2911', 'SENG SAKURA MAROON', 'SENG', 'SAKURA', 'LBR', 75200.00, 3.000, 1, 'MU2911'
UNION ALL SELECT 'MU2912', 'ANGIN ANGIN BAMBU RO 53', 'AKSESORIS', '-', 'PCS', 70000.00, 0.000, 1, 'MU2912'
UNION ALL SELECT 'MU2913', 'BESI HOLO 20X20X1.7', 'BESI', 'SNI', 'BTG', 68000.00, 0.000, 1, 'MU2913'
UNION ALL SELECT 'MU2914', 'MATA BOR BESI 5 MM HSS M42', 'MATA BOR', 'WIPRO', 'PCS', 32450.00, 0.000, 1, 'MU2914'
UNION ALL SELECT 'MU2915', 'BESI HOLO 40X40X1.7', 'BESI', 'SNI', 'BTG', 135600.00, 0.000, 1, 'MU2915'
UNION ALL SELECT 'MU2916', 'PIPA PVC 3 C PUTIH', 'PIPA', 'POWER', 'BTG', 84710.00, 9.000, 1, 'MU2916'
UNION ALL SELECT 'MU2917', 'KACA NAKO 8 DAUN', 'KACA', '-', 'SET', 0.00, 0.000, 1, 'MU2917'
UNION ALL SELECT 'MU2918', 'SHOWER SS-02 BULAT', 'SHOWER', 'SANSHAN', 'PCS', 38000.00, 0.000, 1, 'MU2918'
UNION ALL SELECT 'MU2919', 'SHOWER SS-03 KOTAK', 'SHOWER', 'SANSHAN', 'PCS', 55000.00, 0.000, 1, 'MU2919'
UNION ALL SELECT 'MU2920', 'SHOWER SS-05 BIRU', 'SHOWER', 'SANSHAN', 'PCS', 60000.00, 0.000, 1, 'MU2920'
UNION ALL SELECT 'MU2921', 'SHOWER SS-05 HITAM', 'SHOWER', 'SANSHAN', 'PCS', 60000.00, 0.000, 1, 'MU2921'
UNION ALL SELECT 'MU2922', 'FURING M.BABAT / M.SEMPROT', 'S/PART', 'MORIMOTO', 'SET', 70000.00, 6.000, 1, 'MU2922'
UNION ALL SELECT 'MU2923', 'BATU ASAH MAKITA', 'ALAT', 'MAKITA', 'PCS', 160000.00, 0.000, 1, 'MU2923'
UNION ALL SELECT 'MU2924', 'MATA SERKEL CRONOS', 'MATA SERKEL', '-', 'PCS', 80000.00, 0.000, 1, 'MU2924'
UNION ALL SELECT 'MU2925', 'MATA GERGAJI BESI DOBEL', 'GERGAJI', '-', 'PCS', 6500.00, 0.000, 1, 'MU2925'
UNION ALL SELECT 'MU2926', 'MATA BOR BESI 8 MM HSS M42', 'MATA BOR', 'WIPRO', 'PCS', 89650.00, 0.000, 1, 'MU2926'
UNION ALL SELECT 'MU2927', 'BLACK GASKET', 'DEMPUL', 'POWER SERIES', 'PCS', 20000.00, 0.000, 1, 'MU2927'
UNION ALL SELECT 'MU2928', 'KUAS REFILL KECIL BUSA', 'Kuas', '-', 'PCS', 4500.00, 7.000, 1, 'MU2928'
UNION ALL SELECT 'MU2929', '1\" SKRUP BAJA SKRUP GYPSUM', 'SKRUP', '-', 'DUS', 69750.00, 2.000, 1, 'MU2929'
UNION ALL SELECT 'MU2930', '2\" SKRUP BAJA SKRUP GYPSUM', 'SKRUP', '-', 'DUS', 213750.00, 5.000, 1, 'MU2930'
UNION ALL SELECT 'MU2931', '3/4\" SKRUP BAJA SKRUP GYPSUM', 'SKRUP', '-', 'DUS', 35000.00, 5.000, 1, 'MU2931'
UNION ALL SELECT 'MU2932', '3\" SKRUP BAJA SKRUP GYPSUM', 'SKRUP', '-', 'DUS', 247500.00, 0.000, 1, 'MU2932'
UNION ALL SELECT 'MU2933', 'KEPALA KOMPOR SUMBU 22', 'S/PART', 'HOCK', 'UNIT', 102000.00, 2.000, 1, 'MU2933'
UNION ALL SELECT 'MU2934', 'BODELAC METAL PRIMER (MENI BESI)', 'CAT', 'NIPPON PAINT', 'KLG', 50196.00, 0.000, 1, 'MU2934'
UNION ALL SELECT 'MU2935', 'NIPPE UNDERSEAL FLINKOTE', 'CAT', 'NIPPON PAINT', 'KLG', 50285.00, 0.000, 1, 'MU2935'
UNION ALL SELECT 'MU2936', 'BAK AURORA 20', 'BASKOM', '-', 'PCS', 17000.00, 0.000, 1, 'MU2936'
UNION ALL SELECT 'MU2937', 'BAK CROWN HJ24', 'BASKOM', '-', 'PCS', 21000.00, 0.000, 1, 'MU2937'
UNION ALL SELECT 'MU2938', 'EMBER ABU 66L', 'EMBER', '-', 'PCS', 21000.00, 0.000, 1, 'MU2938'
UNION ALL SELECT 'MU2939', 'EMBER 30L', 'EMBER', '-', 'PCS', 24000.00, 0.000, 1, 'MU2939'
UNION ALL SELECT 'MU2940', 'EMBER 40L', 'EMBER', '-', 'PCS', 27000.00, 0.000, 1, 'MU2940'
UNION ALL SELECT 'MU2941', 'EMBER 50L', 'EMBER', '-', 'PCS', 36000.00, 0.000, 1, 'MU2941'
UNION ALL SELECT 'MU2942', 'EMBER 46L 3205', 'EMBER', '-', 'PCS', 30000.00, 0.000, 1, 'MU2942'
UNION ALL SELECT 'MU2943', 'BASKOM NAGATA 8816', 'BASKOM', '-', 'PCS', 26000.00, 0.000, 1, 'MU2943'
UNION ALL SELECT 'MU2944', 'BASKOM NAGARA 8818', 'BASKOM', '-', 'PCS', 30000.00, 0.000, 1, 'MU2944'
UNION ALL SELECT 'MU2945', 'BASKOM SAKURA 18', 'BASKOM', '-', 'PCS', 8500.00, 0.000, 1, 'MU2945'
UNION ALL SELECT 'MU2946', 'BASKOM KOREA', 'BASKOM', '-', 'PCS', 7000.00, 0.000, 1, 'MU2946'
UNION ALL SELECT 'MU2947', 'DENGKLEK LS BESAR', 'KURSI', 'LUCKY STAR', 'PCS', 33000.00, 0.000, 1, 'MU2947'
UNION ALL SELECT 'MU2948', 'DENGKLEK LS KECIL', 'KURSI', 'LUCKY STAR', 'PCS', 24000.00, 0.000, 1, 'MU2948'
UNION ALL SELECT 'MU2949', 'KARPET MALAYSIA PANJANG 230', 'KARPET', '-', 'ROL', 0.00, 0.000, 1, 'MU2949'
UNION ALL SELECT 'MU2950', 'KARPET BAYI COKLAT', 'KARPET', '-', 'MTR', 0.00, 0.000, 1, 'MU2950'
UNION ALL SELECT 'MU2951', 'BOX UK. 50X35X38', 'BOX MAKANAN', '-', 'PCS', 0.00, 0.000, 1, 'MU2951'
UNION ALL SELECT 'MU2952', 'BOX UK. 69X48X39', 'BOX MAKANAN', '-', 'PCS', 0.00, 0.000, 1, 'MU2952'
UNION ALL SELECT 'MU2953', 'BOX UK. 73X40X30', 'BOX MAKANAN', '-', 'PCS', 0.00, 0.000, 1, 'MU2953'
UNION ALL SELECT 'MU2954', 'PROFILA 88 TANK 5000 L', 'PROFIL', 'PROFILA 88', 'UNIT', 11250000.00, 0.000, 1, 'MU2954'
UNION ALL SELECT 'MU2955', 'BOSTER SKAP BESAR W2820N', 'S/PART', 'WIPRO', 'PSG', 13475.00, 0.000, 1, 'MU2955'
UNION ALL SELECT 'MU2956', 'B 99 BANDO', 'V-BELT', 'BANDO', 'PCS', 74250.00, 0.000, 1, 'MU2956'
UNION ALL SELECT 'MU2957', 'B 98 BANDO', 'V-BELT', 'BANDO', 'PCS', 73500.00, 0.000, 1, 'MU2957'
UNION ALL SELECT 'MU2958', 'B 97 BANDO', 'V-BELT', 'BANDO', 'PCS', 72700.00, 0.000, 1, 'MU2958'
UNION ALL SELECT 'MU2959', 'B 96 BANDO', 'V-BELT', 'BANDO', 'PCS', 72000.00, 0.000, 1, 'MU2959'
UNION ALL SELECT 'MU2960', 'B 95 BANDO', 'V-BELT', 'BANDO', 'PCS', 71250.00, 0.000, 1, 'MU2960'
UNION ALL SELECT 'MU2961', 'B 94 BANDO', 'V-BELT', 'BANDO', 'PCS', 70500.00, 0.000, 1, 'MU2961'
UNION ALL SELECT 'MU2962', 'B 93 BANDO', 'V-BELT', 'BANDO', 'PCS', 69750.00, 0.000, 1, 'MU2962'
UNION ALL SELECT 'MU2963', 'B 92 BANDO', 'V-BELT', 'BANDO', 'PCS', 69000.00, 0.000, 1, 'MU2963'
UNION ALL SELECT 'MU2964', 'B 91 BANDO', 'V-BELT', 'BANDO', 'PCS', 68250.00, 0.000, 1, 'MU2964'
UNION ALL SELECT 'MU2965', 'B 90 BANDO', 'V-BELT', 'BANDO', 'PCS', 67500.00, 0.000, 1, 'MU2965'
UNION ALL SELECT 'MU2966', 'B 86 BANDO', 'V-BELT', 'BANDO', 'PCS', 64500.00, 0.000, 1, 'MU2966'
UNION ALL SELECT 'MU2967', 'B 87 BANDO', 'V-BELT', 'BANDO', 'PCS', 62250.00, 0.000, 1, 'MU2967'
UNION ALL SELECT 'MU2968', 'B 85 BANDO', 'V-BELT', 'BANDO', 'PCS', 63700.00, 0.000, 1, 'MU2968'
UNION ALL SELECT 'MU2969', 'B 84 BANDO', 'V-BELT', 'BANDO', 'PCS', 63000.00, 0.000, 1, 'MU2969'
UNION ALL SELECT 'MU2970', 'B 83 BANDO', 'V-BELT', 'BANDO', 'PCS', 62250.00, 0.000, 1, 'MU2970'
UNION ALL SELECT 'MU2971', 'B 82 BANDO', 'V-BELT', 'BANDO', 'PCS', 61500.00, 0.000, 1, 'MU2971'
UNION ALL SELECT 'MU2972', 'B 81 BANDO', 'V-BELT', 'BANDO', 'PCS', 60750.00, 0.000, 1, 'MU2972'
UNION ALL SELECT 'MU2973', 'B 89 BANDO', 'V-BELT', 'BANDO', 'PCS', 66750.00, 0.000, 1, 'MU2973'
UNION ALL SELECT 'MU2974', 'B 88 BANDO', 'V-BELT', 'BANDO', 'PCS', 66000.00, 0.000, 1, 'MU2974'
UNION ALL SELECT 'MU2975', 'B 105 BANDO', 'V-BELT', 'BANDO', 'PCS', 78700.00, 0.000, 1, 'MU2975'
UNION ALL SELECT 'MU2976', 'B 107 BANDO', 'V-BELT', 'BANDO', 'PCS', 80250.00, 0.000, 1, 'MU2976'
UNION ALL SELECT 'MU2977', 'KUAS ARCEL 2\"', 'Kuas', 'ARCEL', 'PCS', 5000.00, 0.000, 1, 'MU2977'
UNION ALL SELECT 'MU2978', 'MULLER 68201', 'GAGANG PINTU', 'MULLER', 'SET', 0.00, 0.000, 1, 'MU2978'
UNION ALL SELECT 'MU2979', 'MULLER 965184', 'KUNCI PINTU', 'MULLER', 'SET', 0.00, 0.000, 1, 'MU2979'
UNION ALL SELECT 'MU2980', 'IMTAN KEONG 6\"', 'S/PART', 'IMTAN', 'UNIT', 3000000.00, 0.000, 1, 'MU2980'
UNION ALL SELECT 'MU2981', 'KIPAS BERDIRI HACHIDA SF 1602', 'KIPAS', 'HACHIDA', 'UNIT', 155000.00, 1.000, 1, 'MU2981'
UNION ALL SELECT 'MU2982', 'AIR CHUCK WIPRO', 'ALAT', 'WIPRO', 'PCS', 53000.00, 5.000, 1, 'MU2982'
UNION ALL SELECT 'MU2983', 'MATA BOR KAYU 6X160', 'MATA BOR', 'ALWIN', 'PCS', 23000.00, 10.000, 1, 'MU2983'
UNION ALL SELECT 'MU2984', 'MATA BOR KAYU 8X170', 'MATA BOR', 'ALWIN', 'PCS', 24000.00, 10.000, 1, 'MU2984'
UNION ALL SELECT 'MU2985', 'MATA BOR KAYU 10X190', 'MATA BOR', 'ALWIN', 'PCS', 17050.00, 10.000, 1, 'MU2985'
UNION ALL SELECT 'MU2986', 'BAUT SIKU RAK', 'ALAT', 'KURA', 'PCS', 0.00, 0.000, 1, 'MU2986'
UNION ALL SELECT 'MU2987', 'KRAN ANGSA ABU-ABU', 'ACCU', 'GRAVINDO', 'PCS', 75000.00, 0.000, 1, 'MU2987'
UNION ALL SELECT 'MU2988', 'KRAN ANGSA HTM-BESAR', 'KRAN', 'GRAVINDO', 'PCS', 75000.00, 0.000, 1, 'MU2988'
UNION ALL SELECT 'MU2989', 'KUNCI LACI FREDER 508', 'Kunci', 'FREDER', 'PCS', 8333.00, 5.000, 1, 'MU2989'
UNION ALL SELECT 'MU2990', 'KUNCI LACI FRITZ', 'Kunci', '-', 'PCS', 9000.00, 5.000, 1, 'MU2990'
UNION ALL SELECT 'MU2991', 'RENESOLA LED 9W', 'LAMPU', '-', 'PCS', 24100.00, 0.000, 1, 'MU2991'
UNION ALL SELECT 'MU2992', 'LAMPU KUNING 5 W', 'LAMPU', 'CHIYODA', 'PCS', 6500.00, 0.000, 1, 'MU2992'
UNION ALL SELECT 'MU2993', 'AKI HANDSPRAYER 12V 9AH', 'ACCU', 'MIYAMOTO', 'PCS', 145000.00, 6.000, 1, 'MU2993'
UNION ALL SELECT 'MU2994', 'RANGKA MESIN ALKON', 'ALAT', 'MIYAMOTO', 'PSG', 190000.00, 8.000, 1, 'MU2994'
UNION ALL SELECT 'MU2995', 'KUNCI BUSI MESIN BABAT', 'ALAT', 'MIYAMOTO', 'PCS', 8000.00, 12.000, 1, 'MU2995'
UNION ALL SELECT 'MU2996', 'PHILIPS LED 12W 3IN1', 'LAMPU', 'PHILIP', 'IKAT', 100000.00, 1.000, 1, 'MU2996'
UNION ALL SELECT 'MU2997', 'PHILIPS LED 10W 3IN1', 'LAMPU', 'PHILIP', 'IKAT', 127450.00, 1.000, 1, 'MU2997'
UNION ALL SELECT 'MU2998', 'PHILIPS LED 8W 3IN1', 'LAMPU', 'PHILIP', 'IKAT', 112350.00, 1.000, 1, 'MU2998'
UNION ALL SELECT 'MU2999', 'PHILIPS LED 6W 3IN1', 'LAMPU', 'PHILIP', 'IKAT', 99400.00, 1.000, 1, 'MU2999'
UNION ALL SELECT 'MU3000', 'BROWN PB116 PILLOX', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 0.000, 1, 'MU3000'
UNION ALL SELECT 'MU3001', 'ORANGE PB113 PILLOX', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 2.000, 1, 'MU3001'
UNION ALL SELECT 'MU3002', 'RED PB114 PILLOX', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 0.000, 1, 'MU3002'
UNION ALL SELECT 'MU3003', 'LIGHT GREEN PB111 PILLOX', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 0.000, 1, 'MU3003'
UNION ALL SELECT 'MU3004', 'BRIGHT GREEN PB120 PILLOX', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 2.000, 1, 'MU3004'
UNION ALL SELECT 'MU3005', 'DEEP GREEN PB109 PILLOX', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 2.000, 1, 'MU3005'
UNION ALL SELECT 'MU3006', 'BLUE MOON PB124 PILLOX', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 2.000, 1, 'MU3006'
UNION ALL SELECT 'MU3007', 'CLEAR PB100 PILLOX', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 5.000, 1, 'MU3007'
UNION ALL SELECT 'MU3008', 'GREY PB105 PILLOX', 'PILLOX', 'NIPPON PAINT', 'PCS', 29000.00, 2.000, 1, 'MU3008'
UNION ALL SELECT 'MU3009', 'TAWAS 0.5KG', 'ALAT', '-', 'BKS', 7000.00, 6.000, 1, 'MU3009'
UNION ALL SELECT 'MU3010', 'ADDITON HE 1L PENGERAS BETON', 'CAIRAN', 'ADDITON HE', 'KLG', 54337.00, 6.000, 1, 'MU3010'
UNION ALL SELECT 'MU3011', 'ADDITON HE 6KG PENGERAS BETON', 'CAIRAN', 'ADDITON HE', 'GLN', 278289.00, 6.000, 1, 'MU3011'
UNION ALL SELECT 'MU3012', 'GARDEN SET', 'ALAT', '-', 'SET', 25000.00, 5.000, 1, 'MU3012'
UNION ALL SELECT 'MU3013', 'RANTAI ANJING 2MM', 'RANTAI', '-', 'PCS', 15000.00, 3.000, 1, 'MU3013'
UNION ALL SELECT 'MU3014', 'RANTAI ANJING 2.5MM', 'RANTAI', '-', 'PCS', 17000.00, 3.000, 1, 'MU3014'
UNION ALL SELECT 'MU3015', 'RANTAI ANJING 3MM', 'RANTAI', '-', 'PCS', 21000.00, 3.000, 1, 'MU3015'
UNION ALL SELECT 'MU3016', 'OLI REM 1L', 'OLI', '-', 'BTL', 72500.00, 0.000, 1, 'MU3016'
UNION ALL SELECT 'MU3017', 'BASKOM NAGATA 816', 'BASKOM', 'NAGATA', 'PCS', 25000.00, 0.000, 1, 'MU3017'
UNION ALL SELECT 'MU3018', 'BASKOM NAGATA 818', 'BASKOM', 'NAGATA', 'PCS', 30000.00, 0.000, 1, 'MU3018'
UNION ALL SELECT 'MU3019', 'KUNCI KEPALA BOR WIPRO 13MM', 'Kunci', 'WIPRO', 'PCS', 16000.00, 0.000, 1, 'MU3019'
UNION ALL SELECT 'MU3020', 'MATA OBENG TUMBUK PH2 +', 'OBENG', 'ALWIN', 'PCS', 11500.00, 12.000, 1, 'MU3020'
UNION ALL SELECT 'MU3021', 'JCK PLANNER BLADE HSS 82MM', 'S/PART', 'JCK', 'PSG', 79750.00, 0.000, 1, 'MU3021'
UNION ALL SELECT 'MU3022', 'RODA KARET H.DUTY WIPRO 5\"', 'RODA', 'WIPRO', 'PCS', 240000.00, 0.000, 1, 'MU3022'
UNION ALL SELECT 'MU3023', 'RODA MATI KARET H.DUTY WIPRO 5\"', 'RODA', 'WIPRO', 'PCS', 210000.00, 0.000, 1, 'MU3023'
UNION ALL SELECT 'MU3024', 'WIPRO SKETMAT DIGITAL 6\"', 'ALAT UKUR', 'WIPRO', 'UNIT', 450000.00, 0.000, 1, 'MU3024'
UNION ALL SELECT 'MU3025', 'WIPRO CIRCLE SAW BLADE TCT 4\" 30T', 'MATA GURINDA', 'WIPRO', 'PCS', 65000.00, 0.000, 1, 'MU3025'
UNION ALL SELECT 'MU3026', 'WIPRO CIRCLE SAW BLADE TCT 4\" 40T', 'MATA GURINDA', 'WIPRO', 'PCS', 70000.00, 0.000, 1, 'MU3026'
UNION ALL SELECT 'MU3027', 'WIPRO FULLER 13 BLATT', 'ALAT UKUR', 'WIPRO', 'PCS', 37000.00, 0.000, 1, 'MU3027'
UNION ALL SELECT 'MU3028', 'KACA MATA LAS KUNING BUKA TUTUP', 'KACAMATA', 'WIPRO', 'PCS', 30000.00, 0.000, 1, 'MU3028'
UNION ALL SELECT 'MU3029', 'STUD 3/8', 'MUR', '-', 'PCS', 9060.00, 0.000, 1, 'MU3029'
UNION ALL SELECT 'MU3030', 'STUD STAINLESS 1/2', 'MUR', '-', 'PCS', 100000.00, 0.000, 1, 'MU3030'
UNION ALL SELECT 'MU3031', 'STUD STAINLESS 5/8', 'MUR', '-', 'PCS', 133000.00, 0.000, 1, 'MU3031'
UNION ALL SELECT 'MU3032', 'MURR STAINLESS 1/2', 'BAUT', '-', 'PCS', 2400.00, 0.000, 1, 'MU3032'
UNION ALL SELECT 'MU3033', 'MURR STAINLESS 5/8', 'BAUT', '-', 'PCS', 4540.00, 0.000, 1, 'MU3033'
UNION ALL SELECT 'MU3034', 'RING PER KECIL', 'BAUT', '-', 'PCS', 30.00, 50.000, 1, 'MU3034'
UNION ALL SELECT 'MU3035', 'PLAT RAK SIKU 2 BAUT', 'BAUT', '-', 'PCS', 1600.00, 0.000, 1, 'MU3035'
UNION ALL SELECT 'MU3036', 'KIPAS MEJA MASPION F-15DA', 'KIPAS', 'MASPION', 'UNIT', 130000.00, 1.000, 1, 'MU3036'
UNION ALL SELECT 'MU3037', 'VALORA GREEN 20X25', 'TEHEL', 'ASIA TILE', 'DUS', 49000.00, 10.000, 0, 'MU3037'
UNION ALL SELECT 'MU3038', 'VALORA BLUE 20X25', 'TEHEL', 'ASIA TILE', 'DUS', 49000.00, 8.000, 1, 'MU3038'
UNION ALL SELECT 'MU3039', 'VIOLETTA CREAM 20X25', 'TEHEL', 'ASIA TILE', 'DUS', 49000.00, 10.000, 1, 'MU3039'
UNION ALL SELECT 'MU3040', 'VIOLETTA RED 20X25', 'TEHEL', 'ASIA TILE', 'DUS', 49000.00, 8.000, 1, 'MU3040'
UNION ALL SELECT 'MU3041', 'KUAS ROLL KAPAL NISHIO', 'Kuas', 'NISHIO', 'PCS', 3958.00, 0.000, 1, 'MU3041'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU3042' AS sku, 'KRAN CABANG ENKOL BANO' AS name, 'KRAN' AS category_name, 'BANO' AS brand_name, 'PCS' AS base_unit_code, 25750.00 AS cost_price, 0.000 AS min_stock, 1 AS is_active, 'MU3042' AS legacy_sku
UNION ALL SELECT 'MU3043', 'KRAN CABANG BALING BANO', 'KRAN', 'BANO', 'PCS', 25750.00, 0.000, 1, 'MU3043'
UNION ALL SELECT 'MU3044', 'STOP KRAN GG BESI 3/4 MDN', 'KRAN', 'MDN', 'PCS', 8750.00, 0.000, 1, 'MU3044'
UNION ALL SELECT 'MU3045', 'SIKAT KAWAT FAWI', 'SIKAT', 'FAWI', 'PCS', 3200.00, 0.000, 1, 'MU3045'
UNION ALL SELECT 'MU3046', 'BATU GOSOK CS 6\"', 'BATU GOSOK', 'VPR', 'PCS', 15000.00, 12.000, 1, 'MU3046'
UNION ALL SELECT 'MU3047', 'KRAN ANGSA FLEX KPL BSR + KSTF-336', 'KRAN', '-', 'PCS', 41500.00, 0.000, 1, 'MU3047'
UNION ALL SELECT 'MU3048', 'KRAN ANGSA FLEX KPL BSR - KSTF-335', 'KRAN', '-', 'PCS', 41500.00, 0.000, 1, 'MU3048'
UNION ALL SELECT 'MU3049', 'GANCO MERAH CAP MATA', 'ALAT', 'MATA', 'PCS', 66400.00, 0.000, 1, 'MU3049'
UNION ALL SELECT 'MU3050', 'KUNCI INGRIS 10\'  KBS-10', 'Kunci', 'WIPRO', 'PCS', 71500.00, 2.000, 1, 'MU3050'
UNION ALL SELECT 'MU3051', 'WIPRO PALU 8OZ', 'PALU', 'WIPRO', 'PCS', 83000.00, 0.000, 1, 'MU3051'
UNION ALL SELECT 'MU3052', 'GAGANG SOCK 1/2', 'ALAT', 'WIPRO', 'PCS', 110000.00, 0.000, 1, 'MU3052'
UNION ALL SELECT 'MU3053', 'REFIL KUAS ROLL BESAR WIPRO', 'Kuas', 'NIPPON PAINT', 'PCS', 12420.00, 12.000, 1, 'MU3053'
UNION ALL SELECT 'MU3054', 'AVIAN CLING 16', 'CAT', 'Avian', 'KLG', 68300.00, 0.000, 1, 'MU3054'
UNION ALL SELECT 'MU3055', 'AVIAN CLING 38', 'CAT', 'Avian', 'KLG', 68300.00, 0.000, 1, 'MU3055'
UNION ALL SELECT 'MU3056', 'AVIAN CLING 41', 'CAT', 'Avian', 'KLG', 68300.00, 0.000, 1, 'MU3056'
UNION ALL SELECT 'MU3057', 'AVIAN CLING 66', 'CAT', 'Avian', 'KLG', 68300.00, 0.000, 1, 'MU3057'
UNION ALL SELECT 'MU3058', 'AVIAN CLING 77', 'CAT', 'Avian', 'KLG', 68300.00, 0.000, 1, 'MU3058'
UNION ALL SELECT 'MU3059', 'AVIAN CLING 92', 'CAT', 'Avian', 'KLG', 68300.00, 0.000, 1, 'MU3059'
UNION ALL SELECT 'MU3060', 'AVIAN CLING SB', 'CAT', 'Avian', 'KLG', 68300.00, 0.000, 1, 'MU3060'
UNION ALL SELECT 'MU3061', 'AVIAN CLING SW', 'CAT', 'Avian', 'KLG', 68300.00, 0.000, 1, 'MU3061'
UNION ALL SELECT 'MU3062', 'TERMOS 1LT FN', 'TERMOS AIR', 'FN', 'PCS', 42500.00, 0.000, 1, 'MU3062'
UNION ALL SELECT 'MU3063', 'TERMOS AIR 0.75LT FN', 'TERMOS AIR', 'FN', 'PCS', 37500.00, 0.000, 1, 'MU3063'
UNION ALL SELECT 'MU3064', 'POMPA GALON USB Q2', 'POMPA', 'Q2', 'PCS', 38500.00, 0.000, 1, 'MU3064'
UNION ALL SELECT 'MU3065', 'SARINGAN TEH 613 PUP', 'SARINGAN', '-', 'PCS', 4100.00, 0.000, 1, 'MU3065'
UNION ALL SELECT 'MU3066', 'SARINGAN BENSIN 24', 'SARINGAN', '-', 'PCS', 5000.00, 0.000, 1, 'MU3066'
UNION ALL SELECT 'MU3067', 'TERMOS STAINLES 500ML Q2', 'TERMOS AIR', 'Q2', 'PCS', 61000.00, 0.000, 1, 'MU3067'
UNION ALL SELECT 'MU3068', 'TERMOS AIR 2LT ELEPHANT PLASTIC', 'TERMOS AIR', 'ELEPHANT', 'PCS', 43300.00, 0.000, 1, 'MU3068'
UNION ALL SELECT 'MU3069', 'TERMOS AIR CHING KIANG BRAND', 'TERMOS AIR', 'CHING KIANG BRAND', 'PCS', 80000.00, 0.000, 1, 'MU3069'
UNION ALL SELECT 'MU3070', 'MATA SERKEL TCT 7\"X40T SILENT HOLE', 'MATA SERKEL', 'WIPRO', 'PCS', 74250.00, 0.000, 1, 'MU3070'
UNION ALL SELECT 'MU3071', 'GERGAJI FIBER WIPRO 18\"', 'GERGAJI', 'WIPRO', 'PCS', 54450.00, 0.000, 1, 'MU3071'
UNION ALL SELECT 'MU3072', 'KLEM PIPA PLAT 3\"', 'KLEM', '-', 'PCS', 2500.00, 20.000, 1, 'MU3072'
UNION ALL SELECT 'MU3073', 'KLEM PIPA PLAT 11/2\"', 'KLEM', '-', 'PCS', 1150.00, 20.000, 1, 'MU3073'
UNION ALL SELECT 'MU3074', 'CETOK PAPAK 7\" IGM', 'CETOK', 'IGM', 'PCS', 16600.00, 0.000, 1, 'MU3074'
UNION ALL SELECT 'MU3075', '1 1/4\" SKRUP BAJA SKRUP GYPSUM', 'SKRUP', 'ANDOZA', 'DUS', 84375.00, 0.000, 1, 'MU3075'
UNION ALL SELECT 'MU3076', 'TAMPAH 50CM DX', 'ALAT', '-', 'PCS', 17900.00, 0.000, 1, 'MU3076'
UNION ALL SELECT 'MU3077', 'HANGER FOLDING 24 LION STAR', 'ALAT', 'LION STAR', 'PCS', 43300.00, 0.000, 1, 'MU3077'
UNION ALL SELECT 'MU3078', 'HANGER FOLDING 30 LION STAR', 'ALAT', 'LION STAR', 'PCS', 50000.00, 0.000, 1, 'MU3078'
UNION ALL SELECT 'MU3079', 'CYLINDER KUNCI TGG ISCO', 'CYLINDER', 'ISCO', 'PCS', 20000.00, 0.000, 1, 'MU3079'
UNION ALL SELECT 'MU3080', 'SPAN PAPAK POPEYE GG KAYU', 'SPAN', 'POPEYE', 'PCS', 20000.00, 6.000, 1, 'MU3080'
UNION ALL SELECT 'MU3081', 'BAKI NAMPAN FA34', 'ALAT DAPUR', '-', 'PCS', 10000.00, 0.000, 1, 'MU3081'
UNION ALL SELECT 'MU3082', 'BAKI NAMPAN FA36', 'ALAT DAPUR', '-', 'PCS', 11000.00, 0.000, 1, 'MU3082'
UNION ALL SELECT 'MU3083', 'BAKI NAMPAN FA45', 'ALAT DAPUR', '-', 'PCS', 14000.00, 0.000, 1, 'MU3083'
UNION ALL SELECT 'MU3084', 'PISAU PJS-5 STAINLESS STEEL', 'ALAT DAPUR', '-', 'PCS', 8800.00, 0.000, 1, 'MU3084'
UNION ALL SELECT 'MU3085', 'PISAU PJS-6 STAINLESS STEEL', 'ALAT DAPUR', '-', 'PCS', 11600.00, 0.000, 1, 'MU3085'
UNION ALL SELECT 'MU3086', 'SENDOK TB-555', 'SENDOK MAKAN', '-', 'LSN', 18000.00, 0.000, 1, 'MU3086'
UNION ALL SELECT 'MU3087', 'SENDOK TB-805', 'SENDOK MAKAN', '-', 'LSN', 18000.00, 0.000, 1, 'MU3087'
UNION ALL SELECT 'MU3088', 'NAMPAN KOTAK EMAS', 'NAMPAN', '-', 'PCS', 115000.00, 0.000, 1, 'MU3088'
UNION ALL SELECT 'MU3089', 'NAMPAN KOTAK BUNGA', 'NAMPAN', '-', 'PCS', 115000.00, 0.000, 1, 'MU3089'
UNION ALL SELECT 'MU3090', 'WAJAN WT30', 'WAJAN', 'TB', 'PCS', 31000.00, 0.000, 1, 'MU3090'
UNION ALL SELECT 'MU3091', 'WAJAN WT32', 'WAJAN', '-', 'PCS', 34000.00, 0.000, 1, 'MU3091'
UNION ALL SELECT 'MU3092', 'WAJAN WT36', 'WAJAN', '-', 'PCS', 38500.00, 0.000, 1, 'MU3092'
UNION ALL SELECT 'MU3093', 'WAJAN WT38', 'WAJAN', '-', 'PCS', 40000.00, 0.000, 1, 'MU3093'
UNION ALL SELECT 'MU3094', 'SUTIL SMILE ST-12', 'SUTIL', '-', 'PCS', 5250.00, 0.000, 1, 'MU3094'
UNION ALL SELECT 'MU3095', 'SUTIL JAGUNG ST-13', 'SUTIL', '-', 'PCS', 5250.00, 0.000, 1, 'MU3095'
UNION ALL SELECT 'MU3096', 'SUTIL BESI 3403', 'SUTIL', '-', 'PCS', 5000.00, 0.000, 1, 'MU3096'
UNION ALL SELECT 'MU3097', 'SUTIL BESI KECIL 3406', 'SUTIL', '-', 'PCS', 3200.00, 0.000, 1, 'MU3097'
UNION ALL SELECT 'MU3098', 'SUTIL GG KAYU SEDANG 3402', 'SUTIL', '-', 'PCS', 3000.00, 0.000, 1, 'MU3098'
UNION ALL SELECT 'MU3099', 'SUTIL GG KAYU BESAR 3401', 'SUTIL', '-', 'PCS', 4500.00, 0.000, 1, 'MU3099'
UNION ALL SELECT 'MU3100', 'JEPITAN KUE KECIL 9', 'ALAT DAPUR', '-', 'PCS', 4000.00, 0.000, 1, 'MU3100'
UNION ALL SELECT 'MU3101', 'JEPITAN KUE SEDANG 12', 'ALAT DAPUR', '-', 'PCS', 4600.00, 0.000, 1, 'MU3101'
UNION ALL SELECT 'MU3102', 'JEPITAN KUE BESAR 14', 'ALAT DAPUR', '-', 'PCS', 5300.00, 0.000, 1, 'MU3102'
UNION ALL SELECT 'MU3103', 'MANGKOK STAINLESS FD-20', 'ALAT DAPUR', '-', 'PCS', 4400.00, 0.000, 1, 'MU3103'
UNION ALL SELECT 'MU3104', 'MANGKOK STAINLESS FD-22', 'ALAT DAPUR', '-', 'PCS', 5250.00, 0.000, 1, 'MU3104'
UNION ALL SELECT 'MU3105', 'MANGKOK STAINLESS FD-24', 'ALAT DAPUR', '-', 'PCS', 6300.00, 0.000, 1, 'MU3105'
UNION ALL SELECT 'MU3106', 'CHAJING DISH TKS-3', 'ALAT DAPUR', '-', 'PCS', 30000.00, 0.000, 1, 'MU3106'
UNION ALL SELECT 'MU3107', 'PARUTAN PRT-2', 'PARUT', 'TB', 'PCS', 5800.00, 0.000, 1, 'MU3107'
UNION ALL SELECT 'MU3108', 'SELANG WATERPAS KECIL', 'SELANG', 'TRILLIUN', 'MTR', 5000.00, 0.000, 1, 'MU3108'
UNION ALL SELECT 'MU3109', 'SENDOK MAKAN 847', 'SENDOK MAKAN', 'TB', 'LSN', 11000.00, 0.000, 1, 'MU3109'
UNION ALL SELECT 'MU3110', 'IRUS EROS FT-515', 'IRUS', 'TB', 'PCS', 1400.00, 0.000, 1, 'MU3110'
UNION ALL SELECT 'MU3111', 'WAJAN WOK 21', 'WAJAN', 'TB', 'PCS', 17000.00, 0.000, 1, 'MU3111'
UNION ALL SELECT 'MU3112', 'WAJAN WOK 24', 'WAJAN', 'TB', 'PCS', 19000.00, 0.000, 1, 'MU3112'
UNION ALL SELECT 'MU3113', 'WAJAN WOK 26', 'WAJAN', 'TB', 'PCS', 21000.00, 0.000, 1, 'MU3113'
UNION ALL SELECT 'MU3114', 'WAJAN WOK 28', 'WAJAN', 'TB', 'PCS', 23000.00, 0.000, 1, 'MU3114'
UNION ALL SELECT 'MU3115', 'WAJAN DT 2134', 'WAJAN', '-', 'PCS', 75000.00, 0.000, 1, 'MU3115'
UNION ALL SELECT 'MU3116', 'BASKOM STW 40', 'BASKOM', 'TB', 'PCS', 34000.00, 0.000, 1, 'MU3116'
UNION ALL SELECT 'MU3117', 'BASKOM STW 35', 'BASKOM', 'TB', 'PCS', 28500.00, 0.000, 1, 'MU3117'
UNION ALL SELECT 'MU3118', 'BASKOM STW 30', 'BASKOM', 'TB', 'PCS', 21000.00, 0.000, 1, 'MU3118'
UNION ALL SELECT 'MU3119', 'SIKAT', 'SIKAT', '-', 'PCS', 1500.00, 0.000, 1, 'MU3119'
UNION ALL SELECT 'MU3120', 'SARINGAN MAKANAN SM 22', 'SARINGAN', '-', 'PCS', 12000.00, 0.000, 1, 'MU3120'
UNION ALL SELECT 'MU3121', 'SARINGAN MAKANAN SM 16', 'SARINGAN', '-', 'PCS', 7250.00, 0.000, 1, 'MU3121'
UNION ALL SELECT 'MU3122', 'SEROK MAKANAN SM 10', 'SARINGAN', '-', 'PCS', 4500.00, 0.000, 1, 'MU3122'
UNION ALL SELECT 'MU3123', 'SEROK MAKANAN SM 8', 'SARINGAN', '-', 'PCS', 3500.00, 0.000, 1, 'MU3123'
UNION ALL SELECT 'MU3124', 'PANCI GAGANG PSS 16', 'PANCI', 'TB', 'PCS', 16000.00, 0.000, 1, 'MU3124'
UNION ALL SELECT 'MU3125', 'PANCI GAGANG PSS 18', 'PANCI', 'TB', 'PCS', 18000.00, 0.000, 1, 'MU3125'
UNION ALL SELECT 'MU3126', 'VACUM CUP 500ML', 'ALAT DAPUR', '-', 'PCS', 24500.00, 0.000, 1, 'MU3126'
UNION ALL SELECT 'MU3127', 'PISAU MOTIF APEL', 'PISAU', 'TB', 'PCS', 2000.00, 0.000, 1, 'MU3127'
UNION ALL SELECT 'MU3128', 'PISAU GG IKAN', 'PISAU', 'TB', 'PCS', 1250.00, 0.000, 1, 'MU3128'
UNION ALL SELECT 'MU3129', 'KERUKAN BLEWAH', 'SENDOK', '-', 'PCS', 825.00, 0.000, 1, 'MU3129'
UNION ALL SELECT 'MU3130', 'PEMANGGANG GG BESI', 'ALAT DAPUR', '-', 'PCS', 10000.00, 0.000, 1, 'MU3130'
UNION ALL SELECT 'MU3131', 'PISAU PB1', 'PISAU', 'TB', 'PCS', 11500.00, 0.000, 1, 'MU3131'
UNION ALL SELECT 'MU3132', 'SEROK MAKANAN GG KAYU 18', 'SARINGAN', '-', 'PCS', 17000.00, 0.000, 1, 'MU3132'
UNION ALL SELECT 'MU3133', 'SEROK MAKANAN GG KAYU 20', 'SARINGAN', '-', 'PCS', 18400.00, 0.000, 1, 'MU3133'
UNION ALL SELECT 'MU3134', 'SARINGAN MINYAK ST13', 'SARINGAN', '-', 'PCS', 9000.00, 0.000, 1, 'MU3134'
UNION ALL SELECT 'MU3135', 'SARINGAN MINYAK ST17', 'SARINGAN', '-', 'PCS', 10000.00, 0.000, 1, 'MU3135'
UNION ALL SELECT 'MU3136', 'BAKI NAMPAN BUNGA 45', 'NAMPAN', 'TB', 'PCS', 25000.00, 0.000, 1, 'MU3136'
UNION ALL SELECT 'MU3137', 'BAKI NAMPAN BUNGA 50', 'NAMPAN', 'TB', 'PCS', 38000.00, 0.000, 1, 'MU3137'
UNION ALL SELECT 'MU3138', 'IRUS FORTUNA GG SMILE', 'IRUS', 'FORTUNA', 'PCS', 2100.00, 0.000, 1, 'MU3138'
UNION ALL SELECT 'MU3139', 'IRUS FORTUNA BESAR', 'IRUS', 'FORTUNA', 'PCS', 5250.00, 0.000, 1, 'MU3139'
UNION ALL SELECT 'MU3140', 'IRUS MERK STAINLES STEEL', 'IRUS', 'STAINLISSTEEL', 'PCS', 5250.00, 0.000, 1, 'MU3140'
UNION ALL SELECT 'MU3141', 'PANCI MIE', 'PANCI', '-', 'PCS', 22500.00, 0.000, 1, 'MU3141'
UNION ALL SELECT 'MU3142', 'CETAKAN BAKWAN 3', 'IRUS', '-', 'PCS', 16000.00, 0.000, 1, 'MU3142'
UNION ALL SELECT 'MU3143', 'PARUTAN MENARA 4IN1', 'PARUT', 'TB', 'PCS', 16500.00, 0.000, 1, 'MU3143'
UNION ALL SELECT 'MU3144', 'CETAKAN TUMPENG SUSUN BESAR', 'ALAT DAPUR', '-', 'PCS', 25000.00, 0.000, 1, 'MU3144'
UNION ALL SELECT 'MU3145', 'TUMPENG SUSUN KECIL', 'ALAT DAPUR', '-', 'PCS', 12000.00, 0.000, 1, 'MU3145'
UNION ALL SELECT 'MU3146', 'CETAKAN TUMPENG 19', 'CETAKAN', '-', 'PCS', 8000.00, 0.000, 1, 'MU3146'
UNION ALL SELECT 'MU3147', 'CETAKAN TUMPENG 18', 'CETAKAN', '-', 'PCS', 7000.00, 0.000, 1, 'MU3147'
UNION ALL SELECT 'MU3148', 'CETAKAN TUMPENG 20', 'CETAKAN', '-', 'PCS', 9000.00, 0.000, 1, 'MU3148'
UNION ALL SELECT 'MU3149', 'CENTONG KAYU', 'SENDOK NASI', '-', 'PCS', 4500.00, 0.000, 1, 'MU3149'
UNION ALL SELECT 'MU3150', 'KUAS MASAK NILON', 'Kuas', '-', 'PCS', 3000.00, 0.000, 1, 'MU3150'
UNION ALL SELECT 'MU3151', 'KUAS KUE', 'Kuas', '-', 'PCS', 6250.00, 0.000, 1, 'MU3151'
UNION ALL SELECT 'MU3152', 'KOCOKAN TELUR BESI', 'ALAT DAPUR', '-', 'PCS', 13300.00, 0.000, 1, 'MU3152'
UNION ALL SELECT 'MU3153', 'KOCOKAN TELUR SENAR', 'ALAT DAPUR', '-', 'PCS', 10000.00, 0.000, 1, 'MU3153'
UNION ALL SELECT 'MU3154', 'BAKI NAMPAN SS DALAM', 'NAMPAN', 'TB', 'PCS', 16500.00, 0.000, 1, 'MU3154'
UNION ALL SELECT 'MU3155', 'THINNER C A SPECIAL', 'THINNER', 'NIPPON PAINT', 'BTL', 0.00, 0.000, 1, 'MU3155'
UNION ALL SELECT 'MU3156', 'PARUT PRT-1', 'PARUT', 'TB', 'PCS', 3000.00, 0.000, 1, 'MU3156'
UNION ALL SELECT 'MU3157', 'MANGKOK STAINLESS FD-26', 'ALAT DAPUR', 'STAINLISSTEEL', 'PCS', 7250.00, 0.000, 1, 'MU3157'
UNION ALL SELECT 'MU3158', 'MANGKOK STAINLESS FD-28', 'ALAT DAPUR', 'STAINLISSTEEL', 'PCS', 8600.00, 0.000, 1, 'MU3158'
UNION ALL SELECT 'MU3159', 'TEFLON MAXIM VALENTINO 18CM', 'TEFLON', 'MAXIM', 'PCS', 67500.00, 0.000, 1, 'MU3159'
UNION ALL SELECT 'MU3160', 'TEFLON MAXIM VALENTINO 20CM', 'TEFLON', 'MAXIM', 'PCS', 73500.00, 0.000, 1, 'MU3160'
UNION ALL SELECT 'MU3161', 'SELANG GREASE GUN WIPRO', 'S/PART', 'WIPRO', 'PCS', 65000.00, 0.000, 1, 'MU3161'
UNION ALL SELECT 'MU3162', 'KEPALA NAPLE GREASE GUN WIPRO', 'S/PART', 'WIPRO', 'PCS', 30000.00, 0.000, 1, 'MU3162'
UNION ALL SELECT 'MU3163', 'GAS TORCH WIPRO', 'ALAT', 'WIPRO', 'PCS', 95000.00, 0.000, 1, 'MU3163'
UNION ALL SELECT 'MU3164', 'CUNGKIL BAN CUKIT BAN', 'ALAT', 'WIPRO', 'PCS', 55000.00, 0.000, 1, 'MU3164'
UNION ALL SELECT 'MU3165', 'KAWAT SOLDER ECER', 'KAWAT', '-', 'PCS', 5000.00, 0.000, 1, 'MU3165'
UNION ALL SELECT 'MU3166', 'KUNCI PINTU 322L-NB/CP SANSHAN', 'KUNCI PINTU', 'SANSHAN', 'SET', 102500.00, 2.000, 1, 'MU3166'
UNION ALL SELECT 'MU3167', 'SALINAS BEIGE', 'TEHEL', 'ACCURA', 'DUS', 70000.00, 0.000, 1, 'MU3167'
UNION ALL SELECT 'MU3168', 'LINGGIS LANCIP SDG 1M', 'LINGGIS', '-', 'PCS', 42500.00, 6.000, 1, 'MU3168'
UNION ALL SELECT 'MU3169', 'LINGGIS LANCIP BSR 1M', 'LINGGIS', '-', 'PCS', 55000.00, 6.000, 1, 'MU3169'
UNION ALL SELECT 'MU3170', 'PALU KAMBING USA 12OZ GRG', 'PALU', 'HENDSO', 'PCS', 27084.00, 0.000, 1, 'MU3170'
UNION ALL SELECT 'MU3171', 'STOP KRAN TOILET CLASSIC', 'KRAN', 'CLASSIC', 'PCS', 22500.00, 0.000, 1, 'MU3171'
UNION ALL SELECT 'MU3172', 'SALINAS GREY', 'TEHEL', 'ACCURA', 'DUS', 36000.00, 0.000, 1, 'MU3172'
UNION ALL SELECT 'MU3173', 'BRITANIA GREY 60X60', 'TEHEL', 'ACCURA', 'DUS', 105850.00, 0.000, 0, 'MU3173'
UNION ALL SELECT 'MU3174', 'SAIGON GREY 40X40', 'TEHEL', 'ACCURA', 'DUS', 36000.00, 8.000, 1, 'MU3174'
UNION ALL SELECT 'MU3175', 'SAIGON BEIGE 40X40', 'TEHEL', 'ACCURA', 'DUS', 36000.00, 8.000, 1, 'MU3175'
UNION ALL SELECT 'MU3176', 'BRITANIA BEIGE 60X60', 'TEHEL', 'ACCURA', 'DUS', 105850.00, 8.000, 1, 'MU3176'
UNION ALL SELECT 'MU3177', 'ARIES GOLD 306 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU3177'
UNION ALL SELECT 'MU3178', 'ARIES GOLD 604 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU3178'
UNION ALL SELECT 'MU3179', 'ARIES GOLD 506 5KG', 'CAT', 'ARIES GOLD', 'GLN', 93000.00, 0.000, 1, 'MU3179'
UNION ALL SELECT 'MU3180', 'RODA MATI KARET 3 HITAM', 'RODA', 'TRIPLE S', 'PCS', 12750.00, 6.000, 1, 'MU3180'
UNION ALL SELECT 'MU3181', 'KUAS ARCEL 1 1/2\"', 'Kuas', 'ARCEL', 'PCS', 4300.00, 0.000, 1, 'MU3181'
UNION ALL SELECT 'MU3182', 'KUAS BIASA 1\" MDN', 'Kuas', 'FAWI', 'PCS', 1400.00, 6.000, 1, 'MU3182'
UNION ALL SELECT 'MU3183', 'CAT AVIAN 200CC 480', 'CAT', 'Avian', 'KLG', 0.00, 8.000, 1, 'MU3183'
UNION ALL SELECT 'MU3184', 'CAT AVIAN 200CC 470', 'CAT', 'Avian', 'KLG', 0.00, 8.000, 1, 'MU3184'
UNION ALL SELECT 'MU3185', 'CAT AVIAN 200CC 465', 'CAT', 'Avian', 'KLG', 25000.00, 0.000, 1, 'MU3185'
UNION ALL SELECT 'MU3186', 'CAT AVIAN 200CC 194', 'CAT', 'Avian', 'KLG', 25000.00, 8.000, 1, 'MU3186'
UNION ALL SELECT 'MU3187', 'CAT AVIAN 200CC 657', 'CAT', 'Avian', 'KLG', 25000.00, 8.000, 1, 'MU3187'
UNION ALL SELECT 'MU3188', 'CAT AVIAN 200CC 630', 'CAT', 'Avian', 'KLG', 25000.00, 8.000, 1, 'MU3188'
UNION ALL SELECT 'MU3189', 'CAT SUZUKA SS2053 200CC', 'CAT', 'Avian', 'KLG', 40000.00, 0.000, 1, 'MU3189'
UNION ALL SELECT 'MU3190', 'AVIAN 660 100CC', 'CAT', 'Avian', 'KLG', 15000.00, 8.000, 1, 'MU3190'
UNION ALL SELECT 'MU3191', 'AVIAN SYNTHETIC 924', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU3191'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU3192' AS sku, 'AVIAN SYNTHETIC 731' AS name, 'CAT' AS category_name, 'Avian' AS brand_name, 'KLG' AS base_unit_code, 85000.00 AS cost_price, 8.000 AS min_stock, 1 AS is_active, 'MU3192' AS legacy_sku
UNION ALL SELECT 'MU3193', 'AVIAN SYNTHETIC 911', 'CAT', 'Avian', 'KLG', 85000.00, 8.000, 1, 'MU3193'
UNION ALL SELECT 'MU3194', 'JARUM KERAS M6', 'ALAT', '-', 'PCS', 16000.00, 0.000, 1, 'MU3194'
UNION ALL SELECT 'MU3195', 'JARUM KERAS M8', 'ALAT', '-', 'PCS', 16000.00, 0.000, 1, 'MU3195'
UNION ALL SELECT 'MU3196', 'JARUM KERAS M10', 'ALAT', '-', 'PCS', 20000.00, 0.000, 1, 'MU3196'
UNION ALL SELECT 'MU3197', 'JARUM KERAS M16', 'ALAT', '-', 'PCS', 55000.00, 0.000, 1, 'MU3197'
UNION ALL SELECT 'MU3198', 'JARUM KERAS M20', 'ALAT', '-', 'PCS', 75000.00, 0.000, 1, 'MU3198'
UNION ALL SELECT 'MU3199', 'PAYUNG GOLF SILVER', 'ALAT', '-', 'PCS', 35000.00, 0.000, 1, 'MU3199'
UNION ALL SELECT 'MU3200', 'PAYUNG WARNA', 'ALAT', '-', 'PCS', 27500.00, 0.000, 1, 'MU3200'
UNION ALL SELECT 'MU3201', 'PAYUNG GOLF PELANGI', 'ALAT', '-', 'PCS', 32500.00, 0.000, 1, 'MU3201'
UNION ALL SELECT 'MU3202', 'PAYUNG LIPAT', 'ALAT', '-', 'PCS', 25000.00, 0.000, 1, 'MU3202'
UNION ALL SELECT 'MU3203', 'JAS HUJAN SHE', 'ALAT', '-', 'PCS', 55000.00, 0.000, 1, 'MU3203'
UNION ALL SELECT 'MU3204', 'JAS HUJAN TERUSAN WARNA', 'ALAT', '-', 'PCS', 13000.00, 12.000, 1, 'MU3204'
UNION ALL SELECT 'MU3205', 'HANDSPRAYER IMOTO 767 20L', 'SPRAYER', 'IMOTO', 'UNIT', 930000.00, 8.000, 1, 'MU3205'
UNION ALL SELECT 'MU3206', 'JAS HUJAN TRIBAL', 'ALAT', '-', 'PCS', 57500.00, 6.000, 1, 'MU3206'
UNION ALL SELECT 'MU3207', 'JAS HUJAN BIRU', 'ALAT', '-', 'PCS', 52500.00, 0.000, 1, 'MU3207'
UNION ALL SELECT 'MU3208', 'SENG KANOPI JENDELA', 'SENG', '-', 'LBR', 75000.00, 0.000, 1, 'MU3208'
UNION ALL SELECT 'MU3209', 'MATA BOR BESI 6 MM HSS M42', 'MATA BOR', 'WIPRO', 'PCS', 43450.00, 0.000, 1, 'MU3209'
UNION ALL SELECT 'MU3210', 'SPAN PVC LANCIP NEW STAR', 'SPAN', 'NEW STAR', 'PCS', 5416.00, 12.000, 1, 'MU3210'
UNION ALL SELECT 'MU3211', 'BANDO B 57', 'S/PART', 'BANDO', 'PCS', 59000.00, 0.000, 1, 'MU3211'
UNION ALL SELECT 'MU3212', 'TALI STATER UNIVERSAL', 'S/PART', 'RED FOX', 'MTR', 2500.00, 0.000, 1, 'MU3212'
UNION ALL SELECT 'MU3213', 'TERPAL A5L GAJAH 5X7', 'TERPAL', 'GAJAH', 'LBR', 212410.00, 0.000, 1, 'MU3213'
UNION ALL SELECT 'MU3214', 'TERPAL A5L GAJAH 6X8', 'TERPAL', 'GAJAH', 'LBR', 284560.00, 0.000, 1, 'MU3214'
UNION ALL SELECT 'MU3215', 'TERPAL A5L GAJAH 5X6', 'TERPAL', 'GAJAH', 'LBR', 184660.00, 0.000, 1, 'MU3215'
UNION ALL SELECT 'MU3216', 'LAKBAN COKLAT SEDANG PAJERO', 'ACCU', '-', 'PCS', 10000.00, 0.000, 1, 'MU3216'
UNION ALL SELECT 'MU3217', 'NOTA 3 PLY', 'ACCU', '-', 'PCS', 12000.00, 0.000, 1, 'MU3217'
UNION ALL SELECT 'MU3218', 'AMPLOP PAPERLINE 95X152MM', 'AKSESORIS', '-', 'DUS', 20000.00, 0.000, 1, 'MU3218'
UNION ALL SELECT 'MU3219', 'DRAIN TUTUP DRAT 3/4', 'SAMBUNGAN', 'POWER', 'PCS', 1400.00, 0.000, 1, 'MU3219'
UNION ALL SELECT 'MU3220', 'AMPLAS ROLL AA120', 'AMPLAS', '-', 'MTR', 8000.00, 0.000, 1, 'MU3220'
UNION ALL SELECT 'MU3221', 'PAKU KAPAL 5', 'Paku', '-', 'KG', 15000.00, 88.000, 1, 'MU3221'
UNION ALL SELECT 'MU3222', 'PAKU KAPAL 6', 'Paku', '-', 'KG', 15000.00, 88.000, 1, 'MU3222'
UNION ALL SELECT 'MU3223', 'PAKU KAPAL 15', 'Paku', '-', 'KG', 15000.00, 88.000, 1, 'MU3223'
UNION ALL SELECT 'MU3224', 'M 25 BANDO', 'V-BELT', 'BANDO', 'PCS', 14875.00, 0.000, 1, 'MU3224'
UNION ALL SELECT 'MU3225', 'M 22 BANDO', 'V-BELT', 'BANDO', 'PCS', 13090.00, 0.000, 1, 'MU3225'
UNION ALL SELECT 'MU3226', 'M 28 BANDO', 'V-BELT', 'BANDO', 'PCS', 16660.00, 0.000, 1, 'MU3226'
UNION ALL SELECT 'MU3227', 'SARINGAN GOT STAINLESS SSG-01 DOLPHIN', 'SARINGAN', 'SANSHAN', 'PCS', 35000.00, 0.000, 1, 'MU3227'
UNION ALL SELECT 'MU3228', 'SARINGAN GOT STAINLESS SSG-02 STONE', 'SARINGAN', 'SANSHAN', 'PCS', 35000.00, 0.000, 1, 'MU3228'
UNION ALL SELECT 'MU3229', 'SARINGAN GOT STAINLESS SSG-03 SMILE', 'SARINGAN', 'SANSHAN', 'PCS', 35000.00, 0.000, 1, 'MU3229'
UNION ALL SELECT 'MU3230', 'SARINGAN GOT STAINLESS SSG-11', 'SARINGAN', 'SANSHAN', 'PCS', 17500.00, 0.000, 1, 'MU3230'
UNION ALL SELECT 'MU3231', 'SARINGAN GOT STAINLESS SSG-12', 'SARINGAN', 'SANSHAN', 'PCS', 17500.00, 0.000, 1, 'MU3231'
UNION ALL SELECT 'MU3232', 'SIKU RAK TEBAL 12X14 (270)', 'AKSESORIS', 'SCHLIEPER', 'PSG', 28000.00, 6.000, 1, 'MU3232'
UNION ALL SELECT 'MU3233', 'LEM BESI DEMPUL EPOSTIX', 'LEM', 'TAIYO', 'PCS', 16100.00, 6.000, 1, 'MU3233'
UNION ALL SELECT 'MU3234', 'PIPA PVC 3\" C ABU-ABU', 'PIPA', 'POWER', 'BTG', 87782.00, 9.000, 1, 'MU3234'
UNION ALL SELECT 'MU3235', 'REGULATOR GAS', 'S/PART', 'WINN GAS', 'PCS', 0.00, 6.000, 1, 'MU3235'
UNION ALL SELECT 'MU3236', 'SELANG GAS + KLEM', 'S/PART', 'WINN GAS', 'PSG', 34500.00, 0.000, 1, 'MU3236'
UNION ALL SELECT 'MU3237', 'SAKLAR GANTUNG AT', 'SAKLAR', 'DENGFENG', 'PCS', 2500.00, 0.000, 1, 'MU3237'
UNION ALL SELECT 'MU3238', 'FITING TANAM DL-3.5\'', 'FITING', 'OSIWA', 'PCS', 25000.00, 0.000, 1, 'MU3238'
UNION ALL SELECT 'MU3239', 'KLEM SELANG PLAT 1/2', 'KLEM TARIK', 'TAIWAN', 'PCS', 3000.00, 0.000, 1, 'MU3239'
UNION ALL SELECT 'MU3240', 'KLEM SELANG PLAT 1 1/2', 'KLEM', 'TAIWAN', 'PCS', 5900.00, 50.000, 1, 'MU3240'
UNION ALL SELECT 'MU3241', 'KLEM SELANG PLAT 2', 'KLEM', 'TAIWAN', 'PCS', 6500.00, 50.000, 1, 'MU3241'
UNION ALL SELECT 'MU3242', 'KLEM SELANG PLAT 2 1/2', 'KLEM', 'TAIWAN', 'PCS', 7500.00, 50.000, 1, 'MU3242'
UNION ALL SELECT 'MU3243', 'KLEM SELANG PLAT 3', 'KLEM', 'TAIWAN', 'PCS', 9700.00, 50.000, 1, 'MU3243'
UNION ALL SELECT 'MU3244', 'GREASE KYOSO KECIL', 'OLI', 'KYOSO', 'PCS', 4000.00, 0.000, 1, 'MU3244'
UNION ALL SELECT 'MU3245', 'GREASE KYOSO PAIL', 'OLI', 'KYOSO', 'PAIL', 305000.00, 0.000, 1, 'MU3245'
UNION ALL SELECT 'MU3246', 'BAUT 1/2 30 CM', 'BAUT', '-', 'PCS', 10000.00, 0.000, 1, 'MU3246'
UNION ALL SELECT 'MU3247', 'KACA HITAM LAS', 'KACA', 'WIPRO', 'PCS', 3000.00, 0.000, 1, 'MU3247'
UNION ALL SELECT 'MU3248', 'KUKU MACAN 8 BAUT', 'ACCU', '-', 'PCS', 7000.00, 0.000, 1, 'MU3248'
UNION ALL SELECT 'MU3249', 'KUKU MACAN 6 BAUT', 'KUKU MACAN', '-', 'PCS', 5000.00, 0.000, 1, 'MU3249'
UNION ALL SELECT 'MU3250', 'TANG RIVET HASTON', 'SARUNG TANGAN', 'HASSTON', 'PCS', 60000.00, 0.000, 1, 'MU3250'
UNION ALL SELECT 'MU3251', 'OVER SOCK 1 1/2X1', 'SAMBUNGAN', 'POWER', 'PCS', 6381.00, 0.000, 1, 'MU3251'
UNION ALL SELECT 'MU3252', 'OVER SOCK 4X2', 'SAMBUNGAN', 'POWER', 'PCS', 18030.00, 0.000, 1, 'MU3252'
UNION ALL SELECT 'MU3253', 'GANTUNGAN BAJU KECIL', 'ALAT', '-', 'PCS', 10000.00, 0.000, 1, 'MU3253'
UNION ALL SELECT 'MU3254', 'SKRUP BAJA RINGAN 10x16 PUTIH', 'ACCU', '-', 'PCS', 250.00, 1000.000, 1, 'MU3254'
UNION ALL SELECT 'MU3255', 'BAUT RANJANG BULAT HITAM 8X35', 'BAUT', '-', 'PSG', 4000.00, 0.000, 1, 'MU3255'
UNION ALL SELECT 'MU3256', 'BAUT RANJANG BULAT HITAM 8X70', 'BAUT', '-', 'PSG', 6000.00, 0.000, 1, 'MU3256'
UNION ALL SELECT 'MU3257', 'BAUT RANJANG BOLONG', 'BAUT', '555', 'PSG', 2250.00, 0.000, 1, 'MU3257'
UNION ALL SELECT 'MU3258', 'NANOLITE LED 12W', 'LAMPU', 'NANOLITE', 'PCS', 39289.00, 0.000, 1, 'MU3258'
UNION ALL SELECT 'MU3259', 'TANG LAS SET 3M', 'S/PART', 'DOZIRO', 'SET', 71500.00, 0.000, 1, 'MU3259'
UNION ALL SELECT 'MU3260', 'TANG MASSA SET 3M', 'S/PART', 'DOZIRO', 'SET', 71500.00, 0.000, 1, 'MU3260'
UNION ALL SELECT 'MU3261', 'KUNCI PALANG BAN', 'ALAT', 'DOZIRO', 'PCS', 52000.00, 0.000, 1, 'MU3261'
UNION ALL SELECT 'MU3262', 'KOMPRESOR OLI 3/4 10 LT', 'MESIN', 'DOZIRO', 'UNIT', 1181500.00, 0.000, 1, 'MU3262'
UNION ALL SELECT 'MU3263', 'SKRUP HAK BUKA NO 3', 'SKRUP', '-', 'PCS', 300.00, 0.000, 1, 'MU3263'
UNION ALL SELECT 'MU3264', 'SKRUP HAK BUKA NO 5', 'SKRUP', '-', 'PCS', 380.00, 0.000, 1, 'MU3264'
UNION ALL SELECT 'MU3265', 'KRAN ANGSA PVC SOLIGEN', 'KRAN', 'SOLIGEN', 'PCS', 56000.00, 0.000, 1, 'MU3265'
UNION ALL SELECT 'MU3266', 'PELAMPUNG OTOMATIS SOLIGEN 3/4\"', 'KRAN', 'SOLIGEN', 'SET', 57500.00, 0.000, 1, 'MU3266'
UNION ALL SELECT 'MU3267', 'GERGAJI BATA RINGAN 18\"', 'GERGAJI', 'POPEYE', 'PCS', 18750.00, 0.000, 1, 'MU3267'
UNION ALL SELECT 'MU3268', 'JEPITAN TIKUS', 'ALAT', 'KZK', 'PCS', 8250.00, 0.000, 1, 'MU3268'
UNION ALL SELECT 'MU3269', 'KUKU MACAN 10MM BAUT', 'ALAT', '-', 'PCS', 5500.00, 0.000, 1, 'MU3269'
UNION ALL SELECT 'MU3270', 'KUKU MACAN 12MM BAUT', 'ALAT', '-', 'PCS', 12000.00, 0.000, 1, 'MU3270'
UNION ALL SELECT 'MU3271', 'KUKU MACAN 16MM BAUT', 'ALAT', '-', 'PCS', 19500.00, 0.000, 1, 'MU3271'
UNION ALL SELECT 'MU3272', 'TUTUP CYLINDER PINTU EAST', 'AKSESORIS', 'EAST', 'PSG', 33000.00, 0.000, 1, 'MU3272'
UNION ALL SELECT 'MU3273', 'DOOR STOP MAGNET PION 830', 'AKSESORIS', 'BRABUS', 'PCS', 38000.00, 0.000, 1, 'MU3273'
UNION ALL SELECT 'MU3274', 'DOOR STOP MAGNET PION ID-003 SS', 'AKSESORIS', 'IGM', 'PCS', 14500.00, 0.000, 1, 'MU3274'
UNION ALL SELECT 'MU3275', 'DOOR STOP MAGNET PION ID-006 SS', 'AKSESORIS', 'IGM', 'PCS', 32500.00, 0.000, 1, 'MU3275'
UNION ALL SELECT 'MU3276', 'PISAU POTONG KACA GOLD 9083', 'ALAT', 'BENZ', 'PCS', 28500.00, 0.000, 1, 'MU3276'
UNION ALL SELECT 'MU3277', 'TUTUP TANDON 1\"', 'AKSESORIS', 'NEW STAR', 'PCS', 5500.00, 0.000, 1, 'MU3277'
UNION ALL SELECT 'MU3278', 'TUTUP TANDON 3/4\"', 'AKSESORIS', 'NEW STAR', 'PCS', 5000.00, 0.000, 1, 'MU3278'
UNION ALL SELECT 'MU3279', 'SAMBUNGAN SELANG', 'AKSESORIS', 'NEW STAR', 'PCS', 800.00, 12.000, 1, 'MU3279'
UNION ALL SELECT 'MU3280', 'SEMPROTAN SPRINGKLER BALING HSS', 'AKSESORIS', 'HSS', 'PCS', 78000.00, 0.000, 1, 'MU3280'
UNION ALL SELECT 'MU3281', 'TANG SPI BUKA 7\" BENGKOK', 'TANG', 'BLITZ', 'PCS', 45000.00, 0.000, 1, 'MU3281'
UNION ALL SELECT 'MU3282', 'TANG SPI BUKA 7\" LURUS', 'TANG', 'BLITZ', 'PCS', 45000.00, 0.000, 1, 'MU3282'
UNION ALL SELECT 'MU3283', 'TANG SPI TUTUP 7\" BENGKOK', 'TANG', 'BLITZ', 'PCS', 45000.00, 0.000, 1, 'MU3283'
UNION ALL SELECT 'MU3284', 'TANG SPI TUTUP 7\" LURUS', 'TANG', 'BLITZ', 'PCS', 45000.00, 0.000, 1, 'MU3284'
UNION ALL SELECT 'MU3285', 'BOR IMPACT CORDLESS HDT', 'MESIN', 'HD', 'UNIT', 235000.00, 0.000, 1, 'MU3285'
UNION ALL SELECT 'MU3286', 'WASTAFEL KERAMIK K-59', 'WASHTAFEL', 'WIKEA', 'PCS', 710000.00, 0.000, 1, 'MU3286'
UNION ALL SELECT 'MU3287', 'GRENDEL JENDELA BRABUS', 'GRENDEL', 'BRABUS', 'PCS', 24600.00, 0.000, 1, 'MU3287'
UNION ALL SELECT 'MU3288', 'GRENDEL JENDELA BRABUS KOTAK', 'GRENDEL', 'BRABUS', 'PCS', 26000.00, 0.000, 1, 'MU3288'
UNION ALL SELECT 'MU3289', 'KEPALA SHOWER BALING BALING', 'AKSESORIS', 'SOLIGEN', 'PCS', 10000.00, 6.000, 1, 'MU3289'
UNION ALL SELECT 'MU3290', 'KUNCI LACI 502 FREDER', 'Kunci', 'FREDER', 'PCS', 9300.00, 0.000, 1, 'MU3290'
UNION ALL SELECT 'MU3291', 'FLAP DISC A240', 'AMPLAS', 'WIPRO', 'PCS', 8750.00, 12.000, 1, 'MU3291'
UNION ALL SELECT 'MU3292', 'FLAP DISC A220', 'AMPLAS', 'MOLLAR', 'PCS', 7000.00, 0.000, 1, 'MU3292'
UNION ALL SELECT 'MU3293', 'FLAP DISC A150', 'AMPLAS', 'MOLLAR', 'PCS', 7000.00, 12.000, 1, 'MU3293'
UNION ALL SELECT 'MU3294', 'AMPLAS DUCO 100', 'AMPLAS', 'FUJI STAR', 'PCS', 4800.00, 0.000, 1, 'MU3294'
UNION ALL SELECT 'MU3295', 'STOP KRAN 1\" BALL GG STAINLESS', 'KRAN', 'GLX', 'PCS', 28500.00, 0.000, 1, 'MU3295'
UNION ALL SELECT 'MU3296', 'STOP KRAN 3/4\' BALL GG STAINLESS', 'KRAN', 'GLX', 'PCS', 19500.00, 0.000, 1, 'MU3296'
UNION ALL SELECT 'MU3297', 'STOP KRAN 1\" KUNINGAN', 'KRAN', 'BLITZ', 'PCS', 100000.00, 0.000, 1, 'MU3297'
UNION ALL SELECT 'MU3298', 'STOP KRAN 3/4\' KUNINGAN', 'KRAN', 'BLITZ', 'PCS', 67000.00, 0.000, 1, 'MU3298'
UNION ALL SELECT 'MU3299', 'STOP KRAN 1/2\' KUNINGAN', 'KRAN', 'BLITZ', 'PCS', 62000.00, 0.000, 1, 'MU3299'
UNION ALL SELECT 'MU3300', 'NANOLITE LED 15W', 'LAMPU', 'NANOLITE', 'PCS', 46359.00, 0.000, 1, 'MU3300'
UNION ALL SELECT 'MU3301', 'LEM PHILIPIN NON-SAG 1/4 PINT 170G', 'LEM', 'PIONEER', 'PSG', 44280.00, 12.000, 1, 'MU3301'
UNION ALL SELECT 'MU3302', 'LEM PHILIPIN 1/2 PINT K', 'LEM', 'PIONEER', 'PSG', 75500.00, 12.000, 1, 'MU3302'
UNION ALL SELECT 'MU3303', 'IMPACT GUN CORDLESS FISCH FIW 600T', 'ALAT', 'FISCH', 'UNIT', 987801.00, 0.000, 1, 'MU3303'
UNION ALL SELECT 'MU3304', 'NANOLITE LED 9W', 'LAMPU', 'NANOLITE', 'PCS', 33229.00, 0.000, 1, 'MU3304'
UNION ALL SELECT 'MU3305', 'NANOLITE LED 7W', 'LAMPU', 'NANOLITE', 'PCS', 28179.00, 0.000, 1, 'MU3305'
UNION ALL SELECT 'MU3306', 'NANOLITE LED 5W', 'LAMPU', 'NANOLITE', 'PCS', 22119.00, 0.000, 1, 'MU3306'
UNION ALL SELECT 'MU3307', 'BESI BETON 10 MM ULIR', 'BESI', 'SNI', 'BTG', 89000.00, 0.000, 1, 'MU3307'
UNION ALL SELECT 'MU3308', 'SAPU 2 MACAN KECIL', 'SAPU', '2 MACAN', 'PCS', 36800.00, 10.000, 1, 'MU3308'
UNION ALL SELECT 'MU3309', 'TEHEL CURAH 40X40', 'TEHEL', '-', 'LBR', 5000.00, 0.000, 1, 'MU3309'
UNION ALL SELECT 'MU3310', 'BOYO POLITUR 609 1L', 'VERNIS', 'BOYO', 'KLG', 86000.00, 0.000, 1, 'MU3310'
UNION ALL SELECT 'MU3311', 'BOYO POLITUR 607 0.5L', 'VERNIS', 'BOYO', 'KLG', 35000.00, 0.000, 1, 'MU3311'
UNION ALL SELECT 'MU3312', 'BOYO POLITUR VERNISH 0.5L', 'VERNIS', 'BOYO', 'KLG', 35000.00, 0.000, 1, 'MU3312'
UNION ALL SELECT 'MU3313', 'MCB SCHNEIDER C25', 'MCB', '-', 'PCS', 0.00, 0.000, 1, 'MU3313'
UNION ALL SELECT 'MU3314', 'AMPLAS ROLL AA100', 'AMPLAS', '-', 'MTR', 8000.00, 0.000, 1, 'MU3314'
UNION ALL SELECT 'MU3315', 'SEPATU AP BOOT TERRA UK.41', 'SEPATU', 'AP', 'PSG', 100000.00, 0.000, 1, 'MU3315'
UNION ALL SELECT 'MU3316', 'CAMELAI GREY 40X40', 'TEHEL', 'JUPITER', 'DUS', 71394.00, 8.000, 1, 'MU3316'
UNION ALL SELECT 'MU3317', 'SIGMA GREEN 40X40', 'TEHEL', 'JUPITER', 'DUS', 71394.00, 8.000, 1, 'MU3317'
UNION ALL SELECT 'MU3318', 'PADMA PINK 25X40', 'TEHEL', 'JUPITER', 'DUS', 0.00, 24.000, 1, 'MU3318'
UNION ALL SELECT 'MU3319', 'TUTUP PIPA 1/2', 'SAMBUNGAN', 'POWER', 'PCS', 1380.00, 9.000, 1, 'MU3319'
UNION ALL SELECT 'MU3320', 'TRIPLEK 10 MM SENGON', 'PLYWOOD', 'SENGON', 'LBR', 0.00, 0.000, 1, 'MU3320'
UNION ALL SELECT 'MU3321', 'RODA CASTER GEPENG HITAM REM', 'RODA', 'HI TEE', 'PCS', 5000.00, 0.000, 1, 'MU3321'
UNION ALL SELECT 'MU3322', 'RANTAI SENSOR 52 MATA / 27 R 0.70', 'RANTAI', 'OREGON', 'PCS', 285000.00, 6.000, 1, 'MU3322'
UNION ALL SELECT 'MU3323', 'SERENA WOOD 40X40', 'TEHEL', 'IKAD', 'DUS', 50000.00, 8.000, 1, 'MU3323'
UNION ALL SELECT 'MU3324', 'STRAIGHT BITS MODEL 101-463 1/2\'X63.5MM', 'MATA ROUTER', 'WIPRO', 'PCS', 200000.00, 0.000, 1, 'MU3324'
UNION ALL SELECT 'MU3325', 'SLOTING CUTTER MODEL 307-041 1/4\'X8MM', 'MATA ROUTER', 'WIPRO', 'PCS', 48000.00, 0.000, 1, 'MU3325'
UNION ALL SELECT 'MU3326', 'SLOTING CUTTER MODEL 307-021 1/4\"X5MM', 'MATA ROUTER', 'WIPRO', 'PCS', 50000.00, 0.000, 1, 'MU3326'
UNION ALL SELECT 'MU3327', 'SLOTING CUTTER MODEL 307-031 1/4\'X6MM', 'MATA ROUTER', 'WIPRO', 'PCS', 200000.00, 0.000, 1, 'MU3327'
UNION ALL SELECT 'MU3328', 'PALU KAMBING POLOS 16OZ WIPRO', 'PALU', 'WIPRO', 'PCS', 200000.00, 0.000, 1, 'MU3328'
UNION ALL SELECT 'MU3329', 'HAND SOCKET 19MM X 1/2\' WIPRO', 'Kunci', 'WIPRO', 'PCS', 14500.00, 0.000, 1, 'MU3329'
UNION ALL SELECT 'MU3330', 'HAND SOCKET 17MMX 1/2\' WIPRO', 'Kunci', 'WIPRO', 'PCS', 13000.00, 0.000, 1, 'MU3330'
UNION ALL SELECT 'MU3331', 'HAND SOCKET 14MM X 1/2\' WIPRO', 'Kunci', 'WIPRO', 'PCS', 12000.00, 0.000, 1, 'MU3331'
UNION ALL SELECT 'MU3332', 'HAND SOCKET 12MM X 1/2\' WIPRO', 'Kunci', 'WIPRO', 'PCS', 11000.00, 0.000, 1, 'MU3332'
UNION ALL SELECT 'MU3333', 'HAND SOCKET 21MM X 1/2\' WIPRO', 'Kunci', 'WIPRO', 'PCS', 20000.00, 0.000, 1, 'MU3333'
UNION ALL SELECT 'MU3334', 'HAND SOCKET 24MM X 1/2\' WIPRO', 'Kunci', 'WIPRO', 'PCS', 20000.00, 0.000, 1, 'MU3334'
UNION ALL SELECT 'MU3335', 'KUNCI SOCK Y 8-9-10', 'Kunci', 'WIPRO', 'PCS', 24200.00, 15.000, 1, 'MU3335'
UNION ALL SELECT 'MU3336', 'KOBE HANA 40x40', 'TEHEL', 'IKAD', 'DUS', 75000.00, 8.000, 1, 'MU3336'
UNION ALL SELECT 'MU3337', 'SKRUP BAJA RINGAN 12X60', 'SKRUP', 'SCHLIEPER', 'PCS', 296000.00, 200.000, 1, 'MU3337'
UNION ALL SELECT 'MU3338', 'PIKOLITE LED 9W', 'LAMPU', 'PIKOLITE', 'PCS', 21800.00, 24.000, 1, 'MU3338'
UNION ALL SELECT 'MU3339', 'PIKOLITE LED 15W', 'LAMPU', 'PIKOLITE', 'PCS', 28800.00, 0.000, 1, 'MU3339'
UNION ALL SELECT 'MU3340', 'LIST PLAFON P02 GOLD BLACK 120', 'ACCU', '-', 'BTG', 131000.00, 0.000, 1, 'MU3340'
UNION ALL SELECT 'MU3341', 'CLUTCH SHOES W/ BOLT MORIMOTO', 'ACCU', 'MORIMOTO', 'PCS', 20000.00, 8.000, 1, 'MU3341'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU3342' AS sku, 'SIGMA BLUE 40X40' AS name, 'TEHEL' AS category_name, 'JUPITER' AS brand_name, 'DUS' AS base_unit_code, 71394.00 AS cost_price, 8.000 AS min_stock, 1 AS is_active, 'MU3342' AS legacy_sku
UNION ALL SELECT 'MU3343', 'CAKRA BEIGE 40X40', 'TEHEL', 'JUPITER', 'DUS', 71394.00, 8.000, 1, 'MU3343'
UNION ALL SELECT 'MU3344', 'PAMILUTO BEIGE 40X40', 'TEHEL', 'JUPITER', 'DUS', 71394.00, 8.000, 1, 'MU3344'
UNION ALL SELECT 'MU3345', 'WASTAFEL K-1 INNOPLAST', 'WASHTAFEL', 'INNOPLAS', 'SET', 125000.00, 0.000, 1, 'MU3345'
UNION ALL SELECT 'MU3346', 'STEKER SAKLAR UTICON', 'STEKER', 'UTICON', 'PCS', 17900.00, 0.000, 1, 'MU3346'
UNION ALL SELECT 'MU3347', 'PB128 INDIGO BLUE', 'PILLOX', 'NIPPON PAINT', 'PCS', 22052.00, 0.000, 1, 'MU3347'
UNION ALL SELECT 'MU3348', 'BOSTER CB-419', 'S/PART', 'MAKITA', 'PSG', 34000.00, 0.000, 1, 'MU3348'
UNION ALL SELECT 'MU3349', 'BOSTER CB-64A', 'S/PART', 'MAKITA', 'PSG', 25500.00, 0.000, 1, 'MU3349'
UNION ALL SELECT 'MU3350', 'BOSTER CB-303A', 'S/PART', 'MAKITA', 'PSG', 38250.00, 0.000, 1, 'MU3350'
UNION ALL SELECT 'MU3351', 'KABEL PERDANA 0.75 WARNA SERABUT', 'KABEL', 'PERDANA', '100 METER', 126000.00, 1.000, 1, 'MU3351'
UNION ALL SELECT 'MU3352', 'KABEL PERDANA 2.5 WARNA SERABUT', 'KABEL', 'PERDANA', '100 METER', 362000.00, 1.000, 1, 'MU3352'
UNION ALL SELECT 'MU3353', 'AMPLAS BULAT 4\" 120', 'AMPLAS', 'WIPRO', 'LBR', 3850.00, 0.000, 1, 'MU3353'
UNION ALL SELECT 'MU3354', 'AMPLAS BULAT 4\" 180', 'AMPLAS', 'WIPRO', 'LBR', 3850.00, 0.000, 1, 'MU3354'
UNION ALL SELECT 'MU3355', 'ARIES GOLD 601 20KG', 'CAT', 'Avian', 'PAIL', 100000.00, 0.000, 1, 'MU3355'
UNION ALL SELECT 'MU3356', 'ARIES GOLD 104 5 KG', 'CAT', 'Avian', 'GLN', 98000.00, 0.000, 1, 'MU3356'
UNION ALL SELECT 'MU3357', 'TERPAL A8 RAJAWALI 5X6', 'TERPAL', 'MATAHARI', 'LBR', 295660.00, 0.000, 1, 'MU3357'
UNION ALL SELECT 'MU3358', 'HAND TAPS M12X1.75', 'ALAT', 'WIPRO', 'PAK', 48000.00, 0.000, 1, 'MU3358'
UNION ALL SELECT 'MU3359', 'HAND TAPS M14X1.75', 'ALAT', 'WIPRO', 'PAK', 57750.00, 0.000, 1, 'MU3359'
UNION ALL SELECT 'MU3360', 'HAND TAPS M14X2', 'ALAT', 'WIPRO', 'PAK', 57750.00, 0.000, 1, 'MU3360'
UNION ALL SELECT 'MU3361', 'KUNCI RING 8X10', 'Kunci', 'WIPRO', 'PCS', 18975.00, 2.000, 1, 'MU3361'
UNION ALL SELECT 'MU3362', 'KUNCI RING 10X12', 'Kunci', 'WIPRO', 'PCS', 19800.00, 2.000, 1, 'MU3362'
UNION ALL SELECT 'MU3363', 'KUNCI RING 12X14', 'Kunci', 'WIPRO', 'PCS', 22550.00, 2.000, 1, 'MU3363'
UNION ALL SELECT 'MU3364', 'KUNCI RING 14X17', 'Kunci', 'WIPRO', 'PCS', 26400.00, 0.000, 1, 'MU3364'
UNION ALL SELECT 'MU3365', 'KUNCI RING 17X19', 'Kunci', 'WIPRO', 'PCS', 29150.00, 0.000, 1, 'MU3365'
UNION ALL SELECT 'MU3366', 'KUNCI RING 19X22', 'Kunci', 'WIPRO', 'PCS', 34650.00, 0.000, 1, 'MU3366'
UNION ALL SELECT 'MU3367', 'KRAN TAMAN GEMBOK SAILING 3/4', 'KRAN', 'SAILING', 'PCS', 90750.00, 0.000, 1, 'MU3367'
UNION ALL SELECT 'MU3368', 'QUICK COUPLING LAKI PH22', 'SPARE PART', 'WIPRO', 'PCS', 3630.00, 0.000, 1, 'MU3368'
UNION ALL SELECT 'MU3369', 'QUICK COUPLING FEMALE SH22', 'SPARE PART', 'WIPRO', 'PCS', 14300.00, 0.000, 1, 'MU3369'
UNION ALL SELECT 'MU3370', 'SIKAT BULAT TANGKAI SSW-15', 'SIKAT', 'WIPRO', 'PCS', 12100.00, 0.000, 1, 'MU3370'
UNION ALL SELECT 'MU3371', 'SIKAT BULAT TANGKAI SSW-20', 'SIKAT', 'WIPRO', 'PCS', 13475.00, 0.000, 1, 'MU3371'
UNION ALL SELECT 'MU3372', 'SIKAT SAPU LANCIP TANGKAI SSE-04', 'SIKAT', 'WIPRO', 'PCS', 12650.00, 0.000, 1, 'MU3372'
UNION ALL SELECT 'MU3373', 'SOCK KRAN 1X3/4', 'SAMBUNGAN', 'POWER', 'PCS', 3192.00, 0.000, 1, 'MU3373'
UNION ALL SELECT 'MU3374', 'AIR RADIATOR 5 L', 'CAIRAN', '-', 'BTL', 39000.00, 0.000, 1, 'MU3374'
UNION ALL SELECT 'MU3375', 'IMOTO MESIN ALKON 2\"', 'MESIN', 'IMOTO', 'UNIT', 1063100.00, 5.000, 1, 'MU3375'
UNION ALL SELECT 'MU3376', 'IMOTO MESIN ALKON 3\"', 'MESIN', 'IMOTO', 'UNIT', 1130100.00, 5.000, 1, 'MU3376'
UNION ALL SELECT 'MU3377', 'GRENDEL IGM SS 3\'', 'GRENDEL', 'IGM', 'PCS', 23500.00, 6.000, 1, 'MU3377'
UNION ALL SELECT 'MU3378', 'GRENDEL IGM SS 4\'', 'GRENDEL', 'IGM', 'PCS', 27500.00, 6.000, 1, 'MU3378'
UNION ALL SELECT 'MU3379', 'GRENDEL IGM SS PUTIH 4\'', 'GRENDEL', 'IGM', 'PCS', 13333.00, 6.000, 1, 'MU3379'
UNION ALL SELECT 'MU3380', 'GRENDEL IGM SS PUTIH 6\'', 'GRENDEL', 'IGM', 'PCS', 17084.00, 6.000, 1, 'MU3380'
UNION ALL SELECT 'MU3381', 'GRENDEL IGM SS PUTIH 8\'', 'GRENDEL', 'IGM', 'PCS', 22500.00, 6.000, 1, 'MU3381'
UNION ALL SELECT 'MU3382', 'TATAKAN AMPLAS', 'ALAT', '-', 'PCS', 10000.00, 0.000, 1, 'MU3382'
UNION ALL SELECT 'MU3383', 'GAGANG CANGKUL', 'ALAT', '-', 'PCS', 25000.00, 6.000, 1, 'MU3383'
UNION ALL SELECT 'MU3384', 'EMBER COR ORANGE TEBAL TC18', 'EMBER', '-', 'PCS', 10000.00, 0.000, 1, 'MU3384'
UNION ALL SELECT 'MU3385', 'KRAN WASHTAFEL CHROME W19', 'KRAN', 'SOLIGEN', 'PCS', 90000.00, 6.000, 1, 'MU3385'
UNION ALL SELECT 'MU3386', 'KRAN WASHTAFEL HITAM W19', 'KRAN', 'SOLIGEN', 'PCS', 90000.00, 6.000, 1, 'MU3386'
UNION ALL SELECT 'MU3387', 'MATA PISAU GERGAJI M.BABAT', 'PISAU', 'MOLLAR', 'PCS', 48000.00, 6.000, 1, 'MU3387'
UNION ALL SELECT 'MU3388', 'SETRIKA HYPERLITE TIC 8001', 'SETRIKA', 'HYPERLITE', 'UNIT', 85000.00, 1.000, 1, 'MU3388'
UNION ALL SELECT 'MU3389', 'BLENDER PHILIPS HR 2115 BIRU', 'BLENDER', 'PHILIP', 'UNIT', 620000.00, 1.000, 1, 'MU3389'
UNION ALL SELECT 'MU3390', 'MAGIC COM MIYAKO MCM 558 SBC', 'MAGICCOM', 'MIYAKO', 'UNIT', 280000.00, 1.000, 1, 'MU3390'
UNION ALL SELECT 'MU3391', 'MAGIC COM COSMOS CRJ 3305', 'MAGICCOM', 'MIYAKO', 'UNIT', 240000.00, 1.000, 1, 'MU3391'
UNION ALL SELECT 'MU3392', 'BLENDER MIYAKO BL 151 GF', 'ACCU', '-', 'UNIT', 300000.00, 1.000, 1, 'MU3392'
UNION ALL SELECT 'MU3393', 'BLENDER MIYAKO BL 151 PF AP HIJAU', 'BLENDER', 'MIYAKO', 'UNIT', 255000.00, 1.000, 1, 'MU3393'
UNION ALL SELECT 'MU3394', 'SEMEN GERSIK GROSIR', 'SEMEN', 'GRESIK', 'SAK', 99000.00, 0.000, 1, 'MU3394'
UNION ALL SELECT 'MU3395', 'NEO HOBBIE BEIGE 40X40', 'TEHEL', 'ACCURA', 'DUS', 35200.00, 8.000, 1, 'MU3395'
UNION ALL SELECT 'MU3396', 'KUBA GREY 40X40', 'TEHEL', 'ACCURA', 'DUS', 32400.00, 50.000, 1, 'MU3396'
UNION ALL SELECT 'MU3397', 'LAURENT GREY 40X40', 'TEHEL', 'ACCURA', 'DUS', 32400.00, 25.000, 0, 'MU3397'
UNION ALL SELECT 'MU3398', 'SPRING GREY 40X40', 'TEHEL', 'ACCURA', 'DUS', 32600.00, 8.000, 1, 'MU3398'
UNION ALL SELECT 'MU3399', 'BOX PENYIMPANAN 140', 'BASKOM', 'GAJAH', 'UNIT', 270000.00, 0.000, 1, 'MU3399'
UNION ALL SELECT 'MU3400', 'ROTARY GREASE 16 KG', 'GEMUK', 'ROTARY', 'PAIL', 825900.00, 0.000, 1, 'MU3400'
UNION ALL SELECT 'MU3401', 'AIWO LED 15W', 'LAMPU', 'AIWO', 'PCS', 21000.00, 6.000, 1, 'MU3401'
UNION ALL SELECT 'MU3402', 'AIWO LED 12W', 'LAMPU', 'AIWO', 'PCS', 17000.00, 6.000, 1, 'MU3402'
UNION ALL SELECT 'MU3403', 'AIWO LED 9W', 'LAMPU', 'AIWO', 'PCS', 13000.00, 6.000, 1, 'MU3403'
UNION ALL SELECT 'MU3404', 'AIWO LED 7W', 'LAMPU', 'AIWO', 'PCS', 12000.00, 6.000, 1, 'MU3404'
UNION ALL SELECT 'MU3405', 'AIWO LED 5W', 'LAMPU', 'AIWO', 'PCS', 10000.00, 6.000, 1, 'MU3405'
UNION ALL SELECT 'MU3406', 'RED GASKET 35G', 'LEM', 'DESTRONK', 'PCS', 5416.00, 12.000, 1, 'MU3406'
UNION ALL SELECT 'MU3407', 'KUAS LUKIS 3', 'Kuas', '-', 'PCS', 2000.00, 12.000, 1, 'MU3407'
UNION ALL SELECT 'MU3408', 'KUAS LUKIS 5', 'Kuas', '-', 'PCS', 2333.00, 12.000, 1, 'MU3408'
UNION ALL SELECT 'MU3409', 'KUAS LUKIS 6', 'Kuas', '-', 'PCS', 2416.00, 12.000, 1, 'MU3409'
UNION ALL SELECT 'MU3410', 'KUAS LUKIS 7', 'Kuas', '-', 'PCS', 2500.00, 12.000, 1, 'MU3410'
UNION ALL SELECT 'MU3411', 'KUAS LUKIS 9', 'Kuas', '-', 'PCS', 2750.00, 12.000, 1, 'MU3411'
UNION ALL SELECT 'MU3412', 'SEMEN TONASA 50KG', 'SEMEN', 'TONASA', 'AMBIL SENDIRI', 70000.00, 25.000, 1, 'MU3412'
UNION ALL SELECT 'MU3413', 'LEM TIKUS CAP GAJAH 100ML', 'LEM', 'GAJAH', 'PCS', 15215.00, 0.000, 1, 'MU3413'
UNION ALL SELECT 'MU3414', 'SENTER KEPALA WARM HL 2250', 'ALAT', 'PUSH ON', 'PCS', 88200.00, 6.000, 1, 'MU3414'
UNION ALL SELECT 'MU3415', 'SENTER KEPALA WHITE HL 2250', 'ALAT', 'PUSH ON', 'PCS', 88200.00, 6.000, 1, 'MU3415'
UNION ALL SELECT 'MU3416', 'TATAH KAYU TEKIRO 1\'', 'TATAH', 'TEKIRO', 'PCS', 51975.00, 3.000, 1, 'MU3416'
UNION ALL SELECT 'MU3417', 'TATAH KAYU TEKIRO 1 1/4\'', 'TATAH', 'TEKIRO', 'PCS', 54725.00, 3.000, 1, 'MU3417'
UNION ALL SELECT 'MU3418', 'TATAH KAYU TEKIRO 1 1/2\'', 'TATAH', 'TEKIRO', 'PCS', 57200.00, 3.000, 1, 'MU3418'
UNION ALL SELECT 'MU3419', 'TATAH KAYU TEKIRO 1/4\'', 'TATAH', 'TEKIRO', 'PCS', 39050.00, 3.000, 1, 'MU3419'
UNION ALL SELECT 'MU3420', 'TATAH KAYU TEKIRO 3/4\'', 'TATAH', 'TEKIRO', 'PCS', 47545.00, 3.000, 1, 'MU3420'
UNION ALL SELECT 'MU3421', 'TATAH KAYU TEKIRO 1/2\'', 'TATAH', 'TEKIRO', 'PCS', 42900.00, 3.000, 1, 'MU3421'
UNION ALL SELECT 'MU3422', 'TATAH KAYU TEKIRO 3/8\'', 'TATAH', 'TEKIRO', 'PCS', 40975.00, 3.000, 1, 'MU3422'
UNION ALL SELECT 'MU3423', 'TATAH KAYU TEKIRO 5/8', 'TATAH', 'TEKIRO', 'PCS', 46200.00, 3.000, 1, 'MU3423'
UNION ALL SELECT 'MU3424', 'SEPATU AP BOOT TERRA UK.42', 'SEPATU', 'AP', 'PSG', 89000.00, 10.000, 1, 'MU3424'
UNION ALL SELECT 'MU3425', 'SEPATU AP BOOT TERRA UK.43', 'SEPATU', 'AP', 'PSG', 89000.00, 10.000, 1, 'MU3425'
UNION ALL SELECT 'MU3426', 'CUTTER A-300', 'CUTTER', 'KENKO', 'PCS', 6250.00, 6.000, 1, 'MU3426'
UNION ALL SELECT 'MU3427', 'SEAL KIT SCN-30 POMPA HANDSPRAYER', 'S/PART', '-', 'SET', 19000.00, 0.000, 1, 'MU3427'
UNION ALL SELECT 'MU3428', 'TU 26 SEAL KIT KRAN DAN GAGANG HANDSPRAYER', 'S/PART', '-', 'SET', 9000.00, 0.000, 1, 'MU3428'
UNION ALL SELECT 'MU3429', 'STARTER PULLEY ASSY HANDSPRAYER', 'S/PART', 'TORPEDO LEVEL', 'SET', 9000.00, 6.000, 1, 'MU3429'
UNION ALL SELECT 'MU3430', 'FRAME MESIN BABAT BG 378', 'S/PART', '-', 'SET', 190000.00, 0.000, 1, 'MU3430'
UNION ALL SELECT 'MU3431', 'KIPAS LANTAI SEKAI HFN-1060', 'KIPAS', 'SEKAI', 'UNIT', 255000.00, 0.000, 1, 'MU3431'
UNION ALL SELECT 'MU3432', 'STOP KRAN 3/4 MDN PLASTIK', 'KRAN', 'MDN', 'PCS', 9500.00, 12.000, 1, 'MU3432'
UNION ALL SELECT 'MU3433', 'STOP KRAN 1/2 MDN PLASTIK', 'KRAN', 'MDN', 'PCS', 8500.00, 12.000, 1, 'MU3433'
UNION ALL SELECT 'MU3434', 'KLEM PIPA PLAT 2', 'KLEM', '-', 'PCS', 1750.00, 20.000, 1, 'MU3434'
UNION ALL SELECT 'MU3435', 'KLEM PIPA PLAT 2 1/2', 'KLEM', '-', 'PCS', 2000.00, 20.000, 1, 'MU3435'
UNION ALL SELECT 'MU3436', 'KLEM PIPA PLAT 4', 'KLEM', '-', 'PCS', 3000.00, 12.000, 1, 'MU3436'
UNION ALL SELECT 'MU3437', 'SIKU TUKANG WATERPAS 12\'', 'ALAT UKUR', 'IGM', 'PCS', 32500.00, 12.000, 1, 'MU3437'
UNION ALL SELECT 'MU3438', 'PAKU RIVET 640', 'Paku', 'AP', 'DUS', 75000.00, 1.000, 1, 'MU3438'
UNION ALL SELECT 'MU3439', 'KIKIR PLAT 8\"', 'KIKIR', 'IDEKU', 'PCS', 18750.00, 6.000, 1, 'MU3439'
UNION ALL SELECT 'MU3440', 'KIKIR 1/2 LINGKARAN 10\"', 'KIKIR', '-', 'PCS', 29600.00, 6.000, 1, 'MU3440'
UNION ALL SELECT 'MU3441', 'KRAN BENSIN FUEL COCK MESIN BABAT', 'S/PART', 'MORIMOTO', 'PCS', 8000.00, 6.000, 1, 'MU3441'
UNION ALL SELECT 'MU3442', 'HANDLE MESIN BABAT', 'S/PART', 'MORIMOTO', 'PCS', 15000.00, 6.000, 1, 'MU3442'
UNION ALL SELECT 'MU3443', 'TANKI MESIN BABAT FUEL TANK', 'S/PART', 'MORIMOTO', 'PCS', 22000.00, 6.000, 1, 'MU3443'
UNION ALL SELECT 'MU3444', 'RANGKA MESIN BABAT', 'S/PART', 'MORIMOTO', 'PCS', 190000.00, 2.000, 1, 'MU3444'
UNION ALL SELECT 'MU3445', 'MATA GURINDA POTONG KINIK 4\"', 'MATA GURINDA', 'KINIK', 'PCS', 3300.00, 50.000, 1, 'MU3445'
UNION ALL SELECT 'MU3446', 'MATA BOR BETON 12MM', 'MATA BOR', 'ALWIN', 'PCS', 18150.00, 6.000, 1, 'MU3446'
UNION ALL SELECT 'MU3447', 'MATA BOR SET 1MM - 10 MM', 'MATA BOR', 'ALWIN', 'SET', 96250.00, 3.000, 1, 'MU3447'
UNION ALL SELECT 'MU3448', 'KUNCI PAS CR-V 8X10', 'Kunci', 'WIPRO', 'PCS', 17600.00, 6.000, 1, 'MU3448'
UNION ALL SELECT 'MU3449', 'KUNCI PAS CR-V 12X14', 'Kunci', 'WIPRO', 'PCS', 21450.00, 6.000, 1, 'MU3449'
UNION ALL SELECT 'MU3450', 'KUNCI PAS CR-V 16X17', 'Kunci', 'WIPRO', 'PCS', 30250.00, 6.000, 1, 'MU3450'
UNION ALL SELECT 'MU3451', 'MULTITESTER DIGITAL DT-830D', 'ALAT UKUR', 'WIPRO', 'UNIT', 57700.00, 2.000, 1, 'MU3451'
UNION ALL SELECT 'MU3452', 'TERPAL CROCODILE 6X7 A15', 'TERPAL', 'CROCODILE', 'LBR', 387917.00, 1.000, 1, 'MU3452'
UNION ALL SELECT 'MU3453', 'TERPAL CROCODILE 5X7 A15', 'TERPAL', 'CROCODILE', 'LBR', 326317.00, 0.000, 1, 'MU3453'
UNION ALL SELECT 'MU3454', 'AMPLAS BULAT P400', 'AMPLAS', 'VPR', 'PCS', 1500.00, 6.000, 1, 'MU3454'
UNION ALL SELECT 'MU3455', 'CARBURATOR CLEANER 300 ML', 'SEMPROTAN', 'MEGACOOLS', 'BTL', 27200.00, 5.000, 1, 'MU3455'
UNION ALL SELECT 'MU3456', 'CARBURATOR CLEANER 500 ML', 'SEMPROTAN', 'MEGACOOLS', 'BTL', 36350.00, 5.000, 1, 'MU3456'
UNION ALL SELECT 'MU3457', 'CIRCLE SAW BLADE TCT CSW-0740 7\" 40T', 'MATA SERKEL', 'WIPRO', 'PCS', 112000.00, 5.000, 1, 'MU3457'
UNION ALL SELECT 'MU3458', 'KUNCI L 3.5 STANDART', 'Kunci', 'WIPRO', 'PCS', 5250.00, 0.000, 1, 'MU3458'
UNION ALL SELECT 'MU3459', 'MATA BOR NACHI 2.5 MM', 'MATA BOR', 'NACHI', 'PCS', 19720.00, 10.000, 1, 'MU3459'
UNION ALL SELECT 'MU3460', 'ALLWIN DIAMOND WHEELL D2 D400', 'MATA GURINDA', 'ALWIN', 'PCS', 203500.00, 0.000, 1, 'MU3460'
UNION ALL SELECT 'MU3461', 'MATA BOR DIAMOND CORE 10MM', 'MATA BOR', 'WIPRO', 'PCS', 27500.00, 2.000, 1, 'MU3461'
UNION ALL SELECT 'MU3462', 'MATA BOR DIAMOND CORE 15MM', 'MATA BOR', 'WIPRO', 'PCS', 41250.00, 2.000, 1, 'MU3462'
UNION ALL SELECT 'MU3463', 'MATA BOR DIAMOND CORE 18MM', 'MATA BOR', 'WIPRO', 'PCS', 49500.00, 2.000, 1, 'MU3463'
UNION ALL SELECT 'MU3464', 'MATA BOR DIAMOND CORE 20MM', 'MATA BOR', 'WIPRO', 'PCS', 55000.00, 2.000, 1, 'MU3464'
UNION ALL SELECT 'MU3465', 'MATA BOR DIAMOND CORE 22MM', 'MATA BOR', 'WIPRO', 'PCS', 60500.00, 2.000, 1, 'MU3465'
UNION ALL SELECT 'MU3466', 'MATA BOR DIAMOND CORE 32MM', 'MATA BOR', 'WIPRO', 'PCS', 88000.00, 2.000, 1, 'MU3466'
UNION ALL SELECT 'MU3467', 'MATA BOR DIAMOND CORE 36MM', 'MATA BOR', 'WIPRO', 'PCS', 99000.00, 2.000, 1, 'MU3467'
UNION ALL SELECT 'MU3468', 'ROMPI PROYEK HIJAU', 'ALAT', 'WIPRO', 'PCS', 11000.00, 1.000, 1, 'MU3468'
UNION ALL SELECT 'MU3469', 'MATA BOR TUN ROTARY BURR TCT SET 10 PCS', 'MATA BOR', 'ALWIN', 'SET', 258500.00, 2.000, 1, 'MU3469'
UNION ALL SELECT 'MU3470', 'TANG POTONG KABEL SKUN', 'TANG', 'WIPRO', 'PCS', 38500.00, 6.000, 1, 'MU3470'
UNION ALL SELECT 'MU3471', 'HELM PROYEK PUTIH', 'ALAT', 'JCK', 'PCS', 43000.00, 12.000, 1, 'MU3471'
UNION ALL SELECT 'MU3472', 'HELM PROYEK HIJAU', 'ALAT', 'JCK', 'PCS', 43000.00, 12.000, 1, 'MU3472'
UNION ALL SELECT 'MU3473', 'HELM PROYEK KUNING', 'ALAT', 'JCK', 'PCS', 49000.00, 12.000, 1, 'MU3473'
UNION ALL SELECT 'MU3474', 'CORPUS BEIGE 40X40', 'TEHEL', 'JUPITER', 'DUS', 67720.00, 8.000, 1, 'MU3474'
UNION ALL SELECT 'MU3475', 'CORPUS BROWN 40X40', 'TEHEL', 'JUPITER', 'DUS', 67720.00, 8.000, 1, 'MU3475'
UNION ALL SELECT 'MU3476', 'CORPUS GREY 40X40', 'TEHEL', 'JUPITER', 'DUS', 67720.00, 8.000, 1, 'MU3476'
UNION ALL SELECT 'MU3477', 'ANGOLA BEIGE 40X40', 'TEHEL', 'JUPITER', 'DUS', 59395.00, 8.000, 1, 'MU3477'
UNION ALL SELECT 'MU3478', 'WINA BROWN 25X25', 'TEHEL', 'JUPITER', 'DUS', 69555.00, 8.000, 1, 'MU3478'
UNION ALL SELECT 'MU3479', 'ALTAN GREEN 25X25', 'TEHEL', 'JUPITER', 'DUS', 69555.00, 8.000, 1, 'MU3479'
UNION ALL SELECT 'MU3480', 'VIVO BEIGE 25X25', 'TEHEL', 'JUPITER', 'DUS', 69555.00, 8.000, 1, 'MU3480'
UNION ALL SELECT 'MU3481', 'NEVIA BEIGE 25X25', 'TEHEL', 'JUPITER', 'DUS', 69555.00, 8.000, 1, 'MU3481'
UNION ALL SELECT 'MU3482', 'VEGA BLUE 25X40', 'TEHEL', 'JUPITER', 'DUS', 68830.00, 8.000, 1, 'MU3482'
UNION ALL SELECT 'MU3483', 'VEGA GREEN 25X40', 'TEHEL', 'JUPITER', 'DUS', 68830.00, 8.000, 1, 'MU3483'
UNION ALL SELECT 'MU3484', 'MALIQA GREY 25X40', 'TEHEL', 'JUPITER', 'DUS', 69940.00, 8.000, 1, 'MU3484'
UNION ALL SELECT 'MU3485', 'METERAN WIPRO 7.5M', 'METERAN', 'WIPRO', 'PCS', 84000.00, 24.000, 1, 'MU3485'
UNION ALL SELECT 'MU3486', 'PADMA YELLOW 25X40', 'TEHEL', 'JUPITER', 'DUS', 69440.00, 8.000, 1, 'MU3486'
UNION ALL SELECT 'MU3487', 'QUINSTONE BROWN 40X40', 'TEHEL', 'JUPITER', 'DUS', 72715.00, 8.000, 1, 'MU3487'
UNION ALL SELECT 'MU3488', 'AUSTINS BEIGE 40X40', 'TEHEL', 'JUPITER', 'DUS', 59395.00, 8.000, 1, 'MU3488'
UNION ALL SELECT 'MU3489', 'AUSTINS GREY 40X40', 'TEHEL', 'JUPITER', 'DUS', 59395.00, 8.000, 1, 'MU3489'
UNION ALL SELECT 'MU3490', 'AMANO BEIGE 40X40', 'TEHEL', 'JUPITER', 'DUS', 59395.00, 8.000, 1, 'MU3490'
UNION ALL SELECT 'MU3491', 'AMANO GREY 40X40', 'TEHEL', 'JUPITER', 'DUS', 59395.00, 8.000, 1, 'MU3491'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU3492' AS sku, 'ANGOLA GREY 40X40' AS name, 'TEHEL' AS category_name, 'JUPITER' AS brand_name, 'DUS' AS base_unit_code, 59395.00 AS cost_price, 8.000 AS min_stock, 1 AS is_active, 'MU3492' AS legacy_sku
UNION ALL SELECT 'MU3493', 'SKRUP BAJA RINGAN KUNING 12X100', 'BAUT', 'WILSON', 'PCS', 840.00, 200.000, 1, 'MU3493'
UNION ALL SELECT 'MU3494', 'GUNTING BETON 24\"', 'GUNTING', 'BLACKFOOT', 'PCS', 191400.00, 6.000, 1, 'MU3494'
UNION ALL SELECT 'MU3495', 'GUNTING BETON 36\"', 'GUNTING', 'BLACKFOOT', 'PCS', 343200.00, 6.000, 1, 'MU3495'
UNION ALL SELECT 'MU3496', 'MATA GURINDA GOSOK WD', 'MATA GURINDA', 'WD', 'PCS', 3000.00, 100.000, 1, 'MU3496'
UNION ALL SELECT 'MU3497', 'PILLOX SC-71', 'PILLOX', 'SUZUKA', 'PCS', 0.00, 6.000, 1, 'MU3497'
UNION ALL SELECT 'MU3498', 'BEE BRAND JUNIOR 634 0.8L', 'CAT', 'JUNIOR', 'KLG', 57600.00, 0.000, 1, 'MU3498'
UNION ALL SELECT 'MU3499', 'BEE BRAND JUNIOR 614 0.8L', 'CAT', 'JUNIOR', 'KLG', 57600.00, 0.000, 1, 'MU3499'
UNION ALL SELECT 'MU3500', 'BEE BRAND JUNIOR 620 0.8L', 'CAT', 'NIPPON PAINT', 'KLG', 57600.00, 0.000, 1, 'MU3500'
UNION ALL SELECT 'MU3501', 'BEE BRAND JUNIOR 658 0.8L', 'CAT', 'NIPPON PAINT', 'KLG', 57600.00, 0.000, 1, 'MU3501'
UNION ALL SELECT 'MU3502', 'NIPPE 2000 469', 'CAT', 'NIPPON PAINT', 'KLG', 109915.00, 0.000, 1, 'MU3502'
UNION ALL SELECT 'MU3503', 'BEE BRAND JUNIOR 631', 'CAT', 'NIPPON PAINT', 'KLG', 55271.00, 12.000, 1, 'MU3503'
UNION ALL SELECT 'MU3504', 'BENANG TUKANG KEPANG', 'ALAT', '-', 'IKAT', 2125.00, 0.000, 1, 'MU3504'
UNION ALL SELECT 'MU3505', 'BAUT 14 UNC 3/8X6', 'BAUT', '-', 'PSG', 1656.00, 20.000, 1, 'MU3505'
UNION ALL SELECT 'MU3506', 'BAUT 14 UNC 3/8X3', 'BAUT', '-', 'PSG', 1017.00, 20.000, 1, 'MU3506'
UNION ALL SELECT 'MU3507', 'BAUT 14 UNC 3/8X4', 'BAUT', '-', 'PSG', 1208.00, 20.000, 1, 'MU3507'
UNION ALL SELECT 'MU3508', 'BAUT 14 UNC 3/8X3 1/2', 'BAUT', '-', 'PSG', 1118.00, 20.000, 1, 'MU3508'
UNION ALL SELECT 'MU3509', 'BAUT 14 10X100', 'BAUT', '-', 'PSG', 1598.00, 100.000, 1, 'MU3509'
UNION ALL SELECT 'MU3510', 'BAUT 14 10X150', 'BAUT', '-', 'PSG', 2335.00, 100.000, 1, 'MU3510'
UNION ALL SELECT 'MU3511', 'BAUT 17 12X80', 'BAUT', '-', 'PSG', 2077.00, 100.000, 1, 'MU3511'
UNION ALL SELECT 'MU3512', 'BAUT 17 12X100', 'BAUT', '-', 'PSG', 2450.00, 50.000, 1, 'MU3512'
UNION ALL SELECT 'MU3513', 'BAUT 17 12X150', 'BAUT', '-', 'PSG', 3946.00, 0.000, 1, 'MU3513'
UNION ALL SELECT 'MU3514', 'MURR 12', 'ACCU', '-', 'PCS', 250.00, 250.000, 1, 'MU3514'
UNION ALL SELECT 'MU3515', 'MURR 10', 'BAUT', '-', 'PCS', 110.00, 250.000, 1, 'MU3515'
UNION ALL SELECT 'MU3516', 'MURR 8', 'BAUT', '-', 'PCS', 60.00, 0.000, 1, 'MU3516'
UNION ALL SELECT 'MU3517', 'MEDITRAN S 40 10 L', 'OLI', 'MESRAN', 'BTL', 512500.00, 0.000, 1, 'MU3517'
UNION ALL SELECT 'MU3518', 'OVEN HOCK NO 2 PUTAR HAWA', 'OVEN', 'HOCK', 'UNIT', 853000.00, 0.000, 1, 'MU3518'
UNION ALL SELECT 'MU3519', 'OVEN HOCK NO 3 PUTAR HAWA', 'OVEN', 'HOCK', 'UNIT', 545000.00, 0.000, 1, 'MU3519'
UNION ALL SELECT 'MU3520', 'AMPLAS ROLL AA150', 'ACCU', '-', 'MTR', 0.00, 0.000, 1, 'MU3520'
UNION ALL SELECT 'MU3521', 'KIPAS MEJA MIYAKO KAD-1227B PL', 'KIPAS', 'MIYAKO', 'UNIT', 215000.00, 1.000, 1, 'MU3521'
UNION ALL SELECT 'MU3522', 'KIPAS MEJA HYPERLITE TDF 838', 'KIPAS', 'HYPERLITE', 'UNIT', 95000.00, 1.000, 1, 'MU3522'
UNION ALL SELECT 'MU3523', 'ARIES GOLD 104 20KG', 'CAT', 'Avian', 'PAIL', 200000.00, 0.000, 1, 'MU3523'
UNION ALL SELECT 'MU3524', 'NO DROP DEMPUL INSTAN', 'DEMPUL', 'Avian', 'PCS', 2042.00, 0.000, 1, 'MU3524'
UNION ALL SELECT 'MU3525', 'PISTON ASSY STD BG 328', 'S/PART', 'MIYAMOTO', 'SET', 22500.00, 6.000, 1, 'MU3525'
UNION ALL SELECT 'MU3526', 'XIONE GREY 40X40', 'TEHEL', 'ACCURA', 'DUS', 35200.00, 8.000, 1, 'MU3526'
UNION ALL SELECT 'MU3527', 'BAJA RINGAN KANAL C 75X35X0.75 TCT', 'BAJA RINGAN', 'GAJAH', 'BTG', 115000.00, 0.000, 1, 'MU3527'
UNION ALL SELECT 'MU3528', 'SIKU RAK PUTIH', 'BESI', 'IGM', 'BTG', 35000.00, 0.000, 1, 'MU3528'
UNION ALL SELECT 'MU3529', 'SIKU RAK HAMMERTONE', 'BESI', 'IGM', 'BTG', 35000.00, 0.000, 1, 'MU3529'
UNION ALL SELECT 'MU3530', 'QUERA BROWN 40X40', 'TEHEL', 'JUPITER', 'DUS', 72794.00, 8.000, 1, 'MU3530'
UNION ALL SELECT 'MU3531', 'LIST PLAFON P04 BLACK GOLD', 'ACCU', 'LISTPRO', 'BTG', 90487.00, 0.000, 1, 'MU3531'
UNION ALL SELECT 'MU3532', 'LIST PLAFON B02 GOLD 80', 'AKSESORIS', 'LISTPRO', 'BTG', 71500.00, 0.000, 1, 'MU3532'
UNION ALL SELECT 'MU3533', 'BOSTER GURINDA W3436', 'S/PART', 'WIPRO', 'PSG', 7975.00, 5.000, 1, 'MU3533'
UNION ALL SELECT 'MU3534', 'ADDIBOND 1 KG PENGERAS BETON', 'CAIRAN', 'ADDIBOND', 'KLG', 89024.00, 0.000, 1, 'MU3534'
UNION ALL SELECT 'MU3535', 'DEMPUL MOBIL ALPHAGLOSS 1 KG', 'DEMPUL', 'ALFAGLOSS', 'KLG', 43100.00, 0.000, 1, 'MU3535'
UNION ALL SELECT 'MU3536', 'DITON HI-TEMP BLACK 300ML', 'CAT', 'DITTON', 'KLG', 47950.00, 3.000, 1, 'MU3536'
UNION ALL SELECT 'MU3537', 'DITON DB303 INDIGO VIOLET 300ML', 'CAT', 'DITTON', 'KLG', 84600.00, 3.000, 1, 'MU3537'
UNION ALL SELECT 'MU3538', 'DITON 9102 WHITE 400ML', 'CAT', 'DITTON', 'KLG', 37600.00, 3.000, 1, 'MU3538'
UNION ALL SELECT 'MU3539', 'DITON 9120 PRIMER GREY 400ML', 'CAT', 'DITTON', 'KLG', 37600.00, 3.000, 1, 'MU3539'
UNION ALL SELECT 'MU3540', 'DITON 9128 CLEAR GLOSS + ACTIVATOR 400ML', 'CAT', 'DITTON', 'KLG', 37600.00, 3.000, 1, 'MU3540'
UNION ALL SELECT 'MU3541', 'DITON DB301 KELLY ORANGE 300ML', 'CAT', 'DITTON', 'KLG', 84600.00, 3.000, 1, 'MU3541'
UNION ALL SELECT 'MU3542', 'DITON VG9612 BRONZE 400ML', 'CAT', 'DITTON', 'KLG', 42350.00, 3.000, 1, 'MU3542'
UNION ALL SELECT 'MU3543', 'DITON VG9611 TITANIUM GOLD 400ML', 'CAT', 'DITTON', 'KLG', 42350.00, 3.000, 1, 'MU3543'
UNION ALL SELECT 'MU3544', 'DITON 9130 CLEAR DOFF 400ML', 'CAT', 'DITTON', 'KLG', 37600.00, 3.000, 1, 'MU3544'
UNION ALL SELECT 'MU3545', 'DITON VG6815 SILVER GREY 400ML', 'CAT', 'DITTON', 'KLG', 42350.00, 3.000, 1, 'MU3545'
UNION ALL SELECT 'MU3546', 'LIST PLAFON B02 TEAK GOLD 80', 'LIST PLAFON', 'LISTPRO', 'BTG', 71500.00, 0.000, 1, 'MU3546'
UNION ALL SELECT 'MU3547', 'LIST PLAFON B01 GOLD 50', 'LIST PLAFON', 'LISTPRO', 'BTG', 47247.00, 0.000, 1, 'MU3547'
UNION ALL SELECT 'MU3548', 'LIST PLAFON P03 K22 RED CHERRY', 'LIST PLAFON', 'LISTPRO', 'BTG', 37663.00, 0.000, 1, 'MU3548'
UNION ALL SELECT 'MU3549', 'LIST PLAFON P03 K22 GOLD 30', 'LIST PLAFON', 'LISTPRO', 'BTG', 37663.00, 0.000, 1, 'MU3549'
UNION ALL SELECT 'MU3550', 'LIST PLAFON P01 GOLD BLACK 80', 'LIST PLAFON', 'LISTPRO', 'BTG', 63250.00, 0.000, 1, 'MU3550'
UNION ALL SELECT 'MU3551', 'LIST PLAFON P01 GOLD 80', 'LIST PLAFON', 'LISTPRO', 'BTG', 63250.00, 0.000, 1, 'MU3551'
UNION ALL SELECT 'MU3552', 'LIST PLAFON M01 TEAK GOLD 20', 'LIST PLAFON', 'LISTPRO', 'BTG', 25000.00, 0.000, 1, 'MU3552'
UNION ALL SELECT 'MU3553', 'BRILIANT WHITE 40X40', 'TEHEL', 'JUPITER', 'DUS', 57175.00, 8.000, 1, 'MU3553'
UNION ALL SELECT 'MU3554', 'DEMPUL MOBIL SANPOLAC KECIL', 'DEMPUL', 'SANPOLAC', 'PSG', 22950.00, 0.000, 1, 'MU3554'
UNION ALL SELECT 'MU3555', 'LEM ALL IN ONE EASY FIX', 'LEM', 'TAIYO', 'BTL', 42000.00, 6.000, 1, 'MU3555'
UNION ALL SELECT 'MU3556', 'TERMINAL 3 LB MT-554', 'TERMINAL COK', 'MATSUI', 'PCS', 14862.00, 12.000, 1, 'MU3556'
UNION ALL SELECT 'MU3557', 'TERMINAL BLOCK 16a 12MM', 'STEKER', 'MATSUI', 'PCS', 12.00, 2.000, 1, 'MU3557'
UNION ALL SELECT 'MU3558', 'TERMINAL BLOCK 20A14MM', 'SAMBUNGAN', '-', 'BTG', 21.00, 0.000, 1, 'MU3558'
UNION ALL SELECT 'MU3559', 'FITING PAGODA P 203 CREAM', 'FITING', 'PAGODA', 'PCS', 5000.00, 0.000, 1, 'MU3559'
UNION ALL SELECT 'MU3560', 'FITTING PAGODA P202 CREAM', 'FITING', 'PAGODA', 'PCS', 5000.00, 0.000, 1, 'MU3560'
UNION ALL SELECT 'MU3561', 'FITTING PAGODA P201 CREAM', 'FITING', 'PAGODA', 'PCS', 5000.00, 0.000, 1, 'MU3561'
UNION ALL SELECT 'MU3562', 'SAMBUNGAN KABEL 12 MM', 'SAMBUNGAN', '-', 'BTG', 7000.00, 12.000, 1, 'MU3562'
UNION ALL SELECT 'MU3563', 'SAMBUNGAN KABEL 14 MM', 'SAMBUNGAN', '-', 'BTG', 8000.00, 12.000, 1, 'MU3563'
UNION ALL SELECT 'MU3564', 'DOBEL', 'TES PEN', '-', 'PCS', 0.00, 0.000, 1, 'MU3564'
UNION ALL SELECT 'MU3565', 'FISCHER TYRO S6 + SEKRUP', 'FISHER', 'DUMBO', 'BKS', 5500.00, 12.000, 1, 'MU3565'
UNION ALL SELECT 'MU3566', 'FISCHER TYRO S8 + SEKRUP', 'FISHER', 'DUMBO', 'BKS', 6500.00, 12.000, 1, 'MU3566'
UNION ALL SELECT 'MU3567', 'FISCHER TYRO S10 + SEKRUP', 'FISHER', 'DUMBO', 'BKS', 7500.00, 12.000, 1, 'MU3567'
UNION ALL SELECT 'MU3568', 'FISCHER TYRO S12 + SEKRUP', 'FISHER', 'DUMBO', 'BKS', 8500.00, 12.000, 1, 'MU3568'
UNION ALL SELECT 'MU3569', 'MULLER ECO 58219 SN/CP', 'KUNCI PINTU', 'MULLER', 'SET', 155000.00, 0.000, 1, 'MU3569'
UNION ALL SELECT 'MU3570', 'MULLER 978329', 'KUNCI PINTU', 'MULLER', 'SET', 220000.00, 0.000, 1, 'MU3570'
UNION ALL SELECT 'MU3571', 'MULLER 978320 BLACK', 'KUNCI PINTU', 'MULLER', 'SET', 220000.00, 0.000, 1, 'MU3571'
UNION ALL SELECT 'MU3572', 'MULLER 978320 SNCP', 'KUNCI PINTU', 'MULLER', 'SET', 220000.00, 0.000, 1, 'MU3572'
UNION ALL SELECT 'MU3573', 'TESPEN BLITZ 4\"', 'TES PEN', 'BLITZ', 'PCS', 12500.00, 0.000, 1, 'MU3573'
UNION ALL SELECT 'MU3574', 'RODA PAGAR TIPE V 60MM', 'RODA', '-', 'PCS', 30000.00, 0.000, 1, 'MU3574'
UNION ALL SELECT 'MU3575', 'RODA PAGAR TIPE V 70MM', 'RODA', '-', 'PCS', 40000.00, 0.000, 1, 'MU3575'
UNION ALL SELECT 'MU3576', 'SKRUP PUTIH 12X1 1/4', 'SKRUP', '-', 'PCS', 200.00, 0.000, 1, 'MU3576'
UNION ALL SELECT 'MU3577', 'SKRUP PUTIH 12 X 1', 'SKRUP', '-', 'PCS', 200.00, 0.000, 1, 'MU3577'
UNION ALL SELECT 'MU3578', 'CAMERON GREY 20X40', 'TEHEL', 'JUPITER', 'DUS', 68400.00, 8.000, 1, 'MU3578'
UNION ALL SELECT 'MU3579', 'CARAVAN GREY 20X40', 'TEHEL', 'JUPITER', 'DUS', 68400.00, 8.000, 1, 'MU3579'
UNION ALL SELECT 'MU3580', 'GLADSTONE GREY 20X40', 'TEHEL', 'JUPITER', 'DUS', 68400.00, 8.000, 1, 'MU3580'
UNION ALL SELECT 'MU3581', 'ANDESIT BROWN 20X40', 'TEHEL', 'JUPITER', 'DUS', 68400.00, 8.000, 1, 'MU3581'
UNION ALL SELECT 'MU3582', 'CAMERON BROWN 20X40', 'TEHEL', 'JUPITER', 'DUS', 68400.00, 8.000, 1, 'MU3582'
UNION ALL SELECT 'MU3583', 'CHELONIA BROWN 20X40', 'TEHEL', 'JUPITER', 'DUS', 68400.00, 8.000, 1, 'MU3583'
UNION ALL SELECT 'MU3584', 'CORAZON BEIGE 20X40', 'TEHEL', 'JUPITER', 'DUS', 68400.00, 8.000, 1, 'MU3584'
UNION ALL SELECT 'MU3585', 'HARMONY BLUE 60X60', 'TEHEL', 'AURORA', 'DUS', 133000.00, 8.000, 1, 'MU3585'
UNION ALL SELECT 'MU3586', 'ORNAMEN PLAFON PVC LPO-GP/001', 'FITING', 'ORNAMEN', 'SET', 46000.00, 6.000, 1, 'MU3586'
UNION ALL SELECT 'MU3587', 'ORNAMEN PLAFON PVC LPO-GP/006', 'FITING', 'ORNAMEN', 'SET', 46000.00, 6.000, 1, 'MU3587'
UNION ALL SELECT 'MU3588', 'ORNAMEN PLAFON PVC LPO-SE/A121', 'FITING', 'ORNAMEN', 'SET', 182000.00, 4.000, 1, 'MU3588'
UNION ALL SELECT 'MU3589', 'ORNAMEN PLAFON PVC LPO-SE/A201', 'FITING', 'ORNAMEN', 'SET', 182000.00, 4.000, 1, 'MU3589'
UNION ALL SELECT 'MU3590', 'ORNAMEN PLAFON PVC LPO-GPS/006', 'FITING', 'ORNAMEN', 'SET', 40540.00, 6.000, 1, 'MU3590'
UNION ALL SELECT 'MU3591', 'ORNAMEN PLAFON PVC LPO-SE79/001', 'FITING', 'ORNAMEN', 'SET', 406000.00, 2.000, 1, 'MU3591'
UNION ALL SELECT 'MU3592', 'ORNAMEN PLAFON PVC LPO-SE79/002', 'FITING', 'ORNAMEN', 'SET', 406000.00, 2.000, 1, 'MU3592'
UNION ALL SELECT 'MU3593', 'ORNAMEN PLAFON PVC LPO-SE79/003', 'FITING', 'ORNAMEN', 'SET', 406000.00, 2.000, 1, 'MU3593'
UNION ALL SELECT 'MU3594', 'KEPALA BOR KBD-10', 'S/PART', 'WIPRO', 'PCS', 43000.00, 0.000, 1, 'MU3594'
UNION ALL SELECT 'MU3595', 'STOP KONTAK TEMPEL DOBEL BROCO', 'ALAT LISTRIK', 'BROCO', 'PCS', 29500.00, 0.000, 1, 'MU3595'
UNION ALL SELECT 'MU3596', 'AZURA GOLD 25X40', 'TEHEL', 'DIAMOND', 'DUS', 45946.00, 0.000, 1, 'MU3596'
UNION ALL SELECT 'MU3597', 'AZURA BLUE 25X40', 'TEHEL', 'ARCADIA', 'DUS', 45946.00, 0.000, 1, 'MU3597'
UNION ALL SELECT 'MU3598', 'VIOLA GREEN 25X40', 'TEHEL', 'ARCADIA', 'DUS', 45946.00, 0.000, 1, 'MU3598'
UNION ALL SELECT 'MU3599', 'PALU KAMBING USA 16OZ PLS', 'PALU', 'HSJ', 'PCS', 25000.00, 0.000, 1, 'MU3599'
UNION ALL SELECT 'MU3600', 'KUNCI RING PAS 14X15', 'Kunci', 'WIPRO', 'PCS', 35000.00, 0.000, 1, 'MU3600'
UNION ALL SELECT 'MU3601', 'ORLANDO GREY 40X40', 'TEHEL', 'JUPITER', 'DUS', 68000.00, 0.000, 1, 'MU3601'
UNION ALL SELECT 'MU3602', 'ARIES GOLD 604 20 KG', 'CAT', 'ARIES GOLD', 'PAIL', 350000.00, 0.000, 1, 'MU3602'
UNION ALL SELECT 'MU3603', 'TRIPLEKS 18 MM SANDED', 'TRIPLEK', '-', 'LBR', 300000.00, 0.000, 1, 'MU3603'
UNION ALL SELECT 'MU3604', 'GREASE COBRA 1 LB', 'GRENDEL', '-', 'KLG', 70000.00, 0.000, 1, 'MU3604'
UNION ALL SELECT 'MU3605', 'STEKER GENSET KAKI 3', 'STEKER', '-', 'PCS', 12500.00, 0.000, 1, 'MU3605'
UNION ALL SELECT 'MU3606', 'KABEL NYA F 1.5', 'KABEL', '-', 'ROL', 182400.00, 0.000, 1, 'MU3606'
UNION ALL SELECT 'MU3607', 'KABEL NYA F 1.5 HITAM', 'KABEL', '-', 'ROL', 182400.00, 0.000, 1, 'MU3607'
UNION ALL SELECT 'MU3608', 'LAMPU LED DOWNLIGHT 10W', 'LAMPU', 'MIDEA', 'PCS', 43000.00, 0.000, 1, 'MU3608'
UNION ALL SELECT 'MU3609', 'LAMPU LED DOWNLIGHT 14W', 'LAMPU', 'MIDEA', 'PCS', 60500.00, 0.000, 1, 'MU3609'
UNION ALL SELECT 'MU3610', 'METERAN WIPRO 5M', 'METERAN', 'WIPRO', 'PCS', 49000.00, 0.000, 1, 'MU3610'
UNION ALL SELECT 'MU3611', 'ARMANY GREEN 25X25', 'TEHEL', 'DIAMOND', 'DUS', 75000.00, 0.000, 1, 'MU3611'
UNION ALL SELECT 'MU3612', 'PIPA PVC 4\' C ABU K', 'PIPA', 'POWER', 'BTG', 112832.00, 0.000, 1, 'MU3612'
UNION ALL SELECT 'MU3613', 'ORLANDO BEIGE 40X40', 'TEHEL', 'JUPITER', 'DUS', 59000.00, 0.000, 1, 'MU3613'
UNION ALL SELECT 'MU3614', 'SPAN KAYU', 'SPAN', '-', 'PCS', 15000.00, 0.000, 1, 'MU3614'
UNION ALL SELECT 'MU3615', 'GLACIER BEIGE 40X40', 'TEHEL', 'JUPITER', 'DUS', 50000.00, 0.000, 1, 'MU3615'
UNION ALL SELECT 'MU3616', 'GEMBOK BULAT 40MM TOLEDO', 'GEMBOK', 'TOLEDO', 'PCS', 19500.00, 0.000, 1, 'MU3616'
UNION ALL SELECT 'MU3617', 'GEMBOK BULAT 60MM TOLEDO', 'GEMBOK', 'TOLEDO', 'PCS', 27250.00, 0.000, 1, 'MU3617'
UNION ALL SELECT 'MU3618', 'PALU BATU GG FIBER 10LB', 'PALU', 'OSIWA', 'PCS', 165000.00, 0.000, 1, 'MU3618'
UNION ALL SELECT 'MU3619', 'PALU BATU GG FIBER 6LB', 'PALU', '-', 'PCS', 121000.00, 3.000, 1, 'MU3619'
UNION ALL SELECT 'MU3620', 'GREASE COBRA 10 LB', 'GRENDEL', 'AMICO', 'GLN', 290000.00, 0.000, 1, 'MU3620'
UNION ALL SELECT 'MU3621', 'KABEL TIES 4.8X400', 'KABEL', 'WIPRO', 'PAK', 60500.00, 0.000, 1, 'MU3621'
UNION ALL SELECT 'MU3622', 'METERAN SANSHAN 7.5M', 'METERAN', 'SANSHAN', 'PCS', 29000.00, 0.000, 1, 'MU3622'
UNION ALL SELECT 'MU3623', 'METERAN SANHSAN 5M', 'METERAN', 'SANSHAN', 'PCS', 18000.00, 0.000, 1, 'MU3623'
UNION ALL SELECT 'MU3624', 'KIPAS KAD-06', 'KIPAS', 'MIYAKO', 'UNIT', 115000.00, 1.000, 1, 'MU3624'
UNION ALL SELECT 'MU3625', 'PAKU SENG KECIL', 'Paku', 'KAWI', 'KG', 23500.00, 0.000, 1, 'MU3625'
UNION ALL SELECT 'MU3626', 'PLASTIK COR', 'PLASTIK', '-', 'ROL', 26000.00, 0.000, 1, 'MU3626'
UNION ALL SELECT 'MU3627', 'HESLEYO CREAN 50x50', 'TEHEL', 'JUPITER', 'DUS', 76000.00, 0.000, 1, 'MU3627'
UNION ALL SELECT 'MU3628', 'CALSIBOARD 3.5mm', 'TRIPLEK', 'AP', 'LBR', 50000.00, 0.000, 1, 'MU3628'
UNION ALL SELECT 'MU3629', 'TANG POTONG SERONG', 'TANG', 'WIPRO', 'PCS', 37950.00, 0.000, 1, 'MU3629'
UNION ALL SELECT 'MU3630', 'KIPAS MEJA COSMOS 12 DAR', 'KIPAS', 'COSMOS', 'UNIT', 230000.00, 0.000, 1, 'MU3630'
UNION ALL SELECT 'MU3631', 'KIPAS DINDING COSMOS 16 WFO', 'KIPAS', 'COSMOS', 'UNIT', 270000.00, 0.000, 1, 'MU3631'
UNION ALL SELECT 'MU3632', 'KIPAS BERDIRI SEKAI  SFN 1623', 'KIPAS', 'SEKAI', 'UNIT', 255000.00, 0.000, 1, 'MU3632'
UNION ALL SELECT 'MU3633', 'KIPAS MEJA MASPION HF 606', 'KIPAS', 'MASPION', 'UNIT', 150000.00, 0.000, 1, 'MU3633'
UNION ALL SELECT 'MU3634', 'KIPAS MEJA MASPION EF 309', 'KIPAS', 'MASPION', 'UNIT', 330000.00, 0.000, 1, 'MU3634'
UNION ALL SELECT 'MU3635', 'AVIAN SYNTHETIC 190 1 LTR', 'CAT', 'Avian', 'KLG', 85000.00, 0.000, 1, 'MU3635'
UNION ALL SELECT 'MU3636', 'AVIAN SYNTHETIC 301 1 LTR', 'CAT', 'Avian', 'KLG', 85000.00, 0.000, 1, 'MU3636'
UNION ALL SELECT 'MU3637', 'AVIAN SYNTHETIC 657 1 LTR', 'CAT', 'Avian', 'KLG', 85000.00, 0.000, 1, 'MU3637'
UNION ALL SELECT 'MU3638', 'AVIAN SYNTHETIC 733 1 LTR', 'CAT', 'Avian', 'KLG', 85000.00, 0.000, 1, 'MU3638'
UNION ALL SELECT 'MU3639', 'AVIAN SYNTHETIC 193 1 LTR', 'CAT', 'Avian', 'KLG', 85000.00, 0.000, 1, 'MU3639'
UNION ALL SELECT 'MU3640', 'NIPPE 2000 020', 'CAT', 'NIPPON PAINT', 'KLG', 62400.00, 0.000, 1, 'MU3640'
UNION ALL SELECT 'MU3641', 'NIPPE 2000 302-T', 'CAT', 'NIPPON PAINT', 'KLG', 62400.00, 0.000, 1, 'MU3641'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU3642' AS sku, 'NIPPE 2000 454' AS name, 'CAT' AS category_name, 'NIPPON PAINT' AS brand_name, 'KLG' AS base_unit_code, 62400.00 AS cost_price, 0.000 AS min_stock, 1 AS is_active, 'MU3642' AS legacy_sku
UNION ALL SELECT 'MU3643', 'NIPPE 2000 402-S', 'CAT', 'NIPPON PAINT', 'KLG', 62400.00, 0.000, 1, 'MU3643'
UNION ALL SELECT 'MU3644', 'NIPPE 2000 480 D MATT BLACK', 'CAT', 'NIPPON PAINT', 'KLG', 62400.00, 0.000, 1, 'MU3644'
UNION ALL SELECT 'MU3645', 'BODELAC 2 IN 1 9108 SILVER 1 KG', 'CAT', 'NIPPON PAINT', 'KLG', 72000.00, 0.000, 1, 'MU3645'
UNION ALL SELECT 'MU3646', 'AVITEX 601 25KG', 'CAT', 'Avian', 'PAIL', 440000.00, 0.000, 1, 'MU3646'
UNION ALL SELECT 'MU3647', 'NO DROP PAIL 019', 'CAT', 'Avian', 'PAIL', 1230200.00, 0.000, 1, 'MU3647'
UNION ALL SELECT 'MU3648', 'CAT NO DROP 020', 'CAT', 'Avian', 'GLN', 275000.00, 0.000, 1, 'MU3648'
UNION ALL SELECT 'MU3649', 'CAT NO DROP 025', 'CAT', '-', 'GLN', 275000.00, 0.000, 1, 'MU3649'
UNION ALL SELECT 'MU3650', 'AVITEX 752 5KG', 'CAT', 'Avian', 'GLN', 162500.00, 0.000, 1, 'MU3650'
UNION ALL SELECT 'MU3651', 'ARIES GOLD 500 5KG', 'CAT', 'Avian', 'GLN', 93000.00, 0.000, 1, 'MU3651'
UNION ALL SELECT 'MU3652', 'ARIES GOLD 801 5KG', 'CAT', 'Avian', 'GLN', 93000.00, 0.000, 1, 'MU3652'
UNION ALL SELECT 'MU3653', 'WALL SEALER 5400 2.5KG', 'CAT', 'NIPPON PAINT', 'GLN', 157352.00, 0.000, 1, 'MU3653'
UNION ALL SELECT 'MU3654', 'AVIAN 110 100CC', 'CAT', 'Avian', 'KLG', 0.00, 0.000, 1, 'MU3654'
UNION ALL SELECT 'MU3655', 'AVIAN 160 100CC', 'CAT', 'Avian', 'KLG', 6000.00, 0.000, 1, 'MU3655'
UNION ALL SELECT 'MU3656', 'AVIAN 178 100CC', 'CAT', 'Avian', 'KLG', 6000.00, 0.000, 1, 'MU3656'
UNION ALL SELECT 'MU3657', 'AVIAN SYNTHETIC 648 1 LTR', 'CAT', 'Avian', 'KLG', 85000.00, 0.000, 1, 'MU3657'
UNION ALL SELECT 'MU3658', 'AVIAN 191 100CC', 'CAT', 'Avian', 'KLG', 6000.00, 0.000, 1, 'MU3658'
UNION ALL SELECT 'MU3659', 'AVIAN 637 100CC', 'CAT', 'Avian', 'KLG', 6000.00, 0.000, 1, 'MU3659'
UNION ALL SELECT 'MU3660', 'AVIAN 642 100CC', 'CAT', 'Avian', 'KLG', 6000.00, 0.000, 1, 'MU3660'
UNION ALL SELECT 'MU3661', 'AVIAN 752 100CC', 'CAT', 'Avian', 'KLG', 6000.00, 0.000, 1, 'MU3661'
UNION ALL SELECT 'MU3662', 'AVIAN 753 100CC', 'CAT', 'Avian', 'KLG', 6000.00, 0.000, 1, 'MU3662'
UNION ALL SELECT 'MU3663', 'AVIAN 250 100CC', 'CAT', 'Avian', 'KLG', 6000.00, 0.000, 1, 'MU3663'
UNION ALL SELECT 'MU3664', 'AVIAN 189 100CC', 'CAT', 'Avian', 'KLG', 6000.00, 0.000, 1, 'MU3664'
UNION ALL SELECT 'MU3665', 'AVIAN 731 100CC', 'CAT', 'Avian', 'KLG', 6000.00, 0.000, 1, 'MU3665'
UNION ALL SELECT 'MU3666', 'AVIAN 924 100CC', 'CAT', 'Avian', 'KLG', 6000.00, 0.000, 1, 'MU3666'
UNION ALL SELECT 'MU3667', 'AVIAN 911 100CC', 'CAT', 'Avian', 'KLG', 6000.00, 0.000, 1, 'MU3667'
UNION ALL SELECT 'MU3668', 'AVIAN 915 100CC', 'CAT', 'Avian', 'KLG', 6000.00, 0.000, 1, 'MU3668'
UNION ALL SELECT 'MU3669', 'PERNIK GREY 40X40', 'TEHEL', 'LEGION', 'DUS', 78000.00, 0.000, 1, 'MU3669'
UNION ALL SELECT 'MU3670', 'KUNCI MEKAR HUBEN HL 505 M2', 'KUNCI PINTU', 'HUBEN', 'PCS', 7916.00, 0.000, 1, 'MU3670'
UNION ALL SELECT 'MU3671', 'TARIKAN LACI 126 HITAM', 'TARIKAN LACI', 'FRANCO', 'PCS', 5416.00, 0.000, 1, 'MU3671'
UNION ALL SELECT 'MU3672', 'TARIKAN LACI 126 PUTIH', 'TARIKAN LACI', 'FRANCO', 'PCS', 5416.00, 0.000, 1, 'MU3672'
UNION ALL SELECT 'MU3673', 'TARIKAN LACI 126 SILVER', 'TARIKAN LACI', 'FRANCO', 'PCS', 5416.00, 0.000, 1, 'MU3673'
UNION ALL SELECT 'MU3674', 'TARIKAN LACI 125 HITAM', 'TARIKAN LACI', 'FRANCO', 'PCS', 6250.00, 0.000, 1, 'MU3674'
UNION ALL SELECT 'MU3675', 'TARIKAN LACI 125 PUTIH', 'TARIKAN LACI', 'FRANCO', 'PCS', 6250.00, 0.000, 1, 'MU3675'
UNION ALL SELECT 'MU3676', 'TARIKAN LACI 125 SILVER', 'TARIKAN LACI', 'FRANCO', 'PCS', 6250.00, 0.000, 1, 'MU3676'
UNION ALL SELECT 'MU3677', 'TARIKAN LACI 12 BS CROM', 'TARIKAN LACI', '-', 'PCS', 5416.00, 0.000, 1, 'MU3677'
UNION ALL SELECT 'MU3678', 'TARIKAN LACI 12 SULING', 'TARIKAN LACI', '-', 'PCS', 5416.00, 0.000, 1, 'MU3678'
UNION ALL SELECT 'MU3679', 'SPRAY GUN 75 GNT', 'SPRAYER', 'GNT', 'SET', 110000.00, 0.000, 1, 'MU3679'
UNION ALL SELECT 'MU3680', 'PALU KAMBING USA 8OZ GRG', 'PALU', 'KIMITOS', 'PCS', 19573.00, 0.000, 1, 'MU3680'
UNION ALL SELECT 'MU3681', 'RING PAS 11', 'ACCU', '-', 'PCS', 1111.00, 0.000, 1, 'MU3681'
UNION ALL SELECT 'MU3682', 'SARINGAN GOT MAROON SAKURA', 'SARINGAN', 'SAKURA', 'PCS', 6500.00, 0.000, 1, 'MU3682'
UNION ALL SELECT 'MU3683', 'SARINGAN GOT PINK SAKURA', 'SARINGAN', 'SAKURA', 'PCS', 6500.00, 0.000, 1, 'MU3683'
UNION ALL SELECT 'MU3684', 'SARINGAN GOT BIRU SAKURA', 'SARINGAN', 'SAKURA', 'PCS', 6500.00, 0.000, 1, 'MU3684'
UNION ALL SELECT 'MU3685', 'SARINGAN GOT HIJAU SAKURA', 'SARINGAN', 'SAKURA', 'PCS', 6500.00, 0.000, 1, 'MU3685'
UNION ALL SELECT 'MU3686', 'MATA ROUTER 1/4\"XR 1/4\"', 'MATA ROUTER', 'WIPRO', 'PCS', 42350.00, 0.000, 1, 'MU3686'
UNION ALL SELECT 'MU3687', 'MATA ROUTER 1/4\"XR 5/16\"', 'MATA ROUTER', 'WIPRO', 'PCS', 51150.00, 0.000, 1, 'MU3687'
UNION ALL SELECT 'MU3688', 'M. GURINDA DOZIRO', 'ALAT', 'DOZIRO', 'UNIT', 299892.00, 0.000, 1, 'MU3688'
UNION ALL SELECT 'MU3689', 'SENG BIRU GELOMBANG 0.2', 'SENG', 'GAJAH', 'LBR', 45000.00, 0.000, 1, 'MU3689'
UNION ALL SELECT 'MU3690', 'SENG BIRU GELOMBANG 3M', 'SENG', 'GAJAH', 'LBR', 100000.00, 0.000, 1, 'MU3690'
UNION ALL SELECT 'MU3691', 'MATA GURINDA GRINDING CUP', 'MATA GURINDA', 'TAIYO', 'PCS', 71300.00, 0.000, 1, 'MU3691'
UNION ALL SELECT 'MU3692', 'KUNCI L STANDARD 3MM', 'Kunci', 'WIPRO', 'PCS', 3500.00, 0.000, 1, 'MU3692'
UNION ALL SELECT 'MU3693', 'MATA BOR KACA 5 MM', 'MATA BOR', 'SOLIGEN', 'PCS', 15000.00, 0.000, 1, 'MU3693'
UNION ALL SELECT 'MU3694', 'MATA BOR KACA 6 MM', 'MATA BOR', 'SOLIGEN', 'PCS', 18000.00, 0.000, 1, 'MU3694'
UNION ALL SELECT 'MU3695', 'MATA BOR KACA 8 MM', 'MATA BOR', 'SOLIGEN', 'PCS', 24000.00, 0.000, 1, 'MU3695'
UNION ALL SELECT 'MU3696', 'MATA BOR PAGODA 4-32 MM', 'MATA BOR', 'BISON', 'PCS', 88000.00, 0.000, 1, 'MU3696'
UNION ALL SELECT 'MU3697', 'GAS TORCH POPEYE', 'ALAT', 'POPEYE', 'PCS', 46000.00, 0.000, 1, 'MU3697'
UNION ALL SELECT 'MU3698', 'KUNCI GURINDA BMB', 'ALAT', '-', 'PCS', 18000.00, 0.000, 1, 'MU3698'
UNION ALL SELECT 'MU3699', 'POMPA OLI PUTAR', 'POMPA', 'BISON', 'SET', 325000.00, 0.000, 1, 'MU3699'
UNION ALL SELECT 'MU3700', 'SARUNG TANGAN ANTI GORES', 'SARUNG TANGAN', 'MOLLAR', 'PSG', 31250.00, 0.000, 1, 'MU3700'
UNION ALL SELECT 'MU3701', 'SELANG AVUR DOBEL', 'S/PART', 'SOLIGEN', 'SET', 60000.00, 0.000, 1, 'MU3701'
UNION ALL SELECT 'MU3702', 'MATA BOR KACA 10 MM', 'MATA BOR', 'SOLIGEN', 'PCS', 30000.00, 0.000, 1, 'MU3702'
UNION ALL SELECT 'MU3703', 'DIAMOND WHEEL MAZAKI 4\"', 'MATA GURINDA', '-', 'PCS', 70000.00, 0.000, 1, 'MU3703'
UNION ALL SELECT 'MU3704', 'KACA MATA BLACK', 'KACAMATA', '-', 'PCS', 11500.00, 0.000, 1, 'MU3704'
UNION ALL SELECT 'MU3705', 'KACA MATA CLEAR', 'KACAMATA', '-', 'PCS', 11500.00, 0.000, 1, 'MU3705'
UNION ALL SELECT 'MU3706', 'KACA MATA SMOKE', 'KACAMATA', '-', 'PCS', 11500.00, 0.000, 1, 'MU3706'
UNION ALL SELECT 'MU3707', 'KARET KLOSET DUDUK BIRU 4030', 'S/PART', '-', 'PCS', 60000.00, 0.000, 1, 'MU3707'
UNION ALL SELECT 'MU3708', 'PEMBERAT BENANG 200GR KERUCUT', 'ALAT', 'STAVIC', 'PCS', 10416.00, 12.000, 1, 'MU3708'
UNION ALL SELECT 'MU3709', 'PEMBERAT BENANG 150GR PELURU', 'ALAT', 'STAVIC', 'PCS', 10416.00, 0.000, 1, 'MU3709'
UNION ALL SELECT 'MU3710', 'CUTTER RENTENG', 'CUTTER', '-', 'PCS', 1875.00, 0.000, 1, 'MU3710'
UNION ALL SELECT 'MU3711', 'CUTTER BESI', 'CUTTER', '-', 'PCS', 11500.00, 0.000, 1, 'MU3711'
UNION ALL SELECT 'MU3712', 'STOP KRAN TOILET SK-019 TEE', 'KRAN', 'SANSHAN', 'PCS', 25000.00, 2.000, 1, 'MU3712'
UNION ALL SELECT 'MU3713', 'SELANG SPRING HOSE 1\"', 'SELANG', '-', 'ROL', 862500.00, 0.000, 1, 'MU3713'
UNION ALL SELECT 'MU3714', 'SELANG SPRING HOSE 3/4\'', 'SELANG', '-', 'ROL', 575000.00, 0.000, 1, 'MU3714'
UNION ALL SELECT 'MU3715', 'LAKBAN ANTI BOCOR PUTIH 5CMX1M', 'LAKBAN', 'MUGEN', 'ROL', 6000.00, 0.000, 1, 'MU3715'
UNION ALL SELECT 'MU3716', 'POLIBEST DEMPUL 0.8 KG', 'DEMPUL', 'POLIBEST', 'KLG', 33600.00, 0.000, 1, 'MU3716'
UNION ALL SELECT 'MU3717', 'POLIBEST MENI KAYU 0.8 KG', 'DEMPUL', 'POLIBEST', 'KLG', 33600.00, 3.000, 1, 'MU3717'
UNION ALL SELECT 'MU3718', 'POLIBEST PLAMIR KAYU 0.8 KG', 'DEMPUL', 'POLIBEST', 'KLG', 33600.00, 0.000, 1, 'MU3718'
UNION ALL SELECT 'MU3719', 'POLIBEST PLITUR WS02 CHERRY 1 LT', 'CAT', 'POLIBEST', 'KLG', 88900.00, 0.000, 1, 'MU3719'
UNION ALL SELECT 'MU3720', 'POLIBEST PLITUR WS04 WALLNUT 1 LT', 'CAT', 'POLIBEST', 'KLG', 88900.00, 0.000, 1, 'MU3720'
UNION ALL SELECT 'MU3721', 'TINNER UNI HJ 3.50 LT', 'CAT', 'POLIBEST', 'KLG', 157700.00, 0.000, 1, 'MU3721'
UNION ALL SELECT 'MU3722', 'X1106 ACLOSE SUPER MAX WHITE', 'CAT', 'ACLOSE', 'KLG', 24900.00, 0.000, 1, 'MU3722'
UNION ALL SELECT 'MU3723', 'X1342 ACLOSE SUPER MAX GREEN', 'CAT', 'ACLOSE', 'KLG', 24900.00, 0.000, 1, 'MU3723'
UNION ALL SELECT 'MU3724', 'X2096 ACLOSE SUPER MAX LEMON YELLOW', 'CAT', 'ACLOSE', 'KLG', 24900.00, 0.000, 1, 'MU3724'
UNION ALL SELECT 'MU3725', 'X3201 ACLOSE SUPER MAX ENGINE RED', 'CAT', 'ACLOSE', 'KLG', 24900.00, 0.000, 1, 'MU3725'
UNION ALL SELECT 'MU3726', 'X6621 ACLOSE SUPER MAX SILVER 01', 'CAT', 'ACLOSE', 'KLG', 24900.00, 0.000, 1, 'MU3726'
UNION ALL SELECT 'MU3727', 'X9002 ACLOSE SUPER MAX BLACK FLAT', 'CAT', 'ACLOSE', 'KLG', 24900.00, 0.000, 1, 'MU3727'
UNION ALL SELECT 'MU3728', 'X9003 ACLOSE SUPER MAX BLACK 03', 'CAT', 'ACLOSE', 'KLG', 24900.00, 0.000, 1, 'MU3728'
UNION ALL SELECT 'MU3729', 'KUNCI L BINTANG 9 PCS TM219', 'Kunci', 'WIPRO', 'SET', 95000.00, 0.000, 1, 'MU3729'
UNION ALL SELECT 'MU3730', 'TAMBANG KUNING KEMASAN', 'TALI', 'ALWIN', 'BKS', 2250.00, 0.000, 1, 'MU3730'
UNION ALL SELECT 'MU3731', 'SILICON GUN RRT', 'ALAT', 'BONUCCI', 'PCS', 11500.00, 0.000, 1, 'MU3731'
UNION ALL SELECT 'MU3732', 'SAMBUNGAN ALKON 2X2', 'SAMBUNGAN', '-', 'PCS', 13000.00, 0.000, 1, 'MU3732'
UNION ALL SELECT 'MU3733', 'MATA ROUTER 1/4\" x R5/16\" H 12MM', 'MATA ROUTER', 'WIPRO', 'PCS', 75000.00, 0.000, 1, 'MU3733'
UNION ALL SELECT 'MU3734', 'POMPA AIR SHIMIZU 116 BIT', 'POMPA', 'SHIMIZU', 'UNIT', 580700.00, 0.000, 1, 'MU3734'
UNION ALL SELECT 'MU3735', 'LAMPU CAFE ST64 TEH 4W', 'LAMPU', '-', 'PCS', 9500.00, 0.000, 1, 'MU3735'
UNION ALL SELECT 'MU3736', 'FITING DOBEL SAKLAR MATSUI MT2515', 'FITING', 'MATSUI', 'PCS', 15000.00, 0.000, 1, 'MU3736'
UNION ALL SELECT 'MU3737', 'STEKER 3 CABANG ST-6832', 'ALAT LISTRIK', 'PAGODA', 'PCS', 14000.00, 0.000, 1, 'MU3737'
UNION ALL SELECT 'MU3738', 'LAMPU BERAS RGB', 'LAMPU', '-', 'SET', 20000.00, 0.000, 1, 'MU3738'
UNION ALL SELECT 'MU3739', 'FITING LAMPU 10M 10 FITING', 'FITING', '-', 'ROL', 110000.00, 0.000, 1, 'MU3739'
UNION ALL SELECT 'MU3740', 'STOPKONTAK TANAM GONEO', 'ALAT LISTRIK', 'GONEO', 'PCS', 41856.00, 0.000, 1, 'MU3740'
UNION ALL SELECT 'MU3741', 'SAKLAR DOBEL TANAM GONEO', 'ALAT LISTRIK', 'GONEO', 'PCS', 56800.00, 0.000, 1, 'MU3741'
UNION ALL SELECT 'MU3742', 'SAKLAR TUNGGAL TANAM GONEO', 'ALAT LISTRIK', 'GONEO', 'PCS', 39200.00, 0.000, 1, 'MU3742'
UNION ALL SELECT 'MU3743', 'FRAME 2 LUBANG GONEO', 'ALAT LISTRIK', 'GONEO', 'PCS', 20000.00, 0.000, 1, 'MU3743'
UNION ALL SELECT 'MU3744', 'FRAME 3 LUBANG GONEO', 'ALAT LISTRIK', 'GONEO', 'PCS', 29600.00, 0.000, 1, 'MU3744'
UNION ALL SELECT 'MU3745', 'LAMPU LED 15W MATSUI', 'LAMPU', 'MATSUI', 'PCS', 22264.00, 0.000, 1, 'MU3745'
UNION ALL SELECT 'MU3746', 'LAMPU LED 35W ECOVA ULTRA', 'LAMPU', 'ECOVA', 'PCS', 110000.00, 0.000, 1, 'MU3746'
UNION ALL SELECT 'MU3747', 'RAKET NYAMUK TESLA TLR 819', 'ALAT LISTRIK', '-', 'PCS', 55500.00, 0.000, 1, 'MU3747'
UNION ALL SELECT 'MU3748', 'KABEL TIES 7.5X500', 'KABEL', 'WIPRO', 'PAK', 135000.00, 0.000, 1, 'MU3748'
UNION ALL SELECT 'MU3749', 'CCCCC', 'LAMPU', 'MATSUI', 'PCS', 22264.00, 0.000, 1, 'MU3749'
UNION ALL SELECT 'MU3750', 'SEMEN BIASA BUNGKUSAN', 'SEMEN', 'GRESIK', 'BKS', 3800.00, 0.000, 1, 'MU3750'
UNION ALL SELECT 'MU3751', 'KUNCI PINTU BOLZANO', 'KUNCI PINTU', '-', 'SET', 150000.00, 0.000, 1, 'MU3751'
UNION ALL SELECT 'MU3752', 'OVER SOCK 2X1', 'SAMBUNGAN', 'POWER', 'PCS', 9820.00, 0.000, 1, 'MU3752'
UNION ALL SELECT 'MU3753', 'ENGSEL HPP 4\' GOLD', 'ENGSEL', 'HPP', 'PSG', 6666.00, 0.000, 1, 'MU3753'
UNION ALL SELECT 'MU3754', 'GUNTING TANAMAN MLP-308', 'GUNTING', 'MULLER', 'PCS', 70000.00, 0.000, 1, 'MU3754'
UNION ALL SELECT 'MU3755', 'METERAN ROLL BESI 100M IGM', 'METERAN', 'IGM', 'PCS', 185000.00, 0.000, 1, 'MU3755'
UNION ALL SELECT 'MU3756', 'METERAN ROLL BESI 50M IGM', 'METERAN', 'IGM', 'PCS', 115000.00, 0.000, 1, 'MU3756'
UNION ALL SELECT 'MU3757', 'ARIES GOLD 404 5KG', 'CAT', 'Avian', 'GLN', 89000.00, 0.000, 1, 'MU3757'
UNION ALL SELECT 'MU3758', 'RED GASKET DEXTONE', 'LEM', 'DEXTONE', 'PCS', 16150.00, 0.000, 1, 'MU3758'
UNION ALL SELECT 'MU3759', 'HELIOS BROWN 40X40', 'TEHEL', 'LEGION', 'DUS', 59000.00, 0.000, 1, 'MU3759'
UNION ALL SELECT 'MU3760', 'BOYO POLITUR 603 1L', 'CAT', 'BOYO', 'KLG', 88100.00, 0.000, 1, 'MU3760'
UNION ALL SELECT 'MU3761', 'BOYO POLITUR 603 0.5L', 'CAT', 'BOYO', 'KLG', 49500.00, 0.000, 1, 'MU3761'
UNION ALL SELECT 'MU3762', 'PADMA BLUE 25X40', 'TEHEL', 'JUPITER', 'DUS', 69440.00, 0.000, 1, 'MU3762'
UNION ALL SELECT 'MU3763', 'OVER SOCK 1X1/2\"', 'SAMBUNGAN', 'POWER', 'PCS', 2686.00, 0.000, 1, 'MU3763'
UNION ALL SELECT 'MU3764', 'CARITA BROWN 40X40', 'ACCU', 'ARCADIA', 'DUS', 56000.00, 0.000, 1, 'MU3764'
UNION ALL SELECT 'MU3765', 'KUNCI RING 16X17', 'Kunci', 'WIPRO', 'PCS', 38000.00, 0.000, 1, 'MU3765'
UNION ALL SELECT 'MU3766', 'LAMPU SOROT ECOVA 30W', 'LAMPU', 'ECOVA', 'PCS', 164700.00, 0.000, 1, 'MU3766'
UNION ALL SELECT 'MU3767', 'LAMPU SOROT ECOVA 50W', 'LAMPU', 'ECOVA', 'PCS', 217800.00, 0.000, 1, 'MU3767'
UNION ALL SELECT 'MU3768', 'ARCA STONE BROWN 40X40', 'TEHEL', 'ARCADIA', 'DUS', 44144.00, 0.000, 1, 'MU3768'
UNION ALL SELECT 'MU3769', 'METERAN SANHSAN 3M', 'METERAN', 'SANSHAN', 'PCS', 11500.00, 0.000, 1, 'MU3769'
UNION ALL SELECT 'MU3770', 'CYLINDER KUNCI SANSHAN SK-007 BESAR', 'Kunci', 'SANSHAN', 'PCS', 27083.00, 0.000, 1, 'MU3770'
UNION ALL SELECT 'MU3771', 'SPRAYING ROD 1M MKK', 'S/PART', 'MKK', 'PCS', 21150.00, 0.000, 1, 'MU3771'
UNION ALL SELECT 'MU3772', 'SPRAYING STICK BRONZE MKK', 'S/PART', 'MKK', 'PCS', 29550.00, 0.000, 1, 'MU3772'
UNION ALL SELECT 'MU3773', 'WATER NOZZLE SMALL', 'S/PART', 'MKK', 'PCS', 5350.00, 0.000, 1, 'MU3773'
UNION ALL SELECT 'MU3774', 'WATER NOZZLE BIG TEBAL', 'S/PART', 'MKK', 'PCS', 26500.00, 0.000, 1, 'MU3774'
UNION ALL SELECT 'MU3775', 'KEPALA BUSI MESIN BABAT MKK', 'S/PART', 'MKK', 'PCS', 6642.00, 0.000, 1, 'MU3775'
UNION ALL SELECT 'MU3776', 'WATER NOZZLE BIG TIPIS', 'S/PART', 'MKK', 'PCS', 11700.00, 0.000, 1, 'MU3776'
UNION ALL SELECT 'MU3777', 'OUTLET METAL LEFT TU26', 'S/PART', 'MKK', 'PCS', 18450.00, 0.000, 1, 'MU3777'
UNION ALL SELECT 'MU3778', 'AIR CHAMBER TU26', 'S/PART', 'MKK', 'PCS', 26500.00, 0.000, 1, 'MU3778'
UNION ALL SELECT 'MU3779', 'PAKING SET ASSY MESIN BABAT', 'S/PART', 'MKK', 'SET', 6350.00, 0.000, 1, 'MU3779'
UNION ALL SELECT 'MU3780', 'SPRAYER SET U/ SPRAYER CAS', 'S/PART', 'MKK', 'SET', 5000.00, 0.000, 1, 'MU3780'
UNION ALL SELECT 'MU3781', 'PEN STANG MESIN BABAT', 'S/PART', 'MKK', 'PCS', 5940.00, 0.000, 1, 'MU3781'
UNION ALL SELECT 'MU3782', 'ADAPTOR SOCK 1/2X3/8', 'Kunci', 'WIPRO', 'PCS', 31900.00, 0.000, 1, 'MU3782'
UNION ALL SELECT 'MU3783', 'KLEM SELANG KAWAT 4 1/2', 'KLEM', 'JCK', 'PCS', 13200.00, 0.000, 1, 'MU3783'
UNION ALL SELECT 'MU3784', 'KLEM SELANG PLAT KUPING KUNING 1\"', 'KLEM', 'JCK', 'PCS', 4400.00, 0.000, 1, 'MU3784'
UNION ALL SELECT 'MU3785', 'GERGAJI GOROK WIPRO FAST-18\'', 'GERGAJI', 'WIPRO', 'PCS', 35750.00, 0.000, 1, 'MU3785'
UNION ALL SELECT 'MU3786', 'ENGSEL BESI BIRU', 'ENGSEL', '-', 'PCS', 40000.00, 0.000, 1, 'MU3786'
UNION ALL SELECT 'MU3787', 'GARUK TANAH BESI BIRU', 'ALAT', '-', 'PCS', 35000.00, 0.000, 1, 'MU3787'
UNION ALL SELECT 'MU3788', 'GRENDEL PAGAR BESI', 'ALAT', '-', 'SET', 120000.00, 0.000, 1, 'MU3788'
UNION ALL SELECT 'MU3789', 'ENGSEL HITAM BESAR', 'ENGSEL', '-', 'PCS', 70000.00, 0.000, 1, 'MU3789'
UNION ALL SELECT 'MU3790', 'ENGSEL PJG HITAM', 'ENGSEL', '-', 'PCS', 120000.00, 0.000, 1, 'MU3790'
UNION ALL SELECT 'MU3791', 'ENGSEL HITAM KECIL', 'ENGSEL', '-', 'PCS', 35000.00, 0.000, 1, 'MU3791'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

INSERT IGNORE INTO products (sku, name, category_id, brand_id, base_unit_code, cost_price, min_stock, is_active, is_damaged_goods, version, legacy_sku, created_at, updated_at)
SELECT v.sku, v.name, c.id, b.id, v.base_unit_code, v.cost_price, v.min_stock, v.is_active, 0, 1, v.legacy_sku, @now, @now
FROM (
SELECT 'MU3792' AS sku, 'LEM SUPER GLUE 3G VT110' AS name, 'LEM' AS category_name, 'VTECH' AS brand_name, 'PCS' AS base_unit_code, 3540.00 AS cost_price, 0.000 AS min_stock, 1 AS is_active, 'MU3792' AS legacy_sku
UNION ALL SELECT 'MU3793', 'LEM EPOXY NON-SAG 0.7 KG', 'LEM', 'NIPPON PAINT', 'PSG', 58295.00, 0.000, 1, 'MU3793'
UNION ALL SELECT 'MU3794', 'BEE BRAND JR 66 SW 200CC', 'CAT', 'NIPPON PAINT', 'KLG', 20292.00, 0.000, 1, 'MU3794'
UNION ALL SELECT 'MU3795', 'BEE BRAND JR66 625', 'CAT', 'NIPPON PAINT', 'KLG', 64525.00, 0.000, 1, 'MU3795'
UNION ALL SELECT 'MU3796', 'BEE BRAND JR66 655', 'CAT', 'NIPPON PAINT', 'KLG', 64525.00, 0.000, 1, 'MU3796'
UNION ALL SELECT 'MU3797', 'LIME GREEN PB127 PILLOX', 'CAT', 'NIPPON PAINT', 'KLG', 26500.00, 0.000, 1, 'MU3797'
UNION ALL SELECT 'MU3798', 'EASYCOAT SW 18KG', 'CAT', 'NIPPON PAINT', 'PAIL', 232000.00, 0.000, 1, 'MU3798'
UNION ALL SELECT 'MU3799', 'LEM EPOXY NON-SAG 3.2 KG', 'LEM', 'NIPPON PAINT', 'PSG', 240300.00, 0.000, 1, 'MU3799'
UNION ALL SELECT 'MU3800', 'NOZZLE HANDSPRAYER TURBO LONG', 'S/PART', 'MIYAMOTO', 'PCS', 31000.00, 0.000, 1, 'MU3800'
UNION ALL SELECT 'MU3801', 'ARCA STONE GREY 40X40', 'TEHEL', 'ARCADIA', 'DUS', 44144.00, 0.000, 1, 'MU3801'
UNION ALL SELECT 'MU3802', 'ARCA STONE BROWN 40X40', 'TEHEL', 'ARCADIA', 'DUS', 44144.00, 0.000, 1, 'MU3802'
UNION ALL SELECT 'MU3803', 'ASTRO BROWN 40X40', 'TEHEL', 'ARCADIA', 'DUS', 44144.00, 0.000, 1, 'MU3803'
UNION ALL SELECT 'MU3804', 'MIAMI GREY 40X40', 'TEHEL', 'ARCADIA', 'DUS', 39189.00, 0.000, 1, 'MU3804'
UNION ALL SELECT 'MU3805', 'MARELA WHITE 40X40', 'TEHEL', 'ARCADIA', 'DUS', 39189.00, 0.000, 1, 'MU3805'
UNION ALL SELECT 'MU3806', 'CORDOBA BLUE 40X40', 'TEHEL', 'ARCADIA', 'DUS', 39640.00, 0.000, 1, 'MU3806'
UNION ALL SELECT 'MU3807', 'CARITA LIGHT GREEN 40X40', 'TEHEL', 'ARCADIA', 'DUS', 39640.00, 0.000, 1, 'MU3807'
UNION ALL SELECT 'MU3808', 'BATU GOSOK SILICON IGM 8\"', 'BATU GOSOK', 'IGM', 'PCS', 20000.00, 0.000, 1, 'MU3808'
UNION ALL SELECT 'MU3809', 'KEPALA SEKOP EMAS PROHAKSON', 'SEKOP', '-', 'PCS', 54166.00, 0.000, 1, 'MU3809'
UNION ALL SELECT 'MU3810', 'MATEX PUTTY 4KG', 'PLAMIR', 'NIPPON PAINT', 'GLN', 69242.00, 0.000, 1, 'MU3810'
UNION ALL SELECT 'MU3811', 'KEONG 3\" POMPA ALKON PULLY', 'ALAT', 'MKK', 'UNIT', 700000.00, 0.000, 1, 'MU3811'
UNION ALL SELECT 'MU3812', 'PIPA BESI GALVANISH 1/2\"', 'PIPA', '-', 'BTG', 112000.00, 0.000, 1, 'MU3812'
UNION ALL SELECT 'MU3813', 'ENGSEL HPP 4\" SN', 'ENGSEL', 'HPP', 'PSG', 6250.00, 0.000, 1, 'MU3813'
UNION ALL SELECT 'MU3814', 'FILTER AIR GABUS ERNESTO', 'FILTER', 'ERNESTO', 'PCS', 12500.00, 0.000, 1, 'MU3814'
UNION ALL SELECT 'MU3815', 'FILTER AIR BENANG ERNESTO', 'FILTER', 'ERNESTO', 'PCS', 14000.00, 0.000, 1, 'MU3815'
UNION ALL SELECT 'MU3816', 'FILTER AIR CARBON ERNESTO', 'FILTER', 'ERNESTO', 'PCS', 17500.00, 0.000, 1, 'MU3816'
UNION ALL SELECT 'MU3817', 'RUMAH FILTER NEPEL KUNINGAN 3/4', 'FILTER', '-', 'SET', 85000.00, 0.000, 1, 'MU3817'
UNION ALL SELECT 'MU3818', 'LEM THREEBOND 25GR', 'LEM', 'ELGLADO', 'PCS', 15000.00, 0.000, 1, 'MU3818'
UNION ALL SELECT 'MU3819', 'POMPA MINYAK A10', 'POMPA', '-', 'PCS', 18000.00, 0.000, 1, 'MU3819'
UNION ALL SELECT 'MU3820', 'GAGANG KUNCI SOCKET 1/2\"', 'ALAT', 'XP TOOLS', 'PCS', 20000.00, 0.000, 1, 'MU3820'
UNION ALL SELECT 'MU3821', '3X8 SKRUP GYPSUM HITAM MAJESTY', 'SKRUP', 'MAJESTY', 'DUS', 120000.00, 0.000, 1, 'MU3821'
UNION ALL SELECT 'MU3822', 'SKRUP BAJA RINGAN KUNING 12X60', 'SKRUP', 'WILSON', 'DUS', 166500.00, 0.000, 1, 'MU3822'
UNION ALL SELECT 'MU3823', 'KUNCI RING PAS WIPRO 12MM', 'Kunci', 'WIPRO', 'PCS', 15000.00, 0.000, 1, 'MU3823'
UNION ALL SELECT 'MU3824', 'MAGIC COM MCM 609 MIYAKO', 'MAGICCOM', 'MIYAKO', 'UNIT', 220000.00, 0.000, 1, 'MU3824'
UNION ALL SELECT 'MU3825', 'MAGIC COM PSG-607 MIYAKO', 'MAGICCOM', 'MIYAKO', 'UNIT', 185000.00, 0.000, 1, 'MU3825'
UNION ALL SELECT 'MU3826', 'MAGIC COM MCM 606A MIYAKO', 'MAGICCOM', 'MIYAKO', 'UNIT', 225000.00, 0.000, 1, 'MU3826'
UNION ALL SELECT 'MU3827', 'MAGIC COM HD 3003 PHILIPS', 'MAGICCOM', 'PHILIP', 'UNIT', 390000.00, 0.000, 1, 'MU3827'
UNION ALL SELECT 'MU3828', 'KIPAS DINDING COSMOS 16 WFC', 'KIPAS', 'COSMOS', 'UNIT', 270000.00, 0.000, 1, 'MU3828'
UNION ALL SELECT 'MU3829', 'KIPAS ANGIN OKAYAMA OK-808', 'KIPAS', 'OKAYAMA', 'UNIT', 125000.00, 0.000, 1, 'MU3829'
UNION ALL SELECT 'MU3830', 'KIPAS MEJA MIYAKO KAD 927B', 'KIPAS', 'MIYAKO', 'UNIT', 165000.00, 0.000, 1, 'MU3830'
UNION ALL SELECT 'MU3831', 'KAWAT HARMONIKA 2M TIPIS', 'KAWAT RAM', '-', 'MTR', 54000.00, 0.000, 1, 'MU3831'
UNION ALL SELECT 'MU3832', 'KAWAT HARMONIKA 1M TIPIS', 'KAWAT RAM', '-', 'MTR', 28000.00, 0.000, 1, 'MU3832'
UNION ALL SELECT 'MU3833', 'PARANET 100m', 'KAWAT RAM', '-', 'MTR', 6000.00, 0.000, 1, 'MU3833'
UNION ALL SELECT 'MU3834', 'KAWAT PARABOLA PLASTIK NYAMUK', 'KAWAT RAM', 'SANSHAN', 'MTR', 11000.00, 0.000, 1, 'MU3834'
UNION ALL SELECT 'MU3835', 'REGULATOR GAS PUTAR W181M', 'ALAT DAPUR', 'WINN GAS', 'UNIT', 85000.00, 0.000, 1, 'MU3835'
UNION ALL SELECT 'MU3836', 'SEMEN CONS', 'SEMEN', '-', 'SAK', 100000.00, 0.000, 1, 'MU3836'
UNION ALL SELECT 'MU3837', 'SAMBUNGAN  SELANG SPRAYER KUNINGAN', 'S/PART', '-', 'PCS', 10000.00, 12.000, 1, 'MU3837'
UNION ALL SELECT 'MU3838', 'ALANA BEIGE 25X40', 'TEHEL', 'JUPITER', 'DUS', 69940.00, 0.000, 1, 'MU3838'
UNION ALL SELECT 'MU3839', 'KANAL C 75X75', 'BAJA RINGAN', '-', 'BTG', 90000.00, 0.000, 1, 'MU3839'
UNION ALL SELECT 'MU3840', 'GUNTING DAHAN SS FIT', 'GUNTING', '-', 'PCS', 23500.00, 0.000, 1, 'MU3840'
UNION ALL SELECT 'MU3841', '+ BIAYA POTONG', 'ACCU', '-', 'PCS', 3500.00, 0.000, 1, 'MU3841'
UNION ALL SELECT 'MU3842', 'KEONG ALKON 3\"', 'S/PART', '-', 'UNIT', 785000.00, 0.000, 1, 'MU3842'
UNION ALL SELECT 'MU3843', 'KARET PONDASI MESIN, ENGINE MOUNTING M.BABAT', 'S/PART', 'MIYAMOTO', 'PCS', 5000.00, 0.000, 1, 'MU3843'
UNION ALL SELECT 'MU3844', 'TERMINAL UTICON 3 LB', 'TERMINAL COK', 'UTICON', 'PCS', 16375.00, 0.000, 1, 'MU3844'
UNION ALL SELECT 'MU3845', 'TERMINAL UTICON 4 LB', 'TERMINAL COK', 'UTICON', 'PCS', 21250.00, 0.000, 1, 'MU3845'
UNION ALL SELECT 'MU3846', 'TERMINAL UTICON 5 LB', 'TERMINAL COK', 'UTICON', 'PCS', 25925.00, 0.000, 1, 'MU3846'
UNION ALL SELECT 'MU3847', 'BEE BRAND 200CC SB', 'CAT', 'NIPPON PAINT', 'KLG', 20292.00, 0.000, 1, 'MU3847'
UNION ALL SELECT 'MU3848', 'BEE BRAND 200 CC 605', 'CAT', 'NIPPON PAINT', 'KLG', 20292.00, 0.000, 1, 'MU3848'
UNION ALL SELECT 'MU3849', 'MAORI GREY 25X25', 'TEHEL', 'JUPITER', 'DUS', 22222.00, 0.000, 1, 'MU3849'
UNION ALL SELECT 'MU3850', 'ROSETTA PINK 25X40', 'TEHEL', 'JUPITER', 'DUS', 68830.00, 0.000, 1, 'MU3850'
UNION ALL SELECT 'MU3851', 'KUNCI L BINTANG 9 PCS TL219', 'Kunci', 'WIPRO', 'SET', 120000.00, 0.000, 1, 'MU3851'
UNION ALL SELECT 'MU3852', 'SKRUP GIPSUM YUSTAR 1', 'SKRUP', '-', 'DUS', 19500.00, 0.000, 1, 'MU3852'
UNION ALL SELECT 'MU3853', 'SKRUP GIPSUM YUSTAR 1 1/4', 'SKRUP', '-', 'DUS', 19500.00, 0.000, 1, 'MU3853'
UNION ALL SELECT 'MU3854', 'SKRUP GIPSUM YUSTAR 1 1/2', 'SKRUP', '-', 'DUS', 19500.00, 0.000, 1, 'MU3854'
UNION ALL SELECT 'MU3855', 'NEPAL BEIGE 50X50', 'TEHEL', 'JUPITER', 'DUS', 72399.00, 0.000, 1, 'MU3855'
UNION ALL SELECT 'MU3856', 'ALANA GREY 25X40', 'TEHEL', 'JUPITER', 'DUS', 69940.00, 0.000, 1, 'MU3856'
UNION ALL SELECT 'MU3857', 'ARMANI BROWN 25X25', 'TEHEL', 'DIAMOND', 'DUS', 75000.00, 0.000, 1, 'MU3857'
UNION ALL SELECT 'MU3858', 'GYPSUM X120X240', 'TRIPLEK', '-', 'LBR', 73297.00, 0.000, 1, 'MU3858'
UNION ALL SELECT 'MU3859', 'PAKU 8', 'Paku', '-', 'KG', 18000.00, 0.000, 1, 'MU3859'
UNION ALL SELECT 'MU3860', 'ELBOW 4 QUAD', 'SAMBUNGAN', 'QUAD', 'PCS', 16710.00, 0.000, 1, 'MU3860'
UNION ALL SELECT 'MU3861', 'KRAN AIR KP 04 1/2', 'KRAN', 'KRANZ', 'PCS', 11200.00, 0.000, 1, 'MU3861'
UNION ALL SELECT 'MU3862', 'STOP KRAN TOILET KC 01', 'KRAN', 'KRANZ', 'PCS', 41200.00, 0.000, 1, 'MU3862'
UNION ALL SELECT 'MU3863', 'STOP KRAN TOILET CA ECO 1/2', 'KRAN', 'KRANZ', 'PCS', 38800.00, 0.000, 1, 'MU3863'
UNION ALL SELECT 'MU3864', 'STOP KRAN TOILET KCP 01', 'KRAN', 'KRANZ', 'PCS', 12800.00, 0.000, 1, 'MU3864'
UNION ALL SELECT 'MU3865', 'STOP KRAN TOILET CA P 1/2', 'KRAN', 'KRANZ', 'PCS', 11600.00, 0.000, 1, 'MU3865'
UNION ALL SELECT 'MU3866', 'SEAL TAPE KRANZ', 'LAKBAN', 'KRANZ', 'PCS', 2960.00, 0.000, 1, 'MU3866'
UNION ALL SELECT 'MU3867', 'SPON ACIAN', 'ALAT', '-', 'PCS', 12500.00, 0.000, 1, 'MU3867'
UNION ALL SELECT 'MU3868', 'RODA PELOR SET 4', 'RODA', '-', 'SET', 19000.00, 0.000, 1, 'MU3868'
UNION ALL SELECT 'MU3869', 'BENANG MESIN JAHIT KARUNG', 'S/PART', '-', 'PCS', 15000.00, 0.000, 1, 'MU3869'
UNION ALL SELECT 'MU3870', 'DRAIN PLUG MKK', 'S/PART', 'MKK', 'PCS', 3600.00, 0.000, 1, 'MU3870'
UNION ALL SELECT 'MU3871', 'BLADE CIRCULAR SAW', 'MATA GURINDA', 'YOZURI', 'PCS', 65000.00, 0.000, 1, 'MU3871'
UNION ALL SELECT 'MU3872', 'KAPUR BESI', 'ALAT', '-', 'DUS', 5000.00, 0.000, 1, 'MU3872'
UNION ALL SELECT 'MU3873', 'PIPA PVC 4 AW', 'PIPA', 'POWER', 'BTG', 369000.00, 0.000, 1, 'MU3873'
UNION ALL SELECT 'MU3874', 'MATA BOR BESI 3.5 MM HSS M42', 'MATA BOR', 'WIPRO', 'PCS', 16500.00, 0.000, 1, 'MU3874'
UNION ALL SELECT 'MU3875', 'MATA BOR BESI 4 MM HSS M42', 'MATA BOR', 'WIPRO', 'PCS', 20350.00, 0.000, 1, 'MU3875'
UNION ALL SELECT 'MU3876', 'BOSTER SKAP KECIL W2821G', 'S/PART', 'WIPRO', 'PSG', 8250.00, 0.000, 1, 'MU3876'
UNION ALL SELECT 'MU3877', 'SWITCH SKAP W2821G', 'S/PART', 'WIPRO', 'PCS', 8250.00, 0.000, 1, 'MU3877'
UNION ALL SELECT 'MU3878', 'SWITCH MESIN GURINDA W3436', 'S/PART', 'WIPRO', 'PCS', 14850.00, 0.000, 1, 'MU3878'
UNION ALL SELECT 'MU3879', 'Zinc Chromate Primer (Grey Green) 1kg', 'CAT', 'NIPPON PAINT', 'KLG', 66100.00, 0.000, 1, 'MU3879'
UNION ALL SELECT 'MU3880', 'Bodelac 2-in-1 Anti-Karat BD9117 1KG', 'CAT', '-', 'KLG', 101700.00, 0.000, 1, 'MU3880'
UNION ALL SELECT 'MU3881', 'Bodelac 2-in-1 Anti-Karat BD9108 1KG', 'CAT', 'NIPPON PAINT', 'KLG', 101700.00, 0.000, 1, 'MU3881'
UNION ALL SELECT 'MU3882', 'Bodelac 2-in-1 Anti-Karat BD9102 1KG', 'CAT', 'NIPPON PAINT', 'KLG', 101700.00, 0.000, 1, 'MU3882'
UNION ALL SELECT 'MU3883', 'ELBOW 3 QUAD', 'PIPA', 'QUAD', 'PCS', 8780.00, 0.000, 1, 'MU3883'
UNION ALL SELECT 'MU3884', 'LEM PVAC VIP SUPER', 'LEM', 'VIPER', 'PCS', 25000.00, 0.000, 1, 'MU3884'
UNION ALL SELECT 'MU3885', 'LEM PVAC DEXTONE 1KG', 'LEM', 'DEXTONE', 'KLG', 39050.00, 0.000, 1, 'MU3885'
UNION ALL SELECT 'MU3886', 'LEM PVAC 0.5 KG DEXTONE', 'LEM', 'DEXTONE', 'KLG', 24510.00, 0.000, 1, 'MU3886'
UNION ALL SELECT 'MU3887', 'AMPLAS ROLL AA60', 'AMPLAS', 'HOMESTAR', 'MTR', 6728.00, 0.000, 1, 'MU3887'
UNION ALL SELECT 'MU3888', 'KAP LAMPU MINI', 'AKSESORIS', '-', 'PCS', 8500.00, 0.000, 1, 'MU3888'
UNION ALL SELECT 'MU3889', 'KABEL NYMHY 2X0.75', 'ACCU', '-', 'MTR', 14500.00, 0.000, 1, 'MU3889'
UNION ALL SELECT 'MU3890', 'HELIOSO GOLD 50X50', 'KERAMIK', 'JUPITER', 'DUS', 97000.00, 0.000, 1, 'MU3890'
UNION ALL SELECT 'MU3891', 'ARCHI LED 24W', 'LAMPU', '-', 'PCS', 29400.00, 0.000, 1, 'MU3891'
UNION ALL SELECT 'MU3892', 'KABEL NYM ORENCHI 2X1.5X50Y', 'KABEL', '-', 'MTR', 9555.00, 0.000, 1, 'MU3892'
UNION ALL SELECT 'MU3893', 'LINGGIS CUKLIT OREN KOBOT', 'LINGGIS', '-', 'PCS', 56000.00, 0.000, 1, 'MU3893'
UNION ALL SELECT 'MU3894', 'TOPENG LAS BESTGUARD KUNING', 'ALAT', '-', 'PCS', 36000.00, 0.000, 1, 'MU3894'
UNION ALL SELECT 'MU3895', 'TERMINAL 1 LB MT-551', 'TERMINAL COK', 'MATSUI', 'PCS', 8386.00, 0.000, 1, 'MU3895'
UNION ALL SELECT 'MU3896', 'LAMPU LED AKI JEPIT VISERO', 'LAMPU', '-', 'PCS', 9000.00, 1.000, 1, 'MU3896'
UNION ALL SELECT 'MU3897', 'TERMINAL 6 LB MT-556', 'TERMINAL COK', 'MATSUI', 'PCS', 26240.00, 0.000, 1, 'MU3897'
UNION ALL SELECT 'MU3898', 'ELBOW 1 QUAD', 'SAMBUNGAN', 'QUAD', 'PCS', 2590.00, 0.000, 1, 'MU3898'
UNION ALL SELECT 'MU3899', 'BOYO POLITUR 611 0.5 L', 'CAT', 'BOYO', 'KLG', 57800.00, 0.000, 1, 'MU3899'
) v
JOIN categories c ON c.name = v.category_name
JOIN brands b ON b.name = v.brand_name;

-- Satuan barang: satuan dasar (isi 1) dan satuan tambahan beserta harga jual & modal per satuan.
-- Hanya untuk barang yang baru dibuat oleh run ini (created_at = @now); barang yang sudah ada tidak ditambah satuannya.
INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU-2409' AS sku, 'PCS' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 117000.00 AS selling_price, 72875.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU0001', 'PCS', 1.000, 1, 0, 44000.00, 29167.00, 1, 1
UNION ALL SELECT 'MU0002', 'MTR', 1.000, 1, 0, 25000.00, 14750.00, 1, 1
UNION ALL SELECT 'MU0003', 'KG', 1.000, 1, 0, 24000.00, 15200.00, 1, 1
UNION ALL SELECT 'MU0004', 'KG', 1.000, 1, 0, 24000.00, 15200.00, 1, 1
UNION ALL SELECT 'MU0005', 'KG', 1.000, 1, 0, 24000.00, 15200.00, 1, 1
UNION ALL SELECT 'MU0006', 'KG', 1.000, 1, 0, 28000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0007', 'KG', 1.000, 1, 0, 28000.00, 12500.00, 1, 1
UNION ALL SELECT 'MU0008', 'PCS', 1.000, 1, 0, 15000.00, 6666.00, 1, 1
UNION ALL SELECT 'MU0009', 'KG', 1.000, 1, 0, 26000.00, 19200.00, 1, 1
UNION ALL SELECT 'MU0010', 'KG', 1.000, 1, 0, 26000.00, 19200.00, 1, 1
UNION ALL SELECT 'MU0011', 'MTR', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0012', 'PCS', 1.000, 1, 0, 17000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0013', 'PCS', 1.000, 1, 0, 20000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU0014', 'KG', 1.000, 1, 0, 80000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU0015', 'PCS', 1.000, 1, 0, 1429000.00, 1230200.00, 1, 1
UNION ALL SELECT 'MU0016', 'PCS', 1.000, 1, 0, 26000.00, 19200.00, 1, 1
UNION ALL SELECT 'MU0017', 'PCS', 1.000, 1, 0, 62000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU0018', 'PCS', 1.000, 1, 0, 19000.00, 15200.00, 1, 1
UNION ALL SELECT 'MU0019', 'PCS', 1.000, 1, 0, 37000.00, 17875.00, 1, 1
UNION ALL SELECT 'MU0020', 'KG', 1.000, 1, 0, 80000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU0021', 'PCS', 1.000, 1, 0, 25000.00, 18400.00, 1, 1
UNION ALL SELECT 'MU0022', 'PCS', 1.000, 1, 0, 49000.00, 23925.00, 1, 1
UNION ALL SELECT 'MU0023', 'UNIT', 1.000, 1, 0, 372000.00, 285480.00, 1, 1
UNION ALL SELECT 'MU0024', 'UNIT', 1.000, 1, 0, 494000.00, 380000.00, 1, 1
UNION ALL SELECT 'MU0025', 'UNIT', 1.000, 1, 0, 947000.00, 728000.00, 1, 1
UNION ALL SELECT 'MU0026', 'PCS', 1.000, 1, 0, 26000.00, 13000.00, 1, 1
UNION ALL SELECT 'MU0027', 'PCS', 1.000, 1, 0, 95000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU0028', 'PCS', 1.000, 1, 0, 85000.00, 38400.00, 1, 1
UNION ALL SELECT 'MU0029', 'PCS', 1.000, 1, 0, 110000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU0030', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0031', 'PCS', 1.000, 1, 0, 22000.00, 13000.00, 1, 1
UNION ALL SELECT 'MU0032', 'PCS', 1.000, 1, 0, 30000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU0033', 'PCS', 1.000, 1, 0, 72000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU0034', 'KLG', 1.000, 1, 0, 144000.00, 109915.00, 1, 1
UNION ALL SELECT 'MU0035', 'SET', 1.000, 1, 0, 217000.00, 154980.00, 1, 1
UNION ALL SELECT 'MU0036', 'PCS', 1.000, 1, 0, 60000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU0037', 'PCS', 1.000, 1, 0, 18000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0038', 'PCS', 1.000, 1, 0, 33000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU0039', 'PCS', 1.000, 1, 0, 28000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0040', 'PCS', 1.000, 1, 0, 28000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0041', 'PCS', 1.000, 1, 0, 16000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0042', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0043', 'PCS', 1.000, 1, 0, 63000.00, 52000.00, 1, 1
UNION ALL SELECT 'MU0044', 'PCS', 1.000, 1, 0, 80000.00, 56000.00, 1, 1
UNION ALL SELECT 'MU0045', 'ROL', 1.000, 1, 0, 100000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU0046', 'PCS', 1.000, 1, 0, 270000.00, 216000.00, 1, 1
UNION ALL SELECT 'MU0047', 'PCS', 1.000, 1, 0, 119000.00, 91500.00, 1, 1
UNION ALL SELECT 'MU0048', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0049', 'PCS', 1.000, 1, 0, 37000.00, 26500.00, 1, 1
UNION ALL SELECT 'MU0050', 'PCS', 1.000, 1, 0, 30000.00, 16500.00, 1, 1
UNION ALL SELECT 'MU0051', 'PCS', 1.000, 1, 0, 34000.00, 17000.00, 1, 1
UNION ALL SELECT 'MU0052', 'KLG', 1.000, 1, 0, 173000.00, 131542.00, 1, 1
UNION ALL SELECT 'MU0053', 'PCS', 1.000, 1, 0, 88000.00, 43450.00, 1, 1
UNION ALL SELECT 'MU0054', 'PCS', 1.000, 1, 0, 5000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU0055', 'PCS', 1.000, 1, 0, 68000.00, 55000.00, 1, 1
UNION ALL SELECT 'MU0056', 'PCS', 1.000, 1, 0, 148000.00, 118400.00, 1, 1
UNION ALL SELECT 'MU0057', 'PCS', 1.000, 1, 0, 165000.00, 132000.00, 1, 1
UNION ALL SELECT 'MU0058', 'PCS', 1.000, 1, 0, 6000.00, 3500.00, 1, 1
UNION ALL SELECT 'MU0059', 'PCS', 1.000, 1, 0, 45000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU0060', 'PCS', 1.000, 1, 0, 60000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU0061', 'PCS', 1.000, 1, 0, 105000.00, 84000.00, 1, 1
UNION ALL SELECT 'MU0062', 'PCS', 1.000, 1, 0, 130000.00, 104000.00, 1, 1
UNION ALL SELECT 'MU0063', 'PCS', 1.000, 1, 0, 150000.00, 120000.00, 1, 1
UNION ALL SELECT 'MU0064', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU0065', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU0066', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU0067', 'KLG', 1.000, 1, 0, 144000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU0068', 'LBR', 1.000, 1, 0, 85000.00, 68000.00, 1, 1
UNION ALL SELECT 'MU0069', 'KLG', 1.000, 1, 0, 144000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU0070', 'KLG', 1.000, 1, 0, 144000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU0071', 'PCS', 1.000, 1, 0, 32000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0072', 'KLG', 1.000, 1, 0, 144000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU0073', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0074', 'KLG', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0075', 'KG', 1.000, 1, 0, 40000.00, 23000.00, 1, 1
UNION ALL SELECT 'MU0076', 'PCS', 1.000, 1, 0, 250000.00, 200000.00, 1, 1
UNION ALL SELECT 'MU0077', 'PCS', 1.000, 1, 0, 113000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU0078', 'UNIT', 1.000, 1, 0, 907000.00, 604500.00, 1, 1
UNION ALL SELECT 'MU0079', 'UNIT', 1.000, 1, 0, 1638000.00, 1092000.00, 1, 1
UNION ALL SELECT 'MU0080', 'KG', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0081', 'PCS', 1.000, 1, 0, 28000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0082', 'PCS', 1.000, 1, 0, 16000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU0083', 'UNIT', 1.000, 1, 0, 250000.00, 200000.00, 1, 1
UNION ALL SELECT 'MU0084', 'UNIT', 1.000, 1, 0, 351000.00, 260000.00, 1, 1
UNION ALL SELECT 'MU0085', 'PCS', 1.000, 1, 0, 28000.00, 22400.00, 1, 1
UNION ALL SELECT 'MU0086', 'PCS', 1.000, 1, 0, 18000.00, 14400.00, 1, 1
UNION ALL SELECT 'MU0087', 'PCS', 1.000, 1, 0, 22000.00, 17600.00, 1, 1
UNION ALL SELECT 'MU0088', 'LBR', 1.000, 1, 0, 253000.00, 170000.00, 1, 1
UNION ALL SELECT 'MU0089', 'LBR', 1.000, 1, 0, 91000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU0090', 'DUS', 1.000, 1, 0, 99000.00, 72794.00, 1, 1
UNION ALL SELECT 'MU0091', 'KG', 1.000, 1, 0, 53000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU0092', 'PCS', 1.000, 1, 0, 43000.00, 29150.00, 1, 1
UNION ALL SELECT 'MU0093', 'UNIT', 1.000, 1, 0, 988000.00, 791000.00, 1, 1
UNION ALL SELECT 'MU0094', 'UNIT', 1.000, 1, 0, 1437000.00, 950000.00, 1, 1
UNION ALL SELECT 'MU0095', 'PCS', 1.000, 1, 0, 62000.00, 42075.00, 1, 1
UNION ALL SELECT 'MU0096', 'SAK', 1.000, 1, 0, 135000.00, 78588.00, 1, 1
UNION ALL SELECT 'MU0097', 'PAIL', 1.000, 1, 0, 433000.00, 324090.00, 1, 1
UNION ALL SELECT 'MU0098', 'PAIL', 1.000, 1, 0, 433000.00, 357700.00, 1, 1
UNION ALL SELECT 'MU0099', 'PAIL', 1.000, 1, 0, 433000.00, 357700.00, 1, 1
UNION ALL SELECT 'MU0100', 'PAIL', 1.000, 1, 0, 433000.00, 357700.00, 1, 1
UNION ALL SELECT 'MU0101', 'PCS', 1.000, 1, 0, 380000.00, 304000.00, 1, 1
UNION ALL SELECT 'MU0102', 'PCS', 1.000, 1, 0, 275000.00, 220000.00, 1, 1
UNION ALL SELECT 'MU0103', 'LBR', 1.000, 1, 0, 80000.00, 45387.00, 1, 1
UNION ALL SELECT 'MU0104', 'PAIL', 1.000, 1, 0, 876000.00, 440000.00, 1, 1
UNION ALL SELECT 'MU0105', 'LBR', 1.000, 1, 0, 445000.00, 341910.00, 1, 1
UNION ALL SELECT 'MU0106', 'PCS', 1.000, 1, 0, 750000.00, 600000.00, 1, 1
UNION ALL SELECT 'MU0107', 'KLG', 1.000, 1, 0, 178000.00, 135547.00, 1, 1
UNION ALL SELECT 'MU0108', 'PCS', 1.000, 1, 0, 47000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU0109', 'PCS', 1.000, 1, 0, 11000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0110', 'PCS', 1.000, 1, 0, 6000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU0111', 'PCS', 1.000, 1, 0, 7000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU0112', 'PCS', 1.000, 1, 0, 8000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU0113', 'PCS', 1.000, 1, 0, 11000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU0114', 'PCS', 1.000, 1, 0, 23000.00, 18400.00, 1, 1
UNION ALL SELECT 'MU0115', 'BTG', 1.000, 1, 0, 30000.00, 23000.00, 1, 1
UNION ALL SELECT 'MU0116', 'UNIT', 1.000, 1, 0, 1720000.00, 1160000.00, 1, 1
UNION ALL SELECT 'MU0117', 'SET', 1.000, 1, 0, 580000.00, 290000.00, 1, 1
UNION ALL SELECT 'MU0118', 'PCS', 1.000, 1, 0, 60000.00, 42075.00, 1, 1
UNION ALL SELECT 'MU0119', 'SAK', 1.000, 1, 0, 115000.00, 90000.00, 1, 1
UNION ALL SELECT 'MU0120', 'SAK', 1.000, 1, 0, 150000.00, 110000.00, 1, 1
UNION ALL SELECT 'MU0121', 'KLG', 1.000, 1, 0, 28000.00, 18400.00, 1, 1
UNION ALL SELECT 'MU0122', 'MTR', 1.000, 1, 0, 40000.00, 29200.00, 1, 1
UNION ALL SELECT 'MU0123', 'PCS', 1.000, 1, 0, 15000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU0124', 'PCS', 1.000, 1, 0, 17000.00, 13600.00, 1, 1
UNION ALL SELECT 'MU0125', 'PCS', 1.000, 1, 0, 54000.00, 31200.00, 1, 1
UNION ALL SELECT 'MU0126', 'MTR', 1.000, 1, 0, 18000.00, 14400.00, 1, 1
UNION ALL SELECT 'MU0127', 'MTR', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0128', 'PCS', 1.000, 1, 0, 6000.00, 4167.00, 1, 1
UNION ALL SELECT 'MU0129', 'PCS', 1.000, 1, 0, 12000.00, 8750.00, 1, 1
UNION ALL SELECT 'MU0130', 'PCS', 1.000, 1, 0, 50000.00, 33000.00, 1, 1
UNION ALL SELECT 'MU0131', 'PCS', 1.000, 1, 0, 75000.00, 52972.00, 1, 1
UNION ALL SELECT 'MU0132', 'PCS', 1.000, 1, 0, 70000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU0133', 'LBR', 1.000, 1, 0, 78000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU0134', 'PCS', 1.000, 1, 0, 55000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU0135', 'SET', 1.000, 1, 0, 763000.00, 611000.00, 1, 1
UNION ALL SELECT 'MU0136', 'SET', 1.000, 1, 0, 217000.00, 174000.00, 1, 1
UNION ALL SELECT 'MU0137', 'SET', 1.000, 1, 0, 250000.00, 137500.00, 1, 1
UNION ALL SELECT 'MU0138', 'SET', 1.000, 1, 0, 600000.00, 480000.00, 1, 1
UNION ALL SELECT 'MU0139', 'PCS', 1.000, 1, 0, 30000.00, 19500.00, 1, 1
UNION ALL SELECT 'MU0140', 'LBR', 1.000, 1, 0, 585000.00, 280000.00, 1, 1
UNION ALL SELECT 'MU0141', 'LBR', 1.000, 1, 0, 685000.00, 460000.00, 1, 1
UNION ALL SELECT 'MU0142', 'KG', 1.000, 1, 0, 46000.00, 23000.00, 1, 1
UNION ALL SELECT 'MU0143', 'PCS', 1.000, 1, 0, 48000.00, 38400.00, 1, 1
UNION ALL SELECT 'MU0144', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU0145', 'MTR', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0146', 'ROL', 1.000, 1, 0, 7000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU0147', 'LBR', 1.000, 1, 0, 3000.00, 2400.00, 1, 1
UNION ALL SELECT 'MU0148', 'UNIT', 1.000, 1, 0, 750000.00, 690000.00, 1, 1
UNION ALL SELECT 'MU0149', 'ROL', 1.000, 1, 0, 200000.00, 119000.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU0150' AS sku, 'MTR' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 25000.00 AS selling_price, 11764.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU0151', 'MTR', 1.000, 1, 0, 25000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0152', 'PCS', 1.000, 1, 0, 58000.00, 37500.00, 1, 1
UNION ALL SELECT 'MU0153', 'PCS', 1.000, 1, 0, 95000.00, 80000.00, 1, 1
UNION ALL SELECT 'MU0154', 'PCS', 1.000, 1, 0, 95000.00, 80000.00, 1, 1
UNION ALL SELECT 'MU0155', 'PCS', 1.000, 1, 0, 18000.00, 7500.00, 1, 1
UNION ALL SELECT 'MU0156', 'KG', 1.000, 1, 0, 80000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU0157', 'BTL', 1.000, 1, 0, 28000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0158', 'PCS', 1.000, 1, 0, 34000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0159', 'KG', 1.000, 1, 0, 26000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0160', 'PCS', 1.000, 1, 0, 9000.00, 6200.00, 1, 1
UNION ALL SELECT 'MU0161', 'PCS', 1.000, 1, 0, 10000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0162', 'PCS', 1.000, 1, 0, 12000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0163', 'BTG', 1.000, 1, 0, 200000.00, 152000.00, 1, 1
UNION ALL SELECT 'MU0164', 'BTG', 1.000, 1, 0, 108000.00, 81000.00, 1, 1
UNION ALL SELECT 'MU0165', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0166', 'BTG', 1.000, 1, 0, 45000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU0167', 'PSG', 1.000, 1, 0, 5000.00, 2400.00, 1, 1
UNION ALL SELECT 'MU0168', 'UNIT', 1.000, 1, 0, 266000.00, 204600.00, 1, 1
UNION ALL SELECT 'MU0169', 'UNIT', 1.000, 1, 0, 298000.00, 227100.00, 1, 1
UNION ALL SELECT 'MU0170', 'PCS', 1.000, 1, 0, 18000.00, 13000.00, 1, 1
UNION ALL SELECT 'MU0171', 'MTR', 1.000, 1, 0, 8000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU0172', 'BTG', 1.000, 1, 0, 29000.00, 14000.00, 1, 1
UNION ALL SELECT 'MU0173', 'PCS', 1.000, 1, 0, 68000.00, 47300.00, 1, 1
UNION ALL SELECT 'MU0174', 'LBR', 1.000, 1, 0, 529000.00, 406660.00, 1, 1
UNION ALL SELECT 'MU0175', 'LBR', 1.000, 1, 0, 336000.00, 282000.00, 1, 1
UNION ALL SELECT 'MU0176', 'KG', 1.000, 1, 0, 80000.00, 68000.00, 1, 1
UNION ALL SELECT 'MU0177', 'KG', 1.000, 1, 0, 80000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU0178', 'KG', 1.000, 1, 0, 80000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU0179', 'KG', 1.000, 1, 0, 80000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU0180', 'BKS', 1.000, 1, 0, 9000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0181', 'PCS', 1.000, 1, 0, 50000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU0182', 'PCS', 1.000, 1, 0, 4000.00, 2500.00, 1, 1
UNION ALL SELECT 'MU0183', 'PCS', 1.000, 1, 0, 4000.00, 2736.00, 1, 1
UNION ALL SELECT 'MU0184', 'PCS', 1.000, 1, 0, 10000.00, 6108.00, 1, 1
UNION ALL SELECT 'MU0185', 'LBR', 1.000, 1, 0, 77000.00, 59132.00, 1, 1
UNION ALL SELECT 'MU0186', 'UNIT', 1.000, 1, 0, 945000.00, 755700.00, 1, 1
UNION ALL SELECT 'MU0187', 'UNIT', 1.000, 1, 0, 600000.00, 480000.00, 1, 1
UNION ALL SELECT 'MU0188', 'UNIT', 1.000, 1, 0, 400000.00, 320000.00, 1, 1
UNION ALL SELECT 'MU0189', 'SET', 1.000, 1, 0, 610000.00, 305000.00, 1, 1
UNION ALL SELECT 'MU0190', 'PCS', 1.000, 1, 0, 90000.00, 41600.00, 1, 1
UNION ALL SELECT 'MU0191', 'PCS', 1.000, 1, 0, 78000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU0192', 'PCS', 1.000, 1, 0, 25000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0193', 'PCS', 1.000, 1, 0, 79000.00, 52500.00, 1, 1
UNION ALL SELECT 'MU0194', 'PCS', 1.000, 1, 0, 20000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU0195', 'PCS', 1.000, 1, 0, 47000.00, 31000.00, 1, 1
UNION ALL SELECT 'MU0196', 'PCS', 1.000, 1, 0, 38000.00, 19000.00, 1, 1
UNION ALL SELECT 'MU0197', 'PCS', 1.000, 1, 0, 39000.00, 28770.00, 1, 1
UNION ALL SELECT 'MU0198', 'PCS', 1.000, 1, 0, 18000.00, 12790.00, 1, 1
UNION ALL SELECT 'MU0199', 'LBR', 1.000, 1, 0, 253000.00, 194080.00, 1, 1
UNION ALL SELECT 'MU0200', 'LBR', 1.000, 1, 0, 75000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU0201', 'LBR', 1.000, 1, 0, 125000.00, 100000.00, 1, 1
UNION ALL SELECT 'MU0202', 'PCS', 1.000, 1, 0, 46000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0203', 'KLG', 1.000, 1, 0, 150000.00, 120000.00, 1, 1
UNION ALL SELECT 'MU0204', 'KLG', 1.000, 1, 0, 150000.00, 125000.00, 1, 1
UNION ALL SELECT 'MU0205', 'PCS', 1.000, 1, 0, 250000.00, 235000.00, 1, 1
UNION ALL SELECT 'MU0206', 'MTR', 1.000, 1, 0, 50000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU0207', 'MTR', 1.000, 1, 0, 15000.00, 9411.00, 1, 1
UNION ALL SELECT 'MU0208', 'MTR', 1.000, 1, 0, 70000.00, 52000.00, 1, 1
UNION ALL SELECT 'MU0209', 'MTR', 1.000, 1, 0, 40000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU0210', 'MTR', 1.000, 1, 0, 36000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU0211', 'MTR', 1.000, 1, 0, 32000.00, 21000.00, 1, 1
UNION ALL SELECT 'MU0212', 'MTR', 1.000, 1, 0, 29000.00, 19000.00, 1, 1
UNION ALL SELECT 'MU0213', 'PCS', 1.000, 1, 0, 26000.00, 19180.00, 1, 1
UNION ALL SELECT 'MU0214', 'PCS', 1.000, 1, 0, 5000.00, 3030.00, 1, 1
UNION ALL SELECT 'MU0215', 'PCS', 1.000, 1, 0, 18000.00, 11000.00, 1, 1
UNION ALL SELECT 'MU0216', 'PCS', 1.000, 1, 0, 37000.00, 24600.00, 1, 1
UNION ALL SELECT 'MU0217', 'PCS', 1.000, 1, 0, 20000.00, 13060.00, 1, 1
UNION ALL SELECT 'MU0218', 'PCS', 1.000, 1, 0, 12000.00, 8350.00, 1, 1
UNION ALL SELECT 'MU0219', 'PCS', 1.000, 1, 0, 16000.00, 10570.00, 1, 1
UNION ALL SELECT 'MU0220', 'PCS', 1.000, 1, 0, 14000.00, 9240.00, 1, 1
UNION ALL SELECT 'MU0221', 'PCS', 1.000, 1, 0, 130000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU0222', 'PCS', 1.000, 1, 0, 49000.00, 32410.00, 1, 1
UNION ALL SELECT 'MU0223', 'PCS', 1.000, 1, 0, 12000.00, 7640.00, 1, 1
UNION ALL SELECT 'MU0224', 'PCS', 1.000, 1, 0, 124000.00, 91710.00, 1, 1
UNION ALL SELECT 'MU0225', 'PCS', 1.000, 1, 0, 15000.00, 11000.00, 1, 1
UNION ALL SELECT 'MU0226', 'DUS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0227', 'DUS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0228', 'PCS', 1.000, 1, 0, 12000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0229', 'PCS', 1.000, 1, 0, 6000.00, 3308.00, 1, 1
UNION ALL SELECT 'MU0230', 'PCS', 1.000, 1, 0, 5000.00, 2355.00, 1, 1
UNION ALL SELECT 'MU0231', 'PCS', 1.000, 1, 0, 15000.00, 10780.00, 1, 1
UNION ALL SELECT 'MU0232', 'PCS', 1.000, 1, 0, 63000.00, 42000.00, 1, 1
UNION ALL SELECT 'MU0233', 'PCS', 1.000, 1, 0, 5000.00, 1630.00, 1, 1
UNION ALL SELECT 'MU0234', 'DUS', 1.000, 1, 0, 44000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU0235', 'PCS', 1.000, 1, 0, 13000.00, 8300.00, 1, 1
UNION ALL SELECT 'MU0236', 'PCS', 1.000, 1, 0, 25000.00, 18750.00, 1, 1
UNION ALL SELECT 'MU0237', 'UNIT', 1.000, 1, 0, 5693000.00, 3400000.00, 1, 1
UNION ALL SELECT 'MU0238', 'PCS', 1.000, 1, 0, 81000.00, 45000.00, 1, 1
UNION ALL SELECT 'MU0239', 'BTG', 1.000, 1, 0, 285000.00, 197600.00, 1, 1
UNION ALL SELECT 'MU0240', 'PCS', 1.000, 1, 0, 80000.00, 56000.00, 1, 1
UNION ALL SELECT 'MU0241', 'BTG', 1.000, 1, 0, 240000.00, 184800.00, 1, 1
UNION ALL SELECT 'MU0242', 'BTG', 1.000, 1, 0, 200000.00, 155000.00, 1, 1
UNION ALL SELECT 'MU0243', 'PCS', 1.000, 1, 0, 8000.00, 3500.00, 1, 1
UNION ALL SELECT 'MU0244', 'BTG', 1.000, 1, 0, 186000.00, 132000.00, 1, 1
UNION ALL SELECT 'MU0245', 'UNIT', 1.000, 1, 0, 1869000.00, 1015100.00, 1, 1
UNION ALL SELECT 'MU0246', 'BTG', 1.000, 1, 0, 339000.00, 310000.00, 1, 1
UNION ALL SELECT 'MU0247', 'PCS', 1.000, 1, 0, 30000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU0248', 'DUS', 1.000, 1, 0, 30000.00, 19500.00, 1, 1
UNION ALL SELECT 'MU0249', 'MTR', 1.000, 1, 0, 20000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU0250', 'BTG', 1.000, 1, 0, 641000.00, 461000.00, 1, 1
UNION ALL SELECT 'MU0251', 'BTG', 1.000, 1, 0, 329000.00, 234600.00, 1, 1
UNION ALL SELECT 'MU0252', 'BTG', 1.000, 1, 0, 209000.00, 148750.00, 1, 1
UNION ALL SELECT 'MU0253', 'PCS', 1.000, 1, 0, 113000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU0254', 'BTL', 1.000, 1, 0, 49000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU0255', 'BTL', 1.000, 1, 0, 50000.00, 36800.00, 1, 1
UNION ALL SELECT 'MU0256', 'PAIL', 1.000, 1, 0, 286000.00, 225200.00, 1, 1
UNION ALL SELECT 'MU0257', 'PAIL', 1.000, 1, 0, 286000.00, 225200.00, 1, 1
UNION ALL SELECT 'MU0258', 'PAIL', 1.000, 1, 0, 286000.00, 225200.00, 1, 1
UNION ALL SELECT 'MU0259', 'PAIL', 1.000, 1, 0, 286000.00, 225200.00, 1, 1
UNION ALL SELECT 'MU0260', 'PAIL', 1.000, 1, 0, 286000.00, 225200.00, 1, 1
UNION ALL SELECT 'MU0261', 'KG', 1.000, 1, 0, 50000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU0262', 'PAIL', 1.000, 1, 0, 286000.00, 225200.00, 1, 1
UNION ALL SELECT 'MU0263', 'KG', 1.000, 1, 0, 50000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU0264', 'PAIL', 1.000, 1, 0, 433000.00, 357700.00, 1, 1
UNION ALL SELECT 'MU0265', 'DUS', 1.000, 1, 0, 77000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU0266', 'PAIL', 1.000, 1, 0, 433000.00, 357700.00, 1, 1
UNION ALL SELECT 'MU0267', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU0268', 'POT', 1.000, 1, 0, 77000.00, 60400.00, 1, 1
UNION ALL SELECT 'MU0269', 'POT', 1.000, 1, 0, 77000.00, 60400.00, 1, 1
UNION ALL SELECT 'MU0270', 'POT', 1.000, 1, 0, 77000.00, 60400.00, 1, 1
UNION ALL SELECT 'MU0271', 'POT', 1.000, 1, 0, 77000.00, 60400.00, 1, 1
UNION ALL SELECT 'MU0272', 'POT', 1.000, 1, 0, 77000.00, 60400.00, 1, 1
UNION ALL SELECT 'MU0273', 'POT', 1.000, 1, 0, 77000.00, 60400.00, 1, 1
UNION ALL SELECT 'MU0274', 'POT', 1.000, 1, 0, 77000.00, 60400.00, 1, 1
UNION ALL SELECT 'MU0275', 'POT', 1.000, 1, 0, 77000.00, 60400.00, 1, 1
UNION ALL SELECT 'MU0276', 'PAIL', 1.000, 1, 0, 286000.00, 225200.00, 1, 1
UNION ALL SELECT 'MU0277', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU0278', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU0279', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU0280', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU0281', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU0282', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU0283', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU0284', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU0285', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU0286', 'PAIL', 1.000, 1, 0, 433000.00, 357700.00, 1, 1
UNION ALL SELECT 'MU0287', 'PAIL', 1.000, 1, 0, 433000.00, 357700.00, 1, 1
UNION ALL SELECT 'MU0288', 'PAIL', 1.000, 1, 0, 433000.00, 357700.00, 1, 1
UNION ALL SELECT 'MU0289', 'PAIL', 1.000, 1, 0, 433000.00, 357700.00, 1, 1
UNION ALL SELECT 'MU0290', 'KLG', 1.000, 1, 0, 103000.00, 86000.00, 1, 1
UNION ALL SELECT 'MU0291', 'KLG', 1.000, 1, 0, 64000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU0292', 'PCS', 1.000, 1, 0, 150000.00, 120000.00, 1, 1
UNION ALL SELECT 'MU0293', 'BTG', 1.000, 1, 0, 622000.00, 400000.00, 1, 1
UNION ALL SELECT 'MU0294', 'UNIT', 1.000, 1, 0, 635000.00, 488500.00, 1, 1
UNION ALL SELECT 'MU0295', 'PCS', 1.000, 1, 0, 1450000.00, 1069500.00, 1, 1
UNION ALL SELECT 'MU0296', 'PCS', 1.000, 1, 0, 7700000.00, 6160000.00, 1, 1
UNION ALL SELECT 'MU0297', 'PCS', 1.000, 1, 0, 51000.00, 40800.00, 1, 1
UNION ALL SELECT 'MU0298', 'PCS', 1.000, 1, 0, 1170000.00, 900000.00, 1, 1
UNION ALL SELECT 'MU0299', 'PCS', 1.000, 1, 0, 50000.00, 22500.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU0300' AS sku, 'BTG' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 162000.00 AS selling_price, 104000.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU0301', 'BTG', 1.000, 1, 0, 377000.00, 219200.00, 1, 1
UNION ALL SELECT 'MU0302', 'PCS', 1.000, 1, 0, 50000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU0303', 'PCS', 1.000, 1, 0, 36000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0304', 'PCS', 1.000, 1, 0, 95000.00, 58357.00, 1, 1
UNION ALL SELECT 'MU0305', 'UNIT', 1.000, 1, 0, 2750000.00, 2000000.00, 1, 1
UNION ALL SELECT 'MU0306', 'UNIT', 1.000, 1, 0, 743000.00, 540000.00, 1, 1
UNION ALL SELECT 'MU0307', 'PCS', 1.000, 1, 0, 32000.00, 22000.00, 1, 1
UNION ALL SELECT 'MU0308', 'PCS', 1.000, 1, 0, 32000.00, 22000.00, 1, 1
UNION ALL SELECT 'MU0309', 'PCS', 1.000, 1, 0, 33000.00, 23000.00, 1, 1
UNION ALL SELECT 'MU0310', 'PCS', 1.000, 1, 0, 33000.00, 23000.00, 1, 1
UNION ALL SELECT 'MU0311', 'PCS', 1.000, 1, 0, 35000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU0312', 'PCS', 1.000, 1, 0, 35000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU0313', 'PCS', 1.000, 1, 0, 38000.00, 27000.00, 1, 1
UNION ALL SELECT 'MU0314', 'PCS', 1.000, 1, 0, 38000.00, 27000.00, 1, 1
UNION ALL SELECT 'MU0315', 'PAK', 1.000, 1, 0, 39000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU0316', 'BTL', 1.000, 1, 0, 34000.00, 20900.00, 1, 1
UNION ALL SELECT 'MU0317', 'PCS', 1.000, 1, 0, 217000.00, 147060.00, 1, 1
UNION ALL SELECT 'MU0318', 'PCS', 1.000, 1, 0, 71000.00, 47780.00, 1, 1
UNION ALL SELECT 'MU0319', 'PCS', 1.000, 1, 0, 81000.00, 54560.00, 1, 1
UNION ALL SELECT 'MU0320', 'PCS', 1.000, 1, 0, 111000.00, 75400.00, 1, 1
UNION ALL SELECT 'MU0321', 'PCS', 1.000, 1, 0, 184000.00, 125120.00, 1, 1
UNION ALL SELECT 'MU0322', 'PCS', 1.000, 1, 0, 271000.00, 183920.00, 1, 1
UNION ALL SELECT 'MU0323', 'PCS', 1.000, 1, 0, 29000.00, 19720.00, 1, 1
UNION ALL SELECT 'MU0324', 'PCS', 1.000, 1, 0, 33000.00, 22040.00, 1, 1
UNION ALL SELECT 'MU0325', 'PCS', 1.000, 1, 0, 38000.00, 25540.00, 1, 1
UNION ALL SELECT 'MU0326', 'PCS', 1.000, 1, 0, 54000.00, 36500.00, 1, 1
UNION ALL SELECT 'MU0327', 'PCS', 1.000, 1, 0, 22000.00, 17600.00, 1, 1
UNION ALL SELECT 'MU0328', 'PCS', 1.000, 1, 0, 30000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU0329', 'PCS', 1.000, 1, 0, 50000.00, 35700.00, 1, 1
UNION ALL SELECT 'MU0330', 'BKS', 1.000, 1, 0, 16000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU0331', 'UNIT', 1.000, 1, 0, 2200000.00, 1210000.00, 1, 1
UNION ALL SELECT 'MU0332', 'UNIT', 1.000, 1, 0, 1700000.00, 935000.00, 1, 1
UNION ALL SELECT 'MU0333', 'BKS', 1.000, 1, 0, 9000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0334', 'BKS', 1.000, 1, 0, 9000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0335', 'BKS', 1.000, 1, 0, 9000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0336', 'BKS', 1.000, 1, 0, 9000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0337', 'BKS', 1.000, 1, 0, 13000.00, 8750.00, 1, 1
UNION ALL SELECT 'MU0338', 'SET', 1.000, 1, 0, 210000.00, 144000.00, 1, 1
UNION ALL SELECT 'MU0339', 'BTG', 1.000, 1, 0, 41000.00, 23000.00, 1, 1
UNION ALL SELECT 'MU0340', 'PCS', 1.000, 1, 0, 110000.00, 70000.00, 1, 1
UNION ALL SELECT 'MU0341', 'PCS', 1.000, 1, 0, 69000.00, 55200.00, 1, 1
UNION ALL SELECT 'MU0342', 'PCS', 1.000, 1, 0, 110000.00, 84000.00, 1, 1
UNION ALL SELECT 'MU0343', 'BTG', 1.000, 1, 0, 391000.00, 293600.00, 1, 1
UNION ALL SELECT 'MU0344', 'PCS', 1.000, 1, 0, 50000.00, 27500.00, 1, 1
UNION ALL SELECT 'MU0345', 'PCS', 1.000, 1, 0, 3000.00, 1264.00, 1, 1
UNION ALL SELECT 'MU0346', 'PCS', 1.000, 1, 0, 120000.00, 125000.00, 1, 1
UNION ALL SELECT 'MU0347', 'PCS', 1.000, 1, 0, 150000.00, 150000.00, 1, 1
UNION ALL SELECT 'MU0348', 'PCS', 1.000, 1, 0, 185000.00, 180000.00, 1, 1
UNION ALL SELECT 'MU0349', 'PCS', 1.000, 1, 0, 15000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU0350', 'PCS', 1.000, 1, 0, 15000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU0351', 'PCS', 1.000, 1, 0, 15000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU0352', 'PCS', 1.000, 1, 0, 15000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU0353', 'PCS', 1.000, 1, 0, 15000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU0354', 'PCS', 1.000, 1, 0, 16000.00, 10300.00, 1, 1
UNION ALL SELECT 'MU0355', 'PCS', 1.000, 1, 0, 83000.00, 55250.00, 1, 1
UNION ALL SELECT 'MU0356', 'PCS', 1.000, 1, 0, 122000.00, 97600.00, 1, 1
UNION ALL SELECT 'MU0357', 'PCS', 1.000, 1, 0, 45000.00, 45000.00, 1, 1
UNION ALL SELECT 'MU0358', 'PCS', 1.000, 1, 0, 20000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU0359', 'PCS', 1.000, 1, 0, 50000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU0360', 'PCS', 1.000, 1, 0, 150000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU0361', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0362', 'PCS', 1.000, 1, 0, 120000.00, 55000.00, 1, 1
UNION ALL SELECT 'MU0363', 'PCS', 1.000, 1, 0, 205000.00, 164000.00, 1, 1
UNION ALL SELECT 'MU0364', 'PCS', 1.000, 1, 0, 30000.00, 17100.00, 1, 1
UNION ALL SELECT 'MU0365', 'PCS', 1.000, 1, 0, 20000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0366', 'LBR', 1.000, 1, 0, 698000.00, 536160.00, 1, 1
UNION ALL SELECT 'MU0367', 'PCS', 1.000, 1, 0, 22000.00, 12100.00, 1, 1
UNION ALL SELECT 'MU0368', 'PCS', 1.000, 1, 0, 195000.00, 125000.00, 1, 1
UNION ALL SELECT 'MU0369', 'PCS', 1.000, 1, 0, 210000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU0370', 'PCS', 1.000, 1, 0, 64000.00, 42500.00, 1, 1
UNION ALL SELECT 'MU0371', 'DUS', 1.000, 1, 0, 98000.00, 69940.00, 1, 1
UNION ALL SELECT 'MU0372', 'PCS', 1.000, 1, 0, 19000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0373', 'PCS', 1.000, 1, 0, 21000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0374', 'PCS', 1.000, 1, 0, 40000.00, 21600.00, 1, 1
UNION ALL SELECT 'MU0375', 'PCS', 1.000, 1, 0, 58000.00, 38500.00, 1, 1
UNION ALL SELECT 'MU0376', 'PCS', 1.000, 1, 0, 36000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0377', 'PCS', 1.000, 1, 0, 98000.00, 65000.00, 1, 1
UNION ALL SELECT 'MU0378', 'PCS', 1.000, 1, 0, 35000.00, 23750.00, 1, 1
UNION ALL SELECT 'MU0379', 'PCS', 1.000, 1, 0, 45000.00, 28750.00, 1, 1
UNION ALL SELECT 'MU0380', 'PCS', 1.000, 1, 0, 15000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU0381', 'PCS', 1.000, 1, 0, 35000.00, 19166.00, 1, 1
UNION ALL SELECT 'MU0382', 'PCS', 1.000, 1, 0, 185000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU0383', 'DUS', 1.000, 1, 0, 98000.00, 71394.00, 1, 1
UNION ALL SELECT 'MU0384', 'PCS', 1.000, 1, 0, 20000.00, 8500.00, 1, 1
UNION ALL SELECT 'MU0385', 'GLN', 1.000, 1, 0, 112000.00, 101000.00, 1, 1
UNION ALL SELECT 'MU0386', 'PCS', 1.000, 1, 0, 42000.00, 26250.00, 1, 1
UNION ALL SELECT 'MU0387', 'PCS', 1.000, 1, 0, 58000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU0388', 'PCS', 1.000, 1, 0, 8000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU0389', 'PCS', 1.000, 1, 0, 6000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU0390', 'PCS', 1.000, 1, 0, 4000.00, 3200.00, 1, 1
UNION ALL SELECT 'MU0391', 'PCS', 1.000, 1, 0, 7000.00, 5600.00, 1, 1
UNION ALL SELECT 'MU0392', 'PCS', 1.000, 1, 0, 60000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU0393', 'LBR', 1.000, 1, 0, 263000.00, 201748.00, 1, 1
UNION ALL SELECT 'MU0394', 'PCS', 1.000, 1, 0, 41000.00, 27000.00, 1, 1
UNION ALL SELECT 'MU0395', 'PCS', 1.000, 1, 0, 56000.00, 44800.00, 1, 1
UNION ALL SELECT 'MU0396', 'PCS', 1.000, 1, 0, 68000.00, 54400.00, 1, 1
UNION ALL SELECT 'MU0397', 'PCS', 1.000, 1, 0, 75000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU0398', 'PCS', 1.000, 1, 0, 35000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU0399', 'PCS', 1.000, 1, 0, 34000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU0400', 'PCS', 1.000, 1, 0, 40000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU0401', 'PCS', 1.000, 1, 0, 40000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0402', 'UNIT', 1.000, 1, 0, 196000.00, 145000.00, 1, 1
UNION ALL SELECT 'MU0403', 'PCS', 1.000, 1, 0, 15000.00, 11500.00, 1, 1
UNION ALL SELECT 'MU0404', 'PCS', 1.000, 1, 0, 15000.00, 11500.00, 1, 1
UNION ALL SELECT 'MU0405', 'PCS', 1.000, 1, 0, 22000.00, 11000.00, 1, 1
UNION ALL SELECT 'MU0406', 'PCS', 1.000, 1, 0, 37000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU0407', 'PCS', 1.000, 1, 0, 11000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0408', 'PCS', 1.000, 1, 0, 52000.00, 41600.00, 1, 1
UNION ALL SELECT 'MU0409', 'PCS', 1.000, 1, 0, 6000.00, 3500.00, 1, 1
UNION ALL SELECT 'MU0410', 'PCS', 1.000, 1, 0, 5000.00, 1791.00, 1, 1
UNION ALL SELECT 'MU0411', 'PCS', 1.000, 1, 0, 6000.00, 1958.00, 1, 1
UNION ALL SELECT 'MU0412', 'PCS', 1.000, 1, 0, 9000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU0414', 'MTR', 1.000, 1, 0, 85000.00, 55000.00, 1, 1
UNION ALL SELECT 'MU0415', 'MTR', 1.000, 1, 0, 165000.00, 110000.00, 1, 1
UNION ALL SELECT 'MU0416', 'SET', 1.000, 1, 0, 855000.00, 807500.00, 1, 1
UNION ALL SELECT 'MU0417', 'SET', 1.000, 1, 0, 1247000.00, 1187500.00, 1, 1
UNION ALL SELECT 'MU0418', 'PCS', 1.000, 1, 0, 27000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU0419', 'PCS', 1.000, 1, 0, 30000.00, 21000.00, 1, 1
UNION ALL SELECT 'MU0420', 'PCS', 1.000, 1, 0, 3000.00, 2000.00, 1, 1
UNION ALL SELECT 'MU0421', 'SET', 1.000, 1, 0, 77000.00, 61600.00, 1, 1
UNION ALL SELECT 'MU0422', 'PCS', 1.000, 1, 0, 19000.00, 15200.00, 1, 1
UNION ALL SELECT 'MU0423', 'PCS', 1.000, 1, 0, 27000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0424', 'PCS', 1.000, 1, 0, 23000.00, 18400.00, 1, 1
UNION ALL SELECT 'MU0425', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0426', 'PCS', 1.000, 1, 0, 22000.00, 19000.00, 1, 1
UNION ALL SELECT 'MU0427', 'PCS', 1.000, 1, 0, 17000.00, 13600.00, 1, 1
UNION ALL SELECT 'MU0428', 'PCS', 1.000, 1, 0, 17000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU0429', 'PCS', 1.000, 1, 0, 15000.00, 7083.00, 1, 1
UNION ALL SELECT 'MU0430', 'PCS', 1.000, 1, 0, 16000.00, 7916.00, 1, 1
UNION ALL SELECT 'MU0431', 'PCS', 1.000, 1, 0, 25000.00, 19000.00, 1, 1
UNION ALL SELECT 'MU0432', 'PCS', 1.000, 1, 0, 75000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0433', 'PCS', 1.000, 1, 0, 22000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0434', 'PCS', 1.000, 1, 0, 30000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU0435', 'PCS', 1.000, 1, 0, 37000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU0436', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0437', 'PCS', 1.000, 1, 0, 150000.00, 91000.00, 1, 1
UNION ALL SELECT 'MU0438', 'PCS', 1.000, 1, 0, 117000.00, 72875.00, 1, 1
UNION ALL SELECT 'MU0439', 'PCS', 1.000, 1, 0, 117000.00, 72875.00, 1, 1
UNION ALL SELECT 'MU0440', 'PCS', 1.000, 1, 0, 25000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0441', 'PCS', 1.000, 1, 0, 25000.00, 13000.00, 1, 1
UNION ALL SELECT 'MU0442', 'PCS', 1.000, 1, 0, 13000.00, 11000.00, 1, 1
UNION ALL SELECT 'MU0443', 'MTR', 1.000, 1, 0, 300000.00, 168000.00, 1, 1
UNION ALL SELECT 'MU0444', '50 METER', 1.000, 1, 0, 1800000.00, 1138000.00, 1, 1
UNION ALL SELECT 'MU0445', '50 METER', 1.000, 1, 0, 1200000.00, 748000.00, 1, 1
UNION ALL SELECT 'MU0446', 'PCS', 1.000, 1, 0, 15000.00, 6600.00, 1, 1
UNION ALL SELECT 'MU0447', '50 METER', 1.000, 1, 0, 355000.00, 284000.00, 1, 1
UNION ALL SELECT 'MU0448', '50 METER', 1.000, 1, 0, 355000.00, 284000.00, 1, 1
UNION ALL SELECT 'MU0449', '50 METER', 1.000, 1, 0, 565000.00, 452000.00, 1, 1
UNION ALL SELECT 'MU0450', '50 METER', 1.000, 1, 0, 565000.00, 452000.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU0451' AS sku, 'PCS' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 33000.00 AS selling_price, 23000.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU0452', 'PCS', 1.000, 1, 0, 46000.00, 36800.00, 1, 1
UNION ALL SELECT 'MU0453', 'PCS', 1.000, 1, 0, 36000.00, 27200.00, 1, 1
UNION ALL SELECT 'MU0454', '50 METER', 1.000, 1, 0, 1962000.00, 1569000.00, 1, 1
UNION ALL SELECT 'MU0455', 'PCS', 1.000, 1, 0, 24000.00, 16750.00, 1, 1
UNION ALL SELECT 'MU0456', 'PCS', 1.000, 1, 0, 5000.00, 2480.00, 1, 1
UNION ALL SELECT 'MU0457', 'PCS', 1.000, 1, 0, 17000.00, 13600.00, 1, 1
UNION ALL SELECT 'MU0458', 'BTG', 1.000, 1, 0, 210000.00, 134600.00, 1, 1
UNION ALL SELECT 'MU0459', 'BTG', 1.000, 1, 0, 398000.00, 281600.00, 1, 1
UNION ALL SELECT 'MU0460', 'PCS', 1.000, 1, 0, 63000.00, 48200.00, 1, 1
UNION ALL SELECT 'MU0461', 'PCS', 1.000, 1, 0, 15000.00, 9200.00, 1, 1
UNION ALL SELECT 'MU0462', 'PCS', 1.000, 1, 0, 18000.00, 11675.00, 1, 1
UNION ALL SELECT 'MU0463', 'PCS', 1.000, 1, 0, 19000.00, 14500.00, 1, 1
UNION ALL SELECT 'MU0464', 'PCS', 1.000, 1, 0, 67000.00, 53600.00, 1, 1
UNION ALL SELECT 'MU0465', 'PCS', 1.000, 1, 0, 198000.00, 133250.00, 1, 1
UNION ALL SELECT 'MU0466', 'PCS', 1.000, 1, 0, 86000.00, 68800.00, 1, 1
UNION ALL SELECT 'MU0467', 'PCS', 1.000, 1, 0, 88000.00, 70400.00, 1, 1
UNION ALL SELECT 'MU0468', 'PCS', 1.000, 1, 0, 215000.00, 172000.00, 1, 1
UNION ALL SELECT 'MU0469', 'PCS', 1.000, 1, 0, 196000.00, 156800.00, 1, 1
UNION ALL SELECT 'MU0470', 'PCS', 1.000, 1, 0, 61000.00, 46625.00, 1, 1
UNION ALL SELECT 'MU0471', 'PCS', 1.000, 1, 0, 93000.00, 71200.00, 1, 1
UNION ALL SELECT 'MU0472', 'BTG', 1.000, 1, 0, 178000.00, 125600.00, 1, 1
UNION ALL SELECT 'MU0473', 'BTG', 1.000, 1, 0, 57000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU0474', 'BTG', 1.000, 1, 0, 21000.00, 16800.00, 1, 1
UNION ALL SELECT 'MU0475', 'KG', 1.000, 1, 0, 50000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU0476', 'PCS', 1.000, 1, 0, 33000.00, 24900.00, 1, 1
UNION ALL SELECT 'MU0477', 'PCS', 1.000, 1, 0, 68000.00, 52250.00, 1, 1
UNION ALL SELECT 'MU0478', 'PCS', 1.000, 1, 0, 30000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU0479', 'PCS', 1.000, 1, 0, 25000.00, 19375.00, 1, 1
UNION ALL SELECT 'MU0480', 'PCS', 1.000, 1, 0, 53000.00, 40650.00, 1, 1
UNION ALL SELECT 'MU0481', 'PCS', 1.000, 1, 0, 45000.00, 41900.00, 1, 1
UNION ALL SELECT 'MU0482', 'PCS', 1.000, 1, 0, 30000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0483', 'PCS', 1.000, 1, 0, 500000.00, 400000.00, 1, 1
UNION ALL SELECT 'MU0484', 'PCS', 1.000, 1, 0, 58000.00, 46400.00, 1, 1
UNION ALL SELECT 'MU0485', 'PCS', 1.000, 1, 0, 58000.00, 46400.00, 1, 1
UNION ALL SELECT 'MU0486', 'PCS', 1.000, 1, 0, 53000.00, 42400.00, 1, 1
UNION ALL SELECT 'MU0487', 'PCS', 1.000, 1, 0, 173000.00, 138400.00, 1, 1
UNION ALL SELECT 'MU0488', 'PCS', 1.000, 1, 0, 100000.00, 80000.00, 1, 1
UNION ALL SELECT 'MU0489', 'SET', 1.000, 1, 0, 23000.00, 17000.00, 1, 1
UNION ALL SELECT 'MU0490', 'PCS', 1.000, 1, 0, 136000.00, 108800.00, 1, 1
UNION ALL SELECT 'MU0491', 'SET', 1.000, 1, 0, 27000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0492', 'PCS', 1.000, 1, 0, 10000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU0493', 'DUS', 1.000, 1, 0, 190000.00, 100000.00, 1, 1
UNION ALL SELECT 'MU0494', 'SET', 1.000, 1, 0, 12000.00, 5833.00, 1, 1
UNION ALL SELECT 'MU0495', 'PCS', 1.000, 1, 0, 105000.00, 70250.00, 1, 1
UNION ALL SELECT 'MU0496', 'PCS', 1.000, 1, 0, 78000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU0497', 'PCS', 1.000, 1, 0, 132000.00, 101300.00, 1, 1
UNION ALL SELECT 'MU0498', 'BTG', 1.000, 1, 0, 57000.00, 45000.00, 1, 1
UNION ALL SELECT 'MU0499', 'PCS', 1.000, 1, 0, 126000.00, 99500.00, 1, 1
UNION ALL SELECT 'MU0500', 'PCS', 1.000, 1, 0, 36000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU0501', 'PCS', 1.000, 1, 0, 33000.00, 26400.00, 1, 1
UNION ALL SELECT 'MU0502', 'PCS', 1.000, 1, 0, 35000.00, 26600.00, 1, 1
UNION ALL SELECT 'MU0503', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0504', 'PCS', 1.000, 1, 0, 147000.00, 113300.00, 1, 1
UNION ALL SELECT 'MU0505', 'PCS', 1.000, 1, 0, 14000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU0506', 'PCS', 1.000, 1, 0, 36000.00, 23900.00, 1, 1
UNION ALL SELECT 'MU0507', 'LBR', 1.000, 1, 0, 7000.00, 4800.00, 1, 1
UNION ALL SELECT 'MU0508', 'DUS', 1.000, 1, 0, 97000.00, 68400.00, 1, 1
UNION ALL SELECT 'MU0509', 'SET', 1.000, 1, 0, 140000.00, 97000.00, 1, 1
UNION ALL SELECT 'MU0510', 'PCS', 1.000, 1, 0, 18000.00, 11500.00, 1, 1
UNION ALL SELECT 'MU0511', 'DUS', 1.000, 1, 0, 102000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU0512', 'PCS', 1.000, 1, 0, 85000.00, 68000.00, 1, 1
UNION ALL SELECT 'MU0513', 'PCS', 1.000, 1, 0, 37000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU0514', 'PCS', 1.000, 1, 0, 3000.00, 2500.00, 1, 1
UNION ALL SELECT 'MU0515', 'LBR', 1.000, 1, 0, 452000.00, 347170.00, 1, 1
UNION ALL SELECT 'MU0516', 'LBR', 1.000, 1, 0, 7000.00, 2522.00, 1, 1
UNION ALL SELECT 'MU0517', 'LBR', 1.000, 1, 0, 95000.00, 72653.00, 1, 1
UNION ALL SELECT 'MU0518', 'LBR', 1.000, 1, 0, 160000.00, 120080.00, 1, 1
UNION ALL SELECT 'MU0519', 'LBR', 1.000, 1, 0, 192000.00, 142280.00, 1, 1
UNION ALL SELECT 'MU0520', 'PCS', 1.000, 1, 0, 116000.00, 84000.00, 1, 1
UNION ALL SELECT 'MU0521', 'BTG', 1.000, 1, 0, 47000.00, 29600.00, 1, 1
UNION ALL SELECT 'MU0522', 'BTG', 1.000, 1, 0, 35000.00, 24800.00, 1, 1
UNION ALL SELECT 'MU0523', 'PCS', 1.000, 1, 0, 11000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU0524', 'PCS', 1.000, 1, 0, 4000.00, 1792.00, 1, 1
UNION ALL SELECT 'MU0525', 'PCS', 1.000, 1, 0, 3380000.00, 2600000.00, 1, 1
UNION ALL SELECT 'MU0526', 'PCS', 1.000, 1, 0, 48000.00, 38400.00, 1, 1
UNION ALL SELECT 'MU0527', 'BTG', 1.000, 1, 0, 56000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU0528', 'BTG', 1.000, 1, 0, 85000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU0529', 'SET', 1.000, 1, 0, 441000.00, 352800.00, 1, 1
UNION ALL SELECT 'MU0530', 'PCS', 1.000, 1, 0, 35000.00, 23000.00, 1, 1
UNION ALL SELECT 'MU0531', 'PCS', 1.000, 1, 0, 100000.00, 100000.00, 1, 1
UNION ALL SELECT 'MU0532', 'PCS', 1.000, 1, 0, 19000.00, 15200.00, 1, 1
UNION ALL SELECT 'MU0533', 'PCS', 1.000, 1, 0, 15000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0534', 'PCS', 1.000, 1, 0, 30000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0535', 'PCS', 1.000, 1, 0, 45000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU0536', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0537', 'UNIT', 1.000, 1, 0, 1300000.00, 1200000.00, 1, 1
UNION ALL SELECT 'MU0538', 'PCS', 1.000, 1, 0, 70000.00, 46500.00, 1, 1
UNION ALL SELECT 'MU0539', 'PCS', 1.000, 1, 0, 110000.00, 73000.00, 1, 1
UNION ALL SELECT 'MU0540', 'PCS', 1.000, 1, 0, 30000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU0541', 'SET', 1.000, 1, 0, 503000.00, 402400.00, 1, 1
UNION ALL SELECT 'MU0542', 'PCS', 1.000, 1, 0, 212000.00, 106000.00, 1, 1
UNION ALL SELECT 'MU0543', 'SET', 1.000, 1, 0, 322000.00, 224000.00, 1, 1
UNION ALL SELECT 'MU0544', 'SET', 1.000, 1, 0, 260000.00, 224000.00, 1, 1
UNION ALL SELECT 'MU0545', 'UNIT', 1.000, 1, 0, 345000.00, 255000.00, 1, 1
UNION ALL SELECT 'MU0546', 'UNIT', 1.000, 1, 0, 2304000.00, 1843200.00, 1, 1
UNION ALL SELECT 'MU0547', 'SET', 1.000, 1, 0, 322000.00, 224000.00, 1, 1
UNION ALL SELECT 'MU0548', 'SET', 1.000, 1, 0, 88000.00, 65000.00, 1, 1
UNION ALL SELECT 'MU0549', 'SET', 1.000, 1, 0, 179000.00, 155000.00, 1, 1
UNION ALL SELECT 'MU0550', 'PCS', 1.000, 1, 0, 32000.00, 25600.00, 1, 1
UNION ALL SELECT 'MU0551', 'PCS', 1.000, 1, 0, 58000.00, 44000.00, 1, 1
UNION ALL SELECT 'MU0552', 'PCS', 1.000, 1, 0, 37000.00, 29600.00, 1, 1
UNION ALL SELECT 'MU0553', 'PCS', 1.000, 1, 0, 43000.00, 34400.00, 1, 1
UNION ALL SELECT 'MU0554', 'UNIT', 1.000, 1, 0, 821000.00, 656300.00, 1, 1
UNION ALL SELECT 'MU0555', 'PCS', 1.000, 1, 0, 15000.00, 6666.00, 1, 1
UNION ALL SELECT 'MU0556', 'PCS', 1.000, 1, 0, 76000.00, 58680.00, 1, 1
UNION ALL SELECT 'MU0557', 'SET', 1.000, 1, 0, 115000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU0558', 'PCS', 1.000, 1, 0, 149000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0559', 'PCS', 1.000, 1, 0, 17000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0560', 'PCS', 1.000, 1, 0, 51000.00, 40800.00, 1, 1
UNION ALL SELECT 'MU0561', 'PAIL', 1.000, 1, 0, 286000.00, 225200.00, 1, 1
UNION ALL SELECT 'MU0562', 'SET', 1.000, 1, 0, 149000.00, 119200.00, 1, 1
UNION ALL SELECT 'MU0563', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU0564', 'PCS', 1.000, 1, 0, 16000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU0565', 'PCS', 1.000, 1, 0, 17000.00, 13600.00, 1, 1
UNION ALL SELECT 'MU0566', 'PCS', 1.000, 1, 0, 17000.00, 13600.00, 1, 1
UNION ALL SELECT 'MU0567', 'PCS', 1.000, 1, 0, 5000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU0568', 'PCS', 1.000, 1, 0, 5000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU0569', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0570', 'KLG', 1.000, 1, 0, 18000.00, 14400.00, 1, 1
UNION ALL SELECT 'MU0571', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU0572', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU0573', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU0574', 'PCS', 1.000, 1, 0, 751000.00, 600800.00, 1, 1
UNION ALL SELECT 'MU0575', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0576', 'SET', 1.000, 1, 0, 770000.00, 560000.00, 1, 1
UNION ALL SELECT 'MU0577', 'PCS', 1.000, 1, 0, 65000.00, 52000.00, 1, 1
UNION ALL SELECT 'MU0578', 'PCS', 1.000, 1, 0, 28000.00, 15200.00, 1, 1
UNION ALL SELECT 'MU0579', 'PCS', 1.000, 1, 0, 12000.00, 9600.00, 1, 1
UNION ALL SELECT 'MU0580', 'PCS', 1.000, 1, 0, 80000.00, 45000.00, 1, 1
UNION ALL SELECT 'MU0581', 'PCS', 1.000, 1, 0, 13000.00, 11000.00, 1, 1
UNION ALL SELECT 'MU0582', 'PCS', 1.000, 1, 0, 8000.00, 2500.00, 1, 1
UNION ALL SELECT 'MU0583', '100 METER', 1.000, 1, 0, 1300000.00, 760000.00, 1, 1
UNION ALL SELECT 'MU0584', 'UNIT', 1.000, 1, 0, 750000.00, 600000.00, 1, 1
UNION ALL SELECT 'MU0585', 'UNIT', 1.000, 1, 0, 240000.00, 192000.00, 1, 1
UNION ALL SELECT 'MU0586', 'UNIT', 1.000, 1, 0, 330000.00, 264000.00, 1, 1
UNION ALL SELECT 'MU0587', 'UNIT', 1.000, 1, 0, 450000.00, 360000.00, 1, 1
UNION ALL SELECT 'MU0588', 'UNIT', 1.000, 1, 0, 600000.00, 480000.00, 1, 1
UNION ALL SELECT 'MU0589', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0590', 'PSG', 1.000, 1, 0, 28000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU0591', 'UNIT', 1.000, 1, 0, 70000.00, 56000.00, 1, 1
UNION ALL SELECT 'MU0592', 'UNIT', 1.000, 1, 0, 55000.00, 44000.00, 1, 1
UNION ALL SELECT 'MU0593', 'BTG', 1.000, 1, 0, 35000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU0594', 'PCS', 1.000, 1, 0, 65000.00, 35750.00, 1, 1
UNION ALL SELECT 'MU0595', 'PCS', 1.000, 1, 0, 135000.00, 74250.00, 1, 1
UNION ALL SELECT 'MU0596', 'PCS', 1.000, 1, 0, 32000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU0597', 'BKS', 1.000, 1, 0, 16000.00, 10294.00, 1, 1
UNION ALL SELECT 'MU0598', 'PCS', 1.000, 1, 0, 120000.00, 105000.00, 1, 1
UNION ALL SELECT 'MU0599', 'PCS', 1.000, 1, 0, 190000.00, 170000.00, 1, 1
UNION ALL SELECT 'MU0600', 'PCS', 1.000, 1, 0, 180000.00, 144000.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU0601' AS sku, 'PCS' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 85000.00 AS selling_price, 68000.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU0602', 'PCS', 1.000, 1, 0, 40000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU0603', 'PCS', 1.000, 1, 0, 22000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU0604', 'PCS', 1.000, 1, 0, 27000.00, 22000.00, 1, 1
UNION ALL SELECT 'MU0605', 'PCS', 1.000, 1, 0, 30000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU0606', 'PCS', 1.000, 1, 0, 44000.00, 38000.00, 1, 1
UNION ALL SELECT 'MU0607', 'PCS', 1.000, 1, 0, 65000.00, 43200.00, 1, 1
UNION ALL SELECT 'MU0608', 'PCS', 1.000, 1, 0, 93000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU0609', 'SET', 1.000, 1, 0, 420000.00, 215000.00, 1, 1
UNION ALL SELECT 'MU0610', 'SET', 1.000, 1, 0, 858000.00, 429000.00, 1, 1
UNION ALL SELECT 'MU0611', 'PCS', 1.000, 1, 0, 83000.00, 41250.00, 1, 1
UNION ALL SELECT 'MU0612', 'PCS', 1.000, 1, 0, 220000.00, 93500.00, 1, 1
UNION ALL SELECT 'MU0613', 'SET', 1.000, 1, 0, 140000.00, 97000.00, 1, 1
UNION ALL SELECT 'MU0614', 'SET', 1.000, 1, 0, 130000.00, 71500.00, 1, 1
UNION ALL SELECT 'MU0615', 'PCS', 1.000, 1, 0, 37000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU0616', 'PCS', 1.000, 1, 0, 39000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU0617', 'SET', 1.000, 1, 0, 40000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU0618', 'PCS', 1.000, 1, 0, 275000.00, 184000.00, 1, 1
UNION ALL SELECT 'MU0619', 'PCS', 1.000, 1, 0, 70000.00, 38500.00, 1, 1
UNION ALL SELECT 'MU0620', 'PCS', 1.000, 1, 0, 75000.00, 41250.00, 1, 1
UNION ALL SELECT 'MU0621', 'PCS', 1.000, 1, 0, 85000.00, 68000.00, 1, 1
UNION ALL SELECT 'MU0622', 'PCS', 1.000, 1, 0, 15000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0623', 'PCS', 1.000, 1, 0, 18000.00, 11000.00, 1, 1
UNION ALL SELECT 'MU0624', 'PCS', 1.000, 1, 0, 28000.00, 13475.00, 1, 1
UNION ALL SELECT 'MU0625', 'PCS', 1.000, 1, 0, 70000.00, 56000.00, 1, 1
UNION ALL SELECT 'MU0626', 'PCS', 1.000, 1, 0, 75000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU0627', 'PCS', 1.000, 1, 0, 40000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU0628', 'PCS', 1.000, 1, 0, 60000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU0629', 'PCS', 1.000, 1, 0, 70000.00, 56000.00, 1, 1
UNION ALL SELECT 'MU0630', 'PCS', 1.000, 1, 0, 100000.00, 80000.00, 1, 1
UNION ALL SELECT 'MU0631', 'PCS', 1.000, 1, 0, 145000.00, 110000.00, 1, 1
UNION ALL SELECT 'MU0632', 'PCS', 1.000, 1, 0, 65000.00, 33000.00, 1, 1
UNION ALL SELECT 'MU0633', 'PCS', 1.000, 1, 0, 182000.00, 145000.00, 1, 1
UNION ALL SELECT 'MU0634', 'PCS', 1.000, 1, 0, 182000.00, 145000.00, 1, 1
UNION ALL SELECT 'MU0635', 'PCS', 1.000, 1, 0, 219000.00, 175000.00, 1, 1
UNION ALL SELECT 'MU0636', 'PCS', 1.000, 1, 0, 90000.00, 72000.00, 1, 1
UNION ALL SELECT 'MU0637', 'PCS', 1.000, 1, 0, 105000.00, 90000.00, 1, 1
UNION ALL SELECT 'MU0638', 'PCS', 1.000, 1, 0, 150000.00, 68750.00, 1, 1
UNION ALL SELECT 'MU0639', 'KLG', 1.000, 1, 0, 111000.00, 92000.00, 1, 1
UNION ALL SELECT 'MU0640', 'PCS', 1.000, 1, 0, 24000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0641', 'PCS', 1.000, 1, 0, 35000.00, 20500.00, 1, 1
UNION ALL SELECT 'MU0642', 'LBR', 1.000, 1, 0, 7000.00, 3850.00, 1, 1
UNION ALL SELECT 'MU0643', 'LBR', 1.000, 1, 0, 8000.00, 4800.00, 1, 1
UNION ALL SELECT 'MU0644', 'LBR', 1.000, 1, 0, 7000.00, 4800.00, 1, 1
UNION ALL SELECT 'MU0645', 'LBR', 1.000, 1, 0, 8000.00, 4800.00, 1, 1
UNION ALL SELECT 'MU0646', 'UNIT', 1.000, 1, 0, 708000.00, 472000.00, 1, 1
UNION ALL SELECT 'MU0647', 'PCS', 1.000, 1, 0, 805000.00, 644000.00, 1, 1
UNION ALL SELECT 'MU0648', 'MTR', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0649', 'MTR', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0650', 'MTR', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0651', 'GLN', 1.000, 1, 0, 186000.00, 162500.00, 1, 1
UNION ALL SELECT 'MU0652', 'PCS', 1.000, 1, 0, 397000.00, 317600.00, 1, 1
UNION ALL SELECT 'MU0653', 'PCS', 1.000, 1, 0, 7000.00, 5600.00, 1, 1
UNION ALL SELECT 'MU0654', 'UNIT', 1.000, 1, 0, 300000.00, 260000.00, 1, 1
UNION ALL SELECT 'MU0655', 'SET', 1.000, 1, 0, 225000.00, 150000.00, 1, 1
UNION ALL SELECT 'MU0656', 'PCS', 1.000, 1, 0, 4000.00, 3200.00, 1, 1
UNION ALL SELECT 'MU0657', 'PCS', 1.000, 1, 0, 6000.00, 4800.00, 1, 1
UNION ALL SELECT 'MU0658', 'KLG', 1.000, 1, 0, 28000.00, 18400.00, 1, 1
UNION ALL SELECT 'MU0659', 'PCS', 1.000, 1, 0, 25000.00, 10416.00, 1, 1
UNION ALL SELECT 'MU0660', 'KLG', 1.000, 1, 0, 28000.00, 18400.00, 1, 1
UNION ALL SELECT 'MU0661', 'PCS', 1.000, 1, 0, 6000.00, 3200.00, 1, 1
UNION ALL SELECT 'MU0662', 'PCS', 1.000, 1, 0, 82000.00, 60500.00, 1, 1
UNION ALL SELECT 'MU0663', 'KLG', 1.000, 1, 0, 28000.00, 18400.00, 1, 1
UNION ALL SELECT 'MU0664', 'KLG', 1.000, 1, 0, 28000.00, 18400.00, 1, 1
UNION ALL SELECT 'MU0665', 'KLG', 1.000, 1, 0, 28000.00, 18400.00, 1, 1
UNION ALL SELECT 'MU0666', 'KLG', 1.000, 1, 0, 28000.00, 18400.00, 1, 1
UNION ALL SELECT 'MU0667', 'PCS', 1.000, 1, 0, 75000.00, 70000.00, 1, 1
UNION ALL SELECT 'MU0668', 'PSG', 1.000, 1, 0, 28000.00, 14500.00, 1, 1
UNION ALL SELECT 'MU0669', 'PCS', 1.000, 1, 0, 5000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU0670', 'PCS', 1.000, 1, 0, 8000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU0671', 'PCS', 1.000, 1, 0, 50000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU0672', 'PCS', 1.000, 1, 0, 60000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU0673', 'PCS', 1.000, 1, 0, 19000.00, 13600.00, 1, 1
UNION ALL SELECT 'MU0674', 'PCS', 1.000, 1, 0, 22000.00, 16125.00, 1, 1
UNION ALL SELECT 'MU0675', 'PCS', 1.000, 1, 0, 24000.00, 17500.00, 1, 1
UNION ALL SELECT 'MU0676', 'PCS', 1.000, 1, 0, 27000.00, 18537.00, 1, 1
UNION ALL SELECT 'MU0682', 'PCS', 1.000, 1, 0, 22000.00, 11000.00, 1, 1
UNION ALL SELECT 'MU0683', 'PCS', 1.000, 1, 0, 24000.00, 12100.00, 1, 1
UNION ALL SELECT 'MU0684', 'KLG', 1.000, 1, 0, 64000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU0685', 'KLG', 1.000, 1, 0, 95000.00, 86000.00, 1, 1
UNION ALL SELECT 'MU0686', 'PCS', 1.000, 1, 0, 20000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU0687', 'PAK', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0688', 'PAK', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0689', 'PCS', 1.000, 1, 0, 214000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0690', 'PCS', 1.000, 1, 0, 250000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0691', 'PCS', 1.000, 1, 0, 16000.00, 12800.00, 1, 1
UNION ALL SELECT 'MU0692', 'PCS', 1.000, 1, 0, 29000.00, 23200.00, 1, 1
UNION ALL SELECT 'MU0693', 'PCS', 1.000, 1, 0, 45000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0694', 'SET', 1.000, 1, 0, 16000.00, 12800.00, 1, 1
UNION ALL SELECT 'MU0695', 'SET', 1.000, 1, 0, 27000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU0696', 'PCS', 1.000, 1, 0, 7000.00, 2125.00, 1, 1
UNION ALL SELECT 'MU0697', 'PCS', 1.000, 1, 0, 50000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU0698', 'PSG', 1.000, 1, 0, 75000.00, 49500.00, 1, 1
UNION ALL SELECT 'MU0699', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0700', 'SET', 1.000, 1, 0, 441000.00, 352800.00, 1, 1
UNION ALL SELECT 'MU0701', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0702', 'PCS', 1.000, 1, 0, 21000.00, 13750.00, 1, 1
UNION ALL SELECT 'MU0703', 'PCS', 1.000, 1, 0, 5000.00, 1981.00, 1, 1
UNION ALL SELECT 'MU0704', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0705', 'PCS', 1.000, 1, 0, 9000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU0706', 'PCS', 1.000, 1, 0, 14000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU0707', 'SAK', 1.000, 1, 0, 145000.00, 121000.00, 1, 1
UNION ALL SELECT 'MU0708', 'PCS', 1.000, 1, 0, 10000.00, 5740.00, 1, 1
UNION ALL SELECT 'MU0709', 'PCS', 1.000, 1, 0, 22000.00, 14166.00, 1, 1
UNION ALL SELECT 'MU0710', 'SET', 1.000, 1, 0, 140000.00, 97000.00, 1, 1
UNION ALL SELECT 'MU0711', 'PCS', 1.000, 1, 0, 3000.00, 1024.00, 1, 1
UNION ALL SELECT 'MU0712', 'PCS', 1.000, 1, 0, 21000.00, 16800.00, 1, 1
UNION ALL SELECT 'MU0713', 'UNIT', 1.000, 1, 0, 410000.00, 328000.00, 1, 1
UNION ALL SELECT 'MU0714', 'SET', 1.000, 1, 0, 925000.00, 685000.00, 1, 1
UNION ALL SELECT 'MU0715', 'PAK', 1.000, 1, 0, 105000.00, 49500.00, 1, 1
UNION ALL SELECT 'MU0716', 'PAK', 1.000, 1, 0, 76000.00, 36300.00, 1, 1
UNION ALL SELECT 'MU0717', 'PAK', 1.000, 1, 0, 55000.00, 27000.00, 1, 1
UNION ALL SELECT 'MU0718', 'PAK', 1.000, 1, 0, 46000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU0719', 'PAK', 1.000, 1, 0, 9000.00, 7200.00, 1, 1
UNION ALL SELECT 'MU0720', 'PAK', 1.000, 1, 0, 40000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU0721', 'SET', 1.000, 1, 0, 545000.00, 545000.00, 1, 1
UNION ALL SELECT 'MU0722', 'PCS', 1.000, 1, 0, 20000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU0723', 'PCS', 1.000, 1, 0, 13000.00, 8750.00, 1, 1
UNION ALL SELECT 'MU0724', 'PSG', 1.000, 1, 0, 7000.00, 3400.00, 1, 1
UNION ALL SELECT 'MU0725', 'PSG', 1.000, 1, 0, 9000.00, 4200.00, 1, 1
UNION ALL SELECT 'MU0726', 'PCS', 1.000, 1, 0, 55000.00, 42900.00, 1, 1
UNION ALL SELECT 'MU0727', 'UNIT', 1.000, 1, 0, 237000.00, 175000.00, 1, 1
UNION ALL SELECT 'MU0728', 'PCS', 1.000, 1, 0, 8000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU0729', 'UNIT', 1.000, 1, 0, 2200000.00, 1760000.00, 1, 1
UNION ALL SELECT 'MU0730', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0731', 'PCS', 1.000, 1, 0, 5000.00, 3000.00, 1, 1
UNION ALL SELECT 'MU0732', '3PCS', 1.000, 1, 0, 2000.00, 1600.00, 1, 1
UNION ALL SELECT 'MU0733', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0734', 'UNIT', 1.000, 1, 0, 1953000.00, 1420000.00, 1, 1
UNION ALL SELECT 'MU0735', 'PCS', 1.000, 1, 0, 20000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU0736', 'UNIT', 1.000, 1, 0, 176000.00, 130000.00, 1, 1
UNION ALL SELECT 'MU0737', 'PSG', 1.000, 1, 0, 13000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU0738', 'PCS', 1.000, 1, 0, 45000.00, 4500.00, 1, 1
UNION ALL SELECT 'MU0739', 'UNIT', 1.000, 1, 0, 1950000.00, 1800000.00, 1, 1
UNION ALL SELECT 'MU0740', 'PCS', 1.000, 1, 0, 60000.00, 53000.00, 1, 1
UNION ALL SELECT 'MU0741', 'PCS', 1.000, 1, 0, 25500.00, 18000.00, 1, 1
UNION ALL SELECT 'MU0742', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0743', 'SET', 1.000, 1, 0, 16000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU0744', 'KG', 1.000, 1, 0, 55000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU0745', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0746', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0747', 'PCS', 1.000, 1, 0, 48000.00, 32500.00, 1, 1
UNION ALL SELECT 'MU0748', 'PCS', 1.000, 1, 0, 43000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU0749', 'MTR', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0750', 'PCS', 1.000, 1, 0, 35000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0751', 'LBR', 1.000, 1, 0, 80000.00, 64000.00, 1, 1
UNION ALL SELECT 'MU0752', 'PCS', 1.000, 1, 0, 17000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU0753', 'PCS', 1.000, 1, 0, 25000.00, 11000.00, 1, 1
UNION ALL SELECT 'MU0754', 'PCS', 1.000, 1, 0, 20000.00, 10500.00, 1, 1
UNION ALL SELECT 'MU0755', 'PCS', 1.000, 1, 0, 17000.00, 13600.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU0756' AS sku, 'PCS' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 42000.00 AS selling_price, 30752.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU0757', 'PCS', 1.000, 1, 0, 46000.00, 33968.00, 1, 1
UNION ALL SELECT 'MU0758', 'PCS', 1.000, 1, 0, 53000.00, 39207.00, 1, 1
UNION ALL SELECT 'MU0759', 'PCS', 1.000, 1, 0, 61000.00, 45088.00, 1, 1
UNION ALL SELECT 'MU0760', 'MTR', 1.000, 1, 0, 25000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0761', 'PCS', 1.000, 1, 0, 53000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0762', 'PCS', 1.000, 1, 0, 3000.00, 1368.00, 1, 1
UNION ALL SELECT 'MU0763', 'PCS', 1.000, 1, 0, 39000.00, 31200.00, 1, 1
UNION ALL SELECT 'MU0764', 'PCS', 1.000, 1, 0, 106000.00, 81360.00, 1, 1
UNION ALL SELECT 'MU0765', 'PCS', 1.000, 1, 0, 14000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU0766', 'KLG', 1.000, 1, 0, 63000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU0767', 'BKS', 1.000, 1, 0, 27000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0768', 'PCS', 1.000, 1, 0, 50000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU0769', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0770', 'PSG', 1.000, 1, 0, 10000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU0771', 'SET', 1.000, 1, 0, 1850000.00, 1720000.00, 1, 1
UNION ALL SELECT 'MU0772', 'SET', 1.000, 1, 0, 1200000.00, 1040000.00, 1, 1
UNION ALL SELECT 'MU0773', 'SET', 1.000, 1, 0, 2535000.00, 2028000.00, 1, 1
UNION ALL SELECT 'MU0774', 'PCS', 1.000, 1, 0, 60000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU0775', 'LBR', 1.000, 1, 0, 8000.00, 4800.00, 1, 1
UNION ALL SELECT 'MU0776', 'PCS', 1.000, 1, 0, 30000.00, 14824.00, 1, 1
UNION ALL SELECT 'MU0777', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0778', 'PCS', 1.000, 1, 0, 3000.00, 2400.00, 1, 1
UNION ALL SELECT 'MU0779', 'PCS', 1.000, 1, 0, 33000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU0780', 'PCS', 1.000, 1, 0, 8000.00, 3750.00, 1, 1
UNION ALL SELECT 'MU0781', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0782', 'PCS', 1.000, 1, 0, 125000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0783', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0784', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU0785', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU0786', 'PSG', 1.000, 1, 0, 15000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU0787', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU0788', 'PCS', 1.000, 1, 0, 20000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0789', 'KLG', 1.000, 1, 0, 70000.00, 61600.00, 1, 1
UNION ALL SELECT 'MU0790', 'KLG', 1.000, 1, 0, 70000.00, 61600.00, 1, 1
UNION ALL SELECT 'MU0791', 'KLG', 1.000, 1, 0, 70000.00, 61600.00, 1, 1
UNION ALL SELECT 'MU0792', 'KLG', 1.000, 1, 0, 124000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU0793', 'PCS', 1.000, 1, 0, 19000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0794', 'PCS', 1.000, 1, 0, 19000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU0795', 'KLG', 1.000, 1, 0, 24000.00, 13000.00, 1, 1
UNION ALL SELECT 'MU0796', 'KLG', 1.000, 1, 0, 103000.00, 86000.00, 1, 1
UNION ALL SELECT 'MU0797', 'KLG', 1.000, 1, 0, 103000.00, 86000.00, 1, 1
UNION ALL SELECT 'MU0798', 'KLG', 1.000, 1, 0, 64000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU0799', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0800', 'PAIL', 1.000, 1, 0, 433000.00, 357700.00, 1, 1
UNION ALL SELECT 'MU0801', 'PAIL', 1.000, 1, 0, 433000.00, 357700.00, 1, 1
UNION ALL SELECT 'MU0802', 'LBR', 1.000, 1, 0, 7000.00, 4800.00, 1, 1
UNION ALL SELECT 'MU0803', 'PAIL', 1.000, 1, 0, 876000.00, 440000.00, 1, 1
UNION ALL SELECT 'MU0804', 'PAIL', 1.000, 1, 0, 876000.00, 440000.00, 1, 1
UNION ALL SELECT 'MU0805', 'PCS', 1.000, 1, 0, 34000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU0806', 'PSG', 1.000, 1, 0, 12000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU0807', 'PCS', 1.000, 1, 0, 65000.00, 52000.00, 1, 1
UNION ALL SELECT 'MU0808', 'PAIL', 1.000, 1, 0, 433000.00, 357700.00, 1, 1
UNION ALL SELECT 'MU0809', 'PCS', 1.000, 1, 0, 30000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0810', 'PCS', 1.000, 1, 0, 38000.00, 30400.00, 1, 1
UNION ALL SELECT 'MU0811', 'PCS', 1.000, 1, 0, 58000.00, 31900.00, 1, 1
UNION ALL SELECT 'MU0812', 'PCS', 1.000, 1, 0, 53000.00, 42400.00, 1, 1
UNION ALL SELECT 'MU0813', 'PCS', 1.000, 1, 0, 48000.00, 37000.00, 1, 1
UNION ALL SELECT 'MU0814', 'PCS', 1.000, 1, 0, 14000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU0815', 'PCS', 1.000, 1, 0, 88000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0816', 'PCS', 1.000, 1, 0, 88000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0817', 'PCS', 1.000, 1, 0, 88000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0818', 'SET', 1.000, 1, 0, 260000.00, 224000.00, 1, 1
UNION ALL SELECT 'MU0819', 'SET', 1.000, 1, 0, 100000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU0820', 'PCS', 1.000, 1, 0, 1000.00, 790.00, 1, 1
UNION ALL SELECT 'MU0821', 'SET', 1.000, 1, 0, 210000.00, 144000.00, 1, 1
UNION ALL SELECT 'MU0822', 'SET', 1.000, 1, 0, 120000.00, 96000.00, 1, 1
UNION ALL SELECT 'MU0823', 'PCS', 1.000, 1, 0, 20000.00, 14000.00, 1, 1
UNION ALL SELECT 'MU0824', 'PCS', 1.000, 1, 0, 61000.00, 48800.00, 1, 1
UNION ALL SELECT 'MU0825', 'PCS', 1.000, 1, 0, 81000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0826', 'PCS', 1.000, 1, 0, 2000.00, 1600.00, 1, 1
UNION ALL SELECT 'MU0827', 'PCS', 1.000, 1, 0, 30000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU0828', 'GLN', 1.000, 1, 0, 186000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU0829', 'GLN', 1.000, 1, 0, 186000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU0830', 'GLN', 1.000, 1, 0, 186000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU0831', 'GLN', 1.000, 1, 0, 186000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU0832', 'GLN', 1.000, 1, 0, 186000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU0833', 'GLN', 1.000, 1, 0, 186000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU0834', 'GLN', 1.000, 1, 0, 186000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU0835', 'GLN', 1.000, 1, 0, 186000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU0836', 'GLN', 1.000, 1, 0, 186000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU0837', 'PCS', 1.000, 1, 0, 630000.00, 350000.00, 1, 1
UNION ALL SELECT 'MU0838', 'GLN', 1.000, 1, 0, 186000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU0839', 'GLN', 1.000, 1, 0, 186000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU0840', 'GLN', 1.000, 1, 0, 186000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU0841', 'PCS', 1.000, 1, 0, 14000.00, 6666.00, 1, 1
UNION ALL SELECT 'MU0842', 'PCS', 1.000, 1, 0, 10000.00, 5600.00, 1, 1
UNION ALL SELECT 'MU0843', 'UNIT', 1.000, 1, 0, 729000.00, 405000.00, 1, 1
UNION ALL SELECT 'MU0844', 'UNIT', 1.000, 1, 0, 175000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0845', 'UNIT', 1.000, 1, 0, 210000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0846', 'PCS', 1.000, 1, 0, 18000.00, 14400.00, 1, 1
UNION ALL SELECT 'MU0847', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU0848', 'LBR', 1.000, 1, 0, 7000.00, 4800.00, 1, 1
UNION ALL SELECT 'MU0849', 'PCS', 1.000, 1, 0, 15000.00, 1875.00, 1, 1
UNION ALL SELECT 'MU0850', 'PCS', 1.000, 1, 0, 23000.00, 16437.00, 1, 1
UNION ALL SELECT 'MU0851', 'MTR', 1.000, 1, 0, 20000.00, 14672.00, 1, 1
UNION ALL SELECT 'MU0852', '100 METER', 1.000, 1, 0, 342000.00, 228000.00, 1, 1
UNION ALL SELECT 'MU0853', 'MTR', 1.000, 1, 0, 3000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0854', 'MTR', 1.000, 1, 0, 28000.00, 21247.00, 1, 1
UNION ALL SELECT 'MU0855', '50 METER', 1.000, 1, 0, 1287000.00, 1029000.00, 1, 1
UNION ALL SELECT 'MU0856', 'PCS', 1.000, 1, 0, 145000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0857', 'PCS', 1.000, 1, 0, 58000.00, 38500.00, 1, 1
UNION ALL SELECT 'MU0858', 'KLG', 1.000, 1, 0, 144000.00, 83914.00, 1, 1
UNION ALL SELECT 'MU0859', 'PCS', 1.000, 1, 0, 403000.00, 280000.00, 1, 1
UNION ALL SELECT 'MU0860', 'PCS', 1.000, 1, 0, 78000.00, 60320.00, 1, 1
UNION ALL SELECT 'MU0861', 'PCS', 1.000, 1, 0, 288000.00, 200000.00, 1, 1
UNION ALL SELECT 'MU0862', 'PCS', 1.000, 1, 0, 885000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0863', 'UNIT', 1.000, 1, 0, 732000.00, 487500.00, 1, 1
UNION ALL SELECT 'MU0864', 'PCS', 1.000, 1, 0, 1015000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0865', 'PCS', 1.000, 1, 0, 2740000.00, 2192000.00, 1, 1
UNION ALL SELECT 'MU0866', 'PCS', 1.000, 1, 0, 2750000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0867', 'PCS', 1.000, 1, 0, 1044000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0868', 'UNIT', 1.000, 1, 0, 3850000.00, 2800000.00, 1, 1
UNION ALL SELECT 'MU0869', 'UNIT', 1.000, 1, 0, 2133000.00, 1551200.00, 1, 1
UNION ALL SELECT 'MU0870', 'UNIT', 1.000, 1, 0, 1650000.00, 1200000.00, 1, 1
UNION ALL SELECT 'MU0871', 'UNIT', 1.000, 1, 0, 8625000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0872', 'PCS', 1.000, 1, 0, 30000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0873', 'PCS', 1.000, 1, 0, 70000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0874', 'UNIT', 1.000, 1, 0, 2485000.00, 2000000.00, 1, 1
UNION ALL SELECT 'MU0875', 'PCS', 1.000, 1, 0, 4710000.00, 3140000.00, 1, 1
UNION ALL SELECT 'MU0876', 'PCS', 1.000, 1, 0, 19000.00, 10450.00, 1, 1
UNION ALL SELECT 'MU0877', 'PCS', 1.000, 1, 0, 25000.00, 13750.00, 1, 1
UNION ALL SELECT 'MU0878', 'PCS', 1.000, 1, 0, 14000.00, 7425.00, 1, 1
UNION ALL SELECT 'MU0879', 'PCS', 1.000, 1, 0, 7000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0880', 'PCS', 1.000, 1, 0, 6000.00, 9600.00, 1, 1
UNION ALL SELECT 'MU0881', 'PCS', 1.000, 1, 0, 6000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0882', 'PCS', 1.000, 1, 0, 5000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0883', 'PCS', 1.000, 1, 0, 20000.00, 6320.00, 1, 1
UNION ALL SELECT 'MU0884', 'PCS', 1.000, 1, 0, 10000.00, 5500.00, 1, 1
UNION ALL SELECT 'MU0885', 'PCS', 1.000, 1, 0, 7000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0886', 'PCS', 1.000, 1, 0, 11000.00, 5775.00, 1, 1
UNION ALL SELECT 'MU0887', 'PCS', 1.000, 1, 0, 5000.00, 3025.00, 1, 1
UNION ALL SELECT 'MU0888', 'LBR', 1.000, 1, 0, 7000.00, 4800.00, 1, 1
UNION ALL SELECT 'MU0889', 'PCS', 1.000, 1, 0, 28000.00, 16250.00, 1, 1
UNION ALL SELECT 'MU0890', 'PAK', 1.000, 1, 0, 13000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0891', 'SET', 1.000, 1, 0, 110000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU0892', 'SET', 1.000, 1, 0, 200000.00, 160000.00, 1, 1
UNION ALL SELECT 'MU0893', 'PCS', 1.000, 1, 0, 210000.00, 144000.00, 1, 1
UNION ALL SELECT 'MU0894', 'PCS', 1.000, 1, 0, 29000.00, 9900.00, 1, 1
UNION ALL SELECT 'MU0895', 'PCS', 1.000, 1, 0, 7000.00, 2500.00, 1, 1
UNION ALL SELECT 'MU0896', 'PCS', 1.000, 1, 0, 5000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU0897', 'UNIT', 1.000, 1, 0, 90000.00, 53000.00, 1, 1
UNION ALL SELECT 'MU0898', 'PCS', 1.000, 1, 0, 15000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU0899', 'PCS', 1.000, 1, 0, 43000.00, 34400.00, 1, 1
UNION ALL SELECT 'MU0900', 'PCS', 1.000, 1, 0, 58000.00, 46400.00, 1, 1
UNION ALL SELECT 'MU0901', 'DUS', 1.000, 1, 0, 250000.00, 147000.00, 1, 1
UNION ALL SELECT 'MU0902', 'PAK', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0903', 'PAK', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0904', 'SET', 1.000, 1, 0, 120000.00, 96000.00, 1, 1
UNION ALL SELECT 'MU0905', 'PCS', 1.000, 1, 0, 120000.00, 96000.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU0906' AS sku, 'SET' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 110000.00 AS selling_price, 88000.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU0907', 'SET', 1.000, 1, 0, 110000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU0908', 'PAK', 1.000, 1, 0, 106000.00, 70312.00, 1, 1
UNION ALL SELECT 'MU0909', 'PCS', 1.000, 1, 0, 115000.00, 92000.00, 1, 1
UNION ALL SELECT 'MU0910', 'DUS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0911', 'DUS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0912', 'DUS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU0913', 'LBR', 1.000, 1, 0, 10000.00, 5600.00, 1, 1
UNION ALL SELECT 'MU0914', 'PCS', 1.000, 1, 0, 10000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU0915', 'DUS', 1.000, 1, 0, 28000.00, 13600.00, 1, 1
UNION ALL SELECT 'MU0916', 'DUS', 1.000, 1, 0, 105000.00, 100000.00, 1, 1
UNION ALL SELECT 'MU0917', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0918', 'PCS', 1.000, 1, 0, 22000.00, 17600.00, 1, 1
UNION ALL SELECT 'MU0919', 'PCS', 1.000, 1, 0, 95000.00, 76000.00, 1, 1
UNION ALL SELECT 'MU0920', 'PCS', 1.000, 1, 0, 85000.00, 68000.00, 1, 1
UNION ALL SELECT 'MU0921', 'DUS', 1.000, 1, 0, 16000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0922', 'DUS', 1.000, 1, 0, 10000.00, 4700.00, 1, 1
UNION ALL SELECT 'MU0923', 'PCS', 1.000, 1, 0, 18000.00, 14400.00, 1, 1
UNION ALL SELECT 'MU0924', 'PCS', 1.000, 1, 0, 3000.00, 2400.00, 1, 1
UNION ALL SELECT 'MU0925', 'GR', 1.000, 1, 0, 24000.00, 19200.00, 1, 1
UNION ALL SELECT 'MU0926', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0927', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU0928', 'PCS', 1.000, 1, 0, 88000.00, 70400.00, 1, 1
UNION ALL SELECT 'MU0929', 'PCS', 1.000, 1, 0, 61000.00, 48800.00, 1, 1
UNION ALL SELECT 'MU0930', 'PCS', 1.000, 1, 0, 45000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU0931', 'PCS', 1.000, 1, 0, 12000.00, 4200.00, 1, 1
UNION ALL SELECT 'MU0932', 'PCS', 1.000, 1, 0, 87000.00, 13500.00, 1, 1
UNION ALL SELECT 'MU0933', 'PCS', 1.000, 1, 0, 40000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU0934', 'DUS', 1.000, 1, 0, 94000.00, 67818.00, 1, 1
UNION ALL SELECT 'MU0935', 'PCS', 1.000, 1, 0, 10000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0936', 'SET', 1.000, 1, 0, 16000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU0937', 'PCS', 1.000, 1, 0, 37000.00, 29600.00, 1, 1
UNION ALL SELECT 'MU0938', 'PCS', 1.000, 1, 0, 39000.00, 31200.00, 1, 1
UNION ALL SELECT 'MU0939', 'PCS', 1.000, 1, 0, 56000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU0940', 'PCS', 1.000, 1, 0, 39000.00, 31200.00, 1, 1
UNION ALL SELECT 'MU0941', 'PCS', 1.000, 1, 0, 46000.00, 35500.00, 1, 1
UNION ALL SELECT 'MU0942', 'PCS', 1.000, 1, 0, 96000.00, 96000.00, 1, 1
UNION ALL SELECT 'MU0943', 'PCS', 1.000, 1, 0, 84000.00, 83000.00, 1, 1
UNION ALL SELECT 'MU0944', 'PCS', 1.000, 1, 0, 41000.00, 32800.00, 1, 1
UNION ALL SELECT 'MU0945', 'PCS', 1.000, 1, 0, 145000.00, 79750.00, 1, 1
UNION ALL SELECT 'MU0946', 'PCS', 1.000, 1, 0, 42000.00, 33600.00, 1, 1
UNION ALL SELECT 'MU0947', 'PCS', 1.000, 1, 0, 140000.00, 140000.00, 1, 1
UNION ALL SELECT 'MU0948', 'PCS', 1.000, 1, 0, 33000.00, 26400.00, 1, 1
UNION ALL SELECT 'MU0949', 'PCS', 1.000, 1, 0, 27000.00, 21600.00, 1, 1
UNION ALL SELECT 'MU0950', 'BKS', 1.000, 1, 0, 16000.00, 10294.00, 1, 1
UNION ALL SELECT 'MU0951', 'PCS', 1.000, 1, 0, 158000.00, 108000.00, 1, 1
UNION ALL SELECT 'MU0952', 'PCS', 1.000, 1, 0, 23000.00, 13500.00, 1, 1
UNION ALL SELECT 'MU0953', 'PCS', 1.000, 1, 0, 32000.00, 25600.00, 1, 1
UNION ALL SELECT 'MU0954', 'PCS', 1.000, 1, 0, 19000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0955', 'PSG', 1.000, 1, 0, 65000.00, 37000.00, 1, 1
UNION ALL SELECT 'MU0956', 'DUS', 1.000, 1, 0, 103000.00, 59000.00, 1, 1
UNION ALL SELECT 'MU0957', 'DUS', 1.000, 1, 0, 103000.00, 59000.00, 1, 1
UNION ALL SELECT 'MU0958', 'PCS', 1.000, 1, 0, 25000.00, 17500.00, 1, 1
UNION ALL SELECT 'MU0959', 'PCS', 1.000, 1, 0, 13000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU0960', 'PCS', 1.000, 1, 0, 95000.00, 70400.00, 1, 1
UNION ALL SELECT 'MU0961', 'PCS', 1.000, 1, 0, 88000.00, 70400.00, 1, 1
UNION ALL SELECT 'MU0962', 'PCS', 1.000, 1, 0, 25000.00, 14000.00, 1, 1
UNION ALL SELECT 'MU0963', 'PCS', 1.000, 1, 0, 115000.00, 92000.00, 1, 1
UNION ALL SELECT 'MU0964', 'UNIT', 1.000, 1, 0, 297000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0965', 'UNIT', 1.000, 1, 0, 257000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0966', 'PCS', 1.000, 1, 0, 88000.00, 70400.00, 1, 1
UNION ALL SELECT 'MU0967', 'PCS', 1.000, 1, 0, 88000.00, 70400.00, 1, 1
UNION ALL SELECT 'MU0968', 'PCS', 1.000, 1, 0, 46000.00, 23000.00, 1, 1
UNION ALL SELECT 'MU0969', 'UNIT', 1.000, 1, 0, 1044000.00, 835200.00, 1, 1
UNION ALL SELECT 'MU0970', 'PCS', 1.000, 1, 0, 13000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0971', 'UNIT', 1.000, 1, 0, 606000.00, 484800.00, 1, 1
UNION ALL SELECT 'MU0972', 'PCS', 1.000, 1, 0, 65000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0973', 'PCS', 1.000, 1, 0, 125000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0974', 'PCS', 1.000, 1, 0, 81000.00, 64800.00, 1, 1
UNION ALL SELECT 'MU0975', 'PCS', 1.000, 1, 0, 214000.00, 171200.00, 1, 1
UNION ALL SELECT 'MU0976', 'PAK', 1.000, 1, 0, 19000.00, 15200.00, 1, 1
UNION ALL SELECT 'MU0977', 'PAK', 1.000, 1, 0, 16000.00, 12800.00, 1, 1
UNION ALL SELECT 'MU0978', 'PAK', 1.000, 1, 0, 19000.00, 15200.00, 1, 1
UNION ALL SELECT 'MU0979', 'PAK', 1.000, 1, 0, 21000.00, 16800.00, 1, 1
UNION ALL SELECT 'MU0980', 'SET', 1.000, 1, 0, 203000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0981', 'PCS', 1.000, 1, 0, 6000.00, 4800.00, 1, 1
UNION ALL SELECT 'MU0982', 'SET', 1.000, 1, 0, 102000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0983', 'SET', 1.000, 1, 0, 102000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0984', 'SET', 1.000, 1, 0, 69000.00, 55200.00, 1, 1
UNION ALL SELECT 'MU0985', 'PCS', 1.000, 1, 0, 13000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0986', 'SET', 1.000, 1, 0, 23000.00, 13333.00, 1, 1
UNION ALL SELECT 'MU0987', 'UNIT', 1.000, 1, 0, 1975000.00, 1320333.00, 1, 1
UNION ALL SELECT 'MU0988', 'PCS', 1.000, 1, 0, 56000.00, 46380.00, 1, 1
UNION ALL SELECT 'MU0989', 'PCS', 1.000, 1, 0, 135000.00, 74250.00, 1, 1
UNION ALL SELECT 'MU0990', 'PCS', 1.000, 1, 0, 115000.00, 92000.00, 1, 1
UNION ALL SELECT 'MU0991', 'PCS', 1.000, 1, 0, 13000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU0992', 'PCS', 1.000, 1, 0, 3000.00, 2400.00, 1, 1
UNION ALL SELECT 'MU0993', 'PCS', 1.000, 1, 0, 22000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0994', 'PSG', 1.000, 1, 0, 7000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0995', 'PCS', 1.000, 1, 0, 10000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU0996', 'PCS', 1.000, 1, 0, 10000.00, 0.00, 1, 1
UNION ALL SELECT 'MU0997', 'PCS', 1.000, 1, 0, 25000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU0998', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU0999', 'PCS', 1.000, 1, 0, 15000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU1000', 'PCS', 1.000, 1, 0, 80000.00, 64000.00, 1, 1
UNION ALL SELECT 'MU1001', 'PCS', 1.000, 1, 0, 3000.00, 2400.00, 1, 1
UNION ALL SELECT 'MU1002', 'KLG', 1.000, 1, 0, 45000.00, 33000.00, 1, 1
UNION ALL SELECT 'MU1003', 'KLG', 1.000, 1, 0, 170000.00, 11000.00, 1, 1
UNION ALL SELECT 'MU1004', 'PCS', 1.000, 1, 0, 17000.00, 13600.00, 1, 1
UNION ALL SELECT 'MU1005', 'PCS', 1.000, 1, 0, 17000.00, 13600.00, 1, 1
UNION ALL SELECT 'MU1006', 'PCS', 1.000, 1, 0, 14000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1007', 'PCS', 1.000, 1, 0, 36000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU1008', 'SET', 1.000, 1, 0, 80000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU1009', 'PCS', 1.000, 1, 0, 45000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU1010', 'PCS', 1.000, 1, 0, 16000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU1011', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU1012', 'PCS', 1.000, 1, 0, 17000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU1013', 'PCS', 1.000, 1, 0, 8000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU1014', 'PCS', 1.000, 1, 0, 8000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU1015', 'PCS', 1.000, 1, 0, 17000.00, 13600.00, 1, 1
UNION ALL SELECT 'MU1016', 'PCS', 1.000, 1, 0, 41000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU1017', 'PCS', 1.000, 1, 0, 45000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU1018', 'PCS', 1.000, 1, 0, 55000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU1019', 'PCS', 1.000, 1, 0, 322000.00, 189000.00, 1, 1
UNION ALL SELECT 'MU1020', 'PCS', 1.000, 1, 0, 175000.00, 102000.00, 1, 1
UNION ALL SELECT 'MU1021', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU1022', 'PCS', 1.000, 1, 0, 23000.00, 18400.00, 1, 1
UNION ALL SELECT 'MU1023', 'PCS', 1.000, 1, 0, 12000.00, 9600.00, 1, 1
UNION ALL SELECT 'MU1024', 'PCS', 1.000, 1, 0, 35000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU1025', 'PCS', 1.000, 1, 0, 55000.00, 44000.00, 1, 1
UNION ALL SELECT 'MU1026', 'PCS', 1.000, 1, 0, 8000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU1027', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1028', 'PCS', 1.000, 1, 0, 14000.00, 11200.00, 1, 1
UNION ALL SELECT 'MU1029', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU1030', 'PCS', 1.000, 1, 0, 58000.00, 33250.00, 1, 1
UNION ALL SELECT 'MU1031', 'PCS', 1.000, 1, 0, 58000.00, 33250.00, 1, 1
UNION ALL SELECT 'MU1032', 'PCS', 1.000, 1, 0, 53000.00, 29200.00, 1, 1
UNION ALL SELECT 'MU1033', 'IKAT', 1.000, 1, 0, 18000.00, 11200.00, 1, 1
UNION ALL SELECT 'MU1034', 'PCS', 1.000, 1, 0, 3500.00, 19200.00, 1, 1
UNION ALL SELECT 'MU1035', 'PCS', 1.000, 1, 0, 18000.00, 8400.00, 1, 1
UNION ALL SELECT 'MU1036', 'PCS', 1.000, 1, 0, 58000.00, 33500.00, 1, 1
UNION ALL SELECT 'MU1037', 'PCS', 1.000, 1, 0, 20000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU1038', 'PCS', 1.000, 1, 0, 105000.00, 84000.00, 1, 1
UNION ALL SELECT 'MU1039', 'PCS', 1.000, 1, 0, 8000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU1040', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1041', 'PCS', 1.000, 1, 0, 13000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1042', 'PCS', 1.000, 1, 0, 18000.00, 14400.00, 1, 1
UNION ALL SELECT 'MU1043', 'PCS', 1.000, 1, 0, 75000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU1044', 'PCS', 1.000, 1, 0, 85000.00, 68000.00, 1, 1
UNION ALL SELECT 'MU1045', 'PCS', 1.000, 1, 0, 100000.00, 80000.00, 1, 1
UNION ALL SELECT 'MU1046', 'PCS', 1.000, 1, 0, 90000.00, 72000.00, 1, 1
UNION ALL SELECT 'MU1047', 'PCS', 1.000, 1, 0, 113000.00, 90400.00, 1, 1
UNION ALL SELECT 'MU1048', 'PCS', 1.000, 1, 0, 179000.00, 143200.00, 1, 1
UNION ALL SELECT 'MU1049', 'PCS', 1.000, 1, 0, 180000.00, 144000.00, 1, 1
UNION ALL SELECT 'MU1050', 'PCS', 1.000, 1, 0, 230000.00, 184000.00, 1, 1
UNION ALL SELECT 'MU1051', 'PCS', 1.000, 1, 0, 285000.00, 228000.00, 1, 1
UNION ALL SELECT 'MU1052', 'PCS', 1.000, 1, 0, 37000.00, 21250.00, 1, 1
UNION ALL SELECT 'MU1053', 'PCS', 1.000, 1, 0, 42000.00, 24200.00, 1, 1
UNION ALL SELECT 'MU1054', 'PCS', 1.000, 1, 0, 47000.00, 27100.00, 1, 1
UNION ALL SELECT 'MU1055', 'PCS', 1.000, 1, 0, 57000.00, 33400.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU1056' AS sku, 'PCS' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 63000.00 AS selling_price, 36700.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU1057', 'PCS', 1.000, 1, 0, 18000.00, 14400.00, 1, 1
UNION ALL SELECT 'MU1058', 'PCS', 1.000, 1, 0, 19000.00, 15200.00, 1, 1
UNION ALL SELECT 'MU1059', 'PCS', 1.000, 1, 0, 21000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1060', 'PCS', 1.000, 1, 0, 25000.00, 14500.00, 1, 1
UNION ALL SELECT 'MU1061', 'PCS', 1.000, 1, 0, 28000.00, 16250.00, 1, 1
UNION ALL SELECT 'MU1062', 'PCS', 1.000, 1, 0, 60000.00, 29200.00, 1, 1
UNION ALL SELECT 'MU1063', 'PCS', 1.000, 1, 0, 65000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU1064', 'PCS', 1.000, 1, 0, 2500.00, 2000.00, 1, 1
UNION ALL SELECT 'MU1065', 'PCS', 1.000, 1, 0, 5000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU1066', 'PCS', 1.000, 1, 0, 5000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU1067', 'PCS', 1.000, 1, 0, 4000.00, 3200.00, 1, 1
UNION ALL SELECT 'MU1068', 'PCS', 1.000, 1, 0, 65000.00, 36400.00, 1, 1
UNION ALL SELECT 'MU1069', 'PCS', 1.000, 1, 0, 30000.00, 15900.00, 1, 1
UNION ALL SELECT 'MU1070', 'PCS', 1.000, 1, 0, 50000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU1071', 'PCS', 1.000, 1, 0, 60000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU1072', 'PCS', 1.000, 1, 0, 4000.00, 3200.00, 1, 1
UNION ALL SELECT 'MU1073', 'PCS', 1.000, 1, 0, 9000.00, 7200.00, 1, 1
UNION ALL SELECT 'MU1074', 'PCS', 1.000, 1, 0, 8000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU1075', 'PCS', 1.000, 1, 0, 5000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU1076', 'PCS', 1.000, 1, 0, 7000.00, 5600.00, 1, 1
UNION ALL SELECT 'MU1077', 'PCS', 1.000, 1, 0, 8000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU1078', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1079', 'PCS', 1.000, 1, 0, 13000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1080', 'PCS', 1.000, 1, 0, 20000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU1081', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1082', 'PCS', 1.000, 1, 0, 25000.00, 13750.00, 1, 1
UNION ALL SELECT 'MU1083', 'PCS', 1.000, 1, 0, 23000.00, 11100.00, 1, 1
UNION ALL SELECT 'MU1084', 'PCS', 1.000, 1, 0, 30000.00, 15500.00, 1, 1
UNION ALL SELECT 'MU1085', 'ROL', 1.000, 1, 0, 1600000.00, 508000.00, 1, 1
UNION ALL SELECT 'MU1086', 'PCS', 1.000, 1, 0, 11000.00, 6900.00, 1, 1
UNION ALL SELECT 'MU1087', 'PCS', 1.000, 1, 0, 13000.00, 7900.00, 1, 1
UNION ALL SELECT 'MU1088', 'PCS', 1.000, 1, 0, 17000.00, 10600.00, 1, 1
UNION ALL SELECT 'MU1089', 'PCS', 1.000, 1, 0, 28000.00, 14200.00, 1, 1
UNION ALL SELECT 'MU1090', 'PCS', 1.000, 1, 0, 215000.00, 160000.00, 1, 1
UNION ALL SELECT 'MU1091', 'PCS', 1.000, 1, 0, 210000.00, 155000.00, 1, 1
UNION ALL SELECT 'MU1092', 'PCS', 1.000, 1, 0, 304000.00, 225000.00, 1, 1
UNION ALL SELECT 'MU1093', 'UNIT', 1.000, 1, 0, 328000.00, 285000.00, 1, 1
UNION ALL SELECT 'MU1094', 'PCS', 1.000, 1, 0, 440000.00, 352000.00, 1, 1
UNION ALL SELECT 'MU1095', 'PCS', 1.000, 1, 0, 805000.00, 644000.00, 1, 1
UNION ALL SELECT 'MU1096', 'UNIT', 1.000, 1, 0, 196000.00, 145000.00, 1, 1
UNION ALL SELECT 'MU1097', 'PCS', 1.000, 1, 0, 150000.00, 120000.00, 1, 1
UNION ALL SELECT 'MU1098', 'UNIT', 1.000, 1, 0, 385000.00, 285000.00, 1, 1
UNION ALL SELECT 'MU1099', 'PCS', 1.000, 1, 0, 405000.00, 324000.00, 1, 1
UNION ALL SELECT 'MU1100', 'PCS', 1.000, 1, 0, 142000.00, 113000.00, 1, 1
UNION ALL SELECT 'MU1101', 'UNIT', 1.000, 1, 0, 284000.00, 210000.00, 1, 1
UNION ALL SELECT 'MU1102', 'PCS', 1.000, 1, 0, 615000.00, 492000.00, 1, 1
UNION ALL SELECT 'MU1103', 'PCS', 1.000, 1, 0, 750000.00, 600000.00, 1, 1
UNION ALL SELECT 'MU1104', 'PCS', 1.000, 1, 0, 1223000.00, 874000.00, 1, 1
UNION ALL SELECT 'MU1105', 'PCS', 1.000, 1, 0, 733000.00, 524000.00, 1, 1
UNION ALL SELECT 'MU1106', 'PCS', 1.000, 1, 0, 1013000.00, 724000.00, 1, 1
UNION ALL SELECT 'MU1107', 'PCS', 1.000, 1, 0, 1435000.00, 1025000.00, 1, 1
UNION ALL SELECT 'MU1108', 'PCS', 1.000, 1, 0, 875000.00, 625000.00, 1, 1
UNION ALL SELECT 'MU1109', 'PCS', 1.000, 1, 0, 3000.00, 2400.00, 1, 1
UNION ALL SELECT 'MU1110', 'PCS', 1.000, 1, 0, 5000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU1111', 'PCS', 1.000, 1, 0, 7000.00, 5600.00, 1, 1
UNION ALL SELECT 'MU1112', 'PCS', 1.000, 1, 0, 8000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU1113', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1114', 'PCS', 1.000, 1, 0, 200000.00, 146000.00, 1, 1
UNION ALL SELECT 'MU1115', 'PCS', 1.000, 1, 0, 300000.00, 216000.00, 1, 1
UNION ALL SELECT 'MU1116', 'PCS', 1.000, 1, 0, 30000.00, 19200.00, 1, 1
UNION ALL SELECT 'MU1117', 'PCS', 1.000, 1, 0, 38000.00, 24200.00, 1, 1
UNION ALL SELECT 'MU1118', 'PCS', 1.000, 1, 0, 414000.00, 294000.00, 1, 1
UNION ALL SELECT 'MU1119', 'PCS', 1.000, 1, 0, 189000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU1120', 'PCS', 1.000, 1, 0, 440000.00, 352000.00, 1, 1
UNION ALL SELECT 'MU1121', 'UNIT', 1.000, 1, 0, 322000.00, 280000.00, 1, 1
UNION ALL SELECT 'MU1122', 'PCS', 1.000, 1, 0, 325000.00, 260000.00, 1, 1
UNION ALL SELECT 'MU1123', 'PCS', 1.000, 1, 0, 350000.00, 280000.00, 1, 1
UNION ALL SELECT 'MU1124', 'PCS', 1.000, 1, 0, 37000.00, 18500.00, 1, 1
UNION ALL SELECT 'MU1125', 'PCS', 1.000, 1, 0, 345000.00, 276000.00, 1, 1
UNION ALL SELECT 'MU1126', 'PCS', 1.000, 1, 0, 27000.00, 13500.00, 1, 1
UNION ALL SELECT 'MU1127', 'UNIT', 1.000, 1, 0, 288000.00, 250000.00, 1, 1
UNION ALL SELECT 'MU1128', 'PCS', 1.000, 1, 0, 475000.00, 380000.00, 1, 1
UNION ALL SELECT 'MU1129', 'PCS', 1.000, 1, 0, 240000.00, 192000.00, 1, 1
UNION ALL SELECT 'MU1130', 'PCS', 1.000, 1, 0, 855000.00, 684000.00, 1, 1
UNION ALL SELECT 'MU1131', 'PCS', 1.000, 1, 0, 65000.00, 52000.00, 1, 1
UNION ALL SELECT 'MU1132', 'PCS', 1.000, 1, 0, 60000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1133', 'LBR', 1.000, 1, 0, 3000.00, 2000.00, 1, 1
UNION ALL SELECT 'MU1134', 'PCS', 1.000, 1, 0, 40000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU1135', 'PCS', 1.000, 1, 0, 200000.00, 160000.00, 1, 1
UNION ALL SELECT 'MU1136', 'PCS', 1.000, 1, 0, 220000.00, 176000.00, 1, 1
UNION ALL SELECT 'MU1137', 'PCS', 1.000, 1, 0, 285000.00, 228000.00, 1, 1
UNION ALL SELECT 'MU1138', 'PCS', 1.000, 1, 0, 115000.00, 92000.00, 1, 1
UNION ALL SELECT 'MU1139', 'PCS', 1.000, 1, 0, 40000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU1140', 'PCS', 1.000, 1, 0, 55000.00, 44000.00, 1, 1
UNION ALL SELECT 'MU1141', 'LBR', 1.000, 1, 0, 95000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU1142', 'PCS', 1.000, 1, 0, 41000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU1143', 'PCS', 1.000, 1, 0, 350000.00, 280000.00, 1, 1
UNION ALL SELECT 'MU1144', 'PCS', 1.000, 1, 0, 200000.00, 160000.00, 1, 1
UNION ALL SELECT 'MU1145', 'PCS', 1.000, 1, 0, 220000.00, 176000.00, 1, 1
UNION ALL SELECT 'MU1146', 'PCS', 1.000, 1, 0, 60000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU1147', 'PCS', 1.000, 1, 0, 50000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU1148', 'PCS', 1.000, 1, 0, 10000.00, 4500.00, 1, 1
UNION ALL SELECT 'MU1149', 'PCS', 1.000, 1, 0, 33000.00, 16500.00, 1, 1
UNION ALL SELECT 'MU1150', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1151', 'PCS', 1.000, 1, 0, 8000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU1152', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU1153', 'PCS', 1.000, 1, 0, 18000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU1154', 'PCS', 1.000, 1, 0, 40000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU1155', 'PCS', 1.000, 1, 0, 23000.00, 11340.00, 1, 1
UNION ALL SELECT 'MU1156', 'PCS', 1.000, 1, 0, 33000.00, 16500.00, 1, 1
UNION ALL SELECT 'MU1157', 'PCS', 1.000, 1, 0, 8000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU1158', 'PCS', 1.000, 1, 0, 9000.00, 5600.00, 1, 1
UNION ALL SELECT 'MU1159', 'PCS', 1.000, 1, 0, 20000.00, 8500.00, 1, 1
UNION ALL SELECT 'MU116', 'PCS', 1.000, 1, 0, 27000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1160', 'PCS', 1.000, 1, 0, 25000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU1161', 'PCS', 1.000, 1, 0, 15000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1162', 'PCS', 1.000, 1, 0, 16000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1163', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1164', 'PCS', 1.000, 1, 0, 7000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU1165', 'PCS', 1.000, 1, 0, 13000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1166', 'PCS', 1.000, 1, 0, 17000.00, 13600.00, 1, 1
UNION ALL SELECT 'MU1167', 'PCS', 1.000, 1, 0, 2000.00, 1000.00, 1, 1
UNION ALL SELECT 'MU1168', 'SET', 1.000, 1, 0, 125000.00, 100000.00, 1, 1
UNION ALL SELECT 'MU1169', 'PCS', 1.000, 1, 0, 15000.00, 6485.00, 1, 1
UNION ALL SELECT 'MU1170', 'PCS', 1.000, 1, 0, 14000.00, 8333.00, 1, 1
UNION ALL SELECT 'MU1171', 'PCS', 1.000, 1, 0, 38000.00, 23000.00, 1, 1
UNION ALL SELECT 'MU1172', 'PCS', 1.000, 1, 0, 12000.00, 13000.00, 1, 1
UNION ALL SELECT 'MU1173', 'PCS', 1.000, 1, 0, 17000.00, 13600.00, 1, 1
UNION ALL SELECT 'MU1174', 'PCS', 1.000, 1, 0, 30000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU1176', 'PCS', 1.000, 1, 0, 6000.00, 2916.00, 1, 1
UNION ALL SELECT 'MU1177', 'PCS', 1.000, 1, 0, 85000.00, 42500.00, 1, 1
UNION ALL SELECT 'MU1178', 'BTL', 1.000, 1, 0, 34000.00, 20900.00, 1, 1
UNION ALL SELECT 'MU1179', 'PCS', 1.000, 1, 0, 125000.00, 85500.00, 1, 1
UNION ALL SELECT 'MU1180', 'PCS', 1.000, 1, 0, 130000.00, 89600.00, 1, 1
UNION ALL SELECT 'MU1182', 'PCS', 1.000, 1, 0, 70000.00, 38500.00, 1, 1
UNION ALL SELECT 'MU1183', 'PCS', 1.000, 1, 0, 47000.00, 32500.00, 1, 1
UNION ALL SELECT 'MU1184', 'PCS', 1.000, 1, 0, 1500.00, 1000.00, 1, 1
UNION ALL SELECT 'MU1185', 'PCS', 1.000, 1, 0, 20000.00, 11666.00, 1, 1
UNION ALL SELECT 'MU1186', 'PCS', 1.000, 1, 0, 28000.00, 18400.00, 1, 1
UNION ALL SELECT 'MU1187', 'PCS', 1.000, 1, 0, 19000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1188', 'PCS', 1.000, 1, 0, 20000.00, 12800.00, 1, 1
UNION ALL SELECT 'MU1189', 'PCS', 1.000, 1, 0, 54000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU1190', 'PCS', 1.000, 1, 0, 54000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU1191', 'PSG', 1.000, 1, 0, 10000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU1192', 'PCS', 1.000, 1, 0, 59000.00, 21500.00, 1, 1
UNION ALL SELECT 'MU1193', 'PCS', 1.000, 1, 0, 41000.00, 14750.00, 1, 1
UNION ALL SELECT 'MU1194', 'PCS', 1.000, 1, 0, 48000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU1195', 'PCS', 1.000, 1, 0, 41000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU1196', 'UNIT', 1.000, 1, 0, 177000.00, 117500.00, 1, 1
UNION ALL SELECT 'MU1197', 'UNIT', 1.000, 1, 0, 260000.00, 208000.00, 1, 1
UNION ALL SELECT 'MU1198', 'ROL', 1.000, 1, 0, 45000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU1199', 'PCS', 1.000, 1, 0, 205000.00, 164000.00, 1, 1
UNION ALL SELECT 'MU1200', 'PCS', 1.000, 1, 0, 260000.00, 208000.00, 1, 1
UNION ALL SELECT 'MU1201', 'SET', 1.000, 1, 0, 113000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU1202', 'PCS', 1.000, 1, 0, 85000.00, 52250.00, 1, 1
UNION ALL SELECT 'MU1203', 'PCS', 1.000, 1, 0, 322000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1204', 'PCS', 1.000, 1, 0, 3000.00, 1200.00, 1, 1
UNION ALL SELECT 'MU1205', 'PCS', 1.000, 1, 0, 65000.00, 52000.00, 1, 1
UNION ALL SELECT 'MU1206', 'PCS', 1.000, 1, 0, 240000.00, 159900.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU1207' AS sku, 'PCS' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 180000.00 AS selling_price, 150000.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU1208', 'PCS', 1.000, 1, 0, 185000.00, 148000.00, 1, 1
UNION ALL SELECT 'MU1209', 'PCS', 1.000, 1, 0, 24000.00, 16450.00, 1, 1
UNION ALL SELECT 'MU1210', 'SET', 1.000, 1, 0, 113000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU1211', 'PCS', 1.000, 1, 0, 207000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1212', 'PCS', 1.000, 1, 0, 35000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU1213', 'PCS', 1.000, 1, 0, 26000.00, 17600.00, 1, 1
UNION ALL SELECT 'MU1214', 'PCS', 1.000, 1, 0, 19000.00, 9450.00, 1, 1
UNION ALL SELECT 'MU1215', 'PCS', 1.000, 1, 0, 144000.00, 100000.00, 1, 1
UNION ALL SELECT 'MU1216', 'BKS', 1.000, 1, 0, 16000.00, 10294.00, 1, 1
UNION ALL SELECT 'MU1217', 'PCS', 1.000, 1, 0, 15000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU1218', 'PCS', 1.000, 1, 0, 202000.00, 140000.00, 1, 1
UNION ALL SELECT 'MU1219', 'PCS', 1.000, 1, 0, 30000.00, 14000.00, 1, 1
UNION ALL SELECT 'MU1220', 'PCS', 1.000, 1, 0, 110000.00, 76000.00, 1, 1
UNION ALL SELECT 'MU1221', 'SET', 1.000, 1, 0, 113000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU1222', 'PCS', 1.000, 1, 0, 81000.00, 56000.00, 1, 1
UNION ALL SELECT 'MU1223', 'PCS', 1.000, 1, 0, 93000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU1224', 'PCS', 1.000, 1, 0, 93000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU1225', 'SET', 1.000, 1, 0, 93000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU1226', 'PCS', 1.000, 1, 0, 100000.00, 80000.00, 1, 1
UNION ALL SELECT 'MU1227', 'PCS', 1.000, 1, 0, 22000.00, 15695.00, 1, 1
UNION ALL SELECT 'MU1228', 'PCS', 1.000, 1, 0, 10000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU1229', 'PCS', 1.000, 1, 0, 54000.00, 43200.00, 1, 1
UNION ALL SELECT 'MU1230', 'PCS', 1.000, 1, 0, 450000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1231', 'LBR', 1.000, 1, 0, 8000.00, 4750.00, 1, 1
UNION ALL SELECT 'MU1232', 'PCS', 1.000, 1, 0, 111000.00, 55500.00, 1, 1
UNION ALL SELECT 'MU1233', 'PCS', 1.000, 1, 0, 69000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU1234', 'PCS', 1.000, 1, 0, 75000.00, 28600.00, 1, 1
UNION ALL SELECT 'MU1235', 'PCS', 1.000, 1, 0, 58000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU1236', 'PCS', 1.000, 1, 0, 40000.00, 17000.00, 1, 1
UNION ALL SELECT 'MU1237', 'PCS', 1.000, 1, 0, 12000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1238', 'PCS', 1.000, 1, 0, 64000.00, 44000.00, 1, 1
UNION ALL SELECT 'MU1239', 'PCS', 1.000, 1, 0, 29000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1240', 'PCS', 1.000, 1, 0, 23000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU1241', 'PCS', 1.000, 1, 0, 29000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1242', 'KLG', 1.000, 1, 0, 35000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU1243', 'PCS', 1.000, 1, 0, 64000.00, 44000.00, 1, 1
UNION ALL SELECT 'MU1244', 'PCS', 1.000, 1, 0, 23000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU1245', 'PCS', 1.000, 1, 0, 12000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1246', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1247', 'PCS', 1.000, 1, 0, 35000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU1248', 'PCS', 1.000, 1, 0, 138000.00, 96000.00, 1, 1
UNION ALL SELECT 'MU1249', 'PCS', 1.000, 1, 0, 40000.00, 16666.00, 1, 1
UNION ALL SELECT 'MU1250', 'PCS', 1.000, 1, 0, 207000.00, 144000.00, 1, 1
UNION ALL SELECT 'MU1251', 'PCS', 1.000, 1, 0, 403000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1252', 'PCS', 1.000, 1, 0, 403000.00, 280000.00, 1, 1
UNION ALL SELECT 'MU1253', 'KLG', 1.000, 1, 0, 68000.00, 54400.00, 1, 1
UNION ALL SELECT 'MU1254', 'PCS', 1.000, 1, 0, 173000.00, 120000.00, 1, 1
UNION ALL SELECT 'MU1255', 'PCS', 1.000, 1, 0, 161000.00, 112000.00, 1, 1
UNION ALL SELECT 'MU1256', 'PCS', 1.000, 1, 0, 161000.00, 112000.00, 1, 1
UNION ALL SELECT 'MU1257', 'LBR', 1.000, 1, 0, 3000.00, 2000.00, 1, 1
UNION ALL SELECT 'MU1258', 'PCS', 1.000, 1, 0, 173000.00, 120000.00, 1, 1
UNION ALL SELECT 'MU1259', 'PCS', 1.000, 1, 0, 185000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1260', 'PCS', 1.000, 1, 0, 162000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1261', 'PCS', 1.000, 1, 0, 269000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1262', 'PCS', 1.000, 1, 0, 519000.00, 415200.00, 1, 1
UNION ALL SELECT 'MU1263', 'PCS', 1.000, 1, 0, 3100000.00, 2480000.00, 1, 1
UNION ALL SELECT 'MU1264', 'PCS', 1.000, 1, 0, 1350000.00, 1080000.00, 1, 1
UNION ALL SELECT 'MU1265', 'DUS', 1.000, 1, 0, 106000.00, 95000.00, 1, 1
UNION ALL SELECT 'MU1266', 'UNIT', 1.000, 1, 0, 630000.00, 504000.00, 1, 1
UNION ALL SELECT 'MU1267', 'PCS', 1.000, 1, 0, 6000.00, 2000.00, 1, 1
UNION ALL SELECT 'MU1268', 'SET', 1.000, 1, 0, 278000.00, 149600.00, 1, 1
UNION ALL SELECT 'MU1269', 'UNIT', 1.000, 1, 0, 130000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1270', 'UNIT', 1.000, 1, 0, 3283000.00, 1968000.00, 1, 1
UNION ALL SELECT 'MU1271', 'PCS', 1.000, 1, 0, 100000.00, 52000.00, 1, 1
UNION ALL SELECT 'MU1272', 'PCS', 1.000, 1, 0, 100000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU1273', 'PCS', 1.000, 1, 0, 100000.00, 52000.00, 1, 1
UNION ALL SELECT 'MU1274', 'PCS', 1.000, 1, 0, 45000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU1275', 'PCS', 1.000, 1, 0, 45000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU1276', 'PCS', 1.000, 1, 0, 46000.00, 36800.00, 1, 1
UNION ALL SELECT 'MU1277', 'PCS', 1.000, 1, 0, 45000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU1278', 'PCS', 1.000, 1, 0, 45000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU1279', 'UNIT', 1.000, 1, 0, 7700000.00, 6160000.00, 1, 1
UNION ALL SELECT 'MU1280', 'SET', 1.000, 1, 0, 3960000.00, 3960000.00, 1, 1
UNION ALL SELECT 'MU1281', 'UNIT', 1.000, 1, 0, 1144000.00, 832000.00, 1, 1
UNION ALL SELECT 'MU1282', 'SET', 1.000, 1, 0, 140000.00, 112000.00, 1, 1
UNION ALL SELECT 'MU1283', 'UNIT', 1.000, 1, 0, 993000.00, 735000.00, 1, 1
UNION ALL SELECT 'MU1284', 'UNIT', 1.000, 1, 0, 1167000.00, 833000.00, 1, 1
UNION ALL SELECT 'MU1285', 'PCS', 1.000, 1, 0, 205000.00, 160000.00, 1, 1
UNION ALL SELECT 'MU1286', 'PCS', 1.000, 1, 0, 185000.00, 131000.00, 1, 1
UNION ALL SELECT 'MU1287', 'KLG', 1.000, 1, 0, 59000.00, 39000.00, 1, 1
UNION ALL SELECT 'MU1288', 'PCS', 1.000, 1, 0, 23000.00, 12916.00, 1, 1
UNION ALL SELECT 'MU1289', 'PCS', 1.000, 1, 0, 23000.00, 16500.00, 1, 1
UNION ALL SELECT 'MU1290', 'PAIL', 1.000, 1, 0, 433000.00, 357700.00, 1, 1
UNION ALL SELECT 'MU1291', 'PCS', 1.000, 1, 0, 95000.00, 76000.00, 1, 1
UNION ALL SELECT 'MU1292', 'UNIT', 1.000, 1, 0, 5100000.00, 2805000.00, 1, 1
UNION ALL SELECT 'MU1293', 'UNIT', 1.000, 1, 0, 300000.00, 240000.00, 1, 1
UNION ALL SELECT 'MU1294', 'UNIT', 1.000, 1, 0, 2500000.00, 1625000.00, 1, 1
UNION ALL SELECT 'MU1295', 'PCS', 1.000, 1, 0, 61000.00, 45000.00, 1, 1
UNION ALL SELECT 'MU1296', 'PCS', 1.000, 1, 0, 30000.00, 21666.00, 1, 1
UNION ALL SELECT 'MU1297', 'PCS', 1.000, 1, 0, 16000.00, 10250.00, 1, 1
UNION ALL SELECT 'MU1298', 'LBR', 1.000, 1, 0, 44000.00, 44000.00, 1, 1
UNION ALL SELECT 'MU1299', 'MTR', 1.000, 1, 0, 71000.00, 46008.00, 1, 1
UNION ALL SELECT 'MU1300', 'SET', 1.000, 1, 0, 168000.00, 134400.00, 1, 1
UNION ALL SELECT 'MU1301', 'KLG', 1.000, 1, 0, 70000.00, 55200.00, 1, 1
UNION ALL SELECT 'MU1302', 'BTL', 1.000, 1, 0, 34000.00, 20900.00, 1, 1
UNION ALL SELECT 'MU1303', 'SET', 1.000, 1, 0, 140000.00, 97000.00, 1, 1
UNION ALL SELECT 'MU1304', 'KLG', 1.000, 1, 0, 84000.00, 59500.00, 1, 1
UNION ALL SELECT 'MU1305', 'PCS', 1.000, 1, 0, 149000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1306', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU1307', 'BKS', 1.000, 1, 0, 28000.00, 21000.00, 1, 1
UNION ALL SELECT 'MU1308', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1309', 'PCS', 1.000, 1, 0, 187000.00, 149600.00, 1, 1
UNION ALL SELECT 'MU1310', 'PCS', 1.000, 1, 0, 26000.00, 20800.00, 1, 1
UNION ALL SELECT 'MU1311', 'BTG', 1.000, 1, 0, 481000.00, 345000.00, 1, 1
UNION ALL SELECT 'MU1312', 'UNIT', 1.000, 1, 0, 748000.00, 480000.00, 1, 1
UNION ALL SELECT 'MU1313', 'SET', 1.000, 1, 0, 149000.00, 119200.00, 1, 1
UNION ALL SELECT 'MU1314', 'PCS', 1.000, 1, 0, 18000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU1315', 'PCS', 1.000, 1, 0, 27000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU1316', 'BTL', 1.000, 1, 0, 80000.00, 66100.00, 1, 1
UNION ALL SELECT 'MU1317', 'PCS', 1.000, 1, 0, 8000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU1318', 'PCS', 1.000, 1, 0, 11000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU1319', 'PCS', 1.000, 1, 0, 75000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU1320', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1321', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1322', 'PCS', 1.000, 1, 0, 27000.00, 21600.00, 1, 1
UNION ALL SELECT 'MU1323', 'PCS', 1.000, 1, 0, 6000.00, 1750.00, 1, 1
UNION ALL SELECT 'MU1324', 'PCS', 1.000, 1, 0, 129000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1325', 'PCS', 1.000, 1, 0, 22000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU1326', 'PCS', 1.000, 1, 0, 30000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU1327', 'PCS', 1.000, 1, 0, 37000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU1328', 'PCS', 1.000, 1, 0, 145000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU1329', 'PCS', 1.000, 1, 0, 36000.00, 23750.00, 1, 1
UNION ALL SELECT 'MU1330', 'PCS', 1.000, 1, 0, 23000.00, 11200.00, 1, 1
UNION ALL SELECT 'MU1331', 'PCS', 1.000, 1, 0, 28000.00, 13500.00, 1, 1
UNION ALL SELECT 'MU1332', 'PCS', 1.000, 1, 0, 129000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1333', 'PCS', 1.000, 1, 0, 23000.00, 17000.00, 1, 1
UNION ALL SELECT 'MU1334', 'PCS', 1.000, 1, 0, 17000.00, 13600.00, 1, 1
UNION ALL SELECT 'MU1335', 'PCS', 1.000, 1, 0, 47000.00, 31250.00, 1, 1
UNION ALL SELECT 'MU1336', 'SET', 1.000, 1, 0, 12000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1337', 'SET', 1.000, 1, 0, 12000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1338', 'SET', 1.000, 1, 0, 27000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU1339', 'PCS', 1.000, 1, 0, 22000.00, 17600.00, 1, 1
UNION ALL SELECT 'MU1340', 'PCS', 1.000, 1, 0, 129000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1341', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1342', 'PCS', 1.000, 1, 0, 23000.00, 16800.00, 1, 1
UNION ALL SELECT 'MU1343', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1344', 'PCS', 1.000, 1, 0, 13000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1345', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1346', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1347', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1348', 'PCS', 1.000, 1, 0, 6000.00, 4800.00, 1, 1
UNION ALL SELECT 'MU1349', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1350', 'PCS', 1.000, 1, 0, 29000.00, 21000.00, 1, 1
UNION ALL SELECT 'MU1351', 'PCS', 1.000, 1, 0, 129000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1352', 'PCS', 1.000, 1, 0, 15000.00, 10500.00, 1, 1
UNION ALL SELECT 'MU1353', 'PCS', 1.000, 1, 0, 17000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1354', 'PCS', 1.000, 1, 0, 15000.00, 10500.00, 1, 1
UNION ALL SELECT 'MU1355', 'PCS', 1.000, 1, 0, 4000.00, 3200.00, 1, 1
UNION ALL SELECT 'MU1356', 'PCS', 1.000, 1, 0, 28000.00, 21150.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU1357' AS sku, 'PCS' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 20000.00 AS selling_price, 18000.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU1358', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1359', 'PCS', 1.000, 1, 0, 129000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1360', 'PCS', 1.000, 1, 0, 14000.00, 7150.00, 1, 1
UNION ALL SELECT 'MU1361', 'PCS', 1.000, 1, 0, 19000.00, 12800.00, 1, 1
UNION ALL SELECT 'MU1362', 'PCS', 1.000, 1, 0, 22000.00, 15200.00, 1, 1
UNION ALL SELECT 'MU1363', 'PCS', 1.000, 1, 0, 18000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1364', 'PCS', 1.000, 1, 0, 8000.00, 3600.00, 1, 1
UNION ALL SELECT 'MU1365', 'PCS', 1.000, 1, 0, 18000.00, 10377.00, 1, 1
UNION ALL SELECT 'MU1366', 'PCS', 1.000, 1, 0, 33000.00, 21450.00, 1, 1
UNION ALL SELECT 'MU1367', 'PCS', 1.000, 1, 0, 6000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU1368', 'DUS', 1.000, 1, 0, 17000.00, 8800.00, 1, 1
UNION ALL SELECT 'MU1369', 'PCS', 1.000, 1, 0, 115000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1370', 'DUS', 1.000, 1, 0, 22000.00, 14150.00, 1, 1
UNION ALL SELECT 'MU1371', 'BKS', 1.000, 1, 0, 8000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU1372', 'BKS', 1.000, 1, 0, 10000.00, 5500.00, 1, 1
UNION ALL SELECT 'MU1373', 'DUS', 1.000, 1, 0, 30000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1374', 'PCS', 1.000, 1, 0, 13000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1375', 'PCS', 1.000, 1, 0, 54000.00, 41800.00, 1, 1
UNION ALL SELECT 'MU1376', 'PAK', 1.000, 1, 0, 40000.00, 27500.00, 1, 1
UNION ALL SELECT 'MU1377', 'PAK', 1.000, 1, 0, 60000.00, 45400.00, 1, 1
UNION ALL SELECT 'MU1378', 'PCS', 1.000, 1, 0, 10000.00, 4500.00, 1, 1
UNION ALL SELECT 'MU1379', 'PCS', 1.000, 1, 0, 58000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU1380', 'PCS', 1.000, 1, 0, 115000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1381', 'PAK', 1.000, 1, 0, 2550000.00, 1700000.00, 1, 1
UNION ALL SELECT 'MU1382', 'PCS', 1.000, 1, 0, 3000.00, 1070.00, 1, 1
UNION ALL SELECT 'MU1383', 'PCS', 1.000, 1, 0, 15000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU1384', 'PCS', 1.000, 1, 0, 149000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1385', 'PCS', 1.000, 1, 0, 16000.00, 10178.00, 1, 1
UNION ALL SELECT 'MU1386', 'PCS', 1.000, 1, 0, 15000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU1388', 'PCS', 1.000, 1, 0, 16000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU1389', 'PCS', 1.000, 1, 0, 149000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1390', 'PCS', 1.000, 1, 0, 4000.00, 2113.00, 1, 1
UNION ALL SELECT 'MU1391', 'PCS', 1.000, 1, 0, 5000.00, 2084.00, 1, 1
UNION ALL SELECT 'MU1392', 'PCS', 1.000, 1, 0, 45000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU1393', 'PCS', 1.000, 1, 0, 3000.00, 2500.00, 1, 1
UNION ALL SELECT 'MU1394', 'PCS', 1.000, 1, 0, 5000.00, 2741.00, 1, 1
UNION ALL SELECT 'MU1395', 'PCS', 1.000, 1, 0, 59000.00, 38890.00, 1, 1
UNION ALL SELECT 'MU1396', 'PCS', 1.000, 1, 0, 5000.00, 2350.00, 1, 1
UNION ALL SELECT 'MU1397', 'PCS', 1.000, 1, 0, 6000.00, 3734.00, 1, 1
UNION ALL SELECT 'MU1398', 'PCS', 1.000, 1, 0, 149000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1399', 'PCS', 1.000, 1, 0, 6000.00, 4620.00, 1, 1
UNION ALL SELECT 'MU1400', 'PCS', 1.000, 1, 0, 55000.00, 42300.00, 1, 1
UNION ALL SELECT 'MU1401', 'PCS', 1.000, 1, 0, 11000.00, 8090.00, 1, 1
UNION ALL SELECT 'MU1402', 'DUS', 1.000, 1, 0, 143000.00, 110000.00, 1, 1
UNION ALL SELECT 'MU1403', 'PCS', 1.000, 1, 0, 10000.00, 7200.00, 1, 1
UNION ALL SELECT 'MU1404', 'PCS', 1.000, 1, 0, 7000.00, 5070.00, 1, 1
UNION ALL SELECT 'MU1405', 'PCS', 1.000, 1, 0, 7000.00, 4880.00, 1, 1
UNION ALL SELECT 'MU1406', 'PCS', 1.000, 1, 0, 7000.00, 4610.00, 1, 1
UNION ALL SELECT 'MU1407', 'PCS', 1.000, 1, 0, 17000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1408', 'PCS', 1.000, 1, 0, 26000.00, 18820.00, 1, 1
UNION ALL SELECT 'MU1409', 'PCS', 1.000, 1, 0, 16000.00, 11540.00, 1, 1
UNION ALL SELECT 'MU1410', 'PCS', 1.000, 1, 0, 20000.00, 14834.00, 1, 1
UNION ALL SELECT 'MU1411', 'PCS', 1.000, 1, 0, 51000.00, 37370.00, 1, 1
UNION ALL SELECT 'MU1412', 'PCS', 1.000, 1, 0, 32000.00, 20690.00, 1, 1
UNION ALL SELECT 'MU1413', 'PCS', 1.000, 1, 0, 69000.00, 49190.00, 1, 1
UNION ALL SELECT 'MU1414', 'PCS', 1.000, 1, 0, 19000.00, 15200.00, 1, 1
UNION ALL SELECT 'MU1415', 'PCS', 1.000, 1, 0, 88000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1416', 'PCS', 1.000, 1, 0, 45000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU1417', 'PCS', 1.000, 1, 0, 34000.00, 27200.00, 1, 1
UNION ALL SELECT 'MU1418', 'PCS', 1.000, 1, 0, 50000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU1419', 'PCS', 1.000, 1, 0, 6000.00, 4690.00, 1, 1
UNION ALL SELECT 'MU1420', 'PCS', 1.000, 1, 0, 9000.00, 7116.00, 1, 1
UNION ALL SELECT 'MU1421', 'KLG', 1.000, 1, 0, 20000.00, 17000.00, 1, 1
UNION ALL SELECT 'MU1422', 'PCS', 1.000, 1, 0, 11000.00, 8697.00, 1, 1
UNION ALL SELECT 'MU1423', 'PCS', 1.000, 1, 0, 12000.00, 9954.00, 1, 1
UNION ALL SELECT 'MU1424', 'PCS', 1.000, 1, 0, 10000.00, 7898.00, 1, 1
UNION ALL SELECT 'MU1425', 'PCS', 1.000, 1, 0, 58000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1426', 'PCS', 1.000, 1, 0, 15000.00, 11815.00, 1, 1
UNION ALL SELECT 'MU1427', 'PCS', 1.000, 1, 0, 19000.00, 15730.00, 1, 1
UNION ALL SELECT 'MU1428', 'PCS', 1.000, 1, 0, 47000.00, 36150.00, 1, 1
UNION ALL SELECT 'MU1429', 'PCS', 1.000, 1, 0, 20000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1430', 'PCS', 1.000, 1, 0, 28000.00, 21320.00, 1, 1
UNION ALL SELECT 'MU1431', 'PCS', 1.000, 1, 0, 20000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU1432', 'PCS', 1.000, 1, 0, 32000.00, 22500.00, 1, 1
UNION ALL SELECT 'MU1433', 'PCS', 1.000, 1, 0, 7000.00, 5398.00, 1, 1
UNION ALL SELECT 'MU1434', 'PCS', 1.000, 1, 0, 14000.00, 10040.00, 1, 1
UNION ALL SELECT 'MU1435', 'PCS', 1.000, 1, 0, 80000.00, 58950.00, 1, 1
UNION ALL SELECT 'MU1436', 'PCS', 1.000, 1, 0, 29000.00, 21040.00, 1, 1
UNION ALL SELECT 'MU1437', 'PCS', 1.000, 1, 0, 9000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1438', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU1439', 'PCS', 1.000, 1, 0, 5000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU1440', 'PCS', 1.000, 1, 0, 37000.00, 24583.00, 1, 1
UNION ALL SELECT 'MU1441', 'PCS', 1.000, 1, 0, 15000.00, 10830.00, 1, 1
UNION ALL SELECT 'MU1442', 'PCS', 1.000, 1, 0, 605000.00, 505.00, 1, 1
UNION ALL SELECT 'MU1443', 'PCS', 1.000, 1, 0, 18000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1444', 'PCS', 1.000, 1, 0, 18000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1445', 'PCS', 1.000, 1, 0, 15000.00, 10760.00, 1, 1
UNION ALL SELECT 'MU1446', 'PCS', 1.000, 1, 0, 30000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1447', 'PCS', 1.000, 1, 0, 40000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1448', 'PCS', 1.000, 1, 0, 46000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1449', 'PCS', 1.000, 1, 0, 38000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1450', 'SET', 1.000, 1, 0, 53000.00, 19573.00, 1, 1
UNION ALL SELECT 'MU1451', 'MTR', 1.000, 1, 0, 19000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU1452', 'PCS', 1.000, 1, 0, 12000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1453', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1454', 'PCS', 1.000, 1, 0, 338000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1455', 'PCS', 1.000, 1, 0, 27000.00, 19980.00, 1, 1
UNION ALL SELECT 'MU1456', 'BTL', 1.000, 1, 0, 64000.00, 53300.00, 1, 1
UNION ALL SELECT 'MU1457', 'BTL', 1.000, 1, 0, 48000.00, 38400.00, 1, 1
UNION ALL SELECT 'MU1458', 'BTL', 1.000, 1, 0, 13000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1459', 'BTL', 1.000, 1, 0, 69000.00, 57500.00, 1, 1
UNION ALL SELECT 'MU1460', 'BTL', 1.000, 1, 0, 313000.00, 260750.00, 1, 1
UNION ALL SELECT 'MU1461', 'BTL', 1.000, 1, 0, 63000.00, 52500.00, 1, 1
UNION ALL SELECT 'MU1462', 'BTL', 1.000, 1, 0, 71000.00, 58700.00, 1, 1
UNION ALL SELECT 'MU1463', 'PCS', 1.000, 1, 0, 35000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU1464', 'PCS', 1.000, 1, 0, 30000.00, 22000.00, 1, 1
UNION ALL SELECT 'MU1465', 'SET', 1.000, 1, 0, 145000.00, 108000.00, 1, 1
UNION ALL SELECT 'MU1466', 'PCS', 1.000, 1, 0, 26000.00, 18108.00, 1, 1
UNION ALL SELECT 'MU1467', 'PCS', 1.000, 1, 0, 48000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU1468', 'PCS', 1.000, 1, 0, 28000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1469', 'PCS', 1.000, 1, 0, 28000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1470', 'PCS', 1.000, 1, 0, 28000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1471', 'PCS', 1.000, 1, 0, 81000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1472', 'UNIT', 1.000, 1, 0, 187000.00, 105000.00, 1, 1
UNION ALL SELECT 'MU1473', 'UNIT', 1.000, 1, 0, 200000.00, 115000.00, 1, 1
UNION ALL SELECT 'MU1474', 'UNIT', 1.000, 1, 0, 287000.00, 180000.00, 1, 1
UNION ALL SELECT 'MU1475', 'PCS', 1.000, 1, 0, 3000.00, 1750.00, 1, 1
UNION ALL SELECT 'MU1476', 'PCS', 1.000, 1, 0, 3000.00, 1750.00, 1, 1
UNION ALL SELECT 'MU1477', 'SET', 1.000, 1, 0, 120000.00, 96000.00, 1, 1
UNION ALL SELECT 'MU1478', 'PCS', 1.000, 1, 0, 210000.00, 144000.00, 1, 1
UNION ALL SELECT 'MU1479', 'PCS', 1.000, 1, 0, 15000.00, 5594.00, 1, 1
UNION ALL SELECT 'MU1480', 'PCS', 1.000, 1, 0, 16000.00, 5874.00, 1, 1
UNION ALL SELECT 'MU1481', 'PCS', 1.000, 1, 0, 27000.00, 13151.00, 1, 1
UNION ALL SELECT 'MU1482', 'UNIT', 1.000, 1, 0, 4146000.00, 3136350.00, 1, 1
UNION ALL SELECT 'MU1483', 'UNIT', 1.000, 1, 0, 1990000.00, 1505100.00, 1, 1
UNION ALL SELECT 'MU1484', 'PCS', 1.000, 1, 0, 13000.00, 8100.00, 1, 1
UNION ALL SELECT 'MU1485', 'GEN', 1.000, 1, 0, 50000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU1486', 'BTL', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1487', 'PCS', 1.000, 1, 0, 60000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1488', 'PCS', 1.000, 1, 0, 54000.00, 43200.00, 1, 1
UNION ALL SELECT 'MU1489', 'PCS', 1.000, 1, 0, 35000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU1490', 'PCS', 1.000, 1, 0, 50000.00, 34000.00, 1, 1
UNION ALL SELECT 'MU1491', 'PCS', 1.000, 1, 0, 39000.00, 31200.00, 1, 1
UNION ALL SELECT 'MU1492', 'PCS', 1.000, 1, 0, 60000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU1493', 'PCS', 1.000, 1, 0, 33000.00, 24831.00, 1, 1
UNION ALL SELECT 'MU1494', 'PCS', 1.000, 1, 0, 112000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1495', 'PCS', 1.000, 1, 0, 97000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1496', 'PCS', 1.000, 1, 0, 60000.00, 45000.00, 1, 1
UNION ALL SELECT 'MU1497', 'PCS', 1.000, 1, 0, 20000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU1498', 'PCS', 1.000, 1, 0, 20000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU1499', 'PCS', 1.000, 1, 0, 20000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU1500', 'SET', 1.000, 1, 0, 7000.00, 3333.00, 1, 1
UNION ALL SELECT 'MU1501', 'PCS', 1.000, 1, 0, 10000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU1502', 'PCS', 1.000, 1, 0, 12000.00, 9600.00, 1, 1
UNION ALL SELECT 'MU1503', 'PCS', 1.000, 1, 0, 9000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1504', 'PCS', 1.000, 1, 0, 35000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1505', 'PCS', 1.000, 1, 0, 8000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1506', 'PCS', 1.000, 1, 0, 257000.00, 190000.00, 1, 1
UNION ALL SELECT 'MU1507', 'PCS', 1.000, 1, 0, 50000.00, 40000.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU1508' AS sku, 'PAIL' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 368000.00 AS selling_price, 275200.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU1509', 'POT', 1.000, 1, 0, 86000.00, 65400.00, 1, 1
UNION ALL SELECT 'MU1510', 'PCS', 1.000, 1, 0, 46000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU1511', 'KLG', 1.000, 1, 0, 254000.00, 254000.00, 1, 1
UNION ALL SELECT 'MU1512', 'PCS', 1.000, 1, 0, 5000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU1513', 'PCS', 1.000, 1, 0, 223000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1514', 'PCS', 1.000, 1, 0, 37000.00, 24230.00, 1, 1
UNION ALL SELECT 'MU1515', 'PCS', 1.000, 1, 0, 8000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1516', 'PCS', 1.000, 1, 0, 4000.00, 1916.00, 1, 1
UNION ALL SELECT 'MU1517', 'PCS', 1.000, 1, 0, 8000.00, 2916.00, 1, 1
UNION ALL SELECT 'MU1518', 'PCS', 1.000, 1, 0, 12000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1519', 'SET', 1.000, 1, 0, 95000.00, 81400.00, 1, 1
UNION ALL SELECT 'MU1520', 'UNIT', 1.000, 1, 0, 1500000.00, 1200000.00, 1, 1
UNION ALL SELECT 'MU1521', 'PCS', 1.000, 1, 0, 5000.00, 2083.00, 1, 1
UNION ALL SELECT 'MU1522', 'PCS', 1.000, 1, 0, 75000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU1523', 'POT', 1.000, 1, 0, 77000.00, 60400.00, 1, 1
UNION ALL SELECT 'MU1524', 'SET', 1.000, 1, 0, 260000.00, 224000.00, 1, 1
UNION ALL SELECT 'MU1525', 'PCS', 1.000, 1, 0, 70000.00, 56000.00, 1, 1
UNION ALL SELECT 'MU1526', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1527', 'KLG', 1.000, 1, 0, 144000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU1528', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1529', 'PCS', 1.000, 1, 0, 17000.00, 13600.00, 1, 1
UNION ALL SELECT 'MU1530', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1532', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU1533', 'PCS', 1.000, 1, 0, 50000.00, 52000.00, 1, 1
UNION ALL SELECT 'MU1534', 'PCS', 1.000, 1, 0, 40000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU1535', 'LBR', 1.000, 1, 0, 102500.00, 102500.00, 1, 1
UNION ALL SELECT 'MU1536', 'LBR', 1.000, 1, 0, 68500.00, 68500.00, 1, 1
UNION ALL SELECT 'MU1537', 'PCS', 1.000, 1, 0, 176000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU1538', 'PCS', 1.000, 1, 0, 143000.00, 71500.00, 1, 1
UNION ALL SELECT 'MU1539', 'PCS', 1.000, 1, 0, 107000.00, 53350.00, 1, 1
UNION ALL SELECT 'MU1540', 'PCS', 1.000, 1, 0, 18000.00, 11200.00, 1, 1
UNION ALL SELECT 'MU1541', 'KLG', 1.000, 1, 0, 144000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU1542', 'KLG', 1.000, 1, 0, 144000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU1543', 'KLG', 1.000, 1, 0, 144000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU1544', 'PCS', 1.000, 1, 0, 9000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU1545', 'MTR', 1.000, 1, 0, 94000.00, 59752.00, 1, 1
UNION ALL SELECT 'MU1546', 'PCS', 1.000, 1, 0, 33000.00, 23200.00, 1, 1
UNION ALL SELECT 'MU1547', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU1548', 'MTR', 1.000, 1, 0, 94000.00, 59752.00, 1, 1
UNION ALL SELECT 'MU1549', 'LBR', 1.000, 1, 0, 45000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU1550', 'PCS', 1.000, 1, 0, 18000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1551', 'PCS', 1.000, 1, 0, 18000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1552', 'PCS', 1.000, 1, 0, 18000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1553', 'PCS', 1.000, 1, 0, 46000.00, 46000.00, 1, 1
UNION ALL SELECT 'MU1554', 'PCS', 1.000, 1, 0, 11000.00, 11000.00, 1, 1
UNION ALL SELECT 'MU1555', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1556', 'PCS', 1.000, 1, 0, 18000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1557', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1558', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1559', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1560', 'PCS', 1.000, 1, 0, 8000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU1561', 'SET', 1.000, 1, 0, 132000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1562', 'LBR', 1.000, 1, 0, 7000.00, 2432.00, 1, 1
UNION ALL SELECT 'MU1563', 'DUS', 1.000, 1, 0, 89000.00, 63200.00, 1, 1
UNION ALL SELECT 'MU1564', 'PCS', 1.000, 1, 0, 78000.00, 41750.00, 1, 1
UNION ALL SELECT 'MU1565', 'PCS', 1.000, 1, 0, 7000.00, 3300.00, 1, 1
UNION ALL SELECT 'MU1566', 'PCS', 1.000, 1, 0, 37000.00, 24500.00, 1, 1
UNION ALL SELECT 'MU1567', 'PCS', 1.000, 1, 0, 30000.00, 14195.00, 1, 1
UNION ALL SELECT 'MU1568', 'PCS', 1.000, 1, 0, 35000.00, 21935.00, 1, 1
UNION ALL SELECT 'MU1569', 'PCS', 1.000, 1, 0, 16000.00, 10826.00, 1, 1
UNION ALL SELECT 'MU1570', 'UNIT', 1.000, 1, 0, 250000.00, 157500.00, 1, 1
UNION ALL SELECT 'MU1571', 'PCS', 1.000, 1, 0, 17000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1572', 'PCS', 1.000, 1, 0, 78000.00, 57698.00, 1, 1
UNION ALL SELECT 'MU1573', 'UNIT', 1.000, 1, 0, 1527000.00, 1020333.00, 1, 1
UNION ALL SELECT 'MU1574', 'PCS', 1.000, 1, 0, 21000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU1575', 'PCS', 1.000, 1, 0, 60000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU1576', 'PCS', 1.000, 1, 0, 145000.00, 116000.00, 1, 1
UNION ALL SELECT 'MU1577', 'PAIL', 1.000, 1, 0, 433000.00, 324090.00, 1, 1
UNION ALL SELECT 'MU1578', 'PCS', 1.000, 1, 0, 145000.00, 116000.00, 1, 1
UNION ALL SELECT 'MU1579', 'PCS', 1.000, 1, 0, 50000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU1580', 'PCS', 1.000, 1, 0, 25000.00, 13250.00, 1, 1
UNION ALL SELECT 'MU1581', 'PCS', 1.000, 1, 0, 4000.00, 381.00, 1, 1
UNION ALL SELECT 'MU1582', 'PCS', 1.000, 1, 0, 145000.00, 94250.00, 1, 1
UNION ALL SELECT 'MU1583', 'DUS', 1.000, 1, 0, 77000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU1585', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1586', 'PCS', 1.000, 1, 0, 44000.00, 35200.00, 1, 1
UNION ALL SELECT 'MU1587', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1588', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU1589', 'PCS', 1.000, 1, 0, 35000.00, 27000.00, 1, 1
UNION ALL SELECT 'MU1590', 'PCS', 1.000, 1, 0, 150000.00, 120000.00, 1, 1
UNION ALL SELECT 'MU1591', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1592', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1593', 'PCS', 1.000, 1, 0, 23000.00, 16500.00, 1, 1
UNION ALL SELECT 'MU1594', 'PCS', 1.000, 1, 0, 8000.00, 4800.00, 1, 1
UNION ALL SELECT 'MU1595', 'PCS', 1.000, 1, 0, 28000.00, 13703.00, 1, 1
UNION ALL SELECT 'MU1596', 'PCS', 1.000, 1, 0, 23000.00, 9600.00, 1, 1
UNION ALL SELECT 'MU1597', 'PCS', 1.000, 1, 0, 21000.00, 8800.00, 1, 1
UNION ALL SELECT 'MU1598', 'PCS', 1.000, 1, 0, 19000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1599', 'PCS', 1.000, 1, 0, 19000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1600', 'PCS', 1.000, 1, 0, 15000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU1601', 'UNIT', 1.000, 1, 0, 415000.00, 332000.00, 1, 1
UNION ALL SELECT 'MU1602', 'LBR', 1.000, 1, 0, 7000.00, 2342.00, 1, 1
UNION ALL SELECT 'MU1603', 'DUS', 1.000, 1, 0, 106000.00, 74599.00, 1, 1
UNION ALL SELECT 'MU1604', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1605', 'UNIT', 1.000, 1, 0, 28000.00, 17500.00, 1, 1
UNION ALL SELECT 'MU1606', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1607', 'PCS', 1.000, 1, 0, 78000.00, 57500.00, 1, 1
UNION ALL SELECT 'MU1608', 'PCS', 1.000, 1, 0, 85000.00, 62500.00, 1, 1
UNION ALL SELECT 'MU1609', 'PCS', 1.000, 1, 0, 145000.00, 65000.00, 1, 1
UNION ALL SELECT 'MU1610', 'PCS', 1.000, 1, 0, 35000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU1611', 'DUS', 1.000, 1, 0, 75000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU1612', 'DUS', 1.000, 1, 0, 120000.00, 120000.00, 1, 1
UNION ALL SELECT 'MU1613', 'DUS', 1.000, 1, 0, 120000.00, 120000.00, 1, 1
UNION ALL SELECT 'MU1614', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1615', 'SET', 1.000, 1, 0, 149000.00, 119200.00, 1, 1
UNION ALL SELECT 'MU1616', 'DUS', 1.000, 1, 0, 135000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU1617', 'PCS', 1.000, 1, 0, 75000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU1618', 'PCS', 1.000, 1, 0, 70000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU1619', 'PCS', 1.000, 1, 0, 46000.00, 32500.00, 1, 1
UNION ALL SELECT 'MU1620', 'PCS', 1.000, 1, 0, 46000.00, 36800.00, 1, 1
UNION ALL SELECT 'MU1621', 'PCS', 1.000, 1, 0, 87000.00, 87000.00, 1, 1
UNION ALL SELECT 'MU1622', 'PCS', 1.000, 1, 0, 8000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU1623', 'PCS', 1.000, 1, 0, 17000.00, 13600.00, 1, 1
UNION ALL SELECT 'MU1624', 'PCS', 1.000, 1, 0, 28000.00, 22400.00, 1, 1
UNION ALL SELECT 'MU1625', 'PAIL', 1.000, 1, 0, 876000.00, 440000.00, 1, 1
UNION ALL SELECT 'MU1626', 'PAIL', 1.000, 1, 0, 876000.00, 440000.00, 1, 1
UNION ALL SELECT 'MU1627', 'PCS', 1.000, 1, 0, 13000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1628', 'PAIL', 1.000, 1, 0, 876000.00, 440000.00, 1, 1
UNION ALL SELECT 'MU1629', 'PAIL', 1.000, 1, 0, 876000.00, 440000.00, 1, 1
UNION ALL SELECT 'MU1630', 'PAIL', 1.000, 1, 0, 876000.00, 440000.00, 1, 1
UNION ALL SELECT 'MU1631', 'PAIL', 1.000, 1, 0, 876000.00, 440000.00, 1, 1
UNION ALL SELECT 'MU1632', 'PAIL', 1.000, 1, 0, 876000.00, 440000.00, 1, 1
UNION ALL SELECT 'MU1633', 'PAIL', 1.000, 1, 0, 876000.00, 440000.00, 1, 1
UNION ALL SELECT 'MU1634', 'POT', 1.000, 1, 0, 77000.00, 60400.00, 1, 1
UNION ALL SELECT 'MU1635', 'PCS', 1.000, 1, 0, 41000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU1636', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1637', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1638', 'PCS', 1.000, 1, 0, 8000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU1639', 'PCS', 1.000, 1, 0, 15000.00, 9600.00, 1, 1
UNION ALL SELECT 'MU1640', 'PCS', 1.000, 1, 0, 6000.00, 2000.00, 1, 1
UNION ALL SELECT 'MU1641', 'PCS', 1.000, 1, 0, 12000.00, 6500.00, 1, 1
UNION ALL SELECT 'MU1642', 'PCS', 1.000, 1, 0, 15000.00, 8750.00, 1, 1
UNION ALL SELECT 'MU1643', 'PCS', 1.000, 1, 0, 7000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU1644', 'PCS', 1.000, 1, 0, 8000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU1645', 'DUS', 1.000, 1, 0, 25000.00, 12500.00, 1, 1
UNION ALL SELECT 'MU1646', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1647', 'PAIL', 1.000, 1, 0, 286000.00, 225200.00, 1, 1
UNION ALL SELECT 'MU1648', 'MTR', 1.000, 1, 0, 105000.00, 72000.00, 1, 1
UNION ALL SELECT 'MU1649', 'ROL', 1.000, 1, 0, 80000.00, 58500.00, 1, 1
UNION ALL SELECT 'MU1650', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU1651', 'PCS', 1.000, 1, 0, 35000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU1652', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU1653', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU1654', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1655', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU1656', 'KLG', 1.000, 1, 0, 144000.00, 89355.00, 1, 1
UNION ALL SELECT 'MU1657', 'SET', 1.000, 1, 0, 20000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU1658', 'SET', 1.000, 1, 0, 90000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU1659', 'UNIT', 1.000, 1, 0, 437000.00, 311000.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU1660' AS sku, 'PCS' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 35000.00 AS selling_price, 28000.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU1661', 'KLG', 1.000, 1, 0, 85000.00, 68000.00, 1, 1
UNION ALL SELECT 'MU1662', 'PCS', 1.000, 1, 0, 46000.00, 33930.00, 1, 1
UNION ALL SELECT 'MU1663', 'PCS', 1.000, 1, 0, 27000.00, 21600.00, 1, 1
UNION ALL SELECT 'MU1664', 'SET', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1665', 'PCS', 1.000, 1, 0, 30000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU1666', 'PCS', 1.000, 1, 0, 50000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU1667', 'PCS', 1.000, 1, 0, 30000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU1668', 'SET', 1.000, 1, 0, 10000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1669', 'PCS', 1.000, 1, 0, 79000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU1670', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1671', 'MTR', 1.000, 1, 0, 20000.00, 3000.00, 1, 1
UNION ALL SELECT 'MU1672', 'SET', 1.000, 1, 0, 166000.00, 115000.00, 1, 1
UNION ALL SELECT 'MU1673', 'PCS', 1.000, 1, 0, 24000.00, 19000.00, 1, 1
UNION ALL SELECT 'MU1674', 'PCS', 1.000, 1, 0, 11000.00, 7975.00, 1, 1
UNION ALL SELECT 'MU1675', 'PCS', 1.000, 1, 0, 250000.00, 200000.00, 1, 1
UNION ALL SELECT 'MU1676', 'PAIL', 1.000, 1, 0, 240000.00, 192000.00, 1, 1
UNION ALL SELECT 'MU1677', 'PCS', 1.000, 1, 0, 69000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU1678', 'PCS', 1.000, 1, 0, 2000.00, 1418.00, 1, 1
UNION ALL SELECT 'MU1679', 'PCS', 1.000, 1, 0, 21000.00, 16800.00, 1, 1
UNION ALL SELECT 'MU1680', 'PCS', 1.000, 1, 0, 18000.00, 12700.00, 1, 1
UNION ALL SELECT 'MU1681', 'BTL', 1.000, 1, 0, 55000.00, 44000.00, 1, 1
UNION ALL SELECT 'MU1682', 'PCS', 1.000, 1, 0, 121000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU1683', 'PCS', 1.000, 1, 0, 5000.00, 2000.00, 1, 1
UNION ALL SELECT 'MU1684', 'PCS', 1.000, 1, 0, 200000.00, 144000.00, 1, 1
UNION ALL SELECT 'MU1685', 'PCS', 1.000, 1, 0, 23000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU1686', 'SET', 1.000, 1, 0, 90000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU1687', 'SET', 1.000, 1, 0, 69000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU1688', 'UNIT', 1.000, 1, 0, 1575000.00, 1750000.00, 1, 1
UNION ALL SELECT 'MU1689', 'PCS', 1.000, 1, 0, 20000.00, 15500.00, 1, 1
UNION ALL SELECT 'MU1690', 'PCS', 1.000, 1, 0, 23000.00, 16500.00, 1, 1
UNION ALL SELECT 'MU1691', 'PCS', 1.000, 1, 0, 7000.00, 5800.00, 1, 1
UNION ALL SELECT 'MU1692', 'PCS', 1.000, 1, 0, 60000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU1693', 'PCS', 1.000, 1, 0, 9000.00, 4200.00, 1, 1
UNION ALL SELECT 'MU1694', 'SET', 1.000, 1, 0, 166000.00, 115000.00, 1, 1
UNION ALL SELECT 'MU1695', 'SET', 1.000, 1, 0, 166000.00, 115000.00, 1, 1
UNION ALL SELECT 'MU1696', 'PCS', 1.000, 1, 0, 11000.00, 7200.00, 1, 1
UNION ALL SELECT 'MU1697', 'PCS', 1.000, 1, 0, 12000.00, 9600.00, 1, 1
UNION ALL SELECT 'MU1698', 'PCS', 1.000, 1, 0, 18000.00, 14400.00, 1, 1
UNION ALL SELECT 'MU1699', 'PCS', 1.000, 1, 0, 11000.00, 8800.00, 1, 1
UNION ALL SELECT 'MU1700', 'PCS', 1.000, 1, 0, 33000.00, 21600.00, 1, 1
UNION ALL SELECT 'MU1701', 'DUS', 1.000, 1, 0, 116000.00, 69500.00, 1, 1
UNION ALL SELECT 'MU1702', 'PCS', 1.000, 1, 0, 23000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1703', 'PAIL', 1.000, 1, 0, 876000.00, 440000.00, 1, 1
UNION ALL SELECT 'MU1711', 'KG', 1.000, 1, 0, 28000.00, 14000.00, 1, 1
UNION ALL SELECT 'MU1712', 'KG', 1.000, 1, 0, 37000.00, 19000.00, 1, 1
UNION ALL SELECT 'MU1713', 'SET', 1.000, 1, 0, 33000.00, 21500.00, 1, 1
UNION ALL SELECT 'MU1714', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1715', 'PCS', 1.000, 1, 0, 17000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU1716', 'PCS', 1.000, 1, 0, 20000.00, 11200.00, 1, 1
UNION ALL SELECT 'MU1717', 'PCS', 1.000, 1, 0, 25000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1718', 'PCS', 1.000, 1, 0, 22000.00, 11825.00, 1, 1
UNION ALL SELECT 'MU1719', 'PCS', 1.000, 1, 0, 70000.00, 56000.00, 1, 1
UNION ALL SELECT 'MU1720', 'PCS', 1.000, 1, 0, 22000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1721', 'PCS', 1.000, 1, 0, 22000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU1722', 'PCS', 1.000, 1, 0, 7000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU1723', 'UNIT', 1.000, 1, 0, 358000.00, 265000.00, 1, 1
UNION ALL SELECT 'MU1724', 'PCS', 1.000, 1, 0, 18000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1725', 'PCS', 1.000, 1, 0, 16000.00, 11200.00, 1, 1
UNION ALL SELECT 'MU1726', 'PAIL', 1.000, 1, 0, 310000.00, 237500.00, 1, 1
UNION ALL SELECT 'MU1727', 'PCS', 1.000, 1, 0, 61000.00, 44000.00, 1, 1
UNION ALL SELECT 'MU1728', 'PCS', 1.000, 1, 0, 43000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU1729', 'PCS', 1.000, 1, 0, 56000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU1730', 'PCS', 1.000, 1, 0, 8000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU1731', 'PCS', 1.000, 1, 0, 39000.00, 25800.00, 1, 1
UNION ALL SELECT 'MU1732', 'MTR', 1.000, 1, 0, 10000.00, 3000.00, 1, 1
UNION ALL SELECT 'MU1733', 'MTR', 1.000, 1, 0, 15000.00, 3000.00, 1, 1
UNION ALL SELECT 'MU1734', 'PCS', 1.000, 1, 0, 3000.00, 2252.00, 1, 1
UNION ALL SELECT 'MU1735', 'PCS', 1.000, 1, 0, 230000.00, 160000.00, 1, 1
UNION ALL SELECT 'MU1736', 'PCS', 1.000, 1, 0, 115000.00, 55000.00, 1, 1
UNION ALL SELECT 'MU1737', 'PCS', 1.000, 1, 0, 5000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU1738', 'PCS', 1.000, 1, 0, 6000.00, 2716.00, 1, 1
UNION ALL SELECT 'MU1739', 'PCS', 1.000, 1, 0, 6000.00, 3600.00, 1, 1
UNION ALL SELECT 'MU1740', 'SET', 1.000, 1, 0, 100000.00, 80000.00, 1, 1
UNION ALL SELECT 'MU1741', 'SET', 1.000, 1, 0, 45000.00, 38000.00, 1, 1
UNION ALL SELECT 'MU1742', 'PCS', 1.000, 1, 0, 47000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU1743', 'PCS', 1.000, 1, 0, 22000.00, 19000.00, 1, 1
UNION ALL SELECT 'MU1744', 'PAIL', 1.000, 1, 0, 1383000.00, 1024000.00, 1, 1
UNION ALL SELECT 'MU1745', 'PCS', 1.000, 1, 0, 5000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU1746', 'PCS', 1.000, 1, 0, 232000.00, 166000.00, 1, 1
UNION ALL SELECT 'MU1747', 'SET', 1.000, 1, 0, 150000.00, 102500.00, 1, 1
UNION ALL SELECT 'MU1748', 'SET', 1.000, 1, 0, 210000.00, 144000.00, 1, 1
UNION ALL SELECT 'MU1749', 'PCS', 1.000, 1, 0, 32000.00, 25600.00, 1, 1
UNION ALL SELECT 'MU1750', 'PCS', 1.000, 1, 0, 60000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU1751', 'PCS', 1.000, 1, 0, 13000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1752', 'PCS', 1.000, 1, 0, 13000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1753', 'PCS', 1.000, 1, 0, 13000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1754', 'PCS', 1.000, 1, 0, 14000.00, 11200.00, 1, 1
UNION ALL SELECT 'MU1755', 'PCS', 1.000, 1, 0, 14000.00, 11200.00, 1, 1
UNION ALL SELECT 'MU1756', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1757', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1758', 'PCS', 1.000, 1, 0, 16000.00, 12800.00, 1, 1
UNION ALL SELECT 'MU1759', 'PCS', 1.000, 1, 0, 16000.00, 12800.00, 1, 1
UNION ALL SELECT 'MU1760', 'PCS', 1.000, 1, 0, 18000.00, 14400.00, 1, 1
UNION ALL SELECT 'MU1761', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU1762', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU1763', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1764', 'SET', 1.000, 1, 0, 50000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU1765', 'SET', 1.000, 1, 0, 40000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU1766', 'SET', 1.000, 1, 0, 35000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU1767', 'PSG', 1.000, 1, 0, 30000.00, 10500.00, 1, 1
UNION ALL SELECT 'MU1768', 'PCS', 1.000, 1, 0, 13000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1769', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1770', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1771', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1772', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1773', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1774', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1775', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1776', 'PCS', 1.000, 1, 0, 35000.00, 18500.00, 1, 1
UNION ALL SELECT 'MU1777', 'PCS', 1.000, 1, 0, 32000.00, 20833.00, 1, 1
UNION ALL SELECT 'MU1778', 'PCS', 1.000, 1, 0, 32000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU1779', 'PCS', 1.000, 1, 0, 22000.00, 14166.00, 1, 1
UNION ALL SELECT 'MU1780', 'PCS', 1.000, 1, 0, 15000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1781', 'PCS', 1.000, 1, 0, 14000.00, 8541.00, 1, 1
UNION ALL SELECT 'MU1782', 'PCS', 1.000, 1, 0, 8000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU1783', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1784', 'PCS', 1.000, 1, 0, 117000.00, 72875.00, 1, 1
UNION ALL SELECT 'MU1785', 'PCS', 1.000, 1, 0, 8000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU1786', 'PCS', 1.000, 1, 0, 7000.00, 5600.00, 1, 1
UNION ALL SELECT 'MU1787', 'PCS', 1.000, 1, 0, 6000.00, 4800.00, 1, 1
UNION ALL SELECT 'MU1789', 'PCS', 1.000, 1, 0, 65000.00, 32175.00, 1, 1
UNION ALL SELECT 'MU1790', 'PCS', 1.000, 1, 0, 185000.00, 148000.00, 1, 1
UNION ALL SELECT 'MU1791', 'PCS', 1.000, 1, 0, 41000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU1792', 'PCS', 1.000, 1, 0, 17000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1793', 'BTG', 1.000, 1, 0, 100000.00, 80000.00, 1, 1
UNION ALL SELECT 'MU1794', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU1795', 'PCS', 1.000, 1, 0, 33000.00, 23200.00, 1, 1
UNION ALL SELECT 'MU1796', 'KLG', 1.000, 1, 0, 31000.00, 23496.00, 1, 1
UNION ALL SELECT 'MU1797', 'KLG', 1.000, 1, 0, 254000.00, 254000.00, 1, 1
UNION ALL SELECT 'MU1798', 'KLG', 1.000, 1, 0, 47000.00, 37600.00, 1, 1
UNION ALL SELECT 'MU1799', 'PSG', 1.000, 1, 0, 50000.00, 34400.00, 1, 1
UNION ALL SELECT 'MU1800', 'ROL', 1.000, 1, 0, 69000.00, 34500.00, 1, 1
UNION ALL SELECT 'MU1801', 'PCS', 1.000, 1, 0, 68000.00, 54400.00, 1, 1
UNION ALL SELECT 'MU1802', 'PCS', 1.000, 1, 0, 2000.00, 2000.00, 1, 1
UNION ALL SELECT 'MU1803', 'PCS', 1.000, 1, 0, 2000.00, 2000.00, 1, 1
UNION ALL SELECT 'MU1804', 'PCS', 1.000, 1, 0, 2000.00, 2000.00, 1, 1
UNION ALL SELECT 'MU1805', 'PCS', 1.000, 1, 0, 55000.00, 44000.00, 1, 1
UNION ALL SELECT 'MU1806', 'PCS', 1.000, 1, 0, 500.00, 0.00, 1, 1
UNION ALL SELECT 'MU1807', 'PCS', 1.000, 1, 0, 37000.00, 27590.00, 1, 1
UNION ALL SELECT 'MU1808', 'PCS', 1.000, 1, 0, 31000.00, 19200.00, 1, 1
UNION ALL SELECT 'MU1809', 'PCS', 1.000, 1, 0, 20000.00, 9300.00, 1, 1
UNION ALL SELECT 'MU1810', 'PCS', 1.000, 1, 0, 578000.00, 385000.00, 1, 1
UNION ALL SELECT 'MU1811', 'PCS', 1.000, 1, 0, 18000.00, 8166.00, 1, 1
UNION ALL SELECT 'MU1812', 'SAK', 1.000, 1, 0, 130000.00, 107800.00, 1, 1
UNION ALL SELECT 'MU1813', 'PCS', 1.000, 1, 0, 20000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU1814', 'LBR', 1.000, 1, 0, 65000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU1815', 'PCS', 1.000, 1, 0, 15000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU1816', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1817', 'PCS', 1.000, 1, 0, 15000.00, 8900.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU1818' AS sku, 'ROL' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 98000.00 AS selling_price, 65000.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU1819', 'PCS', 1.000, 1, 0, 6000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU1820', 'UNIT', 1.000, 1, 0, 1890000.00, 1450000.00, 1, 1
UNION ALL SELECT 'MU1821', 'PCS', 1.000, 1, 0, 276000.00, 184000.00, 1, 1
UNION ALL SELECT 'MU1822', 'PCS', 1.000, 1, 0, 45000.00, 27500.00, 1, 1
UNION ALL SELECT 'MU1823', 'MTR', 1.000, 1, 0, 5000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU1824', 'UNIT', 1.000, 1, 0, 1380000.00, 639000.00, 1, 1
UNION ALL SELECT 'MU1825', 'PCS', 1.000, 1, 0, 110000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU1826', 'PCS', 1.000, 1, 0, 115000.00, 72000.00, 1, 1
UNION ALL SELECT 'MU1827', 'SET', 1.000, 1, 0, 150000.00, 120000.00, 1, 1
UNION ALL SELECT 'MU1828', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1831', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU1832', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1833', 'SET', 1.000, 1, 0, 115000.00, 100000.00, 1, 1
UNION ALL SELECT 'MU1834', 'KLG', 1.000, 1, 0, 124000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU1835', 'SET', 1.000, 1, 0, 150000.00, 102500.00, 1, 1
UNION ALL SELECT 'MU1836', 'BKS', 1.000, 1, 0, 80000.00, 42350.00, 1, 1
UNION ALL SELECT 'MU1837', 'BKS', 1.000, 1, 0, 20000.00, 11200.00, 1, 1
UNION ALL SELECT 'MU1838', 'SET', 1.000, 1, 0, 6500000.00, 5200000.00, 1, 1
UNION ALL SELECT 'MU1839', 'SET', 1.000, 1, 0, 2200000.00, 1760000.00, 1, 1
UNION ALL SELECT 'MU1840', 'SET', 1.000, 1, 0, 111000.00, 73500.00, 1, 1
UNION ALL SELECT 'MU1842', 'SET', 1.000, 1, 0, 380000.00, 304000.00, 1, 1
UNION ALL SELECT 'MU1843', 'PCS', 1.000, 1, 0, 95000.00, 72000.00, 1, 1
UNION ALL SELECT 'MU1844', 'PCS', 1.000, 1, 0, 30000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU1845', 'PCS', 1.000, 1, 0, 8000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1846', 'PCS', 1.000, 1, 0, 90000.00, 72000.00, 1, 1
UNION ALL SELECT 'MU1847', 'LBR', 1.000, 1, 0, 117000.00, 95000.00, 1, 1
UNION ALL SELECT 'MU1848', 'PCS', 1.000, 1, 0, 4000.00, 950000.00, 1, 1
UNION ALL SELECT 'MU1849', 'PCS', 1.000, 1, 0, 15000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU1850', 'KLG', 1.000, 1, 0, 43000.00, 33000.00, 1, 1
UNION ALL SELECT 'MU1851', 'BTG', 1.000, 1, 0, 203000.00, 150220.00, 1, 1
UNION ALL SELECT 'MU1852', 'DUS', 1.000, 1, 0, 112000.00, 66500.00, 1, 1
UNION ALL SELECT 'MU1853', 'KLG', 1.000, 1, 0, 53000.00, 42000.00, 1, 1
UNION ALL SELECT 'MU1854', 'KLG', 1.000, 1, 0, 211000.00, 166000.00, 1, 1
UNION ALL SELECT 'MU1855', 'KLG', 1.000, 1, 0, 48000.00, 38000.00, 1, 1
UNION ALL SELECT 'MU1856', 'KLG', 1.000, 1, 0, 227000.00, 179000.00, 1, 1
UNION ALL SELECT 'MU1857', 'KLG', 1.000, 1, 0, 248000.00, 190100.00, 1, 1
UNION ALL SELECT 'MU1858', 'KLG', 1.000, 1, 0, 208000.00, 159500.00, 1, 1
UNION ALL SELECT 'MU1859', 'LBR', 1.000, 1, 0, 49000.00, 36932.00, 1, 1
UNION ALL SELECT 'MU1860', 'PCS', 1.000, 1, 0, 3000.00, 1500.00, 1, 1
UNION ALL SELECT 'MU1861', 'PCS', 1.000, 1, 0, 5000.00, 2500.00, 1, 1
UNION ALL SELECT 'MU1862', 'DUS', 1.000, 1, 0, 108000.00, 72399.00, 1, 1
UNION ALL SELECT 'MU1863', 'PCS', 1.000, 1, 0, 30000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1864', 'PCS', 1.000, 1, 0, 30000.00, 46400.00, 1, 1
UNION ALL SELECT 'MU1865', 'SET', 1.000, 1, 0, 86000.00, 63000.00, 1, 1
UNION ALL SELECT 'MU1866', 'PCS', 1.000, 1, 0, 10000.00, 0.00, 1, 1
UNION ALL SELECT 'MU1867', 'PCS', 1.000, 1, 0, 17000.00, 8300.00, 1, 1
UNION ALL SELECT 'MU1869', 'GLN', 1.000, 1, 0, 144000.00, 119000.00, 1, 1
UNION ALL SELECT 'MU1870', 'SET', 1.000, 1, 0, 98000.00, 65000.00, 1, 1
UNION ALL SELECT 'MU1871', 'UNIT', 1.000, 1, 0, 1000000.00, 801300.00, 1, 1
UNION ALL SELECT 'MU1872', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1873', 'PCS', 1.000, 1, 0, 15000.00, 7500.00, 1, 1
UNION ALL SELECT 'MU1874', 'PCS', 1.000, 1, 0, 9000.00, 4500.00, 1, 1
UNION ALL SELECT 'MU1875', 'PCS', 1.000, 1, 0, 8000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU1876', 'SET', 1.000, 1, 0, 23000.00, 13333.00, 1, 1
UNION ALL SELECT 'MU1877', 'LBR', 1.000, 1, 0, 601000.00, 462160.00, 1, 1
UNION ALL SELECT 'MU1878', 'UNIT', 1.000, 1, 0, 2725000.00, 1550000.00, 1, 1
UNION ALL SELECT 'MU1879', 'UNIT', 1.000, 1, 0, 1045000.00, 995000.00, 1, 1
UNION ALL SELECT 'MU1880', 'UNIT', 1.000, 1, 0, 5198000.00, 4950000.00, 1, 1
UNION ALL SELECT 'MU1881', 'UNIT', 1.000, 1, 0, 2641000.00, 2515000.00, 1, 1
UNION ALL SELECT 'MU1882', 'UNIT', 1.000, 1, 0, 1545000.00, 1030000.00, 1, 1
UNION ALL SELECT 'MU1884', 'POT', 1.000, 1, 0, 254000.00, 254000.00, 1, 1
UNION ALL SELECT 'MU1888', 'BTG', 1.000, 1, 0, 75000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU1889', 'POT', 1.000, 1, 0, 254000.00, 254000.00, 1, 1
UNION ALL SELECT 'MU1890', 'POT', 1.000, 1, 0, 254000.00, 254000.00, 1, 1
UNION ALL SELECT 'MU1891', 'POT', 1.000, 1, 0, 254000.00, 254000.00, 1, 1
UNION ALL SELECT 'MU1892', 'POT', 1.000, 1, 0, 254000.00, 254000.00, 1, 1
UNION ALL SELECT 'MU1893', 'POT', 1.000, 1, 0, 254000.00, 254000.00, 1, 1
UNION ALL SELECT 'MU1894', 'KLG', 1.000, 1, 0, 254000.00, 254000.00, 1, 1
UNION ALL SELECT 'MU1895', 'KLG', 1.000, 1, 0, 254000.00, 254000.00, 1, 1
UNION ALL SELECT 'MU1896', 'KLG', 1.000, 1, 0, 254000.00, 254000.00, 1, 1
UNION ALL SELECT 'MU1897', 'KLG', 1.000, 1, 0, 254000.00, 254000.00, 1, 1
UNION ALL SELECT 'MU1898', 'KLG', 1.000, 1, 0, 254000.00, 254000.00, 1, 1
UNION ALL SELECT 'MU1899', 'KLG', 1.000, 1, 0, 254000.00, 254000.00, 1, 1
UNION ALL SELECT 'MU1900', 'KLG', 1.000, 1, 0, 78000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU1901', 'SET', 1.000, 1, 0, 276000.00, 220186.00, 1, 1
UNION ALL SELECT 'MU1902', 'KLG', 1.000, 1, 0, 64000.00, 50641.00, 1, 1
UNION ALL SELECT 'MU1903', 'PCS', 1.000, 1, 0, 18000.00, 14400.00, 1, 1
UNION ALL SELECT 'MU1904', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1905', 'LBR', 1.000, 1, 0, 45000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU1906', 'LBR', 1.000, 1, 0, 157000.00, 80000.00, 1, 1
UNION ALL SELECT 'MU1907', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1908', 'PCS', 1.000, 1, 0, 36000.00, 23400.00, 1, 1
UNION ALL SELECT 'MU1909', 'LBR', 1.000, 1, 0, 80000.00, 48452.00, 1, 1
UNION ALL SELECT 'MU1910', 'PCS', 1.000, 1, 0, 11000.00, 7470.00, 1, 1
UNION ALL SELECT 'MU1911', 'BTG', 1.000, 1, 0, 7000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU1912', 'BTG', 1.000, 1, 0, 9000.00, 4800.00, 1, 1
UNION ALL SELECT 'MU1913', 'BTG', 1.000, 1, 0, 12000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU1914', 'BTG', 1.000, 1, 0, 7000.00, 3200.00, 1, 1
UNION ALL SELECT 'MU1915', 'UNIT', 1.000, 1, 0, 300000.00, 260000.00, 1, 1
UNION ALL SELECT 'MU1916', 'UNIT', 1.000, 1, 0, 289000.00, 250000.00, 1, 1
UNION ALL SELECT 'MU1917', 'UNIT', 1.000, 1, 0, 405000.00, 380000.00, 1, 1
UNION ALL SELECT 'MU1918', 'GLN', 1.000, 1, 0, 186000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU1919', 'GLN', 1.000, 1, 0, 186000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU1920', 'PCS', 1.000, 1, 0, 17000.00, 8525.00, 1, 1
UNION ALL SELECT 'MU1921', 'GLN', 1.000, 1, 0, 186000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU1922', 'GLN', 1.000, 1, 0, 186000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU1923', 'GLN', 1.000, 1, 0, 186000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU1924', 'PAIL', 1.000, 1, 0, 876000.00, 711000.00, 1, 1
UNION ALL SELECT 'MU1925', 'GLN', 1.000, 1, 0, 186000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU1926', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU1927', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU1928', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU1929', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU1930', 'GLN', 1.000, 1, 0, 433000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU1931', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU1932', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU1933', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU1934', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU1935', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU1936', 'PCS', 1.000, 1, 0, 12000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1937', 'PCS', 1.000, 1, 0, 30000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU1938', 'PCS', 1.000, 1, 0, 29000.00, 23200.00, 1, 1
UNION ALL SELECT 'MU1939', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1940', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1941', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1942', 'PCS', 1.000, 1, 0, 40000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU1943', 'PCS', 1.000, 1, 0, 50000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU1944', 'DUS', 1.000, 1, 0, 450000.00, 299700.00, 1, 1
UNION ALL SELECT 'MU1945', 'DUS', 1.000, 1, 0, 517000.00, 344100.00, 1, 1
UNION ALL SELECT 'MU1946', 'DUS', 1.000, 1, 0, 342000.00, 227550.00, 1, 1
UNION ALL SELECT 'MU1947', 'DUS', 1.000, 1, 0, 1049000.00, 699300.00, 1, 1
UNION ALL SELECT 'MU1948', 'DUS', 1.000, 1, 0, 642000.00, 427350.00, 1, 1
UNION ALL SELECT 'MU1949', 'DUS', 1.000, 1, 0, 227000.00, 150960.00, 1, 1
UNION ALL SELECT 'MU1950', 'PCS', 1.000, 1, 0, 400.00, 0.00, 1, 1
UNION ALL SELECT 'MU1951', 'PCS', 1.000, 1, 0, 16000.00, 12800.00, 1, 1
UNION ALL SELECT 'MU1952', 'PCS', 1.000, 1, 0, 23000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU1953', 'MTR', 1.000, 1, 0, 58000.00, 45000.00, 1, 1
UNION ALL SELECT 'MU1954', 'UNIT', 1.000, 1, 0, 1157000.00, 771180.00, 1, 1
UNION ALL SELECT 'MU1955', 'PCS', 1.000, 1, 0, 18000.00, 10833.00, 1, 1
UNION ALL SELECT 'MU1956', 'PCS', 1.000, 1, 0, 43000.00, 28200.00, 1, 1
UNION ALL SELECT 'MU1957', 'UNIT', 1.000, 1, 0, 15600000.00, 12480000.00, 1, 1
UNION ALL SELECT 'MU1958', 'UNIT', 1.000, 1, 0, 1890000.00, 1512000.00, 1, 1
UNION ALL SELECT 'MU1959', 'UNIT', 1.000, 1, 0, 2500000.00, 2000000.00, 1, 1
UNION ALL SELECT 'MU1960', 'UNIT', 1.000, 1, 0, 500000.00, 400000.00, 1, 1
UNION ALL SELECT 'MU1961', 'PCS', 1.000, 1, 0, 65000.00, 43070.00, 1, 1
UNION ALL SELECT 'MU1962', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU1963', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1964', 'PCS', 1.000, 1, 0, 12000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU1965', 'PCS', 1.000, 1, 0, 32000.00, 25600.00, 1, 1
UNION ALL SELECT 'MU1966', 'UNIT', 1.000, 1, 0, 399000.00, 295000.00, 1, 1
UNION ALL SELECT 'MU1967', 'PCS', 1.000, 1, 0, 11000.00, 8800.00, 1, 1
UNION ALL SELECT 'MU1968', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1969', 'PCS', 1.000, 1, 0, 13000.00, 3685.00, 1, 1
UNION ALL SELECT 'MU1970', 'PCS', 1.000, 1, 0, 38000.00, 25200.00, 1, 1
UNION ALL SELECT 'MU1971', 'PCS', 1.000, 1, 0, 90000.00, 39583.00, 1, 1
UNION ALL SELECT 'MU1972', 'PCS', 1.000, 1, 0, 130000.00, 87500.00, 1, 1
UNION ALL SELECT 'MU1973', 'PCS', 1.000, 1, 0, 130000.00, 87500.00, 1, 1
UNION ALL SELECT 'MU1974', 'MTR', 1.000, 1, 0, 75000.00, 45600.00, 1, 1
UNION ALL SELECT 'MU1975', 'MTR', 1.000, 1, 0, 120000.00, 68800.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU1976' AS sku, 'MTR' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 160000.00 AS selling_price, 96000.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU1977', 'UNIT', 1.000, 1, 0, 2013000.00, 1320333.00, 1, 1
UNION ALL SELECT 'MU1978', 'PCS', 1.000, 1, 0, 16000.00, 10570.00, 1, 1
UNION ALL SELECT 'MU1979', 'PCS', 1.000, 1, 0, 27000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU1980', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU1981', 'PCS', 1.000, 1, 0, 4000.00, 3096.00, 1, 1
UNION ALL SELECT 'MU1982', 'PCS', 1.000, 1, 0, 90000.00, 49500.00, 1, 1
UNION ALL SELECT 'MU1983', 'PCS', 1.000, 1, 0, 4000.00, 1750.00, 1, 1
UNION ALL SELECT 'MU1984', 'PCS', 1.000, 1, 0, 254000.00, 254000.00, 1, 1
UNION ALL SELECT 'MU1985', 'KLG', 1.000, 1, 0, 46000.00, 38000.00, 1, 1
UNION ALL SELECT 'MU1986', 'KLG', 1.000, 1, 0, 112000.00, 92685.00, 1, 1
UNION ALL SELECT 'MU1987', 'MTR', 1.000, 1, 0, 20000.00, 8500.00, 1, 1
UNION ALL SELECT 'MU1988', 'PCS', 1.000, 1, 0, 278000.00, 22400.00, 1, 1
UNION ALL SELECT 'MU1989', 'PCS', 1.000, 1, 0, 257000.00, 205600.00, 1, 1
UNION ALL SELECT 'MU1990', 'PCS', 1.000, 1, 0, 297000.00, 237600.00, 1, 1
UNION ALL SELECT 'MU1991', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU1992', 'PCS', 1.000, 1, 0, 108000.00, 80000.00, 1, 1
UNION ALL SELECT 'MU1993', 'PCS', 1.000, 1, 0, 13000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU1994', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1995', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1996', 'PCS', 1.000, 1, 0, 70000.00, 56000.00, 1, 1
UNION ALL SELECT 'MU1997', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1998', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU1999', 'PCS', 1.000, 1, 0, 13000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU2000', 'POT', 1.000, 1, 0, 311000.00, 275000.00, 1, 1
UNION ALL SELECT 'MU2001', 'POT', 1.000, 1, 0, 311000.00, 275000.00, 1, 1
UNION ALL SELECT 'MU2002', 'POT', 1.000, 1, 0, 311000.00, 275000.00, 1, 1
UNION ALL SELECT 'MU2003', 'POT', 1.000, 1, 0, 311000.00, 275000.00, 1, 1
UNION ALL SELECT 'MU2004', 'PCS', 1.000, 1, 0, 37000.00, 23200.00, 1, 1
UNION ALL SELECT 'MU2005', 'PCS', 1.000, 1, 0, 49000.00, 31200.00, 1, 1
UNION ALL SELECT 'MU2006', 'PCS', 1.000, 1, 0, 49000.00, 31200.00, 1, 1
UNION ALL SELECT 'MU2007', 'PCS', 1.000, 1, 0, 49000.00, 31200.00, 1, 1
UNION ALL SELECT 'MU2008', 'PCS', 1.000, 1, 0, 49000.00, 31200.00, 1, 1
UNION ALL SELECT 'MU2009', 'PCS', 1.000, 1, 0, 37000.00, 23200.00, 1, 1
UNION ALL SELECT 'MU2010', 'PCS', 1.000, 1, 0, 37000.00, 23200.00, 1, 1
UNION ALL SELECT 'MU2011', 'PCS', 1.000, 1, 0, 37000.00, 23200.00, 1, 1
UNION ALL SELECT 'MU2012', 'PCS', 1.000, 1, 0, 37000.00, 23200.00, 1, 1
UNION ALL SELECT 'MU2013', 'PCS', 1.000, 1, 0, 37000.00, 23200.00, 1, 1
UNION ALL SELECT 'MU2014', 'PCS', 1.000, 1, 0, 37000.00, 23200.00, 1, 1
UNION ALL SELECT 'MU2015', 'PCS', 1.000, 1, 0, 37000.00, 23200.00, 1, 1
UNION ALL SELECT 'MU2016', 'PCS', 1.000, 1, 0, 37000.00, 23200.00, 1, 1
UNION ALL SELECT 'MU2017', 'PCS', 1.000, 1, 0, 49000.00, 31200.00, 1, 1
UNION ALL SELECT 'MU2018', 'PCS', 1.000, 1, 0, 49000.00, 31200.00, 1, 1
UNION ALL SELECT 'MU2019', 'PCS', 1.000, 1, 0, 37000.00, 23200.00, 1, 1
UNION ALL SELECT 'MU2020', 'PCS', 1.000, 1, 0, 37000.00, 23200.00, 1, 1
UNION ALL SELECT 'MU2021', 'PCS', 1.000, 1, 0, 37000.00, 23200.00, 1, 1
UNION ALL SELECT 'MU2022', 'PCS', 1.000, 1, 0, 37000.00, 23200.00, 1, 1
UNION ALL SELECT 'MU2023', 'PCS', 1.000, 1, 0, 37000.00, 23200.00, 1, 1
UNION ALL SELECT 'MU2024', 'POT', 1.000, 1, 0, 77000.00, 60400.00, 1, 1
UNION ALL SELECT 'MU2025', 'POT', 1.000, 1, 0, 77000.00, 60400.00, 1, 1
UNION ALL SELECT 'MU2026', 'KLG', 1.000, 1, 0, 155000.00, 124000.00, 1, 1
UNION ALL SELECT 'MU2027', 'POT', 1.000, 1, 0, 77000.00, 60400.00, 1, 1
UNION ALL SELECT 'MU2028', 'KLG', 1.000, 1, 0, 103000.00, 86000.00, 1, 1
UNION ALL SELECT 'MU2029', 'KLG', 1.000, 1, 0, 64000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU2030', 'KLG', 1.000, 1, 0, 93000.00, 82900.00, 1, 1
UNION ALL SELECT 'MU2031', 'KLG', 1.000, 1, 0, 36000.00, 32100.00, 1, 1
UNION ALL SELECT 'MU2032', 'PCS', 1.000, 1, 0, 38000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2033', 'KLG', 1.000, 1, 0, 114000.00, 87000.00, 1, 1
UNION ALL SELECT 'MU2034', 'KLG', 1.000, 1, 0, 114000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU2035', 'PCS', 1.000, 1, 0, 46000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU2036', 'PCS', 1.000, 1, 0, 40000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU2037', 'KLG', 1.000, 1, 0, 46000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU2038', 'KLG', 1.000, 1, 0, 46000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU2039', 'KLG', 1.000, 1, 0, 35000.00, 23000.00, 1, 1
UNION ALL SELECT 'MU2040', 'KLG', 1.000, 1, 0, 35000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU2041', 'KLG', 1.000, 1, 0, 35000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU2042', 'KLG', 1.000, 1, 0, 35000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU2043', 'KLG', 1.000, 1, 0, 35000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU2044', 'GLN', 1.000, 1, 0, 157000.00, 130000.00, 1, 1
UNION ALL SELECT 'MU2045', 'KLG', 1.000, 1, 0, 56000.00, 26400.00, 1, 1
UNION ALL SELECT 'MU2046', 'KLG', 1.000, 1, 0, 191000.00, 145159.00, 1, 1
UNION ALL SELECT 'MU2047', 'PCS', 1.000, 1, 0, 75000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU2048', 'SET', 1.000, 1, 0, 149000.00, 119200.00, 1, 1
UNION ALL SELECT 'MU2049', 'SET', 1.000, 1, 0, 149000.00, 119200.00, 1, 1
UNION ALL SELECT 'MU2050', 'SET', 1.000, 1, 0, 140000.00, 112000.00, 1, 1
UNION ALL SELECT 'MU2051', 'SET', 1.000, 1, 0, 278000.00, 149600.00, 1, 1
UNION ALL SELECT 'MU2052', 'SET', 1.000, 1, 0, 278000.00, 149600.00, 1, 1
UNION ALL SELECT 'MU2053', 'PCS', 1.000, 1, 0, 19000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU2054', 'SET', 1.000, 1, 0, 100000.00, 80000.00, 1, 1
UNION ALL SELECT 'MU2055', 'PCS', 1.000, 1, 0, 17000.00, 8500.00, 1, 1
UNION ALL SELECT 'MU2056', 'SET', 1.000, 1, 0, 57000.00, 37500.00, 1, 1
UNION ALL SELECT 'MU2057', 'PCS', 1.000, 1, 0, 42000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU2058', 'SET', 1.000, 1, 0, 140000.00, 112000.00, 1, 1
UNION ALL SELECT 'MU2059', 'SET', 1.000, 1, 0, 150000.00, 120000.00, 1, 1
UNION ALL SELECT 'MU2060', 'SET', 1.000, 1, 0, 149000.00, 119200.00, 1, 1
UNION ALL SELECT 'MU2061', 'SET', 1.000, 1, 0, 60000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU2062', 'SET', 1.000, 1, 0, 77000.00, 61600.00, 1, 1
UNION ALL SELECT 'MU2063', 'SET', 1.000, 1, 0, 77000.00, 61600.00, 1, 1
UNION ALL SELECT 'MU2064', 'SET', 1.000, 1, 0, 77000.00, 61600.00, 1, 1
UNION ALL SELECT 'MU2065', 'SET', 1.000, 1, 0, 77000.00, 61600.00, 1, 1
UNION ALL SELECT 'MU2067', 'SET', 1.000, 1, 0, 135000.00, 108000.00, 1, 1
UNION ALL SELECT 'MU2068', 'PCS', 1.000, 1, 0, 30000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU2069', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU2070', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU2071', 'PCS', 1.000, 1, 0, 19000.00, 12600.00, 1, 1
UNION ALL SELECT 'MU2072', 'PCS', 1.000, 1, 0, 21000.00, 13600.00, 1, 1
UNION ALL SELECT 'MU2073', 'KLG', 1.000, 1, 0, 49000.00, 36140.00, 1, 1
UNION ALL SELECT 'MU2074', 'PCS', 1.000, 1, 0, 28000.00, 22400.00, 1, 1
UNION ALL SELECT 'MU2075', 'PCS', 1.000, 1, 0, 5000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU2076', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU2077', 'PCS', 1.000, 1, 0, 75000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU2078', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU2079', 'PAIL', 1.000, 1, 0, 286000.00, 225200.00, 1, 1
UNION ALL SELECT 'MU2080', 'PAIL', 1.000, 1, 0, 286000.00, 225200.00, 1, 1
UNION ALL SELECT 'MU2081', 'PAIL', 1.000, 1, 0, 286000.00, 225200.00, 1, 1
UNION ALL SELECT 'MU2082', 'PAIL', 1.000, 1, 0, 286000.00, 225200.00, 1, 1
UNION ALL SELECT 'MU2083', 'PAIL', 1.000, 1, 0, 286000.00, 225200.00, 1, 1
UNION ALL SELECT 'MU2084', 'PAIL', 1.000, 1, 0, 286000.00, 225200.00, 1, 1
UNION ALL SELECT 'MU2085', 'PAIL', 1.000, 1, 0, 286000.00, 225200.00, 1, 1
UNION ALL SELECT 'MU2086', 'PAIL', 1.000, 1, 0, 286000.00, 225200.00, 1, 1
UNION ALL SELECT 'MU2087', 'PCS', 1.000, 1, 0, 34000.00, 27200.00, 1, 1
UNION ALL SELECT 'MU2088', 'PAIL', 1.000, 1, 0, 433000.00, 357700.00, 1, 1
UNION ALL SELECT 'MU2089', 'PAIL', 1.000, 1, 0, 433000.00, 357700.00, 1, 1
UNION ALL SELECT 'MU2090', 'PAIL', 1.000, 1, 0, 433000.00, 357700.00, 1, 1
UNION ALL SELECT 'MU2091', 'GLN', 1.000, 1, 0, 112000.00, 84000.00, 1, 1
UNION ALL SELECT 'MU2092', 'PAIL', 1.000, 1, 0, 433000.00, 357700.00, 1, 1
UNION ALL SELECT 'MU2093', 'MTR', 1.000, 1, 0, 19000.00, 12800.00, 1, 1
UNION ALL SELECT 'MU2094', 'MTR', 1.000, 1, 0, 16000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU2095', 'MTR', 1.000, 1, 0, 15000.00, 9600.00, 1, 1
UNION ALL SELECT 'MU2096', 'MTR', 1.000, 1, 0, 14000.00, 8800.00, 1, 1
UNION ALL SELECT 'MU2097', 'MTR', 1.000, 1, 0, 8000.00, 4800.00, 1, 1
UNION ALL SELECT 'MU2098', '50 METER', 1.000, 1, 0, 877000.00, 410000.00, 1, 1
UNION ALL SELECT 'MU2099', '50 METER', 1.000, 1, 0, 962000.00, 590000.00, 1, 1
UNION ALL SELECT 'MU2100', '100 METER', 1.000, 1, 0, 315000.00, 126000.00, 1, 1
UNION ALL SELECT 'MU2101', 'MTR', 1.000, 1, 0, 50000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU2102', 'MTR', 1.000, 1, 0, 35000.00, 22000.00, 1, 1
UNION ALL SELECT 'MU2103', 'MTR', 1.000, 1, 0, 28000.00, 17000.00, 1, 1
UNION ALL SELECT 'MU2104', 'MTR', 1.000, 1, 0, 119000.00, 54400.00, 1, 1
UNION ALL SELECT 'MU2105', 'MTR', 1.000, 1, 0, 104000.00, 39200.00, 1, 1
UNION ALL SELECT 'MU2106', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU2107', 'GEN', 1.000, 1, 0, 235000.00, 195667.00, 1, 1
UNION ALL SELECT 'MU2108', 'GEN', 1.000, 1, 0, 298000.00, 247883.00, 1, 1
UNION ALL SELECT 'MU2109', 'BTL', 1.000, 1, 0, 90000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2110', 'GEN', 1.000, 1, 0, 440000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2111', 'GEN', 1.000, 1, 0, 348000.00, 290000.00, 1, 1
UNION ALL SELECT 'MU2112', 'GEN', 1.000, 1, 0, 376000.00, 313000.00, 1, 1
UNION ALL SELECT 'MU2113', 'PCS', 1.000, 1, 0, 3000.00, 2400.00, 1, 1
UNION ALL SELECT 'MU2114', 'PCS', 1.000, 1, 0, 4000.00, 3200.00, 1, 1
UNION ALL SELECT 'MU2115', 'PCS', 1.000, 1, 0, 180000.00, 144000.00, 1, 1
UNION ALL SELECT 'MU2116', 'PCS', 1.000, 1, 0, 145000.00, 116000.00, 1, 1
UNION ALL SELECT 'MU2117', 'PCS', 1.000, 1, 0, 110000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU2118', 'PCS', 1.000, 1, 0, 55000.00, 44000.00, 1, 1
UNION ALL SELECT 'MU2119', 'PCS', 1.000, 1, 0, 67000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU2120', 'PCS', 1.000, 1, 0, 38000.00, 30400.00, 1, 1
UNION ALL SELECT 'MU21200', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU2121', 'MTR', 1.000, 1, 0, 19000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2122', 'UNIT', 1.000, 1, 0, 297000.00, 220000.00, 1, 1
UNION ALL SELECT 'MU2123', 'PCS', 1.000, 1, 0, 60000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU2124', 'PCS', 1.000, 1, 0, 40000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU2125', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU2126' AS sku, 'PCS' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 10000.00 AS selling_price, 8000.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU2127', 'DUS', 1.000, 1, 0, 15000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU2128', 'PCS', 1.000, 1, 0, 17000.00, 13600.00, 1, 1
UNION ALL SELECT 'MU2129', 'PCS', 1.000, 1, 0, 13000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU2130', 'LTR', 1.000, 1, 0, 51000.00, 42000.00, 1, 1
UNION ALL SELECT 'MU2131', 'PCS', 1.000, 1, 0, 1500.00, 800.00, 1, 1
UNION ALL SELECT 'MU2132', 'PCS', 1.000, 1, 0, 4000.00, 1802.00, 1, 1
UNION ALL SELECT 'MU2133', 'PCS', 1.000, 1, 0, 1000.00, 800.00, 1, 1
UNION ALL SELECT 'MU2134', 'PCS', 1.000, 1, 0, 500.00, 400.00, 1, 1
UNION ALL SELECT 'MU2135', 'PCS', 1.000, 1, 0, 7000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU2136', 'PCS', 1.000, 1, 0, 14000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU2137', 'PCS', 1.000, 1, 0, 1000.00, 800.00, 1, 1
UNION ALL SELECT 'MU2138', 'PCS', 1.000, 1, 0, 3000.00, 2400.00, 1, 1
UNION ALL SELECT 'MU2139', 'PCS', 1.000, 1, 0, 1000.00, 800.00, 1, 1
UNION ALL SELECT 'MU2140', 'PCS', 1.000, 1, 0, 2000.00, 1600.00, 1, 1
UNION ALL SELECT 'MU2141', 'PCS', 1.000, 1, 0, 293000.00, 195000.00, 1, 1
UNION ALL SELECT 'MU2142', 'UNIT', 1.000, 1, 0, 210000.00, 168000.00, 1, 1
UNION ALL SELECT 'MU2143', 'UNIT', 1.000, 1, 0, 777000.00, 520000.00, 1, 1
UNION ALL SELECT 'MU2144', 'UNIT', 1.000, 1, 0, 290000.00, 232000.00, 1, 1
UNION ALL SELECT 'MU2145', 'UNIT', 1.000, 1, 0, 295000.00, 236000.00, 1, 1
UNION ALL SELECT 'MU2146', 'UNIT', 1.000, 1, 0, 300000.00, 240000.00, 1, 1
UNION ALL SELECT 'MU2147', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU2148', 'PCS', 1.000, 1, 0, 14000.00, 11200.00, 1, 1
UNION ALL SELECT 'MU2149', 'PCS', 1.000, 1, 0, 18000.00, 14400.00, 1, 1
UNION ALL SELECT 'MU2150', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU2151', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU2152', 'SET', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU2153', 'SET', 1.000, 1, 0, 166000.00, 115000.00, 1, 1
UNION ALL SELECT 'MU2154', 'PCS', 1.000, 1, 0, 40000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU2155', 'PCS', 1.000, 1, 0, 78000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU2156', 'PCS', 1.000, 1, 0, 80000.00, 64000.00, 1, 1
UNION ALL SELECT 'MU2157', 'PCS', 1.000, 1, 0, 35000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU2158', 'PCS', 1.000, 1, 0, 27000.00, 21600.00, 1, 1
UNION ALL SELECT 'MU2159', 'PCS', 1.000, 1, 0, 60000.00, 33000.00, 1, 1
UNION ALL SELECT 'MU2160', 'PCS', 1.000, 1, 0, 55000.00, 30250.00, 1, 1
UNION ALL SELECT 'MU2161', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU2162', 'PCS', 1.000, 1, 0, 32000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU2163', 'PCS', 1.000, 1, 0, 39000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU2164', 'PCS', 1.000, 1, 0, 22000.00, 11000.00, 1, 1
UNION ALL SELECT 'MU2165', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU2166', 'PCS', 1.000, 1, 0, 30000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU2167', 'PCS', 1.000, 1, 0, 170000.00, 136000.00, 1, 1
UNION ALL SELECT 'MU2168', 'PCS', 1.000, 1, 0, 150000.00, 120000.00, 1, 1
UNION ALL SELECT 'MU2169', 'SET', 1.000, 1, 0, 1500000.00, 1000000.00, 1, 1
UNION ALL SELECT 'MU2170', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU2171', 'PCS', 1.000, 1, 0, 45000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU2172', 'PCS', 1.000, 1, 0, 25000.00, 12925.00, 1, 1
UNION ALL SELECT 'MU2173', 'SET', 1.000, 1, 0, 960000.00, 720000.00, 1, 1
UNION ALL SELECT 'MU2174', 'PCS', 1.000, 1, 0, 13000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU2175', 'PCS', 1.000, 1, 0, 17000.00, 11000.00, 1, 1
UNION ALL SELECT 'MU2176', 'PCS', 1.000, 1, 0, 19000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU2177', 'PCS', 1.000, 1, 0, 93000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU2178', 'PCS', 1.000, 1, 0, 37000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU2179', 'PCS', 1.000, 1, 0, 58000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU2180', 'PCS', 1.000, 1, 0, 87000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU2181', 'PCS', 1.000, 1, 0, 102000.00, 70000.00, 1, 1
UNION ALL SELECT 'MU2182', 'DUS', 1.000, 1, 0, 107000.00, 72399.00, 1, 1
UNION ALL SELECT 'MU2183', 'DUS', 1.000, 1, 0, 107000.00, 63000.00, 1, 1
UNION ALL SELECT 'MU2184', 'DUS', 1.000, 1, 0, 107000.00, 63000.00, 1, 1
UNION ALL SELECT 'MU2185', 'DUS', 1.000, 1, 0, 105000.00, 61000.00, 1, 1
UNION ALL SELECT 'MU2186', 'DUS', 1.000, 1, 0, 102000.00, 57500.00, 1, 1
UNION ALL SELECT 'MU2187', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU2188', 'PCS', 1.000, 1, 0, 11000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU2189', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU2190', 'PCS', 1.000, 1, 0, 11000.00, 10400.00, 1, 1
UNION ALL SELECT 'MU2191', 'PCS', 1.000, 1, 0, 7000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU2192', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU2193', 'PCS', 1.000, 1, 0, 15000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU2194', 'PCS', 1.000, 1, 0, 60000.00, 33000.00, 1, 1
UNION ALL SELECT 'MU2195', 'PCS', 1.000, 1, 0, 7000.00, 4800.00, 1, 1
UNION ALL SELECT 'MU2196', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU2197', 'PCS', 1.000, 1, 0, 20000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU2198', 'PCS', 1.000, 1, 0, 18000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU2199', 'UNIT', 1.000, 1, 0, 54000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU2200', 'PCS', 1.000, 1, 0, 570000.00, 456000.00, 1, 1
UNION ALL SELECT 'MU2201', 'PCS', 1.000, 1, 0, 862000.00, 689600.00, 1, 1
UNION ALL SELECT 'MU2202', 'DUS', 1.000, 1, 0, 74000.00, 52499.00, 1, 1
UNION ALL SELECT 'MU2203', 'DUS', 1.000, 1, 0, 44000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU2204', 'PCS', 1.000, 1, 0, 35000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU2205', 'PCS', 1.000, 1, 0, 33000.00, 22400.00, 1, 1
UNION ALL SELECT 'MU2206', 'PCS', 1.000, 1, 0, 51000.00, 35200.00, 1, 1
UNION ALL SELECT 'MU2207', 'PCS', 1.000, 1, 0, 44000.00, 30400.00, 1, 1
UNION ALL SELECT 'MU2208', 'PCS', 1.000, 1, 0, 46000.00, 36800.00, 1, 1
UNION ALL SELECT 'MU2209', 'PCS', 1.000, 1, 0, 5000.00, 3000.00, 1, 1
UNION ALL SELECT 'MU2210', 'PCS', 1.000, 1, 0, 7000.00, 5600.00, 1, 1
UNION ALL SELECT 'MU2211', 'KLG', 1.000, 1, 0, 65000.00, 56200.00, 1, 1
UNION ALL SELECT 'MU2212', 'PCS', 1.000, 1, 0, 14000.00, 9600.00, 1, 1
UNION ALL SELECT 'MU2213', 'PCS', 1.000, 1, 0, 12000.00, 7200.00, 1, 1
UNION ALL SELECT 'MU2214', 'KLG', 1.000, 1, 0, 283000.00, 188000.00, 1, 1
UNION ALL SELECT 'MU2215', 'KLG', 1.000, 1, 0, 136000.00, 100330.00, 1, 1
UNION ALL SELECT 'MU2216', 'KLG', 1.000, 1, 0, 94000.00, 71022.00, 1, 1
UNION ALL SELECT 'MU2217', 'PCS', 1.000, 1, 0, 4000.00, 3200.00, 1, 1
UNION ALL SELECT 'MU2218', 'PCS', 1.000, 1, 0, 7000.00, 5600.00, 1, 1
UNION ALL SELECT 'MU2219', 'PCS', 1.000, 1, 0, 5000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU2220', 'PCS', 1.000, 1, 0, 4000.00, 3200.00, 1, 1
UNION ALL SELECT 'MU2221', 'PCS', 1.000, 1, 0, 4000.00, 3200.00, 1, 1
UNION ALL SELECT 'MU2222', 'PCS', 1.000, 1, 0, 43000.00, 34400.00, 1, 1
UNION ALL SELECT 'MU2223', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU2224', 'PCS', 1.000, 1, 0, 28000.00, 22400.00, 1, 1
UNION ALL SELECT 'MU2225', 'PCS', 1.000, 1, 0, 30000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU2226', 'PCS', 1.000, 1, 0, 87000.00, 62000.00, 1, 1
UNION ALL SELECT 'MU2227', 'PCS', 1.000, 1, 0, 5000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU2228', 'PCS', 1.000, 1, 0, 54000.00, 41600.00, 1, 1
UNION ALL SELECT 'MU2229', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU2230', 'PCS', 1.000, 1, 0, 6000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU2231', 'SET', 1.000, 1, 0, 113000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU2232', 'PCS', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU2233', 'PCS', 1.000, 1, 0, 5000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU2234', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU2235', 'PCS', 1.000, 1, 0, 10000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU2236', 'KLG', 1.000, 1, 0, 28000.00, 18400.00, 1, 1
UNION ALL SELECT 'MU2237', 'KLG', 1.000, 1, 0, 28000.00, 18400.00, 1, 1
UNION ALL SELECT 'MU2238', 'PCS', 1.000, 1, 0, 20000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU2239', 'PCS', 1.000, 1, 0, 45000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU2240', 'KLG', 1.000, 1, 0, 28000.00, 18400.00, 1, 1
UNION ALL SELECT 'MU2241', 'PCS', 1.000, 1, 0, 54000.00, 43200.00, 1, 1
UNION ALL SELECT 'MU2242', 'KLG', 1.000, 1, 0, 28000.00, 18400.00, 1, 1
UNION ALL SELECT 'MU2243', 'PCS', 1.000, 1, 0, 52000.00, 41600.00, 1, 1
UNION ALL SELECT 'MU2244', 'UNIT', 1.000, 1, 0, 1.00, 0.00, 1, 1
UNION ALL SELECT 'MU2246', 'PCS', 1.000, 1, 0, 10000.00, 8800.00, 1, 1
UNION ALL SELECT 'MU2247', 'PCS', 1.000, 1, 0, 72000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU2248', 'SET', 1.000, 1, 0, 100000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2249', 'SET', 1.000, 1, 0, 100000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2250', 'SET', 1.000, 1, 0, 185000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2251', 'SET', 1.000, 1, 0, 120000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2252', 'KLG', 1.000, 1, 0, 52000.00, 36900.00, 1, 1
UNION ALL SELECT 'MU2253', 'KLG', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU2254', 'KLG', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU2255', 'KLG', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU2256', 'KLG', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU2257', 'KLG', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU2258', 'PCS', 1.000, 1, 0, 15000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU2259', 'SET', 1.000, 1, 0, 27000.00, 21600.00, 1, 1
UNION ALL SELECT 'MU2260', 'PCS', 1.000, 1, 0, 20000.00, 14000.00, 1, 1
UNION ALL SELECT 'MU2261', 'PCS', 1.000, 1, 0, 10000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU2262', 'PCS', 1.000, 1, 0, 12000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU2263', 'PCS', 1.000, 1, 0, 16000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU2264', 'PCS', 1.000, 1, 0, 19000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU2265', 'PCS', 1.000, 1, 0, 12000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU2266', 'PCS', 1.000, 1, 0, 16000.00, 13000.00, 1, 1
UNION ALL SELECT 'MU2267', 'PCS', 1.000, 1, 0, 32000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU2268', 'PCS', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU2269', 'PCS', 1.000, 1, 0, 18000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU2270', 'PCS', 1.000, 1, 0, 12000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU2271', 'PCS', 1.000, 1, 0, 16000.00, 12800.00, 1, 1
UNION ALL SELECT 'MU2272', 'KLG', 1.000, 1, 0, 60000.00, 43400.00, 1, 1
UNION ALL SELECT 'MU2273', 'PCS', 1.000, 1, 0, 9000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU2274', 'PCS', 1.000, 1, 0, 95000.00, 76000.00, 1, 1
UNION ALL SELECT 'MU2275', 'PCS', 1.000, 1, 0, 98000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU2276', 'PCS', 1.000, 1, 0, 0.00, 0.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU2277' AS sku, 'KLG' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 87000.00 AS selling_price, 72500.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU2278', 'BTL', 1.000, 1, 0, 30000.00, 14500.00, 1, 1
UNION ALL SELECT 'MU2279', 'PCS', 1.000, 1, 0, 64000.00, 48500.00, 1, 1
UNION ALL SELECT 'MU2280', 'PCS', 1.000, 1, 0, 18000.00, 9500.00, 1, 1
UNION ALL SELECT 'MU2281', 'PCS', 1.000, 1, 0, 38000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU2282', 'PCS', 1.000, 1, 0, 59000.00, 39100.00, 1, 1
UNION ALL SELECT 'MU2283', 'PCS', 1.000, 1, 0, 14000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU2284', 'PCS', 1.000, 1, 0, 60000.00, 32500.00, 1, 1
UNION ALL SELECT 'MU2285', 'PCS', 1.000, 1, 0, 40000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU2286', 'KLG', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU2287', 'PCS', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU2288', 'PCS', 1.000, 1, 0, 9000.00, 6390.00, 1, 1
UNION ALL SELECT 'MU2289', 'PCS', 1.000, 1, 0, 65000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU2290', 'PCS', 1.000, 1, 0, 138000.00, 105109.00, 1, 1
UNION ALL SELECT 'MU2291', 'PCS', 1.000, 1, 0, 45000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU2292', 'IKAT', 1.000, 1, 0, 8000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU2293', 'PCS', 1.000, 1, 0, 100000.00, 95000.00, 1, 1
UNION ALL SELECT 'MU2294', 'PCS', 1.000, 1, 0, 12000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU2295', 'PCS', 1.000, 1, 0, 13000.00, 8800.00, 1, 1
UNION ALL SELECT 'MU2296', 'PCS', 1.000, 1, 0, 15000.00, 11500.00, 1, 1
UNION ALL SELECT 'MU2297', 'PCS', 1.000, 1, 0, 23000.00, 17000.00, 1, 1
UNION ALL SELECT 'MU2298', 'PCS', 1.000, 1, 0, 41000.00, 31000.00, 1, 1
UNION ALL SELECT 'MU2299', 'PCS', 1.000, 1, 0, 38000.00, 27200.00, 1, 1
UNION ALL SELECT 'MU2300', 'PCS', 1.000, 1, 0, 31000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU2301', 'PCS', 1.000, 1, 0, 45000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU2302', 'PCS', 1.000, 1, 0, 55000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU2303', 'PCS', 1.000, 1, 0, 90000.00, 56000.00, 1, 1
UNION ALL SELECT 'MU2304', 'PCS', 1.000, 1, 0, 125000.00, 76000.00, 1, 1
UNION ALL SELECT 'MU2305', 'PCS', 1.000, 1, 0, 150000.00, 96000.00, 1, 1
UNION ALL SELECT 'MU2306', 'PCS', 1.000, 1, 0, 138000.00, 104000.00, 1, 1
UNION ALL SELECT 'MU2307', 'PCS', 1.000, 1, 0, 167000.00, 116000.00, 1, 1
UNION ALL SELECT 'MU2308', 'PCS', 1.000, 1, 0, 184000.00, 128000.00, 1, 1
UNION ALL SELECT 'MU2309', 'PCS', 1.000, 1, 0, 219000.00, 152000.00, 1, 1
UNION ALL SELECT 'MU2310', 'PCS', 1.000, 1, 0, 85000.00, 46750.00, 1, 1
UNION ALL SELECT 'MU2311', 'PCS', 1.000, 1, 0, 17000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU2312', 'PCS', 1.000, 1, 0, 15000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU2313', 'PCS', 1.000, 1, 0, 22000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU2314', 'PCS', 1.000, 1, 0, 65000.00, 52000.00, 1, 1
UNION ALL SELECT 'MU2315', 'PCS', 1.000, 1, 0, 70000.00, 56000.00, 1, 1
UNION ALL SELECT 'MU2316', 'PCS', 1.000, 1, 0, 85000.00, 68000.00, 1, 1
UNION ALL SELECT 'MU2317', 'PCS', 1.000, 1, 0, 650000.00, 400000.00, 1, 1
UNION ALL SELECT 'MU2318', 'PSG', 1.000, 1, 0, 185000.00, 180000.00, 1, 1
UNION ALL SELECT 'MU2319', 'PAIL', 1.000, 1, 0, 536000.00, 380000.00, 1, 1
UNION ALL SELECT 'MU2320', 'PCS', 1.000, 1, 0, 20000.00, 17000.00, 1, 1
UNION ALL SELECT 'MU2321', 'PCS', 1.000, 1, 0, 250000.00, 200000.00, 1, 1
UNION ALL SELECT 'MU2322', 'KLG', 1.000, 1, 0, 50000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU2323', 'PCS', 1.000, 1, 0, 20000.00, 13000.00, 1, 1
UNION ALL SELECT 'MU2324', 'PCS', 1.000, 1, 0, 42000.00, 27500.00, 1, 1
UNION ALL SELECT 'MU2325', 'PCS', 1.000, 1, 0, 70000.00, 38500.00, 1, 1
UNION ALL SELECT 'MU2326', 'PCS', 1.000, 1, 0, 60000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU2327', 'KLG', 1.000, 1, 0, 53000.00, 43000.00, 1, 1
UNION ALL SELECT 'MU2328', 'PCS', 1.000, 1, 0, 28000.00, 19000.00, 1, 1
UNION ALL SELECT 'MU2329', 'PCS', 1.000, 1, 0, 45000.00, 22083.00, 1, 1
UNION ALL SELECT 'MU2330', 'SET', 1.000, 1, 0, 278000.00, 150000.00, 1, 1
UNION ALL SELECT 'MU2331', 'UNIT', 1.000, 1, 0, 440000.00, 360000.00, 1, 1
UNION ALL SELECT 'MU2332', 'PCS', 1.000, 1, 0, 8000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU2334', 'PCS', 1.000, 1, 0, 15000.00, 13000.00, 1, 1
UNION ALL SELECT 'MU2335', 'PCS', 1.000, 1, 0, 18000.00, 13000.00, 1, 1
UNION ALL SELECT 'MU2336', 'PCS', 1.000, 1, 0, 205000.00, 160000.00, 1, 1
UNION ALL SELECT 'MU2337', 'PCS', 1.000, 1, 0, 55000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU2338', 'PCS', 1.000, 1, 0, 61000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU2339', 'PCS', 1.000, 1, 0, 77000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU2340', 'PCS', 1.000, 1, 0, 94000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU2341', 'PCS', 1.000, 1, 0, 72000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU2342', 'PCS', 1.000, 1, 0, 77000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU2343', 'PCS', 1.000, 1, 0, 88000.00, 45000.00, 1, 1
UNION ALL SELECT 'MU2344', 'PCS', 1.000, 1, 0, 110000.00, 43000.00, 1, 1
UNION ALL SELECT 'MU2345', 'PCS', 1.000, 1, 0, 120000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU2346', 'PCS', 1.000, 1, 0, 49000.00, 31000.00, 1, 1
UNION ALL SELECT 'MU2347', 'PCS', 1.000, 1, 0, 55000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU2348', 'PCS', 1.000, 1, 0, 58000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU2349', 'PCS', 1.000, 1, 0, 61000.00, 38000.00, 1, 1
UNION ALL SELECT 'MU2350', 'PCS', 1.000, 1, 0, 58000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU2351', 'SET', 1.000, 1, 0, 95000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU2352', 'PCS', 1.000, 1, 0, 95000.00, 55000.00, 1, 1
UNION ALL SELECT 'MU2353', 'PCS', 1.000, 1, 0, 35000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU2354', 'PCS', 1.000, 1, 0, 35000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU2355', 'PCS', 1.000, 1, 0, 22000.00, 15500.00, 1, 1
UNION ALL SELECT 'MU2356', 'PCS', 1.000, 1, 0, 7000.00, 2500.00, 1, 1
UNION ALL SELECT 'MU2357', 'SET', 1.000, 1, 0, 13000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU2358', 'SET', 1.000, 1, 0, 15000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU2359', 'PCS', 1.000, 1, 0, 18000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU2360', 'PCS', 1.000, 1, 0, 20000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU2361', 'PCS', 1.000, 1, 0, 33000.00, 18750.00, 1, 1
UNION ALL SELECT 'MU2362', 'PCS', 1.000, 1, 0, 28000.00, 19000.00, 1, 1
UNION ALL SELECT 'MU2363', 'PCS', 1.000, 1, 0, 35000.00, 23500.00, 1, 1
UNION ALL SELECT 'MU2364', 'PCS', 1.000, 1, 0, 33000.00, 17000.00, 1, 1
UNION ALL SELECT 'MU2365', 'DUS', 1.000, 1, 0, 20000.00, 9750.00, 1, 1
UNION ALL SELECT 'MU2366', 'PCS', 1.000, 1, 0, 48000.00, 38400.00, 1, 1
UNION ALL SELECT 'MU2367', 'UNIT', 1.000, 1, 0, 14000000.00, 11200000.00, 1, 1
UNION ALL SELECT 'MU2368', 'PCS', 1.000, 1, 0, 15000.00, 7850.00, 1, 1
UNION ALL SELECT 'MU2369', 'PCS', 1.000, 1, 0, 33000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU2370', 'PCS', 1.000, 1, 0, 23000.00, 10416.00, 1, 1
UNION ALL SELECT 'MU2371', 'PCS', 1.000, 1, 0, 75000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU2372', 'PCS', 1.000, 1, 0, 45000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU2373', 'PCS', 1.000, 1, 0, 45000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU2374', 'PCS', 1.000, 1, 0, 52000.00, 30400.00, 1, 1
UNION ALL SELECT 'MU2375', 'PCS', 1.000, 1, 0, 75000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU2376', 'PCS', 1.000, 1, 0, 75000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU2377', 'PCS', 1.000, 1, 0, 80000.00, 64000.00, 1, 1
UNION ALL SELECT 'MU2378', 'PCS', 1.000, 1, 0, 80000.00, 64000.00, 1, 1
UNION ALL SELECT 'MU2379', 'PCS', 1.000, 1, 0, 54000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU2380', 'PCS', 1.000, 1, 0, 54000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU2381', 'PCS', 1.000, 1, 0, 800000.00, 700000.00, 1, 1
UNION ALL SELECT 'MU2382', 'PCS', 1.000, 1, 0, 1750000.00, 1400000.00, 1, 1
UNION ALL SELECT 'MU2383', 'PCS', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU2384', 'KLG', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU2385', 'KLG', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU2386', 'KLG', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU2387', 'KLG', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU2388', 'KLG', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU2389', 'KLG', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU2390', 'KLG', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU2391', 'KLG', 1.000, 1, 0, 144000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU2392', 'KLG', 1.000, 1, 0, 144000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU2393', 'KLG', 1.000, 1, 0, 144000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU2394', 'KLG', 1.000, 1, 0, 144000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU2395', 'KLG', 1.000, 1, 0, 144000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU2396', 'KLG', 1.000, 1, 0, 44000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU2397', 'SET', 1.000, 1, 0, 87000.00, 37000.00, 1, 1
UNION ALL SELECT 'MU2398', 'SET', 1.000, 1, 0, 275000.00, 110000.00, 1, 1
UNION ALL SELECT 'MU2399', 'DUS', 1.000, 1, 0, 200000.00, 79000.00, 1, 1
UNION ALL SELECT 'MU2400', 'PCS', 1.000, 1, 0, 60000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU2401', 'DUS', 1.000, 1, 0, 2000.00, 479.00, 1, 1
UNION ALL SELECT 'MU2402', 'LBR', 1.000, 1, 0, 3000.00, 550.00, 1, 1
UNION ALL SELECT 'MU2403', 'PCS', 1.000, 1, 0, 3000.00, 550.00, 1, 1
UNION ALL SELECT 'MU2404', 'PCS', 1.000, 1, 0, 10000.00, 5500.00, 1, 1
UNION ALL SELECT 'MU2405', 'SET', 1.000, 1, 0, 75000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU2406', 'SET', 1.000, 1, 0, 20000.00, 9500.00, 1, 1
UNION ALL SELECT 'MU2407', 'SET', 1.000, 1, 0, 25000.00, 12500.00, 1, 1
UNION ALL SELECT 'MU2408', 'PCS', 1.000, 1, 0, 75000.00, 47500.00, 1, 1
UNION ALL SELECT 'MU2409', 'PCS', 1.000, 1, 0, 173000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU2410', 'SET', 1.000, 1, 0, 150000.00, 70000.00, 1, 1
UNION ALL SELECT 'MU2411', 'PCS', 1.000, 1, 0, 13000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU2412', 'PCS', 1.000, 1, 0, 80000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU2413', 'PCS', 1.000, 1, 0, 25000.00, 16500.00, 1, 1
UNION ALL SELECT 'MU2414', 'SET', 1.000, 1, 0, 200000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2415', 'PCS', 1.000, 1, 0, 7000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2416', 'PCS', 1.000, 1, 0, 5000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2417', 'PCS', 1.000, 1, 0, 52000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU2418', 'DUS', 1.000, 1, 0, 70000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2419', 'PCS', 1.000, 1, 0, 3000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2420', 'PCS', 1.000, 1, 0, 5000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2421', 'PSG', 1.000, 1, 0, 8000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2422', 'PSG', 1.000, 1, 0, 6000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2423', 'PSG', 1.000, 1, 0, 4000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2424', 'PSG', 1.000, 1, 0, 2000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2425', 'PSG', 1.000, 1, 0, 4000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2426', 'PSG', 1.000, 1, 0, 3000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2427', 'PSG', 1.000, 1, 0, 3000.00, 0.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU2428' AS sku, 'PSG' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 2000.00 AS selling_price, 0.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU2429', 'PSG', 1.000, 1, 0, 2000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2430', 'PSG', 1.000, 1, 0, 6000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2431', 'PSG', 1.000, 1, 0, 5000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2432', 'PSG', 1.000, 1, 0, 4000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2433', 'PSG', 1.000, 1, 0, 3000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2434', 'PSG', 1.000, 1, 0, 8000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2435', 'PSG', 1.000, 1, 0, 7000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2436', 'PSG', 1.000, 1, 0, 105000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU2437', 'PCS', 1.000, 1, 0, 100000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2438', 'PCS', 1.000, 1, 0, 15000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU2439', 'BTG', 1.000, 1, 0, 32000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2440', 'BTG', 1.000, 1, 0, 126000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2441', 'BTG', 1.000, 1, 0, 170000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2442', 'PSG', 1.000, 1, 0, 105000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU2443', 'PSG', 1.000, 1, 0, 105000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU2444', 'PSG', 1.000, 1, 0, 105000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU2445', 'PSG', 1.000, 1, 0, 105000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2446', 'ROL', 1.000, 1, 0, 200000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU2447', 'ROL', 1.000, 1, 0, 200000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU2448', 'DUS', 1.000, 1, 0, 77000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2449', 'PSG', 1.000, 1, 0, 90000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU2450', 'PSG', 1.000, 1, 0, 90000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU2451', 'LBR', 1.000, 1, 0, 80000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2452', 'PCS', 1.000, 1, 0, 78000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2453', 'PCS', 1.000, 1, 0, 78000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2454', 'PCS', 1.000, 1, 0, 78000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2455', 'PCS', 1.000, 1, 0, 78000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2456', 'PCS', 1.000, 1, 0, 78000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2457', 'PCS', 1.000, 1, 0, 78000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2458', 'PCS', 1.000, 1, 0, 78000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2459', 'PCS', 1.000, 1, 0, 69000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2460', 'PCS', 1.000, 1, 0, 69000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2461', 'PCS', 1.000, 1, 0, 20000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU2462', 'PCS', 1.000, 1, 0, 25000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2463', 'SET', 1.000, 1, 0, 30000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2464', 'ROL', 1.000, 1, 0, 800000.00, 528000.00, 1, 1
UNION ALL SELECT 'MU2465', 'MTR', 1.000, 1, 0, 20000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU2466', 'PCS', 1.000, 1, 0, 35000.00, 17875.00, 1, 1
UNION ALL SELECT 'MU2467', 'PCS', 1.000, 1, 0, 48000.00, 31800.00, 1, 1
UNION ALL SELECT 'MU2468', 'PCS', 1.000, 1, 0, 2500.00, 0.00, 1, 1
UNION ALL SELECT 'MU2469', 'MTR', 1.000, 1, 0, 15000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2470', 'KG', 1.000, 1, 0, 20000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2471', 'KG', 1.000, 1, 0, 85000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2472', 'PCS', 1.000, 1, 0, 36000.00, 23500.00, 1, 1
UNION ALL SELECT 'MU2473', 'LBR', 1.000, 1, 0, 301000.00, 231080.00, 1, 1
UNION ALL SELECT 'MU2474', 'MTR', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU2475', 'PCS', 1.000, 1, 0, 36000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU2476', 'PCS', 1.000, 1, 0, 20000.00, 6486.00, 1, 1
UNION ALL SELECT 'MU2477', 'PCS', 1.000, 1, 0, 60000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU2478', 'PCS', 1.000, 1, 0, 11000.00, 6800.00, 1, 1
UNION ALL SELECT 'MU2479', 'PCS', 1.000, 1, 0, 40000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU2480', 'PCS', 1.000, 1, 0, 80000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU2491', 'PCS', 1.000, 1, 0, 35000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU2492', 'MTR', 1.000, 1, 0, 45000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU2493', 'KLG', 1.000, 1, 0, 94000.00, 52994.00, 1, 1
UNION ALL SELECT 'MU2494', 'KLG', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU2495', 'PCS', 1.000, 1, 0, 5000.00, 2175.00, 1, 1
UNION ALL SELECT 'MU2496', 'PCS', 1.000, 1, 0, 8000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU2497', 'SAK', 1.000, 1, 0, 100000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2498', 'SAK', 1.000, 1, 0, 15000.00, 10500.00, 1, 1
UNION ALL SELECT 'MU2499', 'PCS', 1.000, 1, 0, 11000.00, 7500.00, 1, 1
UNION ALL SELECT 'MU2500', 'PCS', 1.000, 1, 0, 34000.00, 22885.00, 1, 1
UNION ALL SELECT 'MU2501', 'DUS', 1.000, 1, 0, 40000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2503', 'PCS', 1.000, 1, 0, 60000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2504', 'MTR', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU2506', 'PCS', 1.000, 1, 0, 1000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2507', 'PCS', 1.000, 1, 0, 2000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2508', 'PCS', 1.000, 1, 0, 3000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2509', 'PCS', 1.000, 1, 0, 1.00, 0.00, 1, 1
UNION ALL SELECT 'MU2510', 'KG', 1.000, 1, 0, 38000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2511', 'PAK', 1.000, 1, 0, 25000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2512', 'PAK', 1.000, 1, 0, 25000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2513', 'PAK', 1.000, 1, 0, 25000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2514', 'PCS', 1.000, 1, 0, 500.00, 0.00, 1, 1
UNION ALL SELECT 'MU2515', 'PCS', 1.000, 1, 0, 12000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2516', 'PCS', 1.000, 1, 0, 10000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2517', 'PAK', 1.000, 1, 0, 14000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2519', 'PCS', 1.000, 1, 0, 60000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU2520', 'PCS', 1.000, 1, 0, 70000.00, 65000.00, 1, 1
UNION ALL SELECT 'MU2521', 'PCS', 1.000, 1, 0, 29000.00, 14575.00, 1, 1
UNION ALL SELECT 'MU2522', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU2523', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU2524', 'PCS', 1.000, 1, 0, 29000.00, 23000.00, 1, 1
UNION ALL SELECT 'MU2525', 'PCS', 1.000, 1, 0, 38000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU2526', 'PCS', 1.000, 1, 0, 38000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU2527', 'PCS', 1.000, 1, 0, 98000.00, 67500.00, 1, 1
UNION ALL SELECT 'MU2528', 'PCS', 1.000, 1, 0, 70000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU2529', 'PCS', 1.000, 1, 0, 70000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU2530', 'PCS', 1.000, 1, 0, 60000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU2531', 'PCS', 1.000, 1, 0, 174000.00, 115500.00, 1, 1
UNION ALL SELECT 'MU2532', 'PCS', 1.000, 1, 0, 8000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2533', 'PCS', 1.000, 1, 0, 8000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2534', 'BKS', 1.000, 1, 0, 12000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2535', 'PCS', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2536', 'PCS', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2537', 'PCS', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2538', 'PCS', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2539', 'PCS', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2540', 'PCS', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2541', 'PCS', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2542', 'PCS', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2543', 'PCS', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2544', 'PCS', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2545', 'KLG', 1.000, 1, 0, 124000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU2546', 'KLG', 1.000, 1, 0, 124000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU2547', 'KLG', 1.000, 1, 0, 124000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU2548', 'SET', 1.000, 1, 0, 120000.00, 92000.00, 1, 1
UNION ALL SELECT 'MU2549', 'KLG', 1.000, 1, 0, 50000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU2550', 'PCS', 1.000, 1, 0, 29000.00, 14700.00, 1, 1
UNION ALL SELECT 'MU2551', 'PCS', 1.000, 1, 0, 43000.00, 21500.00, 1, 1
UNION ALL SELECT 'MU2552', 'PCS', 1.000, 1, 0, 44000.00, 22000.00, 1, 1
UNION ALL SELECT 'MU2553', 'PCS', 1.000, 1, 0, 46000.00, 22500.00, 1, 1
UNION ALL SELECT 'MU2554', 'PCS', 1.000, 1, 0, 47000.00, 23000.00, 1, 1
UNION ALL SELECT 'MU2555', 'PCS', 1.000, 1, 0, 47000.00, 23000.00, 1, 1
UNION ALL SELECT 'MU2556', 'PCS', 1.000, 1, 0, 48000.00, 23500.00, 1, 1
UNION ALL SELECT 'MU2557', 'PCS', 1.000, 1, 0, 48000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU2558', 'PCS', 1.000, 1, 0, 49000.00, 24500.00, 1, 1
UNION ALL SELECT 'MU2559', 'PCS', 1.000, 1, 0, 50000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU2560', 'PCS', 1.000, 1, 0, 51000.00, 25500.00, 1, 1
UNION ALL SELECT 'MU2561', 'PCS', 1.000, 1, 0, 52000.00, 26000.00, 1, 1
UNION ALL SELECT 'MU2562', 'PCS', 1.000, 1, 0, 52000.00, 26000.00, 1, 1
UNION ALL SELECT 'MU2563', 'PCS', 1.000, 1, 0, 53000.00, 26500.00, 1, 1
UNION ALL SELECT 'MU2564', 'PCS', 1.000, 1, 0, 53000.00, 27000.00, 1, 1
UNION ALL SELECT 'MU2565', 'PCS', 1.000, 1, 0, 54000.00, 27500.00, 1, 1
UNION ALL SELECT 'MU2566', 'PCS', 1.000, 1, 0, 33000.00, 17000.00, 1, 1
UNION ALL SELECT 'MU2567', 'PCS', 1.000, 1, 0, 36000.00, 17500.00, 1, 1
UNION ALL SELECT 'MU2568', 'PCS', 1.000, 1, 0, 37000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU2569', 'PCS', 1.000, 1, 0, 37000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU2570', 'PCS', 1.000, 1, 0, 38000.00, 18500.00, 1, 1
UNION ALL SELECT 'MU2571', 'PCS', 1.000, 1, 0, 39000.00, 19000.00, 1, 1
UNION ALL SELECT 'MU2572', 'PCS', 1.000, 1, 0, 40000.00, 19500.00, 1, 1
UNION ALL SELECT 'MU2573', 'PCS', 1.000, 1, 0, 40000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU2574', 'PCS', 1.000, 1, 0, 42000.00, 20500.00, 1, 1
UNION ALL SELECT 'MU2575', 'PCS', 1.000, 1, 0, 42000.00, 21000.00, 1, 1
UNION ALL SELECT 'MU2576', 'PCS', 1.000, 1, 0, 42000.00, 21000.00, 1, 1
UNION ALL SELECT 'MU2577', 'PCS', 1.000, 1, 0, 75000.00, 38000.00, 1, 1
UNION ALL SELECT 'MU2578', 'PCS', 1.000, 1, 0, 76000.00, 38500.00, 1, 1
UNION ALL SELECT 'MU2579', 'PCS', 1.000, 1, 0, 77000.00, 39000.00, 1, 1
UNION ALL SELECT 'MU2580', 'PCS', 1.000, 1, 0, 80000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU2581', 'PCS', 1.000, 1, 0, 80000.00, 40500.00, 1, 1
UNION ALL SELECT 'MU2582', 'PCS', 1.000, 1, 0, 82000.00, 41000.00, 1, 1
UNION ALL SELECT 'MU2583', 'PCS', 1.000, 1, 0, 82000.00, 49250.00, 1, 1
UNION ALL SELECT 'MU2584', 'PCS', 1.000, 1, 0, 84000.00, 50250.00, 1, 1
UNION ALL SELECT 'MU2585', 'PCS', 1.000, 1, 0, 85000.00, 51000.00, 1, 1
UNION ALL SELECT 'MU2586', 'PCS', 1.000, 1, 0, 86000.00, 43500.00, 1, 1
UNION ALL SELECT 'MU2587', 'PCS', 1.000, 1, 0, 88000.00, 44500.00, 1, 1
UNION ALL SELECT 'MU2588', 'PCS', 1.000, 1, 0, 90000.00, 45000.00, 1, 1
UNION ALL SELECT 'MU2589', 'PCS', 1.000, 1, 0, 91000.00, 45500.00, 1, 1
UNION ALL SELECT 'MU2590', 'PCS', 1.000, 1, 0, 91000.00, 54700.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU2591' AS sku, 'PCS' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 92000.00 AS selling_price, 55500.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU2592', 'PCS', 1.000, 1, 0, 94000.00, 47500.00, 1, 1
UNION ALL SELECT 'MU2593', 'PCS', 1.000, 1, 0, 95000.00, 57000.00, 1, 1
UNION ALL SELECT 'MU2594', 'PCS', 1.000, 1, 0, 96000.00, 57700.00, 1, 1
UNION ALL SELECT 'MU2595', 'PCS', 1.000, 1, 0, 97000.00, 58500.00, 1, 1
UNION ALL SELECT 'MU2596', 'PCS', 1.000, 1, 0, 98000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU2597', 'PCS', 1.000, 1, 0, 99000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU2598', 'PCS', 1.000, 1, 0, 64000.00, 32000.00, 1, 1
UNION ALL SELECT 'MU2599', 'PCS', 1.000, 1, 0, 64000.00, 38500.00, 1, 1
UNION ALL SELECT 'MU2600', 'PCS', 1.000, 1, 0, 65000.00, 33000.00, 1, 1
UNION ALL SELECT 'MU2601', 'PCS', 1.000, 1, 0, 66000.00, 33500.00, 1, 1
UNION ALL SELECT 'MU2602', 'PCS', 1.000, 1, 0, 69000.00, 34500.00, 1, 1
UNION ALL SELECT 'MU2603', 'PCS', 1.000, 1, 0, 70000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU2604', 'PCS', 1.000, 1, 0, 71000.00, 35500.00, 1, 1
UNION ALL SELECT 'MU2605', 'PCS', 1.000, 1, 0, 28000.00, 17500.00, 1, 1
UNION ALL SELECT 'MU2606', 'SET', 1.000, 1, 0, 80000.00, 45000.00, 1, 1
UNION ALL SELECT 'MU2607', 'SET', 1.000, 1, 0, 61000.00, 42084.00, 1, 1
UNION ALL SELECT 'MU2608', 'SET', 1.000, 1, 0, 100000.00, 65000.00, 1, 1
UNION ALL SELECT 'MU2609', 'SET', 1.000, 1, 0, 100000.00, 65000.00, 1, 1
UNION ALL SELECT 'MU2610', 'PCS', 1.000, 1, 0, 100.00, 50.00, 1, 1
UNION ALL SELECT 'MU2611', 'KG', 1.000, 1, 0, 35000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2612', 'GLN', 1.000, 1, 0, 165000.00, 134000.00, 1, 1
UNION ALL SELECT 'MU2613', 'PCS', 1.000, 1, 0, 100.00, 50.00, 1, 1
UNION ALL SELECT 'MU2614', 'PCS', 1.000, 1, 0, 100.00, 50.00, 1, 1
UNION ALL SELECT 'MU2615', 'PCS', 1.000, 1, 0, 300.00, 250.00, 1, 1
UNION ALL SELECT 'MU2616', 'PCS', 1.000, 1, 0, 300.00, 250.00, 1, 1
UNION ALL SELECT 'MU2617', 'PCS', 1.000, 1, 0, 300.00, 250.00, 1, 1
UNION ALL SELECT 'MU2618', 'PCS', 1.000, 1, 0, 100.00, 50.00, 1, 1
UNION ALL SELECT 'MU2619', 'SET', 1.000, 1, 0, 45000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2620', 'BTL', 1.000, 1, 0, 75000.00, 62100.00, 1, 1
UNION ALL SELECT 'MU2621', 'PCS', 1.000, 1, 0, 38000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU2622', 'SET', 1.000, 1, 0, 2019000.00, 1145100.00, 1, 1
UNION ALL SELECT 'MU2623', 'PCS', 1.000, 1, 0, 8000.00, 6400.00, 1, 1
UNION ALL SELECT 'MU2624', 'MTR', 1.000, 1, 0, 65000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2625', 'PCS', 1.000, 1, 0, 54000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2626', 'PCS', 1.000, 1, 0, 500.00, 0.00, 1, 1
UNION ALL SELECT 'MU2627', 'PSG', 1.000, 1, 0, 8000.00, 3750.00, 1, 1
UNION ALL SELECT 'MU2628', 'PCS', 1.000, 1, 0, 35000.00, 8500.00, 1, 1
UNION ALL SELECT 'MU2629', 'PCS', 1.000, 1, 0, 18000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2630', 'PCS', 1.000, 1, 0, 30000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2631', 'PCS', 1.000, 1, 0, 7000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2632', 'PCS', 1.000, 1, 0, 8000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2633', 'PCS', 1.000, 1, 0, 15000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2634', 'PCS', 1.000, 1, 0, 21000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2635', 'PCS', 1.000, 1, 0, 23000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2636', 'PCS', 1.000, 1, 0, 28000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2637', 'PCS', 1.000, 1, 0, 500.00, 0.00, 1, 1
UNION ALL SELECT 'MU2638', 'KLG', 1.000, 1, 0, 155000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2639', 'PCS', 1.000, 1, 0, 3000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2640', 'PCS', 1.000, 1, 0, 3000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2641', 'PCS', 1.000, 1, 0, 3000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2642', 'PCS', 1.000, 1, 0, 8000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2643', 'PCS', 1.000, 1, 0, 8000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2644', '50 METER', 1.000, 1, 0, 1050000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2645', 'PCS', 1.000, 1, 0, 500.00, 0.00, 1, 1
UNION ALL SELECT 'MU2646', 'PCS', 1.000, 1, 0, 23000.00, 12650.00, 1, 1
UNION ALL SELECT 'MU2647', 'BTL', 1.000, 1, 0, 10000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU2648', 'MTR', 1.000, 1, 0, 68000.00, 44464.00, 1, 1
UNION ALL SELECT 'MU2649', 'PCS', 1.000, 1, 0, 5000.00, 2243.00, 1, 1
UNION ALL SELECT 'MU2650', 'PCS', 1.000, 1, 0, 4000.00, 2400.00, 1, 1
UNION ALL SELECT 'MU2651', 'PCS', 1.000, 1, 0, 7000.00, 2650.00, 1, 1
UNION ALL SELECT 'MU2652', 'PCS', 1.000, 1, 0, 5000.00, 2029.00, 1, 1
UNION ALL SELECT 'MU2654', 'PCS', 1.000, 1, 0, 9000.00, 5210.00, 1, 1
UNION ALL SELECT 'MU2655', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU2656', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU2657', 'UNIT', 1.000, 1, 0, 300000.00, 260000.00, 1, 1
UNION ALL SELECT 'MU2658', 'KLG', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2659', 'KLG', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2660', 'KLG', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2661', 'KLG', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2662', 'KLG', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2663', 'KLG', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2664', 'KLG', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2665', 'KLG', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2666', 'KLG', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2667', 'KLG', 1.000, 1, 0, 28000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2668', 'PCS', 1.000, 1, 0, 18000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU2669', 'PCS', 1.000, 1, 0, 5000.00, 3000.00, 1, 1
UNION ALL SELECT 'MU2670', 'PCS', 1.000, 1, 0, 64000.00, 42080.00, 1, 1
UNION ALL SELECT 'MU2671', 'PCS', 1.000, 1, 0, 71000.00, 47916.00, 1, 1
UNION ALL SELECT 'MU2672', 'PCS', 1.000, 1, 0, 24000.00, 15895.00, 1, 1
UNION ALL SELECT 'MU2673', 'MTR', 1.000, 1, 0, 6000.00, 1500.00, 1, 1
UNION ALL SELECT 'MU2674', 'MTR', 1.000, 1, 0, 7000.00, 2000.00, 1, 1
UNION ALL SELECT 'MU2675', 'MTR', 1.000, 1, 0, 8000.00, 2500.00, 1, 1
UNION ALL SELECT 'MU2676', 'BTL', 1.000, 1, 0, 36000.00, 23500.00, 1, 1
UNION ALL SELECT 'MU2677', 'PCS', 1.000, 1, 0, 119000.00, 94500.00, 1, 1
UNION ALL SELECT 'MU2678', 'PCS', 1.000, 1, 0, 45000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU2679', 'PCS', 1.000, 1, 0, 12000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU2680', 'SET', 1.000, 1, 0, 398000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2681', 'LBR', 1.000, 1, 0, 153000.00, 117053.00, 1, 1
UNION ALL SELECT 'MU2682', 'SAK', 1.000, 1, 0, 134000.00, 112000.00, 1, 1
UNION ALL SELECT 'MU2683', 'PCS', 1.000, 1, 0, 9000.00, 3750.00, 1, 1
UNION ALL SELECT 'MU2684', 'SAK', 1.000, 1, 0, 105000.00, 80000.00, 1, 1
UNION ALL SELECT 'MU2685', 'PCS', 1.000, 1, 0, 7000.00, 2500.00, 1, 1
UNION ALL SELECT 'MU2686', 'SAK', 1.000, 1, 0, 87000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2687', 'SAK', 1.000, 1, 0, 82000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2688', 'SAK', 1.000, 1, 0, 100000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2689', 'PCS', 1.000, 1, 0, 110000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2690', 'PCS', 1.000, 1, 0, 78000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2691', 'UNIT', 1.000, 1, 0, 1119000.00, 895000.00, 1, 1
UNION ALL SELECT 'MU2692', 'LBR', 1.000, 1, 0, 178000.00, 137624.00, 1, 1
UNION ALL SELECT 'MU2693', 'SET', 1.000, 1, 0, 130000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2694', 'LBR', 1.000, 1, 0, 95000.00, 80000.00, 1, 1
UNION ALL SELECT 'MU2695', 'LBR', 1.000, 1, 0, 80000.00, 49500.00, 1, 1
UNION ALL SELECT 'MU2696', 'UNIT', 1.000, 1, 0, 1650000.00, 975000.00, 1, 1
UNION ALL SELECT 'MU2697', 'LBR', 1.000, 1, 0, 145000.00, 63000.00, 1, 1
UNION ALL SELECT 'MU2698', 'PCS', 1.000, 1, 0, 58000.00, 45000.00, 1, 1
UNION ALL SELECT 'MU2699', 'LBR', 1.000, 1, 0, 72000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2700', 'BTG', 1.000, 1, 0, 38000.00, 25004.00, 1, 1
UNION ALL SELECT 'MU2701', 'BTG', 1.000, 1, 0, 64000.00, 44686.00, 1, 1
UNION ALL SELECT 'MU2702', 'BTG', 1.000, 1, 0, 95000.00, 68860.00, 1, 1
UNION ALL SELECT 'MU2703', 'BTG', 1.000, 1, 0, 128000.00, 99940.00, 1, 1
UNION ALL SELECT 'MU2704', 'BTG', 1.000, 1, 0, 280000.00, 224000.00, 1, 1
UNION ALL SELECT 'MU2705', 'BTG', 1.000, 1, 0, 615000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2706', 'BTG', 1.000, 1, 0, 906000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2707', 'BTG', 1.000, 1, 0, 1140000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2708', 'BTG', 1.000, 1, 0, 233000.00, 166000.00, 1, 1
UNION ALL SELECT 'MU2709', 'UNIT', 1.000, 1, 0, 1980000.00, 1320000.00, 1, 1
UNION ALL SELECT 'MU2710', 'PCS', 1.000, 1, 0, 10000.00, 4950.00, 1, 1
UNION ALL SELECT 'MU2711', 'PCS', 1.000, 1, 0, 10000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2712', 'BTG', 1.000, 1, 0, 143000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2713', 'BTG', 1.000, 1, 0, 190000.00, 107100.00, 1, 1
UNION ALL SELECT 'MU2714', 'PCS', 1.000, 1, 0, 48000.00, 26400.00, 1, 1
UNION ALL SELECT 'MU2715', 'BTG', 1.000, 1, 0, 220000.00, 110000.00, 1, 1
UNION ALL SELECT 'MU2716', 'LBR', 1.000, 1, 0, 86000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2717', 'LBR', 1.000, 1, 0, 79000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2718', 'LBR', 1.000, 1, 0, 94000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2719', 'LBR', 1.000, 1, 0, 84000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2720', 'LBR', 1.000, 1, 0, 120000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2721', 'LBR', 1.000, 1, 0, 191000.00, 100000.00, 1, 1
UNION ALL SELECT 'MU2722', 'LBR', 1.000, 1, 0, 251000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2723', 'DUS', 1.000, 1, 0, 83000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2724', 'DUS', 1.000, 1, 0, 77000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU2725', 'DUS', 1.000, 1, 0, 70000.00, 66000.00, 1, 1
UNION ALL SELECT 'MU2726', 'DUS', 1.000, 1, 0, 77000.00, 66000.00, 1, 1
UNION ALL SELECT 'MU2727', 'DUS', 1.000, 1, 0, 66000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2728', 'DUS', 1.000, 1, 0, 70000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU2729', 'DUS', 1.000, 1, 0, 70000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU2730', 'DUS', 1.000, 1, 0, 77000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2731', 'DUS', 1.000, 1, 0, 78000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU2732', 'DUS', 1.000, 1, 0, 78000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU2733', 'PCS', 1.000, 1, 0, 70000.00, 24200.00, 1, 1
UNION ALL SELECT 'MU2734', 'PCS', 1.000, 1, 0, 65000.00, 35750.00, 1, 1
UNION ALL SELECT 'MU2735', 'DUS', 1.000, 1, 0, 107000.00, 72399.00, 1, 1
UNION ALL SELECT 'MU2736', 'DUS', 1.000, 1, 0, 83000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2737', 'DUS', 1.000, 1, 0, 83000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2738', 'DUS', 1.000, 1, 0, 75000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2739', 'DUS', 1.000, 1, 0, 75000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2740', 'DUS', 1.000, 1, 0, 88000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2741', 'PCS', 1.000, 1, 0, 80000.00, 75000.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU2742' AS sku, 'PCS' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 128000.00 AS selling_price, 77250.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU2743', 'DUS', 1.000, 1, 0, 88000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2744', 'PCS', 1.000, 1, 0, 20000.00, 14500.00, 1, 1
UNION ALL SELECT 'MU2745', 'DUS', 1.000, 1, 0, 110000.00, 82150.00, 1, 1
UNION ALL SELECT 'MU2746', 'GLN', 1.000, 1, 0, 186000.00, 155800.00, 1, 1
UNION ALL SELECT 'MU2747', 'DUS', 1.000, 1, 0, 77000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2748', 'DUS', 1.000, 1, 0, 98000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2749', 'DUS', 1.000, 1, 0, 115000.00, 76500.00, 1, 1
UNION ALL SELECT 'MU2750', 'PCS', 1.000, 1, 0, 126000.00, 75750.00, 1, 1
UNION ALL SELECT 'MU2751', 'PCS', 1.000, 1, 0, 125000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU2752', 'PCS', 1.000, 1, 0, 131000.00, 79500.00, 1, 1
UNION ALL SELECT 'MU2753', 'GLN', 1.000, 1, 0, 186000.00, 155800.00, 1, 1
UNION ALL SELECT 'MU2754', 'SET', 1.000, 1, 0, 30000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2755', 'SET', 1.000, 1, 0, 35000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU2756', 'PCS', 1.000, 1, 0, 49000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2757', 'UNIT', 1.000, 1, 0, 1563000.00, 1045333.00, 1, 1
UNION ALL SELECT 'MU2758', 'DUS', 1.000, 1, 0, 98000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2759', 'PCS', 1.000, 1, 0, 110000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU2760', 'DUS', 1.000, 1, 0, 110000.00, 76045.00, 1, 1
UNION ALL SELECT 'MU2761', 'BTG', 1.000, 1, 0, 141000.00, 110379.00, 1, 1
UNION ALL SELECT 'MU2762', 'PCS', 1.000, 1, 0, 17000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU2763', 'PCS', 1.000, 1, 0, 42000.00, 19000.00, 1, 1
UNION ALL SELECT 'MU2764', 'DUS', 1.000, 1, 0, 98000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2765', 'DUS', 1.000, 1, 0, 107000.00, 74370.00, 1, 1
UNION ALL SELECT 'MU2766', 'DUS', 1.000, 1, 0, 103000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2767', 'BKS', 1.000, 1, 0, 11000.00, 5500.00, 1, 1
UNION ALL SELECT 'MU2768', 'BKS', 1.000, 1, 0, 12000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU2769', 'UNIT', 1.000, 1, 0, 300000.00, 260000.00, 1, 1
UNION ALL SELECT 'MU2770', 'DUS', 1.000, 1, 0, 82000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2771', 'PSG', 1.000, 1, 0, 74000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2772', 'BKS', 1.000, 1, 0, 15000.00, 7500.00, 1, 1
UNION ALL SELECT 'MU2773', 'PCS', 1.000, 1, 0, 34000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU2774', 'PSG', 1.000, 1, 0, 74000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2775', 'DUS', 1.000, 1, 0, 98000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2776', 'PCS', 1.000, 1, 0, 23000.00, 9500.00, 1, 1
UNION ALL SELECT 'MU2777', 'DUS', 1.000, 1, 0, 98000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2778', 'DUS', 1.000, 1, 0, 105000.00, 100000.00, 1, 1
UNION ALL SELECT 'MU2779', 'DUS', 1.000, 1, 0, 308000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2780', 'DUS', 1.000, 1, 0, 226000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2781', 'DUS', 1.000, 1, 0, 308000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2782', 'DUS', 1.000, 1, 0, 308000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2783', 'DUS', 1.000, 1, 0, 187000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2784', 'DUS', 1.000, 1, 0, 187000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2785', 'DUS', 1.000, 1, 0, 105000.00, 63000.00, 1, 1
UNION ALL SELECT 'MU2786', 'PCS', 1.000, 1, 0, 48000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2787', 'DUS', 1.000, 1, 0, 110000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2788', 'BKS', 1.000, 1, 0, 25000.00, 12500.00, 1, 1
UNION ALL SELECT 'MU2789', 'UNIT', 1.000, 1, 0, 90000.00, 65000.00, 1, 1
UNION ALL SELECT 'MU2790', 'PCS', 1.000, 1, 0, 44000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU2791', 'PCS', 1.000, 1, 0, 32000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU2792', 'DUS', 1.000, 1, 0, 106000.00, 72399.00, 1, 1
UNION ALL SELECT 'MU2793', 'DUS', 1.000, 1, 0, 106000.00, 72399.00, 1, 1
UNION ALL SELECT 'MU2794', 'DUS', 1.000, 1, 0, 99000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2795', 'DUS', 1.000, 1, 0, 99000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2796', 'DUS', 1.000, 1, 0, 99000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2797', 'DUS', 1.000, 1, 0, 99000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2798', 'DUS', 1.000, 1, 0, 99000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2799', 'PCS', 1.000, 1, 0, 20000.00, 13000.00, 1, 1
UNION ALL SELECT 'MU2800', 'PCS', 1.000, 1, 0, 2000.00, 850.00, 1, 1
UNION ALL SELECT 'MU2801', 'DUS', 1.000, 1, 0, 77000.00, 5.00, 1, 1
UNION ALL SELECT 'MU2802', 'DUS', 1.000, 1, 0, 220000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2803', 'SET', 1.000, 1, 0, 203000.00, 98500.00, 1, 1
UNION ALL SELECT 'MU2804', 'PCS', 1.000, 1, 0, 24000.00, 9583.00, 1, 1
UNION ALL SELECT 'MU2805', 'BTG', 1.000, 1, 0, 500000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2806', 'PCS', 1.000, 1, 0, 27000.00, 17500.00, 1, 1
UNION ALL SELECT 'MU2807', 'DUS', 1.000, 1, 0, 81000.00, 51399.00, 1, 1
UNION ALL SELECT 'MU2808', 'PCS', 1.000, 1, 0, 83000.00, 55000.00, 1, 1
UNION ALL SELECT 'MU2809', 'PCS', 1.000, 1, 0, 11000.00, 5416.00, 1, 1
UNION ALL SELECT 'MU2810', 'DUS', 1.000, 1, 0, 101000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2811', 'PCS', 1.000, 1, 0, 25000.00, 18500.00, 1, 1
UNION ALL SELECT 'MU2812', 'PCS', 1.000, 1, 0, 15000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2813', 'BTG', 1.000, 1, 0, 15000.00, 9532.00, 1, 1
UNION ALL SELECT 'MU2814', 'BTG', 1.000, 1, 0, 46000.00, 41100.00, 1, 1
UNION ALL SELECT 'MU2815', 'BTG', 1.000, 1, 0, 21000.00, 18200.00, 1, 1
UNION ALL SELECT 'MU2816', 'BTG', 1.000, 1, 0, 47000.00, 42000.00, 1, 1
UNION ALL SELECT 'MU2817', 'BTG', 1.000, 1, 0, 30000.00, 26400.00, 1, 1
UNION ALL SELECT 'MU2818', 'BTG', 1.000, 1, 0, 32000.00, 28200.00, 1, 1
UNION ALL SELECT 'MU2819', 'BTG', 1.000, 1, 0, 64000.00, 49580.00, 1, 1
UNION ALL SELECT 'MU2820', 'BTG', 1.000, 1, 0, 40000.00, 30826.00, 1, 1
UNION ALL SELECT 'MU2821', 'BTG', 1.000, 1, 0, 68000.00, 52657.00, 1, 1
UNION ALL SELECT 'MU2822', 'BTG', 1.000, 1, 0, 60000.00, 46195.00, 1, 1
UNION ALL SELECT 'MU2823', 'BTG', 1.000, 1, 0, 87000.00, 67487.00, 1, 1
UNION ALL SELECT 'MU2824', 'BTG', 1.000, 1, 0, 77000.00, 59337.00, 1, 1
UNION ALL SELECT 'MU2825', 'BTG', 1.000, 1, 0, 195000.00, 152656.00, 1, 1
UNION ALL SELECT 'MU2826', 'BTG', 1.000, 1, 0, 156000.00, 121214.00, 1, 1
UNION ALL SELECT 'MU2827', 'BTG', 1.000, 1, 0, 244000.00, 190652.00, 1, 1
UNION ALL SELECT 'MU2828', 'BTG', 1.000, 1, 0, 273000.00, 214530.00, 1, 1
UNION ALL SELECT 'MU2829', 'BTG', 1.000, 1, 0, 356000.00, 293624.00, 1, 1
UNION ALL SELECT 'MU2830', 'BTG', 1.000, 1, 0, 136000.00, 91244.00, 1, 1
UNION ALL SELECT 'MU2831', 'BTG', 1.000, 1, 0, 98000.00, 75487.00, 1, 1
UNION ALL SELECT 'MU2832', 'BTG', 1.000, 1, 0, 32000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2833', 'BTG', 1.000, 1, 0, 126000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2834', 'BTG', 1.000, 1, 0, 170000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2835', 'BTG', 1.000, 1, 0, 251000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2836', 'BTG', 1.000, 1, 0, 30000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2837', 'KG', 1.000, 1, 0, 236000.00, 152000.00, 1, 1
UNION ALL SELECT 'MU2838', 'KG', 1.000, 1, 0, 273000.00, 179000.00, 1, 1
UNION ALL SELECT 'MU2839', 'DUS', 1.000, 1, 0, 125000.00, 80000.00, 1, 1
UNION ALL SELECT 'MU2840', 'KG', 1.000, 1, 0, 63000.00, 30450.00, 1, 1
UNION ALL SELECT 'MU2841', 'BTG', 1.000, 1, 0, 22000.00, 19600.00, 1, 1
UNION ALL SELECT 'MU2842', 'DUS', 1.000, 1, 0, 88000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2843', 'DUS', 1.000, 1, 0, 78000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2844', 'PCS', 1.000, 1, 0, 64000.00, 32162.00, 1, 1
UNION ALL SELECT 'MU2845', 'PCS', 1.000, 1, 0, 64000.00, 34414.00, 1, 1
UNION ALL SELECT 'MU2846', 'PCS', 1.000, 1, 0, 64000.00, 36486.00, 1, 1
UNION ALL SELECT 'MU2847', 'UNIT', 1.000, 1, 0, 47000.00, 23423.00, 1, 1
UNION ALL SELECT 'MU2848', 'PCS', 1.000, 1, 0, 85000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU2849', 'SET', 1.000, 1, 0, 320000.00, 297000.00, 1, 1
UNION ALL SELECT 'MU2850', 'PCS', 1.000, 1, 0, 90000.00, 90000.00, 1, 1
UNION ALL SELECT 'MU2851', 'DUS', 1.000, 1, 0, 74000.00, 49000.00, 1, 1
UNION ALL SELECT 'MU2852', 'DUS', 1.000, 1, 0, 74000.00, 49000.00, 1, 1
UNION ALL SELECT 'MU2853', 'DUS', 1.000, 1, 0, 74000.00, 49000.00, 1, 1
UNION ALL SELECT 'MU2854', 'DUS', 1.000, 1, 0, 82000.00, 54500.00, 1, 1
UNION ALL SELECT 'MU2855', 'BKS', 1.000, 1, 0, 28000.00, 14000.00, 1, 1
UNION ALL SELECT 'MU2856', 'DUS', 1.000, 1, 0, 98000.00, 59500.00, 1, 1
UNION ALL SELECT 'MU2857', 'DUS', 1.000, 1, 0, 91000.00, 54500.00, 1, 1
UNION ALL SELECT 'MU2858', 'DUS', 1.000, 1, 0, 83000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU2859', 'DUS', 1.000, 1, 0, 91000.00, 54500.00, 1, 1
UNION ALL SELECT 'MU2860', 'DUS', 1.000, 1, 0, 91000.00, 54500.00, 1, 1
UNION ALL SELECT 'MU2861', 'DUS', 1.000, 1, 0, 83000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU2862', 'DUS', 1.000, 1, 0, 83000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU2863', 'DUS', 1.000, 1, 0, 91000.00, 45500.00, 1, 1
UNION ALL SELECT 'MU2864', 'GLN', 1.000, 1, 0, 186000.00, 162500.00, 1, 1
UNION ALL SELECT 'MU2865', 'GLN', 1.000, 1, 0, 186000.00, 162500.00, 1, 1
UNION ALL SELECT 'MU2866', 'GLN', 1.000, 1, 0, 186000.00, 162500.00, 1, 1
UNION ALL SELECT 'MU2867', 'GLN', 1.000, 1, 0, 186000.00, 162500.00, 1, 1
UNION ALL SELECT 'MU2868', 'PCS', 1.000, 1, 0, 15000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2869', 'PCS', 1.000, 1, 0, 29000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2870', 'DUS', 1.000, 1, 0, 93000.00, 46200.00, 1, 1
UNION ALL SELECT 'MU2871', 'PCS', 1.000, 1, 0, 20000.00, 12083.00, 1, 1
UNION ALL SELECT 'MU2872', 'BTG', 1.000, 1, 0, 40000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2873', 'PCS', 1.000, 1, 0, 76000.00, 50610.00, 1, 1
UNION ALL SELECT 'MU2874', 'PCS', 1.000, 1, 0, 12000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU2875', 'PCS', 1.000, 1, 0, 20000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU2876', 'PCS', 1.000, 1, 0, 194000.00, 117500.00, 1, 1
UNION ALL SELECT 'MU2877', 'DUS', 1.000, 1, 0, 83000.00, 72000.00, 1, 1
UNION ALL SELECT 'MU2878', 'PCS', 1.000, 1, 0, 1000.00, 350.00, 1, 1
UNION ALL SELECT 'MU2879', 'PCS', 1.000, 1, 0, 1200.00, 640.00, 1, 1
UNION ALL SELECT 'MU2880', 'DUS', 1.000, 1, 0, 300000.00, 152950.00, 1, 1
UNION ALL SELECT 'MU2881', 'PCS', 1.000, 1, 0, 900.00, 350.00, 1, 1
UNION ALL SELECT 'MU2882', 'PCS', 1.000, 1, 0, 40000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU2883', 'PCS', 1.000, 1, 0, 10000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU2884', 'SET', 1.000, 1, 0, 75000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2885', 'PCS', 1.000, 1, 0, 23000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2886', 'DUS', 1.000, 1, 0, 102000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU2887', 'DUS', 1.000, 1, 0, 102000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU2888', 'PCS', 1.000, 1, 0, 129000.00, 78000.00, 1, 1
UNION ALL SELECT 'MU2889', 'PCS', 1.000, 1, 0, 35000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU2890', 'DUS', 1.000, 1, 0, 102000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU2891', 'DUS', 1.000, 1, 0, 67000.00, 48999.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU2892' AS sku, 'DUS' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 102000.00 AS selling_price, 88000.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU2893', 'DUS', 1.000, 1, 0, 67000.00, 48999.00, 1, 1
UNION ALL SELECT 'MU2894', 'DUS', 1.000, 1, 0, 102000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU2895', 'PCS', 1.000, 1, 0, 17000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU2896', 'PCS', 1.000, 1, 0, 2000.00, 500.00, 1, 1
UNION ALL SELECT 'MU2897', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU2898', 'PCS', 1.000, 1, 0, 13000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2899', 'LBR', 1.000, 1, 0, 74000.00, 55000.00, 1, 1
UNION ALL SELECT 'MU2900', 'MTR', 1.000, 1, 0, 118000.00, 81500.00, 1, 1
UNION ALL SELECT 'MU2901', 'LBR', 1.000, 1, 0, 1666000.00, 1332000.00, 1, 1
UNION ALL SELECT 'MU2902', 'SET', 1.000, 1, 0, 40000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2903', 'DUS', 1.000, 1, 0, 83000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU2904', 'PCS', 1.000, 1, 0, 22000.00, 14500.00, 1, 1
UNION ALL SELECT 'MU2905', 'PCS', 1.000, 1, 0, 18000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU2906', 'PCS', 1.000, 1, 0, 23000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU2907', 'DUS', 1.000, 1, 0, 77000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU2908', 'DUS', 1.000, 1, 0, 77000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU2909', 'DUS', 1.000, 1, 0, 77000.00, 62100.00, 1, 1
UNION ALL SELECT 'MU2910', 'DUS', 1.000, 1, 0, 80000.00, 67250.00, 1, 1
UNION ALL SELECT 'MU2911', 'LBR', 1.000, 1, 0, 95000.00, 75200.00, 1, 1
UNION ALL SELECT 'MU2912', 'PCS', 1.000, 1, 0, 100000.00, 70000.00, 1, 1
UNION ALL SELECT 'MU2913', 'BTG', 1.000, 1, 0, 178000.00, 68000.00, 1, 1
UNION ALL SELECT 'MU2914', 'PCS', 1.000, 1, 0, 59000.00, 32450.00, 1, 1
UNION ALL SELECT 'MU2915', 'BTG', 1.000, 1, 0, 235000.00, 135600.00, 1, 1
UNION ALL SELECT 'MU2916', 'BTG', 1.000, 1, 0, 109000.00, 84710.00, 1, 1
UNION ALL SELECT 'MU2917', 'SET', 1.000, 1, 0, 250000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2918', 'PCS', 1.000, 1, 0, 57000.00, 38000.00, 1, 1
UNION ALL SELECT 'MU2919', 'PCS', 1.000, 1, 0, 83000.00, 55000.00, 1, 1
UNION ALL SELECT 'MU2920', 'PCS', 1.000, 1, 0, 90000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU2921', 'PCS', 1.000, 1, 0, 90000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU2922', 'SET', 1.000, 1, 0, 167000.00, 70000.00, 1, 1
UNION ALL SELECT 'MU2923', 'PCS', 1.000, 1, 0, 235000.00, 160000.00, 1, 1
UNION ALL SELECT 'MU2924', 'PCS', 1.000, 1, 0, 150000.00, 80000.00, 1, 1
UNION ALL SELECT 'MU2925', 'PCS', 1.000, 1, 0, 13000.00, 6500.00, 1, 1
UNION ALL SELECT 'MU2926', 'PCS', 1.000, 1, 0, 163000.00, 89650.00, 1, 1
UNION ALL SELECT 'MU2927', 'PCS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU2928', 'PCS', 1.000, 1, 0, 8000.00, 4500.00, 1, 1
UNION ALL SELECT 'MU2929', 'DUS', 1.000, 1, 0, 105000.00, 69750.00, 1, 1
UNION ALL SELECT 'MU2930', 'DUS', 1.000, 1, 0, 321000.00, 213750.00, 1, 1
UNION ALL SELECT 'MU2931', 'DUS', 1.000, 1, 0, 70000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU2932', 'DUS', 1.000, 1, 0, 372000.00, 247500.00, 1, 1
UNION ALL SELECT 'MU2933', 'UNIT', 1.000, 1, 0, 143000.00, 102000.00, 1, 1
UNION ALL SELECT 'MU2934', 'KLG', 1.000, 1, 0, 66000.00, 50196.00, 1, 1
UNION ALL SELECT 'MU2935', 'KLG', 1.000, 1, 0, 66000.00, 50285.00, 1, 1
UNION ALL SELECT 'MU2936', 'PCS', 1.000, 1, 0, 23000.00, 17000.00, 1, 1
UNION ALL SELECT 'MU2937', 'PCS', 1.000, 1, 0, 27000.00, 21000.00, 1, 1
UNION ALL SELECT 'MU2938', 'PCS', 1.000, 1, 0, 27000.00, 21000.00, 1, 1
UNION ALL SELECT 'MU2939', 'PCS', 1.000, 1, 0, 30000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU2940', 'PCS', 1.000, 1, 0, 35000.00, 27000.00, 1, 1
UNION ALL SELECT 'MU2941', 'PCS', 1.000, 1, 0, 45000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU2942', 'PCS', 1.000, 1, 0, 38000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU2943', 'PCS', 1.000, 1, 0, 33000.00, 26000.00, 1, 1
UNION ALL SELECT 'MU2944', 'PCS', 1.000, 1, 0, 40000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU2945', 'PCS', 1.000, 1, 0, 11000.00, 8500.00, 1, 1
UNION ALL SELECT 'MU2946', 'PCS', 1.000, 1, 0, 10000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU2947', 'PCS', 1.000, 1, 0, 42000.00, 33000.00, 1, 1
UNION ALL SELECT 'MU2948', 'PCS', 1.000, 1, 0, 30000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU2949', 'ROL', 1.000, 1, 0, 785000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2950', 'MTR', 1.000, 1, 0, 45000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2951', 'PCS', 1.000, 1, 0, 106000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2952', 'PCS', 1.000, 1, 0, 200000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2953', 'PCS', 1.000, 1, 0, 140000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2954', 'UNIT', 1.000, 1, 0, 11813000.00, 11250000.00, 1, 1
UNION ALL SELECT 'MU2955', 'PSG', 1.000, 1, 0, 25000.00, 13475.00, 1, 1
UNION ALL SELECT 'MU2956', 'PCS', 1.000, 1, 0, 123000.00, 74250.00, 1, 1
UNION ALL SELECT 'MU2957', 'PCS', 1.000, 1, 0, 121000.00, 73500.00, 1, 1
UNION ALL SELECT 'MU2958', 'PCS', 1.000, 1, 0, 120000.00, 72700.00, 1, 1
UNION ALL SELECT 'MU2959', 'PCS', 1.000, 1, 0, 119000.00, 72000.00, 1, 1
UNION ALL SELECT 'MU2960', 'PCS', 1.000, 1, 0, 118000.00, 71250.00, 1, 1
UNION ALL SELECT 'MU2961', 'PCS', 1.000, 1, 0, 117000.00, 70500.00, 1, 1
UNION ALL SELECT 'MU2962', 'PCS', 1.000, 1, 0, 116000.00, 69750.00, 1, 1
UNION ALL SELECT 'MU2963', 'PCS', 1.000, 1, 0, 115000.00, 69000.00, 1, 1
UNION ALL SELECT 'MU2964', 'PCS', 1.000, 1, 0, 113000.00, 68250.00, 1, 1
UNION ALL SELECT 'MU2965', 'PCS', 1.000, 1, 0, 112000.00, 67500.00, 1, 1
UNION ALL SELECT 'MU2966', 'PCS', 1.000, 1, 0, 107000.00, 64500.00, 1, 1
UNION ALL SELECT 'MU2967', 'PCS', 1.000, 1, 0, 103000.00, 62250.00, 1, 1
UNION ALL SELECT 'MU2968', 'PCS', 1.000, 1, 0, 106000.00, 63700.00, 1, 1
UNION ALL SELECT 'MU2969', 'PCS', 1.000, 1, 0, 105000.00, 63000.00, 1, 1
UNION ALL SELECT 'MU2970', 'PCS', 1.000, 1, 0, 103000.00, 62250.00, 1, 1
UNION ALL SELECT 'MU2971', 'PCS', 1.000, 1, 0, 102000.00, 61500.00, 1, 1
UNION ALL SELECT 'MU2972', 'PCS', 1.000, 1, 0, 101000.00, 60750.00, 1, 1
UNION ALL SELECT 'MU2973', 'PCS', 1.000, 1, 0, 110000.00, 66750.00, 1, 1
UNION ALL SELECT 'MU2974', 'PCS', 1.000, 1, 0, 109000.00, 66000.00, 1, 1
UNION ALL SELECT 'MU2975', 'PCS', 1.000, 1, 0, 130000.00, 78700.00, 1, 1
UNION ALL SELECT 'MU2976', 'PCS', 1.000, 1, 0, 132000.00, 80250.00, 1, 1
UNION ALL SELECT 'MU2977', 'PCS', 1.000, 1, 0, 9000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU2978', 'SET', 1.000, 1, 0, 260000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2979', 'SET', 1.000, 1, 0, 322000.00, 0.00, 1, 1
UNION ALL SELECT 'MU2980', 'UNIT', 1.000, 1, 0, 3700000.00, 3000000.00, 1, 1
UNION ALL SELECT 'MU2981', 'UNIT', 1.000, 1, 0, 210000.00, 155000.00, 1, 1
UNION ALL SELECT 'MU2982', 'PCS', 1.000, 1, 0, 53000.00, 53000.00, 1, 1
UNION ALL SELECT 'MU2983', 'PCS', 1.000, 1, 0, 28000.00, 23000.00, 1, 1
UNION ALL SELECT 'MU2984', 'PCS', 1.000, 1, 0, 30000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU2985', 'PCS', 1.000, 1, 0, 35000.00, 17050.00, 1, 1
UNION ALL SELECT 'MU2986', 'PCS', 1.000, 1, 0, 1500.00, 0.00, 1, 1
UNION ALL SELECT 'MU2987', 'PCS', 1.000, 1, 0, 145000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU2988', 'PCS', 1.000, 1, 0, 117000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU2989', 'PCS', 1.000, 1, 0, 17000.00, 8333.00, 1, 1
UNION ALL SELECT 'MU2990', 'PCS', 1.000, 1, 0, 17000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU2991', 'PCS', 1.000, 1, 0, 32000.00, 24100.00, 1, 1
UNION ALL SELECT 'MU2992', 'PCS', 1.000, 1, 0, 13000.00, 6500.00, 1, 1
UNION ALL SELECT 'MU2993', 'PCS', 1.000, 1, 0, 251000.00, 145000.00, 1, 1
UNION ALL SELECT 'MU2994', 'PSG', 1.000, 1, 0, 271000.00, 190000.00, 1, 1
UNION ALL SELECT 'MU2995', 'PCS', 1.000, 1, 0, 18000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU2996', 'IKAT', 1.000, 1, 0, 201000.00, 100000.00, 1, 1
UNION ALL SELECT 'MU2997', 'IKAT', 1.000, 1, 0, 174000.00, 127450.00, 1, 1
UNION ALL SELECT 'MU2998', 'IKAT', 1.000, 1, 0, 153000.00, 112350.00, 1, 1
UNION ALL SELECT 'MU2999', 'IKAT', 1.000, 1, 0, 135000.00, 99400.00, 1, 1
UNION ALL SELECT 'MU3000', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU3001', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU3002', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU3003', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU3004', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU3005', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU3006', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU3007', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU3008', 'PCS', 1.000, 1, 0, 33000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU3009', 'BKS', 1.000, 1, 0, 15000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU3010', 'KLG', 1.000, 1, 0, 74000.00, 54337.00, 1, 1
UNION ALL SELECT 'MU3011', 'GLN', 1.000, 1, 0, 378000.00, 278289.00, 1, 1
UNION ALL SELECT 'MU3012', 'SET', 1.000, 1, 0, 40000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU3013', 'PCS', 1.000, 1, 0, 20000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU3014', 'PCS', 1.000, 1, 0, 24000.00, 17000.00, 1, 1
UNION ALL SELECT 'MU3015', 'PCS', 1.000, 1, 0, 31000.00, 21000.00, 1, 1
UNION ALL SELECT 'MU3016', 'BTL', 1.000, 1, 0, 95000.00, 72500.00, 1, 1
UNION ALL SELECT 'MU3017', 'PCS', 1.000, 1, 0, 38000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU3018', 'PCS', 1.000, 1, 0, 45000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU3019', 'PCS', 1.000, 1, 0, 24000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU3020', 'PCS', 1.000, 1, 0, 15000.00, 11500.00, 1, 1
UNION ALL SELECT 'MU3021', 'PSG', 1.000, 1, 0, 145000.00, 79750.00, 1, 1
UNION ALL SELECT 'MU3022', 'PCS', 1.000, 1, 0, 324000.00, 240000.00, 1, 1
UNION ALL SELECT 'MU3023', 'PCS', 1.000, 1, 0, 284000.00, 210000.00, 1, 1
UNION ALL SELECT 'MU3024', 'UNIT', 1.000, 1, 0, 450000.00, 450000.00, 1, 1
UNION ALL SELECT 'MU3025', 'PCS', 1.000, 1, 0, 65000.00, 65000.00, 1, 1
UNION ALL SELECT 'MU3026', 'PCS', 1.000, 1, 0, 70000.00, 70000.00, 1, 1
UNION ALL SELECT 'MU3027', 'PCS', 1.000, 1, 0, 37000.00, 37000.00, 1, 1
UNION ALL SELECT 'MU3028', 'PCS', 1.000, 1, 0, 30000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU3029', 'PCS', 1.000, 1, 0, 18000.00, 9060.00, 1, 1
UNION ALL SELECT 'MU3030', 'PCS', 1.000, 1, 0, 200000.00, 100000.00, 1, 1
UNION ALL SELECT 'MU3031', 'PCS', 1.000, 1, 0, 266000.00, 133000.00, 1, 1
UNION ALL SELECT 'MU3032', 'PCS', 1.000, 1, 0, 5000.00, 2400.00, 1, 1
UNION ALL SELECT 'MU3033', 'PCS', 1.000, 1, 0, 9000.00, 4540.00, 1, 1
UNION ALL SELECT 'MU3034', 'PCS', 1.000, 1, 0, 500.00, 30.00, 1, 1
UNION ALL SELECT 'MU3035', 'PCS', 1.000, 1, 0, 2500.00, 1600.00, 1, 1
UNION ALL SELECT 'MU3036', 'UNIT', 1.000, 1, 0, 176000.00, 130000.00, 1, 1
UNION ALL SELECT 'MU3037', 'DUS', 1.000, 1, 0, 74000.00, 49000.00, 1, 1
UNION ALL SELECT 'MU3038', 'DUS', 1.000, 1, 0, 74000.00, 49000.00, 1, 1
UNION ALL SELECT 'MU3039', 'DUS', 1.000, 1, 0, 73500.00, 49000.00, 1, 1
UNION ALL SELECT 'MU3040', 'DUS', 1.000, 1, 0, 74000.00, 49000.00, 1, 1
UNION ALL SELECT 'MU3041', 'PCS', 1.000, 1, 0, 18000.00, 3958.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU3042' AS sku, 'PCS' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 50000.00 AS selling_price, 25750.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU3043', 'PCS', 1.000, 1, 0, 50000.00, 25750.00, 1, 1
UNION ALL SELECT 'MU3044', 'PCS', 1.000, 1, 0, 25000.00, 8750.00, 1, 1
UNION ALL SELECT 'MU3045', 'PCS', 1.000, 1, 0, 10000.00, 3200.00, 1, 1
UNION ALL SELECT 'MU3046', 'PCS', 1.000, 1, 0, 23000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU3047', 'PCS', 1.000, 1, 0, 75000.00, 41500.00, 1, 1
UNION ALL SELECT 'MU3048', 'PCS', 1.000, 1, 0, 75000.00, 41500.00, 1, 1
UNION ALL SELECT 'MU3049', 'PCS', 1.000, 1, 0, 100000.00, 66400.00, 1, 1
UNION ALL SELECT 'MU3050', 'PCS', 1.000, 1, 0, 155000.00, 71500.00, 1, 1
UNION ALL SELECT 'MU3051', 'PCS', 1.000, 1, 0, 90000.00, 83000.00, 1, 1
UNION ALL SELECT 'MU3052', 'PCS', 1.000, 1, 0, 120000.00, 110000.00, 1, 1
UNION ALL SELECT 'MU3053', 'PCS', 1.000, 1, 0, 28000.00, 12420.00, 1, 1
UNION ALL SELECT 'MU3054', 'KLG', 1.000, 1, 0, 81000.00, 68300.00, 1, 1
UNION ALL SELECT 'MU3055', 'KLG', 1.000, 1, 0, 81000.00, 68300.00, 1, 1
UNION ALL SELECT 'MU3056', 'KLG', 1.000, 1, 0, 81000.00, 68300.00, 1, 1
UNION ALL SELECT 'MU3057', 'KLG', 1.000, 1, 0, 81000.00, 68300.00, 1, 1
UNION ALL SELECT 'MU3058', 'KLG', 1.000, 1, 0, 81000.00, 68300.00, 1, 1
UNION ALL SELECT 'MU3059', 'KLG', 1.000, 1, 0, 81000.00, 68300.00, 1, 1
UNION ALL SELECT 'MU3060', 'KLG', 1.000, 1, 0, 81000.00, 68300.00, 1, 1
UNION ALL SELECT 'MU3061', 'KLG', 1.000, 1, 0, 81000.00, 68300.00, 1, 1
UNION ALL SELECT 'MU3062', 'PCS', 1.000, 1, 0, 75000.00, 42500.00, 1, 1
UNION ALL SELECT 'MU3063', 'PCS', 1.000, 1, 0, 50000.00, 37500.00, 1, 1
UNION ALL SELECT 'MU3064', 'PCS', 1.000, 1, 0, 50000.00, 38500.00, 1, 1
UNION ALL SELECT 'MU3065', 'PCS', 1.000, 1, 0, 10000.00, 4100.00, 1, 1
UNION ALL SELECT 'MU3066', 'PCS', 1.000, 1, 0, 10000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU3067', 'PCS', 1.000, 1, 0, 80000.00, 61000.00, 1, 1
UNION ALL SELECT 'MU3068', 'PCS', 1.000, 1, 0, 50000.00, 43300.00, 1, 1
UNION ALL SELECT 'MU3069', 'PCS', 1.000, 1, 0, 100000.00, 80000.00, 1, 1
UNION ALL SELECT 'MU3070', 'PCS', 1.000, 1, 0, 190000.00, 74250.00, 1, 1
UNION ALL SELECT 'MU3071', 'PCS', 1.000, 1, 0, 100000.00, 54450.00, 1, 1
UNION ALL SELECT 'MU3072', 'PCS', 1.000, 1, 0, 5000.00, 2500.00, 1, 1
UNION ALL SELECT 'MU3073', 'PCS', 1.000, 1, 0, 3000.00, 1150.00, 1, 1
UNION ALL SELECT 'MU3074', 'PCS', 1.000, 1, 0, 24000.00, 16600.00, 1, 1
UNION ALL SELECT 'MU3075', 'DUS', 1.000, 1, 0, 127000.00, 84375.00, 1, 1
UNION ALL SELECT 'MU3076', 'PCS', 1.000, 1, 0, 27000.00, 17900.00, 1, 1
UNION ALL SELECT 'MU3077', 'PCS', 1.000, 1, 0, 60000.00, 43300.00, 1, 1
UNION ALL SELECT 'MU3078', 'PCS', 1.000, 1, 0, 70000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU3079', 'PCS', 1.000, 1, 0, 34000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU3080', 'PCS', 1.000, 1, 0, 30000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU3081', 'PCS', 1.000, 1, 0, 18000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU3082', 'PCS', 1.000, 1, 0, 19000.00, 11000.00, 1, 1
UNION ALL SELECT 'MU3083', 'PCS', 1.000, 1, 0, 24000.00, 14000.00, 1, 1
UNION ALL SELECT 'MU3084', 'PCS', 1.000, 1, 0, 16000.00, 8800.00, 1, 1
UNION ALL SELECT 'MU3085', 'PCS', 1.000, 1, 0, 21000.00, 11600.00, 1, 1
UNION ALL SELECT 'MU3086', 'LSN', 1.000, 1, 0, 36000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU3087', 'LSN', 1.000, 1, 0, 36000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU3088', 'PCS', 1.000, 1, 0, 115000.00, 115000.00, 1, 1
UNION ALL SELECT 'MU3089', 'PCS', 1.000, 1, 0, 115000.00, 115000.00, 1, 1
UNION ALL SELECT 'MU3090', 'PCS', 1.000, 1, 0, 62000.00, 31000.00, 1, 1
UNION ALL SELECT 'MU3091', 'PCS', 1.000, 1, 0, 68000.00, 34000.00, 1, 1
UNION ALL SELECT 'MU3092', 'PCS', 1.000, 1, 0, 77000.00, 38500.00, 1, 1
UNION ALL SELECT 'MU3093', 'PCS', 1.000, 1, 0, 80000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU3094', 'PCS', 1.000, 1, 0, 10000.00, 5250.00, 1, 1
UNION ALL SELECT 'MU3095', 'PCS', 1.000, 1, 0, 10000.00, 5250.00, 1, 1
UNION ALL SELECT 'MU3096', 'PCS', 1.000, 1, 0, 12000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU3097', 'PCS', 1.000, 1, 0, 8000.00, 3200.00, 1, 1
UNION ALL SELECT 'MU3098', 'PCS', 1.000, 1, 0, 9000.00, 3000.00, 1, 1
UNION ALL SELECT 'MU3099', 'PCS', 1.000, 1, 0, 12000.00, 4500.00, 1, 1
UNION ALL SELECT 'MU3100', 'PCS', 1.000, 1, 0, 8000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU3101', 'PCS', 1.000, 1, 0, 10000.00, 4600.00, 1, 1
UNION ALL SELECT 'MU3102', 'PCS', 1.000, 1, 0, 12000.00, 5300.00, 1, 1
UNION ALL SELECT 'MU3103', 'PCS', 1.000, 1, 0, 10000.00, 4400.00, 1, 1
UNION ALL SELECT 'MU3104', 'PCS', 1.000, 1, 0, 12000.00, 5250.00, 1, 1
UNION ALL SELECT 'MU3105', 'PCS', 1.000, 1, 0, 14000.00, 6300.00, 1, 1
UNION ALL SELECT 'MU3106', 'PCS', 1.000, 1, 0, 60000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU3107', 'PCS', 1.000, 1, 0, 12000.00, 5800.00, 1, 1
UNION ALL SELECT 'MU3108', 'MTR', 1.000, 1, 0, 5000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU3109', 'LSN', 1.000, 1, 0, 22000.00, 11000.00, 1, 1
UNION ALL SELECT 'MU3110', 'PCS', 1.000, 1, 0, 5000.00, 1400.00, 1, 1
UNION ALL SELECT 'MU3111', 'PCS', 1.000, 1, 0, 34000.00, 17000.00, 1, 1
UNION ALL SELECT 'MU3112', 'PCS', 1.000, 1, 0, 38000.00, 19000.00, 1, 1
UNION ALL SELECT 'MU3113', 'PCS', 1.000, 1, 0, 42000.00, 21000.00, 1, 1
UNION ALL SELECT 'MU3114', 'PCS', 1.000, 1, 0, 46000.00, 23000.00, 1, 1
UNION ALL SELECT 'MU3115', 'PCS', 1.000, 1, 0, 150000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU3116', 'PCS', 1.000, 1, 0, 68000.00, 34000.00, 1, 1
UNION ALL SELECT 'MU3117', 'PCS', 1.000, 1, 0, 57000.00, 28500.00, 1, 1
UNION ALL SELECT 'MU3118', 'PCS', 1.000, 1, 0, 42000.00, 21000.00, 1, 1
UNION ALL SELECT 'MU3119', 'PCS', 1.000, 1, 0, 3000.00, 1500.00, 1, 1
UNION ALL SELECT 'MU3120', 'PCS', 1.000, 1, 0, 24000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU3121', 'PCS', 1.000, 1, 0, 15000.00, 7250.00, 1, 1
UNION ALL SELECT 'MU3122', 'PCS', 1.000, 1, 0, 9000.00, 4500.00, 1, 1
UNION ALL SELECT 'MU3123', 'PCS', 1.000, 1, 0, 7000.00, 3500.00, 1, 1
UNION ALL SELECT 'MU3124', 'PCS', 1.000, 1, 0, 32000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU3125', 'PCS', 1.000, 1, 0, 36000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU3126', 'PCS', 1.000, 1, 0, 49000.00, 24500.00, 1, 1
UNION ALL SELECT 'MU3127', 'PCS', 1.000, 1, 0, 4000.00, 2000.00, 1, 1
UNION ALL SELECT 'MU3128', 'PCS', 1.000, 1, 0, 3000.00, 1250.00, 1, 1
UNION ALL SELECT 'MU3129', 'PCS', 1.000, 1, 0, 3000.00, 825.00, 1, 1
UNION ALL SELECT 'MU3130', 'PCS', 1.000, 1, 0, 20000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU3131', 'PCS', 1.000, 1, 0, 23000.00, 11500.00, 1, 1
UNION ALL SELECT 'MU3132', 'PCS', 1.000, 1, 0, 34000.00, 17000.00, 1, 1
UNION ALL SELECT 'MU3133', 'PCS', 1.000, 1, 0, 37000.00, 18400.00, 1, 1
UNION ALL SELECT 'MU3134', 'PCS', 1.000, 1, 0, 18000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU3135', 'PCS', 1.000, 1, 0, 20000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU3136', 'PCS', 1.000, 1, 0, 50000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU3137', 'PCS', 1.000, 1, 0, 76000.00, 38000.00, 1, 1
UNION ALL SELECT 'MU3138', 'PCS', 1.000, 1, 0, 5000.00, 2100.00, 1, 1
UNION ALL SELECT 'MU3139', 'PCS', 1.000, 1, 0, 11000.00, 5250.00, 1, 1
UNION ALL SELECT 'MU3140', 'PCS', 1.000, 1, 0, 11000.00, 5250.00, 1, 1
UNION ALL SELECT 'MU3141', 'PCS', 1.000, 1, 0, 45000.00, 22500.00, 1, 1
UNION ALL SELECT 'MU3142', 'PCS', 1.000, 1, 0, 32000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU3143', 'PCS', 1.000, 1, 0, 33000.00, 16500.00, 1, 1
UNION ALL SELECT 'MU3144', 'PCS', 1.000, 1, 0, 50000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU3145', 'PCS', 1.000, 1, 0, 24000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU3146', 'PCS', 1.000, 1, 0, 16000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU3147', 'PCS', 1.000, 1, 0, 14000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU3148', 'PCS', 1.000, 1, 0, 18000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU3149', 'PCS', 1.000, 1, 0, 10000.00, 4500.00, 1, 1
UNION ALL SELECT 'MU3150', 'PCS', 1.000, 1, 0, 6000.00, 3000.00, 1, 1
UNION ALL SELECT 'MU3151', 'PCS', 1.000, 1, 0, 13000.00, 6250.00, 1, 1
UNION ALL SELECT 'MU3152', 'PCS', 1.000, 1, 0, 27000.00, 13300.00, 1, 1
UNION ALL SELECT 'MU3153', 'PCS', 1.000, 1, 0, 20000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU3154', 'PCS', 1.000, 1, 0, 33000.00, 16500.00, 1, 1
UNION ALL SELECT 'MU3155', 'BTL', 1.000, 1, 0, 45000.00, 0.00, 1, 1
UNION ALL SELECT 'MU3156', 'PCS', 1.000, 1, 0, 10000.00, 3000.00, 1, 1
UNION ALL SELECT 'MU3157', 'PCS', 1.000, 1, 0, 15000.00, 7250.00, 1, 1
UNION ALL SELECT 'MU3158', 'PCS', 1.000, 1, 0, 18000.00, 8600.00, 1, 1
UNION ALL SELECT 'MU3159', 'PCS', 1.000, 1, 0, 135000.00, 67500.00, 1, 1
UNION ALL SELECT 'MU3160', 'PCS', 1.000, 1, 0, 147000.00, 73500.00, 1, 1
UNION ALL SELECT 'MU3161', 'PCS', 1.000, 1, 0, 65000.00, 65000.00, 1, 1
UNION ALL SELECT 'MU3162', 'PCS', 1.000, 1, 0, 30000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU3163', 'PCS', 1.000, 1, 0, 95000.00, 95000.00, 1, 1
UNION ALL SELECT 'MU3164', 'PCS', 1.000, 1, 0, 55000.00, 55000.00, 1, 1
UNION ALL SELECT 'MU3165', 'PCS', 1.000, 1, 0, 5000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU3166', 'SET', 1.000, 1, 0, 150000.00, 102500.00, 1, 1
UNION ALL SELECT 'MU3167', 'DUS', 1.000, 1, 0, 70000.00, 70000.00, 1, 1
UNION ALL SELECT 'MU3168', 'PCS', 1.000, 1, 0, 78000.00, 42500.00, 1, 1
UNION ALL SELECT 'MU3169', 'PCS', 1.000, 1, 0, 90000.00, 55000.00, 1, 1
UNION ALL SELECT 'MU3170', 'PCS', 1.000, 1, 0, 40000.00, 27084.00, 1, 1
UNION ALL SELECT 'MU3171', 'PCS', 1.000, 1, 0, 38000.00, 22500.00, 1, 1
UNION ALL SELECT 'MU3172', 'DUS', 1.000, 1, 0, 70000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU3173', 'DUS', 1.000, 1, 0, 187000.00, 105850.00, 1, 1
UNION ALL SELECT 'MU3174', 'DUS', 1.000, 1, 0, 77000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU3175', 'DUS', 1.000, 1, 0, 77000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU3176', 'DUS', 1.000, 1, 0, 187000.00, 105850.00, 1, 1
UNION ALL SELECT 'MU3177', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU3178', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU3179', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU3180', 'PCS', 1.000, 1, 0, 34000.00, 12750.00, 1, 1
UNION ALL SELECT 'MU3181', 'PCS', 1.000, 1, 0, 8000.00, 4300.00, 1, 1
UNION ALL SELECT 'MU3182', 'PCS', 1.000, 1, 0, 4000.00, 1400.00, 1, 1
UNION ALL SELECT 'MU3183', 'KLG', 1.000, 1, 0, 28000.00, 0.00, 1, 1
UNION ALL SELECT 'MU3184', 'KLG', 1.000, 1, 0, 28000.00, 0.00, 1, 1
UNION ALL SELECT 'MU3185', 'KLG', 1.000, 1, 0, 28000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU3186', 'KLG', 1.000, 1, 0, 28000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU3187', 'KLG', 1.000, 1, 0, 28000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU3188', 'KLG', 1.000, 1, 0, 28000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU3189', 'KLG', 1.000, 1, 0, 46000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU3190', 'KLG', 1.000, 1, 0, 16000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU3191', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU3192' AS sku, 'KLG' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 100000.00 AS selling_price, 85000.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU3193', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU3194', 'PCS', 1.000, 1, 0, 16000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU3195', 'PCS', 1.000, 1, 0, 16000.00, 16000.00, 1, 1
UNION ALL SELECT 'MU3196', 'PCS', 1.000, 1, 0, 20000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU3197', 'PCS', 1.000, 1, 0, 55000.00, 55000.00, 1, 1
UNION ALL SELECT 'MU3198', 'PCS', 1.000, 1, 0, 75000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU3199', 'PCS', 1.000, 1, 0, 53000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU3200', 'PCS', 1.000, 1, 0, 42000.00, 27500.00, 1, 1
UNION ALL SELECT 'MU3201', 'PCS', 1.000, 1, 0, 49000.00, 32500.00, 1, 1
UNION ALL SELECT 'MU3202', 'PCS', 1.000, 1, 0, 38000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU3203', 'PCS', 1.000, 1, 0, 83000.00, 55000.00, 1, 1
UNION ALL SELECT 'MU3204', 'PCS', 1.000, 1, 0, 26000.00, 13000.00, 1, 1
UNION ALL SELECT 'MU3205', 'UNIT', 1.000, 1, 0, 1392000.00, 930000.00, 1, 1
UNION ALL SELECT 'MU3206', 'PCS', 1.000, 1, 0, 87000.00, 57500.00, 1, 1
UNION ALL SELECT 'MU3207', 'PCS', 1.000, 1, 0, 79000.00, 52500.00, 1, 1
UNION ALL SELECT 'MU3208', 'LBR', 1.000, 1, 0, 83000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU3209', 'PCS', 1.000, 1, 0, 79000.00, 43450.00, 1, 1
UNION ALL SELECT 'MU3210', 'PCS', 1.000, 1, 0, 12000.00, 5416.00, 1, 1
UNION ALL SELECT 'MU3211', 'PCS', 1.000, 1, 0, 71000.00, 59000.00, 1, 1
UNION ALL SELECT 'MU3212', 'MTR', 1.000, 1, 0, 5000.00, 2500.00, 1, 1
UNION ALL SELECT 'MU3213', 'LBR', 1.000, 1, 0, 280000.00, 212410.00, 1, 1
UNION ALL SELECT 'MU3214', 'LBR', 1.000, 1, 0, 384000.00, 284560.00, 1, 1
UNION ALL SELECT 'MU3215', 'LBR', 1.000, 1, 0, 241000.00, 184660.00, 1, 1
UNION ALL SELECT 'MU3216', 'PCS', 1.000, 1, 0, 15000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU3217', 'PCS', 1.000, 1, 0, 12000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU3218', 'DUS', 1.000, 1, 0, 25000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU3219', 'PCS', 1.000, 1, 0, 3000.00, 1400.00, 1, 1
UNION ALL SELECT 'MU3220', 'MTR', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU3221', 'KG', 1.000, 1, 0, 26000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU3222', 'KG', 1.000, 1, 0, 26000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU3223', 'KG', 1.000, 1, 0, 26000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU3224', 'PCS', 1.000, 1, 0, 26000.00, 14875.00, 1, 1
UNION ALL SELECT 'MU3225', 'PCS', 1.000, 1, 0, 22000.00, 13090.00, 1, 1
UNION ALL SELECT 'MU3226', 'PCS', 1.000, 1, 0, 28000.00, 16660.00, 1, 1
UNION ALL SELECT 'MU3227', 'PCS', 1.000, 1, 0, 53000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU3228', 'PCS', 1.000, 1, 0, 53000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU3229', 'PCS', 1.000, 1, 0, 53000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU3230', 'PCS', 1.000, 1, 0, 25000.00, 17500.00, 1, 1
UNION ALL SELECT 'MU3231', 'PCS', 1.000, 1, 0, 25000.00, 17500.00, 1, 1
UNION ALL SELECT 'MU3232', 'PSG', 1.000, 1, 0, 42000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU3233', 'PCS', 1.000, 1, 0, 25000.00, 16100.00, 1, 1
UNION ALL SELECT 'MU3234', 'BTG', 1.000, 1, 0, 113000.00, 87782.00, 1, 1
UNION ALL SELECT 'MU3235', 'PCS', 1.000, 1, 0, 168000.00, 0.00, 1, 1
UNION ALL SELECT 'MU3236', 'PSG', 1.000, 1, 0, 95000.00, 34500.00, 1, 1
UNION ALL SELECT 'MU3237', 'PCS', 1.000, 1, 0, 8000.00, 2500.00, 1, 1
UNION ALL SELECT 'MU3238', 'PCS', 1.000, 1, 0, 50000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU3239', 'PCS', 1.000, 1, 0, 5000.00, 3000.00, 1, 1
UNION ALL SELECT 'MU3240', 'PCS', 1.000, 1, 0, 10000.00, 5900.00, 1, 1
UNION ALL SELECT 'MU3241', 'PCS', 1.000, 1, 0, 11000.00, 6500.00, 1, 1
UNION ALL SELECT 'MU3242', 'PCS', 1.000, 1, 0, 13000.00, 7500.00, 1, 1
UNION ALL SELECT 'MU3243', 'PCS', 1.000, 1, 0, 17000.00, 9700.00, 1, 1
UNION ALL SELECT 'MU3244', 'PCS', 1.000, 1, 0, 8000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU3245', 'PAIL', 1.000, 1, 0, 458000.00, 305000.00, 1, 1
UNION ALL SELECT 'MU3246', 'PCS', 1.000, 1, 0, 15000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU3247', 'PCS', 1.000, 1, 0, 10000.00, 3000.00, 1, 1
UNION ALL SELECT 'MU3248', 'PCS', 1.000, 1, 0, 7000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU3249', 'PCS', 1.000, 1, 0, 5000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU3250', 'PCS', 1.000, 1, 0, 80000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU3251', 'PCS', 1.000, 1, 0, 8000.00, 6381.00, 1, 1
UNION ALL SELECT 'MU3252', 'PCS', 1.000, 1, 0, 25000.00, 18030.00, 1, 1
UNION ALL SELECT 'MU3253', 'PCS', 1.000, 1, 0, 15000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU3254', 'PCS', 1.000, 1, 0, 500.00, 250.00, 1, 1
UNION ALL SELECT 'MU3255', 'PSG', 1.000, 1, 0, 8000.00, 4000.00, 1, 1
UNION ALL SELECT 'MU3256', 'PSG', 1.000, 1, 0, 12000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU3257', 'PSG', 1.000, 1, 0, 5000.00, 2250.00, 1, 1
UNION ALL SELECT 'MU3258', 'PCS', 1.000, 1, 0, 58000.00, 39289.00, 1, 1
UNION ALL SELECT 'MU3259', 'SET', 1.000, 1, 0, 108000.00, 71500.00, 1, 1
UNION ALL SELECT 'MU3260', 'SET', 1.000, 1, 0, 108000.00, 71500.00, 1, 1
UNION ALL SELECT 'MU3261', 'PCS', 1.000, 1, 0, 78000.00, 52000.00, 1, 1
UNION ALL SELECT 'MU3262', 'UNIT', 1.000, 1, 0, 1773000.00, 1181500.00, 1, 1
UNION ALL SELECT 'MU3263', 'PCS', 1.000, 1, 0, 1000.00, 300.00, 1, 1
UNION ALL SELECT 'MU3264', 'PCS', 1.000, 1, 0, 2000.00, 380.00, 1, 1
UNION ALL SELECT 'MU3265', 'PCS', 1.000, 1, 0, 82000.00, 56000.00, 1, 1
UNION ALL SELECT 'MU3266', 'SET', 1.000, 1, 0, 87000.00, 57500.00, 1, 1
UNION ALL SELECT 'MU3267', 'PCS', 1.000, 1, 0, 52000.00, 18750.00, 1, 1
UNION ALL SELECT 'MU3268', 'PCS', 1.000, 1, 0, 17000.00, 8250.00, 1, 1
UNION ALL SELECT 'MU3269', 'PCS', 1.000, 1, 0, 11000.00, 5500.00, 1, 1
UNION ALL SELECT 'MU3270', 'PCS', 1.000, 1, 0, 24000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU3271', 'PCS', 1.000, 1, 0, 39000.00, 19500.00, 1, 1
UNION ALL SELECT 'MU3272', 'PSG', 1.000, 1, 0, 53000.00, 33000.00, 1, 1
UNION ALL SELECT 'MU3273', 'PCS', 1.000, 1, 0, 57000.00, 38000.00, 1, 1
UNION ALL SELECT 'MU3274', 'PCS', 1.000, 1, 0, 25000.00, 14500.00, 1, 1
UNION ALL SELECT 'MU3275', 'PCS', 1.000, 1, 0, 57000.00, 32500.00, 1, 1
UNION ALL SELECT 'MU3276', 'PCS', 1.000, 1, 0, 48000.00, 28500.00, 1, 1
UNION ALL SELECT 'MU3277', 'PCS', 1.000, 1, 0, 11000.00, 5500.00, 1, 1
UNION ALL SELECT 'MU3278', 'PCS', 1.000, 1, 0, 10000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU3279', 'PCS', 1.000, 1, 0, 3000.00, 800.00, 1, 1
UNION ALL SELECT 'MU3280', 'PCS', 1.000, 1, 0, 110000.00, 78000.00, 1, 1
UNION ALL SELECT 'MU3281', 'PCS', 1.000, 1, 0, 63000.00, 45000.00, 1, 1
UNION ALL SELECT 'MU3282', 'PCS', 1.000, 1, 0, 63000.00, 45000.00, 1, 1
UNION ALL SELECT 'MU3283', 'PCS', 1.000, 1, 0, 63000.00, 45000.00, 1, 1
UNION ALL SELECT 'MU3284', 'PCS', 1.000, 1, 0, 63000.00, 45000.00, 1, 1
UNION ALL SELECT 'MU3285', 'UNIT', 1.000, 1, 0, 353000.00, 235000.00, 1, 1
UNION ALL SELECT 'MU3286', 'PCS', 1.000, 1, 0, 994000.00, 710000.00, 1, 1
UNION ALL SELECT 'MU3287', 'PCS', 1.000, 1, 0, 40000.00, 24600.00, 1, 1
UNION ALL SELECT 'MU3288', 'PCS', 1.000, 1, 0, 42000.00, 26000.00, 1, 1
UNION ALL SELECT 'MU3289', 'PCS', 1.000, 1, 0, 18000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU3290', 'PCS', 1.000, 1, 0, 19000.00, 9300.00, 1, 1
UNION ALL SELECT 'MU3291', 'PCS', 1.000, 1, 0, 18000.00, 8750.00, 1, 1
UNION ALL SELECT 'MU3292', 'PCS', 1.000, 1, 0, 18000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU3293', 'PCS', 1.000, 1, 0, 18000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU3294', 'PCS', 1.000, 1, 0, 8000.00, 4800.00, 1, 1
UNION ALL SELECT 'MU3295', 'PCS', 1.000, 1, 0, 40000.00, 28500.00, 1, 1
UNION ALL SELECT 'MU3296', 'PCS', 1.000, 1, 0, 28000.00, 19500.00, 1, 1
UNION ALL SELECT 'MU3297', 'PCS', 1.000, 1, 0, 150000.00, 100000.00, 1, 1
UNION ALL SELECT 'MU3298', 'PCS', 1.000, 1, 0, 100000.00, 67000.00, 1, 1
UNION ALL SELECT 'MU3299', 'PCS', 1.000, 1, 0, 93000.00, 62000.00, 1, 1
UNION ALL SELECT 'MU3300', 'PCS', 1.000, 1, 0, 68000.00, 46359.00, 1, 1
UNION ALL SELECT 'MU3301', 'PSG', 1.000, 1, 0, 62000.00, 44280.00, 1, 1
UNION ALL SELECT 'MU3302', 'PSG', 1.000, 1, 0, 105000.00, 75500.00, 1, 1
UNION ALL SELECT 'MU3303', 'UNIT', 1.000, 1, 0, 1480000.00, 987801.00, 1, 1
UNION ALL SELECT 'MU3304', 'PCS', 1.000, 1, 0, 49000.00, 33229.00, 1, 1
UNION ALL SELECT 'MU3305', 'PCS', 1.000, 1, 0, 42000.00, 28179.00, 1, 1
UNION ALL SELECT 'MU3306', 'PCS', 1.000, 1, 0, 33000.00, 22119.00, 1, 1
UNION ALL SELECT 'MU3307', 'BTG', 1.000, 1, 0, 104000.00, 89000.00, 1, 1
UNION ALL SELECT 'MU3308', 'PCS', 1.000, 1, 0, 56000.00, 36800.00, 1, 1
UNION ALL SELECT 'MU3309', 'LBR', 1.000, 1, 0, 10000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU3310', 'KLG', 1.000, 1, 0, 103000.00, 86000.00, 1, 1
UNION ALL SELECT 'MU3311', 'KLG', 1.000, 1, 0, 64000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU3312', 'KLG', 1.000, 1, 0, 64000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU3313', 'PCS', 1.000, 1, 0, 100000.00, 0.00, 1, 1
UNION ALL SELECT 'MU3314', 'MTR', 1.000, 1, 0, 10000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU3315', 'PSG', 1.000, 1, 0, 145000.00, 100000.00, 1, 1
UNION ALL SELECT 'MU3316', 'DUS', 1.000, 1, 0, 98000.00, 71394.00, 1, 1
UNION ALL SELECT 'MU3317', 'DUS', 1.000, 1, 0, 98000.00, 71394.00, 1, 1
UNION ALL SELECT 'MU3318', 'DUS', 1.000, 1, 0, 98000.00, 0.00, 1, 1
UNION ALL SELECT 'MU3319', 'PCS', 1.000, 1, 0, 4000.00, 1380.00, 1, 1
UNION ALL SELECT 'MU3320', 'LBR', 1.000, 1, 0, 160000.00, 0.00, 1, 1
UNION ALL SELECT 'MU3321', 'PCS', 1.000, 1, 0, 21000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU3322', 'PCS', 1.000, 1, 0, 371000.00, 285000.00, 1, 1
UNION ALL SELECT 'MU3323', 'DUS', 1.000, 1, 0, 98000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU3324', 'PCS', 1.000, 1, 0, 300000.00, 200000.00, 1, 1
UNION ALL SELECT 'MU3325', 'PCS', 1.000, 1, 0, 85000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU3326', 'PCS', 1.000, 1, 0, 75000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU3327', 'PCS', 1.000, 1, 0, 300000.00, 200000.00, 1, 1
UNION ALL SELECT 'MU3328', 'PCS', 1.000, 1, 0, 130000.00, 200000.00, 1, 1
UNION ALL SELECT 'MU3329', 'PCS', 1.000, 1, 0, 26000.00, 14500.00, 1, 1
UNION ALL SELECT 'MU3330', 'PCS', 1.000, 1, 0, 24000.00, 13000.00, 1, 1
UNION ALL SELECT 'MU3331', 'PCS', 1.000, 1, 0, 23000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU3332', 'PCS', 1.000, 1, 0, 20000.00, 11000.00, 1, 1
UNION ALL SELECT 'MU3333', 'PCS', 1.000, 1, 0, 31000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU3334', 'PCS', 1.000, 1, 0, 38000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU3335', 'PCS', 1.000, 1, 0, 49000.00, 24200.00, 1, 1
UNION ALL SELECT 'MU3336', 'DUS', 1.000, 1, 0, 98000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU3337', 'PCS', 1.000, 1, 0, 1500.00, 296000.00, 1, 1
UNION ALL SELECT 'MU3338', 'PCS', 1.000, 1, 0, 32000.00, 21800.00, 1, 1
UNION ALL SELECT 'MU3339', 'PCS', 1.000, 1, 0, 43000.00, 28800.00, 1, 1
UNION ALL SELECT 'MU3340', 'BTG', 1.000, 1, 0, 202000.00, 131000.00, 1, 1
UNION ALL SELECT 'MU3341', 'PCS', 1.000, 1, 0, 40000.00, 20000.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU3342' AS sku, 'DUS' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 98000.00 AS selling_price, 71394.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU3343', 'DUS', 1.000, 1, 0, 98000.00, 71394.00, 1, 1
UNION ALL SELECT 'MU3344', 'DUS', 1.000, 1, 0, 98000.00, 71394.00, 1, 1
UNION ALL SELECT 'MU3345', 'SET', 1.000, 1, 0, 175000.00, 125000.00, 1, 1
UNION ALL SELECT 'MU3346', 'PCS', 1.000, 1, 0, 25000.00, 17900.00, 1, 1
UNION ALL SELECT 'MU3347', 'PCS', 1.000, 1, 0, 33000.00, 22052.00, 1, 1
UNION ALL SELECT 'MU3348', 'PSG', 1.000, 1, 0, 93000.00, 34000.00, 1, 1
UNION ALL SELECT 'MU3349', 'PSG', 1.000, 1, 0, 93000.00, 25500.00, 1, 1
UNION ALL SELECT 'MU3350', 'PSG', 1.000, 1, 0, 93000.00, 38250.00, 1, 1
UNION ALL SELECT 'MU3351', '100 METER', 1.000, 1, 0, 189000.00, 126000.00, 1, 1
UNION ALL SELECT 'MU3352', '100 METER', 1.000, 1, 0, 543000.00, 362000.00, 1, 1
UNION ALL SELECT 'MU3353', 'LBR', 1.000, 1, 0, 7000.00, 3850.00, 1, 1
UNION ALL SELECT 'MU3354', 'LBR', 1.000, 1, 0, 7000.00, 3850.00, 1, 1
UNION ALL SELECT 'MU3355', 'PAIL', 1.000, 1, 0, 433000.00, 100000.00, 1, 1
UNION ALL SELECT 'MU3356', 'GLN', 1.000, 1, 0, 112000.00, 98000.00, 1, 1
UNION ALL SELECT 'MU3357', 'LBR', 1.000, 1, 0, 385000.00, 295660.00, 1, 1
UNION ALL SELECT 'MU3358', 'PAK', 1.000, 1, 0, 105000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU3359', 'PAK', 1.000, 1, 0, 125000.00, 57750.00, 1, 1
UNION ALL SELECT 'MU3360', 'PAK', 1.000, 1, 0, 125000.00, 57750.00, 1, 1
UNION ALL SELECT 'MU3361', 'PCS', 1.000, 1, 0, 38000.00, 18975.00, 1, 1
UNION ALL SELECT 'MU3362', 'PCS', 1.000, 1, 0, 40000.00, 19800.00, 1, 1
UNION ALL SELECT 'MU3363', 'PCS', 1.000, 1, 0, 46000.00, 22550.00, 1, 1
UNION ALL SELECT 'MU3364', 'PCS', 1.000, 1, 0, 53000.00, 26400.00, 1, 1
UNION ALL SELECT 'MU3365', 'PCS', 1.000, 1, 0, 59000.00, 29150.00, 1, 1
UNION ALL SELECT 'MU3366', 'PCS', 1.000, 1, 0, 70000.00, 34650.00, 1, 1
UNION ALL SELECT 'MU3367', 'PCS', 1.000, 1, 0, 114000.00, 90750.00, 1, 1
UNION ALL SELECT 'MU3368', 'PCS', 1.000, 1, 0, 6600.00, 3630.00, 1, 1
UNION ALL SELECT 'MU3369', 'PCS', 1.000, 1, 0, 26000.00, 14300.00, 1, 1
UNION ALL SELECT 'MU3370', 'PCS', 1.000, 1, 0, 22000.00, 12100.00, 1, 1
UNION ALL SELECT 'MU3371', 'PCS', 1.000, 1, 0, 24500.00, 13475.00, 1, 1
UNION ALL SELECT 'MU3372', 'PCS', 1.000, 1, 0, 23000.00, 12650.00, 1, 1
UNION ALL SELECT 'MU3373', 'PCS', 1.000, 1, 0, 6000.00, 3192.00, 1, 1
UNION ALL SELECT 'MU3374', 'BTL', 1.000, 1, 0, 90000.00, 39000.00, 1, 1
UNION ALL SELECT 'MU3375', 'UNIT', 1.000, 1, 0, 1591000.00, 1063100.00, 1, 1
UNION ALL SELECT 'MU3376', 'UNIT', 1.000, 1, 0, 1691000.00, 1130100.00, 1, 1
UNION ALL SELECT 'MU3377', 'PCS', 1.000, 1, 0, 37000.00, 23500.00, 1, 1
UNION ALL SELECT 'MU3378', 'PCS', 1.000, 1, 0, 42000.00, 27500.00, 1, 1
UNION ALL SELECT 'MU3379', 'PCS', 1.000, 1, 0, 23000.00, 13333.00, 1, 1
UNION ALL SELECT 'MU3380', 'PCS', 1.000, 1, 0, 27000.00, 17084.00, 1, 1
UNION ALL SELECT 'MU3381', 'PCS', 1.000, 1, 0, 36000.00, 22500.00, 1, 1
UNION ALL SELECT 'MU3382', 'PCS', 1.000, 1, 0, 18000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU3383', 'PCS', 1.000, 1, 0, 39000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU3384', 'PCS', 1.000, 1, 0, 18000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU3385', 'PCS', 1.000, 1, 0, 120000.00, 90000.00, 1, 1
UNION ALL SELECT 'MU3386', 'PCS', 1.000, 1, 0, 120000.00, 90000.00, 1, 1
UNION ALL SELECT 'MU3387', 'PCS', 1.000, 1, 0, 78000.00, 48000.00, 1, 1
UNION ALL SELECT 'MU3388', 'UNIT', 1.000, 1, 0, 115000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU3389', 'UNIT', 1.000, 1, 0, 837000.00, 620000.00, 1, 1
UNION ALL SELECT 'MU3390', 'UNIT', 1.000, 1, 0, 322000.00, 280000.00, 1, 1
UNION ALL SELECT 'MU3391', 'UNIT', 1.000, 1, 0, 278000.00, 240000.00, 1, 1
UNION ALL SELECT 'MU3392', 'UNIT', 1.000, 1, 0, 405000.00, 300000.00, 1, 1
UNION ALL SELECT 'MU3393', 'UNIT', 1.000, 1, 0, 345000.00, 255000.00, 1, 1
UNION ALL SELECT 'MU3394', 'SAK', 1.000, 1, 0, 106000.00, 99000.00, 1, 1
UNION ALL SELECT 'MU3395', 'DUS', 1.000, 1, 0, 77000.00, 35200.00, 1, 1
UNION ALL SELECT 'MU3396', 'DUS', 1.000, 1, 0, 77000.00, 32400.00, 1, 1
UNION ALL SELECT 'MU3397', 'DUS', 1.000, 1, 0, 77000.00, 32400.00, 1, 1
UNION ALL SELECT 'MU3398', 'DUS', 1.000, 1, 0, 77000.00, 32600.00, 1, 1
UNION ALL SELECT 'MU3399', 'UNIT', 1.000, 1, 0, 280000.00, 270000.00, 1, 1
UNION ALL SELECT 'MU3400', 'PAIL', 1.000, 1, 0, 1150000.00, 825900.00, 1, 1
UNION ALL SELECT 'MU3401', 'PCS', 1.000, 1, 0, 31000.00, 21000.00, 1, 1
UNION ALL SELECT 'MU3402', 'PCS', 1.000, 1, 0, 25000.00, 17000.00, 1, 1
UNION ALL SELECT 'MU3403', 'PCS', 1.000, 1, 0, 19000.00, 13000.00, 1, 1
UNION ALL SELECT 'MU3404', 'PCS', 1.000, 1, 0, 18000.00, 12000.00, 1, 1
UNION ALL SELECT 'MU3405', 'PCS', 1.000, 1, 0, 15000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU3406', 'PCS', 1.000, 1, 0, 15000.00, 5416.00, 1, 1
UNION ALL SELECT 'MU3407', 'PCS', 1.000, 1, 0, 4000.00, 2000.00, 1, 1
UNION ALL SELECT 'MU3408', 'PCS', 1.000, 1, 0, 5000.00, 2333.00, 1, 1
UNION ALL SELECT 'MU3409', 'PCS', 1.000, 1, 0, 6000.00, 2416.00, 1, 1
UNION ALL SELECT 'MU3410', 'PCS', 1.000, 1, 0, 6000.00, 2500.00, 1, 1
UNION ALL SELECT 'MU3411', 'PCS', 1.000, 1, 0, 7000.00, 2750.00, 1, 1
UNION ALL SELECT 'MU3412', 'AMBIL SENDIRI', 1.000, 1, 0, 120000.00, 70000.00, 1, 1
UNION ALL SELECT 'MU3413', 'PCS', 1.000, 1, 0, 20000.00, 15215.00, 1, 1
UNION ALL SELECT 'MU3414', 'PCS', 1.000, 1, 0, 110000.00, 88200.00, 1, 1
UNION ALL SELECT 'MU3415', 'PCS', 1.000, 1, 0, 110000.00, 88200.00, 1, 1
UNION ALL SELECT 'MU3416', 'PCS', 1.000, 1, 0, 76000.00, 51975.00, 1, 1
UNION ALL SELECT 'MU3417', 'PCS', 1.000, 1, 0, 80000.00, 54725.00, 1, 1
UNION ALL SELECT 'MU3418', 'PCS', 1.000, 1, 0, 83000.00, 57200.00, 1, 1
UNION ALL SELECT 'MU3419', 'PCS', 1.000, 1, 0, 57000.00, 39050.00, 1, 1
UNION ALL SELECT 'MU3420', 'PCS', 1.000, 1, 0, 69000.00, 47545.00, 1, 1
UNION ALL SELECT 'MU3421', 'PCS', 1.000, 1, 0, 63000.00, 42900.00, 1, 1
UNION ALL SELECT 'MU3422', 'PCS', 1.000, 1, 0, 60000.00, 40975.00, 1, 1
UNION ALL SELECT 'MU3423', 'PCS', 1.000, 1, 0, 67000.00, 46200.00, 1, 1
UNION ALL SELECT 'MU3424', 'PSG', 1.000, 1, 0, 145000.00, 89000.00, 1, 1
UNION ALL SELECT 'MU3425', 'PSG', 1.000, 1, 0, 145000.00, 89000.00, 1, 1
UNION ALL SELECT 'MU3426', 'PCS', 1.000, 1, 0, 15000.00, 6250.00, 1, 1
UNION ALL SELECT 'MU3427', 'SET', 1.000, 1, 0, 41000.00, 19000.00, 1, 1
UNION ALL SELECT 'MU3428', 'SET', 1.000, 1, 0, 21000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU3429', 'SET', 1.000, 1, 0, 23000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU3430', 'SET', 1.000, 1, 0, 288000.00, 190000.00, 1, 1
UNION ALL SELECT 'MU3431', 'UNIT', 1.000, 1, 0, 294000.00, 255000.00, 1, 1
UNION ALL SELECT 'MU3432', 'PCS', 1.000, 1, 0, 21000.00, 9500.00, 1, 1
UNION ALL SELECT 'MU3433', 'PCS', 1.000, 1, 0, 19000.00, 8500.00, 1, 1
UNION ALL SELECT 'MU3434', 'PCS', 1.000, 1, 0, 3000.00, 1750.00, 1, 1
UNION ALL SELECT 'MU3435', 'PCS', 1.000, 1, 0, 4000.00, 2000.00, 1, 1
UNION ALL SELECT 'MU3436', 'PCS', 1.000, 1, 0, 6000.00, 3000.00, 1, 1
UNION ALL SELECT 'MU3437', 'PCS', 1.000, 1, 0, 52000.00, 32500.00, 1, 1
UNION ALL SELECT 'MU3438', 'DUS', 1.000, 1, 0, 128000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU3439', 'PCS', 1.000, 1, 0, 38000.00, 18750.00, 1, 1
UNION ALL SELECT 'MU3440', 'PCS', 1.000, 1, 0, 60000.00, 29600.00, 1, 1
UNION ALL SELECT 'MU3441', 'PCS', 1.000, 1, 0, 19000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU3442', 'PCS', 1.000, 1, 0, 35000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU3443', 'PCS', 1.000, 1, 0, 51000.00, 22000.00, 1, 1
UNION ALL SELECT 'MU3444', 'PCS', 1.000, 1, 0, 437000.00, 190000.00, 1, 1
UNION ALL SELECT 'MU3445', 'PCS', 1.000, 1, 0, 6000.00, 3300.00, 1, 1
UNION ALL SELECT 'MU3446', 'PCS', 1.000, 1, 0, 55000.00, 18150.00, 1, 1
UNION ALL SELECT 'MU3447', 'SET', 1.000, 1, 0, 190000.00, 96250.00, 1, 1
UNION ALL SELECT 'MU3448', 'PCS', 1.000, 1, 0, 36000.00, 17600.00, 1, 1
UNION ALL SELECT 'MU3449', 'PCS', 1.000, 1, 0, 43000.00, 21450.00, 1, 1
UNION ALL SELECT 'MU3450', 'PCS', 1.000, 1, 0, 61000.00, 30250.00, 1, 1
UNION ALL SELECT 'MU3451', 'UNIT', 1.000, 1, 0, 105000.00, 57700.00, 1, 1
UNION ALL SELECT 'MU3452', 'LBR', 1.000, 1, 0, 655000.00, 387917.00, 1, 1
UNION ALL SELECT 'MU3453', 'LBR', 1.000, 1, 0, 554000.00, 326317.00, 1, 1
UNION ALL SELECT 'MU3454', 'PCS', 1.000, 1, 0, 3000.00, 1500.00, 1, 1
UNION ALL SELECT 'MU3455', 'BTL', 1.000, 1, 0, 39000.00, 27200.00, 1, 1
UNION ALL SELECT 'MU3456', 'BTL', 1.000, 1, 0, 51000.00, 36350.00, 1, 1
UNION ALL SELECT 'MU3457', 'PCS', 1.000, 1, 0, 160000.00, 112000.00, 1, 1
UNION ALL SELECT 'MU3458', 'PCS', 1.000, 1, 0, 7000.00, 5250.00, 1, 1
UNION ALL SELECT 'MU3459', 'PCS', 1.000, 1, 0, 29000.00, 19720.00, 1, 1
UNION ALL SELECT 'MU3460', 'PCS', 1.000, 1, 0, 370000.00, 203500.00, 1, 1
UNION ALL SELECT 'MU3461', 'PCS', 1.000, 1, 0, 50000.00, 27500.00, 1, 1
UNION ALL SELECT 'MU3462', 'PCS', 1.000, 1, 0, 75000.00, 41250.00, 1, 1
UNION ALL SELECT 'MU3463', 'PCS', 1.000, 1, 0, 90000.00, 49500.00, 1, 1
UNION ALL SELECT 'MU3464', 'PCS', 1.000, 1, 0, 100000.00, 55000.00, 1, 1
UNION ALL SELECT 'MU3465', 'PCS', 1.000, 1, 0, 110000.00, 60500.00, 1, 1
UNION ALL SELECT 'MU3466', 'PCS', 1.000, 1, 0, 160000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU3467', 'PCS', 1.000, 1, 0, 180000.00, 99000.00, 1, 1
UNION ALL SELECT 'MU3468', 'PCS', 1.000, 1, 0, 20000.00, 11000.00, 1, 1
UNION ALL SELECT 'MU3469', 'SET', 1.000, 1, 0, 450000.00, 258500.00, 1, 1
UNION ALL SELECT 'MU3470', 'PCS', 1.000, 1, 0, 70000.00, 38500.00, 1, 1
UNION ALL SELECT 'MU3471', 'PCS', 1.000, 1, 0, 70000.00, 43000.00, 1, 1
UNION ALL SELECT 'MU3472', 'PCS', 1.000, 1, 0, 70000.00, 43000.00, 1, 1
UNION ALL SELECT 'MU3473', 'PCS', 1.000, 1, 0, 70000.00, 49000.00, 1, 1
UNION ALL SELECT 'MU3474', 'DUS', 1.000, 1, 0, 98000.00, 67720.00, 1, 1
UNION ALL SELECT 'MU3475', 'DUS', 1.000, 1, 0, 98000.00, 67720.00, 1, 1
UNION ALL SELECT 'MU3476', 'DUS', 1.000, 1, 0, 98000.00, 67720.00, 1, 1
UNION ALL SELECT 'MU3477', 'DUS', 1.000, 1, 0, 77000.00, 59395.00, 1, 1
UNION ALL SELECT 'MU3478', 'DUS', 1.000, 1, 0, 98000.00, 69555.00, 1, 1
UNION ALL SELECT 'MU3479', 'DUS', 1.000, 1, 0, 98000.00, 69555.00, 1, 1
UNION ALL SELECT 'MU3480', 'DUS', 1.000, 1, 0, 98000.00, 69555.00, 1, 1
UNION ALL SELECT 'MU3481', 'DUS', 1.000, 1, 0, 98000.00, 69555.00, 1, 1
UNION ALL SELECT 'MU3482', 'DUS', 1.000, 1, 0, 98000.00, 68830.00, 1, 1
UNION ALL SELECT 'MU3483', 'DUS', 1.000, 1, 0, 98000.00, 68830.00, 1, 1
UNION ALL SELECT 'MU3484', 'DUS', 1.000, 1, 0, 98000.00, 69940.00, 1, 1
UNION ALL SELECT 'MU3485', 'PCS', 1.000, 1, 0, 95000.00, 84000.00, 1, 1
UNION ALL SELECT 'MU3486', 'DUS', 1.000, 1, 0, 98000.00, 69440.00, 1, 1
UNION ALL SELECT 'MU3487', 'DUS', 1.000, 1, 0, 98000.00, 72715.00, 1, 1
UNION ALL SELECT 'MU3488', 'DUS', 1.000, 1, 0, 77000.00, 59395.00, 1, 1
UNION ALL SELECT 'MU3489', 'DUS', 1.000, 1, 0, 77000.00, 59395.00, 1, 1
UNION ALL SELECT 'MU3490', 'DUS', 1.000, 1, 0, 77000.00, 59395.00, 1, 1
UNION ALL SELECT 'MU3491', 'DUS', 1.000, 1, 0, 77000.00, 59395.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU3492' AS sku, 'DUS' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 77000.00 AS selling_price, 59395.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU3493', 'PCS', 1.000, 1, 0, 2000.00, 840.00, 1, 1
UNION ALL SELECT 'MU3494', 'PCS', 1.000, 1, 0, 288000.00, 191400.00, 1, 1
UNION ALL SELECT 'MU3495', 'PCS', 1.000, 1, 0, 515000.00, 343200.00, 1, 1
UNION ALL SELECT 'MU3496', 'PCS', 1.000, 1, 0, 20000.00, 3000.00, 1, 1
UNION ALL SELECT 'MU3497', 'PCS', 1.000, 1, 0, 40000.00, 0.00, 1, 1
UNION ALL SELECT 'MU3498', 'KLG', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU3499', 'KLG', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU3500', 'KLG', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU3501', 'KLG', 1.000, 1, 0, 94000.00, 57600.00, 1, 1
UNION ALL SELECT 'MU3502', 'KLG', 1.000, 1, 0, 144000.00, 109915.00, 1, 1
UNION ALL SELECT 'MU3503', 'KLG', 1.000, 1, 0, 94000.00, 55271.00, 1, 1
UNION ALL SELECT 'MU3504', 'IKAT', 1.000, 1, 0, 6000.00, 2125.00, 1, 1
UNION ALL SELECT 'MU3505', 'PSG', 1.000, 1, 0, 15000.00, 1656.00, 1, 1
UNION ALL SELECT 'MU3506', 'PSG', 1.000, 1, 0, 10000.00, 1017.00, 1, 1
UNION ALL SELECT 'MU3507', 'PSG', 1.000, 1, 0, 12000.00, 1208.00, 1, 1
UNION ALL SELECT 'MU3508', 'PSG', 1.000, 1, 0, 11000.00, 1118.00, 1, 1
UNION ALL SELECT 'MU3509', 'PSG', 1.000, 1, 0, 11000.00, 1598.00, 1, 1
UNION ALL SELECT 'MU3510', 'PSG', 1.000, 1, 0, 16000.00, 2335.00, 1, 1
UNION ALL SELECT 'MU3511', 'PSG', 1.000, 1, 0, 11000.00, 2077.00, 1, 1
UNION ALL SELECT 'MU3512', 'PSG', 1.000, 1, 0, 13000.00, 2450.00, 1, 1
UNION ALL SELECT 'MU3513', 'PSG', 1.000, 1, 0, 18000.00, 3946.00, 1, 1
UNION ALL SELECT 'MU3514', 'PCS', 1.000, 1, 0, 1000.00, 250.00, 1, 1
UNION ALL SELECT 'MU3515', 'PCS', 1.000, 1, 0, 1000.00, 110.00, 1, 1
UNION ALL SELECT 'MU3516', 'PCS', 1.000, 1, 0, 500.00, 60.00, 1, 1
UNION ALL SELECT 'MU3517', 'BTL', 1.000, 1, 0, 615000.00, 512500.00, 1, 1
UNION ALL SELECT 'MU3518', 'UNIT', 1.000, 1, 0, 1020000.00, 853000.00, 1, 1
UNION ALL SELECT 'MU3519', 'UNIT', 1.000, 1, 0, 650000.00, 545000.00, 1, 1
UNION ALL SELECT 'MU3520', 'MTR', 1.000, 1, 0, 10000.00, 0.00, 1, 1
UNION ALL SELECT 'MU3521', 'UNIT', 1.000, 1, 0, 291000.00, 215000.00, 1, 1
UNION ALL SELECT 'MU3522', 'UNIT', 1.000, 1, 0, 130000.00, 95000.00, 1, 1
UNION ALL SELECT 'MU3523', 'PAIL', 1.000, 1, 0, 433000.00, 200000.00, 1, 1
UNION ALL SELECT 'MU3524', 'PCS', 1.000, 1, 0, 5000.00, 2042.00, 1, 1
UNION ALL SELECT 'MU3525', 'SET', 1.000, 1, 0, 50000.00, 22500.00, 1, 1
UNION ALL SELECT 'MU3526', 'DUS', 1.000, 1, 0, 77000.00, 35200.00, 1, 1
UNION ALL SELECT 'MU3527', 'BTG', 1.000, 1, 0, 150000.00, 115000.00, 1, 1
UNION ALL SELECT 'MU3528', 'BTG', 1.000, 1, 0, 85000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU3529', 'BTG', 1.000, 1, 0, 85000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU3530', 'DUS', 1.000, 1, 0, 99000.00, 72794.00, 1, 1
UNION ALL SELECT 'MU3531', 'BTG', 1.000, 1, 0, 147000.00, 90487.00, 1, 1
UNION ALL SELECT 'MU3532', 'BTG', 1.000, 1, 0, 105000.00, 71500.00, 1, 1
UNION ALL SELECT 'MU3533', 'PSG', 1.000, 1, 0, 15000.00, 7975.00, 1, 1
UNION ALL SELECT 'MU3534', 'KLG', 1.000, 1, 0, 121000.00, 89024.00, 1, 1
UNION ALL SELECT 'MU3535', 'KLG', 1.000, 1, 0, 59000.00, 43100.00, 1, 1
UNION ALL SELECT 'MU3536', 'KLG', 1.000, 1, 0, 65000.00, 47950.00, 1, 1
UNION ALL SELECT 'MU3537', 'KLG', 1.000, 1, 0, 115000.00, 84600.00, 1, 1
UNION ALL SELECT 'MU3538', 'KLG', 1.000, 1, 0, 51000.00, 37600.00, 1, 1
UNION ALL SELECT 'MU3539', 'KLG', 1.000, 1, 0, 51000.00, 37600.00, 1, 1
UNION ALL SELECT 'MU3540', 'KLG', 1.000, 1, 0, 51000.00, 37600.00, 1, 1
UNION ALL SELECT 'MU3541', 'KLG', 1.000, 1, 0, 115000.00, 84600.00, 1, 1
UNION ALL SELECT 'MU3542', 'KLG', 1.000, 1, 0, 58000.00, 42350.00, 1, 1
UNION ALL SELECT 'MU3543', 'KLG', 1.000, 1, 0, 58000.00, 42350.00, 1, 1
UNION ALL SELECT 'MU3544', 'KLG', 1.000, 1, 0, 51000.00, 37600.00, 1, 1
UNION ALL SELECT 'MU3545', 'KLG', 1.000, 1, 0, 58000.00, 42350.00, 1, 1
UNION ALL SELECT 'MU3546', 'BTG', 1.000, 1, 0, 105000.00, 71500.00, 1, 1
UNION ALL SELECT 'MU3547', 'BTG', 1.000, 1, 0, 78000.00, 47247.00, 1, 1
UNION ALL SELECT 'MU3548', 'BTG', 1.000, 1, 0, 61000.00, 37663.00, 1, 1
UNION ALL SELECT 'MU3549', 'BTG', 1.000, 1, 0, 61000.00, 37663.00, 1, 1
UNION ALL SELECT 'MU3550', 'BTG', 1.000, 1, 0, 94000.00, 63250.00, 1, 1
UNION ALL SELECT 'MU3551', 'BTG', 1.000, 1, 0, 94000.00, 63250.00, 1, 1
UNION ALL SELECT 'MU3552', 'BTG', 1.000, 1, 0, 35000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU3553', 'DUS', 1.000, 1, 0, 77000.00, 57175.00, 1, 1
UNION ALL SELECT 'MU3554', 'PSG', 1.000, 1, 0, 35000.00, 22950.00, 1, 1
UNION ALL SELECT 'MU3555', 'BTL', 1.000, 1, 0, 64000.00, 42000.00, 1, 1
UNION ALL SELECT 'MU3556', 'PCS', 1.000, 1, 0, 22000.00, 14862.00, 1, 1
UNION ALL SELECT 'MU3557', 'PCS', 1.000, 1, 0, 1000.00, 12.00, 1, 1
UNION ALL SELECT 'MU3558', 'BTG', 1.000, 1, 0, 1000.00, 21.00, 1, 1
UNION ALL SELECT 'MU3559', 'PCS', 1.000, 1, 0, 10000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU3560', 'PCS', 1.000, 1, 0, 10000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU3561', 'PCS', 1.000, 1, 0, 10000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU3562', 'BTG', 1.000, 1, 0, 15000.00, 7000.00, 1, 1
UNION ALL SELECT 'MU3563', 'BTG', 1.000, 1, 0, 18000.00, 8000.00, 1, 1
UNION ALL SELECT 'MU3564', 'PCS', 1.000, 1, 0, 45000.00, 0.00, 1, 1
UNION ALL SELECT 'MU3565', 'BKS', 1.000, 1, 0, 11000.00, 5500.00, 1, 1
UNION ALL SELECT 'MU3566', 'BKS', 1.000, 1, 0, 13000.00, 6500.00, 1, 1
UNION ALL SELECT 'MU3567', 'BKS', 1.000, 1, 0, 15000.00, 7500.00, 1, 1
UNION ALL SELECT 'MU3568', 'BKS', 1.000, 1, 0, 17000.00, 8500.00, 1, 1
UNION ALL SELECT 'MU3569', 'SET', 1.000, 1, 0, 227000.00, 155000.00, 1, 1
UNION ALL SELECT 'MU3570', 'SET', 1.000, 1, 0, 322000.00, 220000.00, 1, 1
UNION ALL SELECT 'MU3571', 'SET', 1.000, 1, 0, 322000.00, 220000.00, 1, 1
UNION ALL SELECT 'MU3572', 'SET', 1.000, 1, 0, 322000.00, 220000.00, 1, 1
UNION ALL SELECT 'MU3573', 'PCS', 1.000, 1, 0, 25000.00, 12500.00, 1, 1
UNION ALL SELECT 'MU3574', 'PCS', 1.000, 1, 0, 61000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU3575', 'PCS', 1.000, 1, 0, 81000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU3576', 'PCS', 1.000, 1, 0, 500.00, 200.00, 1, 1
UNION ALL SELECT 'MU3577', 'PCS', 1.000, 1, 0, 400.00, 200.00, 1, 1
UNION ALL SELECT 'MU3578', 'DUS', 1.000, 1, 0, 88000.00, 68400.00, 1, 1
UNION ALL SELECT 'MU3579', 'DUS', 1.000, 1, 0, 88000.00, 68400.00, 1, 1
UNION ALL SELECT 'MU3580', 'DUS', 1.000, 1, 0, 88000.00, 68400.00, 1, 1
UNION ALL SELECT 'MU3581', 'DUS', 1.000, 1, 0, 88000.00, 68400.00, 1, 1
UNION ALL SELECT 'MU3582', 'DUS', 1.000, 1, 0, 88000.00, 68400.00, 1, 1
UNION ALL SELECT 'MU3583', 'DUS', 1.000, 1, 0, 88000.00, 68400.00, 1, 1
UNION ALL SELECT 'MU3584', 'DUS', 1.000, 1, 0, 88000.00, 68400.00, 1, 1
UNION ALL SELECT 'MU3585', 'DUS', 1.000, 1, 0, 308000.00, 133000.00, 1, 1
UNION ALL SELECT 'MU3586', 'SET', 1.000, 1, 0, 80000.00, 46000.00, 1, 1
UNION ALL SELECT 'MU3587', 'SET', 1.000, 1, 0, 80000.00, 46000.00, 1, 1
UNION ALL SELECT 'MU3588', 'SET', 1.000, 1, 0, 314000.00, 182000.00, 1, 1
UNION ALL SELECT 'MU3589', 'SET', 1.000, 1, 0, 314000.00, 182000.00, 1, 1
UNION ALL SELECT 'MU3590', 'SET', 1.000, 1, 0, 80000.00, 40540.00, 1, 1
UNION ALL SELECT 'MU3591', 'SET', 1.000, 1, 0, 701000.00, 406000.00, 1, 1
UNION ALL SELECT 'MU3592', 'SET', 1.000, 1, 0, 701000.00, 406000.00, 1, 1
UNION ALL SELECT 'MU3593', 'SET', 1.000, 1, 0, 701000.00, 406000.00, 1, 1
UNION ALL SELECT 'MU3594', 'PCS', 1.000, 1, 0, 66000.00, 43000.00, 1, 1
UNION ALL SELECT 'MU3595', 'PCS', 1.000, 1, 0, 38000.00, 29500.00, 1, 1
UNION ALL SELECT 'MU3596', 'DUS', 1.000, 1, 0, 91000.00, 45946.00, 1, 1
UNION ALL SELECT 'MU3597', 'DUS', 1.000, 1, 0, 91000.00, 45946.00, 1, 1
UNION ALL SELECT 'MU3598', 'DUS', 1.000, 1, 0, 91000.00, 45946.00, 1, 1
UNION ALL SELECT 'MU3599', 'PCS', 1.000, 1, 0, 45000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU3600', 'PCS', 1.000, 1, 0, 46000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU3601', 'DUS', 1.000, 1, 0, 77000.00, 68000.00, 1, 1
UNION ALL SELECT 'MU3602', 'PAIL', 1.000, 1, 0, 433000.00, 350000.00, 1, 1
UNION ALL SELECT 'MU3603', 'LBR', 1.000, 1, 0, 402000.00, 300000.00, 1, 1
UNION ALL SELECT 'MU3604', 'KLG', 1.000, 1, 0, 80000.00, 70000.00, 1, 1
UNION ALL SELECT 'MU3605', 'PCS', 1.000, 1, 0, 25000.00, 12500.00, 1, 1
UNION ALL SELECT 'MU3606', 'ROL', 1.000, 1, 0, 270000.00, 182400.00, 1, 1
UNION ALL SELECT 'MU3607', 'ROL', 1.000, 1, 0, 270000.00, 182400.00, 1, 1
UNION ALL SELECT 'MU3608', 'PCS', 1.000, 1, 0, 55000.00, 43000.00, 1, 1
UNION ALL SELECT 'MU3609', 'PCS', 1.000, 1, 0, 78000.00, 60500.00, 1, 1
UNION ALL SELECT 'MU3610', 'PCS', 1.000, 1, 0, 52000.00, 49000.00, 1, 1
UNION ALL SELECT 'MU3611', 'DUS', 1.000, 1, 0, 88000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU3612', 'BTG', 1.000, 1, 0, 145000.00, 112832.00, 1, 1
UNION ALL SELECT 'MU3613', 'DUS', 1.000, 1, 0, 77000.00, 59000.00, 1, 1
UNION ALL SELECT 'MU3614', 'PCS', 1.000, 1, 0, 20000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU3615', 'DUS', 1.000, 1, 0, 74000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU3616', 'PCS', 1.000, 1, 0, 30000.00, 19500.00, 1, 1
UNION ALL SELECT 'MU3617', 'PCS', 1.000, 1, 0, 41000.00, 27250.00, 1, 1
UNION ALL SELECT 'MU3618', 'PCS', 1.000, 1, 0, 223000.00, 165000.00, 1, 1
UNION ALL SELECT 'MU3619', 'PCS', 1.000, 1, 0, 164000.00, 121000.00, 1, 1
UNION ALL SELECT 'MU3620', 'GLN', 1.000, 1, 0, 310000.00, 290000.00, 1, 1
UNION ALL SELECT 'MU3621', 'PAK', 1.000, 1, 0, 115000.00, 60500.00, 1, 1
UNION ALL SELECT 'MU3622', 'PCS', 1.000, 1, 0, 45000.00, 29000.00, 1, 1
UNION ALL SELECT 'MU3623', 'PCS', 1.000, 1, 0, 28000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU3624', 'UNIT', 1.000, 1, 0, 135000.00, 115000.00, 1, 1
UNION ALL SELECT 'MU3625', 'KG', 1.000, 1, 0, 37000.00, 23500.00, 1, 1
UNION ALL SELECT 'MU3626', 'ROL', 1.000, 1, 0, 45000.00, 26000.00, 1, 1
UNION ALL SELECT 'MU3627', 'DUS', 1.000, 1, 0, 107000.00, 76000.00, 1, 1
UNION ALL SELECT 'MU3628', 'LBR', 1.000, 1, 0, 84000.00, 50000.00, 1, 1
UNION ALL SELECT 'MU3629', 'PCS', 1.000, 1, 0, 69000.00, 37950.00, 1, 1
UNION ALL SELECT 'MU3630', 'UNIT', 1.000, 1, 0, 265000.00, 230000.00, 1, 1
UNION ALL SELECT 'MU3631', 'UNIT', 1.000, 1, 0, 311000.00, 270000.00, 1, 1
UNION ALL SELECT 'MU3632', 'UNIT', 1.000, 1, 0, 294000.00, 255000.00, 1, 1
UNION ALL SELECT 'MU3633', 'UNIT', 1.000, 1, 0, 173000.00, 150000.00, 1, 1
UNION ALL SELECT 'MU3634', 'UNIT', 1.000, 1, 0, 380000.00, 330000.00, 1, 1
UNION ALL SELECT 'MU3635', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU3636', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU3637', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU3638', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU3639', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU3640', 'KLG', 1.000, 1, 0, 144000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU3641', 'KLG', 1.000, 1, 0, 144000.00, 62400.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU3642' AS sku, 'KLG' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 144000.00 AS selling_price, 62400.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU3643', 'KLG', 1.000, 1, 0, 144000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU3644', 'KLG', 1.000, 1, 0, 144000.00, 62400.00, 1, 1
UNION ALL SELECT 'MU3645', 'KLG', 1.000, 1, 0, 98000.00, 72000.00, 1, 1
UNION ALL SELECT 'MU3646', 'PAIL', 1.000, 1, 0, 876000.00, 440000.00, 1, 1
UNION ALL SELECT 'MU3647', 'PAIL', 1.000, 1, 0, 1429000.00, 1230200.00, 1, 1
UNION ALL SELECT 'MU3648', 'GLN', 1.000, 1, 0, 311000.00, 275000.00, 1, 1
UNION ALL SELECT 'MU3649', 'GLN', 1.000, 1, 0, 311000.00, 275000.00, 1, 1
UNION ALL SELECT 'MU3650', 'GLN', 1.000, 1, 0, 186000.00, 162500.00, 1, 1
UNION ALL SELECT 'MU3651', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU3652', 'GLN', 1.000, 1, 0, 112000.00, 93000.00, 1, 1
UNION ALL SELECT 'MU3653', 'GLN', 1.000, 1, 0, 213000.00, 157352.00, 1, 1
UNION ALL SELECT 'MU3654', 'KLG', 1.000, 1, 0, 16000.00, 0.00, 1, 1
UNION ALL SELECT 'MU3655', 'KLG', 1.000, 1, 0, 16000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU3656', 'KLG', 1.000, 1, 0, 16000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU3657', 'KLG', 1.000, 1, 0, 100000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU3658', 'KLG', 1.000, 1, 0, 16000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU3659', 'KLG', 1.000, 1, 0, 16000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU3660', 'KLG', 1.000, 1, 0, 16000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU3661', 'KLG', 1.000, 1, 0, 16000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU3662', 'KLG', 1.000, 1, 0, 16000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU3663', 'KLG', 1.000, 1, 0, 16000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU3664', 'KLG', 1.000, 1, 0, 16000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU3665', 'KLG', 1.000, 1, 0, 16000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU3666', 'KLG', 1.000, 1, 0, 16000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU3667', 'KLG', 1.000, 1, 0, 16000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU3668', 'KLG', 1.000, 1, 0, 16000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU3669', 'DUS', 1.000, 1, 0, 103000.00, 78000.00, 1, 1
UNION ALL SELECT 'MU3670', 'PCS', 1.000, 1, 0, 12000.00, 7916.00, 1, 1
UNION ALL SELECT 'MU3671', 'PCS', 1.000, 1, 0, 11000.00, 5416.00, 1, 1
UNION ALL SELECT 'MU3672', 'PCS', 1.000, 1, 0, 11000.00, 5416.00, 1, 1
UNION ALL SELECT 'MU3673', 'PCS', 1.000, 1, 0, 11000.00, 5416.00, 1, 1
UNION ALL SELECT 'MU3674', 'PCS', 1.000, 1, 0, 13000.00, 6250.00, 1, 1
UNION ALL SELECT 'MU3675', 'PCS', 1.000, 1, 0, 13000.00, 6250.00, 1, 1
UNION ALL SELECT 'MU3676', 'PCS', 1.000, 1, 0, 13000.00, 6250.00, 1, 1
UNION ALL SELECT 'MU3677', 'PCS', 1.000, 1, 0, 11000.00, 5416.00, 1, 1
UNION ALL SELECT 'MU3678', 'PCS', 1.000, 1, 0, 11000.00, 5416.00, 1, 1
UNION ALL SELECT 'MU3679', 'SET', 1.000, 1, 0, 165000.00, 110000.00, 1, 1
UNION ALL SELECT 'MU3680', 'PCS', 1.000, 1, 0, 33000.00, 19573.00, 1, 1
UNION ALL SELECT 'MU3681', 'PCS', 1.000, 1, 0, 21000.00, 1111.00, 1, 1
UNION ALL SELECT 'MU3682', 'PCS', 1.000, 1, 0, 13000.00, 6500.00, 1, 1
UNION ALL SELECT 'MU3683', 'PCS', 1.000, 1, 0, 13000.00, 6500.00, 1, 1
UNION ALL SELECT 'MU3684', 'PCS', 1.000, 1, 0, 13000.00, 6500.00, 1, 1
UNION ALL SELECT 'MU3685', 'PCS', 1.000, 1, 0, 13000.00, 6500.00, 1, 1
UNION ALL SELECT 'MU3686', 'PCS', 1.000, 1, 0, 77000.00, 42350.00, 1, 1
UNION ALL SELECT 'MU3687', 'PCS', 1.000, 1, 0, 93000.00, 51150.00, 1, 1
UNION ALL SELECT 'MU3688', 'UNIT', 1.000, 1, 0, 385000.00, 299892.00, 1, 1
UNION ALL SELECT 'MU3689', 'LBR', 1.000, 1, 0, 70000.00, 45000.00, 1, 1
UNION ALL SELECT 'MU3690', 'LBR', 1.000, 1, 0, 148000.00, 100000.00, 1, 1
UNION ALL SELECT 'MU3691', 'PCS', 1.000, 1, 0, 107000.00, 71300.00, 1, 1
UNION ALL SELECT 'MU3692', 'PCS', 1.000, 1, 0, 6000.00, 3500.00, 1, 1
UNION ALL SELECT 'MU3693', 'PCS', 1.000, 1, 0, 30000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU3694', 'PCS', 1.000, 1, 0, 36000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU3695', 'PCS', 1.000, 1, 0, 48000.00, 24000.00, 1, 1
UNION ALL SELECT 'MU3696', 'PCS', 1.000, 1, 0, 125000.00, 88000.00, 1, 1
UNION ALL SELECT 'MU3697', 'PCS', 1.000, 1, 0, 92000.00, 46000.00, 1, 1
UNION ALL SELECT 'MU3698', 'PCS', 1.000, 1, 0, 36000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU3699', 'SET', 1.000, 1, 0, 450000.00, 325000.00, 1, 1
UNION ALL SELECT 'MU3700', 'PSG', 1.000, 1, 0, 63000.00, 31250.00, 1, 1
UNION ALL SELECT 'MU3701', 'SET', 1.000, 1, 0, 80000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU3702', 'PCS', 1.000, 1, 0, 60000.00, 30000.00, 1, 1
UNION ALL SELECT 'MU3703', 'PCS', 1.000, 1, 0, 105000.00, 70000.00, 1, 1
UNION ALL SELECT 'MU3704', 'PCS', 1.000, 1, 0, 22000.00, 11500.00, 1, 1
UNION ALL SELECT 'MU3705', 'PCS', 1.000, 1, 0, 22000.00, 11500.00, 1, 1
UNION ALL SELECT 'MU3706', 'PCS', 1.000, 1, 0, 22000.00, 11500.00, 1, 1
UNION ALL SELECT 'MU3707', 'PCS', 1.000, 1, 0, 120000.00, 60000.00, 1, 1
UNION ALL SELECT 'MU3708', 'PCS', 1.000, 1, 0, 25000.00, 10416.00, 1, 1
UNION ALL SELECT 'MU3709', 'PCS', 1.000, 1, 0, 21000.00, 10416.00, 1, 1
UNION ALL SELECT 'MU3710', 'PCS', 1.000, 1, 0, 5000.00, 1875.00, 1, 1
UNION ALL SELECT 'MU3711', 'PCS', 1.000, 1, 0, 23000.00, 11500.00, 1, 1
UNION ALL SELECT 'MU3712', 'PCS', 1.000, 1, 0, 50000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU3713', 'ROL', 1.000, 1, 0, 1208000.00, 862500.00, 1, 1
UNION ALL SELECT 'MU3714', 'ROL', 1.000, 1, 0, 805000.00, 575000.00, 1, 1
UNION ALL SELECT 'MU3715', 'ROL', 1.000, 1, 0, 10000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU3716', 'KLG', 1.000, 1, 0, 48000.00, 33600.00, 1, 1
UNION ALL SELECT 'MU3717', 'KLG', 1.000, 1, 0, 48000.00, 33600.00, 1, 1
UNION ALL SELECT 'MU3718', 'KLG', 1.000, 1, 0, 48000.00, 33600.00, 1, 1
UNION ALL SELECT 'MU3719', 'KLG', 1.000, 1, 0, 116000.00, 88900.00, 1, 1
UNION ALL SELECT 'MU3720', 'KLG', 1.000, 1, 0, 116000.00, 88900.00, 1, 1
UNION ALL SELECT 'MU3721', 'KLG', 1.000, 1, 0, 206000.00, 157700.00, 1, 1
UNION ALL SELECT 'MU3722', 'KLG', 1.000, 1, 0, 35000.00, 24900.00, 1, 1
UNION ALL SELECT 'MU3723', 'KLG', 1.000, 1, 0, 35000.00, 24900.00, 1, 1
UNION ALL SELECT 'MU3724', 'KLG', 1.000, 1, 0, 35000.00, 24900.00, 1, 1
UNION ALL SELECT 'MU3725', 'KLG', 1.000, 1, 0, 35000.00, 24900.00, 1, 1
UNION ALL SELECT 'MU3726', 'KLG', 1.000, 1, 0, 35000.00, 24900.00, 1, 1
UNION ALL SELECT 'MU3727', 'KLG', 1.000, 1, 0, 35000.00, 24900.00, 1, 1
UNION ALL SELECT 'MU3728', 'KLG', 1.000, 1, 0, 35000.00, 24900.00, 1, 1
UNION ALL SELECT 'MU3729', 'SET', 1.000, 1, 0, 171000.00, 95000.00, 1, 1
UNION ALL SELECT 'MU3730', 'BKS', 1.000, 1, 0, 68000.00, 2250.00, 1, 1
UNION ALL SELECT 'MU3731', 'PCS', 1.000, 1, 0, 23000.00, 11500.00, 1, 1
UNION ALL SELECT 'MU3732', 'PCS', 1.000, 1, 0, 40000.00, 13000.00, 1, 1
UNION ALL SELECT 'MU3733', 'PCS', 1.000, 1, 0, 93000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU3734', 'UNIT', 1.000, 1, 0, 726000.00, 580700.00, 1, 1
UNION ALL SELECT 'MU3735', 'PCS', 1.000, 1, 0, 19000.00, 9500.00, 1, 1
UNION ALL SELECT 'MU3736', 'PCS', 1.000, 1, 0, 25000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU3737', 'PCS', 1.000, 1, 0, 28000.00, 14000.00, 1, 1
UNION ALL SELECT 'MU3738', 'SET', 1.000, 1, 0, 30000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU3739', 'ROL', 1.000, 1, 0, 150000.00, 110000.00, 1, 1
UNION ALL SELECT 'MU3740', 'PCS', 1.000, 1, 0, 63000.00, 41856.00, 1, 1
UNION ALL SELECT 'MU3741', 'PCS', 1.000, 1, 0, 86000.00, 56800.00, 1, 1
UNION ALL SELECT 'MU3742', 'PCS', 1.000, 1, 0, 79000.00, 39200.00, 1, 1
UNION ALL SELECT 'MU3743', 'PCS', 1.000, 1, 0, 30000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU3744', 'PCS', 1.000, 1, 0, 45000.00, 29600.00, 1, 1
UNION ALL SELECT 'MU3745', 'PCS', 1.000, 1, 0, 31000.00, 22264.00, 1, 1
UNION ALL SELECT 'MU3746', 'PCS', 1.000, 1, 0, 143000.00, 110000.00, 1, 1
UNION ALL SELECT 'MU3747', 'PCS', 1.000, 1, 0, 111000.00, 55500.00, 1, 1
UNION ALL SELECT 'MU3748', 'PAK', 1.000, 1, 0, 165000.00, 135000.00, 1, 1
UNION ALL SELECT 'MU3749', 'PCS', 1.000, 1, 0, 31000.00, 22264.00, 1, 1
UNION ALL SELECT 'MU3750', 'BKS', 1.000, 1, 0, 10000.00, 3800.00, 1, 1
UNION ALL SELECT 'MU3751', 'SET', 1.000, 1, 0, 165000.00, 150000.00, 1, 1
UNION ALL SELECT 'MU3752', 'PCS', 1.000, 1, 0, 12000.00, 9820.00, 1, 1
UNION ALL SELECT 'MU3753', 'PSG', 1.000, 1, 0, 14000.00, 6666.00, 1, 1
UNION ALL SELECT 'MU3754', 'PCS', 1.000, 1, 0, 104000.00, 70000.00, 1, 1
UNION ALL SELECT 'MU3755', 'PCS', 1.000, 1, 0, 287000.00, 185000.00, 1, 1
UNION ALL SELECT 'MU3756', 'PCS', 1.000, 1, 0, 179000.00, 115000.00, 1, 1
UNION ALL SELECT 'MU3757', 'GLN', 1.000, 1, 0, 112000.00, 89000.00, 1, 1
UNION ALL SELECT 'MU3758', 'PCS', 1.000, 1, 0, 23000.00, 16150.00, 1, 1
UNION ALL SELECT 'MU3759', 'DUS', 1.000, 1, 0, 103000.00, 59000.00, 1, 1
UNION ALL SELECT 'MU3760', 'KLG', 1.000, 1, 0, 103000.00, 88100.00, 1, 1
UNION ALL SELECT 'MU3761', 'KLG', 1.000, 1, 0, 64000.00, 49500.00, 1, 1
UNION ALL SELECT 'MU3762', 'DUS', 1.000, 1, 0, 98000.00, 69440.00, 1, 1
UNION ALL SELECT 'MU3763', 'PCS', 1.000, 1, 0, 6000.00, 2686.00, 1, 1
UNION ALL SELECT 'MU3764', 'DUS', 1.000, 1, 0, 84000.00, 56000.00, 1, 1
UNION ALL SELECT 'MU3765', 'PCS', 1.000, 1, 0, 53000.00, 38000.00, 1, 1
UNION ALL SELECT 'MU3766', 'PCS', 1.000, 1, 0, 223000.00, 164700.00, 1, 1
UNION ALL SELECT 'MU3767', 'PCS', 1.000, 1, 0, 295000.00, 217800.00, 1, 1
UNION ALL SELECT 'MU3768', 'DUS', 1.000, 1, 0, 88000.00, 44144.00, 1, 1
UNION ALL SELECT 'MU3769', 'PCS', 1.000, 1, 0, 18000.00, 11500.00, 1, 1
UNION ALL SELECT 'MU3770', 'PCS', 1.000, 1, 0, 42000.00, 27083.00, 1, 1
UNION ALL SELECT 'MU3771', 'PCS', 1.000, 1, 0, 37000.00, 21150.00, 1, 1
UNION ALL SELECT 'MU3772', 'PCS', 1.000, 1, 0, 52000.00, 29550.00, 1, 1
UNION ALL SELECT 'MU3773', 'PCS', 1.000, 1, 0, 11000.00, 5350.00, 1, 1
UNION ALL SELECT 'MU3774', 'PCS', 1.000, 1, 0, 46000.00, 26500.00, 1, 1
UNION ALL SELECT 'MU3775', 'PCS', 1.000, 1, 0, 17000.00, 6642.00, 1, 1
UNION ALL SELECT 'MU3776', 'PCS', 1.000, 1, 0, 24000.00, 11700.00, 1, 1
UNION ALL SELECT 'MU3777', 'PCS', 1.000, 1, 0, 37000.00, 18450.00, 1, 1
UNION ALL SELECT 'MU3778', 'PCS', 1.000, 1, 0, 53000.00, 26500.00, 1, 1
UNION ALL SELECT 'MU3779', 'SET', 1.000, 1, 0, 15000.00, 6350.00, 1, 1
UNION ALL SELECT 'MU3780', 'SET', 1.000, 1, 0, 10000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU3781', 'PCS', 1.000, 1, 0, 14000.00, 5940.00, 1, 1
UNION ALL SELECT 'MU3782', 'PCS', 1.000, 1, 0, 58000.00, 31900.00, 1, 1
UNION ALL SELECT 'MU3783', 'PCS', 1.000, 1, 0, 24000.00, 13200.00, 1, 1
UNION ALL SELECT 'MU3784', 'PCS', 1.000, 1, 0, 8000.00, 4400.00, 1, 1
UNION ALL SELECT 'MU3785', 'PCS', 1.000, 1, 0, 72000.00, 35750.00, 1, 1
UNION ALL SELECT 'MU3786', 'PCS', 1.000, 1, 0, 80000.00, 40000.00, 1, 1
UNION ALL SELECT 'MU3787', 'PCS', 1.000, 1, 0, 70000.00, 35000.00, 1, 1
UNION ALL SELECT 'MU3788', 'SET', 1.000, 1, 0, 240000.00, 120000.00, 1, 1
UNION ALL SELECT 'MU3789', 'PCS', 1.000, 1, 0, 140000.00, 70000.00, 1, 1
UNION ALL SELECT 'MU3790', 'PCS', 1.000, 1, 0, 240000.00, 120000.00, 1, 1
UNION ALL SELECT 'MU3791', 'PCS', 1.000, 1, 0, 70000.00, 35000.00, 1, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU3792' AS sku, 'PCS' AS unit_code, 1.000 AS conversion_factor, 1 AS is_base, 0 AS sort_order, 8000.00 AS selling_price, 3540.00 AS cost_price, 1 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU3793', 'PSG', 1.000, 1, 0, 76000.00, 58295.00, 1, 1
UNION ALL SELECT 'MU3794', 'KLG', 1.000, 1, 0, 28000.00, 20292.00, 1, 1
UNION ALL SELECT 'MU3795', 'KLG', 1.000, 1, 0, 94000.00, 64525.00, 1, 1
UNION ALL SELECT 'MU3796', 'KLG', 1.000, 1, 0, 94000.00, 64525.00, 1, 1
UNION ALL SELECT 'MU3797', 'KLG', 1.000, 1, 0, 33000.00, 26500.00, 1, 1
UNION ALL SELECT 'MU3798', 'PAIL', 1.000, 1, 0, 290000.00, 232000.00, 1, 1
UNION ALL SELECT 'MU3799', 'PSG', 1.000, 1, 0, 313000.00, 240300.00, 1, 1
UNION ALL SELECT 'MU3800', 'PCS', 1.000, 1, 0, 72000.00, 31000.00, 1, 1
UNION ALL SELECT 'MU3801', 'DUS', 1.000, 1, 0, 88000.00, 44144.00, 1, 1
UNION ALL SELECT 'MU3802', 'DUS', 1.000, 1, 0, 88000.00, 44144.00, 1, 1
UNION ALL SELECT 'MU3803', 'DUS', 1.000, 1, 0, 88000.00, 44144.00, 1, 1
UNION ALL SELECT 'MU3804', 'DUS', 1.000, 1, 0, 81000.00, 39189.00, 1, 1
UNION ALL SELECT 'MU3805', 'DUS', 1.000, 1, 0, 81000.00, 39189.00, 1, 1
UNION ALL SELECT 'MU3806', 'DUS', 1.000, 1, 0, 81000.00, 39640.00, 1, 1
UNION ALL SELECT 'MU3807', 'DUS', 1.000, 1, 0, 84000.00, 39640.00, 1, 1
UNION ALL SELECT 'MU3808', 'PCS', 1.000, 1, 0, 26000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU3809', 'PCS', 1.000, 1, 0, 85000.00, 54166.00, 1, 1
UNION ALL SELECT 'MU3810', 'GLN', 1.000, 1, 0, 91000.00, 69242.00, 1, 1
UNION ALL SELECT 'MU3811', 'UNIT', 1.000, 1, 0, 1208000.00, 700000.00, 1, 1
UNION ALL SELECT 'MU3812', 'BTG', 1.000, 1, 0, 161000.00, 112000.00, 1, 1
UNION ALL SELECT 'MU3813', 'PSG', 1.000, 1, 0, 14000.00, 6250.00, 1, 1
UNION ALL SELECT 'MU3814', 'PCS', 1.000, 1, 0, 25000.00, 12500.00, 1, 1
UNION ALL SELECT 'MU3815', 'PCS', 1.000, 1, 0, 28000.00, 14000.00, 1, 1
UNION ALL SELECT 'MU3816', 'PCS', 1.000, 1, 0, 35000.00, 17500.00, 1, 1
UNION ALL SELECT 'MU3817', 'SET', 1.000, 1, 0, 170000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU3818', 'PCS', 1.000, 1, 0, 30000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU3819', 'PCS', 1.000, 1, 0, 36000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU3820', 'PCS', 1.000, 1, 0, 52000.00, 20000.00, 1, 1
UNION ALL SELECT 'MU3821', 'DUS', 1.000, 1, 0, 141000.00, 120000.00, 1, 1
UNION ALL SELECT 'MU3822', 'DUS', 1.000, 1, 0, 250000.00, 166500.00, 1, 1
UNION ALL SELECT 'MU3823', 'PCS', 1.000, 1, 0, 39000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU3824', 'UNIT', 1.000, 1, 0, 264000.00, 220000.00, 1, 1
UNION ALL SELECT 'MU3825', 'UNIT', 1.000, 1, 0, 222000.00, 185000.00, 1, 1
UNION ALL SELECT 'MU3826', 'UNIT', 1.000, 1, 0, 270000.00, 225000.00, 1, 1
UNION ALL SELECT 'MU3827', 'UNIT', 1.000, 1, 0, 468000.00, 390000.00, 1, 1
UNION ALL SELECT 'MU3828', 'UNIT', 1.000, 1, 0, 324000.00, 270000.00, 1, 1
UNION ALL SELECT 'MU3829', 'UNIT', 1.000, 1, 0, 150000.00, 125000.00, 1, 1
UNION ALL SELECT 'MU3830', 'UNIT', 1.000, 1, 0, 198000.00, 165000.00, 1, 1
UNION ALL SELECT 'MU3831', 'MTR', 1.000, 1, 0, 74000.00, 54000.00, 1, 1
UNION ALL SELECT 'MU3832', 'MTR', 1.000, 1, 0, 37000.00, 28000.00, 1, 1
UNION ALL SELECT 'MU3833', 'MTR', 1.000, 1, 0, 15000.00, 6000.00, 1, 1
UNION ALL SELECT 'MU3834', 'MTR', 1.000, 1, 0, 19000.00, 11000.00, 1, 1
UNION ALL SELECT 'MU3835', 'UNIT', 1.000, 1, 0, 133000.00, 85000.00, 1, 1
UNION ALL SELECT 'MU3836', 'SAK', 1.000, 1, 0, 128000.00, 100000.00, 1, 1
UNION ALL SELECT 'MU3837', 'PCS', 1.000, 1, 0, 20000.00, 10000.00, 1, 1
UNION ALL SELECT 'MU3838', 'DUS', 1.000, 1, 0, 98000.00, 69940.00, 1, 1
UNION ALL SELECT 'MU3839', 'BTG', 1.000, 1, 0, 135000.00, 90000.00, 1, 1
UNION ALL SELECT 'MU3840', 'PCS', 1.000, 1, 0, 47000.00, 23500.00, 1, 1
UNION ALL SELECT 'MU3841', 'PCS', 1.000, 1, 0, 7000.00, 3500.00, 1, 1
UNION ALL SELECT 'MU3842', 'UNIT', 1.000, 1, 0, 1020000.00, 785000.00, 1, 1
UNION ALL SELECT 'MU3843', 'PCS', 1.000, 1, 0, 11000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU3844', 'PCS', 1.000, 1, 0, 25000.00, 16375.00, 1, 1
UNION ALL SELECT 'MU3845', 'PCS', 1.000, 1, 0, 32000.00, 21250.00, 1, 1
UNION ALL SELECT 'MU3846', 'PCS', 1.000, 1, 0, 39000.00, 25925.00, 1, 1
UNION ALL SELECT 'MU3847', 'KLG', 1.000, 1, 0, 28000.00, 20292.00, 1, 1
UNION ALL SELECT 'MU3848', 'KLG', 1.000, 1, 0, 28000.00, 20292.00, 1, 1
UNION ALL SELECT 'MU3849', 'DUS', 1.000, 1, 0, 88000.00, 22222.00, 1, 1
UNION ALL SELECT 'MU3850', 'DUS', 1.000, 1, 0, 98000.00, 68830.00, 1, 1
UNION ALL SELECT 'MU3851', 'SET', 1.000, 1, 0, 213000.00, 120000.00, 1, 1
UNION ALL SELECT 'MU3852', 'DUS', 1.000, 1, 0, 30000.00, 19500.00, 1, 1
UNION ALL SELECT 'MU3853', 'DUS', 1.000, 1, 0, 30000.00, 19500.00, 1, 1
UNION ALL SELECT 'MU3854', 'DUS', 1.000, 1, 0, 30000.00, 19500.00, 1, 1
UNION ALL SELECT 'MU3855', 'DUS', 1.000, 1, 0, 106000.00, 72399.00, 1, 1
UNION ALL SELECT 'MU3856', 'DUS', 1.000, 1, 0, 98000.00, 69940.00, 1, 1
UNION ALL SELECT 'MU3857', 'DUS', 1.000, 1, 0, 88000.00, 75000.00, 1, 1
UNION ALL SELECT 'MU3858', 'LBR', 1.000, 1, 0, 110000.00, 73297.00, 1, 1
UNION ALL SELECT 'MU3859', 'KG', 1.000, 1, 0, 24000.00, 18000.00, 1, 1
UNION ALL SELECT 'MU3860', 'PCS', 1.000, 1, 0, 23000.00, 16710.00, 1, 1
UNION ALL SELECT 'MU3861', 'PCS', 1.000, 1, 0, 23000.00, 11200.00, 1, 1
UNION ALL SELECT 'MU3862', 'PCS', 1.000, 1, 0, 75000.00, 41200.00, 1, 1
UNION ALL SELECT 'MU3863', 'PCS', 1.000, 1, 0, 70000.00, 38800.00, 1, 1
UNION ALL SELECT 'MU3864', 'PCS', 1.000, 1, 0, 26000.00, 12800.00, 1, 1
UNION ALL SELECT 'MU3865', 'PCS', 1.000, 1, 0, 24000.00, 11600.00, 1, 1
UNION ALL SELECT 'MU3866', 'PCS', 1.000, 1, 0, 6000.00, 2960.00, 1, 1
UNION ALL SELECT 'MU3867', 'PCS', 1.000, 1, 0, 25000.00, 12500.00, 1, 1
UNION ALL SELECT 'MU3868', 'SET', 1.000, 1, 0, 38000.00, 19000.00, 1, 1
UNION ALL SELECT 'MU3869', 'PCS', 1.000, 1, 0, 30000.00, 15000.00, 1, 1
UNION ALL SELECT 'MU3870', 'PCS', 1.000, 1, 0, 10000.00, 3600.00, 1, 1
UNION ALL SELECT 'MU3871', 'PCS', 1.000, 1, 0, 65000.00, 65000.00, 1, 1
UNION ALL SELECT 'MU3872', 'DUS', 1.000, 1, 0, 30000.00, 5000.00, 1, 1
UNION ALL SELECT 'MU3873', 'BTG', 1.000, 1, 0, 430000.00, 369000.00, 1, 1
UNION ALL SELECT 'MU3874', 'PCS', 1.000, 1, 0, 30000.00, 16500.00, 1, 1
UNION ALL SELECT 'MU3875', 'PCS', 1.000, 1, 0, 37000.00, 20350.00, 1, 1
UNION ALL SELECT 'MU3876', 'PSG', 1.000, 1, 0, 15000.00, 8250.00, 1, 1
UNION ALL SELECT 'MU3877', 'PCS', 1.000, 1, 0, 15000.00, 8250.00, 1, 1
UNION ALL SELECT 'MU3878', 'PCS', 1.000, 1, 0, 27000.00, 14850.00, 1, 1
UNION ALL SELECT 'MU3879', 'KLG', 1.000, 1, 0, 80000.00, 66100.00, 1, 1
UNION ALL SELECT 'MU3880', 'KLG', 1.000, 1, 0, 123000.00, 101700.00, 1, 1
UNION ALL SELECT 'MU3881', 'KLG', 1.000, 1, 0, 123000.00, 101700.00, 1, 1
UNION ALL SELECT 'MU3882', 'KLG', 1.000, 1, 0, 123000.00, 101700.00, 1, 1
UNION ALL SELECT 'MU3883', 'PCS', 1.000, 1, 0, 11000.00, 8780.00, 1, 1
UNION ALL SELECT 'MU3884', 'PCS', 1.000, 1, 0, 30000.00, 25000.00, 1, 1
UNION ALL SELECT 'MU3885', 'KLG', 1.000, 1, 0, 45000.00, 39050.00, 1, 1
UNION ALL SELECT 'MU3886', 'KLG', 1.000, 1, 0, 30000.00, 24510.00, 1, 1
UNION ALL SELECT 'MU3887', 'MTR', 1.000, 1, 0, 10000.00, 6728.00, 1, 1
UNION ALL SELECT 'MU3888', 'PCS', 1.000, 1, 0, 17000.00, 8500.00, 1, 1
UNION ALL SELECT 'MU3889', 'MTR', 1.000, 1, 0, 18000.00, 14500.00, 1, 1
UNION ALL SELECT 'MU3890', 'DUS', 1.000, 1, 0, 108000.00, 97000.00, 1, 1
UNION ALL SELECT 'MU3891', 'PCS', 1.000, 1, 0, 45000.00, 29400.00, 1, 1
UNION ALL SELECT 'MU3892', 'MTR', 1.000, 1, 0, 19000.00, 9555.00, 1, 1
UNION ALL SELECT 'MU3893', 'PCS', 1.000, 1, 0, 78000.00, 56000.00, 1, 1
UNION ALL SELECT 'MU3894', 'PCS', 1.000, 1, 0, 50000.00, 36000.00, 1, 1
UNION ALL SELECT 'MU3895', 'PCS', 1.000, 1, 0, 13000.00, 8386.00, 1, 1
UNION ALL SELECT 'MU3896', 'PCS', 1.000, 1, 0, 14000.00, 9000.00, 1, 1
UNION ALL SELECT 'MU3897', 'PCS', 1.000, 1, 0, 40000.00, 26240.00, 1, 1
UNION ALL SELECT 'MU3898', 'PCS', 1.000, 1, 0, 4000.00, 2590.00, 1, 1
UNION ALL SELECT 'MU3899', 'KLG', 1.000, 1, 0, 64000.00, 57800.00, 1, 1
UNION ALL SELECT 'MU0002', '50 METER', 50.000, 0, 1, 1200000.00, 737500.00, 0, 1
UNION ALL SELECT 'MU0010', 'SAK', 0.300, 0, 1, 741000.00, 5760.00, 0, 1
UNION ALL SELECT 'MU0011', 'ROL', 11.000, 0, 1, 270000.00, 220000.00, 0, 1
UNION ALL SELECT 'MU0122', 'ROL', 20.000, 0, 1, 876000.00, 584000.00, 0, 1
UNION ALL SELECT 'MU0145', 'BTG', 6.000, 0, 1, 60000.00, 72000.00, 0, 1
UNION ALL SELECT 'MU0167', 'LSN', 12.000, 0, 1, 54000.00, 28800.00, 0, 1
UNION ALL SELECT 'MU0315', 'PCS', 0.010, 0, 1, 500.00, 250.00, 0, 1
UNION ALL SELECT 'MU0493', 'PCS', 0.001, 0, 1, 250.00, 100.00, 0, 1
UNION ALL SELECT 'MU0568', 'LSN', 12.000, 0, 1, 54000.00, 48000.00, 0, 1
UNION ALL SELECT 'MU0852', 'MTR', 0.010, 0, 1, 5000.00, 2280.00, 0, 1
UNION ALL SELECT 'MU0901', 'PCS', 600.000, 0, 1, 500.00, 88200000.00, 0, 1
UNION ALL SELECT 'MU0908', 'PCS', 0.100, 0, 1, 250.00, 7031.20, 0, 1
UNION ALL SELECT 'MU1198', 'MTR', 0.050, 0, 1, 3000.00, 1500.00, 0, 1
UNION ALL SELECT 'MU1580', 'DUS', 25.000, 0, 1, 600000.00, 331250.00, 0, 1
UNION ALL SELECT 'MU1648', '25 METER', 25.000, 0, 1, 2500000.00, 1800000.00, 0, 1
UNION ALL SELECT 'MU1800', 'MTR', 40.000, 0, 1, 3000.00, 1380000.00, 0, 1
UNION ALL SELECT 'MU1953', '50 METER', 50.000, 0, 1, 2613000.00, 2250000.00, 0, 1
UNION ALL SELECT 'MU1983', 'BKS', 0.600, 0, 1, 21000.00, 1050.00, 0, 1
UNION ALL SELECT 'MU2093', '50 METER', 50.000, 0, 1, 887000.00, 640000.00, 0, 1
UNION ALL SELECT 'MU2094', '50 METER', 50.000, 0, 1, 711000.00, 520000.00, 0, 1
UNION ALL SELECT 'MU2095', '50 METER', 50.000, 0, 1, 647000.00, 480000.00, 0, 1
UNION ALL SELECT 'MU2096', '50 METER', 50.000, 0, 1, 590000.00, 440000.00, 0, 1
UNION ALL SELECT 'MU2098', 'ROL', 20.000, 0, 1, 351000.00, 8200000.00, 0, 1
UNION ALL SELECT 'MU2101', '50 METER', 50.000, 0, 1, 2175000.00, 1500000.00, 0, 1
UNION ALL SELECT 'MU2103', '50 METER', 50.000, 0, 1, 1200000.00, 850000.00, 0, 1
UNION ALL SELECT 'MU2474', 'ROL', 50.000, 0, 1, 1200000.00, 1000000.00, 0, 1
UNION ALL SELECT 'MU2492', '25 METER', 25.000, 0, 1, 1125000.00, 875000.00, 0, 1
UNION ALL SELECT 'MU2501', 'PCS', 0.020, 0, 1, 2000.00, 0.00, 0, 1
UNION ALL SELECT 'MU2673', '50 METER', 50.000, 0, 1, 250000.00, 75000.00, 0, 1
UNION ALL SELECT 'MU2673', '100 METER', 100.000, 0, 2, 500000.00, 150000.00, 0, 1
UNION ALL SELECT 'MU2674', '50 METER', 50.000, 0, 1, 263000.00, 100000.00, 0, 1
UNION ALL SELECT 'MU2674', '80 METER', 80.000, 0, 2, 526000.00, 160000.00, 0, 1
UNION ALL SELECT 'MU2675', '50 METER', 50.000, 0, 1, 275000.00, 125000.00, 0, 1
UNION ALL SELECT 'MU2675', '100 METER', 100.000, 0, 2, 550000.00, 250000.00, 0, 1
UNION ALL SELECT 'MU2837', 'PCS', 50.000, 0, 1, 9000.00, 7600000.00, 0, 1
UNION ALL SELECT 'MU2838', 'PCS', 100.000, 0, 1, 7000.00, 17900000.00, 0, 1
UNION ALL SELECT 'MU2840', 'DUS', 2.000, 0, 1, 117000.00, 60900.00, 0, 1
UNION ALL SELECT 'MU2880', 'PCS', 400.000, 0, 1, 800.00, 61180000.00, 0, 1
UNION ALL SELECT 'MU3086', 'PCS', 0.083, 0, 1, 4000.00, 1494.00, 0, 1
UNION ALL SELECT 'MU3087', 'PCS', 0.083, 0, 1, 4000.00, 1494.00, 0, 1
UNION ALL SELECT 'MU3127', 'LSN', 12.000, 0, 1, 50000.00, 24000.00, 0, 1
UNION ALL SELECT 'MU3128', 'LSN', 12.000, 0, 1, 30000.00, 15000.00, 0, 1
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

INSERT IGNORE INTO product_units (product_id, unit_code, conversion_factor, is_base, sort_order, is_sellable, selling_price, cost_price, is_default_sale, is_sealed_pack, is_conversion_confirmed, created_at, updated_at)
SELECT p.id, v.unit_code, v.conversion_factor, v.is_base, v.sort_order, 1, v.selling_price, v.cost_price, v.is_default_sale, 0, v.is_conversion_confirmed, @now, @now
FROM (
SELECT 'MU3351' AS sku, 'MTR' AS unit_code, 0.010 AS conversion_factor, 0 AS is_base, 1 AS sort_order, 3000.00 AS selling_price, 1260.00 AS cost_price, 0 AS is_default_sale, 1 AS is_conversion_confirmed
UNION ALL SELECT 'MU3352', 'MTR', 0.010, 0, 1, 8000.00, 3620.00, 0, 1
UNION ALL SELECT 'MU3493', 'DUS', 1200.000, 0, 1, 285000.00, 1008000.00, 0, 1
UNION ALL SELECT 'MU3504', 'PAK', 20.000, 0, 1, 67000.00, 42500.00, 0, 1
UNION ALL SELECT 'MU3833', 'ROL', 100.000, 0, 1, 1000000.00, 600000.00, 0, 1
UNION ALL SELECT 'MU0003', 'SAK', 1.000, 0, 1, 590000, 15200.00, 0, 0
UNION ALL SELECT 'MU0004', 'SAK', 1.000, 0, 1, 590000, 15200.00, 0, 0
UNION ALL SELECT 'MU0005', 'SAK', 1.000, 0, 1, 590000, 15200.00, 0, 0
UNION ALL SELECT 'MU0045', 'MTR', 1.000, 0, 1, 2000, 135000.00, 0, 0
UNION ALL SELECT 'MU0080', 'ROL', 1.000, 0, 1, 450000, 20000.00, 0, 0
UNION ALL SELECT 'MU0091', 'DUS', 1.000, 0, 1, 245000, 32000.00, 0, 0
UNION ALL SELECT 'MU0103', 'GROSIR-LEMBAR', 1.000, 0, 1, 79000, 45387.00, 0, 0
UNION ALL SELECT 'MU0119', 'BKS', 1.000, 0, 1, 8000, 90000.00, 0, 0
UNION ALL SELECT 'MU0120', 'BKS', 1.000, 0, 1, 10000, 110000.00, 0, 0
UNION ALL SELECT 'MU0149', 'MTR', 1.000, 0, 1, 25000, 119000.00, 0, 0
UNION ALL SELECT 'MU0171', 'ROL', 1.000, 0, 1, 650000, 5000.00, 0, 0
UNION ALL SELECT 'MU0207', 'ROL', 1.000, 0, 1, 215000, 9411.00, 0, 0
UNION ALL SELECT 'MU0212', 'ROL', 1.000, 0, 1, 780000, 19000.00, 0, 0
UNION ALL SELECT 'MU0248', 'PCS', 1.000, 0, 1, 250, 19500.00, 0, 0
UNION ALL SELECT 'MU0249', 'ROL', 1.000, 0, 1, 280000, 10000.00, 0, 0
UNION ALL SELECT 'MU0330', 'PCS', 1.000, 0, 1, 250, 10000.00, 0, 0
UNION ALL SELECT 'MU0444', 'MTR', 1.000, 0, 1, 38000, 1138000.00, 0, 0
UNION ALL SELECT 'MU0445', 'MTR', 1.000, 0, 1, 25000, 748000.00, 0, 0
UNION ALL SELECT 'MU0447', 'MTR', 1.000, 0, 1, 8000, 284000.00, 0, 0
UNION ALL SELECT 'MU0448', 'MTR', 1.000, 0, 1, 8000, 284000.00, 0, 0
UNION ALL SELECT 'MU0449', 'MTR', 1.000, 0, 1, 12000, 452000.00, 0, 0
UNION ALL SELECT 'MU0450', 'MTR', 1.000, 0, 1, 12000, 452000.00, 0, 0
UNION ALL SELECT 'MU0454', 'MTR', 1.000, 0, 1, 40000, 1569000.00, 0, 0
UNION ALL SELECT 'MU0583', 'MTR', 1.000, 0, 1, 15000, 760000.00, 0, 0
UNION ALL SELECT 'MU0583', '50 METER', 1.000, 0, 2, 650000, 760000.00, 0, 0
UNION ALL SELECT 'MU0703', 'DUS', 1.000, 0, 1, 200000, 1981.00, 0, 0
UNION ALL SELECT 'MU0744', 'DUS', 1.000, 0, 1, 253000, 29000.00, 0, 0
UNION ALL SELECT 'MU0855', 'MTR', 1.000, 0, 1, 26000, 1029000.00, 0, 0
UNION ALL SELECT 'MU0890', 'PCS', 1.000, 0, 1, 250, 0.00, 0, 0
UNION ALL SELECT 'MU0922', 'PCS', 1.000, 0, 1, 250, 4700.00, 0, 0
UNION ALL SELECT 'MU0977', 'PCS', 1.000, 0, 1, 1000, 12800.00, 0, 0
UNION ALL SELECT 'MU0979', 'PCS', 1.000, 0, 1, 3000, 16800.00, 0, 0
UNION ALL SELECT 'MU1000', 'LBR', 1.000, 0, 1, 2000, 64000.00, 0, 0
UNION ALL SELECT 'MU1269', 'PCS', 1.000, 0, 1, 130000, 0.00, 0, 0
UNION ALL SELECT 'MU1353', 'UNIT', 1.000, 0, 1, 42000, 12000.00, 0, 0
UNION ALL SELECT 'MU1565', 'DUS', 1.000, 0, 1, 120000, 3300.00, 0, 0
UNION ALL SELECT 'MU1566', 'DUS', 1.000, 0, 1, 350000, 24500.00, 0, 0
UNION ALL SELECT 'MU1712', 'SAK', 1.000, 0, 1, 700000, 19000.00, 0, 0
UNION ALL SELECT 'MU1836', 'PCS', 1.000, 0, 1, 1000, 42350.00, 0, 0
UNION ALL SELECT 'MU1837', '3PCS', 1.000, 0, 1, 1000, 11200.00, 0, 0
UNION ALL SELECT 'MU1888', 'MTR', 1.000, 0, 1, 18000, 48000.00, 0, 0
UNION ALL SELECT 'MU1909', 'GROSIR-LEMBAR', 1.000, 0, 1, 79000, 48452.00, 0, 0
UNION ALL SELECT 'MU2044', 'PAIL', 1.000, 0, 1, 1194000, 130000.00, 0, 0
UNION ALL SELECT 'MU2098', 'MTR', 1.000, 0, 2, 20000, 410000.00, 0, 0
UNION ALL SELECT 'MU2099', 'MTR', 1.000, 0, 1, 21000, 590000.00, 0, 0
UNION ALL SELECT 'MU2100', 'MTR', 1.000, 0, 1, 7000, 126000.00, 0, 0
UNION ALL SELECT 'MU2292', 'PAK', 1.000, 0, 1, 25000, 5000.00, 0, 0
UNION ALL SELECT 'MU2446', 'MTR', 1.000, 0, 1, 25000, 60000.00, 0, 0
UNION ALL SELECT 'MU2447', 'MTR', 1.000, 0, 1, 25000, 85000.00, 0, 0
UNION ALL SELECT 'MU2504', 'ROL', 1.000, 0, 1, 170000, 8000.00, 0, 0
UNION ALL SELECT 'MU2514', 'BKS', 1.000, 0, 1, 27000, 0.00, 0, 0
UNION ALL SELECT 'MU2610', 'DUS', 1.000, 0, 1, 90000, 50.00, 0, 0
UNION ALL SELECT 'MU2610', '100GR-145PCS', 1.000, 0, 2, 14000, 50.00, 0, 0
UNION ALL SELECT 'MU2613', 'DUS', 1.000, 0, 1, 90000, 50.00, 0, 0
UNION ALL SELECT 'MU2613', '100GR-145PCS', 1.000, 0, 2, 14000, 50.00, 0, 0
UNION ALL SELECT 'MU2614', 'DUS', 1.000, 0, 1, 90000, 50.00, 0, 0
UNION ALL SELECT 'MU2614', '100GR-145PCS', 1.000, 0, 2, 14000, 50.00, 0, 0
UNION ALL SELECT 'MU2615', 'DUS', 1.000, 0, 1, 128000, 250.00, 0, 0
UNION ALL SELECT 'MU2615', '100GR-110PCS', 1.000, 0, 2, 29000, 250.00, 0, 0
UNION ALL SELECT 'MU2616', 'DUS', 1.000, 0, 1, 128000, 250.00, 0, 0
UNION ALL SELECT 'MU2616', '100GR-110PCS', 1.000, 0, 2, 29000, 250.00, 0, 0
UNION ALL SELECT 'MU2617', 'DUS', 1.000, 0, 1, 128000, 250.00, 0, 0
UNION ALL SELECT 'MU2617', '100GR-110PCS', 1.000, 0, 2, 29000, 250.00, 0, 0
UNION ALL SELECT 'MU2618', 'DUS', 1.000, 0, 1, 90000, 50.00, 0, 0
UNION ALL SELECT 'MU2618', '100GR-145PCS', 1.000, 0, 2, 14000, 50.00, 0, 0
UNION ALL SELECT 'MU2638', 'BTL', 1.000, 0, 1, 85000, 0.00, 0, 0
UNION ALL SELECT 'MU2644', 'MTR', 1.000, 0, 1, 22000, 0.00, 0, 0
UNION ALL SELECT 'MU2682', 'AMBIL SENDIRI', 1.000, 0, 1, 129000, 112000.00, 0, 0
UNION ALL SELECT 'MU2684', 'AMBIL SENDIRI', 1.000, 0, 1, 100000, 80000.00, 0, 0
UNION ALL SELECT 'MU2695', 'GROSIR-LEMBAR', 1.000, 0, 1, 79000, 49500.00, 0, 0
UNION ALL SELECT 'MU2878', 'DUS-PCS', 1.000, 0, 1, 850, 350.00, 0, 0
UNION ALL SELECT 'MU2879', 'DUS', 1.000, 0, 1, 10, 640.00, 0, 0
UNION ALL SELECT 'MU2881', 'DUS-PCS', 1.000, 0, 1, 800, 350.00, 0, 0
UNION ALL SELECT 'MU2899', 'GROSIR-LEMBAR', 1.000, 0, 1, 73000, 55000.00, 0, 0
UNION ALL SELECT 'MU2929', 'PCS', 1.000, 0, 1, 250, 69750.00, 0, 0
UNION ALL SELECT 'MU2930', 'PCS', 1.000, 0, 1, 500, 213750.00, 0, 0
UNION ALL SELECT 'MU2931', 'PCS', 1.000, 0, 1, 250, 35000.00, 0, 0
UNION ALL SELECT 'MU2932', 'PCS', 1.000, 0, 1, 1000, 247500.00, 0, 0
UNION ALL SELECT 'MU2996', 'PCS', 1.000, 0, 1, 55000, 100000.00, 0, 0
UNION ALL SELECT 'MU2997', 'PCS', 1.000, 0, 1, 45000, 127450.00, 0, 0
UNION ALL SELECT 'MU2998', 'PCS', 1.000, 0, 1, 40000, 112350.00, 0, 0
UNION ALL SELECT 'MU2999', 'PCS', 1.000, 0, 1, 35000, 99400.00, 0, 0
UNION ALL SELECT 'MU3075', 'PCS', 1.000, 0, 1, 250, 84375.00, 0, 0
UNION ALL SELECT 'MU3286', 'SET', 1.000, 0, 1, 1020000, 710000.00, 0, 0
UNION ALL SELECT 'MU3314', 'ROL', 1.000, 0, 1, 170000, 8000.00, 0, 0
UNION ALL SELECT 'MU3337', 'DUS', 1.000, 0, 1, 444000, 296000.00, 0, 0
UNION ALL SELECT 'MU3394', 'AMBIL SENDIRI', 1.000, 0, 1, 100000, 99000.00, 0, 0
UNION ALL SELECT 'MU3412', 'SAK', 1.000, 0, 1, 125000, 70000.00, 0, 0
UNION ALL SELECT 'MU3438', 'PCS', 1.000, 0, 1, 300, 75000.00, 0, 0
UNION ALL SELECT 'MU3562', 'PSG', 1.000, 0, 1, 3000, 7000.00, 0, 0
UNION ALL SELECT 'MU3563', 'PSG', 1.000, 0, 1, 5000, 8000.00, 0, 0
UNION ALL SELECT 'MU3606', 'MTR', 1.000, 0, 1, 3000, 182400.00, 0, 0
UNION ALL SELECT 'MU3607', 'MTR', 1.000, 0, 1, 3000, 182400.00, 0, 0
UNION ALL SELECT 'MU3611', 'LBR', 1.000, 0, 1, 5000, 75000.00, 0, 0
UNION ALL SELECT 'MU3621', 'PCS', 1.000, 0, 1, 1500, 60500.00, 0, 0
UNION ALL SELECT 'MU3713', 'MTR', 1.000, 0, 1, 29000, 862500.00, 0, 0
UNION ALL SELECT 'MU3714', 'MTR', 1.000, 0, 1, 21000, 575000.00, 0, 0
UNION ALL SELECT 'MU3730', 'PCS', 1.000, 0, 1, 5000, 2250.00, 0, 0
UNION ALL SELECT 'MU3739', 'SET', 1.000, 0, 1, 277000, 110000.00, 0, 0
UNION ALL SELECT 'MU3748', 'PCS', 1.000, 0, 1, 2000, 135000.00, 0, 0
UNION ALL SELECT 'MU3836', 'AMBIL SENDIRI', 1.000, 0, 1, 123000, 100000.00, 0, 0
UNION ALL SELECT 'MU3852', 'PCS', 1.000, 0, 1, 250, 19500.00, 0, 0
UNION ALL SELECT 'MU3872', 'PCS', 1.000, 0, 1, 5000, 5000.00, 0, 0
UNION ALL SELECT 'MU3887', 'ROL', 1.000, 0, 1, 186000, 6728.00, 0, 0
UNION ALL SELECT 'MU3892', 'ROL', 1.000, 0, 1, 817000, 9555.00, 0, 0
) v
JOIN products p ON p.sku = v.sku
WHERE p.created_at = @now;

COMMIT;

-- ============================================================================
-- Pemeriksaan hasil: pada database yang tadinya kosong, "hasil" sama dengan "seharusnya". Bila sebelumnya sudah ada data
-- lain, "hasil" bisa lebih besar.
-- ============================================================================
SELECT 'barang' AS data, COUNT(*) AS hasil, 3858 AS seharusnya FROM products
UNION ALL SELECT 'barang aktif', COUNT(*), 3847 FROM products WHERE is_active = 1
UNION ALL SELECT 'satuan', COUNT(*), 40 FROM units
UNION ALL SELECT 'kategori', COUNT(*), 215 FROM categories
UNION ALL SELECT 'merek', COUNT(*), 462 FROM brands
UNION ALL SELECT 'satuan barang', COUNT(*), 4010 FROM product_units
UNION ALL SELECT 'satuan belum dikonfirmasi', COUNT(*), 105 FROM product_units WHERE is_conversion_confirmed = 0
UNION ALL SELECT 'barang tanpa satuan dasar', COUNT(*), 0 FROM products p WHERE NOT EXISTS (SELECT 1 FROM product_units u WHERE u.product_id = p.id AND u.is_base = 1)
UNION ALL SELECT 'baris stok', COUNT(*), 0 FROM stocks;

-- ============================================================================
-- Satuan yang masuk dengan konversi 1 dan BELUM DIKONFIRMASI (105 satuan). Isi konversi yang benar di data barang
-- (menu Barang > filter "Satuan tidak valid"), lalu simpan: isi selain 1 dianggap terkonfirmasi.
--   SKU | barang | satuan | satuan dasar | harga jual lama
--   MU0003 | PAKU 5CM | SAK | KG | 590000
--   MU0004 | PAKU 7CM | SAK | KG | 590000
--   MU0005 | PAKU 10CM | SAK | KG | 590000
--   MU0045 | SUMBU KOMPOR HOCK | METER | ROLL | 2000
--   MU0080 | KAWAT BENDRAT | ROLL | KG | 450000
--   MU0091 | KAWAT LAS RB 3.2MM | DUS | KG | 245000
--   MU0103 | SENG MERAH GELOMBANG POLOS 0,20 | GROSIR-LEMBAR | LEMBAR | 79000
--   MU0119 | CALSIUM | BUNGKUS | SAK | 8000
--   MU0120 | SEMEN PUTIH GARUDA | BUNGKUS | SAK | 10000
--   MU0149 | KAWAT RAM 1' | METER | ROLL | 25000
--   MU0171 | WARING 120CM | ROLL | METER | 650000
--   MU0207 | KARPET BANTENG 1.2x17m | ROLL | METER | 215000
--   MU0212 | PLASTIK BENING TIPIS 025X50M | ROLL | METER | 780000
--   MU0248 | SKRUP GIPSUM YUSTAR 3/4 | PCS | DUS | 250
--   MU0249 | KAWAT AYAM | ROLL | METER | 280000
--   MU0330 | KABEL TIES WIPRO 2.5X100 | PCS | BUNGKUS | 250
--   MU0444 | KABEL NYM 2X2,5 ETERNA | METER | 50 METER | 38000
--   MU0445 | KABEL NYM 2X1,5 ETERNA | METER | 50 METER | 25000
--   MU0447 | KABEL NYA 1.5 ETERNA MERAH | METER | 50 METER | 8000
--   MU0448 | KABEL NYA 1.5 ETERNA HITAM | METER | 50 METER | 8000
--   MU0449 | KABEL NYA 2,5 ETERNA MERAH | METER | 50 METER | 12000
--   MU0450 | KABEL NYA 2,5 ETERNA HITAM | METER | 50 METER | 12000
--   MU0454 | KABEL NYM 3X2,5 ETERNA | METER | 50 METER | 40000
--   MU0583 | SELANG BUANG KARET BIRU 3 X 100 | METER | 100 METER | 15000
--   MU0583 | SELANG BUANG KARET BIRU 3 X 100 | 50 METER | 100 METER | 650000
--   MU0703 | MATA GURINDA POTONG | DUS | PCS | 200000
--   MU0744 | KAWAT LAS RB 2,6MM | DUS | KG | 253000
--   MU0855 | KABEL NYM 3X1,5 ETERNA | METER | 50 METER | 26000
--   MU0890 | FISCER S 5 | PCS | PAK | 250
--   MU0922 | PAKU SKRUB 3/4  + | PCS | DUS | 250
--   MU0977 | FISCHER FRT M8 4X40MM | PCS | PAK | 1000
--   MU0979 | FISCHER FRT M12 6X60MM | PCS | PAK | 3000
--   MU1000 | LAKBAN ALUMUNIUM | LEMBAR | PCS | 2000
--   MU1269 | FLEKSIBLE SHAFT ASSY 84CM | PCS | UNIT | 130000
--   MU1353 | HANGER HOLDER (FITING) | UNIT | PCS | 42000
--   MU1565 | MATA GURINDA POTONG WD | DUS | PCS | 120000
--   MU1566 | MATA GURINDA POTONG NIPPON RESIBOND | DUS | PCS | 350000
--   MU1712 | PAKU SENG | SAK | KG | 700000
--   MU1836 | WIPRO KABEL TIES 4.8X300MM | PCS | BUNGKUS | 1000
--   MU1837 | KABEL TIES WIPRO 2.5X150MM | 3PCS | BUNGKUS | 1000
--   MU1888 | BESI GORDEN KOTAK | METER | BATANG | 18000
--   MU1909 | SENG BIRU GELOMBANG POLOS | GROSIR-LEMBAR | LEMBAR | 79000
--   MU2044 | CAT DASAR 5400 NIPPON | PAIL | GALON | 1194000
--   MU2098 | SELANG MILLIARD SUPERFLEX 5/8 | METER | 50 METER | 20000
--   MU2099 | SELANG MILLIARD SUPERFLEX 3/4 | METER | 50 METER | 21000
--   MU2100 | SELANG WATERPAS TR 1/4 | METER | 100 METER | 7000
--   MU2292 | BENANG WOLL | PAK | IKAT | 25000
--   MU2446 | KAWAT RAM 1/2' | METER | ROLL | 25000
--   MU2447 | KAWAT RAM 1/4' | METER | ROLL | 25000
--   MU2504 | AMPLAS GRIT 320 | ROLL | METER | 170000
--   MU2514 | RING GORDEN HITAM | BUNGKUS | PCS | 27000
--   MU2610 | PAKU RIVET 429 | DUS | PCS | 90000
--   MU2610 | PAKU RIVET 429 | 100GR/145PCS | PCS | 14000
--   MU2613 | PAKU RIVET 440 | DUS | PCS | 90000
--   MU2613 | PAKU RIVET 440 | 100GR/145PCS | PCS | 14000
--   MU2614 | PAKU RIVET 450 | DUS | PCS | 90000
--   MU2614 | PAKU RIVET 450 | 100GR/145PCS | PCS | 14000
--   MU2615 | PAKU RIVET 550 NICHIE | DUS | PCS | 128000
--   MU2615 | PAKU RIVET 550 NICHIE | 100GR/110PCS | PCS | 29000
--   MU2616 | PAKU RIVET 635 | DUS | PCS | 128000
--   MU2616 | PAKU RIVET 635 | 100GR/110PCS | PCS | 29000
--   MU2617 | PAKU RIVET 665 NICHIE | DUS | PCS | 128000
--   MU2617 | PAKU RIVET 665 NICHIE | 100GR/110PCS | PCS | 29000
--   MU2618 | PAKU RIVET 423 | DUS | PCS | 90000
--   MU2618 | PAKU RIVET 423 | 100GR/145PCS | PCS | 14000
--   MU2638 | METALLIC PAINT REAL GOLD | BOTOL | KALENG | 85000
--   MU2644 | TALANG PUTIH | METER | 50 METER | 22000
--   MU2682 | SEMEN GRESIK | AMBIL SENDIRI | SAK | 129000
--   MU2684 | SEMEN BOSOWA | AMBIL SENDIRI | SAK | 100000
--   MU2695 | SENG PUTIH | GROSIR-LEMBAR | LEMBAR | 79000
--   MU2878 | SKRUP BAJA RINGAN 12X40 KUNINGAN | DUS/PCS | PCS | 850
--   MU2879 | SKRUP BAJA RINGAN 12X50 KUNINGAN | DUS | PCS | 10
--   MU2881 | SKRUP BAJA RINGAN 12X30 | DUS/PCS | PCS | 800
--   MU2899 | SENG GELOMBANG ALUMUNIUM PUTIH | GROSIR-LEMBAR | LEMBAR | 73000
--   MU2929 | 1" SKRUP BAJA SKRUP GYPSUM | PCS | DUS | 250
--   MU2930 | 2" SKRUP BAJA SKRUP GYPSUM | PCS | DUS | 500
--   MU2931 | 3/4" SKRUP BAJA SKRUP GYPSUM | PCS | DUS | 250
--   MU2932 | 3" SKRUP BAJA SKRUP GYPSUM | PCS | DUS | 1000
--   MU2996 | PHILIPS LED 12W 3IN1 | PCS | IKAT | 55000
--   MU2997 | PHILIPS LED 10W 3IN1 | PCS | IKAT | 45000
--   MU2998 | PHILIPS LED 8W 3IN1 | PCS | IKAT | 40000
--   MU2999 | PHILIPS LED 6W 3IN1 | PCS | IKAT | 35000
--   MU3075 | 1 1/4" SKRUP BAJA SKRUP GYPSUM | PCS | DUS | 250
--   MU3286 | WASTAFEL KERAMIK K-59 | SET | PCS | 1020000
--   MU3314 | AMPLAS ROLL AA100 | ROLL | METER | 170000
--   MU3337 | SKRUP BAJA RINGAN 12X60 | DUS | PCS | 444000
--   MU3394 | SEMEN GERSIK GROSIR | AMBIL SENDIRI | SAK | 100000
--   MU3412 | SEMEN TONASA 50KG | SAK | AMBIL SENDIRI | 125000
--   MU3438 | PAKU RIVET 640 | PCS | DUS | 300
--   MU3562 | SAMBUNGAN KABEL 12 MM | PASANG | BATANG | 3000
--   MU3563 | SAMBUNGAN KABEL 14 MM | PASANG | BATANG | 5000
--   MU3606 | KABEL NYA F 1.5 | METER | ROLL | 3000
--   MU3607 | KABEL NYA F 1.5 HITAM | METER | ROLL | 3000
--   MU3611 | ARMANY GREEN 25X25 | LEMBAR | DUS | 5000
--   MU3621 | KABEL TIES 4.8X400 | PCS | PAK | 1500
--   MU3713 | SELANG SPRING HOSE 1" | METER | ROLL | 29000
--   MU3714 | SELANG SPRING HOSE 3/4' | METER | ROLL | 21000
--   MU3730 | TAMBANG KUNING KEMASAN | PCS | BUNGKUS | 5000
--   MU3739 | FITING LAMPU 10M 10 FITING | SET | ROLL | 277000
--   MU3748 | KABEL TIES 7.5X500 | PCS | PAK | 2000
--   MU3836 | SEMEN CONS | AMBIL SENDIRI | SAK | 123000
--   MU3852 | SKRUP GIPSUM YUSTAR 1 | PCS | DUS | 250
--   MU3872 | KAPUR BESI | PCS | DUS | 5000
--   MU3887 | AMPLAS ROLL AA60 | ROLL | METER | 186000
--   MU3892 | KABEL NYM ORENCHI 2X1.5X50Y | ROLL | METER | 817000
