<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Learning;

use App\Enums\InteractiveActivityType;
use App\Services\Learning\InteractiveActivities\InteractiveActivityRegistry;
use App\Services\Learning\InteractiveActivities\MatchingActivityHandler;
use App\Services\Learning\InteractiveActivities\SequencingActivityHandler;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\UnitTestCase;

class InteractiveActivityHandlerTest extends UnitTestCase
{
    public function test_registry_resolves_registered_handlers_and_rejects_unknown_types(): void
    {
        $matching = new MatchingActivityHandler;
        $sequencing = new SequencingActivityHandler;
        $registry = new InteractiveActivityRegistry($matching, $sequencing);

        $this->assertSame($matching, $registry->for(InteractiveActivityType::MATCHING));
        $this->assertSame($sequencing, $registry->for('sequencing'));

        $this->expectException(InvalidArgumentException::class);
        $registry->for('unknown');
    }

    public function test_matching_normalizes_valid_pairs_preserves_existing_ids_and_rejects_invalid_counts_and_duplicates(): void
    {
        $handler = new MatchingActivityHandler;
        $existing = [
            'schema_version' => 1,
            'pairs' => [$this->pair('pair-existing', 'left-existing', 'right-existing', 'Alpha', 'One')],
        ];

        $normalized = $handler->normalize([
            'pairs' => [
                $this->pair('pair-existing', 'left-existing', 'right-existing', ' Alpha ', ' One '),
                $this->pair('forged', 'forged-left', 'forged-right', 'Bravo', 'Two'),
            ],
        ], $existing);

        $this->assertSame(1, $normalized['schema_version']);
        $this->assertSame('pair-existing', $normalized['pairs'][0]['id']);
        $this->assertSame('left-existing', $normalized['pairs'][0]['left']['id']);
        $this->assertSame('right-existing', $normalized['pairs'][0]['right']['id']);
        $this->assertSame('Alpha', $normalized['pairs'][0]['left']['value']);
        $this->assertNotSame('forged', $normalized['pairs'][1]['id']);
        $this->assertNotSame('forged-left', $normalized['pairs'][1]['left']['id']);
        $this->assertNotSame('forged-right', $normalized['pairs'][1]['right']['id']);

        $this->assertValidationFails(fn (): array => $handler->normalize(['pairs' => [$this->pair('p', 'l', 'r', 'Alpha', 'One')]]));
        $this->assertValidationFails(fn (): array => $handler->normalize(['pairs' => array_fill(0, 13, $this->pair('p', 'l', 'r', 'Alpha', 'One'))]));
        $this->assertValidationFails(fn (): array => $handler->normalize(['pairs' => [
            $this->pair('p1', 'l1', 'r1', 'Same  Value', 'One'),
            $this->pair('p2', 'l2', 'r2', ' same value ', 'Two'),
        ]]));
    }

    public function test_sequencing_normalizes_positions_preserves_existing_ids_and_rejects_invalid_counts_and_duplicates(): void
    {
        $handler = new SequencingActivityHandler;
        $existing = ['schema_version' => 1, 'items' => [$this->item('item-existing', 'First', 1)]];

        $normalized = $handler->normalize([
            'items' => [
                $this->item('item-existing', ' First ', 99),
                $this->item('forged', 'Second', 4),
                $this->item('another', 'Third', 2),
            ],
        ], $existing);

        $this->assertSame([1, 2, 3], array_column($normalized['items'], 'correct_position'));
        $this->assertSame('item-existing', $normalized['items'][0]['id']);
        $this->assertSame('First', $normalized['items'][0]['value']);
        $this->assertNotSame('forged', $normalized['items'][1]['id']);

        $this->assertValidationFails(fn (): array => $handler->normalize(['items' => [
            $this->item('a', 'One', 1), $this->item('b', 'Two', 2),
        ]]));
        $this->assertValidationFails(fn (): array => $handler->normalize(['items' => array_fill(0, 13, $this->item('a', 'One', 1))]));
        $this->assertValidationFails(fn (): array => $handler->normalize(['items' => [
            $this->item('a', 'Same  Value', 1), $this->item('b', ' same value ', 2), $this->item('c', 'Third', 3),
        ]]));
    }

