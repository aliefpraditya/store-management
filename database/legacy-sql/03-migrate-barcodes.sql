-- ============================================================================
-- 03 — Isi barcode barang: barcode lama -> kolom products.barcode; bila tidak ada, diisi SKU
-- (jalankan SETELAH 02-migrate-items.sql; data siap pakai, tidak membaca database lain)
--
-- Database sasaran : u225207914_store_mu
-- Syarat           : migrasi tenant 2026_11_20_100000_add_barcode_to_products_table sudah dijalankan (kolom products.barcode ada).
-- Hasil            : 3858 barang = 668 memakai barcode lama + 3190 memakai SKU sebagai barcode
--                    (31 di antaranya memang barcode lamanya sama dengan SKU-nya sendiri).
--
-- Aturan (sama dengan validasi form barang: barcode unik, dan tidak boleh sama dengan SKU barang lain):
--   * Barcode lama dipakai apa adanya bila bukan placeholder.
--   * Dianggap tidak ada (-> SKU): kosong, '0', dan isian 1-2 karakter ('1', '-', '00', '02', '6').
--   * Barcode yang sama dipakai beberapa barang -> TIDAK dipakai untuk semua barang itu (satu kali scan tidak bisa membedakan),
--     semuanya jadi SKU. Daftarnya di bagian bawah.
--   * Barcode yang sama dengan SKU barang lain -> jadi SKU. Daftarnya di bagian bawah.
-- Aman dijalankan ulang: barang dicari lewat SKU (hasil migrasi baru maupun yang sudah ada sebelumnya). Hanya barcode yang masih kosong
-- yang diisi; barang yang sudah punya barcode tidak diubah, dan barang di luar daftar SKU migrasi tidak disentuh. UPDATE IGNORE melewati
-- barcode yang bentrok dengan barcode barang lain, lalu barang itu mendapat SKU-nya.
--
-- Jalankan:
--   mysql -u<user> -p u225207914_store_mu < database/legacy-sql/03-migrate-barcodes.sql
-- ============================================================================

USE u225207914_store_mu;
SET NAMES utf8mb4;

START TRANSACTION;

