<?php

namespace App\Filament\Resources\InfakResource\Pages;

use Filament\Actions;
use App\Filament\Resources\InfakResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Widgets\BottomFooterWidget;
use EightyNine\ExcelImport\ExcelImportAction;

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

    /* protected function getFooterWidgets(): array
    {
        return [
            BottomFooterWidget::class,
        ];
    } */
}
