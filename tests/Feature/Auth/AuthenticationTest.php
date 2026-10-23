<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function validRegistration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Asha Rao', 'email' => 'asha@example.com', 'phone' => '+91 98765 43210',
            'account_type' => 'customer', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'terms' => '1',
        ], $overrides);
    }

    public function test_pages_render(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back');
        $this->get('/register')->assertOk()->assertSee('Join Excursio');
        $this->get('/forgot-password')->assertOk();
    }

    public function test_guest_can_register_and_is_signed_in(): void
    {
        $this->post('/register', $this->validRegistration())->assertRedirect(route('dashboard'));

        $user = User::where('email', 'asha@example.com')->first();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNotSame('secret123', $user->password, 'password must be hashed');
        $this->assertTrue(AuditLog::where('action', 'auth.registered')->exists());
    }

    public function test_new_host_starts_pending_and_cannot_open_host_tools(): void
    {
        $this->post('/register', $this->validRegistration(['account_type' => 'provider']))->assertRedirect(route('host.pending'));

        $this->assertSame(UserStatus::Pending, User::where('email', 'asha@example.com')->first()->status);
        $this->get('/host')->assertRedirect(route('host.pending'));
        $this->get('/host/pending')->assertOk()->assertSee('Thanks for joining as a host');
    }

    public function test_nobody_can_register_as_admin(): void
    {
        $this->post('/register', $this->validRegistration(['account_type' => 'admin']))->assertSessionHasErrors('account_type');
        $this->post('/register', $this->validRegistration(['role' => 'admin', 'status' => 'active']));

        $this->assertSame(UserRole::Customer, User::where('email', 'asha@example.com')->first()->role);
    }

    public function test_registration_validates_input(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', $this->validRegistration(['email' => 'taken@example.com']))->assertSessionHasErrors('email');
        $this->post('/register', $this->validRegistration(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->post('/register', $this->validRegistration(['password' => 'onlyletters', 'password_confirmation' => 'onlyletters']))->assertSessionHasErrors('password');
        $this->post('/register', $this->validRegistration(['terms' => null]))->assertSessionHasErrors('terms');
        $this->assertGuest();
    }

    public function test_user_can_sign_in_and_out(): void
    {
        $user = User::factory()->customer()->create(['password' => 'secret123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertRedirect(route('account.dashboard'));

        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected_with_a_generic_message(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-pass1'])
            ->assertSessionHasErrors(['email' => 'These details don’t match our records.']);
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-pass1'])
            ->assertSessionHasErrors(['email' => 'These details don’t match our records.']);
        $this->assertGuest();
    }

    public function test_sign_in_is_rate_limited_after_five_failures(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-pass1']);
        }

        // Even the correct password is refused while locked out.
        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('email'));
    }

    public function test_suspended_user_is_signed_out(): void
    {
        $user = User::factory()->customer()->suspended()->create();

        $this->actingAs($user)->get('/account')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_each_role_lands_on_its_own_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertRedirect(route('admin.dashboard'));
        $this->actingAs(User::factory()->provider()->create())->get('/dashboard')->assertRedirect(route('host.dashboard'));
        $this->actingAs(User::factory()->customer()->create())->get('/dashboard')->assertRedirect(route('account.dashboard'));
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $this->get('/reset-password/'.$n->token.'?email='.$user->email)->assertOk();
            $this->post('/reset-password', [
                'token' => $n->token, 'email' => $user->email,
                'password' => 'newpass123', 'password_confirmation' => 'newpass123',
            ])->assertRedirect(route('login'));

            return true;
        });

        $this->post('/login', ['email' => $user->email, 'password' => 'newpass123']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_unknown_email_gets_the_same_reset_response(): void
    {
        Notification::fake();
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_signed_in_users_are_redirected_away_from_login(): void
    {
        $this->actingAs(User::factory()->customer()->create())->get('/login')->assertRedirect(route('dashboard'));
    }
}
