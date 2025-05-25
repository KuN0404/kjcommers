<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Resources\Resource;
use function Laravel\Prompts\text;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Filters\TernaryFilter;
use App\Filament\Resources\UserResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\UserResource\RelationManagers;

class UserResource extends Resource
{
    protected static ?string $navigationLabel = 'Kelola Pengguna';

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255)
                    ->autofocus()
                    ->placeholder('Masukkan nama lengkap pengguna'),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(table: User::class, ignorable: fn ($record): ?User => $record)
                    ->maxLength(255)
                    ->placeholder('Masukkan email pengguna'),
                TextInput::make('password')
                    ->password()
                    // Hanya required saat membuat user baru
                    ->required(fn (string $operation): bool => $operation === 'create')
                    // Hanya kirim ke backend jika diisi (untuk update) atau saat create
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn ($state) => filled($state)) // Hanya dehydrate jika diisi
                    ->minLength(8)
                    ->maxLength(255)
                    ->placeholder('Kosongkan jika tidak ingin mengubah password'),
                TextInput::make('passwordConfirmation')
                    ->password()
                     // Hanya required saat membuat user baru atau jika password diisi
                    ->required(fn (string $operation, Forms\Get $get): bool => $operation === 'create' || filled($get('password')))
                    ->dehydrated(false) // Tidak perlu disimpan ke database
                    ->same('password')
                    ->label('Konfirmasi Password'),
                Select::make('roles') // Ubah ke 'roles' (plural) untuk konsistensi dengan Spatie
                    ->label('Role')
                    //->multiple() // Jika user bisa punya banyak role
                    ->relationship(titleAttribute: 'name') // Gunakan relationship untuk Spatie
                    ->options(Role::all()->pluck('name', 'id')) // Gunakan ID sebagai key untuk relationship
                    ->preload() // Preload role, karena jumlahnya biasanya tidak banyak
                    ->searchable()
                    // ->default(fn ($record) => $record?->roles->pluck('id')->all() ?? []) // Untuk multiple
                    ->default(fn ($record) => $record?->roles->first()?->id ?? null) // Jika hanya 1 role yg mau diedit disini, dan Select tidak multiple
                    ->required(), // Sesuaikan jika user wajib punya role
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->onIcon('heroicon-m-check-badge')
                    ->offIcon('heroicon-m-x-circle'),
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => ucwords($state))
                    ->tooltip('Nama lengkap pengguna')
                    ->limit(20),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->limit(20)
                    ->copyable()
                    ->copyMessage('Email disalin!'),
                TextColumn::make('roles.name') // Akses nama role melalui relasi
                    ->label('Role')
                    ->badge() // Tampilkan sebagai badge
                    ->formatStateUsing(fn ($state) => Str::title($state))
                    ->listWithLineBreaks() // jika ada multiple roles
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->searchable()
                    ->sortable(),
                BooleanColumn::make('is_active') // atau IconColumn
                    ->label('Active')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dibuat pada')
                    ->formatStateUsing(fn ($state) => $state?->translatedFormat('d M Y, H:i')) // Format lebih baik
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Sembunyikan defaultnya
                TextColumn::make('updated_at')
                    ->label('Diubah pada')
                    ->formatStateUsing(fn ($state) => $state?->translatedFormat('d M Y, H:i'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Sembunyikan defaultnya
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->label('Filter Role'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('roles');
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
