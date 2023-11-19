<?php

namespace App\Filament\Resources\CicilanResource\Pages;

use App\Filament\Resources\CicilanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCicilan extends EditRecord
{
    protected static string $resource = CicilanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
