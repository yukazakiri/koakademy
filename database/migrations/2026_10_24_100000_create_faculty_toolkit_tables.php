<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_rubrics', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('faculty_id')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('criteria');
            $table->boolean('is_shared')->default(false);
            $table->unsignedBigInteger('school_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('inbox_threads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('faculty_id')->index();
            $table->unsignedBigInteger('class_id')->nullable()->index();
            $table->unsignedBigInteger('student_id')->nullable()->index();
            $table->string('subject');
            $table->string('status')->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inbox_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('thread_id')->index();
            $table->string('sender_type')->default('faculty');
            $table->unsignedBigInteger('sender_id');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inbox_templates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('faculty_id')->index();
            $table->string('name');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('office_hour_slots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('faculty_id')->index();
            $table->string('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('mode')->default('in_person');
            $table->string('location')->nullable();
            $table->unsignedInteger('capacity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('school_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('office_hour_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('slot_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->text('topic')->nullable();
            $table->string('status')->default('booked');
            $table->timestamp('booked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('faculty_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('faculty_id')->index();
            $table->string('type');
            $table->string('title');
            $table->text('details')->nullable();
            $table->json('steps')->nullable();
            $table->unsignedInteger('current_step')->default(0);
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedBigInteger('school_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('class_resources', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('class_id')->index();
            $table->unsignedBigInteger('faculty_id')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('link');
            $table->string('url');
            $table->string('visibility')->default('faculty_only');
            $table->unsignedInteger('downloads')->default(0);
            $table->unsignedBigInteger('school_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('alert_actions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('class_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('faculty_id')->index();
            $table->string('action');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_actions');
        Schema::dropIfExists('class_resources');
        Schema::dropIfExists('faculty_requests');
        Schema::dropIfExists('office_hour_bookings');
        Schema::dropIfExists('office_hour_slots');
        Schema::dropIfExists('inbox_templates');
        Schema::dropIfExists('inbox_messages');
        Schema::dropIfExists('inbox_threads');
        Schema::dropIfExists('assessment_rubrics');
    }
};
