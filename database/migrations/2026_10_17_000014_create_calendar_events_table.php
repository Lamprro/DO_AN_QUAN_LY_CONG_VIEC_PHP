<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('workspace_id')->nullable()->constrained('workspaces')->restrictOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->foreignUuid('task_id')->nullable()->constrained('tasks')->restrictOnDelete();
            $table->foreignUuid('plan_id')->nullable()->constrained('plans')->restrictOnDelete();
            $table->string('event_type', 24)->default('NOTE');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone', 64)->default('Asia/Ho_Chi_Minh');
            $table->boolean('all_day')->default(false);
            $table->boolean('is_busy')->default(false);
            $table->string('visibility', 24)->default('private');
            $table->string('status', 24)->default('planned');
            $table->json('participants')->nullable();
            $table->foreignUuid('integration_connection_id')->nullable()->constrained('integration_connections')->restrictOnDelete();
            $table->string('external_id', 255)->nullable();
            $table->string('source', 24)->default('manual');
            $table->timestamps();
            $table->unique(['integration_connection_id', 'external_id'], 'calendar_events_uq_0');
            $table->index(['user_id', 'starts_at'], 'calendar_events_idx_0');
            $table->index(['task_id', 'starts_at'], 'calendar_events_idx_1');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
