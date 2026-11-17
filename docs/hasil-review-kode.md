# Temuan review kode yang masih terbuka (sementara)

> File sementara. Temuan yang sudah diperbaiki dihapus dari daftar ini.
> Hapus file ini setelah semua item di bawah selesai.
>
> Item 2-6 berasal dari review fitur yang dikerjakan 3 Nov 2026 (commit 66fb92a..98ec1a1: COD dan void nota, laporan tutup shift, format cetak, pengaturan toko, laporan penjualan). Item 7-12 berasal dari review 9 Nov 2026 atas commit ba02d1c..7df6929 (sisa laci, harga massal, opname lanjutan, gulungan utuh, pesanan pelanggan, sopir dan surat jalan gabungan, tutup shift buta). Tanda **[dicek]** = sudah saya buktikan langsung di kode; sisanya temuan reviewer yang belum saya jalankan.

## 1. Provisioning toko lewat antrean (job)

- **Asal:** review Master Toko, PERF major.
- **Kondisi sekarang:** migrasi sekitar 30 tabel dan seed berjalan di dalam request HTTP saat toko dibuat atau "Coba lagi" ditekan (`StoreProvisioner`). Batas waktu eksekusi Hostinger bisa memutusnya di tengah.
- **Rencana:** jadikan job antrean dengan progres di daftar toko. Butuh cron `php artisan queue:work --stop-when-empty` di Hostinger, jadi dikerjakan **saat deploy ke Hostinger**. Dokumen (`analisis-sistem.md`, sekitar baris 86, 213, 1970) sudah menggambarkan job antrean.

## 2. Sisa dari COD dan void nota

Semua temuan lain di bagian ini sudah diperbaiki dan dihapus dari daftar. Yang tersisa, **diterima**:

- **[TEST][nit]** Penguncian surat jalan saat void dan "Sudah diantar" dibuktikan lewat urutan query (`for update` pada `delivery_notes`), bukan lewat dua proses yang benar-benar berjalan bersamaan. Tes dua proses di PHPUnit rawan flaky (aturan CLAUDE.md: tes flaky lebih buruk dari tanpa tes), jadi tidak dikerjakan.

## 3. Sisa dari pengaturan toko dan satuan

Semua temuan lain di bagian ini sudah diperbaiki dan dihapus dari daftar. Yang tersisa, **diterima**:

- **[LOG][nit]** `requireNoOpnameLock` di `SaleService.php` tidak terkunci terhadap mulai-opname: satu penjualan yang lolos pemeriksaan tepat sebelum opname dimulai tetap masuk (stok aman karena posting membaca stok live di bawah lock). Menutupnya butuh pembacaan terkunci pada gudang dan menyerialkan penjualan, harga yang terlalu mahal untuk jendela yang sempit dan tanpa dampak ke stok.

## 5. Format cetak dan laporan tutup shift

Semua temuan lain di bagian ini sudah diperbaiki dan dihapus dari daftar. Yang tersisa, di luar cakupan:

- **OOS:** tanggal cetak memakai zona waktu browser, bukan toko (pola lama); `Shifts/Show.vue` mendeteksi tunai lewat label `'Tunai'`; `CloseShiftDialog.vue:31` menghitung selisih dengan float di frontend (keputusan akhir tetap di backend).

## 6. Lintas fitur: dasar tanggal

Semua temuan lain di bagian ini sudah diperbaiki dan dihapus dari daftar. Yang tersisa, **diterima**:

- **[TEST][nit]** backfill migrasi `void_business_date` belum punya tes otomatis (dijalankan manual di toko dengan data lama sebelum dipakai).
- **[LOG][nit]** daftar retur Admin (`SaleReturnService`) masih menyaring tanggal dengan `returned_at` (tanggal kalender), karena itu filter daftar dan bukan angka laporan.

## 7. Kas shift: sisa laci, tutup buta, shift ditahan

Semua temuan lain di bagian ini sudah diperbaiki dan dihapus dari daftar. Yang tersisa, **diterima atau ditunda**:

