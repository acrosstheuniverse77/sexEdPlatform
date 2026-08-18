<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttachParentChildRequest;
use App\Http\Requests\Admin\DetachParentChildRequest;
use App\Http\Requests\Admin\ToggleParentChildVerificationRequest;
use App\Models\ParentChildAccount;
use App\Models\User;
use App\Services\Admin\UserRelationshipService;
use App\Support\GuardianRelationshipTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class UserRelationshipAdminController extends Controller
{
    public function __construct(
        private readonly UserRelationshipService $userRelationshipService,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorizeRelationshipAccess($request);

        $search = trim((string) $request->string('search'));
        $verification = (string) $request->string('verification', 'all');
        $relationshipType = (string) $request->string('relationship_type', 'all');
        $perPage = max(10, min((int) $request->integer('per_page', 25), 100));

        $relationships = ParentChildAccount::query()
            ->with([
                'parent:id,name,email',
                'child:id,name,email',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->whereHas('parent', function ($parentQuery) use ($search): void {
                        $parentQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })->orWhereHas('child', function ($childQuery) use ($search): void {
                        $childQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });

                    if (is_numeric($search)) {
                        $id = (int) $search;
                        $nested->orWhere('parent_user_id', $id)
                            ->orWhere('child_user_id', $id);
                    }
                });
            })
            ->when($verification === 'verified', fn ($query) => $query->whereNotNull('relationship_verified_at'))
            ->when($verification === 'unverified', fn ($query) => $query->whereNull('relationship_verified_at'))
            ->when($relationshipType !== 'all', fn ($query) => $query->where('relationship_type', $relationshipType))
            ->latest('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        $parentCandidates = User::query()
            ->select(['id', 'name', 'email'])
            ->where(function ($query): void {
                $query->whereNull('birthdate')
                    ->orWhereDate('birthdate', '<=', now()->subYears(18));
            })
            ->orderBy('name')
            ->limit(200)
            ->get();

        $childCandidates = User::query()
            ->select(['id', 'name', 'email'])
            ->where('role', 'learner')
            ->orderBy('name')
            ->limit(200)
            ->get();

        return view('admin.users.relationships.index', [
            'relationships' => $relationships,
            'filters' => [
                'search' => $search,
                'verification' => $verification,
                'relationship_type' => $relationshipType,
                'per_page' => $perPage,
            ],
            'relationshipOptions' => GuardianRelationshipTypes::options(),
            'parentCandidates' => $parentCandidates,
            'childCandidates' => $childCandidates,
        ]);
    }

    public function attach(AttachParentChildRequest $request): RedirectResponse
    {
        try {
            $this->userRelationshipService->attachParentChild(
                payload: $request->validated(),
                actorId: (int) $request->user()->id,
                request: $request,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['relationship' => $exception->getMessage()])->withInput();
        }

        return back()->with('success', 'Guardian-dependent relationship attached successfully.');
    }

    public function detach(DetachParentChildRequest $request): RedirectResponse
    {
        try {
            $this->userRelationshipService->detachParentChild(
                parentId: (int) $request->integer('parent_user_id'),
                childId: (int) $request->integer('child_user_id'),
                actorId: (int) $request->user()->id,
                request: $request,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['relationship' => $exception->getMessage()])->withInput();
        }

        return back()->with('success', 'Guardian-dependent relationship detached successfully.');
    }

    public function toggleVerification(ToggleParentChildVerificationRequest $request): RedirectResponse
    {
        try {
            $this->userRelationshipService->setRelationshipVerification(
                parentId: (int) $request->integer('parent_user_id'),
                childId: (int) $request->integer('child_user_id'),
                isVerified: (bool) $request->boolean('is_verified'),
                actorId: (int) $request->user()->id,
                request: $request,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['relationship' => $exception->getMessage()])->withInput();
        }

        return back()->with('success', 'Relationship verification updated successfully.');
    }

    private function authorizeRelationshipAccess(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            (bool) $user?->hasRole('admin') || (bool) $user?->can('manage user relationships'),
            403
        );
    }
}
