<?php
/* Copyright (C) 2006-2020 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */

/**
 *	    \file       htdocs/core/lib/dolicloud.lib.php
 *		\brief      Some functions for module Sell-Your-Saas
 */

/**
 * getNextInstanceInChain
 *
 * @param 	Contrat  		$object  	Instance
 * @return 	Contrat|null				Next contract if found
 */
function getNextInstanceInChain($object)
{
	global $db;

	$contract = null;

	$sql="SELECT fk_object FROM ".MAIN_DB_PREFIX."contrat_extrafields WHERE cookieregister_previous_instance = '".$db->escape($object->ref_customer)."'";

	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		if ($obj && $obj->fk_object > 0) {
			$contract = new Contrat($db);
			$contract->fetch($obj->fk_object);
		}
	} else {
		dol_print_error($db);
	}
	return $contract;
}

/**
 * getPreviousInstanceInChain
 *
 * @param 	Contrat   		$object     Instance
 * @return 	Contrat|null				Previous contract if found
 */
function getPreviousInstanceInChain($object)
{
	global $db;

	if (empty($object->array_options['options_cookieregister_previous_instance'])) return null;

	$contract = new Contrat($db);
	$result = $contract->fetch(0, '', $object->array_options['options_cookieregister_previous_instance']);		// $object->array_options['options_cookieregister_previous_instance'] is ref_customer of previous instance
	if ($result > 0) return $contract;
	else return null;
}

/**
 * getListOfInstances
 *
 * @param	Contrat 	$object    	Instance
 * @return	array					Array of instances
 */
function getListOfInstancesInChain($object)
{
	global $conf, $langs, $user, $db;

	$MAXPROTECTION = 100;

	$arrayofinstances = array();
	$arrayofinstances[$object->id] = $object;

	// Get next contracts
	$nextcontract = getNextInstanceInChain($object);
	if ($nextcontract) {
		$arrayofinstances[$nextcontract->id] = $nextcontract;
	}
	$i = 0;
	while ($nextcontract && $i < $MAXPROTECTION) {
		$i++;
		$nextcontract = getNextInstanceInChain($nextcontract);
		if ($nextcontract) {
			if (!array_key_exists($nextcontract->id, $arrayofinstances)) {
				$arrayofinstances[$nextcontract->id] = $nextcontract;
			}
		}
	}
	if ($i == $MAXPROTECTION) {
		dol_syslog("getNextInstanceInChain We reach loop of ".$MAXPROTECTION, LOG_WARNING);
	}

	// Get previous contracts
	$previouscontract = getPreviousInstanceInChain($object);
	if ($previouscontract) {
		$arrayofinstances[$previouscontract->id] = $previouscontract;
	}
	$i = 0;
	while ($previouscontract && $i < $MAXPROTECTION) {
		$i++;
		$previouscontract = getPreviousInstanceInChain($previouscontract);
		if ($previouscontract) {
			if (!array_key_exists($previouscontract->id, $arrayofinstances)) {
				$arrayofinstances[$previouscontract->id] = $previouscontract;
			}
		}
	}
	if ($i == $MAXPROTECTION) {
		dol_syslog("getPreviousInstanceInChain We reach loop of ".$MAXPROTECTION, LOG_WARNING);
	}

	$arrayofinstances = dol_sort_array($arrayofinstances, 'date_creation', 'asc');
	return $arrayofinstances;
}


/**
 * getListOfLinks
 *
 * @param	Object 	$object            	Object
 * @param	string 	$lastloginadmin    	Last login admin
 * @param	string 	$lastpassadmin     	Last pass admin
 * @return	string						HTML content with links
 */
