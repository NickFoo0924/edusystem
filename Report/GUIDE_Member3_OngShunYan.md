# Screenshot guide: MEMBER 3, Ong Shun Yan (Module 3)

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
| Student | `foochongxian@gmail.com` |
| Lecturer (Malarvili) | `malarvili.nallayan@gmail.com` |
| Administrator | `learnsync.admin@gmail.com` |

**Numbers you will need**, already checked against your database:

- The `BMIT3173` forum is **forum 1**, and it already has 22 posts
- For the quiz service: **quiz 1**, **student 17**

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
php artisan test --filter=AwardAndActivityNotificationTest
```

That gives seven green ticks including *earning a certificate notifies the holder*, which
supports your Section 4.

---

## STEP 2. Figure 6.3, consuming Module 4's service (3 minutes)

**Do this before the others**, because it creates a notification you will need in Step 3.

In your **command terminal**, with both servers still running:

```
php artisan notify:quiz-result 1 17
```

You will see:

```
Asking Module 4 for the result of quiz 1 for student 17...
Module 4 answered: {
    "requestID": "QUZ-REQ-...",
    "attempted": true,
    "graded": true,
    "attemptCount": 1,
    "bestScore": 83.33,
    "letterGrade": "A",
    "passed": true
}
Notification sent to student5.
```

📸 **Figure 6.3** — screenshot the whole terminal output.

> This is Module 3 calling Module 4's `getQuizResult` service over HTTP. Notice Module 3 never
> works out the score or the letter grade. It asks Module 4 and uses the answer. That is the
> point of the figure, so say so in the caption.

> If it says **Suppressed**, that student has already been told. Use different numbers, for
> example `php artisan notify:quiz-result 5 13`

---

## STEP 3. Figures 6.1 and 6.2, your own web service (6 minutes)

Your service is a **POST** and needs a key in a header, so a browser cannot call it. Use your
**command terminal**.

### Figure 6.1, sending a notification

Paste this in as one block:

```bash
KEY=$(grep '^INTERNAL_API_KEY=' .env | cut -d= -f2)
curl -s -X POST -H "X-API-Key: $KEY" -H "Content-Type: application/json" \
  -d '{"userId":9,"type":"grade.recorded","message":"Your coursework has been marked.","link":"http://127.0.0.1:8000/dashboard","reference":"guide-demo-1","requestID":"NTF-REQ-42042","timeStamp":"2026-09-06T10:00:00Z"}' \
  http://127.0.0.1:8000/api/notifications/send | python -m json.tool
```

You will see:

```json
{
    "status": "S",
    "timeStamp": "2026-09-06T...",
    "data": {
        "requestID": "NTF-REQ-42042",
        "delivered": true,
        "reason": "Notification written to the inbox."
    }
}
```

📸 **Figure 6.1** — screenshot the terminal showing the command **and** the answer

### Figure 6.2, the notification actually arriving

1. Sign in at `http://127.0.0.1:8000/login` as `foochongxian@gmail.com` / `password`
2. Look at the **bell** in the top bar. It has a red unread count
3. Click it

📸 **Figure 6.2** — screenshot the dropdown showing *"Your coursework has been marked."*, the
message you just sent over HTTP a moment ago

> Pairing 6.1 and 6.2 is what makes this convincing: the request going in, and the result
> appearing in a real person's inbox.

### Worth adding, the allow list refusing a made up type

```bash
KEY=$(grep '^INTERNAL_API_KEY=' .env | cut -d= -f2)
curl -s -X POST -H "X-API-Key: $KEY" -H "Content-Type: application/json" \
  -d '{"userId":9,"type":"anything.i.like","message":"A type nobody can switch off.","requestID":"NTF-REQ-42043","timeStamp":"2026-09-06T10:00:00Z"}' \
  http://127.0.0.1:8000/api/notifications/send | python -m json.tool
```

It comes back with `"status": "F"`. Screenshot it as an extra figure in Section 6.1, because it
shows a caller cannot invent a notification type that bypasses the user's settings.

---

## STEP 4. Figures 5.1 and 5.2, the XSS demonstration (8 minutes)

**These are your two best security figures.**

Still signed in as the **student**.

1. Go to **Courses**, open **BMIT3173**, then click **Discussion forum**
2. In the question box, type exactly this:

   ```
   Does anyone understand this topic? <script>alert('XSS')</script>
   ```

3. Press **Post question**

**No alert box appears.** The post shows up with the script tag sitting there as ordinary,
harmless text.

