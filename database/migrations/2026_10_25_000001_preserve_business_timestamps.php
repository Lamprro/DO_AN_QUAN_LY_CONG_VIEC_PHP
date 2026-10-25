<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        // Older MariaDB implicitly adds ON UPDATE to the first required TIMESTAMP.
        // An explicit default removes that behaviour without converting stored dates.
        foreach (['workspace_members' => 'joined_at', 'project_members' => 'joined_at', 'time_entries' => 'started_at', 'ai_calls' => 'started_at'] as $table => $column) {
            Schema::table($table, fn (Blueprint $t) => $t->timestamp($column)->useCurrent()->change());
        }
    }

    public function down(): void
    {
        // Preserve the safe explicit defaults; never reinstate implicit ON UPDATE.
    }
};
