<?php

namespace App\Filament\Resources;

use Closure;
use Carbon\Carbon;
use App\Models\User;
use Filament\Tables;
use App\Models\Mutasi;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\Pinjaman;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Support\RawJs;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists\Components\Fieldset;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use App\Filament\Resources\PinjamanResource\Pages;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as ComponentsSection;
use Illuminate\Validation\Rules\Unique;

class PinjamanResource extends Resource
{
    //public Pinjaman $pinjaman;

    protected static ?string $model = Pinjaman::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    //protected static ?string $navigationGroup = 'Kelompok';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'pinjaman';


    /* public function mutateFormDataBeforeFill(array $data): array
    {
        // STORE TEAMS
        $data['users'] = $this->pinjaman->users()->get()->toArray();

        return $data;
    } */

    public static function getEloquentQuery(): Builder
    {
        $userAuth = auth()->user();
        if ($userAuth->hasRole(config('bwi.adminAccess'))) {
            return parent::getEloquentQuery()->orderBy('id', 'DESC');
        } else {
            return parent::getEloquentQuery()->where('cabang_id', $userAuth->cabang_id)->orderBy('id', 'DESC');
        }
    }

    public static function form(Form $form): Form
    {
        $userAuth = auth()->user();
        $adminAccess = config('bwi.adminAccess');
        $adminAccessApprove = config('bwi.adminAccessApprove');
        $userAuthAdminAccess = $userAuth->hasRole($adminAccess);

        return $form
            ->schema([

                Section::make('Kelompok')
                    ->description('Nama kelompok dapat diganti jika ingin')
                    ->schema([
                        ($userAuthAdminAccess) ? Select::make('cabang_id')
                            ->label('Cabang')
                            ->dehydrated(false)
                            ->relationship('cabangs', 'nama_cabang')
                            ->live() :
                            Hidden::make('cabang_id')->default($userAuth->cabang_id),
                        TextInput::make('nama_kelompok')
                            ->required()
                            ->unique(modifyRuleUsing: function (Unique $rule) use ($userAuthAdminAccess, $userAuth) {
                                return ($userAuthAdminAccess ? $rule : $rule->where('cabang_id', $userAuth->cabang_id));
                            }, ignoreRecord: true)
                            ->maxLength(255),
                        Hidden::make('dengan_pengurus'),
                        Hidden::make('jumlah_anggota'),
                    ]),
                Section::make('Anggota')
                    ->description('Untuk mengganti silakan tolak dan buat kelompok baru.')
                    ->schema(
                        [
                            TextInput::make('nama_pengurus')
                                ->readOnly()
                                ->dehydrated(false)
                                ->hidden(fn ($state) => !isset($state))
                                ->formatStateUsing(fn ($state) => User::find($state)->name),
                            TextInput::make('bmpa_pengurus')
                                ->readOnly()
                                ->required()
                                ->dehydrated(false)
                                ->hidden(fn ($state) => !isset($state))
                                ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.')),
                            static::getItemsRepeater(),
                        ]
                    ),
                Section::make('Cicilan')
                    ->description('atur cicilan per minggu')
                    ->schema([
                        Hidden::make('nominal_bmpa_max'),
                        TextInput::make('nominal_pinjaman')
                            ->label('Nominal Pinjaman per Anggota')
                            ->mask(RawJs::make(<<<'JS'
                                    $money($input, ',', '.', 2)
                                JS))
                            ->hint(fn (Get $get) => "Nominal pinjaman per anggota tidak boleh melebihi " . number_format($get('nominal_bmpa_max'), 2, ',', '.'))
                            ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                            ->formatStateUsing(fn ($state) => str_replace(".", ",", $state))
                            ->live(onBlur: true)
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
                        TextInput::make('lama_cicilan')
                            ->numeric()
                            ->label('Lama Cicilan (minggu):')
                            ->hint('Minimal 5 minggu, maksimal 50 minggu')
                            ->minValue(5)
                            ->maxValue(50)
                            ->required()
                            ->inputMode('numeric')
                            ->live(onBlur: true)
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
                            ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
                        Hidden::make('status'),
                        ToggleButtons::make('acc_pinjaman')
                            ->hidden(!($userAuth->hasRole($adminAccessApprove)))
                            ->options([
                                '1' => 'Setujui',
                                '-1' => 'Tolak'
                            ])
                            ->icons([
                                '1' => 'heroicon-o-check',
                                '-1' => 'heroicon-o-x-mark',
                            ])
                            ->colors([
                                '1' => 'success',
                                '-1' => 'success',
                            ])
                            ->inline()
                            ->required()
                            ->afterStateUpdated(function (Pinjaman $pinjaman, $state, Set $set) {
                                if ($state) {
                                    $userIds = $pinjaman->list_anggota;
    
                                    $counterPinjaman = 0;
                                    if ($pinjaman->nama_pengurus) {
                                        $nama_pengurus = User::find($pinjaman->nama_pengurus);
                                        if ($nama_pengurus->is_kelompok) {
                                            Notification::make()
                                                ->title("Gagal: " . $nama_pengurus->name . " masih tergabung kelompok pinjaman.")
                                                ->body('Pastikan pinjaman kelompok ' . $nama_pengurus->pinjamans->nama_kelompok . " sudah lunas terlebih dahulu. Kelompok ini akan ditolak.")
                                                ->danger()
                                                ->send();
                                            $counterPinjaman += 1;
                                        }
                                    }
    
                                    foreach ($userIds as $userIdData) {
                                        $userId = $userIdData['user_id'];
                                        $user = User::find($userId);
    
                                        if ($user->is_kelompok) {
                                            Notification::make()
                                                ->title("Gagal: " . $user->name . " masih tergabung kelompok pinjaman.")
                                                ->body('Pastikan pinjaman kelompok ' . $user->pinjamans->nama_kelompok . " sudah lunas terlebih dahulu. Kelompok ini akan ditolak.")
                                                ->danger()
                                                ->send();
                                            $counterPinjaman += 1;
                                        }
                                    }

                                    if ($counterPinjaman) {
                                        $set('status', 'Ditolak');
                                        $set('acc_pinjaman', -1);
                                        $set('cicilan_kelompok', 0);
                                        $set('total_pinjaman', 0);
                                        $set('nominal_pinjaman', 0);
                                        $set('lama_cicilan', 5);
                                    } else {
                                        $set('status', 'Cicilan Berjalan');
                                    }
                                }
                            })
                            //->afterStateUpdated(fn (Set $set, $state) => $state ? $set('status', 'Cicilan Berjalan') : '')
                            ->live()
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get, $userAuthAdminAccess) {
                                    if ($value == 0) {
                                        Notification::make()
                                            ->title("Setujui atau tolak pinjaman terlebih dahulu")
                                            ->body("Ketika disetujui cicilan akan otomatis terbentuk. Ketika ditolak, kelompok ini akan terhapus.")
                                            ->danger()
                                            ->send();
                                        $fail("Setujui atau tolak pinjaman terlebih dahulu");
                                    }
                                },
                            ]),
                        DatePicker::make('tanggal_cicilan_pertama')
                            ->date('d/m/Y')
                            ->native(false)
                            ->hidden((fn (Get $get) => (!($userAuth->hasRole($adminAccessApprove))) || ($get('acc_pinjaman') != 1)))
                            ->required($userAuth->hasRole($adminAccessApprove)),
                        FileUpload::make('berkas'),
                    ])

                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $userAuth = auth()->user();
        $adminAccess = config('bwi.adminAccess');
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
                        decimalPlaces: 0,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                ImageColumn::make('berkas')->simpleLightbox(),
                TextColumn::make('total_pinjaman')
                    ->sortable()
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
                TextColumn::make('cicilan_kelompok')
                    ->sortable()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                TextColumn::make('status')
                    ->searchable()->badge(),

