# Screenshot guide: MEMBER 1, Serena Lim Sze Kee (Module 1)

Follow this top to bottom. It is in the order that needs the fewest logins.

**To take a screenshot:** press `Win` + `Shift` + `S`, drag a box, then paste into Word with
`Ctrl` + `V`.

You need **21 figures**. Two of them are drawings, the other 19 are screenshots.

---

## STEP 0. Get the site running (5 minutes, do this once)

1. Open **XAMPP Control Panel** and press **Start** next to **MySQL**.
   If MySQL is not running, nothing below will work.

2. Open a terminal in `c:\xampp\htdocs\edusystem` and run:

   ```
   php artisan serve
   ```

   Leave this window open the whole time. The site is now at **http://127.0.0.1:8000**

3. Open a **second** terminal in the same folder. You will use it in Step 1.

**Logins you will need.** The password for every account is `password`

| Who | Email |
|---|---|
| Administrator | `learnsync.admin@gmail.com` |
| Student | `foochongxian@gmail.com` |

---

## STEP 1. Figure 8.1, the test results (2 minutes)

In your **second terminal**, type:

```
php artisan test
```

Wait about fifteen seconds. Green ticks scroll past, ending with:

```
Tests:    86 passed (200 assertions)
Duration: 11.88s
```

📸 **Figure 8.1** — screenshot the **bottom** of the terminal, making sure the
`Tests: 86 passed (200 assertions)` line is visible.

> The full list is 90 lines and will not fit on one screen. Just capturing the last 15 lines
> plus the total is fine and is what most reports do.

---

## STEP 2. Figures 6.1 and 6.2, the web service (5 minutes)

No login needed. This service is public on purpose.

### Figure 6.1

Paste this into your browser address bar, all on one line:

```
http://127.0.0.1:8000/api/credentials/verify?credentialId=LS-2026-XTEG2CDW&detailFlag=2&requestID=REQ-CRED-84920&timeStamp=2026-09-06T10:00:00Z
```

You will see:

```json
{
    "status": "S",
    "timeStamp": "2026-09-06T...",
    "data": {
        "requestID": "REQ-CRED-84920",
        "credentialStatus": "VALID",
        "holderName": "Foo Chong Xian",
        "courseTitle": "Integrative Programming",
        "finalScore": 88,
        "issuedDate": "2026-08-28"
    }
}
```

📸 **Figure 6.1** — screenshot the browser **including the address bar**, so the marker can see
the request and the answer together.

### Figure 6.2

Change `detailFlag=2` to `detailFlag=1` in the same URL and press Enter:

```
http://127.0.0.1:8000/api/credentials/verify?credentialId=LS-2026-XTEG2CDW&detailFlag=1&requestID=REQ-CRED-84921&timeStamp=2026-09-06T10:00:00Z
```

Now the answer contains **only** `credentialStatus` and no holder name, no score, no date.

📸 **Figure 6.2** — screenshot it. Caption it as *detailFlag 1 returning the status only,
disclosing nothing about the holder*. This is a strong figure because it shows the service
deliberately gives out the minimum.

---

## STEP 3. Figure 6.3, consuming Module 2's service (3 minutes)

1. Sign in at `http://127.0.0.1:8000/login` as the **student**
   `foochongxian@gmail.com` / `password`
2. Go straight to: **http://127.0.0.1:8000/certificates/76**

You will see the certificate page with a **blue panel** headed
**"Course details, retrieved from Module 2 web service"**, showing the course code, title,
lecturer and student count.

📸 **Figure 6.3** — screenshot the page with that blue panel visible.

> That panel is Module 1 calling Module 2's `getCourseInfo` service over HTTP. It only appears
> when Module 2 answers, which is exactly what makes it worth photographing.

---

## STEP 4. Figures 2.7, 2.8, 5.1 and 5.2, the TAMPERED demonstration (10 minutes)

**This is the most important pair of figures in your whole report.** Take your time.

### First, the VALID state

1. Open a **private / incognito window** (`Ctrl` + `Shift` + `N` in Chrome), so you are clearly
   not logged in
2. Go to: **http://127.0.0.1:8000/verify/LS-2026-XTEG2CDW**
3. The page shows a big green **VALID** with the holder, course, score and date

📸 **Figure 2.7** — screenshot it
📸 **Figure 5.1** — use the **same screenshot** again, it serves both figures

### Now break it

4. Open **http://localhost/phpmyadmin** in a normal window
5. On the left, click the **`edusystem`** database
6. Click the **`certificates`** table
7. Press the **Search** tab, put `LS-2026-XTEG2CDW` in the `credential_id` box, press **Go**
8. Press **Edit** on the row that comes back
9. Change **`final_score`** from `88` to `99`
10. Press **Go** to save

### Show it caught the change

11. Go back to your private window and **reload** the verification page
12. It now shows a red **TAMPERED**

📸 **Figure 2.8** — screenshot it
📸 **Figure 5.2** — use the **same screenshot** again

### ⚠️ PUT IT BACK

13. Return to phpMyAdmin and **change `final_score` back to `88`**. Save.
14. Reload the verification page once more and check it says **VALID** again

> If you skip step 13, your demo data stays broken and the certificate will show as tampered
> during your presentation.

---

## STEP 5. Figures 5.3 and 5.4, the account lockout (8 minutes)

