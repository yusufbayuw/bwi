<?php

namespace App\Filament\Resources\InfakResource\Pages;

use App\Filament\Resources\InfakResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInfak extends ViewRecord
{
    protected static string $resource = InfakResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
