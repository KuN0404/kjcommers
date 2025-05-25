<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\Product;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'orderItems';

    public function form(Form $form): Form
    {
        return $form
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
                    })
                    ->label('Produk')
                    ->columnSpan([
                        'md' => 2,
                    ]),
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
                    })
                    ->label('Jumlah')
                    ->columnSpan([
                        'md' => 1,
                    ]),
                Forms\Components\TextInput::make('unit_price')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->readOnly()
                    ->dehydrated()
                    ->label('Harga Satuan')
                    ->columnSpan([
                        'md' => 1,
                    ]),
                Forms\Components\TextInput::make('subtotal')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->readOnly()
                    ->dehydrated()
                    ->label('Subtotal')
                    ->columnSpan([
                        'md' => 2,
                    ]),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->money('IDR', true)
                    ->label('Harga Satuan')
                    ->sortable(),
                Tables\Columns\TextColumn::make('subtotal')
                    ->money('IDR', true)
                    ->label('Subtotal')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('IDR', true)),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $product = Product::find($data['product_id']);
                        if ($product) {
                            $data['unit_price'] = $product->price ?? 0;
                            $data['subtotal'] = ($product->price ?? 0) * ($data['quantity'] ?? 1);
                        }
                        return $data;
                    })
                    ->after(function () {
                        $this->updateOrderTotalAmount();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data, Model $record): array {
                        $productId = $data['product_id'] ?? $record->product_id;
                        $product = Product::find($productId);
                        if ($product) {
                             $data['unit_price'] = $data['unit_price'] ?? ($product->price ?? 0); // Jaga unit_price jika produk tidak diubah
                        }
                        $data['subtotal'] = ($data['unit_price'] ?? 0) * ($data['quantity'] ?? 1);
                        return $data;
                    })
                    ->after(function () {
                        $this->updateOrderTotalAmount();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->after(function () {
                        $this->updateOrderTotalAmount();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function () {
                            $this->updateOrderTotalAmount();
                        }),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('product'));
    }

    protected function updateOrderTotalAmount(): void
    {
        $order = $this->getOwnerRecord();
        if ($order instanceof Order) {
            $total = $order->orderItems()->sum('subtotal'); // Hitung dari database untuk akurasi
            $order->total_amount = $total;
            $order->saveQuietly();

            // Untuk me-refresh form utama di OrderResource (jika sedang di halaman edit Order)
            // agar field total_amount di sana juga terupdate secara visual.
            if (method_exists($this->getLivewire(), 'dispatch')) {
                 $this->getLivewire()->dispatch('refreshOrderForm');
            }
        }
    }
}
