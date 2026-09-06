<?php

/**
 * LearnSync -- Database seeder
 *
 * Shared: project-wide infrastructure
 *
 * @author Serena Lim Sze Kee, Foo Chong Xian, Ong Shun Yan, Wong Siew Lam, Ong Kwong Wei
 */

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\Answer;
use App\Models\Assignment;
use App\Models\Badge;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\CourseEvent;
use App\Models\CourseMaterial;
use App\Models\DiscussionForum;
use App\Models\LearningPath;
use App\Models\Permission;
use App\Models\Post;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\PermissionRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Gives a demonstrable LearnSync straight after `php artisan migrate:fresh --seed`:
 * one admin, two instructors, five students, three courses with enrolments, the
 * RBAC matrix from EduSystem.md Section 7, one certificate template and five
 * badge rules.
 *
 * Everything here goes through Eloquent -- no raw SQL (EduSystem.md Section 5).
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Every demo account uses this password.
     */
    private const DEMO_PASSWORD = 'password';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedSettings();
        $this->seedPermissions();

        $admin = $this->seedAdmin();
        $instructors = $this->seedInstructors();
        $students = $this->seedStudents();

        $this->seedCourses($instructors, $students);
        $this->seedCertificateTemplate();
        $this->seedBadges();
        $this->seedLearningPath();
        $this->seedCourseContent($admin, $students);

        $this->command->info('Seeded '.User::count().' users, '.Course::count().' courses, '
            .Permission::count().' permissions, '.Badge::count().' badges.');
        $this->command->info('Log in as '.$admin->email.' / '.self::DEMO_PASSWORD);
    }

    /**
     * The admin-configurable values that 1B insists must not be magic numbers.
     * The three progress weights are intended to total 100.
     */
    private function seedSettings(): void
    {
        $settings = [
            'progress.quiz_weight' => '50',
            'progress.assignment_weight' => '40',
            'progress.participation_weight' => '10',
            'certificate.pass_threshold' => '80',
        ];

        foreach ($settings as $key => $value) {
            Setting::create([
                'key' => $key,
                'value' => $value,
            ]);
        }
    }

    /**
     * The RBAC matrix from EduSystem.md Section 7, stored as data so the admin
     * permission grid can retune it without a code change.
     *
     * Each entry is: key => [label, group, [roles that hold it]].
     */
    private function seedPermissions(): void
    {
        $permissions = [
            // Identity & access -- administrator only.
            'invitation.issue' => ['Issue user invitations', 'User & Access Management', ['admin']],
            'user.activate' => ['Activate and deactivate accounts', 'User & Access Management', ['admin']],
            'user.delete' => ['Soft-delete user accounts', 'User & Access Management', ['admin']],
            'user.unlock' => ['Unlock locked-out accounts', 'User & Access Management', ['admin']],
            'permission.manage' => ['Edit the permission matrix', 'User & Access Management', ['admin']],
            'activitylog.view' => ['View and export the activity log', 'User & Access Management', ['admin']],

            // System configuration.
            'setting.manage' => ['Manage system settings', 'System Configuration', ['admin']],
            'analytics.view_system' => ['View system-level analytics', 'System Configuration', ['admin']],

            // Courses and resources -- Module 2 territory.
            'course.create' => ['Create courses', 'Course Management', ['instructor']],
            'course.update' => ['Edit own courses', 'Course Management', ['instructor']],
            'course.delete' => ['Delete own courses', 'Course Management', ['instructor']],
            'course.enroll' => ['Enrol in a course', 'Course Management', ['student']],
            'material.create' => ['Upload course materials', 'Course Management', ['instructor']],
            'material.view' => ['View and download course materials', 'Course Management', ['instructor', 'student']],
            'announcement.create' => ['Post announcements', 'Course Management', ['admin', 'instructor']],
            // Admins are excluded for the same reason Section 7 keeps them out
            // of forums: they administer the class, they are not in it.
            'announcement.comment' => ['Comment on an announcement', 'Course Management', ['instructor', 'student']],
            // Instructors schedule for their own courses; an administrator can
            // also schedule institution-wide, the same split as announcements.
            'event.manage' => ['Schedule classes and meetings', 'Course Management', ['admin', 'instructor']],

            // Assessment -- Modules 4 and 5.
            'quiz.create' => ['Create quizzes and questions', 'Assessment', ['instructor']],
            'quiz.attempt' => ['Take a quiz', 'Assessment', ['student']],
            'assignment.create' => ['Create assignments', 'Assessment', ['instructor']],
            'assignment.submit' => ['Submit work to an assignment', 'Assessment', ['student']],
            'grade.assign' => ['Review submissions and assign grades', 'Assessment', ['instructor']],

            // Forum -- Module 3. Admins are deliberately excluded (Section 7).
            'forum.post' => ['Create forum posts and replies', 'Forum', ['instructor', 'student']],
            'forum.moderate' => ['Monitor and moderate forums', 'Forum', ['instructor']],

            // Credentialing -- Module 1's centrepiece.
            'certificate.issue' => ['Manually issue a certificate', 'Credentialing', ['admin']],
            'certificate.revoke' => ['Revoke a certificate', 'Credentialing', ['admin']],
            'certificate.view_own' => ['View and download own certificates', 'Credentialing', ['student']],
            'certificate.view_course' => ['View certificates issued for own courses', 'Credentialing', ['instructor']],
            'template.manage' => ['Manage certificate templates', 'Credentialing', ['admin']],
            'badge.manage' => ['Manage badge rules', 'Credentialing', ['admin']],
            'learningpath.manage' => ['Manage learning paths', 'Credentialing', ['admin']],

            // Progress.
            'progress.view_own' => ['View own progress, badges and certificates', 'Progress', ['student']],
            'progress.view_students' => ['View progress of students in own courses', 'Progress', ['instructor']],

            // Self-service -- everyone.
            'profile.manage' => ['Manage own profile, avatar and password', 'Profile', ['admin', 'instructor', 'student']],
            'notification.preferences' => ['Manage own notification preferences', 'Profile', ['admin', 'instructor', 'student']],
        ];

        /*
         * updateOrCreate rather than create: a permission may already exist
         * because the migration that introduced it inserted it, which is how an
         * existing database picks up a new key without being re-seeded. On a
         * fresh migrate:fresh --seed the two paths meet here, and a plain
         * create() would collide on the unique key.
         */
        foreach ($permissions as $key => [$label, $group, $roles]) {
            $permission = Permission::updateOrCreate(
                ['key' => $key],
                ['label' => $label, 'group' => $group]
            );

            foreach ($roles as $role) {
                PermissionRole::firstOrCreate([
                    'permission_id' => $permission->id,
                    'role' => $role,
                ]);
            }
        }
    }

    private function seedAdmin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'learnsync.admin@gmail.com',
            'password' => self::DEMO_PASSWORD,
            'role' => 'admin',
            'bio' => 'System administrator for LearnSync.',
            'is_active' => true,
        ]);
    }

    /**
     * The lecturers who actually teach these modules, one per course.
     *
     * Keyed by course code so seedCourses() can attach each course to its own
     * lecturer without relying on array position.
     *
     * @return array<string, User>
     */
    private function seedInstructors(): array
    {
        /*
         * Phone numbers are seeded in all three states a lecturer can be in, so
         * the contact card demonstrates each: published, recorded but withheld,
         * and never given. Email is always shown.
         */
        $instructors = [
            'BMCS3404' => ['name' => 'Ting Hie Choon', 'email' => 'tinghiechoon@gmail.com',
                'school_email' => 'tinghc@tarc.edu.my',
                'phone' => '+60 3-4145 0123', 'show_phone' => true],
            'BMSE3153' => ['name' => 'Fatima Ahmed Mohamed Abdalla', 'email' => 'fatima.abdalla@yahoo.com',
                'school_email' => 'fatimaama@tarc.edu.my',
                'phone' => '+60 3-4145 0124', 'show_phone' => false],
            'BMIT3173' => ['name' => 'Malarvili A/P Nallayan', 'email' => 'malarvili.nallayan@gmail.com',
                'school_email' => 'malarvili@tarc.edu.my',
                'phone' => '+60 3-4145 0125', 'show_phone' => true],
            'BMIT3123' => ['name' => 'Lim Mei Shyan', 'email' => 'limmeishyan@outlook.com',
                'school_email' => 'limms@tarc.edu.my',
                'phone' => null, 'show_phone' => false],
            'BMIT3113' => ['name' => 'Wong Jee Fong', 'email' => 'wongjeefong@yahoo.com',
                'school_email' => 'wongjf@tarc.edu.my',
                'phone' => '+60 3-4145 0127', 'show_phone' => true],
            'BMIT3084' => ['name' => 'Jessie Teoh Poh Lin', 'email' => 'jessieteoh@gmail.com',
                'school_email' => 'jessietpl@tarc.edu.my',
                'phone' => null, 'show_phone' => false],
        ];

        $created = [];

        foreach ($instructors as $code => $instructor) {
            $created[$code] = User::create([
                'name' => $instructor['name'],
                'email' => $instructor['email'],
                'school_email' => $instructor['school_email'],
                'password' => self::DEMO_PASSWORD,
                'role' => 'instructor',
                'bio' => 'Lecturer at LearnSync. Please email me about coursework in the first instance.',
                'phone' => $instructor['phone'],
                'show_phone' => $instructor['show_phone'],
                'is_active' => true,
            ]);
        }

        return $created;
    }

    /**
     * @return array<int, User>
     */
    private function seedStudents(): array
    {
        // The five group members who own the five modules (EduSystem.md Section 2).
        $students = [
            ['name' => 'Serena Lim Sze Kee', 'email' => 'serenalim@gmail.com'],
            ['name' => 'Foo Chong Xian', 'email' => 'foochongxian@gmail.com'],
            ['name' => 'Ong Shun Yan', 'email' => 'ongshunyan@yahoo.com'],
            ['name' => 'Wong Siew Lam', 'email' => 'wongsiewlam@outlook.com'],
            ['name' => 'Ong Kwong Wei', 'email' => 'ongkwongwei@hotmail.com'],
        ];

        return array_map(fn (array $student) => User::create([
            'name' => $student['name'],
            'email' => $student['email'],
            'password' => self::DEMO_PASSWORD,
            'role' => 'student',
            'is_active' => true,
        ]), $students);
    }

    /**
     * Three courses owned by the two instructors, with students enrolled.
     *
     * Module 2 owns the `courses` table; these rows exist only so Module 1 has
     * something to name a certificate after (EduSystem.md Section 2A).
     *
     * @param  array<string, User>  $instructors  keyed by course code
     * @param  array<int, User>  $students
     */
    private function seedCourses(array $instructors, array $students): void
    {
        $courses = [
            [
                'code' => 'BMIT3173',
                'title' => 'Integrative Programming',
                'description' => 'Integrate systems using web services, data interchange formats and design patterns within an MVC framework.',
                // Every student takes this one -- it is the module LearnSync
                // itself was built for.
                'enrol' => [0, 1, 2, 3, 4],
            ],
            [
                'code' => 'BMCS3404',
                'title' => 'Project I',
                'description' => 'Plan, design and deliver a substantial software project, documented from proposal through to evaluation.',
                'enrol' => [0, 1, 2, 3],
            ],
            [
                'code' => 'BMSE3153',
                'title' => 'Software Project Management',
                'description' => 'Estimation, scheduling, risk management and team practice across the software development lifecycle.',
                'enrol' => [0, 1, 4],
            ],
            [
                'code' => 'BMIT3084',
                'title' => 'Enterprise Networking',
                'description' => 'Design and configure switched and routed enterprise networks, including VLANs, routing protocols and WAN links.',
                'enrol' => [1, 2, 3, 4],
                // Serena is invited but has not accepted, so the Invitations
                // section on the Courses page has something in it out of the
                // box. Without a row like this the flow is invisible until
                // someone invites by hand.
                'invite' => [0],
            ],
            [
                'code' => 'BMIT3113',
                'title' => 'Systems Administration',
                'description' => 'Administer server operating systems: users and permissions, services, storage, backup and monitoring.',
                'enrol' => [2, 3, 4],
                'invite' => [0, 1],
            ],
            [
                'code' => 'BMIT3123',
                'title' => 'Vulnerability Assessment and Penetration Testing',
                'description' => 'Identify, assess and responsibly report security weaknesses using recognised testing methodologies.',
                'enrol' => [0, 3],
            ],
        ];

        foreach ($courses as $course) {
            $created = Course::create([
                'instructor_id' => $instructors[$course['code']]->id,
                'code' => $course['code'],
                'title' => $course['title'],
                'description' => $course['description'],
            ]);

            $enrolledIds = array_map(fn (int $index) => $students[$index]->id, $course['enrol']);
            $created->students()->attach($enrolledIds);

            // Pending invitations: named by the lecturer, still waiting on the
            // student to accept.
            foreach ($course['invite'] ?? [] as $index) {
                CourseInvitation::create([
                    'course_id' => $created->id,
                    'student_id' => $students[$index]->id,
                    'invited_by' => $created->instructor_id,
                ]);
            }

            // Module 3 gives every course exactly one forum.
            DiscussionForum::create([
                'course_id' => $created->id,
                'title' => $created->title.' — Q&A',
            ]);
        }
    }

    /**
     * Demo content for Modules 2 to 5, so every module has something to show
     * immediately after seeding.
     *
     * No grades are seeded on purpose: writing a Grade triggers the
     * CredentialAuthority, and that chain is best demonstrated live by taking a
     * quiz or marking a submission.
     *
     * @param  array<int, User>  $students
     */
    private function seedCourseContent(User $admin, array $students): void
    {
        // Demo content sits on BMIT3173, the module LearnSync was built for.
        $first = Course::where('code', 'BMIT3173')->first();

        // MODULE 2 -- materials. The external link exercises the Adapter: it has
        // no file behind it, yet renders in the same list as an upload.
        CourseMaterial::create([
            'course_id' => $first->id,
            'title' => 'REST APIs explained — video walkthrough',
            'type' => 'lecture',
            'file_path' => 'https://www.youtube.com/watch?v=lsMQRaeKNDk',
            'is_external' => true,
        ]);

        CourseMaterial::create([
            'course_id' => $first->id,
            'title' => 'MDN reference: HTTP request methods',
            'type' => 'tutorial',
            'file_path' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Methods',
            'is_external' => true,
        ]);

        // MODULE 2 -- announcements, one global and one course-level.
        Announcement::create([
            'course_id' => null,
            'author_id' => $admin->id,
            'content' => 'Welcome to LearnSync. Certificates issued here carry a QR code anyone can verify publicly.',
        ]);

        $courseNotice = Announcement::create([
            'course_id' => $first->id,
            'author_id' => $first->instructor_id,
            'content' => 'Week 1 materials are up. Watch the REST walkthrough first, then attempt the quiz.',
        ]);

        /*
         * A short exchange under that notice, so the comment thread is not an
         * empty box on a fresh install. Written oldest-first: a student asks,
         * the lecturer answers, which is the shape the feature exists for.
         */
        AnnouncementComment::create([
            'announcement_id' => $courseNotice->id,
            'user_id' => $students[1]->id,
            'body' => 'Is the quiz open book, and does the attempt count if I run out of time?',
        ]);

        AnnouncementComment::create([
            'announcement_id' => $courseNotice->id,
            'user_id' => $first->instructor_id,
            'body' => 'Open book, yes. The timer is a guide for now, so a slow attempt still submits and still counts.',
        ]);

        AnnouncementComment::create([
            'announcement_id' => $courseNotice->id,
            'user_id' => $students[3]->id,
            'body' => 'Thanks — the walkthrough link worked for me once I signed in.',
        ]);

        // MODULE 3 -- a question already waiting in the forum.
        Post::create([
            'forum_id' => $first->forum->id,
            'user_id' => $students[1]->id,
            'content' => 'For the assignment, does the API have to return JSON or can we use XML?',
        ]);

        // MODULE 4 -- one quiz with both question types, so the Strategy pattern
        // has two different algorithms to demonstrate.
        $quiz = Quiz::create([
            'course_id' => $first->id,
            'title' => 'Web Services and Integration Basics',
            'time_limit' => 10,
        ]);

        $mcq = Question::create([
            'quiz_id' => $quiz->id,
            'type' => Question::TYPE_MCQ,
            'question_text' => 'Which HTTP method retrieves a resource without modifying it?',
        ]);
        foreach ([['GET', true], ['POST', false], ['DELETE', false], ['PATCH', false]] as [$text, $correct]) {
            Answer::create(['question_id' => $mcq->id, 'answer_text' => $text, 'is_correct' => $correct]);
        }

        $text = Question::create([
            'quiz_id' => $quiz->id,
            'type' => Question::TYPE_TEXT,
            'question_text' => 'Which architectural pattern separates data, presentation and control logic, and underpins Laravel?',
        ]);
        // Several accepted wordings; the strategy also tolerates typos.
        foreach (['MVC', 'Model View Controller', 'Model-View-Controller'] as $accepted) {
            Answer::create(['question_id' => $text->id, 'answer_text' => $accepted, 'is_correct' => true]);
        }

        // A multiple-answer question, marked by the third grading Strategy.
        // Two correct options, so the student is told to select exactly 2.
        $multi = Question::create([
            'quiz_id' => $quiz->id,
            'type' => Question::TYPE_MULTI,
            'question_text' => 'Which of these are valid HTTP methods? Select exactly 2.',
        ]);
        foreach ([['GET', true], ['POST', true], ['SEND', false], ['FETCH', false]] as [$text, $correct]) {
            Answer::create(['question_id' => $multi->id, 'answer_text' => $text, 'is_correct' => $correct]);
        }

        // MODULE 5 -- an assignment open for submission. Left on the default
        // late policy: accepted after the deadline, but marked "Turned in late".
        Assignment::create([
            'course_id' => $first->id,
            'title' => 'Consume a public REST API',
            'description' => 'Write a small client that calls a public REST API, parses the JSON response and displays it. Submit your source as a zip archive with a short report.',
            'due_date' => now()->addWeeks(2),
            'allow_late_submission' => true,
        ]);

        /*
         * MODULE 2 -- a few things in the diary, so the calendar is not an
         * empty grid on a fresh install. Dates are relative to seeding time so
         * they always sit around "today" whenever this is run.
         *
         * No deadline is seeded here on purpose: the assignment above already
         * carries one, and the calendar adapts it rather than storing a copy.
         */
        CourseEvent::create([
            'course_id' => $first->id,
            'created_by' => $first->instructor_id,
            'title' => 'Week 3 lecture: REST and JSON',
            'type' => 'class',
            'location' => 'D303B',
            'starts_at' => now()->addDays(2)->setTime(10, 0),
            'ends_at' => now()->addDays(2)->setTime(12, 0),
        ]);

        CourseEvent::create([
            'course_id' => $first->id,
            'created_by' => $first->instructor_id,
            'title' => 'Assignment consultation (online)',
            'description' => 'Drop in with questions about the REST API assignment.',
            'type' => 'meeting',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'starts_at' => now()->addDays(5)->setTime(20, 0),
            'ends_at' => now()->addDays(5)->setTime(21, 0),
        ]);

        CourseEvent::create([
            'course_id' => null,
            'created_by' => $admin->id,
            'title' => 'Semester break begins',
            'type' => 'other',
            'starts_at' => now()->addDays(9)->setTime(9, 0),
        ]);
    }

    /**
     * Two reusable templates. The placeholders are substituted at issuance time
     * by the CredentialAuthority (EduSystem.md 1C).
     *
     * The standard one is created first because course certificates pick the
     * first active template; the pathway one exists because its wording has to
     * read "the learning path", not "the course", and a learning path may name
     * its own template.
     */
    private function seedCertificateTemplate(): void
    {
        CertificateTemplate::create([
            'name' => 'LearnSync Standard Certificate',
            'background_path' => null,
            'signature_path' => null,
            'body_text' => <<<'TEXT'
            This is to certify that {{student_name}} has successfully completed
            the course {{course_title}} with a final score of {{score}}%.

            Issued on {{issued_date}}.

            Credential ID: {{credential_id}}
            Verify this credential at the address encoded in the QR code below.
            TEXT,
            'is_active' => true,
        ]);

        CertificateTemplate::create([
            'name' => 'LearnSync Pathway Certificate',
            'background_path' => null,
            'signature_path' => null,
            'body_text' => <<<'TEXT'
            This is to certify that {{student_name}} has completed every course in
            the learning path {{course_title}}, with an overall score of {{score}}%.

            Issued on {{issued_date}}.

            Credential ID: {{credential_id}}
            Verify this credential at the address encoded in the QR code below.
            TEXT,
            'is_active' => true,
        ]);
    }

    /**
     * One learning path chaining the three seeded courses in study order, so a
     * pathway certificate can be demonstrated end to end (EduSystem.md 1C).
     */
    private function seedLearningPath(): void
    {
        $path = LearningPath::create([
            'title' => 'Network Infrastructure and Security Pathway',
            'description' => 'Build the network, learn to run the servers on it, then learn to attack both so you can defend them.',
            'certificate_template_id' => CertificateTemplate::where('name', 'LearnSync Pathway Certificate')->first()?->id,
            'is_active' => true,
        ]);

        // Sequence is the order the courses are meant to be taken in: you
        // cannot sensibly test the security of infrastructure you cannot build.
        $order = [
            'BMIT3084' => 1,
            'BMIT3113' => 2,
            'BMIT3123' => 3,
        ];

        foreach ($order as $code => $sequence) {
            $course = Course::where('code', $code)->first();

            if ($course !== null) {
                $path->courses()->attach($course->id, ['sequence' => $sequence]);
            }
        }
    }

    /**
     * Badge rules spanning the criteria types in EduSystem.md 1D, so the
     * trophy cabinet has both earned and locked badges to render.
     *
     * The last one is subject-scoped: "Subject Expert" is awarded per course,
     * so it names the course it belongs to. It is seeded against BMIT3173
     * because that is the course carrying the demo quizzes -- see
     * seedSubjectExpertBadges() below.
     */
    private function seedBadges(): void
    {
        $badges = [
            [
                'name' => 'First Steps',
                'description' => 'Complete your first course.',
                'icon_path' => null,
                'tier' => 'bronze',
                'criteria_type' => 'course_completion',
                'criteria_value' => 1,
            ],
            [
                'name' => 'High Achiever',
                'description' => 'Score 90% or higher on any quiz.',
                'icon_path' => null,
                'tier' => 'silver',
                'criteria_type' => 'quiz_score',
                'criteria_value' => 90,
            ],
            [
                'name' => 'Always On Time',
                'description' => 'Submit five assignments before their due date.',
                'icon_path' => null,
                'tier' => 'silver',
                'criteria_type' => 'on_time_submissions',
                'criteria_value' => 5,
            ],
            [
                'name' => 'Conversation Starter',
                'description' => 'Publish your first post in a discussion forum.',
                'icon_path' => null,
                'tier' => 'bronze',
                'criteria_type' => 'first_forum_post',
                'criteria_value' => 1,
            ],
            [
                'name' => 'Pathway Graduate',
                'description' => 'Complete every course in a learning path.',
                'icon_path' => null,
                'tier' => 'gold',
                'criteria_type' => 'path_completion',
                'criteria_value' => 1,
            ],
        ];

        foreach ($badges as $badge) {
            Badge::create($badge + ['is_active' => true]);
        }

        $this->seedSubjectExpertBadges();
    }

    /**
     * One "Subject Expert" badge per course that actually has quizzes.
     *
     * Per-subject rather than one global rule, because badge_student is unique
     * on (badge_id, student_id): a single row could only ever be awarded once,
     * and clearing two different subjects should earn two different badges.
     *
     * Courses with no quizzes are skipped -- a badge whose condition is "pass
     * every quiz" would otherwise be unearnable and sit greyed out for ever in
     * every cabinet.
     */
    private function seedSubjectExpertBadges(): void
    {
        $courses = Course::has('quizzes')->orderBy('code')->get();

        foreach ($courses as $course) {
            Badge::create([
                'name' => 'Subject Expert — '.$course->title,
                'description' => 'Pass every quiz in '.$course->code.' '.$course->title.'.',
                'icon_path' => null,
                'tier' => 'gold',
                'criteria_type' => 'all_quizzes_in_course',
                'criteria_value' => 1,
                'course_id' => $course->id,
                'is_active' => true,
            ]);
        }
    }
}
