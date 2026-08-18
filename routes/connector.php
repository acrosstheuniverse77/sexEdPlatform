<?php

use App\Http\Controllers\Connector\DashboardController;
use App\Http\Controllers\Connector\CommunityCommentController;
use App\Http\Controllers\Connector\CommunityFeedController;
use App\Http\Controllers\Connector\CommunityModerationController;
use App\Http\Controllers\Connector\CommunityReactionController;
use App\Http\Controllers\Connector\CommunityReportController;
use App\Http\Controllers\Connector\HomeController;
use App\Http\Controllers\Connector\InvitationController;
use App\Http\Controllers\Connector\InvitationInboxController;
use App\Http\Controllers\Connector\MemberController;
use App\Http\Controllers\Connector\MembershipRequestController;
use App\Http\Controllers\Connector\NotificationController;
use App\Http\Controllers\Connector\RegistrationController;
use App\Http\Controllers\Connector\RoleController;
use App\Http\Controllers\Connector\SeminarController;
use App\Http\Controllers\Connector\SeminarAttendanceController;
use App\Http\Controllers\Connector\SeminarLivestreamController;
use App\Http\Controllers\Connector\SeminarInteractionController;
use App\Http\Controllers\Connector\SeminarRegistrantController;
use App\Http\Controllers\Connector\SeminarSpeakerController;
use App\Http\Controllers\Connector\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/connectors', [HomeController::class, 'index'])->name('connectors.index');
    Route::get('/connectors/invitations', [InvitationInboxController::class, 'index'])->name('connectors.invitations.index');
    Route::get('/connectors/register', [RegistrationController::class, 'create'])->name('connectors.register');
    Route::post('/connectors/register', [RegistrationController::class, 'store'])->name('connectors.store');
    Route::get('/connectors/{connector}', [HomeController::class, 'show'])->name('connectors.show');
    Route::post('/connectors/{connector}/membership-requests', [MembershipRequestController::class, 'store'])->name('connectors.membership-requests.store');
    Route::get('/connector/{connector}/status', [RegistrationController::class, 'status'])->name('connector.status');
    Route::post('/connector/{connector}/withdraw', [RegistrationController::class, 'withdraw'])->name('connector.withdraw');

    Route::get('/connector/{connector}/dashboard', [DashboardController::class, 'index'])->name('connector.dashboard');
    Route::get('/connector/{connector}/modules', [DashboardController::class, 'modules'])->name('connector.modules');
    Route::get('/connector/{connector}/educators', [DashboardController::class, 'educators'])->name('connector.educators');
    Route::get('/connector/{connector}/notifications', [NotificationController::class, 'index'])->name('connector.notifications.index');
    Route::post('/connector/{connector}/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('connector.notifications.mark-all-read');
    Route::post('/connector/{connector}/notifications/dropdown-open', [NotificationController::class, 'markDropdownRead'])->name('connector.notifications.dropdown-open');
    Route::get('/connector/{connector}/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('connector.notifications.read');

    Route::get('/connector/{connector}/community', [CommunityFeedController::class, 'index'])->name('connector.community.index');
    Route::get('/connector/{connector}/community/create', [CommunityFeedController::class, 'create'])->name('connector.community.create');
    Route::post('/connector/{connector}/community', [CommunityFeedController::class, 'store'])->name('connector.community.store');
    Route::get('/connector/{connector}/community/moderation', [CommunityFeedController::class, 'moderation'])->name('connector.community.moderation.index');
    Route::get('/connector/{connector}/community/{communityPost}', [CommunityFeedController::class, 'show'])->name('connector.community.show');
    Route::get('/connector/{connector}/community/{communityPost}/edit', [CommunityFeedController::class, 'edit'])->name('connector.community.edit');
    Route::put('/connector/{connector}/community/{communityPost}', [CommunityFeedController::class, 'update'])->name('connector.community.update');
    Route::post('/connector/{connector}/community/{communityPost}/comments', [CommunityCommentController::class, 'store'])->name('connector.community.comments.store');
    Route::post('/connector/{connector}/community/{communityPost}/reactions', [CommunityReactionController::class, 'store'])->name('connector.community.reactions.store');
    Route::delete('/connector/{connector}/community/{communityPost}/reactions', [CommunityReactionController::class, 'destroy'])->name('connector.community.reactions.destroy');
    Route::post('/connector/{connector}/community/{communityPost}/reports', [CommunityReportController::class, 'store'])->name('connector.community.reports.store');
    Route::post('/connector/{connector}/community/{communityPost}/moderation/approve', [CommunityModerationController::class, 'approve'])->name('connector.community.moderation.approve');
    Route::post('/connector/{connector}/community/{communityPost}/moderation/reject', [CommunityModerationController::class, 'reject'])->name('connector.community.moderation.reject');
    Route::post('/connector/{connector}/community/{communityPost}/moderation/hide', [CommunityModerationController::class, 'hide'])->name('connector.community.moderation.hide');
    Route::post('/connector/{connector}/community/{communityPost}/moderation/lock', [CommunityModerationController::class, 'lock'])->name('connector.community.moderation.lock');
    Route::post('/connector/{connector}/community/{communityPost}/moderation/unlock', [CommunityModerationController::class, 'unlock'])->name('connector.community.moderation.unlock');
    Route::post('/connector/{connector}/community/{communityPost}/moderation/restore', [CommunityModerationController::class, 'restore'])->name('connector.community.moderation.restore');
    Route::post('/connector/{connector}/community/{communityPost}/moderation/remove', [CommunityModerationController::class, 'remove'])->name('connector.community.moderation.remove');
    Route::post('/connector/{connector}/community/{communityPost}/moderation/escalate', [CommunityModerationController::class, 'escalate'])->name('connector.community.moderation.escalate');

    Route::get('/connector/{connector}/seminars', [SeminarController::class, 'index'])->name('connector.seminars.index');
    Route::get('/connector/{connector}/seminars/create', [SeminarController::class, 'create'])->name('connector.seminars.create');
    Route::post('/connector/{connector}/seminars', [SeminarController::class, 'store'])->name('connector.seminars.store');
    Route::get('/connector/{connector}/seminars/{seminar}', [SeminarController::class, 'show'])->name('connector.seminars.show');
    Route::get('/connector/{connector}/seminars/{seminar}/edit', [SeminarController::class, 'edit'])->name('connector.seminars.edit');
    Route::put('/connector/{connector}/seminars/{seminar}', [SeminarController::class, 'update'])->name('connector.seminars.update');
    Route::delete('/connector/{connector}/seminars/{seminar}', [SeminarController::class, 'destroy'])->name('connector.seminars.destroy');
    Route::post('/connector/{connector}/seminars/{seminar}/submit-review', [SeminarController::class, 'submitForReview'])->name('connector.seminars.submit-review');
    Route::post('/connector/{connector}/seminars/{seminar}/publish', [SeminarController::class, 'publish'])->name('connector.seminars.publish');
    Route::post('/connector/{connector}/seminars/{seminar}/archive', [SeminarController::class, 'archive'])->name('connector.seminars.archive');
    Route::post('/connector/{connector}/seminars/{seminar}/cancel', [SeminarController::class, 'cancel'])->name('connector.seminars.cancel');
    Route::post('/connector/{connector}/seminars/{seminar}/complete', [SeminarController::class, 'complete'])->name('connector.seminars.complete');
    Route::get('/connector/{connector}/seminars/{seminar}/speakers/search', [SeminarSpeakerController::class, 'search'])->name('connector.seminars.speakers.search');
    Route::post('/connector/{connector}/seminars/{seminar}/speakers', [SeminarSpeakerController::class, 'store'])->name('connector.seminars.speakers.store');
    Route::post('/connector/{connector}/seminars/{seminar}/speakers/{speaker}/approve', [SeminarSpeakerController::class, 'approve'])->name('connector.seminars.speakers.approve');
    Route::post('/connector/{connector}/seminars/{seminar}/speakers/{speaker}/reject', [SeminarSpeakerController::class, 'reject'])->name('connector.seminars.speakers.reject');
    Route::delete('/connector/{connector}/seminars/{seminar}/speakers/{speaker}', [SeminarSpeakerController::class, 'destroy'])->name('connector.seminars.speakers.destroy');
    Route::get('/connector/{connector}/seminars/{seminar}/livestream', [SeminarLivestreamController::class, 'show'])->name('connector.seminars.livestream');
    Route::post('/connector/{connector}/seminars/{seminar}/agora-token', [SeminarLivestreamController::class, 'token'])->name('connector.seminars.agora-token');
    Route::post('/connector/{connector}/seminars/{seminar}/livestream/prepare', [SeminarLivestreamController::class, 'prepare'])->name('connector.seminars.livestream.prepare');
    Route::post('/connector/{connector}/seminars/{seminar}/livestream/start', [SeminarLivestreamController::class, 'start'])->name('connector.seminars.livestream.start');
    Route::post('/connector/{connector}/seminars/{seminar}/livestream/end', [SeminarLivestreamController::class, 'end'])->name('connector.seminars.livestream.end');
    Route::get('/connector/{connector}/seminars/{seminar}/livestream/status', [SeminarLivestreamController::class, 'status'])->name('connector.seminars.livestream.status');
    Route::post('/connector/{connector}/seminars/{seminar}/comments/{comment}/hide', [SeminarInteractionController::class, 'hideComment'])->name('connector.seminars.comments.hide');
    Route::post('/connector/{connector}/seminars/{seminar}/questions/{question}/hide', [SeminarInteractionController::class, 'hideQuestion'])->name('connector.seminars.questions.hide');
    Route::post('/connector/{connector}/seminars/{seminar}/questions/{question}/answer', [SeminarInteractionController::class, 'answerQuestion'])->name('connector.seminars.questions.answer');
    Route::get('/connector/{connector}/seminars/{seminar}/registrants', [SeminarRegistrantController::class, 'index'])->name('connector.seminars.registrants.index');
    Route::get('/connector/{connector}/seminars/{seminar}/registrants/export', [SeminarRegistrantController::class, 'export'])->name('connector.seminars.registrants.export');
    Route::post('/connector/{connector}/seminars/{seminar}/registrants/{registrant}/approve', [SeminarRegistrantController::class, 'approve'])->name('connector.seminars.registrants.approve');
    Route::post('/connector/{connector}/seminars/{seminar}/registrants/{registrant}/reject', [SeminarRegistrantController::class, 'reject'])->name('connector.seminars.registrants.reject');
    Route::delete('/connector/{connector}/seminars/{seminar}/registrants/{registrant}', [SeminarRegistrantController::class, 'destroy'])->name('connector.seminars.registrants.destroy');
    Route::get('/connector/{connector}/seminars/{seminar}/attendance', [SeminarAttendanceController::class, 'index'])->name('connector.seminars.attendance');
    Route::get('/connector/{connector}/seminars/{seminar}/attendance/export', [SeminarAttendanceController::class, 'export'])->name('connector.seminars.attendance.export');

    Route::get('/connector/{connector}/members', [MemberController::class, 'index'])->name('connector.members.index');
    Route::get('/connector/{connector}/members/removed', [MemberController::class, 'removed'])->name('connector.members.removed');
    Route::patch('/connector/{connector}/members/{membership}/role', [MemberController::class, 'updateRole'])->name('connector.members.role');
    Route::delete('/connector/{connector}/members/{membership}', [MemberController::class, 'destroy'])->name('connector.members.destroy');
    Route::post('/connector/{connector}/membership-requests/{membershipRequest}/approve', [MembershipRequestController::class, 'approve'])->name('connector.membership-requests.approve');
    Route::post('/connector/{connector}/membership-requests/{membershipRequest}/reject', [MembershipRequestController::class, 'reject'])->name('connector.membership-requests.reject');

    Route::post('/connector/{connector}/invitations', [InvitationController::class, 'store'])->name('connector.invitations.store');
    Route::post('/connector/{connector}/invitations/{invitation}/accept', [InvitationController::class, 'accept'])->name('connector.invitations.accept');
    Route::post('/connector/{connector}/invitations/{invitation}/reject', [InvitationController::class, 'reject'])->name('connector.invitations.reject');
    Route::post('/connector/{connector}/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('connector.invitations.resend');
    Route::delete('/connector/{connector}/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('connector.invitations.destroy');

    Route::get('/connector/{connector}/roles', [RoleController::class, 'index'])->name('connector.roles.index');
    Route::post('/connector/{connector}/roles', [RoleController::class, 'store'])->name('connector.roles.store');
    Route::put('/connector/{connector}/roles/{role}', [RoleController::class, 'update'])->name('connector.roles.update');
    Route::delete('/connector/{connector}/roles/{role}', [RoleController::class, 'destroy'])->name('connector.roles.destroy');

    Route::get('/connector/{connector}/subscription', [SubscriptionController::class, 'show'])->name('connector.subscription');
});