    public function test_handlers_reject_unsupported_schema_versions_and_non_text_envelopes(): void
    {
        $this->assertValidationFails(fn (): array => (new MatchingActivityHandler)->normalize([
            'schema_version' => 2,
            'pairs' => [$this->pair('a', 'b', 'c', 'One', 'Two'), $this->pair('d', 'e', 'f', 'Three', 'Four')],
        ]));
        $this->assertValidationFails(fn (): array => (new SequencingActivityHandler)->normalize([
            'items' => [
                ['id' => 'a', 'kind' => 'image', 'value' => 'One'],
                $this->item('b', 'Two', 2), $this->item('c', 'Three', 3),
            ],
        ]));
        $this->assertValidationFails(fn (): array => (new MatchingActivityHandler)->normalize([
            'pairs' => [
                ['id' => 'a', 'left' => ['id' => 'b', 'value' => 'One'], 'right' => ['id' => 'c', 'kind' => 'text', 'value' => 'Two']],
                $this->pair('d', 'e', 'f', 'Three', 'Four'),
            ],
        ]));
        $this->assertValidationFails(fn (): array => (new SequencingActivityHandler)->normalize([
            'items' => [
                ['id' => 'a', 'value' => 'One'],
                $this->item('b', 'Two', 2), $this->item('c', 'Three', 3),
            ],
        ]));
    }

    public function test_initial_states_are_deterministic_and_never_correct(): void
    {
        $matching = new MatchingActivityHandler;
        $twoPairs = $matching->normalize(['pairs' => [
            $this->pair('p1', 'l1', 'r1', 'One', 'First'), $this->pair('p2', 'l2', 'r2', 'Two', 'Second'),
        ]]);
        $rightIds = array_map(static fn (array $pair): string => $pair['right']['id'], $twoPairs['pairs']);
        $this->assertSame(array_reverse($rightIds), $matching->initialWorkingState($twoPairs, new Randomizer(new Mt19937(1234)))['right_order']);

        $sequencing = new SequencingActivityHandler;
        $items = $sequencing->normalize(['items' => [
            $this->item('a', 'First', 1), $this->item('b', 'Second', 2), $this->item('c', 'Third', 3),
        ]]);
        $state = $sequencing->initialWorkingState($items, new Randomizer(new Mt19937(1234)));
        $this->assertNotSame(array_column($items['items'], 'id'), $state['item_order']);
        $this->assertSame($state, $sequencing->initialWorkingState($items, new Randomizer(new Mt19937(1234))));
    }

