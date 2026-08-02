<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NewsletterSubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * Records how a portal user responded to the newsletter prompt so they are
 * only ever asked once (subscribed or declined), and so users who already
 * exist on Sequenzy are never prompted again.
 *
 * @property int $id
 * @property int $user_id
 * @property string $email
 * @property NewsletterSubscriptionStatus $status
 * @property \Illuminate\Support\Carbon|null $subscribed_at
 * @property \Illuminate\Support\Carbon|null $declined_at
 */
final class NewsletterSubscription extends Model
{
    #[Override]
    protected $fillable = [
        'user_id',
        'email',
        'status',
        'subscribed_at',
        'declined_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSubscribed(): bool
    {
        return $this->status === NewsletterSubscriptionStatus::Subscribed;
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'status' => NewsletterSubscriptionStatus::class,
            'subscribed_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }
}
