# Screenshot guide: MEMBER 4, Wong Siew Lam (Module 4)

Follow this top to bottom. It is in the order that needs the fewest logins.

**To take a screenshot:** press `Win` + `Shift` + `S`, drag a box, then paste into Word with
`Ctrl` + `V`.

You need **15 figures**. Two of them are drawings, the other 13 are screenshots.

---

## STEP 0. Get the site running (5 minutes, do this once)

1. Open **XAMPP Control Panel** and press **Start** next to **MySQL**.

2. Open a terminal in `c:\xampp\htdocs\edusystem` and run:

   ```
   php artisan serve
   ```

   Leave this window open. The site is at **http://127.0.0.1:8000**

3. **Open a THIRD terminal** and run a **second** server:

   ```
   php artisan serve --port=8001
   ```

   > **You need both.** `php artisan serve` handles one request at a time, so a page that calls
   > the system's own web service cannot answer itself and would hang for ten seconds and then
   > show nothing. The site runs on 8000 and the web services answer on 8001, which is what
   > `INTERNAL_API_BASE_URL` in your `.env` points at. **Without the second server your service
   > panel will not appear.**

4. Open a **fourth** terminal for commands.

**Logins.** The password for every account is `password`

| Who | Email |
|---|---|
| Lecturer (Malarvili) | `malarvili.nallayan@gmail.com` |
| Student (Foo) | `foochongxian@gmail.com` |

**Numbers you will need**, already checked against your database:

- **Quiz 1**, *Web Services and Integration Basics*, on `BMIT3173`. **This is the only quiz with
  all three question types**, so use it for everything
- Foo's own attempt is **attempt 118**
- **Attempt 1** belongs to a different student, which you need for the 403

---

## STEP 1. Figure 8.1, the test results (2 minutes)

In your **command terminal**:

```
php artisan test
```

📸 **Figure 8.1** — screenshot the bottom of the terminal with
`Tests: 86 passed (200 assertions)` visible.

Optional extra, your own module's tests:

```
php artisan test --filter=SubjectExpertBadgeTest
```

---

## STEP 2. Figures 6.1 and 6.2, your own web service (5 minutes)

Your service needs a key in a header, so a browser cannot call it. Use your **command terminal**.

### Figure 6.1, the service working

Paste this in as one block:

```bash
KEY=$(grep '^INTERNAL_API_KEY=' .env | cut -d= -f2)
curl -s -H "X-API-Key: $KEY" "http://127.0.0.1:8000/api/quizzes/result?quizId=1&studentId=17&requestID=QUZ-REQ-55667&timeStamp=2026-09-06T10:00:00Z" | python -m json.tool
```

You will see:

```json
{
    "status": "S",
    "timeStamp": "2026-09-06T...",
    "data": {
        "requestID": "QUZ-REQ-55667",
        "attempted": true,
        "graded": true,
        "attemptCount": 1,
        "bestScore": 83.33,
        "letterGrade": "A",
        "passed": true
    }
}
```

📸 **Figure 6.1** — screenshot the terminal showing the command **and** the answer together

> Worth putting in the caption: the service returns a score and a letter, and **never** the
> questions, the student's answers, or the answer key. Learning how somebody did is a different
> thing from reading their paper, and only the first is offered.

### Figure 6.2, refused without a key

```bash
curl -i "http://127.0.0.1:8000/api/quizzes/result?quizId=1&studentId=17&requestID=X&timeStamp=2026-09-06T10:00:00Z"
```

The first line is `HTTP/1.1 401 Unauthorized`.

📸 **Figure 6.2** — screenshot it

### Worth adding, a student who never sat the quiz

```bash
KEY=$(grep '^INTERNAL_API_KEY=' .env | cut -d= -f2)
curl -s -H "X-API-Key: $KEY" "http://127.0.0.1:8000/api/quizzes/result?quizId=1&studentId=99999&requestID=QUZ-REQ-55668&timeStamp=2026-09-06T10:00:00Z" | python -m json.tool
```

It comes back `"status": "S"` with `"attempted": false`. Screenshot it as an extra, and explain
in the caption that not having sat a quiz is a **successful answer to a fair question**, not an
error, which is why the status is S rather than F.

---

## STEP 3. Figures 2.1, 2.2, 2.3 and 6.3, as the LECTURER (8 minutes)

Sign in as `malarvili.nallayan@gmail.com` / `password`

