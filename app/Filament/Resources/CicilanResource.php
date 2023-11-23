<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Cicilan;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Support\RawJs;
use Filament\Resources\Resource;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\CicilanResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\CicilanResource\RelationManagers;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;

class CicilanResource extends Resource
{
    protected static ?string $model = Cicilan::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Transaksi';

    protected static ?string $navigationLabel = 'Cicilan';

    protected static ?string $slug = 'cicilan';

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
                    ->relationship('cabangs', 'nama_cabang') : 
                    Hidden::make('cabang_id')->default($userAuth->cabang_id),
                Select::make('pinjaman_id')
                    ->relationship('pinjamans', 'nama_kelompok')
                    ->disabled(),
                TextInput::make('nominal_cicilan')
                    ->mask(RawJs::make(<<<'JS'
                            $money($input, ',', '.', 2)
                        JS))
                    ->disabled()
                    ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                    ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
                DatePicker::make('tanggal_cicilan')
                    ->disabled(),
                TextInput::make('tagihan_ke')
                    ->mask(RawJs::make(<<<'JS'
                                $money($input, ',', '.', 0)
                            JS))
                    ->disabled()
                    ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                    ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
                Hidden::make('is_final')
                    ->disabled(),
                Toggle::make('status_cicilan'),
                DatePicker::make('tanggal_bayar')
                    ->maxDate(now()),
                FileUpload::make('berkas'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no')
                    ->rowIndex(isFromZero: false),
                TextColumn::make('cabangs.nama_cabang')
                    ->sortable(),
                TextColumn::make('pinjamans.nama_kelompok')
                    ->sortable(),
                TextColumn::make('tanggal_cicilan')
                    ->label('Tanggal Tagihan')
                    ->date()
                    ->sortable(),    
                TextColumn::make('nominal_cicilan')
                    ->searchable()
                    ->label('Nominal')
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                TextColumn::make('tagihan_ke')
                    ->searchable()
                    ->numeric(
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                IconColumn::make('status_cicilan')
                    ->boolean(),
                TextColumn::make('tanggal_bayar')
                    ->label('Tanggal Pembayaran')
                    ->date()
                    ->sortable(),
                TextColumn::make('berkas')
                    ->searchable(),
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
                //Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //Tables\Actions\BulkActionGroup::make([
                    //Tables\Actions\DeleteBulkAction::make(),
                //]),
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
            //'create' => Pages\CreateCicilan::route('/create'),
            'view' => Pages\ViewCicilan::route('/{record}'),
            'edit' => Pages\EditCicilan::route('/{record}/edit'),
        ];
    }
}
