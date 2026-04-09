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
        if (! Schema::hasTable('general_settings')) {
            Schema::create('general_settings', function (Blueprint $table): void {
                $table->id();

                // Basic site settings
                $table->string('site_name')->nullable();
                $table->text('site_description')->nullable();
                $table->string('theme_color')->nullable();
                $table->string('support_email')->nullable();
                $table->string('support_phone')->nullable();

                // Analytics and tracking
                $table->string('google_analytics_id')->nullable();
                $table->text('posthog_html_snippet')->nullable();

                // SEO settings
                $table->string('seo_title')->nullable();
                $table->text('seo_keywords')->nullable();
                $table->json('seo_metadata')->nullable();

                // Email settings
                $table->json('email_settings')->nullable();
                $table->string('email_from_address')->nullable();
                $table->string('email_from_name')->nullable();

                // Social and configuration
                $table->json('social_network')->nullable();
                $table->json('more_configs')->nullable();

                // School settings
                $table->date('school_starting_date')->nullable();
                $table->date('school_ending_date')->nullable();
                $table->string('school_portal_url')->nullable();
                $table->boolean('school_portal_enabled')->default(false);
                $table->boolean('online_enrollment_enabled')->default(false);
                $table->boolean('school_portal_maintenance')->default(false);

                // Academic settings
                $table->integer('semester')->default(1);
                $table->json('enrollment_courses')->nullable();
                $table->string('curriculum_year')->nullable();

                // Portal branding
                $table->string('school_portal_logo')->nullable();
                $table->string('school_portal_favicon')->nullable();
                $table->string('school_portal_title')->nullable();
                $table->text('school_portal_description')->nullable();

                // Feature toggles
                $table->boolean('enable_clearance_check')->default(false);
                $table->boolean('enable_signatures')->default(false);
                $table->boolean('enable_qr_codes')->default(false);
                $table->boolean('enable_public_transactions')->default(false);
                $table->boolean('enable_support_page')->default(false);
                $table->boolean('inventory_module_enabled')->default(false);
                $table->boolean('library_module_enabled')->default(false);
                $table->boolean('enable_student_transfer_email_notifications')->default(false);
                $table->boolean('enable_faculty_transfer_email_notifications')->default(false);

                // Additional features
                $table->json('features')->nullable();

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_settings');
    }
};
