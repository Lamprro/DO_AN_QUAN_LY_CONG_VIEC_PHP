<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Đăng ký');
    }

    public function test_new_users_always_redirect_to_verification_notice(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Nguyễn Văn Test',
            'email' => 'testuser@example.test',
            'password' => 'Password123@',
            'password_confirmation' => 'Password123@',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'testuser@example.test')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
    }

    public function test_unverified_user_cannot_access_workspaces(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/workspaces');

        $response->assertRedirect(route('verification.notice'));
    }
}
