<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CabangResource\Pages;
use App\Filament\Resources\CabangResource\RelationManagers;
use App\Models\Cabang;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CabangResource extends Resource
{
    protected static ?string $model = Cabang::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Administrator';

    protected static ?string $slug = 'cabang';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama_cabang')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
                Forms\Components\TextInput::make('lokasi')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('ketua_pembina')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Repeater::make('anggota_pembina'),
                Forms\Components\TextInput::make('ketua_pengawas')
                    ->maxLength(255),
                Forms\Components\Repeater::make('anggota_pengawas'),
                Forms\Components\TextInput::make('ketua_pengurus')
                    ->maxLength(255),
                Forms\Components\TextInput::make('sekretaris'),
                Forms\Components\TextInput::make('bendahara'),
                Forms\Components\TextInput::make('saldo_awal')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama_cabang')
                    ->searchable(),
                Tables\Columns\TextColumn::make('lokasi')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ketua_pembina')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ketua_pengawas')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ketua_pengurus')
                    ->searchable(),
                Tables\Columns\TextColumn::make('saldo_awal')
                    ->searchable(),
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