- **[PERF][nit]** `bestSellers` (join `sale_items` x `sales` x `stocks` 30 hari) belum punya indeks `(warehouse_id, status, sold_at)` dan belum diukur; kini hanya dihitung saat halaman dimuat atau diminta, tidak di tiap polling.
- **[SEC][nit]** kasir masih bisa menebak tunai seharusnya dari daftar nota dan pengeluaran shiftnya (data itu memang dipakai untuk cetak ulang); dicatat di docs D9 sebagai dorongan UX.
- **[TEST][nit]** belum ada tes untuk pesanan dan DP pelanggan saat menunggu review, backfill `drawer_left_cash` dan `down()` migrasi `2026_11_08_110000`, backfill `counted_at`/`closed_by`, dan konkurensi buka/tutup shift (tes dua proses rawan flaky).
- **[ARCH][nit]** migrasi `2026_11_08_120000` membuang `followed_up_by/at` tanpa menyalin daftar selisih "ditolak, belum ditindaklanjuti"; sudah terpasang di toko yang ada, jadi hanya perlu dicek bila ada DB lama lain.
- **[UX][nit]** kartu *Perlu tindakan* di Dashboard Admin masih menampilkan selisih yang hanya bisa diputuskan Super Admin; halaman detailnya menjelaskan alasannya.

## 8. Sisa dari harga massal dan kalkulator

Semua temuan lain di bagian ini sudah diperbaiki dan dihapus dari daftar. Yang tersisa, **diterima**:

- **[TEST][nit]** Paritas rumus JS (`usePriceMargin.js`) dengan backend tidak punya tes otomatis karena belum ada runner JS; dicek manual dengan Node (mis. 1,15 × 1,5 = 1,73, sama dengan server).
- **[QUAL][nit]** Audit satu proses ubah massal tidak punya tag bersama: laravel-auditing memberi tag per model, jadi satu tag untuk semua barang butuh status statis. Waktu dan pengguna yang sama sudah cukup untuk mengelompokkannya.
- **[PERF][nit]** Terapkan tetap sinkron (±15 md per barang); pindah ke antrean menunggu keputusan deploy Hostinger (bagian 1).
- **OOS:** `CartPricer` memakai `product_units.cost_price` per satuan, jadi ubah massal memengaruhi HPP penjualan berikutnya (area kasir).

## 9. Sisa dari opname lanjutan, barang rusak, gudang retur

Semua temuan lain di bagian ini sudah diperbaiki dan dihapus dari daftar. Yang tersisa, **diterima**:

- **[PERF][nit]** Layar opname kini memuat kembar DG dan masalah SKU satu kali per halaman (bukan 2-3 query per baris), ringkasan DG satu kali, dan posting mencari gudang tujuan satu kali; belum diukur dengan data besar. Pemeriksaan review masih memanggil `deadStockWarehouse()` per barang yang punya jumlah dead stock (satu query kecil per barang).
- **[TEST][nit]** Belum ada tes: migrasi `2026_11_08_100000` naik untuk toko lama (satu gudang jual → `RETUR`, banyak → `RETUR-{kode}`; DDL-nya mengubah skema DB tes); hitungan kemasan utuh yang turun di Pemeriksaan Retur; balapan klaim gudang di `create()`/`start()` dan dua posting bersamaan (tes dua proses rawan flaky, dan aturan CLAUDE.md menyebut tes flaky lebih buruk dari tanpa tes).
- **OOS:** `StockOpnameItem` tidak `Auditable` padahal keputusan DG dan tanda review menentukan mutasi stok. Nama `$isDamaged`/`needs_damage_decision`/`damage_summary` dipakai ulang untuk Pemeriksaan Retur; `$needsReviewMark` melanggar aturan boolean. Rugi dead stock sudah masuk laporan laba (12 Nov 2026); rugi DG tidak dihitung saat posting karena muncul sendiri saat barang DG terjual di bawah modal, dan selisih kurang selain Rusak belum masuk laporan.

## 10. Stok gulungan utuh

Semua temuan lain di bagian ini sudah diperbaiki dan dihapus dari daftar. Yang tersisa, **diterima atau ditunda**:

