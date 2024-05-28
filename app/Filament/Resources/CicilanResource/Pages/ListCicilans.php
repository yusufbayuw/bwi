<?php

namespace App\Filament\Resources\CicilanResource\Pages;

use Filament\Actions;
use App\Models\Cabang;
use App\Models\Pinjaman;
use pxlrbt\FilamentExcel\Columns\Column;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\CicilanResource;
use App\Filament\Widgets\BottomFooterWidget;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Resources\Pages\ListRecords\Tab;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;

class ListCicilans extends ListRecords
{
    protected static string $resource = CicilanResource::class;

    protected static bool $isLazy = false;

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
                        Column::make('pinjaman_id')
                            ->heading('Kelompok')
                            ->formatStateUsing(fn ($state) => Pinjaman::find($state)->nama_kelompok ?? ""),
                        Column::make('tanggal_cicilan')
                            ->heading('Tanggal Tagihan'),
                        Column::make('tanggal')
                            ->heading('Tanggal'),
                        Column::make('jenis')
                            ->heading('Jenis'),
                        
                    ])
                    ->withFilename('Infak-' . date('d-m-Y') . '-export')
                    ->withWriterType(\Maatwebsite\Excel\Excel::XLSX),
            ])->hidden(!$adminAccess),
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
        $adminAuth = $userAuth->hasRole(config('bwi.adminAccess'));
        $cabang_id = $userAuth->cabang_id;
        return [
            'Belum Dibayar' => Tab::make()->query(fn ($query) => $adminAuth ? $query->where('status_cicilan', false)->orderBy('tanggal_cicilan', 'asc')->orderBy('tagihan_ke', 'asc') : $query->where('cabang_id', $cabang_id)->where('status_cicilan', false)->orderBy('tanggal_cicilan', 'asc')->orderBy('tagihan_ke', 'asc') ),
            'Lunas' => Tab::make()->query(fn ($query) => $adminAuth ? $query->where('status_cicilan', true)->orderBy('updated_at', 'desc') : $query->where('cabang_id', $cabang_id)->where('status_cicilan', true)->orderBy('updated_at', 'desc')),
        ];
    }
}
