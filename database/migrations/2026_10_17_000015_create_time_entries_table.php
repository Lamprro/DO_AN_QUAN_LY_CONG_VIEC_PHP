<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained('tasks')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('calendar_event_id')->nullable()->constrained('calendar_events')->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->uuid('running_user_key')->nullable()->storedAs('CASE WHEN ended_at IS NULL THEN user_id ELSE NULL END');
            $table->unique('running_user_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