-- Barcode lama (699 barang)
UPDATE IGNORE products SET barcode = '3606481491169' WHERE sku = 'MU-2409' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207856467' WHERE sku = 'MU0001' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU0012' WHERE sku = 'MU0012' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '74-5345-08' WHERE sku = 'MU0013' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-1335-0060' WHERE sku = 'MU0019' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-1335-0070' WHERE sku = 'MU0022' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '83-3310-012' WHERE sku = 'MU0027' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '83-3310-010' WHERE sku = 'MU0028' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '83-3310-014' WHERE sku = 'MU0029' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8992832559981' WHERE sku = 'MU0031' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8996681780404' WHERE sku = 'MU0032' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '4800374027827' WHERE sku = 'MU0035' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8995207209207' WHERE sku = 'MU0048' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-1335-0090' WHERE sku = 'MU0053' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '96-6714-0301' WHERE sku = 'MU0055' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994004101226' WHERE sku = 'MU0074' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8858703021448' WHERE sku = 'MU0077' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '9555086301301' WHERE sku = 'MU0081' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '9555202610805' WHERE sku = 'MU0082' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '721688005186' WHERE sku = 'MU0092' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '721688005087' WHERE sku = 'MU0095' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8710103727422' WHERE sku = 'MU0106' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '721688005155' WHERE sku = 'MU0118' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994680063153' WHERE sku = 'MU0130' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8993319420053' WHERE sku = 'MU0139' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '67-3266-0311' WHERE sku = 'MU0143' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207851523' WHERE sku = 'MU0152' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU0157' WHERE sku = 'MU0157' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997020368109' WHERE sku = 'MU0158' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997020369083' WHERE sku = 'MU0161' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997020369106' WHERE sku = 'MU0162' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '721688005032' WHERE sku = 'MU0173' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207851509' WHERE sku = 'MU0195' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '10102611' WHERE sku = 'MU0232' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '9314188006039' WHERE sku = 'MU0247' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997015880067' WHERE sku = 'MU0254' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997015881767' WHERE sku = 'MU0255' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '86-3087-0310' WHERE sku = 'MU0292' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '3788911442276' WHERE sku = 'MU0297' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '079567600053' WHERE sku = 'MU0304' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381637190' WHERE sku = 'MU0305' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '62-1001-3620' WHERE sku = 'MU0315' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '62-1001-2510' WHERE sku = 'MU0330' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8788911442257' WHERE sku = 'MU0339' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '1811500114901' WHERE sku = 'MU0341' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '79-6510-10' WHERE sku = 'MU0342' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '77-1105-' WHERE sku = 'MU0344' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-3816-100' WHERE sku = 'MU0346' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-3816-120' WHERE sku = 'MU0347' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-3815-140' WHERE sku = 'MU0348' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '78-8004-16' WHERE sku = 'MU0357' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '15-2820-0026' WHERE sku = 'MU0359' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '057689232109' WHERE sku = 'MU0361' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-3745-080' WHERE sku = 'MU0362' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '0788928962050' WHERE sku = 'MU0363' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '31-1025-' WHERE sku = 'MU0367' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '7311518288358' WHERE sku = 'MU0368' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '7311518233334' WHERE sku = 'MU0369' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MB1690' WHERE sku = 'MU0387' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU0389' WHERE sku = 'MU0389' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '81-1428-5160' WHERE sku = 'MU0398' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '65-1750-1080' WHERE sku = 'MU0403' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '65-1750-3080' WHERE sku = 'MU0404' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU0407' WHERE sku = 'MU0407' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '83-3100-006' WHERE sku = 'MU0424' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '0900003428705' WHERE sku = 'MU0425' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997777111027' WHERE sku = 'MU0436' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '3606481491138' WHERE sku = 'MU0437' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '3606481491145' WHERE sku = 'MU0438' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '3606481491152' WHERE sku = 'MU0439' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU0444' WHERE sku = 'MU0444' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU0445' WHERE sku = 'MU0445' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '65-1100-0001' WHERE sku = 'MU0446' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU0447' WHERE sku = 'MU0447' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU0448' WHERE sku = 'MU0448' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU0449' WHERE sku = 'MU0449' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU0450' WHERE sku = 'MU0450' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU0454' WHERE sku = 'MU0454' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011103024' WHERE sku = 'MU0457' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011156457' WHERE sku = 'MU0461' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011156464' WHERE sku = 'MU0462' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011146663' WHERE sku = 'MU0463' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011157034' WHERE sku = 'MU0464' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011155269' WHERE sku = 'MU0465' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011157041' WHERE sku = 'MU0467' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011152879' WHERE sku = 'MU0470' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011153913' WHERE sku = 'MU0471' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8998889814004' WHERE sku = 'MU0482' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011147073' WHERE sku = 'MU0484' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011147066' WHERE sku = 'MU0485' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011147080' WHERE sku = 'MU0486' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011153906' WHERE sku = 'MU0492' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011155306' WHERE sku = 'MU0495' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011146533' WHERE sku = 'MU0496' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011098177' WHERE sku = 'MU0497' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011120250' WHERE sku = 'MU0499' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011103291' WHERE sku = 'MU0500' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997228591200' WHERE sku = 'MU0501' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011102805' WHERE sku = 'MU0502' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011103017' WHERE sku = 'MU0503' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011103079' WHERE sku = 'MU0505' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011097651' WHERE sku = 'MU0506' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997020367034' WHERE sku = 'MU0513' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU0531' WHERE sku = 'MU0531' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011120274' WHERE sku = 'MU0542' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '38CWH500225' WHERE sku = 'MU0546' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8718291791874' WHERE sku = 'MU0550' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8718696822920' WHERE sku = 'MU0551' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8718291791911' WHERE sku = 'MU0552' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8718291777236' WHERE sku = 'MU0553' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8887549815829' WHERE sku = 'MU0554' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8718699640491' WHERE sku = 'MU0556' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8718696822883' WHERE sku = 'MU0560' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '68112355' WHERE sku = 'MU0569' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994042102742' WHERE sku = 'MU0570' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '41CWHA00758' WHERE sku = 'MU0574' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '000745345084' WHERE sku = 'MU0575' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '076192015061' WHERE sku = 'MU0576' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '67-3260-0101' WHERE sku = 'MU0577' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '082472123562' WHERE sku = 'MU0578' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '15-1555-9003' WHERE sku = 'MU0579' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '6949509206961' WHERE sku = 'MU0580' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '15-7400-0004' WHERE sku = 'MU0581' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '28-3600-0150' WHERE sku = 'MU0584' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '26-3375-' WHERE sku = 'MU0585' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '28-3600-0030' WHERE sku = 'MU0586' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '28-3600-0050' WHERE sku = 'MU0587' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '28-3600-0100' WHERE sku = 'MU0588' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '42-1060-03' WHERE sku = 'MU0589' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '42-9085-03' WHERE sku = 'MU0590' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '43-3760-200' WHERE sku = 'MU0591' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '43-3798-300' WHERE sku = 'MU0592' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '49-2088-18' WHERE sku = 'MU0594' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '49-2001-18' WHERE sku = 'MU0595' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '49-2697-0030' WHERE sku = 'MU0596' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '4927870500' WHERE sku = 'MU0598' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '4927871000' WHERE sku = 'MU0599' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '67-2104-10' WHERE sku = 'MU0600' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '67-2816-' WHERE sku = 'MU0601' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '67-3068-' WHERE sku = 'MU0602' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1359-0010' WHERE sku = 'MU0603' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1359-0012' WHERE sku = 'MU0604' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1359-0014' WHERE sku = 'MU0605' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1359-0019' WHERE sku = 'MU0606' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1359-0022' WHERE sku = 'MU0607' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1359-0024' WHERE sku = 'MU0608' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1359-9911' WHERE sku = 'MU0609' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1359-9914' WHERE sku = 'MU0610' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-3745-060' WHERE sku = 'MU0611' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-3745-120' WHERE sku = 'MU0612' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '73-6824-' WHERE sku = 'MU0614' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '76-1820-08' WHERE sku = 'MU0615' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '76-1820-10' WHERE sku = 'MU0616' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '77-6100-' WHERE sku = 'MU0617' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '34-3111-' WHERE sku = 'MU0618' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '79-3206-175' WHERE sku = 'MU0619' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '79-3206-200' WHERE sku = 'MU0620' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '79-3206-07' WHERE sku = 'MU0621' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-1335-0030' WHERE sku = 'MU0622' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-1335-0040' WHERE sku = 'MU0623' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-1335-0050' WHERE sku = 'MU0624' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '84-6112-04' WHERE sku = 'MU0625' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '85-2245-3513' WHERE sku = 'MU0626' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '86-2040-0110' WHERE sku = 'MU0627' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '86-3013-0210' WHERE sku = 'MU0628' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '86-3020-0510' WHERE sku = 'MU0629' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '86-3027-4510' WHERE sku = 'MU0630' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '86-3027-4520' WHERE sku = 'MU0631' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '86-3087-0314' WHERE sku = 'MU0633' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '86-3093-0310' WHERE sku = 'MU0634' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '86-3107-0310' WHERE sku = 'MU0635' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '86-3125-0310' WHERE sku = 'MU0636' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '86-3130-0310' WHERE sku = 'MU0637' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '88-7730-7024' WHERE sku = 'MU0638' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '079567600312' WHERE sku = 'MU0639' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '98-3199-' WHERE sku = 'MU0640' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '3451321781265' WHERE sku = 'MU0641' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207855538' WHERE sku = 'MU0659' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8998838061015' WHERE sku = 'MU0687' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8998838061022' WHERE sku = 'MU0688' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207855699' WHERE sku = 'MU0695' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207855781' WHERE sku = 'MU0698' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8992875140139' WHERE sku = 'MU0702' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207859024' WHERE sku = 'MU0709' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8710103793830' WHERE sku = 'MU0714' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '87-3328-1212' WHERE sku = 'MU0715' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '87-3328-1012' WHERE sku = 'MU0716' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '87-3328-0812' WHERE sku = 'MU0717' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '87-3328-0610' WHERE sku = 'MU0718' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'HTC001' WHERE sku = 'MU0721' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381099080' WHERE sku = 'MU0734' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'KBL001' WHERE sku = 'MU0737' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8312332613479' WHERE sku = 'MU0740' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '68-1123-48' WHERE sku = 'MU0742' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '4719930060171' WHERE sku = 'MU0743' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '0101' WHERE sku = 'MU0744' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8718291791959' WHERE sku = 'MU0763' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8718699640538' WHERE sku = 'MU0764' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994397000144' WHERE sku = 'MU0766' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994397000366' WHERE sku = 'MU0767' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'LIP001' WHERE sku = 'MU0768' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '7226029988201' WHERE sku = 'MU0769' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '4511545582297' WHERE sku = 'MU0774' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994397000052' WHERE sku = 'MU0795' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '4719910500086' WHERE sku = 'MU0799' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207852957' WHERE sku = 'MU0805' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '6933164683441' WHERE sku = 'MU0813' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'M093' WHERE sku = 'MU0851' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'M096' WHERE sku = 'MU0854' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997011422902' WHERE sku = 'MU0856' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381609791' WHERE sku = 'MU0864' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381001991' WHERE sku = 'MU0865' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381052382' WHERE sku = 'MU0866' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '3165140185011' WHERE sku = 'MU0867' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381077095' WHERE sku = 'MU0868' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381828413' WHERE sku = 'MU0869' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381622998' WHERE sku = 'MU0870' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997020369014' WHERE sku = 'MU0872' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU0873' WHERE sku = 'MU0873' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '83-3120-008' WHERE sku = 'MU0894' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8888882201010' WHERE sku = 'MU0899' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8711295663543' WHERE sku = 'MU0928' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997025943066' WHERE sku = 'MU0933' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU0934' WHERE sku = 'MU0934' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997025942793' WHERE sku = 'MU0937' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997025943035' WHERE sku = 'MU0938' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997025942830' WHERE sku = 'MU0940' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '68-1608-0012' WHERE sku = 'MU0942' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '68-1365-10' WHERE sku = 'MU0943' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8718291777151' WHERE sku = 'MU0944' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '85-2521-1616' WHERE sku = 'MU0945' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8718291777199' WHERE sku = 'MU0946' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '85-2245-3516' WHERE sku = 'MU0947' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8718696484920' WHERE sku = 'MU0948' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8718696484807' WHERE sku = 'MU0949' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997238770114' WHERE sku = 'MU0951' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '6958001331309' WHERE sku = 'MU0953' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '3165140835930' WHERE sku = 'MU0969' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '3165140836821' WHERE sku = 'MU0971' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011096531' WHERE sku = 'MU0974' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011096685' WHERE sku = 'MU0975' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'WPN60380007' WHERE sku = 'MU0976' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'WPN60380008' WHERE sku = 'MU0977' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'WPN60380009' WHERE sku = 'MU0978' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'WPN60380006' WHERE sku = 'MU0979' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '500300381' WHERE sku = 'MU0985' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207855651' WHERE sku = 'MU0986' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '67-3050-40' WHERE sku = 'MU0989' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994923006657' WHERE sku = 'MU0990' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '6951227200671' WHERE sku = 'MU0991' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '4711734338685' WHERE sku = 'MU0993' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8886022941512' WHERE sku = 'MU0994' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997777051026' WHERE sku = 'MU0995' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997777051019' WHERE sku = 'MU0996' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997015380567' WHERE sku = 'MU1000' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8999118582206' WHERE sku = 'MU1021' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8999118582503' WHERE sku = 'MU1022' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011163677' WHERE sku = 'MU1023' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011144966' WHERE sku = 'MU1024' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011144973' WHERE sku = 'MU1025' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991020506011' WHERE sku = 'MU1094' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '38CHJB00066' WHERE sku = 'MU1095' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011111074' WHERE sku = 'MU1097' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8710103016816' WHERE sku = 'MU1099' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '079567600282' WHERE sku = 'MU1100' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8710103763406' WHERE sku = 'MU1102' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997010490476' WHERE sku = 'MU1104' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997010490445' WHERE sku = 'MU1105' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997010490469' WHERE sku = 'MU1106' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997010490452' WHERE sku = 'MU1108' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991020306031' WHERE sku = 'MU1120' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8658327691415' WHERE sku = 'MU1124' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8993505162620' WHERE sku = 'MU1129' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '7C1A8823132326501909051169' WHERE sku = 'MU1130' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU1132' WHERE sku = 'MU1132' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1359-0017' WHERE sku = 'MU1142' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU116' WHERE sku = 'MU116' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '79-3303-200' WHERE sku = 'MU1182' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207859550' WHERE sku = 'MU1183' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1359-0013' WHERE sku = 'MU1186' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1359-0009' WHERE sku = 'MU1188' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381187756' WHERE sku = 'MU1206' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '087295170212' WHERE sku = 'MU1209' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381357920' WHERE sku = 'MU1223' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381357760' WHERE sku = 'MU1224' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381357852' WHERE sku = 'MU1225' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '087295165119' WHERE sku = 'MU1227' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011124012' WHERE sku = 'MU1232' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '091042180086' WHERE sku = 'MU1233' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '091042170151' WHERE sku = 'MU1234' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994122009879' WHERE sku = 'MU1242' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994122009862' WHERE sku = 'MU1253' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381998567' WHERE sku = 'MU1262' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '3606485121970' WHERE sku = 'MU1271' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '3606485122311' WHERE sku = 'MU1272' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '3606485121987' WHERE sku = 'MU1273' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MKT002' WHERE sku = 'MU1280' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381618977' WHERE sku = 'MU1281' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381434911' WHERE sku = 'MU1285' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381998512' WHERE sku = 'MU1286' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381640503' WHERE sku = 'MU1294' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994397000090' WHERE sku = 'MU1304' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997006900026' WHERE sku = 'MU1307' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8993120200318' WHERE sku = 'MU1315' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991011327816' WHERE sku = 'MU1316' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994397000120' WHERE sku = 'MU1321' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '71-6510-1215' WHERE sku = 'MU1323' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '7311518233570' WHERE sku = 'MU1331' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207859031' WHERE sku = 'MU1333' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU1352' WHERE sku = 'MU1352' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '77-1101-' WHERE sku = 'MU1360' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '3606480907395' WHERE sku = 'MU1361' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '3606480907401' WHERE sku = 'MU1362' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '3606480907661' WHERE sku = 'MU1363' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991704000033' WHERE sku = 'MU1371' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991704000040' WHERE sku = 'MU1372' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997209940171' WHERE sku = 'MU1373' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '7311518239152' WHERE sku = 'MU1392' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU1395' WHERE sku = 'MU1395' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8992746700844' WHERE sku = 'MU1419' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8992746700875' WHERE sku = 'MU1420' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8992746700417' WHERE sku = 'MU1422' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8992745700913' WHERE sku = 'MU1423' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8992745700951' WHERE sku = 'MU1424' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8992745325222' WHERE sku = 'MU1425' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8992749770318' WHERE sku = 'MU1426' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8992742770391' WHERE sku = 'MU1427' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997015740224' WHERE sku = 'MU1431' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997015740248' WHERE sku = 'MU1432' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8992748195402' WHERE sku = 'MU1433' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8992745730200' WHERE sku = 'MU1438' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '91-5504-0100' WHERE sku = 'MU1443' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991011114928' WHERE sku = 'MU1456' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '90793AT205' WHERE sku = 'MU1457' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '90793AJ804' WHERE sku = 'MU1458' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991011324921' WHERE sku = 'MU1459' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991011111040' WHERE sku = 'MU1460' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991011104929' WHERE sku = 'MU1461' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991011110951' WHERE sku = 'MU1462' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '4719911120108' WHERE sku = 'MU1502' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-1335-0120' WHERE sku = 'MU1537' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-1335-0110' WHERE sku = 'MU1538' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-1335-0100' WHERE sku = 'MU1539' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '73-3844-' WHERE sku = 'MU1561' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '88-4518-0024' WHERE sku = 'MU1582' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '42-1060-04' WHERE sku = 'MU1591' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '42-1060-02' WHERE sku = 'MU1592' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'WPN40240036' WHERE sku = 'MU1606' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '25-9009-038' WHERE sku = 'MU1617' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997215401031' WHERE sku = 'MU1618' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '6936073350015' WHERE sku = 'MU1620' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207853015' WHERE sku = 'MU1642' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8858752700134' WHERE sku = 'MU1669' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991011328073' WHERE sku = 'MU1681' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '6939916600698' WHERE sku = 'MU1692' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MB1620' WHERE sku = 'MU1702' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MB392' WHERE sku = 'MU1716' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997020367195' WHERE sku = 'MU1717' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-1350-0025' WHERE sku = 'MU1718' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '76-1820-14' WHERE sku = 'MU1728' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU2653' WHERE sku = 'MU1739' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011217448' WHERE sku = 'MU1741' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011153722' WHERE sku = 'MU1742' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '6912562856619' WHERE sku = 'MU1745' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU1777' WHERE sku = 'MU1777' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '3606481491176' WHERE sku = 'MU1784' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-1335-0080' WHERE sku = 'MU1789' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '76-1820-12' WHERE sku = 'MU1791' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '091042170066' WHERE sku = 'MU1800' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8998889214002' WHERE sku = 'MU1801' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '6970575510089' WHERE sku = 'MU1808' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '091042170045' WHERE sku = 'MU1809' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'RP001' WHERE sku = 'MU1823' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '67-3106-' WHERE sku = 'MU1825' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '78-8212-16' WHERE sku = 'MU1826' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '62-1003-4830' WHERE sku = 'MU1836' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '62-1004-2515' WHERE sku = 'MU1837' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '23-7218-' WHERE sku = 'MU1839' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207852001' WHERE sku = 'MU1840' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '076192010066' WHERE sku = 'MU1842' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '67-3230-0200' WHERE sku = 'MU1843' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '3127885188610' WHERE sku = 'MU1844' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU0000800030001' WHERE sku = 'MU1846' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU1848' WHERE sku = 'MU1848' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990310340823' WHERE sku = 'MU1863' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207855682' WHERE sku = 'MU1876' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '65-1865-0165' WHERE sku = 'MU1920' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '92-3841-240' WHERE sku = 'MU1952' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '84-6212-044' WHERE sku = 'MU1982' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'WPN50260025' WHERE sku = 'MU1991' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '85-2521-1316' WHERE sku = 'MU2047' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '96-4704-0241' WHERE sku = 'MU2053' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8993414772705' WHERE sku = 'MU2087' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991011104936' WHERE sku = 'MU2107' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991011327823' WHERE sku = 'MU2108' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'CATAAB--1234077' WHERE sku = 'MU2109' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'CATAASG-- 3E9901' WHERE sku = 'MU2110' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991011110982' WHERE sku = 'MU2111' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991011110999' WHERE sku = 'MU2112' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '67-3260-0102' WHERE sku = 'MU2118' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'WPN40210300' WHERE sku = 'MU2120' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU2121' WHERE sku = 'MU2121' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '0MU2125' WHERE sku = 'MU2125' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997777080323' WHERE sku = 'MU2126' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997209940126' WHERE sku = 'MU2127' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990007910117' WHERE sku = 'MU2147' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997223620028' WHERE sku = 'MU2148' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997223620011' WHERE sku = 'MU2149' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '42-1060-01' WHERE sku = 'MU2150' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207853954' WHERE sku = 'MU2155' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '4717302603186' WHERE sku = 'MU2157' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '79-3206-150' WHERE sku = 'MU2159' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '79-3427-150' WHERE sku = 'MU2160' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1359-0015' WHERE sku = 'MU2163' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '73-3228-060' WHERE sku = 'MU2164' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1359-0008' WHERE sku = 'MU2165' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '67-3210-0040' WHERE sku = 'MU2167' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '67-3220-0100' WHERE sku = 'MU2168' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '076192024063' WHERE sku = 'MU2169' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381357814' WHERE sku = 'MU2177' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997225946713' WHERE sku = 'MU2178' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997225946720' WHERE sku = 'MU2179' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997225946737' WHERE sku = 'MU2180' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997225946744' WHERE sku = 'MU2181' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '6907698016167' WHERE sku = 'MU2192' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '79-3303-150' WHERE sku = 'MU2194' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997016100355' WHERE sku = 'MU2197' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994844012966' WHERE sku = 'MU2198' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994680021634' WHERE sku = 'MU2199' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU2276' WHERE sku = 'MU2276' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997015880029' WHERE sku = 'MU2278' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8995588010157' WHERE sku = 'MU2279' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8718696822968' WHERE sku = 'MU2289' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '69-3071-25' WHERE sku = 'MU2300' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '69-3071-32' WHERE sku = 'MU2301' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '69-3071-38' WHERE sku = 'MU2302' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '69-3071-50' WHERE sku = 'MU2303' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '69-3071-63' WHERE sku = 'MU2304' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '69-3071-75' WHERE sku = 'MU2305' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '69-3075-40' WHERE sku = 'MU2306' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '69-3075-50' WHERE sku = 'MU2307' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '69-3075-60' WHERE sku = 'MU2308' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '69-3075-70' WHERE sku = 'MU2309' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '67-3050-10' WHERE sku = 'MU2310' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '25-3501-1065' WHERE sku = 'MU2311' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '25-3512-2065' WHERE sku = 'MU2312' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '85-2810-10' WHERE sku = 'MU2313' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '79-3303-06' WHERE sku = 'MU2314' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '79-3303-08' WHERE sku = 'MU2315' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '15-6820-082' WHERE sku = 'MU2318' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'WPN40330010' WHERE sku = 'MU2320' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '4006062740713' WHERE sku = 'MU2322' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '79-3427-200' WHERE sku = 'MU2325' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207859574' WHERE sku = 'MU2326' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207859048' WHERE sku = 'MU2328' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997016490258' WHERE sku = 'MU2334' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '0900003428704' WHERE sku = 'MU2335' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381998505' WHERE sku = 'MU2336' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1268-1113' WHERE sku = 'MU2337' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1268-1315' WHERE sku = 'MU2338' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1268-1417' WHERE sku = 'MU2339' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1268-1922' WHERE sku = 'MU2340' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1264-1011' WHERE sku = 'MU2341' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1264-1213' WHERE sku = 'MU2342' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1264-1415' WHERE sku = 'MU2343' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1264-1617' WHERE sku = 'MU2344' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1264-1819' WHERE sku = 'MU2345' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '76-1857-802' WHERE sku = 'MU2346' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '76-1857-013' WHERE sku = 'MU2347' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '76-1857-024' WHERE sku = 'MU2348' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '76-1857-247' WHERE sku = 'MU2349' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '81-9950-' WHERE sku = 'MU2351' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '24-3122-03' WHERE sku = 'MU2352' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '15-2821B-0006' WHERE sku = 'MU2353' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '15-2110-0040' WHERE sku = 'MU2354' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '26-3620-' WHERE sku = 'MU2405' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '7792643155218' WHERE sku = 'MU2472' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MB1526' WHERE sku = 'MU2495' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '63-3373-08' WHERE sku = 'MU2519' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '63-3373-10' WHERE sku = 'MU2520' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '83-3140-010' WHERE sku = 'MU2521' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '77-1150-' WHERE sku = 'MU2525' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '78-8002-08' WHERE sku = 'MU2526' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011151049' WHERE sku = 'MU2548' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994397000083' WHERE sku = 'MU2549' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '6970575510027' WHERE sku = 'MU2605' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '9557286378718' WHERE sku = 'MU2606' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991011328172' WHERE sku = 'MU2620' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-1350-0030' WHERE sku = 'MU2646' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997015880203' WHERE sku = 'MU2647' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '08118422030' WHERE sku = 'MU2668' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '08118422010' WHERE sku = 'MU2669' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997015880197' WHERE sku = 'MU2676' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8993322101123' WHERE sku = 'MU2677' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8718696822845' WHERE sku = 'MU2678' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '84-6212-041' WHERE sku = 'MU2689' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '84-2041-06' WHERE sku = 'MU2690' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '3201103572' WHERE sku = 'MU2691' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '42-9045-203' WHERE sku = 'MU2698' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-1350-0045' WHERE sku = 'MU2714' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '83-3310-008' WHERE sku = 'MU2733' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '73-6831-' WHERE sku = 'MU2734' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207851660' WHERE sku = 'MU2808' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '4927870300' WHERE sku = 'MU2848' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1129-9808' WHERE sku = 'MU2849' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-3816-080' WHERE sku = 'MU2850' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381357579' WHERE sku = 'MU2870' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8998838060018' WHERE sku = 'MU2871' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU2873' WHERE sku = 'MU2873' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU2874' WHERE sku = 'MU2874' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU2875' WHERE sku = 'MU2875' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '0900003428706' WHERE sku = 'MU2906' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-1350-0050' WHERE sku = 'MU2914' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381110174' WHERE sku = 'MU2923' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-1350-0080' WHERE sku = 'MU2926' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8992875110514' WHERE sku = 'MU2927' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '15-2820N-0028' WHERE sku = 'MU2955' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '08118422020' WHERE sku = 'MU2977' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '32-1004-' WHERE sku = 'MU2982' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '81-3026-0060' WHERE sku = 'MU2983' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '81-3026-0080' WHERE sku = 'MU2984' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '81-3026-0100' WHERE sku = 'MU2985' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '6933164683427' WHERE sku = 'MU2991' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8993888110003' WHERE sku = 'MU2992' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '555234789066' WHERE sku = 'MU2993' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8718696823545' WHERE sku = 'MU2996' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8718696823521' WHERE sku = 'MU2997' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8718699614294' WHERE sku = 'MU2998' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8718696823569' WHERE sku = 'MU2999' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997015881323' WHERE sku = 'MU3016' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8998889816008' WHERE sku = 'MU3017' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8998889818002' WHERE sku = 'MU3018' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '85-2810-13' WHERE sku = 'MU3019' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '65-1750-2080' WHERE sku = 'MU3020' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '15-6850-082' WHERE sku = 'MU3021' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '24-3630-05' WHERE sku = 'MU3022' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '24-3620-05' WHERE sku = 'MU3023' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '49-1526-0150' WHERE sku = 'MU3024' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '88-4511-0030' WHERE sku = 'MU3025' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '88-4510-0040' WHERE sku = 'MU3026' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '49-4134-4' WHERE sku = 'MU3027' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '42-1003-01' WHERE sku = 'MU3028' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-3745-100' WHERE sku = 'MU3050' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '78-8212-08' WHERE sku = 'MU3051' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '76-1808-12' WHERE sku = 'MU3052' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '88-7730-7040' WHERE sku = 'MU3070' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '67-2818-' WHERE sku = 'MU3071' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207859093' WHERE sku = 'MU3074' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8999979025799' WHERE sku = 'MU3077' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8999979026611' WHERE sku = 'MU3078' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '6970863769045' WHERE sku = 'MU3156' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011120236' WHERE sku = 'MU3159' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8990011120243' WHERE sku = 'MU3160' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '43-3531-' WHERE sku = 'MU3163' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '32-4784-08' WHERE sku = 'MU3164' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-1350-0060' WHERE sku = 'MU3209' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991389248065' WHERE sku = 'MU3217' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991389247037' WHERE sku = 'MU3218' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997020192391' WHERE sku = 'MU3235' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997020190939' WHERE sku = 'MU3236' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994680017040' WHERE sku = 'MU3250' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997238770039' WHERE sku = 'MU3258' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '91-5504-0240' WHERE sku = 'MU3291' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '91-5504-0150' WHERE sku = 'MU3293' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997238770107' WHERE sku = 'MU3300' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '4800374027803' WHERE sku = 'MU3301' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '4800374027797' WHERE sku = 'MU3302' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997238770084' WHERE sku = 'MU3304' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997238770060' WHERE sku = 'MU3305' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997238770053' WHERE sku = 'MU3306' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '3606481491183' WHERE sku = 'MU3313' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU3314' WHERE sku = 'MU3314' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU3315' WHERE sku = 'MU3315' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU3316' WHERE sku = 'MU3316' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU3317' WHERE sku = 'MU3317' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU3318' WHERE sku = 'MU3318' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MU3319' WHERE sku = 'MU3319' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '86-1013-4630' WHERE sku = 'MU3324' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '86-3070-0410' WHERE sku = 'MU3325' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '86-3070-0210' WHERE sku = 'MU3326' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '86-3070-0310' WHERE sku = 'MU3327' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '78-8210-16' WHERE sku = 'MU3328' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '76-1857-890' WHERE sku = 'MU3335' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997238770657' WHERE sku = 'MU3339' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381357951' WHERE sku = 'MU3348' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381357784' WHERE sku = 'MU3349' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '088381357906' WHERE sku = 'MU3350' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '87-3328-1217' WHERE sku = 'MU3358' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '87-3328-1417' WHERE sku = 'MU3359' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '87-3328-1420' WHERE sku = 'MU3360' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1239-0810' WHERE sku = 'MU3361' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1239-1012' WHERE sku = 'MU3362' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1239-1214' WHERE sku = 'MU3363' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1239-1417' WHERE sku = 'MU3364' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1239-1719' WHERE sku = 'MU3365' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1239-1922' WHERE sku = 'MU3366' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '26-6472-0024' WHERE sku = 'MU3367' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '98-3308-0015' WHERE sku = 'MU3370' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '98-3308-0020' WHERE sku = 'MU3371' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '98-3312-0004' WHERE sku = 'MU3372' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997015880005' WHERE sku = 'MU3374' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207852964' WHERE sku = 'MU3377' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207852971' WHERE sku = 'MU3378' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207853039' WHERE sku = 'MU3379' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207853046' WHERE sku = 'MU3380' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207853053' WHERE sku = 'MU3381' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997225946706' WHERE sku = 'MU3401' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997225946690' WHERE sku = 'MU3402' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997225946683' WHERE sku = 'MU3403' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997225946676' WHERE sku = 'MU3404' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997225946669' WHERE sku = 'MU3405' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8992875140122' WHERE sku = 'MU3406' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'KUAS LUKIS 3' WHERE sku = 'MU3407' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8992748195204' WHERE sku = 'MU3413' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994175003138' WHERE sku = 'MU3416' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994175003206' WHERE sku = 'MU3417' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994175003190' WHERE sku = 'MU3418' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994175003152' WHERE sku = 'MU3419' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994175003169' WHERE sku = 'MU3420' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994175003145' WHERE sku = 'MU3421' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994175003176' WHERE sku = 'MU3422' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8994175003183' WHERE sku = 'MU3423' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8998838060032' WHERE sku = 'MU3426' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207859413' WHERE sku = 'MU3437' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '83-3160-012' WHERE sku = 'MU3446' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-3519-' WHERE sku = 'MU3447' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-3125-0810' WHERE sku = 'MU3448' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-3125-1214' WHERE sku = 'MU3449' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-3125-1617' WHERE sku = 'MU3450' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '49-3830-' WHERE sku = 'MU3451' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MB1219' WHERE sku = 'MU3454' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997015880050' WHERE sku = 'MU3455' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997015880043' WHERE sku = 'MU3456' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '88-4518-0040' WHERE sku = 'MU3457' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '84-4335-400' WHERE sku = 'MU3460' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '84-2135-010' WHERE sku = 'MU3461' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '84-2135-015' WHERE sku = 'MU3462' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '84-2135-018' WHERE sku = 'MU3463' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '84-2135-020' WHERE sku = 'MU3464' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '84-2135-022' WHERE sku = 'MU3465' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '84-2135-032' WHERE sku = 'MU3466' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '84-2135-036' WHERE sku = 'MU3467' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-3350-03' WHERE sku = 'MU3469' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '79-3729-2003' WHERE sku = 'MU3470' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '42-1802-06' WHERE sku = 'MU3471' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '42-1802-02' WHERE sku = 'MU3472' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '42-1802-03' WHERE sku = 'MU3473' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '49-2697-0075' WHERE sku = 'MU3485' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '6971701560015' WHERE sku = 'MU3496' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8991011324945' WHERE sku = 'MU3517' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '15-3436-0037' WHERE sku = 'MU3533' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '2220100840605' WHERE sku = 'MU3564' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '85-2240-3510' WHERE sku = 'MU3594' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1239-1415' WHERE sku = 'MU3600' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8995156902266' WHERE sku = 'MU3604' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8389388223549' WHERE sku = 'MU3608' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8389388223556' WHERE sku = 'MU3609' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '49-2697-0050' WHERE sku = 'MU3610' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MB1577' WHERE sku = 'MU3614' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '79-3310-05' WHERE sku = 'MU3629' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1359-0011' WHERE sku = 'MU3681' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '86-3013-0310' WHERE sku = 'MU3686' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '73-3843-' WHERE sku = 'MU3729' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '86-3013-0410' WHERE sku = 'MU3733' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '6950393442892' WHERE sku = 'MU3740' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '6950393442687' WHERE sku = 'MU3741' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '6950393442625' WHERE sku = 'MU3742' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '6950393442830' WHERE sku = 'MU3743' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '6950393442861' WHERE sku = 'MU3744' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8998865111509' WHERE sku = 'MU3745' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '1457514254440' WHERE sku = 'MU3746' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MB1761' WHERE sku = 'MU3752' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207858492' WHERE sku = 'MU3755' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207851684' WHERE sku = 'MU3756' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997016100348' WHERE sku = 'MU3758' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1239-1617' WHERE sku = 'MU3765' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '1480083446022' WHERE sku = 'MU3766' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '1480083446039' WHERE sku = 'MU3767' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '111042170128' WHERE sku = 'MU3771' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '221042170046' WHERE sku = 'MU3772' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '111042170145' WHERE sku = 'MU3776' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '111042170164' WHERE sku = 'MU3778' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '091042180096' WHERE sku = 'MU3779' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '221042170037' WHERE sku = 'MU3780' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '091042180005' WHERE sku = 'MU3781' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '67-2910-0180' WHERE sku = 'MU3785' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '9555202600196' WHERE sku = 'MU3792' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997207852674' WHERE sku = 'MU3808' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '72-1359-0016' WHERE sku = 'MU3823' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '73-3842-' WHERE sku = 'MU3851' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = 'MB1016' WHERE sku = 'MU3871' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-1350-0035' WHERE sku = 'MU3874' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '82-1350-0040' WHERE sku = 'MU3875' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '13-2821G-0017' WHERE sku = 'MU3876' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '13-2821G-0038' WHERE sku = 'MU3877' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '15-3436-0043' WHERE sku = 'MU3878' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997016100256' WHERE sku = 'MU3885' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '8997016100249' WHERE sku = 'MU3886' AND barcode IS NULL;
UPDATE IGNORE products SET barcode = '7792643165224' WHERE sku = 'MU3891' AND barcode IS NULL;

