<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('operation', 24);
            $table->string('trigger_type', 40);
            $table->text('user_prompt');
            $table->json('context_snapshot');
            $table->string('status', 24)->default('queued');
            $table->string('idempotency_key', 100);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['idempotency_key'], 'ai_runs_uq_0');
            $table->index(['workspace_id', 'status'], 'ai_runs_idx_0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
    }
};
