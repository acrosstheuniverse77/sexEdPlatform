<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class LearnerReviewModalMarkupTest extends TestCase
{
    public function test_review_modal_does_not_use_native_required_on_rich_text_field(): void
    {
        $markup = file_get_contents(dirname(__DIR__, 2) . '/resources/views/learner/modules/partials/review-modal.blade.php');

        $this->assertStringContainsString('data-review-form="true"', $markup);
        $this->assertStringContainsString('id="review_content"', $markup);
        $this->assertStringNotContainsString('required', $markup);
    }
}
