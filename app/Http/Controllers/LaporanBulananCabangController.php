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

    public function laporanBulananCabang($cabang_id, $jenis)
    {
        $cabang = Cabang::find($cabang_id);
        // nama cabang
        $cabang_nama = $cabang->nama_cabang;
        // alamat cabang
        $cabang_alamat = $cabang->lokasi;
        // jenis laporan
        $jenis_laporan = $jenis;
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
        ])->where('cabang_id', $cabang_id)->where('status_cicilan', true)->sum('nominal_cicilan');
        // total pinjaman
        $pinjaman = Pinjaman::whereBetween('updated_at', [
            Carbon::now()->subMonth()->startOfMonth(),
            Carbon::now()->subMonth()->endOfMonth()
        ])->where('cabang_id', $cabang_id)->where('acc_pinjaman', true)->sum('total_pinjaman');
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
        $mutasi = Mutasi::where('cabang_id', $cabang_id)->whereBetween('created_at', [
            Carbon::now()->subMonth(10)->startOfMonth(),
            Carbon::now()->subMonth()->endOfMonth()
        ]);
        $mutasi_first =  $mutasi->oldest()->first();
        $mutasi_last = $mutasi->latest()->first();
        dd($mutasi_first);
        // saldo umum
        $saldo_umum = $mutasi_last->saldo_umum ?? 0;
        // saldo keamilan
        $saldo_keamilan = $mutasi_last->saldo_keamilan ?? 0;
        // saldo sosial
        $saldo_sosial = $mutasi_last->saldo_csr ?? 0;
        // saldo cadangan
        $saldo_cadangan = $mutasi_last->saldo_cadangan ?? 0;
        // Piutang (anggota)
        $pinjaman_berjalan_aktif = Pinjaman::where('cabang_id', $cabang_id)->where('acc_pinjaman', true)->where('status', false)->get();
        $pinjaman_berjalan = Pinjaman::where('cabang_id', $cabang_id)->where('acc_pinjaman', true)->where('status', false)->sum('total_pinjaman');
        $cicilan_berjalan = 0;
        foreach ($pinjaman_berjalan_aktif as $key => $value) {
            $cicilan_berjalan_aktif = Cicilan::where('pinjaman_id', $value->id)->where('status_cicilan', true)->sum('nominal_cicilan');
            $cicilan_berjalan += $cicilan_berjalan_aktif;
        }
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
        $saldo_awal = ($mutasi_first->saldo_umum ?? 0) + ($mutasi_first->saldo_keamilan ?? 0) + ($mutasi_first->saldo_csr ?? 0) + ($mutasi_first->saldo_cadangan ?? 0);
        // selisih
        // posisi akhir
        $saldo_akhir = $saldo_awal + $selisih;
        // 
        
        $data = [
            'cabang_nama' => $cabang_nama,
            'cabang_alamat' => $cabang_alamat,
            'jenis_laporan' => ucwords(strtolower($jenis_laporan)),
            'bulan_laporan' => $bulan_laporan,
            'infak_umum' => number_format($infak_umum, 2, ",", "."),
            'infak_keamilan' => number_format($infak_keamilan, 2, ",", "."),
            'infak_sosial' => number_format($infak_sosial, 2, ",", "."),
            'infak_cadangan' => number_format($infak_cadangan, 2, ",", "."),
            'cicilan' => number_format($cicilan, 2, ",", "."),
            'pinjaman' => number_format($pinjaman, 2, ",", "."),
            'pengeluaran_keamilan' => number_format($pengeluaran_keamilan, 2, ",", "."),
            'pengeluaran_sosial' => number_format($pengeluaran_sosial, 2, ",", "."),
            'pengeluaran_cadangan' => number_format($pengeluaran_cadangan, 2, ",", "."),
            'total_pengeluaran' => number_format($total_pengeluaran, 2, ",", "."),
            'total_pendapatan' => number_format($total_pendapatan, 2, ",", "."),
            'total_beban' => number_format($total_beban, 2, ",", "."),
            'selisih' => number_format($selisih, 2, ",", "."),
            'keterangan' => $keterangan,
            'saldo_umum' => number_format($saldo_umum, 2, ",", "."),
            'saldo_keamilan' => number_format($saldo_keamilan, 2, ",", "."),
            'saldo_sosial' => number_format($saldo_sosial, 2, ",", "."),
            'saldo_cadangan' => number_format($saldo_cadangan, 2, ",", "."),
            'pinjaman_berjalan' => number_format($pinjaman_berjalan, 2, ",", "."),
            'cicilan_berjalan' => number_format($cicilan_berjalan, 2, ",", "."),
            'piutang' => number_format($piutang, 2, ",", "."),
            'total_asset' => number_format($total_asset, 2, ",", "."),
            'total_infak' => number_format($total_infak, 2, ",", "."),
            'total_cicilan' => number_format($total_cicilan, 2, ",", "."),
            'total_pinjaman' => number_format($total_pinjaman, 2, ",", "."),
            'saldo_awal' => number_format($saldo_awal, 2, ",", "."),
            'saldo_akhir' => number_format($saldo_akhir, 2, ",", "."),
        ]; 

        $pdf = Pdf::setOption(['dpi' => 150, 'isHtml5ParserEnabled' => true, 'defaultFont' => 'sans-serif'])->loadView('laporan.bulanan.cabang', $data);
        return $pdf->stream(); //download('invoice.pdf');
    }
}
