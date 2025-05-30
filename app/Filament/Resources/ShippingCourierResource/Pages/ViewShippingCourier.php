<?php

namespace App\Filament\Resources\ShippingCourierResource\Pages;

use App\Filament\Resources\ShippingCourierResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewShippingCourier extends ViewRecord
{
    protected static string $resource = ShippingCourierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
