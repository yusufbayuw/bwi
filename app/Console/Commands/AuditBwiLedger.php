<?php

namespace App\Console\Commands;

use App\Models\Cabang;
use App\Models\Mutasi;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AuditBwiLedger extends Command
{
    protected $signature = 'bwi:audit-ledger
        {--cabang= : Batasi ke ID cabang tertentu}
        {--as-of= : Batas waktu pembukuan, format YYYY-MM-DD}
        {--limit=30 : Batas rincian temuan per cabang}
        {--json : Tampilkan hasil sebagai JSON}';

    protected $description = 'Audit mutasi dan saldo BWI secara READ ONLY; tidak melakukan koreksi data';

    private const FUNDS = ['saldo_umum', 'saldo_keamilan', 'saldo_csr', 'saldo_cadangan'];

    public function handle(): int
    {
        $date = $this->option('as-of');
        try {
            if ($date !== null) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    throw new InvalidArgumentException('Format tanggal harus YYYY-MM-DD.');
                }
                $cutoff = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Jakarta');
                if (!$cutoff || $cutoff->format('Y-m-d') !== $date) {
                    throw new InvalidArgumentException('Tanggal tidak valid.');
                }
                $end = $cutoff->addDay();
            } else {
                $end = CarbonImmutable::now('Asia/Jakarta')->addDay()->startOfDay();
            }
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $branches = Cabang::query()->when($this->option('cabang'), function ($q) {
            $q->whereKey((int) $this->option('cabang'));
        })->orderBy('id')->get();

        $limit = max(1, (int) $this->option('limit'));
        $summary = [];
        $totalProblems = 0;

        foreach ($branches as $branch) {
            $mutations = Mutasi::where('cabang_id', $branch->id)
                ->where('created_at', '<', $end)
                ->orderBy('id')->get();

            $previous = array_fill_keys(self::FUNDS, 0.0);
            $findings = [];
            $problems = 0;

            $record = function (string $message) use (&$findings, &$problems, $limit): void {
                $problems++;
                if (count($findings) < $limit) {
                    $findings[] = $message;
                }
            };

            $disbursements = [];
            $payments = [];

            foreach ($mutations as $mutation) {
                $next = [];
                foreach (self::FUNDS as $fund) {
                    $next[$fund] = (float) ($mutation->{$fund} ?? 0);
                    if ($next[$fund] < -0.01) {
                        $record("Mutasi #{$mutation->id}: saldo negatif {$fund}");
                    }
                }

                $delta = array_sum($next) - array_sum($previous);
                $expected = (float) ($mutation->kredit ?? 0) - (float) ($mutation->debet ?? 0);
                $isOpeningCapital = strtolower(trim((string) $mutation->keterangan)) === 'dana awal cabang';

                // Legacy opening capital was incorrectly labeled as a debit;
                // assess its ending balance, not the debit/credit label.
                if (!$isOpeningCapital && abs($delta - $expected) > 0.02) {
                    $record("Mutasi #{$mutation->id}: perubahan saldo {$delta} tidak sesuai debet/kredit {$expected}");
                }

                $references = array_filter([
                    $mutation->infak_id, $mutation->pengeluaran_id,
                    $mutation->pinjaman_id, $mutation->cicilan_id,
                ], static fn ($value) => $value !== null);
                if (count($references) > 1) {
                    $record("Mutasi #{$mutation->id}: referensi sumber lebih dari satu");
                }

                if ($mutation->pinjaman_id !== null && $mutation->debet !== null) {
                    $disbursements[$mutation->pinjaman_id] = ($disbursements[$mutation->pinjaman_id] ?? 0) + 1;
                }
                if ($mutation->cicilan_id !== null && $mutation->kredit !== null) {
                    $payments[$mutation->cicilan_id] = ($payments[$mutation->cicilan_id] ?? 0) + 1;
                }

                $previous = $next;
            }

            foreach ($disbursements as $loanId => $count) {
                if ($count > 1) {
                    $record("Pinjaman #{$loanId}: {$count} posting pencairan (duplikat)");
                }
            }
            foreach ($payments as $installmentId => $count) {
                if ($count > 1) {
                    $record("Cicilan #{$installmentId}: {$count} posting pembayaran (duplikat)");
                }
            }

            $summary[] = [
                'cabang_id' => (int) $branch->id,
                'nama_cabang' => $branch->nama_cabang,
                'mutasi_diperiksa' => $mutations->count(),
                'saldo_akhir' => $previous,
                'jumlah_masalah' => $problems,
                'contoh_masalah' => $findings,
            ];
            $totalProblems += $problems;
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'read_only' => true, 'jumlah_cabang' => $branches->count(),
                'jumlah_masalah' => $totalProblems, 'cabang' => $summary,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            foreach ($summary as $branch) {
                $this->line("Cabang #{$branch['cabang_id']} ({$branch['nama_cabang']}): "
                    ."{$branch['mutasi_diperiksa']} mutasi, {$branch['jumlah_masalah']} temuan.");
                foreach ($branch['contoh_masalah'] as $problem) {
                    $this->warn('  - '.$problem);
                }
            }
            $this->info("Audit READ ONLY: {$branches->count()} cabang, {$totalProblems} temuan. Tidak ada data diubah.");
        }

        return $totalProblems === 0 ? self::SUCCESS : self::FAILURE;
    }
}
