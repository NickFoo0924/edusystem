<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Permission;
use App\Models\PermissionRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enrolment is the lecturer's decision in BOTH directions.
 *
 * A student joins by invitation or class code, but may not leave: dropping a
 * class unaided would let somebody walk away from an assessment and take their
 * submissions and grades out of the lecturer's view with them. Only the
 * lecturer who owns the course can remove a student.
 *
 * These tests exist because hiding the Leave button is not the control. Each
 * one calls the endpoint directly, the way a student with the developer tools
 * open would.
 */
class CourseEnrolmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The Gate resolves against these tables, so the two keys this
        // behaviour turns on have to exist before any can() call means anything.
        $this->grant('course.create', 'instructor');
        $this->grant('course.enroll', 'student');
    }

    public function test_a_student_cannot_leave_a_course_themselves(): void
    {
        [$course, $student] = $this->courseWithStudent();

        $response = $this->actingAs($student)
            ->delete(route('courses.unenrol', $course));

        $response->assertForbidden();

        // The point of the test: the enrolment survived the attempt.
        $this->assertTrue($course->fresh()->hasStudent($student));
    }

    public function test_the_owning_lecturer_can_remove_a_student(): void
    {
        [$course, $student, $lecturer] = $this->courseWithStudent();

        $response = $this->actingAs($lecturer)
            ->delete(route('courses.students.destroy', [$course, $student]));

        $response->assertRedirect();
        $this->assertFalse($course->fresh()->hasStudent($student));
    }

    public function test_a_lecturer_cannot_remove_a_student_from_another_lecturers_course(): void
    {
        [$course, $student] = $this->courseWithStudent();

        $intruder = User::factory()->create(['role' => 'instructor']);

        $this->actingAs($intruder)
            ->delete(route('courses.students.destroy', [$course, $student]))
            ->assertForbidden();

        $this->assertTrue($course->fresh()->hasStudent($student));
    }

    public function test_a_student_cannot_remove_a_classmate(): void
    {
        [$course, $student] = $this->courseWithStudent();

        $classmate = User::factory()->create(['role' => 'student']);
        $course->students()->attach($classmate->id);

        $this->actingAs($student)
            ->delete(route('courses.students.destroy', [$course, $classmate]))
            ->assertForbidden();

        $this->assertTrue($course->fresh()->hasStudent($classmate));
    }

    public function test_removing_somebody_who_is_not_enrolled_is_a_404(): void
    {
        [$course, , $lecturer] = $this->courseWithStudent();

        $stranger = User::factory()->create(['role' => 'student']);

        $this->actingAs($lecturer)
            ->delete(route('courses.students.destroy', [$course, $stranger]))
            ->assertNotFound();
    }

    /**
     * A course, its lecturer, and one enrolled student.
     *
     * @return array{0: Course, 1: User, 2: User}
     */
    private function courseWithStudent(): array
    {
        $lecturer = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['role' => 'student']);

        $course = Course::create([
            'instructor_id' => $lecturer->id,
            'code' => 'BMIT9999',
            'title' => 'Integration Testing',
            'description' => 'A course that exists to be left, and cannot be.',
        ]);

        $course->students()->attach($student->id);

        return [$course, $student, $lecturer];
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
