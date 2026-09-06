<?php

/**
 * LearnSync -- Automated test
 *
 * Module 2: Academic Resources Repository
 *
 * @author Foo Chong Xian
 */

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Permission;
use App\Models\PermissionRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clicking a calendar entry opens its detail page.
 *
 * Two things are being protected here. Access: guessing an event id must reveal
 * nothing the calendar would not already have shown you. And the join button:
 * it appears only when there is a genuinely usable link behind it, never as a
 * dead link and never wrapping something that is not a web address.
 */
class CalendarEventDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grant('course.enroll', 'student');
        $this->grant('course.create', 'instructor');
        $this->grant('event.manage', 'instructor');
    }

    public function test_an_enrolled_student_can_open_an_events_detail_page(): void
    {
        [$course, $student, $lecturer] = $this->courseWithStudent();
        $event = $this->event($course, $lecturer, ['title' => 'Week 5 Lecture']);

        $this->actingAs($student)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('Week 5 Lecture');
    }

    public function test_a_student_cannot_open_an_event_for_a_course_they_are_not_in(): void
    {
        [$course, , $lecturer] = $this->courseWithStudent();
        $event = $this->event($course, $lecturer);

        // Enrolled in nothing, guessing the id straight off the URL bar.
        $outsider = User::factory()->create(['role' => 'student']);

        $this->actingAs($outsider)
            ->get(route('events.show', $event))
            ->assertForbidden();
    }

    public function test_a_lecturer_cannot_open_an_event_for_another_lecturers_course(): void
    {
        [$course, , $lecturer] = $this->courseWithStudent();
        $event = $this->event($course, $lecturer);

        $intruder = User::factory()->create(['role' => 'instructor']);

        $this->actingAs($intruder)
            ->get(route('events.show', $event))
            ->assertForbidden();
    }

    public function test_an_institution_wide_event_is_visible_to_everyone(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $anyone = User::factory()->create(['role' => 'student']);

        $event = CourseEvent::create([
            'course_id' => null,
            'created_by' => $admin->id,
            'title' => 'Open Day',
            'type' => 'other',
            'starts_at' => now()->addWeek(),
        ]);

        $this->actingAs($anyone)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('Open Day');
    }

    public function test_a_meeting_shows_a_join_button(): void
    {
        [$course, $student, $lecturer] = $this->courseWithStudent();
        $event = $this->event($course, $lecturer, [
            'type' => 'meeting',
            'meeting_url' => 'https://meet.example.com/abc-defg-hij',
        ]);

        $this->actingAs($student)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('Join meeting')
            ->assertSee('https://meet.example.com/abc-defg-hij');
    }

    public function test_an_event_with_no_link_shows_no_join_button(): void
    {
        [$course, $student, $lecturer] = $this->courseWithStudent();
        $event = $this->event($course, $lecturer, [
            'type' => 'class',
            'location' => 'Room A301',
            'meeting_url' => null,
        ]);

        $this->actingAs($student)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('Room A301')
            // Not a disabled button, not a dead link -- absent.
            ->assertDontSee('Join meeting');
    }

    public function test_a_malformed_meeting_link_does_not_crash_or_render_a_button(): void
    {
        [$course, $student, $lecturer] = $this->courseWithStudent();
        $event = $this->event($course, $lecturer, [
            'type' => 'meeting',
            'meeting_url' => 'not a url at all',
        ]);

        $this->actingAs($student)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertDontSee('Join meeting')
            ->assertSee('not a usable web address');
    }

    public function test_a_javascript_url_is_never_rendered_as_a_join_button(): void
    {
        [$course, $student, $lecturer] = $this->courseWithStudent();
        $event = $this->event($course, $lecturer, [
            'type' => 'meeting',
            // Passes FILTER_VALIDATE_URL, and must still never become a button.
            'meeting_url' => 'javascript:alert(1)',
        ]);

        $this->actingAs($student)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertDontSee('Join meeting')
            ->assertDontSee('javascript:alert(1)', false);
    }

    public function test_a_student_does_not_see_the_names_of_their_classmates(): void
    {
        [$course, $student, $lecturer] = $this->courseWithStudent();

        $classmate = User::factory()->create(['role' => 'student', 'name' => 'Nadia Iskandar']);
        $course->students()->attach($classmate->id);

        $event = $this->event($course, $lecturer);

        // The lecturer running the class sees who it concerns...
        $this->actingAs($lecturer)
            ->get(route('events.show', $event))
            ->assertSee('Nadia Iskandar');

        // ...a student sees a count instead, as with the course roster.
        $this->actingAs($student)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertDontSee('Nadia Iskandar')
            ->assertSee('students enrolled');
    }

    /* ---------------------------------------------------------------- */

    /**
     * @return array{0: Course, 1: User, 2: User}
     */
    private function courseWithStudent(): array
    {
        $lecturer = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['role' => 'student']);

        $course = Course::create([
            'instructor_id' => $lecturer->id,
            'code' => 'BMIT4444',
            'title' => 'Calendar Testing',
            'description' => 'A course with things in the diary.',
        ]);

        $course->students()->attach($student->id);

        return [$course, $student, $lecturer];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function event(Course $course, User $creator, array $attributes = []): CourseEvent
    {
        return CourseEvent::create(array_merge([
            'course_id' => $course->id,
            'created_by' => $creator->id,
            'title' => 'Scheduled Thing',
            'type' => 'class',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHour(),
        ], $attributes));
    }

    private function grant(string $key, string $role): void
    {
        $permission = Permission::firstOrCreate(
            ['key' => $key],
            ['label' => $key, 'group' => 'Testing']
        );

        PermissionRole::firstOrCreate([
            'permission_id' => $permission->id,
            'role' => $role,
        ]);
    }
}
