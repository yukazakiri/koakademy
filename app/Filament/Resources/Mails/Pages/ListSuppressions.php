<?php

declare(strict_types=1);

namespace App\Filament\Resources\Mails\Pages;

use App\Filament\Resources\Mails\SuppressionResource;
use Backstage\Mails\Resources\SuppressionResource\Pages\ListSuppressions as BaseListSuppressions;

final class ListSuppressions extends BaseListSuppressions
{
    protected static string $resource = SuppressionResource::class;
}
