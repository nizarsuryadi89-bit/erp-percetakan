# STEP 2 — Review fondasi Laravel ERP Percetakan

Tanggal: **10 Oktober 2026**. Project: `/Volumes/zarnizar/herd_laravel/erp_percetakan`.

Acuan utama: [AUDIT_ERP_TGM.md](/Volumes/zarnizar/herd_laravel/erp_percetakan/AUDIT_ERP_TGM.md). Status pekerjaan ini: **review selesai; perbaikan dan implementasi berikutnya menunggu instruksi**.

## 1. Kesimpulan

Project Laravel sudah memiliki fondasi yang dapat dipertahankan: autentikasi, tenant, cabang, pengguna/peran, audit aktivitas, katalog produk, matriks harga dan kalkulator harga server. Project tidak perlu dibuat ulang.

Keadaan saat ini **belum konsisten antara source, database dan frontend**. Perubahan registry/platform sudah mulai masuk ke backend, tetapi migration platform belum dijalankan, frontend platform belum tersedia, navigasi masih memakai enam feature lama, dan tes belum disesuaikan dengan entitlement baru. Karena itu keberadaan source tidak boleh dilaporkan sebagai seluruh fondasi siap berjalan.

Prioritas berikutnya adalah menyelesaikan transisi akses modul dan membuktikan konsistensi fondasi, lalu memvalidasi aturan harga terhadap TGM. POS/SPK, pembayaran, produksi serta laporan operasional belum dibuat. Review ini tidak melanjutkan coding atau menjalankan migration untuk menutup kekurangan tersebut.

## 2. Cara pemeriksaan dan batas hasil

- Membaca source aplikasi, migration, model, controller/action, konfigurasi, frontend dan isi tes.
- Membaca schema serta metadata database **SQLite lokal dengan `mode=ro` dan `PRAGMA query_only=ON`**; tidak menggunakan koneksi Laravel yang dapat menulis session/cache/log atau menjalankan migration.
- Memeriksa status Git dan metadata repository GitHub tujuan secara read only.
- Memeriksa sintaks **114 file PHP**: seluruhnya lulus `php -l`.
- Memeriksa TypeScript dengan `node node_modules/typescript/bin/tsc --noEmit --incremental false`: **lulus**, tanpa menghasilkan file.
- Tidak menjalankan Pest/Artisan test, migration, seeder, perintah aktivasi, formatter, build frontend, atau server aplikasi. Tes fondasi/katalog menggunakan `LazilyRefreshDatabase`; eksekusinya akan membuat/memigrasikan database pengujian. Itu tidak dilakukan dalam batas review ini.
- Tidak ada uji browser, email sungguhan, perangkat cetak, MySQL produksi, deployment, commit, push, perubahan dependency, atau perubahan source lama.

Lulus sintaks dan TypeScript tidak membuktikan route menghasilkan halaman yang tersedia, tabel sudah ada, akses sudah sesuai, atau alur pengguna berhasil. Status fitur dalam laporan dibedakan antara **source tersedia**, **integrasi sebagian**, dan **belum dibuat**. Tidak ada klaim end-to-end baru berdasarkan hasil tes lama.

## 3. Struktur project dan versi terpasang

| Komponen | Hasil |
| --- | --- |
| PHP CLI | 8.4.16, Laravel Herd |
| Laravel | 13.35.0 |
| Inertia Laravel | 3.5.1 |
| Fortify | 1.41.0 |
| Spatie Permission | 8.3.0 |
| Pest / plugin Laravel | 5.3.1 / 5.0.1 |
| React / Inertia React | 19.3.0 / 3.9.1 |
| TypeScript / Vite / Tailwind | 5.9.3 / 8.3.4 / 4.3.3 |
| Wayfinder | Paket PHP 0.1.21; plugin Vite dan helper route frontend tersedia |

Versi PHP package dibaca dari `composer show --direct`, JS dari package terpasang di `node_modules`, bukan diasumsikan dari rentang `package.json`.

| Area | Struktur yang ditemukan |
| --- | --- |
| Backend | `app/Actions`, `app/Models`, `app/Support`, `app/Http/Controllers`, `Requests`, `Middleware`, `Providers`, `Console/Commands` |
| Data | 9 file migration, 11 factory dan 10 seeder; database pengembangan SQLite |
| Aplikasi PHP | 57 file di `app`; tenant memakai base model dan global scope |
| Frontend | React/TypeScript, Inertia, Tailwind, komponen UI bersama, halaman `auth`, `settings`, `foundation`, `products`, dashboard |
| Routing | `routes/web.php`, `settings.php`, `console.php`; middleware alias tenant/module/platform |
| Test | 7 file PHP dalam `tests`, termasuk fondasi, katalog dan stub platform |
| Build | `public/build/manifest.json` dan asset sudah ada; entry app/CSS serta halaman fondasi/katalog tercatat; bukan build yang dibuat dalam review |
| Dokumentasi | `README.md` masih template Laravel; audit TGM sudah tersedia; panduan project ada di `AGENTS.md` dan skill lokal |

