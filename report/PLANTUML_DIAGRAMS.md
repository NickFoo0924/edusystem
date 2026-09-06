# PlantUML source for Figures 3.1 and 4.1, all five members

Ten diagrams. Every class, attribute and method below was read from the actual code, so what
you draw matches what a marker will find if they open the files.

---

## How to turn these into PNG images

**The quickest way, no installation:**

1. Go to **https://www.plantuml.com/plantuml/uml/**
2. Delete whatever is in the box
3. Paste one diagram, from `@startuml` to `@enduml`
4. Press **Submit**
5. Right click the image, **Save image as**, and save it as a PNG
6. Paste the PNG into Word

**If you prefer VS Code:** install the extension **PlantUML** by jebbs, paste a diagram into a
`.puml` file, then press `Alt` + `D` to preview and export.

**For the Google Drive link your report asks for:** upload the PNG to Drive, right click it,
**Share**, set **Anyone with the link**, copy the link, and paste it where the report says
`[paste your Google Drive link here]`.

---

## ⚠ Two corrections to make in your reports

While checking the code for these diagrams I found two method names in the written reports that
do not exist. Fix them before submitting, because a marker who opens the file will see it:

| Report | Says | Should be |
|---|---|---|
| Member 1, Section 3.1 | `Certificate` has `isRevoked()` and `isValid()` | Neither exists. `Certificate` has no behaviour methods, only relationships and `scopeFilter()` |
| Member 4, Section 3.1 | `Question.requiredAnswerCount()` | The real name is **`requiredSelections()`** |

The diagrams below use the **correct** names.

---

# MEMBER 1, Serena Lim Sze Kee (Module 1)

## Figure 3.1, entity class diagram

```text
@startuml Member1_EntityClasses
title Module 1: Identity, Access and Digital Credentialing\nEntity Class Diagram

skinparam classAttributeIconSize 0
skinparam shadowing false
hide circle

class User {
  - id : Integer
  - name : String
  - email : String
  - schoolEmail : String
  - password : String
  - role : String
  - avatarPath : String
  - bio : Text
  - phone : String
  - showPhone : Boolean
  - isActive : Boolean
  - failedLoginAttempts : Integer
  - lockedUntil : DateTime
  - mustChangePassword : Boolean
  - lastLoginAt : DateTime
  + contactEmail() : String
  + publicPhone() : String
  + hasPublicProfile() : Boolean
  + avatarLetter() : String
  + permissions() : Collection
}

class Certificate {
  - id : Integer
  - credentialId : String
  - finalScore : Double
  - integrityHash : String
  - pdfPath : String
  - issuedAt : DateTime
  - expiresAt : DateTime
  - revokedAt : DateTime
  - revocationReason : String
}

class CertificateTemplate {
  - id : Integer
  - name : String
  - backgroundPath : String
  - signaturePath : String
  - bodyText : Text
  - isActive : Boolean
  + previewBody() : String
}

class LearningPath {
  - id : Integer
  - title : String
  - description : Text
  - isActive : Boolean
}

class StudentProgress {
  - id : Integer
  - materialsViewed : Integer
  - quizzesPassed : Integer
  - assignmentsSubmitted : Integer
  - completionPercentage : Double
  - lastCalculatedAt : DateTime
}

class ProgressSnapshot {
  - id : Integer
  - completionPercentage : Double
  - capturedAt : DateTime
}

class Badge {
  - id : Integer
  - name : String
  - description : String
  - awardType : String
  - tier : String
  - criteriaType : String
  - criteriaValue : Integer
  - iconPath : String
  - isActive : Boolean
  + criteriaDescription() : String
  + isCertificateRule() : Boolean
  + iconUrl() : String
}

class Permission {
  - id : Integer
  - key : String
  - label : String
  - group : String
}

class PermissionRole {
  - id : Integer
  - role : String
}

class Invitation {
  - id : Integer
  - email : String
  - role : String
  - token : String
  - expiresAt : DateTime
  - acceptedAt : DateTime
  + status() : String
  + registrationUrl() : String
}

class ActivityLog {
  - id : Integer
  - action : String
  - targetType : String
  - targetId : Integer
  - ipAddress : String
  - userAgent : String
  + targetLabel() : String
}

class Notification {
  - id : Integer
  - type : String
  - message : String
  - link : String
  - reference : String
  - isRead : Boolean
}

class NotificationPreference {
  - id : Integer
  - type : String
  - enabled : Boolean
}

class Course <<Module 2>> {
  - code : String
  - title : String
}

User "1" -- "0..*" Certificate : certificates >
User "1" -- "0..*" StudentProgress : studentProgress >
User "1" -- "0..*" Notification : notifications >
User "1" -- "0..*" NotificationPreference : notificationPreferences >
User "1" -- "0..*" ActivityLog : activityLogs >
User "1" -- "0..*" Invitation : invitationsSent >
User "0..*" -- "0..*" Badge : badges >

Certificate "0..*" -- "1" User : student >
Certificate "0..*" -- "0..1" Course : course >
Certificate "0..*" -- "0..1" LearningPath : learningPath >
Certificate "0..*" -- "1" CertificateTemplate : certificateTemplate >

StudentProgress "1" -- "0..*" ProgressSnapshot : snapshots >
StudentProgress "0..*" -- "1" Course : course >

Badge "0..*" -- "0..1" Course : course >
Badge "0..*" -- "0..1" CertificateTemplate : certificateTemplate >

LearningPath "0..*" -- "1..*" Course : courses (ordered) >
LearningPath "0..*" -- "0..1" CertificateTemplate : certificateTemplate >

Permission "1" -- "0..*" PermissionRole : permissionRoles >
Invitation "0..*" -- "1" User : inviter >

note bottom of Certificate
  Exactly one of course or learningPath is set.
  integrityHash is recomputed at every verification,
  which is how tampering is detected.
end note

note bottom of Badge
  One row is an award RULE, not an award.
  awardType decides whether satisfying it
  gives a badge or mints a certificate.
end note

@enduml
```