function getListOfLinks($object, $lastloginadmin, $lastpassadmin)
{
	global $db, $conf, $langs, $user;

	// Define links
	$links='';

	// Get the main application/service of contract
	include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
	dol_include_once('sellyoursaas/class/packages.class.php');
	dol_include_once('sellyoursaas/lib/sellyoursaas.lib.php');

	$dataofcontract = sellyoursaasGetExpirationDate($object, 0);
	$tmpproduct = new Product($db);
	$tmppackage = new Packages($db);

	if ($dataofcontract['appproductid'] > 0) {
		$tmpproduct->fetch($dataofcontract['appproductid']);
		$tmppackage->fetch($tmpproduct->array_options['options_package']);
	}

	//if (empty($conf->global->DOLICLOUD_EXT_HOME)) $links='Error: DOLICLOUD_EXT_HOME not defined<br>';
	$domainforapp=$object->hostname_os;
	if (preg_match('/^[0-9\.]$/', $domainforapp)) $domainforapp=$object->ref_customer;	// If this is an ip, we use the ref_customer.

	// Application instance url
	if (empty($lastpassadmin)) {
		if (! empty($object->array_options['options_deployment_init_adminpass'])) {
			$url='https://'.$object->ref_customer.'?username='.$lastloginadmin.'&amp;password='.$object->array_options['options_deployment_init_adminpass'];
			$link='<a class="wordwrap" href="'.$url.'" target="_blank" id="dollink">'.$url.'</a>';
			$links.='Link to application (initial install pass) : ';
		} else {
			$url='https://'.$object->ref_customer.'?username='.$lastloginadmin;
			$link='<a class="wordwrap" href="'.$url.'" target="_blank" id="dollink">'.$url.'</a>';
			$links.='Link to application : ';
		}
	} else {
		$url='https://'.$object->ref_customer.'?username='.$lastloginadmin.'&amp;password='.$lastpassadmin;
		$link='<a class="wordwrap" href="'.$url.'" target="_blank" id="dollink">'.$url.'</a>';
		$links.='Link to application (last logged admin) : ';
	}
	$links.=$link.'<br>';

	$links.='<br>';

	// Dashboard
	$url='';
	$thirdparty = null;
	if (get_class($object) == 'Societe') $thirdparty = $object;
	elseif (is_object($object->thirdparty)) $thirdparty = $object->thirdparty;
	if ($user->admin && is_object($thirdparty) && (! empty($thirdparty->array_options['options_dolicloud']))) {
		$urlmyaccount = $conf->global->SELLYOURSAAS_ACCOUNT_URL;
		if (! empty($thirdparty->array_options['options_domain_registration_page'])
			&& $thirdparty->array_options['options_domain_registration_page'] != $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME) {
			$constforaltname = $thirdparty->array_options['options_domain_registration_page'];
			$newurlkey = 'SELLYOURSAAS_ACCOUNT_URL-'.$constforaltname;
			if (! empty($conf->global->$newurlkey)) {
				$urlmyaccount = $conf->global->$newurlkey;
			} else {
				$urlmyaccount = preg_replace('/'.$conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'/', $thirdparty->array_options['options_domain_registration_page'], $urlmyaccount);
			}
		}
		$dol_login_hash=dol_hash($conf->global->SELLYOURSAAS_KEYFORHASH.$thirdparty->email.dol_print_date(dol_now(), 'dayrfc'), 5);	// hash is valid one hour
		$url=$urlmyaccount.'?mode=logout_dashboard&password=&username='.$thirdparty->email.'&login_hash='.$dol_login_hash;	// Note that password may have change and not being the one of dolibarr admin user
	}
	$link='<a class="wordwrap" href="'.$url.'" target="_blank" id="dashboardlink">'.$url.'</a>';
	$links.='Link to customer dashboard : ';
	$links.=$link.'<br>';

	$links.='<br>';

	$ispaid = sellyoursaasIsPaidInstance($object);

	// Home
	//$homestring=$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->username_os.'/'.preg_replace('/_([a-zA-Z0-9]+)$/','',$object->database_db);
	$homestring=$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->username_os;
	$links.='Home dir: ';
	$links.='<input type="text" name="homestring" id="homestring" value="'.$homestring.'" class="maxwidth250"> ';
	if ($conf->use_javascript_ajax) $links.=ajax_autoselect('homestring');
	//$links.='<br>';

	// BackupDir
	$backupstring=$conf->global->DOLICLOUD_BACKUP_PATH.'/'.$object->username_os;
	$links .= ' &nbsp; ';
	$links .= ' &nbsp; ';
	$links.='Backup dir: ';
	$links.='<input type="text" name="backupstring" id="backupstring" value="'.$backupstring.'" class="maxwidth250"> ';
	if ($conf->use_javascript_ajax) $links.=ajax_autoselect('backupstring');
	//$links.='<br>';

	// ArchiveDir
	$archivestring = $conf->global->SELLYOURSAAS_TEST_ARCHIVES_PATH.'/'.$object->username_os;
	$archivestringwithdb = $archivestring.'/'.preg_replace('/_([a-zA-Z0-9]+)$/', '', $object->database_db);
	if ($ispaid) {
		$archivestring = $conf->global->SELLYOURSAAS_PAID_ARCHIVES_PATH.'/'.$object->username_os;
		$archivestringwithdb = $archivestring.'/'.preg_replace('/_([a-zA-Z0-9]+)$/', '', $object->database_db);
	}
	$links .= ' &nbsp; ';
	$links .= ' &nbsp; ';
	$links .= 'Archive dir: ';
	$links .= '<input type="text" name="archivestring" id="archivestring" value="'.$archivestring.'" class="maxwidth250"><br>';
	if ($conf->use_javascript_ajax) $links .= ajax_autoselect('archivestring');

	// User and Password
	$userstring=$object->username_os;
	$links.=$langs->trans("User").': ';
	$links.='<input type="text" name="userstring" id="userstring" value="'.$userstring.'" class="maxwidth200">';
	if ($conf->use_javascript_ajax) $links.=ajax_autoselect('userstring');
	$links .= ' &nbsp; ';
	$links .= $langs->trans("Password").': ';
	$links.='<input type="text" name="sshpassword" id="sshpassword" value="'.$object->password_os.'" class="maxwidth200">';
	if ($conf->use_javascript_ajax) $links.=ajax_autoselect('sshpassword');
	$links.='<br>'."\n";

	$links.='<br>'."\n";

	// SSH
	$sshconnectstring='ssh '.$object->username_os.'@'.$object->hostname_os;
	$links.='<span class="fa fa-terminal"></span> SSH connect string: ';
	$links.='<input type="text" name="sshconnectstring" id="sshconnectstring" value="'.$sshconnectstring.'" class="maxwidth250">';
	if ($conf->use_javascript_ajax) $links.=ajax_autoselect('sshconnectstring');
	$links.=' &nbsp; '.$langs->trans("or").' SU: ';
	$sustring='su '.$object->username_os;
	$links.='<input type="text" name="sustring" id="sustring" value="'.$sustring.'" class="maxwidth200">';
	if ($conf->use_javascript_ajax) $links.=ajax_autoselect('sustring');
	$links.='<br>';
	//$links.='<br>';

	// SFTP
	//$sftpconnectstring=$object->username_os.':'.$object->password_web.'@'.$object->hostname_os.$conf->global->DOLICLOUD_EXT_HOME.'/'.$object->username_os.'/'.preg_replace('/_([a-zA-Z0-9]+)$/','',$object->database_db);
	$sftpconnectstring='sftp://'.$object->username_os.'@'.$object->hostname_os.'/'.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->username_os.'/'.preg_replace('/_([a-zA-Z0-9]+)$/', '', $object->database_db);
	$links.='<span class="fa fa-terminal"></span> SFTP connect string: ';
	$links.='<input type="text" name="sftpconnectstring" id="sftpconnectstring" value="'.$sftpconnectstring.'"><br>';
	if ($conf->use_javascript_ajax) $links.=ajax_autoselect('sftpconnectstring');
	//$links.='<br>';

	// MySQL
	$mysqlconnectstring='mysql -A -C -u '.$object->username_db.' -p\''.$object->password_db.'\' -h '.$object->hostname_db.' -D '.$object->database_db;
	$links.='<span class="fa fa-database"></span> Mysql connect string: ';
	$links.='<input type="text" name="mysqlconnectstring" id="mysqlconnectstring" value="'.$mysqlconnectstring.'"><br>';
	if ($conf->use_javascript_ajax) $links.=ajax_autoselect('mysqlconnectstring');

	// MySQL backup
	/*$mysqlconnectstring='mysqldump -A -u '.$object->username_db.' -p\''.$object->password_db.'\' -h '.$object->hostname_db.' -D '.$object->database_db;
	$links.='Mysql connect string: ';
	$links.='<input type="text" name="mysqlconnectstring" value="'.$mysqlconnectstring.'" size="110"><br>';*/

	// JDBC
	$jdbcconnectstring='jdbc:mysql://'.$object->hostname_db.'/';
	//$jdbcconnectstring.=$object->database_db;
	$links.='<span class="fa fa-database"></span> JDBC connect string: ';
	$links.='<input type="text" name="jdbcconnectstring" id="jdbcconnectstring" value="'.$jdbcconnectstring.'"><br>';
	if ($conf->use_javascript_ajax) $links.=ajax_autoselect('jdbcconnectstring');

	$links.='<br>';
	$links.='<br>';

	$dirforexampleforsources = preg_replace('/__DOL_DATA_ROOT__/', DOL_DATA_ROOT, preg_replace('/\/htdocs\/?$/', '', $tmppackage->srcfile1));

	$upgradestring=$conf->global->DOLICLOUD_SCRIPTS_PATH.'/rsync_instance.php '.$dirforexampleforsources.' '.$object->hostname_os;

	$purgestring=DOL_DATA_ROOT.'/../dev/initdata/purge-data.php test (all|option) (all|YYYY-MM-DD) mysqli '.$object->hostname_db.' '.$object->username_db.' '.$object->password_db.' '.$object->database_db.' '.($object->database_port?$object->database_port:3306);

	// Mysql Backup
	$mysqlbackupcommand='mysqldump -C -u '.$object->username_db.' -p\''.$object->password_db.'\' -h '.$object->hostname_db.' '.$object->database_db.' > '.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->username_os.'/'.preg_replace('/_([a-zA-Z0-9]+)$/', '', $object->database_db).'/documents/admin/backup/mysqldump_'.$object->database_db.'_'.dol_print_date(dol_now(), 'dayhourlog').'.sql';
	$links.='<span class="fa fa-database"></span> ';
	$links.='Mysql backup database:<br>';
	$links.='<input type="text" id="mysqlbackupcommand" name="mysqlbackupcommand" value="'.$mysqlbackupcommand.'" class="marginleftonly quatrevingtpercent"><br>';
	if ($conf->use_javascript_ajax) $links.=ajax_autoselect("mysqlbackupcommand", 0);
	$links.='<br>';

	// Mysql to Restore a dump
	//$mysqlresotrecommand='mysql -C -A -u '.$object->username_db.' -p\''.$object->password_db.'\' -h '.$object->hostname_db.' -D '.$object->database_db.' < '.$conf->global->DOLICLOUD_INSTANCES_PATH.'/'.$object->username_os.'/'.preg_replace('/_([a-zA-Z0-9]+)$/', '', $object->database_db).'/documents/admin/backup/filetorestore.sql';
	$mysqlresotrecommand='mysql -C -A -u '.$object->username_db.' -p\''.$object->password_db.'\' -h '.$object->hostname_db.' -D '.$object->database_db.' < filetorestore.sql';
	$links.='<span class="fa fa-database"></span> ';
	$links.='Mysql restore database:<br>';
	$links.='<input type="text" id="mysqlrestorecommand" name="mysqlrestorecommand" value="'.$mysqlresotrecommand.'" class="marginleftonly quatrevingtpercent"><br>';
	if ($conf->use_javascript_ajax) $links.=ajax_autoselect("mysqlrestorecommand", 0);
	$links.='<br>';

	// Rsync to Restore Program directory
	$sftprestorestring='rsync -n -v -a --exclude \'conf.php\' --exclude \'*.cache\' htdocs/* '.$object->username_os.'@'.$object->hostname_os.':'.$object->database_db.'/';
	$links.='<span class="fa fa-terminal"></span> ';
	$links.='Rsync to copy/overwrite application dir';
	$links.='<span class="opacitymedium"> (remove -n to execute really)</span>:<br>';
	$links.='<input type="text" id="sftprestoreappstring" name="sftprestoreappstring" value="'.$sftprestorestring.'" class="marginleftonly quatrevingtpercent"><br>';
	if ($conf->use_javascript_ajax) $links.=ajax_autoselect("sftprestoreappstring", 0);
	$links.='<br>';

	// Rsync to Restore Document directory
	$sftprestorestring='rsync -n -v -a --exclude \'*.cache\' documents/* '.$object->username_os.'@'.$object->hostname_os.':'.$object->database_db.'/documents';
	$links.='<span class="fa fa-terminal"></span> ';
	$links.='Rsync to copy/overwrite document dir';
	$links.='<span class="opacitymedium"> (remove -n to execute really)</span>:<br>';
	$links.='<input type="text" id="sftprestorestring" name="sftprestorestring" value="'.$sftprestorestring.'" class="marginleftonly quatrevingtpercent"><br>';
	if ($conf->use_javascript_ajax) $links.=ajax_autoselect("sftprestorestring", 0);
	$links.='<br>';

	// Rsync to Deploy module
	$sftpdeploystring='rsync -n -v -a --exclude \'*.cache\' --exclude \'conf\.php\' pathtohtdocsofmodule/* '.$object->username_os.'@'.$object->hostname_os.':'.$object->database_db.'/htdocs/custom/namemodule';
	$links.='<span class="fa fa-terminal"></span> ';
	$links.='Rsync to install or overwrite module';
	$links.='<span class="opacitymedium"> (remove -n to execute really)</span>:<br>';
	$links.='<input type="text" id="sftpdeploystring" name="sftpdeploystring" value="'.$sftpdeploystring.'" class="marginleftonly quatrevingtpercent"><br>';
	if ($conf->use_javascript_ajax) $links.=ajax_autoselect("sftpdeploystring", 0);
	$links.='<br>';

	// Upgrade link
	$upgradestringtoshow=$upgradestring.' test';
	$links.='<span class="fa fa-arrow-up"></span> ';
	$links.='Upgrade version line string';
	$links.='<span class="opacitymedium"> (remplacer "test" par "confirmunlock" pour exécuter réellement)</span><br>';
	$links.='<input type="text" id="upgradestring" name="upgradestring" value="'.$upgradestringtoshow.'" class="marginleftonly quatrevingtpercent"><br>';
	if ($conf->use_javascript_ajax) $links.=ajax_autoselect("upgradestring", 0);
	$links.='<br>';

	// Purge data
	$purgestringtoshow=$purgestring;
	$links.='<span class="fa fa-eraser"></span> ';
	$links.='Purge command line string';
	$links.='<span class="opacitymedium"> (remplacer "test" par "confirm" pour exécuter réellement)</span><br>';
	$links.='<input type="text" id="purgestring" name="purgestring" value="'.$purgestringtoshow.'" class="marginleftonly quatrevingtpercent"><br>';
	if ($conf->use_javascript_ajax) $links.=ajax_autoselect("purgestring", 0);
	$links.='<br>';

	// Upgrade link from V1
	/*
	$migratestring='sudo '.$conf->global->DOLICLOUD_SCRIPTS_PATH.'/old_migrate_v1v2.php '.$object->instance.' '.$object->instance.' confirm';
	$links.='Migrate v1 to V2 command line string (remplacer "test" par "confirm" pour exécuter réellement)<br>';
	$links.='<input type="text" id="v1tov2" name="v1tov2" value="'.$migratestring.'" class="marginleftonly quatrevingtpercent"><br>';
	if ($conf->use_javascript_ajax) $links.=ajax_autoselect("v1tov2", 0);
	$links.='<br>';
	*/

	return $links;
}


