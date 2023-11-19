<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CicilanResource\Pages;
use App\Filament\Resources\CicilanResource\RelationManagers;
use App\Models\Cicilan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CicilanResource extends Resource
{
    protected static ?string $model = Cicilan::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Transaksi';

    protected static ?string $navigationLabel = 'Cicilan';

    protected static ?string $slug = 'cicilan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('pinjaman_id')
                    ->numeric(),
                Forms\Components\TextInput::make('nominal_cicilan')
                    ->maxLength(255),
                Forms\Components\DatePicker::make('tanggal_cicilan'),
                Forms\Components\TextInput::make('tagihan_ke')
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_final')
                    ->required(),
                Forms\Components\TextInput::make('berkas')
                    ->maxLength(255),
                Forms\Components\Toggle::make('status_cicilan')
                    ->required(),
                Forms\Components\DatePicker::make('tanggal_bayar'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pinjaman_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nominal_cicilan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tanggal_cicilan')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tagihan_ke')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_final')
                    ->boolean(),
                Tables\Columns\TextColumn::make('berkas')
                    ->searchable(),
                Tables\Columns\IconColumn::make('status_cicilan')
                    ->boolean(),
                Tables\Columns\TextColumn::make('tanggal_bayar')
                    ->date()
                    ->sortable(),
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
            'index' => Pages\ListCicilans::route('/'),
            'create' => Pages\CreateCicilan::route('/create'),
            'view' => Pages\ViewCicilan::route('/{record}'),
            'edit' => Pages\EditCicilan::route('/{record}/edit'),
        ];
    }
}
