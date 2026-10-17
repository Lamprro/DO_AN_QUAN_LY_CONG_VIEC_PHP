<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->foreignUuid('parent_project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('draft');
            $table->date('start_date')->nullable();
            $table->date('deadline')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignUuid('github_connection_id')->nullable()->constrained('integration_connections')->restrictOnDelete();
            $table->json('github_config')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'status'], 'projects_idx_0');
        });
    }

    public function down(): void
    {
        // Release self references before DROP TABLE (required by SQLite RESTRICT).
        DB::table('projects')->update(['parent_project_id' => null]);
        Schema::dropIfExists('projects');
    }
};
