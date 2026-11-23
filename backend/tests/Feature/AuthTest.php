<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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

    public function test_remember_me_sets_a_long_lived_cookie(): void
    {
        $user = User::factory()->create(['email' => 'lena@example.com']);

        $this->fromSpa()
            ->postJson('/api/login', ['email' => 'lena@example.com', 'password' => 'password', 'remember' => true])
            ->assertOk()
            ->assertCookie($this->recallerCookieName());

        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_without_remember_me_there_is_no_remember_cookie(): void
    {
        User::factory()->create(['email' => 'lena@example.com']);

        $this->fromSpa()
            ->postJson('/api/login', ['email' => 'lena@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertCookieMissing($this->recallerCookieName());
    }

    /**
     * The session has expired (no session cookie is sent), but the remember
     * cookie is still there, so Laravel signs the user back in.
     */
    public function test_remember_cookie_signs_the_user_back_in(): void
    {
        $user = User::factory()->create(['remember_token' => 'known-token']);

        // Same format Laravel writes: "id|token|password hash" (the test client encrypts it)
        $recaller = $user->id.'|known-token|'.$user->getAuthPassword();

        $this->withCookie($this->recallerCookieName(), $recaller)
            ->withCredentials() // JSON test requests only send cookies when asked to
            ->fromSpa()
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_logout_invalidates_old_remember_cookies(): void
    {
        $user = User::factory()->create(['remember_token' => 'old-token']);

        $this->actingAs($user)->fromSpa()->postJson('/api/logout')->assertNoContent();

        // A new token means any copy of the old cookie no longer matches
        $this->assertNotSame('old-token', $user->fresh()->remember_token);
    }

    private function recallerCookieName(): string
    {
        return Auth::guard('web')->getRecallerName();
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