Tidak ditemukan `.ai/rules` pada project saat pemeriksaan. Panduan `laravel-best-practices`, `fortify-development` dan `testing-best-practices` digunakan untuk review. Tidak ada folder dasar atau project baru yang dibuat.

## 4. Fitur yang sudah tersedia dan belum selesai

| Fitur | Status | Bukti dan batas |
| --- | --- | --- |
| Login/register/logout | Source dan halaman tersedia | Fortify, email/password, hash password, throttling; browser tidak diuji |
| Reset password / verifikasi email | Source tersedia; pengiriman sungguhan belum siap dibuktikan | `MAIL_MAILER=log` lokal; email dicatat ke log, bukan dikirim ke pelanggan |
| 2FA / passkeys / profil / keamanan | Scaffolding terintegrasi tersedia | Feature Fortify, migration dan halaman settings; perangkat/browser belum diuji |
| Tenant/perusahaan | Model, tabel, membership, pembuatan dan pergantian tersedia | Onboarding sekarang tergantung registry `modules` yang belum ada, sehingga integrasi terkini bermasalah |
| Cabang | Tabel, CRUD dan pemilihan cabang tersedia | Terisolasi tenant; belum ada penugasan pengguna ke cabang tertentu |
| Pengguna dan peran | CRUD staf dan izin per tenant tersedia | Owner dilindungi dari perubahan tertentu; undangan akun global yang sudah ada belum dibuat |
| Lisensi | Model, tabel, evaluasi masa akses dan command tersedia | Aktivasi manual; bukan subscription/billing otomatis |
| Modul lama | Enam feature toggle tersedia di database dan UI awal | `transaksi`, `bom`, `routing`, `produksi`, `keuangan`, `laba_rugi`; masih dipakai navigasi |
| Registry modul baru | Backend/migration/seeder tersedia sebagian | Delapan code canonical; tabel belum ada, frontend dan integrasi navigasi belum selesai |
| Entitlement developer | Model/action/request dan route tersedia sebagian | Validasi akses/grant/dependency ada; belum dapat dianggap fitur siap pakai |
| Panel platform | Controller, Gate, middleware, route, command tersedia sebagian | Dua halaman React yang dirender controller belum dibuat |
| Dashboard | Halaman fondasi tersedia | Ringkasan lisensi/cabang/pengguna, akses modul dan aktivitas; bukan dashboard bisnis TGM |
| Audit log | Source/tabel dan pencatatan beberapa tindakan tersedia | Belum ada UI penelusuran audit lengkap; bukan semua perubahan platform dicatat |
| Produk dan matriks harga | Model/tabel/controller/frontend/tes tersedia | Database lokal saat ini **0 produk dan 0 harga**; akses HTTP bergantung registry yang belum siap |
| Simulasi harga | Kalkulator server dan UI tersedia | Belum menjadi order/SPK atau invoice; tidak ada snapshot transaksi permanen |
| Form Builder TGM / bahan / BOM / routing | Belum dibuat secara operasional | Kategori/satuan dan empat spesifikasi masih string; belum ada schema form dinamis atau relasi bahan/BOM |
| POS / order / SPK / kasir / pembayaran / piutang | Belum dibuat | Halaman workspace hanya placeholder yang menyatakan alur operasional belum tersedia |
| Produksi/WIP/QC, stok/pembelian, keuangan/laba rugi | Belum dibuat | Registry/placeholder bukan bukti modul operasional |
| Lite/Growth/Pro, quota, subscription, billing hosting/support | Belum dibuat | Belum ada model/tabel paket/subscription, invoice langganan, renewals atau batas pengguna/cabang |
| Integrasi TGM, import data, printer, WhatsApp, marketplace | Belum dibuat dalam Laravel yang diperiksa | Dibahas pada audit sumber; belum dipindahkan |

Source: [routes](/Volumes/zarnizar/herd_laravel/erp_percetakan/routes/web.php), [FortifyServiceProvider](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Providers/FortifyServiceProvider.php), [WorkspaceController](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Http/Controllers/WorkspaceController.php), [workspace UI](/Volumes/zarnizar/herd_laravel/erp_percetakan/resources/js/pages/foundation/workspace.tsx), [config ERP](/Volumes/zarnizar/herd_laravel/erp_percetakan/config/erp.php).

## 5. Migration dan database lokal

Database yang diperiksa: `database/database.sqlite`. Environment lokal memakai `APP_ENV=local`, SQLite, session/cache/queue berbasis database. Kredensial dan data pribadi tidak disalin ke laporan.