-- Barang tanpa barcode: SKU dipakai sebagai barcode (hanya SKU hasil migrasi)
UPDATE IGNORE products SET barcode = sku
WHERE barcode IS NULL AND sku IN (
  'MU-2409', 'MU0001', 'MU0002', 'MU0003', 'MU0004', 'MU0005', 'MU0006', 'MU0007',
  'MU0008', 'MU0009', 'MU0010', 'MU0011', 'MU0012', 'MU0013', 'MU0014', 'MU0015',
  'MU0016', 'MU0017', 'MU0018', 'MU0019', 'MU0020', 'MU0021', 'MU0022', 'MU0023',
  'MU0024', 'MU0025', 'MU0026', 'MU0027', 'MU0028', 'MU0029', 'MU0030', 'MU0031',
  'MU0032', 'MU0033', 'MU0034', 'MU0035', 'MU0036', 'MU0037', 'MU0038', 'MU0039',
  'MU0040', 'MU0041', 'MU0042', 'MU0043', 'MU0044', 'MU0045', 'MU0046', 'MU0047',
  'MU0048', 'MU0049', 'MU0050', 'MU0051', 'MU0052', 'MU0053', 'MU0054', 'MU0055',
  'MU0056', 'MU0057', 'MU0058', 'MU0059', 'MU0060', 'MU0061', 'MU0062', 'MU0063',
  'MU0064', 'MU0065', 'MU0066', 'MU0067', 'MU0068', 'MU0069', 'MU0070', 'MU0071',
  'MU0072', 'MU0073', 'MU0074', 'MU0075', 'MU0076', 'MU0077', 'MU0078', 'MU0079',
  'MU0080', 'MU0081', 'MU0082', 'MU0083', 'MU0084', 'MU0085', 'MU0086', 'MU0087',
  'MU0088', 'MU0089', 'MU0090', 'MU0091', 'MU0092', 'MU0093', 'MU0094', 'MU0095',
  'MU0096', 'MU0097', 'MU0098', 'MU0099', 'MU0100', 'MU0101', 'MU0102', 'MU0103',
  'MU0104', 'MU0105', 'MU0106', 'MU0107', 'MU0108', 'MU0109', 'MU0110', 'MU0111',
  'MU0112', 'MU0113', 'MU0114', 'MU0115', 'MU0116', 'MU0117', 'MU0118', 'MU0119',
  'MU0120', 'MU0121', 'MU0122', 'MU0123', 'MU0124', 'MU0125', 'MU0126', 'MU0127',
  'MU0128', 'MU0129', 'MU0130', 'MU0131', 'MU0132', 'MU0133', 'MU0134', 'MU0135',
  'MU0136', 'MU0137', 'MU0138', 'MU0139', 'MU0140', 'MU0141', 'MU0142', 'MU0143',
  'MU0144', 'MU0145', 'MU0146', 'MU0147', 'MU0148', 'MU0149', 'MU0150', 'MU0151',
  'MU0152', 'MU0153', 'MU0154', 'MU0155', 'MU0156', 'MU0157', 'MU0158', 'MU0159',
  'MU0160', 'MU0161', 'MU0162', 'MU0163', 'MU0164', 'MU0165', 'MU0166', 'MU0167',
  'MU0168', 'MU0169', 'MU0170', 'MU0171', 'MU0172', 'MU0173', 'MU0174', 'MU0175',
  'MU0176', 'MU0177', 'MU0178', 'MU0179', 'MU0180', 'MU0181', 'MU0182', 'MU0183',
  'MU0184', 'MU0185', 'MU0186', 'MU0187', 'MU0188', 'MU0189', 'MU0190', 'MU0191',
  'MU0192', 'MU0193', 'MU0194', 'MU0195', 'MU0196', 'MU0197', 'MU0198', 'MU0199',
  'MU0200', 'MU0201', 'MU0202', 'MU0203', 'MU0204', 'MU0205', 'MU0206', 'MU0207',
  'MU0208', 'MU0209', 'MU0210', 'MU0211', 'MU0212', 'MU0213', 'MU0214', 'MU0215',
  'MU0216', 'MU0217', 'MU0218', 'MU0219', 'MU0220', 'MU0221', 'MU0222', 'MU0223',
  'MU0224', 'MU0225', 'MU0226', 'MU0227', 'MU0228', 'MU0229', 'MU0230', 'MU0231',
  'MU0232', 'MU0233', 'MU0234', 'MU0235', 'MU0236', 'MU0237', 'MU0238', 'MU0239',
  'MU0240', 'MU0241', 'MU0242', 'MU0243', 'MU0244', 'MU0245', 'MU0246', 'MU0247',
  'MU0248', 'MU0249', 'MU0250', 'MU0251', 'MU0252', 'MU0253', 'MU0254', 'MU0255',
  'MU0256', 'MU0257', 'MU0258', 'MU0259', 'MU0260', 'MU0261', 'MU0262', 'MU0263',
  'MU0264', 'MU0265', 'MU0266', 'MU0267', 'MU0268', 'MU0269', 'MU0270', 'MU0271',
  'MU0272', 'MU0273', 'MU0274', 'MU0275', 'MU0276', 'MU0277', 'MU0278', 'MU0279',
  'MU0280', 'MU0281', 'MU0282', 'MU0283', 'MU0284', 'MU0285', 'MU0286', 'MU0287',
  'MU0288', 'MU0289', 'MU0290', 'MU0291', 'MU0292', 'MU0293', 'MU0294', 'MU0295',
  'MU0296', 'MU0297', 'MU0298', 'MU0299', 'MU0300', 'MU0301', 'MU0302', 'MU0303',
  'MU0304', 'MU0305', 'MU0306', 'MU0307', 'MU0308', 'MU0309', 'MU0310', 'MU0311',
  'MU0312', 'MU0313', 'MU0314', 'MU0315', 'MU0316', 'MU0317', 'MU0318', 'MU0319',
  'MU0320', 'MU0321', 'MU0322', 'MU0323', 'MU0324', 'MU0325', 'MU0326', 'MU0327',
  'MU0328', 'MU0329', 'MU0330', 'MU0331', 'MU0332', 'MU0333', 'MU0334', 'MU0335',
  'MU0336', 'MU0337', 'MU0338', 'MU0339', 'MU0340', 'MU0341', 'MU0342', 'MU0343',
  'MU0344', 'MU0345', 'MU0346', 'MU0347', 'MU0348', 'MU0349', 'MU0350', 'MU0351',
  'MU0352', 'MU0353', 'MU0354', 'MU0355', 'MU0356', 'MU0357', 'MU0358', 'MU0359',
  'MU0360', 'MU0361', 'MU0362', 'MU0363', 'MU0364', 'MU0365', 'MU0366', 'MU0367',
  'MU0368', 'MU0369', 'MU0370', 'MU0371', 'MU0372', 'MU0373', 'MU0374', 'MU0375',
  'MU0376', 'MU0377', 'MU0378', 'MU0379', 'MU0380', 'MU0381', 'MU0382', 'MU0383',
  'MU0384', 'MU0385', 'MU0386', 'MU0387', 'MU0388', 'MU0389', 'MU0390', 'MU0391',
  'MU0392', 'MU0393', 'MU0394', 'MU0395', 'MU0396', 'MU0397', 'MU0398', 'MU0399',
  'MU0400', 'MU0401', 'MU0402', 'MU0403', 'MU0404', 'MU0405', 'MU0406', 'MU0407',
  'MU0408', 'MU0409', 'MU0410', 'MU0411', 'MU0412', 'MU0414', 'MU0415', 'MU0416',
  'MU0417', 'MU0418', 'MU0419', 'MU0420', 'MU0421', 'MU0422', 'MU0423', 'MU0424',
  'MU0425', 'MU0426', 'MU0427', 'MU0428', 'MU0429', 'MU0430', 'MU0431', 'MU0432',
  'MU0433', 'MU0434', 'MU0435', 'MU0436', 'MU0437', 'MU0438', 'MU0439', 'MU0440',
  'MU0441', 'MU0442', 'MU0443', 'MU0444', 'MU0445', 'MU0446', 'MU0447', 'MU0448',
  'MU0449', 'MU0450', 'MU0451', 'MU0452', 'MU0453', 'MU0454', 'MU0455', 'MU0456',
  'MU0457', 'MU0458', 'MU0459', 'MU0460', 'MU0461', 'MU0462', 'MU0463', 'MU0464',
  'MU0465', 'MU0466', 'MU0467', 'MU0468', 'MU0469', 'MU0470', 'MU0471', 'MU0472',
  'MU0473', 'MU0474', 'MU0475', 'MU0476', 'MU0477', 'MU0478', 'MU0479', 'MU0480',
  'MU0481', 'MU0482', 'MU0483', 'MU0484', 'MU0485', 'MU0486', 'MU0487', 'MU0488',
  'MU0489', 'MU0490', 'MU0491', 'MU0492', 'MU0493', 'MU0494', 'MU0495', 'MU0496',
  'MU0497', 'MU0498', 'MU0499', 'MU0500', 'MU0501', 'MU0502', 'MU0503', 'MU0504',
  'MU0505', 'MU0506', 'MU0507', 'MU0508', 'MU0509', 'MU0510', 'MU0511', 'MU0512',
  'MU0513', 'MU0514', 'MU0515', 'MU0516', 'MU0517', 'MU0518', 'MU0519', 'MU0520',
  'MU0521', 'MU0522', 'MU0523', 'MU0524', 'MU0525', 'MU0526', 'MU0527', 'MU0528',
  'MU0529', 'MU0530', 'MU0531', 'MU0532', 'MU0533', 'MU0534', 'MU0535', 'MU0536',
  'MU0537', 'MU0538', 'MU0539', 'MU0540', 'MU0541', 'MU0542', 'MU0543', 'MU0544',
  'MU0545', 'MU0546', 'MU0547', 'MU0548', 'MU0549', 'MU0550', 'MU0551', 'MU0552',
  'MU0553', 'MU0554', 'MU0555', 'MU0556', 'MU0557', 'MU0558', 'MU0559', 'MU0560',
  'MU0561', 'MU0562', 'MU0563', 'MU0564', 'MU0565', 'MU0566', 'MU0567', 'MU0568',
  'MU0569', 'MU0570', 'MU0571', 'MU0572', 'MU0573', 'MU0574', 'MU0575', 'MU0576',
  'MU0577', 'MU0578', 'MU0579', 'MU0580', 'MU0581', 'MU0582', 'MU0583', 'MU0584',
  'MU0585', 'MU0586', 'MU0587', 'MU0588', 'MU0589', 'MU0590', 'MU0591', 'MU0592',
  'MU0593', 'MU0594', 'MU0595', 'MU0596', 'MU0597', 'MU0598', 'MU0599', 'MU0600',
  'MU0601', 'MU0602', 'MU0603', 'MU0604', 'MU0605', 'MU0606', 'MU0607', 'MU0608',
  'MU0609', 'MU0610', 'MU0611', 'MU0612', 'MU0613', 'MU0614', 'MU0615', 'MU0616',
  'MU0617', 'MU0618', 'MU0619', 'MU0620', 'MU0621', 'MU0622', 'MU0623', 'MU0624',
  'MU0625', 'MU0626', 'MU0627', 'MU0628', 'MU0629', 'MU0630', 'MU0631', 'MU0632',
  'MU0633', 'MU0634', 'MU0635', 'MU0636', 'MU0637', 'MU0638', 'MU0639', 'MU0640',
  'MU0641', 'MU0642', 'MU0643', 'MU0644', 'MU0645', 'MU0646', 'MU0647', 'MU0648',
  'MU0649', 'MU0650', 'MU0651', 'MU0652', 'MU0653', 'MU0654', 'MU0655', 'MU0656',
  'MU0657', 'MU0658', 'MU0659', 'MU0660', 'MU0661', 'MU0662', 'MU0663', 'MU0664',
  'MU0665', 'MU0666', 'MU0667', 'MU0668', 'MU0669', 'MU0670', 'MU0671', 'MU0672',
  'MU0673', 'MU0674', 'MU0675', 'MU0676', 'MU0682', 'MU0683', 'MU0684', 'MU0685',
  'MU0686', 'MU0687', 'MU0688', 'MU0689', 'MU0690', 'MU0691', 'MU0692', 'MU0693',
  'MU0694', 'MU0695', 'MU0696', 'MU0697', 'MU0698', 'MU0699', 'MU0700', 'MU0701',
  'MU0702', 'MU0703', 'MU0704', 'MU0705', 'MU0706', 'MU0707', 'MU0708', 'MU0709',
  'MU0710', 'MU0711', 'MU0712', 'MU0713', 'MU0714', 'MU0715', 'MU0716', 'MU0717',
  'MU0718', 'MU0719', 'MU0720', 'MU0721', 'MU0722', 'MU0723', 'MU0724', 'MU0725',
  'MU0726', 'MU0727', 'MU0728', 'MU0729', 'MU0730', 'MU0731', 'MU0732', 'MU0733',
  'MU0734', 'MU0735', 'MU0736', 'MU0737', 'MU0738', 'MU0739', 'MU0740', 'MU0741',
  'MU0742', 'MU0743', 'MU0744', 'MU0745', 'MU0746', 'MU0747', 'MU0748', 'MU0749',
  'MU0750', 'MU0751', 'MU0752', 'MU0753', 'MU0754', 'MU0755', 'MU0756', 'MU0757',
  'MU0758', 'MU0759', 'MU0760', 'MU0761', 'MU0762', 'MU0763', 'MU0764', 'MU0765',
  'MU0766', 'MU0767', 'MU0768', 'MU0769', 'MU0770', 'MU0771', 'MU0772', 'MU0773',
  'MU0774', 'MU0775', 'MU0776', 'MU0777', 'MU0778', 'MU0779', 'MU0780', 'MU0781',
  'MU0782', 'MU0783', 'MU0784', 'MU0785', 'MU0786', 'MU0787', 'MU0788', 'MU0789',
  'MU0790', 'MU0791', 'MU0792', 'MU0793', 'MU0794', 'MU0795', 'MU0796', 'MU0797',
  'MU0798', 'MU0799', 'MU0800', 'MU0801', 'MU0802', 'MU0803', 'MU0804', 'MU0805',
  'MU0806', 'MU0807', 'MU0808', 'MU0809', 'MU0810', 'MU0811', 'MU0812', 'MU0813',
  'MU0814', 'MU0815', 'MU0816', 'MU0817', 'MU0818', 'MU0819', 'MU0820', 'MU0821',
  'MU0822', 'MU0823', 'MU0824', 'MU0825', 'MU0826', 'MU0827', 'MU0828', 'MU0829',
  'MU0830', 'MU0831', 'MU0832', 'MU0833', 'MU0834', 'MU0835', 'MU0836', 'MU0837',
  'MU0838', 'MU0839', 'MU0840', 'MU0841', 'MU0842', 'MU0843', 'MU0844', 'MU0845',
  'MU0846', 'MU0847', 'MU0848', 'MU0849', 'MU0850', 'MU0851', 'MU0852', 'MU0853',
  'MU0854', 'MU0855', 'MU0856', 'MU0857', 'MU0858', 'MU0859', 'MU0860', 'MU0861',
  'MU0862', 'MU0863', 'MU0864', 'MU0865', 'MU0866', 'MU0867', 'MU0868', 'MU0869',
  'MU0870', 'MU0871', 'MU0872', 'MU0873', 'MU0874', 'MU0875', 'MU0876', 'MU0877',
  'MU0878', 'MU0879', 'MU0880', 'MU0881', 'MU0882', 'MU0883', 'MU0884', 'MU0885',
  'MU0886', 'MU0887', 'MU0888', 'MU0889', 'MU0890', 'MU0891', 'MU0892', 'MU0893',
  'MU0894', 'MU0895', 'MU0896', 'MU0897', 'MU0898', 'MU0899', 'MU0900', 'MU0901',
  'MU0902', 'MU0903', 'MU0904', 'MU0905', 'MU0906', 'MU0907', 'MU0908', 'MU0909',
  'MU0910', 'MU0911', 'MU0912', 'MU0913', 'MU0914', 'MU0915', 'MU0916', 'MU0917',
  'MU0918', 'MU0919', 'MU0920', 'MU0921', 'MU0922', 'MU0923', 'MU0924', 'MU0925',
  'MU0926', 'MU0927', 'MU0928', 'MU0929', 'MU0930', 'MU0931', 'MU0932', 'MU0933',
  'MU0934', 'MU0935', 'MU0936', 'MU0937', 'MU0938', 'MU0939', 'MU0940', 'MU0941',
  'MU0942', 'MU0943', 'MU0944', 'MU0945', 'MU0946', 'MU0947', 'MU0948', 'MU0949',
  'MU0950', 'MU0951', 'MU0952', 'MU0953', 'MU0954', 'MU0955', 'MU0956', 'MU0957',
  'MU0958', 'MU0959', 'MU0960', 'MU0961', 'MU0962', 'MU0963', 'MU0964', 'MU0965',
  'MU0966', 'MU0967', 'MU0968', 'MU0969', 'MU0970', 'MU0971', 'MU0972', 'MU0973',
  'MU0974', 'MU0975', 'MU0976', 'MU0977', 'MU0978', 'MU0979', 'MU0980', 'MU0981',
  'MU0982', 'MU0983', 'MU0984', 'MU0985', 'MU0986', 'MU0987', 'MU0988', 'MU0989',
  'MU0990', 'MU0991', 'MU0992', 'MU0993', 'MU0994', 'MU0995', 'MU0996', 'MU0997',
  'MU0998', 'MU0999', 'MU1000', 'MU1001', 'MU1002', 'MU1003', 'MU1004', 'MU1005',
  'MU1006', 'MU1007', 'MU1008', 'MU1009', 'MU1010', 'MU1011', 'MU1012', 'MU1013',
  'MU1014', 'MU1015', 'MU1016', 'MU1017', 'MU1018', 'MU1019', 'MU1020', 'MU1021',
  'MU1022', 'MU1023', 'MU1024', 'MU1025', 'MU1026', 'MU1027', 'MU1028', 'MU1029',
  'MU1030', 'MU1031', 'MU1032', 'MU1033', 'MU1034', 'MU1035', 'MU1036', 'MU1037',
  'MU1038', 'MU1039', 'MU1040', 'MU1041', 'MU1042', 'MU1043', 'MU1044', 'MU1045',
  'MU1046', 'MU1047', 'MU1048', 'MU1049', 'MU1050', 'MU1051', 'MU1052', 'MU1053',
  'MU1054', 'MU1055', 'MU1056', 'MU1057', 'MU1058', 'MU1059', 'MU1060', 'MU1061',
  'MU1062', 'MU1063', 'MU1064', 'MU1065', 'MU1066', 'MU1067', 'MU1068', 'MU1069',
  'MU1070', 'MU1071', 'MU1072', 'MU1073', 'MU1074', 'MU1075', 'MU1076', 'MU1077',
  'MU1078', 'MU1079', 'MU1080', 'MU1081', 'MU1082', 'MU1083', 'MU1084', 'MU1085',
  'MU1086', 'MU1087', 'MU1088', 'MU1089', 'MU1090', 'MU1091', 'MU1092', 'MU1093',
  'MU1094', 'MU1095', 'MU1096', 'MU1097', 'MU1098', 'MU1099', 'MU1100', 'MU1101',
  'MU1102', 'MU1103', 'MU1104', 'MU1105', 'MU1106', 'MU1107', 'MU1108', 'MU1109',
  'MU1110', 'MU1111', 'MU1112', 'MU1113', 'MU1114', 'MU1115', 'MU1116', 'MU1117',
  'MU1118', 'MU1119', 'MU1120', 'MU1121', 'MU1122', 'MU1123', 'MU1124', 'MU1125',
  'MU1126', 'MU1127', 'MU1128', 'MU1129', 'MU1130', 'MU1131', 'MU1132', 'MU1133',
  'MU1134', 'MU1135', 'MU1136', 'MU1137', 'MU1138', 'MU1139', 'MU1140', 'MU1141',
  'MU1142', 'MU1143', 'MU1144', 'MU1145', 'MU1146', 'MU1147', 'MU1148', 'MU1149',
  'MU1150', 'MU1151', 'MU1152', 'MU1153', 'MU1154', 'MU1155', 'MU1156', 'MU1157',
  'MU1158', 'MU1159', 'MU116', 'MU1160', 'MU1161', 'MU1162', 'MU1163', 'MU1164',
  'MU1165', 'MU1166', 'MU1167', 'MU1168', 'MU1169', 'MU1170', 'MU1171', 'MU1172',
  'MU1173', 'MU1174', 'MU1176', 'MU1177', 'MU1178', 'MU1179', 'MU1180', 'MU1182',
  'MU1183', 'MU1184', 'MU1185', 'MU1186', 'MU1187', 'MU1188', 'MU1189', 'MU1190',
  'MU1191', 'MU1192', 'MU1193', 'MU1194', 'MU1195', 'MU1196', 'MU1197', 'MU1198',
  'MU1199', 'MU1200', 'MU1201', 'MU1202', 'MU1203', 'MU1204', 'MU1205', 'MU1206',
  'MU1207', 'MU1208', 'MU1209', 'MU1210', 'MU1211', 'MU1212', 'MU1213', 'MU1214',
  'MU1215', 'MU1216', 'MU1217', 'MU1218', 'MU1219', 'MU1220', 'MU1221', 'MU1222',
  'MU1223', 'MU1224', 'MU1225', 'MU1226', 'MU1227', 'MU1228', 'MU1229', 'MU1230',
  'MU1231', 'MU1232', 'MU1233', 'MU1234', 'MU1235', 'MU1236', 'MU1237', 'MU1238',
  'MU1239', 'MU1240', 'MU1241', 'MU1242', 'MU1243', 'MU1244', 'MU1245', 'MU1246',
  'MU1247', 'MU1248', 'MU1249', 'MU1250', 'MU1251', 'MU1252', 'MU1253', 'MU1254',
  'MU1255', 'MU1256', 'MU1257', 'MU1258', 'MU1259', 'MU1260', 'MU1261', 'MU1262',
  'MU1263', 'MU1264', 'MU1265', 'MU1266', 'MU1267', 'MU1268', 'MU1269', 'MU1270',
  'MU1271', 'MU1272', 'MU1273', 'MU1274', 'MU1275', 'MU1276', 'MU1277', 'MU1278',
  'MU1279', 'MU1280', 'MU1281', 'MU1282', 'MU1283', 'MU1284', 'MU1285', 'MU1286',
  'MU1287', 'MU1288', 'MU1289', 'MU1290', 'MU1291', 'MU1292', 'MU1293', 'MU1294',
  'MU1295', 'MU1296', 'MU1297', 'MU1298', 'MU1299', 'MU1300', 'MU1301', 'MU1302',
  'MU1303', 'MU1304', 'MU1305', 'MU1306', 'MU1307', 'MU1308', 'MU1309', 'MU1310',
  'MU1311', 'MU1312', 'MU1313', 'MU1314', 'MU1315', 'MU1316', 'MU1317', 'MU1318',
  'MU1319', 'MU1320', 'MU1321', 'MU1322', 'MU1323', 'MU1324', 'MU1325', 'MU1326',
  'MU1327', 'MU1328', 'MU1329', 'MU1330', 'MU1331', 'MU1332', 'MU1333', 'MU1334',
  'MU1335', 'MU1336', 'MU1337', 'MU1338', 'MU1339', 'MU1340', 'MU1341', 'MU1342',
  'MU1343', 'MU1344', 'MU1345', 'MU1346', 'MU1347', 'MU1348', 'MU1349', 'MU1350',
  'MU1351', 'MU1352', 'MU1353', 'MU1354', 'MU1355', 'MU1356', 'MU1357', 'MU1358',
  'MU1359', 'MU1360', 'MU1361', 'MU1362', 'MU1363', 'MU1364', 'MU1365', 'MU1366',
  'MU1367', 'MU1368', 'MU1369', 'MU1370', 'MU1371', 'MU1372', 'MU1373', 'MU1374',
  'MU1375', 'MU1376', 'MU1377', 'MU1378', 'MU1379', 'MU1380', 'MU1381', 'MU1382',
  'MU1383', 'MU1384', 'MU1385', 'MU1386', 'MU1388', 'MU1389', 'MU1390', 'MU1391',
  'MU1392', 'MU1393', 'MU1394', 'MU1395', 'MU1396', 'MU1397', 'MU1398', 'MU1399',
  'MU1400', 'MU1401', 'MU1402', 'MU1403', 'MU1404', 'MU1405', 'MU1406', 'MU1407',
  'MU1408', 'MU1409', 'MU1410', 'MU1411', 'MU1412', 'MU1413', 'MU1414', 'MU1415',
  'MU1416', 'MU1417', 'MU1418', 'MU1419', 'MU1420', 'MU1421', 'MU1422', 'MU1423',
  'MU1424', 'MU1425', 'MU1426', 'MU1427', 'MU1428', 'MU1429', 'MU1430', 'MU1431',
  'MU1432', 'MU1433', 'MU1434', 'MU1435', 'MU1436', 'MU1437', 'MU1438', 'MU1439',
  'MU1440', 'MU1441', 'MU1442', 'MU1443', 'MU1444', 'MU1445', 'MU1446', 'MU1447',
  'MU1448', 'MU1449', 'MU1450', 'MU1451', 'MU1452', 'MU1453', 'MU1454', 'MU1455',
  'MU1456', 'MU1457', 'MU1458', 'MU1459', 'MU1460', 'MU1461', 'MU1462', 'MU1463',
  'MU1464', 'MU1465', 'MU1466', 'MU1467', 'MU1468', 'MU1469', 'MU1470', 'MU1471',
  'MU1472', 'MU1473', 'MU1474', 'MU1475', 'MU1476', 'MU1477', 'MU1478', 'MU1479',
  'MU1480', 'MU1481', 'MU1482', 'MU1483', 'MU1484', 'MU1485', 'MU1486', 'MU1487',
  'MU1488', 'MU1489', 'MU1490', 'MU1491', 'MU1492', 'MU1493', 'MU1494', 'MU1495',
  'MU1496', 'MU1497', 'MU1498', 'MU1499', 'MU1500', 'MU1501', 'MU1502', 'MU1503',
  'MU1504', 'MU1505', 'MU1506', 'MU1507', 'MU1508', 'MU1509', 'MU1510', 'MU1511',
  'MU1512', 'MU1513', 'MU1514', 'MU1515', 'MU1516', 'MU1517', 'MU1518', 'MU1519',
  'MU1520', 'MU1521', 'MU1522', 'MU1523', 'MU1524', 'MU1525', 'MU1526', 'MU1527',
  'MU1528', 'MU1529', 'MU1530', 'MU1532', 'MU1533', 'MU1534', 'MU1535', 'MU1536',
  'MU1537', 'MU1538', 'MU1539', 'MU1540', 'MU1541', 'MU1542', 'MU1543', 'MU1544',
  'MU1545', 'MU1546', 'MU1547', 'MU1548', 'MU1549', 'MU1550', 'MU1551', 'MU1552',
  'MU1553', 'MU1554', 'MU1555', 'MU1556', 'MU1557', 'MU1558', 'MU1559', 'MU1560',
  'MU1561', 'MU1562', 'MU1563', 'MU1564', 'MU1565', 'MU1566', 'MU1567', 'MU1568',
  'MU1569', 'MU1570', 'MU1571', 'MU1572', 'MU1573', 'MU1574', 'MU1575', 'MU1576',
  'MU1577', 'MU1578', 'MU1579', 'MU1580', 'MU1581', 'MU1582', 'MU1583', 'MU1585',
  'MU1586', 'MU1587', 'MU1588', 'MU1589', 'MU1590', 'MU1591', 'MU1592', 'MU1593',
  'MU1594', 'MU1595', 'MU1596', 'MU1597', 'MU1598', 'MU1599', 'MU1600', 'MU1601',
  'MU1602', 'MU1603', 'MU1604', 'MU1605', 'MU1606', 'MU1607', 'MU1608', 'MU1609',
  'MU1610', 'MU1611', 'MU1612', 'MU1613', 'MU1614', 'MU1615', 'MU1616', 'MU1617',
  'MU1618', 'MU1619', 'MU1620', 'MU1621', 'MU1622', 'MU1623', 'MU1624', 'MU1625',
  'MU1626', 'MU1627', 'MU1628', 'MU1629', 'MU1630', 'MU1631', 'MU1632', 'MU1633',
  'MU1634', 'MU1635', 'MU1636', 'MU1637', 'MU1638', 'MU1639', 'MU1640', 'MU1641',
  'MU1642', 'MU1643', 'MU1644', 'MU1645', 'MU1646', 'MU1647', 'MU1648', 'MU1649',
  'MU1650', 'MU1651', 'MU1652', 'MU1653', 'MU1654', 'MU1655', 'MU1656', 'MU1657',
  'MU1658', 'MU1659', 'MU1660', 'MU1661', 'MU1662', 'MU1663', 'MU1664', 'MU1665',
  'MU1666', 'MU1667', 'MU1668', 'MU1669', 'MU1670', 'MU1671', 'MU1672', 'MU1673',
  'MU1674', 'MU1675', 'MU1676', 'MU1677', 'MU1678', 'MU1679', 'MU1680', 'MU1681',
  'MU1682', 'MU1683', 'MU1684', 'MU1685', 'MU1686', 'MU1687', 'MU1688', 'MU1689',
  'MU1690', 'MU1691', 'MU1692', 'MU1693', 'MU1694', 'MU1695', 'MU1696', 'MU1697',
  'MU1698', 'MU1699', 'MU1700', 'MU1701', 'MU1702', 'MU1703', 'MU1711', 'MU1712',
  'MU1713', 'MU1714', 'MU1715', 'MU1716', 'MU1717', 'MU1718', 'MU1719', 'MU1720',
  'MU1721', 'MU1722', 'MU1723', 'MU1724', 'MU1725', 'MU1726', 'MU1727', 'MU1728',
  'MU1729', 'MU1730', 'MU1731', 'MU1732', 'MU1733', 'MU1734', 'MU1735', 'MU1736',
  'MU1737', 'MU1738', 'MU1739', 'MU1740', 'MU1741', 'MU1742', 'MU1743', 'MU1744',
  'MU1745', 'MU1746', 'MU1747', 'MU1748', 'MU1749', 'MU1750', 'MU1751', 'MU1752',
  'MU1753', 'MU1754', 'MU1755', 'MU1756', 'MU1757', 'MU1758', 'MU1759', 'MU1760',
  'MU1761', 'MU1762', 'MU1763', 'MU1764', 'MU1765', 'MU1766', 'MU1767', 'MU1768',
  'MU1769', 'MU1770', 'MU1771', 'MU1772', 'MU1773', 'MU1774', 'MU1775', 'MU1776',
  'MU1777', 'MU1778', 'MU1779', 'MU1780', 'MU1781', 'MU1782', 'MU1783', 'MU1784',
  'MU1785', 'MU1786', 'MU1787', 'MU1789', 'MU1790', 'MU1791', 'MU1792', 'MU1793',
  'MU1794', 'MU1795', 'MU1796', 'MU1797', 'MU1798', 'MU1799', 'MU1800', 'MU1801',
  'MU1802', 'MU1803', 'MU1804', 'MU1805', 'MU1806', 'MU1807', 'MU1808', 'MU1809',
  'MU1810', 'MU1811', 'MU1812', 'MU1813', 'MU1814', 'MU1815', 'MU1816', 'MU1817',
  'MU1818', 'MU1819', 'MU1820', 'MU1821', 'MU1822', 'MU1823', 'MU1824', 'MU1825',
  'MU1826', 'MU1827', 'MU1828', 'MU1831', 'MU1832', 'MU1833', 'MU1834', 'MU1835',
  'MU1836', 'MU1837', 'MU1838', 'MU1839', 'MU1840', 'MU1842', 'MU1843', 'MU1844',
  'MU1845', 'MU1846', 'MU1847', 'MU1848', 'MU1849', 'MU1850', 'MU1851', 'MU1852',
  'MU1853', 'MU1854', 'MU1855', 'MU1856', 'MU1857', 'MU1858', 'MU1859', 'MU1860',
  'MU1861', 'MU1862', 'MU1863', 'MU1864', 'MU1865', 'MU1866', 'MU1867', 'MU1869',
  'MU1870', 'MU1871', 'MU1872', 'MU1873', 'MU1874', 'MU1875', 'MU1876', 'MU1877',
  'MU1878', 'MU1879', 'MU1880', 'MU1881', 'MU1882', 'MU1884', 'MU1888', 'MU1889',
  'MU1890', 'MU1891', 'MU1892', 'MU1893', 'MU1894', 'MU1895', 'MU1896', 'MU1897',
  'MU1898', 'MU1899', 'MU1900', 'MU1901', 'MU1902', 'MU1903', 'MU1904', 'MU1905',
  'MU1906', 'MU1907', 'MU1908', 'MU1909', 'MU1910', 'MU1911', 'MU1912', 'MU1913',
  'MU1914', 'MU1915', 'MU1916', 'MU1917', 'MU1918', 'MU1919', 'MU1920', 'MU1921',
  'MU1922', 'MU1923', 'MU1924', 'MU1925', 'MU1926', 'MU1927', 'MU1928', 'MU1929',
  'MU1930', 'MU1931', 'MU1932', 'MU1933', 'MU1934', 'MU1935', 'MU1936', 'MU1937',
  'MU1938', 'MU1939', 'MU1940', 'MU1941', 'MU1942', 'MU1943', 'MU1944', 'MU1945',
  'MU1946', 'MU1947', 'MU1948', 'MU1949', 'MU1950', 'MU1951', 'MU1952', 'MU1953',
  'MU1954', 'MU1955', 'MU1956', 'MU1957', 'MU1958', 'MU1959', 'MU1960', 'MU1961',
  'MU1962', 'MU1963', 'MU1964', 'MU1965', 'MU1966', 'MU1967', 'MU1968', 'MU1969',
  'MU1970', 'MU1971', 'MU1972', 'MU1973', 'MU1974', 'MU1975', 'MU1976', 'MU1977',
  'MU1978', 'MU1979', 'MU1980', 'MU1981', 'MU1982', 'MU1983', 'MU1984', 'MU1985',
  'MU1986', 'MU1987', 'MU1988', 'MU1989', 'MU1990', 'MU1991', 'MU1992', 'MU1993',
  'MU1994', 'MU1995', 'MU1996', 'MU1997', 'MU1998', 'MU1999', 'MU2000', 'MU2001',
  'MU2002', 'MU2003', 'MU2004', 'MU2005', 'MU2006', 'MU2007', 'MU2008', 'MU2009',
  'MU2010', 'MU2011', 'MU2012', 'MU2013', 'MU2014', 'MU2015', 'MU2016', 'MU2017',
  'MU2018', 'MU2019', 'MU2020', 'MU2021', 'MU2022', 'MU2023', 'MU2024', 'MU2025',
  'MU2026', 'MU2027', 'MU2028', 'MU2029', 'MU2030', 'MU2031', 'MU2032', 'MU2033',
  'MU2034', 'MU2035', 'MU2036', 'MU2037', 'MU2038', 'MU2039', 'MU2040', 'MU2041',
  'MU2042', 'MU2043', 'MU2044', 'MU2045', 'MU2046', 'MU2047', 'MU2048', 'MU2049',
  'MU2050', 'MU2051', 'MU2052', 'MU2053', 'MU2054', 'MU2055', 'MU2056', 'MU2057',
  'MU2058', 'MU2059', 'MU2060', 'MU2061', 'MU2062', 'MU2063', 'MU2064', 'MU2065',
  'MU2067', 'MU2068', 'MU2069', 'MU2070', 'MU2071', 'MU2072', 'MU2073', 'MU2074',
  'MU2075', 'MU2076', 'MU2077', 'MU2078', 'MU2079', 'MU2080', 'MU2081', 'MU2082',
  'MU2083', 'MU2084', 'MU2085', 'MU2086', 'MU2087', 'MU2088', 'MU2089', 'MU2090',
  'MU2091', 'MU2092', 'MU2093', 'MU2094', 'MU2095', 'MU2096', 'MU2097', 'MU2098',
  'MU2099', 'MU2100', 'MU2101', 'MU2102', 'MU2103', 'MU2104', 'MU2105', 'MU2106',
  'MU2107', 'MU2108', 'MU2109', 'MU2110', 'MU2111', 'MU2112', 'MU2113', 'MU2114',
  'MU2115', 'MU2116', 'MU2117', 'MU2118', 'MU2119', 'MU2120', 'MU21200', 'MU2121',
  'MU2122', 'MU2123', 'MU2124', 'MU2125', 'MU2126', 'MU2127', 'MU2128', 'MU2129',
  'MU2130', 'MU2131', 'MU2132', 'MU2133', 'MU2134', 'MU2135', 'MU2136', 'MU2137',
  'MU2138', 'MU2139', 'MU2140', 'MU2141', 'MU2142', 'MU2143', 'MU2144', 'MU2145',
  'MU2146', 'MU2147', 'MU2148', 'MU2149', 'MU2150', 'MU2151', 'MU2152', 'MU2153',
  'MU2154', 'MU2155', 'MU2156', 'MU2157', 'MU2158', 'MU2159', 'MU2160', 'MU2161',
  'MU2162', 'MU2163', 'MU2164', 'MU2165', 'MU2166', 'MU2167', 'MU2168', 'MU2169',
  'MU2170', 'MU2171', 'MU2172', 'MU2173', 'MU2174', 'MU2175', 'MU2176', 'MU2177',
  'MU2178', 'MU2179', 'MU2180', 'MU2181', 'MU2182', 'MU2183', 'MU2184', 'MU2185',
  'MU2186', 'MU2187', 'MU2188', 'MU2189', 'MU2190', 'MU2191', 'MU2192', 'MU2193',
  'MU2194', 'MU2195', 'MU2196', 'MU2197', 'MU2198', 'MU2199', 'MU2200', 'MU2201',
  'MU2202', 'MU2203', 'MU2204', 'MU2205', 'MU2206', 'MU2207', 'MU2208', 'MU2209',
  'MU2210', 'MU2211', 'MU2212', 'MU2213', 'MU2214', 'MU2215', 'MU2216', 'MU2217',
  'MU2218', 'MU2219', 'MU2220', 'MU2221', 'MU2222', 'MU2223', 'MU2224', 'MU2225',
  'MU2226', 'MU2227', 'MU2228', 'MU2229', 'MU2230', 'MU2231', 'MU2232', 'MU2233',
  'MU2234', 'MU2235', 'MU2236', 'MU2237', 'MU2238', 'MU2239', 'MU2240', 'MU2241',
  'MU2242', 'MU2243', 'MU2244', 'MU2246', 'MU2247', 'MU2248', 'MU2249', 'MU2250',
  'MU2251', 'MU2252', 'MU2253', 'MU2254', 'MU2255', 'MU2256', 'MU2257', 'MU2258',
  'MU2259', 'MU2260', 'MU2261', 'MU2262', 'MU2263', 'MU2264', 'MU2265', 'MU2266',
  'MU2267', 'MU2268', 'MU2269', 'MU2270', 'MU2271', 'MU2272', 'MU2273', 'MU2274',
  'MU2275', 'MU2276', 'MU2277', 'MU2278', 'MU2279', 'MU2280', 'MU2281', 'MU2282',
  'MU2283', 'MU2284', 'MU2285', 'MU2286', 'MU2287', 'MU2288', 'MU2289', 'MU2290',
  'MU2291', 'MU2292', 'MU2293', 'MU2294', 'MU2295', 'MU2296', 'MU2297', 'MU2298',
  'MU2299', 'MU2300', 'MU2301', 'MU2302', 'MU2303', 'MU2304', 'MU2305', 'MU2306',
  'MU2307', 'MU2308', 'MU2309', 'MU2310', 'MU2311', 'MU2312', 'MU2313', 'MU2314',
  'MU2315', 'MU2316', 'MU2317', 'MU2318', 'MU2319', 'MU2320', 'MU2321', 'MU2322',
  'MU2323', 'MU2324', 'MU2325', 'MU2326', 'MU2327', 'MU2328', 'MU2329', 'MU2330',
  'MU2331', 'MU2332', 'MU2334', 'MU2335', 'MU2336', 'MU2337', 'MU2338', 'MU2339',
  'MU2340', 'MU2341', 'MU2342', 'MU2343', 'MU2344', 'MU2345', 'MU2346', 'MU2347',
  'MU2348', 'MU2349', 'MU2350', 'MU2351', 'MU2352', 'MU2353', 'MU2354', 'MU2355',
  'MU2356', 'MU2357', 'MU2358', 'MU2359', 'MU2360', 'MU2361', 'MU2362', 'MU2363',
  'MU2364', 'MU2365', 'MU2366', 'MU2367', 'MU2368', 'MU2369', 'MU2370', 'MU2371',
  'MU2372', 'MU2373', 'MU2374', 'MU2375', 'MU2376', 'MU2377', 'MU2378', 'MU2379',
  'MU2380', 'MU2381', 'MU2382', 'MU2383', 'MU2384', 'MU2385', 'MU2386', 'MU2387',
  'MU2388', 'MU2389', 'MU2390', 'MU2391', 'MU2392', 'MU2393', 'MU2394', 'MU2395',
  'MU2396', 'MU2397', 'MU2398', 'MU2399', 'MU2400', 'MU2401', 'MU2402', 'MU2403',
  'MU2404', 'MU2405', 'MU2406', 'MU2407', 'MU2408', 'MU2409', 'MU2410', 'MU2411',
  'MU2412', 'MU2413', 'MU2414', 'MU2415', 'MU2416', 'MU2417', 'MU2418', 'MU2419',
  'MU2420', 'MU2421', 'MU2422', 'MU2423', 'MU2424', 'MU2425', 'MU2426', 'MU2427',
  'MU2428', 'MU2429', 'MU2430', 'MU2431', 'MU2432', 'MU2433', 'MU2434', 'MU2435',
  'MU2436', 'MU2437', 'MU2438', 'MU2439', 'MU2440', 'MU2441', 'MU2442', 'MU2443',
  'MU2444', 'MU2445', 'MU2446', 'MU2447', 'MU2448', 'MU2449', 'MU2450', 'MU2451',
  'MU2452', 'MU2453', 'MU2454', 'MU2455', 'MU2456', 'MU2457', 'MU2458', 'MU2459',
  'MU2460', 'MU2461', 'MU2462', 'MU2463', 'MU2464', 'MU2465', 'MU2466', 'MU2467',
  'MU2468', 'MU2469', 'MU2470', 'MU2471', 'MU2472', 'MU2473', 'MU2474', 'MU2475',
  'MU2476', 'MU2477', 'MU2478', 'MU2479', 'MU2480', 'MU2491', 'MU2492', 'MU2493',
  'MU2494', 'MU2495', 'MU2496', 'MU2497', 'MU2498', 'MU2499', 'MU2500', 'MU2501',
  'MU2503', 'MU2504', 'MU2506', 'MU2507', 'MU2508', 'MU2509', 'MU2510', 'MU2511',
  'MU2512', 'MU2513', 'MU2514', 'MU2515', 'MU2516', 'MU2517', 'MU2519', 'MU2520',
  'MU2521', 'MU2522', 'MU2523', 'MU2524', 'MU2525', 'MU2526', 'MU2527', 'MU2528',
  'MU2529', 'MU2530', 'MU2531', 'MU2532', 'MU2533', 'MU2534', 'MU2535', 'MU2536',
  'MU2537', 'MU2538', 'MU2539', 'MU2540', 'MU2541', 'MU2542', 'MU2543', 'MU2544',
  'MU2545', 'MU2546', 'MU2547', 'MU2548', 'MU2549', 'MU2550', 'MU2551', 'MU2552',
  'MU2553', 'MU2554', 'MU2555', 'MU2556', 'MU2557', 'MU2558', 'MU2559', 'MU2560',
  'MU2561', 'MU2562', 'MU2563', 'MU2564', 'MU2565', 'MU2566', 'MU2567', 'MU2568',
  'MU2569', 'MU2570', 'MU2571', 'MU2572', 'MU2573', 'MU2574', 'MU2575', 'MU2576',
  'MU2577', 'MU2578', 'MU2579', 'MU2580', 'MU2581', 'MU2582', 'MU2583', 'MU2584',
  'MU2585', 'MU2586', 'MU2587', 'MU2588', 'MU2589', 'MU2590', 'MU2591', 'MU2592',
  'MU2593', 'MU2594', 'MU2595', 'MU2596', 'MU2597', 'MU2598', 'MU2599', 'MU2600',
  'MU2601', 'MU2602', 'MU2603', 'MU2604', 'MU2605', 'MU2606', 'MU2607', 'MU2608',
  'MU2609', 'MU2610', 'MU2611', 'MU2612', 'MU2613', 'MU2614', 'MU2615', 'MU2616',
  'MU2617', 'MU2618', 'MU2619', 'MU2620', 'MU2621', 'MU2622', 'MU2623', 'MU2624',
  'MU2625', 'MU2626', 'MU2627', 'MU2628', 'MU2629', 'MU2630', 'MU2631', 'MU2632',
  'MU2633', 'MU2634', 'MU2635', 'MU2636', 'MU2637', 'MU2638', 'MU2639', 'MU2640',
  'MU2641', 'MU2642', 'MU2643', 'MU2644', 'MU2645', 'MU2646', 'MU2647', 'MU2648',
  'MU2649', 'MU2650', 'MU2651', 'MU2652', 'MU2654', 'MU2655', 'MU2656', 'MU2657',
  'MU2658', 'MU2659', 'MU2660', 'MU2661', 'MU2662', 'MU2663', 'MU2664', 'MU2665',
  'MU2666', 'MU2667', 'MU2668', 'MU2669', 'MU2670', 'MU2671', 'MU2672', 'MU2673',
  'MU2674', 'MU2675', 'MU2676', 'MU2677', 'MU2678', 'MU2679', 'MU2680', 'MU2681',
  'MU2682', 'MU2683', 'MU2684', 'MU2685', 'MU2686', 'MU2687', 'MU2688', 'MU2689',
  'MU2690', 'MU2691', 'MU2692', 'MU2693', 'MU2694', 'MU2695', 'MU2696', 'MU2697',
  'MU2698', 'MU2699', 'MU2700', 'MU2701', 'MU2702', 'MU2703', 'MU2704', 'MU2705',
  'MU2706', 'MU2707', 'MU2708', 'MU2709', 'MU2710', 'MU2711', 'MU2712', 'MU2713',
  'MU2714', 'MU2715', 'MU2716', 'MU2717', 'MU2718', 'MU2719', 'MU2720', 'MU2721',
  'MU2722', 'MU2723', 'MU2724', 'MU2725', 'MU2726', 'MU2727', 'MU2728', 'MU2729',
  'MU2730', 'MU2731', 'MU2732', 'MU2733', 'MU2734', 'MU2735', 'MU2736', 'MU2737',
  'MU2738', 'MU2739', 'MU2740', 'MU2741', 'MU2742', 'MU2743', 'MU2744', 'MU2745',
  'MU2746', 'MU2747', 'MU2748', 'MU2749', 'MU2750', 'MU2751', 'MU2752', 'MU2753',
  'MU2754', 'MU2755', 'MU2756', 'MU2757', 'MU2758', 'MU2759', 'MU2760', 'MU2761',
  'MU2762', 'MU2763', 'MU2764', 'MU2765', 'MU2766', 'MU2767', 'MU2768', 'MU2769',
  'MU2770', 'MU2771', 'MU2772', 'MU2773', 'MU2774', 'MU2775', 'MU2776', 'MU2777',
  'MU2778', 'MU2779', 'MU2780', 'MU2781', 'MU2782', 'MU2783', 'MU2784', 'MU2785',
  'MU2786', 'MU2787', 'MU2788', 'MU2789', 'MU2790', 'MU2791', 'MU2792', 'MU2793',
  'MU2794', 'MU2795', 'MU2796', 'MU2797', 'MU2798', 'MU2799', 'MU2800', 'MU2801',
  'MU2802', 'MU2803', 'MU2804', 'MU2805', 'MU2806', 'MU2807', 'MU2808', 'MU2809',
  'MU2810', 'MU2811', 'MU2812', 'MU2813', 'MU2814', 'MU2815', 'MU2816', 'MU2817',
  'MU2818', 'MU2819', 'MU2820', 'MU2821', 'MU2822', 'MU2823', 'MU2824', 'MU2825',
  'MU2826', 'MU2827', 'MU2828', 'MU2829', 'MU2830', 'MU2831', 'MU2832', 'MU2833',
  'MU2834', 'MU2835', 'MU2836', 'MU2837', 'MU2838', 'MU2839', 'MU2840', 'MU2841',
  'MU2842', 'MU2843', 'MU2844', 'MU2845', 'MU2846', 'MU2847', 'MU2848', 'MU2849',
  'MU2850', 'MU2851', 'MU2852', 'MU2853', 'MU2854', 'MU2855', 'MU2856', 'MU2857',
  'MU2858', 'MU2859', 'MU2860', 'MU2861', 'MU2862', 'MU2863', 'MU2864', 'MU2865',
  'MU2866', 'MU2867', 'MU2868', 'MU2869', 'MU2870', 'MU2871', 'MU2872', 'MU2873',
  'MU2874', 'MU2875', 'MU2876', 'MU2877', 'MU2878', 'MU2879', 'MU2880', 'MU2881',
  'MU2882', 'MU2883', 'MU2884', 'MU2885', 'MU2886', 'MU2887', 'MU2888', 'MU2889',
  'MU2890', 'MU2891', 'MU2892', 'MU2893', 'MU2894', 'MU2895', 'MU2896', 'MU2897',
  'MU2898', 'MU2899', 'MU2900', 'MU2901', 'MU2902', 'MU2903', 'MU2904', 'MU2905',
  'MU2906', 'MU2907', 'MU2908', 'MU2909', 'MU2910', 'MU2911', 'MU2912', 'MU2913',
  'MU2914', 'MU2915', 'MU2916', 'MU2917', 'MU2918', 'MU2919', 'MU2920', 'MU2921',
  'MU2922', 'MU2923', 'MU2924', 'MU2925', 'MU2926', 'MU2927', 'MU2928', 'MU2929',
  'MU2930', 'MU2931', 'MU2932', 'MU2933', 'MU2934', 'MU2935', 'MU2936', 'MU2937',
  'MU2938', 'MU2939', 'MU2940', 'MU2941', 'MU2942', 'MU2943', 'MU2944', 'MU2945',
  'MU2946', 'MU2947', 'MU2948', 'MU2949', 'MU2950', 'MU2951', 'MU2952', 'MU2953',
  'MU2954', 'MU2955', 'MU2956', 'MU2957', 'MU2958', 'MU2959', 'MU2960', 'MU2961',
  'MU2962', 'MU2963', 'MU2964', 'MU2965', 'MU2966', 'MU2967', 'MU2968', 'MU2969',
  'MU2970', 'MU2971', 'MU2972', 'MU2973', 'MU2974', 'MU2975', 'MU2976', 'MU2977',
  'MU2978', 'MU2979', 'MU2980', 'MU2981', 'MU2982', 'MU2983', 'MU2984', 'MU2985',
  'MU2986', 'MU2987', 'MU2988', 'MU2989', 'MU2990', 'MU2991', 'MU2992', 'MU2993',
  'MU2994', 'MU2995', 'MU2996', 'MU2997', 'MU2998', 'MU2999', 'MU3000', 'MU3001',
  'MU3002', 'MU3003', 'MU3004', 'MU3005', 'MU3006', 'MU3007', 'MU3008', 'MU3009',
  'MU3010', 'MU3011', 'MU3012', 'MU3013', 'MU3014', 'MU3015', 'MU3016', 'MU3017',
  'MU3018', 'MU3019', 'MU3020', 'MU3021', 'MU3022', 'MU3023', 'MU3024', 'MU3025',
  'MU3026', 'MU3027', 'MU3028', 'MU3029', 'MU3030', 'MU3031', 'MU3032', 'MU3033',
  'MU3034', 'MU3035', 'MU3036', 'MU3037', 'MU3038', 'MU3039', 'MU3040', 'MU3041',
  'MU3042', 'MU3043', 'MU3044', 'MU3045', 'MU3046', 'MU3047', 'MU3048', 'MU3049',
  'MU3050', 'MU3051', 'MU3052', 'MU3053', 'MU3054', 'MU3055', 'MU3056', 'MU3057',
  'MU3058', 'MU3059', 'MU3060', 'MU3061', 'MU3062', 'MU3063', 'MU3064', 'MU3065',
  'MU3066', 'MU3067', 'MU3068', 'MU3069', 'MU3070', 'MU3071', 'MU3072', 'MU3073',
  'MU3074', 'MU3075', 'MU3076', 'MU3077', 'MU3078', 'MU3079', 'MU3080', 'MU3081',
  'MU3082', 'MU3083', 'MU3084', 'MU3085', 'MU3086', 'MU3087', 'MU3088', 'MU3089',
  'MU3090', 'MU3091', 'MU3092', 'MU3093', 'MU3094', 'MU3095', 'MU3096', 'MU3097',
  'MU3098', 'MU3099', 'MU3100', 'MU3101', 'MU3102', 'MU3103', 'MU3104', 'MU3105',
  'MU3106', 'MU3107', 'MU3108', 'MU3109', 'MU3110', 'MU3111', 'MU3112', 'MU3113',
  'MU3114', 'MU3115', 'MU3116', 'MU3117', 'MU3118', 'MU3119', 'MU3120', 'MU3121',
  'MU3122', 'MU3123', 'MU3124', 'MU3125', 'MU3126', 'MU3127', 'MU3128', 'MU3129',
  'MU3130', 'MU3131', 'MU3132', 'MU3133', 'MU3134', 'MU3135', 'MU3136', 'MU3137',
  'MU3138', 'MU3139', 'MU3140', 'MU3141', 'MU3142', 'MU3143', 'MU3144', 'MU3145',
  'MU3146', 'MU3147', 'MU3148', 'MU3149', 'MU3150', 'MU3151', 'MU3152', 'MU3153',
  'MU3154', 'MU3155', 'MU3156', 'MU3157', 'MU3158', 'MU3159', 'MU3160', 'MU3161',
  'MU3162', 'MU3163', 'MU3164', 'MU3165', 'MU3166', 'MU3167', 'MU3168', 'MU3169',
  'MU3170', 'MU3171', 'MU3172', 'MU3173', 'MU3174', 'MU3175', 'MU3176', 'MU3177',
  'MU3178', 'MU3179', 'MU3180', 'MU3181', 'MU3182', 'MU3183', 'MU3184', 'MU3185',
  'MU3186', 'MU3187', 'MU3188', 'MU3189', 'MU3190', 'MU3191', 'MU3192', 'MU3193',
  'MU3194', 'MU3195', 'MU3196', 'MU3197', 'MU3198', 'MU3199', 'MU3200', 'MU3201',
  'MU3202', 'MU3203', 'MU3204', 'MU3205', 'MU3206', 'MU3207', 'MU3208', 'MU3209',
  'MU3210', 'MU3211', 'MU3212', 'MU3213', 'MU3214', 'MU3215', 'MU3216', 'MU3217',
  'MU3218', 'MU3219', 'MU3220', 'MU3221', 'MU3222', 'MU3223', 'MU3224', 'MU3225',
  'MU3226', 'MU3227', 'MU3228', 'MU3229', 'MU3230', 'MU3231', 'MU3232', 'MU3233',
  'MU3234', 'MU3235', 'MU3236', 'MU3237', 'MU3238', 'MU3239', 'MU3240', 'MU3241',
  'MU3242', 'MU3243', 'MU3244', 'MU3245', 'MU3246', 'MU3247', 'MU3248', 'MU3249',
  'MU3250', 'MU3251', 'MU3252', 'MU3253', 'MU3254', 'MU3255', 'MU3256', 'MU3257',
  'MU3258', 'MU3259', 'MU3260', 'MU3261', 'MU3262', 'MU3263', 'MU3264', 'MU3265',
  'MU3266', 'MU3267', 'MU3268', 'MU3269', 'MU3270', 'MU3271', 'MU3272', 'MU3273',
  'MU3274', 'MU3275', 'MU3276', 'MU3277', 'MU3278', 'MU3279', 'MU3280', 'MU3281',
  'MU3282', 'MU3283', 'MU3284', 'MU3285', 'MU3286', 'MU3287', 'MU3288', 'MU3289',
  'MU3290', 'MU3291', 'MU3292', 'MU3293', 'MU3294', 'MU3295', 'MU3296', 'MU3297',
  'MU3298', 'MU3299', 'MU3300', 'MU3301', 'MU3302', 'MU3303', 'MU3304', 'MU3305',
  'MU3306', 'MU3307', 'MU3308', 'MU3309', 'MU3310', 'MU3311', 'MU3312', 'MU3313',
  'MU3314', 'MU3315', 'MU3316', 'MU3317', 'MU3318', 'MU3319', 'MU3320', 'MU3321',
  'MU3322', 'MU3323', 'MU3324', 'MU3325', 'MU3326', 'MU3327', 'MU3328', 'MU3329',
  'MU3330', 'MU3331', 'MU3332', 'MU3333', 'MU3334', 'MU3335', 'MU3336', 'MU3337',
  'MU3338', 'MU3339', 'MU3340', 'MU3341', 'MU3342', 'MU3343', 'MU3344', 'MU3345',
  'MU3346', 'MU3347', 'MU3348', 'MU3349', 'MU3350', 'MU3351', 'MU3352', 'MU3353',
  'MU3354', 'MU3355', 'MU3356', 'MU3357', 'MU3358', 'MU3359', 'MU3360', 'MU3361',
  'MU3362', 'MU3363', 'MU3364', 'MU3365', 'MU3366', 'MU3367', 'MU3368', 'MU3369',
  'MU3370', 'MU3371', 'MU3372', 'MU3373', 'MU3374', 'MU3375', 'MU3376', 'MU3377',
  'MU3378', 'MU3379', 'MU3380', 'MU3381', 'MU3382', 'MU3383', 'MU3384', 'MU3385',
  'MU3386', 'MU3387', 'MU3388', 'MU3389', 'MU3390', 'MU3391', 'MU3392', 'MU3393',
  'MU3394', 'MU3395', 'MU3396', 'MU3397', 'MU3398', 'MU3399', 'MU3400', 'MU3401',
  'MU3402', 'MU3403', 'MU3404', 'MU3405', 'MU3406', 'MU3407', 'MU3408', 'MU3409',
  'MU3410', 'MU3411', 'MU3412', 'MU3413', 'MU3414', 'MU3415', 'MU3416', 'MU3417',
  'MU3418', 'MU3419', 'MU3420', 'MU3421', 'MU3422', 'MU3423', 'MU3424', 'MU3425',
  'MU3426', 'MU3427', 'MU3428', 'MU3429', 'MU3430', 'MU3431', 'MU3432', 'MU3433',
  'MU3434', 'MU3435', 'MU3436', 'MU3437', 'MU3438', 'MU3439', 'MU3440', 'MU3441',
  'MU3442', 'MU3443', 'MU3444', 'MU3445', 'MU3446', 'MU3447', 'MU3448', 'MU3449',
  'MU3450', 'MU3451', 'MU3452', 'MU3453', 'MU3454', 'MU3455', 'MU3456', 'MU3457',
  'MU3458', 'MU3459', 'MU3460', 'MU3461', 'MU3462', 'MU3463', 'MU3464', 'MU3465',
  'MU3466', 'MU3467', 'MU3468', 'MU3469', 'MU3470', 'MU3471', 'MU3472', 'MU3473',
  'MU3474', 'MU3475', 'MU3476', 'MU3477', 'MU3478', 'MU3479', 'MU3480', 'MU3481',
  'MU3482', 'MU3483', 'MU3484', 'MU3485', 'MU3486', 'MU3487', 'MU3488', 'MU3489',
  'MU3490', 'MU3491', 'MU3492', 'MU3493', 'MU3494', 'MU3495', 'MU3496', 'MU3497',
  'MU3498', 'MU3499', 'MU3500', 'MU3501', 'MU3502', 'MU3503', 'MU3504', 'MU3505',
  'MU3506', 'MU3507', 'MU3508', 'MU3509', 'MU3510', 'MU3511', 'MU3512', 'MU3513',
  'MU3514', 'MU3515', 'MU3516', 'MU3517', 'MU3518', 'MU3519', 'MU3520', 'MU3521',
  'MU3522', 'MU3523', 'MU3524', 'MU3525', 'MU3526', 'MU3527', 'MU3528', 'MU3529',
  'MU3530', 'MU3531', 'MU3532', 'MU3533', 'MU3534', 'MU3535', 'MU3536', 'MU3537',
  'MU3538', 'MU3539', 'MU3540', 'MU3541', 'MU3542', 'MU3543', 'MU3544', 'MU3545',
  'MU3546', 'MU3547', 'MU3548', 'MU3549', 'MU3550', 'MU3551', 'MU3552', 'MU3553',
  'MU3554', 'MU3555', 'MU3556', 'MU3557', 'MU3558', 'MU3559', 'MU3560', 'MU3561',
  'MU3562', 'MU3563', 'MU3564', 'MU3565', 'MU3566', 'MU3567', 'MU3568', 'MU3569',
  'MU3570', 'MU3571', 'MU3572', 'MU3573', 'MU3574', 'MU3575', 'MU3576', 'MU3577',
  'MU3578', 'MU3579', 'MU3580', 'MU3581', 'MU3582', 'MU3583', 'MU3584', 'MU3585',
  'MU3586', 'MU3587', 'MU3588', 'MU3589', 'MU3590', 'MU3591', 'MU3592', 'MU3593',
  'MU3594', 'MU3595', 'MU3596', 'MU3597', 'MU3598', 'MU3599', 'MU3600', 'MU3601',
  'MU3602', 'MU3603', 'MU3604', 'MU3605', 'MU3606', 'MU3607', 'MU3608', 'MU3609',
  'MU3610', 'MU3611', 'MU3612', 'MU3613', 'MU3614', 'MU3615', 'MU3616', 'MU3617',
  'MU3618', 'MU3619', 'MU3620', 'MU3621', 'MU3622', 'MU3623', 'MU3624', 'MU3625',
  'MU3626', 'MU3627', 'MU3628', 'MU3629', 'MU3630', 'MU3631', 'MU3632', 'MU3633',
  'MU3634', 'MU3635', 'MU3636', 'MU3637', 'MU3638', 'MU3639', 'MU3640', 'MU3641',
  'MU3642', 'MU3643', 'MU3644', 'MU3645', 'MU3646', 'MU3647', 'MU3648', 'MU3649',
  'MU3650', 'MU3651', 'MU3652', 'MU3653', 'MU3654', 'MU3655', 'MU3656', 'MU3657',
  'MU3658', 'MU3659', 'MU3660', 'MU3661', 'MU3662', 'MU3663', 'MU3664', 'MU3665',
  'MU3666', 'MU3667', 'MU3668', 'MU3669', 'MU3670', 'MU3671', 'MU3672', 'MU3673',
  'MU3674', 'MU3675', 'MU3676', 'MU3677', 'MU3678', 'MU3679', 'MU3680', 'MU3681',
  'MU3682', 'MU3683', 'MU3684', 'MU3685', 'MU3686', 'MU3687', 'MU3688', 'MU3689',
  'MU3690', 'MU3691', 'MU3692', 'MU3693', 'MU3694', 'MU3695', 'MU3696', 'MU3697',
  'MU3698', 'MU3699', 'MU3700', 'MU3701', 'MU3702', 'MU3703', 'MU3704', 'MU3705',
  'MU3706', 'MU3707', 'MU3708', 'MU3709', 'MU3710', 'MU3711', 'MU3712', 'MU3713',
  'MU3714', 'MU3715', 'MU3716', 'MU3717', 'MU3718', 'MU3719', 'MU3720', 'MU3721',
  'MU3722', 'MU3723', 'MU3724', 'MU3725', 'MU3726', 'MU3727', 'MU3728', 'MU3729',
  'MU3730', 'MU3731', 'MU3732', 'MU3733', 'MU3734', 'MU3735', 'MU3736', 'MU3737',
  'MU3738', 'MU3739', 'MU3740', 'MU3741', 'MU3742', 'MU3743', 'MU3744', 'MU3745',
  'MU3746', 'MU3747', 'MU3748', 'MU3749', 'MU3750', 'MU3751', 'MU3752', 'MU3753',
  'MU3754', 'MU3755', 'MU3756', 'MU3757', 'MU3758', 'MU3759', 'MU3760', 'MU3761',
  'MU3762', 'MU3763', 'MU3764', 'MU3765', 'MU3766', 'MU3767', 'MU3768', 'MU3769',
  'MU3770', 'MU3771', 'MU3772', 'MU3773', 'MU3774', 'MU3775', 'MU3776', 'MU3777',
  'MU3778', 'MU3779', 'MU3780', 'MU3781', 'MU3782', 'MU3783', 'MU3784', 'MU3785',
  'MU3786', 'MU3787', 'MU3788', 'MU3789', 'MU3790', 'MU3791', 'MU3792', 'MU3793',
  'MU3794', 'MU3795', 'MU3796', 'MU3797', 'MU3798', 'MU3799', 'MU3800', 'MU3801',
  'MU3802', 'MU3803', 'MU3804', 'MU3805', 'MU3806', 'MU3807', 'MU3808', 'MU3809',
  'MU3810', 'MU3811', 'MU3812', 'MU3813', 'MU3814', 'MU3815', 'MU3816', 'MU3817',
  'MU3818', 'MU3819', 'MU3820', 'MU3821', 'MU3822', 'MU3823', 'MU3824', 'MU3825',
  'MU3826', 'MU3827', 'MU3828', 'MU3829', 'MU3830', 'MU3831', 'MU3832', 'MU3833',
  'MU3834', 'MU3835', 'MU3836', 'MU3837', 'MU3838', 'MU3839', 'MU3840', 'MU3841',
  'MU3842', 'MU3843', 'MU3844', 'MU3845', 'MU3846', 'MU3847', 'MU3848', 'MU3849',
  'MU3850', 'MU3851', 'MU3852', 'MU3853', 'MU3854', 'MU3855', 'MU3856', 'MU3857',
  'MU3858', 'MU3859', 'MU3860', 'MU3861', 'MU3862', 'MU3863', 'MU3864', 'MU3865',
  'MU3866', 'MU3867', 'MU3868', 'MU3869', 'MU3870', 'MU3871', 'MU3872', 'MU3873',
  'MU3874', 'MU3875', 'MU3876', 'MU3877', 'MU3878', 'MU3879', 'MU3880', 'MU3881',
  'MU3882', 'MU3883', 'MU3884', 'MU3885', 'MU3886', 'MU3887', 'MU3888', 'MU3889',
  'MU3890', 'MU3891', 'MU3892', 'MU3893', 'MU3894', 'MU3895', 'MU3896', 'MU3897',
  'MU3898', 'MU3899'
);

