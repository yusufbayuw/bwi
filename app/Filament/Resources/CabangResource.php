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
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Navigation\NavigationItem;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\CabangResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\CabangResource\RelationManagers;

class CabangResource extends Resource
{
    protected static ?string $model = Cabang::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    //protected static ?string $navigationGroup = 'Administrator';
    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'cabang';

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName() . '.*'))
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->sort(static::getNavigationSort())
                ->url((!Auth::check() || Auth::user()->hasRole(['super_admin'])) ? static::getNavigationUrl() : static::getNavigationUrl()."/".Auth::user()->cabang_id),
        ];
    }

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
                            ->required()
                            ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                            ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
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
            ])->deferLoading();
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
            'index' => (!Auth::check() || Auth::user()->hasRole(['super_admin'])) ? Pages\ListCabangs::route('/') : Pages\ViewCabang::route('/'.Auth::user()->cabang_id),
            'create' => Pages\CreateCabang::route('/create'),
            'view' => (!Auth::check() || Auth::user()->hasRole(['super_admin'])) ? Pages\ViewCabang::route('/{record}') : Pages\ViewCabang::route('/'.Auth::user()->cabang_id),
            'edit' => Pages\EditCabang::route('/{record}/edit'),
        ];
    }    
}
