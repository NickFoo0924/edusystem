# Screenshot guide: MEMBER 5, Ong Kwong Wei (Module 5)

Follow this top to bottom. It is in the order that needs the fewest logins.

**To take a screenshot:** press `Win` + `Shift` + `S`, drag a box, then paste into Word with
`Ctrl` + `V`.

You need **17 figures**. Two of them are drawings, the other 15 are screenshots.

---

## STEP 0. Get the site running (5 minutes, do this once)

1. Open **XAMPP Control Panel** and press **Start** next to **MySQL**.

2. Open a terminal in `c:\xampp\htdocs\edusystem` and run:

   ```
   php artisan serve --port=8000
   ```

3. **Open a THIRD terminal** and run a **second** server:

   ```
   php artisan serve --port=8001
   ```

   > **You need both.** `php artisan serve` handles one request at a time, so a page that calls
   > the system's own web service cannot answer itself and would hang. The site runs on 8000 and
   > the web services answer on 8001, which is what `INTERNAL_API_BASE_URL` in your `.env` points
   > at. Without the second server your Figure 6.3 panel will not appear.

4. Open a **fourth** terminal for commands.

The site is at **http://127.0.0.1:8000**

**Logins.** The password for every account is `password`

| Who | Email |
|---|---|
| Lecturer (Malarvili) | `malarvili.nallayan@gmail.com` |
| Administrator | `learnsync.admin@gmail.com` |
| Student (Foo) | `foochongxian@gmail.com` |

**Numbers you will need**, already checked against your database:

