<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthenticationTest extends FeatureTestCase
{
    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }

    public function test_users_can_authenticate(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => AuditAction::Login->value,
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_users_cannot_authenticate_with_an_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_inactive_users_cannot_authenticate_even_with_the_right_password(): void
    {
        $user = User::factory()->inactive()->create([
            'password' => Hash::make('password'),
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertContains($response->status(), [302, 429]);

        if ($response->status() === 302) {
            $response->assertSessionHasErrors('email');
            $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
        }
    }

    public function test_users_can_logout_and_the_event_is_audited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => AuditAction::Logout->value,
        ]);
    }

    public function test_state_changing_routes_use_the_web_middleware_group(): void
    {
        foreach (['login.store', 'logout', 'partners.store', 'users.store'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains('web', $route->gatherMiddleware(), $name);
        }
    }
}
