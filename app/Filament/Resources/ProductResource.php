<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\Product;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\BooleanColumn;
use App\Filament\Resources\ProductResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\ProductResource\RelationManagers;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Produk';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('seller_id')
                    ->relationship('seller', 'name')
                    ->required()

                    ->label('Seller'),
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required()
                    ->label('Category'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord:true)
                    ->maxLength(255),
                Textarea::make('description'),
                TextInput::make('price')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->step(0.01),
                TextInput::make('stock')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                ->sortable(),
                TextColumn::make('seller.name')
                ->label('Seller')
                ->searchable(),
                TextColumn::make('category.name')
                ->label('Category')
                ->searchable(),
                TextColumn::make('name')
                ->searchable()
                ->sortable(),
                TextColumn::make('slug')
                ->sortable(),
                TextColumn::make('price')
                ->money('Rp', divideBy: TRUE),
                TextColumn::make('stock')
                ->sortable(),
                BooleanColumn::make('is_active')
                ->label('Active')
                ->sortable(),
                TextColumn::make('created_at')
                ->dateTime()
                ->sortable(),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
