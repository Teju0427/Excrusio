<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAndProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_buttons_sign_in_each_role(): void
    {
        $this->post('/demo-login/customer')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs(User::where('email', 'guest@excursio.test')->first());
        $this->post('/logout');

        $this->post('/demo-login/provider');
        $this->assertAuthenticatedAs(User::where('email', 'host.alpine@excursio.test')->first());
        $this->post('/logout');

        $this->post('/demo-login/admin');
        $this->assertAuthenticatedAs(User::where('email', 'admin@excursio.test')->first());
    }

    public function test_demo_logins_can_be_switched_off(): void
    {
        config(['excursio.demo_logins' => false]);

        $this->get('/login')->assertDontSee('Continue as demo');
        $this->post('/demo-login/admin')->assertNotFound();
        $this->assertGuest();
    }

    public function test_demo_login_requires_csrf_token_via_post_only(): void
    {
        $this->get('/demo-login/admin')->assertStatus(405);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->customer()->create(['password' => 'secret123']);

        $this->actingAs($user)->put('/account/profile', ['name' => 'New Name', 'email' => 'new@example.com', 'phone' => '+91 90000 00000'])
            ->assertSessionHas('status');
        $this->assertSame('New Name', $user->fresh()->name);

        $this->put('/account/password', ['current_password' => 'wrong', 'password' => 'another123', 'password_confirmation' => 'another123'])
            ->assertSessionHasErrors('current_password');
        $this->put('/account/password', ['current_password' => 'secret123', 'password' => 'another123', 'password_confirmation' => 'another123'])
            ->assertSessionHas('status');
    }

    public function test_demo_accounts_cannot_change_email_or_password_or_reset_it(): void
    {
        $demo = User::where('email', 'guest@excursio.test')->first();
        $hash = $demo->password;

        $this->actingAs($demo)->put('/account/profile', ['name' => 'Hacker', 'email' => 'mine@example.com'])->assertSessionHasErrors('email');
        $this->put('/account/password', ['current_password' => config('excursio.demo_password'), 'password' => 'another123', 'password_confirmation' => 'another123'])
            ->assertSessionHasErrors('current_password');
        $this->assertSame('guest@excursio.test', $demo->fresh()->email);
        $this->assertSame($hash, $demo->fresh()->password);

        $this->post('/logout');
        $this->post('/forgot-password', ['email' => 'guest@excursio.test'])->assertSessionHasErrors('email');
    }
}
