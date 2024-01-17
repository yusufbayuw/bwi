<?php

namespace App\Filament\Resources\BerkasResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\BerkasResource;
use App\Filament\Widgets\BottomFooterWidget;

class ListBerkas extends ListRecords
{
    protected static string $resource = BerkasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->hidden(!(auth()->user()->hasRoles(['super_admin', 'admin_pusat']))),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            BottomFooterWidget::class,
        ];
    }
}
