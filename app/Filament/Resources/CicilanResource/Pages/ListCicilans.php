<?php

namespace App\Filament\Resources\CicilanResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\CicilanResource;
use App\Filament\Widgets\BottomFooterWidget;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Resources\Components\Tab;
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
        return [
            'lunas' => Tab::make('Belum Dibayar')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status_cicilan', false)->orderBy('tanggal_cicilan', 'asc')->orderBy('tagihan_ke', 'asc')),
            'tagihan' => Tab::make('Lunas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status_cicilan', true)->orderBy('updated_at', 'desc')),
        ];
    }
}
