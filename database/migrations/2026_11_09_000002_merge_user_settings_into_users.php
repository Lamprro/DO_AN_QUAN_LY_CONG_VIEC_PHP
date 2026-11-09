<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * user_settings was a strict 1-1 table holding a single JSON column.
 * The JSON now lives in users.settings; every row is copied before the old table is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->json('settings')->nullable());

        if (! Schema::hasTable('user_settings')) {
            return;
        }

        DB::transaction(function () {
            foreach (DB::table('user_settings')->get(['user_id', 'settings']) as $row) {
                DB::table('users')->where('id', $row->user_id)->update(['settings' => $row->settings]);
            }
        });

        Schema::drop('user_settings');
    }

    public function down(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->json('settings');
            $table->timestamps();
            $table->unique(['user_id'], 'user_settings_uq_0');
        });

        DB::transaction(function () {
            foreach (DB::table('users')->whereNotNull('settings')->get(['id', 'settings']) as $user) {
                DB::table('user_settings')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'settings' => $user->settings, 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('settings'));
    }
};
