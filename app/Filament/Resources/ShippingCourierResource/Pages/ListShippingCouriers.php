<?php

namespace App\Filament\Resources\ShippingCourierResource\Pages;

use App\Filament\Resources\ShippingCourierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShippingCouriers extends ListRecords
{
    protected static string $resource = ShippingCourierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
