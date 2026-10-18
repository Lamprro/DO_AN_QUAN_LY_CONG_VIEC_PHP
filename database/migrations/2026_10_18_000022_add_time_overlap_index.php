<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', fn (Blueprint $t) => $t->index(['user_id', 'started_at', 'ended_at'], 'time_entries_user_interval_idx'));
    }

    public function down(): void
    {
        Schema::table('time_entries', fn (Blueprint $t) => $t->dropIndex('time_entries_user_interval_idx'));
    }
};
