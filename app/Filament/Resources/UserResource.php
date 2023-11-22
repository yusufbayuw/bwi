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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
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
use Filament\Support\Enums\Alignment;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationGroup = 'Anggota';

    protected static ?string $navigationLabel = 'Anggota';

    protected static ?string $slug = 'anggota';

    public static function form(Form $form): Form
    {
        $userAuth = auth()->user();
        $adminAccess = ['super_admin', 'admin_pusat', 'admin_cabang'];
        $userAuthAdminAccess = $userAuth->hasRole($adminAccess);

        return $form
            ->schema([
                Section::make('Data Diri')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('no_hp')
                            ->label('Nomor HP')
                            ->mask('9999 9999 9999 9999'),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('nomor_ktp')
                            ->label('Nomor KTP')
                            ->maxLength(16)
                            ->mask('9999 9999 9999 9999'),
                        FileUpload::make('file_ktp')
                            ->label('Berkas KTP'),
                        TextInput::make('nomor_kk')
                            ->label('Nomor KK')
                            ->maxLength(16)
                            ->mask('9999 9999 9999 9999'),
                        FileUpload::make('file_kk')
                            ->label('Berkas KK'),
                        Textarea::make('alamat')
                            ->maxLength(255),
                    ]),
                Section::make('Data Penghasilan')
                    ->schema([
                        TextInput::make('pekerjaan')
                            ->maxLength(255),
                        TextInput::make('penghasilan_bulanan')
                            ->maxLength(255),
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
                            ->default(500000),
                    ]),
                Section::make()
                    ->schema([
                        TextInput::make('username')
                            ->maxLength(255)
                            ->hidden(!($userAuthAdminAccess)),
                        Select::make('cabang_id')
                            ->relationship('cabangs', 'nama_cabang')
                            ->disabledOn(($userAuthAdminAccess) ? '' : ['create', 'edit'])
                            ->hidden(!($userAuthAdminAccess)),
                        TextInput::make('password')
                            ->password()
                            ->required(!($userAuthAdminAccess))
                            ->hidden(!($userAuthAdminAccess))
                            ->maxLength(255),
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
        $adminAccess = ['super_admin', 'admin_pusat', 'admin_cabang'];
        $userAuthAdminAccess = $userAuth->hasRole($adminAccess);

        return $table
            ->columns([
               TextColumn::make('no')
                    ->rowIndex(isFromZero: false),
                TextColumn::make('cabangs.nama_cabang')
                    ->numeric()
                    ->sortable()
                    ->hidden(!($userAuthAdminAccess)),
                TextColumn::make('pinjamans.nama_kelompok')
                    ->label('Kelompok')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('bmpa')
                    ->label('BMPA')
                    ->alignment(Alignment::End)
                    ->searchable()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                TextColumn::make('email')
                    ->searchable()
                    ->hidden(!($userAuthAdminAccess)),
                TextColumn::make('username')
                    ->searchable()
                    ->hidden(!($userAuthAdminAccess)),
                TextColumn::make('no_hp')
                    ->label('Nomor HP')
                    ->searchable(),
                TextColumn::make('nomor_ktp')
                    ->label('Nomor KTP')
                    ->searchable(),
                ImageColumn::make('file_ktp')
                    ->label('Berkas KTP'),
                TextColumn::make('nomor_kk')
                    ->label('Nomor KK')
                    ->searchable(),
                ImageColumn::make('file_kk')
                    ->label('Berkas KK'),
                TextColumn::make('alamat')
                    ->searchable()
                    ->limit(20),
                TextColumn::make('pekerjaan')
                    ->searchable(),
                TextColumn::make('penghasilan_bulanan')
                    ->label('Penghasilan')
                    ->searchable()
                    ->sortable()
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    ),
                IconColumn::make('is_kelompok')
                    ->boolean()
                    ->hidden(!($userAuthAdminAccess)),
                IconColumn::make('is_can_login')
                    ->boolean()
                    ->hidden(!($userAuthAdminAccess)),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
