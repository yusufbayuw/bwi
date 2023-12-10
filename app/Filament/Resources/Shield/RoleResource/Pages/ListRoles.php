<?php

namespace App\Filament\Resources\Shield\RoleResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use EightyNine\ExcelImport\ExcelImportAction;
use App\Filament\Resources\Shield\RoleResource;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getActions(): array
    {
        return [
            ExcelImportAction::make()
                ->color("primary")
                ->hidden(!auth()->user()->hasRole('super_admin')),
            Actions\CreateAction::make(),
        ];
    }
}
