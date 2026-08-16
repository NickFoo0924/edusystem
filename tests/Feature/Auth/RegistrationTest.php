<?php

namespace Tests\Feature\Auth;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Registration is by invitation only (EduSystem.md 1A).
 *
 * Breeze's original tests asserted that /register renders and that anyone can
 * sign up. Both are now the opposite of the requirement, so they assert the
 * removal instead, and the invitation flow that replaced them.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_registration_route_does_not_exist(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_an_unknown_token_is_rejected(): void
    {
        $this->get('/register/not-a-real-token')->assertNotFound();
    }

    public function test_an_expired_invitation_is_refused(): void
    {
        $invitation = $this->makeInvitation(['expires_at' => now()->subDay()]);

        $this->get('/register/'.$invitation->token)->assertGone();
    }

    public function test_an_already_accepted_invitation_is_refused(): void
    {
        $invitation = $this->makeInvitation(['accepted_at' => now()]);

        $this->get('/register/'.$invitation->token)->assertGone();
    }

    public function test_a_valid_invitation_creates_the_account_with_its_fixed_role(): void
    {
        $invitation = $this->makeInvitation(['role' => 'instructor']);

        $response = $this->post('/register/'.$invitation->token, [
            'name' => 'Nadia Iskandar',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));

        $user = User::where('email', $invitation->email)->first();

        $this->assertNotNull($user);
        // The recipient never submits these: both come from the invitation.
        $this->assertSame('instructor', $user->role);
        $this->assertSame($invitation->email, $user->email);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_a_token_cannot_be_redeemed_twice(): void
    {
        $invitation = $this->makeInvitation();

        $this->post('/register/'.$invitation->token, [
            'name' => 'First Person',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ]);

        $this->post('/logout');

        $this->get('/register/'.$invitation->token)->assertGone();
        $this->assertSame(1, User::where('email', $invitation->email)->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeInvitation(array $overrides = []): Invitation
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return Invitation::create(array_merge([
            'email' => 'invited@learnsync.test',
            'role' => 'student',
            'token' => Str::random(64),
            'invited_by' => $admin->id,
            'expires_at' => now()->addWeek(),
        ], $overrides));
    }
}
