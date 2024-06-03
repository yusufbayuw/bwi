<?php

namespace App\Filament\Widgets;

use App\Models\Cabang;
use App\Models\Mutasi;
use Carbon\Carbon;
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
            $saldoUmumBulanLalu = 0;
            $saldoKeamilanBulanLalu = 0;
            $saldoCSRBulanLalu = 0;
            $saldoCadanganBulanLalu = 0;

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

                $mutasiCabangBulanLalu = $mutasiAll->where('cabang_id', $cabangid)->whereBetween('created_at', [
                    Carbon::now()->startOfMonth()->subMonthNoOverflow(),
                    Carbon::now()->endOfMonth()->subMonthNoOverflow()
                ])->sortByDesc('id')->first();
                if ($mutasiCabangBulanLalu) {
                    $saldoUmumBulanLalu += (float)($mutasiCabangBulanLalu->saldo_umum ?? 0);
                    $saldoKeamilanBulanLalu += (float)($mutasiCabangBulanLalu->saldo_keamilan ?? 0);
                    $saldoCSRBulanLalu += (float)($mutasiCabangBulanLalu->saldo_csr ?? 0);
                    $saldoCadanganBulanLalu += (float)($mutasiCabangBulanLalu->saldo_cadangan ?? 0);
                }
            }
            $saldoTotal = $saldoUmum + $saldoKeamilan + $saldoCSR + $saldoCadangan;
            $saldoTotalBulanLalu = $saldoUmumBulanLalu + $saldoKeamilanBulanLalu + $saldoCSRBulanLalu + $saldoCadanganBulanLalu;

            $deskripsiTotal = "Total uang seluruh cabang";
        } else {
            $userAuthCabang = $userAuth->cabang_id;

            $saldo = Mutasi::where('cabang_id', $userAuthCabang)->whereBetween('created_at', [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth()
            ])->orderBy('id', 'DESC')->first();
            $saldoUmum = (float)($saldo->saldo_umum ?? 0);
            $saldoKeamilan = (float)($saldo->saldo_keamilan ?? 0);
            $saldoCSR = (float)($saldo->saldo_csr ?? 0);
            $saldoCadangan = (float)($saldo->saldo_cadangan ?? 0);
            $saldoTotal = $saldoUmum + $saldoKeamilan + $saldoCSR + $saldoCadangan;

            $mutasiCabangBulanLalu = Mutasi::where('cabang_id', $userAuthCabang)->whereBetween('created_at', [
                Carbon::now()->startOfMonth()->subMonthNoOverflow(),
                Carbon::now()->endOfMonth()->subMonthNoOverflow()
            ])->orderBy('id', 'DESC')->first();

            $saldoUmumBulanLalu = (float)($mutasiCabangBulanLalu->saldo_umum ?? 0);
            $saldoKeamilanBulanLalu = (float)($mutasiCabangBulanLalu->saldo_keamilan ?? 0);
            $saldoCSRBulanLalu = (float)($mutasiCabangBulanLalu->saldo_csr ?? 0);
            $saldoCadanganBulanLalu = (float)($mutasiCabangBulanLalu->saldo_cadangan ?? 0);
            $saldoTotalBulanLalu = $saldoUmumBulanLalu + $saldoKeamilanBulanLalu + $saldoCSRBulanLalu + $saldoCadanganBulanLalu;

            $deskripsiTotal = "Total uang cabang ini";
        }
        $perubahanSaldoTotal = $saldoTotal - $saldoTotalBulanLalu;
        $persenPerubahanSaldoTotal = (($saldoTotalBulanLalu == 0.0) ? '~' : number_format($perubahanSaldoTotal / $saldoTotalBulanLalu * 100, 2, ',', '.'));
        $persentasePerubahanSaldoTotal = ($perubahanSaldoTotal > 0) ? ('Naik ' . number_format($perubahanSaldoTotal, 2, ',', '.') . ' (' . $persenPerubahanSaldoTotal . '%)') : (($perubahanSaldoTotal < 0) ? 'Turun ' . number_format($perubahanSaldoTotal, 2, ',', '.') . ' (' . $persenPerubahanSaldoTotal . '%)' : 'Tidak Ada Perubahan');
        $iconPerubahanSaldoTotal = ($perubahanSaldoTotal > 0) ? 'heroicon-o-arrow-trending-up' : (($perubahanSaldoTotal < 0) ? 'heroicon-o-arrow-trending-down' : 'heroicon-o-arrow-long-right');
        $colorPerubahanSaldoTotal = ($perubahanSaldoTotal >= 0) ? 'success' : 'danger';

        $perubahanSaldoUmum = $saldoUmum - $saldoUmumBulanLalu;
        $persenPerubahanSaldoUmum = (($saldoUmumBulanLalu == 0.0) ? '~' : number_format($perubahanSaldoUmum / $saldoUmumBulanLalu * 100, 2, ',', '.'));
        $persentasePerubahanSaldoUmum = ($perubahanSaldoUmum > 0) ? ('Naik ' . number_format($perubahanSaldoUmum, 2, ',', '.') . ' (' . $persenPerubahanSaldoUmum . '%)') : (($perubahanSaldoUmum < 0) ? 'Turun ' . number_format($perubahanSaldoUmum, 2, ',', '.') . ' (' . $persenPerubahanSaldoUmum . '%)' : 'Tidak Ada Perubahan');
        $iconPerubahanSaldoUmum = ($perubahanSaldoUmum > 0) ? 'heroicon-o-arrow-trending-up' : (($perubahanSaldoUmum < 0) ? 'heroicon-o-arrow-trending-down' : 'heroicon-o-arrow-long-right');
        $colorPerubahanSaldoUmum = ($perubahanSaldoUmum > 0) ? 'success' : (($perubahanSaldoUmum < 0) ? 'danger' : 'info');

        $perubahanSaldoKeamilan = $saldoKeamilan - $saldoKeamilanBulanLalu;
        $persenPerubahanSaldoKeamilan = (($saldoKeamilanBulanLalu == 0.0) ? '~' : number_format($perubahanSaldoKeamilan / $saldoKeamilanBulanLalu * 100, 2, ',', '.'));
        $persentasePerubahanSaldoKeamilan = ($perubahanSaldoKeamilan > 0) ? ('Naik ' . number_format($perubahanSaldoKeamilan, 2, ',', '.') . ' (' . $persenPerubahanSaldoKeamilan . '%)') : (($perubahanSaldoKeamilan < 0) ? 'Turun ' . number_format($perubahanSaldoKeamilan, 2, ',', '.') . ' (' . $persenPerubahanSaldoKeamilan . '%)' : 'Tidak Ada Perubahan');
        $iconPerubahanSaldoKeamilan = ($perubahanSaldoKeamilan > 0) ? 'heroicon-o-arrow-trending-up' : (($perubahanSaldoKeamilan < 0) ? 'heroicon-o-arrow-trending-down' : 'heroicon-o-arrow-long-right');
        $colorPerubahanSaldoKeamilan = ($perubahanSaldoKeamilan > 0) ? 'success' : (($perubahanSaldoKeamilan < 0) ? 'danger' : 'info');

        $perubahanSaldoCSR = $saldoCSR - $saldoCSRBulanLalu;
        $persenPerubahanSaldoCSR = (($saldoCSRBulanLalu == 0.0) ? '~' : number_format($perubahanSaldoCSR / $saldoCSRBulanLalu * 100, 2, ',', '.'));
        $persentasePerubahanSaldoCSR = ($perubahanSaldoCSR > 0) ? ('Naik ' . number_format($perubahanSaldoCSR, 2, ',', '.') . ' (' . $persenPerubahanSaldoCSR . '%)') : (($perubahanSaldoCSR < 0) ? 'Turun ' . number_format($perubahanSaldoCSR, 2, ',', '.') . ' (' . $persenPerubahanSaldoCSR . '%)' : 'Tidak Ada Perubahan');
        $iconPerubahanSaldoCSR = ($perubahanSaldoCSR > 0) ? 'heroicon-o-arrow-trending-up' : (($perubahanSaldoCSR < 0) ? 'heroicon-o-arrow-trending-down' : 'heroicon-o-arrow-long-right');
        $colorPerubahanSaldoCSR = ($perubahanSaldoCSR > 0) ? 'success' : (($perubahanSaldoCSR < 0) ? 'success' : 'info');

        $perubahanSaldoCadangan = $saldoCadangan - $saldoCadanganBulanLalu;
        $persenPerubahanSaldoCadangan = (($saldoCadanganBulanLalu == 0.0) ? '~' : number_format($perubahanSaldoCadangan / $saldoCadanganBulanLalu * 100, 2, ',', '.'));
        $persentasePerubahanSaldoCadangan = ($perubahanSaldoCadangan > 0) ? ('Naik ' . number_format($perubahanSaldoCadangan, 2, ',', '.') . ' (' . $persenPerubahanSaldoCadangan . '%)') : (($perubahanSaldoCadangan < 0) ? 'Turun ' . number_format($perubahanSaldoCadangan, 2, ',', '.') . ' (' . $persenPerubahanSaldoCadangan . '%)' : 'Tidak Ada Perubahan');
        $iconPerubahanSaldoCadangan = ($perubahanSaldoCadangan > 0) ? 'heroicon-o-arrow-trending-up' : (($perubahanSaldoCadangan < 0) ? 'heroicon-o-arrow-trending-down' : 'heroicon-o-arrow-long-right');
        $colorPerubahanSaldoCadangan = ($perubahanSaldoCadangan > 0) ? 'success' : (($perubahanSaldoCadangan < 0) ? 'danger' : 'info');
        //$jumlahAnggota = ($userAuthCabang) ? User::where('cabang_id', $userAuthCabang)->count() : User::all()->count();

        return [
            Stat::make("Total Saldo", number_format($saldoTotal, 2, ',', '.'))
                ->description($deskripsiTotal)
                ->descriptionIcon($iconPerubahanSaldoTotal)
                ->color($colorPerubahanSaldoTotal),
            Stat::make("Saldo Umum", number_format($saldoUmum, 2, ',', '.'))
                ->description($persentasePerubahanSaldoUmum)
                ->descriptionIcon($iconPerubahanSaldoUmum)
                ->color($colorPerubahanSaldoUmum), //->chart($saldoTotal->pluck('saldo_umum')->toArray()),
            Stat::make("Saldo Keamilan", number_format($saldoKeamilan, 2, ',', '.'))
                ->description($persentasePerubahanSaldoKeamilan)
                ->descriptionIcon($iconPerubahanSaldoKeamilan)
                ->color($colorPerubahanSaldoKeamilan),
            Stat::make("Saldo CSR", number_format($saldoCSR, 2, ',', '.'))
                ->description($persentasePerubahanSaldoCSR)
                ->descriptionIcon($iconPerubahanSaldoCSR)
                ->color($colorPerubahanSaldoCSR),
            Stat::make("Saldo Cadangan", number_format($saldoCadangan, 2, ',', '.'))
                ->description($persentasePerubahanSaldoCadangan)
                ->descriptionIcon($iconPerubahanSaldoCadangan)
                ->color($colorPerubahanSaldoCadangan),
        ];
    }

    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;
}
