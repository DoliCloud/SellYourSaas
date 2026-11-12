-- Unique index on dictionary code
ALTER TABLE llx_c_sellyoursaas_source_choice ADD UNIQUE INDEX uk_c_sellyoursaas_source_choice_code (code);
