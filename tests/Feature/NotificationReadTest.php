<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationReadTest extends TestCase
{
    use RefreshDatabase;

    private function notificationFor(User $user, array $attributes = []): Notification
    {
        return Notification::factory()->create(['workspace_id' => null, 'recipient_id' => $user->id] + $attributes);
    }

    public function test_ajax_mark_read_returns_stored_state_and_unread_count(): void
    {
        $user = User::factory()->create();
        $target = $this->notificationFor($user);
        $this->notificationFor($user);

        $this->actingAs($user)
            ->patchJson(route('notifications.read', $target->id), ['unread' => false])
            ->assertOk()
            ->assertJson(['success' => true, 'id' => $target->id, 'read' => true, 'unread_count' => 1]);

        $this->assertNotNull($target->fresh()->read_at);
    }

    public function test_ajax_mark_unread_clears_read_at(): void
    {
        $user = User::factory()->create();
        $target = $this->notificationFor($user, ['read_at' => now()]);

        $this->actingAs($user)
            ->patchJson(route('notifications.read', $target->id), ['unread' => true])
            ->assertOk()
            ->assertJson(['read' => false, 'read_at' => null, 'unread_count' => 1]);

        $this->assertNull($target->fresh()->read_at);
    }

    public function test_ajax_read_all_returns_zero_unread(): void
    {
        $user = User::factory()->create();
        $this->notificationFor($user);
        $this->notificationFor($user);

        $this->actingAs($user)
            ->postJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJson(['success' => true, 'unread_count' => 0]);
    }

    public function test_cannot_mark_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $target = $this->notificationFor($owner);

        $this->actingAs($other)
            ->patchJson(route('notifications.read', $target->id), ['unread' => false])
            ->assertNotFound();

        $this->assertNull($target->fresh()->read_at);
    }

    public function test_form_post_without_js_still_redirects_back(): void
    {
        $user = User::factory()->create();
        $target = $this->notificationFor($user);

        $this->actingAs($user)
            ->from(route('notifications.index'))
            ->patch(route('notifications.read', $target->id), ['unread' => 0])
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull($target->fresh()->read_at);
    }

    public function test_inbox_page_loads_the_script(): void
    {
        $user = User::factory()->create();
        $this->notificationFor($user);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('js/notification-inbox.js', false)
            ->assertSee('data-read-toggle', false);
    }
}
