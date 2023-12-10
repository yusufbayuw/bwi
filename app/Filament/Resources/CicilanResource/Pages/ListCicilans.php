<?php

namespace App\Filament\Resources\CicilanResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\CicilanResource;
use App\Filament\Widgets\BottomFooterWidget;

class ListCicilans extends ListRecords
{
    protected static string $resource = CicilanResource::class;

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