| Migration | Status pada tabel `migrations` |
| --- | --- |
| `0001_01_01_000000_create_users_table` | Sudah tercatat, batch 1 |
| `0001_01_01_000001_create_cache_table` | Sudah tercatat, batch 1 |
| `0001_01_01_000002_create_jobs_table` | Sudah tercatat, batch 1 |
| `2024_01_01_000000_create_passkeys_table` | Sudah tercatat, batch 2 |
| `2025_08_14_170933_add_two_factor_columns_to_users_table` | Sudah tercatat, batch 2 |
| `2026_10_10_022837_create_erp_foundation_tables` | Sudah tercatat, batch 2 |
| `2026_10_10_022842_create_permission_tables` | Sudah tercatat, batch 2 |
| `2026_10_10_033746_create_product_catalog_tables` | Sudah tercatat, batch 3 |
| `2026_10_10_041453_create_platform_module_access_tables` | **Belum tercatat; tidak dijalankan dalam review** |

Terdapat **23 tabel aplikasi/framework** pada SQLite, tidak termasuk tabel internal SQLite. Tabel fondasi: `tenants`, `tenant_user`, `branches`, `licenses`, `tenant_modules`, `audit_logs`; katalog: `products`, `product_prices`; permission: `roles`, `permissions`, `role_has_permissions`, `model_has_roles`, `model_has_permissions`; sisanya users/session/password/passkeys/cache/queue/migrations.

Keadaan data: 1 tenant, 1 membership, 1 cabang, 1 pengguna, 1 lisensi aktif, 6 feature toggle aktif, 6 peran, 11 permission, 1 penugasan peran. Tidak ada produk atau harga. Lisensi lama tersimpan dengan harga Rp5 juta tanpa expiry; tidak diperbarui oleh review.

Empat tabel yang akan dibuat migration pending **belum ada**: `modules`, `module_dependencies`, `platform_administrators`, `tenant_module_entitlements`. Permission canonical seperti `POS.view`, `PRODUCTION.view`, `FINANCE.view`, `ANALYTICS.view` juga belum ada pada data lokal.

### Tinjauan desain migration

- Fondasi memiliki unique membership tenant-user dan kode cabang per tenant.
- Katalog memakai decimal untuk uang/qty, unique kode produk per tenant, serta foreign key gabungan `(tenant_id, product_id)` agar harga tidak menunjuk produk tenant lain.
- Migration permission memiliki constraint gabungan tenant-role untuk penugasan role; tabel direct permission dan role belum sekaligus menegakkan keanggotaan `tenant_user` pada level DB. Middleware menjadi batas akses utama.
- Migration platform mengisi delapan definisi modul dan dependensi, lalu memberi grant dari keberadaan alias lama. Ia **tidak menyinkronkan role canonical** dan tidak membuat platform administrator.
- Backfill platform hanya membaca keberadaan code `tenant_modules`, bukan `is_enabled`; grant baru diisi `true`. Feature toggle lama masih ada, sehingga semantik “hak diberikan” dan “sedang aktif” harus diuji pada transisi.
- Down migration menghapus tabel/data; rollback bukan cara aman untuk menyelesaikan integrasi yang belum selesai.
- Database pengembangan dan tes memakai SQLite. Compatibility, constraint, transaksi/concurrency dan locking MySQL belum dibuktikan dalam review.

Source: [fondasi](/Volumes/zarnizar/herd_laravel/erp_percetakan/database/migrations/2026_10_10_022837_create_erp_foundation_tables.php), [permission](/Volumes/zarnizar/herd_laravel/erp_percetakan/database/migrations/2026_10_10_022842_create_permission_tables.php), [katalog](/Volumes/zarnizar/herd_laravel/erp_percetakan/database/migrations/2026_10_10_033746_create_product_catalog_tables.php), [platform](/Volumes/zarnizar/herd_laravel/erp_percetakan/database/migrations/2026_10_10_041453_create_platform_module_access_tables.php:64).

## 6. Tenant, cabang, pengguna, role dan permission

### Komponen yang baik dan dapat dipertahankan

`TenantContext` terdaftar sebagai scoped service. `ResolveTenant` hanya memilih tenant aktif dari membership pengguna yang aktif; session tidak dipakai sebagai satu-satunya bukti otorisasi. Konteks tenant, team permission dan relasi role/permission pengguna dibersihkan pada akhir request. Middleware tenant diprioritaskan sebelum route model binding.

`TenantScope` menambahkan tenant aktif pada query, atau `-1` ketika tidak ada konteks. `TenantModel` juga memeriksa tenant pada save/delete instance, termasuk perubahan tenant_id pada record yang sudah ada. Ini melindungi jalur model instance; raw SQL dan bulk operasi tetap perlu aturan eksplisit karena tidak otomatis menjalankan event instance.

Spatie memakai teams dengan `tenant_id`. Role tersedia: Owner, Administrator, Kasir, Operator Produksi, Keuangan, Read Only. Role management dan product management dibatasi melalui Gate/permission serta `FormRequest::authorize`, bukan sekadar hide tombol. Pergantian tenant memeriksa membership dan mengosongkan branch selection.

Controller staf membuat akun baru dengan password ter-hash, penugasan peran dan email verifikasi. Ia tidak menggabungkan akun global lain tanpa persetujuan. Update melindungi akun sendiri dan Owner. `ProfileDeleteRequest` menolak penghapusan akun yang masih mempunyai membership, sehingga akses perusahaan tidak ditinggalkan tanpa pengguna melalui fitur hapus profil.

