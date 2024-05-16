<?php

namespace App\Filament\Resources;

use Carbon\Carbon;
use Filament\Forms;
use Filament\Tables;
use App\Models\Mutasi;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\MutasiResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\MutasiResource\RelationManagers;

class MutasiResource extends Resource
{
    protected static ?string $model = Mutasi::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    //protected static ?string $navigationGroup = 'Catatan';
    protected static ?int $navigationSort = 25;

    protected static ?string $slug = 'mutasi';

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
                TextColumn::make('no')
                    ->rowIndex(isFromZero: false),
                TextColumn::make('cabangs.nama_cabang')
                    ->label('Nama Cabang')
                    ->sortable()
                    ->searchable()
                    ->hidden(!(auth()->user()->hasRole(config('bwi.adminAccess'))))
                    ->toggleable(isToggledHiddenByDefault:false),
                /* TextColumn::make('pinjaman_id')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault:true),
                TextColumn::make('cicilan_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('pengeluaran_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('infak_id')
                    ->numeric()
                    ->sortable(), */
                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime()
                    ->sortable(),
                    //->formatStateUsing(fn ($state) => Carbon::parse($state)->translatedFormat('l, d M Y')),
                /* TextColumn::make('tanggal')
                    ->sortable()
                    ->date()
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->translatedFormat('l, d M Y')), */
                TextColumn::make('keterangan')
                    ->searchable(),
                TextColumn::make('debet')
                    ->sortable()
                    ->alignRight()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                TextColumn::make('kredit')
                    ->sortable()
                    ->alignRight()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                TextColumn::make('saldo_umum')
                    ->searchable()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                TextColumn::make('saldo_keamilan')
                    ->searchable()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                TextColumn::make('saldo_csr')
                    ->searchable()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                TextColumn::make('saldo_cadangan')
                    ->searchable()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                TextColumn::make('updated_at')
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
            ])
            ->recordUrl(null);
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
