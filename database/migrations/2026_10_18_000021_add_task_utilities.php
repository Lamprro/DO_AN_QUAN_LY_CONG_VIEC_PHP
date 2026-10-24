<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_task_filters', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('project_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('name', 80);
            $t->json('query');
            $t->timestamps();
            $t->index(['user_id', 'workspace_id', 'project_id']);
        });
        Schema::create('time_entry_adjustments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('time_entry_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $t->json('before');
            $t->json('after');
            $t->text('reason');
            $t->dateTime('created_at');
            $t->index(['time_entry_id', 'created_at']);
        });
        Schema::create('task_reminders', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('workspace_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('task_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $t->string('kind', 16);
            $t->date('local_date');
            $t->string('timezone', 64);
            $t->string('source_token', 64);
            $t->unsignedInteger('review_after_minutes')->default(60);
            $t->string('dedupe_key', 64)->unique();
            $t->string('status', 16)->default('queued');
            $t->string('skip_reason', 160)->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->index(['workspace_id', 'user_id', 'created_at']);
        });
        Schema::table('tasks', function (Blueprint $t) {
            $t->index(['workspace_id', 'project_id', 'assignee_id', 'status'], 'tasks_scope_assignee_status_idx');
            $t->index(['workspace_id', 'project_id', 'reviewer_id', 'status'], 'tasks_scope_reviewer_status_idx');
            $t->index(['workspace_id', 'project_id', 'deadline_at'], 'tasks_scope_deadline_idx');
        });
        Schema::table('task_blockers', fn (Blueprint $t) => $t->index(['task_id', 'status'], 'blockers_task_status_idx'));
    }

    public function down(): void
    {
        Schema::table('task_blockers', fn (Blueprint $t) => $t->dropIndex('blockers_task_status_idx'));
        Schema::table('tasks', function (Blueprint $t) {
            $t->dropIndex('tasks_scope_assignee_status_idx');
            $t->dropIndex('tasks_scope_reviewer_status_idx');
            $t->dropIndex('tasks_scope_deadline_idx');
        });
        Schema::dropIfExists('task_reminders');
        Schema::dropIfExists('time_entry_adjustments');
        Schema::dropIfExists('saved_task_filters');
    }
};
