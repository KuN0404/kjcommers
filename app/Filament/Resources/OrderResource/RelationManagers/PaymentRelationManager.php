<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Model;

class PaymentRelationManager extends RelationManager
{
    protected static string $relationship = 'payment'; // hasOne relationship
    protected static ?string $recordTitleAttribute = 'status';

    protected static ?string $label = 'Pembayaran';
    protected static ?string $pluralLabel = 'Pembayaran';


    public function form(Form $form): Form
    {
        return $form
            ->schema([
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
                    ->default(fn (Get $get, RelationManager $livewire): float => $livewire->getOwnerRecord()->grand_total ?? 0.00)
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

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('paymentType.name')->label('Tipe Pembayaran'),
                Tables\Columns\TextColumn::make('amount')->label('Jumlah')->money('idr'),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                 ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\ImageColumn::make('proof_of_payment_url')->label('Bukti Bayar')->disk('public'),
                Tables\Columns\TextColumn::make('paid_at')->label('Dibayar Pada')->dateTime(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // CreateAction hanya jika belum ada payment untuk order ini
                Tables\Actions\CreateAction::make()->visible(fn (RelationManager $livewire) => !$livewire->getOwnerRecord()->payment),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->visible(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->payment && $livewire->getOwnerRecord()->payment->status === 'pending'), // Hanya bisa hapus jika pending
            ]);
    }

    // Menyesuaikan karena ini adalah relasi hasOne
    protected function canCreate(): bool
    {
        return !$this->getOwnerRecord()->payment()->exists();
    }
}
