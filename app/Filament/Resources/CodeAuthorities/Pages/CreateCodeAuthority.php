<?php

declare(strict_types=1);

namespace App\Filament\Resources\CodeAuthorities\Pages;

use App\Filament\Resources\CodeAuthorities\CodeAuthorityResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreateCodeAuthority extends CreateRecord
{
    #[Override]
    protected static string $resource = CodeAuthorityResource::class;
}
