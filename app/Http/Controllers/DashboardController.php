<?php

/**
 * LearnSync -- HTTP controller
 *
 * Module 5: Academic Progress Analytics
 *
 * @author Ong Kwong Wei
 */

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Assignment;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Notification;
use App\Models\StudentProgress;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The landing page, shaped by what the signed-in user is allowed to do.
 *
 * For a student this is Module 1's credential-oriented view (EduSystem.md
 * Section 2A): progress towards the next certificate, the badge cabinet and the
 * progress-over-time chart 1B asks for. Class-wide grading analytics belong to
 * Module 5 and live on its own screen.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->can('progress.view_own')) {
            return $this->studentDashboard($user);
        }

        if ($user->can('course.create')) {
            return $this->instructorDashboard($user);
        }

        return $this->adminDashboard();
    }

    private function studentDashboard(User $user): View
    {
        $progress = StudentProgress::with('course')
            ->where('student_id', $user->id)
            ->get();

        // Chart.js series: every snapshot, oldest first, per course.
        $chart = $progress->mapWithKeys(function (StudentProgress $row) {
            $snapshots = $row->snapshots()->orderBy('captured_at')->get();

            return [$row->course->title => [
                'labels' => $snapshots->map(fn ($s) => $s->captured_at->format('j M H:i'))->values(),
                'points' => $snapshots->pluck('completion_percentage')->values(),
            ]];
        })->filter(fn ($series) => count($series['labels']) > 0);

        return view('dashboard.student', [
            'progress' => $progress,
            'chart' => $chart,
            /*
             * Enrolments, not progress rows. The tile used to count $progress,
             * which only gains a row once something has been graded -- so a
             * student enrolled in five courses who had not yet been marked saw
             * "Courses 0" while the Courses page listed all five.
             */
            'courseCount' => $user->courses()->count(),
            'outstanding' => $this->outstandingWork($user),
            'certificates' => Certificate::with(['course', 'learningPath'])
                ->where('student_id', $user->id)->whereNull('revoked_at')->latest('issued_at')->get(),
            'badgeCount' => $user->badges()->count(),
            'unread' => Notification::where('user_id', $user->id)->where('is_read', false)->count(),
            'threshold' => (float) (\App\Models\Setting::where('key', 'certificate.pass_threshold')->first()->value ?? 80),
        ]);
    }

    /**
     * Work the student still owes: anything due within the next seven days, and
     * anything already past its deadline that was never handed in.
     *
     * "Handed in" means the submission has left the draft state. A saved draft
     * is deliberately still counted as outstanding, because a student who
     * uploaded a file but never pressed submit is exactly the person this panel
     * exists to catch -- and they are the likeliest to believe they are done.
     *
     * Only assignments appear: they are the only thing in the schema carrying a
     * due date. Quizzes have a time limit, not a deadline.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function outstandingWork(User $student)
    {
        $enrolledCourseIds = $student->courses()->pluck('courses.id');

        if ($enrolledCourseIds->isEmpty()) {
            return collect();
        }

        // Everything this student has already submitted or had graded.
        $settled = Submission::where('student_id', $student->id)
            ->whereIn('state', ['submitted', 'graded'])
            ->pluck('assignment_id');

        // Drafts, so the panel can distinguish "not started" from "started but
        // never submitted".
        $drafts = Submission::where('student_id', $student->id)
            ->where('state', 'draft')
            ->pluck('id', 'assignment_id');

        return Assignment::with('course')
            ->whereIn('course_id', $enrolledCourseIds)
            ->whereNotIn('id', $settled)
            // No lower bound on purpose: overdue work is the most urgent thing
            // this panel can show, so it must not be filtered out.
            ->where('due_date', '<=', now()->addDays(7))
            ->orderBy('due_date')
            ->get()
            ->map(fn (Assignment $assignment) => [
                'assignment' => $assignment,
                'overdue' => $assignment->due_date->isPast(),
                'dueToday' => $assignment->due_date->isToday(),
                'hasDraft' => $drafts->has($assignment->id),
            ]);
    }

    private function instructorDashboard(User $user): View
    {
        $courses = $user->coursesTeaching()
            ->withCount(['students', 'materials', 'quizzes', 'assignments'])
            ->get();

        return view('dashboard.instructor', [
            'courses' => $courses,
            // Work waiting to be marked, across every course they own.
            'awaitingReview' => Submission::with(['assignment.course', 'student'])
                ->where('state', 'submitted')
                ->whereIn('assignment_id', function ($query) use ($user) {
                    $query->select('id')->from('assignments')
                        ->whereIn('course_id', $user->coursesTeaching()->select('id'));
                })
                ->get(),
        ]);
    }

    private function adminDashboard(): View
    {
        return view('dashboard.admin', [
            'userCount' => User::count(),
            'activeCount' => User::where('is_active', true)->count(),
            'lockedCount' => User::whereNotNull('locked_until')->where('locked_until', '>', now())->count(),
            'courseCount' => Course::count(),
            'certificateCount' => Certificate::whereNull('revoked_at')->count(),
            'revokedCount' => Certificate::whereNotNull('revoked_at')->count(),
            'recentActivity' => ActivityLog::with('user')->latest('created_at')->limit(10)->get(),
        ]);
    }
}
