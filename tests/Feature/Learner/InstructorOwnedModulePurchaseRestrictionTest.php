<?php

namespace Tests\Feature\Learner;

use App\Http\Middleware\EnsureProfileCompleted;
use App\Models\LearnerProfile;
use App\Models\Module;
use App\Models\User;
use App\Services\PayMongoPaymentLinkService;
use Mockery\MockInterface;
use Tests\TestCase;

class InstructorOwnedModulePurchaseRestrictionTest extends TestCase
{
    public function test_instructor_learner_cannot_purchase_their_own_paid_module(): void
    {
        $this->withoutMiddleware(EnsureProfileCompleted::class);

        $instructor = User::factory()->create([
            'role' => 'instructor',
            'status' => User::STATUS_ACTIVE,
            'birthdate' => now()->subYears(30)->toDateString(),
        ]);
        $instructor->assignRole('instructor');
        $instructor->assignRole('learner');

        LearnerProfile::query()->create([
            'user_id' => $instructor->id,
            'username' => 'owner_learner_' . $instructor->id,
            'birthdate' => now()->subYears(30)->toDateString(),
            'age_range' => 'adult_18_plus',
            'gender' => 'female',
            'barangay' => 'Barangay 1',
            'bio' => 'Bio',
            'is_parent_account' => false,
            'requires_parental_consent' => false,
        ]);

        $module = Module::factory()->create([
            'created_by' => $instructor->id,
            'content_owner_type' => 'instructor',
            'is_published' => true,
            'current_review_status' => null,
            'access_type' => 'paid',
            'price_amount' => 499,
            'price_currency' => 'PHP',
            'min_age' => 18,
            'max_age' => 100,
        ]);

        $this->mock(PayMongoPaymentLinkService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('createCheckoutSession');
        });

        $this->actingAs($instructor)
            ->get(route('learner.modules.show', $module))
            ->assertOk()
            ->assertSee('Owned by You', false)
            ->assertDontSee('Pay &#8369;499', false);

        $this->actingAs($instructor)
            ->post(route('learner.modules.purchase.process', $module), [
                'payment_method' => 'gcash',
                'accept_terms' => '1',
            ])
            ->assertRedirect(route('learner.modules.show', $module))
            ->assertSessionHas('error', 'You cannot purchase a module that you own.');

        $this->assertDatabaseMissing('module_purchases', [
            'user_id' => $instructor->id,
            'module_id' => $module->id,
        ]);
    }
}
