<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_and_fetch_their_profile(): void
    {
        $user = User::factory()->manager()->create(['email' => 'lena@example.com']);

        $this->fromSpa()
            ->postJson('/api/login', ['email' => 'lena@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.email', 'lena@example.com')
            ->assertJsonPath('data.role', 'manager')
            ->assertJsonMissingPath('data.password');

        $this->assertAuthenticatedAs($user);

        $this->fromSpa()->getJson('/api/user')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'lena@example.com']);

        $this->fromSpa()
            ->postJson('/api/login', ['email' => 'lena@example.com', 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->fromSpa()
            ->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_guest_cannot_read_profile(): void
    {
        $this->fromSpa()->getJson('/api/user')->assertUnauthorized();
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->fromSpa()->postJson('/api/logout')->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_demo_login_signs_in_the_demo_account_for_that_role(): void
    {
        $demoStaff = User::factory()->demo()->create();

        $this->fromSpa()
            ->postJson('/api/demo-login', ['role' => 'staff'])
            ->assertOk()
            ->assertJsonPath('data.is_demo', true);

        $this->assertAuthenticatedAs($demoStaff);
    }

    public function test_demo_login_cannot_be_used_for_admin(): void
    {
        User::factory()->admin()->demo()->create();

        $this->fromSpa()
            ->postJson('/api/demo-login', ['role' => 'admin'])
            ->assertStatus(422);

        $this->assertGuest();
    }

    public function test_demo_login_ignores_non_demo_accounts(): void
    {
        User::factory()->manager()->create(); // a real manager, not a demo account

        $this->fromSpa()->postJson('/api/demo-login', ['role' => 'manager'])->assertNotFound();

        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'lena@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->fromSpa()->postJson('/api/login', ['email' => 'lena@example.com', 'password' => 'wrong'])->assertStatus(422);
        }

        $this->fromSpa()
            ->postJson('/api/login', ['email' => 'lena@example.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }
}
