<?php

namespace App\Filament\Resources\InfakResource\Pages;

use Filament\Actions;
use App\Filament\Resources\InfakResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Widgets\BottomFooterWidget;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Resources\Pages\ListRecords\Tab;

class ListInfaks extends ListRecords
{
    protected static string $resource = InfakResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExcelImportAction::make()
                ->color("primary")
                ->hidden(!auth()->user()->hasRole('super_admin')),
            Actions\CreateAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            BottomFooterWidget::class,
        ];
    }

    public function getTabs(): array
    {
        $userAuth = auth()->user();
        $adminAuth = $userAuth->hasRole(['super_admin', 'admin_pusat']);
        $cabang_id = $userAuth->cabang_id;
        return [
            null => Tab::make('Semua'),
            'Kotak Infaq' => Tab::make()->query(fn ($query) => $adminAuth ? $query->where('jenis', 'Kotak Infaq') : $query->where('cabang_id', $cabang_id)->where('jenis', 'Kotak Infaq') ),
            'Anggota' => Tab::make()->query(fn ($query) => $adminAuth ? $query->where('jenis', 'Anggota') : $query->where('cabang_id', $cabang_id)->where('jenis', 'Anggota')),
            'Donatur' => Tab::make()->query(fn ($query) => $adminAuth ? $query->where('jenis', 'Donatur') : $query->where('cabang_id', $cabang_id)->where('jenis', 'Donatur')),
        ];
    }
}