Source: [TenantContext](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Support/TenantContext.php), [ResolveTenant](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Http/Middleware/ResolveTenant.php), [TenantModel](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Models/TenantModel.php), [TenantScope](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Models/Scopes/TenantScope.php), [TenantUserController](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Http/Controllers/TenantUserController.php), [ProfileDeleteRequest](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Http/Requests/Settings/ProfileDeleteRequest.php).

### Batas dan pekerjaan lanjutan

- Cabang saat ini adalah pilihan session dalam tenant; **belum ada `branch_user` atau pembatasan staf ke outlet tertentu**. Pengguna anggota tenant dapat memilih cabang aktif tenant itu. Ini perlu keputusan bisnis sebelum order/keuangan memakai branch scope.
- Produk dan harga memakai tenant, tidak branch. Belum diputuskan apakah seluruh cabang berbagi harga atau mempunyai override.
- `CreateCompany` masih membuat enam feature toggle lama. Itu tidak otomatis membatasi registry ke enam modul, tetapi frontend masih menampilkan konsep lama.
- `ConfigureTenantRoles` sekarang membaca tabel `modules`; pada database lokal yang belum dimigrasikan, onboarding dapat gagal dan transaksi creation rollback.
- Role lama tidak otomatis memperoleh permission baru hanya karena tabel platform dimigrasikan. Perlu langkah provisioning/update yang teruji dan menjaga akses lama.
- Belum ada flow undangan/membership untuk akun global existing, UI transfer Owner, atau izin tindakan granular untuk transaksi/produksi/keuangan. Placeholder `.view` tidak cukup untuk operasi bisnis yang kelak ditambahkan.
- License check baru berada pada jalur module; pengaturan perusahaan tetap dapat digunakan saat pending. Itu berguna untuk onboarding, tetapi bukan quota atau subscription enforcement.

## 7. Registry, lisensi, subscription dan entitlement

### Desain yang sudah ada

Registry canonical berisi **POS, PRODUCTION, INVENTORY, FINANCE, CRM, HRM, ANALYTICS, AI_ASSISTANT**. PRODUCTION dan FINANCE bergantung POS; ANALYTICS bergantung POS, PRODUCTION dan FINANCE.

`ModuleRegistry` memetakan alias feature ke code canonical, menelusuri dependensi dengan deteksi siklus, serta menentukan akses berdasarkan modul aktif dan entitlement granted/enabled/time window. `EnsureModuleAccess` menambahkan license check, feature toggle lama serta permission pengguna. Pemisahan hak developer memberikan modul dan hak tenant menonaktifkan modul merupakan desain yang sesuai arah diskusi.

`AssignTenantModules` mengecek Gate platform, validasi modul aktif/dependency, memakai transaksi dan lock, menyimpan grant, dapat mengubah status/expiry lisensi, dan mencatat audit. `PlatformAdministrator` merupakan akses global terpisah dari Owner tenant; hak ini diberikan lewat command, bukan berdasarkan username hardcoded TGM.

`License` menyimpan status, activated_at, expires_at dan support_ends_at. Hak pakai tidak otomatis berakhir karena support berakhir. Ini cocok sebagai fondasi lisensi sekali bayar. **Belum ada subscription, rencana Lite/Growth/Pro, renewal, tagihan berkala, payment gateway, quota atau trial.** Kolom expiry bukan bukti subscription sudah dibangun.

Source: [registry](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Support/ModuleRegistry.php), [middleware modul](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Http/Middleware/EnsureModuleAccess.php), [assign grant](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Actions/AssignTenantModules.php), [lisensi](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Models/License.php), [platform Gate](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Providers/AppServiceProvider.php).

### Integrasi yang belum selesai

