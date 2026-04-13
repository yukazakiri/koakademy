<?php

declare(strict_types=1);

use App\Models\Department;
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
        // 1. Seed missing departments that courses reference
        $courseDepartmentCodes = DB::table('courses')
            ->selectRaw('DISTINCT UPPER(TRIM(department)) AS dept_code')
            ->pluck('dept_code')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existingCodes = Department::query()->pluck('code')->map(fn (string $code): string => mb_strtoupper($code))->all();

        $schoolId = DB::table('schools')->value('id');

        $labelMap = [
            'IT' => 'Information Technology',
            'HM' => 'Hospitality Management',
            'BA' => 'Business Administration',
            'HRM' => 'Hotel and Restaurant Management',
            'TESDA' => 'TESDA',
        ];

        foreach ($courseDepartmentCodes as $code) {
            if (! in_array($code, $existingCodes, true)) {
                Department::query()->create([
                    'school_id' => $schoolId,
                    'code' => $code,
                    'name' => $labelMap[$code] ?? $code,
                    'is_active' => true,
                ]);
            }
        }

        // 2. Add department_id column
        Schema::table('courses', function (Blueprint $table): void {
            $table->unsignedBigInteger('department_id')->nullable()->after('department');
        });

        // 3. Populate department_id by matching department string to departments.code
        $departments = Department::query()->get()->keyBy(fn (Department $dept): string => mb_strtoupper($dept->code));

        DB::table('courses')->orderBy('id')->chunk(200, function ($courses) use ($departments): void {
            foreach ($courses as $course) {
                $code = mb_strtoupper(mb_trim((string) $course->department));
                $dept = $departments->get($code);

                if ($dept) {
                    DB::table('courses')
                        ->where('id', $course->id)
                        ->update(['department_id' => $dept->id]);
                }
            }
        });

        // 4. Add foreign key constraint
        Schema::table('courses', function (Blueprint $table): void {
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });

        // 5. Drop the old department string column
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn('department');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Re-add the department string column
        Schema::table('courses', function (Blueprint $table): void {
            $table->string('department')->nullable()->after('description');
        });

        // 2. Populate department from departments.code
        DB::table('courses')->orderBy('id')->chunk(200, function ($courses): void {
            foreach ($courses as $course) {
                if ($course->department_id) {
                    $dept = Department::query()->find($course->department_id);
                    if ($dept) {
                        DB::table('courses')
                            ->where('id', $course->id)
                            ->update(['department' => $dept->code]);
                    }
                }
            }
        });

        // 3. Drop foreign key and department_id column
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};
