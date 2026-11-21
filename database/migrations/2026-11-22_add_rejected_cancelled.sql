-- Run this ONCE on an existing BarangayServe database (phpMyAdmin > barangayserve > SQL tab).
-- It adds the two new statuses "Rejected" and "Cancelled". Existing data is not changed.
-- A fresh install does not need it: database/barangayserve.sql already includes it.

ALTER TABLE service_requests
  MODIFY status ENUM('Submitted','Under Review','Processing','Ready for Pickup','Completed','Rejected','Cancelled')
  NOT NULL DEFAULT 'Submitted';

ALTER TABLE request_history
  MODIFY status ENUM('Submitted','Under Review','Processing','Ready for Pickup','Completed','Rejected','Cancelled')
  NOT NULL;
