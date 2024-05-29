<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\Infak;
use App\Models\Cabang;
use App\Models\Mutasi;
use App\Models\Cicilan;
use App\Models\Laporan;
use App\Models\Pinjaman;
use App\Models\Pengeluaran;
use Illuminate\Bus\Queueable;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class GenerateLaporanBulananPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $cabang_id;
    /**
     * Create a new job instance.
     */
    public function __construct($cabang_id)
    {
        $this->cabang_id = $cabang_id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cabang = Cabang::find($this->cabang_id);
                // nama cabang
                $cabang_nama = $cabang->nama_cabang;
                // alamat cabang
                $cabang_alamat = $cabang->lokasi;
                // jenis laporan
                $jenis_laporan = "Bulanan";
                // bulan laporan

                $tahun = date('Y');
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
                    Carbon::now()->startOfMonth(), //Carbon::now()->startOfMonth()
                    Carbon::now()->endOfMonth()
                ])->where('cabang_id', $this->cabang_id)->sum('nominal');
                // infak umum
                $infak_umum = config('bwi.persentase_saldo_umum') * $infak / 100;
                // infak keamilan
                $infak_keamilan = config('bwi.persentase_saldo_keamilan') * $infak / 100;
                // infak sosial
                $infak_sosial = config('bwi.persentase_saldo_csr') * $infak / 100;
                // infak cadangan
                $infak_cadangan = config('bwi.persentase_saldo_cadangan') * $infak / 100;
                // total infak
                $infak_total = $infak;
                // total cicilan
                $cicilan = Cicilan::whereBetween('tanggal_bayar', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth()
                ])->where('cabang_id', $this->cabang_id)->where('status_cicilan', true)->sum('nominal_cicilan');
                // total pinjaman
                $pinjaman = Pinjaman::whereBetween('updated_at', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth()
                ])->where('cabang_id', $this->cabang_id)->where('acc_pinjaman', true)->sum('total_pinjaman');
                // pengeluaran

                // pengeluaran keamilan
                $pengeluaran_keamilan = Pengeluaran::whereBetween('tanggal', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth()
                ])->where('cabang_id', $this->cabang_id)->where('jenis', 'Keamilan')->sum('nominal');
                // pengeluaran sosial
                $pengeluaran_sosial = Pengeluaran::whereBetween('tanggal', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth()
                ])->where('cabang_id', $this->cabang_id)->where('jenis', 'CSR')->sum('nominal');
                // pengeluaran gagal bayar
                $pengeluaran_cadangan = Pengeluaran::whereBetween('tanggal', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth()
                ])->where('cabang_id', $this->cabang_id)->where('jenis', 'Cadangan')->sum('nominal');
                // total pengeluaran
                $total_pengeluaran = $pengeluaran_keamilan + $pengeluaran_cadangan + $pengeluaran_sosial;
                // total pendapatan
                $total_pendapatan = $infak_total + $cicilan;
                // total beban
                $total_beban = $pinjaman + $total_pengeluaran;
                // selisih
                $selisih = $total_pendapatan - $total_beban;
                // keterangan surplus/defisit
                $keterangan = ($selisih > 0) ? "Surplus" : (($selisih < 0) ? "Defisit" : "Impas");

                // laporan posisi keuangan
                $mutasi_last = Mutasi::where('cabang_id', $this->cabang_id)->whereBetween('created_at', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth()
                ])->orderBy('id', 'DESC')->first();
                $mutasi_first = Mutasi::where('cabang_id', $this->cabang_id)->whereBetween('created_at', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth()
                ])->orderBy('id', 'ASC')->first();

                // saldo umum
                $saldo_umum = $mutasi_last->saldo_umum ?? 0;
                // saldo keamilan
                $saldo_keamilan = $mutasi_last->saldo_keamilan ?? 0;
                // saldo sosial
                $saldo_sosial = $mutasi_last->saldo_csr ?? 0;
                // saldo cadangan
                $saldo_cadangan = $mutasi_last->saldo_cadangan ?? 0;
                // Piutang (anggota)
                $pinjaman_berjalan_aktif = Pinjaman::where('cabang_id', $this->cabang_id)->where('acc_pinjaman', true)->where('status', false)->get();
                $pinjaman_berjalan = Pinjaman::where('cabang_id', $this->cabang_id)->where('acc_pinjaman', true)->where('status', false)->sum('total_pinjaman');
                $cicilan_berjalan = 0;
                foreach ($pinjaman_berjalan_aktif as $key => $value) {
                    $cicilan_berjalan_aktif = Cicilan::where('cabang_id', $this->cabang_id)->where('pinjaman_id', $value->id)->where('status_cicilan', true)->sum('nominal_cicilan');
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
                    'bulan_laporan' => $bulan_laporan.' '.$tahun,
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

                $nama_file_laporan = $cabang_nama . ' - Laporan '. $jenis_laporan . ' - ' . $bulan_laporan . ' '. $tahun .'.pdf';
                
                $pdf = Pdf::setOption(['dpi' => 150, 'isHtml5ParserEnabled' => true, 'defaultFont' => 'sans-serif'])->loadView('laporan.bulanan.cabang', $data);
                //dd(public_path('storage\\' . $nama_file_laporan));
                $pdf->save(public_path('storage\\' . $nama_file_laporan)); //download('invoice.pdf');
                
                Laporan::updateOrCreate([
                    'berkas' => $nama_file_laporan
                ],[
                    'cabang_id' => $this->cabang_id,
                    'tanggal' => date('Y-m-d'),
                    'jenis' => 'Laporan Bulanan',
                ]);
    }
}
