<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Manajemen Pesanan';
    protected static ?string $label = 'Pembayaran';
    protected static ?string $pluralLabel = 'Semua Pembayaran';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('order_id')
                    ->label('Nomor Pesanan')
                    ->relationship('order', 'order_number')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                Forms\Components\Select::make('payment_type_id')
                    ->label('Tipe Pembayaran')
                    ->relationship('paymentType', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->label('Jumlah Pembayaran')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(function (Get $get) {
                        $orderId = $get('order_id');
                        if ($orderId) {
                            $order = \App\Models\Order::find($orderId);
                            return $order ? $order->grand_total : 0.00;
                        }
                        return 0.00;
                    })
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label('Status Pembayaran')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Lunas',
                        'failed' => 'Gagal',
                        'refunded' => 'Dikembalikan (Refund)',
                    ])
                    ->default('pending')
                    ->live()
                    ->required(),
                Forms\Components\TextInput::make('transaction_id')
                    ->label('ID Transaksi (Jika Ada dari Payment Gateway)')
                    ->maxLength(255),
                Forms\Components\FileUpload::make('proof_of_payment_url')
                    ->label('Bukti Pembayaran (Jika Diperlukan)')
                    ->image()
                    ->disk('public')
                    ->directory('payment-proofs')
                    ->imageEditor()
                    ->rules(['nullable', 'image', 'max:2048'])
                    ->visible(function (Get $get): bool {
                        $paymentTypeId = $get('payment_type_id');
                        if ($paymentTypeId) {
                            $paymentType = \App\Models\PaymentType::find($paymentTypeId);
                            return $paymentType && $paymentType->requires_proof;
                        }
                        return false;
                    }),
                Forms\Components\Textarea::make('payment_notes_from_user')
                    ->label('Catatan Pembayaran dari Pengguna (Opsional)')
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('paid_at')
                    ->label('Dibayar Pada')
                    ->visible(fn (Get $get) => $get('status') === 'paid'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.order_number')->label('No. Pesanan')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('paymentType.name')->label('Tipe Pembayaran')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('amount')->label('Jumlah')->money('idr')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                 ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    })->sortable(),
                Tables\Columns\ImageColumn::make('proof_of_payment_url')->label('Bukti Bayar')->disk('public')->defaultImageUrl(url('/placeholder-image.png')),
                Tables\Columns\TextColumn::make('paid_at')->label('Dibayar Pada')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->label('Terakhir Diubah')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Lunas',
                        'failed' => 'Gagal',
                        'refunded' => 'Dikembalikan',
                    ])->label('Status Pembayaran'),
                Tables\Filters\SelectFilter::make('payment_type_id')->relationship('paymentType', 'name')->label('Tipe Pembayaran'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }

    // Hanya admin yang bisa membuat pembayaran baru secara manual jika diperlukan
    // public static function canCreate(): bool
    // {
    //     return auth()->user()->hasRole('admin');
    // }
}
