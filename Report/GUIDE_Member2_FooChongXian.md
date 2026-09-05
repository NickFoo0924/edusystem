# Screenshot guide: MEMBER 2, Foo Chong Xian (Module 2)

Follow this top to bottom. It is in the order that needs the fewest logins.

**To take a screenshot:** press `Win` + `Shift` + `S`, drag a box, then paste into Word with
`Ctrl` + `V`.

You need **20 figures**. Two of them are drawings, the other 18 are screenshots.

---

## STEP 0. Get the site running (5 minutes, do this once)

1. Open **XAMPP Control Panel** and press **Start** next to **MySQL**.
   If MySQL is not running, nothing below will work.

2. Open a terminal in `c:\xampp\htdocs\edusystem` and run:

   ```
   php artisan serve
   ```

   Leave this window open the whole time. The site is at **http://127.0.0.1:8000**

3. Open a **second** terminal in the same folder for commands.

**Logins.** The password for every account is `password`

| Who | Email | You are |
|---|---|---|
| Lecturer (Malarvili) | `malarvili.nallayan@gmail.com` | Owner of `BMIT3173` |
| Student (you) | `foochongxian@gmail.com` | Enrolled in courses 1 to 5, **not** 6 |
| Another lecturer | `tinghiechoon@gmail.com` | Owner of `BMCS3404` |

**Numbers you will need**, already checked against your database:

- `BMIT3173` is **course 1**
- `BMCS3404` is **course 2**, owned by a **different** lecturer
- Foo is **not enrolled in course 6**
- The online meeting is **event 2**

---

## STEP 1. Figure 8.1, the test results (2 minutes)

In your **second terminal**:

```
php artisan test
```

Wait about fifteen seconds for:

```
Tests:    86 passed (200 assertions)
Duration: 12.11s
```

📸 **Figure 8.1** — screenshot the **bottom** of the terminal with the
`Tests: 86 passed (200 assertions)` line visible.

If you want to show your own module's tests as well, run this and screenshot it too:

```
php artisan test --filter=CourseMaterialUploadTest
```

That gives seven green ticks including *a php file is refused*, which supports your Section 5.

---

## STEP 2. Figures 5.1 and 5.2, the upload being refused (10 minutes)

**These are your two best security figures.** Do them carefully.

### Make a fake attack file first

1. Open **Notepad**
2. Type anything, for example `<?php echo "hello"; ?>`
3. **File**, **Save As**, go to your Desktop
4. Set **Save as type** to **All Files**
5. Name it exactly `shell.php` and save

### Figure 5.1, the .php file refused

6. Sign in as the **lecturer** `malarvili.nallayan@gmail.com`
7. Go to **Courses**, open **BMIT3173**
8. Press **Add material**
9. Fill in a title such as `Test upload`, pick any category
10. Choose the **upload a file** option and select `shell.php`
11. Press save

The form comes back with a red error saying the file type is not allowed.

📸 **Figure 5.1** — screenshot the form showing that error

### Figure 5.2, the disguised file still refused

12. On your Desktop, rename `shell.php` to `shell.pdf`
    (if Windows hides extensions: **View** menu, tick **File name extensions**)
13. Try the upload again with `shell.pdf`

**It is still refused**, because the system checks what the file really is rather than trusting
its name.

📸 **Figure 5.2** — screenshot it. **This is the stronger of the two figures**, so make sure the
error message is readable.

14. Delete `shell.php` / `shell.pdf` from your Desktop when you are done

---

## STEP 3. Figures 2.5 and 2.6, materials and the Adapter (5 minutes)

Still signed in as the **lecturer**.

### Figure 2.5

1. On **BMIT3173**, press **Add material**

📸 **Figure 2.5** — screenshot the form showing **both** options: upload a file, and link to an
external resource. The two options side by side is the point, because that is the mismatch your
Adapter absorbs.

### Now add a real file, because your course has only links at the moment

2. Fill in the title `Week 1 Lecture Notes`, category **Lecture notes**
3. Choose **upload a file** and pick any **PDF** you have (any PDF at all will do)
4. Save

### Figure 2.6

5. You are back on the course page. Scroll to the materials section

You now see your uploaded PDF sitting in the same list as the existing YouTube and MDN links.
The PDF shows its **size**, and the links show their **host name**.

📸 **Figure 2.6** — screenshot that list. **This is your key Adapter figure.** In the caption,
point out that a file and a link appear in one list with the same layout, and the template never
asks which is which.

