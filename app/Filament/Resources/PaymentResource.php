<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Payment;
use App\Models\Order; // <-- Pastikan ini ada
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\PaymentResource\Pages;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Illuminate\Validation\ValidationException; // Meskipun tidak digunakan langsung di rules, baik untuk diketahui jika membuat custom rule object

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Manajemen Pesanan';
    protected static ?string $label = 'Pembayaran';
    // protected static ?string $pluralLabel = 'Semua Pembayaran'; // Dihapus agar getPluralLabel() yang dipakai
    protected static ?int $navigationSort = 2;

    public static function canViewNavigation(): bool // Kontrol visibilitas menu
    {
        // Admin, Seller, dan Buyer bisa melihat menu Pembayaran
        return Auth::user()->hasAnyRole(['admin', 'seller', 'buyer']);
    }

    public static function getLabel(): string // Label dinamis
    {
        if (Auth::user()->hasRole('buyer')) {
            return 'Pembayaran Saya';
        }
        return 'Pembayaran'; // Label singular umum
    }

    public static function getPluralLabel(): string // Plural label dinamis
    {
        if (Auth::user()->hasRole('buyer')) {
            return 'Pembayaran Saya';
        }
        return 'Semua Pembayaran'; // Untuk admin dan seller
    }


    public static function form(Form $form): Form
    {
        $user = Auth::user();
        return $form
            ->schema([
                Forms\Components\Select::make('order_id')
                    ->label('Nomor Pesanan')
                    ->relationship(
                        name: 'order',
                        titleAttribute: 'order_number',
                        // --- AWAL MODIFIKASI QUERY UNTUK DROPDOWN ORDER ---
                        modifyQueryUsing: function (Builder $query) use ($user) {
                            // Scope order berdasarkan role
                            if ($user->hasRole('seller')) {
                                $query->whereHas('items.product', fn(Builder $subQuery) => $subQuery->where('seller_id', $user->id));
                            } elseif ($user->hasRole('buyer')) {
                                $query->where('buyer_id', $user->id);
                            }
                            // Admin melihat semua (dengan filter di bawah)

                            // Filter tambahan:
                            // Hanya tampilkan order yang statusnya memungkinkan untuk pembayaran baru
                            // dan belum memiliki pembayaran yang lunas.
                            $query->whereNotIn('status', ['completed', 'cancelled', 'refunded']) // Order yang statusnya belum final
                                  ->whereDoesntHave('payment', function (Builder $paymentQuery) {
                                      // Dan tidak memiliki payment yang sudah 'paid'
                                      $paymentQuery->where('status', 'paid');
                                  });
                        }
                        // --- AKHIR MODIFIKASI QUERY UNTUK DROPDOWN ORDER ---
                    )
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->rules([
                        fn (Get $get, $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $existingPaymentQuery = Payment::where('order_id', $value);

                            if ($record instanceof Payment) {
                                $existingPaymentQuery->where('id', '!=', $record->id);
                            }

                            $existingPayment = $existingPaymentQuery->whereIn('status', ['paid', 'pending'])->first();

                            if ($existingPayment) {
                                $fail('Pesanan ini sudah memiliki pembayaran yang aktif (pending) atau lunas (paid).');
                                return;
                            }

                            $order = Order::find($value);
                            if ($order && $order->status === 'cancelled') {
                                $fail('Tidak dapat membuat pembayaran untuk pesanan yang sudah dibatalkan.');
                            }
                        },
                    ]),
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
                    ->visible(fn (Get $get) => $get('status') === 'paid')
                    ->default(fn (Get $get) => $get('status') === 'paid' ? now() : null),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Payment $record) => $user->hasAnyRole(['admin', 'seller']) && $record->status !== 'paid' && $record->status !== 'refunded'), // Hanya bisa edit jika belum lunas/refund

                ActionGroup::make([
                    Action::make('markAsPaid')
                        ->label('Tandai Lunas')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Payment $record) {
                            if ($record->order->status === 'cancelled') {
                                Notification::make()->title('Aksi Gagal')->body('Pesanan terkait sudah dibatalkan. Pembayaran tidak bisa diubah ke Lunas.')->danger()->send();
                                return;
                            }
                            if ($record->status === 'paid') {
                                Notification::make()->title('Info')->body('Pembayaran sudah Lunas.')->warning()->send();
                                return;
                            }
                            $record->update(['status' => 'paid', 'paid_at' => now()]);
                            // Update status order menjadi 'processing' jika masih 'pending'
                            if ($record->order->status === 'pending') {
                                $record->order->update(['status' => 'processing']);
                            }
                            Notification::make()->title('Pembayaran berhasil ditandai Lunas')->success()->send();
                        })
                        ->visible(fn (Payment $record) => $user->hasAnyRole(['admin', 'seller']) && in_array($record->status, ['pending', 'failed']) && $record->order->status !== 'cancelled'),

                    Action::make('markAsFailed')
                        ->label('Tandai Gagal')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Payment $record) {
                            $record->update(['status' => 'failed', 'paid_at' => null]);
                            Notification::make()->title('Pembayaran berhasil ditandai Gagal')->success()->send();
                        })
                        ->visible(fn (Payment $record) => $user->hasAnyRole(['admin', 'seller']) && $record->status === 'pending'),

                    Action::make('markAsPending')
                        ->label('Tandai Pending (Ulangi)')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Payment $record) {
                            if ($record->order->status === 'cancelled') {
                                Notification::make()->title('Aksi Gagal')->body('Pesanan terkait sudah dibatalkan. Status pembayaran tidak bisa diubah.')->danger()->send();
                                return;
                            }
                            $record->update(['status' => 'pending', 'paid_at' => null]);
                            Notification::make()->title('Pembayaran berhasil ditandai Pending')->success()->send();
                        })
                        ->visible(fn (Payment $record) => $user->hasAnyRole(['admin', 'seller']) && $record->status === 'failed' && $record->order->status !== 'cancelled'),

                    Action::make('markAsRefunded')
                        ->label('Tandai Refund')
                        ->icon('heroicon-o-receipt-refund')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (Payment $record) {
                            $record->update(['status' => 'refunded', 'paid_at' => null]);
                            $record->order->update(['status' => 'refunded']);
                            Notification::make()->title('Pembayaran berhasil ditandai Refunded')->success()->send();
                        })
                        ->visible(fn (Payment $record) => $user->hasAnyRole(['admin', 'seller']) && $record->status === 'paid'),
                ])->dropdownPlacement('bottom-end'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => $user->hasRole('admin')),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user->hasRole('seller')) {
            $query->whereHas('order.items.product', function (Builder $subQuery) use ($user) {
                $subQuery->where('seller_id', $user->id);
            });
        } elseif ($user->hasRole('buyer')) {
            $query->whereHas('order', function (Builder $subQuery) use ($user) {
                $subQuery->where('buyer_id', $user->id);
            });
        }
        return $query;
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

    public static function canCreate(): bool
    {
        $user = Auth::user();
        if ($user->hasAnyRole(['admin', 'seller'])) {
            return true;
        }
        return false;
    }
}