<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Product;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Model; // Untuk $ownerRecord

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $label = 'Item Pesanan';
    protected static ?string $pluralLabel = 'Item Pesanan';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('Produk')
                    ->options(Product::query()->where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state, RelationManager $livewire) {
                        if ($state) {
                            $product = Product::find($state);
                            if ($product) {
                                $set('unit_price', $product->price);
                                $quantity = $get('quantity') ?: 1;
                                $set('subtotal', $product->price * $quantity);
                            }
                        }
                        $livewire->dispatch('updateOrderTotal'); // Kirim event untuk update total order
                    })
                    ->required()
                    ->reactive(),
                Forms\Components\TextInput::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state, RelationManager $livewire) {
                        $unitPrice = $get('unit_price') ?: 0;
                        $quantity = $state ?: 1;
                        $set('subtotal', (float)$unitPrice * (int)$quantity);
                        $livewire->dispatch('updateOrderTotal'); // Kirim event untuk update total order
                    })
                    ->required()
                    ->reactive(),
                Forms\Components\TextInput::make('unit_price')
                    ->label('Harga Satuan')
                    ->numeric()
                    ->prefix('Rp')
                    ->disabled()
                    ->required(),
                Forms\Components\TextInput::make('subtotal')
                    ->label('Subtotal Item')
                    ->numeric()
                    ->prefix('Rp')
                    ->disabled()
                    ->required(),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Produk')->searchable(),
                Tables\Columns\TextColumn::make('quantity')->label('Jumlah')->alignCenter(),
                Tables\Columns\TextColumn::make('unit_price')->label('Harga Satuan')->money('idr'),
                Tables\Columns\TextColumn::make('subtotal')->label('Subtotal Item')->money('idr'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (isset($data['product_id']) && isset($data['quantity'])) {
                            $product = Product::find($data['product_id']);
                            if ($product) {
                                $data['unit_price'] = $product->price;
                                $data['subtotal'] = $product->price * $data['quantity'];
                            }
                        }
                        return $data;
                    })
                    ->after(fn (RelationManager $livewire) => $livewire->dispatch('updateOrderTotal')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (isset($data['product_id']) && isset($data['quantity'])) {
                            $product = Product::find($data['product_id']);
                            if ($product) {
                                $data['unit_price'] = $product->price;
                                $data['subtotal'] = $product->price * $data['quantity'];
                            }
                        }
                        return $data;
                    })
                    ->after(fn (RelationManager $livewire) => $livewire->dispatch('updateOrderTotal')),
                Tables\Actions\DeleteAction::make()
                    ->after(fn (RelationManager $livewire) => $livewire->dispatch('updateOrderTotal')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(fn (RelationManager $livewire) => $livewire->dispatch('updateOrderTotal')),
                ]),
            ]);
    }

    // Untuk mengupdate total_amount di OrderResource setelah item berubah
    protected function getListeners(): array
    {
        return array_merge(
            parent::getListeners(),
            ['updateOrderTotal' => 'updateOwnerRecordTotalAmount']
        );
    }

    public function updateOwnerRecordTotalAmount(): void
    {
        $order = $this->getOwnerRecord();
        if ($order instanceof \App\Models\Order) {
            $newTotalAmount = $order->items()->sum('subtotal');
            $order->update(['total_amount' => $newTotalAmount]);
            // Ini akan merefresh data di form Order jika sedang di halaman edit/view
            $this->dispatch('refresh');
            if (method_exists($this->getLivewire(), 'refreshForm')) {
                 $this->getLivewire()->refreshForm();
            }
        }
    }
}
