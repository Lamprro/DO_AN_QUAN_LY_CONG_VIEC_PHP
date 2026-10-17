<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('joined_at');
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id'], 'workspace_members_uq_0');
            $table->index(['user_id', 'status'], 'workspace_members_idx_0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_members');
    }
};
