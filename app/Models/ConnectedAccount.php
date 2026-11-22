<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedOrLegacyStringCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

final class ConnectedAccount extends Model
{
    #[Override]
    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'name',
        'nickname',
        'email',
        'avatar_path',
        'token',
        'secret',
        'refresh_token',
        'expires_at',
    ];

    /**
     * @var list<string>
     */
    #[Override]
    protected $hidden = [
        'token',
        'secret',
        'refresh_token',
    ];

    #[Override]
    protected $casts = [
        'token' => EncryptedOrLegacyStringCast::class,
        'secret' => EncryptedOrLegacyStringCast::class,
        'refresh_token' => EncryptedOrLegacyStringCast::class,
        'expires_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, self>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