1. **Source bergantung schema yang belum ada.** Akses modul/katalog memanggil `ModuleRegistry::resolve` → query `modules`; menu pengaturan modul query entitlement; platform Gate query platform_administrators. Jalur ini berpotensi error tabel tidak ditemukan, bukan penolakan akses yang terkontrol.
2. **Dua frontend platform belum tersedia:** `platform/index` dan `platform/company-modules`. Tidak ditemukan di `resources/js/pages` maupun build manifest. Setelah schema/akses diperbaiki pun halaman belum dapat dianggap berjalan.
3. **Navigasi belum memakai entitlement baru.** `HandleInertiaRequests::workspace` masih membaca lisensi + `tenant_modules` + config enam feature, sedangkan endpoint memakai registry/grant/time window. Menu dapat terlihat tetapi endpoint menolak 403; modul baru tidak muncul pada navigasi.
4. **Dashboard masih enam feature.** Controller memakai `config('erp.modules')`. `/modul/POS` hanya membuka placeholder; UI workspace menampilkan link katalog hanya jika `code === 'transaksi'`, sehingga canonical POS kehilangan pintasan produk tersebut.
5. **Provisioning belum utuh.** Tenant baru sengaja tidak menerima grant otomatis dan license masih pending. Setelah aktivasi license saja, modul tetap tidak tersedia; perlu grant developer. Seeder entitlement membuat row ungranted untuk tenant yang ada, bukan solusi otomatis onboarding/preset paket.
6. **Ada dua state aktivasi.** `tenant_modules.is_enabled` per feature dan `tenant_module_entitlements.is_enabled` per canonical module sama-sama memengaruhi akses. UI baru mengubah canonical, tetapi navigasi lama hanya melihat state feature. Aturan transisi dan kontrol keduanya belum disatukan.
7. **Permission existing belum dinaikkan.** Migration platform tidak memperbarui role; database saat ini belum mempunyai permission canonical. Route canonical dapat ditolak untuk pengguna lama meskipun grant ada.
8. **UI harga paket belum konsisten.** `config/erp.php` mengisi harga default `0.00`, sementara UI modul hardcode “Rp5.000.000 sekali bayar” dan tes onboarding masih mengharapkan Rp5 juta. Harga tersimpan lama, harga default tenant baru dan informasi UI harus diselaraskan dengan keputusan komersial.
9. **Analitik terlalu bergantung manufaktur untuk Lite.** Dependency ANALYTICS → PRODUCTION perlu dipisahkan dari ringkasan penjualan/HPP manual agar Lite dapat menghasilkan informasi dasar tanpa membeli WIP penuh, sesuai audit TGM.
10. **Audit admin platform belum lengkap.** Assign grant tercatat, tetapi command pemberian/pencabutan akses platform tidak mencatat audit dan backfill grant hanya mengandalkan proses migration.

Source: [shared navigation](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Http/Middleware/HandleInertiaRequests.php:44), [DashboardController](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Http/Controllers/DashboardController.php), [platform render](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Http/Controllers/PlatformController.php:24), [UI workspace](/Volumes/zarnizar/herd_laravel/erp_percetakan/resources/js/pages/foundation/workspace.tsx), [UI modul](/Volumes/zarnizar/herd_laravel/erp_percetakan/resources/js/pages/foundation/modules.tsx:26), [CreateCompany](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Actions/CreateCompany.php:34), [ConfigureTenantRoles](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Actions/ConfigureTenantRoles.php:14).

## 8. Audit CalculateProductPrice dibandingkan TGM

### Alur kalkulasi Laravel sekarang

`ProductQuoteController` menerima input tervalidasi → `CalculateProductPrice` memeriksa tenant/status produk → menghitung qty tagihan → menentukan basis tier → mencari baris harga aktif dengan empat spesifikasi exact → menghitung subtotal/minimum → membulatkan → mengembalikan quote lewat flash session.

Rumusnya:

- Quantity: billable qty = qty.
- Length: billable qty = qty × panjang; jika cm, panjang dibagi 100.
- Area: billable qty = qty × panjang × lebar; jika cm, setiap dimensi dibagi 100.
- Ukuran minimum diterapkan pada dimensi yang dipakai menghitung tagihan.
- Tier bisa berdasarkan qty input atau billable qty. Qty lookup di bawah 1 dinaikkan ke 1, tetapi tagihan tetap qty sebenarnya.
- Subtotal = billable qty × unit_price; dinaikkan ke minimum_charge bila perlu; dibulatkan HalfUp ke rupiah, dikembalikan sebagai decimal 2 digit.

`BigDecimal` menghindari perhitungan float. Input HTTP membatasi presisi/nominal dan menolak format eksponen/pemisah ribuan. Kelebihan batas total menimbulkan validation error. Price management memeriksa overlap spesifikasi/rentang dalam transaksi dengan lock produk. HPP disembunyikan dari props pengguna non-manager.

Source: [CalculateProductPrice](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Actions/CalculateProductPrice.php), [quote request](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Http/Requests/QuoteProductRequest.php), [price request](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Http/Requests/StoreProductPriceRequest.php), [price save](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Http/Controllers/ProductPriceController.php), [quote controller](/Volumes/zarnizar/herd_laravel/erp_percetakan/app/Http/Controllers/ProductQuoteController.php).

