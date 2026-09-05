<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\Certificate;
use App\Models\CourseInvitation;
use App\Models\Grade;
use App\Models\Post;
use App\Models\Reply;
use App\Models\User;
use App\Patterns\Observer\SystemNotificationObserver;
use App\Patterns\Facade\CredentialAuthority;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPermissionGate();
        $this->registerAuditListeners();
        $this->registerModelObservers();
    }

    /**
     * Attach Module 3's Observer to its subjects.
     *
     * Post and Reply are the Subjects; SystemNotificationObserver is the
     * Observer. Registering here means neither model has to know the observer
     * exists (EduSystem.md Section 2).
     */
    private function registerModelObservers(): void
    {
        // Conversation.
        Post::observe(SystemNotificationObserver::class);
        Reply::observe(SystemNotificationObserver::class);
        AnnouncementComment::observe(SystemNotificationObserver::class);

        /*
         * Everything else worth being told about.
         *
         * These four were the gaps in docs/module-integration-audit.md: a
         * notice posted, work marked, a credential minted and a course
         * invitation issued all happened in silence. Adding them took four
         * lines here and no change whatsoever to the announcement screen, the
         * grading flow, the credential authority or the enrolment controller --
         * which is the claim the Observer pattern makes, demonstrated rather
         * than asserted.
         */
        Announcement::observe(SystemNotificationObserver::class);
        Grade::observe(SystemNotificationObserver::class);
        Certificate::observe(SystemNotificationObserver::class);
        CourseInvitation::observe(SystemNotificationObserver::class);

        $this->registerGradeTrigger();
    }

    /**
     * Workflow Step 5 (EduSystem.md Section 4): a Grade write wakes the
     * CredentialAuthority.
     *
     * Listening for the model event rather than calling the authority from
     * Module 5's code is what keeps Section 2A's boundary intact -- Module 5
     * writes `grades` and knows nothing about credentialing, and Module 1
     * reacts. This is framework plumbing, not a second design pattern: Module
     * 1's one GoF pattern is the CredentialAuthority Facade.
     *
     * The authority is resolved inside the closure rather than injected into
     * this provider, because the listener is registered at boot and must not
     * build the credentialing subsystem on every request that never grades
     * anything. Resolving lazily is dependency injection deferred to the point
     * of use, not a static accessor -- the class exposes none.
     */
    private function registerGradeTrigger(): void
    {
        Grade::created(function (Grade $grade) {
            app(CredentialAuthority::class)->handleGradeRecorded($grade);
        });
    }

    /**
     * Write the authentication half of the audit trail (EduSystem.md 1A).
     *
     * Laravel already fires these events, so listening is more reliable than
     * editing Breeze's controllers: any future login path is covered too.
     */
    private function registerAuditListeners(): void
    {
        Event::listen(Login::class, function (Login $event) {
            ActivityLog::record('auth.login', null, $event->user);

            // Section 1A also wants the last successful sign-in on the account,
            // and a clean slate for the failed-attempt lockout counter.
            $event->user->forceFill([
                'last_login_at' => now(),
                'failed_login_attempts' => 0,
            ])->save();
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user !== null) {
                ActivityLog::record('auth.logout', null, $event->user);
            }
        });

        /*
         * Failed sign-ins are recorded only when the address matches a real
         * account, because the schema attributes a row to a user and an attempt
         * against an unknown address has nobody to attribute it to. That is
         * also the case worth auditing: repeated failures against an account
         * that exists.
         */
        Event::listen(Failed::class, function (Failed $event) {
            if ($event->user === null) {
                return;
            }

            ActivityLog::record('auth.failed', null, $event->user);

            $event->user->increment('failed_login_attempts');
        });
    }

    /**
     * Resolve every permission key in EduSystem.md Section 7 against the
     * database instead of hardcoded role checks.
     *
     * This is what makes $user->can('certificate.revoke') work anywhere in the
     * application -- controllers, middleware and @can in Blade -- while the
     * grants themselves stay editable at runtime through the permission matrix.
     *
     * Gate::before returning null falls through to any policy, so this adds the
     * permission layer without displacing Laravel's normal authorisation.
     */
    private function registerPermissionGate(): void
    {
        Gate::before(function (User $user, string $ability) {
            // A deactivated or locked-out account can do nothing at all.
            if (! $user->is_active) {
                return false;
            }

            return $user->permissions()->contains('key', $ability) ? true : null;
        });
    }
}