## Figure 4.1, Facade class diagram

```text
@startuml Member1_FacadePattern
title Module 1: Facade Pattern\nCredentialAuthority and its subsystem

skinparam classAttributeIconSize 0
skinparam shadowing false
hide circle

class CertificateController <<Client>> {
  - authority : CredentialAuthority
  + store(Request) : RedirectResponse
  + revoke(Request, Certificate) : RedirectResponse
  + verify(String) : View
}

class CredentialAuthority <<Facade>> {
  + issueCertificate(User, Course, Float, Badge) : Certificate
  + issuePathwayCertificate(User, LearningPath) : Certificate
  + revoke(Certificate, String) : Certificate
  + verify(String) : Array
  + evaluateBadges(User) : Collection
  + recalculateProgress(User, Course) : StudentProgress
  + handleGradeRecorded(Grade) : Array
  + evaluateCertificateRules(User) : Collection
  + verificationUrl(String) : String
  + verificationQrCode(String) : String
}

package "The subsystem" <<Rectangle>> {

  class CredentialIdGenerator {
    - BASE32_ALPHABET : String
    - MAX_ID_ATTEMPTS : Integer
    + generate() : String
    - randomBase32(Integer) : String
  }

  class IntegrityHasher {
    + hash(Integer, Integer, Float, String, String) : String
    + matches(Certificate) : Boolean
  }

  class CertificateRenderer {
    + pdfPathFor(String) : String
    + verificationUrl(String) : String
    + verificationQrCode(String) : String
    + render(Certificate) : void
    - fillPlaceholders(Certificate) : String
  }

  class ProgressCalculator {
    + recalculate(User, Course) : StudentProgress
    + passThreshold() : Float
    + averageScoreIn(User, Course) : Float
    + recordedScoreFor(User, Course) : Float
    - quizzesPassedIn(User, Course) : Integer
  }

  class BadgeRuleEvaluator {
    - badgeRules : Collection
    + evaluate(User) : Collection
    - badgeRules() : Collection
  }

  class AwardConditionEvaluator {
    + isSatisfied(User, Badge) : Boolean
    - hasClearedEveryQuiz(User, Badge) : Boolean
    - loginStreak(User) : Integer
  }
}

CertificateController --> CredentialAuthority : depends only on the Facade

CredentialAuthority --> CredentialIdGenerator
CredentialAuthority --> IntegrityHasher
CredentialAuthority --> CertificateRenderer
CredentialAuthority --> ProgressCalculator
CredentialAuthority --> BadgeRuleEvaluator
CredentialAuthority --> AwardConditionEvaluator
BadgeRuleEvaluator --> AwardConditionEvaluator

note right of CredentialAuthority
  Constructed by dependency injection.
  Public constructor, no static state,
  no getInstance(). This is NOT a Singleton.
end note

note bottom of CertificateRenderer
  Hides DomPDF and the QR encoder.
  CertificateController imports neither.
end note

@enduml
```

