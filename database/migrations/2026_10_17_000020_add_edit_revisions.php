<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['tasks', 'projects'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('revision')->default(1));
        }
    }

    public function down(): void
    {
        foreach (['tasks', 'projects'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('revision'));
        }
    }
};
