-- Copyright (C) Laurent Destailleur <eldy@users.sourceforge.net>
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


CREATE TABLE llx_sellyoursaas_clean_dbnotindisk (
  nom varchar(128) DEFAULT NULL,
  client tinyint(4) DEFAULT 0,
  rowid int(11) NOT NULL DEFAULT 0,
  ref varchar(50) DEFAULT NULL,
  ref_customer varchar(128) DEFAULT NULL,
  deployment_date_start datetime DEFAULT NULL,
  undeployment_date datetime DEFAULT NULL,
  deployment_host varchar(128) DEFAULT NULL,
  deployment_ip varchar(128) DEFAULT NULL
) ENGINE=innodb;

