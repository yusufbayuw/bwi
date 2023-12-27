<?php

namespace App\Filament\Resources\InfakResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Resources\InfakResource;
use App\Filament\Widgets\BottomFooterWidget;

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
