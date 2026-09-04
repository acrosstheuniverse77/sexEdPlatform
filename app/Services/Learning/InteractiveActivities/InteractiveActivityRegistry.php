<?php

declare(strict_types=1);

namespace App\Services\Learning\InteractiveActivities;

use App\Contracts\Learning\InteractiveActivityHandler;
use App\Enums\InteractiveActivityType;
use InvalidArgumentException;

class InteractiveActivityRegistry
{
    /** @var array<string, InteractiveActivityHandler> */
    private array $handlers;

    public function __construct(
        MatchingActivityHandler $matching,
        SequencingActivityHandler $sequencing,
    ) {
        $this->handlers = [
            $matching->type()->value => $matching,
            $sequencing->type()->value => $sequencing,
        ];
    }

    public function for(InteractiveActivityType|string $type): InteractiveActivityHandler
    {
        $value = $type instanceof InteractiveActivityType ? $type->value : $type;

        return $this->handlers[$value]
            ?? throw new InvalidArgumentException("Unsupported interactive activity type: {$value}");
    }
}
