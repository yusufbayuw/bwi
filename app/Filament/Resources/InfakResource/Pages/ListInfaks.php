<?php

namespace App\Filament\Resources\InfakResource\Pages;

use Filament\Actions;
use App\Models\Cabang;
use pxlrbt\FilamentExcel\Columns\Column;
use App\Filament\Resources\InfakResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Widgets\BottomFooterWidget;
use App\Models\User;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Resources\Pages\ListRecords\Tab;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;

class ListInfaks extends ListRecords
{
    protected static string $resource = InfakResource::class;

    protected function getHeaderActions(): array
    {
        $adminAccess = auth()->user()->hasRole(config('bwi.adminAccess'));
        return [
            ExportAction::make()->exports([
                ExcelExport::make('Export')
                    ->withColumns([
                        Column::make('cabang_id')
                            ->heading('Cabang')
                            ->formatStateUsing(fn ($state) => Cabang::find($state)->nama_cabang ?? ""),
                        Column::make('nominal')
                            ->heading('Nominal'),
                        Column::make('tanggal')
                            ->heading('Tanggal'),
                        Column::make('jenis')
                            ->heading('Jenis'),
                        Column::make('user_id')
                            ->heading('Pemberi Infak')
                            ->formatStateUsing(fn ($state) => User::find($state)->name ?? ""),
                    ])
                    ->withFilename('BWI-Infak-' . now() . '-export')
                    ->withWriterType(\Maatwebsite\Excel\Excel::XLSX),
            ])->hidden(!$adminAccess),
            ExcelImportAction::make()
                ->color("primary")
                ->icon('heroicon-o-arrow-up-tray')
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
        $adminAuth = $userAuth->hasRole(config('bwi.adminAccess'));
        $cabang_id = $userAuth->cabang_id;
        return [
            null => Tab::make('Semua'),
            'Kotak Infaq' => Tab::make()->query(fn ($query) => $adminAuth ? $query->where('jenis', 'Kotak Infaq') : $query->where('cabang_id', $cabang_id)->where('jenis', 'Kotak Infaq')),
            'Anggota' => Tab::make()->query(fn ($query) => $adminAuth ? $query->where('jenis', 'Anggota') : $query->where('cabang_id', $cabang_id)->where('jenis', 'Anggota')),
            'Donatur' => Tab::make()->query(fn ($query) => $adminAuth ? $query->where('jenis', 'Donatur') : $query->where('cabang_id', $cabang_id)->where('jenis', 'Donatur')),
        ];
    }
}
