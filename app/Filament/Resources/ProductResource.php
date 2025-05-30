<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Product;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User; // Untuk filter seller
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers\ProductImagesRelationManager;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Manajemen Produk';
    protected static ?string $label = 'Produk';
    protected static ?string $pluralLabel = 'Produk';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $loggedInUser = Auth::user();
        $isAdmin = $loggedInUser && $loggedInUser->hasRole('admin');

        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Informasi Produk Utama')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Produk')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),
                                Forms\Components\TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(Product::class, 'slug', ignoreRecord: true),
                                Forms\Components\MarkdownEditor::make('description')
                                    ->label('Deskripsi Produk')
                                    ->columnSpanFull(),
                            ])->columns(2),
                        Forms\Components\Section::make('Harga dan Stok')
                            ->schema([
                                Forms\Components\TextInput::make('price')
                                    ->label('Harga')
                                    ->required()
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->minValue(0),
                                Forms\Components\TextInput::make('stock')
                                    ->label('Stok')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                            ])->columns(2),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Asosiasi Produk')
                            ->schema([
                                Forms\Components\Select::make('seller_id')
                                    ->label('Penjual (Seller)')
                                    ->relationship(
                                        name: 'seller',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: function (Builder $query) use ($isAdmin, $loggedInUser) {
                                            if ($isAdmin) {
                                            //     // Admin bisa memilih dari semua user atau user dengan peran 'seller'
                                            //     // Contoh: hanya user dengan peran 'seller'
                                            //     // return $query->whereHas('roles', fn ($q) => $q->where('name', 'seller'));
                                            //     return $query; // Admin dapat memilih dari semua user
                                            // }
                                            // // Untuk non-admin, query ini memastikan hanya diri mereka sendiri yang relevan
                                            // // meskipun fieldnya akan di-disable.
                                            return $query->where('id', $loggedInUser ? $loggedInUser->id : null);
                                        }
                                    }
                                    )
                                    ->default(fn () => $loggedInUser ? $loggedInUser->id : null) // Default ke ID user yang login
                                    // ->disabled(!$isAdmin) // Tidak bisa diubah jika bukan admin
                                    ->disabled()
                                    ->dehydrated() // Pastikan nilai ini dikirim saat menyimpan
                                    ->searchable($isAdmin) // Hanya admin yang bisa mencari
                                    ->preload($isAdmin)    // Hanya admin yang bisa preload
                                    ->required(),
                                Forms\Components\Select::make('category_id')
                                    ->label('Kategori')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Forms\Components\Select::make('unit_id')
                                    ->label('Satuan')
                                    ->relationship('unit', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Pilih Satuan (Opsional)'),
                            ]),
                        Forms\Components\Section::make('Status Publikasi')
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Aktif (Ditampilkan di Toko)')
                                    ->default(true)
                                    ->required(),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('images.0.url')->label('Gambar')->disk('public')->defaultImageUrl(url('/placeholder-image.png')), // Ambil gambar pertama
                Tables\Columns\TextColumn::make('name')->label('Nama Produk')->searchable()->sortable()->limit(50),
                Tables\Columns\TextColumn::make('category.name')->label('Kategori')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('seller.name')->label('Penjual')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('price')->label('Harga')->money('idr')->sortable(),
                Tables\Columns\TextColumn::make('stock')->label('Stok')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->label('Terakhir Diubah')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')->relationship('category', 'name')->label('Kategori'),
                Tables\Filters\SelectFilter::make('seller_id')->relationship('seller', 'name')->label('Penjual'),
                Tables\Filters\TernaryFilter::make('is_active')->label('Status Aktif'),
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



    public static function getEloquentQuery(): Builder // Pembatasan data untuk Seller
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user && $user->hasRole('seller')) {
            $query->where('seller_id', $user->id);
        }
        // Admin melihat semua, Buyer tidak melihat resource ini di navigasi

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            ProductImagesRelationManager::class,
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
