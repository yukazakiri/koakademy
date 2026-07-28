<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * Class AdminTransaction
 *
 * @property-read Transaction|null $transaction
 * @property-read User|null $user
 *
 * @method static Builder<static>|AdminTransaction newModelQuery()
 * @method static Builder<static>|AdminTransaction newQuery()
 * @method static Builder<static>|AdminTransaction query()
 *
 * @mixin \Eloquent
 */
final class AdminTransaction extends Model
{
    #[Override]
    protected $table = 'admin_transactions';

    #[Override]
    protected $fillable = [
        'admin_id',
        'transaction_id',
        'amount',
        'type',
        'description',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    protected function casts(): array
    {
        return [
            'admin_id' => 'int',
            'transaction_id' => 'int',
        ];
    }
}