### Figure 5.3, an account getting locked

1. In a **private window**, go to `http://127.0.0.1:8000/login`
2. Type `serenalim@gmail.com` with a **wrong** password such as `wrongpassword`
3. Press **Log in**. It fails
4. **Repeat this five times in total**
5. On the fifth attempt, the account locks

📸 **Figure 5.3a** — screenshot the login page showing the lockout message

6. Now sign in as the **administrator** in a normal window
7. Go to **Accounts** in the left rail
8. Find Serena's row. It shows as locked, with an **Unlock** control

📸 **Figure 5.3b** — screenshot the Accounts screen showing the locked account and the unlock
control. Use this and 5.3a together as Figure 5.3

9. **Press Unlock** so the account works again

### Figure 5.4, the failed attempts in the log

10. Still as the administrator, go to **Activity log** in the left rail
11. You will see several `auth.failed` rows from the attempts you just made, each with an IP
    address and a timestamp

📸 **Figure 5.4** — screenshot the log showing the `auth.failed` rows with their IP addresses

---

## STEP 6. Everything else as the ADMINISTRATOR (15 minutes)

Sign in as `learnsync.admin@gmail.com` / `password` and take these in one pass. Each one is a
link in the **left rail**.

| Figure | Click this | What to capture |
|---|---|---|
| 📸 **2.1** | **Invitations**, then **Invite** | The invitation form, and the list showing an issued invitation with its link |
| 📸 **2.2** | **Permissions** | The grid of tick boxes with the permission keys down the side and the roles across the top |
| 📸 **2.3** | **Accounts**, then click any action such as **Deactivate** | Two shots: the accounts list, and the page asking for **your own password** to confirm |
| 📸 **2.4** | **Activity log** | The log with its filter controls and the **Export CSV** button |
| 📸 **2.9** | **Badge rules** | The award rules table, showing both badge rules and certificate rules |

---

## STEP 7. Everything else as the STUDENT (10 minutes)

Sign out, then sign in as `foochongxian@gmail.com` / `password`

| Figure | Click this | What to capture |
|---|---|---|
| 📸 **2.5** | **Dashboard** | The progress-over-time line chart |
| 📸 **2.6** | **My certificates**, then **Download** on any certificate | Open the downloaded PDF and screenshot it, showing the credential ID and the QR code |
| 📸 **2.10** | **Trophy cabinet** | Badges in colour (earned) next to greyed out ones (locked) with their unlock conditions |
| 📸 **2.11** | The **bell** in the top bar | Two shots: the bell with its red unread count, and the **Preferences** page with the on/off switches |

---

## STEP 8. The two drawings (45 minutes)

These are **not** screenshots. You have to draw them.

Use **draw.io** at https://app.diagrams.net, which is free and needs no account.

### Figure 3.1, the entity class diagram

Your report has the full list of classes and their attributes in Section 3.1. Copy it into
boxes.

- One box per class, with three compartments: **name**, **attributes**, **methods**
- Draw lines between related classes, and label the ends with `1` or `0..*`
- **Do not draw an ERD.** No `student_id` columns. The line between `Certificate` and `User`
  is labelled `student`, because it is an object reference

Classes to include: `User`, `Certificate`, `StudentProgress`, `ProgressSnapshot`, `Badge`,
`Permission`, `PermissionRole`, `Invitation`, `ActivityLog`, `LearningPath`,
`CertificateTemplate`, `Notification`, `NotificationPreference`

### Figure 4.1, the Facade class diagram

Section 4.1 of your report already has this drawn in text. Copy that layout:

- `CertificateController` at the top, with one arrow down to `CredentialAuthority`
- `CredentialAuthority` in the middle, listing its public methods
- Five boxes underneath: `CredentialIdGenerator`, `IntegrityHasher`, `CertificateRenderer`,
  `ProgressCalculator`, `BadgeRuleEvaluator`
- Arrows from `CredentialAuthority` down to each of the five
- Label the middle box `«Facade»`

When both are done: **File**, **Export as**, **PNG**, then paste into Word.
Also upload each PNG to Google Drive, press **Share**, set it to **Anyone with the link**, and
paste the link where your report says `[paste your Google Drive link here]`.

---

## Your figure checklist

- [ ] 8.1 test results
- [ ] 6.1 web service, detailFlag 2
- [ ] 6.2 web service, detailFlag 1
- [ ] 6.3 certificate page with the blue Module 2 panel
- [ ] 2.7 and 5.1 verification page VALID *(same image)*
- [ ] 2.8 and 5.2 verification page TAMPERED *(same image)*
- [ ] **`final_score` put back to 88**
- [ ] 5.3 account locked, and the admin unlock control
- [ ] 5.4 activity log with `auth.failed` rows
- [ ] 2.1 invitations
- [ ] 2.2 permission matrix
- [ ] 2.3 accounts and password confirmation
- [ ] 2.4 activity log with CSV export
- [ ] 2.9 award rules
- [ ] 2.5 progress chart
- [ ] 2.6 certificate PDF with QR code
- [ ] 2.10 trophy cabinet
- [ ] 2.11 notification bell and preferences
- [ ] 3.1 entity class diagram *(drawn)*
- [ ] 4.1 Facade class diagram *(drawn)*
- [ ] Serena's account unlocked again
