-- Copyright (C) ---Put here your own copyright and developer email---
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see http://www.gnu.org/licenses/.


CREATE TABLE llx_packages(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	ref varchar(64) NOT NULL, 
	entity integer DEFAULT 1 NOT NULL, 
	label varchar(255), 
	restrict_domains varchar(255),
	date_creation datetime NOT NULL, 
	tms timestamp NOT NULL, 
	fk_user_creat integer NOT NULL, 
	fk_user_modif integer, 
	import_key varchar(14),
	sqldump varchar(255), 
	srcfile1 varchar(255), 
	srcfile2 varchar(255), 
	srcfile3 varchar(255), 
	targetsrcfile1 varchar(255),
	targetsrcfile2 varchar(255),
	targetsrcfile3 varchar(255),
	conffile1 text, 
	targetconffile1 text, 
	datafile1 varchar(255), 
	cliafter text, 
	cliafterpaid text, 
	sqlafter text,
	sqlpasswordreset text,
	sqlafterpaid text,
	crontoadd text,
	version_formula text,
	allowoverride varchar(255), 
	status integer,
	register_text varchar(255),
	note_public text,
	note_private text
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;
