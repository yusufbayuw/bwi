<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\Pinjaman;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Support\RawJs;
use Filament\Resources\Resource;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\PinjamanResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Icetalker\FilamentStepper\Forms\Components\Stepper;
use App\Filament\Resources\PinjamanResource\RelationManagers;
use Filament\Forms\Components\Component;

class PinjamanResource extends Resource
{
    public Pinjaman $pinjaman;

    protected static ?string $model = Pinjaman::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Kelompok';

    protected static ?string $slug = 'pinjaman';

    public function mutateFormDataBeforeFill(array $data): array
    {
        // STORE TEAMS
        $data['users'] = $this->pinjaman->users()->get()->toArray();

        return $data;
    }

    public static function getEloquentQuery(): Builder
    {
        $userAuth = auth()->user();
        if ($userAuth->hasRole(['super_admin', 'admin_pusat'])) {
            return parent::getEloquentQuery();
        } else {
            return parent::getEloquentQuery()->where('cabang_id', $userAuth->cabang_id);
        }
    }

    public static function form(Form $form): Form
    {
        $userAuth = auth()->user();
        $adminAccess = ['super_admin', 'admin_pusat'];
        $adminBendaharaAccess = ['super_admin', 'admin_pusat', 'bendahara_cabang'];
        $userAuthAdminAccess = $userAuth->hasRole($adminAccess);

        if ($userAuthAdminAccess) {
            $userOption = null;
        } else {
            $userOption = User::where('cabang_id', ($userAuth->cabang_id ?? 0))->where('is_kelompok', false);
        }

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
                            Select::make('jumlah_anggota')
                                ->options([
                                    '5' => '5 anggota',
                                    '7' => '7 anggota',
                                    '9' => '9 anggota',
                                    '11' => '11 anggota',
                                ])
                                ->live()
                                ->afterStateUpdated(
                                    fn (Select $component) => $component->getContainer()->getParentComponent()->getContainer()->getComponent('dynamicTypeFields')->getChildComponentContainer()->fill()
                                ),
                        ]),
                    Wizard\Step::make('Anggota')
                        ->description('daftarkan anggota kelompok')
                        ->schema(
                            fn (Get $get): array => match ($get('jumlah_anggota')) {
                                '5' => [
                                    Repeater::make('list_anggota')
                                        ->schema([
                                            Select::make('user_id')
                                                ->label('Nama Anggota')
                                                ->options(function (Get $get) use ($userAuthAdminAccess, $userOption) {
                                                    if ($userAuthAdminAccess) {
                                                        return User::where('cabang_id', ($get('../../cabang_id')))->where('is_kelompok', false)->pluck('name', 'id');
                                                    } else {
                                                        return $userOption->pluck('name', 'id');
                                                    }
                                                })
                                                ->afterStateUpdated(fn (Set $set, $state) => $set('bmpa', number_format(User::where('id', $state)->first()->bmpa, 2, ",", ".")))
                                                ->required()
                                                ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                            TextInput::make('bmpa')
                                                ->label('BMPA')
                                                ->mask(RawJs::make(<<<'JS'
                                                $money($input, ',', '.', 0)
                                            JS))
                                                ->disabled()
                                                ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                                                ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
                                        ])
                                        ->live(debounce: 500)
                                        ->maxItems(5)
                                        ->minItems(5)
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
                                        })
                                        ->label('Daftar Anggota')
                                        ->reorderableWithDragAndDrop(false)
                                        ->deletable(false)
                                        ->columns(2)
                                        ->defaultItems(5),
                                ],
                                '7' => [
                                    Repeater::make('list_anggota')
                                        ->schema([
                                            Select::make('nama')
                                                ->label('Nama Anggota')
                                                ->options(function (Get $get) use ($userAuthAdminAccess, $userOption) {
                                                    if ($userAuthAdminAccess) {
                                                        return User::where('cabang_id', ($get('../../cabang_id')))->where('is_kelompok', false)->pluck('name', 'id');
                                                    } else {
                                                        return $userOption->pluck('name', 'id');
                                                    }
                                                })
                                                ->afterStateUpdated(fn (Set $set, $state) => $set('bmpa', number_format(User::where('id', $state)->first()->bmpa, 2, ",", ".")))
                                                ->live()
                                                ->required(),
                                            TextInput::make('bmpa')
                                                ->label('BMPA')
                                                ->mask(RawJs::make(<<<'JS'
                                                    $money($input, ',', '.', 0)
                                                JS))
                                                ->disabled()
                                                ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                                                ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
                                        ])
                                        ->live(debounce: 500)
                                        ->maxItems(7)
                                        ->minItems(7)
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
                                        })
                                        ->label('Daftar Anggota')
                                        ->reorderableWithDragAndDrop(false)
                                        ->deletable(false)
                                        ->columns(2)
                                        ->defaultItems(7),
                                ],
                                '9' => [
                                    Repeater::make('list_anggota')
                                        ->schema([
                                            Select::make('nama')
                                                ->label('Nama Anggota')
                                                ->options(function (Get $get) use ($userAuthAdminAccess, $userOption) {
                                                    if ($userAuthAdminAccess) {
                                                        return User::where('cabang_id', ($get('../../cabang_id')))->where('is_kelompok', false)->pluck('name', 'id');
                                                    } else {
                                                        return $userOption->pluck('name', 'id');
                                                    }
                                                })
                                                ->afterStateUpdated(fn (Set $set, $state) => $set('bmpa', number_format(User::where('id', $state)->first()->bmpa, 2, ",", ".")))
                                                ->live()
                                                ->required(),
                                            TextInput::make('bmpa')
                                                ->label('BMPA')
                                                ->mask(RawJs::make(<<<'JS'
                                                        $money($input, ',', '.', 0)
                                                    JS))
                                                ->disabled()
                                                ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                                                ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
                                        ])
                                        ->live(debounce: 500)
                                        ->maxItems(9)
                                        ->minItems(9)
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
                                        })
                                        ->label('Daftar Anggota')
                                        ->reorderableWithDragAndDrop(false)
                                        ->deletable(false)
                                        ->columns(2)
                                        ->defaultItems(9),
                                ],
                                '11' => [
                                    Repeater::make('list_anggota')
                                        ->schema([
                                            Select::make('nama')
                                                ->label('Nama Anggota')
                                                ->options(function (Get $get) use ($userAuthAdminAccess, $userOption) {
                                                    if ($userAuthAdminAccess) {
                                                        return User::where('cabang_id', ($get('../../cabang_id')))->where('is_kelompok', false)->pluck('name', 'id');
                                                    } else {
                                                        return $userOption->pluck('name', 'id');
                                                    }
                                                })
                                                ->afterStateUpdated(fn (Set $set, $state) => $set('bmpa', number_format(User::where('id', $state)->first()->bmpa, 2, ",", ".")))
                                                ->live()
                                                ->required(),
                                            TextInput::make('bmpa')
                                                ->label('BMPA')
                                                ->mask(RawJs::make(<<<'JS'
                                                            $money($input, ',', '.', 0)
                                                        JS))
                                                ->disabled()
                                                ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                                                ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
                                        ])
                                        ->live(debounce: 500)
                                        ->maxItems(11)
                                        ->minItems(11)
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
                                        })
                                        ->label('Daftar Anggota')
                                        ->reorderableWithDragAndDrop(false)
                                        ->deletable(false)
                                        ->columns(2)
                                        ->defaultItems(11),
                                ],
                                default => [],
                            }
                        )->key('dynamicTypeFields'),
                    Wizard\Step::make('Cicilan')
                        ->description('atur cicilan per minggu')
                        ->schema([
                            TextInput::make('nominal_bmpa_max')
                                ->mask(RawJs::make(<<<'JS'
                                    $money($input, ',', '.', 2)
                                JS))
                                ->label('Maksimum Pinjaman Per Anggota')
                                ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                                ->formatStateUsing(fn ($state) => str_replace(".", ",", $state))
                                ->disabled(),
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
                                ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                                ->formatStateUsing(fn ($state) => str_replace(".", ",", $state))
                                ->disabled(),
                            TextInput::make('cicilan_kelompok')
                                ->mask(RawJs::make(<<<'JS'
                                    $money($input, ',', '.', 2)
                                JS))
                                ->label('Cicilan Kelompok per Minggu')
                                ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                                ->formatStateUsing(fn ($state) => str_replace(".", ",", $state))
                                ->disabled(),
                            Select::make('status')
                                ->options([
                                    'Pembuatan Kelompok' => 'Pembuatan Kelompok',
                                    'Menunggu Verifikasi' => 'Menunggu Verifikasi',
                                    'Cicilan Berjalan' => 'Cicilan Berjalan',
                                    'Sudah Lunas' => 'Sudah Lunas',
                                ])
                                ->default('Pembuatan Kelompok')
                                ->disabled(),
                            Toggle::make('acc_pinjaman')
                                ->hidden(!($userAuth->hasRole($adminBendaharaAccess))),
                            DatePicker::make('tanggal_cicilan_pertama')
                                ->format('dd/mm/yyyy')
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
            ->statePath('data')
            ->model($this->pinjaman);
    }

    public static function table(Table $table): Table
    {
        $userAuth = auth()->user();
        $adminAccess = ['super_admin', 'admin_pusat'];
        $userAuthAdminAccess = $userAuth->hasRole($adminAccess);

        return $table
            ->columns([
                TextColumn::make('no')
                    ->rowIndex(isFromZero: false),
                TextColumn::make('cabangs.nama_cabang')
                    ->numeric()
                    ->sortable()
                    ->hidden(!($userAuthAdminAccess)),
                TextColumn::make('nama_kelompok')
                    ->searchable(),
                TextColumn::make('jumlah_anggota')
                    ->sortable()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                ImageColumn::make('berkas'),
                TextColumn::make('nominal_bmpa_max')
                    ->searchable()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                TextColumn::make('lama_cicilan')
                    ->numeric()
                    ->sortable()
                    ->numeric(
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('total_pinjaman')
                    ->sortable()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                IconColumn::make('acc_pinjaman')
                    ->boolean(),
                TextColumn::make('tanggal_cicilan_pertama')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPinjamen::route('/'),
            'create' => Pages\CreatePinjaman::route('/create'),
            'view' => Pages\ViewPinjaman::route('/{record}'),
            'edit' => Pages\EditPinjaman::route('/{record}/edit'),
        ];
    }
}
