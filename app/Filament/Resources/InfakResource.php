<?php

namespace App\Filament\Resources;

use Carbon\Carbon;
use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\Infak;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Support\RawJs;
use Filament\Resources\Resource;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\InfakResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\InfakResource\RelationManagers;
use Filament\Forms\Components\FileUpload;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;

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
        if ($userAuth->hasRole(config('bwi.adminAccess'))) {
            return parent::getEloquentQuery()->orderBy('id', 'DESC');
        } else {
            return parent::getEloquentQuery()->where('cabang_id', $userAuth->cabang_id)->orderBy('id', 'DESC');
        }
    }

    public static function form(Form $form): Form
    {
        $userAuth = auth()->user();
        $adminAccess = config('bwi.adminAccess');
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
                    ->label('Pemberi Infaq')
                    ->live()
                    ->hidden(fn (Get $get) => $get('jenis') === "Kotak Infaq")
                    ->options(($userAuthAdminAccess) ? fn (Get $get) => $userRecord->where('cabang_id', $get('cabang_id'))->where('jenis_anggota', $get('jenis'))->pluck('name', 'id') : fn (Get $get) => $userRecord->where('cabang_id', $userAuth->cabang_id)->where('jenis_anggota', $get('jenis'))->pluck('name', 'id')),
                TextInput::make('nominal')
                    ->mask(RawJs::make(<<<'JS'
                            $money($input, ',', '.', 2)
                        JS))
                    ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                    ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
                Select::make('metode')
                    ->label('Jenis Pembayaran')
                    ->options([
                        "Cash Tunai" => "Cash Tunai",
                        "Transfer Bank" => "Transfer Bank",
                    ])->default("Cash Tunai"),
                DatePicker::make('tanggal')->default(now())->maxDate(now())->hint('Maksimal hari ini')->native(false),
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
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->translatedFormat('l, d M Y'))
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('INFAQ')
                    ->columns([
                        'sm' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextEntry::make('cabangs.nama_cabang')
                            ->label('Cabang:')
                            ->hidden(!auth()->user()->hasRole(config('bwi.adminAccess'))),
                        TextEntry::make('jenis')
                            ->label('Sumber Infaq:')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Kotak Infaq' => 'success',
                                'Anggota' => 'warning',
                                'Donatur' => 'danger',
                            }),
                        TextEntry::make('users.name')
                            ->label('Pemberi Infaq:'),
                        TextEntry::make('nominal')
                            ->label('Nominal:')
                            ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.')),
                        TextEntry::make('metode')
                            ->label('Jenis Pembayaran:'),
                        TextEntry::make('tanggal')
                            ->label('Tanggal Pembayaran:')
                            ->date(),
                        ImageEntry::make('berkas'),
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
            'index' => Pages\ListInfaks::route('/'),
            'create' => Pages\CreateInfak::route('/create'),
            'view' => Pages\ViewInfak::route('/{record}'),
            'edit' => Pages\EditInfak::route('/{record}/edit'),
        ];
    }
}
