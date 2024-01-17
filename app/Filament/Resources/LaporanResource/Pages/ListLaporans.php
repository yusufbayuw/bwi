<?php

namespace App\Filament\Resources\LaporanResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\LaporanResource;
use App\Filament\Widgets\BottomFooterWidget;

class ListLaporans extends ListRecords
{
    protected static string $resource = LaporanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //Actions\CreateAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            BottomFooterWidget::class,
        ];
    }
}
