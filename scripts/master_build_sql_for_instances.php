#!/usr/bin/php
<?php
/* Copyright (C) 2012-2023 Laurent Destailleur	<eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
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
 *      \file       sellyoursaas/scripts/master_build_sql_for_instances.php
 *		\ingroup    sellyoursaas
 *      \brief      Script to run from master server to generate maintenance SQL files
 */

if (!defined('NOREQUIREDB')) {
	define('NOREQUIREDB', '1');
}					// Do not create database handler $db
if (!defined('NOSESSION')) {
	define('NOSESSION', '1');
}
if (!defined('NOREQUIREVIRTUALURL')) {
	define('NOREQUIREVIRTUALURL', '1');
}

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname(__FILE__).'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
	exit;
}

// Global variables
$version='1.0';
$error=0;
$RSYNCDELETE=0;

$server=isset($argv[1]) ? $argv[1] : '';
$mode=isset($argv[2]) ? $argv[2] : '';
$instance=isset($argv[3]) ? $argv[3] : '';

@set_time_limit(0);							// No timeout for this script
define('EVEN_IF_ONLY_LOGIN_ALLOWED', 1);		// Set this define to 0 if you want to lock your script when dolibarr setup is "locked to admin user only".

// Read /etc/sellyoursaas.conf file
$masterserver='';
$databasehost='localhost';
$databaseport='3306';
$database='';
$databaseuser='sellyoursaas';
$databasepass='';
$dolibarrdir='';
$usecompressformatforarchive='gzip';
$fp = @fopen('/etc/sellyoursaas.conf', 'r');
// Add each line to an array
if ($fp) {
	$array = explode("\n", fread($fp, filesize('/etc/sellyoursaas.conf')));
	foreach ($array as $val) {
		$tmpline=explode("=", $val);
		if ($tmpline[0] == 'masterserver') {
			$masterserver = $tmpline[1];
		}
		if ($tmpline[0] == 'instanceserver') {
			$instanceserver = $tmpline[1];
		}
		if ($tmpline[0] == 'databasehost') {
			$databasehost = $tmpline[1];
		}
		if ($tmpline[0] == 'databaseport') {
			$databaseport = $tmpline[1];
		}
		if ($tmpline[0] == 'database') {
			$database = $tmpline[1];
		}
		if ($tmpline[0] == 'databaseuser') {
			$databaseuser = $tmpline[1];
		}
		if ($tmpline[0] == 'databasepass') {
			$databasepass = $tmpline[1];
		}
		if ($tmpline[0] == 'dolibarrdir') {
			$dolibarrdir = $tmpline[1];
		}
		if ($tmpline[0] == 'usecompressformatforarchive') {
			$usecompressformatforarchive = $tmpline[1];
		}
	}
} else {
	print "Failed to open /etc/sellyoursaas.conf file\n";
	exit(-1);
}

if (empty($dolibarrdir)) {
	print "Failed to find 'dolibarrdir' entry into /etc/sellyoursaas.conf file\n";
	exit(-1);
}
if (empty($masterserver)) {
	print "Failed to find 'masterserver' entry into /etc/sellyoursaas.conf file. This script must be run on master server.\n";
	print "\n";
	exit(-1);
}

// Load Dolibarr environment
$res=0;
// Try master.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) {
	$i--;
	$j--;
}
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/master.inc.php")) {
	$res=@include substr($tmp, 0, ($i+1))."/master.inc.php";
}
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/master.inc.php")) {
	$res=@include dirname(substr($tmp, 0, ($i+1)))."/master.inc.php";
}
// Try master.inc.php using relative path
if (! $res && file_exists("../master.inc.php")) {
	$res=@include "../master.inc.php";
}
if (! $res && file_exists("../../master.inc.php")) {
	$res=@include "../../master.inc.php";
}
if (! $res && file_exists("../../../master.inc.php")) {
	$res=@include "../../../master.inc.php";
}
if (! $res && file_exists(__DIR__."/../../master.inc.php")) {
	$res=@include __DIR__."/../../../master.inc.php";
}
if (! $res && file_exists(__DIR__."/../../../master.inc.php")) {
	$res=@include __DIR__."/../../../master.inc.php";
}
if (! $res && file_exists($dolibarrdir."/htdocs/master.inc.php")) {
	$res=@include $dolibarrdir."/htdocs/master.inc.php";
}
if (! $res) {
	print("Include of master fails");
	exit(-1);
}
include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once("/sellyoursaas/core/lib/sellyoursaas.lib.php");

