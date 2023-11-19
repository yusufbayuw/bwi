<?php

namespace App\Filament\Resources\CicilanResource\Pages;

use App\Filament\Resources\CicilanResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCicilan extends ViewRecord
{
    protected static string $resource = CicilanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
