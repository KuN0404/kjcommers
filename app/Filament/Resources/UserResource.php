<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use function Laravel\Prompts\text;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\UserResource\Pages;

use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\UserResource\RelationManagers;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
                    ->required(fn (string $context): bool => $context === 'create')
                    ->dehydrated(fn (string $context): bool => $context === 'create')
                    ->minLength(8)
                    ->maxLength(255),
                TextInput::make('passwordConfirmation')
                    ->password()
                    ->required(fn (string $context): bool => $context === 'create')
                    ->dehydrated(fn (string $context): bool => $context === 'create')
                    ->same('password')
                    ->minLength(8)
                    ->maxLength(255),
                Forms\Components\Select::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'seller' => 'Seller',
                        'buyer' => 'Buyer',
                    ])
                    ->required()
                    ->placeholder('Pilih role')
                    ->searchable()
                    ->reactive()
                    ->columnSpanFull(),
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
                    ->limit(20),
                TextColumn::make('role')
                    ->label('Role')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => ucwords($state)),
                TextColumn::make('created_at')
                    ->label('Dibuat pada')
                    // ->dateTime()
                    // ->description(fn ($state): string => $state->diffForHumans())
                    ->formatStateUsing(fn ($state) => $state?->diffForHumans())
                    ->sortable()
                    ->searchable(),
                    TextColumn::make('updated_at')
                    ->label('Diubah pada')
                    ->formatStateUsing(fn ($state) => $state?->diffForHumans())
                    ->sortable()
                    ->searchable(),


            ])
            ->filters([
                //
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