- **[UX][nit]** `StockOpnameService.php` — hitungan hanya dalam satuan dasar disimpan `counted_packs = 0` dan posting menghapus semua rol utuh; pengaman satu-satunya tetap konfirmasi UI `sealed_pack_drops`, `post()` tidak menuntut di server (mutasinya sudah mencatat `pack_change`). Menuntut alasan/review butuh keputusan produk dan mengubah `isReviewNeeded` beserta cerminnya di `StockOpnameReport`.
- **[LOG][nit]** `CustomerOrderService::stockCoverageOf` (petunjuk "barang sudah tercukupi" di daftar pesanan dan lencana kasir) hanya menghitung kuantitas dasar, belum rol utuh; keputusan akhir tetap di `markReady`.
- **[TEST][nit]** `SealedPackStockTest.php` belum menguji: opname mode kedatangan (`isArrivalCount`) yang menambah rol, opname barang rusak dengan perubahan rol, `countUncountedAsZero` pada barang berkemasan, dan kembar DG dari barang berkemasan.
- **[PERF][nit]** `Product.php` `scopeWithStockTotals` kini 5 subquery berkorelasi per baris daftar (sebelumnya 2); belum diukur.

## 11. Sisa dari pesanan pelanggan dan checkout kasir

Semua temuan lain di bagian ini sudah diperbaiki dan dihapus dari daftar. Yang tersisa, **diterima atau di luar cakupan**:

- **[TEST][nit]** Penyisihan stok serentak (dua `markReady`, atau `markReady` bersamaan dengan penjualan) dan deadlock urutan kunci batal/pengambilan dibuktikan lewat urutan query (kunci baris stok dulu, lalu bacaan penyisihan yang mengunci), bukan lewat dua proses yang benar-benar berjalan bersamaan; tes dua proses rawan flaky (aturan CLAUDE.md).
- **[PERF][nit]** Poll lencana 60 detik kini hanya menghitung dua `count()` dan stok yang cukup untuk pesanan berstatus Dipesan (tanpa memuat pesanan siap diambil), tetapi props non-lazy `shift`, `payment_methods` dan `urls` di `CashierController::index()` tetap dibangun tiap poll (murah). Indeks `sales.sold_at` untuk `bestSellers` tetap di bagian 7 (tidak ikut poll).
- **[LOG][nit]** Urutan kunci batal (pesanan lalu shift) dan nota pengambilan (shift lalu pesanan) tidak diubah; `cancel()` kini diulang bila kalah deadlock (`DEADLOCK_ATTEMPTS`).
- **OOS:** refund retur atas nota pengambilan yang dibayar `order_deposit`; pengeluaran stok lain (barang rusak, gudang retur, opname minus) yang menurunkan stok di bawah jumlah yang disisihkan hanya gagal-aman saat pengambilan.

## 12. Sopir dan surat jalan gabungan

