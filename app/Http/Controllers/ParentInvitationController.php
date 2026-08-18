<?php

namespace App\Http\Controllers;

use App\Http\Requests\Parent\RespondParentChildInvitationRequest;
use App\Http\Requests\Parent\SendParentChildInvitationRequest;
use App\Models\ParentChildInvitation;
use App\Models\User;
use App\Services\ParentChildInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class ParentInvitationController extends Controller
{
    public function __construct(private readonly ParentChildInvitationService $invitationService)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $parent = $request->user();

        if ($redirect = $this->ensureApprovedParent($parent)) {
            return $redirect;
        }

        $outgoingInvitations = $this->invitationService->getOutgoingInvitations($parent);

        return view('parent.invitations.index', [
            'outgoingInvitations' => $outgoingInvitations->take(5)->values(),
            'totalOutgoingInvitations' => $outgoingInvitations->count(),
        ]);
    }

    public function history(Request $request): View|RedirectResponse
    {
        $parent = $request->user();

        if ($redirect = $this->ensureApprovedParent($parent)) {
            return $redirect;
        }

        return view('parent.invitations.history', [
            'outgoingInvitations' => $this->invitationService->getOutgoingInvitations($parent),
        ]);
    }

    public function store(SendParentChildInvitationRequest $request): RedirectResponse
    {
        $parent = $request->user();

        if ($redirect = $this->ensureApprovedParent($parent)) {
            return $redirect;
        }

        try {
            $this->invitationService->sendInvitation(
                $parent,
                (string) $request->string('identifier'),
                (string) $request->string('relationship_type'),
                $request->filled('relationship_custom') ? (string) $request->string('relationship_custom') : null,
                $request->filled('message') ? (string) $request->string('message') : null,
                $request->hasFile('relationship_document') ? [
                    'document_type' => (string) $request->string('relationship_document_type'),
                    'document' => $request->file('relationship_document'),
                    'supporting_document' => $request->file('relationship_supporting_document'),
                ] : null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['identifier' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('parent.invitations.index')
            ->with('success', 'Invitation sent successfully.');
    }

    public function show(Request $request, ParentChildInvitation $invitation): View
    {
        $viewer = $request->user();
        $isParentViewer = (int) $viewer->id === (int) $invitation->inviter_parent_user_id;
        $isChildViewer = (int) $viewer->id === (int) $invitation->child_user_id;

        abort_if(! $isParentViewer && ! $isChildViewer, 403);

        $invitation->load([
            'inviterParent:id,name,email',
            'child:id,name,email,first_name,last_name,birthdate',
            'child.learnerProfile:id,user_id,username,birthdate',
        ]);

        return view('parent.invitations.show', [
            'invitation' => $invitation,
            'isParentViewer' => $isParentViewer,
            'isChildViewer' => $isChildViewer,
        ]);
    }

    public function respond(RespondParentChildInvitationRequest $request, ParentChildInvitation $invitation): RedirectResponse
    {
        try {
            $updatedInvitation = $this->invitationService->respondToInvitation(
                $request->user(),
                $invitation,
                (string) $request->string('decision'),
                $request->filled('note') ? (string) $request->string('note') : null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['decision' => $exception->getMessage()]);
        }

        $message = $updatedInvitation->status->value === 'accepted'
            ? 'Invitation accepted. Guardian relationship is awaiting any required admin review.'
            : 'Invitation rejected.';

        return redirect()->route('parent.invitations.show', $updatedInvitation)
            ->with('success', $message);
    }

    public function cancel(Request $request, ParentChildInvitation $invitation): RedirectResponse
    {
        try {
            $this->invitationService->cancelInvitation($request->user(), $invitation);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['invitation' => $exception->getMessage()]);
        }

        return redirect()->route('parent.invitations.index')
            ->with('success', 'Invitation cancelled.');
    }

    private function ensureApprovedParent(?User $parent): ?RedirectResponse
    {
        if (! $parent || ! $parent->hasVerifiedEmail()) {
            return redirect()->route('verification.notice')
                ->with('error', 'Please verify your email first.');
        }

        if (! $parent->canBeParent()) {
            abort(403, 'You must be 18 or older to invite a child account.');
        }

        if (! $parent->isParentRegistration() || ! $parent->isParentVerificationApproved()) {
            return redirect()->route('parent.verification.status')
                ->with('warning', 'Your guardian account is still under admin review.');
        }

        if (! $parent->hasCompletedProfile()) {
            return redirect()->route('profile.complete')
                ->with('warning', 'Please complete your profile before sending invitations.');
        }

        return null;
    }
}
