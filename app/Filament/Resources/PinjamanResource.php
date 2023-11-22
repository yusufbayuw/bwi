<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Pinjaman;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\PinjamanResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\PinjamanResource\RelationManagers;

class PinjamanResource extends Resource
{
    protected static ?string $model = Pinjaman::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Kelompok';

    protected static ?string $slug = 'pinjaman';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('cabang_id')
                    ->numeric(),
                Forms\Components\TextInput::make('nama_kelompok')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('jumlah_anggota')
                    ->numeric(),
                Forms\Components\TextInput::make('list_anggota'),
                Forms\Components\TextInput::make('berkas')
                    ->maxLength(255),
                Forms\Components\TextInput::make('nominal_bmpa_max')
                    ->maxLength(255),
                Forms\Components\TextInput::make('lama_cicilan')
                    ->numeric(),
                Forms\Components\TextInput::make('status')
                    ->maxLength(255),
                Forms\Components\TextInput::make('total_pinjaman')
                    ->maxLength(255),
                Forms\Components\Toggle::make('acc_pinjaman')
                    ->required(),
                Forms\Components\DatePicker::make('tanggal_cicilan_pertama'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no')
                    ->rowIndex(isFromZero: false),
                TextColumn::make('cabang_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('nama_kelompok')
                    ->searchable(),
                TextColumn::make('jumlah_anggota')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('berkas')
                    ->searchable(),
                TextColumn::make('nominal_bmpa_max')
                    ->searchable(),
                TextColumn::make('lama_cicilan')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('total_pinjaman')
                    ->searchable(),
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
