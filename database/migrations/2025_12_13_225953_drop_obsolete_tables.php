<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::dropIfExists('account_balance');
        Schema::dropIfExists('account_request_metas');
        Schema::dropIfExists('account_requests');
        Schema::dropIfExists('account_transactions');
        Schema::dropIfExists('accountportal');
        Schema::dropIfExists('breezy_sessions');
        Schema::dropIfExists('bulk_action_records');
        Schema::dropIfExists('bulk_actions');
        Schema::dropIfExists('ch_favorites');
        Schema::dropIfExists('ch_messages');
        Schema::dropIfExists('clients');

        // Drop dependent/join tables before parent tables to satisfy PostgreSQL FKs
        Schema::dropIfExists('fblog_category_fblog_post');
        Schema::dropIfExists('fblog_post_fblog_tag'); // fixed table name (was fblob_)
        Schema::dropIfExists('fblog_comments');
        Schema::dropIfExists('fblog_seo_details');
        Schema::dropIfExists('fblog_share_snippets');

        // Then drop parent/base tables
        Schema::dropIfExists('fblog_tags');
        Schema::dropIfExists('fblog_posts'); // fixed table name (was fblob_)
        Schema::dropIfExists('fblog_categories');

        // Remaining standalone tables
        Schema::dropIfExists('fblog_jobs');
        Schema::dropIfExists('fblog_news_letters');
        Schema::dropIfExists('fblog_settings');
        Schema::dropIfExists('filachat_agents');
        Schema::dropIfExists('filachat_conversations');
        Schema::dropIfExists('filachat_messages');
        Schema::dropIfExists('filament_db_sync_table');
        Schema::dropIfExists('filaponds');
        Schema::dropIfExists('guest_education_id');
        Schema::dropIfExists('guest_enrollments');
        Schema::dropIfExists('guest_guardian_contact');
        Schema::dropIfExists('guest_personal_info');
        Schema::dropIfExists('guest_tuition');
        Schema::dropIfExists('guest_parents_info');
        Schema::dropIfExists('health_check_result_history_items');
        Schema::dropIfExists('missing_student_requests');
        Schema::dropIfExists('notification_campaigns');
        Schema::dropIfExists('pending_enrollments');
        Schema::dropIfExists('pending_user_emails');
        Schema::dropIfExists('private_beta_invitations');
        Schema::dropIfExists('process_approval_flow_steps');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('route_statistics');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('server_metrics');
        Schema::dropIfExists('shetabit_visits');

        Schema::dropIfExists('table_cols');
        Schema::dropIfExists('tables');

        Schema::dropIfExists('timesheets');

        Schema::dropIfExists('types');
        Schema::dropIfExists('types_metas');
        Schema::dropIfExists('typables');
        Schema::dropIfExists('uptime_checks');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
