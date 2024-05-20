<?php

namespace App\Filament\Resources\UserResource\Pages;

use Filament\Actions;
use App\Imports\UpdateUserImport;
use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Widgets\BottomFooterWidget;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Resources\Pages\ListRecords\Tab;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $userAuthSpAd = auth()->user()->hasRole('super_admin');
        return [
            ExcelImportAction::make('update')
                ->label('Update')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->use(UpdateUserImport::class)
                ->hidden(!$userAuthSpAd),
            ExcelImportAction::make('import')
                ->color("primary")
                ->label('Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->hidden(!$userAuthSpAd),
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
        $adminAuth = $userAuth->hasRole(config('bwi.adminAccess'));
        $cabang_id = $userAuth->cabang_id;
        return [
            null => Tab::make('Semua'),
            'BMPA' => Tab::make()->query(fn ($query) => $adminAuth ? $query->where('bmpa_gain_counter', '>' , 0) : $query->where('cabang_id', $cabang_id)->where('bmpa_gain_counter', '>' , 0)),
            'Anggota' => Tab::make()->query(fn ($query) => $adminAuth ? $query->where('jenis_anggota', 'Anggota') : $query->where('cabang_id', $cabang_id)->where('jenis_anggota', 'Anggota')),
            'Donatur' => Tab::make()->query(fn ($query) => $adminAuth ? $query->where('jenis_anggota', 'Donatur') : $query->where('cabang_id', $cabang_id)->where('jenis_anggota', 'Donatur')),

        ];
    }
}
