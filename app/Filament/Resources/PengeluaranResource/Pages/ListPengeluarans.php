<?php

namespace App\Filament\Resources\PengeluaranResource\Pages;

use Filament\Actions;
use App\Models\Cabang;
use pxlrbt\FilamentExcel\Columns\Column;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Widgets\BottomFooterWidget;
use EightyNine\ExcelImport\ExcelImportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use App\Filament\Resources\PengeluaranResource;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;

class ListPengeluarans extends ListRecords
{
    protected static string $resource = PengeluaranResource::class;

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
                        Column::make('jenis')
                            ->heading('Jenis'),
                        Column::make('tanggal')
                            ->heading('Tanggal'),
                        Column::make('nominal')
                            ->heading('Nominal'),
                        Column::make('keterangan')
                            ->heading('Keterangan'),
                    ])
                    ->withFilename('BWI-Pengeluaran-' . now() . '-export')
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
}