| Temuan audit TGM | Laravel sekarang | Keputusan review |
| --- | --- | --- |
| Estimator/subtotal berbeda dan satuan ambigu | Satu kalkulator dengan unit cm/m eksplisit | Pertahankan desain; belum ada invoice/order untuk membuktikan hasil sama di semua tahap |
| Lookup menyilangkan `ket_1`/mesin dan mengabaikan side/atribut tertentu | Exact `variant`, `machine`, `print_side`, `finishing` | Lebih jelas; butuh mapping data TGM dan contoh nyata |
| Rentang/status harga ambigu | Harga aktif saja, batas atas nullable, overlap dicegah | Pertahankan; lakukan validasi data legacy sebelum import |
| Minimum ukuran hanya dipakai sebagian jalur | Minimum memengaruhi billable dimension | Perubahan bisnis yang perlu disahkan, jangan dianggap identik dengan source lama |
| Basis tier menurut kategori/ukuran | `pricing_basis` dan `tier_basis` per produk | Pertahankan konfigurasi eksplisit; map setiap produk lama |
| Form Builder dan metadata atribut tambahan | Empat string spesifikasi, kategori/satuan string | Belum setara dengan konfigurasi TGM yang dinamis |
| DTG/DTF mapping luas dan DTF meter lari, Laser/Flatbed | Dasar quantity/length/area umum, minimum per produk | Belum ada adapter aturan khusus/preset ukuran; perlu validasi sebelum porting |
| Finishing dapat berupa pilihan/matriks atau item jasa terpisah | Finishing dianggap sudah termasuk pada baris harga, ditulis di UI | Itu pilihan desain baru; belum memodelkan finishing tambahan/multi-item secara umum |
| BOM dinamis, scrap, biaya routing dan HPP | HPP manual ada pada baris harga; kalkulator tidak menghitung BOM/routing | Cocok untuk awal Lite; HPP otomatis belum dibangun |
| Snapshot historis tidak dipakai seragam | Quote menyimpan nominal hanya di flash session | Snapshot transaksi permanen belum tersedia |
| Price versioning ada pada sebagian skrip TGM | Row harga bisa di-update; return `version=1` dan `price_updated_at` | `version=1` adalah bentuk payload, **bukan versi aturan harga historis** |

### Kekurangan yang perlu ditutup sebelum POS

- Hasil quote tidak menyertakan snapshot HPP server untuk transaksi; HPP publik tetap harus disembunyikan. Perlu snapshot internal terpisah ketika order dibangun.
- Belum ada effective date/revisi harga, quote ID/revision, penyimpanan snapshot spesifikasi maupun nominal order yang tahan perubahan master.
- Panjang/lebar pada output adalah dimensi setelah minimum; dimensi asli input tidak disimpan terpisah. Untuk SPK produksi perlu membedakan ukuran fisik pesanan dan ukuran ditagihkan.
- Satuan tagihan length/area adalah meter/m², tetapi `unit_name` bebas ditulis. UI menampilkan `billable_qty` bersama unit_name; konfigurasi keliru dapat melabeli nilai m² sebagai cm. Perlu validasi atau field unit tagihan yang jelas.
- Rate tier di bawah 1 diizinkan inputnya, tetapi lookup selalu minimal 1; rate yang hanya mencakup 0–0,99 tidak akan terpilih. Konfirmasi apakah perilaku kompatibilitas ini berlaku untuk seluruh pelanggan atau perlu konfigurasi per produk.
- Empat spesifikasi belum dapat menyimpan banyak finishing/atribut kondisional. Ini tidak menghilangkan nilai kalkulator, tetapi membatasi kesetaraan terhadap katalog TGM.
- Discount, ongkir, pajak, DP, revisi order, harga override terotorisasi dan total invoice belum termasuk ruang lingkup kalkulator sekarang.
- Belum ada contoh harga yang disahkan pengguna dari operasi TGM; angka dalam tes membuktikan desain yang dipilih, bukan otomatis membuktikan kesetaraan bisnis lama.

Kalkulator layak dipertahankan sebagai titik awal. Tidak perlu menyalin bug estimasi TGM; perubahan aturan harus disepakati dan dicatat sebelum seluruh produk dimigrasikan.

## 9. Frontend dan cakupan pengujian

Frontend tersedia untuk auth/settings, pembuatan/perubahan perusahaan, cabang, pengguna, modul lama, katalog/detail produk, price editor dan calculator. Komponen `ErpPage`, `ProductFields`, layouts, sidebar dan workspace switcher dapat dipakai kembali.

`public/build/manifest.json` memuat entrypoints dan halaman fondasi/produk. Review tidak membangun ulang frontend atau menyatakan bundle cocok dengan perubahan backend terbaru. Wayfinder helper generated diabaikan Git dan harus diregenerasi dalam workflow build yang disepakati kelak.

| File tes | Isi yang ditinjau | Batas |
| --- | --- | --- |
| `FoundationTest.php` | 45 deklarasi test: auth, tenant isolation, membership, role, license, feature toggle, constraint, seeder dan proteksi akun | Data provider menambah jumlah kasus; asumsi paket/akses masih konsep lama |
| `ProductCatalogTest.php` | 30 deklarasi test: CRUD, tenant boundary, HPP visibility, overlap, qty/ukuran/tier/specification/minimum/rounding | Helper hanya mengaktifkan license, belum memberi entitlement baru |
| `PlatformModuleAccessTest.php` | Satu test “example” yang membuka `/` | **Belum menguji platform, developer grant, expiry, dependency atau larangan eskalasi tenant** |
| `ExampleTest.php`, Unit example | Test contoh | Bukan penerimaan ERP |

Contoh ketidaksinkronan statis: tes onboarding masih mengharapkan harga `5000000`, sementara konfigurasi tenant baru `0.00`; tes license aktif mengharapkan akses modul, sementara controller kini mensyaratkan grant developer; helper katalog tidak menyiapkan entitlement. Ini menunjukkan perlunya revisi fixture dan ekspektasi. **Tes tidak dijalankan pada review, sehingga tidak ada angka pass/fail suite terkini.**

