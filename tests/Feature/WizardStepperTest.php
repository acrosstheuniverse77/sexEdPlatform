<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\View\Components\WizardStepper;

class WizardStepperTest extends TestCase
{
    use RefreshDatabase;

    // ─── Learner flow (4 steps) ──────────────────────────────────────────────

    public function test_learner_flow_detected_on_register_route(): void
    {
        $component = new WizardStepper('register', false);
        $steps = $component->steps;

        $this->assertCount(4, $steps);
        $this->assertTrue($steps[0]['isActive']);
        $this->assertFalse($steps[1]['isActive']);
    }

    public function test_learner_flow_step_2_active_on_account_info(): void
    {
        $component = new WizardStepper('register.account', false);
        $steps = $component->steps;

        $this->assertCount(4, $steps);
        $this->assertTrue($steps[0]['isCompleted']);
        $this->assertTrue($steps[1]['isActive']);
        $this->assertFalse($steps[2]['isActive']);
    }

    public function test_learner_flow_step_3_active_on_verification_notice(): void
    {
        $component = new WizardStepper('verification.notice', false);
        $steps = $component->steps;

        $this->assertCount(4, $steps);
        $this->assertTrue($steps[0]['isCompleted']);
        $this->assertTrue($steps[1]['isCompleted']);
        $this->assertTrue($steps[2]['isActive']);
        $this->assertFalse($steps[3]['isActive']);
    }

    public function test_learner_flow_step_4_active_on_profile_complete(): void
    {
        $component = new WizardStepper('profile.complete', false);
        $steps = $component->steps;

        $this->assertCount(4, $steps);
        $this->assertTrue($steps[0]['isCompleted']);
        $this->assertTrue($steps[1]['isCompleted']);
        $this->assertTrue($steps[2]['isCompleted']);
        $this->assertTrue($steps[3]['isActive']);
    }

    // ─── Parent flow (6 steps) ───────────────────────────────────────────────

    public function test_parent_flow_detected_when_session_flag_is_set(): void
    {
        $component = new WizardStepper('verification.notice', true);
        $steps = $component->steps;

        $this->assertCount(4, $steps);
    }

    public function test_parent_flow_step_1_active_on_parent_register(): void
    {
        $component = new WizardStepper('parent.register', true);
        $steps = $component->steps;

        $this->assertCount(4, $steps);
        $this->assertTrue($steps[0]['isActive']);
        $this->assertFalse($steps[0]['isCompleted']);
    }

    public function test_parent_flow_step_2_active_on_account_info(): void
    {
        $component = new WizardStepper('parent.register.account', true);
        $steps = $component->steps;

        $this->assertCount(4, $steps);
        $this->assertTrue($steps[0]['isCompleted']);
        $this->assertTrue($steps[1]['isActive']);
        $this->assertFalse($steps[2]['isActive']);
    }

    public function test_parent_flow_step_3_active_on_verification_notice(): void
    {
        $component = new WizardStepper('verification.notice', true);
        $steps = $component->steps;

        $this->assertCount(4, $steps);
        $this->assertTrue($steps[0]['isCompleted']);
        $this->assertTrue($steps[1]['isCompleted']);
        $this->assertTrue($steps[2]['isActive']);
        $this->assertFalse($steps[3]['isActive']);
    }

    public function test_parent_flow_step_4_active_on_guardian_verification(): void
    {
        $component = new WizardStepper('guardian.verification.create', true);
        $steps = $component->steps;

        $this->assertCount(4, $steps);
        $this->assertTrue($steps[2]['isCompleted']);
        $this->assertTrue($steps[3]['isActive']);
    }

    // ─── Auto-detection without explicit flag ────────────────────────────────

    public function test_parent_register_route_auto_detects_parent_flow(): void
    {
        // parent.register route name alone signals parent flow — no session flag needed
        $component = new WizardStepper('parent.register');
        $steps = $component->steps;

        $this->assertCount(4, $steps);
        $this->assertTrue($steps[0]['isActive']);
    }

    public function test_parent_register_account_route_auto_detects_parent_flow(): void
    {
        $component = new WizardStepper('parent.register.account');
        $steps = $component->steps;

        $this->assertCount(4, $steps);
        $this->assertTrue($steps[0]['isCompleted']);
        $this->assertTrue($steps[1]['isActive']);
    }

    public function test_create_child_route_returns_null_steps_without_explicit_prop(): void
    {
        // child wizard pages supply their own :steps, so auto-detection returns null
        $component = new WizardStepper('parent.create-child', true);
        $this->assertNull($component->steps);
    }

    // ─── Disambiguation (shared routes) ─────────────────────────────────────

    public function test_verification_notice_shows_learner_flow_without_session_flag(): void
    {
        $component = new WizardStepper('verification.notice', false);
        $this->assertCount(4, $component->steps);
    }

    public function test_verification_notice_shows_parent_flow_with_session_flag(): void
    {
        $component = new WizardStepper('verification.notice', true);
        $this->assertCount(4, $component->steps);
    }

    public function test_guardian_verification_shows_parent_flow_with_session_flag(): void
    {
        $component = new WizardStepper('guardian.verification.create', true);
        $steps = $component->steps;
        $this->assertCount(4, $steps);
        $this->assertTrue($steps[3]['isActive']); // step 4 of 4
    }

    // ─── Edge cases ──────────────────────────────────────────────────────────

    public function test_unknown_route_returns_null_steps(): void
    {
        $component = new WizardStepper('some.unknown.route', false);

        $this->assertNull($component->steps);
    }
}
