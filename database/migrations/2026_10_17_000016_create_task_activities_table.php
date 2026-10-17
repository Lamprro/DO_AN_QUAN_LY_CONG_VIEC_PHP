<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained('tasks')->restrictOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 24);
            $table->string('event_type', 60)->nullable();
            $table->text('content')->nullable();
            $table->json('data')->nullable();
            $table->foreignUuid('plan_id')->nullable()->constrained('plans')->restrictOnDelete();
            $table->string('source', 24)->default('user');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->index(['task_id', 'type', 'created_at', 'id'], 'task_activities_idx_0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activities');
    }
};
