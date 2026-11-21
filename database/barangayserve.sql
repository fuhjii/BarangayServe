-- =====================================================================
-- BarangayServe: database schema and initial data
-- A Web-Based Barangay Service Request Submission and Tracking System
--
-- Works on MySQL 5.7+ / 8.x and MariaDB 10.3+ (XAMPP, Laragon, WAMP, etc.)
--
-- HOW TO USE
--   phpMyAdmin : open the "Import" tab, choose this file, click "Import".
--   Command line: mysql -u root -p < database/barangayserve.sql
--
-- This script is safe to run more than once. It never drops tables or
-- deletes data (it only uses CREATE ... IF NOT EXISTS and INSERT IGNORE).
-- To start completely fresh, drop the "barangayserve" database first.
--
-- If your hosting does not let you create databases, create one in the
-- control panel, delete the CREATE DATABASE / USE lines below, select that
-- database in phpMyAdmin, and import the rest.
--
-- All timestamps are stored as DATETIME in Philippine time (Asia/Manila).
-- The PHP application sets the same time zone for every connection.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS barangayserve
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE barangayserve;

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- users: every account (residents and barangay staff)
--   role is set by the system. Public registration always creates
--   'resident'. Staff accounts are created with tools/create_staff.php.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name       VARCHAR(100) NOT NULL,
  email           VARCHAR(150) NOT NULL,
  contact_number  VARCHAR(20)  NOT NULL,
  address         VARCHAR(255) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  role            ENUM('resident','staff') NOT NULL DEFAULT 'resident',
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- services: the barangay services residents can request
--   Staff can add, rename, reorder, and switch services on or off in the app.
--   Services are never deleted, so old requests always keep their service.
--   sort_order: smaller numbers are shown first (ties are sorted by name).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS services (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(100) NOT NULL,
  description  VARCHAR(255) NOT NULL DEFAULT '',
  pickup_requirements VARCHAR(255) NULL,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_services_name (name),
  KEY idx_services_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- service_fields: the extra questions each service asks
--   The request form is built from these rows, so staff can change what a
--   service asks without touching any code (SRS 7.7 Scalability).
--   options: for field_type 'select', one choice per line.
--   min_value / max_value: only used by field_type 'number'.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS service_fields (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_id   INT UNSIGNED NOT NULL,
  field_key    VARCHAR(50)  NOT NULL,
  label        VARCHAR(100) NOT NULL,
  field_type   ENUM('text','textarea','number','date','select') NOT NULL DEFAULT 'text',
  is_required  TINYINT(1)   NOT NULL DEFAULT 1,
  options      TEXT NULL,
  min_value    INT NULL,
  max_value    INT NULL,
  help_text    VARCHAR(200) NULL,
  sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_service_field_key (service_id, field_key),
  KEY idx_service_fields_order (service_id, sort_order),
  CONSTRAINT fk_service_fields_service
    FOREIGN KEY (service_id) REFERENCES services (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- service_requests: one row per submitted request
--   service_type     : name of the service when the request was submitted
--                      (kept even if staff rename the service later)
--   applicant_*      : personal information for this request (copied from
--                      the resident's profile, and editable on the form)
--   request_data     : answers to the service-specific questions, stored as
--                      JSON: [{"key":"civil_status","label":"Civil status",
--                      "type":"select","value":"Single"}, ...]
--                      The PHP code validates every answer against
--                      service_fields BEFORE saving it.
--   submission_token : random value created when the form is opened. The
--                      UNIQUE key makes refresh / double-click harmless.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS service_requests (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference_number   VARCHAR(20)  NOT NULL,
  user_id            INT UNSIGNED NOT NULL,
  service_id         INT UNSIGNED NOT NULL,
  service_type       VARCHAR(100) NOT NULL,
  applicant_name     VARCHAR(100) NOT NULL,
  applicant_address  VARCHAR(255) NOT NULL,
  applicant_contact  VARCHAR(20)  NOT NULL,
  purpose            VARCHAR(255) NOT NULL,
  request_data       JSON NOT NULL,
  status             ENUM('Submitted','Under Review','Processing','Ready for Pickup','Completed','Rejected','Cancelled')
                     NOT NULL DEFAULT 'Submitted',
  submission_token   CHAR(32) NULL,
  submitted_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_requests_reference (reference_number),
  UNIQUE KEY uq_requests_token (submission_token),
  KEY idx_requests_user (user_id, submitted_at),
  KEY idx_requests_status (status, submitted_at),
  KEY idx_requests_service (service_id),
  KEY idx_requests_submitted (submitted_at),
  CONSTRAINT fk_requests_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_requests_service
    FOREIGN KEY (service_id) REFERENCES services (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- request_history: append-only log of everything that happens to a request
--   The app only ever INSERTs into this table. Rows are never updated or
--   deleted, so the full history is preserved (see also
--   database/optional_protect_history.sql for a database-level guard).
--   entry_type: submitted | status_change | correction | remark
--   changed_by: the resident (for 'submitted') or the staff member.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS request_history (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id  INT UNSIGNED NOT NULL,
  status      ENUM('Submitted','Under Review','Processing','Ready for Pickup','Completed','Rejected','Cancelled') NOT NULL,
  entry_type  ENUM('submitted','status_change','correction','remark') NOT NULL,
  remark      VARCHAR(500) NULL,
  changed_by  INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_history_request (request_id, created_at, id),
  KEY idx_history_changed_by (changed_by),
  CONSTRAINT fk_history_request
    FOREIGN KEY (request_id) REFERENCES service_requests (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_history_user
    FOREIGN KEY (changed_by) REFERENCES users (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- reference_counters: one row per year, used to build BR-YYYY-NNNNN
--   The app increments last_number inside the same transaction that saves
--   the request, so two residents can never receive the same number and a
--   failed submission does not leave a gap.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reference_counters (
  ref_year     SMALLINT UNSIGNED NOT NULL,
  last_number  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (ref_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- login_attempts: failed log-ins, used to slow down password guessing
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(150) NOT NULL,
  ip_address    VARCHAR(45)  NOT NULL,
  attempted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_attempts_lookup (email, ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- INITIAL DATA: the services (the six from SRS 3.3 plus Loan Clearance)
-- =====================================================================
INSERT IGNORE INTO services (name, description, pickup_requirements, sort_order) VALUES
  ('Barangay Clearance',
   'Certifies that you live in the barangay and have no pending complaint on record. Often required for employment, business permits, and government IDs.', 'Valid ID, Cedula, and ₱100 fee', 10),
  ('Loan Clearance',
   'Certifies that you live in the barangay. Required by lending institutions when you apply for a loan.', 'Valid ID and ₱130 fee', 15),
  ('Certificate of Residency',
   'Certifies that you live in the barangay. Often required for school enrollment, employment, and benefit applications.', 'Valid ID and ₱80 fee', 20),
  ('Certificate of Indigency',
   'Certifies that your household has limited income. Used when applying for medical, educational, or legal assistance. For residents of the barangay for at least six months.', 'Valid ID', 30),
  ('Certificate of Good Moral Character',
   'Certifies that you are of good standing in the community. Often required for school, scholarship, and job applications.', 'Valid ID and ₱80 fee', 40),
  ('Business-related Request',
   'Barangay clearance or certification for a business located in the barangay.', 'DTI registration, Valid ID, and ₱330 fee', 50),
  ('Other Barangay Service',
   'Any other barangay document or service that is not listed above. Describe what you need.', NULL, 60);

-- ---------------------------------------------------------------------
-- INITIAL DATA: service-specific questions
--   These are proposed starting points. The SRS does not list the exact
--   questions for each service, so barangay staff should confirm or change
--   them (Staff > Services). Name, address, contact number, and purpose are
--   asked for every service and are NOT listed here.
-- ---------------------------------------------------------------------

-- Barangay Clearance
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'date_of_birth', 'Date of birth', 'date', 1, NULL, NULL, NULL, NULL, 10
FROM services s WHERE s.name = 'Barangay Clearance';

INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'civil_status', 'Civil status', 'select', 1, 'Single\nMarried\nWidowed\nSeparated', NULL, NULL, NULL, 20
FROM services s WHERE s.name = 'Barangay Clearance';

INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'years_of_residency', 'Years of residency in the barangay', 'number', 1, NULL, 0, 120, 'Enter 0 if you have lived here for less than one year.', 30
FROM services s WHERE s.name = 'Barangay Clearance';

-- Certificate of Residency
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'years_of_residency', 'Years of residency in the barangay', 'number', 1, NULL, 0, 120, 'Enter 0 if you have lived here for less than one year.', 10
FROM services s WHERE s.name = 'Certificate of Residency';

INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'residence_type', 'You are living in the barangay as', 'select', 1, 'Homeowner\nRenter\nLiving with family or relatives\nCaretaker\nOther', NULL, NULL, NULL, 20
FROM services s WHERE s.name = 'Certificate of Residency';

-- Certificate of Indigency
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'household_members', 'Number of people in your household', 'number', 1, NULL, 1, 30, NULL, 10
FROM services s WHERE s.name = 'Certificate of Indigency';

INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'assistance_needed', 'What kind of assistance is this for?', 'select', 1,
       'Medical or hospital\nEducation or scholarship\nLegal\nBurial or funeral\nOther government assistance', NULL, NULL,
       'Income details are not collected online. Staff may ask for proof when you pick up the certificate.', 20
FROM services s WHERE s.name = 'Certificate of Indigency';

-- Certificate of Good Moral Character
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'years_of_residency', 'Years of residency in the barangay', 'number', 1, NULL, 0, 120, 'Enter 0 if you have lived here for less than one year.', 10
FROM services s WHERE s.name = 'Certificate of Good Moral Character';

INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'requesting_organization', 'School, employer, or office that needs this certificate', 'text', 0, NULL, NULL, NULL, 'Leave blank if not applicable.', 20
FROM services s WHERE s.name = 'Certificate of Good Moral Character';

-- Business-related Request
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'request_kind', 'Type of business request', 'select', 1,
       'New business clearance\nRenewal of business clearance\nClosure of business\nOther business certification', NULL, NULL, NULL, 10
FROM services s WHERE s.name = 'Business-related Request';

INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'business_name', 'Business name', 'text', 1, NULL, NULL, NULL, NULL, 20
FROM services s WHERE s.name = 'Business-related Request';

INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'business_address', 'Business address', 'text', 1, NULL, NULL, NULL, 'Street and building, inside the barangay.', 30
FROM services s WHERE s.name = 'Business-related Request';

INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'nature_of_business', 'Nature of business', 'text', 1, NULL, NULL, NULL, 'For example: sari-sari store, carinderia, repair shop.', 40
FROM services s WHERE s.name = 'Business-related Request';

-- Other Barangay Service
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'service_description', 'Describe the service or document you need', 'textarea', 1, NULL, NULL, NULL, 'Be as specific as you can so staff can help you faster.', 10
FROM services s WHERE s.name = 'Other Barangay Service';

-- ---------------------------------------------------------------------
-- Details the barangay asks for (age, gender, civil status, lender)
-- ---------------------------------------------------------------------
-- Barangay Clearance
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'gender', 'Gender', 'select', 1, 'Male\nFemale', NULL, NULL, NULL, 25
FROM services s WHERE s.name = 'Barangay Clearance';

-- Loan Clearance
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'age', 'Age', 'number', 1, NULL, 1, 120, NULL, 10
FROM services s WHERE s.name = 'Loan Clearance';

INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'gender', 'Gender', 'select', 1, 'Male\nFemale', NULL, NULL, NULL, 20
FROM services s WHERE s.name = 'Loan Clearance';

INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'civil_status', 'Civil status', 'select', 1, 'Single\nMarried\nWidowed\nSeparated', NULL, NULL, NULL, 30
FROM services s WHERE s.name = 'Loan Clearance';

INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'lending_institution', 'Lending institution', 'text', 1, NULL, NULL, NULL, 'Name of the bank, cooperative, or lender.', 40
FROM services s WHERE s.name = 'Loan Clearance';

-- Certificate of Residency
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'age', 'Age', 'number', 1, NULL, 1, 120, NULL, 2
FROM services s WHERE s.name = 'Certificate of Residency';

INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'gender', 'Gender', 'select', 1, 'Male\nFemale', NULL, NULL, NULL, 4
FROM services s WHERE s.name = 'Certificate of Residency';

INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'civil_status', 'Civil status', 'select', 1, 'Single\nMarried\nWidowed\nSeparated', NULL, NULL, NULL, 6
FROM services s WHERE s.name = 'Certificate of Residency';

-- Certificate of Indigency
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'age', 'Age', 'number', 1, NULL, 1, 120, NULL, 2
FROM services s WHERE s.name = 'Certificate of Indigency';

INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'gender', 'Gender', 'select', 1, 'Male\nFemale', NULL, NULL, NULL, 4
FROM services s WHERE s.name = 'Certificate of Indigency';

-- Certificate of Good Moral Character
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'age', 'Age', 'number', 1, NULL, 1, 120, NULL, 2
FROM services s WHERE s.name = 'Certificate of Good Moral Character';

INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'gender', 'Gender', 'select', 1, 'Male\nFemale', NULL, NULL, NULL, 4
FROM services s WHERE s.name = 'Certificate of Good Moral Character';

INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'civil_status', 'Civil status', 'select', 1, 'Single\nMarried\nWidowed\nSeparated', NULL, NULL, NULL, 6
FROM services s WHERE s.name = 'Certificate of Good Moral Character';