---

# MEMBER 2, Foo Chong Xian (Module 2)

## Figure 3.1, entity class diagram

```text
@startuml Member2_EntityClasses
title Module 2: Academic Resources Repository\nEntity Class Diagram

skinparam classAttributeIconSize 0
skinparam shadowing false
hide circle

class Course {
  - id : Integer
  - code : String
  - classCode : String
  - title : String
  - description : Text
  + label() : String
  + hasStudent(User) : Boolean
  + generateClassCode() : String
}

class CourseMaterial {
  - id : Integer
  - title : String
  - type : String
  - filePath : String
  - isExternal : Boolean
  + categoryLabel() : String
}

class CourseInvitation {
  - id : Integer
  - acceptedAt : DateTime
  + isPending() : Boolean
}

class Announcement {
  - id : Integer
  - content : Text
  + isGlobal() : Boolean
  + isVisibleTo(User) : Boolean
}

class AnnouncementComment {
  - id : Integer
  - body : Text
}

class CourseEvent {
  - id : Integer
  - title : String
  - description : Text
  - type : String
  - location : String
  - meetingUrl : String
  - startsAt : DateTime
  - endsAt : DateTime
  + isGlobal() : Boolean
}

class User <<Module 1>> {
  - name : String
  - role : String
}

class DiscussionForum <<Module 3>> {
  - title : String
}

class Quiz <<Module 4>> {
  - title : String
}

class Assignment <<Module 5>> {
  - title : String
  - dueDate : DateTime
}

Course "0..*" -- "1" User : instructor >
Course "0..*" -- "0..*" User : students >
Course "1" -- "0..*" CourseMaterial : materials >
Course "1" -- "0..*" CourseInvitation : invitations >
Course "1" -- "0..*" Announcement : announcements >
Course "1" -- "0..*" CourseEvent : events >
Course "1" -- "0..1" DiscussionForum : forum >
Course "1" -- "0..*" Quiz : quizzes >
Course "1" -- "0..*" Assignment : assignments >

CourseInvitation "0..*" -- "1" User : student >
CourseInvitation "0..*" -- "1" User : invitedBy >

Announcement "0..*" -- "1" User : author >
Announcement "1" -- "0..*" AnnouncementComment : comments >
AnnouncementComment "0..*" -- "1" User : author >

CourseEvent "0..*" -- "1" User : creator >

note bottom of CourseEvent
  A null course means an institution wide event,
  the same convention Announcement uses.
  Assignment deadlines get NO row here.
end note

note bottom of CourseMaterial
  filePath holds either a stored path or an
  external URL. isExternal is the only thing
  telling them apart, and only the Adapter
  factory ever reads it.
end note

note right of Course
  classCode is NOT the public code.
  Holding it is what grants enrolment,
  so it is never exposed by the web service.
end note

@enduml
```

## Figure 4.1, Adapter class diagram (both uses)

