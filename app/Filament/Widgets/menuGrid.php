<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;
use Filament\Facades\Filament;

class menuGrid extends Widget
{
    use HasWidgetShield;

    protected static string $view = 'filament.widgets.menu-grid';

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $navigation = Filament::getNavigation();

        $dasborUrl = null;
        $cabangUrl = null;
        $anggotaUrl = null;
        $infaqUrl = null;
        $pinjamanUrl = null;
        $cicilanUrl = null;
        $pengeluaranUrl = null;

        foreach ($navigation as $key => $nav) {
            $item =  $nav->getItems()->toArray();
            foreach ($item as $label => $navigationItem) {
                if ($label === 'Dasbor') {
                    $dasborUrl = $navigationItem->getUrl();
                } elseif ($label === 'Cabang') {
                    $cabangUrl = $navigationItem->getUrl();
                } elseif ($label === 'Anggota') {
                    $anggotaUrl = $navigationItem->getUrl();
                } elseif ($label === 'Infaq') {
                    $infaqUrl = $navigationItem->getUrl();
                } elseif ($label === 'Pinjaman') {
                    $pinjamanUrl = $navigationItem->getUrl();
                } elseif ($label === 'Cicilan') {
                    $cicilanUrl = $navigationItem->getUrl();
                } elseif ($label === 'Pengeluaran') {
                    $pengeluaranUrl = $navigationItem->getUrl();
                } elseif ($label === 'Mutasi') {
                    $pengeluaranUrl = $navigationItem->getUrl();
                } elseif ($label === 'Peran') {
                    $pengeluaranUrl = $navigationItem->getUrl();
                } 
            }
        }
        
        return [
            'dasborUrl' => $dasborUrl,
            'cabangUrl' => $cabangUrl,
            'anggotaUrl' => $anggotaUrl,
            'infaqUrl' => $infaqUrl,
            'pinjamanUrl' => $pinjamanUrl,
            'cicilanUrl' => $cicilanUrl,
            'pengeluaranUrl' => $pengeluaranUrl,
            'navigation' => $navigation,
        ];
    }
}