- Assignments on `BMIT3173` are **1 and 2**
- A **draft** submission is **id 3**, a **submitted** one is **id 11**, a **graded** one is **id 1**
- Foo is **user 9**

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
php artisan test --filter=WebServiceTest
```

---

## STEP 2. Figure 5.1, no raw SQL anywhere (3 minutes)

**This is the easiest of your security figures and one of the strongest.**

In your **command terminal**:

```
grep -rn "DB::raw\|DB::select\|DB::statement\|DB::table\|whereRaw\|selectRaw\|havingRaw\|orderByRaw" app/
```

Exactly **one** line comes back:

```
app/Http/Controllers/EnrolmentController.php:69:  * whereRaw('lower(...)') here was in breach of it.
```

📸 **Figure 5.1** — screenshot the terminal showing the command and that single result

> The caption is what makes this figure land. Say: the only match in the entire application is
> **inside a comment**, recording that an earlier `whereRaw` was removed for exactly this reason.
> There is no raw SQL in any executable line, so every query is parameter bound by Eloquent.

To make it even stronger, open that file and show the comment in context:

```
sed -n '60,72p' app/Http/Controllers/EnrolmentController.php
```

📸 **Optional extra** — screenshot that, showing a case insensitive match being done by the
database collation rather than by reaching for raw SQL.

---

## STEP 3. Figure 5.4, the fillable allow lists (3 minutes)

In your **command terminal**:

```
grep -n "fillable" -A 5 app/Models/Grade.php app/Models/Submission.php
```

📸 **Figure 5.4** — screenshot the output showing both `$fillable` arrays

Then prove no model is unprotected:

```
grep -rn "guarded" app/Models/
```

Nothing comes back. 📸 **Optional extra** — screenshot that empty result and caption it as *no
model uses an empty `$guarded`, which would have disabled the protection entirely*.

---

## STEP 4. Figure 5.2, the query log showing bound parameters (5 minutes)

In your **command terminal**:

```
php artisan tinker
```

Then paste these lines one at a time:

```php
DB::enableQueryLog();
App\Models\Grade::whereIn('submission_id', App\Models\Submission::where('student_id', 9)->select('id'))->pluck('calculated_score');
print_r(DB::getQueryLog());
```

You will see the SQL with **question marks** where the values should be, and the values listed
separately underneath as `bindings`:

```
[sql] => select `calculated_score` from `grades` where `submission_id` in (select `id` from `submissions` where `student_id` = ?)
[bindings] => Array ( [0] => 9 )
```

📸 **Figure 5.2** — screenshot that output

> This is the whole SQL injection defence in one picture. In the caption: the `?` is a
> placeholder. The database compiles the statement's structure **before** it ever sees the value
> `9`, so a value such as `1 OR 1=1` would be searched for as a literal string and match nothing.
> This is stronger than escaping, because it never lets the value be code at all.

Type `exit` to leave tinker.

---

## STEP 5. Figure 5.3, mass assignment being ignored (8 minutes)

1. Sign in as the **student** `foochongxian@gmail.com`
2. Go to **Courses**, open **BMIT3173**, and open an assignment
3. Choose a file to upload but **do not submit yet**
4. Press **F12**, go to the **Elements** tab
5. Find the upload `<form>`, right click it, choose **Edit as HTML**
6. Just after the opening `<form ...>` tag, paste these extra fields:

   ```html
   <input type="hidden" name="state" value="graded">
   <input type="hidden" name="submitted_at" value="2020-01-01 09:00:00">
   <input type="hidden" name="calculated_score" value="100">
   ```

7. Click outside the box, then press the upload button

📸 **Figure 5.3a** — screenshot the developer tools showing the three injected fields

### Now show they were ignored

8. Open **http://localhost/phpmyadmin**, click the **`edusystem`** database
9. Open the **`submissions`** table, press **Browse**, and find the newest row
   (sort by `id` descending)

The row shows `state` as **draft**, not `graded`. `submitted_at` is **empty**, not 2020.

📸 **Figure 5.3b** — screenshot that database row

10. Also check the **`grades`** table. There is **no** new row with a score of 100

> Use 5.3a and 5.3b together as Figure 5.3. In the caption: the controller never passes the
> whole request to the model. It names two fields, both taken from the logged in session and the
> route rather than from the request body, so the extra fields simply had nowhere to go.

---

## STEP 6. Figures 2.2 and 2.3, the State pattern in the interface (6 minutes)

**These two figures are your design pattern demonstrated on screen.** They must be taken in
order, because the second one is only possible after the first.

Still signed in as the **student**, on the assignment page from Step 5.

### Figure 2.2, the draft state

Your upload from Step 5 is saved as a **draft**. The page shows the file, and a control to
**replace** it.

📸 **Figure 2.2** — screenshot the assignment page showing the replace file control

### Figure 2.3, the submitted state

1. Press **Submit for marking**

The page reloads and **the replace control is gone**. There is no way to change the file.

📸 **Figure 2.3** — screenshot the same page with the control absent

> This is the best caption in your report. Say: nothing in the controller checked a status
> column. The submission was asked whether the file could be replaced, and the answer changed
> because the object holding the answer changed from `DraftState` to `SubmittedState`.

---

## STEP 7. Figures 2.4 and 2.5, marking (5 minutes)

1. Sign out, sign in as the **lecturer** `malarvili.nallayan@gmail.com`

### Figure 2.4

2. Go to **Dashboard**

📸 **Figure 2.4** — screenshot the **review queue** showing work handed in and not yet marked.
The submission you just made in Step 6 should be in it.

### Figure 2.5

3. Click through to that submission

📸 **Figure 2.5** — screenshot the marking screen showing the student's file and the score box

4. **Enter a mark and press Grade.** Do this, because it is what triggers the whole credentialing
   chain and you may want to mention that in your presentation

---

## STEP 8. Figures 2.7, 2.8, 2.9 and 6.3, the analytics screen (8 minutes)

Still the **lecturer**. Click **Class analytics** in the left rail.

> Give the page a few seconds. It calls Module 1's web service once per credential it checks.

### Figure 2.7

📸 **Figure 2.7** — screenshot the top of the page showing the class average, highest, lowest,
pass count and the **grade distribution** bars

### Figure 6.3, consuming Module 1's service

Scroll down the same card to the row headed **Credentials confirmed**, labelled
*via Module 1 web service*, reading something like **5 of 5 checked valid, 12 issued in total**.

📸 **Figure 6.3** — screenshot that row

> That figure was not worked out by Module 5. Each credential was checked through Module 1's
> `getCredentialStatus` service, because whether a credential is live depends on revocation and
> an integrity hash Module 1 owns. Re-implementing that check here would mean two versions of a
> security rule. **Say that in the caption.**

> If the row is missing, your **second server on port 8001 is not running**. Go back to Step 0.

### Figure 2.8, the XSLT chart

Scroll to the **Completion trend** card. The chart there is an **SVG image produced by an XSLT
stylesheet**, not by any JavaScript charting library.

📸 **Figure 2.8** — screenshot the chart

### Figure 2.9, the same data as XML

Go to **http://127.0.0.1:8000/analytics/export.xml**

Your browser either shows the XML or downloads it. If it downloads, open the file in Notepad or
drag it into the browser.

You will see:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<analytics generated="2026-09-05T22:51:43+00:00">
  <course code="BMIT3084" title="Enterprise Networking" students="29">
    <point date="2026-06-08" average="50"/>
    ...
```

📸 **Figure 2.9** — screenshot the XML

> Worth saying in the caption: this is the **same document** the chart is drawn from. It is
> validated against `resources/xml/analytics.xsd` before the stylesheet runs, and it is served as
> a real data export rather than being a throwaway step on the way to a picture.

---

## STEP 9. Figures 6.1 and 6.2, your own web service (5 minutes)

Your service needs a key in a header, so a browser cannot call it. Use your **command terminal**.

### Figure 6.1

```bash
KEY=$(grep '^INTERNAL_API_KEY=' .env | cut -d= -f2)
curl -s -H "X-API-Key: $KEY" "http://127.0.0.1:8000/api/analytics/course?courseId=1&requestID=ANL-REQ-99001&timeStamp=2026-09-06T10:00:00Z" | python -m json.tool
```

