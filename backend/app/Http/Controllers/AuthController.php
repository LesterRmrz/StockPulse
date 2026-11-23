<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Session-based login for the React SPA (Laravel Sanctum, "SPA authentication").
 *
 * Flow:
 *   1. GET  /sanctum/csrf-cookie  -> sets the XSRF-TOKEN cookie
 *   2. POST /api/login            -> checks the password, starts a session (cookie)
 *   3. Every later request sends the session cookie automatically
 *   4. POST /api/logout           -> ends the session
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): UserResource
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // Same message whether the email or the password was wrong,
            // so the form can't be used to discover which emails exist.
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // New session id after login prevents "session fixation" attacks.
        $request->session()->regenerate();

        return new UserResource($request->user());
    }

    /** One-click login for portfolio visitors, as the demo Manager or Staff account. */
    public function demoLogin(Request $request): UserResource
    {
        $data = $request->validate([
            'role' => ['required', Rule::in([Role::Manager->value, Role::Staff->value])],
        ]);

        $user = User::query()
            ->where('is_demo', true)
            ->where('role', $data['role'])
            ->first();

        abort_if($user === null, 404, 'Demo account not available.');

        Auth::login($user);
        $request->session()->regenerate();

        return new UserResource($user);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        // Throw away the session data and issue a fresh CSRF token.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
