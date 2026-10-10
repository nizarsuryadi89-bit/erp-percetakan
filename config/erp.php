<?php

return [
    'package_name' => 'Akses ERP Percetakan',
    'license_price' => '0.00',
    'roles' => ['Owner', 'Administrator', 'Kasir', 'Operator Produksi', 'Keuangan', 'Read Only'],
    'module_registry' => [
        'POS' => ['name' => 'Transaksi / POS', 'description' => 'Order, SPK, kasir, invoice, dan piutang konsumen.', 'feature_codes' => ['transaksi'], 'sort_order' => 0, 'is_active' => true, 'dependencies' => []],
        'PRODUCTION' => ['name' => 'Produksi', 'description' => 'BOM, routing, antrean produksi, finishing, dan QC.', 'feature_codes' => ['bom', 'routing', 'produksi'], 'sort_order' => 1, 'is_active' => true, 'dependencies' => ['POS']],
        'INVENTORY' => ['name' => 'Persediaan', 'description' => 'Bahan, stok, penerimaan, dan pergerakan persediaan.', 'feature_codes' => [], 'sort_order' => 2, 'is_active' => true, 'dependencies' => []],
        'FINANCE' => ['name' => 'Keuangan', 'description' => 'Pembayaran, piutang, pengeluaran, dan rekap keuangan.', 'feature_codes' => ['keuangan'], 'sort_order' => 3, 'is_active' => true, 'dependencies' => ['POS']],
        'CRM' => ['name' => 'CRM', 'description' => 'Pelanggan, prospek, dan tindak lanjut penjualan.', 'feature_codes' => [], 'sort_order' => 4, 'is_active' => true, 'dependencies' => []],
        'HRM' => ['name' => 'SDM', 'description' => 'Karyawan, kehadiran, dan pengelolaan sumber daya manusia.', 'feature_codes' => [], 'sort_order' => 5, 'is_active' => true, 'dependencies' => []],
        'ANALYTICS' => ['name' => 'Analitik / Laba Rugi', 'description' => 'Laporan, pendapatan, HPP, biaya, dan laba per periode.', 'feature_codes' => ['laba_rugi'], 'sort_order' => 6, 'is_active' => true, 'dependencies' => ['POS', 'PRODUCTION', 'FINANCE']],
        'AI_ASSISTANT' => ['name' => 'Asisten AI', 'description' => 'Bantuan analisis dan pencarian informasi ERP.', 'feature_codes' => [], 'sort_order' => 7, 'is_active' => true, 'dependencies' => []],
    ],
    'modules' => [
        'transaksi' => ['name' => 'Transaksi', 'description' => 'Order / SPK, pembayaran kasir, invoice, dan piutang konsumen.', 'dependencies' => [], 'steps' => ['Buat SPK', 'Detail pesanan', 'Pembayaran kasir', 'Cetak invoice']],
        'bom' => ['name' => 'BOM', 'description' => 'Kebutuhan bahan, satuan, ukuran, dan scrap per produk atau variasi.', 'dependencies' => [], 'steps' => ['Pilih produk / variasi', 'Tentukan bahan', 'Jumlah kebutuhan', 'Hitung HPP']],
        'routing' => ['name' => 'Routing', 'description' => 'Urutan pekerjaan, mesin, operator, dan finishing per produk.', 'dependencies' => [], 'steps' => ['Pilih produk / variasi', 'Urutan proses', 'Mesin dan operator', 'Finishing']],
        'produksi' => ['name' => 'Produksi', 'description' => 'SPK produksi, pekerjaan operator, konsumsi bahan, QC, dan penyerahan.', 'dependencies' => ['transaksi', 'bom', 'routing'], 'steps' => ['Antrean SPK', 'Proses produksi', 'Finishing / QC', 'Penyerahan barang']],
        'keuangan' => ['name' => 'Keuangan', 'description' => 'Piutang, pengeluaran, serta mutasi tunai, transfer, EDC, dan e-wallet.', 'dependencies' => ['transaksi'], 'steps' => ['Pembayaran', 'Piutang', 'Pengeluaran', 'Rekap metode pembayaran']],
        'laba_rugi' => ['name' => 'Laba Rugi', 'description' => 'Pendapatan, HPP, biaya, dan laba per periode.', 'dependencies' => ['transaksi', 'keuangan', 'bom'], 'steps' => ['Pilih periode', 'Pendapatan', 'HPP dan biaya', 'Laba bersih']],
    ],
];
