<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\Cabang;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Support\RawJs;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\CabangResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\CabangResource\RelationManagers;

class CabangResource extends Resource
{
    protected static ?string $model = Cabang::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Administrator';

    protected static ?string $slug = 'cabang';

    public static function getEloquentQuery(): Builder
    {
        $userAuth = auth()->user();
        if ($userAuth->hasRole(['super_admin', 'admin_pusat'])) {
            return parent::getEloquentQuery();
        } else {
            return parent::getEloquentQuery()->where('id', $userAuth->cabang_id);
        }
    }

    public static function form(Form $form): Form
    {
        $userOptions = User::all()->pluck('name', 'id');
        return $form
            ->schema([
                Section::make('CABANG')
                    ->schema([
                        TextInput::make('nama_cabang')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('lokasi')
                            ->maxLength(255)
                            ->required(),
                        TextInput::make('saldo_awal')
                            ->mask(RawJs::make(<<<'JS'
                               $money($input, ',', '.', 2)
                            JS))
                            ->required(),
                    ]),
                Section::make('KEAMILAN')
                    ->schema([
                        Select::make('ketua_pembina')
                            ->label('Ketua Pembina')
                            ->options($userOptions)
                            ->searchable(),
                        Repeater::make('anggota_pembina')
                            ->label('Anggota Pembina')
                            ->schema([
                                Select::make('nama')
                                    ->options($userOptions)
                                    ->searchable(),
                            ]),
                        Select::make('ketua_pengawas')
                            ->label('Ketua Pengawas')
                            ->options($userOptions)
                            ->searchable(),
                        Repeater::make('anggota_pengawas')
                            ->schema([
                                Select::make('nama')
                                    ->options($userOptions)
                                    ->searchable(),
                            ]),
                        Select::make('ketua_pengurus')
                            ->label('Ketua Pengurus')
                            ->options($userOptions)
                            ->searchable(),
                        Repeater::make('sekretaris')
                            ->schema([
                                Select::make('nama')
                                    ->options($userOptions)
                                    ->searchable(),
                            ]),
                        Repeater::make('bendahara')
                            ->schema([
                                Select::make('nama')
                                    ->options($userOptions)
                                    ->searchable(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('no')
                    ->rowIndex(isFromZero: false),
                Tables\Columns\TextColumn::make('nama_cabang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lokasi')
                    ->searchable(),
                Tables\Columns\TextColumn::make('saldo_awal')
                    ->searchable()
                    ->sortable()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
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
            'index' => Pages\ListCabangs::route('/'),
            'create' => Pages\CreateCabang::route('/create'),
            'view' => Pages\ViewCabang::route('/{record}'),
            'edit' => Pages\EditCabang::route('/{record}/edit'),
        ];
    }    
}
