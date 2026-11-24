# TODO — Laporan penjualan dan laporan stok

Terakhir diperbarui: 24 Nov 2026. Commit di `main`: `8b9be09`, `5f9d119`. Ringkasan (keterangan COD) dan Daftar nota per hari dikerjakan 24 Nov dan **belum di-commit**. Belum ada yang di-push.

## Laporan penjualan

### Sudah selesai

- **Tab Barang & kategori** (`reports/sales/products`, 24 Nov): tabel laba kotor per barang (paginasi 7/15/30) dan tabel penjualan per kategori atau merek (paginasi 5/10, tombol Kategori/Merek); semua kolom di kedua tabel bisa diurutkan (diurutkan di server, jadi urutan berlaku lintas halaman). Daftar nota sengaja tidak bisa diurutkan karena dikelompokkan per hari. Ringkasan kini selalu memberi 7 barang dan 5 kelompok teratas (tidak lagi mengikuti halaman/ukuran di alamat) dan punya tautan "Lihat semua" ke tab ini yang membawa periode dan pilihan Kategori/Merek. Alamat dengan periode disusun lewat `Composables/reportRange.js`.
- **Excel rinci bisa setahun** (24 Nov): baris nota dan baris barang dibaca langsung dari database satu per satu (bukan model Eloquent) lalu ditulis ke berkas, jadi memori datar. Sebelumnya setahun memuat +438 MB dan melewati batas 512 MB. PDF rinci dibatasi 31 hari (browser tidak sanggup merender setahun); periode lebih panjang ditolak dengan pesan, tombolnya dimatikan, dan Excel tetap tersedia. Kode: `SalesDetailSheetRows` (generator per sheet), `SalesDetailWorkbook` (lebar kolom tetap menurut judul).

- **Laporan penjualan rinci** (per nota, per hari, plus void, retur, rekap barang): lembar cetak/PDF dan Excel (4 sheet: Nota, Barang, Retur, Void, masing-masing dengan baris jumlah). Bisa dipersempit per metode bayar. Kode: `SalesDetailReportService`, `SalesDetailSheetRows`, `SalesDetailWorkbook`, `SalesDetailReportSheet.vue`.
- **Laporan tutup hari** memuat rincian nota hari itu (termasuk nota yang kemudian di-void, bertanda VOID) dan retur hari itu. Subtotal metode bayar dihitung seperti tutup hari, jadi angkanya sama. Kode: `SalesDetailReportService::forDay()`.
- **Tab Ringkasan** (`reports/sales`): hanya grafik. Kartu angka, grafik harian, pie metode bayar (COD diberi keterangan belum/sudah diantar di legenda), pie kategori/merek (5 teratas + Lainnya), batang peringkat laba per barang (7 teratas).
- **Tab Daftar nota** (`reports/sales/receipts`): semua nota periode **dikelompokkan per hari buku** (judul hari memuat nota, penjualan, retur, bersih, void; "(lanjutan)" bila hari terbelah antar halaman; saat disaring hanya nota dan penjualan), cari nomor atau pelanggan, filter metode bayar, paginasi 15/30/50, baris bisa dibuka (barang dan pembayaran). Tombol Cetak/PDF rinci dan Excel rinci ada di sini.
- **Tampilan tab** menonjol (tombol berikon, tab aktif diisi merah), dipakai bersama laporan penjualan dan stok lewat `ReportTabs.vue`.

### Rencana tab setelah keputusan 23 Nov

| Tab | Status |
|---|---|
| Ringkasan | selesai |
| Daftar nota | selesai (sudah per hari) |
| Barang & kategori | selesai |
| Retur & void | belum |

**Tab Pembayaran dicoret.** Isinya sudah tertutup: jumlah per metode ada di pie Ringkasan; transfer/QRIS/EDC dengan nomor referensi ada di Daftar nota (filter metode, buka baris) dan di Excel rinci (kolom per metode dan kolom referensi).

### Yang belum dikerjakan

**Tab Retur & void:**
- Nasib barang retur (diretur, hasil pemeriksaan gudang retur, menunggu diperiksa) dan 5 barang paling sering diretur. Data: `SalesReportService::returnDetails()`.
- Baru: daftar lengkap void dan retur beserta alasan. Data: `SalesDetailReportService` (`voids()`, `returns()`).

**Beres-beres setelah tab Retur & void jadi:**
- Tiap tab = route sendiri di bawah `permission:reports.view` (tanpa izin baru), pola seperti `reports/sales/receipts`. Tambahkan URL di `HandleInertiaRequests` (termasuk docblock tipe `currentStore`) dan daftar tab di `SalesReportTabs.vue`.
- Hapus `return_details` dari `ReportController::sales()`: sekarang **masih dihitung server tiap Ringkasan dibuka padahal tidak dipakai** (hitungan terbuang).
- Hapus `defineOptions({ inheritAttrs: false })` beserta komentarnya di `Sales.vue` setelah `return_details` tidak dikirim lagi.
- Tes eturn_details di 	ests/Feature/Tenancy/SalesReportTest.php (sekitar 10 tes, lewat route sales) dipindah ke route tab Retur & void.

**Tab opsional (belum diputuskan):** Jam & hari ramai; Pengiriman (nota diantar yang belum sampai, COD belum disetor); Antar toko (Super Admin).

## Laporan stok: tab Ringkasan (baru, grafik)

Tab pertama laporan stok, seperti Ringkasan di penjualan. Hasil diskusi 23 Nov. Tab jadi: **Ringkasan**, Posisi stok, Perlu restok, Tidak terjual, Pergerakan.

