# Implementation Notes

## 1. Requirements extracted from the research paper and SRS

**Scope (paper, Chapter 1):** submission, management, and tracking of barangay service requests only. Two areas: Resident Area and Barangay Staff Area. Not a full Barangay Information Management System.

**Objectives:** (1) residents submit requests online, (2) staff centrally review/manage/process, (3) residents monitor the current status and progress history.

**Functional requirements (SRS section 3):** FR-01 resident registration, FR-02 login by role, FR-03 select service, FR-04 submit request, FR-05 reference number `BR-YYYY-NNNNN`, FR-06 update status (five statuses, record date/time), FR-07 resident views own requests, FR-08 chronological history with remark, FR-09 staff list/select/update/remark, FR-10 staff dashboard counts from the database.

**Interfaces (section 4):** resident pages (registration, login, dashboard, request form, request list, details, history); staff pages (login, dashboard, request list, details, status update controls, history). PHP + MySQL + web server. Core tables: `users`, `service_requests`, `request_history`.

**Performance (section 5):** login and pages within 3 s; submission within 5 s; status update visible immediately; history within 3 s; no duplicate records on submission.

**Non-functional (sections 6 and 7):** HTML/CSS/JS/Bootstrap/PHP/MySQL; staff-only admin functions; residents cannot change status; no payment/GPS/AI/chat/messaging; hashed passwords; validation; clear error messages; logical code organization; desktop and mobile; new service types without redesign.

## 2. Ambiguities and the decisions taken

| Topic | Decision | Why |
|---|---|---|
| Fields per service | Each service has a small set of questions stored in the `service_fields` table (listed below). Name, address, contact number, and purpose are asked for every service. | The SRS says the service "shall determine the information" but does not list it. Staff can confirm or change them in *Staff > Services*. |
| Sensitive data | No income, ID numbers, or similar are collected online. | Data minimization; staff can ask for proof at pickup. |
| Variable answers | Stored as JSON in `service_requests.request_data`, each answer validated against `service_fields` before saving. Fixed relationships (user, service, history) stay relational. | Keeps the three core tables of SRS 4.3 and still supports configurable services (7.7). |
| Services table | Added `services` and `service_fields` (plus `reference_counters`, `login_attempts`). | Allowed by the brief; needed for FR-03 and scalability. `service_requests.service_type` keeps the service name as submitted. |
| Status transitions | Forward one step at a time; going back one step is a "correction" with a mandatory remark, recorded in the history as such. Completed requests cannot move forward. | The SRS names the workflow but not exceptions; this prevents skipped steps while allowing an auditable fix. |
| "Remark-only" update | Staff may add a remark without changing the status (FR-09 "update its status **or** add a processing remark"). | Matches SRS wording. |
| Staff accounts | Created only with `tools/create_staff.php` (command line). Staff log in at `/staff/login.php`. | Public registration must never grant staff rights (SRS 6.4, 7.1). |
| Reference number year | The year of the submission moment. Sequence restarts at 00001 each year. | Format in FR-05. |
| Edit after submit | Residents cannot edit or cancel a submitted request. | Not in the SRS; keeps the record trustworthy. Easy future requirement. |
| Resident sees who updated | No: residents see status, date, and remark only; staff see the staff member's name. | Privacy of staff; not required by the SRS. |

### Initial service questions (proposed; confirm with the barangay)

| Service | Extra questions |
|---|---|
| Barangay Clearance | Date of birth, civil status, years of residency |
| Certificate of Residency | Years of residency, living as (homeowner/renter/…) |
| Certificate of Indigency | Household size, kind of assistance needed |
| Certificate of Good Moral Character | Years of residency, school/employer that needs it (optional) |
| Business-related Request | Type of request, business name, business address, nature of business |
| Other Barangay Service | Description of the service needed |

## 3. Implementation stages (as carried out)

1. Database schema, configuration, authentication (`database/barangayserve.sql`, `includes/db.php`, `auth.php`, `csrf.php`).
2. Resident registration, login, dashboard, service list, request form.
3. Reference numbers (counter table + transaction), initial history row, duplicate-submission protection.
4. Staff dashboard, request list with search/filters, details, status update, remarks, service management.
5. Resident tracking pages, responsive UI, accessibility, security headers, error handling.
6. Automatic tests (`tests/run_tests.php`), bug fixes, documentation, SRS traceability.

## 4. Known limitations (honest list)

- Performance figures come from tests on one development machine with PHP's built-in server and about 3,000 requests. They show the design is fast, not that a particular hosting service will meet the targets.
- Tests were run on PHP 8.3 with MariaDB 10.11. MySQL 5.7+/8.x and XAMPP are expected to work (the SQL uses standard features and the `JSON` type) but were not run here.
- No password-reset or email verification (email integration is out of scope).
- There is no CAPTCHA; a login lockout slows password guessing.
- Staff cannot yet edit or delete accounts from the website; use the database/command line.
- Manual cross-browser checks (Chrome, Firefox, Safari, real phones) still need to be done by the team using `TESTING_CHECKLIST.md`. Layout was checked in Chromium at 1280 px and 390 px widths.