1. Go to **Courses**, open **BMIT3173**, and click the quiz
   *Web Services and Integration Basics* (or go straight to
   **http://127.0.0.1:8000/quizzes/1**)

### Figure 6.3, consuming Module 2's service

At the top of the page, under the quiz title, there is a **blue strip** headed
**"From Module 2 web service"** showing the course code, title, lecturer and enrolled count.

📸 **Figure 6.3** — screenshot the top of the page with that blue strip visible

> That label was fetched over HTTP from Module 2's `getCourseInfo` service. Module 4 does not
> read Module 2's tables for it. Say that in the caption.

### Figure 2.2

📸 **Figure 2.2** — scroll down and screenshot the list of questions with their answer options.
Note that the options flagged correct are marked here, **because you are the lecturer**. A
student never sees this, which is exactly the point of Figure 5.1 later.

### Figures 2.1 and 2.3

2. Scroll to the **Add question** form at the bottom
3. Click the **question type** dropdown to open it

📸 **Figure 2.1** — screenshot the add question form with the dropdown open
📸 **Figure 2.3** — the same shot works, showing the three options:
*Multiple choice (one answer)*, *Multiple choice (several answers)*, *Fill in the blank*

> These three are your three Strategy classes. In the caption, name them:
> `MCQGradingStrategy`, `MultipleAnswerGradingStrategy`, `TextMatchGradingStrategy`. The dropdown
> is built from `GradingStrategyResolver::availableTypes()`, so a type cannot appear in it
> without a strategy existing to mark it.

---

## STEP 4. Figures 5.1 and 2.4, the quiz paper (8 minutes)

**Figure 5.1 is your most important security figure.**

1. Sign out, then sign in as the **student** `foochongxian@gmail.com` / `password`
2. Go to **http://127.0.0.1:8000/quizzes/1** and press **Start quiz**

### Figure 2.4, the live counter

Scroll to the **several answers** question. It says how many options to select, and a counter
tracks how many you have ticked. Once you reach the limit, the remaining boxes stop responding.

3. Tick options until you hit the limit, so the counter is visible

📸 **Figure 2.4** — screenshot that question showing the instruction and the live counter

### Figure 5.1, the answers are not in the page

4. Press **F12** to open developer tools
5. Choose the **Elements** tab (Chrome) or **Inspector** (Firefox)
6. Find the answer options in the HTML. Expand one of the `<label>` elements

You will see each option is just:

```html
<input type="radio" name="responses[3]" value="17">
<span>Representational State Transfer</span>
```

**A number and some text. Nothing marks which one is correct.** Search the page with `Ctrl` + `F`
inside developer tools for `is_correct` or `correct` and you will find nothing.

📸 **Figure 5.1** — screenshot the developer tools panel showing the answer options in the HTML

> This is the figure that proves your Section 5.2. In the caption say: the browser receives the
> same information a printed exam paper gives, which is the question and the choices. The answer
> key stays in the `answers.is_correct` column on the server and is never sent to a student.

---

## STEP 5. Figure 5.2, a crafted request being ignored (6 minutes)

Still on the quiz paper with developer tools open.

1. In the **Elements** tab, find the `<form>` that holds the questions
2. **Right click** the form tag, choose **Edit as HTML**
3. Just after the opening `<form ...>` tag, paste this extra field:

   ```html
   <input type="hidden" name="score" value="100">
   <input type="hidden" name="calculated_score" value="100">
   ```

4. Click outside the box to apply it
5. Now answer the questions **deliberately wrongly**, or leave them blank
6. Press **Submit answers**

The result page shows the mark you actually earned, which will be low or zero. **It is not 100.**

📸 **Figure 5.2** — screenshot two things side by side if you can: the injected fields in
developer tools, and the result page showing the real mark

> In the caption: the request contained a `score` field, and the server never reads one. The
> percentage is worked out from the answer key by the Strategy classes, so there is no number in
> the request for an attacker to change. The worst a crafted request can do is submit answers
> that could have been submitted through the form anyway.

---

## STEP 6. Figures 2.5 and 2.6, the result and the review (3 minutes)

You are on the result page from Step 5.

📸 **Figure 2.5** — screenshot the result page showing the score

7. The same page lists each question with what you answered and whether it was right

📸 **Figure 2.6** — screenshot the question by question review

> Worth noting in the caption: this is only possible because `quiz_attempt_answers` records what
> the student actually answered. Without that table an attempt could be scored but never
> reviewed.

> If you would rather use a cleaner attempt than your deliberately wrong one, Foo's earlier
> attempt is at **http://127.0.0.1:8000/attempts/118**

---

## STEP 7. Figure 2.7, a student cannot read another student's paper (2 minutes)

Still signed in as **Foo**.

1. Go to **http://127.0.0.1:8000/attempts/1**

Attempt 1 belongs to a **different student**, so you get **403 Forbidden**.

📸 **Figure 2.7** — screenshot it **including the address bar**

> To make the caption stronger, first open `/attempts/118`, which is Foo's own and works, then
> change the number to `1`. That shows the whole attack was one character in the URL, and the
> check is on who owns the attempt rather than on being logged in.

---

## STEP 8. Figures 5.3 and 5.4, session management (6 minutes)

### Figure 5.3, the cookie cannot be read by JavaScript

1. Still signed in, press **F12**
2. Go to the **Application** tab (Chrome) or **Storage** tab (Firefox)
3. On the left, expand **Cookies** and click **http://127.0.0.1:8000**
4. Find the row named **`laravel_session`**
5. Look across to the **HttpOnly** column. It has a **tick**

📸 **Figure 5.3** — screenshot the cookie row with the HttpOnly tick clearly visible

> In the caption: HttpOnly means `document.cookie` cannot read this value. If a cross site
> scripting flaw ever appeared anywhere in the application, it still could not steal the session.
> Also point at the **SameSite** column, which says `Lax`, meaning the browser will not attach
> this cookie to requests coming from another website.

### Figure 5.4, sessions live on the server

6. Open **http://localhost/phpmyadmin**
7. Click the **`edusystem`** database on the left
8. Click the **`sessions`** table
9. Press the **Browse** tab

You see one row per active session, with an `id`, a `user_id`, an `ip_address`, a `user_agent`
and a `payload`.

📸 **Figure 5.4** — screenshot the table contents

> In the caption: the cookie in the browser holds only the random `id` from this table. Nothing
> about the user travels in the cookie, so there is nothing in it worth stealing except the
> pointer, and the pointer is protected by HttpOnly.

---

## STEP 9. The two drawings (45 minutes)

Not screenshots. Use **draw.io** at https://app.diagrams.net, free and no account needed.

### Figure 3.1, the entity class diagram

Section 3.1 of your report lists the classes with their attributes. Copy them into boxes.

- One box per class, three compartments: **name**, **attributes**, **methods**
- Lines between related classes, labelled `1`, `1..*` or `0..*`
- **Do not draw an ERD.** No `quiz_id` columns. The line from `Question` to `Quiz` is labelled
  `quiz`, because it is an object reference

Classes: `Quiz`, `Question`, `Answer`, `QuizAttempt`, `QuizAttemptAnswer`

Two things worth marking on the diagram:
- `Question` to `Answer` is `1..*`, because a question with no options is unanswerable
- Put a note beside `Answer.isCorrect` saying **never sent to a student's browser**, which ties
  the diagram to your Section 5

### Figure 4.1, the Strategy class diagram

Section 4.1 already has this drawn in text. Copy that layout:

- `QuizAttemptController` at the top, labelled `«Context»`
- An arrow down to `GradingStrategyResolver`, with the arrow labelled
  **asks for a strategy, never checks the type**
- Below that, `«interface» GradingStrategy` listing `grade()` and `describe()`
- Three boxes implementing it, side by side:
  `MCQGradingStrategy`, `MultipleAnswerGradingStrategy`, `TextMatchGradingStrategy`
- Under each, a one line note on what makes it different:
  *equality test*, *set comparison with partial credit*, *string similarity*
- All three pointing to one box at the bottom, `GradedAnswer`, with
  `isCorrect`, `score` and `explanation`

Add a caption line under the diagram: *the words mcq, multi and text do not appear anywhere in
the controller.*

When both are done: **File**, **Export as**, **PNG**, then paste into Word. Also upload each PNG
to Google Drive, press **Share**, set **Anyone with the link**, and paste the link where your
report says `[paste your Google Drive link here]`.

---

## Your figure checklist

- [ ] 8.1 test results
- [ ] 6.1 `getQuizResult` working
- [ ] 6.2 the same call getting 401 without a key
- [ ] Optional: a student who never sat the quiz, returning `attempted: false`
- [ ] 6.3 blue "From Module 2 web service" strip on the quiz page
- [ ] 2.2 questions with their answer options, as the lecturer
- [ ] 2.1 and 2.3 add question form with the type dropdown open *(same shot)*
- [ ] 2.4 several answers question with its live counter
- [ ] 5.1 developer tools showing no correct answer flag *(your key security figure)*
- [ ] 5.2 injected `score` field ignored, real mark shown
- [ ] 2.5 the result page
- [ ] 2.6 question by question review
- [ ] 2.7 403 opening another student's attempt
- [ ] 5.3 `laravel_session` cookie with HttpOnly ticked
- [ ] 5.4 the `sessions` table in phpMyAdmin
- [ ] 3.1 entity class diagram *(drawn)*
- [ ] 4.1 Strategy class diagram *(drawn)*
