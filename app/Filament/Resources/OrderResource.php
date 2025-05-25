<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model; // Diperlukan untuk type hint pada action
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str; // Untuk Str::random()
use Carbon\Carbon; // Untuk format tanggal
use Filament\Notifications\Notification; // Untuk notifikasi setelah ubah status

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pesanan')
                    ->schema([
                        Forms\Components\Select::make('buyer_id')
                            ->relationship(
                                name: 'buyer',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->whereHas('roles', function (Builder $roleQuery) {
                                    $roleQuery->where('name', 'buyer');
                                })
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('Pembeli')
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('order_number')
                            ->label('Nomor Pesanan')
                            ->required()
                            ->disabled()
                            ->dehydrated(true)
                            ->default(function () {
                                $maxTries = 5;
                                $attempt = 0;
                                do {
                                    $orderNumber = 'TRX-' . Carbon::now()->format('Ymd') . '-' . strtoupper(Str::random(10));
                                    $attempt++;
                                    if ($attempt > $maxTries) {
                                        // Fallback jika setelah beberapa kali masih gagal (sangat jarang terjadi)
                                        return 'TRX-' . Carbon::now()->format('YmdHisu') . '-' . strtoupper(Str::random(5));
                                    }
                                } while (Order::where('order_number', $orderNumber)->exists());
                                return $orderNumber;
                            })
                            ->unique(Order::class, 'order_number', ignoreRecord: true)
                            ->maxLength(255)
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('total_amount')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->readOnly()
                            ->dehydrated(true)
                            ->label('Total Bayar')
                            ->columnSpan(1),
                    ])->columns(2),

                Forms\Components\Section::make('Item Pesanan')
                    ->schema([
                        Forms\Components\Repeater::make('orderItems')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        $product = Product::find($state);
                                        $quantity = $get('quantity') ?? 1;
                                        if ($product) {
                                            $unitPrice = $product->price ?? 0;
                                            $set('unit_price', $unitPrice);
                                            $set('subtotal', $unitPrice * $quantity);
                                        } else {
                                            $set('unit_price', 0);
                                            $set('subtotal', 0);
                                        }
                                        self::updateTotalAmountBasedOnRepeater($get, $set, 'orderItems', '../../');
                                    })
                                    ->label('Produk')
                                    ->columnSpan(['md' => 2]),
                                Forms\Components\TextInput::make('quantity')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->reactive()
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                        $unitPrice = $get('unit_price') ?? 0;
                                        $quantity = $get('quantity') ?? 0;
                                        $set('subtotal', $unitPrice * $quantity);
                                        self::updateTotalAmountBasedOnRepeater($get, $set, 'orderItems', '../../');
                                    })
                                    ->label('Jumlah')
                                    ->columnSpan(['md' => 1]),
                                Forms\Components\TextInput::make('unit_price')
                                    ->required()
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->dehydrated()
                                    ->label('Harga Satuan')
                                    ->columnSpan(['md' => 1]),
                                Forms\Components\TextInput::make('subtotal')
                                    ->required()
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->dehydrated()
                                    ->label('Subtotal')
                                    ->columnSpan(['md' => 2]),
                            ])
                            ->columns(4)
                            ->addActionLabel('Tambah Item')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => Product::find($state['product_id'])?->name . ' (' . ($state['quantity'] ?? 0) . ')' ?? null)
                            ->deleteAction(
                                fn (Forms\Components\Actions\Action $action) => $action->requiresConfirmation()
                                    ->after(function (Forms\Get $get, Forms\Set $set) {
                                        self::updateTotalAmountBasedOnRepeater($get, $set, 'orderItems', '../../');
                                    })
                            )
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                $product = Product::find($data['product_id']);
                                if ($product) {
                                    $data['unit_price'] = $product->price ?? 0;
                                    $data['subtotal'] = ($product->price ?? 0) * ($data['quantity'] ?? 1);
                                }
                                return $data;
                            })
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                self::updateTotalAmountBasedOnRepeater($get, $set, 'orderItems', '../../');
                            })
                            ->label(false),
                    ])->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable()
                    ->sortable()
                    ->label('Nomor Pesanan'),
                Tables\Columns\TextColumn::make('buyer.name')
                    ->searchable()
                    ->sortable()
                    ->label('Pembeli'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'refunded' => 'gray',
                        default => 'secondary',
                    })
                    ->searchable()
                    ->sortable()
                    ->label('Status'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->numeric(decimalPlaces: 0, decimalSeparator: ',', thousandsSeparator: '.')
                    ->prefix('Rp ')
                    ->sortable()
                    ->label('Total Bayar'),
                Tables\Columns\TextColumn::make('orderItems.product.name')
                    ->label('Produk Dipesan')
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Tanggal Dibuat'),
            ])
            ->filters([
                // ... filter yang sudah ada ...
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('updateStatus')
                        ->label('Ubah Status')
                        ->icon('heroicon-o-pencil-square')
                        ->form([
                            Forms\Components\Select::make('new_status')
                                ->label('Status Baru')
                                ->options([
                                    'pending' => 'Pending',
                                    'processing' => 'Processing',
                                    'completed' => 'Completed',
                                    'cancelled' => 'Cancelled', // Opsi untuk membatalkan
                                    'refunded' => 'Refunded',
                                ])
                                ->default(fn (Model $record): ?string => $record->status)
                                ->required(),
                        ])
                        ->action(function (Model $record, array $data) {
                            if (isset($data['new_status'])) {
                                $newStatus = $data['new_status'];
                                $currentStatus = $record->status;

                                // Validasi: Tidak bisa cancel jika status completed atau refunded
                                if ($newStatus === 'cancelled' && ($currentStatus === 'completed' || $currentStatus === 'processing'|| $currentStatus === 'refunded')) {
                                    Notification::make()
                                        ->title('Aksi Tidak Diizinkan')
                                        ->body('Pesanan yang sudah selesai (completed), diproses, dan direfund tidak dapat dibatalkan.')
                                        ->danger()
                                        ->send();
                                    return; // Hentikan eksekusi lebih lanjut
                                }

                                // Jika validasi lolos atau status baru bukan 'cancelled' (atau boleh di-cancel)
                                $record->status = $newStatus;
                                $record->save();
                                Notification::make()
                                    ->title('Status pesanan berhasil diubah')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Gagal mengubah status')
                                    ->body('Status baru tidak dipilih.')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->modalHeading('Ubah Status Pesanan')
                        ->modalButton('Simpan Perubahan Status'),
                    Tables\Actions\DeleteAction::make(),
                ])->icon('heroicon-m-ellipsis-vertical'),
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
            RelationManagers\OrderItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['buyer', 'orderItems.product']);
    }

    public static function updateTotalAmountBasedOnRepeater(Forms\Get $get, Forms\Set $set, string $repeaterName, string $pathPrefix = ''): void
    {
        $items = [];
        if ($pathPrefix) {
            $items = $get($pathPrefix . $repeaterName);
        } else {
            $items = $get($repeaterName);
        }

        $total = 0;
        if (is_array($items)) {
            foreach ($items as $item) {
                $total += $item['subtotal'] ?? 0;
            }
        }
        $set($pathPrefix . 'total_amount', $total);
    }
}