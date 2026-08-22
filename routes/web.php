<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\Auth\InvitedRegistrationController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseMaterialController;
use App\Http\Controllers\EnrolmentController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\QuizAttemptController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateTemplateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LearningPathController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * PUBLIC -- no authentication.
 *
 * EduSystem.md Section 7 gives Role 0 (Guest) exactly one entitlement: check a
 * certificate's authenticity. This route is what the QR code printed on every
 * certificate PDF points at, so it must resolve for someone with no account.
 */
Route::get('/verify/{credential_id}', [CertificateController::class, 'verify'])
    ->name('certificates.verify');

/*
 * Registration by invitation only (EduSystem.md 1A).
 *
 * These replace Breeze's deleted GET/POST /register pair. The token in the URL
 * is the entire authorisation to create an account, and the controller refuses
 * it if it is unknown, already redeemed, expired, or the address already has an
 * account.
 */
Route::middleware('guest')->group(function () {
    Route::get('register/{token}', [InvitedRegistrationController::class, 'create'])
        ->name('register.invited');

    Route::post('register/{token}', [InvitedRegistrationController::class, 'store']);
});

// Shaped by permission: students get progress and credentials, instructors get
// their review queue, administrators get the system overview.
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Streams the stored PDF to its holder. Declared before the resource route
    // so it is matched on its own terms.
    Route::get('certificates/{certificate}/download', [CertificateController::class, 'download'])
        ->name('certificates.download');

    /*
     * Only index and show exist: a certificate is minted solely by the
     * CredentialAuthority Singleton, so there is no create, store, edit, update
     * or destroy for a user to reach.
     */
    Route::resource('certificates', CertificateController::class)->only(['index', 'show']);

    // The student's own trophy cabinet. Given its own path rather than
    // badges/cabinet so it cannot be mistaken for badges/{badge}.
    Route::get('trophy-cabinet', [BadgeController::class, 'cabinet'])->name('badges.cabinet');

    /*
     * ---------------------------------------------------------------------
     * MODULE 2 -- Academic Resources Repository (Foo Chong Xian)
     * Adapter pattern: app/Patterns/Adapter
     * ---------------------------------------------------------------------
     */
    /*
     * A lecturer's read-only contact card, linked from everywhere their name
     * appears. Deliberately show-only: there is no edit or update route here,
     * so nothing a student can reach writes to another user's record.
     */
    Route::get('instructors/{user}', [ProfileController::class, 'showInstructor'])
        ->name('instructors.show');

    /*
     * Joining by class code. Registered before the resource on purpose:
     * `courses/{course}` would otherwise match "join" as a course id and this
     * page would 404.
     */
    Route::get('courses/join', [EnrolmentController::class, 'create'])->name('courses.join');
    Route::post('courses/join', [EnrolmentController::class, 'join'])->name('courses.join.store');

    Route::resource('courses', CourseController::class);

    // The two ways into a course: accepting an invitation, or the code above.
    Route::post('courses/{course}/enrol', [EnrolmentController::class, 'store'])->name('courses.enrol');
    Route::delete('courses/{course}/enrol', [EnrolmentController::class, 'destroy'])->name('courses.unenrol');

    // The instructor's side of enrolment.
    Route::post('courses/{course}/invitations', [EnrolmentController::class, 'invite'])
        ->name('courses.invitations.store');
    Route::delete('courses/{course}/invitations/{invitation}', [EnrolmentController::class, 'withdrawInvitation'])
        ->name('courses.invitations.destroy');
    Route::post('courses/{course}/class-code', [EnrolmentController::class, 'rotateClassCode'])
        ->name('courses.class-code.rotate');

    Route::get('courses/{course}/materials/create', [CourseMaterialController::class, 'create'])
        ->name('courses.materials.create');
    Route::post('courses/{course}/materials', [CourseMaterialController::class, 'store'])
        ->name('courses.materials.store');
    Route::delete('courses/{course}/materials/{material}', [CourseMaterialController::class, 'destroy'])
        ->name('courses.materials.destroy');

    /*
     * The calendar. Reads scheduled events and assignment deadlines through
     * one adapted interface -- see app/Patterns/Adapter/CalendarEntry.php.
     * `events/create` is registered before the {event} routes for the same
     * reason join was: otherwise "create" matches as an event id.
     */
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('calendar/events/create', [CalendarController::class, 'createEvent'])->name('events.create');
    Route::post('calendar/events', [CalendarController::class, 'storeEvent'])->name('events.store');
    Route::delete('calendar/events/{event}', [CalendarController::class, 'destroyEvent'])->name('events.destroy');

    Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
    Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])
        ->name('announcements.destroy');

    // The discussion under an announcement -- instructors and students both.
    Route::post('announcements/{announcement}/comments', [AnnouncementController::class, 'storeComment'])
        ->name('announcements.comments.store');
    Route::delete('announcements/{announcement}/comments/{comment}', [AnnouncementController::class, 'destroyComment'])
        ->name('announcements.comments.destroy');

    /*
     * ---------------------------------------------------------------------
     * MODULE 3 -- Student Forum & Notifications (Ong Shun Yan)
     * Observer pattern: app/Patterns/Observer
     * ---------------------------------------------------------------------
     */
    Route::get('forums/{forum}', [ForumController::class, 'show'])->name('forums.show');
    Route::post('forums/{forum}/posts', [ForumController::class, 'storePost'])->name('forums.posts.store');
    Route::delete('posts/{post}', [ForumController::class, 'destroyPost'])->name('posts.destroy');
    Route::post('posts/{post}/replies', [ForumController::class, 'storeReply'])->name('posts.replies.store');
    Route::delete('replies/{reply}', [ForumController::class, 'destroyReply'])->name('replies.destroy');

    // The inbox itself is Module 1's (EduSystem.md Section 2A).
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])
        ->name('notifications.destroy');
    Route::get('notification-preferences', [NotificationController::class, 'editPreferences'])
        ->name('notifications.preferences.edit');
    Route::put('notification-preferences', [NotificationController::class, 'updatePreferences'])
        ->name('notifications.preferences.update');

    /*
     * ---------------------------------------------------------------------
     * MODULE 4 -- Skill Assessment & Quiz (Wong Siew Lam)
     * Strategy pattern: app/Patterns/Strategy
     * ---------------------------------------------------------------------
     */
    Route::get('courses/{course}/quizzes/create', [QuizController::class, 'create'])->name('courses.quizzes.create');
    Route::post('courses/{course}/quizzes', [QuizController::class, 'store'])->name('courses.quizzes.store');
    Route::get('quizzes/{quiz}', [QuizController::class, 'show'])->name('quizzes.show');
    Route::delete('quizzes/{quiz}', [QuizController::class, 'destroy'])->name('quizzes.destroy');
    Route::post('quizzes/{quiz}/questions', [QuizController::class, 'storeQuestion'])->name('quizzes.questions.store');
    Route::delete('questions/{question}', [QuizController::class, 'destroyQuestion'])->name('questions.destroy');

    // Sitting a quiz. The Strategy is chosen per question inside store().
    Route::get('quizzes/{quiz}/attempt', [QuizAttemptController::class, 'create'])->name('quizzes.attempt');
    Route::post('quizzes/{quiz}/attempt', [QuizAttemptController::class, 'store'])->name('quizzes.attempt.store');
    Route::get('attempts/{attempt}', [QuizAttemptController::class, 'show'])->name('attempts.show');

    /*
     * ---------------------------------------------------------------------
     * MODULE 5 -- Evaluation & Grading (Ong Kwong Wei)
     * State pattern: app/Patterns/State
     * ---------------------------------------------------------------------
     */
    Route::get('courses/{course}/assignments/create', [AssignmentController::class, 'create'])
        ->name('courses.assignments.create');
    Route::post('courses/{course}/assignments', [AssignmentController::class, 'store'])
        ->name('courses.assignments.store');
    Route::get('assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
    Route::get('assignments/{assignment}/edit', [AssignmentController::class, 'edit'])->name('assignments.edit');
    Route::put('assignments/{assignment}', [AssignmentController::class, 'update'])->name('assignments.update');
    Route::delete('assignments/{assignment}', [AssignmentController::class, 'destroy'])
        ->name('assignments.destroy');

    Route::post('assignments/{assignment}/submissions', [SubmissionController::class, 'store'])
        ->name('assignments.submissions.store');
    Route::post('submissions/{submission}/submit', [SubmissionController::class, 'submit'])
        ->name('submissions.submit');
    Route::post('submissions/{submission}/grade', [SubmissionController::class, 'grade'])
        ->name('submissions.grade');
    Route::get('submissions/{submission}/download', [SubmissionController::class, 'download'])
        ->name('submissions.download');

    /*
     * Administrator screens.
     *
     * The `can:` middleware is Laravel's own authorisation middleware and
     * resolves through the database-backed Gate registered in
     * AppServiceProvider, so these guards are permission keys, never roles.
     */
    Route::resource('badges', BadgeController::class)
        ->middleware('can:badge.manage');

    /*
     * The administrator's credential register (build-priority item 7).
     *
     * Authorisation is done inside the controller rather than as route
     * middleware, because the index is readable with either certificate.issue
     * or certificate.revoke, and the `can:` middleware only takes one ability.
     */
    Route::get('admin/certificates', [CertificateController::class, 'adminIndex'])
        ->name('admin.certificates.index');
    Route::get('admin/certificates/create', [CertificateController::class, 'create'])
        ->name('admin.certificates.create');
    Route::post('admin/certificates', [CertificateController::class, 'store'])
        ->name('admin.certificates.store');
    Route::patch('admin/certificates/{certificate}/revoke', [CertificateController::class, 'revoke'])
        ->name('admin.certificates.revoke');

    Route::resource('learning-paths', LearningPathController::class)
        ->parameters(['learning-paths' => 'learningPath'])
        ->except(['show'])
        ->middleware('can:learningpath.manage');

    /*
     * Account lifecycle (EduSystem.md 1A). Each action writes an audit row, so
     * role changes and deactivations appear in the activity log.
     */
    // Module 5's cohort analytics (EduSystem.md Section 2A).
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // The admin-configurable numbers, and the certificate designs.
    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

    Route::resource('templates', CertificateTemplateController::class)
        ->parameters(['templates' => 'template'])
        ->except(['show']);

    /*
     * Account lifecycle (EduSystem.md 1A).
     *
     * The list links only to `users.confirm`, a GET page that changes nothing.
     * `users.perform` is the sole route that acts, and it requires the
     * administrator's own password in the request body. Splitting it this way
     * means no single click anywhere can alter an account, and a POST cannot be
     * replayed from a stale page without the password.
     */
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{action}/{userId}/confirm', [UserController::class, 'confirm'])->name('users.confirm');
    Route::post('users/{action}/{userId}', [UserController::class, 'perform'])->name('users.perform');

    Route::post('invitations/bulk', [InvitationController::class, 'bulkStore'])
        ->middleware('can:invitation.issue')
        ->name('invitations.bulk');

    Route::resource('invitations', InvitationController::class)
        ->only(['index', 'create', 'store', 'destroy'])
        ->middleware('can:invitation.issue');

    // Export is declared first so it is never mistaken for a log-detail route.
    Route::get('activity-logs/export', [ActivityLogController::class, 'export'])
        ->middleware('can:activitylog.view')
        ->name('activity-logs.export');

    Route::get('activity-logs', [ActivityLogController::class, 'index'])
        ->middleware('can:activitylog.view')
        ->name('activity-logs.index');

    Route::get('permissions', [PermissionController::class, 'index'])
        ->middleware('can:permission.manage')
        ->name('permissions.index');

    Route::put('permissions', [PermissionController::class, 'update'])
        ->middleware('can:permission.manage')
        ->name('permissions.update');
});

require __DIR__.'/auth.php';
