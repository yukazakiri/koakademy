<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EnrollmentPolicySnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Override;

/**
 * @property array<string, mixed> $configuration
 * @property array<int, int> $source_version_ids
 */
final class EnrollmentPolicySnapshot extends Model
{
    /** @use HasFactory<EnrollmentPolicySnapshotFactory> */
    use HasFactory;

    #[Override]
    protected $fillable = ['schema_version', 'checksum', 'configuration', 'source_version_ids'];

    /** @return HasMany<StudentEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Enrollment policy snapshots are immutable.'));
        self::deleting(fn (): never => throw new LogicException('Enrollment policy snapshots cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'configuration' => 'array',
            'source_version_ids' => 'array',
        ];
    }
}
