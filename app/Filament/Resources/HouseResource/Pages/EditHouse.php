<?php

namespace App\Filament\Resources\HouseResource\Pages;

use App\Filament\Actions\TranslateAction;
use App\Filament\Resources\HouseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHouse extends EditRecord
{
    protected static string $resource = HouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            TranslateAction::make(),
        ];
    }
}
