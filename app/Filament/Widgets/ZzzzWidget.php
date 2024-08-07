<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class ZzzzWidget extends Widget
{
    protected static string $view = 'filament.widgets.zzzz-widget';

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;
}
