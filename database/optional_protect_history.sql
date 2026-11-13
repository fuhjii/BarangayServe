-- =====================================================================
-- OPTIONAL: make request_history append-only at the database level
--
-- The BarangayServe application only ever INSERTs into request_history.
-- These two triggers add a second layer of protection: even a mistake in
-- the code (or a careless query in phpMyAdmin) cannot change or delete an
-- existing history row. This supports SRS 3.8 and 7.4 (history is kept).
--
-- Import this file AFTER barangayserve.sql. It is optional because some
-- shared hosts do not allow CREATE TRIGGER. If the import fails with a
-- privilege error, the system still works without it.
--
-- To remove the protection later:
--   DROP TRIGGER trg_request_history_no_update;
--   DROP TRIGGER trg_request_history_no_delete;
-- =====================================================================

USE barangayserve;

DROP TRIGGER IF EXISTS trg_request_history_no_update;
CREATE TRIGGER trg_request_history_no_update
  BEFORE UPDATE ON request_history
  FOR EACH ROW
  SIGNAL SQLSTATE '45000'
  SET MESSAGE_TEXT = 'request_history is append-only: existing rows cannot be changed.';

DROP TRIGGER IF EXISTS trg_request_history_no_delete;
CREATE TRIGGER trg_request_history_no_delete
  BEFORE DELETE ON request_history
  FOR EACH ROW
  SIGNAL SQLSTATE '45000'
  SET MESSAGE_TEXT = 'request_history is append-only: existing rows cannot be deleted.';
