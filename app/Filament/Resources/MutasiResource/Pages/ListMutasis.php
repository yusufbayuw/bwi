<?php

namespace App\Filament\Resources\MutasiResource\Pages;

use Filament\Actions;
use App\Filament\Widgets\StatsOverview;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\MutasiResource;
use App\Filament\Widgets\BottomFooterWidget;
use App\Models\Cabang;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;

class ListMutasis extends ListRecords
{
    protected static string $resource = MutasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()->exports([
                ExcelExport::make('Export')
                    ->withColumns([
                        Column::make('cabang_id')
                            ->heading('Cabang')
                            ->formatStateUsing(fn ($state) => Cabang::find($state)->nama_cabang ?? ""),
                        Column::make('created_at')
                            ->heading('Tanggal'),
                        Column::make('keterangan')
                            ->heading('Keterangan'),
                        Column::make('debet')
                            ->heading('Debet'),
                        Column::make('kredit')
                            ->heading('Kredit'),
                        Column::make('saldo_umum')
                            ->heading('Saldo Umum'),
                        Column::make('saldo_keamilan')
                            ->heading('Saldo Keamilan'),
                        Column::make('saldo_csr')
                            ->heading('Saldo CSR'),
                        Column::make('saldo_cadangan')
                            ->heading('Saldo Cadangan')
                    ])
                    ->withFilename('Mutasi-' . date('d-m-Y') . '-export')
                    ->withWriterType(\Maatwebsite\Excel\Excel::XLSX),
            ])
            /* ExcelImportAction::make()
                ->color("primary")
                ->hidden(!auth()->user()->hasRole('super_admin')), */
            //Actions\CreateAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            BottomFooterWidget::class,
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [StatsOverview::class];
    }
}
