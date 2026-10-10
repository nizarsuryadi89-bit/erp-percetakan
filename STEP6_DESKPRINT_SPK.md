# STEP 6 — Deskprint dan SPK Lite

Tanggal: 10 Oktober 2026. Acuan: [Issue #4](https://github.com/nizarsuryadi89-bit/erp-percetakan/issues/4), [audit ERP TGM](AUDIT_ERP_TGM.md), [review fondasi](REVIEW_FONDASI_LARAVEL.md), [STEP 4](STEP4_FONDASI_SAAS.md), [STEP 5](STEP5_KATALOG_PRICING.md), dan [arsitektur PWA](docs/PWA_ARCHITECTURE.md).

Implementasi dan pengujian STEP 6 selesai pada branch `step-6/deskprint-spk-lite`, untuk review terhadap `main`, tanpa merge atau deployment. Baseline: `1bcab66f9cb87e7a1b22a191cfd3f338d4d2a9b4` setelah PR #3 di-merge oleh pemilik. Source, dependency dan database proyek utama tidak ditimpa. Worktree implementasi permanen: `/Volumes/zarnizar/herd_laravel/erp-percetakan-step6`.

## 1. Desain yang dicatat sebelum implementasi

Source aktual belum memiliki pelanggan, order/SPK, nomor atomik atau assignment cabang. Kalkulator STEP 5, tenant scope, POS entitlement, audit dan role existing dipakai kembali. Source TGM lokal dibaca read only pada `Transaksi::step_satu`, `createspk`, `action_add_temp`, `add_pemesanan` dan partial `createspk`. Urutan SPK di awal, lalu Produk → Spesifikasi → Rincian dipertahankan. Perhitungan kategori khusus yang belum disahkan tidak disalin menjadi formula baru.

| Bagian | Kontrak implementasi |
| --- | --- |
| `branch_user` | Assignment staf eksplisit; constraint tenant/cabang dan tenant/membership. Pengguna dengan `cabang.manage` mengakses seluruh cabang aktif. Staf tanpa assignment tidak otomatis mendapat cabang. |
| `branches.spk_sequence` | Counter naik monoton per cabang. Increment atomik dalam transaksi dan nomor unik pada database. Tidak memakai MAX+1 atau mengulang nomor setelah pembatalan. |
| `customers` | Nama, telepon opsional dan alamat, terikat tenant/cabang. Pelanggan sederhana POS dapat dipakai tanpa CRM premium. |
| `print_orders` | Header draft dibuat sejak awal: nomor, metode, marketplace manual, snapshot pelanggan/branding/pembuat, tanggal/deadline, catatan, total decimal dan versi edit. |
| `print_order_items` | Snapshot hasil kalkulasi server dan HPP internal tersembunyi; catatan, lokasi proses dan deadline. Revisi menambah versi item, mempertahankan versi lama. |
| `print_order_events` | Riwayat tindakan/status dengan aktor dan waktu, tanpa HPP pada metadata publik. Audit tenant juga dicatat. |
| `deskprint_requests` | Kunci idempotensi per tenant/aktor, fingerprint operasi/payload dan hasil. Retry identik mengembalikan hasil existing; penggunaan key dengan payload/operasi lain ditolak. |

Semua mutasi operasional berada dalam transaksi. Write pertama pada baris cabang menyerialisasi writer SQLite dan memperoleh row lock pada MySQL; setelah itu akses diperiksa kembali, receipt retry dicari, versi/status diuji dan perubahan disimpan. Constraint gabungan menolak relasi customer/order/item/produk yang melintasi tenant atau cabang yang tidak cocok. Retry transaksi menggunakan kemampuan Laravel menangani konflik/deadlock. Rujukan API: [pessimistic locking](https://laravel.com/framework/docs/13.x/queries#pessimistic-locking) dan [database transactions](https://laravel.com/framework/docs/13.x/database#database-transactions). Keberhasilan runtime MySQL tetap perlu dibuktikan terpisah.

## 2. Fitur dan file utama

| Area | File utama dan hasil |
| --- | --- |
| Operasi SPK | [ManagePrintOrder.php](app/Actions/ManagePrintOrder.php): header, tambah/revisi/hapus item draft, total dan transisi status. |
| Retry dan concurrency | [DeskprintTransaction.php](app/Actions/DeskprintTransaction.php): transaksi, lock cabang, receipt dan penolakan konflik key. |
| Pelanggan | [CreateCustomer.php](app/Actions/CreateCustomer.php), [CustomerController.php](app/Http/Controllers/CustomerController.php), `Customer` dan factory; pencarian nama/telepon, tambah dan retry. |
| Akses tenant/cabang | [BranchAccess.php](app/Support/BranchAccess.php), [DeskprintAccess.php](app/Support/DeskprintAccess.php), [BranchMembershipController.php](app/Http/Controllers/BranchMembershipController.php). Assignment melalui pengaturan cabang. Switcher dan daftar cabang mengikuti hak yang sama. |
| Endpoint dan validation | [PrintOrderController.php](app/Http/Controllers/PrintOrderController.php), [PrintOrderItemController.php](app/Http/Controllers/PrintOrderItemController.php), Form Requests dan [routes/web.php](routes/web.php). Guard pending schema memberi 503 terkontrol. |
| Model dan integritas | `Customer`, `PrintOrder`, `PrintOrderItem`, `PrintOrderEvent`, `DeskprintRequest` beserta factory/seeder. Seeder baru tidak memasukkan riwayat atau pelanggan fiktif ke database pengguna. |
| UI | [create.tsx](resources/js/pages/deskprint/create.tsx), [show.tsx](resources/js/pages/deskprint/show.tsx), [index.tsx](resources/js/pages/deskprint/index.tsx), [print.tsx](resources/js/pages/deskprint/print.tsx). |
| Frontend bersama | [ProductQuoteSummary](resources/js/components/product-quote-summary.tsx) dipakai untuk simulator, preview dan item; hook pencarian membatalkan request lama, menyediakan retry dan tidak memakai HTTP cache. Pemberitahuan koneksi mempertahankan isian di layar. |
| Navigasi | Workspace POS menyediakan tautan Deskprint/SPK dan katalog. Tidak ada modul premium baru yang diwajibkan. |

PHP 8.4.16, Laravel 13.35.0, Inertia Laravel 3.5.1, Spatie Permission 8.3.0, Wayfinder 0.1.21 dan Pest 5.3.1 dikonfirmasi dari dependency terpasang. Dependency/lockfile tidak diubah.

## 3. Pilihan bisnis dan aturan operasional

- Metode pesanan: On the Spot/Walk-in, WhatsApp/Online dan Marketplace. Nama marketplace, referensi pesanan dan resi hanya metadata yang dimasukkan pengguna; tidak ada API marketplace atau pengiriman pesan otomatis.
- Nomor berformat `SPK-<ID cabang>-<YYYYMM>-<urutan minimal 6 digit>`. Periode mengikuti timezone tenant; counter tidak direset tiap bulan dan identitas pembuat dicatat terpisah. Format ini merupakan pilihan STEP 6 yang dapat direview pemilik, bukan klaim sama persis dengan format lama cabang-user-bulan.
- Permission `spk.manage` diberikan pada default role Owner, Administrator dan Kasir. Semua operasi SPK/pelanggan tetap memerlukan POS, lisensi, membership tenant aktif serta cabang yang diizinkan. Read Only dapat membaca/mencetak dengan POS dan assignment cabang, tetapi tidak menulis. Default Keuangan/Operator Produksi tidak diberi operasi SPK.
- Pengelolaan assignment adalah pengaturan administratif: memerlukan `cabang.manage` dan `pengguna.manage`; tidak otomatis memberikan POS, lisensi atau permission operasi. Migration tidak mengarang assignment staf lama. Custom role tidak diberi `spk.manage` otomatis.
- Status: `draft → confirmed`, `draft → cancelled`, `confirmed → cancelled`. Konfirmasi wajib mempunyai minimal satu item aktif. Pembatalan wajib beralasan, mempertahankan nomor, total historis dan seluruh snapshot. Tidak ada pembukaan ulang.
- Item hanya dapat direvisi/dikeluarkan saat draft. Revisi menghitung harga master saat revisi dan menambah snapshot baru; versi lama ditandai `superseded`. Penghapusan draft menandai `removed`. Header pelanggan/metode/branding dibekukan; koreksi header atau SPK confirmed menggunakan pembatalan dan SPK pengganti.
- Maksimal 100 item aktif per SPK merupakan batas teknis MVP. Total adalah penjumlahan subtotal item aktif menggunakan decimal. Diskon/pajak/ongkir belum ditambahkan. Overflow total membatalkan mutasi baru beserta receipt/event-nya.
- Cabang dipilih pada header, lalu server memeriksa hak. Branch/tenant item berasal dari order/context server. Nominal, harga ID, HPP, status awal dan nomor dari browser tidak dapat menentukan hasil tersimpan.

Update header snapshot, snapshot item dan event melalui model ditolak. Item lama tidak dapat diaktifkan ulang. Proteksi aplikasi ini bukan jaminan terhadap SQL langsung, bulk write administratif atau manipulasi oleh administrator database; belum ada signed/WORM audit.

## 4. Harga yang divalidasi dan snapshot

`CalculateProductPrice` menjadi satu-satunya rumus. Preview tidak membuat `product_quotes`; simpan/revisi item selalu menghitung ulang dan menyimpan snapshot SPK sendiri. Konfirmasi, baca dan cetak tidak mengambil ulang harga master. Estimasi dapat berubah bila master berubah sebelum item disimpan; yang berlaku adalah hasil server saat simpan.

| Contoh bisnis yang telah dikonfirmasi pengguna | Harga satuan | Subtotal item |
| --- | --- | --- |
| HVS 100 gr, 2 sisi, 20 lembar | Rp4.100 | **Rp82.000** |
| Lanyard 2 cm + stopper, 5 pcs | Rp12.000 | **Rp60.000** |
| Kedua item pada satu SPK | — | **Rp142.000** total item |

Tes membatasi tier contoh pada qty 20 dan 5. Tidak menganggap kertas A3/A4, mesin, kuantitas lain, biaya tambahan finishing atau HPP fixture sebagai data operasional yang disahkan. HPP pada fixture hanya data pengujian. Seluruh contoh berada pada database disposable dan tidak di-seed ke database pengguna.

Snapshot item memuat spesifikasi, qty, ukuran input/tertagih, satuan, basis/tier dan rentangnya, tarif, minimum, pembulatan, subtotal, marker aturan serta fingerprint publik; HPP internal disimpan terpisah dan tidak dikirim pada preview, katalog pilihan, detail, riwayat atau cetak. Perubahan produk, harga, pelanggan dan branding tidak mengubah dokumen historis.

Rumus panjang/luas, minimum dan decimal diuji secara teknis. Kesetaraan semua kategori TGM tetap belum disahkan; rujuk matriks validasi [STEP 5](STEP5_KATALOG_PRICING.md). Kombinasi yang belum dikonfigurasi atau harga ambigu ditolak, bukan dipilih acak.

## 5. Sampel alur UI dan PWA-ready

1. Buka workspace POS → Deskprint & SPK → Buat SPK.
2. Pilih cabang yang diizinkan, metode, pelanggan dari pencarian atau tambah pelanggan baru. Deadline opsional mengikuti timezone tenant.
3. Buat header draft; nomor SPK sudah tersedia sebelum pemesanan item/pembayaran.
4. Cari produk → pilih spesifikasi dari matriks aktif → isi qty/ukuran/catatan/lokasi/deadline. Preview live dihitung server, dan simpan menghitung ulang.
5. Tambah beberapa item, periksa ukuran asli vs tagihan, satuan, tarif, minimum dan total. Draft dapat direvisi/dikeluarkan dengan riwayat tetap tersedia.
6. Review → konfirmasi → lihat/cetak dokumen kerja. Draft dan cancelled diberi status jelas pada dokumen. Dokumen berisi branding snapshot tenant, pelanggan, produk/spesifikasi, ukuran, catatan, deadline dan total item.
7. Daftar SPK mendukung pencarian nomor/nama pelanggan pada snapshot, filter status dan pagination. Tombol muat ulang tersedia saat konflik versi.

Desktop lebar memakai tiga panel. Smartphone/tablet memakai langkah Produk → Spesifikasi → Rincian → Review dengan ringkasan dan target sentuh minimal 44px. Penanganan Enter menghindari submit panel dimensi yang masih tersembunyi pada layout langkah. Isian tidak disimpan ke localStorage/IndexedDB. POST/PATCH/DELETE menunggu respons server; tidak ada penandaan sukses ketika offline. Preview dan pencarian lama dibatalkan; error, loading, retry dan pencegahan klik ganda tersedia.

Respons Deskprint terproteksi memakai `Cache-Control: private, no-store`. Tidak ada service worker, offline checkout, instalasi PWA, ESC/POS atau silent print pada STEP 6. History encryption, lintas-tab/logout, installability dan audit cache/perangkat tetap mengikuti rencana [PWA](docs/PWA_ARCHITECTURE.md) sebelum rollout.

**Dua screenshot pemesanan yang disebut Issue #4 tidak tersedia dalam sesi ini dan belum dilihat.** Layout mengikuti audit/source TGM, bukan klaim kecocokan pixel. Browser tidak tersedia pada pemeriksaan sesi implementasi. Review screenshot/visual, alur keyboard/touch pada 360px/tablet/desktop dan print nyata masih diperlukan sebelum kemiripan UI atau dukungan perangkat dinyatakan terverifikasi.

## 6. Hasil pengujian nyata

Pengujian akhir dijalankan ulang pada source di worktree permanen. Suite utama memakai SQLite `:memory:` dengan guard [TestCase](tests/TestCase.php). Satu tes concurrency memakai file SQLite baru dari `tempnam`, worker terpisah yang hanya menerima path sementara dengan prefix khusus dan environment testing, lalu membersihkan file tersebut. Tidak ada migration pada database pengguna.

| Pemeriksaan | Hasil akhir |
| --- | --- |
| `php artisan test --compact` | **237 passed; 1.344 assertions**, 28,487 detik. Termasuk seluruh tes STEP 4–5. |
| `php artisan test --compact tests/Feature/DeskprintTest.php tests/Feature/DeskprintConcurrencyTest.php` | **50 passed; 287 assertions**, 8,938 detik. Bagian dari 237 tes, bukan tambahan di luar jumlah itu. |
| PHP syntax lint | **160 file lulus**, 0 kegagalan. |
| `vendor/bin/pint --dirty --format agent` | **Lulus**, format PHP diperbaiki sebelum suite final. |
| `node node_modules/typescript/bin/tsc --noEmit --incremental false` | **Lulus**. |
| `npm run build` | **Lulus**, Vite 8.3.4; manifest mencakup create/index/show/print Deskprint. |
| `git diff --check` | **Lulus**. |

Cakupan tes: alur multi-item dengan POS saja; preview/item identik; total SPK; perubahan master historis; area/panjang/qty pecahan/minimum/pembulatan; ambigu dan input invalid; manipulasi nilai dari browser; idempotensi tambah/revisi/hapus/status/header/pelanggan; konflik versi; confirmed/cancelled; confidentiality HPP; tenant/cabang/role/lisensi; assignment konservatif; constraint DB; pending schema; upgrade schema/permission tanpa mengubah katalog/lisensi/assignment role.

Tes concurrency menjalankan lima proses pembuatan header dengan dua request identik: menghasilkan empat SPK bernomor unik. Empat proses penambahan item memakai key sama: menghasilkan satu item, total Rp82.000, versi order 2 dan satu receipt operasi. Ini membuktikan skenario tersebut pada SQLite disposable; bukan uji beban umum atau bukti runtime MySQL.

Pada putaran source yang dipulihkan, tiga tes gagal karena callback eager-loading memakai tipe Builder sedangkan Laravel menyerahkan HasMany. Tipe diperbaiki dan suite kemudian lulus. Hasil akhir di atas berasal dari eksekusi lokal, bukan klaim GitHub Actions. Log sementara mesin: `/private/tmp/erp-step6-final-full.log`, `/private/tmp/erp-step6-final-focused.log`, `/private/tmp/erp-step6-final-lint.json` dan `/private/tmp/erp-step6-restored-build.log`; tidak dikomit sebagai arsip permanen.

## 7. Migration pending dan batas tahap

Migration baru:

- [2026_10_10_123459_create_deskprint_tables.php](database/migrations/2026_10_10_123459_create_deskprint_tables.php): enam tabel Deskprint/assignment serta counter cabang.
- [2026_10_10_123500_grant_deskprint_permissions.php](database/migrations/2026_10_10_123500_grant_deskprint_permissions.php): menambah permission operasi dan grant pada default Owner/Administrator/Kasir tanpa mengganti ID/assignment role atau entitlement/lisensi.

Pemeriksaan read only pada database lokal sebelum implementasi menunjukkan **8 migration tercatat**. Migration platform module access, platform audit logs dan product quotes STEP 4–5 masih pending; kedua migration STEP 6 juga belum diterapkan. Status Git proyek utama tetap `main` pada checkpoint lama, bersih dan tertinggal 6 commit dari main remote pada pemeriksaan akhir. Source STEP 6 hanya berada pada worktree/branch implementasi dan PR.

Sebelum aplikasi lokal memakai fitur, verifikasi semua pending migration, backup di luar Git, uji mesin DB target dan dapatkan persetujuan pemilik database untuk penerapan. Jangan menjalankan migration ini sendirian pada fondasi STEP 4 yang belum tersedia. Down migration menghapus pelanggan/SPK/riwayat/receipt/assignment dan counter; rollback tersebut tidak aman pada data pengguna yang telah terisi.

Belum dibuat: pembayaran/DP/piutang, invoice final/akuntansi, diskon/pajak/ongkir, WIP/routing/gudang, billing subscription, API marketplace, CRUD pelanggan penuh, perubahan header/confirmed, penjadwalan harga, retensi/arsip SPK dan PWA installable. Lokasi proses hanya informasi kerja. Riwayat versi/event belum dipaginasi; volume riwayat serta retensi perlu diuji sebelum skala besar.

Belum diuji: browser/E2E/kemiripan screenshot, printer/perangkat, mobile keyboard/touch nyata, MySQL/concurrency MySQL, audit cache/history lintas-tab/logout dan deployment. Keterbatasan ini tidak ditutup dengan hasil build atau tes SQLite.

Langkah berikutnya adalah review PR, pengesahan format nomor/aturan cabang dan review UI dengan pemilik, lalu validasi database/perangkat terisolasi. Penerapan migration, merge, checkout pembayaran dan deployment menunggu instruksi terpisah. STEP 6 tidak melakukan tindakan tersebut.
