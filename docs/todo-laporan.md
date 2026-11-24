# TODO — Laporan penjualan dan laporan stok

Terakhir diperbarui: 24 Nov 2026. Commit di `main`: `5f9d119`, `8b9be09`, `97185d1`, `8377845`, `ec40769` (tab Retur & void). Tab Ringkasan laporan stok ada di commit terbaru di `main`. Belum ada yang di-push.

## Laporan penjualan

### Sudah selesai

- **Tab Retur & void** (`reports/sales/returns`, 24 Nov): nasib barang retur dan barang paling sering diretur (dipindah dari Ringkasan), ditambah daftar retur disetujui (nomor, nota asal, penyelesaian, barang, alasan, nilai) dan daftar nota void (tanggal nota, tanggal void, alasan, total), terbaru dulu, dipaginasi di server (10/25/50) dengan jumlah dan nilai seluruh periode di judulnya. Semua kolom kedua daftar bisa diurutkan (diurutkan di server, berlaku lintas halaman; kolom teks mulai A–Z, tanggal dan nilai mulai terbaru/terbesar; yang sama nilainya tetap terbaru dulu pada urutan menurun). Siapa yang menangani retur atau void tidak ditampilkan. Pembersihan ikut beres: Ringkasan tidak lagi menghitung `return_details`, `inheritAttrs` dihapus, tes `return_details` pindah ke rute baru, dan paginasi koleksi dipindah ke trait `PaginatesReportRows` agar dipakai bersama.
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
| Retur & void | selesai |

**Tab Pembayaran dicoret.** Isinya sudah tertutup: jumlah per metode ada di pie Ringkasan; transfer/QRIS/EDC dengan nomor referensi ada di Daftar nota (filter metode, buka baris) dan di Excel rinci (kolom per metode dan kolom referensi).

### Yang belum dikerjakan

**Tab opsional (belum diputuskan):** Jam & hari ramai; Pengiriman (nota diantar yang belum sampai, COD belum disetor); Antar toko (Super Admin).

## Laporan stok

### Sudah selesai

- **Tab Ringkasan** (`reports/stock`, 24 Nov): tab pertama, hanya grafik. Tab sekarang: Ringkasan, Posisi stok, Perlu restok, Tidak terjual, Pergerakan. Isinya:
  - Angka utama: nilai stok jual (estimasi), estimasi biaya restok, dan jumlah stok negatif (kartu merah, hanya bila ada).
  - Pie uang tertanam di stok (stok jual, dead stock, retur menunggu diperiksa; nilai modal).
  - Batang bersegmen kondisi barang aktif: sehat, menipis, habis, dengan tautan ke Posisi stok yang sudah tersaring.
  - Pie stok jual per kategori atau merek (5 teratas + Lainnya, tombol Kategori/Merek), bisa diurutkan menurut **Nilai** (modal) atau **Jumlah stok** (enum `StockGroupMetric`). Jumlah stok hanya dijumlahkan **dalam satu satuan dasar** yang dipilih lewat dropdown (kg, sak, pcs tidak bisa dijumlahkan): barang bersatuan lain tidak ikut, bawaannya satuan yang paling banyak dipakai barang berstok (`StockReportService::stockUnits()`), dan nilai di bawah nama adalah nilai modal barang-barang satuan itu. Pada mode Nilai, `in_stock_quantity` dan `quantity_total` bernilai `null` karena akan menjumlahkan satuan berbeda.
  - Meter stok mengendap (porsi nilai stok jual yang tidak terjual 60 hari).
  - **Paling cepat habis:** batang peringkat 5 barang yang paling banyak terjual dalam 30 hari terakhir (nota selesai, retur belum dikurangkan), dalam satu satuan dasar yang dipilih lewat dropdown dengan alasan yang sama (`StockReportService::bestSellers()`).
  - **Perkiraan stok habis:** bagian terpisah berisi 5 barang dengan sisa hari stok paling sedikit (`RestockReportService::mostUrgent()`), memakai penjualan 30 hari dan stok cukup 14 hari seperti tab Perlu restok.
