<?php

namespace App\Filament\Resources\CabangResource\Pages;

use App\Filament\Resources\CabangResource;
use App\Services\CabangUserWorkbookService;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ListCabangs extends ListRecords
{
    protected static string $resource = CabangResource::class;

    protected function getHeaderActions(): array
    {
        $isSuperAdmin = auth()->user()->hasRole('super_admin');

        return [
            Actions\Action::make('templateCabangPengguna')
                ->label('Template XLSX')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible($isSuperAdmin)
                ->action(fn () => app(CabangUserWorkbookService::class)->downloadTemplate()),

            Actions\Action::make('exportCabangPengguna')
                ->label('Export Cabang + Pengguna')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible($isSuperAdmin)
                ->action(fn () => app(CabangUserWorkbookService::class)->downloadExport()),

            Actions\Action::make('importCabangPengguna')
                ->label('Import / Update XLSX')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->visible($isSuperAdmin)
                ->modalHeading('Import / update cabang dan pengguna')
                ->modalDescription('Satu file untuk cabang, pengguna dan kepengurusan. Seluruh baris akan dibatalkan jika ada validasi yang gagal. Saldo awal cabang yang sudah ada tidak dapat diubah melalui import.')
                ->form([
                    FileUpload::make('file')
                        ->label('File XLSX')
                        ->disk('local')
                        ->directory('bwi/imports')
                        ->visibility('private')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(10240)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $relativePath = $data['file'];
                    $path = Storage::disk('local')->path($relativePath);

                    try {
                        $result = app(CabangUserWorkbookService::class)->import($path);
                        Notification::make()
                            ->title('Import berhasil')
                            ->body(
                                "Cabang: {$result['cabang_baru']} baru, {$result['cabang_diperbarui']} diperbarui. " .
                                "Pengguna: {$result['pengguna_baru']} baru, {$result['pengguna_diperbarui']} diperbarui. " .
                                "Kepengurusan: {$result['kepengurusan_diperbarui']} cabang."
                            )
                            ->success()
                            ->send();
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Import gagal, database tidak diubah')
                            ->body(implode(' ', $exception->validator->errors()->all()))
                            ->danger()
                            ->persistent()
                            ->send();
                    } finally {
                        Storage::disk('local')->delete($relativePath);
                    }
                }),

            // Standard Filament create is preserved for manual entry.
            Actions\CreateAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Widgets\BottomFooterWidget::class,
        ];
    }
}
