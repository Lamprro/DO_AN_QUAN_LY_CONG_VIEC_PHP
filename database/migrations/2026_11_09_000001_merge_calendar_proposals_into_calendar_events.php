<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * calendar_proposals duplicated the event columns and copied them into a new
 * calendar_events row on accept. A proposal is now a calendar_events row with
 * proposed_by set; status proposed/declined/withdrawn keeps it off every calendar.
 *
 * Data move (no row is lost):
 *  - accepted/cancelled proposal with an existing event -> that event gets proposed_by/responded_at
 *    (event id is kept, time entries stay attached); notification keys are re-pointed to the event id.
 *  - pending -> proposed, declined -> declined, cancelled before acceptance -> withdrawn (same id).
 *  - accepted whose event was later deleted -> cancelled (same id), so the history row remains.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $t) {
            $t->foreignUuid('proposed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->dateTime('responded_at')->nullable();
            $t->index(['proposed_by', 'status'], 'calendar_events_proposer_status_idx');
            $t->index(['user_id', 'status'], 'calendar_events_owner_status_idx');
        });

        if (! Schema::hasTable('calendar_proposals')) {
            return;
        }

        DB::transaction(function () {
            foreach (DB::table('calendar_proposals')->orderBy('created_at')->orderBy('id')->get() as $p) {
                if ($p->calendar_event_id && DB::table('calendar_events')->where('id', $p->calendar_event_id)->exists()) {
                    DB::table('calendar_events')->where('id', $p->calendar_event_id)->update(['proposed_by' => $p->proposed_by, 'responded_at' => $p->responded_at]);
                    $this->repointNotifications($p->id, $p->calendar_event_id);

                    continue;
                }
                DB::table('calendar_events')->insert([
                    'id' => $p->id, 'user_id' => $p->recipient_id, 'workspace_id' => $p->workspace_id, 'project_id' => $p->project_id,
                    'task_id' => $p->task_id, 'plan_id' => null, 'event_type' => $p->event_type, 'title' => $p->title, 'description' => $p->description,
                    'starts_at' => $p->starts_at, 'ends_at' => $p->ends_at, 'timezone' => $p->timezone, 'all_day' => false, 'is_busy' => true,
                    'visibility' => $p->visibility, 'participants' => '[]', 'integration_connection_id' => null, 'external_id' => null, 'source' => 'manual',
                    'status' => match ($p->status) {
                        'pending' => 'proposed', 'declined' => 'declined', 'cancelled' => 'withdrawn', default => 'cancelled',
                    },
                    'proposed_by' => $p->proposed_by, 'responded_at' => $p->responded_at, 'created_at' => $p->created_at, 'updated_at' => $p->updated_at,
                ]);
            }
        });

        Schema::drop('calendar_proposals');
    }

    public function down(): void
    {
        Schema::create('calendar_proposals', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('workspace_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('project_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignUuid('task_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignUuid('proposed_by')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('recipient_id')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('calendar_event_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title', 200);
            $t->text('description')->nullable();
            $t->string('event_type', 20);
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('timezone', 64);
            $t->string('visibility', 20);
            $t->string('status', 20)->default('pending');
            $t->unsignedInteger('revision')->default(1);
            $t->dateTime('responded_at')->nullable();
            $t->timestamps();
            $t->index(['recipient_id', 'status']);
            $t->index(['workspace_id', 'project_id']);
        });

        $proposalOnly = ['proposed', 'declined', 'withdrawn'];
        DB::transaction(function () use ($proposalOnly) {
            foreach (DB::table('calendar_events')->whereNotNull('proposed_by')->orderBy('created_at')->get() as $e) {
                $onlyProposal = in_array($e->status, $proposalOnly, true);
                $id = $onlyProposal ? $e->id : (string) Str::uuid();
                DB::table('calendar_proposals')->insert([
                    'id' => $id, 'workspace_id' => $e->workspace_id, 'project_id' => $e->project_id, 'task_id' => $e->task_id,
                    'proposed_by' => $e->proposed_by, 'recipient_id' => $e->user_id, 'calendar_event_id' => $onlyProposal ? null : $e->id,
                    'title' => $e->title, 'description' => $e->description, 'event_type' => $e->event_type, 'starts_at' => $e->starts_at, 'ends_at' => $e->ends_at,
                    'timezone' => $e->timezone, 'visibility' => $e->visibility,
                    'status' => match ($e->status) {
                        'proposed' => 'pending', 'declined' => 'declined', 'withdrawn', 'cancelled' => 'cancelled', default => 'accepted',
                    },
                    'revision' => 1, 'responded_at' => $e->responded_at, 'created_at' => $e->created_at, 'updated_at' => $e->updated_at,
                ]);
                if (! $onlyProposal) {
                    $this->repointNotifications($e->id, $id);
                }
            }
            DB::table('calendar_events')->whereIn('status', $proposalOnly)->delete();
        });

        Schema::table('calendar_events', function (Blueprint $t) {
            $t->dropIndex('calendar_events_proposer_status_idx');
            $t->dropIndex('calendar_events_owner_status_idx');
            $t->dropForeign(['proposed_by']);
            $t->dropColumn(['proposed_by', 'responded_at']);
        });
    }

    /** Notification links open Team Calendar by the id inside dedupe_key "calendar-proposal:{id}:...". */
    private function repointNotifications(string $from, string $to): void
    {
        $prefix = 'calendar-proposal:'.$from.':';
        foreach (DB::table('notifications')->where('dedupe_key', 'like', $prefix.'%')->get(['id', 'dedupe_key']) as $n) {
            DB::table('notifications')->where('id', $n->id)->update(['dedupe_key' => 'calendar-proposal:'.$to.':'.substr($n->dedupe_key, strlen($prefix))]);
        }
    }
};
