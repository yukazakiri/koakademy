<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('grade_release_clearances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('school_year', 20);
            $table->unsignedTinyInteger('semester');
            $table->boolean('is_cleared')->default(false);
            $table->text('remarks')->nullable();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'school_year', 'semester'], 'grade_clearance_student_period_unique');
        });

        Schema::create('grade_releases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('school_year', 20);
            $table->unsignedTinyInteger('semester');
            $table->json('snapshot');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->json('notification_status')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'school_year', 'semester'], 'grade_release_student_period_unique');
        });

        Schema::create('grade_release_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('school_year', 20);
            $table->unsignedTinyInteger('semester');
            $table->string('event', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'school_year', 'semester'], 'grade_release_events_period_idx');
        });

        // Preserve grades that were already visible before the release workflow existed.
        // No notifications are sent for this initial baseline.
        DB::table('students')->select('id')->orderBy('id')->chunkById(200, function ($students): void {
            $ids = $students->pluck('id')->all();
            $subjectsByStudent = DB::table('subject_enrollments')->whereIn('student_id', $ids)->get()->groupBy('student_id');
            $classesByStudent = DB::table('class_enrollments')->join('classes', 'classes.id', '=', 'class_enrollments.class_id')
                ->whereIn('class_enrollments.student_id', $ids)->whereNull('class_enrollments.deleted_at')
                ->select('class_enrollments.*', 'classes.school_year as period_year', 'classes.semester as period_semester')->get()->groupBy('student_id');
            $rows = [];
            foreach ($students as $student) {
                $periods = [];
                $subjects = $subjectsByStudent->get($student->id, collect());
                $classes = $classesByStudent->get($student->id, collect());
                foreach ($subjects as $subject) {
                    $period = $this->period($subject->school_year, $subject->semester, 'subject enrollment #'.$subject->id);
                    if ($period === null) {
                        continue;
                    }
                    [$year, $semester] = $period;
                    $key = $year.'|'.$semester;
                    $periods[$key] ??= ['year' => $year, 'semester' => $semester, 'subjects' => [], 'classes' => []];
                    $matchingClass = $subject->class_id ? $classes->firstWhere('class_id', $subject->class_id) : null;
                    $useClassResult = $subject->grade === null && $subject->grade_symbol === null && $matchingClass !== null;
                    $periods[$key]['subjects'][(string) $subject->id] = [
                        'grade' => $useClassResult ? $matchingClass->total_average : $subject->grade,
                        'grade_symbol' => $useClassResult ? $matchingClass->grade_symbol : $subject->grade_symbol,
                        'grade_outcome' => $useClassResult ? $matchingClass->grade_outcome : $subject->grade_outcome,
                        'grade_quality_points' => $useClassResult ? $matchingClass->grade_quality_points : $subject->grade_quality_points,
                        'remarks' => $subject->remarks,
                        'is_credited' => (bool) $subject->is_credited,
                    ];
                }
                foreach ($classes as $class) {
                    $period = $this->period($class->period_year, $class->period_semester, 'class enrollment #'.$class->id);
                    if ($period === null) {
                        continue;
                    }
                    [$year, $semester] = $period;
                    $key = $year.'|'.$semester;
                    $periods[$key] ??= ['year' => $year, 'semester' => $semester, 'subjects' => [], 'classes' => []];
                    $periods[$key]['classes'][(string) $class->id] = [
                        'finals_grade' => $class->finals_grade, 'total_average' => $class->total_average,
                        'grade_symbol' => $class->grade_symbol, 'grade_outcome' => $class->grade_outcome,
                        'grade_quality_points' => $class->grade_quality_points,
                        'grading_components' => $class->grading_components ? json_decode($class->grading_components, true) : null,
                    ];
                }
                foreach ($periods as $period) {
                    $rows[] = [
                        'student_id' => $student->id, 'school_year' => $period['year'], 'semester' => $period['semester'],
                        'snapshot' => json_encode(['subjects' => $period['subjects'], 'classes' => $period['classes']], JSON_THROW_ON_ERROR),
                        'revision' => 1, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                    ];
                }
            }
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('grade_releases')->insert($chunk);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_release_events');
        Schema::dropIfExists('grade_releases');
        Schema::dropIfExists('grade_release_clearances');
    }

    /** @return array{string, int}|null */
    private function period(?string $year, mixed $semester, string $record): ?array
    {
        if (! is_string($year) || preg_match('/^\s*(\d{4})\s*-\s*(\d{4})\s*$/', $year, $matches) !== 1 || (int) $semester < 1 || (int) $semester > 3) {
            Illuminate\Support\Facades\Log::warning('Grade-release baseline skipped an invalid academic period.', ['record' => $record, 'school_year' => $year, 'semester' => $semester]);

            return null;
        }

        return [$matches[1].' - '.$matches[2], (int) $semester];
    }
};
