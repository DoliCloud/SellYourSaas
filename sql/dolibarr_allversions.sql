
-- Script run during a migration of Dolibarr

ALTER TABLE llx_packages ADD COLUMN cliafterpaid text;
ALTER TABLE llx_packages ADD COLUMN sqlafterpaid text;



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


ALTER TABLE llx_sellyoursaas_blacklistip ADD COLUMN date_use datetime;
ALTER TABLE llx_sellyoursaas_blacklistip ADD COLUMN comment varchar(255);
ALTER TABLE llx_sellyoursaas_whitelistip ADD COLUMN comment varchar(255);

ALTER TABLE llx_sellyoursaas_blacklistdir ADD COLUMN noblacklistif varchar(255);

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




