<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignUuid('workspace_member_id')->constrained('workspace_members')->restrictOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('joined_at');
            $table->unique(['project_id', 'workspace_member_id'], 'project_members_uq_0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');
    }
};
