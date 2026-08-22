<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\AnnouncementComment;

/**
 * MODULE 2 -- announcements.
 *
 * An administrator may broadcast globally; an instructor may only address a
 * course they own (EduSystem.md Section 7).
 */
class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // comments.author is eager-loaded so a page of announcements is not one
        // query per thread -- the partial reads the relation directly.
        $announcements = Announcement::with(['author', 'course', 'comments.author'])
            ->where(function ($query) use ($user) {
                // Global announcements reach everybody.
                $query->whereNull('course_id');

                if ($user->can('course.enroll')) {
                    $query->orWhereIn('course_id', $user->courses()->pluck('courses.id'));
                }

                if ($user->can('course.create')) {
                    $query->orWhereIn('course_id', $user->coursesTeaching()->pluck('id'));
                }

                if ($user->can('analytics.view_system')) {
                    $query->orWhereNotNull('course_id');
                }
            })
            ->latest()
            ->get();

        return view('announcements.index', compact('announcements'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('announcement.create'), 403);

        return view('announcements.create', [
            'courses' => $this->writableCourses($request),
            'canBroadcastGlobally' => $request->user()->can('analytics.view_system'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('announcement.create'), 403);

        $data = $request->validate([
            'course_id' => ['nullable', 'exists:courses,id'],
            'content' => ['required', 'string', 'max:2000'],
        ]);

        // A global announcement is an administrator's privilege only.
        if (blank($data['course_id'] ?? null)) {
            abort_unless($request->user()->can('analytics.view_system'), 403,
                'Only an administrator may post a global announcement.');
            $data['course_id'] = null;
        } else {
            abort_unless(
                $this->writableCourses($request)->contains('id', (int) $data['course_id']),
                403,
                'You cannot post to that course.'
            );
        }

        Announcement::create($data + ['author_id' => $request->user()->id]);

        return redirect()->route('announcements.index')->with('success', 'Announcement posted.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($request->user()->can('announcement.create'), 403);
        abort_unless(
            $announcement->author_id === $request->user()->id || $request->user()->can('analytics.view_system'),
            403
        );

        $announcement->delete();

        return redirect()->route('announcements.index')->with('success', 'Announcement deleted.');
    }

    /**
     * Courses this user may address: their own if an instructor, all of them if
     * an administrator.
     */
    private function writableCourses(Request $request)
    {
        return $request->user()->can('analytics.view_system')
            ? Course::orderBy('title')->get()
            : $request->user()->coursesTeaching()->orderBy('title')->get();
    }

    /*
     * The conversation under an announcement. A comment has no life outside
     * the notice it hangs from, which is why it lives here rather than in a
     * resource of its own.
     *
     * Instructors and students both take part. Administrators are excluded for
     * the same reason Section 7 keeps them out of forums: they run the class,
     * they are not in it.
     */
    public function storeComment(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($request->user()->can('announcement.comment'), 403);

        /*
         * Visibility is checked separately from the permission. Holding
         * announcement.comment says you may join a conversation; it does not
         * say which -- without this, a student could post under a course
         * announcement they are not enrolled in by guessing its id.
         */
        abort_unless($announcement->isVisibleTo($request->user()), 403,
            'This announcement is not addressed to you.');

        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ], [], ['body' => 'comment']);

        AnnouncementComment::create([
            'announcement_id' => $announcement->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        // Back to the comment that was just written, with the thread open.
        return back()->withFragment('announcement-'.$announcement->id);
    }

    public function destroyComment(Request $request, Announcement $announcement, AnnouncementComment $comment): RedirectResponse
    {
        abort_unless($comment->announcement_id === $announcement->id, 404);

        /*
         * Your own comment, or the announcement author moderating their own
         * notice. An administrator can reach any of it, which is consistent
         * with them being able to delete the announcement itself.
         */
        $allowed = $comment->user_id === $request->user()->id
            || $announcement->author_id === $request->user()->id
            || $request->user()->can('analytics.view_system');

        abort_unless($allowed, 403);

        $comment->delete();

        return back()->withFragment('announcement-'.$announcement->id);
    }
}
