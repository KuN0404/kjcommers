<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Order;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\UserAddress;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Product; // Untuk kalkulasi total
use Filament\Notifications\Notification; // <-- Tambahkan ini
use Filament\Tables\Actions\Action; // <-- Tambahkan ini
use App\Filament\Resources\OrderResource\RelationManagers\OrderItemsRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\PaymentRelationManager;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Manajemen Pesanan';
    protected static ?string $label = 'Pesanan';
    protected static ?string $pluralLabel = 'Pesanan';
    protected static ?int $navigationSort = 1;


    public static function canViewNavigation(): bool // Kontrol visibilitas menu
    {
        // Admin, Seller, dan Buyer bisa melihat menu Pesanan
        return Auth::user()->hasAnyRole(['admin', 'seller', 'buyer']);
    }
    public static function form(Form $form): Form
    {
        $loggedInUser = Auth::user();
        $isAdmin = $loggedInUser && $loggedInUser->hasRole('admin');
        $isSeller = $loggedInUser && $loggedInUser->hasRole('seller');
        $isBuyer = $loggedInUser && $loggedInUser->hasRole('buyer');
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    // ... Step 'Informasi Pembeli & Pengiriman' ...
                    Forms\Components\Wizard\Step::make('Informasi Pembeli & Pengiriman')
                        ->schema([
                            Forms\Components\Select::make('buyer_id')
                                ->label('Pembeli')
                                ->relationship(
                                    name: 'buyer',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: function (Builder $query) use ($isAdmin, $loggedInUser) {
                                        if ($isAdmin) {
                                        // Admin bisa memilih dari semua user dengan role 'buyer'
                                            return $query->whereHas('roles', fn ($subQuery) => $subQuery->where('name', 'buyer'));
                                        }
                                        // Seller/Buyer hanya bisa memilih diri sendiri (jika form ini untuk mereka membuat order)
                                        // Atau jika admin membuatkan untuk buyer tertentu.
                                        // Untuk buyer yang login, ini akan di-set otomatis
                                        return $query->where('id', $loggedInUser ? $loggedInUser->id : null);
                                    }
                                )
                                ->default(fn() => !$isAdmin && $isBuyer ? $loggedInUser->id : null) // Jika buyer, default ke diri sendiri
                                ->disabled(!$isAdmin && $isBuyer) // Buyer tidak bisa ganti buyer, Admin bisa
                                ->searchable($isAdmin)
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
                                ->dehydrated(fn (string $operation): bool => $operation === 'edit'), // Hanya kirim saat edit
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
        $user = Auth::user();

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
                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Grand Total')
                    ->money('idr')
                    ->getStateUsing(fn (Order $record): float => $record->grand_total)
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
                Tables\Filters\SelectFilter::make('buyer_id')
                    ->label('Pembeli')
                    ->options(
                        \App\Models\User::whereHas('roles', function ($query) {
                            $query->where('name', 'buyer');
                        })->pluck('name', 'id')
                    )->visible(fn() => Auth::user()->hasRole('admin')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),

                // --- AWAL TOMBOL AKSI STATUS ---
                Action::make('markAsProcessing')
                    ->label('Proses Pesanan')
                    ->icon('heroicon-o-play-circle')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (Order $record) {
                        if ($record->status === 'pending' && ($record->payment && $record->payment->status === 'paid')) {
                            $record->update(['status' => 'processing']);
                            Notification::make()->title('Status berhasil diubah ke Diproses')->success()->send();
                        } else {
                            Notification::make()->title('Aksi tidak valid')->body('Pesanan belum dibayar atau status tidak sesuai.')->danger()->send();
                        }
                    })
                    ->visible(fn (Order $record) => $user->hasAnyRole(['admin', 'seller']) && $record->status === 'pending' && ($record->payment && $record->payment->status === 'paid')),

                Action::make('markAsShipped')
                    ->label('Kirim Pesanan')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->requiresConfirmation()
                    // Tambahkan form untuk input nomor resi jika diperlukan
                    ->form([
                        Forms\Components\TextInput::make('shipping_tracking_number')
                            ->label('Nomor Resi Pengiriman')
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data) {
                        if ($record->status === 'processing') {
                            $record->update([
                                'status' => 'shipped',
                                'shipping_tracking_number' => $data['shipping_tracking_number']
                            ]);
                            Notification::make()->title('Status berhasil diubah ke Dikirim')->success()->send();
                        } else {
                            Notification::make()->title('Aksi tidak valid')->body('Status pesanan tidak sesuai.')->danger()->send();
                        }
                    })
                    ->visible(fn (Order $record) => $user->hasAnyRole(['admin', 'seller']) && $record->status === 'processing'),

                Action::make('markAsCompleted')
                    ->label('Selesaikan Pesanan')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Order $record) {
                        if ($record->status === 'shipped') {
                            $record->update(['status' => 'completed']);
                            Notification::make()->title('Status berhasil diubah ke Selesai')->success()->send();
                        } else {
                            Notification::make()->title('Aksi tidak valid')->body('Status pesanan tidak sesuai.')->danger()->send();
                        }
                    })
                    // Buyer bisa menandai selesai, atau admin/seller
                    ->visible(fn (Order $record) => ($user->hasAnyRole(['admin', 'seller']) || ($user->hasRole('buyer') && $record->buyer_id === $user->id)) && $record->status === 'shipped'),

                Action::make('markAsCancelled')
                    ->label('Batalkan Pesanan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Order $record) {
                        // Tambahkan logika validasi pembatalan (misal hanya jika belum dikirim)
                        if (in_array($record->status, ['pending', 'processing'])) {
                            $record->update(['status' => 'cancelled']);
                            // Tambahkan logika pengembalian stok jika perlu
                            Notification::make()->title('Pesanan berhasil dibatalkan')->success()->send();
                        } else {
                            Notification::make()->title('Aksi tidak valid')->body('Pesanan tidak dapat dibatalkan pada status ini.')->danger()->send();
                        }
                    })
                    ->visible(fn (Order $record) => $user->hasAnyRole(['admin', 'seller']) && in_array($record->status, ['pending', 'processing'])),
                // --- AKHIR TOMBOL AKSI STATUS ---
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    // Anda bisa menambahkan bulk action untuk status di sini jika perlu
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

    public static function getEloquentQuery(): Builder // Pembatasan data
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user->hasRole('seller')) {
            // Seller melihat pesanan yang mengandung produk miliknya
            $query->whereHas('items.product', function (Builder $subQuery) use ($user) {
                $subQuery->where('seller_id', $user->id);
            });
        } elseif ($user->hasRole('buyer')) {
            // Buyer hanya melihat pesanannya sendiri
            $query->where('buyer_id', $user->id);
        }
        // Admin melihat semua (tidak ada filter tambahan)

        return $query;
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
