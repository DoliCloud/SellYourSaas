
-- Script run during a migration of Dolibarr

ALTER TABLE llx_packages ADD COLUMN cliafterpaid text;
ALTER TABLE llx_packages ADD COLUMN sqlafterpaid text;
