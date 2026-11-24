<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LaporanResource\Pages;
use App\Filament\Resources\LaporanResource\RelationManagers;
use App\Models\Laporan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LaporanResource extends Resource
{
    protected static ?string $model = Laporan::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    //protected static ?string $navigationGroup = 'Catatan';
    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'laporan';

    public static function getEloquentQuery(): Builder
    {
        $userAuth = auth()->user();
        if ($userAuth->hasRole(config('bwi.adminAccess'))) {
            return parent::getEloquentQuery()->orderBy('tanggal', 'desc');
        } else {
            return parent::getEloquentQuery()->where('cabang_id', $userAuth->cabang_id)->orderBy('tanggal', 'desc');
        }
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('cabang_id')
                    ->numeric(),
                Forms\Components\DatePicker::make('tanggal')
                    ->required(),
                Forms\Components\TextInput::make('jenis')
                    ->required()
                    ->maxLength(255),
                \App\Filament\Forms\Components\SecureAttachmentUpload::make('berkas')->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cabangs.nama_cabang')
                    ->limit(25)
                    ->label('Cabang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('jenis')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tanggal')
                    ->date('M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('berkas')
                    ->formatStateUsing(fn ($state) => (explode('.', $state)[1] ?? 'file'))
                    ->url(fn (Laporan $record) => route('bwi.laporan.download', ['laporan' => $record->id]), true),
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
                Tables\Actions\EditAction::make()
                    ->visible(fn (Laporan $record): bool => ! $record->isSystemGenerated()),
            ])
            ->bulkActions([
                // Bulk removal of generated reports is forbidden.
                // Non-generated records can still use the existing edit screen.
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
            'index' => Pages\ListLaporans::route('/'),
            'create' => Pages\CreateLaporan::route('/create'),
            'edit' => Pages\EditLaporan::route('/{record}/edit'),
        ];
    }
}
