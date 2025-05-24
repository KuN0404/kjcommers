<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Category;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\CategoryResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\CategoryResource\RelationManagers;

class CategoryResource extends Resource
{
    protected static ?string $navigationLabel = 'Kategori';

    protected static ?int $navigationSort = 1;
    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                ->label('Nama Kategori')
                ->required()
                ->maxLength(255)
                ->autofocus()
                ->placeholder('Masukkan nama kategori'),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Masukkan slug kategori')
                    ->unique(Category::class, 'slug', ignoreRecord: true)
                    ->autofocus(),
                Select::make('parent_id')
                    ->label('Induk Kategori')
                    ->options(Category::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Status Aktif')
                    ->default(true)
                    ->inline()
                    ->helperText('Tandai jika kategori ini aktif'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Kategori')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
                TextColumn::make('parent.name')
                    ->label('Induk Kategori')
                    ->sortable()
                    ->toggleable()
                    ->wrap(),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => $state ? 'Aktif' : 'Tidak Aktif')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->formatStateUsing(fn ($state) => $state?->diffForHumans())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
                TextColumn::make('updated_at')
                    ->label('Diperbarui Pada')
                    ->formatStateUsing(fn ($state) => $state?->diffForHumans())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'view' => Pages\ViewCategory::route('/{record}'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}