<?php

declare(strict_types=1);

use App\Services\StudentConfigurationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_configurations', function (Blueprint $table): void {
            $table->id();
            $table->string('scope')->unique();
            $table->foreignId('school_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('configuration');
            $table->timestamps();
        });

        // Existing schools are upgrades even if they have no students yet.
        // A fresh installation has no school/settings data when migrations run.
        $configuration = json_encode(StudentConfigurationService::defaults(legacy: true), JSON_THROW_ON_ERROR);
        DB::table('schools')->orderBy('id')->chunkById(100, function ($schools) use ($configuration): void {
            foreach ($schools as $school) {
                DB::table('student_configurations')->insert([
                    'scope' => 'school:'.$school->id, 'school_id' => $school->id,
                    'configuration' => $configuration, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        if (DB::table('schools')->exists() || DB::table('students')->exists() || DB::table('id_sequences')->exists() || DB::table('general_settings')->where('is_setup', true)->exists()) {
            DB::table('student_configurations')->insert([
                'scope' => 'installation', 'configuration' => $configuration, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_configurations');
    }
};