| # | Grafik | Menjawab | Sumber data |
|---|---|---|---|
| 1 | Pie komposisi nilai stok (3 bagian: stok jual, dead stock, retur menunggu diperiksa; nilai modal) | Uang tertanam di mana, berapa yang sudah "mati" | `StockReportService::summary()` (`sellable_value`, `dead_stock.value`, `returns_waiting.value`) |
| 2 | Batang bersegmen kondisi barang aktif: sehat, menipis, habis | Berapa barang yang perlu perhatian | `summary()`: habis = `out_of_stock_count`; menipis = `below_minimum_count` − `out_of_stock_count`; sehat = `products_count` − `below_minimum_count` |
| 3 | Pie nilai stok per kategori/merek (5 teratas + Lainnya, tombol Kategori/Merek) | Modal paling banyak tertanam di kategori apa | `StockReportService::groupsPage()`; tambahkan `value_total` (seperti `revenue_total` di penjualan) |
| 4 | Meter stok mengendap: porsi nilai stok jual yang tidak terjual 60 hari | Seberapa besar modal yang tidak berputar | `IdleStockService::summary(60)` (`stock_value`, `share_percent`) |
| 5 | Batang peringkat 5 barang paling mendesak direstok (sisa hari stok) | Barang yang akan habis duluan | `RestockReportService::page()` urut `days_left`, ambil 5 |

Di atasnya: beberapa angka utama (nilai stok jual, estimasi biaya restok dari `RestockReportService::summary()`, dan jumlah stok negatif sebagai peringatan merah kalau ada). Grafik pergerakan stok per hari **ditunda** (butuh hitungan baru).

Keputusan:
- **Stok negatif bukan bagian grafik 2.** Barang bisa negatif sekaligus di bawah minimum, jadi bagiannya tumpang tindih; tampilkan sebagai angka peringatan terpisah.
- **Tabel "Nilai stok jual per kategori/merek" dihapus dari tab Posisi stok** (digantikan grafik 3): hapus prop `groups` dan `group_per_page_options` di `ReportController::stock()`, `groupingOptions`/tabel/paginasinya di `Stock.vue`, dan pindahkan tes grup di `StockReportTest` ke route Ringkasan.
- **Nilai bawaan dipakai, tetapi dijelaskan di layar.** Meter (4) memakai 60 hari; batang restok (5) memakai pengaturan bawaan tab Perlu restok (penjualan 30 hari terakhir, stok dianggap cukup 14 hari). Di bawah tiap grafik tulis penjelasannya dan beri tautan ke tab Tidak terjual / Perlu restok untuk mengubahnya. Ambil nilai dari konstanta `IdleStockService::DEFAULT_DAYS`, `RestockReportService::WINDOW_OPTIONS[0]`, `COVER_OPTIONS[0]`, jangan ditulis ulang.
- Semua nilai stok adalah estimasi (stok × harga beli terakhir); peringatan "N barang belum punya harga beli" tetap ditampilkan.
- Tanpa izin baru (`reports.view`), tanpa library baru. Pakai ulang `PieChart` dan `RankedBars`; komponen baru yang dibutuhkan: batang bersegmen (grafik 2, satu keseluruhan ≤ 6 bagian, dengan legenda) dan meter (grafik 4, satu rasio terhadap total, bukan pie dua bagian).
- Tiap bagian dimuat sebagai prop tertunda (deferred) dalam grup sendiri, seperti halaman laporan lain, supaya halaman tampil seketika.
- Menu samping "Laporan Stok" sekarang menuju `stock_report_url` (Posisi stok). Supaya mendarat di Ringkasan: tambahkan kunci URL baru untuk Ringkasan, arahkan menu ke situ, dan biarkan `tenant.reports.stock` tetap Posisi stok (menghindari memindahkan banyak tes). Periksa juga penanda menu aktif di `StoreLayout.vue`.

## Perlu dicek manual / belum diverifikasi

- Excel rinci **setahun di server Hostinger**: di laptop dengan data buatan (75 nota per hari, 27.450 nota, 96 ribu baris barang) selesai 9,1 detik dengan memori datar (puncak 82 MB di bawah batas 512 MB), berkas 3,5 MB; cek di hosting yang lebih lambat (batas waktu skrip dan gateway). Skrip mengangkat batas waktu ke 300 detik lewat `set_time_limit` bila fungsinya tersedia.
- PDF rinci dibatasi **31 hari** (`SalesDetailReportService::MAX_SHEET_DAYS`): data sebulan 1,0 detik dan +46 MB, tetapi sekitar 230 halaman (perkiraan, belum diukur di browser). Cek jumlah halaman sebenarnya dengan data nyata dan putuskan apakah 31 hari terlalu panjang untuk kertas (mis. 7 atau 14 hari).
- Nomor halaman "Hal. X dari Y" di PDF rinci memakai CSS margin box (Chrome/Edge versi baru). Cek apakah muncul di printer toko.
- Efek hover pada tab baru belum terlihat langsung di tangkapan layar; cek dengan kursor.
- `composer require openspout/openspout` menampilkan 9 advisori keamanan pada 6 paket; belum diperiksa (`composer audit`).
- Halaman penjualan dan komponen grafik sudah dicoba di browser headless dengan data contoh, belum dengan data asli.

## Catatan teknis

- Verifikasi tampilan tanpa pane browser: lihat memori `reference-vue-verification-harness` (harness vite lib + headless Edge). Jebakan yang pernah kena: gaya `scoped` induk menempel ke elemen akar komponen anak, jadi beri kelas komponen anak awalan unik.
- Jangan commit otomatis; commit hanya saat diminta. Cek cabang aktif (`git branch --show-current`) sebelum commit. Saat ini cabang kerja adalah `main`.
