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
        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignIdFor(App\Models\CourseType::class)
                ->nullable()
                ->after('department_id')
                ->constrained()
                ->nullOnDelete();
        });

        // Insert default course types
        $defaultType = DB::table('course_types')->insertGetId([
            'name' => 'College Undergraduate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('course_types')->insert([
            ['name' => 'Masters', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'TESDA', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Migrate existing courses
        DB::table('courses')->update(['course_type_id' => $defaultType]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropForeignIdFor(App\Models\CourseType::class);
            $table->dropColumn('course_type_id');
        });
    }
};
