# SRS Requirements to Implementation Checklist

"Automatic tests" refers to the section titles in `tests/run_tests.php`. "Manual" refers to case IDs in `TESTING_CHECKLIST.md`.

| ID | Requirement | Pages | Backend logic | Database | Automatic tests | Manual |
|---|---|---|---|---|---|---|
| FR-01 | Resident registration (name, email, contact, address, password; validation; duplicate email; confirmation) | `register.php` | `includes/auth.php` `create_user`, `email_is_taken`; `validation.php` (`validate_email`, `validate_password`, ...) | `users` (UNIQUE `email`, `role` defaults to resident) | 2. Resident registration | TC-01 to TC-05 |
| FR-02 | Login with email and password; role-based dashboards; error on bad credentials | `login.php`, `staff/login.php`, `logout.php` | `auth.php` `attempt_login`, `login_session_start` (regenerates session ID), `require_login/resident/staff`, lockout | `users`, `login_attempts` | 3. Login, logout, sessions; 4. Staff accounts; 5. Role-based access control | TC-06 to TC-12 |
| FR-03 | Select a service before requesting; six initial services; service decides required info | `resident/services.php`, `resident/request_new.php` (form built from the service's questions) | `includes/services.php` `get_active_services`, `get_service_fields` | `services`, `service_fields` (six services seeded) | 6. Service selection and request submission; 11. Services managed by staff only | TC-13 to TC-15 |
| FR-04 | Authenticated resident submits after providing all required info; incomplete submission prevented; request saved | `resident/request_new.php`, `resident/request_submitted.php` | `services.php` `validate_service_answers`; `requests.php` `create_service_request` (one transaction) | `service_requests`, `request_history` | 6. Service selection and request submission | TC-16 to TC-20 |
| FR-05 | Unique reference number `BR-YYYY-NNNNN`, one request per number | shown on `request_submitted.php` and all request pages | `requests.php` `next_reference_sequence`, `format_reference_number` | `reference_counters`; UNIQUE `reference_number` | 6; 8. Reference numbers under simultaneous submissions; 12. Database integrity | TC-21, TC-22 |
| FR-06 | Staff update status (five statuses); date and time recorded for each change | `staff/request.php` (update form) | `requests.php` `staff_update_request`, `staff_action_choices` (transactional, stale-update check) | `service_requests.status` (ENUM of five), `request_history` | 10. Status workflow and history | TC-30 to TC-37 |
| FR-07 | Residents view status of their own requests: reference, service, date, status; only own requests | `resident/dashboard.php`, `resident/requests.php`, `resident/request.php` | `requests.php` `find_request_for_resident`, `list_resident_requests`; ownership enforced in SQL (`user_id = ?`) | `service_requests.user_id` FK | 7. Request ownership | TC-23 to TC-29 |
| FR-08 | Chronological history: status, date/time, remark when applicable | `resident/history.php`, `staff/history.php`, history panel on both detail pages | `requests.php` `get_request_history`, `add_history_entry` (append-only) | `request_history` (+ optional triggers `optional_protect_history.sql`) | 10; 12 | TC-38 to TC-41 |
| FR-09 | Staff list of requests (reference, resident name, service, date, status); select, update, add remark | `staff/requests.php` (search and filters), `staff/request.php` | `requests.php` `list_staff_requests`, `staff_filters_from_query`, `find_request_for_staff`, `staff_update_request` | `service_requests` JOIN `users` | 9. Staff dashboard, search, filters; 10 | TC-42 to TC-47 |
| FR-10 | Staff dashboard with counts per status from the database | `staff/dashboard.php` | `requests.php` `count_requests_by_status` | `service_requests` | 9 | TC-48, TC-49 |

## Other SRS sections

| SRS section | Requirement | Where / how | Evidence |
|---|---|---|---|
| 2.2 / 3.7 | Resident can tell when the request is ready for pickup | Banner on `resident/request.php` and `resident/dashboard.php` for status *Ready for Pickup* | Test group 10; TC-36 |
| 4.1 | Resident and staff interface pages | See project structure in `README.md` | Test groups 5, 15 |
| 4.2, 6.1, 6.3 | PHP, MySQL, web server, HTML/CSS/JS/Bootstrap | Whole project | Environment |
| 4.3 | Tables `users`, `service_requests`, `request_history` | `database/barangayserve.sql` | Test group 1 |
| 5.1 | Unique identifiers; no intentional duplicate records | PKs; UNIQUE keys; one-time form token | Test groups 6, 8, 12 |
| 5.2 | 3 s login/pages/history, 5 s submission, immediate status update | Indexed queries, paging, no N+1 queries | Test group 14 (3,000+ requests), group 6 |
| 6.4 | Only staff reach admin functions; residents cannot change status | `require_staff()` on every staff page and POST | Test groups 4, 5, 10, 11 |
| 7.1 | Login required; staff-only admin; ownership; hashed passwords; validation | `auth.php`, `csrf.php`, `validation.php`, `password_hash` | Test groups 2 to 7 |
| 7.2 | Saved requests persist; history stays with its request; clear errors | InnoDB transactions, FKs with RESTRICT, friendly error page | Test groups 12, 16 |
| 7.3 | Simple UI, required fields marked, helpful errors | Bootstrap forms, error summary, `class="req"` markers | Test group 15; TC-50 |
| 7.4 | Data integrity; unique references; invalid data not stored | FKs, UNIQUE, ENUMs, server validation | Test group 12 |
| 7.5 | Logical code components | `includes/` split by job; pages stay short | Code review |
| 7.6 | Modern browsers, desktop and mobile | Responsive Bootstrap layout | Test group 15 (viewport tag); manual TC-52 |
| 7.7 | Add services without redesign | `services` and `service_fields` tables; *Staff > Services* | Test group 11 |
