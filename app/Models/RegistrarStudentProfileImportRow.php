<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RegistrarStudentProfileImportRow extends Model
{
    use BelongsToSchool;

    protected $attributes = ['status' => 'invalid'];

    protected $fillable = [
        'registrar_student_profile_import_id',
        'school_id',
        'row_number',
        'student_id',
        'student_enrollment_id',
        'student_number',
        'student_name',
        'course_code',
        'year_level',
        'intake_category',
        'changes',
        'errors',
        'warnings',
        'result',
        'status',
    ];

    /** @return BelongsTo<RegistrarStudentProfileImport, $this> */
    public function import(): BelongsTo
    {
        return $this->belongsTo(RegistrarStudentProfileImport::class, 'registrar_student_profile_import_id');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<StudentEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'year_level' => 'integer',
            'changes' => 'array',
            'errors' => 'array',
            'warnings' => 'array',
            'result' => 'array',
        ];
    }
}