You will see the class average, highest, lowest, pass count and the grade distribution.

📸 **Figure 6.1** — screenshot the terminal showing the command **and** the answer

> Strong caption point: **no student is named anywhere in that answer.** The service returns
> figures about a cohort and never about an individual, because a service that returned per
> student marks would let any key holder assemble somebody else's transcript. There is an
> automated test asserting exactly this, so the property cannot be broken later without a test
> failing.

### Figure 6.2

```bash
curl -i "http://127.0.0.1:8000/api/analytics/course?courseId=1&requestID=X&timeStamp=2026-09-06T10:00:00Z"
```

The first line is `HTTP/1.1 401 Unauthorized`.

📸 **Figure 6.2** — screenshot it

---

## STEP 10. Figures 2.1 and 2.6, the remaining two (4 minutes)

### Figure 2.1, the assignment form

Sign in as the **lecturer**, open **BMIT3173**, press **Add assignment**.

📸 **Figure 2.1** — screenshot the form, making sure the **late submission** option is visible.
That option is the per assignment policy your Section 2.2 describes.

### Figure 2.6, the grade scale

Sign in as the **student**. The letter grade legend appears where marks are shown, on the
**Dashboard** or on a graded assignment.

📸 **Figure 2.6** — screenshot the legend showing the letters against their mark ranges

> Caption point: the letter is worked out from the percentage every time it is displayed and is
> never stored, so a mark and its letter can never disagree.

---

## STEP 11. The two drawings (45 minutes)

Not screenshots. Use **draw.io** at https://app.diagrams.net, free and no account needed.

### Figure 3.1, the entity class diagram

Section 3.1 of your report lists the classes with their attributes. Copy them into boxes.

- One box per class, three compartments: **name**, **attributes**, **methods**
- Lines between related classes, labelled `1`, `0..1` or `0..*`
- **Do not draw an ERD.** No `assignment_id` columns. The line from `Submission` to `Assignment`
  is labelled `assignment`, because it is an object reference

Classes: `Assignment`, `Submission`, `Grade`, `QuizAttempt`

Two things worth marking:
- `Grade` points at **either** a `Submission` **or** a `QuizAttempt`, never both. Mark both links
  `0..1` and add a note saying **exactly one is set**
- Put a note beside `Submission.state` saying **only the stored name; the behaviour lives in the
  state objects**, which ties the diagram to your Section 4

### Figure 4.1, the State class diagram

Section 4.1 already has this drawn in text. Copy that layout:

- `Submission` at the top, labelled `«Context»`, showing `state: String` and `state(): SubmissionState`
- An arrow down labelled **holds one, chosen from the stored state name**
- `«interface» SubmissionState` listing its eight methods
- Three boxes implementing it side by side: `DraftState`, `SubmittedState`, `GradedState`
- Under each, its three permissions as a small table:
  - `DraftState`: update YES, submit YES, grade NO
  - `SubmittedState`: update NO, submit NO, grade YES
  - `GradedState`: update NO, submit NO, grade NO
- **Arrows between the three states**, left to right, labelled `submit()` and `assignGrade()`
- A note under the arrows: **the object moves itself along**, which is what separates State from
  Strategy
- A note beside the interface: **anything a state forbids throws IllegalSubmissionTransition**

When both are done: **File**, **Export as**, **PNG**, then paste into Word. Also upload each PNG
to Google Drive, press **Share**, set **Anyone with the link**, and paste the link where your
report says `[paste your Google Drive link here]`.

---

## Your figure checklist

- [ ] 8.1 test results
- [ ] 5.1 the raw SQL search returning only a comment
- [ ] 5.4 the `$fillable` allow lists
- [ ] 5.2 query log showing `?` placeholders and bindings
- [ ] 5.3a injected `state` and `calculated_score` fields
- [ ] 5.3b the database row showing they were ignored
- [ ] 2.2 draft submission with the replace control
- [ ] 2.3 submitted, with the control gone *(your State pattern figure)*
- [ ] 2.4 the lecturer's review queue
- [ ] 2.5 the marking screen
- [ ] 2.7 analytics with averages and distribution
- [ ] 6.3 Credentials confirmed row *(Module 1 service)*
- [ ] 2.8 the XSLT completion trend chart
- [ ] 2.9 `/analytics/export.xml`
- [ ] 6.1 `getCourseAnalytics` working
- [ ] 6.2 the same call getting 401 without a key
- [ ] 2.1 assignment form with the late option
- [ ] 2.6 the grade scale legend
- [ ] 3.1 entity class diagram *(drawn)*
- [ ] 4.1 State class diagram with the transitions *(drawn)*
- [ ] The test submission from Step 5 tidied up if you want the demo data clean
