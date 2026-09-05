# How to capture every figure in the five reports

Work through this once and you will have every screenshot all five reports need.

**Screenshot tool on Windows:** press `Win` + `Shift` + `S`, drag a box, then paste
straight into Word with `Ctrl` + `V`. Nothing else needs installing.

---

## 0. Before you start

Open **two** terminals in `c:\xampp\htdocs\edusystem` and leave both open.

**Terminal 1** runs the site. Leave this running the whole time:

```bash
php artisan serve
```

The site is then at **http://127.0.0.1:8000**

**Terminal 2** is for commands. You will use it for the test screenshot.

Make sure **MySQL is started in the XAMPP control panel**, or nothing will load.

### Logins

Every account uses the password `password`.

| Role | Email | Use it for |
|---|---|---|
| Administrator | `learnsync.admin@gmail.com` | Permissions, accounts, invitations, credentials, award rules |
| Lecturer | `malarvili@tarc.edu.my` — sign in with `malarvili.nallayan@gmail.com` | Course owner screens, marking, analytics, forums |
| Student | `foochongxian@gmail.com` | Student views, quizzes, submissions, trophy cabinet |
| Second student | `serenalim@gmail.com` | For the "another student" 403 screenshots |

`BMIT3173 Integrative Programming` is the demo course with the most content in it.

---

## 1. Appendix A, the `php artisan test` screenshot

**This is the easiest one, so do it first.**

In **Terminal 2**, run:

```bash
php artisan test
```

Wait about fifteen seconds. You will see a long list of green ticks ending with:

```
Tests:    86 passed (200 assertions)
Duration: 13.80s
```

**Screenshot the bottom of the terminal**, making sure the final `Tests: 86 passed` line is
visible. That is `Figure 8.1` in every report.

### If the whole list will not fit on one screen

The list is about 90 lines, so it will not fit in one window. Two options:

**Option A, just show the summary.** Scroll to the bottom and capture the last 15 or so lines
plus the `Tests: 86 passed` total. This is enough, and it is what most reports do.

**Option B, capture the full list.** Run this instead, which writes the output to a file:

```bash
php artisan test > test-results.txt
```

Then open `test-results.txt` in Notepad, and take two or three screenshots scrolling down.
Delete the file afterwards so it does not end up in the repository.

### If you want only your own module's tests

Each member can show just their own, which is shorter and more relevant:

```bash
# Member 1, Serena
php artisan test --filter=AwardAndActivityNotificationTest

# Member 2, Foo Chong Xian
php artisan test --filter=CourseMaterialUploadTest
php artisan test --filter=CourseEnrolmentTest
php artisan test --filter=CalendarEventDetailTest

# Member 3, Ong Shun Yan
php artisan test --filter=AwardAndActivityNotificationTest

# Member 4, Wong Siew Lam
php artisan test --filter=SubjectExpertBadgeTest

# Member 5, Ong Kwong Wei
php artisan test --filter=WebServiceTest
```

Include **both**: one screenshot of your own module's tests, and one of the full
`Tests: 86 passed` total.

---

## 2. Web service screenshots (Figures 6.1 to 6.3 in every report)

You do **not** need Postman. A browser works for the four GET services, and the answer is
already formatted nicely in Chrome and Edge.

**Your API key is:**

```
learnsync-WE8NaNORj0OhHAvrEDaGJ8yEpP90JOTF
```

> If that key does not work, open `.env`, find the `INTERNAL_API_KEY=` line, and use the value
> there instead.

### 2a. The one service that needs no key at all (Member 1)

Paste this straight into your browser address bar:

```
http://127.0.0.1:8000/api/credentials/verify?credentialId=LS-2026-XTEG2CDW&detailFlag=2&requestID=REQ-CRED-84920&timeStamp=2026-09-06T10:00:00Z
```

You will see the JSON answer with `"status": "S"` and `"credentialStatus": "VALID"`.
**Screenshot the browser window including the address bar**, so the marker can see the URL and
the answer together.

### 2b. The four services that need the key

A browser cannot send a header, so use **Postman** for these, or **curl** in Terminal 2.

**Using curl** is quicker. Paste one of these into Terminal 2:

