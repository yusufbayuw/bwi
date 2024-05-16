<?php

namespace App\Filament\Resources;

use Closure;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Tables;
use App\Models\Mutasi;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\Pengeluaran;
use Filament\Support\RawJs;
use Filament\Resources\Resource;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\PengeluaranResource\Pages;
use App\Filament\Resources\PengeluaranResource\RelationManagers;

class PengeluaranResource extends Resource
{
    protected static ?string $model = Pengeluaran::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    //protected static ?string $navigationGroup = 'Transaksi';
    protected static ?int $navigationSort = 15;

    protected static ?string $slug = 'pengeluaran';

    public static function getEloquentQuery(): Builder
    {
        $userAuth = auth()->user();
        if ($userAuth->hasRole(config('bwi.adminAccess'))) {
            return parent::getEloquentQuery();
        } else {
            return parent::getEloquentQuery()->where('cabang_id', $userAuth->cabang_id);
        }
    }

    public static function form(Form $form): Form
    {
        $userAuth = auth()->user();
        $adminAccess = config('bwi.adminAccess');
        $userAuthAdminAccess = $userAuth->hasRole($adminAccess);
        $max_mutasi = Mutasi::where('cabang_id', $userAuth->cabang_id)->orderBy('id', 'DESC')->first();

        return $form
            ->schema([
                ($userAuthAdminAccess) ? Select::make('cabang_id')
                    ->label('Cabang')
                    ->relationship('cabangs', 'nama_cabang')
                    ->live() :
                    Hidden::make('cabang_id')->default($userAuth->cabang_id),
                Select::make('jenis')
                    ->options(config('bwi.jenis_pengeluaran'))
                    ->required()
                    ->disabled(fn (Get $get) => $get('cabang_id') ? false : true)
                    ->live(),
                DatePicker::make('tanggal')
                    ->default(now())
                    ->maxDate(now())
                    ->native(false)
                    ->required(),
                TextInput::make('nominal')
                    ->mask(RawJs::make(<<<'JS'
                    $money($input, ',', '.', 2)
                JS))
                    ->required()
                    ->hint(function (Get $get)
                    use ($max_mutasi, $userAuthAdminAccess) {
                        $jenis = $get('jenis');
                        if ($userAuthAdminAccess) {
                            $max_mutasi = Mutasi::where('cabang_id', $get('cabang_id'))->orderBy('id', 'DESC')->first();
                            if ($jenis === "Keamilan") {
                                return "Saldo Keamilan: Rp " . number_format((float)$max_mutasi->saldo_keamilan, 2, ',', '.');
                            } elseif ($jenis === "CSR") {
                                return "Saldo CSR: Rp " . number_format((float)$max_mutasi->saldo_csr, 2, ',', '.');
                            } elseif ($jenis === "Umum") {
                                return "Saldo Umum: Rp " . number_format((float)$max_mutasi->saldo_umum, 2, ',', '.');
                            } else {
                                return "Pilih jenis pengeluaran terlebih dahulu.";
                            }
                        } else {
                            if ($jenis === "Keamilan") {
                                return "Saldo Keamilan: Rp " . number_format((float)$max_mutasi->saldo_keamilan, 2, ',', '.');
                            } elseif ($jenis === "CSR") {
                                return "Saldo CSR: Rp " . number_format((float)$max_mutasi->saldo_csr, 2, ',', '.');
                            } elseif ($jenis === "Umum") {
                                return "Saldo Umum: Rp " . number_format((float)$max_mutasi->saldo_umum, 2, ',', '.');
                            } else {
                                return "Pilih jenis pengeluaran terlebih dahulu.";
                            }
                        }
                    })
                    ->rules([

                        fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get, $max_mutasi, $userAuthAdminAccess) {
                            $jenis = $get('jenis');
                            $nilai = str_replace(",", ".", preg_replace('/[^0-9,]/', '', $value));
                            if ($userAuthAdminAccess) {
                                $max_mutasi = Mutasi::where('cabang_id', $get('cabang_id'))->orderBy('id', 'DESC')->first();
                                if ($jenis === 'Keamilan' && $nilai > (float)$max_mutasi->saldo_keamilan) {
                                    $fail("Nilai {$attribute} terlalu besar. Saldo Keamilan tidak cukup.");
                                } elseif ($jenis === 'CSR' && $nilai > (float)$max_mutasi->saldo_csr) {
                                    $fail("Nilai {$attribute} terlalu besar. Saldo CSR tidak cukup.");
                                } elseif ($jenis === 'Umum' && $nilai > (float)$max_mutasi->saldo_umum) {
                                    $fail("Nilai {$attribute} terlalu besar. Saldo Umum tidak cukup.");
                                }
                            } else {
                                if ($jenis === 'Keamilan' && $nilai > (float)$max_mutasi->saldo_keamilan) {
                                    $fail("Nilai {$attribute} terlalu besar. Saldo Keamilan tidak cukup.");
                                } elseif ($jenis === 'CSR' && $nilai > (float)$max_mutasi->saldo_csr) {
                                    $fail("Nilai {$attribute} terlalu besar. Saldo CSR tidak cukup.");
                                } elseif ($jenis === 'Umum' && $nilai > (float)$max_mutasi->saldo_umum) {
                                    $fail("Nilai {$attribute} terlalu besar. Saldo Umum tidak cukup.");
                                }
                            }
                        },

                    ])
                    ->disabled(fn (Get $get) => (($get('jenis') ? false : true)))
                    ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                    ->formatStateUsing(fn ($state) => str_replace(".", ",", $state))
                    ->live(debounce: 300),
                TextInput::make('keterangan')
                    ->required(),
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
                    ->searchable(),
                TextColumn::make('tanggal')
                    ->date()
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->translatedFormat('l, d M Y'))
                    ->searchable(),
                TextColumn::make('nominal')
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    )
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
            'index' => Pages\ListPengeluarans::route('/'),
            'create' => Pages\CreatePengeluaran::route('/create'),
            'view' => Pages\ViewPengeluaran::route('/{record}'),
            'edit' => Pages\EditPengeluaran::route('/{record}/edit'),
        ];
    }
}
