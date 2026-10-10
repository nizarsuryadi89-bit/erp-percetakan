# Arsitektur PWA ERP Percetakan

Status: keputusan desain STEP 4, 10 Oktober 2026. Implementasi manifest, service worker, installability dan transaksi offline belum dibuat. Dokumen ini memenuhi persiapan PWA pada [Issue #1](https://github.com/nizarsuryadi89-bit/erp-percetakan/issues/1); satu Laravel 13 + Inertia 3 + React/TypeScript codebase tetap dipakai.

## 1. Keputusan MVP: online-first

Server wajib mengesahkan tenant aktif, membership, role, lisensi, entitlement, harga, order, pembayaran dan stok setiap kali operasi dilakukan. UI memakai keputusan akses server. Status online pada browser hanya petunjuk konektivitas, bukan bukti bahwa server menerima operasi.

Saat koneksi terputus, tampilkan fallback offline generik dan tombol coba lagi. Jangan menampilkan data pelanggan, harga terotorisasi, order terakhir, atau dashboard dari cache. Jangan memberi tanda transaksi berhasil sebelum server mengonfirmasi. Pembuatan order/POS/SPK sendiri berada di tahap berikutnya.

Transaksi offline dan sinkronisasi antarperangkat memerlukan desain terpisah: antrean terenkripsi dengan pemilik yang jelas, idempotency key, konflik stok/harga, kedaluwarsa otorisasi, rekonsiliasi pembayaran, serta penghapusan data setelah logout. Semuanya harus disahkan dan diuji sebelum diimplementasikan; STEP 4 tidak membuat antrean atau background sync.

## 2. Manifest, identitas aplikasi dan domain

Rencana manifest publik tanpa data pengguna: `name: ERP Percetakan`, `short_name: Print ERP`, `display: standalone`, `lang: id`, warna tema, ikon PNG 192/512, ikon maskable dan apple-touch-icon yang ditinjau secara visual. Identitas aplikasi (`id`) harus stabil per origin; `start_url` menuju entry aplikasi tanpa token/tenant ID pribadi. `scope` manifest dan scope service worker harus mengikuti base path aplikasi, tidak diasumsikan selalu `/`.

Produksi menggunakan HTTPS, termasuk custom domain pelanggan. Installability berbeda menurut browser; manifest dan secure context perlu diverifikasi pada perangkat sasaran. Rujukan: [MDN — Making PWAs installable](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable).

Pilihan hosting bisa satu origin dengan tenant switcher atau subdomain/custom domain per tenant. Implementasi sekarang menyelesaikan tenant dari session dan membership; resolver domain belum dibuat. Resolver masa depan wajib memakai pemetaan host yang disetujui server dan cocok dengan membership. Header Host atau tenant ID dari klien tidak memberi hak akses.

Setiap origin memiliki registrasi service worker dan cache sendiri. Berpindah origin memakai navigasi penuh ke host allowlist, bukan mengirim cookie/token ke domain dari input pengguna. Cookie session direncanakan host-only, Secure, HttpOnly dan SameSite yang sesuai alur auth. SSO lintas domain perlu rancangan tersendiri. Tidak ada asumsi bahwa satu instalasi PWA mencakup seluruh domain pelanggan.

## 3. Service worker dan kebijakan cache

Service worker kelak hanya memakai allowlist aset publik; jangan memakai fallback cache umum untuk semua GET. Pola lifecycle/precache mengikuti [MDN — Caching](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Caching); aturan berikut merupakan kebijakan keamanan aplikasi ini.

| Jenis resource | Strategi yang ditetapkan |
| --- | --- |
| JS/CSS/font/ikon build dengan hash | Precache daftar eksplisit versi build; cache-first untuk aset tersebut saja |
| Halaman offline publik | Precache dokumen statis mandiri tanpa props, nama tenant, token atau tautan dokumen privat |
| HTML Inertia dan respons `X-Inertia` | Network-only; tidak ditulis ke Cache Storage |
| API, auth/login/logout, tenant/branch switch, platform, entitlement, pricing, pelanggan, order, pembayaran, stok | Network-only, semua metode; kegagalan jaringan tetap kegagalan operasi |
| Berkas unggahan, invoice, unduhan/URL bertanda tangan, data personal | Network-only; tidak masuk precache maupun runtime cache |
| Resource lintas origin / URL tidak dikenal | Tidak disimpan service worker |

Network-only pada service worker belum cukup mengendalikan HTTP cache, CDN, Inertia history dan prefetch. Respons terautentikasi perlu `Cache-Control: private, no-store`; CDN harus melewati semua respons pribadi dan tidak menggabungkan variasi HTML/Inertia. Header serta perilaku browser harus diuji sebelum rollout PWA.

Cache statis dinamai per aplikasi/origin/base path dan build, tanpa menyimpan user ID atau tenant ID. Aktivasi versi baru membersihkan hanya cache milik aplikasi yang tidak diperlukan; hindari menghapus cache aplikasi lain pada origin sama. Tidak boleh menyimpan token, kredensial, entitlement, daftar pelanggan atau hasil pricing pada localStorage/IndexedDB/service worker.

## 4. Logout, tenant switch dan perangkat bersama

Pada implementasi PWA berikutnya, logout/pergantian pengguna/perusahaan harus mengosongkan form personal, memori/prefetch Inertia dan penyimpanan aplikasi pribadi, membatalkan request lama, serta memuat ulang keputusan akses dari server. Uji dua tab: request dari konteks tenant lama tidak boleh selesai lalu menimpa layar tenant baru. Jangan menambahkan draft pribadi persisten sebelum ada desain keamanan dan retensinya.

Inertia history juga harus dilindungi: rencanakan history encryption pada HTTPS dan rotasi history key saat logout atau perubahan konteks, disertai uji Back/Forward/bfcache. API dan syarat secure context dirujuk dari [Inertia v3 — History encryption](https://inertiajs.com/docs/v3/security/history-encryption). Jangan menganggap `router.flushAll()` saja membuktikan seluruh history pribadi terhapus.

Temuan STEP 4: kode aplikasi belum mendaftarkan service worker/Cache Storage/IndexedDB; localStorage yang ditemukan hanya preferensi tema. Logout existing memanggil `router.flushAll()`, tenant switch memvalidasi membership server dan membersihkan cabang/relasi role, tetapi history encryption, invalidasi lintas tab dan pengujian Back sesudah logout belum tersedia. Ini menjadi syarat sebelum PWA dirilis, bukan klaim keamanan browser yang sudah teruji.

## 5. Layout dan perangkat

Sidebar existing menyediakan mode mobile; header perusahaan/cabang memakai flex-wrap dan label; halaman platform memakai grid satu kolom yang melebar sesuai viewport, form berlabel dan tabel dengan scroll horizontal. Link menggunakan Wayfinder atau URL server, tidak hardcode origin tenant. Pemeriksaan ini berbasis source dan build, belum observasi browser.

Alur kasir/Deskprint/operator berikutnya dirancang mobile-first pada 360 px, tablet portrait/landscape dan desktop. Gunakan kontrol sentuh yang lapang (target desain 44 px), label yang tetap terlihat, fokus/keyboard yang jelas, angka dengan inputmode yang tepat, ringkasan order terbaca, dan aksi simpan yang tidak tertutup keyboard. Hak akses diperiksa ulang saat submit, bukan bergantung pada menu.

Scanner barcode keyboard-wedge dan printer lewat dialog print menjadi kandidat awal. WebUSB/WebBluetooth, printer thermal/ESC-POS, kamera dan silent printing memerlukan pembuktian per browser, OS dan perangkat serta izin pengguna; jangan menjanjikan dukungan umum. Template ukuran kertas, margin dan hasil cetak harus diuji dengan perangkat pelanggan.

## 6. Update dan rencana verifikasi

Versi build Vite menjadi sumber versi aset precache. Tampilkan pemberitahuan versi baru; jangan memaksa `skipWaiting`/reload saat form/order sedang dikerjakan. Aktivasi dilakukan setelah pengguna siap dan request selesai. Pertahankan aset lama selama masa transisi yang ditentukan agar tab lama tidak rusak; uji kompatibilitas request lama dengan server baru dan kegagalan update.

Urutan implementasi setelah fondasi disetujui:

1. Pastikan tenant/auth/entitlement dan header cache konsisten pada HTTPS; selesaikan kebersihan history, pergantian konteks dan multi-tab.
2. Buat manifest, ikon, halaman offline generik dan service worker allowlist statis; tinjau diff cache sebelum memasang plugin/dependency apa pun.
3. Uji install/update/uninstall di Chrome/Edge desktop dan Android, Safari macOS/iOS/iPadOS, serta browser web biasa sebagai fallback. Catat versi aktual; kemampuan instalasi tidak diasumsikan sama.
4. Audit DevTools Application/Network, Cache Storage, localStorage, sessionStorage dan IndexedDB saat login A → switch tenant → logout → login B → offline → Back/Forward. Tidak boleh ada data A tampil/terbaca oleh B.
5. Uji server tidak tersedia, jaringan lambat, putus di tengah submit, grant dicabut, lisensi habis, dua tab, perpindahan domain, hard refresh dan update service worker. Tidak boleh ada transaksi sukses palsu atau request ganda.
6. Jalankan Lighthouse untuk audit web yang tersedia pada versi terpasang dan pemeriksaan manual installability/service worker; jangan mengandalkan satu skor sebagai bukti aman. Lanjutkan uji sentuh, keyboard, scanner dan printer fisik.

Tidak ada hasil browser, Lighthouse, installability, hardware atau MySQL yang diklaim pada STEP 4. Persiapan ini menetapkan arah dan batas keamanan; penerapannya memerlukan tahap terpisah.


## Pembaruan STEP 7 — 11 Oktober 2026

Kasir/invoice kini tersedia dengan online-first, private/no-store pada respons sukses/error, history Inertia terenkripsi, dan guard HTTPS pada domain aplikasi (localhost/loopback HTTP sebagai secure-context development). Tidak ada transaksi/pembayaran yang disimpan untuk offline atau ditandai sukses sebelum respons server.

Logout Fortify, tenant switch dan branch switch meminta clear history. Boundary app mengirim sinyal BroadcastChannel tanpa PII agar tab lain menghapus history dan menuju halaman publik; pageshow BFCache memicu pemeriksaan session lewat reload. Halaman lama di luar kasir belum semuanya mengenkripsi history. Ini belum menuntaskan seluruh audit privasi PWA pada bagian 4–6.

Tes server membuktikan flag encryptHistory/clearHistory, HTTPS guard serta cache-control. Browser tidak dapat diakses karena request-header policy CUA gagal; WebCrypto/BroadcastChannel/lintas-tab/Back/Forward/storage tidak diklaim terverifikasi. Wajib uji browser target sebelum rollout. Kunci enkripsi Inertia memakai sessionStorage; data pelanggan/payment tidak disimpan plaintext di localStorage/Cache Storage/service worker.

Manifest, installability, service worker, offline checkout dan printer otomatis tetap belum dibuat. Detail ledger, batas penerimaan dan migration pending ada pada [laporan STEP 7](../STEP7_KASIR_PEMBAYARAN.md).
