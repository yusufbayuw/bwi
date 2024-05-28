<?php

namespace App\Filament\Resources\PinjamanResource\Pages;

use Filament\Actions;
use App\Models\Cabang;
use pxlrbt\FilamentExcel\Columns\Column;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\PinjamanResource;
use App\Filament\Widgets\BottomFooterWidget;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Resources\Pages\ListRecords\Tab;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use App\Filament\Widgets\StatsOverviewPinjaman;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;

class ListPinjamen extends ListRecords
{
    protected static string $resource = PinjamanResource::class;

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
                        Column::make('nama_kelompok')
                            ->heading('Nama Kelompok'),
                        Column::make('status')
                            ->heading('Status'),
                        Column::make('jumlah_anggota')
                            ->heading('Jumlah Anggota'),
                        Column::make('nominal_pinjaman')
                            ->heading('Pinjaman Per Anggota'),
                        Column::make('total_pinjaman')
                            ->heading('Total Pinjaman'),
                        Column::make('total_pinjaman')
                            ->heading('Total Pinjaman'),
                        Column::make('lama_cicilan')
                            ->heading('Lama Cicilan (minggu)'),
                        Column::make('cicilan_kelompok')
                            ->heading('Cicilan Mingguan'),
                    ])
                    ->withFilename('BWI-Kelompok Pinjaman-' . now() . '-export')
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

    protected function getHeaderWidgets(): array
    {
        return [StatsOverviewPinjaman::class];
    }

    public function getTabs(): array
    {
        $userAuth = auth()->user();
        $adminAuth = $userAuth->hasRole(config('bwi.adminAccess'));
        $cabang_id = $userAuth->cabang_id;
        return [
            'Semua' => Tab::make()->query(fn ($query) => $adminAuth ? $query->orderBy('id', 'DESC') : $query->where('cabang_id', $cabang_id)->orderBy('id', 'DESC')),
            'Verifikasi' => Tab::make()->query(fn ($query) => $adminAuth ? $query->where('status', 'Menunggu Verifikasi')->orderBy('id', 'DESC') : $query->where('cabang_id', $cabang_id)->where('status', 'Menunggu Verifikasi')->orderBy('id', 'DESC')),
            'Cicilan' => Tab::make()->query(fn ($query) => $adminAuth ? $query->where('status', 'Cicilan Berjalan')->orderBy('id', 'DESC') : $query->where('cabang_id', $cabang_id)->where('status', 'Cicilan Berjalan')->orderBy('id', 'DESC')),
            'Lunas' => Tab::make()->query(fn ($query) => $adminAuth ? $query->where('status', 'Lunas')->orderBy('id', 'DESC') : $query->where('cabang_id', $cabang_id)->where('status', 'Lunas')->orderBy('id', 'DESC')),
            'Ditolak' => Tab::make()->query(fn ($query) => $adminAuth ? $query->where('acc_pinjaman', -1)->orderBy('id', 'DESC') : $query->where('cabang_id', $cabang_id)->where('acc_pinjaman', -1)->orderBy('id', 'DESC')),
        ];
    }
}
