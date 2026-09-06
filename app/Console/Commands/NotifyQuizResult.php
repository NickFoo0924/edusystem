<?php

/**
 * LearnSync -- Artisan console command
 *
 * Module 3: Student Forum & Notifications
 *
 * @author Ong Shun Yan
 */

namespace App\Console\Commands;

use App\Models\Quiz;
use App\Models\User;
use App\Patterns\Observer\SystemNotificationObserver;
use App\Support\Api\QuizResultClient;
use App\Support\Notifier;
use Illuminate\Console\Command;

/**
 * MODULE 3 CONSUMES MODULE 4's WEB SERVICE.
 *
 * Pushes a "your quiz has been marked" notification to a student, carrying
 * the actual score and letter grade.
 *
 * The point of this command is the data it does NOT own. Module 3 knows how
 * to reach a person's inbox, and nothing whatever about marking. The score
 * and the letter come from Module 4's getQuizResult service over HTTP, so
 * Module 3 never reads Module 4's tables and never repeats its grade scale.
 *
 * Deliberately a command a lecturer runs rather than something automatic.
 * A quiz is marked the instant it is submitted and the student is already
 * looking at the result, so notifying them every time would be noise. This
 * exists for the case where a lecturer wants to draw attention to a result
 * after the fact.
 *
 *   php artisan notify:quiz-result 1 17
 */
class NotifyQuizResult extends Command
{
    protected $signature = 'notify:quiz-result
                            {quiz : The quiz id}
                            {student : The student id}';

    protected $description = "Tell a student their quiz was marked, fetching the score from Module 4's web service";

    public function handle(QuizResultClient $quizzes): int
    {
        $quizId = (int) $this->argument('quiz');
        $studentId = (int) $this->argument('student');

        $quiz = Quiz::find($quizId);
        $student = User::find($studentId);

        if ($quiz === null || $student === null) {
            $this->error('No such quiz or student.');

            return self::FAILURE;
        }

        $this->line("Asking Module 4 for the result of quiz {$quizId} for student {$studentId}...");

        // THE CONSUMPTION. A real HTTP call to Module 4's service.
        $result = $quizzes->fetch($quizId, $studentId);

        if ($result === null) {
            // Fail soft. Module 4 being down must not crash Module 3.
            $this->warn('Module 4 did not answer. Nothing was sent.');

            return self::FAILURE;
        }

        $this->line('Module 4 answered: '.json_encode($result, JSON_PRETTY_PRINT));

        if (! ($result['graded'] ?? false)) {
            $this->warn('That attempt has not been marked yet. Nothing was sent.');

            return self::SUCCESS;
        }

        // Module 3's own job: reaching the inbox, honouring the recipient's
        // settings and refusing to say the same thing twice.
        $sent = Notifier::send(
            $student->id,
            SystemNotificationObserver::TYPE_GRADE_RECORDED,
            "Your quiz \"{$quiz->title}\" was marked: {$result['bestScore']}% ({$result['letterGrade']})",
            route('quizzes.show', $quiz->id),
            'quiz_result:'.$quizId.':'.$studentId
        );

        $sent
            ? $this->info("Notification sent to {$student->name}.")
            : $this->warn('Suppressed: the student switched this type off, or has already been told.');

        return self::SUCCESS;
    }
}
