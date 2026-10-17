<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->foreignUuid('parent_task_id')->nullable()->constrained('tasks')->restrictOnDelete();
            $table->string('task_type', 20)->default('WORK');
            $table->string('group_label', 120)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('code', 40);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->foreignUuid('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('TODO');
            $table->string('priority', 16)->default('medium');
            $table->unsignedInteger('estimate_minutes')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'code'], 'tasks_uq_0');
            $table->index(['workspace_id', 'project_id', 'status'], 'tasks_idx_0');
            $table->index(['assignee_id', 'deadline_at'], 'tasks_idx_1');
            $table->index(['parent_task_id', 'sort_order'], 'tasks_idx_2');
        });
    }

    public function down(): void
    {
        // Release self references before DROP TABLE (required by SQLite RESTRICT).
        DB::table('tasks')->update(['parent_task_id' => null]);
        Schema::dropIfExists('tasks');
    }
};
