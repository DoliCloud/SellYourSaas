-- Script run during a migration of Dolibarr
--
-- To restrict request to Mysql version x.y minimum use -- VMYSQLx.y
-- To restrict request to Pgsql version x.y minimum use -- VPGSQLx.y
-- To rename a table:       ALTER TABLE llx_table RENAME TO llx_table_new;
-- To add a column:         ALTER TABLE llx_table ADD COLUMN newcol varchar(60) NOT NULL DEFAULT '0' AFTER existingcol;
-- To rename a column:      ALTER TABLE llx_table CHANGE COLUMN oldname newname varchar(60);
-- To drop a column:        ALTER TABLE llx_table DROP COLUMN oldname;
-- To change type of field: ALTER TABLE llx_table MODIFY COLUMN name varchar(60);
-- To drop a foreign key:   ALTER TABLE llx_table DROP FOREIGN KEY fk_name;
-- To create a unique index ALTER TABLE llx_table ADD UNIQUE INDEX uk_table_field (field);
-- To drop an index:        -- VMYSQL4.1 DROP INDEX nomindex on llx_table;
-- To drop an index:        -- VPGSQL8.2 DROP INDEX nomindex;
-- To make pk to be auto increment (mysql):
-- -- VMYSQL4.3 ALTER TABLE llx_table ADD PRIMARY KEY(rowid);
-- -- VMYSQL4.3 ALTER TABLE llx_table CHANGE COLUMN rowid rowid INTEGER NOT NULL AUTO_INCREMENT;
-- To make pk to be auto increment (postgres):
-- -- VPGSQL8.2 CREATE SEQUENCE llx_table_rowid_seq OWNED BY llx_table.rowid;
-- -- VPGSQL8.2 ALTER TABLE llx_table ADD PRIMARY KEY (rowid);
-- -- VPGSQL8.2 ALTER TABLE llx_table ALTER COLUMN rowid SET DEFAULT nextval('llx_table_rowid_seq');
-- -- VPGSQL8.2 SELECT setval('llx_table_rowid_seq', MAX(rowid)) FROM llx_table;
-- To set a field as NULL:                     -- VMYSQL4.3 ALTER TABLE llx_table MODIFY COLUMN name varchar(60) NULL;
-- To set a field as NULL:                     -- VPGSQL8.2 ALTER TABLE llx_table ALTER COLUMN name DROP NOT NULL;
-- To set a field as NOT NULL:                 -- VMYSQL4.3 ALTER TABLE llx_table MODIFY COLUMN name varchar(60) NOT NULL;
-- To set a field as NOT NULL:                 -- VPGSQL8.2 ALTER TABLE llx_table ALTER COLUMN name SET NOT NULL;
-- To set a field as default NULL:             -- VPGSQL8.2 ALTER TABLE llx_table ALTER COLUMN name SET DEFAULT NULL;
-- Note: fields with type BLOB/TEXT can't have default value.
-- To rebuild sequence for postgresql after insert by forcing id autoincrement fields:
-- -- VPGSQL8.2 SELECT dol_util_rebuild_sequences();



ALTER TABLE llx_packages ADD COLUMN cliafterpaid text;
ALTER TABLE llx_packages ADD COLUMN sqlafterpaid text;
ALTER TABLE llx_packages ADD COLUMN sqlpasswordreset text;



