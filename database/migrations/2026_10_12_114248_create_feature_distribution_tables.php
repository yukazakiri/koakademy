<?php

declare(strict_types=1);

use App\Services\FeatureToggleRegistry;
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
        Schema::create('feature_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('enabled')->default(true);
            $table->string('mode')->default('legacy');
            $table->string('strategy')->default('defaults');
            $table->unsignedSmallInteger('percentage')->default(0);
            $table->string('rollout_seed', 64);
            $table->boolean('allow_opt_in')->default(false);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
        Schema::create('feature_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('school_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('feature_group_user', function (Blueprint $table): void {
            $table->foreignId('feature_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['feature_group_id', 'user_id']);
            $table->index('user_id');
        });
        Schema::create('feature_assignments', function (Blueprint $table): void {
            $table->id();
            $table->string('feature_key');
            $table->string('subject_type', 16);
            $table->string('subject_key', 64);
            $table->string('effect', 8);
            $table->string('source', 16)->default('administrator');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['feature_key', 'subject_type', 'subject_key', 'source'], 'feature_assignment_subject_unique');
        });
        Schema::create('feature_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('feature_key')->nullable()->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at');
        });

        // One-time bootstrap. Never guess override provenance from old rows.
        $existing = DB::table('users')->exists()
            || (Schema::hasTable('general_settings') && DB::table('general_settings')->where('is_setup', true)->exists());
        foreach (FeatureToggleRegistry::allKeys() as $key) {
            DB::table('feature_definitions')->insert([
                'key' => $key, 'enabled' => true, 'mode' => $existing ? 'legacy' : 'enforced',
                'strategy' => 'defaults', 'percentage' => 0, 'rollout_seed' => hash('sha256', 'koakademy:'.$key),
                'allow_opt_in' => false, 'revision' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Local rollback only. Production changes mode without dropping policy.
        Schema::dropIfExists('feature_audit_logs');
        Schema::dropIfExists('feature_assignments');
        Schema::dropIfExists('feature_group_user');
        Schema::dropIfExists('feature_groups');
        Schema::dropIfExists('feature_definitions');
    }
};
