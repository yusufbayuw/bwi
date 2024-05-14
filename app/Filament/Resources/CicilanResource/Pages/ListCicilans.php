<?php

namespace App\Filament\Resources\CicilanResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\CicilanResource;
use App\Filament\Widgets\BottomFooterWidget;
use EightyNine\ExcelImport\ExcelImportAction;

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
}
