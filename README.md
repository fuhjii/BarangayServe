# BarangayServe

**A Web-Based Barangay Service Request Submission and Tracking System**
Software Engineering 1 research project, School of Information and Technology Education, Universidad de Dagupan.
Authors: Farhan U. Haji Jamil and Maris R. Melendez (31-BSCS-01).

Residents submit barangay service requests online and follow their progress. Authorized barangay staff review and process the requests. The system records and tracks requests only. It does **not** approve requests or create official certificates; barangay personnel still decide and release documents.

Technology (as required by the SRS, section 6.1): HTML5, CSS3, JavaScript, Bootstrap 5, PHP 8, MySQL / MariaDB. No frameworks, no Composer, nothing to build.

---

## 1. What you need

| Item | Notes |
|---|---|
| XAMPP, Laragon, or WAMP | Gives you Apache, PHP 7.4+ (8.x recommended) and MySQL/MariaDB. |
| PHP extension `pdo_mysql` | Enabled by default in XAMPP. |
| A web browser | Chrome, Edge, Firefox, Safari (desktop or phone). |

Bootstrap 5, Bootstrap Icons, and the fonts are already inside `assets/vendor/`, so the system also works **without internet**.

## 2. Installation (XAMPP, step by step)

1. **Copy the project.** Put the `barangayserve` folder into `C:\xampp\htdocs\` so you have `C:\xampp\htdocs\barangayserve\index.php`.
2. **Start Apache and MySQL** in the XAMPP Control Panel.
3. **Create the database.** Open <http://localhost/phpmyadmin>, click **Import**, choose `database/barangayserve.sql`, click **Import**.
   It creates the database `barangayserve`, all tables, and the six initial services. Running it twice is safe.
4. **Create your configuration file.** Copy `config/config.example.php` and name the copy `config/config.php`. For XAMPP's defaults (user `root`, empty password) you do not need to change anything except, if you like, `barangay_name`.
5. **Add demo data (optional, for presentations).** Open a terminal in the project folder:
   ```
   C:\xampp\php\php.exe tools\seed_demo.php
   ```
6. **Create a real staff account** (see section 4).
7. **Open** <http://localhost/barangayserve/>.

### Without XAMPP: PHP's built-in server

```
mysql -u root -p < database/barangayserve.sql
cp config/config.example.php config/config.php      # then edit the database user/password
php -S localhost:8000 router.php
```
Open <http://localhost:8000/>. (`router.php` is only for this built-in server; it keeps `config/`, `database/`, etc. private the way `.htaccess` does under Apache.)

### Optional extra protection for the history table

`database/optional_protect_history.sql` adds two database triggers so that even a careless query cannot change or delete an existing history row. Import it after `barangayserve.sql` if your MySQL user may create triggers. The system works without it.

## 3. Demo accounts (only if you ran `tools/seed_demo.php`)

These are **fake, non-production** accounts on the reserved domain `demo.barangayserve.test`. Never use them or their passwords on a public website.

| Role | Email | Password | Log in at |
|---|---|---|---|
| Staff | `staff@demo.barangayserve.test` | `DemoStaff123` | `/staff/login.php` |
| Resident | `resident1@demo.barangayserve.test` | `DemoResident123` | `/login.php` |
| Resident | `resident2@demo.barangayserve.test` | `DemoResident123` | `/login.php` |

The script also creates five demo requests, one for each status, made through the real application code (real reference numbers and history).

## 4. Creating staff accounts

Public registration only ever creates **residents**; there is no role field to tamper with. Staff accounts are created by whoever manages the server, from the command line:

```
php tools/create_staff.php --name="Maria Santos" --email="maria@barangay.example"
```
The script asks for the password (it is not typed in the command, so it stays out of the terminal history). The web server blocks the `tools/` folder.

## 5. Demonstration script (the complete workflow)

1. Open `/register.php` and register a resident (or use a demo resident). Log in.
2. **Request a service**, choose *Barangay Clearance*, fill in the form, submit. Note the reference number (`BR-2026-00001` style).
3. Open **My requests** and the request details: status is **Submitted**, history has one line.
4. Log in as staff (use a private window). The **dashboard** counts changed. Open the request from **Requests** (try search and filters).
5. Choose **Move forward** and save. Repeat: Under Review, Processing, Ready for Pickup, Completed. Add your own remarks.
6. Back in the resident window, refresh: the status, remarks, and the full history are there, and at *Ready for Pickup* a notice says the document can be picked up.

## 6. Project structure

```
barangayserve/
├── index.php, login.php, register.php, logout.php, account.php   Public pages and profile
├── resident/        dashboard, services, request_new, request_submitted, requests, request, history
├── staff/           login, dashboard, requests, request (details + status update), history,
│                    services, service_edit, service_fields, field_edit (service management)
├── includes/        The "logic" files, one job each:
│   ├── bootstrap.php   config, session, security headers (every page starts here)
│   ├── db.php          PDO connection, transactions
│   ├── auth.php        registration, login, roles, access checks
│   ├── csrf.php        CSRF tokens and one-time form tokens
│   ├── validation.php  server-side validation rules
│   ├── services.php    services and their questions
│   ├── requests.php    reference numbers, history, status workflow, lists
│   ├── layout.php, components.php   page layout, badges, tables, forms
│   └── helpers.php     small utilities (escaping, dates)
├── assets/          css/app.css, js/app.js, vendor/ (Bootstrap 5, icons, fonts)
├── config/          config.example.php (copy to config.php)
├── database/        barangayserve.sql, optional_protect_history.sql
├── tools/           create_staff.php, seed_demo.php  (command line only)
├── tests/           run_tests.php  (automatic tests)
└── docs/            documentation (see below)
```

Other documents in `docs/`: `IMPLEMENTATION_NOTES.md` (plan, assumptions, design decisions), `SRS_TRACEABILITY.md` (FR-01 to FR-10 mapped to pages, code and tests), `TESTING_CHECKLIST.md` (manual test cases). These files are blocked from the web by `.htaccess`; open them from the folder.

## 7. How the main requirements are met

- **Reference numbers `BR-YYYY-NNNNN`**: a `reference_counters` table holds one counter per year. It is incremented inside the same database transaction that saves the request, with the row locked, so two simultaneous submissions can never get the same number. A `UNIQUE` key on `reference_number` is a second safety net. (Counting existing rows would not be safe.)
- **No duplicate requests on refresh or double click**: every request form carries a one-time token stored in a `UNIQUE` column.
- **Status workflow**: Submitted → Under Review → Processing → Ready for Pickup → Completed. Staff move a request **one step forward** at a time. Mistakes are fixed with an explicit **correction** (one step back, remark required) that is clearly marked in the history. Nothing is ever overwritten: every change adds a new `request_history` row.
- **Two staff editing at once**: each update carries the status the staff member saw; a stale update is refused instead of silently skipping a step.
- **Services are configurable**: staff can add, rename, hide, and reorder services and edit each service's questions (*Staff > Services*), without changing code (SRS 7.7).
- **Security**: PDO prepared statements everywhere, `password_hash()` / `password_verify()`, session ID regenerated on login, server-side role checks on every protected page, ownership checks on every request lookup (another resident's request returns "not found"), CSRF tokens on every POST, output escaping, strict Content-Security-Policy, login lockout after repeated wrong passwords, idle session timeout, no technical errors shown to visitors.

## 8. Running the automatic tests

```
php tests/run_tests.php
```
Requires the PHP `curl` and `pdo_mysql` extensions and a MySQL user allowed to create databases (XAMPP's `root` works). The tests **erase and rebuild a separate database named `barangayserve_test`** (never your real one), start PHP's built-in server, and use the application like a browser would. Set `'debug' => false` in `config/config.php` to include the last test group (friendly error page when the database is down).

The latest run (228 checks, debug off): all passed. The tests cover registration, login, role and ownership restrictions, submission, concurrent reference numbers, status history, staff search and filters, service management, database constraints, and page speed against the SRS 3-second and 5-second targets with 3,000+ requests. They are a development aid; they do not replace the manual checklist in `docs/TESTING_CHECKLIST.md`.

## 9. Troubleshooting

| Problem | Fix |
|---|---|
| "BarangayServe is not set up yet" | Copy `config/config.example.php` to `config/config.php`. |
| "Something went wrong" on every page | The database settings in `config/config.php` are wrong or MySQL is not running. For details set `'debug' => true` (own computer only) or read `storage/logs/php-error.log`. |
| Links or styles are broken | Set `'base_url' => '/barangayserve'` in `config/config.php`. |
| Import fails on `CREATE DATABASE` (shared hosting) | Create a database in the hosting panel, delete the `CREATE DATABASE` and `USE` lines from the SQL file, select that database in phpMyAdmin and import. |
| Triggers file fails with a privilege error | Skip it; it is optional. |

## 10. Deploying online (outside the school demo)

Use HTTPS (the session cookie then becomes `Secure` automatically), set `'debug' => false`, use a dedicated MySQL user with rights only on this database, keep `config/config.php` out of version control (it is already in `.gitignore`), and do **not** run `seed_demo.php`.

## 11. Scope reminder

Out of scope for this version (SRS 6.4 and the research delimitation): online payments, GPS, AI, chat, SMS/email integration, resident-record management, finance, automatic approval, and automatic certificate generation.
