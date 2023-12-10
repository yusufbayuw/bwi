<?php

namespace App\Filament\Resources\PinjamanResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\PinjamanResource;
use App\Filament\Widgets\BottomFooterWidget;
use EightyNine\ExcelImport\ExcelImportAction;

class ListPinjamen extends ListRecords
{
    protected static string $resource = PinjamanResource::class;

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
}
