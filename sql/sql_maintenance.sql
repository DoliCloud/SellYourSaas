
-- To move user elements onto another user
update llx_societe set fk_user_creat = newid where fk_user_creat = oldid;
update llx_societe set fk_user_modif = newid where fk_user_modif = oldid;
update llx_socpeople set fk_user_creat = newid where fk_user_creat = oldid;
update llx_socpeople set fk_user_modif = newid where fk_user_modif = oldid;


-- Convert field into utf8_general_ci
ALTER TABLE llx_contrat_extrafields MODIFY username_db VARCHAR(32) COLLATE utf8_general_ci;


-- To list opened connection
select * from dolibarr.llx_contrat_extrafields, information_schema.processlist where dolibarr.llx_contrat_extrafields.username_db = information_schema.processlist.user;
--select concat('KILL ',information_schema.processlist.id,';') from dolibarr.llx_contrat_extrafields, information_sch:qema.processlist where dolibarr.llx_contrat_extrafields.username_db = information_schema.processlist.user and TIME > 1800 into outfile '/tmp/mkill.txt';
--source /tmp/a.txt;

-- List nb of running connections
mysqladmin -uaaa -paaa -i1  extended | grep Threads_running



-- Instance with incorrect entry localhost
select * from dolibarr.llx_contrat_extrafields where username_db in (select user from mysql.user where Host = 'localhost' and password = '' and user like 'dbu%');
select * from dolibarr.llx_contrat_extrafields where username_db in (select user from mysql.db where Host = 'localhost' and user like 'dbu%');
--delete from mysql.db where Host = 'localhost' and user like 'dbu%';
--delete from mysql.user where Host = 'localhost' and password = '' and user like 'dbu%';

select * from mysql.user where user in (select user from mysql.user where Host = 'localhost' and password = '' and user like 'dbu%') order by user;

select concat("update mysql.user set password = '", u.password, "' where host = 'localhost' and user = '", u.user, "'; -- ", host, " ", name, " ", status) from mysql.user as u, dolibarr.llx_contrat_extrafields where username_db = user and u.password <> '' and user in (select user from mysql.user where Host = 'localhost' and password = '') order by user into outfile '/tmp/fixpass.txt';


-- Other
See also the documentation *Documentation SellYourSaas - Master and Deployment Servers*