Coverage baru yang paling bernilai: tenant tidak bisa memperoleh grant sendiri, administrator tenant tidak menjadi platform admin, akses grant disabled/expired menolak URL dan menghilangkan menu, dependency siklus/putus ditolak, upgrade tenant lama menjaga feature yang disembunyikan, role canonical diprovisioning, serta license aktif tanpa grant tetap ditolak secara konsisten.

Source: [FoundationTest](/Volumes/zarnizar/herd_laravel/erp_percetakan/tests/Feature/FoundationTest.php), [ProductCatalogTest](/Volumes/zarnizar/herd_laravel/erp_percetakan/tests/Feature/ProductCatalogTest.php), [stub platform](/Volumes/zarnizar/herd_laravel/erp_percetakan/tests/Feature/PlatformModuleAccessTest.php), [phpunit.xml](/Volumes/zarnizar/herd_laravel/erp_percetakan/phpunit.xml), [manifest](/Volumes/zarnizar/herd_laravel/erp_percetakan/public/build/manifest.json).

## 10. Risiko teknis dan komponen yang harus diperbaiki

| Prioritas | Temuan | Dampak dan arah perbaikan |
| --- | --- | --- |
| P0 | Backend membaca registry/platform tables yang belum ada | Module/catalog, onboarding atau platform check berpotensi error SQL; selesaikan rencana integrasi schema/source sebelum mengaktifkan |
| P0 | Halaman React platform tidak ada | Setelah hak platform diberikan, halaman yang dirender belum tersedia; perlu implementasi lengkap pada tahap berikutnya |
| P1 | Navigasi legacy dan gate entitlement menggunakan sumber berbeda | Menu terlihat tetapi URL menolak; gunakan satu keputusan akses untuk navigasi/backend |
| P1 | Role existing belum punya canonical permission; migration tidak memperbaruinya | Grant tidak cukup untuk akses canonical; provisioning tenant existing perlu teruji |
| P1 | Tes/fixture belum mengikuti registry dan harga default baru | Hasil sukses sebelum perubahan tidak berlaku untuk keadaan sekarang; perbarui dan jalankan tes terisolasi setelah diotorisasi |
| P1 | Snapshot order/harga/HPP permanen dan versi harga belum ada | Jangan memakai flash quote sebagai dasar laporan historis atau tagihan POS |
| P1 | Kesetaraan aturan TGM belum disahkan | Risiko harga jual salah saat konversi unit, preset DTG/DTF dan finishing; gunakan contoh operasional |
| P1 | Membership cabang dan branch scope bisnis belum ada | Sebelum transaksi/keuangan, tentukan staf lintas cabang dan cakupan data yang benar |
| P1 | Hampir seluruh pekerjaan ERP belum masuk commit dan remote belum ada | Remote/clone baru tidak mereproduksi pekerjaan lokal; jangan menganggap source sudah tersimpan di GitHub |
| P2 | Subscription/paket/quota belum dibangun; harga UI/default/data berbeda | Onboarding dan penawaran komersial belum seragam |
| P2 | HPP/routing/form schema TGM belum dimodelkan | Growth/Pro belum dapat dianggap tersedia dari registry saja |
| P2 | Raw/bulk writes dan job future dapat melewati event TenantModel | Tetapkan boundary tenant untuk impor, jobs, file dan query non-model saat komponen itu dibuat |
| P2 | Pengiriman email lokal berupa log; printer/integrasi belum ada | Pengguna baru belum menerima verifikasi sungguhan; validasi kanal pada lingkungan uji berikutnya |
| P2 | Penetapan admin platform lewat command belum masuk audit log | Lengkapi jejak perubahan akses administratif |
| P2 | SQLite saja; build/syntax check bukan runtime check | Verifikasi DB target, browser dan concurrency pada tahap penerimaan terpisah |

P0 berarti perlu diselesaikan sebelum fondasi disebut siap; bukan hasil reproduksi browser atau pernyataan kerusakan database.

## 11. Status Git dan alasan belum tersinkronisasi

| Objek | Hasil read only |
| --- | --- |
| Cabang lokal | `main` |
| HEAD lokal | `52b089b9138cfcbc4ea521c002132a2c452fb0d6` — “Configure Boost post-update script” |
| Commit sebelumnya | “Install Laravel Boost”, “Install Pest” |
| Remote lokal | **Tidak ada** pada `git remote -v` |
| Staging area | Kosong pada `git diff --cached --name-only` |
| Perubahan sebelum laporan review ditambahkan | **19 file tracked dimodifikasi + 195 file untracked = 214 path** memakai `--untracked-files=all` |
| Repository GitHub tujuan | Tersedia, public, default branch `main`, metadata size **0** saat review |

Contoh source untracked: `CalculateProductPrice`, `ModuleRegistry`, migration platform, halaman produk, controller ERP dan tes ERP. Artinya file ada di disk, tetapi belum ada dalam commit lokal yang dapat di-push. `git status` default merangkum beberapa folder; angka 214 memakai inventaris seluruh file, bukan jumlah baris tampilan ringkas.

