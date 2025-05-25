<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\Product;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn; // Tambahkan ini
use Filament\Forms\Components\FileUpload; // Tambahkan ini
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Forms\Set;
use App\Filament\Resources\ProductResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\ProductResource\RelationManagers;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Produk';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Informasi Produk')
                            ->schema([
                                Select::make('seller_id')
                                    // ... (konfigurasi seller_id) ...
                                    ->label('Penjual')
                                    ->relationship(
                                        name: 'seller',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query) => $query->role('seller')
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('category_id')
                                    // ... (konfigurasi category_id) ...
                                    ->relationship('category', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->label('Kategori'),
                                TextInput::make('name')
                                    // ... (konfigurasi name) ...
                                    ->label('Nama Produk')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),
                                TextInput::make('slug')
                                    // ... (konfigurasi slug) ...
                                    ->required()
                                    ->unique(Product::class, 'slug', ignoreRecord: true)
                                    ->maxLength(255)
                                    ->disabled()
                                    ->dehydrated(),
                                Textarea::make('description')
                                    // ... (konfigurasi description) ...
                                    ->label('Deskripsi')
                                    ->rows(5)
                                    ->columnSpanFull(),
                            ])->columns(2),

                        Forms\Components\Section::make('Gambar Produk') // Section baru untuk gambar
                            ->schema([
                                Forms\Components\Repeater::make('productImages')
                                    ->relationship() // Ini akan menghubungkan ke relasi productImages() di model Product
                                    ->label('Gambar')
                                    ->schema([
                                        FileUpload::make('url')
                                            ->label('Upload Gambar')
                                            ->disk('public') // Pastikan disk 'public' terkonfigurasi dan di-link
                                            ->directory('product-images') // Direktori penyimpanan
                                            ->image() // Menandakan ini adalah upload gambar (untuk validasi & preview)
                                            ->imageEditor() // Opsional: aktifkan editor gambar bawaan Filament
                                            ->maxSize(2048) // Opsional: batas ukuran file dalam KB
                                            ->required()
                                            ->columnSpan(2), // Lebar kolom untuk FileUpload
                                        TextInput::make('alt_text')
                                            ->label('Teks Alternatif (SEO)')
                                            ->maxLength(255),
                                        TextInput::make('sort_order')
                                            ->label('Urutan')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0),
                                    ])
                                    ->columns(2) // Jumlah kolom di dalam setiap item repeater
                                    ->columnSpanFull()
                                    ->addActionLabel('Tambah Gambar')
                                    ->reorderableWithButtons() // Memungkinkan pengurutan item
                                    ->collapsible() // Item repeater bisa di-collapse
                                    ->itemLabel(fn (array $state): ?string => $state['alt_text'] ?? 'Gambar Baru') // Label untuk setiap item
                                    ->deleteAction( // Tambahkan konfirmasi saat menghapus
                                        fn (Forms\Components\Actions\Action $action) => $action->requiresConfirmation(),
                                    )
                                    ->maxItems(5), // Opsional: Batasi jumlah maksimal gambar
                            ])->collapsible(),

                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make() // Grup untuk Harga & Stok & Status
                    ->schema([
                        Forms\Components\Section::make('Harga & Stok')
                            ->schema([
                                TextInput::make('price')
                                    ->label('Harga')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->required()
                                    ->minValue(0)
                                    ->formatStateUsing(function (mixed $state): ?string {
                                        if ($state === null || (is_string($state) && trim($state) === '')) {
                                            return null;
                                        }

                                        $value = filter_var($state, FILTER_VALIDATE_FLOAT);

                                        if ($value === false) {
                                            return null;
                                        }

                                        $formatted = number_format($value, 0, ',', '.');
                                        return $formatted;
                                    })
                                    ->dehydrateStateUsing(function (mixed $state): ?string {
                                        if ($state === null || (is_string($state) && trim($state) === '')) {
                                            return null;
                                        }

                                        $cleaned = preg_replace('/[^0-9]/', '', (string)$state);

                                        if ($cleaned === '') {
                                            return null;
                                        }
                                        return $cleaned;
                                    }),
                                TextInput::make('stock')
                                    // ... (konfigurasi stok) ...
                                     ->label('Stok')
                                     ->numeric()
                                     ->required()
                                     ->minValue(0)
                                     ->default(0),
                            ]),
                        Forms\Components\Section::make('Status')
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Aktif')
                                    ->default(true)
                                    ->onIcon('heroicon-m-check-badge')
                                    ->offIcon('heroicon-m-x-circle'),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('productImages.0.full_url') // Menggunakan accessor full_url dari ProductImage
                    ->label('Gambar Utama')
                    ->disk('public') // Tidak perlu disk lagi jika full_url sudah menyediakannya, tapi bisa jadi fallback
                    ->defaultImageUrl(url('/images/placeholder-image.webp')) // Sediakan placeholder jika tidak ada gambar
                    ->width(60) // Atur lebar
                    ->height(60) // Atur tinggi
                    ->circular() // Tampilkan sebagai lingkaran
                    ->toggleable(),
                TextColumn::make('name')
                    // ... (konfigurasi nama) ...
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->tooltip(fn (Product $record): string => $record->name),
                TextColumn::make('seller.name')
                    // ... (konfigurasi seller) ...
                    ->label('Pembuat')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->formatStateUsing(fn (?string $state): string => $state ? Str::title($state) : ''),
                TextColumn::make('category.name')
                    // ... (konfigurasi kategori) ...
                    ->label('Kategori')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                TextColumn::make('price')
                    // ... (konfigurasi harga) ...
                    ->label('Harga')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('stock')
                    // ... (konfigurasi stok) ...
                    ->label('Stok')
                    ->sortable()
                    ->alignCenter(),
                BooleanColumn::make('is_active')
                    // ... (konfigurasi is_active) ...
                    ->label('Aktif')
                    ->sortable(),
                // ... (kolom created_at, updated_at) ...
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // ... (filters, actions, bulkActions tetap sama) ...
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->label('Filter Kategori'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
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
            ->defaultSort('created_at', 'desc');
    }

    // ... (getRelations, getPages tetap sama) ...
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
