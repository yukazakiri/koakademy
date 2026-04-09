<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Models;

use App\Models\Course;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\LibrarySystem\Database\Factories\ResearchPaperFactory;
use Spatie\Tags\HasTags;

final class ResearchPaper extends Model
{
    use HasFactory;
    use HasTags;
    use SoftDeletes;

    protected $table = 'library_research_papers';

    protected $fillable = [
        'title',
        'type',
        'student_id',
        'course_id',
        'advisor_name',
        'contributors',
        'abstract',
        'keywords',
        'publication_year',
        'document_url',
        'document_path',
        'cover_image_path',
        'status',
        'is_public',
        'notes',
    ];

    protected $casts = [
        'publication_year' => 'integer',
        'is_public' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'library_research_paper_student')
            ->withTimestamps();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    protected static function newFactory(): ResearchPaperFactory
    {
        return ResearchPaperFactory::new();
    }
}
