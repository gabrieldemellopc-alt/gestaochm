<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_authenticate_with_only_their_corporate_username(): void
    {
        $user = User::factory()->create(['email' => 'josue@gestaochm.com.br']);

        $this->post('/login', ['email' => 'josue', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_trims_a_corporate_username_before_authenticating(): void
    {
        $user = User::factory()->create(['email' => 'josue@gestaochm.com.br']);

        $this->post('/login', ['email' => ' josue ', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_does_not_append_the_corporate_domain_to_an_email_address(): void
    {
        $user = User::factory()->create(['email' => 'josue@empresa.test']);

        $this->post('/login', ['email' => 'josue@empresa.test', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_unknown_username_remains_unauthenticated(): void
    {
        $this->post('/login', ['email' => 'inexistente', 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
