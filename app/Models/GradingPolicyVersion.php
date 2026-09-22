<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Override;

final class GradingPolicyVersion extends Model
{
    use HasFactory;

    public const string Published = 'published';

    #[Override]
    protected $fillable = [
        'grading_policy_id',
        'version',
        'state',
        'configuration',
        'created_by',
        'published_by',
        'published_at',
    ];

    /** @return BelongsTo<GradingPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(GradingPolicy::class, 'grading_policy_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        self::updating(function (self $version): void {
            if ($version->getOriginal('state') === self::Published) {
                throw new LogicException('Published grading policy versions are immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'configuration' => 'array',
            'published_at' => 'immutable_datetime',
        ];
    }
}
