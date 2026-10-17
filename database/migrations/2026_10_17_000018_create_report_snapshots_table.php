<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->foreignUuid('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('ai_run_id')->nullable()->constrained('ai_runs')->restrictOnDelete();
            $table->string('scope', 20);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->json('metrics_json');
            $table->text('ai_commentary')->nullable();
            $table->string('storage_key', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['workspace_id', 'period_start'], 'report_snapshots_idx_0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_snapshots');
    }
};
