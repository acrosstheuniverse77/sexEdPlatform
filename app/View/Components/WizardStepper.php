<?php

namespace App\View\Components;

use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

class WizardStepper extends Component
{
    public ?array $steps;

    private const LEARNER_FLOW = [
        ['label' => 'Personal Info', 'route' => 'register'],
        ['label' => 'Account Info',  'route' => 'register.account'],
        ['label' => 'Verify Email',  'route' => 'verification.notice'],
        ['label' => 'Profile',       'route' => 'profile.complete'],
    ];

    // Parent registers their own account first (4 steps — mirrors learner flow)
    private const PARENT_FLOW = [
        ['label' => 'Personal Info', 'route' => 'parent.register'],
        ['label' => 'Account Info',  'route' => 'parent.register.account'],
        ['label' => 'Verify Email',  'route' => 'verification.notice'],
        ['label' => 'Guardian Verification', 'route' => 'guardian.verification.create'],
    ];

    public function __construct(
        private ?string $currentRoute = null,
        private ?bool $isParentFlow = null,
        ?array $steps = null,
    ) {
        $this->currentRoute = $currentRoute ?? Route::currentRouteName() ?? '';

        // Detect parent flow via session flag OR by being on a parent-only route
        // (the session flag is not yet set when parent first visits parent.register)
        $this->isParentFlow = $isParentFlow ?? (
            (bool) session('is_parent_registration') ||
            in_array($this->currentRoute, ['parent.register', 'parent.register.account'])
        );

        // Explicit :steps prop (from child wizard pages) takes priority over auto-detection
        $this->steps = $steps ?? $this->buildSteps();
    }

    private function buildSteps(): ?array
    {
        $map = $this->isParentFlow ? self::PARENT_FLOW : self::LEARNER_FLOW;

        $activeIndex = null;
        foreach ($map as $i => $step) {
            if ($step['route'] === $this->currentRoute) {
                $activeIndex = $i;
                break;
            }
        }

        if ($activeIndex === null) {
            return null;
        }

        return array_map(function (array $step, int $i) use ($activeIndex) {
            return [
                'label'       => $step['label'],
                'isCompleted' => $i < $activeIndex,
                'isActive'    => $i === $activeIndex,
                'isUpcoming'  => $i > $activeIndex,
            ];
        }, $map, array_keys($map));
    }

    public function render()
    {
        return view('components.wizard-stepper');
    }
}
