# Estimasi Biaya & Waktu — POS Multi-Toko (v0.4, Fase 1a + 1b)

> Perkiraan dari sudut pandang programmer/software house di Indonesia (harga pasar 2026). **Bukan penawaran.** Tarif per hari adalah asumsi saya dari kisaran pasar umum, belum diverifikasi ke sumber tertentu. Rujukan scope: [`analisis-sistem.md`](analisis-sistem.md) v0.4. **Hanya Fase 1** (Fase 2 tidak dihitung).

> **Prioritas terbaru (v0.7): R1 Inti = operasional harian toko** (tambah toko, barang & harga, stok opname, kasir, buka/tutup kas, pembayaran, nota, retur, surat jalan, tutup hari, pengeluaran; **tanpa penerimaan**). Estimasinya ada di **bagian 7**: ±128–174 hari kerja, titik tengah ±151 (≈ Rp 60–90 juta pada tarif Rp 400–600 rb/hari). Bagian 1–2 sudah memuat modul baru v0.7 sebagai bagian Fase 1a; bagian 6 adalah catatan historis "R1 Hemat".

## 0. Ringkasan perubahan v0.4

Fase 1 dipecah dua: **1a = go-live online** dan **1b = offline** (dikerjakan sesudah go-live). Total gabungan tetap ±271 hari kerja, tetapi **go-live online datang jauh lebih cepat**.

| Perubahan | Dampak (hari kerja) |
|---|---|
| **Pembelian dari supplier** sebagai modul terpisah (`purchases`) + penerimaan merujuk pembelian full/partial | +10–12 (modul 8–10, penyesuaian penerimaan +2) |
| **Kredit & hutang dihapus** (pelanggan, penjualan hutang, pembayaran hutang, piutang) | −8–10 (modul pelanggan) −1 (kasir) −1 (retur) −1–2 (migrasi) |
| **Offline dipindah ke Fase 1b** (35–48 hari kerja keluar dari go-live pertama) | −35–48 dari 1a; masuk 1b |
| Prinsip **offline-ready** tetap dikerjakan sejak awal (idempotensi, nomor per register, snapshot, delta API) | +3–5 di 1a |

## 1. Estimasi Effort (hari kerja / *man-day*, MD)