📸 **Figure 5.1** — screenshot the forum showing your post with the visible `<script>` text

### Now show why it is safe

4. Press **Ctrl** + **U** to open the page source (or right click, **View page source**)
5. Press **Ctrl** + **F** and search for `alert`
6. You will find your post rendered as:

   ```
   &lt;script&gt;alert('XSS')&lt;/script&gt;
   ```

📸 **Figure 5.2** — screenshot the page source with that highlighted. **This is the stronger of
the two figures**, because it shows the actual escaping rather than just the absence of a popup.

> In the caption, explain that `&lt;` is the HTML entity for `<`, so the browser prints the text
> instead of treating it as a tag. That happens in `Mentions::highlight()`, which escapes the
> whole message **before** adding the highlight markup.

7. **Delete the post** afterwards using the delete control on it

---

## STEP 5. Figure 2.3, an @mention (3 minutes)

Still the **student**, still in the forum.

1. Post a question that tags the lecturer:

   ```
   @Malarvili could you explain question 3 from the tutorial?
   ```

2. Press **Post question**

The `@Malarvili` appears with a **blue highlighted background**, because the system recognised
the handle as somebody in this course.

📸 **Figure 2.3** — screenshot the post with the highlighted handle

> Worth adding to the caption: a handle nobody in the course answers to is left as plain text,
> and a handle two people share notifies neither, which is quieter than guessing wrong.

---

## STEP 6. Figures 2.1 and 2.4, the forum and the bell (5 minutes)

### Figure 2.1

Still in the forum as the student.

📸 **Figure 2.1** — screenshot the forum showing a question with **replies indented underneath
it**. Scroll to find one that has replies. If none do, reply to your own post first, or sign in
as the lecturer and reply.

### Figure 2.4, the Observer working

This is the figure that demonstrates your pattern, so it is worth doing properly.

1. As the **student**, post a new question in the forum. Anything will do
2. Sign out
3. Sign in as the **lecturer** `malarvili.nallayan@gmail.com`
4. Look at the **bell** in the top bar. **It has a red unread count on it**

📸 **Figure 2.4** — screenshot the top bar showing the bell with its unread badge, then click it
and screenshot the dropdown showing *"Foo Chong Xian posted in Integrative Programming"*

> This is the whole Observer pattern in one picture. Nothing in the forum code mentions
> notifications. Saving the post raised an Eloquent event, and the observer wrote that row.
> **Say exactly that in the caption.**

---

## STEP 7. Figure 2.5, notification settings (2 minutes)

Still signed in as anyone.

1. Click the **bell**, then **Preferences** at the bottom of the dropdown
   (or go straight to `http://127.0.0.1:8000/notification-preferences`)

📸 **Figure 2.5** — screenshot the list of notification types, each with its own on and off
switch

> There are eleven types listed. In the caption, note that the setting is checked **before** a
> row is written, so switching a type off stops it being produced rather than merely hiding it.

---

## STEP 8. Figure 2.2, an administrator locked out of the forum (2 minutes)

1. Sign out, then sign in as `learnsync.admin@gmail.com` / `password`
2. Go straight to **http://127.0.0.1:8000/forums/1**

You get **403 Forbidden** with the message *"Administrators do not take part in forums."*

📸 **Figure 2.2** — screenshot it **including the address bar**

> This is the specification's rule that administrators run the class but are not in it, enforced
> as a permission key rather than a role comparison.

---

## STEP 9. Figure 2.6, the reminder command (5 minutes)

Running `php artisan reminders:send` right now prints *"Nothing to remind anyone about"*, which
is a weak screenshot. Give it something to find first.

1. Sign in as the **lecturer** `malarvili.nallayan@gmail.com`
2. Click **Calendar** in the left rail, then **Schedule an event**
3. Fill in:
   - Title: `Tutorial consultation`
   - Course: `BMIT3173`
   - Type: **Online meeting**
   - Meeting link: `https://meet.google.com/abc-defg-hij`
   - Starts at: **about 30 minutes from now**
   - Ends at: about an hour from now
4. Save

5. Now in your **command terminal**:

   ```
   php artisan reminders:send
   ```

It now reports how many reminders it sent, one per person the event concerns.

📸 **Figure 2.6** — screenshot the terminal output

6. Run it **a second time**. It now says nothing to remind anyone about, because each reminder
   carries a reference and nobody is told twice

📸 **Optional but good** — screenshot the second run too, and caption the pair as showing the
command is safe to run on a schedule