- **[LOG][major] [dicek]** `SaleVoidService.php:96` — surat jalan gabungan berstatus Kembali memblokir void nota B selamanya: syaratnya `note->sale_id !== sale->id && status !== Cancelled`, sedangkan Returned tak bisa dibatalkan dan void nota pemilik hanya membatalkan yang `canBeCancelled`. Pesan `CodSaleException:35` menyuruh "void nota pengambilan dulu atau batalkan surat jalannya", kedua jalan itu tak membuka blokir. Bertentangan dengan docs butir 5 (baris 1903: "surat jalan Kembali tidak menghalangi") dan nota tunggal. → `$note->status->isUnfinished()` (Delivered sudah ditangani baris 95); tambah tes.
- **[BUG][major]** `CashierDeliveryNoteController.php:46,66,73` — `show()` memakai `notesOfSale()` yang sejak pivot juga memuat surat jalan gabungan milik nota lain, tetapi `print()`/`sheet()` hanya memeriksa `canHandleReceipt($deliveryNote->sale)` (nota pemilik); kasir nota B melihat tombol Cetak lalu 403 bila nota pengambilan dibuat kasir lain. Belum dicek apakah dialog bisa dibuka untuk nota B milik kasir lain. → otorisasi bila pengguna boleh menangani salah satu nota di `$deliveryNote->sales`.
- **[UX][major]** `DeliveryNotes/Create.vue:39,135-138` — memilih nota A mengisi semua baris dengan qty = sisa, termasuk baris nota B hasil `deliverableLines`; tabel tanpa `sale_number` ("dari nota …" hanya di dialog kasir) sehingga admin bisa tak sadar mengantar barang nota lain. → kolom nota asal, bawaan qty 0 untuk baris nota tertaut.
- **[DOC][major]** `analisis-sistem.md:844` (BPMN), `:1486` (ERD), `:1894`, `:1906`, `:1899`, `:1901`, D30 (`:151`), `diagram.html:625` — baris 1837 bilang sopir tidak ditanya saat membuat surat jalan dan kendaraan tak dipakai, tetapi BPMN, header 1894, paragraf "Dibangun" 1906 dan form kasir masih menyebut isi sopir/no. kendaraan saat membuat; ERD tak punya `drivers` dan `delivery_note_sales`; D30 dan 1899 masih "merujuk nota" (tunggal); butir 3 (1901) menyebut "Admin atau kasir shift berjalan memperbarui status" padahal ubah status hanya di rute admin.
- **[TEST][major]** belum ada tes: `ship` pada surat jalan non-Draft dengan `driver_id`; `driver_id` tidak ada/bukan integer; kasir mencoba PUT drivers.update, `is_active` null/string, nama sopir dengan spasi; void nota B dengan surat jalan gabungan Kembali; `store` dengan `sale_item_id` nota tertaut yang melebihi sisa lintas dua nota (`DeliveryNoteTest` tak punya tes surat jalan gabungan sama sekali); kasir mencetak surat jalan gabungan.
- **[UX][nit]** `DeliveryNotes/Show.vue:118`, `DeliveryNoteResource.php:44` — "Buat Retur" selalu ke `sale_id` nota pemilik padahal barang yang kembali bisa milik nota B (docs 1837: retur lewat nota asalnya). → arahkan per baris ke nota asal.
- **[LOG][nit]** `DeliveryNoteService.php:53-56`, `DeliveryNoteListResource.php:28` — pencarian daftar memakai `orWhereHas('sale', …)` (nota pemilik saja), jadi surat jalan gabungan tak ketemu dengan nomor nota B padahal docs 1906 menjanjikan; hanya satu `sale_number` ditampilkan. → `whereHas('sales', …)`.
- **[PERF][nit]** `DeliveryNoteController.php:90-93`, `DeliveryNoteService.php:185-202` — `sales()` memanggil `saleOption()` per hasil (limit 10), masing-masing `deliverableLines()` menjalankan `linkedSaleOf` (2 query), `loadMissing('items')`, `remainingQuantities` → ±40-50 query per ketikan. → batch.
- **[ARCH][nit]** `SaveDeliveryNoteRequest.php:32-33`, `StoreSaleRequest.php:47-48`, `DeliveryNoteService.php:153-154` — UI tak lagi menanyakan sopir/kendaraan tetapi `driver_name`/`vehicle_number` masih diterima dan disimpan lewat API: dua penulis `driver_name` (teks bebas dan snapshot dari `drivers` saat ship), `vehicle_number` tanpa jalur UI; tes (`CashierDeliveryNoteTest:61`, `CodSaleTest:66`, `DeliveryNoteTest:74-75`) mengunci perilaku mati itu. → hapus dari request/service/fillable atau tulis sengaja dipertahankan.
- **[API][nit]** `SaveStoreSettingsRequest.php:34`, `StoreSettingService.php:120-124` — `auto_print_receipt` `nullable` sedangkan `lock_sales_during_opname` `required`; `update()` menulis semua key sehingga PUT tanpa `auto_print_receipt` diam-diam mematikannya. → `required|boolean`.
- **[QUAL][nit]** `DeliveryNoteService.php:240-250`, `DeliveryNoteController.php:121-123` — aturan "hanya sopir aktif" hanya di FormRequest; `ship(?Driver)` menerima sopir nonaktif, dan controller mencari `Driver` lagi di luar transaksi. Catatan saja selama pemanggilnya satu.
- **OOS:** `PrintableDeliveryNoteResource` meneruskan URL aksi admin dan flag `can_*` ke halaman cetak yang juga dibuka kasir; balapan nama sopir unik berakhir error DB 500 (teoretis); perilaku kunci FOR UPDATE pada `lockedNotesOf` lewat `whereRelation` (subquery) belum diverifikasi.
