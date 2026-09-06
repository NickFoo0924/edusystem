<?php

/**
 * LearnSync -- Web service consumer client
 *
 * Module 4: Skill Assessment & Quiz
 *
 * @author Wong Siew Lam
 */

namespace App\Support\Api;

/**
 * CONSUMES Module 4's getQuizResult service.
 *
 * Used by Module 3, which needs a student's mark before it can tell them
 * their quiz has been graded. Writing "you scored 82%, a B+" needs the score
 * and the letter, and Module 4 owns both.
 */
class QuizResultClient extends ServiceClient
{
    protected function requestPrefix(): string
    {
        return 'QUZ-REQ';
    }

    /**
     * One student's best attempt at one quiz.
     *
     * @return array<string, mixed>|null
     */
    public function fetch(int $quizId, int $studentId): ?array
    {
        return $this->get('/quizzes/result', [
            'quizId' => $quizId,
            'studentId' => $studentId,
        ]);
    }
}
