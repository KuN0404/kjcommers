<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\PaymentType;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\PaymentTypeResource\Pages;

class PaymentTypeResource extends Resource
{
    protected static ?string $model = PaymentType::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Pengaturan Pengiriman & Pembayaran';
    protected static ?string $label = 'Tipe Pembayaran';
    protected static ?string $pluralLabel = 'Tipe Pembayaran';
    protected static ?int $navigationSort = 2;

        public static function canViewNavigation(): bool // Kontrol visibilitas menu
    {
        return Auth::user()->hasRole('admin'); // Hanya admin
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Tipe Pembayaran')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->label('Kode (untuk identifikasi sistem)')
                    ->required()
                    ->maxLength(50)
                    ->unique(PaymentType::class, 'code', ignoreRecord: true),
                Forms\Components\FileUpload::make('logo_url')
                    ->label('Logo Tipe Pembayaran')
                    ->image()
                    ->disk('public')
                    ->directory('payment-type-logos')
                    ->imageEditor()
                    ->rules(['nullable', 'image', 'max:1024']),
                Forms\Components\Textarea::make('description')
                    ->label('Deskripsi/Instruksi Pembayaran')
                    ->helperText('Contoh: Nomor rekening, cara bayar, dll.')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('requires_proof')
                    ->label('Membutuhkan Unggah Bukti Pembayaran?')
                    ->default(false)
                    ->helperText('Aktifkan jika pelanggan perlu mengunggah bukti transfer.'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
                    ->required(),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Urutan Tampilan')
                    ->numeric()
                    ->default(0)
                    ->helperText('Urutan lebih kecil akan tampil lebih dulu.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_url')->label('Logo')->disk('public')->defaultImageUrl(url('/placeholder-image.png')),
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->label('Kode')->searchable(),
                Tables\Columns\IconColumn::make('requires_proof')->label('Butuh Bukti?')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Urutan')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->label('Terakhir Diubah')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Status Aktif'),
                Tables\Filters\TernaryFilter::make('requires_proof')->label('Membutuhkan Bukti'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentTypes::route('/'),
            'create' => Pages\CreatePaymentType::route('/create'),
            'view' => Pages\ViewPaymentType::route('/{record}'),
            'edit' => Pages\EditPaymentType::route('/{record}/edit'),
        ];
    }
}
