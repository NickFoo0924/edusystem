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
use App\Models\CourseMaterial;
use App\Models\Permission;
use App\Models\PermissionRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uploading course material.
 *
 * A material file is written to the `public` disk, which is symlinked into the
 * web root so students can open it. That makes the upload the most dangerous
 * input in Module 2: anything written there is reachable at a URL, and under
 * Apache a reachable .php file is executed rather than downloaded.
 *
 * These tests pin the allow-list that stops it.
 */
class CourseMaterialUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->grant('material.create', 'instructor');
    }

    public function test_a_lecturer_can_upload_a_normal_document(): void
    {
        [$course, $lecturer] = $this->course();

        $this->actingAs($lecturer)
            ->post(route('courses.materials.store', $course), [
                'title' => 'Week 1 Lecture Notes',
                'type' => 'lecture',
                'source' => 'file',
                'file' => UploadedFile::fake()->create('notes.pdf', 200, 'application/pdf'),
            ])->assertRedirect();

        $this->assertSame(1, CourseMaterial::count());
    }

    public function test_a_php_file_is_refused(): void
    {
        [$course, $lecturer] = $this->course();

        $this->actingAs($lecturer)
            ->post(route('courses.materials.store', $course), [
                'title' => 'Totally normal notes',
                'type' => 'lecture',
                'source' => 'file',
                'file' => UploadedFile::fake()->create('shell.php', 10, 'application/x-httpd-php'),
            ])->assertSessionHasErrors('file');

        $this->assertSame(0, CourseMaterial::count());
    }

    public function test_a_php_file_renamed_to_look_like_a_pdf_is_still_refused(): void
    {
        [$course, $lecturer] = $this->course();

        // The extension says pdf, the real content type does not. `mimes:`
        // checks the type rather than trusting the name.
        $this->actingAs($lecturer)
            ->post(route('courses.materials.store', $course), [
                'title' => 'Disguised',
                'type' => 'lecture',
                'source' => 'file',
                'file' => UploadedFile::fake()->create('shell.pdf', 10, 'application/x-httpd-php'),
            ])->assertSessionHasErrors('file');

        $this->assertSame(0, CourseMaterial::count());
    }

    public function test_an_html_file_is_refused(): void
    {
        [$course, $lecturer] = $this->course();

        // HTML would run script in the browser under this site's own origin.
        $this->actingAs($lecturer)
            ->post(route('courses.materials.store', $course), [
                'title' => 'Notes',
                'type' => 'lecture',
                'source' => 'file',
                'file' => UploadedFile::fake()->create('payload.html', 10, 'text/html'),
            ])->assertSessionHasErrors('file');

        $this->assertSame(0, CourseMaterial::count());
    }

    public function test_a_javascript_url_cannot_be_saved_as_an_external_material(): void
    {
        [$course, $lecturer] = $this->course();

        $this->actingAs($lecturer)
            ->post(route('courses.materials.store', $course), [
                'title' => 'Helpful video',
                'type' => 'lecture',
                'source' => 'link',
                'url' => 'javascript:alert(document.cookie)',
            ])->assertSessionHasErrors('url');

        $this->assertSame(0, CourseMaterial::count());
    }

    public function test_an_ordinary_external_link_is_accepted(): void
    {
        [$course, $lecturer] = $this->course();

        $this->actingAs($lecturer)
            ->post(route('courses.materials.store', $course), [
                'title' => 'Laravel documentation',
                'type' => 'tutorial',
                'source' => 'link',
                'url' => 'https://laravel.com/docs/12.x',
            ])->assertRedirect();

        $this->assertTrue(CourseMaterial::first()->is_external);
    }

    public function test_a_lecturer_cannot_upload_to_another_lecturers_course(): void
    {
        [$course] = $this->course();
        $intruder = User::factory()->create(['role' => 'instructor']);

        $this->actingAs($intruder)
            ->post(route('courses.materials.store', $course), [
                'title' => 'Not mine',
                'type' => 'lecture',
                'source' => 'file',
                'file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            ])->assertForbidden();

        $this->assertSame(0, CourseMaterial::count());
    }

    /* ---------------------------------------------------------------- */

    /**
     * @return array{0: Course, 1: User}
     */
    private function course(): array
    {
        $lecturer = User::factory()->create(['role' => 'instructor']);

        $course = Course::create([
            'instructor_id' => $lecturer->id,
            'code' => 'BMIT3173',
            'title' => 'Integrative Programming',
            'description' => 'A course used by the upload tests.',
        ]);

        return [$course, $lecturer];
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
