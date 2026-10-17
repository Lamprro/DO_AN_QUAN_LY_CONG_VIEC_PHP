<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ai_run_id')->constrained('ai_runs')->restrictOnDelete();
            $table->string('step_key', 60);
            $table->unsignedInteger('attempt_no')->default(1);
            $table->string('provider', 40);
            $table->string('model', 100);
            $table->json('request_payload');
            $table->json('response_payload')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->string('status', 24)->default('running');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unique(['ai_run_id', 'step_key', 'attempt_no'], 'ai_calls_uq_0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_calls');
    }
};
