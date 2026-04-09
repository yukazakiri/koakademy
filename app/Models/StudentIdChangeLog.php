<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read Account|null $changedByUser
 * @property-read Account|null $undoneByUser
 *
 * @method static Builder<static>|StudentIdChangeLog newModelQuery()
 * @method static Builder<static>|StudentIdChangeLog newQuery()
 * @method static Builder<static>|StudentIdChangeLog query()
 * @method static Builder<static>|StudentIdChangeLog undoable()
 * @method static Builder<static>|StudentIdChangeLog undone()
 *
 * @mixin \Eloquent
 */
final class StudentIdChangeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'old_student_id',
        'new_student_id',
        'student_name',
        'changed_by',
        'affected_records',
        'backup_data',
        'is_undone',
        'undone_at',
        'undone_by',
        'reason',
    ];

    /**
     * Get the user who made the change
     */
    public function changedByUser()
    {
        return $this->belongsTo(Account::class, 'changed_by', 'email');
    }

    /**
     * Get the user who undid the change
     */
    public function undoneByUser()
    {
        return $this->belongsTo(Account::class, 'undone_by', 'email');
    }

    /**
     * Scope to get only changes that can be undone
     */
    protected function scopeUndoable($query)
    {
        return $query->where('is_undone', false);
    }

    /**
     * Scope to get only undone changes
     */
    protected function scopeUndone($query)
    {
        return $query->where('is_undone', true);
    }

    protected function casts(): array
    {
        return [
            'affected_records' => 'array',
            'backup_data' => 'array',
            'is_undone' => 'boolean',
            'undone_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
