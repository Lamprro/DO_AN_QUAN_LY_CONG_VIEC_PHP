<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_group_id');
            $table->foreignUuid('parent_plan_id')->nullable()->constrained('plans')->restrictOnDelete();
            $table->unsignedInteger('version_no')->default(1);
            $table->foreignUuid('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->foreignUuid('ai_run_id')->nullable()->constrained('ai_runs')->restrictOnDelete();
            $table->string('name', 180);
            $table->json('data');
            $table->string('status', 24)->default('draft');
            $table->boolean('is_active')->default(false);
            $table->json('validation_errors')->nullable();
            $table->text('change_summary')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->uuid('active_group_key')->nullable()->storedAs('CASE WHEN is_active = 1 THEN plan_group_id ELSE NULL END');
            $table->unique('active_group_key');
            $table->unique(['plan_group_id', 'version_no'], 'plans_uq_0');
            $table->index(['workspace_id', 'project_id', 'status'], 'plans_idx_0');
        });
    }

    public function down(): void
    {
        // Release self references before DROP TABLE (required by SQLite RESTRICT).
        DB::table('plans')->update(['parent_plan_id' => null]);
        Schema::dropIfExists('plans');
    }
};
