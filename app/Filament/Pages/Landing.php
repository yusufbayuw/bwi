<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BottomFooterWidget;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;

class Landing extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.landing';

    /* protected function getFooterWidgets(): array
    {
        return [
            BottomFooterWidget::class,
        ];
    }
 */
    public function getFooter(): ?View
    {
        return view('filament.pages.landing.custom-footer');
    }
}
