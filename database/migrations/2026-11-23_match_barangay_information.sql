-- Run this ONCE on an existing BarangayServe database (phpMyAdmin > barangayserve > SQL tab).
-- Run it after 2026-11-22_services_pickup_and_details.sql.
--
-- Makes the details each service asks for match exactly what the barangay told us:
--   Barangay Clearance ........ Age, Gender, Civil status
--   Loan Clearance ............ Age, Gender, Civil status, Lending institution
--   Certificate of Residency .. Age, Gender, Civil status, Length of residency
--   Certificate of Indigency .. Age, Gender
--   Good Moral Character ...... Age, Gender, Civil status
--   Business-related Request .. Business name, Business address, Nature of business (New/Renewal)
-- (Name, contact number, address and purpose are unchanged.)
--
-- Only the starter questions are removed. Requests already submitted keep their saved answers.
-- Any extra question that staff added themselves is left alone.
-- A fresh install does not need this file.

SET NAMES utf8mb4;

DELETE sf FROM service_fields sf JOIN services s ON s.id = sf.service_id WHERE s.name = 'Barangay Clearance' AND sf.field_key IN ('date_of_birth', 'years_of_residency');
DELETE sf FROM service_fields sf JOIN services s ON s.id = sf.service_id WHERE s.name = 'Certificate of Residency' AND sf.field_key IN ('residence_type');
DELETE sf FROM service_fields sf JOIN services s ON s.id = sf.service_id WHERE s.name = 'Certificate of Indigency' AND sf.field_key IN ('household_members', 'assistance_needed');
DELETE sf FROM service_fields sf JOIN services s ON s.id = sf.service_id WHERE s.name = 'Certificate of Good Moral Character' AND sf.field_key IN ('years_of_residency', 'requesting_organization');
DELETE sf FROM service_fields sf JOIN services s ON s.id = sf.service_id WHERE s.name = 'Business-related Request' AND sf.field_key IN ('nature_of_business');

-- Barangay Clearance
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'age', 'Age', 'number', 1, NULL, 1, 120, NULL, 10
FROM services s WHERE s.name = 'Barangay Clearance';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Age', sf.field_type = 'number', sf.options = NULL, sf.min_value = 1, sf.max_value = 120, sf.help_text = NULL, sf.sort_order = 10
WHERE s.name = 'Barangay Clearance' AND sf.field_key = 'age';
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'gender', 'Gender', 'select', 1, 'Male\nFemale', NULL, NULL, NULL, 20
FROM services s WHERE s.name = 'Barangay Clearance';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Gender', sf.field_type = 'select', sf.options = 'Male\nFemale', sf.min_value = NULL, sf.max_value = NULL, sf.help_text = NULL, sf.sort_order = 20
WHERE s.name = 'Barangay Clearance' AND sf.field_key = 'gender';
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'civil_status', 'Civil status', 'select', 1, 'Single\nMarried\nWidowed\nSeparated', NULL, NULL, NULL, 30
FROM services s WHERE s.name = 'Barangay Clearance';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Civil status', sf.field_type = 'select', sf.options = 'Single\nMarried\nWidowed\nSeparated', sf.min_value = NULL, sf.max_value = NULL, sf.help_text = NULL, sf.sort_order = 30
WHERE s.name = 'Barangay Clearance' AND sf.field_key = 'civil_status';

-- Loan Clearance
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'age', 'Age', 'number', 1, NULL, 1, 120, NULL, 10
FROM services s WHERE s.name = 'Loan Clearance';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Age', sf.field_type = 'number', sf.options = NULL, sf.min_value = 1, sf.max_value = 120, sf.help_text = NULL, sf.sort_order = 10
WHERE s.name = 'Loan Clearance' AND sf.field_key = 'age';
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'gender', 'Gender', 'select', 1, 'Male\nFemale', NULL, NULL, NULL, 20
FROM services s WHERE s.name = 'Loan Clearance';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Gender', sf.field_type = 'select', sf.options = 'Male\nFemale', sf.min_value = NULL, sf.max_value = NULL, sf.help_text = NULL, sf.sort_order = 20
WHERE s.name = 'Loan Clearance' AND sf.field_key = 'gender';
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'civil_status', 'Civil status', 'select', 1, 'Single\nMarried\nWidowed\nSeparated', NULL, NULL, NULL, 30
FROM services s WHERE s.name = 'Loan Clearance';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Civil status', sf.field_type = 'select', sf.options = 'Single\nMarried\nWidowed\nSeparated', sf.min_value = NULL, sf.max_value = NULL, sf.help_text = NULL, sf.sort_order = 30
WHERE s.name = 'Loan Clearance' AND sf.field_key = 'civil_status';
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'lending_institution', 'Lending institution', 'text', 1, NULL, NULL, NULL, 'Name of the bank, cooperative, or lender.', 40
FROM services s WHERE s.name = 'Loan Clearance';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Lending institution', sf.field_type = 'text', sf.options = NULL, sf.min_value = NULL, sf.max_value = NULL, sf.help_text = 'Name of the bank, cooperative, or lender.', sf.sort_order = 40
WHERE s.name = 'Loan Clearance' AND sf.field_key = 'lending_institution';