---

## STEP 4. Figures 2.9 and 2.10, the calendar (5 minutes)

Still the **lecturer**.

1. Click **Calendar** in the left rail

📸 **Figure 2.9** — screenshot the month grid. Look for a **blue** entry (a class or meeting)
and an **amber** entry (an assignment deadline) on the grid. Both colours in one shot is the
figure you want, because the deadline is not stored as an event at all.

> If you cannot see both, use the arrows to move to **August 2026**, which has the most content.

2. Go to **http://127.0.0.1:8000/calendar/events/2**

📸 **Figure 2.10** — screenshot the event detail page showing the **Join meeting** button, the
times, the organiser and who it concerns.

> Optional extra worth having: open a **classroom** event instead, one with a room rather than a
> link. It has **no Join button at all**, which shows the button is absent rather than dead.

---

## STEP 5. Figures 2.2, 2.4 and 6.3, the lecturer's course view (5 minutes)

Still the **lecturer**, on **BMIT3173**.

📸 **Figure 2.2** — screenshot the top of the course page showing the **class code** panel and
the page title

📸 **Figure 2.4** — scroll to the **Students** roster panel. Screenshot it showing the invite
box and the **Remove** control beside each student

📸 **Figure 6.3** — scroll to the **Class performance** panel, which is green and headed
*Retrieved from Module 5 web service*. Screenshot it.

> That panel is Module 2 calling Module 5's `getCourseAnalytics` service over HTTP. It shows the
> class average, highest, lowest, pass count and grade spread, none of which Module 2 works out
> itself. It only appears when Module 5 answers, which is what makes it worth photographing.

---

## STEP 6. Figures 6.1 and 6.2, your own web service (5 minutes)

Your service needs a key in a header, so a browser cannot call it. Use your **second terminal**.

### Figure 6.1, the service working

Paste this in as one block:

```bash
KEY=$(grep '^INTERNAL_API_KEY=' .env | cut -d= -f2)
curl -s -H "X-API-Key: $KEY" "http://127.0.0.1:8000/api/courses/info?courseId=1&queryFlag=2&requestID=CRS-REQ-11223&timeStamp=2026-09-06T10:00:00Z" | python -m json.tool
```

You will see:

```json
{
    "status": "S",
    "timeStamp": "2026-09-06T...",
    "data": {
        "requestID": "CRS-REQ-11223",
        "courseCode": "BMIT3173",
        "courseTitle": "Integrative Programming",
        "studentCount": 33,
        "instructorName": "Malarvili A/P Nallayan",
        "instructorEmail": "malarvili@tarc.edu.my"
    }
}
```

📸 **Figure 6.1** — screenshot the terminal showing the command **and** the answer together

### Figure 6.2, the same call refused without a key

```bash
curl -i "http://127.0.0.1:8000/api/courses/info?courseId=1&queryFlag=1&requestID=X&timeStamp=2026-09-06T10:00:00Z"
```

The first line is `HTTP/1.1 401 Unauthorized`, and the body says a valid `X-API-Key` header is
required.

📸 **Figure 6.2** — screenshot it. This is a strong figure because it proves the security is
real rather than only described.

> **Prefer Postman?** New request, `GET`, URL `http://127.0.0.1:8000/api/courses/info`.
> Under **Params** add `courseId`=`1`, `queryFlag`=`2`, `requestID`=`CRS-REQ-11223`,
> `timeStamp`=`2026-09-06T10:00:00Z`. Under **Headers** add `X-API-Key` with the value from your
> `.env` file. Press **Send** and screenshot the window.

---

## STEP 7. Figure 5.4, one lecturer cannot edit another's course (2 minutes)

Still signed in as **Malarvili**, who owns course 1 but **not** course 2.

1. Go to **http://127.0.0.1:8000/courses/2/edit**

You get **403 Forbidden** with the message that this course belongs to another instructor.

📸 **Figure 5.4** — screenshot it **including the address bar**, so the URL that was typed is
visible

---

## STEP 8. Figures 2.1, 2.3, 2.11 and 5.3, as the STUDENT (8 minutes)

Sign out, then sign in as `foochongxian@gmail.com` / `password`

### Figure 5.3, the 403 from guessing a course

1. Go to **http://127.0.0.1:8000/courses/6**

