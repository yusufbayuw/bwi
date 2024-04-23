<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Infak;
use App\Models\Cabang;
use App\Models\Cicilan;
use App\Models\Mutasi;
use App\Models\Pengeluaran;
use App\Models\Pinjaman;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class LaporanBulananCabangController extends Controller
{
    public function generatePDF()
    {
        $pdf = Pdf::setOption(['dpi' => 150, 'isHtml5ParserEnabled' => true, 'defaultFont' => 'sans-serif'])->loadView('laporan.bulanan.cabang');
        return $pdf->stream(); //download('invoice.pdf');
    }

    public function laporanBulananCabang()
    {
        $cabang = Cabang::find($cabang_id);
        // nama cabang
        $cabang_nama = $cabang->nama_cabang;
        // alamat cabang
        $cabang_alamat = $cabang->lokasi;
        // jenis laporan
        $jenis_laporan = "";
        // bulan laporan
        $months = [
            'January' => 'Januari',
            'February' => 'Februari',
            'March' => 'Maret',
            'April' => 'April',
            'May' => 'Mei',
            'June' => 'Juni',
            'July' => 'Juli',
            'August' => 'Agustus',
            'September' => 'September',
            'October' => 'Oktober',
            'November' => 'November',
            'December' => 'Desember'
        ];
        $bulan_laporan = $months[date('F')];
        // 
        // LAPORAN SELISIH (laba-rugi)
        $infak = Infak::whereBetween('created_at', [
            Carbon::now()->subMonth()->startOfMonth(),
            Carbon::now()->subMonth()->endOfMonth()
        ])->sum('nominal');
        // infak umum
        $infak_umum = config('bwi.persentase_saldo_umum')*$infak;
        // infak keamilan
        $infak_keamilan = config('bwi.persentase_saldo_keamilan')*$infak;
        // infak sosial
        $infak_sosial = config('bwi.persentase_saldo_csr')*$infak;
        // infak cadangan
        $infak_cadangan = config('bwi.persentase_saldo_cadangan')*$infak;
        // total infak
        $infak_total = $infak;
        // total cicilan
        $cicilan = Cicilan::whereBetween('tanggal_bayar', [
            Carbon::now()->subMonth()->startOfMonth(),
            Carbon::now()->subMonth()->endOfMonth()
        ])->where('status_bayar', true)->sum('nominal_cicilan');
        // total pinjaman
        $pinjaman = Pinjaman::whereBetween('updated_at', [
            Carbon::now()->subMonth()->startOfMonth(),
            Carbon::now()->subMonth()->endOfMonth()
        ])->where('acc_pinjaman', true)->sum('total_pinjaman');
        // pengeluaran
        $pengeluaran = Pengeluaran::whereBetween('tanggal', [
            Carbon::now()->subMonth()->startOfMonth(),
            Carbon::now()->subMonth()->endOfMonth()
        ]);
        // pengeluaran keamilan
        $pengeluaran_keamilan = $pengeluaran->where('jenis', 'Keamilan')->sum('nominal');
        // pengeluaran sosial
        $pengeluaran_sosial = $pengeluaran->where('jenis', 'CSR')->sum('nominal');
        // pengeluaran gagal bayar
        $pengeluaran_cadangan = $pengeluaran->where('jenis', 'Cadangan')->sum('nominal');
        // total pengeluaran
        $total_pengeluaran = $pengeluaran->sum('nominal');
        // total pendapatan
        $total_pendapatan = $infak_total + $cicilan;
        // total beban
        $total_beban = $pinjaman + $total_pengeluaran;
        // selisih
        $selisih = $total_pendapatan - $total_beban;
        // keterangan surplus/defisit
        $keterangan = ($selisih > 0) ? "Surplus" : (($selisih < 0) ? "Defisit" : "Impas");

        // laporan posisi keuangan
        $mutasi = Mutasi::whereBetween('created_at', [
            Carbon::now()->subMonth()->startOfMonth(),
            Carbon::now()->subMonth()->endOfMonth()
        ]);
        $mutasi_first =  $mutasi->oldest()->first();
        $mutasi_last = $mutasi->latest()->first();
        // saldo umum
        $saldo_umum = $mutasi_last->saldo_umum;
        // saldo keamilan
        $saldo_keamilan = $mutasi_last->saldo_keaamilan;
        // saldo sosial
        $saldo_sosial = $mutasi_last->saldo_csr;
        // saldo cadangan
        $saldo_cadangan = $mutasi_last->saldo_cadangan;
        // Piutang (anggota)
        $pinjaman_berjalan = "";
        $cicilan_berjalan = "";
        $piutang = $pinjaman_berjalan - $cicilan_berjalan;
        // total asset
        $total_asset = $saldo_umum + $saldo_keamilan + $saldo_sosial + $saldo_cadangan + $piutang;

        // laporan arus kas
        // total infak
        $total_infak = $infak_total;
        // total cicilan
        $total_cicilan = $cicilan;
        // total pendapatan
        // total pinjaman
        $total_pinjaman = $pinjaman;
        // total pengeluaran
        // posisi awal
        $saldo_awal = $mutasi_first->saldo_umum + $mutasi_first->saldo_keamilan + $mutasi_first->saldo_csr + $mutasi_first->saldo_cadangan;
        // selisih
        // posisi akhir
        $saldo_akhir = $saldo_awal + $selisih;
        // 
        
        $data = [];
    }
}
