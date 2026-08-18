<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCommunityFeedSettingsRequest;
use App\Services\Community\CommunityFeedSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunityFeedSettingsController extends Controller
{
    public function __construct(private readonly CommunityFeedSettingsService $settings)
    {
    }

    public function show(Request $request): View
    {
        abort_unless($request->user()->can('community.manage_settings'), 403);

        return view('admin.community.settings', [
            'isGloballyFrozen' => $this->settings->isGloballyFrozen(),
        ]);
    }

    public function freeze(UpdateCommunityFeedSettingsRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('community.freeze'), 403);
        $this->settings->freezeGlobal($request->user(), $request->validated('reason'));

        return back()->with('success', 'Community feed frozen.');
    }

    public function unfreeze(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('community.freeze'), 403);
        $this->settings->unfreezeGlobal($request->user());

        return back()->with('success', 'Community feed reopened.');
    }
}
