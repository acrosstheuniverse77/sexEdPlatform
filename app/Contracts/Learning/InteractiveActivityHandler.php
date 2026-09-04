<?php

declare(strict_types=1);

namespace App\Contracts\Learning;

use App\Enums\InteractiveActivityType;
use Random\Randomizer;

interface InteractiveActivityHandler
{
    public function type(): InteractiveActivityType;

    public function rules(string $prefix = 'configuration'): array;

    public function normalize(array $configuration, ?array $existingConfiguration = null): array;

    public function initialWorkingState(array $configuration, Randomizer $randomizer): array;

    public function learnerPayload(array $configuration, array $workingState): array;

    public function evaluate(array $configuration, array $answer, array $workingState): array;

    public function answerFingerprint(array $configuration): string;

    public function previewPayload(array $configuration, array $workingState): array;
}