```text
@startuml Member2_AdapterPattern
title Module 2: Adapter Pattern\nApplied twice, to two mismatched pairs

skinparam classAttributeIconSize 0
skinparam shadowing false
hide circle

package "USE ONE: course materials" <<Rectangle>> {

  class MaterialsView <<Client>> {
    courses/show.blade.php
  }

  interface DisplayableMaterial <<Target>> {
    + title() : String
    + url() : String
    + kind() : String
    + detail() : String
    + opensExternally() : Boolean
    + iconPath() : String
  }

  class FileResourceAdapter <<Adapter>> {
    - material : CourseMaterial
    + title() : String
    + url() : String
    + kind() : String
    + detail() : String
  }

  class ExternalResourceAdapter <<Adapter>> {
    - material : CourseMaterial
    - KNOWN_HOSTS : Array
    + title() : String
    + url() : String
    + kind() : String
    + detail() : String
    - host() : String
  }

  class MaterialAdapterFactory {
    + for(CourseMaterial) : DisplayableMaterial
    + forAll(Iterable) : Collection
  }

  class CourseMaterial <<Adaptee>> {
    - filePath : String
    - isExternal : Boolean
  }
}

package "USE TWO: the calendar" <<Rectangle>> {

  class CalendarView <<Client>> {
    calendar/index.blade.php
  }

  interface CalendarEntry <<Target>> {
    + title() : String
    + startsAt() : Carbon
    + endsAt() : Carbon
    + kind() : String
    + url() : String
    + courseLabel() : String
    + detail() : String
    + classes() : String
  }

  class ScheduledEventAdapter <<Adapter>> {
    - event : CourseEvent
    + startsAt() : Carbon
    + endsAt() : Carbon
    - timeRange() : String
  }

  class AssignmentDeadlineAdapter <<Adapter>> {
    - assignment : Assignment
    + startsAt() : Carbon
    + endsAt() : Carbon
  }

  class CalendarAdapterFactory {
    + forEvent(CourseEvent) : CalendarEntry
    + forAssignment(Assignment) : CalendarEntry
    + groupedByDay(...) : Collection
    + merge(...) : Collection
  }

  class CourseEvent <<Adaptee>> {
    - startsAt : DateTime
    - endsAt : DateTime
    - location : String
  }

  class Assignment <<Adaptee, Module 5>> {
    - dueDate : DateTime
  }
}

MaterialsView --> DisplayableMaterial : calls only the interface
MaterialAdapterFactory ..> DisplayableMaterial : creates
FileResourceAdapter ..|> DisplayableMaterial
ExternalResourceAdapter ..|> DisplayableMaterial
FileResourceAdapter --> CourseMaterial : wraps
ExternalResourceAdapter --> CourseMaterial : wraps

CalendarView --> CalendarEntry : calls only the interface
CalendarAdapterFactory ..> CalendarEntry : creates
ScheduledEventAdapter ..|> CalendarEntry
AssignmentDeadlineAdapter ..|> CalendarEntry
ScheduledEventAdapter --> CourseEvent : wraps
AssignmentDeadlineAdapter --> Assignment : wraps

note bottom of MaterialAdapterFactory
  The ONLY place in the whole system
  that reads isExternal.
end note

note bottom of AssignmentDeadlineAdapter
  endsAt() returns null: a deadline is a
  moment, not a span. Nothing is copied,
  so moving a due date moves the calendar.
end note

@enduml
```

---

# MEMBER 3, Ong Shun Yan (Module 3)

## Figure 3.1, entity class diagram

```text
@startuml Member3_EntityClasses
title Module 3: Student Forum and Notifications\nEntity Class Diagram

skinparam classAttributeIconSize 0
skinparam shadowing false
hide circle

class DiscussionForum {
  - id : Integer
  - title : String
}

class Post {
  - id : Integer
  - content : Text
}

class Reply {
  - id : Integer
  - content : Text
}

class Notification {
  - id : Integer
  - type : String
  - message : String
  - link : String
  - reference : String
  - isRead : Boolean
}

class NotificationPreference {
  - id : Integer
  - type : String
  - enabled : Boolean
}

class User <<Module 1>> {
  - name : String
  - role : String
}

class Course <<Module 2>> {
  - code : String
  - title : String
}

DiscussionForum "1" -- "1" Course : course >
DiscussionForum "1" -- "0..*" Post : posts >

Post "0..*" -- "1" User : author >
Post "1" -- "0..*" Reply : replies >

Reply "0..*" -- "1" User : author >

Notification "0..*" -- "1" User : user >
NotificationPreference "0..*" -- "1" User : user >

note bottom of DiscussionForum
  One to one with Course. Every course gets a
  forum the moment it is created, so a course
  is never without somewhere to ask.
end note

note bottom of Notification
  reference says what the notification is ABOUT,
  for example event:12. It is what lets the
  sender refuse to say the same thing twice.

  Module 1 owns the inbox. Module 3 writes the rows.
end note

note right of NotificationPreference
  Opt out: a missing row means the user has
  never changed the setting, and silence must
  not mean send nothing.
end note

@enduml
```

## Figure 4.1, Observer class diagram (seven subjects)

