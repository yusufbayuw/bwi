<?php

namespace App\Filament\Resources;

use Carbon\Carbon;
use Filament\Forms;
use Filament\Tables;
use App\Models\Cicilan;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Support\RawJs;
use Filament\Resources\Resource;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\CicilanResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\CicilanResource\RelationManagers;
use App\Models\Pinjaman;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;

class CicilanResource extends Resource
{
    protected static ?string $model = Cicilan::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    //protected static ?string $navigationGroup = 'Transaksi';
    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Cicilan';

    protected static ?string $slug = 'cicilan';

    /* public static function getEloquentQuery(): Builder
    {
        $userAuth = auth()->user();
        if ($userAuth->hasRole(config('bwi.adminAccess'))) {
            return parent::getEloquentQuery();
        } else {
            return parent::getEloquentQuery()->where('cabang_id', $userAuth->cabang_id);
        }
    } */

    public static function form(Form $form): Form
    {
        $userAuth = auth()->user();
        $adminAccess = config('bwi.adminAccess');
        $userAuthAdminAccess = $userAuth->hasRole($adminAccess);

        return $form
            ->schema([
                ($userAuthAdminAccess) ? Select::make('cabang_id')
                    ->label('Cabang')
                    ->relationship('cabangs', 'nama_cabang')->disabled() :
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
                ToggleButtons::make('status_cicilan')
                    ->label('Status Pembayaran Cicilan')
                    //->hidden(!($userAuth->hasRole($adminAccessApprove)))
                    ->options([
                        '1' => 'SUDAH Dibayar',
                        '0' => 'BELUM Dibayar',
                    ])
                    ->icons([
                        '1' => 'heroicon-o-check',
                        '0' => 'heroicon-o-x-mark',
                    ])
                    ->colors([
                        '1' => 'success',
                        '0' => 'success',
                    ])
                    ->afterStateUpdated(function (Get $get, Cicilan $cicilan, Set $set) {

                        $pinjaman_id = $cicilan->pinjaman_id;
                        $cicilan_sama_ids = Cicilan::where('pinjaman_id', $pinjaman_id)->pluck('status_cicilan','id')->toArray();
                        
                        if ($cicilan_sama_ids) {
                            foreach ($cicilan_sama_ids as $id => $value) {
                                // Jika nilai adalah 0 dan id kurang dari current_id
                                if ($value == 0 && $id < $cicilan->id) {
                                    Notification::make()
                                        ->title('Bayar dulu cicilan ke-'.Cicilan::find($id)->tagihan_ke)
                                        ->body('Ini adalah cicilan ke-'.$cicilan->tagihan_ke.'. Cicilan ke-'.Cicilan::find($id)->tagihan_ke.' belum dibayar.')
                                        ->danger()
                                        ->send();
                                    $set('status_cicilan', false);
                                    break;
                                }
                            }
                        }
                    })
                    ->disableOptionWhen(fn (string $value): bool => $value == false)
                    ->inline()
                    ->live(),
                DatePicker::make('tanggal_bayar')
                    ->required()
                    ->hidden(fn (Get $get) => !$get('status_cicilan')),
                Textarea::make('catatan')
                    ->maxLength(255)
                    ->hidden(fn (Get $get) => !$get('status_cicilan')),
                FileUpload::make('berkas')
                ->hidden(fn (Get $get) => !$get('status_cicilan')),
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
                    ->sortable()
                    ->searchable(),
                TextColumn::make('tanggal_cicilan')
                    ->label('Tanggal Tagihan')
                    ->date()
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->translatedFormat('l, d M Y'))
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
                    ->label('Status Pembayaran')
                    ->boolean(),
                TextColumn::make('tanggal_bayar')
                    ->label('Tanggal Pembayaran')
                    ->date()
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->translatedFormat('l, d M Y'))
                    ->sortable(),
                ImageColumn::make('berkas')->simpleLightbox(),
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
                //Tables\Actions\BulkActionGroup::make([
                //Tables\Actions\DeleteBulkAction::make(),
                //]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        $userAuth = auth()->user();
        $adminAccess = config('bwi.adminAccess');
        $userAuthAdminAccess = $userAuth->hasRole($adminAccess);

        return $infolist
            ->schema([
                Section::make('CICILAN')
                    ->columns([
                        'sm' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextEntry::make('cabangs.nama_cabang')
                            ->label('Cabang:')
                            ->hidden(!$userAuthAdminAccess),
                        TextEntry::make('pinjamans.nama_kelompok')
                            ->label('Kelompok Pinjaman:'),
                        TextEntry::make('nominal_cicilan')
                            ->label('Nominal:')
                            ->badge()
                            ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.')),
                        TextEntry::make('tanggal_cicilan')
                            ->label('Tanggal Tagihan:')
                            ->badge()
                            ->date(),
                        TextEntry::make('tagihan_ke')
                            ->label('Tagihan Ke:')
                            ->formatStateUsing(fn ($state, Cicilan $cicilan) => $state.'/'.Pinjaman::find($cicilan->pinjaman_id)->lama_cicilan),
                        TextEntry::make('status_cicilan')
                            ->label('Status Cicilan:')
                            ->formatStateUsing(fn ($state) => $state ? 'LUNAS' : 'Belum Lunas')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                '1' => 'success',
                                '0' => 'danger',
                            }),

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
