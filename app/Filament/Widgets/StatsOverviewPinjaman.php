<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use App\Models\Cabang;
use App\Models\Mutasi;
use App\Models\Pinjaman;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;

class StatsOverviewPinjaman extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $userAuth = auth()->user();

        if ($userAuth->hasRole(config('bwi.adminAccess'))) {
            $pinjamanAll = Pinjaman::where('acc_pinjaman', true);
            $pinjamanTotal = $pinjamanAll->sum('total_pinjaman') ?? 0;
            $pinjamanBerjalan = $pinjamanAll->where('is_organ', false)->sum('total_pinjaman') ?? 0;
            $pinjamanSelesai = $pinjamanAll->where('is_organ', true)->sum('total_pinjaman') ?? 0;
        } else {
            $pinjamanAll = Pinjaman::where('cabang_id', $userAuth->cabang_id)->where('acc_pinjaman', true);
            $pinjamanTotal = $pinjamanAll->sum('total_pinjaman') ?? 0;
            $pinjamanBerjalan = $pinjamanAll->where('is_organ', false)->sum('total_pinjaman') ?? 0;
            $pinjamanSelesai = $pinjamanAll->where('is_organ', true)->sum('total_pinjaman') ?? 0;
        }

        return [
            Stat::make('Total Pinjaman', $pinjamanTotal),
            Stat::make('Pinjaman Berjalan', $pinjamanBerjalan),
            Stat::make('Pinjaman Selesai', $pinjamanSelesai),
        ];
    }
}
