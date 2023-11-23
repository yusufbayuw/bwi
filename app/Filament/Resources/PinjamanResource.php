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
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
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

class PinjamanResource extends Resource
{
    protected static ?string $model = Pinjaman::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Kelompok';

    protected static ?string $slug = 'pinjaman';

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
        $userAuthAdminAccess = $userAuth->hasRole($adminAccess);

        return $form
            ->schema([
                Wizard::make([
                    Wizard\Step::make('Kelompok')
                        ->schema([
                            ($userAuthAdminAccess) ? Select::make('cabang_id')
                                ->label('Cabang')
                                ->relationship('cabangs', 'nama_cabang')
                                ->live(debounce: 500) :
                                Hidden::make('cabang_id')->default($userAuth->cabang_id),
                            TextInput::make('nama_kelompok')
                                ->required()
                                ->maxLength(255),
                            Stepper::make('jumlah_anggota')
                                ->minValue(5)
                                ->maxValue(11)
                                ->step(2)
                                ->default(5)
                                ->live(debounce: 500),
                        ]),
                    Wizard\Step::make('Anggota')
                        ->schema([
                            Repeater::make('list_anggota')
                                ->schema([
                                    Select::make('nama')
                                        ->label('Nama Anggota')
                                        ->options(function (Get $get) {
                                            $userAuth = auth()->user();
                                            $adminAccess = ['super_admin', 'admin_pusat'];
                                            $userAuthAdminAccess = $userAuth->hasRole($adminAccess);
                                            if ($userAuthAdminAccess) {
                                                return User::where('cabang_id', ($get('../../cabang_id')))->pluck('name', 'id');
                                            } else {
                                                return User::where('cabang_id', ($userAuth->cabang_id ?? 0))->pluck('name', 'id');
                                            }
                                        })
                                        ->afterStateUpdated(fn (Set $set, $state) => $set('bmpa', User::where('id', $state)->first()->bmpa))
                                        ->live(debounce: 500),
                                    TextInput::make('bmpa')
                                        ->mask(RawJs::make(<<<'JS'
                                $money($input, ',', '.', 0)
                            JS))
                                        ->disabled(),
                                ])
                                ->live(debounce: 500)
                                ->maxItems(fn (Get $get) => $get('jumlah_anggota'))
                                ->minItems(fn (Get $get) => $get('jumlah_anggota'))
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $totalBmpa = 0;
                                    foreach ($state as $key => $item) {
                                        if ($totalBmpa < (float)$item['bmpa']) {
                                            $totalBmpa = (float)$item['bmpa'];
                                            $set('nominal_bmpa_max', $totalBmpa);
                                        }
                                    };
                                })->label('Daftar Anggota'),
                        ]),
                    Wizard\Step::make('Cicilan')
                        ->schema([
                            TextInput::make('nominal_bmpa_max')
                                ->mask(RawJs::make(<<<'JS'
                        $money($input, ',', '.', 2)
                    JS))
                                ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                                ->formatStateUsing(fn ($state) => str_replace(".", ",", $state))
                                ->disabled(),
                            Stepper::make('lama_cicilan')
                                ->label('Lama Cicilan (minggu)')
                                ->minValue(5)
                                ->maxValue(50)
                                ->step(5)
                                ->default(5)
                                ->live(debounce:500)
                                ->afterStateUpdated(fn (Set $set, Get $get, $state) => $set('total_pinjaman', (float)$get('nominal_bmpa_max')*(float)$state)),
                            TextInput::make('status'),
                            TextInput::make('total_pinjaman')->disabled(),
                            Toggle::make('acc_pinjaman'),
                            DatePicker::make('tanggal_cicilan_pertama')->format('dd/mm/yyyy'),
                            FileUpload::make('berkas'),
                        ])
                ])->submitAction(new HtmlString('<button type="submit">Simpan</button>')),



            ]);
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