```text
@startuml Member3_ObserverPattern
title Module 3: Observer Pattern\nSeven subjects, one observer, none of them knows it exists

skinparam classAttributeIconSize 0
skinparam shadowing false
hide circle

package "The SUBJECTS" <<Rectangle>> {
  class Post <<Subject>>
  class Reply <<Subject>>
  class AnnouncementComment <<Subject>>
  class Announcement <<Subject>>
  class Grade <<Subject, Module 5>>
  class Certificate <<Subject, Module 1>>
  class CourseInvitation <<Subject, Module 2>>
}

class SystemNotificationObserver <<Observer>> {
  + TYPE_NEW_POST : String
  + TYPE_NEW_REPLY : String
  + TYPE_MENTION : String
  + TYPE_ANNOUNCEMENT_COMMENT : String
  + TYPE_ANNOUNCEMENT_POSTED : String
  + TYPE_GRADE_RECORDED : String
  + TYPE_CERTIFICATE_ISSUED : String
  + TYPE_COURSE_INVITATION : String
  --
  + created(Model) : void
  - onPostCreated(Post) : void
  - onReplyCreated(Reply) : void
  - onAnnouncementCommentCreated(...) : void
  - onAnnouncementCreated(Announcement) : void
  - onGradeRecorded(Grade) : void
  - onCertificateIssued(Certificate) : void
  - onCourseInvitationCreated(...) : void
  - notifyMentions(...) : Collection
  - notify(...) : void
}

class Notifier {
  + send(Integer, String, String, String, String) : Boolean
  + alreadySent(Integer, String, String) : Boolean
}

class Mentions {
  + parse(String, Course) : Collection
  + candidates(Course) : Collection
  + highlight(String, Course) : String
}

class Notification <<Module 1 owns the inbox>> {
  - type : String
  - message : String
  - reference : String
  - isRead : Boolean
}

class AppServiceProvider {
  - registerModelObservers() : void
}

Post ..> SystemNotificationObserver : created event
Reply ..> SystemNotificationObserver : created event
AnnouncementComment ..> SystemNotificationObserver : created event
Announcement ..> SystemNotificationObserver : created event
Grade ..> SystemNotificationObserver : created event
Certificate ..> SystemNotificationObserver : created event
CourseInvitation ..> SystemNotificationObserver : created event

SystemNotificationObserver --> Notifier : hands the row to
SystemNotificationObserver --> Mentions : resolves @handles
Notifier --> Notification : writes

AppServiceProvider ..> SystemNotificationObserver : Post::observe(...)

note bottom of Notifier
  Checks the recipient's preference and the
  duplicate guard BEFORE writing. Turning a
  type off stops the row being created at all.
end note

note top of SystemNotificationObserver
  Eloquent's created event IS the notify() call
  of the pattern. Four of these seven subjects
  were added later: it took four lines in the
  service provider and no change to the code
  that saves them.
end note

@enduml
```

---

# MEMBER 4, Wong Siew Lam (Module 4)

## Figure 3.1, entity class diagram

```text
@startuml Member4_EntityClasses
title Module 4: Skill Assessment and Quiz\nEntity Class Diagram

skinparam classAttributeIconSize 0
skinparam shadowing false
hide circle

class Quiz {
  - id : Integer
  - title : String
  - timeLimit : Integer
}

class Question {
  - id : Integer
  - type : String
  - questionText : Text
  + requiredSelections() : Integer
  + selectionInstruction() : String
  + correctAnswers() : Collection
}

class Answer {
  - id : Integer
  - answerText : String
  - isCorrect : Boolean
}

class QuizAttempt {
  - id : Integer
  - durationSeconds : Integer
}

class QuizAttemptAnswer {
  - id : Integer
  - response : Text
  - isCorrect : Boolean
  - awardedScore : Double
}

class Course <<Module 2>> {
  - code : String
  - title : String
}

class User <<Module 1>> {
  - name : String
}

class Grade <<Module 5>> {
  - calculatedScore : Double
}

Quiz "0..*" -- "1" Course : course >
Quiz "1" -- "1..*" Question : questions >
Quiz "1" -- "0..*" QuizAttempt : attempts >

Question "1" -- "1..*" Answer : answers >

QuizAttempt "0..*" -- "1" User : student >
QuizAttempt "1" -- "0..*" QuizAttemptAnswer : answers >
QuizAttempt "1" -- "0..1" Grade : grade >

QuizAttemptAnswer "0..*" -- "1" Question : question >

note bottom of Answer
  isCorrect is the answer key.
  It is NEVER sent to a student's browser:
  the quiz page renders only answerText and id.
end note

note bottom of Question
  requiredSelections() is DERIVED from how many
  answers are flagged correct, not stored.
  A question claiming to want three while holding
  two correct options would be unanswerable.
end note

note bottom of QuizAttemptAnswer
  Unique on (attempt, question).
  Without this table an attempt could be scored
  but never reviewed, because nothing would
  record what the student actually answered.
end note

@enduml
```

