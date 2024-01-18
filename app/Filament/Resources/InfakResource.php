<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\Infak;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Support\RawJs;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\InfakResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\InfakResource\RelationManagers;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Get;

class InfakResource extends Resource
{
    protected static ?string $model = Infak::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    //protected static ?string $navigationGroup = 'Transaksi';
    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'infaq';

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
        $userRecord = User::all();

        return $form
            ->schema([
                ($userAuthAdminAccess) ? Select::make('cabang_id')
                    ->label('Cabang')
                    ->live()
                    ->relationship('cabangs', 'nama_cabang') :
                    Hidden::make('cabang_id')->default($userAuth->cabang_id),
                Select::make('jenis')
                    ->label('Sumber Infak')
                    ->options([
                        "Kotak Infaq" => "Kotak Infaq",
                        "Anggota" => "Anggota",
                        "Donatur" => "Donatur"
                    ])->live(),
                Select::make('user_id')
                    ->label('Anggota')
                    ->live()
                    ->hidden(fn (Get $get) => $get('jenis') === "Kotak Infaq")
                    ->options(($userAuthAdminAccess) ? fn (Get $get) => $userRecord->where('cabang_id', $get('cabang_id'))->where('jenis_pengguna', $get('jenis'))->pluck('name', 'id') : fn (Get $get) => $userRecord->where('cabang_id', $userAuth->cabang_id)->where('jenis_pengguna', $get('jenis'))->pluck('name', 'id')),
                TextInput::make('nominal')
                    ->mask(RawJs::make(<<<'JS'
                            $money($input, ',', '.', 2)
                        JS))
                    ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                    ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
                DatePicker::make('tanggal')->maxDate(now()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no')
                    ->rowIndex(isFromZero: false),
                TextColumn::make('cabangs.nama_cabang')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jenis')
                    ->label("Sumber")
                    ->sortable(),
                TextColumn::make('users.name')
                    ->label("Nama")
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nominal')
                    ->searchable()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                TextColumn::make('tanggal')
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
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
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
            'index' => Pages\ListInfaks::route('/'),
            'create' => Pages\CreateInfak::route('/create'),
            'view' => Pages\ViewInfak::route('/{record}'),
            'edit' => Pages\EditInfak::route('/{record}/edit'),
        ];
    }
}
