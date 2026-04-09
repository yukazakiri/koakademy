<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('name');
                $blueprint->string('username')->nullable();
                $blueprint->string('email')->unique();
                $blueprint->string('phone')->nullable();
                $blueprint->string('password');
                $blueprint->string('role')->default('student');
                $blueprint->boolean('is_active')->default(true);
                $blueprint->boolean('is_login')->default(false);
                $blueprint->boolean('is_notification_active')->default(true);
                $blueprint->bigInteger('person_id')->nullable();
                $blueprint->string('person_type')->nullable();
                $blueprint->string('profile_photo_path')->nullable();
                $blueprint->timestamp('email_verified_at')->nullable();
                $blueprint->timestamp('two_factor_confirmed_at')->nullable();
                $blueprint->timestamp('otp_activated_at')->nullable();
                $blueprint->timestamp('last_login')->nullable();
                $blueprint->rememberToken();
                $blueprint->string('two_factor_secret')->nullable();
                $blueprint->text('two_factor_recovery_codes')->nullable();
                $blueprint->string('stripe_id')->nullable();
                $blueprint->string('pm_type')->nullable();
                $blueprint->string('pm_last_four', 4)->nullable();
                $blueprint->timestamp('trial_ends_at')->nullable();
                $blueprint->timestamps();
                $blueprint->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