COMMIT;

-- ============================================================================
-- Pemeriksaan hasil: kolom "hasil" harus sama dengan "seharusnya".
-- ============================================================================
SELECT 'barang tanpa barcode' AS data, COUNT(*) AS hasil, 0 AS seharusnya FROM products WHERE barcode IS NULL
UNION ALL SELECT 'barang dengan barcode = SKU', COUNT(*), 3190 FROM products WHERE barcode = sku
UNION ALL SELECT 'barang dengan barcode lama (beda dari SKU)', COUNT(*), 668 FROM products WHERE barcode <> sku
UNION ALL SELECT 'barcode sama dengan SKU barang lain', COUNT(*), 0 FROM products a JOIN products b ON a.barcode = b.sku AND a.id <> b.id;

-- ============================================================================
-- Untuk ditinjau
-- ============================================================================
-- Dianggap tidak ada barcode (-> SKU): isian pendek ('0', '1', '-', dst.): 2106 barang, kosong: 1043 barang
--
-- Barcode kembar, tidak dipakai (-> SKU):
--   barcode | SKU | barang
--   4588888887549 | MU0110 | KUAS BIASA 1 1/2"
--   4588888887549 | MU0111 | KUAS BIASA 2"
--   4588888887549 | MU1593 | KUAS BIASA 5"
--   4588888887549 | MU2273 | KUAS BIASA 3'
--   49-2001-48 | MU0205 | WATERPASS DP-2001B 48"
--   49-2001-48 | MU2035 | SUZUKA CAT SM116 200CC
--   8993888712269 | MU2438 | LAMPU KUNING 15 W
--   8993888712269 | MU2895 | LAMPU KUNING 25 W
--
-- Barcode sama dengan SKU barang lain, tidak dipakai (-> SKU):
--   barang | SKU barang | barcode lama (= SKU barang lain)
--   MU3622 METERAN SANSHAN 7.5M | MU3622 | MU2370 (= SKU MU2370)
--   MU3623 METERAN SANHSAN 5M | MU3623 | MU2763 (= SKU MU2763)
