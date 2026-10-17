<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('predecessor_task_id')->constrained('tasks')->restrictOnDelete();
            $table->foreignUuid('successor_task_id')->constrained('tasks')->restrictOnDelete();
            $table->string('dependency_type', 4)->default('FS');
            $table->unsignedInteger('lag_minutes')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['predecessor_task_id', 'successor_task_id'], 'task_dependencies_uq_0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
    }
};