Foo is **not enrolled** in course 6, so you get **403 Forbidden** saying you are not enrolled in
this course.

📸 **Figure 5.3** — screenshot it **including the address bar**

> To make the caption stronger, first visit `/courses/1`, which works, then change the `1` to a
> `6`. That shows it was one character in the URL.

### Figures 2.1 and 2.11

2. Go to **Courses**, open **BMIT3173**

📸 **Figure 2.1** — screenshot the course page as the student sees it. Note there is **no Leave
button**, which supports your Section 2.2

📸 **Figure 2.11** — screenshot the **Suggested study plan** panel showing the ordered steps with
ticks against the ones that are done

### Figure 2.3

3. Click the **+** in the top bar

📸 **Figure 2.3** — screenshot the join by class code page

---

## STEP 9. Figures 2.7 and 2.8, announcements (3 minutes)

Still the **student**.

1. Click **Announcements** in the left rail

📸 **Figure 2.7** — screenshot the list showing an announcement with its comment thread
**collapsed** behind a *View comments* link

2. Click the link to expand a thread that has replies

📸 **Figure 2.8** — screenshot the expanded thread. Look for a reply from the lecturer carrying
the **author** tag, which is what distinguishes an answer from a classmate's guess

> If no thread has comments yet, post one yourself first, then sign in as the lecturer and reply,
> then come back as the student.

---

## STEP 10. The two drawings (45 minutes)

These are **not** screenshots. Use **draw.io** at https://app.diagrams.net, free and no account
needed.

### Figure 3.1, the entity class diagram

Section 3.1 of your report lists every class with its attributes. Copy it into boxes.

- One box per class, three compartments: **name**, **attributes**, **methods**
- Lines between related classes, labelled at the ends with `1` or `0..*`
- **Do not draw an ERD.** No `course_id` columns. The line from `CourseMaterial` to `Course` is
  labelled `course`, because it is an object reference

Classes: `Course`, `CourseMaterial`, `CourseInvitation`, `Announcement`, `AnnouncementComment`,
`CourseEvent`

### Figure 4.1, the Adapter class diagram

Section 4.1 already has this drawn in text. Copy that layout. **Show both uses**, because using
the pattern twice on two different mismatched pairs is your strongest point:

**Left half, materials**
- `courses/show.blade.php` at the top as the client
- `«interface» DisplayableMaterial` below it, listing its six methods
- Two boxes implementing it: `FileResourceAdapter` and `ExternalResourceAdapter`
- Both pointing down to `CourseMaterial` as the adaptee

**Right half, calendar**
- `calendar/index.blade.php` at the top as the client
- `«interface» CalendarEntry` below it
- Two boxes implementing it: `ScheduledEventAdapter` and `AssignmentDeadlineAdapter`
- Pointing down to `CourseEvent` and `Assignment`
- Label `Assignment` with a note saying **Module 5 owns this one**

When both are done: **File**, **Export as**, **PNG**, then paste into Word. Also upload each PNG
to Google Drive, press **Share**, set **Anyone with the link**, and paste the link where your
report says `[paste your Google Drive link here]`.

---

## Your figure checklist

- [ ] 8.1 test results
- [ ] 5.1 `shell.php` refused
- [ ] 5.2 `shell.pdf` still refused *(the stronger one)*
- [ ] 2.5 add material form, both options visible
- [ ] 2.6 materials list with a PDF and links together *(key Adapter figure)*
- [ ] 2.9 calendar grid with a class and a deadline
- [ ] 2.10 event detail page with Join meeting
- [ ] 2.2 course page with class code
- [ ] 2.4 roster with invite box and Remove
- [ ] 6.3 green Class performance panel *(Module 5 service)*
- [ ] 6.1 `getCourseInfo` working
- [ ] 6.2 the same call getting 401 without a key
- [ ] 5.4 403 editing another lecturer's course
- [ ] 5.3 403 opening course 6 as a student
- [ ] 2.1 student course page, no Leave button
- [ ] 2.11 suggested study plan
- [ ] 2.3 join by class code page
- [ ] 2.7 collapsed comment thread
- [ ] 2.8 expanded thread with the author tag
- [ ] 3.1 entity class diagram *(drawn)*
- [ ] 4.1 Adapter class diagram, both uses *(drawn)*
- [ ] `shell.php` deleted from your Desktop
- [ ] The `Test upload` material deleted from BMIT3173 if you left one behind
