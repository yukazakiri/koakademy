<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class SocialMediaSettings extends Settings
{
    public ?string $linkedin = null;

    public ?string $whatsapp = null;

    public ?string $x = null;

    public ?string $facebook = null;

    public ?string $instagram = null;

    public ?string $tiktok = null;

    public ?string $medium = null;

    public ?string $youtube = null;

    public ?string $github = null;

    public static function group(): string
    {
        return 'social-media';
    }
}