// Read /etc/sellyoursaas.conf file
$databasehost='localhost';
$databaseport='3306';
$database='';
$databaseuser='sellyoursaas';
$databasepass='';
$fp = @fopen('/etc/sellyoursaas.conf', 'r');
// Add each line to an array
if ($fp) {
	$array = explode("\n", fread($fp, filesize('/etc/sellyoursaas.conf')));
	foreach ($array as $val) {
		$tmpline=explode("=", $val);
		if ($tmpline[0] == 'databasehost') {
			$databasehost = $tmpline[1];
		}
		if ($tmpline[0] == 'databaseport') {
			$databaseport = $tmpline[1];
		}
		if ($tmpline[0] == 'database') {
			$database = $tmpline[1];
		}
		if ($tmpline[0] == 'databaseuser') {
			$databaseuser = $tmpline[1];
		}
		if ($tmpline[0] == 'databasepass') {
			$databasepass = $tmpline[1];
		}
	}
} else {
	print "Failed to open /etc/sellyoursaas.conf file\n";
	exit;
}


/*
 *	Main
 */

if (0 == posix_getuid() && empty($_SERVER['NOROOTCHECK'])) {
	print "***** ".$script_file." (".$version.") - ".dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt')." *****\n";
	echo "Script must not be ran with root (but with the 'admin' sellyoursaas account).\n";
	exit(-1);
}

$dbmaster=getDoliDBInstance('mysqli', $databasehost, $databaseuser, $databasepass, $database, $databaseport);
if ($dbmaster->error) {
	dol_print_error($dbmaster, "host=".$databasehost.", port=".$databaseport.", user=".$databaseuser.", databasename=".$database.", ".$dbmaster->error);
	exit;
}
if ($dbmaster) {
	$conf->setValues($dbmaster);
}
if (empty($db)) {
	$db=$dbmaster;
}

if (empty($server) || empty($mode)) {
	print "***** ".$script_file." (".$version.") - ".dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt')." *****\n";
	print "This script must be ran as 'admin' user from master server.\n";
	print "Usage:   $script_file  deploymentserverip  (dbcreate|dbusercreate|dbuserresetpass|sellyoursaasuserupdate)  [instance]\n";
	print "Example: $script_file  1.2.3.4             dbuserresetpass\n";
	print "Return code: 0 if success, <>0 if error\n";
	exit(-1);
}

if (! in_array($mode, array('dbcreate', 'dbusercreate', 'dbuserresetpass', 'all', 'sellyoursaasuserupdate'))) {
	print "***** ".$script_file." (".$version.") - ".dol_print_date(dol_now('gmt'), "%Y%m%d-%H%M%S", 'gmt')." *****\n";
	print "Error: Bad value for last parameter (action must be dbcreate|usercreate|userresetpass|all).\n";
	exit(-2);
}

// Forge complete name of instance
if (! empty($instance) && ! preg_match('/\./', $instance) && ! preg_match('/\.home\.lan$/', $instance)) {
	if (empty(getDolGlobalString('SELLYOURSAAS_OBJECT_DEPLOYMENT_SERVER_MIGRATION'))) {
		$tmparray = explode(',', getDolGlobalString('SELLYOURSAAS_SUB_DOMAIN_NAMES'));
	} else {
		dol_include_once('sellyoursaas/class/deploymentserver.class.php');
		$staticdeploymentserver = new Deploymentserver($db);
		$tmparray = $staticdeploymentserver->fetchAllDomains();
	}
	$tmpstring = preg_replace('/:.*$/', '', $tmparray[0]);
	$instance = $instance.".".$tmpstring;   // Automatically concat first domain name
}


$sql = "SELECT c.rowid, c.statut";
$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c LEFT JOIN ".MAIN_DB_PREFIX."contrat_extrafields as ce ON c.rowid = ce.fk_object";
$sql.= " WHERE c.entity IN (".getEntity('contract').")";
//$sql.= " AND c.statut > 0";
if ($instance) {
	$sql.= " AND c.ref_customer = '".$dbmaster->escape($instance)."'";
}
$sql.= " AND ce.deployment_status = 'done'";
$sql.= " AND ce.deployment_host LIKE '".$dbmaster->escape($server)."'";

$resql = $dbmaster->query($sql);
if (! $resql) {
	dol_print_error($resql);
	exit(-3);
}
$num_rows = $dbmaster->num_rows($resql);

$i = 0;

