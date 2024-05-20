<?php

return [
    // pengaturan persentase distribusi infak
    'persentase_saldo_umum' => 80,
    'persentase_saldo_keamilan' => 12.5,
    'persentase_saldo_csr' => 2.5,
    'persentase_saldo_cadangan' => 5,

    // jenis pengeluaran yang bisa dipilih
    'jenis_pengeluaran' => [
        "Keamilan" => "Pengeluaran Keamilan",
        "CSR" => "Pengeluaran Sosial",
    ],

    // pinjaman 1 pengurus/pengawas/pembina
    'pinjamanOrganisasi' => true,

    // role simplified
    'adminAccessSuper' => ['super_admin'],
    'adminAccess' => ['super_admin', 'admin_pusat', 'monitoring_pusat'],
    'adminAccessCreatePinjaman' => ['super_admin', 'admin_pusat', 'sekretaris_cabang'],
    'adminAccessApprove' => ['super_admin', 'admin_pusat', 'pengawas_cabang', 'bendahara_cabang'],

    // nama role di filament-shield
    'ketua_pembina' => 'pembina_cabang',
    'anggota_pembina' => 'pembina_cabang',
    'ketua_pengawas' => 'pengawas_cabang',
    'anggota_pengawas' => 'pengawas_cabang',
    'ketua_pengurus' => 'ketua_pengurus_cabang',
    'sekretaris' => 'sekretaris_cabang',
    'bendahara' => 'bendahara_cabang',
];