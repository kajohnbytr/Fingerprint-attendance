<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\LoginVerificationCode;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use ReflectionProperty;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_responses_include_browser_security_headers_and_csp_nonce(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("script-src 'self' 'nonce-", $policy);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
    }

    public function test_http_requests_redirect_to_https_when_enforcement_is_enabled(): void
    {
        config(['security.force_https' => true]);

        $this->get('http://localhost/')->assertStatus(308)
            ->assertRedirect('https://localhost');
    }

    public function test_api_login_requires_a_valid_email_verification_code_before_issuing_a_token(): void
    {
        config(['security.mfa_enabled' => true]);
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'secure@example.test',
            'password' => 'Existing-password-2026!',
        ]);

        $challenge = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Existing-password-2026!',
        ])->assertAccepted()
            ->assertJsonPath('mfaRequired', true)
            ->assertJsonMissingPath('token');

        $code = null;
        Notification::assertSentTo($user, LoginVerificationCode::class, function (LoginVerificationCode $notification) use (&$code): bool {
            $property = new ReflectionProperty($notification, 'code');
            $code = $property->getValue($notification);

            return true;
        });

        $this->postJson('/api/auth/mfa', [
            'challengeToken' => $challenge->json('challengeToken'),
            'code' => $code,
        ])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_recent_passwords_cannot_be_reused(): void
    {
        $user = User::factory()->create(['password' => 'Existing-password-2026!']);
        $this->actingAs($user);

        $this->putJson('/api/auth/password', [
            'currentPassword' => 'Existing-password-2026!',
            'newPassword' => 'Replacement-password-2026!',
            'confirmPassword' => 'Replacement-password-2026!',
        ])->assertOk();

        $this->putJson('/api/auth/password', [
            'currentPassword' => 'Replacement-password-2026!',
            'newPassword' => 'Existing-password-2026!',
            'confirmPassword' => 'Existing-password-2026!',
        ])->assertUnprocessable()->assertJsonValidationErrors('newPassword');

        $this->assertTrue(Hash::check('Replacement-password-2026!', $user->fresh()->password));
    }

    public function test_idle_web_session_is_invalidated(): void
    {
        $user = User::factory()->create();
        DB::table('super_admin_settings')->where('id', 1)->update([
            'session_timeout_minutes' => 5,
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['security.last_activity' => time() - 301])
            ->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_emergency_lockdown_blocks_other_users_but_keeps_super_admin_access(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'must_change_password' => false,
        ]);
        $faculty = User::factory()->create([
            'role' => 'faculty',
            'must_change_password' => false,
        ]);
        DB::table('super_admin_settings')->where('id', 1)->update([
            'system_lockdown' => true,
            'updated_at' => now(),
        ]);

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('settings.systemLockdown', true);

        $this->actingAs($faculty, 'sanctum')
            ->getJson('/api/notifications')
            ->assertStatus(503)
            ->assertJsonPath('lockdown', true);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', [
            'email' => $faculty->email,
            'password' => 'password',
            'expectedRole' => 'admin',
        ])->assertStatus(503)
            ->assertJsonPath('lockdown', true);
    }

    public function test_emergency_lockdown_displays_lockdown_page_for_web_users(): void
    {
        $programHead = User::factory()->create(['role' => 'program_head']);
        DB::table('super_admin_settings')->where('id', 1)->update([
            'system_lockdown' => true,
            'updated_at' => now(),
        ]);

        $this->actingAs($programHead)
            ->get('/dashboard')
            ->assertStatus(503)
            ->assertSee('System Lockdown');

        $this->assertGuest();
    }
}
