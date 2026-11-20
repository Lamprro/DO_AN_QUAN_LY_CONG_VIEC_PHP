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

    public function test_registration_failure_shows_inline_field_errors_and_toast_carrier(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
            'timezone' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'timezone']);

        $follow = $this->get('/register');
        $follow->assertStatus(200);
        $follow->assertSee('data-flash-type="error"', false);
        $follow->assertSee('Đăng ký không thành công', false);
        $follow->assertSee('class="error"', false);
        $follow->assertSee('aria-invalid="true"', false);
        $follow->assertSee('sr-only', false);
    }

    public function test_login_failure_shows_inline_error_and_toast_carrier(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'nonexistent@example.test',
            'password' => 'WrongPassword123@',
        ]);

        $response->assertSessionHasErrors('email');

        $follow = $this->get('/login');
        $follow->assertStatus(200);
        $follow->assertSee('data-flash-type="error"', false);
        $follow->assertSee('Đăng nhập không thành công', false);
        $follow->assertSee('Email, mật khẩu không đúng hoặc tài khoản không hoạt động.', false);
        $follow->assertSee('class="error"', false);
    }

    public function test_unmapped_business_error_renders_carrier_and_dedicated_banner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag([
                'task' => ['Hãy lưu trữ task con trước khi lưu trữ GROUP.'],
            ]))])
            ->get('/workspaces');

        $response->assertStatus(200);
        $response->assertSee('unmapped-errors-carrier', false);
        $response->assertSee('unmapped-errors-banner', false);
        $response->assertSee('data-field="task"', false);
        $response->assertSee('Hãy lưu trữ task con trước khi lưu trữ GROUP.', false);
    }
}
