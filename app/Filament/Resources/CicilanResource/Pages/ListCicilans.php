<?php

namespace App\Filament\Resources\CicilanResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\CicilanResource;
use App\Filament\Widgets\BottomFooterWidget;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Resources\Pages\ListRecords\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCicilans extends ListRecords
{
    protected static string $resource = CicilanResource::class;

    protected static bool $isLazy = false;

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
        $adminAuth = $userAuth->hasRole(config('bwi.adminAccess'));
        $cabang_id = $userAuth->cabang_id;
        return [
            'Belum Dibayar' => Tab::make()->query(fn ($query) => $adminAuth ? $query->where('status_cicilan', false)->orderBy('tanggal_cicilan', 'asc')->orderBy('tagihan_ke', 'asc') : $query->where('cabang_id', $cabang_id)->where('status_cicilan', false)->orderBy('tanggal_cicilan', 'asc')->orderBy('tagihan_ke', 'asc') ),
            'Lunas' => Tab::make()->query(fn ($query) => $adminAuth ? $query->where('status_cicilan', true)->orderBy('updated_at', 'desc') : $query->where('cabang_id', $cabang_id)->where('status_cicilan', true)->orderBy('updated_at', 'desc')),
        ];
    }
}
