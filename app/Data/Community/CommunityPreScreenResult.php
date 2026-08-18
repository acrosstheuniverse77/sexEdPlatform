<?php

namespace App\Data\Community;

use App\Enums\CommunityPreScreenDecision;

final readonly class CommunityPreScreenResult
{
    public function __construct(
        public CommunityPreScreenDecision $decision,
        public array $flags = [],
        public ?string $message = null,
    ) {
    }

    public function allowsPublication(): bool
    {
        return $this->decision === CommunityPreScreenDecision::Allow;
    }
}
