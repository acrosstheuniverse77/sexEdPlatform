<?php

declare(strict_types=1);

namespace App\Services\Learning\InteractiveActivities;

use App\Enums\InteractiveActivityType;
use App\Models\InteractiveActivity;
use App\Models\Lesson;
use App\Models\LessonTopic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Random\Engine\Mt19937;
use Random\Randomizer;

class InteractiveActivityAuthoringService
{
    private const ALLOWED_HTML = '<p><br><strong><b><em><i><u><ul><ol><li><a><blockquote><code>';

    public function __construct(private readonly InteractiveActivityRegistry $registry) {}

    public function validate(\Illuminate\Http\Request $request, Lesson $lesson, ?InteractiveActivity $activity = null): array
    {
        $validated = $request->validate([
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:interactive'],
            'activity_type' => ['required', 'in:matching,sequencing'],
            'placement' => ['required', 'in:inside_topic,between_topics'],
            'parent_topic_id' => ['nullable', 'integer'],
            'insert_after_block' => ['nullable', 'integer', 'min:0'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'explanation' => ['nullable', 'string', 'max:10000'],
            'configuration' => ['required', 'array'],
        ]);

        $handler = $this->registry->for($validated['activity_type']);
        $configuration = $this->addDefaultTextKinds($validated['configuration'], $validated['activity_type']);
        $configuration = Validator::make(['configuration' => $configuration], $handler->rules())->validate()['configuration'];
        $normalized = $handler->normalize($configuration, $activity?->configuration);

        return [
            'lesson_id' => $lesson->id,
            'title' => trim($validated['title']),
            'type' => 'interactive',
            'activity_type' => $validated['activity_type'],
            'placement' => $validated['placement'],
            'parent_topic_id' => $validated['parent_topic_id'] ?? null,
            'insert_after_block' => $validated['insert_after_block'] ?? null,
            'instructions' => $this->sanitize($validated['instructions'] ?? null),
            'explanation' => $this->sanitize($validated['explanation'] ?? null),
            'configuration' => $normalized,
        ];
    }

    public function create(Lesson $lesson, array $data): InteractiveActivity
    {
        return DB::transaction(function () use ($lesson, $data): InteractiveActivity {
            if ($data['placement'] === 'inside_topic') {
                $parent = $lesson->topics()
                    ->instructional()
                    ->find($data['parent_topic_id'] ?? null);

                if (! $parent) {
                    throw ValidationException::withMessages([
                        'parent_topic_id' => 'Choose an eligible topic in this lesson.',
                    ]);
                }

                Gate::authorize('update', $parent);

                $blockUuid = (string) Str::uuid();
                $activity = $this->createActivity($parent, $data, 'inside_topic', $blockUuid);
                $blocks = $this->blocksForTopic($parent);
                $insertAfter = (int) ($data['insert_after_block'] ?? 0);
                array_splice($blocks, min(max(0, $insertAfter) + 1, count($blocks)), 0, [[
                    'type' => 'interactive_activity',
                    'uuid' => $blockUuid,
                    'activity_id' => $activity->id,
                ]]);
                $parent->update(['content_blocks' => array_values($blocks)]);
            } else {
                $topic = $lesson->topics()->create([
                    'title' => $data['title'],
                    'type' => 'interactive',
                    'duration' => 0,
                    'is_prerequisite' => false,
                    'order' => ($lesson->topics()->max('order') ?? 0) + 1,
                    'interactive_config' => ['placement' => 'between_topics'],
                ]);
                $activity = $this->createActivity($topic, $data, 'between_topics', null);
            }

            $this->recalculateDurations($lesson);

            return $activity->fresh();
        });
    }

    public function update(InteractiveActivity $activity, array $data): InteractiveActivity
    {
        $handler = $this->registry->for($activity->activity_type);
        $configuration = $handler->normalize($data['configuration'], $activity->configuration);
        $activity->update([
            'title' => trim((string) $data['title']),
            'instructions' => $this->sanitize($data['instructions'] ?? null),
            'explanation' => $this->sanitize($data['explanation'] ?? null),
            'configuration' => $configuration,
        ]);

        return $activity->fresh();
    }

    public function delete(InteractiveActivity $activity): void
    {
        $activity->delete();
    }

    public function preview(array $data): array
    {
        $handler = $this->registry->for($data['activity_type']);
        $configuration = $handler->normalize(
            $this->addDefaultTextKinds($data['configuration'], $data['activity_type']),
        );
        $workingState = $handler->initialWorkingState($configuration, new Randomizer(new Mt19937(1234)));

        return $handler->previewPayload($configuration, $workingState);
    }

    private function createActivity(LessonTopic $topic, array $data, string $placement, ?string $blockUuid): InteractiveActivity
    {
        return $topic->interactiveActivities()->create([
            'placement' => $placement,
            'block_uuid' => $blockUuid,
            'activity_type' => $data['activity_type'],
            'title' => $data['title'],
            'instructions' => $data['instructions'],
            'explanation' => $data['explanation'],
            'configuration' => $data['configuration'],
            'revision' => 1,
        ]);
    }

    private function recalculateDurations(Lesson $lesson): void
    {
        $lesson->update(['duration' => $lesson->topics()->instructional()->sum('duration')]);
        $lesson->module?->update([
            'duration_minutes' => $lesson->module->lessons()->sum('duration'),
        ]);
    }

    private function blocksForTopic(LessonTopic $topic): array
    {
        if (is_array($topic->content_blocks) && count($topic->content_blocks) > 0) {
            return $topic->content_blocks;
        }

        return [[
            'type' => 'rich_text',
            'html' => $topic->text_content ?? '',
        ]];
    }

    private function addDefaultTextKinds(array $configuration, string $activityType): array
    {
        if ($activityType === InteractiveActivityType::MATCHING->value) {
            foreach ($configuration['pairs'] ?? [] as $index => $pair) {
                if (! is_array($pair['left'] ?? null) || ! is_array($pair['right'] ?? null)) {
                    continue;
                }

                $configuration['pairs'][$index]['left']['kind'] ??= 'text';
                $configuration['pairs'][$index]['right']['kind'] ??= 'text';
            }
        } else {
            foreach ($configuration['items'] ?? [] as $index => $item) {
                $configuration['items'][$index]['kind'] ??= 'text';
            }
        }

        return $configuration;
    }

    private function sanitize(?string $html): ?string
    {
        return $html === null ? null : strip_tags($html, self::ALLOWED_HTML);
    }
}
