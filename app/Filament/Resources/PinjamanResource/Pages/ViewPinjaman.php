<?php

namespace App\Filament\Resources\PinjamanResource\Pages;

use App\Filament\Resources\PinjamanResource;
use App\Models\Pinjaman;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPinjaman extends ViewRecord
{
    protected static string $resource = PinjamanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->hidden(fn (Pinjaman $pinjaman) => $pinjaman->acc_pinjaman),
        ];
    }
}
