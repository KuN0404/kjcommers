<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth as FacadesAuth; // <-- Alias untuk Auth

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Manajemen Akses & Pengguna';
    protected static ?string $label = 'Peran (Role)';
    protected static ?string $pluralLabel = 'Peran (Roles)';
    protected static ?int $navigationSort = 2;

    public static function canViewNavigation(): bool
    {
        return FacadesAuth::user()->hasRole('admin');
    }

    public static function form(Form $form): Form
    {
        $defaultGuardName = Config::get('auth.defaults.guard');

        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Peran')
                    ->minLength(2)
                    ->maxLength(255)
                    ->required()
                    ->unique(table: Role::class, column: 'name', ignoreRecord: true)
                    ->helperText('Nama peran unik, contoh: admin, editor, seller.'),
                Forms\Components\TextInput::make('guard_name')
                    ->label('Nama Guard')
                    ->default($defaultGuardName)
                    ->disabled()
                    ->helperText('Guard name default untuk aplikasi web adalah "web".'),
                Forms\Components\Select::make('permissions')
                    ->label('Izin (Permissions)')
                    ->multiple()
                    ->relationship(name: 'permissions', titleAttribute: 'name')
                    ->options(Permission::pluck('name', 'id'))
                    ->preload()
                    ->helperText('Pilih izin yang akan diberikan untuk peran ini.')
                    ->columnSpanFull()
                    ->Hidden(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nama Peran')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('guard_name')->label('Guard Name')->searchable()->sortable(),
                // Tables\Columns\TextColumn::make('permissions_count')->label('Jumlah Izin')->counts('permissions')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Dibuat Pada')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->label('Diperbarui Pada')->dateTime('d M Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $defaultGuardName = Config::get('auth.defaults.guard');
        return parent::getEloquentQuery()->where('guard_name', $defaultGuardName);
    }
}
