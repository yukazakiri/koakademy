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
        Schema::table('sanity_contents', function (Blueprint $table): void {
            // Update existing columns
            $table->dropColumn(['document_type', 'content']);

            // Post Kind and Content
            $table->string('post_kind')->default('news')->after('sanity_id');
            $table->text('excerpt')->nullable()->after('title');
            $table->json('content')->nullable()->after('excerpt');
            $table->string('content_focus')->nullable()->after('post_kind');

            // Featured Image
            $table->json('featured_image')->nullable()->after('content');

            // Priority and Activation (for alerts/announcements)
            $table->string('priority')->default('normal')->after('status');
            $table->json('activation_window')->nullable()->after('priority');
            $table->json('channels')->nullable()->after('activation_window');
            $table->json('cta')->nullable()->after('channels');

            // Flags
            $table->boolean('featured')->default(false)->after('published_at');

            // Legacy fields
            $table->string('category')->nullable()->after('featured');
            $table->string('author')->nullable()->after('category');

            // Arrays
            $table->json('tags')->nullable()->after('author');
            $table->json('audiences')->nullable()->after('tags');

            // References (stored as IDs or arrays of IDs)
            $table->string('primary_category_id')->nullable()->after('audiences');
            $table->json('department_ids')->nullable()->after('primary_category_id');
            $table->json('program_ids')->nullable()->after('department_ids');
            $table->json('author_ids')->nullable()->after('program_ids');
            $table->json('related_post_ids')->nullable()->after('author_ids');

            // SEO
            $table->json('seo')->nullable()->after('related_post_ids');

            // Add updated_at tracking from Sanity
            $table->timestamp('sanity_updated_at')->nullable()->after('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sanity_contents', function (Blueprint $table): void {
            $table->dropColumn([
                'post_kind',
                'excerpt',
                'content_focus',
                'featured_image',
                'priority',
                'activation_window',
                'channels',
                'cta',
                'featured',
                'category',
                'author',
                'tags',
                'audiences',
                'primary_category_id',
                'department_ids',
                'program_ids',
                'author_ids',
                'related_post_ids',
                'seo',
                'sanity_updated_at',
            ]);

            // Restore original columns
            $table->string('document_type')->after('sanity_id');
            $table->text('content')->nullable()->after('title');
        });
    }
};
