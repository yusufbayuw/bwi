<?php

return [
    'persentase_saldo_umum' => 77.5,
    'persentase_saldo_keamilan' => 12.5,
    'persentase_saldo_csr' => 2.5,
    'persentase_saldo_cadangan' => 5,

    'jenis_pengeluaran' => [
        "Keamilan" => "Pengeluaran Keamilan",
        "CSR" => "Pengeluaran Sosial",
    ],

    'adminAccessSuper' => 'super_admin',
    'adminAccess' => ['super_admin', 'admin_pusat'],
    'adminAccessApprove' => ['super_admin', 'admin_pusat', 'pengawas_cabang'],
];