## Figure 4.1, Strategy class diagram

```text
@startuml Member4_StrategyPattern
title Module 4: Strategy Pattern\nThree question types, three unrelated algorithms

skinparam classAttributeIconSize 0
skinparam shadowing false
hide circle

class QuizAttemptController <<Context>> {
  - resolver : GradingStrategyResolver
  + create(Request, Quiz) : View
  + store(Request, Quiz) : RedirectResponse
  + show(Request, QuizAttempt) : View
}

class GradingStrategyResolver {
  - STRATEGIES : Array
  + for(Question) : GradingStrategy
  + availableTypes() : Array
}

interface GradingStrategy <<Strategy>> {
  + grade(Question, String) : GradedAnswer
  + describe() : String
}

class MCQGradingStrategy <<ConcreteStrategy>> {
  + grade(Question, String) : GradedAnswer
  + describe() : String
}

class MultipleAnswerGradingStrategy <<ConcreteStrategy>> {
  + grade(Question, String) : GradedAnswer
  + describe() : String
}

class TextMatchGradingStrategy <<ConcreteStrategy>> {
  + grade(Question, String) : GradedAnswer
  + describe() : String
}

class GradedAnswer {
  + isCorrect : Boolean
  + score : Double
  + explanation : String
  + correct(String) : GradedAnswer
  + incorrect(String) : GradedAnswer
  + partial(Double, String) : GradedAnswer
}

QuizAttemptController --> GradingStrategyResolver : asks for a strategy,\nnever checks the type
GradingStrategyResolver ..> GradingStrategy : returns one of
MCQGradingStrategy ..|> GradingStrategy
MultipleAnswerGradingStrategy ..|> GradingStrategy
TextMatchGradingStrategy ..|> GradingStrategy
GradingStrategy ..> GradedAnswer : returns

note bottom of MCQGradingStrategy
  Equality test:
  is the chosen id the correct id?
end note

note bottom of MultipleAnswerGradingStrategy
  Set comparison, with partial credit
  in proportion to how many were right.
end note

note bottom of TextMatchGradingStrategy
  String similarity, so a small
  spelling mistake still passes.
end note

note top of QuizAttemptController
  The words mcq, multi and text do not appear
  anywhere in this controller. It calls
  resolver->for($question) then $strategy->grade().

  The third strategy was added later and this
  class did not change at all.
end note

@enduml
```

---

# MEMBER 5, Ong Kwong Wei (Module 5)

## Figure 3.1, entity class diagram

```text
@startuml Member5_EntityClasses
title Module 5: Academic Progress Analytics and Evaluation\nEntity Class Diagram

skinparam classAttributeIconSize 0
skinparam shadowing false
hide circle

class Assignment {
  - id : Integer
  - title : String
  - description : Text
  - dueDate : DateTime
  - allowLateSubmission : Boolean
  + isOverdue() : Boolean
  + isClosed() : Boolean
  + wouldBeLate() : Boolean
  + latePolicyLabel() : String
}

class Submission {
  - id : Integer
  - filePath : String
  - state : String
  - submittedAt : DateTime
  + state() : SubmissionState
  + wasOnTime() : Boolean
}

class Grade {
  - id : Integer
  - calculatedScore : Double
  + letter() : String
  + gradePoint() : Float
  + isPass() : Boolean
  + display() : String
  + course() : Course
  + student() : User
}

class QuizAttempt <<Module 4>> {
  - durationSeconds : Integer
}

class Course <<Module 2>> {
  - code : String
  - title : String
}

class User <<Module 1>> {
  - name : String
}

Assignment "0..*" -- "1" Course : course >
Assignment "1" -- "0..*" Submission : submissions >

Submission "0..*" -- "1" User : student >
Submission "1" -- "0..1" Grade : grade >

Grade "0..1" -- "0..1" Submission : submission >
Grade "0..1" -- "0..1" QuizAttempt : quizAttempt >

QuizAttempt "0..*" -- "1" User : student >

note bottom of Grade
  Points at EITHER a Submission OR a QuizAttempt,
  never both, because a mark comes from exactly
  one piece of work.

  course() and student() follow whichever link
  exists, which is how Module 1 knows what to
  recalculate when a grade appears.
end note

note bottom of Submission
  Unique on (assignment, student):
  one submission per student per assignment.

  The state attribute is only the stored NAME.
  The behaviour lives in the state objects.
end note

note right of Assignment
  allowLateSubmission is a per assignment policy.
  Deadlines are never copied into the calendar;
  Module 2 adapts this dueDate instead.
end note

@enduml
```