-- Certificate of Residency
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'age', 'Age', 'number', 1, NULL, 1, 120, NULL, 10
FROM services s WHERE s.name = 'Certificate of Residency';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Age', sf.field_type = 'number', sf.options = NULL, sf.min_value = 1, sf.max_value = 120, sf.help_text = NULL, sf.sort_order = 10
WHERE s.name = 'Certificate of Residency' AND sf.field_key = 'age';
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'gender', 'Gender', 'select', 1, 'Male\nFemale', NULL, NULL, NULL, 20
FROM services s WHERE s.name = 'Certificate of Residency';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Gender', sf.field_type = 'select', sf.options = 'Male\nFemale', sf.min_value = NULL, sf.max_value = NULL, sf.help_text = NULL, sf.sort_order = 20
WHERE s.name = 'Certificate of Residency' AND sf.field_key = 'gender';
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'civil_status', 'Civil status', 'select', 1, 'Single\nMarried\nWidowed\nSeparated', NULL, NULL, NULL, 30
FROM services s WHERE s.name = 'Certificate of Residency';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Civil status', sf.field_type = 'select', sf.options = 'Single\nMarried\nWidowed\nSeparated', sf.min_value = NULL, sf.max_value = NULL, sf.help_text = NULL, sf.sort_order = 30
WHERE s.name = 'Certificate of Residency' AND sf.field_key = 'civil_status';
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'years_of_residency', 'Length of residency (years)', 'number', 1, NULL, 0, 120, 'Enter 0 if you have lived here for less than one year.', 40
FROM services s WHERE s.name = 'Certificate of Residency';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Length of residency (years)', sf.field_type = 'number', sf.options = NULL, sf.min_value = 0, sf.max_value = 120, sf.help_text = 'Enter 0 if you have lived here for less than one year.', sf.sort_order = 40
WHERE s.name = 'Certificate of Residency' AND sf.field_key = 'years_of_residency';

-- Certificate of Indigency
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'age', 'Age', 'number', 1, NULL, 1, 120, NULL, 10
FROM services s WHERE s.name = 'Certificate of Indigency';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Age', sf.field_type = 'number', sf.options = NULL, sf.min_value = 1, sf.max_value = 120, sf.help_text = NULL, sf.sort_order = 10
WHERE s.name = 'Certificate of Indigency' AND sf.field_key = 'age';
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'gender', 'Gender', 'select', 1, 'Male\nFemale', NULL, NULL, NULL, 20
FROM services s WHERE s.name = 'Certificate of Indigency';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Gender', sf.field_type = 'select', sf.options = 'Male\nFemale', sf.min_value = NULL, sf.max_value = NULL, sf.help_text = NULL, sf.sort_order = 20
WHERE s.name = 'Certificate of Indigency' AND sf.field_key = 'gender';

-- Certificate of Good Moral Character
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'age', 'Age', 'number', 1, NULL, 1, 120, NULL, 10
FROM services s WHERE s.name = 'Certificate of Good Moral Character';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Age', sf.field_type = 'number', sf.options = NULL, sf.min_value = 1, sf.max_value = 120, sf.help_text = NULL, sf.sort_order = 10
WHERE s.name = 'Certificate of Good Moral Character' AND sf.field_key = 'age';
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'gender', 'Gender', 'select', 1, 'Male\nFemale', NULL, NULL, NULL, 20
FROM services s WHERE s.name = 'Certificate of Good Moral Character';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Gender', sf.field_type = 'select', sf.options = 'Male\nFemale', sf.min_value = NULL, sf.max_value = NULL, sf.help_text = NULL, sf.sort_order = 20
WHERE s.name = 'Certificate of Good Moral Character' AND sf.field_key = 'gender';
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'civil_status', 'Civil status', 'select', 1, 'Single\nMarried\nWidowed\nSeparated', NULL, NULL, NULL, 30
FROM services s WHERE s.name = 'Certificate of Good Moral Character';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Civil status', sf.field_type = 'select', sf.options = 'Single\nMarried\nWidowed\nSeparated', sf.min_value = NULL, sf.max_value = NULL, sf.help_text = NULL, sf.sort_order = 30
WHERE s.name = 'Certificate of Good Moral Character' AND sf.field_key = 'civil_status';

-- Business-related Request
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'business_name', 'Business name', 'text', 1, NULL, NULL, NULL, NULL, 10
FROM services s WHERE s.name = 'Business-related Request';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Business name', sf.field_type = 'text', sf.options = NULL, sf.min_value = NULL, sf.max_value = NULL, sf.help_text = NULL, sf.sort_order = 10
WHERE s.name = 'Business-related Request' AND sf.field_key = 'business_name';
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'business_address', 'Business address', 'text', 1, NULL, NULL, NULL, 'Street and building, inside the barangay.', 20
FROM services s WHERE s.name = 'Business-related Request';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Business address', sf.field_type = 'text', sf.options = NULL, sf.min_value = NULL, sf.max_value = NULL, sf.help_text = 'Street and building, inside the barangay.', sf.sort_order = 20
WHERE s.name = 'Business-related Request' AND sf.field_key = 'business_address';
INSERT IGNORE INTO service_fields (service_id, field_key, label, field_type, is_required, options, min_value, max_value, help_text, sort_order)
SELECT s.id, 'request_kind', 'Nature of business', 'select', 1, 'New\nRenewal', NULL, NULL, NULL, 30
FROM services s WHERE s.name = 'Business-related Request';
UPDATE service_fields sf JOIN services s ON s.id = sf.service_id SET sf.label = 'Nature of business', sf.field_type = 'select', sf.options = 'New\nRenewal', sf.min_value = NULL, sf.max_value = NULL, sf.help_text = NULL, sf.sort_order = 30
WHERE s.name = 'Business-related Request' AND sf.field_key = 'request_kind';

