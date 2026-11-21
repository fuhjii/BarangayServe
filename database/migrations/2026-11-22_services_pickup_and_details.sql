-- Run this ONCE on an existing BarangayServe database (phpMyAdmin > barangayserve > SQL tab).
--
-- What it does (existing data is not deleted or changed, except where noted):
--   1. Adds "What to bring when picking it up" to every service (new column pickup_requirements).
--   2. Fills it in for the barangay's services (Valid ID, Cedula, fees). Only services that are still empty are filled.
--   3. Adds the new service "Loan Clearance".
--   4. Adds the details the barangay asks for that were missing (age, gender, civil status, lending institution).
--   5. Adds the six-month residency note to the Certificate of Indigency description (if it is not there yet).
-- A fresh install does not need this file: database/barangayserve.sql already includes everything.
-- Fees are in Philippine pesos. Staff can change them any time in Staff > Services > Edit.

SET NAMES utf8mb4;

ALTER TABLE services ADD COLUMN pickup_requirements VARCHAR(255) NULL AFTER description;

INSERT IGNORE INTO services (name, description, pickup_requirements, sort_order)
VALUES ('Loan Clearance', 'Certifies that you live in the barangay. Required by lending institutions when you apply for a loan.', NULL, 15);

UPDATE services SET pickup_requirements = 'Valid ID, Cedula, and ₱100 fee' WHERE name = 'Barangay Clearance' AND pickup_requirements IS NULL;
UPDATE services SET pickup_requirements = 'Valid ID and ₱130 fee' WHERE name = 'Loan Clearance' AND pickup_requirements IS NULL;
UPDATE services SET pickup_requirements = 'Valid ID and ₱80 fee' WHERE name = 'Certificate of Residency' AND pickup_requirements IS NULL;
UPDATE services SET pickup_requirements = 'Valid ID' WHERE name = 'Certificate of Indigency' AND pickup_requirements IS NULL;
UPDATE services SET pickup_requirements = 'Valid ID and ₱80 fee' WHERE name = 'Certificate of Good Moral Character' AND pickup_requirements IS NULL;
UPDATE services SET pickup_requirements = 'DTI registration, Valid ID, and ₱330 fee' WHERE name = 'Business-related Request' AND pickup_requirements IS NULL;

UPDATE services SET description = 'Certifies that your household has limited income. Used when applying for medical, educational, or legal assistance. For residents of the barangay for at least six months.'
 WHERE name = 'Certificate of Indigency' AND description NOT LIKE '%six months%';

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

