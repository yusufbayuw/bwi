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
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\PinjamanResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Icetalker\FilamentStepper\Forms\Components\Stepper;
use App\Filament\Resources\PinjamanResource\RelationManagers;
use Filament\Tables\Columns\ImageColumn;

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
                ($userAuthAdminAccess) ? Select::make('cabang_id')
                    ->label('Cabang')
                    ->relationship('cabangs', 'nama_cabang')
                    ->live() : 
                    Hidden::make('cabang_id')->default($userAuth->cabang_id),
                TextInput::make('nama_kelompok')
                    ->required()
                    ->maxLength(255),
                Select::make('jumlah_anggota')
                    ->options([]),
                Repeater::make('list_anggota')
                    ->schema([
                        Select::make('nama')
                            ->label('Nama Anggota')
                            ->options(function (Get $get) {
                                $userAuth = auth()->user();
                                $adminAccess = ['super_admin', 'admin_pusat'];
                                $userAuthAdminAccess = $userAuth->hasRole($adminAccess);
                                if ($userAuthAdminAccess) {
                                    return User::where('cabang_id', ($get('dummy')))->pluck('name', 'id');
                                } else {
                                    return User::where('cabang_id', ($userAuth->cabang_id ?? 0))->pluck('name', 'id');
                                } 
                            } )
                            ->afterStateUpdated(fn (Set $set, $state) => $set('bmpa', User::where('id', $state)->first()->bmpa))
                            ->live(),
                        TextInput::make('bmpa')
                            ->disabled(),
                        Hidden::make('dummy')
                            ->default(fn (Get $get) => ($get('../../cabang_id') ?? 0))
                    ])->live(),
                TextInput::make('berkas')
                    ->maxLength(255),
                TextInput::make('nominal_bmpa_max')
                    ->mask(RawJs::make(<<<'JS'
                        $money($input, ',', '.', 2)
                    JS))
                    //->content(fn (Get $get) => min($get('list_anggota')))
                    ->disabled(),
                Stepper::make('lama_cicilan')
                    ->label('Lama Cicilan (minggu)')
                    ->minValue(5)
                    ->maxValue(50)
                    ->step(5)
                    ->default(5),
                TextInput::make('status')
                    ->maxLength(255),
                TextInput::make('total_pinjaman')
                    ->maxLength(255),
                Toggle::make('acc_pinjaman')
                    ->required(),
                DatePicker::make('tanggal_cicilan_pertama'),
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
