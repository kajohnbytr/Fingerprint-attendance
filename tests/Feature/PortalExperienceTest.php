<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PortalExperienceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shared_portal_layout_contains_the_interactive_experience_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('theme-toggle', false)
            ->assertSee('mobile-menu', false)
            ->assertSee('scroll-progress', false)
            ->assertSee('back-to-top', false)
            ->assertSee('newsletter-form', false)
            ->assertSee('cookie-banner', false)
            ->assertSee('confirm-dialog', false)
            ->assertSee('global-search', false);
    }

    public function test_unknown_page_renders_the_custom_not_found_experience(): void
    {
        $this->get('/a-page-that-does-not-exist')
            ->assertNotFound()
            ->assertSee('That page left the campus.')
            ->assertSee('Return to sign in');
    }
}
