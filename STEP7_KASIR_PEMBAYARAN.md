# STEP 7 — Kasir, Pembayaran dan Invoice Lite

Tanggal laporan: **11 Oktober 2026**. Acuan: [Issue #6](https://github.com/nizarsuryadi89-bit/erp-percetakan/issues/6), [audit TGM](AUDIT_ERP_TGM.md), laporan [STEP 4](STEP4_FONDASI_SAAS.md), [STEP 5](STEP5_KATALOG_PRICING.md), [STEP 6](STEP6_DESKPRINT_SPK.md), dan [arsitektur PWA](docs/PWA_ARCHITECTURE.md).

Baseline wajib `main` setelah PR #5 di-merge oleh pemilik: `c242b327d917b9ced9482c7153ea2b4de3a41598`. Branch `step-7/cashier-payments-lite`, worktree `/Volumes/zarnizar/herd_laravel/erp-percetakan-step7`. Implementasi dilakukan pada source existing; tidak membuat proyek baru, menimpa source pengguna, mengubah dependency atau ERP TGM lama. Database pengguna tidak dimigrasikan. PR untuk review, tanpa merge/deployment/STEP 8.

## 1. Desain yang dicatat sebelum coding

Rancangan awal dicatat sebelum implementasi backend pada dokumen ini. Pemilik kemudian **menyetujui** default: nomor `INV-<cabang>-<bulan>-<urutan>`, DP positif sampai sisa tagihan, penolakan kelebihan bayar, koreksi Owner/Admin dengan alasan tanpa refund uang, serta blokir pembatalan SPK selama invoice aktif.

Keputusan tambahan MVP yang perlu diketahui saat review: counter monoton tidak direset bulanan; void tidak membuka kesempatan menerbitkan invoice utama kedua pada SPK sama; batch 1–10 pembayaran atomik; koreksi selalu membalik penuh satu pembayaran. Tidak ada aturan minimum DP, jatuh tempo baru, bunga, potongan, pajak atau ongkir yang dikarang.

TGM dibaca read only: `Transaksi::action_bayar/cetak_invoice`, `M_pembayaran::prosesTransaksi/insertPembayaran/insertPembayaranDetail`. Alur SPK → periksa tagihan → metode/split pembayaran → invoice/DP/piutang dipertahankan. Kode lama menimpa header, menghapus detail saat retry, membaca kembali master untuk sebagian dokumen, dan memicu produksi saat membayar. Pola tersebut tidak disalin; pembayaran tidak mengubah progres produksi.

## 2. Fitur yang tersedia

- POS saja cukup: daftar SPK confirmed siap tagih, review rincian, penerbitan satu invoice, pembayaran tunai/manual transfer/debit-EDC/e-wallet, DP/cicilan/split pembayaran, piutang dasar, koreksi pencatatan, void dan cetak.
- Invoice mengambil **snapshot item aktif SPK**, bukan harga master. Jumlah snapshot wajib cocok total SPK dan positif. Snapshot perusahaan/cabang/pelanggan berasal dari SPK; issuer dan waktu terbit dicatat tersendiri.
- Harga, nomor, total, tenant, status dan HPP dari browser tidak menentukan invoice. Field item publik disalin dengan allowlist; `cost_snapshot`/HPP tidak masuk daftar, rincian, audit, dokumen atau bukti pembayaran.
- Pembayaran/pembalik tercatat append-only dengan aktor, waktu bayar, waktu pencatatan server, referensi, metode/akun snapshot, alasan koreksi dan referensi asal. Riwayat asal tidak dihapus.
- Owner/Admin mengatur metode/akun tenant, menambah akun baru dan mengaktifkan/nonaktifkan metode. Empat jenis umum tersedia per tenant tanpa identitas/nomor rekening TGM. Non-tunai wajib referensi bukti manual; tidak ada pemeriksaan bank otomatis.
- Daftar invoice mendukung pencarian nomor SPK/invoice/nama snapshot, status dan pagination 20. Piutang menampilkan invoice aktif belum/sebagian bayar; bukan jurnal akuntansi atau sistem penagihan otomatis.

## 3. Schema dan integritas

| Komponen | Isi dan kontrak |
| --- | --- |
| `branches.invoice_sequence` | Counter atomik per cabang, default 0, tidak mengubah counter SPK. Nomor `INV-<ID cabang>-<YYYYMM tenant timezone>-<minimal 6 digit>`, tidak memakai MAX+1 atau mengulang nomor void. |
| `print_orders` | Unique tambahan `(tenant_id, branch_id, id)` untuk FK invoice; source SPK/data existing tetap dipertahankan. |
| `payment_methods` | Tenant, jenis, nama unik per tenant, keterangan akun opsional, active. Snapshot pembayaran tetap tersedia sesudah metode dinonaktifkan. Tidak ada delete/rename metode melalui UI; buat pengganti. |
| `invoices` | FK gabungan tenant/cabang/SPK; unique tenant/SPK selamanya dan tenant/nomor; header dan array snapshot item, total, issued_at, issuer; status issued/void dan proyeksi saldo. |
| `invoice_payments` | FK gabungan tenant/cabang/invoice, FK tenant/metode, FK pembalik ke pembayaran pada invoice/cabang/tenant yang sama; unique satu pembalik per pembayaran asal. Amount positif untuk payment maupun reversal; reversal dikurangkan dari ledger. |
| `invoice_events` | Audit per invoice dengan tenant/cabang, aktor/nama, action, metadata before/after, alasan dan waktu. Audit tenant di `audit_logs` juga ditulis dalam transaksi yang sama. |
| `cashier_requests` | Receipt tenant/aktor/key unik, fingerprint resource/operasi/payload dan hasil. Berbeda dari receipt Deskprint dan tidak memerlukan `spk.manage`. |

Nominal memakai **BigDecimal**, input string decimal maksimal 13 digit integer + 2 pecahan, tanpa kalkulasi float di backend. MySQL menggunakan `DECIMAL(15,2)`. Khusus SQLite, empat kolom uang baru (`total`, `paid_amount`, `balance`, `amount`) memakai representasi string decimal dengan cast `decimal:2`, karena numeric affinity SQLite dapat menjadi REAL dan menghilangkan presisi pecahan pada nominal besar. Tidak ada SQL SUM atas kolom string tersebut; saldo dihitung dengan BigDecimal dari ledger. Tes batas `9999999999999.99` mempertahankan sisa satu sen secara exact. MySQL runtime belum dibuktikan.

`paid_amount`, `balance`, `payment_status` merupakan **proyeksi untuk filter/index**, diperbarui atomik dari ledger oleh server. Rincian/cetak/daftar menghitung kembali ringkasan dari entri asli, tidak mempercayai input frontend atau menghitung ulang harga master. Nilai/proyeksi akibat SQL administratif tidak diaudit otomatis; model guard bukan signed/WORM storage. Positivitas, jenis entri dan aturan saldo ditegakkan action/validation; FK dan uniqueness ditegakkan DB.

## 4. Pembayaran, status dan pembatalan

| Situasi | Perilaku |
| --- | --- |
| Penerbitan | Hanya SPK confirmed, minimal satu item, total positif dan konsisten. Invoice langsung diterbitkan atomik; tidak ada draft invoice tersimpan. `created_at` waktu pencatatan, `issued_at` waktu penerbitan, `paid` hasil saldo. |
| Belum bayar | Jumlah payment sah minus reversal = 0 → `unpaid`. |
| DP/sebagian | Nilai bersih positif dan kurang total → `partially_paid`; sisa terlihat sebagai piutang operasional. DP bukan jenis pembayaran baru. |
| Lunas | Nilai bersih sama total → `paid`. Pembayaran tambahan ditolak. |
| Split | 1–10 metode/entri dalam satu submit, seluruh batch berhasil atau seluruhnya rollback. Waktu bayar memakai timezone tenant saat input; waktu server terpisah. Tanggal invalid/masa depan ditolak. |
| Overpay/kembalian | Total submit melebihi sisa, nilai nol/negatif/malformed/lebih dari 2 pecahan ditolak; tidak ada input kembalian. |
| Koreksi | Hanya permission sensitif Owner/Admin, alasan dan pengakuan catatan salah wajib. Menambah satu reversal penuh; original payment tetap ada, receipt retry mencegah duplikasi. Pembalik tidak bisa dibalik lagi. |
| Refund uang | **Belum tersedia**. Reversal hanya koreksi pencatatan salah, tidak menyatakan uang telah dikembalikan. UI menyampaikan batas ini. Pembayaran yang benar-benar diterima tidak boleh diperlakukan sebagai refund lewat menu koreksi. |
| Void | Hanya Owner/Admin dengan alasan dan pembayaran bersih nol. Invoice dan nomor tetap disimpan; collectible menjadi 0, total historis tetap. Tidak boleh issue ulang SPK sama; buat SPK pengganti bila perlu. |
| Batal SPK | Diblokir selama ada invoice issued, termasuk belum bayar. Pengelola harus review dan void dahulu; setelah itu SPK boleh cancelled. Model update biasa juga menolak pembatalan yang meninggalkan invoice aktif. |

Lock cabang dipakai bersama Deskprint untuk menyerialisasi issue vs cancellation. Writer pertama menyentuh baris cabang sebelum pembacaan dalam transaksi; pada MySQL memperoleh row lock, pada SQLite serialisasi writer. Akses diperiksa sebelum dan sesudah lock. Lock invoice/metode dipakai saat mutasi. Receipt mengembalikan hasil existing untuk retry identik; key dengan payload/operasi/resource lain ditolak. Permission yang dicabut tetap diperiksa sebelum retry. Retry transaksi maksimal 5 melalui Laravel. Rujukan: [transactions](https://laravel.com/framework/docs/13.x/database#database-transactions), [pessimistic locking](https://laravel.com/framework/docs/13.x/queries#pessimistic-locking).

## 5. Permission, frontend dan privasi

| Role default | Baca/cetak dengan POS + assignment cabang | Issue / record | Reverse / void / metode |
| --- | --- | --- | --- |
| Owner / Administrator | Ya; `cabang.manage` dapat mengakses cabang aktif | Ya | Ya |
| Kasir | Ya, cabang ditugaskan | Ya | Tidak |
| Read Only | Ya, cabang ditugaskan | Tidak | Tidak |
| Keuangan / Operator Produksi | Tidak otomatis mendapat POS | Tidak | Tidak |
| Custom | Sesuai grant/permission/assignment eksplisit | Tidak diberi otomatis | Tidak diberi otomatis |

Seluruh route/operasi memakai membership aktif, tenant scope, branch scope eksplisit, lisensi, entitlement dan POS permission. FINANCE/ANALYTICS/PRODUCTION tidak diperlukan. Backfill hanya menambah grant pada default role canonical, tidak mengganti ID/assignment/licence/modul atau mengaktifkan custom roles. `ConfigureTenantRoles` menyediakan permission yang sama untuk tenant baru/recovery existing.

Layar memakai tema/layout STEP 6: desktop rincian + panel pembayaran; smartphone/tablet langkah rincian → pembayaran → konfirmasi dengan target 44px, input angka, pending/disabled, batch metode, ringkasan server dan error/retry. Isian tidak disimpan di localStorage, Cache Storage, IndexedDB atau service worker. Perubahan menunggu konfirmasi server. Tautan invoice dari SPK menghormati hak baca; Read Only tidak diarahkan ke form issue berhak tulis.

Invoice browser-print A4 dan tampilan thermal 72mm; ukuran kertas sebenarnya tetap dipilih pada dialog printer. Bukti pembayaran individual menunjukkan jika catatan asal sudah dikoreksi. Tidak ada silent printing, ESC/POS, payment gateway, NFC, koneksi EDC, PDF server, QR publik atau pengiriman invoice otomatis.

Respons sukses dan error kasir memakai **private, no-store**. Halaman kasir meminta enkripsi history Inertia. Domain aplikasi melalui HTTP ditolak 503 dengan petunjuk HTTPS; HTTP hanya diizinkan pada localhost/loopback untuk secure-context development. HTTPS/reverse proxy yang benar tetap perlu diverifikasi saat penerapan; tidak ada perubahan TLS/deployment di tahap ini.

Logout lewat response contract Fortify meminta clear history **setelah** session invalidation; tenant/cabang switch meminta clear history sebelum halaman konteks baru. Boundary frontend mengirim sinyal tanpa PII melalui BroadcastChannel agar tab lain menghapus history dan pindah ke halaman publik; halaman yang dipulihkan BFCache dimuat ulang untuk memeriksa session server. Kunci history berada pada sessionStorage sesuai mekanisme Inertia, bukan data pelanggan/payment plaintext. Rujukan [history encryption Inertia v3](https://inertiajs.com/docs/v3/security/history-encryption). Halaman lama di luar kasir belum semuanya mengenkripsi history; ini bukan klaim audit PWA menyeluruh.

WebCrypto, BroadcastChannel, Back/Forward, logout lintas-tab, tenant switch/offline dan browser yang tidak mendukung API tersebut **belum diuji langsung**. Dukungan riil perlu disahkan sebelum rollout; jangan menganggap server protocol tests sebagai bukti perilaku browser. Respons no-store/enkripsi bukan service worker/offline checkout.

## 6. File utama

| Area | Referensi |
| --- | --- |
| Operasi bisnis | [ManageInvoice](app/Actions/ManageInvoice.php), [CashierTransaction](app/Actions/CashierTransaction.php), [CashierAccess](app/Support/CashierAccess.php). |
| HTTP | [InvoiceController](app/Http/Controllers/InvoiceController.php), [PaymentMethodController](app/Http/Controllers/PaymentMethodController.php), Form Requests, [EnsureCashierReady](app/Http/Middleware/EnsureCashierReady.php), [routes](routes/web.php). |
| Model/schema | `Invoice`, `InvoicePayment`, `InvoiceEvent`, `PaymentMethod`, `CashierRequest`, factory/seeder dan dua migration STEP 7. Seeder baru tidak memasukkan invoice/pembayaran fiktif ke DB pengguna. |
| Layar | [daftar](resources/js/pages/cashier/index.tsx), [terbitkan](resources/js/pages/cashier/create.tsx), [rincian](resources/js/pages/cashier/show.tsx), [cetak](resources/js/pages/cashier/print.tsx), [metode](resources/js/pages/cashier/methods.tsx), `InvoiceLines`. |
| History/context | [ClearHistoryLogoutResponse](app/Support/ClearHistoryLogoutResponse.php), provider Fortify, tenant/branch switch, [PrivateHistoryBoundary](resources/js/components/private-history-boundary.tsx), bootstrap cache-error handler. |
| Tes | [CashierTest](tests/Feature/CashierTest.php), [CashierConcurrencyTest](tests/Feature/CashierConcurrencyTest.php), [worker disposable](tests/Fixtures/cashier-worker.php). |

Dependency terpasang: PHP 8.4.16, Laravel 13.35.0, Inertia Laravel 3.5.1, Fortify 1.41.0, Spatie Permission 8.3.0, Wayfinder 0.1.21, Pest 5.3.1; Vite 8.3.4. Tidak ada dependency baru.

## 7. Hasil pengujian aktual

Seluruh feature tests memakai SQLite `:memory:` melalui guard TestCase/phpunit.xml. Concurrency memakai SQLite baru dari tempnam, worker testing dengan guard prefix dan directory sementara, dibersihkan setelah tes. Migration/factory pada DB testing bukan penerapan pada DB pengguna.

| Pemeriksaan | Hasil |
| --- | --- |
| Seluruh suite `php artisan test --compact` | **296 passed, 1.832 assertions**, 51,034 detik. Termasuk seluruh tes STEP 4–6. |
| STEP 7 `CashierTest` + `CashierConcurrencyTest` | **59 passed, 488 assertions**, 21,903 detik. Merupakan bagian dari suite lengkap. |
| PHP lint | **193 file lulus**, 0 kegagalan. |
| Pint `--dirty --format agent` | Lulus; format diperbaiki sebelum suite final. |
| TypeScript `tsc --noEmit --incremental false` | Lulus. |
| Vite `npm run build` | Lulus, 2,01 detik; lima halaman kasir masuk build. |
| Routes / whitespace | 33 route transaksi terdaftar; `git diff --check` lulus. |

Cakupan: POS-only, dua harga pengguna, DP/split/paid, sisa piutang, exact cents/nominal batas, overpay/negatif/float/decimal invalid, batch atomik/max/required, double-click/key conflict, koreksi/reversal/idempotency/void, pembayaran sah mencegah void, cancelled/draft/corrupt SPK, snapshot sesudah master berubah sebelum dan sesudah issue, session restart, HPP, tenant/cabang, receipt salah invoice, FK/unique, role/membership/lisensi/entitlement revoke, custom POS kasir tanpa spk.manage, metode tenant/nonaktif/referensi/tanggal, pending schema, upgrade preservasi data, HTTPS/history flags dan logout/switch flags.

Contoh bisnis tetap hanya dua yang telah disahkan: HVS 100 gr 2 sisi 20 × Rp4.100 = **Rp82.000**; lanyard 2 cm + stopper 5 × Rp12.000 = **Rp60.000**; invoice gabungan **Rp142.000**. Fixture tidak menganggap ukuran A3/A4, mesin, harga qty lain atau HPP sebagai data operasional tersahkan dan tidak di-seed ke DB pengguna. Validasi kategori pricing lainnya tetap mengikuti matriks STEP 5.

Concurrency nyata beberapa proses: empat request issue (dua key identik) menghasilkan **satu invoice** dan tiga receipt issue; dua kasir masing-masing mencoba Rp50.000 terhadap Rp82.000 menghasilkan satu berhasil/satu ditolak; tiga retry Rp32.000 menghasilkan satu payment; tiga retry reversal menghasilkan satu pembalik. Hasil: 1 invoice, 2 payment + 1 reversal, 6 receipt dan saldo akhir bayar Rp50.000/sisa Rp32.000. Race issue vs cancellation menghasilkan tepat satu operasi berhasil: SPK confirmed berinvoice atau SPK cancelled tanpa invoice aktif. Bukti ini terbatas SQLite disposable, bukan bukti MySQL atau load test.

Tes awal mendeteksi route/layer UI belum dibuat, syntax Form Request saat generator, fixture revoke yang kehilangan tenant selection setelah 403, ekspektasi count receipt concurrency yang salah, serta tes Inertia yang tidak mengirim asset version sesudah build dan mendapat 409. Source syntax diperbaiki; fixture/assertion disesuaikan dengan perilaku aplikasi/framework. Tes protokol history membaca payload halaman initial, tidak mematikan guard keamanan. Hasil final berasal dari pengujian lokal, **bukan klaim GitHub Actions**.

Log sementara: `/private/tmp/erp-step7-focused-final.log`, `erp-step7-full-final.log`, `erp-step7-lint-final.json`, `erp-step7-pint-final.log`, `erp-step7-build-final.log`. Tidak dikomit dan bukan arsip CI permanen.

## 8. Migration pending dan verifikasi penerapan

Migration baru:

1. [2026_10_10_141350_create_cashier_tables.php](database/migrations/2026_10_10_141350_create_cashier_tables.php): lima tabel, counter invoice, index relasi SPK dan metode default tenant existing.
2. [2026_10_10_141351_grant_cashier_permissions.php](database/migrations/2026_10_10_141351_grant_cashier_permissions.php): lima permission dan grant konservatif default roles, cache Spatie dibersihkan.

Pemeriksaan SQLite pengguna dilakukan **read only** (`mode=ro`, query_only): **8 migration applied**. Source utama bersih pada main `c242b327`. Lima migration STEP 4–6 masih pending (platform module access, platform audit logs, product quotes, Deskprint tables, Deskprint permissions); dua migration STEP 7 juga belum diterapkan dan baru berada di branch ini. **Tidak ada perubahan DB pengguna.**

Sebelum penerapan yang mendapat instruksi terpisah: verifikasi baseline/pending schema dan backup di luar Git, uji salinan DB disposable dengan mesin target, periksa pemetaan role/branch assignment dan metode akun, lalu tentukan window penerapan. Jangan rollback/migrate:fresh untuk memaksa schema cocok. Down migration menghapus ledger/invoice/events/receipt/metode/counter; tidak aman untuk data terisi. Bila schema parsial atau index versi berbeda ditemukan, siapkan forward repair khusus pada salinan data.

Urutan verifikasi setelah PR ditinjau:

1. Pada lingkungan disposable HTTPS/localhost, aktifkan hanya POS, tetapkan lisensi/membership/cabang Kasir, konfigurasi metode/akun, lalu confirmed SPK → invoice → DP → split pelunasan.
2. Periksa master harga berubah tidak memengaruhi invoice; retry setelah response putus tidak menggandakan catatan; koreksi salah input mempertahankan bukti; void saat paid diblokir.
3. Uji browser 360px, tablet dan desktop: keyboard/sentuh, error, loading, jaringan putus, saldo setelah dua kasir, dua tab/logout/switch tenant, Back/Forward/BFCache dan inspeksi storage. Tidak boleh ada sukses palsu atau data konteks lama.
4. Cetak invoice/bukti A4 dan thermal pada printer pelanggan, pilih kertas/margin dan pastikan status/koreksi serta wrapping terbaca.
5. Uji upgrade, concurrency, constraint dan decimal pada MySQL disposable bila itu mesin tujuan. Tidak ada claim MySQL tanpa bukti.

## 9. Batas dan risiko yang masih terbuka

Browser/CUA gagal dengan **“Unable to load browser request-header policy”** pada inventory dan retry membuka tab; tidak ada screenshot/E2E atau verifikasi enkripsi/cross-tab di browser. Dua screenshot referensi TGM tidak tersedia dan tidak diklaim telah dilihat. Printer/perangkat, MySQL, beban tinggi, restore backup dan deployment belum diuji. Build/server tests tidak menggantikan verifikasi tersebut.

Riwayat pembayaran/event belum dipaginasi; retensi dan pengarsipan receipt/ledger belum ditentukan. Serialisasi per cabang sengaja konservatif dan perlu load test sebelum tenant besar. Proyeksi/filter mengandalkan mutasi melalui action; SQL langsung/bulk administrative write dapat melanggar kontrak dan perlu kontrol operasional terpisah. Tindakan admin hanya koreksi pencatatan dengan pengakuan manusia, bukan validasi bank atau bukti refund.

Belum dibuat: refund nyata/partial reversal, invoice kedua/pengganti pada SPK sama, invoice draft, perubahan snapshot invoice, payment gateway/bank/EDC otomatis, discount/tax/shipping, due-date baru/penagihan otomatis, jurnal/neraca/laba rugi, kasir shift/rekonsiliasi kas, produksi/gudang, paket/billing SaaS, import TGM, service worker/PWA installable/offline checkout dan silent print. Semuanya pekerjaan terpisah, bukan syarat premium untuk memakai POS Lite saat ini.

Pekerjaan berhenti pada laporan dan PR STEP 7. Merge, migration pengguna, deployment dan STEP 8 menunggu instruksi berikutnya.
