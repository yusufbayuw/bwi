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
        $cabangs = Cabang::pluck('id')->toArray();
        $saldoUmum = 0;
        $saldoKeamilan = 0;
        $saldoCSR = 0;
        $saldoCadangan = 0;

        $pinjamanAll = Pinjaman::where('acc_pinjaman', true);
        $mutasiAll = Mutasi::all();
        foreach ($cabangs as $key => $cabangid) {
            $mutasiCabang = $mutasiAll->where('cabang_id', $cabangid)->whereBetween('created_at', [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth()
            ])->sortByDesc('id')->first();
            $saldoUmum += (float)($mutasiCabang->saldo_umum ?? 0);
            $saldoKeamilan += (float)($mutasiCabang->saldo_keamilan ?? 0);
            $saldoCSR += (float)($mutasiCabang->saldo_csr ?? 0);
            $saldoCadangan += (float)($mutasiCabang->saldo_cadangan ?? 0);
        

        $saldoTotal = $saldoUmum + $saldoKeamilan + $saldoCSR + $saldoCadangan;

        $pinjamanTotal = $pinjamanAll->sum('');
        $pinjamanBerjalan = 0;
        $pinjamanSelesai = 
    } else {
        # code...
    }
    
        return [
            Stat::make('Total Pinjaman', 'abc'),
            Stat::make('Pinjaman Berjalan', 'def'),
            Stat::make('Pinjaman Selesai', 'ghi'),
        ];
    }
}
