<?php

namespace App\Jobs;

use App\Services\BwiMonthlyReport;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateLaporanBulanan2MonthPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $cabang_id;
    private string $period;

    public function __construct(int $cabang_id, ?string $period = null)
    {
        $this->cabang_id = $cabang_id;
        // Fix the target period when queued, not when a delayed worker runs.
        $this->period = $period ?? CarbonImmutable::now('Asia/Jakarta')
            ->subMonthsNoOverflow(2)->format('Y-m');
    }

    public function handle(BwiMonthlyReport $reports): void
    {
        $reports->generate(
            $this->cabang_id,
            CarbonImmutable::parse($this->period.'-01', 'Asia/Jakarta')
        );
    }
}