> In the caption, make the point your report makes: these reminders are **not** the Observer.
> An observer fires when a model is saved, and nothing is saved when a deadline approaches.

---

## STEP 10. Figures 5.3 and 5.4, CSRF protection (8 minutes)

### Figure 5.3, a request with its token removed

1. Sign in as the **student**, open the forum at `http://127.0.0.1:8000/forums/1`
2. Press **F12** to open developer tools, choose the **Elements** tab
3. Press **Ctrl** + **F** inside developer tools and search for `_token`
4. You will find a hidden input like
   `<input type="hidden" name="_token" value="a-long-random-string">`
5. **Right click that line**, choose **Delete element**
6. Now type something in the question box and press **Post question**

**The post is not created.** You are bounced to the login page with a message explaining the
page was left open until the session expired, so the action was not carried out.

📸 **Figure 5.3** — screenshot the message, and take a second shot of the forum showing your post
is **not there**

> This is what a forged request from another website looks like to the server. An attacker's page
> cannot read your token, so their request arrives without one, exactly like this. In the caption
> say that the token is what proves the request came from a real form on this site.

### Figure 5.4, the stale tab

1. Sign in as the **student** in a normal window and open the forum. Do not submit anything yet
2. Open a **private / incognito window** and sign in as the **lecturer**
3. Go back to the first window, which is still showing the student's forum page, type something
   and press **Post question**

You are redirected with a message saying the page was left open while another sign in replaced
the session, so the action was not carried out, and telling you who this browser is now signed in
as.

📸 **Figure 5.4** — screenshot that message

> This is worth showing because it proves the protection is genuinely switched on. The stale
> tab did **not** get to post as the new user, and the person is told why in plain English rather
> than being shown a bare error code.

---

## STEP 11. The two drawings (45 minutes)

Not screenshots. Use **draw.io** at https://app.diagrams.net, free and no account needed.

### Figure 3.1, the entity class diagram

Section 3.1 of your report lists the classes with their attributes. Copy them into boxes.

- One box per class, three compartments: **name**, **attributes**, **methods**
- Lines between related classes, labelled `1` or `0..*`
- **Do not draw an ERD.** No `forum_id` columns. The line from `Post` to `DiscussionForum` is
  labelled `forum`, because it is an object reference

Classes: `DiscussionForum`, `Post`, `Reply`, `Notification`, `NotificationPreference`

Note that `DiscussionForum` to `Course` is **one to one**, and mark it as such.

### Figure 4.1, the Observer class diagram

Section 4.1 already has this drawn in text. Copy that layout. **Show all seven subjects**,
because seven subjects sharing one observer is your strongest point:

- Seven boxes across the top: `Post`, `Reply`, `AnnouncementComment`, `Announcement`, `Grade`,
  `Certificate`, `CourseInvitation`
- Dashed arrows from all seven down to one box, `SystemNotificationObserver`, labelled
  **`created` event**
- That box lists its methods: `created()`, `onPostCreated()`, `onGradeRecorded()` and so on
- An arrow from it down to `Notifier`
- An arrow from `Notifier` down to `Notification`
- A note beside `Notifier` saying **checks preferences, refuses duplicates**

Add a caption line under the diagram: *none of the seven subjects contains any reference to
notifications.*

When both are done: **File**, **Export as**, **PNG**, then paste into Word. Also upload each PNG
to Google Drive, press **Share**, set **Anyone with the link**, and paste the link where your
report says `[paste your Google Drive link here]`.

---

## Your figure checklist

- [ ] 8.1 test results
- [ ] 6.3 `notify:quiz-result` consuming Module 4
- [ ] 6.1 `sendNotification` service call
- [ ] 6.2 the notification arriving in the bell
- [ ] Optional: the allow list refusing a made up type
- [ ] 5.1 script tag shown as harmless text
- [ ] 5.2 page source showing `&lt;script&gt;` *(the stronger one)*
- [ ] 2.3 an @mention highlighted
- [ ] 2.1 forum with indented replies
- [ ] 2.4 the bell with an unread count after a student posts *(your Observer figure)*
- [ ] 2.5 notification preferences with on/off switches
- [ ] 2.2 administrator getting 403 on a forum
- [ ] 2.6 `reminders:send` output
- [ ] 5.3 request refused after deleting the token
- [ ] 5.4 the stale tab message
- [ ] 3.1 entity class diagram *(drawn)*
- [ ] 4.1 Observer class diagram, seven subjects *(drawn)*
- [ ] The `<script>` test post deleted from the forum
- [ ] The `Tutorial consultation` test event deleted from the calendar
