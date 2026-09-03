<?php

use App\Http\Controllers\Instructor;
use App\Http\Controllers\Connector\HomeController as ConnectorHomeController;
use App\Http\Controllers\SeminarBrowseController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Instructor Routes
|--------------------------------------------------------------------------
|
| Routes for instructor-facing content management features.
| All routes are prefixed with /instructor and use permission-based access.
|
*/

Route::prefix('instructor')->name('instructor.')->middleware(['auth', 'permission:access instructor panel|create modules'])->group(function () {
    // Instructor Dashboard
    Route::get('/dashboard', [Instructor\DashboardController::class, 'index'])->name('dashboard');

    // Context switch back to learner view for transitioned accounts.
    Route::get('/switch-to-learner', [Instructor\ContextSwitchController::class, 'toLearner'])
        ->name('switch-to-learner');

    // Search endpoint
    Route::get('/search', [Instructor\SearchController::class, 'index'])->name('search');

    // Notifications
    Route::get('/notifications', [Instructor\NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::post('/notifications/mark-all-read', [Instructor\NotificationController::class, 'markAllRead'])
        ->name('notifications.mark-all-read');
    Route::post('/notifications/dropdown-open', [Instructor\NotificationController::class, 'markDropdownRead'])
        ->name('notifications.dropdown-open');
    Route::get('/notifications/{id}/read', [Instructor\NotificationController::class, 'markRead'])
        ->name('notifications.read');

    Route::get('/speaker-invitations', [Instructor\SeminarSpeakerInvitationController::class, 'index'])
        ->name('speaker-invitations.index');
    Route::get('/speaker-invitations/{speaker}', [Instructor\SeminarSpeakerInvitationController::class, 'show'])
        ->name('speaker-invitations.show');
    Route::post('/speaker-invitations/{speaker}/accept', [Instructor\SeminarSpeakerInvitationController::class, 'accept'])
        ->name('speaker-invitations.accept');
    Route::post('/speaker-invitations/{speaker}/decline', [Instructor\SeminarSpeakerInvitationController::class, 'decline'])
        ->name('speaker-invitations.decline');

    Route::get('/connectors', [ConnectorHomeController::class, 'index'])->name('connectors.index');
    Route::get('/seminars', [SeminarBrowseController::class, 'index'])->name('seminars.index');
    Route::get('/seminars/{seminar}', [SeminarBrowseController::class, 'show'])->name('seminars.show');

    // Assessment Insights
    Route::get('/assessments', [Instructor\AssessmentLogController::class, 'index'])
        ->name('assessments.index');

    // Instructor Profile
    Route::get('/profile', [Instructor\ProfileController::class, 'show'])
        ->name('profile.show');
    Route::get('/profile/edit', [Instructor\ProfileController::class, 'edit'])
        ->name('profile.edit');
    Route::put('/profile', [Instructor\ProfileController::class, 'update'])
        ->name('profile.update');

    // Instructor subscription offers
    Route::get('/subscriptions', [Instructor\SubscriptionController::class, 'index'])
        ->name('subscriptions.index');
    Route::post('/subscriptions/subscribe', [Instructor\SubscriptionController::class, 'subscribe'])
        ->name('subscriptions.subscribe');

    // Instructor subscription checkout and payment history
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/checkout/{subscription}', [PaymentController::class, 'create'])
            ->name('checkout.summary');
        Route::post('/checkout/{subscription}', [PaymentController::class, 'process'])
            ->name('checkout.proceed');
        Route::get('/pending/{payment}', [PaymentController::class, 'pending'])
            ->name('pending');
        Route::get('/status/{payment}', [PaymentController::class, 'checkStatus'])
            ->name('status');
        Route::get('/history', [PaymentController::class, 'history'])
            ->name('history');
        Route::get('/receipt/{payment}', [PaymentController::class, 'receipt'])
            ->name('receipt');
        Route::get('/paymongo/success/{subscription}', [PaymentController::class, 'paymongoSuccess'])
            ->name('paymongo.success');
        Route::get('/paymongo/failed/{subscription}', [PaymentController::class, 'paymongoFailed'])
            ->name('paymongo.failed');
    });

    // Learner Management (view-only)
    Route::resource('users', Instructor\UserController::class)->only(['index', 'show']);
    Route::patch('users/{user}/archive', [Instructor\UserController::class, 'archive'])
        ->name('users.archive');
    Route::delete('users/{user}/remove', [Instructor\UserController::class, 'remove'])
        ->name('users.remove');

    // Module Management
    Route::resource('modules', Instructor\ModuleController::class);
    Route::post('modules/{module}/review/submit', [Instructor\ModuleReviewController::class, 'submit'])
        ->name('modules.review.submit');
    Route::post('modules/{module}/review/resubmit', [Instructor\ModuleReviewController::class, 'resubmit'])
        ->name('modules.review.resubmit');
    Route::post('modules/{module}/review/withdraw', [Instructor\ModuleReviewController::class, 'withdraw'])
        ->name('modules.review.withdraw');
    Route::patch('modules/{module}/activate', [Instructor\ModuleController::class, 'activate'])
        ->name('modules.activate');
    Route::patch('modules/{module}/deactivate', [Instructor\ModuleController::class, 'deactivate'])
        ->name('modules.deactivate');
    Route::patch('modules/{id}/restore', [Instructor\ModuleController::class, 'restore'])
        ->name('modules.restore');
    Route::put('modules/{module}/feedback/{feedback}/reply', [Instructor\ModuleFeedbackController::class, 'updateReply'])
        ->name('modules.feedback.reply');

    // Enrollment Management
    Route::get('enrollments', [Instructor\EnrollmentController::class, 'index'])
        ->name('enrollments.index');
    Route::get('enrollments/{enrollment}', [Instructor\EnrollmentController::class, 'show'])
        ->name('enrollments.show');
    Route::patch('enrollments/{enrollment}/approve', [Instructor\EnrollmentController::class, 'approve'])
        ->name('enrollments.approve');
    Route::patch('enrollments/{enrollment}/reject', [Instructor\EnrollmentController::class, 'reject'])
        ->name('enrollments.reject');
    Route::patch('enrollments/{enrollment}/archive', [Instructor\EnrollmentController::class, 'archive'])
        ->name('enrollments.archive');
    Route::delete('enrollments/{enrollment}', [Instructor\EnrollmentController::class, 'destroy'])
        ->name('enrollments.destroy');
    Route::get('modules/{module}/enrollments', [Instructor\EnrollmentController::class, 'moduleEnrollments'])
        ->name('modules.enrollments');

    // Earnings
    Route::get('earnings', [Instructor\ModuleEarningsController::class, 'index'])
        ->name('earnings.index');
    Route::get('earnings/{moduleSaleLedger}', [Instructor\ModuleEarningsController::class, 'show'])
        ->name('earnings.show');
    Route::post('earnings/{moduleSaleLedger}/archive', [Instructor\ModuleEarningsController::class, 'archive'])
        ->name('earnings.archive');
    Route::delete('earnings/{moduleSaleLedger}/delete', [Instructor\ModuleEarningsController::class, 'delete'])
        ->name('earnings.delete');
    Route::delete('earnings/{moduleSaleLedger}/visibility', [Instructor\ModuleEarningsController::class, 'destroyVisibility'])
        ->name('earnings.visibility.destroy');
    Route::get('earnings/export/{format}', [Instructor\InstructorFinancialReportExportController::class, 'export'])
        ->whereIn('format', ['pdf', 'csv', 'xlsx'])
        ->middleware('permission:view own financial reports|export own financial reports')
        ->name('earnings.export');

    // Lesson Management
    Route::patch('lessons/reorder', [Instructor\LessonController::class, 'reorder'])
        ->name('lessons.reorder');
    Route::resource('lessons', Instructor\LessonController::class);
    Route::patch('lessons/{lesson}/move', [Instructor\LessonController::class, 'move'])
        ->name('lessons.move');

    // Topic Management (Lesson Topics)
    Route::patch('topics/reorder', [Instructor\TopicController::class, 'reorder'])
        ->name('topics.reorder');
    Route::get('topics/create', [Instructor\TopicController::class, 'create'])
        ->name('topics.create');
    Route::post('topics', [Instructor\TopicController::class, 'store'])
        ->name('topics.store');
    Route::get('topics/{topic}/checkpoints/{question}/edit', [Instructor\TopicController::class, 'editCheckpoint'])
        ->name('topics.checkpoints.edit');
    Route::put('topics/{topic}/checkpoints/{question}', [Instructor\TopicController::class, 'updateCheckpoint'])
        ->name('topics.checkpoints.update');
    Route::get('topics/{topic}/edit', [Instructor\TopicController::class, 'edit'])
        ->name('topics.edit');
    Route::get('topics/{topic}/preview', [Instructor\TopicController::class, 'preview'])
        ->name('topics.preview');
    Route::put('topics/{topic}', [Instructor\TopicController::class, 'update'])
        ->name('topics.update');
    Route::delete('topics/{topic}', [Instructor\TopicController::class, 'destroy'])
        ->name('topics.destroy');

    Route::post('interactive-activities/preview', [Instructor\InteractiveActivityController::class, 'preview'])
        ->name('interactive-activities.preview');
    Route::get('interactive-activities/{interactiveActivity}/edit', [Instructor\InteractiveActivityController::class, 'edit'])
        ->name('interactive-activities.edit');
    Route::put('interactive-activities/{interactiveActivity}', [Instructor\InteractiveActivityController::class, 'update'])
        ->name('interactive-activities.update');
    Route::delete('interactive-activities/{interactiveActivity}', [Instructor\InteractiveActivityController::class, 'destroy'])
        ->name('interactive-activities.destroy');

    // Image upload for TinyMCE
    Route::post('upload/image', [Instructor\TopicController::class, 'uploadImage'])
        ->name('upload.image');

    // Quiz Management
    Route::resource('quizzes', Instructor\QuizManagementController::class);
    Route::get('quizzes/{quiz}/add-question', [Instructor\QuizManagementController::class, 'addQuestion'])
        ->name('quizzes.add-question');
    Route::post('quizzes/{quiz}/store-question', [Instructor\QuizManagementController::class, 'storeQuestion'])
        ->name('quizzes.store-question');
    Route::get('quizzes/{quiz}/questions/{question}/edit', [Instructor\QuizManagementController::class, 'editQuestion'])
        ->name('quizzes.edit-question');
    Route::put('quizzes/{quiz}/questions/{question}', [Instructor\QuizManagementController::class, 'updateQuestion'])
        ->name('quizzes.update-question');
    Route::delete('quizzes/{quiz}/questions/{question}', [Instructor\QuizManagementController::class, 'deleteQuestion'])
        ->name('quizzes.delete-question');

    // CSV Import
    Route::get('quizzes/{quiz}/import/template', [Instructor\QuizManagementController::class, 'downloadTemplate'])
        ->name('quizzes.import.template');
    Route::post('quizzes/{quiz}/import/preview', [Instructor\QuizManagementController::class, 'previewImport'])
        ->name('quizzes.import.preview');
    Route::post('quizzes/{quiz}/import/confirm', [Instructor\QuizManagementController::class, 'confirmImport'])
        ->name('quizzes.import.confirm');

    // Image Library
    Route::get('image-library', [Instructor\ImageLibraryController::class, 'index'])
        ->name('image-library.index');
    Route::get('image-library/json', [Instructor\ImageLibraryController::class, 'indexJson'])
        ->name('image-library.json');
    Route::post('image-library/upload', [Instructor\ImageLibraryController::class, 'upload'])
        ->name('image-library.upload');
    Route::delete('image-library/{filename}', [Instructor\ImageLibraryController::class, 'delete'])
        ->name('image-library.delete');
});
