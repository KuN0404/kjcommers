<?php
namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class UserAddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';
    protected static ?string $recordTitleAttribute = 'label';

    protected static ?string $label = 'Alamat';
    protected static ?string $pluralLabel = 'Alamat Pengguna';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('label')
                    ->label('Label Alamat (Contoh: Rumah, Kantor)')
                    ->maxLength(255)
                    ->required(),
                Forms\Components\TextInput::make('recipient_name')
                    ->label('Nama Penerima')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone_number')
                    ->label('Nomor Telepon')
                    ->tel()
                    ->required()
                    ->maxLength(20)
                    ->rules(['min:10', 'max:15']), // Validasi panjang nomor telepon
                Forms\Components\Textarea::make('address_line1')
                    ->label('Alamat Baris 1 (Nama Jalan, Nomor Rumah, RT/RW)')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('address_line2')
                    ->label('Alamat Baris 2 (Detail Tambahan, Ancer-ancer)')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('village_subdistrict')
                    ->label('Desa/Kelurahan')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('district')
                    ->label('Kecamatan')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('city_regency')
                    ->label('Kota/Kabupaten')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('province')
                    ->label('Provinsi')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('postal_code')
                    ->label('Kode Pos')
                    ->required()
                    ->numeric()
                    ->maxLength(10),
                Forms\Components\TextInput::make('country')
                    ->label('Negara')
                    ->default('Indonesia')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('notes')
                    ->label('Catatan untuk Kurir (Opsional)')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_default')
                    ->label('Jadikan Alamat Utama?')
                    ->default(false),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')->label('Label')->searchable(),
                Tables\Columns\TextColumn::make('recipient_name')->label('Penerima')->searchable(),
                Tables\Columns\TextColumn::make('phone_number')->label('Telepon'),
                Tables\Columns\TextColumn::make('city_regency')->label('Kota/Kabupaten')->searchable(),
                Tables\Columns\IconColumn::make('is_default')->label('Utama')->boolean(),
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
            ]);
    }
}
