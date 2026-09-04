<?php

namespace App\Services\Community;

use App\Models\CommunityFeedSetting;
use App\Models\CommunitySpace;
use App\Models\User;
use App\Notifications\Community\CommunitySafetyEventNotification;

class CommunityFeedSettingsService
{
    public function isGloballyFrozen(): bool
    {
        $settings = CommunityFeedSetting::query()
            ->where('scope_type', 'global')
            ->whereNull('scope_id')
            ->first();

        return (bool) data_get($settings?->settings, 'frozen', false);
    }

    public function freezeGlobal(User $actor, string $reason): CommunityFeedSetting
    {
        $setting = CommunityFeedSetting::query()->updateOrCreate(
            ['scope_type' => 'global', 'scope_id' => null],
            [
                'settings' => [
                    'frozen' => true,
                    'reason' => $reason,
                    'frozen_at' => now()->toDateTimeString(),
                ],
                'updated_by' => $actor->id,
            ],
        );

        $this->notifyAdmins('global_frozen', $reason, $actor);

        return $setting;
    }

    public function unfreezeGlobal(User $actor): CommunityFeedSetting
    {
        $setting = CommunityFeedSetting::query()->updateOrCreate(
            ['scope_type' => 'global', 'scope_id' => null],
            [
                'settings' => [
                    'frozen' => false,
                    'unfrozen_at' => now()->toDateTimeString(),
                ],
                'updated_by' => $actor->id,
            ],
        );

        $this->notifyAdmins('global_unfrozen', 'Community feed was reopened.', $actor);

        return $setting;
    }

    public function isSpaceFrozen(CommunitySpace $space): bool
    {
        return $this->isGloballyFrozen() || $space->frozen_at !== null;
    }

    private function notifyAdmins(string $event, string $reason, User $actor): void
    {
        User::role('admin')->get()->each(
            fn (User $admin) => $admin->notify(new CommunitySafetyEventNotification($event, $reason, $actor->id))
        );
    }
}
