<?php

declare(strict_types=1);

namespace App\Filament\Auth\MultiFactor;

use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Contracts\Auth\Authenticatable;

final class SecurityAwareAppAuthentication extends AppAuthentication
{
    public function isEnabled(Authenticatable $user): bool
    {
        if ($user instanceof User && ! ($user->security_two_factor_enabled ?? true)) {
            return false;
        }

        return parent::isEnabled($user);
    }
}
