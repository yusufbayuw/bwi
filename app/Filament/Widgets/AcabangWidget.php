<?php

namespace App\Filament\Widgets;

use App\Models\Cabang;
use Filament\Widgets\Widget;

class AcabangWidget extends Widget
{
    protected static string $view = 'filament.widgets.acabang-widget';

    protected static bool $isLazy = false;

    protected function getViewData(): array
    {
        $cabang = Cabang::find(auth()->user()->cabang_id);

        return [ 'cabang' => $cabang, ];
    }
}
