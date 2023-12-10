<?php

namespace App\Filament\Widgets;

use App\Models\Mutasi;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    { 
        $userAuthCabang = auth()->user()->cabang_id;
        //$jumlahAnggota = ($userAuthCabang) ? User::where('cabang_id', $userAuthCabang)->count() : User::all()->count();
        $saldoTotal = Mutasi::where('cabang_id', $userAuthCabang)->latest();
        $saldo = ($userAuthCabang) ? $saldoTotal->first() : 0;
        $saldoUmum = ($userAuthCabang) ? $saldo->saldo_umum : 0;
        $saldoKeamilan = ($userAuthCabang) ? $saldo->saldo_keamilan : 0;
        $saldoCSR = ($userAuthCabang) ? $saldo->saldo_csr : 0;
        return [
            Stat::make("Saldo Umum", $saldoUmum),//->chart($saldoTotal->pluck('saldo_umum')->toArray()),
            Stat::make("Saldo Keamilan", $saldoKeamilan),
            Stat::make("Saldo CSR", $saldoCSR),
        ];
    }
}
