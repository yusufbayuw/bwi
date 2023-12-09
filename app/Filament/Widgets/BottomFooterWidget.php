<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

class BottomFooterWidget extends Widget
{
    use HasWidgetShield;
    protected static string $view = 'filament.widgets.bottom-footer-widget';
}
