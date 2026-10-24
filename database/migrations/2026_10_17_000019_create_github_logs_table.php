<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignUuid('task_id')->nullable()->constrained('tasks')->restrictOnDelete();
            $table->foreignUuid('integration_connection_id')->constrained('integration_connections')->restrictOnDelete();
            $table->string('delivery_id', 255);
            $table->string('event_type', 60);
            $table->string('repository_external_id', 100);
            $table->string('external_object_id', 100)->nullable();
            $table->string('actor_external_id', 100)->nullable();
            $table->text('summary')->nullable();
            $table->string('url', 512)->nullable();
            $table->json('payload');
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('error_message')->nullable();
            $table->dateTime('occurred_at')->nullable();
            $table->dateTime('received_at');
            $table->dateTime('processed_at')->nullable();
            $table->unique(['integration_connection_id', 'delivery_id', 'project_id'], 'github_logs_uq_0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_logs');
    }
};
