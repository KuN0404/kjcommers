<?php
namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProductImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';
    protected static ?string $recordTitleAttribute = 'alt_text';

    protected static ?string $label = 'Gambar Produk';
    protected static ?string $pluralLabel = 'Gambar Produk';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('url')
                    ->label('File Gambar')
                    ->image()
                    ->disk('public')
                    ->directory('product-images')
                    ->required()
                    ->imageEditor() // Aktifkan editor gambar jika perlu
                    ->rules(['image', 'max:2048']), // Validasi tipe dan ukuran
                Forms\Components\TextInput::make('alt_text')
                    ->label('Teks Alternatif (untuk SEO & Aksesibilitas)')
                    ->maxLength(255),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Urutan Tampilan')
                    ->numeric()
                    ->default(0)
                    ->helperText('Gambar dengan urutan lebih kecil akan tampil lebih dulu.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('url')->label('Gambar')->disk('public'),
                Tables\Columns\TextColumn::make('alt_text')->label('Teks Alternatif')->limit(50),
                Tables\Columns\TextColumn::make('sort_order')->label('Urutan')->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
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
}
