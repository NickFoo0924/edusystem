<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\DiscussionForum;
use App\Patterns\Adapter\MaterialAdapterFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * MODULE 2 (Foo Chong Xian) -- the course hub.
 *
 * Module 2 is the sole writer of `courses` and `course_materials`
 * (EduSystem.md Section 2A). Every authorisation check here is a permission
 * key, never a role comparison.
 */
class CourseController extends Controller
{
    /**
     * What a user sees depends on who they are: instructors get their own
     * courses, students get the ones they are enrolled in plus everything they
     * could still enrol in.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $teaching = $user->can('course.create')
            ? Course::with('instructor')->withCount(['students', 'materials'])
                ->where('instructor_id', $user->id)->orderBy('code')->get()
            : collect();

        $enrolled = $user->can('course.enroll')
            ? $user->courses()->with('instructor')->withCount('materials')->orderBy('code')->get()
            : collect();

        $available = $user->can('course.enroll')
            ? Course::with('instructor')
                ->whereNotIn('id', $enrolled->pluck('id'))
                ->orderBy('code')
                ->get()
            : collect();

        /*
         * Administrators teach nothing and enrol in nothing, so the three lists
         * above are all empty for them and this page used to read "You have no
         * courses" -- even though show() lets them open any course for
         * oversight. They get the full catalogue instead.
         */
        $all = $user->can('analytics.view_system')
            ? Course::with('instructor')->withCount(['students', 'materials'])->orderBy('code')->get()
            : collect();

        return view('courses.index', compact('teaching', 'enrolled', 'available', 'all'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('course.create'), 403);

        return view('courses.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('course.create'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:courses,code'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        $course = Course::create($data + ['instructor_id' => $request->user()->id]);

        // Module 3 gives every course a forum; creating it here means a course
        // is never left without somewhere to ask questions.
        DiscussionForum::create([
            'course_id' => $course->id,
            'title' => $course->title.' — Q&A',
        ]);

        return redirect()->route('courses.show', $course)
            ->with('success', 'Course created. Add some materials to get started.');
    }

    /**
     * The course page: materials, announcements, quizzes and assignments.
     */
    public function show(Request $request, Course $course): View
    {
        $this->authoriseAccess($request, $course);

        $course->load([
            'instructor',
            'materials',
            'announcements.author',
            'quizzes.questions',
            'assignments',
            'forum',
        ]);

        /*
         * Materials are grouped into the four fixed categories, and every
         * category is present even when empty -- a student should be able to
         * see at a glance that, say, no practical questions have been posted
         * yet, rather than wondering whether the section exists at all.
         */
        $materialsByCategory = collect(CourseMaterial::CATEGORIES)
            ->map(fn (string $label, string $type) => [
                'label' => $label,
                // Every material arrives as a DisplayableMaterial, so the view
                // has no idea which are files and which are external links.
                'items' => MaterialAdapterFactory::forAll(
                    $course->materials->where('type', $type)
                ),
            ]);

        return view('courses.show', [
            'course' => $course,
            'materialsByCategory' => $materialsByCategory,
            'isEnrolled' => $request->user()->can('course.enroll') && $course->hasStudent($request->user()),
            'isOwner' => $course->instructor_id === $request->user()->id,
        ]);
    }

    public function edit(Request $request, Course $course): View
    {
        $this->authoriseOwner($request, $course, 'course.update');

        return view('courses.edit', compact('course'));
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $this->authoriseOwner($request, $course, 'course.update');

        $course->update($request->validate([
            // Ignores this course's own row so saving without changing the code
            // does not trip the unique rule.
            'code' => ['required', 'string', 'max:20', Rule::unique('courses', 'code')->ignore($course->id)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]));

        return redirect()->route('courses.show', $course)->with('success', 'Course updated.');
    }

    public function destroy(Request $request, Course $course): RedirectResponse
    {
        $this->authoriseOwner($request, $course, 'course.delete');

        if ($course->certificates()->exists()) {
            return back()->with('error', 'This course has issued certificates and cannot be deleted.');
        }

        $title = $course->title;
        $course->delete();

        return redirect()->route('courses.index')->with('success', "\"{$title}\" deleted.");
    }

    /**
     * Who may look at a course: its instructor, an enrolled student, or an
     * administrator with system oversight.
     */
    private function authoriseAccess(Request $request, Course $course): void
    {
        $user = $request->user();

        $allowed = $course->instructor_id === $user->id
            || $user->can('analytics.view_system')
            || ($user->can('material.view') && $course->hasStudent($user));

        abort_unless($allowed, 403, 'You are not enrolled in this course.');
    }

    /**
     * Section 7 is explicit: an instructor cannot alter courses assigned to
     * another instructor.
     */
    private function authoriseOwner(Request $request, Course $course, string $permission): void
    {
        abort_unless($request->user()->can($permission), 403);
        abort_unless($course->instructor_id === $request->user()->id, 403, 'This course belongs to another instructor.');
    }
}
