<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use App\Models\Cabang;
use App\Models\Cicilan;
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
            $pinjamanTotal = Cicilan::sum('nominal_cicilan');
            $pinjamanBerjalan = Cicilan::where('status_cicilan', false)->sum('nominal_cicilan');
            $pinjamanSelesai = Cicilan::where('status_cicilan', true)->sum('nominal_cicilan');
        } else {
            $pinjamanTotal = Cicilan::where('cabang_id', $userAuth->cabang_id)->sum('nominal_cicilan');
            $pinjamanBerjalan = Cicilan::where('cabang_id', $userAuth->cabang_id)->where('status_cicilan', false)->sum('nominal_cicilan');
            $pinjamanSelesai = Cicilan::where('cabang_id', $userAuth->cabang_id)->where('status_cicilan', true)->sum('nominal_cicilan');
        }

        return [
            Stat::make('Total Pinjaman', number_format($pinjamanTotal, 2, ',', '.'))
                ->description("Pinjaman telah terdistribusi")
                ->descriptionColor("info"),
            Stat::make('Pinjaman Berjalan', number_format($pinjamanBerjalan, 2, ',', '.'))
                ->description("Cicilan yang belum lunas")
                ->descriptionColor("danger"),
            Stat::make('Pinjaman Selesai', number_format($pinjamanSelesai, 2, ',', '.'))
                ->description("Cicilan yang sudah lunas")
                ->descriptionColor("success"),
        ];
    }
}
