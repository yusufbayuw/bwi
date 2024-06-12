<?php

namespace App\Console;

use Carbon\Carbon;
use App\Models\Infak;
use App\Models\Cabang;
use App\Models\Mutasi;
use App\Models\Cicilan;
use App\Models\Laporan;
use App\Models\Pinjaman;
use App\Models\Pengeluaran;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Jobs\GenerateLaporanBulananPdfJob;
use App\Jobs\GenerateLaporanBulanan2MonthPdf;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->call(function () {
            $cabangs = Cabang::all()->pluck('id')->toArray();

            foreach ($cabangs as $key => $cabang_id) {
                dispatch(new GenerateLaporanBulananPdfJob($cabang_id));
            }
        });

        $schedule->call(function () {
            $cabangs = Cabang::all()->pluck('id')->toArray();

            foreach ($cabangs as $key => $cabang_id) {
                dispatch(new GenerateLaporanBulanan2MonthPdf($cabang_id));
            }
        });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
