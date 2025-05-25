<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Payment;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\DateTimePicker;
use App\Filament\Resources\PaymentResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\PaymentResource\RelationManagers;
use App\Models\Order; // Pastikan Anda mengimpor model Order
use Filament\Forms\Get; // Diperlukan untuk afterStateUpdated
use Filament\Forms\Set; // Diperlukan untuk afterStateUpdated
use Filament\Tables\Columns\IconColumn; // Untuk status yang lebih visual
use Filament\Tables\Filters\SelectFilter; // Untuk filter berdasarkan enum
use Filament\Forms\Components\Toggle; // Alternatif untuk status jika boolean

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $navigationLabel = 'Pembayaran';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationGroup = 'Transaksi';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('order_id')
                    ->relationship(
                        name: 'order',
                        titleAttribute: 'order_number', // Menampilkan order_number
                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('created_at', 'desc') // Opsional: urutkan order terbaru
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive() // Membuat field ini reaktif
                    ->afterStateUpdated(function (Set $set, ?string $state) { // $state adalah order_id yang dipilih
                        if ($state) {
                            $order = Order::find($state);
                            if ($order) {
                                $set('amount', $order->total_amount); // Set field 'amount'
                            }
                        } else {
                            $set('amount', null); // Kosongkan jika tidak ada order dipilih
                        }
                    })
                    ->label('Nomor Pesanan (Order)'),
                Select::make('payment_method')
                    ->options([
                        'e_wallet' => 'E-Wallet',
                        'bank_transfer' => 'Bank Transfer',
                    ])
                    ->required()
                    ->label('Metode Pembayaran'),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->label('Jumlah')
                    ->readOnly(), // Sebaiknya readOnly karena diambil dari total_amount order
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ])
                    ->default('pending')
                    ->required()
                    ->label('Status'),
                TextInput::make('transaction_id')
                    ->label('ID Transaksi')
                            ->required()
                            ->disabled()
                            ->dehydrated(true)
                            ->default(function () {
                                $maxTries = 5;
                                $attempt = 0;
                                do {
                                    $orderNumber = 'PYM-' . Carbon::now()->format('Ymd') . '-' . strtoupper(Str::random(10));
                                    $attempt++;
                                    if ($attempt > $maxTries) {
                                        // Fallback jika setelah beberapa kali masih gagal (sangat jarang terjadi)
                                        return 'PYM-' . Carbon::now()->format('YmdHisu') . '-' . strtoupper(Str::random(5));
                                    }
                                } while (Order::where('order_number', $orderNumber)->exists());
                                return $orderNumber;
                            })
                            ->unique(Order::class, 'order_number', ignoreRecord: true)
                            ->maxLength(255)
                            ->columnSpan(1),
                DateTimePicker::make('paid_at')
                    ->label('Dibayar Pada')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_id')
                    ->label('No Transaksi')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('order.order_number') // Menampilkan order_number dari relasi order
                    ->label('Nomor Pesanan')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'e_wallet' => 'success',
                        'bank_transfer' => 'info',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status Pembayaran')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'failed' => 'danger',
                        'refunded' => 'info',
                        default => 'gray',
                    })
                    ->searchable(),

                TextColumn::make('paid_at')
                    ->label('Dibayar Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Diperbarui Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->options([
                        'e_wallet' => 'E-Wallet',
                        'bank_transfer' => 'Bank Transfer',
                    ])
                    ->label('Metode Pembayaran'),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ])
                    ->label('Status Pembayaran'),
                // Anda bisa menambahkan filter berdasarkan Order jika diperlukan
                // SelectFilter::make('order_id')
                // ->relationship('order', 'order_number')
                // ->label('Nomor Pesanan')
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
                                    'paid' => 'Paid',
                                    'failed' => 'Failed',
                                    'refunded' => 'Refunded',
                                ])
                                ->default(fn (Model $record): ?string => $record->status)
                                ->required(),
                        ])
            ->action(function (Model $record, array $data) {
                if (isset($data['new_status'])) {
                    $newStatus = $data['new_status'];
                    $currentStatus = $record->status; // Mengambil status saat ini dari record

                    // Membuat kunci transisi untuk validasi
                    $transitionKey = $currentStatus . '->' . $newStatus;
                    $allowTransition = true; // Asumsikan transisi diizinkan secara default

                    // Logika validasi menggunakan switch berdasarkan transisi status
                    switch ($transitionKey) {
                        // Aturan 1: Tidak bisa 'refunded' jika status 'failed' atau 'pending'.
                        // Pesan disesuaikan agar sesuai dengan kondisi.
                        case 'failed->refunded':
                        case 'pending->refunded':
                            Notification::make()
                                ->title('Aksi Tidak Diizinkan')
                                ->body('Pesanan yang gagal (failed) atau pending tidak dapat langsung di-refund.')
                                ->danger()
                                ->send();
                            $allowTransition = false;
                            break;

                        // Aturan 2: Tidak bisa 'failed' jika status sudah 'completed'.
                        case 'completed->failed':
                            Notification::make()
                                ->title('Aksi Tidak Diizinkan')
                                ->body('Pesanan yang sudah selesai (completed) tidak dapat diubah menjadi gagal (failed).')
                                ->danger()
                                ->send();
                            $allowTransition = false;
                            break;

                        // Aturan 3: Tidak bisa 'cancelled' jika status 'completed', 'processing', atau 'refunded'.
                        case 'completed->cancelled':
                        case 'processing->cancelled':
                        case 'refunded->cancelled':
                            Notification::make()
                                ->title('Aksi Tidak Diizinkan')
                                ->body('Pesanan yang sudah selesai (completed), sedang diproses (processing), atau sudah direfund (refunded) tidak dapat dibatalkan.')
                                ->danger()
                                ->send();
                            $allowTransition = false;
                            break;

                        // Tidak ada default case yang mengubah $allowTransition,
                        // karena jika tidak ada aturan yang cocok, transisi diizinkan ($allowTransition tetap true).
                    }

                    // Jika validasi tidak lolos, hentikan eksekusi lebih lanjut
                    if (!$allowTransition) {
                        return;
                    }

                    // Jika semua validasi lolos, lanjutkan untuk mengubah status
                    $record->status = $newStatus;
                    $record->save();

                    Notification::make()
                        ->title('Status pesanan berhasil diubah')
                        ->success()
                        ->send();

                } else {
                    // Jika 'new_status' tidak ada dalam data (misalnya, input tidak dipilih)
                    Notification::make()
                        ->title('Gagal mengubah status')
                        ->body('Status baru tidak dipilih atau tidak valid.')
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'view' => Pages\ViewPayment::route('/{record}'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }

    // Opsional: Eager load relasi order untuk optimasi query di tabel
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('order');
    }
}