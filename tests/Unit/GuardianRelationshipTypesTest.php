<?php

namespace Tests\Unit;

use App\Support\GuardianRelationshipTypes;
use Tests\TestCase;

class GuardianRelationshipTypesTest extends TestCase
{
    public function test_relationship_verification_policy_is_centralized_by_type(): void
    {
        $this->assertFalse(GuardianRelationshipTypes::requiresVerification('biological_mother'));
        $this->assertTrue(GuardianRelationshipTypes::requiresVerification('adoptive_parent'));
        $this->assertContains('adoption_order', GuardianRelationshipTypes::acceptedDocumentTypes('adoptive_parent'));
    }
}
