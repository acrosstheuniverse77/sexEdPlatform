<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ParentVisibilityController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $profileSelectColumns = [
            'id',
            'user_id',
            'username',
            'avatar_path',
            'birthdate',
            'gender',
            'city_code',
            'barangay_code',
            'bio',
        ];

        if (Schema::hasColumn('learner_profiles', 'about')) {
            $profileSelectColumns[] = 'about';
        }

        $parentLinks = $user->parentLinks()
            ->where('verification_status', 'approved')
            ->whereNotNull('relationship_verified_at')
            ->with([
                'parent:id,name,email,birthdate,created_at',
                'parent.learnerProfile:' . implode(',', $profileSelectColumns),
                'parent.learnerProfile.city:code,name',
                'parent.learnerProfile.barangay:code,name',
            ])
            ->orderByDesc('relationship_verified_at')
            ->get()
            ->filter(fn ($link) => $link->parent)
            ->values();

        if ($parentLinks->isEmpty()) {
            return redirect()->route('learner.dashboard')
                ->with('info', 'No linked guardian account found.');
        }

        return view('learner.parent.index', [
            'parentLinks' => $parentLinks,
        ]);
    }
}
