# Manual Testing Checklist

Use a clean database (import `database/barangayserve.sql`) and two browsers or one normal plus one private window (resident and staff at the same time). Mark each row Pass/Fail and note the date and tester. The automatic suite (`php tests/run_tests.php`) covers much of this already; this list is for the demonstration and for what a browser must confirm.

| ID | Requirement | Steps | Expected result | Result |
|---|---|---|---|---|
| **Registration (FR-01)** |||||
| TC-01 | FR-01 | Register with valid data | Account created, confirmation message shown | |
| TC-02 | FR-01 | Leave each required field empty, one at a time | Message names the missing field; no account created | |
| TC-03 | FR-01 | Enter `abc@` as the email | Invalid email message | |
| TC-04 | FR-01 | Register again with the same email | "Already registered" style message, no second account | |
| TC-05 | FR-01 | Password `abc` or `12345678` | Rejected (needs 8+ characters, a letter and a number) | |
| **Login (FR-02)** |||||
| TC-06 | FR-02 | Log in as a resident | Redirected to the resident dashboard | |
| TC-07 | FR-02 | Log in as staff at `/staff/login.php` | Redirected to the staff dashboard | |
| TC-08 | FR-02 | Wrong password | Generic error; does not say whether the email exists | |
| TC-09 | FR-02 | Resident account on the staff login page | Refused | |
| TC-10 | FR-02 | While logged out, open `/resident/dashboard.php` and `/staff/dashboard.php` | Sent to login | |
| TC-11 | FR-02 | Logged in as resident, open `/staff/dashboard.php` | Access denied; no staff data | |
| TC-12 | FR-02 | Log out, press the browser Back button | Private page is not shown again | |
| **Services and submission (FR-03 to FR-05)** |||||
| TC-13 | FR-03 | Open *Request a service* | Six services listed | |
| TC-14 | FR-03 | Open Business-related Request, then Barangay Clearance | Different questions for each | |
| TC-15 | FR-03 | As a resident, try `/staff/services.php` | Access denied | |
| TC-16 | FR-04 | Submit with a required field empty | Not saved; errors shown; typed values kept | |
| TC-17 | FR-04 | Submit a complete request | Confirmation page with reference number | |
| TC-18 | FR-04 | Press Refresh / Back and resend on the confirmation page | No second request in My requests | |
| TC-19 | FR-04 | Click Submit twice quickly | Only one request | |
| TC-20 | FR-04 | Type `<script>alert(1)</script>` in Purpose | Shown as plain text everywhere; no popup | |
| TC-21 | FR-05 | Look at the reference number | Format `BR-YYYY-NNNNN`, current year | |
| TC-22 | FR-05 | Submit a second request | Number is one higher; never repeated | |
| **Resident tracking (FR-07)** |||||
| TC-23 | FR-07 | Open *My requests* | Reference, service, date, status badge, details link | |
| TC-24 | FR-07 | Dashboard counts | Equal the real number of this resident's requests per status | |
| TC-25 | FR-07 | New resident with no requests | Friendly empty state | |
| TC-26 | FR-07 | Open a request's details | Reference, service, date, status, submitted data, progress | |
| TC-27 | FR-07 | As resident B, change the `id=` in the URL to resident A's request | "Not found"; nothing from A is shown | |
| TC-28 | FR-07 | Try `id=abc`, `id=-1`, `id=1 OR 1=1` | Safe "not found" page, no error text | |
| TC-29 | FR-07 | Look for any way to change a status as a resident | No form or button; direct POST is refused | |
| **Status updates (FR-06)** |||||
| TC-30 | FR-06 | As staff, open a *Submitted* request | Details, history, and update form shown | |
| TC-31 | FR-06 | Move forward to Under Review (leave remark empty) | Status changes; default remark saved | |
| TC-32 | FR-06 | Move forward to Processing with your own remark | Remark appears in the history | |
| TC-33 | FR-06 | Add a remark only | Status unchanged; new history line | |
| TC-34 | FR-06 | Try to skip a step (edit the form in browser tools) | Refused | |
| TC-35 | FR-06 | Open the same request in two staff windows; update in one, then the other | The second update is refused as out of date | |
| TC-36 | FR-06 | Move to Ready for Pickup; check as resident | Pickup notice on the details page and dashboard | |
| TC-37 | FR-06 | Move to Completed; try to move on | Cannot move forward; correction possible only with a remark | |
| **History (FR-08)** |||||
| TC-38 | FR-08 | Resident history page | Oldest first: status, date and time, remark | |
| TC-39 | FR-08 | After each update | A new row is added; earlier rows unchanged | |
| TC-40 | FR-08 | Make a correction | Row labelled as a correction, remark shown | |
| TC-41 | FR-08 | Compare resident and staff history | Staff see who made each change; residents do not | |
| **Staff management (FR-09)** |||||
| TC-42 | FR-09 | Open *Requests* | All residents' requests: reference, name, service, date, status | |
| TC-43 | FR-09 | Search by reference number | Only that request | |
| TC-44 | FR-09 | Search by part of a name | Matching requests | |
| TC-45 | FR-09 | Filter by service, status, date | Correct subsets; combinations work | |
| TC-46 | FR-09 | More than 10 requests | Paging works | |
| TC-47 | FR-09 | Staff: add a service and a question; hide a service | Residents see changes immediately; old requests keep their service | |
| **Dashboard (FR-10)** |||||
| TC-48 | FR-10 | Staff dashboard | Five counts match the Requests list filtered by each status | |
| TC-49 | FR-10 | Change a status, reload | Counts change accordingly | |
| **Usability, devices, security** |||||
| TC-50 | 7.3 | Required fields | Marked with * ; errors say what to fix | |
| TC-51 | 7.3 | Confirmation prompt before submitting a request | Appears | |
| TC-52 | 7.6 | Open every page on a phone (or 390 px wide window) | No sideways scrolling; tables readable; buttons usable | |
| TC-53 | 7.6 | Repeat in Chrome, Edge, Firefox (and Safari if available) | Same behaviour | |
| TC-54 | 7.1 | Open `/config/config.php`, `/database/barangayserve.sql`, `/includes/auth.php`, `/tools/seed_demo.php` in the browser | Forbidden or blank; no code or secrets shown | |
| TC-55 | 7.1 | Check the database: `SELECT password_hash FROM users` | Hashes only (`$2y$...`), no plain passwords | |
| TC-56 | 7.1 | Submit a POST form after deleting the hidden `csrf_token` field | Refused | |
| TC-57 | 7.2 | Stop MySQL, open a page that needs it | Friendly "Something went wrong" page without technical details | |
| TC-58 | 5.2 | Time login, dashboard, submission, history with a stopwatch | Within 3 s / 3 s / 5 s / 3 s | |
