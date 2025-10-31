CREATE TABLE llx_c_sellyoursaas_source_choice (
     rowid INT(11) AUTO_INCREMENT PRIMARY KEY,
     code VARCHAR(255) NOT NULL,
     label VARCHAR(255) NOT NULL,
     pos INT(11) DEFAULT 0,
     active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;