if ($mode == 'userresetpass') {
	print '-- File generated by master_build_sql_for_instances with mode='.$mode."\n";
	print '-- SQL script to reinit password of osu.... users'."\n";
}
print '-- Extraction done from database '.$dbmaster->database_name."\n";
print '-- with '.$sql."\n";

while ($i < $num_rows) {
	$idofinstancefound = 0;

	$obj = $dbmaster->fetch_object($resql);
	if ($obj) {
		$idofinstancefound = $obj->rowid;
	}

	dol_include_once('/sellyoursaas/class/sellyoursaascontract.class.php');

	$object = new SellYourSaasContract($dbmaster);
	$result=0;
	if ($idofinstancefound) {
		$result=$object->fetch($idofinstancefound);
	}

	if ($result <= 0) {
		print "Error: Instance with id '".$idofinstancefound."' could not be loaded.\n";
		exit(-4);
	}

	$object->instance = $object->ref_customer;
	$object->username_os = $object->array_options['options_username_os'];
	$object->password_os = $object->array_options['options_password_os'];
	$object->username_db = $object->array_options['options_username_db'];
	$object->password_db = $object->array_options['options_password_db'];
	$object->database_db = $object->array_options['options_database_db'];
	$object->username_ro_db = $object->array_options['options_username_ro_db'];
	$object->password_ro_db = $object->array_options['options_password_ro_db'];
	$object->deployment_host = $object->array_options['options_deployment_host'];
	//$object->username_web = $object->thirdparty->email;
	//$object->password_web = $object->thirdparty->array_options['options_password'];

	if (empty($object->instance) && empty($object->username_os) && empty($object->password_os) && empty($object->database_db)) {
		print "Error: properties for instance ".$instance." was not registered into database.\n";
		exit(-5);
	}

	$dirdb = preg_replace('/_([a-zA-Z0-9]+)/', '', $object->database_db);

	$server = ($object->deployment_host ? $object->deployment_host : $object->array_options['options_hostname_os']);

	if (empty($object->array_options['options_username_os']) || empty($dirdb)) {
		print "Error: properties for instance ".$instance." are not registered completely (missing at least login or database name).\n";
		exit(-6);
	}

	$now = dol_now();

	if ($mode == 'dbcreate' || $mode == 'all') {
		print "CREATE DATABASE IF NOT EXISTS ".$dbmaster->escape($object->database_db).";\n";
	}
	if ($mode == 'dbusercreate' || $mode == 'all') {
		print "CREATE USER '".$dbmaster->escape($object->username_db)."'@'localhost' IDENTIFIED WITH mysql_native_password BY '".$dbmaster->escape($object->password_db)."';\n";
		print "CREATE USER '".$dbmaster->escape($object->username_db)."'@'%' IDENTIFIED WITH mysql_native_password BY '".$dbmaster->escape($object->password_db)."';\n";
		print "GRANT CREATE,CREATE TEMPORARY TABLES,CREATE VIEW,DROP,DELETE,INSERT,SELECT,UPDATE,ALTER,INDEX,LOCK TABLES,REFERENCES,SHOW VIEW ON ".$object->database_db.".* TO '".$dbmaster->escape($object->username_db)."'@'localhost';\n";
		print "GRANT CREATE,CREATE TEMPORARY TABLES,CREATE VIEW,DROP,DELETE,INSERT,SELECT,UPDATE,ALTER,INDEX,LOCK TABLES,REFERENCES,SHOW VIEW ON ".$object->database_db.".* TO '".$dbmaster->escape($object->username_db)."'@'%';\n";
	}
	if ($mode == 'dbuserresetpass' || $mode == 'all') {
		print "ALTER USER ".$dbmaster->escape($object->username_db)." IDENTIFIED WITH mysql_native_password BY '".$dbmaster->escape($object->password_db)."';\n";
	}

	if ($mode == 'sellyoursaasuserupdate') {
		print '-- Process instance '.$dbmaster->escape($object->instance).'--'."\n";
		print "UPDATE llx_contrat_extrafields SET password_os = '".$dbmaster->escape($object->password_os)."', password_db = '".$dbmaster->escape($object->password_db)."', password_ro_db = '".$dbmaster->escape($object->password_ro_db)."' WHERE username_db = '".$dbmaster->escape($object->username_db)."' AND database_db = '".$dbmaster->escape($object->database_db)."' AND password_os like 'Error%';\n";
	}

	$i++;
}

if ($num_rows > 0) {
	print '-- This script can be run on the deployment server with the command:'."\n";
	print '-- sudo mysql -h '.$server.' < sqlfile.sql'."\n";
} else {
	print '-- No record found'."\n";
}

exit(0);