CREATE TABLE llx_sellyoursaas_blacklistcontent(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	entity integer DEFAULT 1 NOT NULL, 
	content varchar(128) NOT NULL, 
	date_creation datetime NOT NULL, 
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
	status integer DEFAULT 1 NOT NULL
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;


CREATE TABLE llx_sellyoursaas_blacklistfrom(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	entity integer DEFAULT 1 NOT NULL, 
	content varchar(128) NOT NULL, 
	date_creation datetime NOT NULL, 
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
	status integer DEFAULT 1 NOT NULL
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;



CREATE TABLE llx_sellyoursaas_blacklistip(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	entity integer DEFAULT 1 NOT NULL, 
	content varchar(128) NOT NULL, 
	date_creation datetime NOT NULL, 
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
	status integer DEFAULT 1 NOT NULL
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;



CREATE TABLE llx_sellyoursaas_blacklistmail(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	entity integer DEFAULT 1 NOT NULL, 
	content text NOT NULL, 
	date_creation datetime NOT NULL, 
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
	status integer DEFAULT 1 NOT NULL
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;


CREATE TABLE llx_sellyoursaas_blacklistto(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	entity integer DEFAULT 1 NOT NULL, 
	content varchar(128) NOT NULL, 
	date_creation datetime NOT NULL, 
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
	status integer DEFAULT 1 NOT NULL
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;



CREATE TABLE llx_sellyoursaas_blacklistdir(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	entity integer DEFAULT 1 NOT NULL, 
	content varchar(128) NOT NULL, 
	noblacklistif varchar(255), 
	date_creation datetime NOT NULL, 
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
	status integer DEFAULT 1 NOT NULL
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;

CREATE TABLE llx_sellyoursaas_deploymentserver(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	ref varchar(128) NOT NULL,
	entity integer DEFAULT 1 NOT NULL,  -- multi company id
	note_private text, 
	note_public text, 
	date_creation datetime NOT NULL,
	date_modification timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	status integer DEFAULT 0 NOT NULL,
	fk_country integer,
	fromdomainname varchar(128),
	ipaddress varchar(128) NOT NULL,
	servercountries text,
	servercustomerannounce text,
	servercustomerannouncestatus integer
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;

ALTER TABLE llx_sellyoursaas_blacklistip ADD COLUMN date_use datetime;
ALTER TABLE llx_sellyoursaas_blacklistip ADD COLUMN comment varchar(255);
ALTER TABLE llx_sellyoursaas_whitelistip ADD COLUMN comment varchar(255);

ALTER TABLE llx_sellyoursaas_blacklistdir ADD COLUMN noblacklistif varchar(255);

ALTER TABLE llx_sellyoursaas_deploymentserver ADD COLUMN servercountries text;

ALTER TABLE llx_sellyoursaas_blacklistdir DROP INDEX idx_sellyoursaas_blacklistto_content;
ALTER TABLE llx_sellyoursaas_blacklistto DROP INDEX idx_sellyoursaas_blacklistto_content;
ALTER TABLE llx_sellyoursaas_blacklistfrom DROP INDEX idx_sellyoursaas_blacklistfrom_content;
ALTER TABLE llx_sellyoursaas_blacklistip DROP INDEX idx_sellyoursaas_blacklistip_content;
ALTER TABLE llx_sellyoursaas_whitelistip DROP INDEX idx_sellyoursaas_whitelistip_content;

ALTER TABLE llx_sellyoursaas_blacklistcontent ADD INDEX idx_sellyoursaas_blacklistcontent_date_creation (date_creation);
ALTER TABLE llx_sellyoursaas_blacklistmail ADD INDEX idx_sellyoursaas_blacklistmail_date_creation (date_creation);

ALTER TABLE llx_sellyoursaas_blacklistdir ADD UNIQUE INDEX uk_sellyoursaas_blacklistdir_content (content);
ALTER TABLE llx_sellyoursaas_blacklistto ADD UNIQUE INDEX uk_sellyoursaas_blacklistto_content (content);
ALTER TABLE llx_sellyoursaas_blacklistfrom ADD UNIQUE INDEX uk_sellyoursaas_blacklistfrom_content (content);
ALTER TABLE llx_sellyoursaas_blacklistip ADD UNIQUE INDEX uk_sellyoursaas_blacklistip_content (content);
ALTER TABLE llx_sellyoursaas_whitelistip ADD UNIQUE INDEX uk_sellyoursaas_whitelistip_content (content);



ALTER TABLE llx_dolicloud_stats RENAME TO llx_sellyoursaas_stats;

ALTER TABLE llx_sellyoursaas_deploymentserver ADD COLUMN fk_user_modif integer;
ALTER TABLE llx_sellyoursaas_deploymentserver ADD COLUMN note_public text;

