<?php

declare(strict_types=1);

use App\Models\Lesson;
use App\Models\LessonTopic;
use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $lessonIds = LessonTopic::query()
            ->where('type', 'interactive')
            ->pluck('lesson_id')
            ->unique()
            ->values();

        if ($lessonIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($lessonIds): void {
            $moduleIds = Lesson::query()
                ->whereIn('id', $lessonIds)
                ->pluck('module_id')
                ->unique()
                ->values();

            LessonTopic::query()
                ->whereIn('lesson_id', $lessonIds)
                ->where('type', 'interactive')
                ->delete();

            foreach ($lessonIds as $lessonId) {
                $topics = LessonTopic::query()
                    ->where('lesson_id', $lessonId)
                    ->orderBy('order')
                    ->orderBy('id')
                    ->get();

                foreach ($topics as $index => $topic) {
                    $topic->update(['order' => $index + 1]);
                }

                Lesson::query()
                    ->whereKey($lessonId)
                    ->update([
                        'duration' => LessonTopic::query()
                            ->where('lesson_id', $lessonId)
                            ->instructional()
                            ->sum('duration'),
                    ]);
            }

            foreach ($moduleIds as $moduleId) {
                Module::query()
                    ->whereKey($moduleId)
                    ->update([
                        'duration_minutes' => Lesson::query()
                            ->where('module_id', $moduleId)
                            ->sum('duration'),
                    ]);
            }
        });
    }

    public function down(): void
    {
        // Deleted authored interactive topics cannot be reconstructed.
    }
};
