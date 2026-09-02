<?php

declare(strict_types=1);

namespace App\Services\Learning\InteractiveActivities;

use App\Contracts\Learning\InteractiveActivityHandler;
use App\Enums\InteractiveActivityType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Random\Randomizer;

class MatchingActivityHandler implements InteractiveActivityHandler
{
    public function type(): InteractiveActivityType
    {
        return InteractiveActivityType::MATCHING;
    }

    public function rules(string $prefix = 'configuration'): array
    {
        $prefix = $prefix === '' ? '' : "{$prefix}.";

        return [
            "{$prefix}schema_version" => ['sometimes', 'integer', 'in:1'],
            "{$prefix}pairs" => ['required', 'array', 'min:2', 'max:12'],
            "{$prefix}pairs.*.id" => ['nullable', 'string'],
            "{$prefix}pairs.*.left.id" => ['nullable', 'string'],
            "{$prefix}pairs.*.left.kind" => ['required', 'in:text'],
            "{$prefix}pairs.*.left.value" => ['required', 'string', 'max:500'],
            "{$prefix}pairs.*.right.id" => ['nullable', 'string'],
            "{$prefix}pairs.*.right.kind" => ['required', 'in:text'],
            "{$prefix}pairs.*.right.value" => ['required', 'string', 'max:500'],
        ];
    }

    public function normalize(array $configuration, ?array $existingConfiguration = null): array
    {
        $this->validate($configuration, $existingConfiguration);
        $existingPairIds = array_flip(array_filter(array_column($existingConfiguration['pairs'] ?? [], 'id'), 'is_string'));
        $existingLeftIds = array_flip(array_filter(array_map(static fn (array $pair): mixed => $pair['left']['id'] ?? null, $existingConfiguration['pairs'] ?? []), 'is_string'));
        $existingRightIds = array_flip(array_filter(array_map(static fn (array $pair): mixed => $pair['right']['id'] ?? null, $existingConfiguration['pairs'] ?? []), 'is_string'));

        return [
            'schema_version' => 1,
            'pairs' => array_map(function (array $pair) use ($existingPairIds, $existingLeftIds, $existingRightIds): array {
                $left = $pair['left'];
                $right = $pair['right'];

                return [
                    'id' => isset($existingPairIds[$pair['id'] ?? '']) ? $pair['id'] : (string) Str::uuid(),
                    'left' => [
                        'id' => isset($existingLeftIds[$left['id'] ?? '']) ? $left['id'] : (string) Str::uuid(),
                        'kind' => 'text',
                        'value' => trim($left['value']),
                    ],
                    'right' => [
                        'id' => isset($existingRightIds[$right['id'] ?? '']) ? $right['id'] : (string) Str::uuid(),
                        'kind' => 'text',
                        'value' => trim($right['value']),
                    ],
                ];
            }, $configuration['pairs']),
        ];
    }

    public function initialWorkingState(array $configuration, Randomizer $randomizer): array
    {
        $canonical = array_map(static fn (array $pair): string => $pair['right']['id'], $configuration['pairs']);
        $order = $randomizer->shuffleArray($canonical);

        if ($order === $canonical) {
            $order = count($order) === 2 ? array_reverse($order) : [...array_slice($order, 1), $order[0]];
        }

        return ['right_order' => $order, 'matched' => []];
    }

    public function learnerPayload(array $configuration, array $workingState): array
    {
        $rightById = [];
        $left = [];
        foreach ($configuration['pairs'] as $pair) {
            $left[] = $pair['left'];
            $rightById[$pair['right']['id']] = $pair['right'];
        }

        return [
            'left_items' => $left,
            'right_items' => array_values(array_filter(array_map(static fn (string $id): ?array => $rightById[$id] ?? null, $workingState['right_order'] ?? []))),
            'matched' => $workingState['matched'] ?? [],
        ];
    }

    public function evaluate(array $configuration, array $answer, array $workingState): array
    {
        $leftId = $answer['left_id'] ?? null;
        $rightId = $answer['right_id'] ?? null;
        $pairs = $workingState['matched'] ?? [];
        $mapping = [];

        foreach ($configuration['pairs'] as $pair) {
            $mapping[$pair['left']['id']] = $pair['right']['id'];
        }

        if (! is_string($leftId) || ! is_string($rightId) || ! isset($mapping[$leftId]) || ! in_array($rightId, $mapping, true) || $this->containsId($pairs, 'left_id', $leftId) || $this->containsId($pairs, 'right_id', $rightId)) {
            return $this->result(false, false, false, $workingState);
        }

        $correct = $mapping[$leftId] === $rightId;
        if ($correct) {
            $pairs[] = ['left_id' => $leftId, 'right_id' => $rightId];
            $workingState['matched'] = $pairs;
        }

        return $this->result(true, $correct, $correct && count($pairs) === count($mapping), $workingState);
    }

    public function answerFingerprint(array $configuration): string
    {
        $material = array_map(fn (array $pair): array => [$this->comparisonValue($pair['left']['value']), $this->comparisonValue($pair['right']['value'])], $configuration['pairs'] ?? []);
        usort($material, static fn (array $a, array $b): int => $a <=> $b);

        return hash('sha256', json_encode($material, JSON_THROW_ON_ERROR));
    }

    public function previewPayload(array $configuration, array $workingState): array
    {
        return $this->learnerPayload($configuration, $workingState);
    }

    private function validate(array $configuration, ?array $existingConfiguration): void
    {
        if (isset($existingConfiguration['schema_version']) && $existingConfiguration['schema_version'] !== 1) {
            throw ValidationException::withMessages(['configuration.schema_version' => 'Unsupported schema version.']);
        }

        $validator = Validator::make($configuration, $this->rules(''));
        $validator->after(function ($validator) use ($configuration): void {
            foreach (['left', 'right'] as $side) {
                $values = array_map(fn (array $pair): string => $this->comparisonValue((string) ($pair[$side]['value'] ?? '')), $configuration['pairs'] ?? []);
                if (count($values) !== count(array_unique($values))) {
                    $validator->errors()->add("pairs.{$side}", "Duplicate {$side} values are not allowed.");
                }
            }
        });
        $validator->validate();
    }

    private function comparisonValue(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($value)));
    }

    private function containsId(array $pairs, string $key, string $id): bool
    {
        return in_array($id, array_column($pairs, $key), true);
    }

    private function result(bool $accepted, bool $correct, bool $complete, array $workingState): array
    {
        return ['accepted' => $accepted, 'is_correct' => $correct, 'is_complete' => $complete, 'working_state' => $workingState];
    }
}
