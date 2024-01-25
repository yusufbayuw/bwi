<?php

namespace App\Filament\Widgets;

use App\Models\Cabang;
use App\Models\Mutasi;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $userAuth = auth()->user();

        if ($userAuth->hasRole(config('bwi.adminAccess'))) {
            $cabangs = Cabang::pluck('id')->toArray();
            $saldoUmum = 0;
            $saldoKeamilan = 0;
            $saldoCSR = 0;
            $mutasiAll = Mutasi::all();
            foreach ($cabangs as $key => $cabangid) {
                $mutasiCabang = $mutasiAll->where('cabang_id', $cabangid)->sortByDesc('created_at')->first();
                $saldoUmum += (float)($mutasiCabang->saldo_umum);
                $saldoKeamilan += (float)($mutasiCabang->saldo_keamilan);
                $saldoCSR += (float)($mutasiCabang->saldo_csr);
            }
        } else {
            $userAuthCabang = $userAuth->cabang_id;
    
            $saldo = Mutasi::where('cabang_id', $userAuthCabang)->latest()->first();
            $saldoUmum = $saldo->saldo_umum;
            $saldoKeamilan = $saldo->saldo_keamilan;
            $saldoCSR = $saldo->saldo_csr;
        }

        //$jumlahAnggota = ($userAuthCabang) ? User::where('cabang_id', $userAuthCabang)->count() : User::all()->count();

        return [
            Stat::make("Saldo Umum", number_format($saldoUmum, 2, ',', '.')), //->chart($saldoTotal->pluck('saldo_umum')->toArray()),
            Stat::make("Saldo Keamilan", number_format($saldoKeamilan, 2, ',', '.')),
            Stat::make("Saldo CSR", number_format($saldoCSR, 2, ',', '.')),
        ];
    }
}
