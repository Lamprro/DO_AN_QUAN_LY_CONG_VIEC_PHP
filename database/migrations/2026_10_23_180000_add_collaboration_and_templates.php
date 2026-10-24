<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_proposals', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('workspace_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('project_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignUuid('task_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignUuid('proposed_by')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('recipient_id')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('calendar_event_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title', 200);
            $t->text('description')->nullable();
            $t->string('event_type', 20);
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('timezone', 64);
            $t->string('visibility', 20);
            $t->string('status', 20)->default('pending');
            $t->unsignedInteger('revision')->default(1);
            $t->dateTime('responded_at')->nullable();
            $t->timestamps();
            $t->index(['recipient_id', 'status']);
            $t->index(['workspace_id', 'project_id']);
        });
        Schema::create('task_watchers', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('added_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['task_id', 'user_id']);
        });
        Schema::create('task_templates', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('workspace_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('project_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->string('name', 160);
            $t->json('definition');
            $t->string('frequency', 16)->nullable();
            $t->unsignedSmallInteger('interval')->default(1);
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->string('local_time', 5)->nullable();
            $t->string('timezone', 64);
            $t->unsignedSmallInteger('deadline_offset_days')->nullable();
            $t->dateTime('next_run_at')->nullable();
            $t->string('status', 16)->default('paused');
            $t->unsignedInteger('revision')->default(1);
            $t->text('last_error')->nullable();
            $t->timestamps();
            $t->index(['status', 'next_run_at']);
        });
        Schema::create('task_template_runs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('task_template_id')->constrained()->restrictOnDelete();
            $t->string('occurrence_key', 100);
            $t->string('status', 16);
            $t->json('task_map')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
            $t->unique(['task_template_id', 'occurrence_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_template_runs');
        Schema::dropIfExists('task_templates');
        Schema::dropIfExists('task_watchers');
        Schema::dropIfExists('calendar_proposals');
    }
};