## Figure 4.1, State class diagram

```text
@startuml Member5_StatePattern
title Module 5: State Pattern\nA submission behaves differently depending on where it is in its life

skinparam classAttributeIconSize 0
skinparam shadowing false
hide circle

class Submission <<Context>> {
  - state : String
  - filePath : String
  - submittedAt : DateTime
  - STATES : Array
  + state() : SubmissionState
  + wasOnTime() : Boolean
}

interface SubmissionState <<State>> {
  + name() : String
  + label() : String
  + canUpdateFile() : Boolean
  + canSubmit() : Boolean
  + canAssignGrade() : Boolean
  + updateFile(Submission, String) : void
  + submit(Submission) : void
  + assignGrade(Submission, Double) : Grade
}

class DraftState <<ConcreteState>> {
  + canUpdateFile() : true
  + canSubmit() : true
  + canAssignGrade() : false
  + updateFile(...) : void
  + submit(...) : void
  + assignGrade(...) : throws
}

class SubmittedState <<ConcreteState>> {
  + canUpdateFile() : false
  + canSubmit() : false
  + canAssignGrade() : true
  + updateFile(...) : throws
  + submit(...) : throws
  + assignGrade(...) : Grade
}

class GradedState <<ConcreteState>> {
  + canUpdateFile() : false
  + canSubmit() : false
  + canAssignGrade() : false
  + updateFile(...) : throws
  + submit(...) : throws
  + assignGrade(...) : throws
}

class IllegalSubmissionTransition {
  + because(String, SubmissionState) : self
}

class Grade {
  - calculatedScore : Double
}

Submission --> SubmissionState : holds one, chosen\nfrom the stored name
DraftState ..|> SubmissionState
SubmittedState ..|> SubmissionState
GradedState ..|> SubmissionState

DraftState -right-> SubmittedState : submit()
SubmittedState -right-> GradedState : assignGrade()

SubmissionState ..> IllegalSubmissionTransition : throws when forbidden
SubmittedState ..> Grade : creates

note bottom of GradedState
  Refuses ALL THREE operations.
  This is what protects the credentialing chain:
  a certificate is issued off the back of a grade,
  so editing graded work afterwards would leave a
  credential attesting to something the record
  no longer matches.
end note

note top of Submission
  The object moves ITSELF along, which is what
  separates State from Strategy. A submission that
  was a draft a moment ago is a submitted one now,
  because DraftState::submit() moved it.
end note

note right of SubmissionState
  An unknown value in the state column falls
  back to DraftState, so a hand edited database
  row can never leave a submission with no
  behaviour at all.
end note

@enduml
```

---

## Checklist

- [ ] Member 1: 3.1 entities, 4.1 Facade
- [ ] Member 2: 3.1 entities, 4.1 Adapter (both uses)
- [ ] Member 3: 3.1 entities, 4.1 Observer (seven subjects)
- [ ] Member 4: 3.1 entities, 4.1 Strategy
- [ ] Member 5: 3.1 entities, 4.1 State
- [ ] Each PNG pasted into the right report
- [ ] Each PNG uploaded to Google Drive and the link pasted in
- [ ] Member 1's report: remove `isRevoked()` and `isValid()` from `Certificate`
- [ ] Member 4's report: change `requiredAnswerCount()` to `requiredSelections()`
