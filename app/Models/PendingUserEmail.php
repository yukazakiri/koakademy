<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PendingUserEmail
 *
 * @method static Builder<static>|PendingUserEmail newModelQuery()
 * @method static Builder<static>|PendingUserEmail newQuery()
 * @method static Builder<static>|PendingUserEmail query()
 *
 * @mixin \Eloquent
 */
final class PendingUserEmail extends Model
{
    public $timestamps = false;

    protected $table = 'pending_user_emails';

    protected $hidden = [
        'token',
    ];

    protected $fillable = [
        'user_type',
        'user_id',
        'email',
        'token',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'float',
        ];
    }
}
