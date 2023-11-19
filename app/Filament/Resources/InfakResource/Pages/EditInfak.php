<?php

namespace App\Filament\Resources\InfakResource\Pages;

use App\Filament\Resources\InfakResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInfak extends EditRecord
{
    protected static string $resource = InfakResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
