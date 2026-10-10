# STEP 4 — Fondasi SaaS, modul dan akses platform

Tanggal: 10 Oktober 2026. Acuan: [Issue #1](https://github.com/nizarsuryadi89-bit/erp-percetakan/issues/1), [AUDIT_ERP_TGM.md](AUDIT_ERP_TGM.md), dan [REVIEW_FONDASI_LARAVEL.md](REVIEW_FONDASI_LARAVEL.md).

Implementasi dan verifikasi STEP 4 selesai pada branch `step-4/saas-module-foundation`. Database pengembangan belum dimigrasikan. STEP 5 belum dimulai.

- Baseline: `9f09918c0994827fdc810cb1dcdca24c288abd21` (`main`).
- Commit implementasi: [`0072e2906caa82f9d29ba31df848d2529ce9fd8c`](https://github.com/nizarsuryadi89-bit/erp-percetakan/commit/0072e2906caa82f9d29ba31df848d2529ce9fd8c).
- Pull Request: [#2 — STEP 4: satukan akses modul SaaS dan lengkapi panel platform](https://github.com/nizarsuryadi89-bit/erp-percetakan/pull/2), berstatus open; tidak di-merge. Commit dokumentasi setelah implementasi hanya melengkapi tautan ini.
- Worktree implementasi: `/private/tmp/erp-percetakan-step4-20261010`.
- Proyek utama `/Volumes/zarnizar/herd_laravel/erp_percetakan` tetap pada baseline `main`, bersih saat pemeriksaan akhir sebelum commit.

## 1. Kondisi yang dikonfirmasi

Source baseline memang telah memiliki delapan registry modul dan backend platform parsial, tetapi guard/navigasi masih mencampur enam toggle lama, role masih memakai ability lama, onboarding tidak melengkapi entitlement, dan dua halaman platform belum ada. Tes lama juga mengharapkan harga Rp5 juta sementara konfigurasi default bernilai 0.

Pemeriksaan ulang SQLite pengembangan menggunakan koneksi `mode=ro` dan `PRAGMA query_only=ON` menunjukkan **8 migration tercatat; migration platform `2026_10_10_041453_create_platform_module_access_tables` belum dijalankan**. Karena itu perbaikan backfill dilakukan pada migration pending tersebut. Tidak ada koneksi Laravel ke database pengembangan selama implementasi/pengujian ini.

PHP 8.4 / Laravel 13, Inertia 3, React/TypeScript dan struktur source existing dipertahankan. Tidak ada perubahan dependency atau logika `CalculateProductPrice`.

## 2. Kontrak akses tunggal

Akses modul bisnis diberikan hanya ketika semua syarat berikut terpenuhi:

1. Pengguna terautentikasi/terverifikasi pada kelompok route terkait, tenant aktif, membership tenant aktif dan konteks tenant berhasil diselesaikan server.
2. Lisensi tenant berstatus aktif, telah diaktivasi, serta belum kedaluwarsa menurut waktu server.
3. Modul registry aktif; entitlement milik tenant `is_granted=true` dan `is_enabled=true`; waktu mulai sudah berlaku dan expiry belum tercapai.
4. Seluruh dependensi transitif memenuhi syarat registry/entitlement yang sama; dependensi berulang atau tidak valid menolak akses.
5. Pengguna memiliki permission canonical `<CODE>.view` dalam team/tenant yang sedang aktif. Permission dependensi tidak wajib dimiliki pengguna yang hanya mengerjakan modul turunannya; entitlement dependensi tetap wajib tersedia bagi tenant.

`ModuleAccess` menerapkan keputusan ini untuk `EnsureModuleAccess` dan navigasi Inertia. Direct URL, alias lama dan menu mengacu pada keputusan yang sama. Harga dan status lisensi di UI berasal dari data server; perhitungan masa lisensi oleh jam browser dan label Rp5 juta hardcoded dihapus. Nilai lisensi existing tetap dihormati; harga kosong/default 0 ditampilkan sebagai belum ditetapkan.

Platform admin merupakan akun terverifikasi dengan baris aktif di `platform_administrators`. Gate `platform.manage` diputuskan khusus dan tidak dapat ditimpa permission Spatie bernama sama dari tenant. Role Owner/Administrator tenant tidak memberi akses platform. Admin platform tidak otomatis mendapat membership maupun akses data operasional tenant.

Pengaturan modul tenant hanya mengubah `is_enabled` pada grant yang sudah diberikan developer; tidak dapat memberikan grant, memperpanjang masa akses, atau mengaktifkan lisensi sendiri. Menyalakan modul mewajibkan dependensi tersedia; mematikan modul yang masih dipakai modul aktif lain ditolak dengan pesan yang dapat ditindaklanjuti. Pencabutan grant tidak menghapus data produk/operasional.

## 3. Transisi legacy dan provisioning

`tenant_module_entitlements` menjadi satu sumber state akses modul. Enam baris `tenant_modules` lama dipertahankan sebagai bukti asal migrasi dan tidak dibaca guard/navigasi atau diperbarui onboarding. Alias route lama tetap diterima dan dinormalisasi ke code canonical.

| Canonical | Syarat backfill grant dari legacy |
| --- | --- |
| POS | `transaksi` enabled |
| PRODUCTION | `bom`, `routing` dan `produksi` semuanya enabled, serta POS granted |
| FINANCE | `keuangan` enabled, serta POS granted |
| ANALYTICS | `laba_rugi` enabled, serta POS, PRODUCTION dan FINANCE granted |
| INVENTORY, CRM, HRM, AI_ASSISTANT | Tanpa grant otomatis |

Untuk migrasi legacy, `is_enabled` canonical mengikuti hasil grant konservatif di atas. Nilai false atau toggle yang hilang tidak memberi grant. Gabungan BOM/routing/produksi sengaja memakai semua syarat; jika sebelumnya hanya sebagian aktif, PRODUCTION ditahan untuk review developer agar fitur yang dulu disabled tidak terbuka. Aktivasi ulang memerlukan grant developer dan pengaktifan tenant sesuai kebutuhan.

Migration menyediakan permission canonical dan enam role default, mempertahankan ID/assignment role lama, serta mencatat `modul.legacy_dikonversi` per tenant. Permission legacy tersimpan untuk kompatibilitas jejak data, tetapi guard modul tidak lagi memakainya. Data lisensi/harga, produk dan legacy toggle tidak diubah.

`ProvisionTenantAccess` dipakai onboarding, penetapan grant dan recovery. Tenant baru mendapat delapan baris entitlement **tanpa grant** serta lisensi pending. Role default dan entitlement yang hilang dapat disediakan ulang tanpa duplikasi. Grant, pilihan enabled dan masa akses existing tidak di-reset.

Command `erp:recover-access <UUID> --dry-run` memeriksa kesiapan/tenant tanpa menulis; eksekusi tanpa dry-run memulihkan role default dan baris entitlement kosong dalam transaksi, lalu mencatat audit. Recovery tidak mengimpor ulang toggle legacy dan tidak memberikan grant baru. Enam role default dikelola sistem: permission-nya disinkronkan ke matriks canonical, sehingga perubahan manual pada role default perlu ditinjau sebelum recovery. Role khusus di luar enam nama tersebut tidak dipetakan otomatis.

## 4. Platform UI dan audit

- `platform/index`: daftar perusahaan, pencarian, jumlah pengguna dan pagination.
- `platform/company-modules`: checkbox grant canonical, penyesuaian pilihan dependensi, status lisensi, batas akses perusahaan dan per modul berdasarkan zona waktu tenant.
- Server memvalidasi modul aktif, duplikasi, dependensi, tanggal, serta masa modul hanya untuk code yang dipilih; perubahan dilakukan dalam transaksi dengan penguncian tenant.
- Grant ulang mempertahankan pilihan modul yang disembunyikan tenant dan expiry yang tidak dikirim. Revoke hanya mencabut akses.
- Perubahan grant/status/masa akses dicatat di audit tenant dengan aktor developer. Command pemberian/pencabutan platform admin mencatat `platform_audit_logs` global; sumber terminal dicatat sebagai `artisan`, tanpa mengarang identitas aktor OS.
- Sidebar, dashboard, workspace dan halaman pengaturan modul memakai canonical codes serta props yang selaras. Jika tabel platform belum tersedia, jalur dashboard menampilkan status persiapan dan jalur modul/onboarding yang diuji memberi 503 terkontrol.

## 5. POS Lite dan strategi PWA

POS tidak memerlukan PRODUCTION. Katalog dan quote server memakai hak POS; tes katalog/pricing dijalankan dengan grant POS saja. SPK dasar dan ringkasan penjualan dasar merupakan lingkup POS pada tahap berikutnya. ANALYTICS tetap untuk analisis lanjutan yang bergantung pada data produksi/keuangan. Belum ada implementasi order, SPK, pembayaran, billing subscription atau ringkasan penjualan pada STEP 4.

[docs/PWA_ARCHITECTURE.md](docs/PWA_ARCHITECTURE.md) menetapkan **online-first** sesuai keputusan dalam issue: semua operasi bisnis dan hak tenant disahkan server; cache hanya aset build publik berversi dan halaman offline generik. HTML/props Inertia pribadi, API, pelanggan, token, pricing terotorisasi, transaksi dan data tenant tidak boleh masuk service worker cache.

Dokumen mencakup manifest/ikon/scope, HTTPS, domain tenant, pembaruan versi, responsive/touch layout, hardware, kebersihan logout/tenant switch, history Inertia, browser support dan rencana pengujian. Resolver custom domain, history encryption/invalidasi lintas tab, header cache pribadi, manifest, service worker dan installability masih pekerjaan berikutnya. Tidak ada plugin PWA baru atau klaim aplikasi sudah installable.

## 6. Pengujian aktual

Seluruh Artisan/build dijalankan di worktree terisolasi dengan environment testing sendiri; `.env`/SQLite pengguna tidak disalin. Vendor dan node_modules disalin untuk menjaga autoload/cache tetap berada dalam worktree uji. PHPUnit memaksa SQLite `:memory:`, DB_URL kosong, cache/session array; `Tests\TestCase` menolak koneksi selain database memory sebelum trait migrasi pengujian dijalankan. Migration/down dalam tes upgrade hanya menyentuh database memory disposable.

| Pemeriksaan akhir | Hasil |
| --- | --- |
| `vendor/bin/pint --dirty --format agent` | Lulus; format PHP diperbaiki |
| `php -l` pada app/bootstrap/config/database/routes/tests (120 file, mengecualikan cache hasil runtime) | 120 lulus |
| `node node_modules/typescript/bin/tsc --noEmit --incremental false` | Lulus, exit 0 |
| `npm run build` | Lulus, Vite 8.3.4; 2.34 detik; kedua halaman platform tercatat di manifest |
| `php artisan test --compact` setelah build | **166 passed, 898 assertions, 16.305 detik** |
| `git diff --check` | Lulus |
| Pemeriksaan kandidat file sensitif/pola secret | Tidak ditemukan file env/database/backup/log/key atau pola token/private key yang ikut kandidat commit; pemeriksaan pola bukan audit keamanan menyeluruh |

Cakupan perilaku termasuk fresh install, tenant baru tanpa grant, upgrade legacy enabled/disabled, konservasi lisensi/toggle lama, migrasi role, recovery berulang, platform admin vs enam role tenant, forged permission, grant/revoke, role vs menu/URL, expiry/start time, dependency/cycle, cross-tenant, audit serta katalog/pricing existing. Tes lama yang relevan dipertahankan dan disesuaikan pada kontrak canonical.

Kegagalan yang ditemukan dan diselesaikan:

- Baseline FoundationTest: 8 passed, 1 failed pada ekspektasi harga Rp5 juta vs konfigurasi 0. Ekspektasi sekarang mengikuti konfigurasi tanpa mengubah harga komersial.
- Setelah transisi awal: 111/118 tes fondasi+katalog lulus; 7 gagal pada ekspektasi role legacy dan seeder. Ekspektasi dipindahkan ke canonical entitlement/permission dan diperluas ke delapan modul; kemudian 118/118 lulus.
- TypeScript awal gagal karena chaining `form.transform(...).patch(...)`; API Inertia 3 mengembalikan void pada transform, sehingga submit dipisah. Pemeriksaan ulang lulus.
- Full suite awal 165/166 lulus; ExampleTest mendapat 500 karena manifest Vite belum ada pada worktree baru. Build menyediakan manifest, lalu 166/166 lulus tanpa menghapus tes.
- Build pertama terhalang EPERM saat Vite mencoba menulis `.vite-temp` melalui symlink node_modules ke proyek utama. Dependency disalin ke worktree uji dan build ulang lulus; tidak diperlukan perubahan dependency/source utama.

Tidak ada pengujian browser visual/end-to-end, Lighthouse, PWA installability, printer/scanner, email sungguhan atau MySQL. Hasil SQLite tidak membuktikan perilaku DDL/locking/collation MySQL. Tes platform memastikan render/props Inertia, bukan screenshot/browser execution.

## 7. Rencana migrasi database pengembangan — belum dieksekusi

Langkah berikut hanya dijalankan setelah review PR, bukti tes dan **persetujuan khusus pemilik database** sebagaimana diminta Issue #1.

1. Verifikasi ulang branch/commit, koneksi database target dan daftar migration dengan pemeriksaan read-only. Pastikan migration platform lama masih pending, tabel platform belum tercipta sebagian, serta tersedia inventaris grant/toggle/role/lisensi sebelum perubahan. Jangan mengasumsikan snapshot 10 Oktober masih berlaku.
2. Buat backup konsisten database pengembangan di luar Git dan verifikasi dapat dipulihkan pada salinan disposable. Hentikan penulisan aplikasi selama snapshot/penerapan sesuai mekanisme database yang dipakai.
3. Tinjau matriks legacy per tenant, khususnya kombinasi BOM/routing/produksi sebagian aktif dan role yang pernah dimodifikasi manual. Konfirmasi hasil grant konservatif yang diharapkan.
4. Terapkan source PR yang sudah disetujui dan jalankan hanya pending migration dengan `php artisan migrate --no-interaction` pada target yang telah dikonfirmasi. Dua migration terkait adalah registry/access `2026_10_10_041453` dan audit platform `2026_10_10_055750`. Jangan memakai `migrate:fresh`, `db:wipe` atau rollback pada data pengguna.
5. Verifikasi delapan registry, graph dependensi, delapan entitlement per tenant, role/assignment, lisensi/produk tetap sesuai snapshot, dan audit konversi. Jalankan `erp:recover-access <UUID> --dry-run` bila perlu; recovery menulis hanya setelah peninjauan matriks role. Jangan menjalankan seeder legacy untuk mengaktifkan menu.
6. Bila diperlukan, berikan admin platform secara eksplisit kepada akun developer terverifikasi melalui `erp:platform-access <email-developer>`. Jangan otomatis mengangkat owner tenant. Grant modul diberikan dari panel sesuai kebutuhan pelanggan.
7. Build frontend, lalu uji login, switch tenant, direct URL/menu, grant/revoke, expiry dan katalog POS pada browser. Uji MySQL disposable sebelum memilih MySQL untuk deployment; deployment tidak termasuk tahap ini.

**Kondisi berhenti:** jika migration platform versi lama ternyata sudah diterapkan di instalasi lain, atau ada tabel parsial, jangan menjalankan ulang file yang diedit/rollback untuk memaksa cocok. Perubahan file migration yang sudah applied tidak akan otomatis memperbaiki datanya. Inventarisasi data dan siapkan forward repair khusus yang diaudit pada salinan database; minta persetujuan sebelum menyentuh data pengguna. PR ini dibuktikan untuk fresh install dan upgrade dari skema legacy yang platform migration-nya masih pending.

## 8. Risiko dan batas yang tersisa

- Migrasi legacy sebagian aktif dapat mengurangi akses PRODUCTION/ANALYTICS sampai developer dan tenant meninjau ulang grant/pilihan enabled. Ini mencegah akses baru ke feature yang dulu disabled.
- Recovery menyinkronkan enam role default; custom permission pada role default perlu review, dan role bernama lain masih memerlukan pemetaan tersendiri.
- Schema readiness/dependency lookup menjalankan beberapa query; belum dilakukan load test untuk tenant besar atau admin berjumlah banyak.
- UI penelusuran audit lengkap, paket Lite/Growth/Pro/kuota/billing dan modul operasional masih belum tersedia. Registry/placeholder tidak berarti implementasi bisnisnya selesai.
- Kebersihan browser history/prefetch dan domain routing harus ditutup sebelum PWA dipasarkan; strategi sudah dicatat, verifikasi perangkat belum dilakukan.
- Belum ada bukti MySQL, browser visual, hardware atau deployment. Main dan database pengembangan belum menerima implementasi branch ini.

Pekerjaan berhenti pada STEP 4 untuk review Pull Request; tidak ada merge, deployment atau implementasi STEP 5.
