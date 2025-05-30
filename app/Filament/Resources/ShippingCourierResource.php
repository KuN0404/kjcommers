<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingCourierResource\Pages;
use App\Models\ShippingCourier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShippingCourierResource extends Resource
{
    protected static ?string $model = ShippingCourier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Pengaturan Pengiriman & Pembayaran';
    protected static ?string $label = 'Jasa Pengiriman';
    protected static ?string $pluralLabel = 'Jasa Pengiriman';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Kurir')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->label('Kode Kurir (untuk integrasi, jika ada)')
                    ->required()
                    ->maxLength(50)
                    ->unique(ShippingCourier::class, 'code', ignoreRecord: true),
                Forms\Components\FileUpload::make('logo_url')
                    ->label('Logo Kurir')
                    ->image()
                    ->disk('public')
                    ->directory('courier-logos')
                    ->imageEditor()
                    ->rules(['nullable', 'image', 'max:1024']),
                Forms\Components\Textarea::make('description')
                    ->label('Deskripsi Layanan (Opsional)')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_url')->label('Logo')->disk('public')->defaultImageUrl(url('/placeholder-image.png')),
                Tables\Columns\TextColumn::make('name')->label('Nama Kurir')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->label('Kode')->searchable(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('Dibuat Pada')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Status Aktif'),
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
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingCouriers::route('/'),
            'create' => Pages\CreateShippingCourier::route('/create'),
            'view' => Pages\ViewShippingCourier::route('/{record}'),
            'edit' => Pages\EditShippingCourier::route('/{record}/edit'),
        ];
    }
}
