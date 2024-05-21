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
            $saldoCadangan = 0;
            $mutasiAll = Mutasi::all();
            foreach ($cabangs as $key => $cabangid) {
                $mutasiCabang = $mutasiAll->where('cabang_id', $cabangid)->sortByDesc('id')->first();
                $saldoUmum += (float)($mutasiCabang->saldo_umum ?? 0);
                $saldoKeamilan += (float)($mutasiCabang->saldo_keamilan ?? 0);
                $saldoCSR += (float)($mutasiCabang->saldo_csr ?? 0);
                $saldoCadangan += (float)($mutasiCabang->saldo_cadangan ?? 0);
                $saldoTotal = $saldoUmum + $saldoKeamilan + $saldoCSR + $saldoCadangan;
            }
        } else {
            $userAuthCabang = $userAuth->cabang_id;
    
            $saldo = Mutasi::where('cabang_id', $userAuthCabang)->orderBy('id', 'DESC')->first();
            $saldoUmum = $saldo->saldo_umum ?? 0;
            $saldoKeamilan = $saldo->saldo_keamilan ?? 0;
            $saldoCSR = $saldo->saldo_csr ?? 0;
            $saldoCadangan = $saldo->saldo_cadangan ?? 0;
            $saldoTotal = $saldoUmum + $saldoKeamilan + $saldoCSR + $saldoCadangan;
        }

        //$jumlahAnggota = ($userAuthCabang) ? User::where('cabang_id', $userAuthCabang)->count() : User::all()->count();

        return [
            Stat::make("Total Saldo", number_format($saldoTotal, 2, ',', '.')),
            Stat::make("Saldo Umum", number_format($saldoUmum, 2, ',', '.')), //->chart($saldoTotal->pluck('saldo_umum')->toArray()),
            Stat::make("Saldo Keamilan", number_format($saldoKeamilan, 2, ',', '.')),
            Stat::make("Saldo CSR", number_format($saldoCSR, 2, ',', '.')),
            Stat::make("Saldo Cadangan", number_format($saldoCadangan, 2, ',', '.')),
        ];
    }

    protected static bool $isLazy = false;
}
