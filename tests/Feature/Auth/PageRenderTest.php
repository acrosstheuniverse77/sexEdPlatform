<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_uses_brand_purple_gradient(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
        $response->assertSee('#A30EB2', false);
    }

    public function test_learner_login_page_shows_welcome_back_panel(): void
    {
        $response = $this->get(route('login'));
        $response->assertStatus(200);
        $response->assertSee('Conscious Connections');
    }

    public function test_register_page_shows_start_your_learning_journey_panel(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
        $response->assertSee('Start your journey');
    }

    public function test_register_account_page_shows_almost_there_panel(): void
    {
        $this->withSession(['pending_personal_info' => [
            'first_name' => 'Juan', 'last_name' => 'dela Cruz',
            'birthdate' => '2000-01-01', 'age' => 25,
        ]]);
        $response = $this->get('/register/account');
        $response->assertStatus(200);
        $response->assertSee('Almost there!');
    }

    public function test_verify_email_page_shows_check_your_inbox_panel(): void
    {
        $user = User::factory()->unverified()->create();
        $response = $this->actingAs($user)->get('/verify-email');
        $response->assertStatus(200);
        $response->assertSee('Check your inbox');
    }

    public function test_parent_required_page_shows_safe_learning_panel(): void
    {
        $response = $this->get(route('parent.registration.required'));
        $response->assertStatus(200);
        $response->assertSee('Young learner?');
    }

    public function test_parent_register_page_shows_guide_their_journey_panel(): void
    {
        $response = $this->get(route('parent.register'));
        $response->assertStatus(200);
        $response->assertSee('Guide their journey');
    }

    public function test_complete_profile_page_shows_one_last_step_panel(): void
    {
        $user = User::factory()->create();
        $user->assignRole('learner');
        // Re-fresh the user model to pick up DB defaults
        $user->refresh();
        $response = $this->actingAs($user)->get(route('profile.complete'));
        $response->assertStatus(200);
        $response->assertSee('One last step!');
    }

    public function test_create_child_page_shows_set_up_their_account_panel(): void
    {
        $parent = User::factory()->create([
            'role' => 'admin',
            'birthdate' => now()->subYears(25)->toDateString(),
            'email_verified_at' => now(),
            'is_parent_registration' => true,
            'parent_verification_status' => 'approved',
        ]);
        $parent->assignRole('admin');

        $response = $this->actingAs($parent)->get(route('parent.create-child'));
        $response->assertStatus(200);
        $response->assertSee('Set up their account');
    }
}