    public function test_learner_payloads_keep_answer_material_secret(): void
    {
        $matching = new MatchingActivityHandler;
        $configuration = $matching->normalize(['pairs' => [
            $this->pair('pair-uuid', 'left-item-uuid', 'right-item-uuid', 'One', 'First'),
            $this->pair('pair-uuid-2', 'left-item-uuid-2', 'right-item-uuid-2', 'Two', 'Second'),
        ]]);
        $rightIds = array_map(static fn (array $pair): string => $pair['right']['id'], $configuration['pairs']);
        $encoded = json_encode($matching->learnerPayload($configuration, ['right_order' => array_reverse($rightIds), 'matched' => []]), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('pair-uuid', $encoded);

        $sequencing = new SequencingActivityHandler;
        $configuration = $sequencing->normalize(['items' => [
            $this->item('item-a', 'First', 1), $this->item('item-b', 'Second', 2), $this->item('item-c', 'Third', 3),
        ]]);
        $itemIds = array_column($configuration['items'], 'id');
        $encoded = json_encode($sequencing->learnerPayload($configuration, ['item_order' => [$itemIds[1], $itemIds[0], $itemIds[2]]]), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('correct_position', $encoded);
    }

    public function test_evaluation_rejects_unknown_missing_and_duplicate_ids_before_evaluation(): void
    {
        $matching = new MatchingActivityHandler;
        $configuration = $matching->normalize(['pairs' => [
            $this->pair('p1', 'l1', 'r1', 'One', 'First'), $this->pair('p2', 'l2', 'r2', 'Two', 'Second'),
        ]]);
        $leftIds = array_map(static fn (array $pair): string => $pair['left']['id'], $configuration['pairs']);
        $rightIds = array_map(static fn (array $pair): string => $pair['right']['id'], $configuration['pairs']);
        $state = ['right_order' => array_reverse($rightIds), 'matched' => []];
        $this->assertSame([
            'accepted' => true,
            'is_correct' => true,
            'is_complete' => false,
            'working_state' => ['right_order' => array_reverse($rightIds), 'matched' => [['left_id' => $leftIds[0], 'right_id' => $rightIds[0]]]],
        ], $matching->evaluate($configuration, ['left_id' => $leftIds[0], 'right_id' => $rightIds[0]], $state));
        $this->assertFalse($matching->evaluate($configuration, ['left_id' => 'unknown', 'right_id' => $rightIds[0]], $state)['accepted']);
        $this->assertFalse($matching->evaluate($configuration, ['left_id' => $leftIds[0]], $state)['accepted']);
        $this->assertFalse($matching->evaluate($configuration, ['left_id' => $leftIds[0], 'right_id' => $rightIds[0]], ['right_order' => array_reverse($rightIds), 'matched' => [['left_id' => $leftIds[0], 'right_id' => $rightIds[0]]]])['accepted']);

        $sequencing = new SequencingActivityHandler;
        $configuration = $sequencing->normalize(['items' => [
            $this->item('a', 'First', 1), $this->item('b', 'Second', 2), $this->item('c', 'Third', 3),
        ]]);
        $itemIds = array_column($configuration['items'], 'id');
        $this->assertSame([
            'accepted' => true,
            'is_correct' => true,
            'is_complete' => true,
            'working_state' => ['item_order' => $itemIds],
        ], $sequencing->evaluate($configuration, ['item_order' => $itemIds], ['item_order' => [$itemIds[1], $itemIds[0], $itemIds[2]]]));
        $this->assertFalse($sequencing->evaluate($configuration, ['item_order' => [$itemIds[0], $itemIds[1]]], ['item_order' => [$itemIds[1], $itemIds[0], $itemIds[2]]])['accepted']);
        $this->assertFalse($sequencing->evaluate($configuration, ['item_order' => [$itemIds[0], $itemIds[0], $itemIds[2]]], ['item_order' => [$itemIds[1], $itemIds[0], $itemIds[2]]])['accepted']);
        $this->assertFalse($sequencing->evaluate($configuration, ['item_order' => [$itemIds[0], $itemIds[1], 'unknown']], ['item_order' => [$itemIds[1], $itemIds[0], $itemIds[2]]])['accepted']);
    }

    public function test_fingerprints_ignore_display_metadata_but_detect_answer_changes(): void
    {
        $matching = new MatchingActivityHandler;
        $base = $matching->normalize(['pairs' => [
            $this->pair('p1', 'l1', 'r1', 'One', 'First'), $this->pair('p2', 'l2', 'r2', 'Two', 'Second'),
        ]]);
        $reordered = array_reverse($base['pairs']);
        $changed = $base;
        $changed['pairs'][0]['right']['value'] = 'Changed';
        $this->assertSame($matching->answerFingerprint($base), $matching->answerFingerprint(['schema_version' => 1, 'pairs' => $reordered]));
        $this->assertNotSame($matching->answerFingerprint($base), $matching->answerFingerprint($changed));

        $sequencing = new SequencingActivityHandler;
        $base = $sequencing->normalize(['items' => [
            $this->item('a', 'First', 1), $this->item('b', 'Second', 2), $this->item('c', 'Third', 3),
        ]]);
        $display = $base;
        $display['items'][0]['id'] = 'different-id';
        $changed = $base;
        $changed['items'][1]['value'] = 'Changed';
        $this->assertSame($sequencing->answerFingerprint($base), $sequencing->answerFingerprint($display));
        $this->assertNotSame($sequencing->answerFingerprint($base), $sequencing->answerFingerprint($changed));
    }

    private function assertValidationFails(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected configuration validation to fail.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    private function pair(string $pairId, string $leftId, string $rightId, string $left, string $right): array
    {
        return [
            'id' => $pairId,
            'left' => ['id' => $leftId, 'kind' => 'text', 'value' => $left],
            'right' => ['id' => $rightId, 'kind' => 'text', 'value' => $right],
        ];
    }

    private function item(string $id, string $value, int $position): array
    {
        return ['id' => $id, 'kind' => 'text', 'value' => $value, 'correct_position' => $position];
    }
}
