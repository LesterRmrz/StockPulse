<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A throwaway route that exists only during this test.
        Route::middleware(['api', 'auth:sanctum', 'role:admin,manager'])
            ->get('/api/_test/managers-only', fn () => ['ok' => true]);
    }

    public function test_allowed_roles_get_through(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/_test/managers-only')->assertOk();

        $this->actingAs(User::factory()->manager()->create())
            ->getJson('/api/_test/managers-only')->assertOk();
    }

    public function test_other_roles_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create()) // staff
            ->getJson('/api/_test/managers-only')->assertForbidden();
    }

    public function test_guests_are_unauthenticated(): void
    {
        $this->getJson('/api/_test/managers-only')->assertUnauthorized();
    }
}
