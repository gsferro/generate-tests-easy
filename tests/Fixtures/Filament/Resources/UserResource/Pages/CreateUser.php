<?php

namespace Tests\Fixtures\Filament\Resources\UserResource\Pages;

use Tests\Fixtures\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}