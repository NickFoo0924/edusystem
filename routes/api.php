<?php

use App\Http\Controllers\Api\AnalyticsApiController;
use App\Http\Controllers\Api\CourseApiController;
use App\Http\Controllers\Api\CredentialApiController;
use App\Http\Controllers\Api\NotificationApiController;
use App\Http\Controllers\Api\QuizApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Module-to-module web services
|--------------------------------------------------------------------------
|
| One service exposed per module, and each module consumes a different
| member's service, so no pair of members duplicates an integration:
|
|   Module | Exposes                | Consumes
|   -------|------------------------|------------------------------------
|   1      | getCredentialStatus    | Module 2's getCourseInfo
|   2      | getCourseInfo          | Module 5's getCourseAnalytics
|   3      | sendNotification       | Module 4's getQuizResult
|   4      | getQuizResult          | Module 2's getCourseInfo
|   5      | getCourseAnalytics     | Module 1's getCredentialStatus
|
| Every request must carry requestID and timeStamp; every response carries
| status (S, F or E) and timeStamp. See app/Support/Ifa.php.
|
*/

/*
 * PUBLIC. No API key.
 *
 * EduSystem.md Section 7 gives an unauthenticated guest exactly one right:
 * checking whether a credential is genuine. A verification service behind a
 * key would defeat the purpose of public verification, so this one route is
 * deliberately open.
 */
Route::get('/credentials/verify', [CredentialApiController::class, 'verify'])
    ->name('api.credentials.verify');

/*
 * INTERNAL. These four return data that is not public: a course roster count,
 * a student's quiz mark, a cohort's marks, and the ability to write into
 * somebody's inbox. A caller must present the shared key in an X-API-Key
 * header (app/Http/Middleware/VerifyApiKey.php).
 */
Route::middleware('api.key')->group(function () {
    Route::get('/courses/info', [CourseApiController::class, 'info'])
        ->name('api.courses.info');

    Route::get('/quizzes/result', [QuizApiController::class, 'result'])
        ->name('api.quizzes.result');

    Route::get('/analytics/course', [AnalyticsApiController::class, 'courseAnalytics'])
        ->name('api.analytics.course');

    Route::post('/notifications/send', [NotificationApiController::class, 'send'])
        ->name('api.notifications.send');
});
