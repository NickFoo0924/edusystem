<?php

/**
 * LearnSync -- Web service consumer client
 *
 * Module 5: Academic Progress Analytics
 *
 * @author Ong Kwong Wei
 */

namespace App\Support\Api;

/**
 * CONSUMES Module 5's getCourseAnalytics service.
 *
 * Used by Module 2 to show a class performance summary on the course page.
 *
 * Module 2 owns course content and knows nothing about how a mark is worked
 * out. Rather than duplicating Module 5's grading logic, which would then have
 * to be kept in step every time the grade scale changed, it asks Module 5 for
 * the figures and displays whatever comes back.
 */
class CourseAnalyticsClient extends ServiceClient
{
    protected function requestPrefix(): string
    {
        return 'ANL-REQ';
    }

    /**
     * Cohort figures for one course. Never per-student marks.
     *
     * @return array<string, mixed>|null
     */
    public function fetch(int $courseId): ?array
    {
        return $this->get('/analytics/course', [
            'courseId' => $courseId,
        ]);
    }
}
