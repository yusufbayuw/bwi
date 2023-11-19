<?php

namespace App\Filament\Resources\CicilanResource\Pages;

use App\Filament\Resources\CicilanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCicilans extends ListRecords
{
    protected static string $resource = CicilanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
