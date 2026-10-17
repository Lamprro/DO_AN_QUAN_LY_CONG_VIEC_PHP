<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->nullable()->constrained('workspaces')->restrictOnDelete();
            $table->foreignUuid('recipient_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('task_id')->nullable()->constrained('tasks')->restrictOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->string('type', 60);
            $table->string('title', 200);
            $table->text('body');
            $table->string('dedupe_key', 200);
            $table->json('deliveries');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['dedupe_key'], 'notifications_uq_0');
            $table->index(['recipient_id', 'read_at', 'created_at'], 'notifications_idx_0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
