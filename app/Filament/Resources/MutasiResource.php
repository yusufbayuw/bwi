<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MutasiResource\Pages;
use App\Filament\Resources\MutasiResource\RelationManagers;
use App\Models\Mutasi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MutasiResource extends Resource
{
    protected static ?string $model = Mutasi::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Catatan';

    protected static ?string $slug = 'mutasi';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                /* Forms\Components\Select::make('cabang_id')
                    ->relationship('cabangs', 'nama_cabang'),
                Forms\Components\TextInput::make('pinjaman_id')
                    ->relationship('pinjamans', 'nama_cabang'),
                Forms\Components\TextInput::make('cicilan_id')
                    ->relationship('cabangs', 'nama_cabang'),
                Forms\Components\TextInput::make('pengeluaran_id')
                    ->relationship('cabangs', 'nama_cabang'),
                Forms\Components\TextInput::make('infak_id')
                    ->numeric(),
                Forms\Components\TextInput::make('debet')
                    ->maxLength(255),
                Forms\Components\TextInput::make('kredit')
                    ->maxLength(255),
                Forms\Components\TextInput::make('saldo_umum')
                    ->maxLength(255),
                Forms\Components\TextInput::make('saldo_keamilan')
                    ->maxLength(255),
                Forms\Components\TextInput::make('saldo_csr')
                    ->maxLength(255),
                Forms\Components\DatePicker::make('tanggal'), */
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('no')
                    ->rowIndex(isFromZero: false),
                Tables\Columns\TextColumn::make('cabangs.nama_cabang')
                    ->label('Nama Cabang')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault:false),
                /* Tables\Columns\TextColumn::make('pinjaman_id')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault:true),
                Tables\Columns\TextColumn::make('cicilan_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pengeluaran_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('infak_id')
                    ->numeric()
                    ->sortable(), */
                Tables\Columns\TextColumn::make('tanggal')
                    ->sortable()
                    ->date(),
                Tables\Columns\TextColumn::make('keterangan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('debet')
                    ->searchable()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                Tables\Columns\TextColumn::make('kredit')
                    ->searchable()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                Tables\Columns\TextColumn::make('saldo_umum')
                    ->searchable()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                Tables\Columns\TextColumn::make('saldo_keamilan')
                    ->searchable()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                Tables\Columns\TextColumn::make('saldo_csr')
                    ->searchable()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                Tables\Columns\TextColumn::make('tanggal')
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
                //Tables\Actions\ViewAction::make(),
                //Tables\Actions\EditAction::make(),
            ])
            //->bulkActions([
            //    Tables\Actions\BulkActionGroup::make([
            //        Tables\Actions\DeleteBulkAction::make(),
            //    ]),
            //])
            ->emptyStateActions([
                //Tables\Actions\CreateAction::make(),
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
            'index' => Pages\ListMutasis::route('/'),
            //'create' => Pages\CreateMutasi::route('/create'),
            'view' => Pages\ViewMutasi::route('/{record}'),
            //'edit' => Pages\EditMutasi::route('/{record}/edit'),
        ];
    }    
}