```bash
KEY=$(grep '^INTERNAL_API_KEY=' .env | cut -d= -f2)
TS=$(date -u +%Y-%m-%dT%H:%M:%SZ)

# Member 2 exposes this
curl -s -H "X-API-Key: $KEY" "http://127.0.0.1:8000/api/courses/info?courseId=1&queryFlag=2&requestID=CRS-REQ-11223&timeStamp=$TS" | python -m json.tool

# Member 4 exposes this
curl -s -H "X-API-Key: $KEY" "http://127.0.0.1:8000/api/quizzes/result?quizId=5&studentId=13&requestID=QUZ-REQ-55667&timeStamp=$TS" | python -m json.tool

# Member 5 exposes this
curl -s -H "X-API-Key: $KEY" "http://127.0.0.1:8000/api/analytics/course?courseId=1&requestID=ANL-REQ-99001&timeStamp=$TS" | python -m json.tool

# Member 3 exposes this (a POST)
curl -s -X POST -H "X-API-Key: $KEY" -H "Content-Type: application/json" \
  -d "{\"userId\":8,\"type\":\"grade.recorded\",\"message\":\"Your quiz has been marked.\",\"link\":\"http://127.0.0.1:8000/dashboard\",\"reference\":\"demo:1\",\"requestID\":\"NTF-REQ-42042\",\"timeStamp\":\"$TS\"}" \
  http://127.0.0.1:8000/api/notifications/send | python -m json.tool
```

**Screenshot the terminal showing the command and the JSON answer together.**

**Using Postman** instead, if you prefer it for the report:

1. New request, method `GET`
2. URL: `http://127.0.0.1:8000/api/courses/info`
3. **Params** tab, add: `courseId` = `1`, `queryFlag` = `2`, `requestID` = `CRS-REQ-11223`,
   `timeStamp` = `2026-09-06T10:00:00Z`
4. **Headers** tab, add: `X-API-Key` = the key above
5. Press **Send**, then screenshot the whole Postman window

### 2c. The 401 screenshot, showing the key is really required

Run the same call with **no key**:

```bash
curl -i "http://127.0.0.1:8000/api/courses/info?courseId=1&queryFlag=1&requestID=X&timeStamp=2026-09-06T10:00:00Z"
```

You will get `HTTP/1.1 401 Unauthorized` and a JSON body saying a valid `X-API-Key` header is
required. **Screenshot it.** This is a strong figure because it proves the security is real
rather than described.

---

## 3. The special demonstration screenshots

These are the figures that prove a security claim, so they are worth doing carefully.

### 3a. TAMPERED (Members 1 and 2 need this)

This is the single best figure in the whole project.

1. Open `http://127.0.0.1:8000/verify/LS-2026-XTEG2CDW` in a **private or incognito window**,
   so you are clearly logged out. It says **VALID**. **Screenshot it.**
2. Open **phpMyAdmin** at `http://localhost/phpmyadmin`, choose the `edusystem` database, open
   the `certificates` table, find the row where `credential_id` is `LS-2026-XTEG2CDW`, and
   change `final_score` from `88` to `99`. Save.
3. Reload the verification page. It now says **TAMPERED**. **Screenshot it.**
4. **Change the score back to `88` in phpMyAdmin** so your demo data is correct again.

### 3b. A PHP file being refused on upload (Member 2)

1. On your desktop, make a file called `shell.php` with any text inside it
2. Sign in as the **lecturer**, open `BMIT3173`, press **Add material**
3. Choose a category, pick the upload option, select `shell.php`, and save
4. The form comes back with the error that the file type is not allowed. **Screenshot it.**
5. Rename the file to `shell.pdf` and try again. **It is still refused**, because the real
   content type is checked rather than the name. **Screenshot that too**, it is the better figure.

### 3c. A script tag displayed as text, not run (Member 3)

1. Sign in as a **student**, open `BMIT3173`, then **Discussion forum**
2. Post this as a question:
   ```
   Does anyone understand this? <script>alert('XSS')</script>
   ```
3. The post appears with the script tag **visible as ordinary text**. No alert box appears.
   **Screenshot it.**
4. Right click the page, choose **View page source**, and find your post. You will see
   `&lt;script&gt;` instead of `<script>`. **Screenshot that**, it is the stronger figure.
5. Delete the post afterwards to tidy up.

### 3d. Correct answers not being sent to the browser (Member 4)

1. Sign in as a **student**, open `BMIT3173`, open the quiz, press **Start quiz**
2. Press **F12** to open developer tools, choose the **Elements** or **Inspector** tab
3. Find the answer options in the HTML. Each one is an `<input>` with a `value` that is just a
   number, and the option text beside it
4. **Screenshot it**, showing there is no `is_correct`, no `correct="true"` and nothing marking
   which option is right

### 3e. No raw SQL anywhere (Member 5)

In Terminal 2:

```bash
grep -rn "DB::raw\|DB::select\|DB::statement\|DB::table\|whereRaw\|selectRaw" app/
```

