<?php

namespace App\Services;

use App\Models\Cabang;
use App\Models\Cicilan;
use App\Models\Laporan;
use App\Models\Mutasi;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BwiMonthlyReport
{
    private const FUNDS = [
        'saldo_umum',
        'saldo_keamilan',
        'saldo_csr',
        'saldo_cadangan',
    ];

    /**
     * All cash flows and receivables are derived from the ledger AS POSTED
     * by the end of the requested month. Never use a loan's current status
     * for a historical balance sheet.
     */
    public function calculate(Cabang $branch, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();
        $end = $start->addMonth();
        $mutations = Mutasi::query()
            ->where('cabang_id', $branch->id)
            ->where('created_at', '<', $end)
            ->orderBy('id')
            ->get();

        $balances = array_fill_keys(self::FUNDS, 0.0);
        $opening = $balances;
        $flows = [
            'infak_umum' => 0.0, 'infak_keamilan' => 0.0,
            'infak_sosial' => 0.0, 'infak_cadangan' => 0.0,
            'cicilan' => 0.0, 'pinjaman' => 0.0,
            'pengeluaran_umum' => 0.0, 'pengeluaran_keamilan' => 0.0,
            'pengeluaran_sosial' => 0.0, 'pengeluaran_cadangan' => 0.0,
            'penyesuaian' => 0.0,
        ];
        $loanDisbursements = [];
        $installmentPayments = [];

        foreach ($mutations as $mutation) {
            $next = [];
            $delta = [];
            foreach (self::FUNDS as $fund) {
                $next[$fund] = (float) ($mutation->{$fund} ?? 0);
                $delta[$fund] = $next[$fund] - $balances[$fund];
            }

            if ($mutation->pinjaman_id !== null && $mutation->debet !== null) {
                // Old data may contain duplicate disbursement entries.
                // Count the first posting only in receivables; audit duplicates separately.
                $loanDisbursements[$mutation->pinjaman_id] ??= (float) $mutation->debet;
            }
            if ($mutation->cicilan_id !== null) {
                $id = (int) $mutation->cicilan_id;
                $installmentPayments[$id] = ($installmentPayments[$id] ?? 0.0)
                    + (float) ($mutation->kredit ?? 0) - (float) ($mutation->debet ?? 0);
            }

            if ($mutation->created_at->greaterThanOrEqualTo($start)) {
                $total = array_sum($delta);
                if ($mutation->infak_id !== null) {
                    $flows['infak_umum'] += $delta['saldo_umum'];
                    $flows['infak_keamilan'] += $delta['saldo_keamilan'];
                    $flows['infak_sosial'] += $delta['saldo_csr'];
                    $flows['infak_cadangan'] += $delta['saldo_cadangan'];
                } elseif ($mutation->pengeluaran_id !== null) {
                    $flows['pengeluaran_umum'] -= $delta['saldo_umum'];
                    $flows['pengeluaran_keamilan'] -= $delta['saldo_keamilan'];
                    $flows['pengeluaran_sosial'] -= $delta['saldo_csr'];
                    $flows['pengeluaran_cadangan'] -= $delta['saldo_cadangan'];
                } elseif ($mutation->pinjaman_id !== null) {
                    $flows['pinjaman'] -= $total;
                } elseif ($mutation->cicilan_id !== null) {
                    $flows['cicilan'] += $total;
                } else {
                    // Initial capital or historic uncategorized adjustments.
                    $flows['penyesuaian'] += $total;
                }
            }

            $balances = $next;
            if ($mutation->created_at->lessThan($start)) {
                $opening = $next;
            }
        }

        $installmentLoanIds = Cicilan::whereIn('id', array_keys($installmentPayments))
            ->pluck('pinjaman_id', 'id');
        $paidPrincipal = 0.0;
        foreach ($installmentPayments as $id => $paid) {
            $loanId = $installmentLoanIds[$id] ?? null;
            if ($loanId !== null && array_key_exists($loanId, $loanDisbursements)) {
                $paidPrincipal += $paid;
            }
        }

        $principal = array_sum($loanDisbursements);
        $receivable = $principal - $paidPrincipal;
        $infak = $flows['infak_umum'] + $flows['infak_keamilan']
            + $flows['infak_sosial'] + $flows['infak_cadangan'];
        $expenses = $flows['pengeluaran_umum'] + $flows['pengeluaran_keamilan']
            + $flows['pengeluaran_sosial'] + $flows['pengeluaran_cadangan'];
        $inflows = $infak + $flows['cicilan'];
        $outflows = $flows['pinjaman'] + $expenses;
        $cashChange = $inflows - $outflows;
        $closingCash = array_sum($balances);

        return array_merge($flows, [
            'infak_total' => $infak,
            'total_infak' => $infak,
            'total_cicilan' => $flows['cicilan'],
            'total_pinjaman' => $flows['pinjaman'],
            'total_pengeluaran' => $expenses,
            'total_pendapatan' => $inflows,
            'total_beban' => $outflows,
            'selisih' => $cashChange,
            'saldo_umum' => $balances['saldo_umum'],
            'saldo_keamilan' => $balances['saldo_keamilan'],
            'saldo_sosial' => $balances['saldo_csr'],
            'saldo_cadangan' => $balances['saldo_cadangan'],
            'pinjaman_berjalan' => $principal,
            'cicilan_berjalan' => $paidPrincipal,
            'piutang' => $receivable,
            'saldo_awal' => array_sum($opening),
            'saldo_akhir' => $closingCash,
            'total_asset' => $closingCash + $receivable,
            'keterangan' => $cashChange > 0 ? 'Arus Kas Positif'
                : ($cashChange < 0 ? 'Arus Kas Negatif' : 'Arus Kas Impas'),
        ]);
    }

    public function viewData(Cabang $branch, CarbonImmutable $month): array
    {
        $data = $this->calculate($branch, $month);
        foreach ($data as $key => $value) {
            if (is_numeric($value)) {
                $data[$key] = number_format((float) $value, 2, ',', '.');
            }
        }

        return array_merge($data, [
            'cabang_nama' => $branch->nama_cabang,
            'cabang_alamat' => $branch->lokasi,
            'jenis_laporan' => 'Bulanan',
            'bulan_laporan' => $month->startOfMonth()->locale('id')->translatedFormat('F Y'),
        ]);
    }

    public function generate(int $branchId, CarbonImmutable $month): Laporan
    {
        $branch = Cabang::findOrFail($branchId);
        $start = $month->startOfMonth();
        $path = 'bwi/reports/cabang-'.$branch->id.'/'.$start->format('Y-m').'.pdf';
        $content = Pdf::loadView('laporan.bulanan.cabang', $this->viewData($branch, $start))->output();

        if (! Storage::disk('local')->put($path, $content)) {
            throw new RuntimeException('Gagal menyimpan PDF laporan pada storage privat.');
        }

        return DB::transaction(function () use ($branch, $start, $path) {
            Cabang::whereKey($branch->id)->lockForUpdate()->firstOrFail();

            $record = Laporan::query()
                ->where('cabang_id', $branch->id)
                ->where('jenis', 'Laporan Bulanan')
                ->whereYear('tanggal', $start->year)
                ->whereMonth('tanggal', $start->month)
                ->orderBy('id')
                ->first() ?? new Laporan();

            $record->cabang_id = $branch->id;
            $record->jenis = 'Laporan Bulanan';
            $record->tanggal = $start->toDateString();
            $record->berkas = $path;
            $record->save();

            return $record;
        });
    }
}
