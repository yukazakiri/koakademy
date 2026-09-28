<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_servers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('school_id')->constrained('schools');

            // Also the key passed to Mcp::registerClient(), so it has to be a
            // valid client name as well as a human label.
            $table->string('name', 64);

            $table->string('description')->nullable();

            // web = remote HTTP/SSE server, local = stdio command.
            $table->string('transport', 16)->default('web');
            $table->string('url')->nullable();
            $table->string('command')->nullable();
            $table->json('command_arguments')->nullable();

            // none | bearer | oauth. oauth is not yet wired; the column exists
            // so the credential shape does not have to change when it is.
            $table->string('auth_type', 16)->default('none');
            $table->text('credentials')->nullable();

            // Tool allowlist. An empty list exposes nothing, so a newly added
            // server cannot hand its whole catalog to the model by default.
            $table->json('enabled_tools')->nullable();

            // Which of the administrator page agents may see these tools.
            $table->json('allowed_agents')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('timeout_seconds')->default(15);
            $table->unsignedInteger('cache_ttl_seconds')->default(300);

            $table->timestamp('last_connected_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_servers');
    }
};
