-- Copyright (C) 2022 SuperAdmin 
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
-- along with this program.  If not, see https://www.gnu.org/licenses/.


CREATE TABLE llx_sellyoursaas_deploymentserver(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	note_private text, 
	date_creation datetime NOT NULL, 
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
	status integer NOT NULL, 
	fk_country integer, 
	domainname varchar(128) NOT NULL, 
	fromdomainname varchar(128), 
	ipaddress varchar(128) NOT NULL, 
	servercustomerannouncestatus integer, 
	servercustomerannounce text
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;
