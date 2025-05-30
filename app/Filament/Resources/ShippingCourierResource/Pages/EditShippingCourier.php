<?php

namespace App\Filament\Resources\ShippingCourierResource\Pages;

use App\Filament\Resources\ShippingCourierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShippingCourier extends EditRecord
{
    protected static string $resource = ShippingCourierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