Alasan yang dapat dibuktikan: **belum ada remote lokal dan pekerjaan ERP belum dikomit**, sedangkan GitHub tujuan masih kosong menurut metadata. Tidak ditemukan bukti kegagalan push atau masalah autentikasi; review tidak mencoba push/fetch untuk menebak alasan tambahan. Instruksi pengguna sebelumnya memang melarang commit/push sampai diminta.

Sesudah laporan ini dibuat akan ada satu tambahan file dokumentasi untracked. Keadaan source sebelumnya tetap dipertahankan. Penyambungan remote, staging, commit dan push menjadi pekerjaan terpisah yang menunggu instruksi.

## 12. Komponen yang dapat dipertahankan

1. **Project dan stack yang ada:** Laravel 13, Inertia/React, Tailwind dan pola folder sekarang; tidak perlu generator project baru atau dependency arsitektur tambahan.
2. **Batas tenant:** scoped context, membership resolution, team permission, model scope, proteksi save/delete dan constraint gabungan.
3. **Fondasi akses:** Gate platform terpisah, permission tenant, license/support separation dan konsep grant/enabled/expiry; selesaikan integrasinya.
4. **Katalog/pricing:** decimal, unit/basis eksplisit, minimum, exact specification, validasi overlap dan proteksi HPP publik; tambahkan kemampuan TGM berdasarkan contoh nyata.
5. **Pola tindakan:** transaksi dan audit pada CreateCompany, price management serta assignment grant; gunakan pola action yang sama saat proses bisnis ditambahkan.
6. **Komponen frontend:** layouts, form, sidebar, switcher, price editor/calculator; pertahankan istilah/alur TGM, kemudian sesuaikan contract props dan modul.
7. **Tes perilaku fondasi/katalog:** banyak batas keamanan/perhitungan sudah dinyatakan; pertahankan tes bernilai, revisi bagian yang sudah berubah, bukan menghapus suite.

## 13. Urutan implementasi paling aman — belum dikerjakan

1. **Sepakati contract akses:** canonical module vs feature toggle, developer vs owner, grant/enable/license/expiry, preset Lite/Growth/Pro dan informasi harga. POS Lite tetap memiliki katalog/pricing/SPK dasar tanpa harus aktif WIP.
2. **Siapkan perbaikan transisi dalam satu paket reviewable:** schema platform, backfill hak lama, provisioning permission, navigasi/backend konsisten, contract props, UI platform dan fixture tes. Jangan menjalankan migration pending sendirian sebagai solusi seluruh masalah.
3. **Buktikan pada DB uji terisolasi terlebih dahulu:** fresh install dan upgrade data lama, tenant baru tanpa grant, developer grant/revoke, expiry, dependency, tenant isolation serta role tenant/platform. Baru tentukan langkah pada DB lokal yang ada setelah mendapat instruksi.
4. **Tuntaskan fondasi cabang dan onboarding:** akses cabang, verifikasi email sungguhan di lingkungan uji, provisioning role yang idempotent, penanganan tenant/module yang belum aktif dan audit admin.
5. **Validasi harga terhadap TGM:** kumpulkan contoh yang disanitasi dan hasil yang disahkan pengguna; mapping unit, tier, ukuran minimum, DTG/DTF, sisi dan finishing. Pisahkan kebutuhan minimal Lite dari Form Builder/BOM lanjutan.
6. **Rancang snapshot permanen:** spesifikasi asli/tertagih, tarif, HPP internal, versi harga, subtotal dan pembulatan; riwayat tidak berubah ketika master diperbarui. Desain ini mendahului penyimpanan order/invoice.
7. **Bangun POS/SPK Lite bila diminta:** pelanggan → SPK/draft → item tervalidasi → pembayaran/DP → invoice/serah dokumen kerja. Harga dihitung server dan side effect/retry aman. Produksi/gudang/laporan lanjutan setelah inti stabil.
8. **Sinkronisasi Git ketika diminta:** review perubahan existing dan file rahasia/generated, hubungkan remote yang benar, lalu commit/push sesuai izin. Dokumentasikan hasil penerimaan sehingga repository merepresentasikan keadaan yang teruji.

Tidak ada tahap di atas yang diimplementasikan dalam review ini. Laporan tidak meminta pengguna menjalankan migration sekarang dan tidak mengubah keputusan yang sudah disepakati secara diam-diam.

## 14. Catatan penutupan

Satu-satunya penambahan pada project oleh STEP 2 adalah **`REVIEW_FONDASI_LARAVEL.md`**. Source, database, audit TGM, dependency dan riwayat Git tidak ditimpa. Pemeriksaan checksum terhadap file source/database yang diinventarisasi sebelum pemeriksaan memastikan langkah baca, PHP lint dan TypeScript check tidak mengubah file tersebut.

**Review selesai. Berhenti dan menunggu instruksi berikutnya.**
