# STEP 1 — Audit ERP TGM sebelum pengembangan Laravel SaaS

Tanggal audit: **10 Oktober 2026**. Status: **audit selesai; implementasi tahap berikutnya belum diotorisasi**.

## 1. Kesimpulan audit

ERP TGM menyediakan referensi bisnis yang kuat untuk ERP percetakan: Deskprint/SPK, katalog dengan matriks harga dan Form Builder, BOM, routing, kasir, WIP, gudang, dan laporan. POS Lite sebaiknya mengikuti istilah serta urutan kerja ini. Memindahkan kode lama langsung ke Laravel akan membawa ketidakkonsistenan harga, ketergantungan database, dan pola akses yang belum sesuai untuk banyak pelanggan terpisah.

Temuan paling mendesak adalah perbedaan perhitungan estimasi/subtotal, pencarian harga yang belum mencocokkan seluruh spesifikasi, laporan yang masih membaca harga master terkini, penghapusan ulang data produksi saat revisi pembayaran, dan ketidakcocokan dump database dengan kode. Audit ini **tidak menyatakan aplikasi bebas error atau siap dijual**; temuan berasal dari pemeriksaan statis, tanpa menjalankan aplikasi atau database produksi.

## 2. Sumber, versi, dan batas pemeriksaan

| Objek | Hasil pemeriksaan |
| --- | --- |
| Repository sumber | [nizarsuryadi89-bit/erp_v5](https://github.com/nizarsuryadi89-bit/erp_v5), berhasil diakses melalui akses GitHub yang tersedia |
| Cabang default `main` | Hanya `.gitattributes`; commit `6863bc5c6ae41d198eea33bd10be0760522a7504` |
| Baseline kode audit | `master`, commit **`ae1e26081840d397982cfa1b5391aaa967aca277`**, 6 Oktober 2026, pesan “Prepare selected application changes for GitHub” |
| Cabang `production` | Commit `3835c361ebb6b2411a6306765d5c624e67666876`, 9 Juli 2026; bukan baseline laporan ini |
| Salinan pemeriksaan | Checkout terpisah di `/private/tmp/erp-v5-audit-20261010` |
| Source TGM lokal | `/Applications/XAMPP/xamppfiles/htdocs/tgm_erp` berada pada cabang berbeda dan memiliki perubahan lokal; tidak dijadikan baseline dan tidak diubah |
| Repository tujuan | [nizarsuryadi89-bit/erp-percetakan](https://github.com/nizarsuryadi89-bit/erp-percetakan), tersedia tetapi metadata GitHub menunjukkan repository kosong saat pemeriksaan |
| Laravel lokal | `/Volumes/zarnizar/herd_laravel/erp_percetakan`; belum memiliki Git remote yang dikonfigurasi |

Pemilihan `master` menyediakan snapshot kode yang dapat dirujuk secara konsisten. **Belum dibuktikan bahwa commit ini sama dengan aplikasi yang sedang dipakai di produksi.** Isi submodule/gitlink `tgm_erp`, vendor pihak ketiga, dan aplikasi pendamping bukan cakupan audit utama. Pemeriksaan difokuskan pada MVC aplikasi, layout, JavaScript transaksi/produk, rute, definisi SQL, dan dokumen terkait.

UI dipetakan dari PHP/HTML/CSS/JavaScript. Tidak ada verifikasi tampilan melalui browser, koneksi database produksi, impor dump, pengiriman WhatsApp, cetak ke perangkat, eksekusi skrip backfill, deployment, commit, atau push. Keberadaan controller/view merupakan bukti adanya implementasi di source, bukan bukti semua fitur berhasil berjalan.

## 3. Struktur folder dan framework

Framework yang ditemukan adalah **CodeIgniter 3.1.11**, dibuktikan oleh [konstanta CI_VERSION](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/system/core/CodeIgniter.php#L58). Aplikasi memakai PHP MVC dengan view yang dirender server, session CodeIgniter, query builder, dan SQL langsung.

| Folder/file | Peran yang ditemukan |
| --- | --- |
| `index.php` | Entry point CodeIgniter |
| `application/controllers/` | Endpoint dan proses bisnis; 71 file PHP langsung, 76 termasuk subfolder |
| `application/models/` | Akses data dan sebagian logika bisnis; 34 file PHP langsung, 42 termasuk subfolder |
| `application/views/` | 582 file PHP, termasuk halaman operasional, partial, layout, dan salinan/variasi lama |
| `application/core/MY_Controller.php` | Session, hak akses, konteks perusahaan/cabang, pemilihan layout |
| `application/config/routes.php` | Alias rute; banyak metode juga memakai routing konvensional CI |
| `assets/js/`, `assets/css/` | Interaksi produk/SPK, kalkulasi di browser, gaya AdminLTE/TGM |
| `database/`, `documentation/` | Dump dan skrip perubahan database yang tidak seluruhnya tersinkronisasi |
| `application/migrations/`, `application/tests/` | SQL QC dan pengujian tertentu |

Layout memuat jQuery **3.7.1**, Bootstrap **5.3.3**, AdminLTE, Select2 **4.1.0-rc.0**, DataTables **1.13.6**, serta pustaka grafik. `package.json` lama menyebut jQuery `^4.0.0`; versi deklarasi npm berbeda dari yang dimuat browser. Ini perlu dirapikan ketika frontend dipindahkan. Sumber: [head layout](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/views/layout_admin/head.php), [footer](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/views/layout_admin/footer.php), [package.json](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/package.json).

`composer.json` memuat integrasi Google Auth, JWT, Firebase, dan ESC/POS. Pemeriksaan kerentanan dependency belum dijalankan; konfigurasi `audit.block-insecure=false` bukan bukti bahwa dependency aman. PHP 8.4.16 dan MariaDB 10.11.13 tercatat pada **header dump Januari 2026**, bukan hasil pemeriksaan runtime produksi sekarang.

## 4. Inventaris modul ERP lama

Menu dalam dump: **Dashbord/Beranda, CRM Database, Transaksi, Produksi, Gudang, Keuangan, SDM, System, Panduan**. Produk/manufaktur muncul sebagai submenu dan controller, bukan selalu menu utama tersendiri. Status serta akses menu pada database yang sedang digunakan belum diverifikasi. Sumber: [menu](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L16827), [submenu](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17068).

| Area/modul | Fungsi yang ditemukan di source | Controller utama |
| --- | --- | --- |
| Dashboard | Ringkasan menurut peran, statistik, pintasan operasional, tampilan owner/developer | `Dashboard`, `Owner`, `OwnerReport` |
| Transaksi / Deskprint / POS | SPK baru, produk dan spesifikasi, draft item, pemesanan, edit/revisi | `Transaksi`, `Deskprint`, `Kelola_pesanan` |
| Kasir dan piutang | Pembayaran beberapa metode, DP, invoice, pembayaran susulan/kolektif | `Transaksi`, `KasirShift` |
| Penjualan manual | Transaksi dan pelanggan manual, item serta riwayat pembayaran terpisah | `Penjualan`, `Transaksi` |
| Katalog / master produk / harga | Produk, kategori, subkategori, satuan, matriks harga, Form Builder, katalog | `Manufaktur`, `Pengaturan`, `Produk`, `Katalog` |
| BOM dan HPP | Resep bahan, scrap, mapping bahan dinamis, biaya proses, simulasi margin | `Manufaktur` |
| Routing / mesin / workcenter | Urutan proses, mesin, operator, routing per variasi | `Manufaktur`, `Workcenter`, `OperatorBersama` |
| Produksi / WIP / QC | Antrean, progres operator, finishing, inspeksi dan permintaan produksi | `Wip`, `Produksi_Request` |
| Pengambilan / logistik / tracking | Serah terima PB, penyerahan pelanggan, sortir pengiriman, cek SPK | `Pb`, `Tracking`, `Cekpesanan`, `Transaksi` |
| Gudang / persediaan | Bahan/material/varian, stok per gudang, kartu stok, opname, pembelian, retur | `Warehouse`, `Manufaktur` |
| Keuangan / laporan | Pemasukan, pengeluaran, hutang, piutang, bank/EDC/e-wallet, laba rugi, pajak | `Laporan`, `Lapor`, `OwnerReport` |
| CRM | Pelanggan, aktivitas dan pencarian konsumen | `Konsumen` |
| SDM / payroll / aset | Karyawan, absensi, jadwal, izin/cuti, lembur, gaji, kinerja, aset | `Karyawan`, `Absensi`, `Hr`, `Kinerja`, `Aset`, `Employee_monitor` |
| Antrean / kiosk / print queue | Nomor antrean, counter, kiosk, antrean pencetakan | `Antrian`, `Kiosk`, `Print_queue`, `Clear_queue` |
| Marketplace / pengiriman | Pesanan marketplace dan data kurir | `Marketplace`, `Rajaongkir`, `Transaksi` |
| Website / konten / katalog publik | Halaman publik, konten, katalog dan pelacakan | `Website`, `Content`, `Katalog` |
| Mobile / API | Autentikasi, katalog, mobile, portal, tracking API | `Mobile`, `Api`, `Api_auth`, `Api_katalog`, `Api_mobile`, `Api_portal`, `Api_tracking` |
| Komunikasi / otomasi | Chat, notifikasi, WhatsApp bot, cron | `Chat`, `Notifikasi`, `Whatsapp_bot`, `Cron`, `Cron_notif` |
| Pencarian AI | Controller pencarian/asisten; kemampuan dan integrasi belum diuji | `SearchAI` |
| Akun / konfigurasi / administrasi | Login, pengguna, peran, menu, profil, perusahaan/cabang, koreksi transaksi | `Auth`, `Akun`, `Menu`, `Profile`, `Pengaturan`, `SwitchBranch`, `Administrative` |
| Pemeliharaan / dukungan | Backup, restore, audit aktivitas, error log, health, debug dan perbaikan DB | `Backup`, `Restore`, `Log_audit`, `ErrorLog`, `Health`, `Support`, controller diagnostik |

Submenu operasional penting yang benar-benar tercatat: SPK Baru – Deskprint, Pembayaran – Kasir, Daftar Piutang, Transaksi Manual, Operator Produksi, Pengambilan Barang – PB, Progress Produksi, Material, Kartu Stok, Stok Opname, Laba Rugi, Pemasukan Harian/Bulanan, Hutang, Pengeluaran, Laporan Pajak, Absensi, Lembur, Penggajian, Kinerja, Approval Izin & Cuti, Manufactur, dan Backup & Restore.

Beberapa link submenu/rute memakai nama lama seperti `gudang`, `supplier`, atau `settings`; tidak boleh dianggap valid hanya karena ada dalam dump. Inventaris seluruh controller langsung disertakan pada lampiran untuk membantu penelusuran modul tambahan.

## 5. Produk dan harga: controller, model, view, tabel

| Lapisan | Lokasi dan tanggung jawab |
| --- | --- |
| Controller katalog | [Manufaktur.php](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/controllers/Manufaktur.php): `index`, `detail` (681), `action_save_all` (798), `get_calculated_hpp_ajax` (1005), `get_dynamic_bom` (2578), `save_dynamic_bom` (2633) |
| Controller schema | [Pengaturan.php](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/controllers/Pengaturan.php#L2711): menyimpan `form_schema` kategori/subkategori/produk dan konfigurasi subkategori |
| Controller transaksi | [Transaksi.php](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/controllers/Transaksi.php): resolusi kunci form (1028), estimasi (1088), tambah draft (1178), simpan pesanan (1620), pembayaran (2268), SPK baru (3006/3213) |
| Model produk/harga | [M_master.php](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/models/M_master.php#L784): produk lengkap, harga (975), BOM, routing |
| Model pencarian harga | [M_transaksi.php](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/models/M_transaksi.php#L228): `get_product_price` |
| Model pembayaran/produksi | [M_pembayaran.php](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/models/M_pembayaran.php#L107): pembayaran, snapshot item dan pembuatan produksi |
| View produk | `application/views/admin/product/index.php`, `detail.php`, `add_harga/universal_pricing.php` |
| View input | `application/views/admin/transaksi/createspk.php`, `createspk/parts/*`, `createspk/kategori_dynamic.php`, `tmp_body.php` |
| JavaScript | [product-detail.js](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/assets/js/product-detail.js#L556) dan `createspk/parts/scripts.php` |
| Tabel utama | `tbl_product`, `tbl_harga_product`, `tbl_kategori`, `tbl_subkategori`, `tbl_satuan`, `tbl_side`, `tbl_detail_product`, `tbl_range`, `tbl_mesin_printing`, `bom_lines`, `routing` |

`Produk_model.php` adalah jalur berbeda yang masih mengacu pada **`tbl_produk`**; tabel tersebut tidak terdapat dalam dump master yang diperiksa. Jangan menjadikannya model utama katalog tanpa menelusuri pemakaiannya. Jalur katalog utama yang dibaca adalah `Manufaktur` → `M_master` → `tbl_product`.

## 6. Struktur database yang ditemukan

`database/DB_ERP_Master.sql` berisi **108 definisi tabel** dan **52 deklarasi foreign key**. Semua definisi tabel memakai InnoDB; charset: 93 tabel `utf8mb4`, 14 `latin1`, 1 `utf8mb3`. Angka ini dihitung dari dump, bukan database aktif. Dump kedua `documentation/db_erp.sql` tersedia tetapi tidak digunakan sebagai skema acuan laporan. Sumber: [dump master](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql).

| Entitas lama | Kolom/relasi penting yang ditemukan | Adaptasi konseptual Laravel |
| --- | --- | --- |
| `tbl_perusahaan` | `id_perusahaan`, identitas, alamat, branding; dipakai sebagai konteks cabang/perusahaan | Pisahkan pelanggan SaaS (`tenant`) dan outlet/cabang; mapping eksplisit, bukan salin ID langsung |
| `users`, `roles`, `user_roles` | Akun dan penugasan peran; `tbl_divisi` juga dipakai sebagai level akses | Pengguna, keanggotaan tenant/cabang, peran dan izin |
| `menu`, `submenu`, `tbl_menu_access`, `tbl_submenu_access` | Navigasi dan izin menu | Registry modul serta izin tindakan; tampilan menu mengikuti akses backend |
| `tbl_product` | `product_id`, kategori/subkategori/satuan/tipe/sisi, barcode, nama, `detail_id` | Produk dengan kategori, satuan dan atribut percetakan |
| `tbl_harga_product` | `harga_id`, `product_id`, `detail_product`, `hpp`, `harga_1`, `qty_min/max`, `ket_1`, `side_id`, `jenisprint_id`, status | Aturan/matriks harga berversi, rentang qty dan pilihan atribut jelas |
| `tbl_kategori`, `tbl_subkategori`, `tbl_detail_product` | Pengelompokan produk dan jenis/metode | Kategori/subkategori dan definisi form per produk |
| `tbl_satuan`, `tbl_range`, `tbl_side`, `tbl_type` | Satuan, rentang, sisi cetak, jenis barang/jasa | Data referensi dan kode stabil; basis satuan/luas/panjang dipisahkan |
| `tbl_bahan`, `tbl_material`, `material_variant` | Bahan generik, material/konversi, variasi ukuran | Bahan/material serta varian dan konversi unit |
| `bom_lines` | Produk, bahan, ukuran, quantity, satuan, scrap | Resep bahan, aturan kebutuhan dan revisi BOM |
| `routing`, `routing_steps`, `tbl_routing` | Header routing, tahapan/workcenter; ada tabel penamaan lama paralel | Definisi proses dan langkah terurut; konsolidasi setelah validasi pemakaian |
| `tbl_mesin`, `tbl_mesin_printing`, `tbl_operator` | Mesin dan operator | Workcenter/mesin serta penugasan operator |
| `tbl_konsumen` | Pelanggan percetakan | Pelanggan per tenant |
| `tbl_spk` | `nospk`, nomor urut, user/customer, metode pesan, marketplace, perusahaan | Identitas SPK dan versi dokumen pekerjaan |
| `tbl_temp_transaksi` | Draft item, SPK, produk, qty, ukuran, harga, user | Keranjang/draft milik pengguna dan tenant |
| `tbl_pemesanan`, `tbl_pemesanan_detail` | Header pesanan dan item; nomor SPK, harga, ukuran, diskon, deadline, finishing, approval | Pesanan dan item dengan snapshot spesifikasi/harga |
| `tbl_pembayaran`, `tbl_pembayaran_detail`, `tbl_pembayaran_produk` | Tagihan, pembayaran, pecahan metode, item yang dibayar | Invoice, pembayaran, alokasi pembayaran serta snapshot item |
| `tbl_piutang`, pembayaran/penyesuaiannya | Saldo dan pelunasan susulan | Piutang dengan riwayat, koreksi terotorisasi |
| `tbl_produksi`, `detail_produksi` | Job produk per SPK; tahapan mesin/operator, output, kerusakan, waktu dan biaya | Job produksi dan riwayat tahap/progres |
| `tbl_stok_gudang`, `stok_gudang` | Dua struktur stok, yang satu berbasis material dan satu varian | Saldo dan mutasi inventori; tentukan struktur yang dipakai sebelum konsolidasi |
| Pembelian, retur, pengambilan, stok log/opname | Header/detail dan mutasi stok | Pembelian, penerimaan, konsumsi bahan dan ledger |
| Pengeluaran, hutang, bank/EDC/e-wallet, mutasi | Operasional kas dan keuangan | Kas/biaya/metode pembayaran; laporan memakai nilai transaksi historis |
| Karyawan, absensi, lembur, gaji, izin/cuti | SDM/payroll | Modul opsional terpisah dengan data sensitif per tenant |
| Manual sales, antrean, chat, notifikasi, WA, log | Modul pendamping | Diadaptasi bertahap sesuai paket dan kebutuhan |

Relasi penting: produk → baris harga/BOM/routing; pelanggan dan nomor SPK → pesanan → detail; nomor SPK → pembayaran → detail metode dan item; item/produk/harga → produksi → detail proses; bahan/material → stok gudang, pembelian dan pengambilan. Sebagian relasi ditegakkan foreign key dan sebagian hanya melalui query aplikasi. `nospk` dipakai lintas beberapa tabel; tidak boleh diganti tanpa mapping seluruh hubungan.

### Kesenjangan kode dan dump

Dump dibuat **25 Januari 2026**, sedangkan baseline kode Oktober 2026. Kode memakai kolom yang belum terdapat dalam dump tersebut: `form_schema`, `subkategori_config`, `metadata`, `harga_minimal`, BOM per `harga_id`/`calc_type`, biaya/variasi routing, dan data snapshot/serah terima produksi.

Skrip [payment snapshot dan price versioning](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/2026_05_04_payment_snapshot_and_price_versioning.sql) menambah snapshot harga/HPP serta masa berlaku harga. Skrip itu mengasumsikan kolom `metadata` sudah ada; mengimpor dump Januari lalu menjalankan hanya skrip ini belum cukup untuk merekonstruksi skema yang dibutuhkan kode.

[SQL QC Oktober](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/migrations/20261006_wip_quality.sql) mendefinisikan `tbl_wip_qc` dan `tbl_wip_qc_inspection`, di luar 108 tabel dump. Controller BOM dinamis memakai `tbl_bom_dynamic_mapping`, yang juga tidak terdapat dalam dump master. **Jumlah tabel database operasional sekarang belum diketahui.** Lampiran berisi 108 tabel yang benar-benar ditemukan pada dump utama.

## 7. Pemetaan Pricing Engine yang benar-benar dibaca

### A. Produk, bahan dan atribut

Produk mengambil kategori, subkategori, satuan, jenis, sisi dan metode dari master. Master kategori dalam dump: Digital A3+, Large Format, Flatbed & Rotary, Sublimasi, Tekstil, Laser Cut & Engravir, Apparel, DTG, DTF, Merchandise.

Form Builder menghasilkan pilihan seperti radio/select/segmented, angka, ukuran panjang/lebar, teks dan catatan. Pada jalur `createspk` terbaru, controller **mewajibkan schema di tingkat produk**; tidak otomatis mengambil schema subkategori/kategori untuk menggantikan produk yang kosong. Konfigurasi lama masih tersedia di master, sehingga aturan pewarisan harus disepakati ketika migrasi.

Bahan memengaruhi harga melalui variasi/baris matriks dan memengaruhi HPP melalui BOM/material. Belum ditemukan satu formula server umum yang selalu menghitung harga jual langsung dari bahan, ukuran, sisi dan finishing pada setiap transaksi.

### B. Matriks harga

`Manufaktur::action_save_all` menyimpan HPP, harga jual, harga minimum, rentang qty, variasi `ket_1`, mesin/jenis print, sisi cetak, dan atribut tambahan pada JSON `metadata`. Form schema dipetakan ke kolom berdasarkan nama/label serta heuristik. Baris harga dapat diperbarui langsung; belum ada jaminan seluruh jalur memakai versioning harga.

`M_transaksi::get_product_price` memilih berdasarkan produk, qty tier, `ket_1` dan `jenisprint_id`. Qty antara 0 dan 1 dinaikkan menjadi 1 untuk lookup. Batas atas `0` atau `<=1` diperlakukan sebagai rentang terbuka. Ketika hanya satu kunci variasi diberikan, nilainya boleh cocok dengan kolom lain untuk kompatibilitas data lama. Urutan utama adalah `qty_min` terbesar yang sesuai.

**Filter eksplisit `side_id`, atribut JSON tambahan, finishing, status aktif, `is_current`, dan periode harga belum ada pada fungsi ini.** Pilihan sisi bisa saja dimasukkan ke kunci lain oleh konfigurasi tertentu, tetapi itu bukan pencocokan universal yang aman. Baris ambigu/rentang tumpang tindih perlu dideteksi sebelum diadaptasi.

Sumber: [penyimpanan matriks](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/controllers/Manufaktur.php#L798), [pencarian harga](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/models/M_transaksi.php#L228).

### C. Kuantitas, ukuran dan estimasi

Untuk kategori tertentu, `estimate_price`/`action_add_temp` menentukan qty lookup dari ukuran:

- Satuan ID 11 (meter lari): qty × panjang.
- Panjang dan lebar: qty × panjang × lebar / 10.000 pada jalur lookup.
- Panjang tanpa lebar: qty × panjang.
- Flatbed/Rotary dan Laser: ukuran untuk lookup dibatasi minimum 5 pada kedua dimensi ketika ada input ukuran.
- DTG/DTF mempunyai mapping luas pilihan 100, 350, 630, 1.260, 1.536; DTF `ket_1=5` mempunyai perlakuan meter lari. Input dimensi selanjutnya dapat menimpa hasil mapping tersebut.

Namun **subtotal respons estimasi tetap `harga_satuan × qty input`**, bukan qty luas/panjang yang baru dihitung dan bukan perhitungan harga minimum. Sumber: [estimate_price](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/controllers/Transaksi.php#L1088).

### D. Subtotal draft dan snapshot

Helper `calculate_snapshot_subtotal` memakai aturan berbeda:

- Satuan non-luas tertentu: qty × tarif.
- Meter lari atau DTF tertentu: qty × panjang × tarif.
- Dimensi lengkap: qty × panjang × lebar × tarif, **tanpa pembagi 10.000**.
- Dimensi panjang saja: qty × panjang × tarif.
- Terapkan harga minimum bila subtotal lebih rendah.

View draft mempunyai percabangan satuan sendiri; model pembayaran juga mempunyai fallback kalkulasi tersendiri. UI pasangan ukuran memberi label `MTR`, sementara lookup luas memakai pembagi 10.000. **Satuan ukuran aktual per produk harus diverifikasi bersama pengguna; audit tidak mengasumsikan semua input sentimeter atau semua input meter.** Ukuran minimum lookup juga tidak otomatis menjadi ukuran yang disimpan.

Contoh aritmetika ilustratif, bukan transaksi nyata: dengan tarif terpilih Rp10.000, qty 2, panjang 2, lebar 3 dan produk yang memakai cabang luas, estimator mengembalikan Rp20.000, sedangkan helper subtotal menghitung Rp120.000 sebelum minimum/diskon. Contoh menunjukkan dua jalur berbeda, bukan menetapkan mana harga bisnis yang benar.

Sumber: [helper subtotal/snapshot](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/controllers/Transaksi.php#L29), [tmp_body](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/views/admin/transaksi/tmp_body.php), [fallback pembayaran](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/models/M_pembayaran.php#L18).

### E. Sisi cetak dan finishing

`side_id` tersedia pada harga/master, tetapi tidak dipakai sebagai filter eksplisit di lookup utama. `finishing_id` dibawa pada item pesanan/pembayaran, dan ada partial lama yang memilih produk/baris harga finishing sebagai item tersendiri. Routing juga dapat mencakup finishing. Belum terbukti ada penambahan biaya finishing universal di backend utama; Laravel tidak boleh mengarang tarif finishing atau menganggap biaya sudah selalu termasuk.

### F. HPP dan harga jual

HPP pada endpoint simulator menjumlahkan **bahan × quantity × (1 + scrap%) + biaya routing + overhead 5%**. JavaScript produk juga menghitung BOM dinamis berdasarkan atribut, pengali, pembagi dan scrap. Simulator margin memakai `harga jual = HPP / (1 − margin%)`.

Perhitungan HPP per variasi di JavaScript masih menjumlahkan seluruh biaya routing yang dimuat, sehingga perlu dibuktikan bahwa biaya routing benar untuk variasi tersebut. Harga transaksi tetap berasal dari matriks harga tersimpan; simulator HPP bukan pengganti lookup matriks.

Sumber: [HPP server](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/controllers/Manufaktur.php#L1005), [BOM/HPP JavaScript](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/assets/js/product-detail.js#L556).

### G. Kontrak harga yang direkomendasikan untuk tahap berikutnya

Satu kalkulator server perlu menghasilkan spesifikasi yang cocok, basis tier, qty tagihan, tarif, minimum, subtotal, HPP dan versi aturan. Estimasi, keranjang, pesanan, invoice serta laporan harus memakai hasil yang sama. Tarif jual manual tetap tersedia; BOM/routing otomatis menjadi kemampuan tambahan, bukan syarat agar POS Lite bisa berjalan.

Ini **rekomendasi**, belum implementasi. Semantik satuan, finishing, diskon, pembulatan dan tier harus disahkan menggunakan contoh operasional TGM sebelum aturan lama dinormalisasi.

## 8. Alur pemesanan → SPK → kasir → produksi

1. **Mulai SPK:** pilih pelanggan, metode pesanan dan konteks cabang. `step_satu` langsung membuat header `tbl_spk`, sebelum item diisi. Nomor memakai cabang-user-bulan/tahun-urutan. Pembacaan nomor terakhir lalu `+1` memerlukan perlindungan concurrency.
2. **Input produk:** `createspk` memuat katalog dan schema produk, spesifikasi, qty, deadline, approval, lokasi cetak, serta catatan.
3. **Estimasi dan draft:** browser memanggil `estimate_price`; `action_add_temp` memilih `harga_id` dan menyimpan item di `tbl_temp_transaksi`. Atribut tambahan disimpan melalui metadata.
4. **Kirim pesanan:** `add_pemesanan` menyimpan `tbl_pemesanan`/detail dalam transaksi DB. Subtotal/harga dibekukan pada metadata dan pada kolom khusus bila tersedia. Jalur marketplace dapat membuat produksi dan menandai pembayaran tanpa melewati layar kasir normal.
5. **Kasir:** layar `bayar` menampilkan item/tagihan, diskon, ongkir dan metode pembayaran. Tunai, transfer, EDC, e-wallet, QRIS dan DP tersedia pada jalur pembayaran.
6. **Simpan pembayaran:** `M_pembayaran::prosesTransaksi` menyimpan pembayaran, detail metode, item yang dibayar, produksi dan piutang; kemudian memperbarui status. WhatsApp dan print queue merupakan side effect terpisah yang perlu dirapikan.
7. **Job produksi:** produk dengan routing masuk status `approved`; tanpa routing dapat langsung `done` dan melalui pengurangan stok otomatis. Tahapan disimpan di `detail_produksi`.
8. **Proses dan serah terima:** WIP/operator → finishing/QC sesuai alur → PB → pelanggan. Pelunasan piutang berjalan melalui pembayaran susulan atau kolektif.

**Perbedaan penting dengan gambaran POS generik:** SPK sudah dibuat di awal alur, bukan baru muncul setelah pembayaran. POS Lite perlu tetap dapat menyerahkan dokumen kerja/SPK meskipun modul WIP lengkap dinonaktifkan.

Sumber: [step_satu](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/controllers/Transaksi.php#L3213), [simpan pesanan](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/controllers/Transaksi.php#L1620), [bayar](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/controllers/Transaksi.php#L2268), [proses pembayaran](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/models/M_pembayaran.php#L107), [produksi](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/models/M_pembayaran.php#L298).

## 9. Pemetaan UI lama

| Halaman/area | Bentuk dan interaksi yang dibaca | Yang sebaiknya dipertahankan |
| --- | --- | --- |
| Navigasi | Sidebar untuk kelompok admin/owner/developer/HR; staf biasanya top navigation; ada navigasi mobile dan override layout | Istilah modul, susunan kerja menurut peran, akses cepat cabang |
| Dashboard | Controller memilih ringkasan per peran: kasir, Deskprint, operator, owner, HR, PB, gudang/marketplace; kartu statistik dan pintasan | Informasi/tugas yang relevan per peran |
| Sidebar | Menu/submenu berasal dari DB dan cache session; warna gelap dengan indikator aktif | Pengelompokan familier; izinkan hide sesuai entitlement dan peran |
| Deskprint/SPK | Header pelanggan/SPK, tiga blok pilih produk–spesifikasi–rincian, estimasi live, list item, modal pelanggan/kiosk | Urutan input, pencarian/barcode, catatan, approval, deadline, kirim ke kasir |
| Kasir | Daftar SPK belum dibayar, halaman item/invoice dan panel tagihan, pilihan beberapa metode/DP, cetak | Pilih SPK → periksa item → pembayaran → invoice |
| Shift kasir | Halaman buka/tutup shift dan detail rekap | Kontrol kas sesuai kebutuhan paket, tidak dicampur dengan pricing |
| Produk | Control panel, cari/filter, New/Actions, status konfigurasi harga, katalog/grid, pagination | Pencarian serta status kesiapan produk/harga |
| Detail produk | Form Builder, Resep BOM, Mapping BOM Dinamis, Routing, Simulator HPP, Finalisasi Harga | Konsep konfigurasi produk bertahap; tampilkan bagian sesuai modul |
| Matriks harga | Kombinasi spesifikasi, min/max qty, HPP, harga jual, minimum, duplikasi baris/dua sisi, pemeriksaan duplikat UI | Matriks percetakan; backend wajib ikut memvalidasi |

Sumber UI: [MY_Controller](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/core/MY_Controller.php#L338), [sidebar](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/views/layout_admin/sidebar.php), [Dashboard](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/controllers/Dashboard.php), [SPK](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/views/admin/transaksi/createspk.php), [form SPK](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/views/admin/transaksi/createspk/parts/product_form.php), [bayar](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/views/admin/transaksi/bayar.php), [produk](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/views/admin/product/index.php), [detail produk](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/views/admin/product/detail.php), [matriks](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/views/admin/product/add_harga/universal_pricing.php).

Belum tersedia hasil screenshot/penilaian visual runtime dalam audit ini. Tampilan, responsivitas, keypad/barcode, plugin serta printer tetap memerlukan pengujian pada lingkungan terisolasi tahap berikutnya.

## 10. Risiko dan temuan yang perlu ditindaklanjuti

Prioritas di bawah adalah urutan penanganan sebelum menjual SaaS, bukan hasil uji penetrasi atau reproduksi error produksi.

| Prioritas | Temuan berbukti | Dampak / tindakan berikutnya |
| --- | --- | --- |
| P0 | Rumus estimasi, draft, snapshot dan fallback pembayaran berbeda | Pelanggan dapat melihat estimasi berbeda dari tagihan; satukan kontrak harga setelah verifikasi satuan |
| P0 | Lookup hanya produk/qty/dua kunci; belum memfilter seluruh sisi/atribut/status/versi | Baris harga dapat ambigu atau tidak sesuai spesifikasi; pencocokan harus eksplisit dan rentang tidak tumpang tindih |
| P0 | Produk/pelanggan master tidak mempunyai isolasi tenant yang memadai pada query yang dibaca | Pemakaian lintas pelanggan SaaS berisiko kebocoran; terapkan batas tenant/cabang pada query, izin, file dan job |
| P0 | `action_bayar` menerima perusahaan dan grandtotal dari POST; model menyimpan grandtotal tersebut | Nominal dan konteks cabang perlu dihitung/divalidasi server dari order yang terotorisasi |
| P0 | `_cleanup_existing_produksi` menghapus detail dan job lama sebelum membangun ulang | Revisi/bayar ulang berpotensi menghilangkan progres operator; gunakan revisi yang menjaga riwayat dan operasi idempotent |
| P1 | Dump lebih lama dari kode; beberapa tabel/kolom belum terdapat pada dump | Rekonstruksi aplikasi dari dump saja berpotensi gagal; perlu skema terbaru yang disanitasi dan daftar perubahan |
| P1 | `M_laporan::getLabaRugi`/`total_pendapatan` memakai `qty × harga_1/hpp` dari master harga saat ini | Laporan historis dapat bergeser dan salah untuk luas; gunakan snapshot pendapatan/HPP serta rekonsiliasi |
| P1 | Ada 12 alias rute ke controller `Keuangan` yang tidak ada dalam snapshot | Endpoint alias tersebut berpotensi gagal; tentukan rute aktif `Laporan` sebelum porting |
| P1 | Empat view yang dirujuk `Transaksi` tidak ditemukan | Jalur lama pemesanan_body, update_spk, transaksi_kasir, kasir_shift_rekap berpotensi gagal saat dipanggil |
| P1 | Constructor `Transaksi` melakukan ALTER, insert submenu dan seed pengiriman | Membuka endpoint saja bisa menulis DB; pindahkan setup ke mekanisme terkontrol pada sistem baru |
| P1 | Constructor membaca `$m['id_menu']` sementara kolom menu pada dump adalah `id` | Potensi mismatch schema/warning/insert submenu; verifikasi skema aktual |
| P1 | Nomor SPK dibentuk dari nomor terakhir +1 | Dua request bersamaan dapat bertabrakan; alokasi atomik dan constraint diperlukan |
| P1 | Qty draft/order dapat pecahan, tetapi qty pembayaran/produksi pada dump adalah INT | Risiko hilang presisi; tetapkan decimal qty/unit secara konsisten |
| P1 | Uang memakai campuran INT/FLOAT/DECIMAL; charset campuran dan zero-date | Risiko pembulatan, encoding dan import ke mode SQL ketat; lakukan normalisasi dengan rekonsiliasi |
| P1 | Akses berbasis prefix controller/menu dan bypass peran/username; pindah cabang hanya memeriksa cabang ada | Tidak cukup untuk izin tindakan dan keanggotaan tenant; developer platform dipisahkan dari owner tenant |
| P2 | Root memuat skrip debug/dump/backfill, URL tracking TGM hardcoded | Distribusi SaaS perlu konfigurasi per tenant dan pembatasan artefak internal; jangan menyalin ke web root publik |
| P2 | Formula HPP JavaScript menjumlahkan biaya routing yang dimuat; overhead 5% tetap | Verifikasi biaya per variasi dan keputusan bisnis overhead, jangan hardcode ke semua pelanggan |
| P2 | Dependency frontend deklarasi dan runtime berbeda | Risiko plugin/UI; frontend dibangun ulang dengan komponen konsisten dan uji alur |

Bukti tambahan: [laporan laba rugi](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/models/M_laporan.php#L47), [cleanup produksi](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/models/M_pembayaran.php#L366), [constructor](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/controllers/Transaksi.php#L109), [rute keuangan](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/config/routes.php#L156), [izin menu](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/core/MY_Controller.php#L634), [pindah cabang](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/controllers/SwitchBranch.php), [URL QR](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/models/M_pembayaran.php#L715), [Produk_model](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/application/models/Produk_model.php).

Empat referensi view: `Transaksi.php` baris **972, 3654, 3872, 4304**. Ini temuan statis; audit belum menentukan seberapa sering jalur tersebut dipakai atau apakah instalasi produksi memiliki file tambahan.

Repository memuat pengujian Akun, WIP workflow, QC MySQL dan beberapa pengujian UI WIP. **Tidak dijalankan dalam audit ini**, karena perlu lingkungan terisolasi dan konfigurasi DB yang jelas. Tidak ada klaim seluruh modul lolos tes.

## 11. Pertahankan, refactor, dan bangun baru

| Keputusan | Bagian |
| --- | --- |
| Pertahankan secara bisnis | Istilah Deskprint/SPK/PB; kategori percetakan; matriks harga; tier qty; satuan/luas/meter lari; minimum harga; spesifikasi produk; DP/piutang; BOM/scrap; routing dan serah terima |
| Pertahankan secara pengalaman pengguna | Urutan kerja, pilihan produk dan spesifikasi, pencarian/barcode, catatan/deadline/approval, pemisahan Deskprint dan kasir |
| Refactor | Kalkulasi harga yang tersebar; mapping ID/label heuristik; validation backend; snapshot; biaya per variasi; workflow revisi; transaksi stok; perhitungan laporan; SQL/view campur; rute/file duplikat |
| Bangun baru untuk SaaS | Tenant dan membership; akses developer platform; entitlement modul; default paket; audit akses; batas file/cache/job per tenant; konfigurasi integrasi per pelanggan; onboarding/import terkontrol |
| Evaluasi sebelum membawa | AI, HR/payroll, mobile, marketplace, website, kiosk, backup/restore, debug tools; modul tambahan dipindahkan sesuai kebutuhan dan bukti pemakaian |

Mempertahankan fitur tidak berarti mempertahankan bug atau menyambungkan Laravel langsung ke database produksi. Data lama dapat dipetakan melalui proses import terkontrol setelah struktur sumber diverifikasi.

## 12. Rekomendasi struktur Laravel SaaS

Rekomendasi ini desain konseptual, bukan kode/migration yang dibuat dalam audit.

**Satu aplikasi Laravel dengan modul yang dapat diaktifkan per tenant** sesuai keputusan pengguna. Lite/Growth/Pro adalah preset paket dan batas pemakaian; developer dapat mencentang modul yang diberikan kepada pelanggan. Tenant owner mengatur izin staf dalam batas entitlement. Tidak ada aturan mengunci penjualan ke enam menu lama.

| Lapisan | Rekomendasi |
| --- | --- |
| Identitas | Tenant/pelanggan SaaS → cabang → keanggotaan pengguna; platform administrator merupakan akses terpisah |
| Modul | Katalog registry, dependensi, hak tenant, status aktif, preset paket; menu serta endpoint memakai keputusan akses yang sama |
| Domain | Katalog/pricing, order/SPK/POS, pembayaran/piutang, BOM/routing/produksi, persediaan, keuangan, CRM, HR dan integrasi |
| Proses bisnis | Ikuti pola `app/Actions` yang sudah ada: operasi harga, order, pembayaran, SPK dan produksi mempunyai tanggung jawab terpisah |
| HTTP | Controller tipis, validasi input dan izin tindakan; semua pencarian data terikat tenant/cabang yang terotorisasi |
| Data transaksi | Spesifikasi, satuan, tarif, HPP dan versi harga dibekukan; riwayat tetap benar ketika master berubah |
| Proses lanjutan | Print/WhatsApp/integrasi diproses sesudah penyimpanan berhasil, dapat diulang dengan aman tanpa transaksi ganda |
| UI | Pakai komponen frontend project yang sudah ada, tetapi pertahankan alur dan istilah TGM; tampilkan konfigurasi lanjutan sesuai modul |
| Impor | Pemetaan ID lama dan sumber versi, validasi data, laporan konflik serta rekonsiliasi jumlah/nominal; tidak menulis ke sumber |

Tidak direkomendasikan memperkenalkan dependency atau folder dasar baru pada tahap audit. Untuk database SaaS, shared schema dengan `tenant_id` adalah opsi awal yang cocok dievaluasi terhadap fondasi lokal; database terpisah per pelanggan tetap keputusan arsitektur yang harus mempertimbangkan operasi/biaya. Audit belum menetapkan keduanya sebagai keputusan final.

### Usulan isi paket, bukan keputusan komersial final

| Paket | Kemampuan yang diusulkan |
| --- | --- |
| Lite | Katalog dan matriks harga percetakan otomatis, pelanggan dasar, draft/order/SPK, kasir, invoice, pembayaran/DP/piutang dasar dan ringkasan penjualan |
| Growth | Lite + BOM/routing/WIP, persediaan/pembelian, pengeluaran serta pelaporan biaya/HPP lebih lengkap |
| Pro | Growth + kebutuhan multi-cabang/analitik/integrasi/CRM atau HR lanjutan yang benar-benar dibutuhkan dan telah diverifikasi |

**POS dan pricing wajib tetap berguna pada Lite walaupun produksi/gudang penuh tidak aktif.** Catatan bahan dan finishing pada order/SPK tetap tersedia. HPP sederhana/manual dan ringkasan pendapatan dapat disediakan tanpa memaksa paket Lite mengaktifkan seluruh manufaktur. Entitlement BOM/produksi tidak seharusnya mematikan identitas produk, satuan atau kemampuan mencetak SPK dasar.

Riwayat keputusan bisnis mencatat Rp5 juta sebagai **lisensi sekali bayar per pelanggan**. Arah terbaru menyebut paket SaaS Lite/Growth/Pro, tetapi belum menetapkan model biaya hosting/support berulang atau harga tiap paket. Laporan tidak menganggap Rp5 juta sebagai langganan tahunan/bulanan.

## 13. Sinkronisasi dokumentasi dan keadaan Laravel lokal

Dokumen [MIGRASI_LARAVEL_ROADMAP.md](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/MIGRASI_LARAVEL_ROADMAP.md) masih membahas Laravel 11, koneksi ke DB TGM lama, dan auth berbasis karyawan/operator. Itu adalah **rencana tertulis lama**, bukan hasil audit runtime atau instruksi baru untuk menjalankan tindakan tersebut. Permintaan pengguna saat ini menjadi acuan: audit dahulu, sumber read only, dan belum ada migration/implementasi baru.

[Manual book](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/MANUAL_BOOK_TGM_ERP.md) membantu membaca istilah dan alur pengguna, tetapi uraian umum di dalamnya tidak menggantikan bukti kode. Contohnya alur marketplace dan pembuatan job saat pembayaran perlu dibaca langsung dari controller/model, bukan disimpulkan dari kalimat “Kirim ke Produksi”.

Versi terpasang Laravel lokal diperiksa menggunakan `composer show --direct`: Laravel **13.35.0**, Inertia Laravel **3.5.1**, Fortify **1.41.0**, Spatie Permission **8.3.0**, Pest **5.3.1**. `package.json` memakai React/TypeScript dan Tailwind; versi deklarasi JS bukan bukti versi browser yang berjalan.

| Bagian lokal sebelum permintaan audit | Status yang bisa dinyatakan |
| --- | --- |
| Fondasi/auth/tenant/cabang dan katalog | Source dan migration sudah ada dari pekerjaan sebelumnya; bukan dibuat dalam audit ini |
| Kalkulator `CalculateProductPrice` | Sudah ada; memakai basis unit/length/area, spesifikasi variant/machine/print_side/finishing, minimum dan decimal; kesetaraan terhadap seluruh konfigurasi TGM belum dibuktikan |
| Registry/platform entitlement | Backend awal sudah sempat dibuat sebelum pengguna meminta berhenti coding; masih pekerjaan belum selesai |
| Migration platform `2026_10_10_041453_create_platform_module_access_tables.php` | Sudah ada dari pekerjaan sebelumnya, **belum dijalankan** |
| UI platform dan verifikasi setelah perubahan terbaru | Belum selesai; hasil tes lama tidak memvalidasi perubahan backend terakhir |
| Sinkronisasi remote | Project lokal belum mempunyai remote; repository tujuan belum merepresentasikan source lokal |

Perubahan lokal tersebut tidak dibatalkan, dirapikan, dilanjutkan atau di-commit pada audit ini. Karena source backend sudah merujuk tabel platform yang belum dimigrasikan, ada **risiko error pada keadaan lokal yang belum selesai**. Tidak tepat menandai platform/module checkbox sebagai fitur siap pakai.

Dokumen audit ini menjadi titik referensi hasil pemeriksaan. Dokumen source tetap tidak diubah. Tahap Laravel selanjutnya perlu memperbarui spesifikasi berdasarkan temuan ini setelah pengguna memberikan instruksi lanjutan.

## 14. Rencana pekerjaan berikutnya — menunggu instruksi

1. **Bekukan referensi dan skema:** konfirmasi snapshot yang sesuai dengan operasional TGM; peroleh schema terbaru dan contoh konfigurasi produk yang disanitasi tanpa mengubah DB produksi.
2. **Validasi aturan harga:** susun contoh operasional untuk Digital A3+, Large Format, meter lari, Laser/Flatbed, DTG/DTF, merchandise, sisi cetak dan finishing. Tetapkan hasil yang benar bersama pengguna.
3. **Sepakati scope Lite dan hak modul:** pricing percetakan tetap inti, SPK dasar tersedia; definisikan preset Lite/Growth/Pro, checklist developer, dependency dan hak staf. Konfirmasi aspek hosting/support dari lisensi sekali bayar.
4. **Review fondasi Laravel yang sudah ada:** tentukan tindakan terhadap scaffolding platform yang belum selesai; verifikasi isolasi tenant, membership cabang dan akses modul sebelum menambah proses transaksi.
5. **Susun pemetaan data:** mapping ID/kode lama, normalisasi satuan/decimal/charset/tanggal, deduplikasi matriks/rentang, snapshot harga dan HPP. Baru setelah disepakati, rancang migration/import terpisah.
6. **Implementasi bertahap bila diminta:** katalog/pricing tervalidasi → order/SPK dan kasir Lite → produksi/inventori/keuangan tambahan. Pertahankan alur TGM dan hindari menambahkan fitur generik yang menjauh dari kebiasaan pengguna.
7. **Uji pada lingkungan terisolasi:** konsistensi estimator–invoice, batas tier, ukuran minimum, sisi/finishing, status harga, DP/pelunasan, perubahan harga historis, revisi job yang sudah dikerjakan, nomor SPK bersamaan, isolasi tenant, serta printer/integrasi.
8. **Rekonsiliasi dan dokumentasi penerimaan:** bandingkan pesanan, total pembayaran, piutang, HPP dan stok dari data uji; catat selisih yang memang disepakati. Penjualan/deployment baru menjadi tahap tersendiri setelah hasilnya diterima.

Kriteria sebelum POS Lite dianggap layak: harga yang disahkan sama pada semua layar/dokumen, riwayat tidak berubah saat master berubah, pembayaran/retry tidak menggandakan transaksi atau menghapus progres, dan data pelanggan SaaS tidak dapat diakses tenant lain.

**Audit berhenti di sini. Tidak ada implementasi tahap berikutnya yang dimulai setelah instruksi audit-only.**

## Lampiran A — Inventaris controller langsung

`Absensi`, `Administrative`, `Akun`, `Antrian`, `Api`, `Api_auth`, `Api_katalog`, `Api_mobile`, `Api_portal`, `Api_tracking`, `Aset`, `Auth`, `Backup`, `Cekpesanan`, `Chat`, `Clear_queue`, `Content`, `Cron`, `Cron_notif`, `Dashboard`, `Db_check`, `Db_check_menu`, `Db_debug`, `Db_setup`, `Debug`, `DebugMenu`, `Deskprint`, `Employee_monitor`, `ErrorLog`, `Health`, `Hr`, `Karyawan`, `KasirShift`, `Katalog`, `Kelola_pesanan`, `Kinerja`, `Kiosk`, `Konsumen`, `Lapor`, `Laporan`, `Log_audit`, `Manufaktur`, `Marketplace`, `Menu`, `Mobile`, `Notifikasi`, `OperatorBersama`, `Owner`, `OwnerReport`, `Pb`, `Pengaturan`, `Penjualan`, `Print_queue`, `Produk`, `Produksi_Request`, `Profile`, `Rajaongkir`, `Repair_db`, `Restore`, `SearchAI`, `Support`, `SwitchBranch`, `Test`, `Testmakan`, `Tracking`, `Transaksi`, `Warehouse`, `Website`, `Whatsapp_bot`, `Wip`, `Workcenter`.

## Lampiran B — Seluruh tabel pada dump master

Daftar berikut berasal dari definisi `CREATE TABLE` dump Januari. Kolom yang hanya terdapat pada kode/skrip tambahan tidak dimasukkan seolah-olah sudah ada pada dump. Link nama tabel menunjuk definisi schema pada snapshot yang diaudit; isi baris data pelanggan, password, dan kredensial tidak disalin ke laporan.

| Tabel | Kolom pada dump |
| --- | --- |
| [absensi](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L30) | `id`, `karyawan_id`, `tanggal`, `jam_masuk`, `foto_masuk`, `jam_pulang`, `foto_pulang`, `status`, `keterangan`, `created_at` |
| [bom_lines](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L16074) | `bom_line_id`, `product_id`, `panjang`, `lebar`, `quantity`, `satuan_id`, `scrap_factor`, `keterangan`, `bahan_id`, `created_at` |
| [detail_produksi](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L16209) | `detail_produksi_id`, `produksi_id`, `routing_id`, `routing_urutan`, `urutan_sebenarnya`, `mesin_id`, `operator_id`, `qty_input`, `qty_output`, `qty_missing`, `panjang_output`, `lebar_output`, `lokasi_proses`, `material_id`, `alasan_missing`, `catatan`, `status_produksi`, `biaya_proses`, `waktu_mulai`, `waktu_selesai`, `update_time`, `qty_bahan_pakai`, `qty_bahan_rusak` |
| [hari_libur](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L16639) | `id`, `tanggal`, `nama_libur`, `keterangan`, `dibuat_pada`, `diubah_pada` |
| [kasir_shift](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L16681) | `shift_id`, `user_id`, `tanggal_shift`, `jam_open`, `jam_close`, `modal_awal`, `total_penjualan_tunai`, `total_penjualan_non_tunai`, `total_pengeluaran`, `uang_fisik_tutup`, `selisih`, `status`, `catatan` |
| [manual_customers](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L16713) | `customer_id`, `customer_name`, `customer_phone`, `customer_email`, `customer_address`, `created_at`, `updated_at` |
| [manual_payment_history](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L16729) | `payment_id`, `transaction_id`, `payment_date`, `payment_time`, `payment_amount`, `payment_method`, `payment_reference`, `payment_status`, `payment_notes`, `created_at` |
| [manual_transaction_items](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L16774) | `item_id`, `transaction_id`, `item_name`, `item_description`, `quantity`, `unit`, `unit_price`, `discount_percent`, `discount_amount`, `total`, `created_at` |
| [manual_transactions](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L16748) | `transaction_id`, `transaction_number`, `customer_id`, `transaction_date`, `due_date`, `sales_person`, `total_items`, `subtotal`, `discount_total`, `tax_amount`, `grand_total`, `payment_status`, `status`, `priority`, `notes`, `created_at`, `updated_at` |
| [material_variant](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L16794) | `variant_id`, `material_id`, `nama_variant`, `ukuran_lebar`, `satuan_id`, `is_active` |
| [menu](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L16809) | `id`, `nama_menu`, `alias`, `icon`, `icon_fa`, `link`, `icon_menu`, `urutan`, `status`, `created_at`, `updated_at` |
| [roles](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L16844) | `role_id`, `role_name`, `created_at`, `updated_at`, `no_urut` |
| [routing](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L16876) | `routing_id`, `routing_name`, `product_id`, `bom_line_id`, `routing_urutan`, `created_at`, `operator_id` |
| [routing_steps](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17021) | `step_id`, `routing_id`, `sequence`, `operation`, `workcenter_id`, `duration_mins`, `notes` |
| [stok_gudang](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17037) | `stok_id`, `variant_id`, `gudang_id`, `qty`, `updated_at` |
| [submenu](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17051) | `id`, `id_menu`, `nama_submenu`, `link_submenu`, `icon_submenu`, `role_id`, `urutan`, `status`, `created_at`, `updated_at` |
| [tbl_absensi](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17116) | `id`, `user_id`, `employee_id`, `employee_name`, `tanggal`, `jam_masuk`, `jam_pulang`, `scan_masuk`, `scan_keluar`, `terlambat`, `pulang_cepat`, `lembur`, `jadwal_hadir`, `pengesahan`, `keterangan`, `status_kehadiran`, `created_at`, `updated_at` |
| [tbl_antrian](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17143) | `antrian_id`, `nomor_antrian`, `counter_id`, `counter_kode`, `status`, `estimasi_waktu`, `waktu_ambil`, `waktu_panggil`, `waktu_selesai`, `user_id`, `keterangan`, `created_at`, `updated_at` |
| [tbl_antrian_counter](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17165) | `counter_id`, `counter_nama`, `counter_kode`, `counter_nomorurut`, `status`, `created_at`, `updated_at` |
| [tbl_antrian_history](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17192) | `history_id`, `antrian_id`, `status_lama`, `status_baru`, `user_id`, `waktu_perubahan`, `keterangan` |
| [tbl_antrian_stats](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17208) | `stats_id`, `counter_id`, `tanggal`, `total_antrian`, `antrian_selesai`, `antrian_batal`, `rata_waktu_tunggu`, `waktu_puncak`, `created_at` |
| [tbl_bahan](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17226) | `bahan_id`, `barcode`, `bahan_nama`, `satuan_id`, `bahan_keterangan`, `jenis_barang`, `updated_at`, `keterangan`, `product_jasa`, `active` |
| [tbl_belanja_bahan](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17325) | `belanja_id`, `bahan_id`, `harga_beli`, `qty_beli`, `suplier_id` |
| [tbl_bulan](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17339) | `bulan_id`, `bulan_kode`, `bulan_nama` |
| [tbl_chat_umum](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17369) | `chat_id`, `user_id`, `pesan`, `created_at` |
| [tbl_config](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17382) | `id`, `bg_login`, `nama_perusahaan` |
| [tbl_detail_product](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17401) | `detail_id`, `detail_product` |
| [tbl_divisi](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17412) | `divisi_id`, `divisi_nama`, `divisi_keterangan` |
| [tbl_ewallet](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17443) | `id`, `ewallet_nama`, `wallet_ket` |
| [tbl_flow_diagram](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17464) | `id`, `nospk`, `waktu_transaksi`, `konsumen_id`, `status_transaksi` |
| [tbl_gaji_manual](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17478) | `id`, `karyawan_id`, `bulan`, `gaji_pokok`, `tunjangan_jabatan`, `tunjangan_transportasi`, `bonus_kinerja`, `bonus_kehadiran`, `uang_lembur`, `potongan_alpha`, `kasbon`, `total_gaji`, `keterangan`, `created_at`, `updated_at` |
| [tbl_gudang](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17502) | `gudang_id`, `gudang_nama`, `gudang_lokasi` |
| [tbl_harga_product](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17523) | `harga_id`, `product_id`, `detail_product`, `range_id`, `jml_minimum`, `hpp`, `harga_1`, `qty_min`, `qty_max`, `harga_aktif`, `ket_1`, `is_active`, `created_at`, `side_id`, `jenisprint_id` |
| [tbl_hutang](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L17997) | `hutang_id`, `pembelian_id`, `supplier_id`, `total_tagihan`, `total_bayar`, `sisa_hutang`, `jatuh_tempo`, `status`, `created_at`, `updated_at` |
| [tbl_hutang_pembayaran](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L18016) | `pembayaran_id`, `hutang_id`, `tanggal_bayar`, `jumlah_bayar`, `metode_bayar`, `rekening_tujuan`, `keterangan`, `created_at`, `updated_at` |
| [tbl_jabatan](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L18034) | `jabatan_id`, `nama_jabatan`, `gaji_pokok`, `keterangan`, `no_urut`, `tunjangan_jabatan`, `tunjangan_transportasi` |
| [tbl_jadwal_kerja](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L18080) | `id`, `nama_jadwal`, `jam_masuk`, `jam_pulang`, `toleransi_terlambat`, `is_active`, `created_at`, `updated_at` |
| [tbl_jam_kerja](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L18106) | `id`, `hari`, `jam_masuk`, `jam_pulang`, `urutan` |
| [tbl_jenis_transaksi](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L18133) | `id_jenis_transaksi`, `nama_jenis_transaksi`, `ket_jenis_transaksi` |
| [tbl_karyawan](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L18154) | `karyawan_id`, `nik`, `nama_lengkap`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `alamat`, `telepon`, `email`, `tanggal_masuk`, `status_karyawan`, `gaji_pokok`, `nominal_lembur`, `jabatan_id`, `divisi_id`, `foto`, `perusahaan_id`, `created_at`, `updated_at` |
| [tbl_karyawan_dokumen](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L18250) | `id`, `karyawan_id`, `jenis_dokumen`, `file_path`, `created_at` |
| [tbl_kategori](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L18271) | `kategori_id`, `kategori_nama`, `kategori_deskripsi`, `kategori_url`, `kategori_img`, `kategori_urutan`, `status` |
| [tbl_kategori_pengeluaran](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L18303) | `id`, `kategori_pengeluaran`, `keterangan_pengeluaran` |
| [tbl_konsumen](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L18330) | `konsumen_id`, `konsumen_nama`, `konsumen_alamat`, `konsumen_nohp`, `konsumen_email`, `update_at`, `status` |
| [tbl_lebar_textile](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L34926) | `id`, `lebar_textile`, `lebar_hitung` |
| [tbl_lembur](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L34948) | `id`, `karyawan_id`, `tanggal`, `jam_mulai`, `jam_selesai`, `kegiatan`, `foto_kegiatan`, `status`, `create_at` |
| [tbl_marketplace](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L35036) | `marketplace_id`, `marketplace_nama`, `marketplace_ket` |
| [tbl_mata_cutting](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L35058) | `id_mata`, `keterangan_mata`, `min`, `max` |
| [tbl_material](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L35083) | `material_id`, `bahan_id`, `barcode`, `varian`, `satuan_gudang_id`, `konversi`, `harga_jual`, `stok_min`, `status`, `created_at`, `updated_at` |
| [tbl_menu_access](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L35171) | `id`, `menu_id`, `role_id` |
| [tbl_mesin](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L35247) | `mesin_id`, `mesin_nama`, `kategori`, `mesin_keterangan`, `status`, `operator_id`, `perusahaan_id` |
| [tbl_mesin_edc](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L35278) | `edc_id`, `edc_nama`, `edc_ket` |
| [tbl_mesin_printing](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L35299) | `id`, `mesin_nama`, `mesin_keterangan` |
| [tbl_metode](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L35331) | `metode_id`, `metode_nama` |
| [tbl_mutasi](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L35350) | `mutasi_id`, `no_mutasi`, `mutasi_tgl`, `gudang_asal`, `gudang_tujuan`, `user_id`, `keterangan`, `created_at`, `status` |
| [tbl_mutasi_detail](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L35368) | `detail_id`, `mutasi_id`, `material_id`, `qty` |
| [tbl_operator](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L35381) | `operator_id`, `operator_nama`, `operator_keterangan` |
| [tbl_page_visits](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L35405) | `id`, `user_id`, `page_url`, `page_title`, `visit_count`, `last_visit` |
| [tbl_pembayaran](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37241) | `pembayaran_id`, `nospk`, `konsumen_id`, `tgl_bayar`, `jam_bayar`, `total_tagihan`, `total_bayar`, `status_lunas`, `kasir_id`, `perusahaan_id`, `created_at`, `updated_at` |
| [tbl_pembayaran_detail](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37262) | `detail_id`, `pembayaran_id`, `metode`, `jumlah`, `referensi`, `bank_id`, `edc_id`, `ewallet_id`, `keterangan`, `created_at` |
| [tbl_pembayaran_produk](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37281) | `id`, `nospk`, `product_id`, `qty`, `diskon`, `panjang`, `lebar`, `mesin_id`, `finishing_id`, `harga_id`, `dateline_tgl`, `dateline_jam`, `lokasi_cetak`, `approve`, `created_at` |
| [tbl_pembelian](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37305) | `pembelian_id`, `no_faktur`, `supplier_id`, `pembelian_tgl`, `pembelian_waktu`, `pembelian_total`, `metode_bayar`, `karyawan_id` |
| [tbl_pembelian_detail](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37334) | `detail_id`, `pembelian_id`, `material_id`, `qty`, `harga_beli`, `subtotal` |
| [tbl_pemesanan](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37366) | `nospk`, `konsumen_id`, `deskprint_id`, `id_jenis_transaksi`, `marketplace_id`, `resi_marketplace`, `id_pengiriman`, `perusahaan_id`, `status_pembayaran`, `created_at` |
| [tbl_pemesanan_detail](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37385) | `id`, `product_id`, `qty`, `diskon`, `panjang`, `lebar`, `nospk`, `harga_id`, `dateline_tgl`, `dateline_jam`, `posisi_barang`, `finishing_id`, `approve`, `lokasi_cetak` |
| [tbl_pengajuan_izin](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37416) | `id`, `karyawan_id`, `tanggal_mulai`, `durasi_tipe`, `durasi_jumlah`, `tipe_izin`, `keterangan`, `status`, `created_at`, `responded_at`, `responded_by`, `dokumen` |
| [tbl_pengambilan](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37437) | `pengambilan_id`, `karyawan_id`, `pengambilan_tgl`, `pengambilan_waktu`, `gudang_id` |
| [tbl_pengambilan_detail](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37451) | `detail_id`, `material_id`, `qty`, `pengambilan_id`, `kondisi`, `keterangan`, `gudang_id` |
| [tbl_pengeluaran](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37467) | `id`, `pengeluaran_tgl`, `kategori_pengeluaran_id`, `pengeluaran_detail`, `pengeluaran_jumlah`, `pengeluaran_penerima`, `metode_bayar`, `rekening_tujuan`, `rekening_id`, `ewallet_id`, `edc_id`, `user_id`, `shift_id`, `perusahaan_id`, `created_at`, `updated_at` |
| [tbl_pengiriman](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37492) | `id_pengiriman`, `nama_pengiriman`, `ket_pengiriman` |
| [tbl_pengumuman](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37504) | `id`, `judul`, `isi`, `kategori`, `tanggal_mulai`, `tanggal_selesai`, `user_id`, `perusahaan_id`, `status`, `created_at` |
| [tbl_perusahaan](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37530) | `id_perusahaan`, `nama_perusahaan`, `perusahaan_alias`, `npwp_perusahaan`, `alamat_perusahaan`, `kota`, `provinsi`, `telepon`, `no_wa`, `email`, `fax`, `website`, `kode_pos`, `logo`, `instagram`, `facebook`, `owner`, `status`, `rate_lembur_wajib`, `rate_lembur_kerjaan` |
| [tbl_piutang](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37568) | `piutang_id`, `nospk`, `konsumen_id`, `perusahaan_id`, `total_tagihan`, `total_bayar`, `sisa_piutang`, `jatuh_tempo`, `status`, `created_at`, `updated_at` |
| [tbl_piutang_pembayaran](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37588) | `piutang_pembayaran_id`, `piutang_id`, `tanggal_bayar`, `jumlah_bayar`, `metode_bayar`, `keterangan`, `rekening_id`, `ewallet_id`, `edc_id` |
| [tbl_piutang_penyesuaian](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37606) | `penyesuaian_id`, `piutang_id`, `jenis`, `nilai`, `keterangan`, `created_at` |
| [tbl_product](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37621) | `product_id`, `kategori_id`, `subkategori_id`, `satuan_id`, `tipe_id`, `side_id`, `status`, `barcode_product`, `product_nama`, `product_deskripsi`, `product_img`, `detail_id`, `created_at` |
| [tbl_produksi](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37758) | `produksi_id`, `dateline_tgl`, `konsumen_id`, `dateline_jam`, `product_id`, `harga_id`, `qty`, `panjang`, `lebar`, `nospk`, `created_by`, `perusahaan_id`, `update_time`, `approve`, `catatan`, `status_produksi`, `lokasi_cetak`, `tgl_selesai`, `tgl_kirim_pb` |
| [tbl_quota_cuti](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37786) | `id`, `karyawan_id`, `tahun`, `quota_awal`, `terpakai`, `sisa`, `updated_at` |
| [tbl_range](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37838) | `range_id`, `range_text`, `range_min`, `range_max` |
| [tbl_rekening](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37913) | `id`, `nama_bank`, `no_rekening`, `atas_nama`, `rekening_ket`, `active` |
| [tbl_return_pembelian](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37936) | `return_id`, `supplier_id`, `return_tgl`, `no_return`, `keterangan`, `karyawan_id`, `return_total`, `perusahaan_id`, `created_at` |
| [tbl_return_pembelian_detail](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37954) | `return_detail_id`, `return_id`, `material_id`, `qty_return`, `harga_return`, `alasan_return` |
| [tbl_routing](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37969) | `id`, `nama_routing` |
| [tbl_satuan](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L37980) | `satuan_id`, `satuan_nama`, `satuan_turunan`, `qty_turunan`, `satuan_deskripsi` |
| [tbl_side](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38011) | `side_id`, `side_name` |
| [tbl_slip_gaji](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38031) | `id`, `kode_slip`, `karyawan_id`, `periode_bulan`, `periode_tahun`, `gaji_pokok`, `tunjangan`, `bonus`, `potongan`, `gaji_bersih`, `status_pembayaran`, `tanggal_pembayaran`, `keterangan`, `created_by`, `created_at`, `updated_by`, `updated_at` |
| [tbl_spk](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38057) | `id`, `nospk`, `no_urut_spk`, `user_id`, `konsumen_id`, `metode_pesan`, `id_marketplace`, `resi_marketplace`, `perusahaan_id`, `spk_time` |
| [tbl_stok_gudang](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38076) | `stok_id`, `material_id`, `gudang_id`, `qty`, `created_at`, `last_update` |
| [tbl_stok_log](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38162) | `log_id`, `material_id`, `gudang_id`, `trx_type`, `trx_id`, `qty_in`, `qty_out`, `qty_balance`, `user_id`, `catatan`, `created_at` |
| [tbl_stok_opname](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38182) | `son_id`, `material_id`, `son_tgl`, `son_real`, `son_aplikasi`, `son_keterangan`, `gudang_id`, `verifikasi`, `update_at` |
| [tbl_subkategori](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38200) | `subkategori_id`, `subkategori_nama`, `subkategori_deskripsi`, `subkategori_ket`, `subkategori_img`, `subkategori_urutan`, `status`, `update_time` |
| [tbl_submenu_access](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38249) | `id`, `submenu_id`, `role_id` |
| [tbl_supplier](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38404) | `supplier_id`, `supplier_kode`, `supplier_nama`, `supplier_alamat`, `kota_provinsi`, `supplier_nohp`, `supplier_telp`, `supplier_email`, `alamat_pajak`, `NPWP`, `termin` |
| [tbl_system_roadmap](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38453) | `id`, `feature_name`, `category`, `status`, `priority`, `progress`, `description`, `target_date`, `created_at` |
| [tbl_tahun](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38505) | `id`, `tahun` |
| [tbl_temp_beli](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38524) | `temp_id`, `material_id`, `qty`, `harga` |
| [tbl_temp_pengambilan](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38537) | `temp_id`, `material_id`, `bahan_id`, `qty` |
| [tbl_temp_return](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38550) | `temp_id`, `bahanbaku_id`, `qty_return`, `harga_return`, `alasan_return` |
| [tbl_temp_transaksi](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38564) | `temp_id`, `product_id`, `no_spk`, `konsumen_id`, `temp_qty`, `temp_diskon`, `temp_panjang`, `temp_lebar`, `keterangan`, `user_id`, `harga_id`, `lokasi_cetak`, `dateline_tgl`, `dateline_jam`, `finishing_id`, `perusahaan_id`, `approve` |
| [tbl_type](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38597) | `tipe_id`, `tipe_nama` |
| [tbl_wa_logs](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38661) | `id`, `target`, `message`, `status`, `response`, `created_at` |
| [tbl_wa_setting](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38676) | `id`, `wa_token`, `wa_server`, `wa_number`, `status`, `updated_at`, `manager_number` |
| [tbl_waktu](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38618) | `waktu_id`, `waktu_nama` |
| [tbl_warna](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38638) | `warna_id`, `warna` |
| [user_activity_log](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38787) | `id`, `user_id`, `action`, `module`, `reference_id`, `timestamp` |
| [user_log](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38802) | `id`, `user_id`, `username`, `login_time` |
| [user_roles](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38827) | `user_role_id`, `user_id`, `role_id` |
| [users](https://github.com/nizarsuryadi89-bit/erp_v5/blob/ae1e26081840d397982cfa1b5391aaa967aca277/database/DB_ERP_Master.sql#L38699) | `user_id`, `username`, `password`, `email`, `karyawan_id`, `perusahaan_id1`, `role_id`, `operator_id`, `active`, `created_at`, `updated_at`, `last_login` |