The only line that comes back is a **comment** in `EnrolmentController.php` explaining that an
earlier `whereRaw` was removed. **Screenshot it**, and point out in the caption that the single
hit is a comment rather than executable code.

### 3f. A 403 from editing the URL (Member 2, and useful for others)

1. Sign in as **Foo Chong Xian**, and open a course he **is** enrolled in. Note the number in
   the address bar, for example `/courses/1`
2. Change the number to a course he is **not** enrolled in, for example `/courses/4`
3. You get **403 Forbidden** with the message that you are not enrolled in this course.
   **Screenshot it including the address bar.**

The same trick works for the calendar: `/calendar/events/12` for an event belonging to another
course gives 403 as well.

### 3g. A student cannot leave a course (Member 2)

Show that the Leave button does not exist:

1. Sign in as a **student**, open a course, and **screenshot the button row** at the top. There
   is an **Enrol** or **Discussion forum** button but no **Leave**
2. Then sign in as the **lecturer**, open the same course, and screenshot the **roster panel**
   showing the **Remove** control next to each student

---

## 4. The ordinary interface screenshots

These are the bulk of Section 2 in each report. Sign in once per role and take them all in one
pass.

### Signed in as the ADMINISTRATOR

| Figure | Where to go |
|---|---|
| Invitations screen | **Invitations** in the left rail, then **Invite** |
| Permission matrix | **Permissions** in the left rail |
| Accounts screen | **Accounts** in the left rail |
| Password confirmation page | **Accounts**, then click any action such as Deactivate |
| Activity log with CSV export | **Activity log** in the left rail |
| Award rules screen | **Badge rules** in the left rail |
| Certificate templates | **Certificate templates** in the left rail |
| Credentials register | **Credentials** in the left rail |
| Learning paths | **Learning paths** in the left rail |
| System settings | **System settings** in the left rail |

### Signed in as the LECTURER (Malarvili)

| Figure | Where to go |
|---|---|
| Course page with class code and roster | **Courses**, then `BMIT3173` |
| Add material form | On the course page, **Add material** |
| Materials list with a file and a link side by side | The course page, materials section |
| Announcements with a comment thread | **Announcements** in the left rail |
| Calendar month grid | **Calendar** in the left rail |
| Event detail page with Join meeting | **Calendar**, click any online meeting |
| Quiz builder | Course page, then the quiz, then **Add question** |
| Assignment form | Course page, **Add assignment** |
| Review queue | **Dashboard** |
| Marking screen | Open an assignment, then a submission |
| Analytics with averages and distribution | **Class analytics** in the left rail |
| Completion trend chart, drawn as SVG | The same analytics page, scroll down |

### Signed in as a STUDENT (Foo Chong Xian)

| Figure | Where to go |
|---|---|
| Course page with the study plan | **Courses**, then `BMIT3173` |
| Join by class code page | The **+** in the top bar |
| Discussion forum | Course page, **Discussion forum** |
| Notification bell with unread count | Top bar, click the bell |
| Notification settings | Click the bell, then **Preferences** |
| Trophy cabinet, earned and locked badges | **Trophy cabinet** in the left rail |
| My certificates | **My certificates** in the left rail |
| Certificate PDF with QR code | **My certificates**, then **Download** |
| Progress chart | **Dashboard** |
| Quiz paper with the live counter | Course page, open the quiz, **Start quiz** |
| Result page after submitting | Submit the quiz |
| Draft submission with the replace control | Open an assignment, upload a file |
| Submitted, with the control gone | Press **Submit for marking** |

### Signed in as the SECOND STUDENT (Serena)

| Figure | Where to go |
|---|---|
| 403 opening another student's quiz attempt | Take Foo's attempt URL, `/attempts/{id}`, and open it as Serena |

---

## 5. Putting figures into Word

1. Paste the screenshot with `Ctrl` + `V`
2. Click the image, then **Picture Format**, and set the width to about 15 cm so it fits the page
3. Click below the image and type the caption, for example
   `Figure 2.1: Admin Invitation Screen Showing the Sign Up Link`
4. Select the caption, set it to **italic**, size 10, and **centre** it

Every figure caption is already written in your report. Search the document for `Figure` and
replace each placeholder line with the real image plus that caption.

---

## 6. A quick checklist

- [ ] MySQL started in XAMPP
- [ ] `php artisan serve` running in Terminal 1
- [ ] Appendix A test screenshot taken
- [ ] Your own module's service called and screenshotted
- [ ] The 401 no-key screenshot taken
- [ ] Your module's special security demonstration taken
- [ ] All Section 2 interface screenshots taken
- [ ] `final_score` put back to `88` if you did the TAMPERED demo
- [ ] Test posts and probe uploads deleted