Asumsi: Laravel 12 API + Vue 3/**Quasar** (PWA-ready); go-live awal 1 toko (Mitra Utama), arsitektur multi-toko; Hostinger Cloud; nota via browser print.

### Fase 1a — Go-live online

| # | Modul | Cakupan | MD (min–maks) |
|---|-------|---------|---------------|
| 1 | Fondasi proyek | Repo, Laravel + Quasar scaffold, auth, role, layout, CI dasar | 6–8 |
| 2 | Multi-toko (tenancy) | Master toko, provisioning DB, ganti koneksi, `tenants:migrate`, login routing per role, user/akses | 8–11 |
| 3 | Master pendukung per toko | Kategori, merek, satuan, supplier per toko; kebijakan satuan boleh dibeli/dijual; salin barang dari toko lain via Excel (**tanpa katalog pusat**, D27) | 4–6 |
| 4 | Barang & harga (toko) | Multi-satuan, konversi, harga nominal/margin (tanpa pembulatan), histori harga, persen untung (margin) | 12–16 |
| 5 | Import Excel | Template, validasi, pratinjau (dipakai juga untuk migrasi) | 3–4 |
| 6 | Gudang, kartu stok, transfer antar-gudang | Ledger, saldo cache, row lock, rebuild, uji konkurensi, transfer | 8–10 |
| 7 | **Pembelian dari supplier** | Nota pembelian (`purchases`/`purchase_items`), status draft→ordered→partially_received→received/closed, tutup sisa, laporan pembelian per supplier | 8–10 |
| 8 | Penerimaan kontainer | Multi-supplier, tanggal kirim/sampai/bongkar, biaya kontainer, **merujuk pembelian full/partial**, posting/batal, update status pembelian & harga beli | 12–15 |
| 9 | Stok opname | Per gudang, cakupan, snapshot, hitung multi-satuan, posting | 6–8 |
| 10 | Kasir online (inti) | Layar POS, pembayaran campur (tunai/transfer/QRIS/EDC), simpan atomik, penomoran per register, riwayat, void, log batal | 12–16 |
| 11 | Shift & kas | Buka/tutup (modal tetap), setoran, kas masuk/keluar, laporan Z, rekap & approval selisih | 7–10 |
| 12 | Retur | Refund/tukar (nilai retur dipotong otomatis), disposisi dead stock, approval Admin, kas keluar, bukti retur | 8–11 |
| 13 | Persiapan offline-ready | `client_uuid` + endpoint idempoten, nomor per register, snapshot, API delta, pemisahan lapisan akses data di frontend | 3–5 |
| 14 | Nota | Template + pratinjau + CSS 58/80 mm/A4 + pengaturan | 3–4 |
| 15 | Laporan dasar | Penjualan bersih, laba (FIFO), nilai persediaan, stok per gudang, kartu stok, pembelian, penerimaan, opname, shift; ekspor | 6–8 |
| 16 | Audit | `laravel-auditing` di pusat & toko, layar Audit Trail | 3–5 |
| 17 | UI/UX | Penyempurnaan dari wireframe, konsistensi | 5–7 |
| 18 | Migrasi data lama | ETL hanya Mitra Utama: barang/merek, template opname stok awal, transaksi (de-dup + harga turunan + hanya baris Mitra Utama pada nota campuran), hutang lama diekspor Excel, Excel konversi, rekonsiliasi, 2× dry-run, cutover | 14–18 |
| 19 | QA & UAT | Uji isolasi toko, konkurensi stok, FIFO, migrasi | 10–15 |
| 20 | Deploy & operasional | Hostinger Cloud, queue worker/cron, backup, dokumentasi, pelatihan | 4–6 |
| 21 | Cek stok lintas toko (pusat) | Query paralel ke DB tiap toko (timeout, toko gagal tidak menggagalkan hasil), per toko × gudang, ekspor Excel | 3–4 |
| 22 | Costing FIFO (layer persediaan) | Layer + alokasi, konsumsi FIFO row lock, pemulihan void/retur, pemindahan layer, layer defisit, nilai persediaan, `stock:rebuild` + uji invarian | 12–16 |
| 23 | **Surat jalan** | Dokumen pengantaran dari nota, item/qty kirim bertahap, status antar/pulang, cetak tanpa harga | 7–9 |
| 24 | **Pengeluaran** | Kategori, pengeluaran laci/lainnya, kaitan shift, laporan | 3–4 |
| 25 | **Tutup hari** | Checklist, snapshot laporan harian, penguncian, buka kembali | 4–6 |
| | **Subtotal 1a** | | **171–232** |
| | Cadangan risiko (±15 %) | Revisi layout nota, perubahan kecil, data kotor | 26–35 |
| | **Total Fase 1a** | | **≈ 197–267 MD** (titik tengah **±232 MD**) |

### Fase 1b — Offline (sesudah go-live online)

| # | Modul | Cakupan | MD (min–maks) |
|---|-------|---------|---------------|
| 23 | Offline PWA, sinkronisasi & backup lokal | Service worker, IndexedDB, outbox, sinkron delta/batch idempoten, `sync_issues`, backup file (File System Access), impor file backup, kunci satu-tab, uji offline otomatis | 35–48 |
| | Cadangan risiko (±15 %) | | 5–7 |
| | **Total Fase 1b** | | **≈ 40–55 MD** (titik tengah **±48 MD**) |

**Total 1a + 1b ≈ 237–322 MD (±280 MD)** — naik ±19 MD karena surat jalan, pengeluaran, dan tutup hari; go-live online tidak menunggu offline.

### Di luar Fase 1 (tidak dihitung, hanya indikasi)
| Item | MD |
|---|---|
| Pelanggan, kredit & piutang (master, penjualan hutang, pembayaran + alokasi, umur hutang) — **dihapus dulu** | 8–10 |
| Laporan penjualan overall lintas toko (akuntansi, role `accounting`) | 8–12 |
| Transfer antar-toko (dokumen pusat; cek stok lintas toko sudah di Fase 1) | 8–11 |
| Snapshot stok ke pusat (bila toko banyak / laporan overall) | 5–8 |
| Alokasi biaya kontainer ke modal (landed cost; data sudah dicatat) | 5–8 |
| Pembayaran ke supplier (utang usaha) | 6–9 |
| Penanganan dead stock lanjutan (jual diskon / musnah / retur supplier) | 6–9 |
| Diskon | 3–5 |
| Cetak langsung ESC/POS | 4–8 |
| PPN | 3–4 |
| Antrean void/retur offline | 4–6 |
| Input nota susulan oleh Admin (cadangan sebelum offline ada; opsional) | ±2 |
| Server localhost penuh sebagai fallback offline | 25–40 |

---

## 2. Biaya per Skenario

Tarif acuan (asumsi): **freelancer menengah ±Rp 600–800 rb/MD**, **freelancer senior ±Rp 900 rb–1,2 jt/MD**, **software house ±Rp 1,3–2 jt/MD** (termasuk PM, QA, margin). Satu orang efektif ±19 MD/bulan.

### Fase 1a — go-live online
| Skenario | Komposisi | Durasi realistis | Biaya 1a |
|---|---|---|---|
| **A. Freelancer menengah (solo)** | 1 full-stack, ±232 MD | ±12 bulan | **Rp 140–185 juta** |
| **B. Freelancer senior (solo)** | 1 full-stack berpengalaman, ±197 MD | ±10 bulan | **Rp 175–235 juta** |
| **C. Dua freelancer (BE + FE)** | Paralel, ±255 MD total (koordinasi) | ±6,5 bulan | **Rp 155–205 juta** |
| **D. Software house (tim)** | BE + FE + PM/QA paruh waktu, ±232 MD | ±5,5–7 bulan | **Rp 300–465 juta** |
| **E. Developer internal (gaji)** | 1 developer menengah, gaji ±Rp 8–15 jt/bulan + beban perusahaan | ±12 bulan | **Rp 95–215 juta** (biaya gaji selama proyek) |

### Fase 1b — offline (tambahan)
| Skenario | MD | Durasi | Biaya 1b |
|---|---|---|---|
| A. Freelancer menengah | ±48 | ±2,5 bulan | **Rp 30–40 juta** |
| B. Freelancer senior | ±41 | ±2 bulan | **Rp 35–50 juta** |
| C. Dua freelancer | ±53 | ±1,5 bulan | **Rp 30–45 juta** |
| D. Software house | ±48 | ±1,5–2 bulan | **Rp 60–95 juta** |
| E. Developer internal | ±48 | ±2,5 bulan | **Rp 20–45 juta** |

**Cara membaca:** skenario C (BE + FE paralel) tetap paling seimbang; go-live online sekitar 6,5 bulan, offline menyusul 1,5 bulan kemudian. Satu orang solo membutuhkan sekitar 12 bulan hanya untuk 1a.

### Rilis bertahap
| Milestone | Isi | MD kasar (sebelum cadangan) |
|---|---|---|
| M1 | Tenancy, login/role, master barang per toko, barang & harga, import, audit, UI/UX | ±41–57 |
| M2 | Gudang, kartu stok, **costing FIFO**, **pembelian**, penerimaan (merujuk pembelian), opname, transfer gudang, cek stok lintas toko | ±49–63 |
| M3 | Kasir online + shift & kas + nota + void + laporan dasar + persiapan offline-ready | ±31–43 |
| M4 | Retur + migrasi data (Mitra Utama) + QA/UAT + deploy → **go-live online** | ±36–50 |
| M5 (Fase 1b) | **Offline PWA, sinkronisasi, backup lokal** | ±35–48 |

---

## 3. Biaya Berjalan (di luar pengembangan)

| Komponen | Perkiraan |
|---|---|
| Hosting Hostinger Cloud (awal 1 toko, 1 server) | Menyesuaikan plan; **perlu dipastikan boleh `CREATE DATABASE`, cron, dan queue worker** — jika tidak, VPS atau provisioning semi-manual lewat hPanel/API (+2–3 hari) |
| Domain + SSL (PWA wajib HTTPS) | Rp 150–300 rb/tahun |
| Backup server (eksternal/object storage) | Rp 50–200 rb/bulan |
| Pemeliharaan & dukungan | **10–20 % nilai proyek per tahun** |
| Perangkat kasir (di luar proyek) | Printer thermal ±Rp 0,8–2 jt, scanner barcode ±Rp 0,3–1 jt, PC kasir |

## 4. Risiko Biaya & Dampak Menunda Offline

**Menaikkan:** kualitas data lama lebih buruk dari perkiraan (konversi 137 barang, baris ganda, harga manual) · prototipe offline menunjukkan backup file tidak memadai (→ server localhost, +25–40 MD) · retur/void offline ternyata diperlukan · layout nota berubah-ubah · fitur backlog dimajukan · keputusan lambat.

**Menurunkan:** opname fisik stok awal siap tepat waktu · memakai `stancl/tenancy`, Quasar, Dexie, Laravel Auditing · pilot 1 toko dulu · 1 kasir per toko.

**Menunda offline — untung dan ruginya**
- *Untung:* go-live online lebih cepat; offline diuji di toko yang sudah berjalan; scope M1–M4 lebih tenang.
- *Rugi:* selama 1a, **internet mati = kasir tidak bisa memproses nota**. Mitigasi murah: tethering ponsel sebagai jalur darurat, catat manual lalu Admin input nota susulan (±2 hari kerja, opsional).
- *Biaya retrofit* kecil karena prinsip offline-ready (±3–5 hari) sudah dikerjakan sejak awal; tanpa itu offline kelak bisa +15–25 hari.

## 5. Rekomendasi
1. **Fase 0 (2 minggu):** konfirmasi pertanyaan 10.2, freeze ERD & wireframe, profil data lama lebih dalam (sudah dimulai).
2. Kerjakan **1a dulu**, pilot di Mitra Utama; mulai Fase 1b setelah toko stabil ±2–4 minggu.
3. **Prototipe teknis offline (3–5 hari)** tepat sebelum 1b, di PC kasir nyata.
4. **Opname fisik stok awal** dijadwalkan sejak awal proyek (±1.759 barang Mitra Utama) — bukan di akhir.
5. Termin per milestone (mis. 15 % kickoff · 20 % M1 · 20 % M2 · 15 % M3 · 20 % M4/go-live · 10 % setelah 1b).
6. Pastikan **kode sumber, dokumentasi, dan akses server** diserahkan ke Prolind.

---

## 6. Opsi menekan biaya (target di bawah ±Rp 100 juta)

Tiga tuas yang bisa dipakai sendiri-sendiri atau digabung: **(a) cakupan rilis pertama**, **(b) siapa yang membangun**, **(c) tarif per hari**. Estimasi di bawah adalah perkiraan saya, bukan penawaran.

### 6.1 Rilis bertahap: R1 Hemat → R2 → R3
| Rilis | Isi | MD (sebelum cadangan) |
|---|---|---|
| **R1 Hemat (go-live Mitra Utama)** | Tenancy minimal (toko dibuat lewat perintah artisan, bukan layar lengkap), user & role, barang + multi-satuan + konversi + harga margin + histori harga, import Excel, 1 gudang + kartu stok, **pembelian**, **penerimaan kontainer** (multi-supplier, biaya kontainer), opname, kasir online, shift sederhana (buka/tutup + selisih, tanpa alur approval), nota, laporan minimal, audit (tanpa layar), persiapan offline-ready, migrasi **barang + stok awal saja**, QA, deploy. Modal memakai **harga beli terakhir** (tanpa FIFO) | **±112–151** (+ cadangan 10–15 % → **±125–170**) |
| R2 (menyusul) | Costing FIFO (+9–11 di atas versi sederhana), retur refund/tukar + dead stock (8–11), katalog pusat + publish ke toko (12–16), transfer antar-gudang (2–3), cek stok lintas toko (3–4), approval selisih kas + rekap (2–3), layar audit (1–2), laporan lengkap (2–3), migrasi transaksi lama (8–10), polish UI (2) | ±49–65 |
| R3 (Fase 1b) | Offline PWA + sinkronisasi + backup lokal | ±35–48 |

R1 + R2 ≈ cakupan Fase 1a penuh (165–223 MD); jadi biayanya **dicicil**, bukan dihilangkan. Yang benar-benar terhemat hanya bila sebagian R2 ternyata tidak pernah dibutuhkan.

**Syarat agar menunda tidak mahal:** `uuid` di semua tabel katalog, `client_uuid` idempoten, nomor nota per register, dan nilai persediaan disimpan sebagai layer sejak R1 (hanya sumber modalnya yang disederhanakan). Peralihan "harga beli terakhir" → FIFO dilakukan dengan membuat layer awal dari nilai persediaan saat itu.

### 6.2 Sensitivitas tarif per hari
| Tarif / MD | R1 Hemat (±147 MD) | Fase 1a penuh (±223 MD) | + offline (±48 MD) |
|---|---|---|---|
| Rp 400 rb | ±Rp 59 juta | ±Rp 89 juta | ±Rp 19 juta |
| Rp 500 rb | ±Rp 74 juta | ±Rp 112 juta | ±Rp 24 juta |
| Rp 600 rb | ±Rp 88 juta | ±Rp 134 juta | ±Rp 29 juta |
| Rp 800 rb | ±Rp 118 juta | ±Rp 178 juta | ±Rp 38 juta |

Tarif Rp 600–800 rb/hari di bab 2 adalah asumsi saya untuk freelancer menengah; di luar kota besar atau untuk developer junior-menengah, Rp 400–500 rb tidak mustahil. **Mintalah 2–3 penawaran** dengan rincian per modul dan bandingkan dengan tabel di atas.

### 6.3 Siapa yang membangun
| Opsi | Catatan |
|---|---|
| Developer internal Prolind | Biaya tunai tambahan bisa mendekati nol bila gaji sudah ada; yang dihitung adalah waktu (±8–10 bulan untuk R1+R2 oleh 1 orang) |
| Pengembangan dibantu AI (mis. Claude Code) oleh developer internal/freelancer | **Perkiraan saya**: bagian CRUD, form, migrasi, dan test bisa 20–35 % lebih cepat; logika inti (FIFO, stok, posting, retur, sinkron) tetap perlu desain, review, dan pengujian manual. Dokumen analisis ini sudah berfungsi sebagai spesifikasi |
| Freelancer / software house | Harga bergantung tarif (6.2); minta rincian per modul dan serah terima kode |

### 6.4 Rekomendasi
Mulai **R1 Hemat** dengan developer internal atau freelancer bertarif Rp 400–600 rb/hari → **±Rp 60–100 juta** untuk toko Mitra Utama yang sudah bisa jualan, beli, terima kontainer, dan opname. Putuskan R2 dan R3 sesudah R1 dipakai 2–4 minggu, berdasarkan kebutuhan nyata (mis. seberapa sering retur dan internet mati).

> Catatan v0.5: "R1 Hemat" di atas **digantikan** oleh **R1 Inti** yang lebih sempit (bagian 7): hanya input stok barang + penjualan.

---

## 7. R1 Inti — operasional harian toko (prioritas v0.7)

Cakupan rinci: [`analisis-sistem.md`](analisis-sistem.md) bagian 0.3. Peta menu R1: **Master** (tambah toko) · **Barang** (daftar barang per toko, harga per satuan + konversi) · **Stok** (stok opname) · **Penjualan** (kasir, buka/tutup kas, pembayaran, nota, retur, surat jalan, tutup hari, pengeluaran). **Tanpa:** pembelian, penerimaan kontainer, FIFO (modal = harga beli terakhir), transfer/multi-gudang, cek stok lintas toko, migrasi transaksi lama, offline.

| # | Modul | Cakupan | MD (min–maks) |
|---|-------|---------|---------------|
| 1 | Fondasi | Laravel + Quasar scaffold, auth, role (admin/gudang/kasir), layout | 6–8 |
| 2 | Tenancy + Master Toko | Pusat (users, stores), **tambah toko** (provisioning DB otomatis + progres), user & akses minimal, routing login per role | 8–11 |
| 3 | Barang & harga | Kategori, merek, satuan, barang per toko, multi-satuan + **konversi**, **harga per satuan** (nominal/margin), histori harga, satuan boleh dibeli/dijual | 12–16 |
| 4 | Import Excel | Barang + stok awal (template, validasi, pratinjau) | 4–5 |
| 5 | Stok | `stock_movements` + `stocks` (2 gudang), StockService, kartu stok | 5–6 |
| 6 | **Stok opname** | Cakupan semua/kategori/barang terpilih, snapshot, hitung multi-satuan, import hasil hitung, harga beli opsional, review selisih + alasan, posting Admin | 7–9 |
| 7 | Kasir online + pembayaran | Cari/scan, barang + satuan, keranjang, bayar campuran, simpan atomik, nomor per register, riwayat nota, batal sebelum bayar | 12–16 |
| 8 | Nota | Template + pratinjau + cetak browser (58/80 mm/A4) | 3–4 |
| 9 | **Buka/tutup kas (shift)** | Modal tetap, setoran, selisih + approval Admin, laporan Z, rekap shift | 7–10 |
| 10 | **Retur** | Refund/tukar (nilai retur dipotong otomatis), disposisi dead stock, approval Admin | 8–11 |
| 11 | **Surat jalan** | Dari nota, kirim bertahap, status antar/pulang, cetak tanpa harga | 7–9 |
| 12 | **Pengeluaran** | Kategori, sumber laci/lainnya, kaitan shift | 3–4 |
| 13 | **Tutup hari** | Checklist, snapshot laporan harian, penguncian, buka kembali | 4–6 |
| 14 | Void oleh Admin | Pembalikan sederhana + alasan + audit | 2–3 |
| 15 | Laporan minimal | Stok, kartu stok, selisih opname, penjualan, retur, pengeluaran, laporan harian, laba sederhana | 4–6 |
| 16 | Audit | `laravel-auditing` aktif (tanpa layar) | 1–2 |
| 17 | Persiapan offline-ready | `client_uuid`, idempotensi, snapshot harga | 2–3 |
| 18 | UI/UX | Penyempurnaan dari wireframe | 4–5 |
| 19 | QA & UAT | Konkurensi stok, shift/kas/tutup hari, retur, surat jalan, import | 9–13 |
| 20 | Deploy | Hostinger, backup, dokumentasi singkat | 3–4 |
| | **Subtotal R1** | | **111–151** |
| | Cadangan risiko (±15 %) | | 17–23 |
| | **Total R1 Inti** | | **≈ 128–174 MD** (titik tengah **±151 MD**) |

**Dibanding v0.6 (±110 MD):** naik ±41 MD karena menu baru (buka/tutup kas, retur, surat jalan, pengeluaran, tutup hari, tambah toko) dan turun ±10–13 MD karena **penerimaan keluar dari R1**.

### Biaya R1 Inti menurut tarif (asumsi, bukan penawaran)
| Tarif / MD | Rentang 128–174 MD | Titik tengah (±151 MD) |
|---|---|---|
| Rp 400 rb | Rp 51–70 juta | ±Rp 60 juta |
| Rp 500 rb | Rp 64–87 juta | ±Rp 76 juta |
| Rp 600 rb | Rp 77–104 juta | ±Rp 91 juta |
| Rp 800 rb | Rp 102–139 juta | ±Rp 121 juta |

**Durasi:** satu developer ±7–9 bulan; dua developer (BE + FE) ±4,5–5 bulan.

### Jika ingin menekan di bawah ±Rp 100 juta
R1 bisa dipecah lagi tanpa merusak desain (semua modul berdiri sendiri):
| Opsi | Dikeluarkan dari R1 | Penghematan | R1 jadi |
|---|---|---|---|
| **R1a** | Retur (8–11) + surat jalan (7–9) ke R1b | ±15–20 MD (sebelum cadangan) | ±105–156 MD dengan cadangan (titik tengah ±130 MD) ≈ **Rp 52–78 juta** pada tarif Rp 400–600 rb |
| R1a + tanpa tutup hari | + tutup hari (4–6) memakai laporan Z per shift | ±4–6 MD tambahan | ±98–152 MD dengan cadangan (titik tengah ±125 MD) ≈ Rp 50–75 juta |
Risiko: tanpa retur, pengembalian barang hanya lewat opname/void; tanpa surat jalan, pengantaran dicatat manual.

### Sesudah R1
| Rilis | Isi | MD kasar |
|---|---|---|
| **R2** | Pembelian + penerimaan kontainer (multi-supplier, biaya kontainer), FIFO, transfer antar-gudang, cek stok lintas toko, salin barang antar toko (Excel), supplier, migrasi transaksi lama, layar Audit Trail, laporan lengkap | ±70–95 (dengan cadangan) |
| **R3** | Offline PWA + sinkronisasi + backup lokal | ±40–55 |

R1 + R2 ≈ Fase 1a penuh (bagian 1: 197–267 MD).

**Risiko menunda:** tanpa penerimaan, barang masuk dicatat lewat opname parsial (lebih manual, tanpa dokumen supplier/kontainer); tanpa FIFO, laba memakai harga beli terakhir (cenderung tinggi saat harga beli naik); tanpa pembelian, pesanan supplier belum tercatat. Semuanya ditandai estimasi di data dan tidak menghalangi R2 menyusul.
