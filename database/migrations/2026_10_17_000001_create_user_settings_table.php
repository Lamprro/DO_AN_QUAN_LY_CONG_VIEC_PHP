<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->json('settings');
            $table->timestamps();
            $table->unique(['user_id'], 'user_settings_uq_0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
