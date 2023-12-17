<?php

namespace App\Filament\Resources\UserResource\Pages;

use Filament\Actions;
use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Widgets\BottomFooterWidget;
use App\Imports\UpdateUserImport;
use EightyNine\ExcelImport\ExcelImportAction;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $userAuthSpAd = auth()->user()->hasRole('super_admin');
        return [
            ExcelImportAction::make()
                ->label('Update')
                ->color('success')
                ->hidden(!$userAuthSpAd)
                ->icon('heroicon-o-arrow-path')
                ->use(UpdateUserImport::class),
            ExcelImportAction::make()
                ->color("primary")
                ->icon('heroicon-o-arrow-up-tray')
                ->hidden(!$userAuthSpAd),
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
