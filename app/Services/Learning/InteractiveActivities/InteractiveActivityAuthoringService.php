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
        if ($activity && $validated['activity_type'] !== $activity->activity_type->value) {
            throw ValidationException::withMessages([
                'activity_type' => 'Activity type cannot be changed after creation.',
            ]);
        }
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
        return DB::transaction(function () use ($activity, $data): InteractiveActivity {
            $stored = InteractiveActivity::query()
                ->lockForUpdate()
                ->findOrFail($activity->id);
            $currentTopic = $stored->lessonTopic()->lockForUpdate()->firstOrFail();
            $lesson = $currentTopic->lesson()->firstOrFail();
            $handler = $this->registry->for($stored->activity_type);
            $configuration = $handler->normalize($data['configuration'], $stored->configuration);
            $nextRevision = $handler->answerFingerprint($stored->configuration) !== $handler->answerFingerprint($configuration)
                ? ((int) $stored->revision) + 1
                : (int) $stored->revision;
            $targetPlacement = $data['placement'] ?? $stored->placement;
            $oldPlacement = $stored->placement;
            $oldTopic = $currentTopic;
            $targetTopic = $currentTopic;
            $targetBlockUuid = null;
            $oldHostToDelete = null;

            if ($targetPlacement === 'inside_topic') {
                $targetTopic = $lesson->topics()
                    ->instructional()
                    ->lockForUpdate()
                    ->find($data['parent_topic_id'] ?? null);

                if (! $targetTopic) {
                    throw ValidationException::withMessages([
                        'parent_topic_id' => 'Choose an eligible topic in this lesson.',
                    ]);
                }

                Gate::authorize('update', $targetTopic);
                $sameParent = $oldPlacement === 'inside_topic' && $oldTopic->is($targetTopic);
                $targetBlockUuid = $sameParent ? $stored->block_uuid : (string) Str::uuid();

                if (! $sameParent) {
                    if ($oldPlacement === 'inside_topic') {
                        $oldTopic->update(['content_blocks' => $this->removeActivityBlock($oldTopic, $stored)]);
                    } else {
                        $oldHostToDelete = $oldTopic;
                    }

                    $this->addActivityBlock($targetTopic, $targetBlockUuid, $stored->id, (int) ($data['insert_after_block'] ?? 0));
                }
            } else {
                if ($oldPlacement === 'between_topics') {
                    $targetTopic = $oldTopic;
                } else {
                    $targetTopic = $lesson->topics()->create([
                        'title' => trim((string) $data['title']),
                        'type' => 'interactive',
                        'duration' => 0,
                        'is_prerequisite' => false,
                        'order' => ($lesson->topics()->max('order') ?? 0) + 1,
                        'interactive_config' => ['placement' => 'between_topics'],
                    ]);
                    $oldTopic->update(['content_blocks' => $this->removeActivityBlock($oldTopic, $stored)]);
                }

                $targetTopic->update([
                    'title' => trim((string) $data['title']),
                    'type' => 'interactive',
                    'duration' => 0,
                    'is_prerequisite' => false,
                    'interactive_config' => ['placement' => 'between_topics'],
                ]);
            }

            $stored->update([
                'lesson_topic_id' => $targetTopic->id,
                'placement' => $targetPlacement,
                'block_uuid' => $targetBlockUuid,
                'title' => trim((string) $data['title']),
                'instructions' => $this->sanitize($data['instructions'] ?? null),
                'explanation' => $this->sanitize($data['explanation'] ?? null),
                'configuration' => $configuration,
                'revision' => $nextRevision,
            ]);

            if ($oldHostToDelete) {
                $oldHostToDelete->delete();
            }

            $this->resequenceLesson($lesson);
            $this->recalculateDurations($lesson);

            return $stored->fresh();
        });
    }

    public function delete(InteractiveActivity $activity): void
    {
        DB::transaction(function () use ($activity): void {
            $stored = InteractiveActivity::query()->lockForUpdate()->findOrFail($activity->id);
            $topic = $stored->lessonTopic()->lockForUpdate()->firstOrFail();
            $lesson = $topic->lesson()->firstOrFail();

            if ($stored->placement === 'inside_topic') {
                $topic->update(['content_blocks' => $this->removeActivityBlock($topic, $stored)]);
                $stored->delete();
            } else {
                $stored->delete();
                $topic->delete();
            }

            $this->resequenceLesson($lesson);
            $this->recalculateDurations($lesson);
        });
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

    private function addActivityBlock(LessonTopic $topic, string $blockUuid, int $activityId, int $insertAfter): void
    {
        $blocks = $this->blocksForTopic($topic);
        array_splice($blocks, min(max(0, $insertAfter) + 1, count($blocks)), 0, [[
            'type' => 'interactive_activity',
            'uuid' => $blockUuid,
            'activity_id' => $activityId,
        ]]);
        $topic->update(['content_blocks' => array_values($blocks)]);
    }

    private function removeActivityBlock(LessonTopic $topic, InteractiveActivity $activity): array
    {
        return array_values(array_filter(
            $this->blocksForTopic($topic),
            static fn ($block): bool => ! (
                is_array($block)
                && ($block['type'] ?? null) === 'interactive_activity'
                && ($block['uuid'] ?? null) === $activity->block_uuid
                && (int) ($block['activity_id'] ?? 0) === (int) $activity->id
            ),
        ));
    }

    private function resequenceLesson(Lesson $lesson): void
    {
        foreach ($lesson->topics()->orderBy('order')->orderBy('id')->get() as $index => $topic) {
            $topic->update(['order' => $index + 1]);
        }
    }

    private function addDefaultTextKinds(array $configuration, string $activityType): array
    {
        if ($activityType === InteractiveActivityType::MATCHING->value) {
            $pairs = $configuration['pairs'] ?? null;
            if (! is_array($pairs)) {
                return $configuration;
            }

            foreach ($pairs as $index => $pair) {
                if (! is_array($pair['left'] ?? null) || ! is_array($pair['right'] ?? null)) {
                    continue;
                }

                $configuration['pairs'][$index]['left']['kind'] ??= 'text';
                $configuration['pairs'][$index]['right']['kind'] ??= 'text';
            }
        } else {
            $items = $configuration['items'] ?? null;
            if (! is_array($items)) {
                return $configuration;
            }

            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

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
