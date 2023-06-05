
-- To move user elements onto another user
update llx_societe set fk_user_creat = newid where fk_user_creat = oldid;
update llx_societe set fk_user_modif = newid where fk_user_modif = oldid;
update llx_socpeople set fk_user_creat = newid where fk_user_creat = oldid;
update llx_socpeople set fk_user_modif = newid where fk_user_modif = oldid;


-- Convert field into utf8_unicode_ci
ALTER TABLE llx_contrat_extrafields MODIFY username_db VARCHAR(32) COLLATE utf8_unicode_ci;


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



-- List on recuring invoice line and contract invoice line with amount of 9 euros and qty of 1 or 2 for some languages for product 162
drop table tmp_tmp;
create table tmp_tmp AS SELECT
	DISTINCT fr.rowid as frid, s.rowid as sid,
	fdr.rowid as fdrid, fdr.qty as fdrqty, fdr.subprice as fdrsubprice,
	c.rowid as cid, cd.rowid as cdid, cd.qty as cdqty, cd.subprice as cdsubprice,
	s.email,
	s.nom as lastname,
	'' as firstname
FROM
	llx_societe as s
INNER JOIN llx_facture_rec as fr on
	fr.fk_soc = s.rowid
	and fr.suspended = 0
INNER JOIN llx_facturedet_rec as fdr on
	fdr.fk_facture = fr.rowid
INNER JOIN llx_contrat as c on
	c.fk_soc = s.rowid
INNER JOIN llx_contrat_extrafields as ce on
	ce.fk_object = c.rowid AND ce.deployment_status = 'done'
INNER JOIN llx_contratdet as cd on
	cd.fk_contrat = c.rowid
INNER JOIN llx_element_element as ee on
    ee.sourcetype = 'contrat' and ee.targettype = 'facturerec' and ee.fk_source = c.rowid and ee.fk_target = fr.rowid
WHERE
	email IS NOT NULL
	AND email <> ''
	AND (default_lang IN ('fr_FR', 'fr_BE', 'fr_CM', 'fr_CA', 'fr_CI', 'fr_GA', 'fr_NC', 'fr_CH'))
	AND fdr.qty IN (1, 2)
	AND fdr.fk_product = 162
	AND (fdr.remise_percent IS NULL
		or fdr.remise_percent = 0)
	AND fdr.subprice = 9
	AND cd.qty IN (1, 2)
	AND cd.fk_product = 162
	AND (cd.remise_percent IS NULL
		or cd.remise_percent = 0)
	AND cd.subprice = 9
ORDER BY
	frid


UPDATE llx_facturedet_rec SET subprice = 12, total_ht = 12 * qty, total_tva = 12 * qty * (tva_tx / 100), total_ttc = 12 * qty * (1 + tva_tx / 100), 
multicurrency_subprice = 12, multicurrency_total_ht = 12 * qty, multicurrency_total_tva =  12 * qty * (tva_tx / 100), multicurrency_total_ttc = 12 * qty * (1 + tva_tx / 100)
where subprice = 12 AND qty IN (1,2) AND rowid IN (SELECT fdrid from tmp_tmp);

UPDATE llx_contratdet SET subprice = 12, price_ht = 12, total_ht = 12 * qty, total_tva = 12 * qty * (tva_tx / 100), total_ttc = 12 * qty * (1 + tva_tx / 100) where subprice = 12 AND qty IN (1,2) AND rowid IN (SELECT cdid from tmp_tmp);

