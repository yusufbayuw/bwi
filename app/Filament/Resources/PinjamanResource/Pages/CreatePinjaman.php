<?php

namespace App\Filament\Resources\PinjamanResource\Pages;

use Filament\Actions;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Support\RawJs;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Wizard\Step;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\PinjamanResource;
use Icetalker\FilamentStepper\Forms\Components\Stepper;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;

class CreatePinjaman extends CreateRecord
{
    use HasWizard;

    protected static string $resource = PinjamanResource::class;

    protected static bool $canCreateAnother = false;

    protected function getFormActions(): array
    {
        return [
            
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function form(Form $form): Form
    {
        $userAuth = auth()->user();
        $adminAccess = ['super_admin', 'admin_pusat'];
        $adminBendaharaAccess = ['super_admin', 'admin_pusat', 'bendahara_cabang'];
        $userAuthAdminAccess = $userAuth->hasRole($adminAccess);

        return $form
            ->schema([
                Wizard::make([
                    Wizard\Step::make('Kelompok')
                        ->description('buat kelompok pinjaman')
                        ->schema([
                            ($userAuthAdminAccess) ? Select::make('cabang_id')
                                ->label('Cabang')
                                ->relationship('cabangs', 'nama_cabang')
                                ->live() :
                                Hidden::make('cabang_id')->default($userAuth->cabang_id),
                            TextInput::make('nama_kelompok')
                                ->required()
                                ->maxLength(255),
                            PinjamanResource::getSelectOption()
                                ->afterStateUpdated(
                                    fn (Select $component) => $component->getContainer()->getParentComponent()->getContainer()->getComponent('dynamicTypeFields')->getChildComponentContainer()->fill()
                                ),
                        ]),
                    Wizard\Step::make('Anggota')
                        ->description('daftarkan anggota kelompok')
                        ->schema(
                            fn (Get $get): array => match ($get('jumlah_anggota')) {
                                '5' => [
                                    PinjamanResource::getItemsRepeater()
                                        ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                            $totalBmpa = 9999999999;
                                            foreach ($state as $key => $item) {
                                                $bmpa = str_replace(",", ".", preg_replace('/[^0-9,]/', '', $item['bmpa']));
                                                if (($totalBmpa > (float)$bmpa) && $bmpa) {
                                                    $totalBmpa = (float)$bmpa;
                                                }
                                            };
                                            $set('nominal_bmpa_max', number_format($totalBmpa, 2, ",", "."));
                                            $set('nominal_pinjaman', number_format($totalBmpa, 2, ",", "."));
                                            $set('cicilan_kelompok', number_format($totalBmpa / 5, 2, ",", "."));
                                            $set('total_pinjaman', number_format($totalBmpa * 5, 2, ",", "."));
                                            $set('status', 'Menunggu Verifikasi');
                                        })
                                        ->defaultItems(5)
                                        ->maxItems(5)
                                        ->minItems(5),
                                ],
                                '7' => [
                                    PinjamanResource::getItemsRepeater()
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            $totalBmpa = 9999999999;
                                            foreach ($state as $key => $item) {
                                                $bmpa = str_replace(",", ".", preg_replace('/[^0-9,]/', '', $item['bmpa']));
                                                if (($totalBmpa > (float)$bmpa) && $bmpa) {
                                                    $totalBmpa = (float)$bmpa;
                                                }
                                            };
                                            $set('nominal_bmpa_max', number_format($totalBmpa, 2, ",", "."));
                                            $set('nominal_pinjaman', number_format($totalBmpa, 2, ",", "."));
                                            $set('cicilan_kelompok', number_format($totalBmpa / 5, 2, ",", "."));
                                            $set('total_pinjaman', number_format($totalBmpa * 7, 2, ",", "."));
                                            $set('status', 'Menunggu Verifikasi');
                                        })
                                        ->defaultItems(7)
                                        ->maxItems(7)
                                        ->minItems(7),
                                ],
                                '9' => [
                                    PinjamanResource::getItemsRepeater()
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            $totalBmpa = 9999999999;
                                            foreach ($state as $key => $item) {
                                                $bmpa = str_replace(",", ".", preg_replace('/[^0-9,]/', '', $item['bmpa']));
                                                if (($totalBmpa > (float)$bmpa) && $bmpa) {
                                                    $totalBmpa = (float)$bmpa;
                                                }
                                            };
                                            $set('nominal_bmpa_max', number_format($totalBmpa, 2, ",", "."));
                                            $set('nominal_pinjaman', number_format($totalBmpa, 2, ",", "."));
                                            $set('cicilan_kelompok', number_format($totalBmpa / 5, 2, ",", "."));
                                            $set('total_pinjaman', number_format($totalBmpa * 9, 2, ",", "."));
                                            $set('status', 'Menunggu Verifikasi');
                                        })
                                        ->defaultItems(9)
                                        ->maxItems(9)
                                        ->minItems(9),
                                ],
                                '11' => [
                                    PinjamanResource::getItemsRepeater()
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            $totalBmpa = 9999999999;
                                            foreach ($state as $key => $item) {
                                                $bmpa = str_replace(",", ".", preg_replace('/[^0-9,]/', '', $item['bmpa']));
                                                if (($totalBmpa > (float)$bmpa) && $bmpa) {
                                                    $totalBmpa = (float)$bmpa;
                                                }
                                            };
                                            $set('nominal_bmpa_max', number_format($totalBmpa, 2, ",", "."));
                                            $set('nominal_pinjaman', number_format($totalBmpa, 2, ",", "."));
                                            $set('cicilan_kelompok', number_format($totalBmpa / 5, 2, ",", "."));
                                            $set('total_pinjaman', number_format($totalBmpa * 11, 2, ",", "."));
                                            $set('status', 'Menunggu Verifikasi');
                                        })
                                        ->defaultItems(11)
                                        ->maxItems(11)
                                        ->minItems(11),
                                ],
                                default => [],
                            }
                        )->key('dynamicTypeFields'),
                    Wizard\Step::make('Cicilan')
                        ->description('atur cicilan per minggu')
                        ->schema([
                            TextInput::make('nominal_bmpa_max')
                                ->readOnly()
                                ->mask(RawJs::make(<<<'JS'
                                    $money($input, ',', '.', 2)
                                JS))
                                ->label('Maksimum Pinjaman Per Anggota')
                                ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                                ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
                            TextInput::make('nominal_pinjaman')
                                ->label('Nominal Pinjaman per Anggota')
                                ->mask(RawJs::make(<<<'JS'
                                    $money($input, ',', '.', 2)
                                JS))
                                //->hint("Nominal pinjaman per anggota tidak boleh melebihi maksimum pinjaman per anggota")
                                ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                                ->formatStateUsing(fn ($state) => str_replace(".", ",", $state))
                                ->live(debounce: 2000)
                                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                    $number_total = (float)(str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state))) / (float)$get('lama_cicilan');
                                    $set('cicilan_kelompok', number_format($number_total, 2, ',', '.'));
                                    $set('total_pinjaman', number_format((float)(str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state))) * (float)$get('jumlah_anggota'), 2, ',', '.'));
                                }),
                            Stepper::make('lama_cicilan')
                                ->label('Lama Cicilan (minggu)')
                                ->minValue(5)
                                ->maxValue(50)
                                ->step(5)
                                ->default(5)
                                ->live(debounce: 1000)
                                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                    $number_total = (float)(str_replace(",", ".", preg_replace('/[^0-9,]/', '', $get('nominal_pinjaman')))) / (float)$state;
                                    $set('cicilan_kelompok', number_format($number_total, 2, ',', '.'));
                                }),
                            TextInput::make('total_pinjaman')
                                ->mask(RawJs::make(<<<'JS'
                                $money($input, ',', '.', 2)
                            JS))
                                ->label("Total Pinjaman Kelompok")
                                ->readOnly()
                                ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                                ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
                            TextInput::make('cicilan_kelompok')
                                ->mask(RawJs::make(<<<'JS'
                                    $money($input, ',', '.', 2)
                                JS))
                                ->label('Cicilan Kelompok per Minggu')
                                ->readOnly()
                                ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                                ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
                            Hidden::make('status')
                                /* ->options([
                                    'Pembuatan Kelompok' => 'Pembuatan Kelompok',
                                    'Menunggu Verifikasi' => 'Menunggu Verifikasi',
                                    'Cicilan Berjalan' => 'Cicilan Berjalan',
                                    'Sudah Lunas' => 'Sudah Lunas',
                                ])
                                ->default('Pembuatan Kelompok') */,
                            Toggle::make('acc_pinjaman')
                                ->hidden(!($userAuth->hasRole($adminBendaharaAccess)))
                                ->afterStateUpdated(fn (Set $set) => $set('status', 'Cicilan Berjalan'))
                                ->live(),
                            DatePicker::make('tanggal_cicilan_pertama')
                                ->date('d/m/Y')
                                ->hidden(!($userAuth->hasRole($adminBendaharaAccess)))
                                ->required(!($userAuth->hasRole($adminBendaharaAccess))),
                            FileUpload::make('berkas'),
                        ])
                ])->submitAction(new HtmlString(Blade::render(<<<BLADE
                <x-filament::button
                    type="submit"
                    size="sm"
                >
                    Simpan
                </x-filament::button>
            BLADE)))
                    ->columnSpanFull(),
            ])
        ;
    }
    
}
