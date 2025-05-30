<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers\OrderItemsRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\PaymentRelationManager; // Tambah ini
use App\Models\Order;
use App\Models\UserAddress;
use App\Models\Product; // Untuk kalkulasi total
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Manajemen Pesanan';
    protected static ?string $label = 'Pesanan';
    protected static ?string $pluralLabel = 'Pesanan';
    protected static ?int $navigationSort = 1;

  public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    // ... Step 'Informasi Pembeli & Pengiriman' ...
                    Forms\Components\Wizard\Step::make('Informasi Pembeli & Pengiriman')
                        ->schema([
                            Forms\Components\Select::make('buyer_id')
                                ->label('Pembeli')
                                // ->relationship('buyer', 'name'
                                    ->relationship(
                                name: 'buyer', // Nama relasi di model Order
                                titleAttribute: 'name', // Atribut yang ditampilkan di dropdown
                                // --- AWAL MODIFIKASI UNTUK FILTER BUYER ---
                                modifyQueryUsing: fn (Builder $query) => $query->whereHas('roles', function ($subQuery) {
                                    $subQuery->where('name', 'buyer'); // Hanya user dengan role 'buyer'
                                })
                                // --- AKHIR MODIFIKASI ---
                            )
                                ->searchable()
                                ->preload()
                                ->live()
                                ->required()
                                ->columnSpanFull(),
                            Forms\Components\Select::make('shipping_address_id')
                                ->label('Alamat Pengiriman')
                                ->options(function (Get $get) {
                                    $buyerId = $get('buyer_id');
                                    if ($buyerId) {
                                        return UserAddress::where('user_id', $buyerId)->get()->mapWithKeys(function ($address) {
                                            return [$address->id => "{$address->label} - {$address->recipient_name}, {$address->address_line1}, {$address->city_regency}"];
                                        });
                                    }
                                    return [];
                                })
                                ->searchable()
                                ->live()
                                ->required(fn (Get $get) => filled($get('buyer_id')))
                                ->disabled(fn (Get $get) => !filled($get('buyer_id')))
                                ->helperText('Pilih pembeli terlebih dahulu untuk memuat alamat.'),
                            Forms\Components\Select::make('shipping_courier_id')
                                ->label('Jasa Pengiriman')
                                ->relationship('shippingCourier', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                            Forms\Components\TextInput::make('shipping_cost')
                                ->label('Biaya Pengiriman')
                                ->numeric()
                                ->prefix('Rp')
                                ->live(onBlur: true)
                                ->default(0.00)
                                ->required(),
                            Forms\Components\TextInput::make('shipping_tracking_number')
                                ->label('Nomor Resi Pengiriman (Opsional)')
                                ->maxLength(255),
                        ])->columns(2),
                    Forms\Components\Wizard\Step::make('Detail Pesanan')
                        ->schema([
                             Forms\Components\TextInput::make('order_number')
                                ->label('Nomor Pesanan')
                                ->disabled() // Dibuat otomatis oleh model
                                ->placeholder('Akan dibuat otomatis')
                                ->dehydrated(false) // Jangan kirim nilai dari field ini saat create
                                // ->required() // Hapus required dari form
                                ->maxLength(255),
                            Forms\Components\Select::make('status')
                                ->label('Status Pesanan')
                                ->options([
                                    'pending' => 'Pending (Menunggu Pembayaran)',
                                    'processing' => 'Diproses (Pembayaran Diterima)',
                                    'shipped' => 'Dikirim',
                                    'completed' => 'Selesai (Diterima Pembeli)',
                                    'cancelled' => 'Dibatalkan',
                                    'refunded' => 'Dikembalikan (Refund)',
                                ])
                                ->default('pending')
                                ->required(),
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Harga Produk (Subtotal)')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled() // Tetap disabled karena dihitung dari item
                            ->default(0.00) // Untuk tampilan awal di form
                            // ->required() // Bisa dihapus karena model akan mengisi default 0.00
                            ->dehydrated(fn (string $operation): bool => $operation === 'edit'),
                            Forms\Components\Placeholder::make('grand_total_placeholder')
                                ->label('Grand Total (Termasuk Ongkir)')
                                ->content(function (Get $get): string {
                                    $totalAmount = (float) $get('total_amount');
                                    $shippingCost = (float) $get('shipping_cost');
                                    return 'Rp ' . number_format($totalAmount + $shippingCost, 2, ',', '.');
                                }),
                        ])->columns(2),
                ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->label('No. Pesanan')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('buyer.name')->label('Pembeli')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'shipped' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    })->sortable(),
                Tables\Columns\TextColumn::make('total_amount')->label('Subtotal Produk')->money('idr')->sortable(),
                Tables\Columns\TextColumn::make('shipping_cost')->label('Ongkir')->money('idr')->sortable(),
                Tables\Columns\TextColumn::make('grand_total') // Menggunakan accessor
                    ->label('Grand Total')
                    ->money('idr')
                    ->getStateUsing(fn (Order $record): float => $record->grand_total) // Panggil accessor
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Tanggal Pesan')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Diproses',
                        'shipped' => 'Dikirim',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                        'refunded' => 'Dikembalikan',
                    ])->label('Status Pesanan'),
                Tables\Filters\SelectFilter::make('buyer_id')->relationship('buyer', 'name')->label('Pembeli'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
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
            OrderItemsRelationManager::class,
            PaymentRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}