<?php

namespace App\Filament\Resources\PinjamanResource\Pages;

use Closure;
use App\Models\Mutasi;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Support\RawJs;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\PinjamanResource;
use App\Models\User;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;

class CreatePinjaman extends CreateRecord
{
    use HasWizard;

    protected static string $resource = PinjamanResource::class;

    protected static bool $canCreateAnother = false;

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function form(Form $form): Form
    {
        $userAuth = auth()->user();
        $adminAccess = config('bwi.adminAccess');
        $adminAccessApprove = config('bwi.adminAccessApprove');
        $userAuthAdminAccess = $userAuth->hasRole($adminAccess);

        $adminAccessCreatePinjaman = config('bwi.adminAccessCreatePinjaman');
        $userAuthAdminAccessCreatePinjaman = $userAuth->hasRole($adminAccessCreatePinjaman);

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
                                ->disabled(!$userAuthAdminAccessCreatePinjaman)
                                ->maxLength(255),
                            ToggleButtons::make('dengan_pengurus')
                                ->label('Apakah ada Pengurus dalam kelompok?')
                                //->hidden(!($userAuth->hasRole($adminAccessApprove)))
                                ->options([
                                    '1' => 'ADA Pengurus',
                                    '0' => 'TIDAK ada Pengurus',
                                ])
                                ->icons([
                                    '1' => 'heroicon-o-check',
                                    '0' => 'heroicon-o-x-mark',
                                ])
                                ->colors([
                                    '1' => 'success',
                                    '0' => 'success',
                                ])
                                ->live()
                                ->afterStateUpdated(
                                    fn (Set $set) => $set('jumlah_anggota', null)
                                )
                                ->inline()
                                ->default(null),
                            PinjamanResource::getSelectOption()
                                ->hidden(fn (Get $get) => ($get('dengan_pengurus') == null) ? true : false)
                                ->required()
                                ->afterStateUpdated(
                                    fn (Select $component) => $component->getContainer()->getParentComponent()->getContainer()->getComponent('dynamicTypeFields')->getChildComponentContainer()->fill()
                                ),
                        ]),
                    Wizard\Step::make('Anggota')
                        ->description('daftarkan anggota kelompok')
                        ->schema(
                            function (Get $get): array {
                                $roleCabang = [
                                    config('bwi.ketua_pengurus'),
                                    config('bwi.bendahara'),
                                    config('bwi.sekretaris'),
                                    config('bwi.ketua_pembina'),
                                    config('bwi.anggota_pembina'),
                                    config('bwi.ketua_pengawas'),
                                    config('bwi.anggota_pengawas')
                                ];
                                return match ($get('jumlah_anggota')) {
                                    '5' => ($get('dengan_pengurus')) ? [
                                        Select::make('nama_pengurus')->label('Nama Pengurus:')
                                            ->options(User::where('cabang_id', auth()->user()->cabang_id)
                                                ->where('is_kelompok', false)->where('jenis_anggota', 'Anggota')
                                                ->whereHas('roles', function ($query) use ($roleCabang) {
                                                    $query->whereIn('name', $roleCabang);
                                                })
                                                ->pluck('name', 'id'))
                                            ->searchable()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('bmpa_pengurus', number_format(User::where('id', $state)->first()->bmpa  ?? null, 2, ",", "."));
                                            })
                                            ->live()
                                            ->columnSpan(1)
                                            ->required(),
                                        TextInput::make('bmpa_pengurus')
                                            ->label('BMPA Pengurus:')
                                            ->columnSpan(1)
                                            ->mask(RawJs::make(<<<'JS'
                                        $money($input, ',', '.', 0)
                                    JS))
                                            ->readOnly()
                                            ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                                            ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
                                        PinjamanResource::getItemsRepeaterCreate()
                                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                                $totalBmpa = 9999999999;
                                                $bmpa_pengurus = $get('bmpa_pengurus') ?? null;
                                                if ($bmpa_pengurus) {
                                                    $bmpa_pengurus = str_replace(",", ".", preg_replace('/[^0-9,]/', '', $bmpa_pengurus));
                                                    if ($totalBmpa > (float)$bmpa_pengurus) {
                                                        $totalBmpa = (float)$bmpa_pengurus;
                                                    }
                                                }
                                                foreach ($state as $key => $item) {
                                                    $bmpa = str_replace(",", ".", preg_replace('/[^0-9,]/', '', $item['bmpa']));
                                                    if (($totalBmpa > (float)$bmpa) && $bmpa) {
                                                        $totalBmpa = (float)$bmpa;
                                                    }
                                                };
                                                $set('nominal_bmpa_max', number_format($totalBmpa, 2, ",", "."));
                                                $set('nominal_pinjaman', number_format($totalBmpa, 2, ",", "."));
                                                $set('cicilan_kelompok', number_format($totalBmpa / 5 * 5, 2, ",", "."));
                                                $set('total_pinjaman', number_format($totalBmpa * 5, 2, ",", "."));
                                                $set('status', 'Menunggu Verifikasi');
                                            })
                                            ->defaultItems(4)
                                            ->maxItems(4)
                                            ->minItems(4),
                                    ] : [
                                        PinjamanResource::getItemsRepeaterCreate()
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
                                                $set('cicilan_kelompok', number_format($totalBmpa / 5 * 5, 2, ",", "."));
                                                $set('total_pinjaman', number_format($totalBmpa * 5, 2, ",", "."));
                                                $set('status', 'Menunggu Verifikasi');
                                            })
                                            ->defaultItems(5)
                                            ->maxItems(5)
                                            ->minItems(5),
                                    ],
                                    '7' => ($get('dengan_pengurus')) ? [
                                        Select::make('nama_pengurus')->label('Nama Pengurus:')
                                            ->options(User::where('cabang_id', auth()->user()->cabang_id)
                                                ->where('is_kelompok', false)->where('jenis_anggota', 'Anggota')
                                                ->whereHas('roles', function ($query) use ($roleCabang) {
                                                    $query->whereIn('name', $roleCabang);
                                                })
                                                ->pluck('name', 'id'))
                                            ->searchable()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('bmpa_pengurus', number_format(User::where('id', $state)->first()->bmpa  ?? null, 2, ",", "."));
                                            })
                                            ->live()
                                            ->columnSpan(1)
                                            ->required(),
                                        TextInput::make('bmpa_pengurus')
                                            ->label('BMPA Pengurus:')
                                            ->columnSpan(1)
                                            ->mask(RawJs::make(<<<'JS'
                                        $money($input, ',', '.', 0)
                                    JS))
                                            ->readOnly()
                                            ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                                            ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
                                        PinjamanResource::getItemsRepeaterCreate()
                                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                                $totalBmpa = 9999999999;
                                                $bmpa_pengurus = $get('bmpa_pengurus') ?? null;
                                                if ($bmpa_pengurus) {
                                                    $bmpa_pengurus = str_replace(",", ".", preg_replace('/[^0-9,]/', '', $bmpa_pengurus));
                                                    if ($totalBmpa > (float)$bmpa_pengurus) {
                                                        $totalBmpa = (float)$bmpa_pengurus;
                                                    }
                                                }
                                                foreach ($state as $key => $item) {
                                                    $bmpa = str_replace(",", ".", preg_replace('/[^0-9,]/', '', $item['bmpa']));
                                                    if (($totalBmpa > (float)$bmpa) && $bmpa) {
                                                        $totalBmpa = (float)$bmpa;
                                                    }
                                                };
                                                $set('nominal_bmpa_max', number_format($totalBmpa, 2, ",", "."));
                                                $set('nominal_pinjaman', number_format($totalBmpa, 2, ",", "."));
                                                $set('cicilan_kelompok', number_format($totalBmpa / 5 * 7, 2, ",", "."));
                                                $set('total_pinjaman', number_format($totalBmpa * 7, 2, ",", "."));
                                                $set('status', 'Menunggu Verifikasi');
                                            })
                                            ->defaultItems(6)
                                            ->maxItems(6)
                                            ->minItems(6),
                                    ] : [
                                        PinjamanResource::getItemsRepeaterCreate()
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
                                                $set('cicilan_kelompok', number_format($totalBmpa / 5 * 7, 2, ",", "."));
                                                $set('total_pinjaman', number_format($totalBmpa * 7, 2, ",", "."));
                                                $set('status', 'Menunggu Verifikasi');
                                            })
                                            ->defaultItems(7)
                                            ->maxItems(7)
                                            ->minItems(7),
                                    ],
                                    '9' => ($get('dengan_pengurus')) ? [
                                        Select::make('nama_pengurus')->label('Nama Pengurus:')
                                            ->options(User::where('cabang_id', auth()->user()->cabang_id)
                                                ->where('is_kelompok', false)->where('jenis_anggota', 'Anggota')
                                                ->whereHas('roles', function ($query) use ($roleCabang) {
                                                    $query->whereIn('name', $roleCabang);
                                                })
                                                ->pluck('name', 'id'))
                                            ->searchable()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('bmpa_pengurus', number_format(User::where('id', $state)->first()->bmpa  ?? null, 2, ",", "."));
                                            })
                                            ->live()
                                            ->columnSpan(1)
                                            ->required(),
                                        TextInput::make('bmpa_pengurus')
                                            ->label('BMPA Pengurus:')
                                            ->columnSpan(1)
                                            ->mask(RawJs::make(<<<'JS'
                                        $money($input, ',', '.', 0)
                                    JS))
                                            ->readOnly()
                                            ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                                            ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
                                        PinjamanResource::getItemsRepeaterCreate()
                                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                                $totalBmpa = 9999999999;
                                                $bmpa_pengurus = $get('bmpa_pengurus') ?? null;
                                                if ($bmpa_pengurus) {
                                                    $bmpa_pengurus = str_replace(",", ".", preg_replace('/[^0-9,]/', '', $bmpa_pengurus));
                                                    if ($totalBmpa > (float)$bmpa_pengurus) {
                                                        $totalBmpa = (float)$bmpa_pengurus;
                                                    }
                                                }
                                                foreach ($state as $key => $item) {
                                                    $bmpa = str_replace(",", ".", preg_replace('/[^0-9,]/', '', $item['bmpa']));
                                                    if (($totalBmpa > (float)$bmpa) && $bmpa) {
                                                        $totalBmpa = (float)$bmpa;
                                                    }
                                                };
                                                $set('nominal_bmpa_max', number_format($totalBmpa, 2, ",", "."));
                                                $set('nominal_pinjaman', number_format($totalBmpa, 2, ",", "."));
                                                $set('cicilan_kelompok', number_format($totalBmpa / 5 * 9, 2, ",", "."));
                                                $set('total_pinjaman', number_format($totalBmpa * 9, 2, ",", "."));
                                                $set('status', 'Menunggu Verifikasi');
                                            })
                                            ->defaultItems(8)
                                            ->maxItems(8)
                                            ->minItems(8),
                                    ] : [
                                        PinjamanResource::getItemsRepeaterCreate()
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
                                                $set('cicilan_kelompok', number_format($totalBmpa / 5 * 9, 2, ",", "."));
                                                $set('total_pinjaman', number_format($totalBmpa * 9, 2, ",", "."));
                                                $set('status', 'Menunggu Verifikasi');
                                            })
                                            ->defaultItems(9)
                                            ->maxItems(9)
                                            ->minItems(9),
                                    ],
                                    '11' => ($get('dengan_pengurus')) ? [
                                        Select::make('nama_pengurus')->label('Nama Pengurus:')
                                            ->options(User::where('cabang_id', auth()->user()->cabang_id)
                                                ->where('is_kelompok', false)->where('jenis_anggota', 'Anggota')
                                                ->whereHas('roles', function ($query) use ($roleCabang) {
                                                    $query->whereIn('name', $roleCabang);
                                                })
                                                ->pluck('name', 'id'))
                                            ->searchable()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('bmpa_pengurus', number_format(User::where('id', $state)->first()->bmpa  ?? null, 2, ",", "."));
                                            })
                                            ->live()
                                            ->columnSpan(1)
                                            ->required(),
                                        TextInput::make('bmpa_pengurus')
                                            ->label('BMPA Pengurus:')
                                            ->columnSpan(1)
                                            ->mask(RawJs::make(<<<'JS'
                                        $money($input, ',', '.', 0)
                                    JS))
                                            ->readOnly()
                                            ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                                            ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
                                        PinjamanResource::getItemsRepeaterCreate()
                                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                                $totalBmpa = 9999999999;
                                                $bmpa_pengurus = $get('bmpa_pengurus') ?? null;
                                                if ($bmpa_pengurus) {
                                                    $bmpa_pengurus = str_replace(",", ".", preg_replace('/[^0-9,]/', '', $bmpa_pengurus));
                                                    if ($totalBmpa > (float)$bmpa_pengurus) {
                                                        $totalBmpa = (float)$bmpa_pengurus;
                                                    }
                                                }
                                                foreach ($state as $key => $item) {
                                                    $bmpa = str_replace(",", ".", preg_replace('/[^0-9,]/', '', $item['bmpa']));
                                                    if (($totalBmpa > (float)$bmpa) && $bmpa) {
                                                        $totalBmpa = (float)$bmpa;
                                                    }
                                                };
                                                $set('nominal_bmpa_max', number_format($totalBmpa, 2, ",", "."));
                                                $set('nominal_pinjaman', number_format($totalBmpa, 2, ",", "."));
                                                $set('cicilan_kelompok', number_format($totalBmpa / 5 * 11, 2, ",", "."));
                                                $set('total_pinjaman', number_format($totalBmpa * 11, 2, ",", "."));
                                                $set('status', 'Menunggu Verifikasi');
                                            })
                                            ->defaultItems(10)
                                            ->maxItems(10)
                                            ->minItems(10),
                                    ] : [
                                        PinjamanResource::getItemsRepeaterCreate()
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
                                                $set('cicilan_kelompok', number_format($totalBmpa / 5 * 11, 2, ",", "."));
                                                $set('total_pinjaman', number_format($totalBmpa * 11, 2, ",", "."));
                                                $set('status', 'Menunggu Verifikasi');
                                            })
                                            ->defaultItems(11)
                                            ->maxItems(11)
                                            ->minItems(11),
                                    ],
                                    default => [],
                                };
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
                                ->required()
                                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                    $lama_cicilan = (int)$get('lama_cicilan');
                                    $nominal_pinjaman = $state;
                                    if ($lama_cicilan && $nominal_pinjaman) {
                                        $total_pinjaman = (float)(str_replace(",", ".", preg_replace('/[^0-9,]/', '', $nominal_pinjaman))) * (int)$get('jumlah_anggota');
                                        $number_total = $total_pinjaman / $lama_cicilan;
                                        $set('cicilan_kelompok', number_format($number_total, 2, ',', '.'));
                                        $set('total_pinjaman', number_format($total_pinjaman, 2, ',', '.'));
                                    }
                                })
                                ->rules([

                                    fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get, $userAuthAdminAccess) {
                                        $nilai = str_replace(",", ".", preg_replace('/[^0-9,]/', '', $value));
                                        $nominal_bmpa_max = str_replace(",", ".", preg_replace('/[^0-9,]/', '', $get('nominal_bmpa_max')));
                                        if ($nilai > $nominal_bmpa_max) {
                                            Notification::make()
                                                ->title("Nilai pinjaman terlalu besar, maksimal adalah " . $get('nominal_bmpa_max'))
                                                ->danger()
                                                ->send();
                                            $fail("Nilai pinjaman terlalu besar, maksimal adalah " . $get('nominal_bmpa_max'));
                                        }
                                    },
                                    fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get, $userAuthAdminAccess) {
                                        $nilai = str_replace(",", ".", preg_replace('/[^0-9,]/', '', $value)) * $get('jumlah_anggota');
                                        $saldo_pinjaman = Mutasi::where('cabang_id', $get('cabang_id'))->orderBy('id', 'desc')->first()->saldo_umum;
                                        if ($nilai > $saldo_pinjaman) {
                                            Notification::make()
                                                ->title("Total pinjaman terlalu besar (" . number_format($nilai, 2, ',', '.') . "), maksimal adalah " . number_format($saldo_pinjaman, 2, ',', '.'))
                                                ->danger()
                                                ->send();
                                            $fail("Saldo tidak cukup. Maksimal pinjaman adalah " . number_format($saldo_pinjaman / $get('jumlah_anggota'), 2, ',', '.') . ".");
                                        }
                                    },

                                ]),
                            /* Stepper::make('lama_cicilan')
                                ->label('Lama Cicilan (minggu)')
                                ->minValue(5)
                                ->maxValue(50)
                                ->step(5)
                                ->default(5)
                                ->disableManualInput()
                                ->live(debounce: 1000) */
                            TextInput::make('lama_cicilan')
                                ->numeric()
                                ->minValue(5)
                                ->maxValue(50)
                                ->step(5)
                                ->default(5)
                                ->inputMode('numeric')
                                ->live(debounce: 1000)
                                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                    $lama_cicilan = (int)$state;
                                    $nominal_pinjaman = $get('nominal_pinjaman');
                                    if ($lama_cicilan && $nominal_pinjaman) {
                                        $total_pinjaman = (float)(str_replace(",", ".", preg_replace('/[^0-9,]/', '', $nominal_pinjaman))) * (int)$get('jumlah_anggota');
                                        $number_total = $total_pinjaman / $lama_cicilan;
                                        $set('cicilan_kelompok', number_format($number_total, 2, ',', '.'));
                                        $set('total_pinjaman', number_format($total_pinjaman, 2, ',', '.'));
                                    }
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
                                ->formatStateUsing(fn ($state) => str_replace(".", ",", $state))
                                ->hint(fn (Get $get, $state) => '@ ' . str_replace(",", ".", number_format((float)str_replace(".", ",", preg_replace('/[^0-9,]/', '', $state)) / ((int)$get('jumlah_anggota') === 0 ? 1 : (int)$get('jumlah_anggota'))))),
                            Hidden::make('status')
                            /* ->options([
                                    'Pembuatan Kelompok' => 'Pembuatan Kelompok',
                                    'Menunggu Verifikasi' => 'Menunggu Verifikasi',
                                    'Cicilan Berjalan' => 'Cicilan Berjalan',
                                    'Sudah Lunas' => 'Sudah Lunas',
                                ])
                                ->default('Pembuatan Kelompok') */,
                            Toggle::make('acc_pinjaman')
                                ->hidden(!($userAuth->hasRole($adminAccessApprove)))
                                ->afterStateUpdated(fn (Set $set) => $set('status', 'Cicilan Berjalan'))
                                ->live(),
                            DatePicker::make('tanggal_cicilan_pertama')
                                ->date('d/m/Y')
                                ->native(false)
                                ->hidden(!($userAuth->hasRole($adminAccessApprove)))
                                ->required(!($userAuth->hasRole($adminAccessApprove))),
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
            ]);
    }
}
