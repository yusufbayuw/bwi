<?php

namespace App\Filament\Resources\InfakResource\Pages;

use Filament\Actions;
use App\Filament\Resources\InfakResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Widgets\BottomFooterWidget;

class ListInfaks extends ListRecords
{
    protected static string $resource = InfakResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
