<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Support\RawJs;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Support\Enums\Alignment;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\CheckboxList;
use App\Filament\Resources\UserResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\UserResource\RelationManagers;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class UserResource extends Resource
{
    const SUPER_ADMIN_ROLE = 'super_admin';
    const ADMIN_PUSAT_ROLE = 'admin_pusat';

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?int $navigationSort = 35;

    protected static ?string $navigationLabel = 'Anggota';

    protected static ?string $slug = 'anggota';

    protected static ?string $modelLabel = 'anggota';

    protected function hasAdminAccess(): bool
    {
        $adminRoles = [self::SUPER_ADMIN_ROLE, self::ADMIN_PUSAT_ROLE];
        return auth()->user()->hasRole($adminRoles);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->when(
            !(new self())->hasAdminAccess(),
            fn ($query) => $query->where('cabang_id', auth()->user()->cabang_id)
        );
    }

    public static function form(Form $form): Form
    {
        $userAuth = auth()->user();
        $adminAccess = [self::SUPER_ADMIN_ROLE, self::ADMIN_PUSAT_ROLE];
        $userAuthAdminAccess = $userAuth->hasRole($adminAccess);
      
        return $form
            ->schema([
                Section::make('DATA DIRI')
                    ->schema([
                        ($userAuthAdminAccess) ? Select::make('cabang_id')
                            ->label('Cabang')
                            ->relationship('cabangs', 'nama_cabang') :
                            Hidden::make('cabang_id')
                            ->default($userAuth->cabang_id)
                            ->dehydrateStateUsing(fn () => $userAuth->cabang_id),
                        TextInput::make('name')
                            ->required()
                            ->label('Nama Lengkap')
                            ->maxLength(255),
                        TextInput::make('no_hp')
                            ->label('Nomor HP')
                            ->mask('9999 9999 9999 9999'),
                        TextInput::make('email')
                            ->email()
                            ->hidden(!($userAuthAdminAccess))
                            ->maxLength(255),
                        TextInput::make('nomor_ktp')
                            ->required()
                            ->unique()
                            ->label('Nomor KTP')
                            ->mask('9999 9999 9999 9999'),
                        FileUpload::make('file_ktp')
                            ->required()
                            ->label('Berkas KTP'),
                        TextInput::make('nomor_kk')
                            ->required()
                            ->unique()
                            ->label('Nomor KK')
                            ->mask('9999 9999 9999 9999'),
                        FileUpload::make('file_kk')
                            ->required()
                            ->label('Berkas KK'),
                        Textarea::make('alamat')
                            ->maxLength(255),
                    ]),
                Section::make('DATA PENGHASILAN')
                    ->schema([
                        TextInput::make('pekerjaan')
                            ->maxLength(255),
                        TextInput::make('penghasilan_bulanan')
                            ->label('Penghasilan Bulanan')
                            ->mask(RawJs::make(<<<'JS'
                                $money($input, ',', '.', 2)
                            JS))
                            ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                            ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
                    ]),
                Section::make('Kelompok Pinjaman')
                    ->schema([
                        Toggle::make('is_kelompok')
                            ->live(debounce: 500)
                            ->disabled()
                            ->label('Tergabung Kelompok Peminjam'),
                        Select::make('pinjaman_id')
                            ->label('Kelompok Pinjaman')
                            ->relationship('pinjamans', 'nama_kelompok')
                            ->disabled(fn (Get $get) => !($get('is_kelompok'))),
                        TextInput::make('bmpa')
                            ->mask(RawJs::make(<<<'JS'
                               $money($input, ',', '.', 2)
                            JS))
                            ->disabled()
                            ->default(500000)
                            ->dehydrateStateUsing(fn ($state) => str_replace(",", ".", preg_replace('/[^0-9,]/', '', $state)))
                            ->formatStateUsing(fn ($state) => str_replace(".", ",", $state)),
                    ]),
                Section::make('ADMIN SETTING')
                    ->schema([
                        TextInput::make('username')
                            ->maxLength(255)
                            ->hidden(!($userAuthAdminAccess)),
                        TextInput::make('password')
                            ->password()
                            ->required(!($userAuthAdminAccess))
                            ->hidden(!($userAuthAdminAccess))
                            ->maxLength(255)
                            ->dehydrateStateUsing(static fn (null|string $state): null|string => filled($state) ? Hash::make($state) : null,)
                            ->dehydrated(static fn (null|string $state): bool => filled($state)),
                        Toggle::make('is_can_login')
                            ->hidden(!($userAuthAdminAccess)),
                        CheckboxList::make('roles')
                            ->relationship('roles', 'name')
                            ->searchable()
                            ->hidden(!($userAuthAdminAccess)),
                    ])
                    ->hidden(!($userAuthAdminAccess)),

            ]);
    }

    public static function table(Table $table): Table
    {
        $userAuth = auth()->user();
        $adminAccess = [self::SUPER_ADMIN_ROLE, self::ADMIN_PUSAT_ROLE];
        $userAuthAdminAccess = $userAuth->hasRole($adminAccess);

        return $table
            ->columns([

                $userAuthAdminAccess ? TextColumn::make('id')
                    ->label('ID') : TextColumn::make('no')
                    ->rowIndex(isFromZero: false),

                TextColumn::make('name')
                    ->label('Nama')
                    ->weight(FontWeight::Bold)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('alamat')
                    ->searchable()
                    ->limit(20),

                TextColumn::make('cabangs.nama_cabang')
                    ->numeric()
                    ->icon('heroicon-m-building-office')
                    ->sortable()
                    ->hidden(!($userAuthAdminAccess)),
                TextColumn::make('pinjamans.nama_kelompok')
                    ->label('Kelompok')
                    ->icon('heroicon-m-user-group')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('bmpa')
                    ->label('BMPA')
                    //->alignment(Alignment::End)
                    ->searchable()
                    ->badge()
                    ->formatStateUsing(fn ($state) => 'BMPA: ' . number_format($state, 2, ',', '.')),

                TextColumn::make('email')
                    ->searchable()
                    ->icon('heroicon-m-envelope')
                    ->hidden(!($userAuthAdminAccess)),
                TextColumn::make('username')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => 'Username: ' . $state)
                    ->hidden(!($userAuthAdminAccess)),
                TextColumn::make('no_hp')
                    ->label('Nomor HP')
                    ->icon('heroicon-m-phone')
                    ->searchable(),
                TextColumn::make('nomor_ktp')
                    ->label('Nomor KTP')
                    ->icon('heroicon-m-identification')
                    //->formatStateUsing(fn ($state) => 'NIK: ' . $state)
                    ->searchable(),
                TextColumn::make('nomor_kk')
                    ->label('Nomor KK')
                    //->formatStateUsing(fn ($state) => 'No. KK: ' . $state)
                    ->searchable(),
                TextColumn::make('pekerjaan')
                    ->formatStateUsing(fn ($state) => 'Pekerjaan: ' . $state)
                    ->searchable(),
                TextColumn::make('penghasilan_bulanan')
                    ->label('Penghasilan')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn ($state) => 'Penghasilan: ' . number_format($state, 2, ',', '.')),
                /* IconColumn::make('is_kelompok')
                                ->boolean()
                                ->hidden(!($userAuthAdminAccess)),
                            IconColumn::make('is_can_login')
                                ->boolean()
                                ->hidden(!($userAuthAdminAccess)), */

                ImageColumn::make('file_ktp')
                    ->label('Berkas KTP'),
                ImageColumn::make('file_kk')
                    ->label('Berkas KK'),



                /* TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true), */
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                ExportBulkAction::make()->hidden(!$userAuth->hasRole([self::SUPER_ADMIN_ROLE, self::ADMIN_PUSAT_ROLE])),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