/**
 * getvalfromkey
 *
 * @param 	DoliDb	$db		Database handler
 * @param 	string	$param	Param
 * @param	string	$val	Val
 * @return	string			Value
 */
function getvalfromkey($db, $param, $val)
{
	$sql ="select ".$param." as val from dolicloud_saasplex.app_instance, dolicloud_saasplex.customer, dolicloud_saasplex.address, dolicloud_saasplex.country_region";
	$sql.=" where dolicloud_saasplex.address.country_id=dolicloud_saasplex.country_region.id AND";
	$sql.=" dolicloud_saasplex.customer.address_id=dolicloud_saasplex.address.id AND dolicloud_saasplex.app_instance.customer_id = dolicloud_saasplex.customer.id AND dolicloud_saasplex.customer.org_name = '".$db->escape($val)."'";
	//print $sql;
	$resql=$db->query($sql);
	if ($resql) {
		$obj=$db->fetch_object($resql);
		$ret=$obj->val;
	} else {
		dol_print_error($db, 'Failed to get key sql='.$sql);
	}
	return $ret;
}


/**
 * Prepare array with list of tabs
 *
 * @param   Object	$object		Object related to tabs
 * @param   string  $prefix     Prefix
 * @return  array				Array of tabs to shoc
 */
function dolicloud_prepare_head($object, $prefix = '')
{
	global $langs, $conf;

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/sellyoursaas/backoffice/instance_links'.$prefix.'.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("UsefulLinks");
	$head[$h][2] = 'upgrade';
	$h++;

	$head[$h][0] = dol_buildpath('/sellyoursaas/backoffice/instance_users'.$prefix.'.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("Users");
	$head[$h][2] = 'users';
	$h++;

	$head[$h][0] = dol_buildpath('/sellyoursaas/backoffice/instance_backup'.$prefix.'.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("Backup");
	$head[$h][2] = 'backup';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	// $this->tabs = array('entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to add new tab
	// $this->tabs = array('entity:-tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to remove a tab
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'contact');

	/*
	$head[$h][0] = dol_buildpath('/sellyoursaas/backoffice/dolicloud_info.php',1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("Info");
	$head[$h][2] = 'info';
	$h++;
	*/

	return $head;
}

/**
 * Check if Windows system
 *
 * @return boolean  true or false
 */
function is_windows()
{
	return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}

/**
 * Check if shell command exists and executable.
 * This function must be used only for command line PHP. The method shell_exec should not available in web context with recommended setup.
 *
 * @param   string  $command    Name of shell command (eg zstd)
 * @return  boolean             true or false
 */
function command_exists($command)
{
	$test = is_windows() ? "where" : "which";
	return is_executable(trim(shell_exec("$test $command")));
}
