<?php

namespace App\Support\Api;

/**
 * CONSUMES Module 2's getCourseInfo service.
 *
 * Used by Module 1, which needs the course code and title to print on a
 * certificate, and by Module 4, which needs them to label a quiz.
 *
 * Neither module reads Module 2's `courses` table to get them. Module 2 owns
 * that data (EduSystem.md Section 2A), so they ask Module 2's service, and
 * the ownership boundary holds even across an HTTP call.
 */
class CourseInfoClient extends ServiceClient
{
    protected function requestPrefix(): string
    {
        return 'CRS-REQ';
    }

    /**
     * Course code and title.
     *
     * @return array{courseCode: string, courseTitle: string, studentCount: int}|null
     */
    public function fetch(int $courseId): ?array
    {
        return $this->get('/courses/info', [
            'courseId' => $courseId,
            'queryFlag' => 1,
        ]);
    }

    /**
     * The same, plus the lecturer's name and published contact address.
     *
     * @return array<string, mixed>|null
     */
    public function fetchWithInstructor(int $courseId): ?array
    {
        return $this->get('/courses/info', [
            'courseId' => $courseId,
            'queryFlag' => 2,
        ]);
    }
}
