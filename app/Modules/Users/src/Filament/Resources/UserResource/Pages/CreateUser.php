<?php

namespace App\Modules\Users\Filament\Resources\UserResource\Pages;

use App\Modules\Users\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
