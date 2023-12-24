<?php

namespace App\Filament\Resources\MutasiResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\MutasiResource;
use App\Filament\Widgets\BottomFooterWidget;
use App\Filament\Widgets\StatsOverview;
use EightyNine\ExcelImport\ExcelImportAction;

class ListMutasis extends ListRecords
{
    protected static string $resource = MutasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExcelImportAction::make()
                ->color("primary")
                ->hidden(!auth()->user()->hasRole('super_admin')),
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
