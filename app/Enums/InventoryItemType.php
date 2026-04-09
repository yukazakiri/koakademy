<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryItemType: string
{
    case Tool = 'Tool';
    case Router = 'Router';
    case Nvr = 'NVR';
    case Cctv = 'CCTV';

    /**
     * @return list<string>
     */
    public static function networkValues(): array
    {
        return [
            self::Router->value,
            self::Nvr->value,
            self::Cctv->value,
        ];
    }
}