- **Nilai bawaan dijelaskan di layar:** meter memakai 60 hari tanpa penjualan (`IdleStockService::DEFAULT_DAYS`); batang restok memakai penjualan 30 hari terakhir dan stok dianggap cukup 14 hari (`RestockReportService::WINDOW_OPTIONS[0]`, `COVER_OPTIONS[0]`). Di bawah tiap grafik ada tautan ke tab Tidak terjual / Perlu restok untuk mengubahnya.
- **Rumus kondisi barang diperbaiki dari rencana awal.** "Di bawah minimum" hanya berlaku bila barang punya minimum (> 0), jadi barang habis tanpa minimum tidak termasuk di dalamnya dan rumus `below_minimum − out_of_stock` bisa salah. Kini `StockReportService::summary()` memberi `low_stock_count` (di bawah minimum, belum habis) dan `healthy_count` (sisanya) yang tidak tumpang tindih dengan `out_of_stock_count`. Stok negatif tetap angka terpisah.
- **Rute dipindah:** Ringkasan memakai `reports/stock` (nama `tenant.reports.stock`, kunci URL `stock_report_url`, jadi menu samping dan penanda menu aktif tidak perlu diubah), dan Posisi stok pindah ke `reports/stock/positions` (`tenant.reports.stock.positions`, `stock_positions_url`).
- **Tabel "Nilai stok jual per kategori/merek" dihapus dari Posisi stok** (digantikan pie); tes grupnya pindah ke `StockSummaryReportTest`. `StockSummaryRequest` baru; `StockReportRequest` tinggal untuk tabel posisi. `RestockReportService::mostUrgent()` baru.
- **Komponen baru:** `SegmentedBar.vue` (batang bersegmen tinggi, persen di dalam blok bila muat, legenda nama dan jumlah) dan `MeterBar.vue` (satu rasio; trek biru muda). `PieChart` punya prop `emptyText`. Warna kondisi memakai warna status tetap (hijau, kuning, merah) dengan label di legenda.
- Grafik pergerakan stok per hari **ditunda** (butuh hitungan baru).

## Perlu dicek manual / belum diverifikasi

- Excel rinci **setahun di server Hostinger**: di laptop dengan data buatan (75 nota per hari, 27.450 nota, 96 ribu baris barang) selesai 9,1 detik dengan memori datar (puncak 82 MB di bawah batas 512 MB), berkas 3,5 MB; cek di hosting yang lebih lambat (batas waktu skrip dan gateway). Skrip mengangkat batas waktu ke 300 detik lewat `set_time_limit` bila fungsinya tersedia.
- PDF rinci dibatasi **31 hari** (`SalesDetailReportService::MAX_SHEET_DAYS`): data sebulan 1,0 detik dan +46 MB, tetapi sekitar 230 halaman (perkiraan, belum diukur di browser). Cek jumlah halaman sebenarnya dengan data nyata dan putuskan apakah 31 hari terlalu panjang untuk kertas (mis. 7 atau 14 hari).
- Nomor halaman "Hal. X dari Y" di PDF rinci memakai CSS margin box (Chrome/Edge versi baru). Cek apakah muncul di printer toko.
- Efek hover pada tab baru belum terlihat langsung di tangkapan layar; cek dengan kursor.
- `composer require openspout/openspout` menampilkan 9 advisori keamanan pada 6 paket; belum diperiksa (`composer audit`).
- Halaman penjualan, Ringkasan stok dan komponen grafik sudah dicoba di browser headless dengan data contoh, belum dengan data asli.
- Ringkasan stok: cek dengan data asli apakah blok kuning (menipis) dan merah (habis) di batang kondisi terbaca bila kecil (persen hanya dicetak di blok selebar 9% ke atas; sisanya lewat legenda dan tooltip).

## Catatan teknis

- Verifikasi tampilan tanpa pane browser: lihat memori `reference-vue-verification-harness` (harness vite lib + headless Edge). Jebakan yang pernah kena: gaya `scoped` induk menempel ke elemen akar komponen anak, jadi beri kelas komponen anak awalan unik.
- Jangan commit otomatis; commit hanya saat diminta. Cek cabang aktif (`git branch --show-current`) sebelum commit. Saat ini cabang kerja adalah `main`.
