# STEP 5 — Laporan pengujian katalog dan Pricing Engine

Tanggal laporan: 10 Oktober 2026. Acuan: [AUDIT_ERP_TGM.md](AUDIT_ERP_TGM.md), [REVIEW_FONDASI_LARAVEL.md](REVIEW_FONDASI_LARAVEL.md), dan [STEP4_FONDASI_SAAS.md](STEP4_FONDASI_SAAS.md).

**Implementasi STEP 5 telah diuji dan diajukan untuk review. Validasi bisnis baru mencakup dua contoh harga yang dikonfirmasi pengguna.** Hasil tes teknis tidak membuktikan bahwa seluruh kategori dan aturan operasional ERP TGM sudah setara.

- Branch: `step-5/catalog-pricing`, tujuan `main`.
- Baseline: `57725ba03bb861b008d9b8ce2837d9686e03dedb`, setelah PR STEP 4 di-merge.
- Commit kode yang diuji: [`2073c109dcae04e8aaefca82c5df04d616e04f03`](https://github.com/nizarsuryadi89-bit/erp-percetakan/commit/2073c109dcae04e8aaefca82c5df04d616e04f03).
- Pull Request: [#3 — STEP 5: validasi pricing TGM dan simpan riwayat perhitungan harga](https://github.com/nizarsuryadi89-bit/erp-percetakan/pull/3). Terbuka dan belum di-merge pada saat laporan disusun.
- Penyelesaian laporan ini hanya menambah dokumentasi; tidak mengubah kode yang telah diuji.

## 1. Ruang lingkup dan hasil implementasi

Katalog, matriks harga, serta kalkulator existing dipertahankan. STEP 5 melengkapi hasil perhitungan agar dapat diperiksa kembali setelah session berakhir atau master harga berubah.

| Komponen | Hasil STEP 5 |
| --- | --- |
| `CalculateProductPrice` | Payload versi 2 memisahkan ukuran input dan ukuran tagihan, mencatat satuan tagihan, basis/batas tier, pembulatan serta fingerprint harga publik. Lebih dari satu harga aktif yang cocok menghasilkan validation error. |
| `RecordProductQuote` | Menghitung ulang di server dan menyimpan hasil dalam transaksi dengan penguncian produk; akses POS diperiksa sebelum pencatatan. |
| `ProductQuote` | UUID, tenant, produk, pembuat, waktu, snapshot harga/spesifikasi dan snapshot HPP internal terpisah. Update melalui model ditolak. |
| Detail produk | Menampilkan 10 hasil tersimpan terbaru dan tautan masing-masing hasil. Flash quote versi lama diabaikan dengan aman. |
| Halaman hasil tersimpan | Menampilkan hasil historis tanpa menghitung ulang dari master; ukuran asli, ukuran tagihan dan satuan `m`/`m²` ditampilkan sesuai basis. |
| Akses | Tetap memakai entitlement POS, membership, permission dan lisensi. POS tidak memerlukan grant PRODUCTION untuk katalog/pricing. |

Sumber utama: [CalculateProductPrice.php](app/Actions/CalculateProductPrice.php), [RecordProductQuote.php](app/Actions/RecordProductQuote.php), [ProductQuote.php](app/Models/ProductQuote.php), [ProductQuoteController.php](app/Http/Controllers/ProductQuoteController.php), [ProductController.php](app/Http/Controllers/ProductController.php), [rincian quote](resources/js/components/product-quote-summary.tsx), dan [halaman quote](resources/js/pages/products/quote.tsx).

Snapshot adalah hasil simulasi historis. Belum ada penerimaan penawaran, order, invoice, pembayaran atau SPK yang mengikat harga tersebut. Setiap perhitungan menghasilkan UUID baru. Larangan update pada model bukan jaminan kebal perubahan melalui SQL langsung atau administrator database.

## 2. Laporan pengujian

### Lingkungan dan keselamatan data

Pengujian dilakukan di worktree terpisah `/private/tmp/erp-percetakan-step5-20261010`, dengan dependency lokal tersalin dan konfigurasi testing tersendiri. [Tests/TestCase.php](tests/TestCase.php) dan [phpunit.xml](phpunit.xml) membatasi pengujian ke SQLite `:memory:`. Migration/factory hanya berjalan pada database pengujian sementara.

Database proyek utama `/Volumes/zarnizar/herd_laravel/erp_percetakan` tidak dimigrasikan atau diisi fixture STEP 5. Source ERP TGM lama tidak diubah. Tidak ada deployment atau perubahan dependency.

### Hasil akhir yang tercatat

| Pemeriksaan | Hasil | Cakupan/batas bukti |
| --- | --- | --- |
| `php artisan test --compact` | **187 passed; 1.057 assertions**; 19,123 detik | Seluruh suite pada commit kode di atas, menggunakan database testing. |
| `php artisan test --compact tests/Feature/ProductCatalogTest.php tests/Feature/ProductQuoteSnapshotTest.php` | **84 passed; 362 assertions**; 7,854 detik | 63 kasus katalog existing dan 21 kasus snapshot baru, termasuk ekspansi dataset. Merupakan bagian dari 187 tes, bukan tambahan di luar jumlah tersebut. |
| PHP syntax lint | **Lulus, 126 file** | File PHP di `app`, `bootstrap`, `config`, `database`, `routes` dan `tests`, di luar cache. |
| `vendor/bin/pint --dirty --format agent` | **Lulus** | Format PHP diperbaiki sebelum commit, termasuk kalkulator dan urutan import route. |
| `node node_modules/typescript/bin/tsc --noEmit --incremental false` | **Lulus** | Pemeriksaan tipe frontend; bukan pengujian interaksi browser. |
| `npm run build` | **Lulus**; Vite 8.3.4, 2,34 detik | Manifest build mencakup halaman hasil tersimpan dan komponen rinciannya. |
| `git diff --check` | **Lulus** | Pemeriksaan whitespace perubahan implementasi. |
| Pemeriksaan kandidat commit | **Tidak ditemukan file sensitif/pola token yang diperiksa** | `.env`, database SQLite, backup, log, key dan dependency/build yang diabaikan tidak disertakan. Pemeriksaan ini bukan audit keamanan menyeluruh. |

Ringkasan log lokal yang menjadi bukti laporan:

```text
erp-step5-full-tests.log:
{"tool":"pest","result":"passed","tests":187,"passed":187,"assertions":1057,"duration_ms":19123}

erp-step5-pricing-tests.log:
{"tool":"pest","result":"passed","tests":84,"passed":84,"assertions":362,"duration_ms":7854}
```

Log lengkap berada di `/private/tmp/erp-step5-full-tests.log`, `/private/tmp/erp-step5-pricing-tests.log`, dan `/private/tmp/erp-step5-build.log` pada mesin pelaksana. File sementara ini bukan artefak CI permanen. Angka di atas adalah hasil eksekusi lokal, bukan klaim bahwa GitHub Actions telah menjalankan pemeriksaan yang sama. Suite tidak diulang untuk penambahan laporan Markdown karena kode tidak berubah.

### Kegagalan awal dan perbaikan

Putaran awal suite snapshot menghasilkan 12 passed dan 6 errors. Pemanggilan action langsung pada fixture menggunakan objek tenant yang belum memuat nilai default `is_active` dari database, sehingga akses ditolak 403. Fixture diperbaiki dengan `$tenant->refresh()`; guard otorisasi aplikasi tetap dipertahankan. Pengujian diulang setelah perbaikan dan penambahan cakupan hingga hasil akhir 84 tes katalog/pricing dan 187 tes seluruh suite lulus.

### Skenario penting yang tercakup

Rincian dapat ditelusuri di [ProductCatalogTest.php](tests/Feature/ProductCatalogTest.php) dan [ProductQuoteSnapshotTest.php](tests/Feature/ProductQuoteSnapshotTest.php):

- Hasil dua contoh TGM tersimpan dan dapat dibuka melalui URL dengan grant POS saja.
- Perubahan nama produk, satuan, tarif, finishing dan HPP tidak mengubah hasil yang telah tersimpan, termasuk setelah session dibersihkan.
- Fingerprint publik berubah ketika aturan jual berubah, tetap sama untuk perubahan HPP saja, serta tidak menggantikan UUID unik tiap perhitungan.
- Klien tidak dapat menetapkan subtotal, HPP, ID harga, tenant atau ukuran asli snapshot melalui field tambahan request.
- HPP tidak masuk payload quote publik atau daftar riwayat; akses HPP pada konfigurasi katalog tetap mengikuti hak pengguna existing.
- Tenant lain atau produk yang tidak cocok menghasilkan 404; pencabutan membership, role, entitlement atau lisensi kembali membatasi akses riwayat.
- Foreign key mencegah quote mengacu ke produk tenant lain. Riwayat ditampilkan maksimal 10, berurutan terbaru.
- Produk nonaktif tidak dapat dihitung ulang, tetapi riwayatnya tetap dapat dibaca pengguna berhak.
- Tabel quote yang belum tersedia tidak mematikan pembacaan katalog; operasi quote memberi 503 terkontrol.
- Migration quote dapat diterapkan pada katalog pengujian existing tanpa mengubah produk dan harga.

### Pengujian yang belum dilakukan

Browser tidak tersedia pada sesi pengujian, sehingga belum ada bukti visual, interaksi end-to-end, mobile/touch, cetak atau hardware. MySQL, contention/penguncian antarproses, beban tinggi, pemulihan backup, serta deployment juga belum diuji. Keberhasilan SQLite, pemeriksaan tipe dan build tidak menggantikan pengujian tersebut.

## 3. Harga yang sudah divalidasi secara bisnis

Pengguna mengonfirmasi harga satuan dan total berikut dalam percakapan. Keduanya menjadi tes otomatis pada dataset `confirmed TGM product samples produce the approved totals with POS alone`.

| Produk dan spesifikasi yang dikonfirmasi | Qty | Harga satuan | Total disahkan | Batas validasi |
| --- | --- | --- | --- | --- |
| HVS 100 gr, 2 sisi | 20 lembar | Rp4.100 | **Rp82.000** | Hanya kombinasi ini dan qty 20. Ukuran kertas, mesin dan spesifikasi lain belum diberikan. |
| Lanyard 2 cm + stopper | 5 pcs | Rp12.000 | **Rp60.000** | Hanya kombinasi ini dan qty 5. Varian 2 cm ditagih per pcs. |

Fixture membatasi rentang harga menjadi `20–20` dan `5–5`; tidak mengekstrapolasi harga untuk kuantitas lain. Nilai 2 cm pada lanyard dipetakan sebagai varian, bukan panjang meter lari yang dikalikan ke harga. Stopper berada pada kombinasi harga Rp12.000 yang disahkan; tarif stopper terpisah belum disepakati.

HPP `0` pada kedua fixture hanya nilai pengujian, bukan konfirmasi biaya produksi atau margin nyata. Kedua contoh tidak di-seed ke database pengguna dan tidak membuktikan pemetaan terhadap seluruh master produk/harga TGM.

## 4. Aturan yang sudah diuji secara teknis

Aturan berikut menjelaskan perilaku implementasi dan cakupan tes. Kecuali dua contoh di atas, persetujuan bisnis untuk seluruh produk TGM masih diperlukan.

| Aturan | Perilaku saat ini | Bukti/catatan validasi |
| --- | --- | --- |
| Basis satuan | Jumlah tagihan = `qty`; subtotal awal = jumlah tagihan × tarif. | Dataset quantity dan dua contoh bisnis. |
| Basis panjang | Jumlah tagihan = `qty × panjang tertagih dalam meter`. | Dataset length dan ukuran minimum; satuan hasil `m`. |
| Basis luas | Jumlah tagihan = `qty × panjang tertagih dalam meter × lebar tertagih dalam meter`. | Dataset area dan ukuran minimum; satuan hasil `m²`. |
| Konversi ukuran | Input `cm` dibagi 100 per dimensi; input `m` tidak dikonversi. | Dataset kalkulasi ukuran/desimal; belum ada dukungan `mm`. |
| Ukuran minimum | Minimum panjang/lebar diterapkan sebelum hitungan panjang/luas; ukuran input disimpan terpisah. | Dataset `original dimensions and billing units...`. |
| Basis tier | Memilih rentang dari qty pesanan atau jumlah tagihan sesuai `tier_basis`. Batas min/max inklusif. | Dataset `tier boundaries...`; konfigurasi tiap produk tetap perlu disahkan. |
| Lookup di bawah 1 | Nilai pencarian tier minimal 1, sedangkan jumlah tagihan tetap hasil perhitungan sebenarnya. | Perilaku kalkulator existing dan kasus tier/luas pecahan; kebijakan operasional belum dikonfirmasi. |
| Pencocokan spesifikasi | `variant`, `machine`, `print_side`, `finishing` harus cocok dengan baris aktif yang sama. | Tes exact match dan spesifikasi tidak dikenal; tidak ada fallback ke kombinasi lain. |
| Harga aktif/rentang | Produk nonaktif, harga nonaktif, rentang kosong atau lebih dari satu baris cocok ditolak. | Tes katalog dan tes rentang ambigu; editor existing juga menolak overlap kombinasi yang sama. |
| Harga minimum | `minimum_charge` diterapkan setelah perkalian tarif dan sebelum pembulatan. | Tes minimum setelah kalkulasi dimensional. |
| Pembulatan | Hitungan memakai decimal; subtotal akhir dibulatkan ke rupiah terdekat dengan Half Up, lalu diserialisasi dua desimal. | Tes pembulatan setelah perkalian. Formatter harga satuan mempertahankan pecahan hingga dua desimal. |
| Input dan nominal | Qty harus positif, dimensi wajib sesuai basis, decimal valid, total melampaui batas nominal ditolak. | Dataset validasi dan tes overflow; bukan persetujuan kebijakan minimum pesanan bisnis. |
| Riwayat | Nilai snapshot tersimpan dibaca kembali tanpa kalkulasi ulang dari master. | Tes perubahan master, pembersihan session, akses dan manipulasi request. |

Alur perhitungan: validasi request dan akses → tentukan ukuran tertagih → hitung jumlah tagihan → tentukan qty lookup tier → cocokkan spesifikasi dan satu harga aktif → kalikan tarif → terapkan harga minimum → pembulatan → simpan snapshot → tampilkan hasil.

`version=2` menunjukkan versi struktur payload. `pricing_rule=catalog-v1` menandai aturan kalkulator. `price_revision` merupakan SHA-256 konten aturan jual yang relevan dan sengaja tidak memasukkan HPP; bukan tanda tangan anti-manipulasi, periode berlaku harga atau sistem penjadwalan versi harga.

## 5. Aturan yang belum divalidasi atau belum dibuat

| Bagian | Status | Bukti/keputusan yang masih diperlukan |
| --- | --- | --- |
| HVS selain contoh qty 20 | Belum divalidasi secara bisnis | Ukuran fisik kertas, mesin, 1/2 sisi, finishing, tier kuantitas lain dan nilai minimum. Jangan menyimpulkan A3/A4 dari nama HVS. |
| Lanyard selain contoh qty 5 | Belum divalidasi secara bisnis | Tier lain, panjang/varian, tanpa stopper, kombinasi aksesori dan biaya finishing terpisah. |
| Large Format dan meter lari | Rumus umum diuji; belum divalidasi dengan transaksi nyata | Contoh ukuran/satuan, minimum ukuran, minimum nominal, basis tier dan total yang disahkan. |
| Lookup minimal 1, minimum dan pembulatan | Perilaku teknis existing diuji; kebijakan bisnis belum disahkan menyeluruh | Contoh batas tier, ukuran/qty pecahan, minimum dan hasil pembulatan pada setiap kategori yang akan dijual. |
| Bahan/varian/master TGM | Belum direkonsiliasi dengan seluruh katalog | Mapping produk, bahan, mesin, label spesifikasi, satuan, harga aktif dan deduplikasi rentang sumber. |
| Finishing bertingkat atau banyak pilihan | Pencocokan satu field tersedia; perhitungan tambahan universal belum dibuat | Definisi finishing yang termasuk tarif atau ditagih terpisah, qty/luas dasarnya serta urutan penambahan. |
| Laser/Flatbed, DTG/DTF dan kategori khusus lain | Belum divalidasi/diimplementasikan secara khusus | Contoh dan rumus operasional kategori tersebut; jangan menganggap rumus luas umum sudah cukup. |
| HPP, BOM, scrap, routing, overhead dan margin | Snapshot HPP tersimpan tersedia; kalkulasi biaya otomatis belum menjadi pekerjaan STEP 5 | Rekonsiliasi biaya per variasi dan aturan margin sebelum dipakai untuk laba rugi. |
| Harga efektif terjadwal | Belum dibuat | Tanggal berlaku, versi aktif, aturan perubahan dan pengelolaan riwayat harga master. |
| Diskon, pajak, ongkir, DP/pelunasan | Belum dibuat pada alur STEP 5 | Kontrak order dan pembayaran serta contoh total ujung-ke-ujung. |
| Persetujuan quote, override, order/invoice/SPK | Belum dibuat | Masa berlaku, otorisasi perubahan harga, pembekuan harga transaksi dan alur pembatalan/revisi. |
| Retensi quote | Belum ditetapkan | Lama penyimpanan, pengarsipan dan kebijakan penghapusan; daftar 10 terbaru bukan batas jumlah data tersimpan. |

[Audit TGM bagian 7](AUDIT_ERP_TGM.md#7-pemetaan-pricing-engine-yang-benar-benar-dibaca) menemukan perbedaan estimator dengan subtotal draft, pencocokan spesifikasi yang tidak universal dan snapshot yang belum konsisten pada semua jalur. STEP 5 menyatukan hasil simulator Laravel dan penyimpanannya, tetapi belum membuktikan konsistensi estimator–order–invoice–SPK karena alur tersebut belum dibuat. Kesalahan lama tidak dianggap sebagai rumus bisnis yang harus disalin.

## 6. Migration dan batas penerapan

Migration baru: [2026_10_10_070531_create_product_quotes_table.php](database/migrations/2026_10_10_070531_create_product_quotes_table.php).

Tabel `product_quotes` berisi ID, UUID unik, tenant, produk, pembuat nullable, snapshot publik, snapshot biaya internal, serta timestamps. Foreign key gabungan tenant/produk menjaga kepemilikan; penghapusan fisik produk yang masih dirujuk quote dibatasi. Menonaktifkan produk mempertahankan riwayat. Seeder quote tidak membuat riwayat fiktif untuk tenant pengguna.

Migration diuji pada SQLite sementara untuk pembuatan skema dan upgrade katalog existing. **Migration belum diterapkan pada database pengguna.** Penerapan nantinya memerlukan pemeriksaan status migration STEP 4 dan STEP 5, backup database di luar Git, pengujian pada database terisolasi yang mewakili mesin tujuan, serta persetujuan pemilik database sebelum menjalankan pending migrations.

Down migration menghapus tabel dan riwayat quote. Rollback tersebut bukan cara aman memperbaiki masalah pada database yang sudah berisi riwayat pengguna. Jaminan transaksi/penguncian dalam kode juga masih membutuhkan verifikasi concurrency pada MySQL bila itu mesin deployment yang dipilih.

## 7. Urutan pekerjaan berikutnya

1. Review PR #3 beserta laporan ini. Merge tetap menunggu tindakan/instruksi terpisah dari pengguna.
2. Lengkapi contoh bisnis yang belum disahkan, terutama batas tier, ukuran, minimum, pembulatan dan finishing untuk produk yang akan masuk paket awal. Jadikan setiap contoh yang disahkan sebagai tes regresi.
3. Verifikasi browser dan database target pada lingkungan terisolasi: tampilan ukuran/satuan, riwayat setelah refresh, perubahan master, akses tenant, upgrade skema dan perubahan harga bersamaan.
4. Susun pemetaan katalog TGM serta penanganan baris ambigu sebelum merencanakan import. Tidak ada import produksi pada STEP 5.
5. Setelah kontrak harga dan penerapan database disetujui, rancang order/SPK/POS Lite memakai hasil server serta snapshot transaksi yang konsisten; pisahkan pekerjaan pembayaran dan modul produksi sesuai entitlement.

Penyelesaian laporan dan pembaruan PR tidak menjalankan tahap lanjutan di atas. Tidak ada merge, migration database pengguna atau deployment dalam penutupan STEP 5 ini.
