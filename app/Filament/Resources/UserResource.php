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
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\Fieldset;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section as ComponentsSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
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
        $adminRoles = config('bwi.adminAccess');
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
        $adminAccess = config('bwi.adminAccess');
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
                        Select::make('jenis_anggota')
                            ->required()
                            ->live()
                            ->label('Jenis Anggota')
                            ->options([
                                "Anggota" => "Anggota",
                                "Donatur" => "Donatur"
                            ]),
                        TextInput::make('no_hp')
                            ->label('Nomor HP')
                            ->mask('9999 9999 9999 9999')
                            ->dehydrateStateUsing(function ($state) {

                                $phoneNumber = preg_replace('/\D/', '', $state);

                                if (substr($phoneNumber, 0, 1) == '0') {
                                    $phoneNumber = '62' . substr($phoneNumber, 1);
                                } elseif (substr($phoneNumber, 0, 1) == '8') {
                                    $phoneNumber = '62' . $phoneNumber;
                                }

                                return $phoneNumber;
                            }),
                        TextInput::make('email')
                            ->email()
                            ->hidden(!($userAuthAdminAccess))
                            ->maxLength(255),
                        TextInput::make('nomor_ktp')
                            ->required(fn (Get $get) => $get('jenis_anggota') === 'Anggota' && !$userAuthAdminAccess)
                            ->unique(ignoreRecord: true)
                            ->label('Nomor KTP')
                            ->mask('9999 9999 9999 9999'),
                        FileUpload::make('file_ktp')
                            ->required(fn (Get $get) => $get('jenis_anggota') === 'Anggota' && !$userAuthAdminAccess)
                            ->label('Berkas KTP'),
                        TextInput::make('nomor_kk')
                            ->required(fn (Get $get) => $get('jenis_anggota') === 'Anggota' && !$userAuthAdminAccess)
                            ->unique(ignoreRecord: true)
                            ->label('Nomor KK')
                            ->mask('9999 9999 9999 9999'),
                        FileUpload::make('file_kk')
                            ->required(fn (Get $get) => $get('jenis_anggota') === 'Anggota' && !$userAuthAdminAccess)
                            ->label('Berkas KK'),
                        Textarea::make('alamat')
                            ->maxLength(255),
                    ]),
                Section::make('DATA PENDAPATAN')
                    ->schema([
                        TextInput::make('pekerjaan')
                            ->maxLength(255),
                        TextInput::make('penghasilan_bulanan')
                            ->label('Pendapatan Bulanan Keluarga (Rata-rata)')
                            ->required(fn (Get $get) => $get('jenis_anggota') === 'Anggota' && !$userAuthAdminAccess)
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
                        //Toggle::make('is_can_login')
                        //    ->hidden(!($userAuthAdminAccess)),
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
        $adminAccess = config('bwi.adminAccess');
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
                TextColumn::make('roles.name')
                    ->badge()
                    ->hidden(!($userAuthAdminAccess)),
                TextColumn::make('alamat')
                    ->searchable()
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.')),

                TextColumn::make('email')
                    ->searchable()
                    ->icon('heroicon-m-envelope')
                    //->hidden(!($userAuthAdminAccess))
                    ->copyable(),
                TextColumn::make('username')
                    ->searchable()
                    ->hidden(!($userAuthAdminAccess))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('no_hp')
                    ->label('Nomor HP')
                    ->icon('heroicon-m-phone')
                    ->url(fn ($state) => ($state ? "https://wa.me/" . $state : ""), true)
                    ->searchable(),
                TextColumn::make('nomor_ktp')
                    ->label('Nomor KTP')
                    ->icon('heroicon-m-identification')
                    //->formatStateUsing(fn ($state) => 'NIK: ' . $state)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ImageColumn::make('file_ktp')
                    ->label('Berkas KTP')
                    ->simpleLightbox()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nomor_kk')
                    ->label('Nomor KK')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ImageColumn::make('file_kk')
                    ->label('Berkas KK')
                    ->simpleLightbox()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pekerjaan')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('penghasilan_bulanan')
                    ->label('Pendapatan')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.'))
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                /* IconColumn::make('is_kelompok')
                                ->boolean()
                                ->hidden(!($userAuthAdminAccess)),
                            IconColumn::make('is_can_login')
                                ->boolean()
                                ->hidden(!($userAuthAdminAccess)), */

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
                Tables\Actions\Action::make('aturBMPA')
                    ->label('BMPA')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->modalHeading(fn (User $user) => 'Apakah BMPA '.$user->name.' akan dinaikkan?')
                    ->modalDescription(fn (User $user) => 'BMPA saat ini '.number_format($user->bmpa, 0, ',', '.').', jika dinaikkan maka BMPA akan menjadi '.number_format((float)$user->bmpa+500000, 0, ',', '.').'. Pilih NAIKKAN untuk menaikkan BMPA, atau TOLAK jika ingin BMPA tidak berubah. Selanjutnya klik Kirim untuk menyimpan perubahan, atau klik Batal jika tidak ingin mengubah apa pun.')
                    ->form([
                        ToggleButtons::make('bmpa_management')
                            ->options([
                                '2' => 'NAIKKAN',
                                '1' => 'TOLAK',
                            ])
                            ->icons([
                                '2' => 'heroicon-o-check',
                                '1' => 'heroicon-o-x-mark',
                            ])
                            ->colors([
                                '2' => 'success',
                                '1' => 'danger',
                            ])
                            ->required()
                            ->inline()
                            ->extraAttributes(['class' => 'flex justify-center'])
                            ->label('')
                            ->hiddenLabel(true),
                    ])
                    ->modalIcon('heroicon-o-arrow-trending-up')
                    ->modalAlignment(Alignment::Center)
                    ->action(function (array $data, User $user): void {
                        if ($data['bmpa_management'] == '2') {
                            $user->bmpa_gain_counter = $user->bmpa_gain_counter - 1;
                            $user->bmpa = (float)$user->bmpa + 500000;
                            $user->save();
                        } elseif ($data['bmpa_management'] == '1') {
                            $user->bmpa_gain_counter = $user->bmpa_gain_counter - 1;
                            $user->save();
                        }
                    })->hidden(fn (User $user) => ($user->bmpa_gain_counter > 0) ? false : true),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                ExportBulkAction::make()->hidden(!$userAuth->hasRole(config('bwi.adminAccess'))),
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
        $userAuth = auth()->user();
        $adminAccess = config('bwi.adminAccess');
        $userAuthAdminAccess = $userAuth->hasRole($adminAccess);

        return $infolist
            ->schema([
                ComponentsSection::make('IDENTITAS')
                    ->columns([
                        'sm' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Fieldset::make('Data Diri')
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nama'),
                                TextEntry::make('no_hp')
                                    ->label('Nomor HP')
                                    ->icon('heroicon-m-phone')
                                    ->url(fn ($state) => ($state ? "https://wa.me/" . $state : ""), true),

                                TextEntry::make('email')
                                    ->icon('heroicon-m-envelope')
                                    ->hidden(!($userAuthAdminAccess)),
                                TextEntry::make('username')
                                    ->hidden(!($userAuthAdminAccess)),
                                TextEntry::make('alamat')->columnSpanFull(),

                            ]),
                        Fieldset::make('Berkas')
                            ->schema([
                                TextEntry::make('nomor_ktp')
                                    ->label('Nomor KTP')
                                    ->icon('heroicon-m-identification'),
                                ImageEntry::make('file_ktp')
                                    ->label('Berkas KTP')
                                    ->simpleLightbox(),
                                TextEntry::make('nomor_kk')
                                    ->label('Nomor KK'),
                                ImageEntry::make('file_kk')
                                    ->label('Berkas KK')
                                    ->simpleLightbox(),
                            ]),
                        Fieldset::make('Pendapatan')
                            ->schema([
                                TextEntry::make('pekerjaan'),
                                TextEntry::make('penghasilan_bulanan')
                                    ->label('Pendapatan')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.')),
                            ])
                    ]),
                ComponentsSection::make('ORGANISASI')
                    ->columns([
                        'sm' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextEntry::make('cabangs.nama_cabang')
                            ->icon('heroicon-m-building-office')
                            ->label('Nama Cabang')
                            ->hidden(!($userAuthAdminAccess)),
                        TextEntry::make('pinjamans.nama_kelompok')
                            ->icon('heroicon-m-user-group')
                            ->label('Nama Kelompok'),
                        TextEntry::make('roles.name')
                            ->label('Jabatan')
                            ->badge(),
                        TextEntry::make('bmpa')
                            ->label('Nilai BMPA')
                            ->badge()
                            ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.')),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