                IconColumn::make('acc_pinjaman')
                    ->icon(fn (string $state): string => match ($state) {
                        '-1' => 'heroicon-o-x-circle',
                        '0' => 'heroicon-o-clock',
                        '1' => 'heroicon-o-check-circle',
                    }),
                TextColumn::make('tanggal_cicilan_pertama')
                    ->date()
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->translatedFormat('l, d M Y'))
                    ->sortable(),
                ImageColumn::make('berkas')->simpleLightbox(),
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
                Tables\Actions\EditAction::make()->hidden(fn (Pinjaman $pinjaman) => $pinjaman->acc_pinjaman),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        $userAuth = auth()->user();
        $adminAccess = config('bwi.adminAccess');
        $userAuthAdminAccess = $userAuth->hasRole($adminAccess);

        return $infolist
            ->schema([
                ComponentsSection::make('KELOMPOK')
                    ->columns([
                        'sm' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextEntry::make('cabangs.nama_cabang')
                            ->label('Cabang:')
                            ->hidden(!$userAuthAdminAccess),
                        TextEntry::make('nama_kelompok')
                            ->label('Nama Kelompok:'),
                        TextEntry::make('jumlah_anggota')
                            ->label('Jumlah Anggota:'),
                        TextEntry::make('berkas'),
                        Fieldset::make('daftar_anggota')
                            ->label('Daftar Anggota & BMPA')
                            ->schema([

                                Grid::make([
                                    'sm' => 1,
                                    'md' => 2,
                                    'lg' => 4,
                                ])->schema([
                                    TextEntry::make('nama_pengurus')
                                        ->label('')
                                        ->formatStateUsing(fn ($state) => User::find($state)->name)
                                        ->hidden(fn ($state) => !isset($state)),
                                    TextEntry::make('bmpa_pengurus')
                                        ->label('')
                                        ->badge()
                                        ->hidden(fn ($state) => !isset($state))
                                        ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.')),
                                    /* TextEntry::make('nama_pengurus')
                                        ->label('')
                                        ->hidden(fn ($state) => !isset($state))
                                        ->formatStateUsing(fn ($state) => ucfirst(str_replace("_", " ", User::find($state)->roles[0]->name ?? ""))  ) */
                                ]),

                                RepeatableEntry::make('list_anggota')
                                    ->label('')
                                    ->contained(false)
                                    ->columns([
                                        'sm' => 1,
                                        'md' => 2,
                                    ])
                                    ->schema([
                                        TextEntry::make('user_id')
                                            ->formatStateUsing(fn ($state) => User::find($state)->name)
                                            ->columnSpan(1)
                                            ->label(''),
                                        TextEntry::make('bmpa')
                                            ->badge()
                                            ->label('')
                                            ->columnSpan(1)
                                            ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.')),
                                    ])
                            ])
                    ]),
                ComponentsSection::make('CICILAN')
                    ->columns([
                        'sm' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextEntry::make('nominal_bmpa_max')
                            ->label('Maksimum pinjaman/anggota:')
                            ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.')),
                        TextEntry::make('nominal_pinjaman')
                            ->label('Nominal Pinjaman/Anggota')
                            ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.')),
                        TextEntry::make('total_pinjaman')
                            ->label('Total Pinjaman Kelompok')
                            ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.')),
                        TextEntry::make('lama_cicilan')
                            ->label('Lama Cicilan (minggu):'),
                        TextEntry::make('cicilan_kelompok')
                            ->label('Cicilan Kelompok per Minggu')
                            ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.')),
                        TextEntry::make('status')
                            ->label('Status Pinjaman:')
                            ->badge(),
                        TextEntry::make('acc_pinjaman')
                            ->label('Persetujuan Pinjaman:')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? 'DISETUJUI' : 'Belum/Tidak Disetujui')
                            ->color(fn (string $state): string => match ($state) {
                                '1' => 'success',
                                '0' => 'danger',
                            }),
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

    public static function getSelectOption(): Select
    {
        return Select::make('jumlah_anggota')
            ->options([
                '5' => '5 anggota',
                '7' => '7 anggota',
                '9' => '9 anggota',
                '11' => '11 anggota',
            ])
            ->live();
    }

    public static function getItemsRepeater(): Repeater
    {
        $userAuth = auth()->user();
        $adminAccess = config('bwi.adminAccess');
        $adminAccessCreatePinjaman = config('bwi.adminAccessCreatePinjaman');
        $userAuthAdminAccess = $userAuth->hasRole($adminAccess);
        $userAuthAdminAccessCreatePinjaman = $userAuth->hasRole($adminAccessCreatePinjaman);

        return Repeater::make('list_anggota')
            ->schema([
                TextInput::make('user_id')
                    ->label('Nama Anggota')
                    ->readOnly()
                    ->dehydrated(false)
                    ->formatStateUsing(fn ($state) => User::find($state)->name),
                TextInput::make('bmpa')
                    ->label('BMPA')
                    ->dehydrated(false)
                    ->required()
                    ->mask(RawJs::make(<<<'JS'
                    $money($input, ',', '.', 0)
                JS))
                    ->readOnly()
                    ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
            ])
            ->label('Daftar Anggota')
            ->reorderableWithDragAndDrop(false)
            ->deletable(false)
            ->dehydrated(false)
            ->addable(false)
            ->columns(2);
    }

    public static function getItemsRepeaterCreate(): Repeater
    {
        $userAuth = auth()->user();
        $adminAccess = config('bwi.adminAccess');
        $userAuthAdminAccess = $userAuth->hasRole($adminAccess);

        $roleCabang = [
            config('bwi.ketua_pengurus'),
            config('bwi.bendahara'),
            config('bwi.sekretaris'),
            config('bwi.ketua_pembina'),
            config('bwi.anggota_pembina'),
            config('bwi.ketua_pengawas'),
            config('bwi.anggota_pengawas')
        ];

        if ($userAuthAdminAccess) {
            $userOption = null;
        } else {
            $userOption = User::where('cabang_id', ($userAuth->cabang_id ?? 0))
                ->where('is_kelompok', false)
                ->where('jenis_anggota', 'Anggota')
                ->whereDoesntHave('roles', function ($query) use ($roleCabang) {
                    $query->whereIn('name', $roleCabang);
                });
            //$userOptionUser = $userOption->where('is_organ', false);
        }

        return Repeater::make('list_anggota')
            ->schema([
                Select::make('user_id')
                    ->searchable()
                    ->label('Nama Anggota')
                    ->options(function (Get $get) use ($userAuthAdminAccess, $userOption) {
                        if ($userAuthAdminAccess) {
                            return User::where('cabang_id', ($get('../../cabang_id')))->where('is_kelompok', false)->where('jenis_anggota', 'Anggota')->pluck('name', 'id');
                        } else {
                            /* if (config('bwi.pinjamanOrganisasi') && $get('../../is_organ'))
                            {
                                return $userOptionUser->pluck('name', 'id');
                            } else {
                                return $userOption->pluck('name', 'id');
                            } */
                            return $userOption->pluck('name', 'id');
                        }
                    })
                    ->preload()
                    ->afterStateUpdated(function (Set $set, $state) {
                        $set('bmpa', number_format(User::where('id', $state)->first()->bmpa  ?? null, 2, ",", "."));
                        /* if (User::find($state)->is_organ ?? false) {
                            $set('../../is_organ', true);
                        } */
                    })
                    ->required()
                    ->live()
                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                TextInput::make('bmpa')
                    ->label('BMPA')
                    ->required()
                    ->mask(RawJs::make(<<<'JS'
                $money($input, ',', '.', 0)
            JS))
                    ->readOnly()
                    ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                    ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
            ])
            ->live()
            ->label('Daftar Anggota')
            ->reorderableWithDragAndDrop(false)
            ->deletable(false)
            ->addable(false)
            ->columns(2);
    }
}
